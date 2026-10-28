<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Connexion, double authentification, durée de session et permissions. */
final class Auth
{
    private const ABSOLUTE = 172800; // une session ne dure jamais plus de 48 h
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
     * Renvoie « ok », « totp » (code requis), « invalid » ou « locked ».
     */
    public static function attempt(string $email, string $password): string
    {
        $ipKey = 'login-ip:' . Req::ip();
        $userKey = 'login-user:' . mb_strtolower(trim($email));
        if (RateLimit::blocked($ipKey) || RateLimit::blocked($userKey)) {
            Log::security('login_blocked', ['email' => $email]);
            return 'locked';
        }
        $user = Users::findByEmail($email);
        // Même coût de calcul que le compte existe ou non (pas d'énumération par le temps de réponse).
        $valid = password_verify($password, $user['pass'] ?? Keys::get('dummy')) && $user !== null;
        if (!$valid) {
            $locked = RateLimit::hit($ipKey, 8, 900);
            $locked = RateLimit::hit($userKey, 20, 3600, 1800) || $locked;
            Log::security($locked ? 'login_locked' : 'login_failed', ['email' => $email]);
            return $locked ? 'locked' : 'invalid';
        }
        if (password_needs_rehash((string) $user['pass'], Security::algo())) {
            $user['pass'] = Security::hashPassword($password);
            $user = Users::save($user);
        }
        RateLimit::clear($userKey);
        if (!empty($user['totp']['on'])) {
            Session::regenerate();
            $_SESSION['pending'] = ['uid' => $user['id'], 't' => time(), 'n' => 0];
            return 'totp';
        }
        self::login($user);
        return 'ok';
    }

    /** Deuxième étape : code TOTP ou code de secours. */
    public static function attemptTotp(string $code): string
    {
        Session::start();
        $pending = $_SESSION['pending'] ?? null;
        if (!is_array($pending) || time() - (int) $pending['t'] > 300) {
            unset($_SESSION['pending']);
            return 'expired';
        }
        $user = Users::find((string) $pending['uid']);
        if (!$user) {
            return 'expired';
        }
        $totp = $user['totp'];
        $step = Totp::verify((string) $totp['secret'], $code, (int) ($totp['last'] ?? 0));
        if ($step !== null) {
            $user['totp']['last'] = $step;
        } else {
            $hash = Totp::hashRecovery($code);
            $index = array_search($hash, (array) ($totp['recovery'] ?? []), true);
            if ($index === false) {
                $_SESSION['pending']['n'] = (int) $pending['n'] + 1;
                RateLimit::hit('login-ip:' . Req::ip(), 8, 900);
                Log::security('totp_failed', ['email' => $user['email']]);
                if ($_SESSION['pending']['n'] >= 5) {
                    unset($_SESSION['pending']);
                    return 'expired';
                }
                return 'invalid';
            }
            array_splice($user['totp']['recovery'], (int) $index, 1);
            Log::security('recovery_code_used', ['email' => $user['email']]);
        }
        $user = Users::save($user);
        unset($_SESSION['pending']);
        self::login($user);
        return 'ok';
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $now = time();
        $_SESSION['uid'] = $user['id'];
        $_SESSION['ver'] = (int) ($user['ver'] ?? 1);
        $_SESSION['born'] = $now;
        $_SESSION['last'] = $now;
        $_SESSION['ua'] = self::fingerprint();
        unset($_SESSION['csrf']);
        $user['last_login'] = $now;
        $user['last_ip'] = Req::ip();
        Users::save($user);
        self::$user = $user;
        self::$checked = true;
        RateLimit::clear('login-ip:' . Req::ip());
        Log::security('login', ['email' => $user['email']]);
        // Indice (non secret) pour afficher le bouton « Modifier » sur le site public.
        setcookie('eclair_edit', Req::base() . '/' . Settings::get('admin_path'), [
            'expires' => 0,
            'path' => Req::base() . '/',
            'secure' => Req::isHttps(),
            'httponly' => false,
            'samesite' => 'Strict',
        ]);
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

    /** Déconnecte tous les appareils d'un compte (incrémente sa version de session). */
    public static function revokeAll(array $user): array
    {
        $user['ver'] = (int) ($user['ver'] ?? 1) + 1;
        return Users::save($user);
    }

    private static function fingerprint(): string
    {
        return substr(hash('sha256', Req::userAgent()), 0, 32);
    }
}
