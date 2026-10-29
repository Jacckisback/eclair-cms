<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Connexion, double authentification, durée de session et permissions. */
final class Auth
{
    private const ABSOLUTE = 172800; // une session ne dure jamais plus de 48 h
    private const SUDO = 900;        // actions sensibles : mot de passe redemandé au-delà de 15 min
    private const DEVICE = 'eclair_dev';
    private const EDITOR_CAN = ['content', 'media', 'categories', 'menus', 'messages', 'redirects'];

    private static ?array $user = null;
    private static bool $checked = false;

    public static function user(): ?array
    {
        if (self::$checked) {
            return self::$user;
        }
        self::$checked = true;
        if (empty($_COOKIE[Session::NAME]) || !Settings::installed()) {
            return null;
        }
        Session::start();
        $uid = $_SESSION['uid'] ?? '';
        if (!is_string($uid) || $uid === '') {
            return null;
        }
        $user = Users::find($uid);
        $now = time();
        $idle = max(15, (int) Settings::get('security.idle', 240)) * 60;
        if (!$user
            || (int) ($user['ver'] ?? 1) !== (int) ($_SESSION['ver'] ?? 0)
            || $now - (int) ($_SESSION['last'] ?? 0) > $idle
            || $now - (int) ($_SESSION['born'] ?? 0) > self::ABSOLUTE
            || !hash_equals((string) ($_SESSION['ua'] ?? ''), self::fingerprint())) {
            $_SESSION = [];
            return null;
        }
        if ($now - (int) $_SESSION['last'] > 30) {
            $_SESSION['last'] = $now;
        }
        I18n::setLang((string) ($user['lang'] ?? 'fr'));
        return self::$user = $user;
    }

    public static function can(string $area): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] === 'admin' || in_array($area, self::EDITOR_CAN, true));
    }

    /**
     * Vérifie identifiant + mot de passe.
     * Renvoie « ok », « totp » (code requis), « invalid », « locked » ou « busy ».
     */
    public static function attempt(string $email, string $password): string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || strlen($email) > 254 || strlen($password) > 1024) {
            return 'invalid';
        }
        $user = Users::findByEmail($email);
        $keys = self::limits($email, $user);
        foreach ($keys as $key => [$max, $window, $block]) {
            if (!RateLimit::attempt($key, $max, $window, $block, $fresh)) {
                if ($fresh) {
                    Log::security('login_locked', ['email' => $email]);
                }
                return 'locked';
            }
        }
        $slot = self::slot();
        if ($slot === null) {
            foreach (array_keys($keys) as $key) {
                RateLimit::release($key);
            }
            return 'busy';
        }
        try {
            self::resetFromFile($email, $password);
            $user = Users::findByEmail($email);
            // Même coût de calcul que le compte existe ou non (pas d'énumération par le temps de réponse).
            $valid = password_verify($password, (string) ($user['pass'] ?? Keys::dummy())) && $user !== null;
            if ($valid) {
                $user = self::rehash($user, $password);
            }
        } finally {
            if (is_resource($slot)) {
                flock($slot, LOCK_UN);
                fclose($slot);
            }
        }
        if (!$valid || !$user) {
            Log::security('login_failed', ['email' => $email]);
            return 'invalid';
        }
        if (!empty($user['totp']['on'])) {
            Session::regenerate();
            $_SESSION['pending'] = [
                'uid' => $user['id'],
                'fp' => hash('sha256', (string) $user['pass']),
                'ver' => (int) ($user['ver'] ?? 1),
                'keys' => array_keys($keys),
                't' => time(),
                'n' => 0,
            ];
            return 'totp';
        }
        if (!self::login($user)) {
            return 'invalid';
        }
        foreach (array_keys($keys) as $key) {
            RateLimit::release($key);
        }
        return 'ok';
    }

    /** Deuxième étape : code TOTP ou code de secours, vérifié et consommé sous verrou (usage strictement unique). */
    public static function attemptTotp(string $code): string
    {
        if (!Session::resume()) {
            return 'expired';
        }
        $pending = $_SESSION['pending'] ?? null;
        if (!is_array($pending) || time() - (int) ($pending['t'] ?? 0) > 300) {
            unset($_SESSION['pending']);
            return 'expired';
        }
        $key = 'totp:' . $pending['uid'];
        if (!RateLimit::attempt($key, 10, 900, 900, $fresh)) {
            unset($_SESSION['pending']);
            if ($fresh) {
                self::alertTotp((string) $pending['uid']);
            }
            return 'locked';
        }
        $used = '';
        $user = Users::update((string) $pending['uid'], function (array $u) use ($pending, $code, &$used): ?array {
            if (!hash_equals((string) $pending['fp'], hash('sha256', (string) $u['pass']))
                || (int) ($u['ver'] ?? 1) !== (int) $pending['ver'] || empty($u['totp']['on'])) {
                $used = 'changed';
                return null;
            }
            $step = Totp::verify((string) $u['totp']['secret'], $code, (int) ($u['totp']['last'] ?? 0));
            if ($step !== null) {
                $u['totp']['last'] = $step;
                $used = 'totp';
                return $u;
            }
            $index = array_search(Totp::hashRecovery($code), (array) ($u['totp']['recovery'] ?? []), true);
            if ($index === false) {
                return null;
            }
            array_splice($u['totp']['recovery'], (int) $index, 1);
            $used = 'recovery';
            return $u;
        });
        if (!$user || $used === 'changed') {
            unset($_SESSION['pending']);
            return 'expired';
        }
        if ($used === '') {
            $_SESSION['pending']['n'] = (int) ($pending['n'] ?? 0) + 1;
            Log::security('totp_failed', ['email' => $user['email']]);
            if ($_SESSION['pending']['n'] >= 5) {
                unset($_SESSION['pending']);
                return 'expired';
            }
            return 'invalid';
        }
        if ($used === 'recovery') {
            Log::security('recovery_code_used', ['email' => $user['email']]);
        }
        unset($_SESSION['pending']);
        if (!self::login($user)) {
            return 'expired';
        }
        RateLimit::release($key);
        foreach ((array) ($pending['keys'] ?? []) as $k) {
            RateLimit::release((string) $k);
        }
        return 'ok';
    }

    /**
     * Ouvre la session. Refuse si le compte a changé depuis la vérification du mot de passe
     * (mot de passe, déconnexion générale, rôle, suppression) : une connexion lente ne peut rien annuler.
     */
    public static function login(array $user): bool
    {
        $now = time();
        $ip = Req::ip();
        $ok = false;
        $saved = Users::update((string) $user['id'], function (array $u) use ($user, $now, $ip, &$ok): ?array {
            if (!hash_equals((string) $u['pass'], (string) $user['pass'])
                || (int) ($u['ver'] ?? 1) !== (int) ($user['ver'] ?? 1)
                || $u['role'] !== $user['role']) {
                return null;
            }
            $u['last_login'] = $now;
            $u['last_ip'] = $ip;
            $ok = true;
            return $u;
        });
        if (!$ok || !$saved) {
            return false;
        }
        Session::regenerate();
        $_SESSION['uid'] = $saved['id'];
        $_SESSION['ver'] = (int) ($saved['ver'] ?? 1);
        $_SESSION['born'] = $now;
        $_SESSION['last'] = $now;
        $_SESSION['sudo'] = $now;
        $_SESSION['ua'] = self::fingerprint();
        unset($_SESSION['csrf']);
        self::$user = $saved;
        self::$checked = true;
        Log::security('login', ['email' => $saved['email']]);
        self::trustDevice($saved);
        // Simple indicateur (ni secret, ni adresse de l'administration) pour le bouton « Modifier » du site.
        setcookie('eclair_edit', '1', [
            'expires' => 0,
            'path' => Req::base() . '/',
            'secure' => Req::isHttps(),
            'httponly' => false,
            'samesite' => 'Strict',
        ]);
        return true;
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user) {
            Log::security('logout', ['email' => $user['email']]);
        }
        Session::end();
        setcookie('eclair_edit', '', ['expires' => time() - 3600, 'path' => Req::base() . '/']);
        self::$user = null;
    }

    /**
     * Met l'empreinte du mot de passe aux paramètres actuels, seulement si elle n'a pas changé entre-temps.
     * Renvoie le compte à jour, ou null si un changement concurrent l'a modifié.
     */
    private static function rehash(array $user, string $password): ?array
    {
        if (!Security::needsRehash((string) $user['pass'])) {
            return $user;
        }
        $old = (string) $user['pass'];
        $new = Security::hashPassword($password);
        $ok = false;
        $saved = Users::update((string) $user['id'], function (array $u) use ($old, $new, &$ok): ?array {
            if (!hash_equals((string) $u['pass'], $old)) {
                return null;
            }
            $ok = true;
            return ['pass' => $new] + $u;
        });
        return $ok ? $saved : null;
    }

    /** Déconnecte tous les appareils d'un compte (incrémente sa version de session). */
    public static function revokeAll(string $id): ?array
    {
        return Users::update($id, fn (array $u): array => ['ver' => (int) ($u['ver'] ?? 1) + 1] + $u);
    }

    /**
     * Actions sensibles (sauvegarde, gestion des comptes, adresse de l'administration…) :
     * mot de passe saisi il y a moins de 15 minutes, ou fourni avec la demande (champ « confirm_password »).
     */
    public static function confirmed(array $user): bool
    {
        if (self::fresh()) {
            return true;
        }
        $password = Req::input('confirm_password');
        if ($password === '' || strlen($password) > 1024 || !RateLimit::attempt('sudo:' . $user['id'], 10, 900, 900)) {
            return false;
        }
        if (!password_verify($password, (string) $user['pass'])) {
            Log::security('sudo_failed');
            return false;
        }
        RateLimit::release('sudo:' . $user['id']);
        $_SESSION['sudo'] = time();
        return true;
    }

    /** Le mot de passe a-t-il été saisi récemment ? (sinon les formulaires sensibles le redemandent) */
    public static function fresh(): bool
    {
        return Session::resume() && time() - (int) ($_SESSION['sudo'] ?? 0) < self::SUDO;
    }

    /**
     * Limites de tentatives. Un appareil où le compte s'est déjà connecté (cookie signé) a son propre
     * compteur : un attaquant ne peut pas bloquer le vrai propriétaire en ratant exprès. Ailleurs, la
     * limite porte sur le réseau (IPv4, ou /64 en IPv6) ET sur le compte visé.
     */
    private static function limits(string $email, ?array $user): array
    {
        $device = self::device($user);
        if ($device !== '') {
            return ['login-dev:' . $device => [10, 900, 900]];
        }
        return [
            'login-ip:' . Req::ipPrefix() => [10, 900, 900],
            'login-acct:' . $email => [30, 3600, 3600],
        ];
    }

    private static function device(?array $user): string
    {
        $raw = $_COOKIE[self::DEVICE] ?? '';
        if (!$user || !is_string($raw) || !preg_match('/^([a-f0-9]{32})\.([a-f0-9]{32})$/', $raw, $m)) {
            return '';
        }
        return hash_equals(self::deviceSig((string) $user['id'], $m[1]), $m[2]) ? $m[1] : '';
    }

    private static function trustDevice(array $user): void
    {
        $id = self::device($user) ?: bin2hex(random_bytes(16));
        setcookie(self::DEVICE, $id . '.' . self::deviceSig((string) $user['id'], $id), [
            'expires' => time() + 31536000,
            'path' => Req::base() . '/' . Settings::get('admin_path'),
            'secure' => Req::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private static function deviceSig(string $uid, string $id): string
    {
        return substr(Keys::sign('device|' . $uid . '|' . $id), 0, 32);
    }

    /**
     * Au plus deux vérifications de mot de passe simultanées : un afflux de tentatives
     * ne peut pas épuiser la mémoire d'un hébergement mutualisé. Attente maximale : 5 s.
     *
     * @return resource|true|null
     */
    private static function slot(): mixed
    {
        Store::ensureDir(ECLAIR_DATA);
        $until = microtime(true) + 5;
        do {
            for ($i = 0; $i < 2; $i++) {
                $h = @fopen(ECLAIR_DATA . '/.login-' . $i . '.lock', 'c');
                if ($h === false) {
                    return true;
                }
                if (flock($h, LOCK_EX | LOCK_NB)) {
                    return $h;
                }
                fclose($h);
            }
            usleep(50000);
        } while (microtime(true) < $until);
        return null;
    }

    /**
     * Secours par FTP : un fichier data/reset.php (ligne de protection, e-mail, nouveau mot de passe)
     * déposé il y a moins de 24 h. Il ne s'applique qu'à la connexion faite avec CET e-mail et CE mot de
     * passe : personne d'autre ne peut le déclencher ni savoir qu'il existe. La double authentification
     * du compte est désactivée et les autres sessions sont fermées.
     */
    private static function resetFromFile(string $email, string $password): void
    {
        $file = ECLAIR_DATA . '/reset.php';
        if (!is_file($file)) {
            return;
        }
        $raw = (string) @file_get_contents($file, false, null, 0, 4096);
        if (!preg_match('/^(?:\xEF\xBB\xBF)?<\?php\s+(?:exit|die)\s*(?:\(\s*\))?\s*;?\s*\?>[ \t]*\r?\n(.*)$/s', $raw, $m)
            || (int) filemtime($file) < time() - 86400) {
            @unlink($file);
            Log::security('ftp_reset_refused');
            return;
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $m[1]) ?: []), 'strlen'));
        if (count($lines) < 2 || mb_strtolower($lines[0]) !== $email || !hash_equals($lines[1], $password)) {
            return;
        }
        @unlink($file);
        $user = Users::findByEmail($email);
        if (!$user || Security::passwordIssues($password)) {
            Log::security('ftp_reset_refused', ['email' => $email]);
            return;
        }
        $hash = Security::hashPassword($password);
        Users::update($user['id'], fn (array $u): array => [
            'pass' => $hash,
            'ver' => (int) ($u['ver'] ?? 1) + 1,
            'must_change' => false,
            'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []],
        ] + $u);
        Log::security('ftp_reset', ['email' => $email]);
    }

    /** Trop de codes faux avec le bon mot de passe : le titulaire est prévenu (son mot de passe est connu). */
    private static function alertTotp(string $uid): void
    {
        $user = Users::find($uid);
        if (!$user) {
            return;
        }
        Log::security('totp_locked', ['email' => $user['email']]);
        Mail::send(
            (string) $user['email'],
            t('Alerte de sécurité : tentative de connexion bloquée'),
            t("Bonjour {name},\n\nQuelqu'un a saisi le bon mot de passe de ton compte, puis s'est trompé de code de double authentification à plusieurs reprises. La connexion est bloquée pour 15 minutes.\n\nSi ce n'était pas toi, change ton mot de passe dès maintenant et déconnecte tous tes appareils depuis ton profil.\n\nAdresse IP : {ip}", [
                'name' => (string) $user['name'],
                'ip' => Req::ip(),
            ]) . "\n"
        );
    }

    private static function fingerprint(): string
    {
        return substr(hash('sha256', Req::userAgent()), 0, 32);
    }
}
