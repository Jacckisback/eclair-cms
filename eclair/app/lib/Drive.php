<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Sauvegardes sur Google Drive.
 *
 * Connexion sans rien recopier dans Éclair : l'administrateur ouvre google.com/device et y tape le code affiché (OAuth
 * « appareil »). Éclair ne demande que l'accès aux fichiers qu'il crée lui-même (drive.file) : il ne voit rien d'autre du
 * Drive. Le jeton de renouvellement est chiffré dans le coffre (Vault) et n'entre jamais dans une sauvegarde.
 *
 * Une sauvegarde est un travail qui avance par étapes courtes (archive, puis envoi par morceaux de 4 Mo, avec reprise) :
 * il continue à la requête suivante quand l'hébergeur limite la durée d'exécution. Il part à la demande, quand une
 * sauvegarde automatique est due et qu'un administrateur ouvre l'administration, ou par l'adresse de tâche planifiée.
 *
 * Le compte Google qui reçoit les sauvegardes est celui qui valide le code : la connexion est donc liée à la session de
 * l'administrateur qui l'a lancée après avoir confirmé son mot de passe (compte, version de session, empreinte de
 * l'identifiant de session). Seule cette session voit le code et peut terminer ou annuler la connexion, et seulement tant
 * que son mot de passe est récent. Le compte qui a connecté le Drive est noté : s'il est supprimé ou n'est plus
 * administrateur, les sauvegardes s'arrêtent et la connexion est retirée (un changement de mot de passe ne la coupe pas).
 */
final class Drive
{
    private const ENDPOINTS = [
        'device' => 'https://oauth2.googleapis.com/device/code',
        'token' => 'https://oauth2.googleapis.com/token',
        'revoke' => 'https://oauth2.googleapis.com/revoke',
        'api' => 'https://www.googleapis.com/drive/v3',
        'upload' => 'https://www.googleapis.com/upload/drive/v3',
    ];
    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';
    /** Application Google d'Éclair (client « TV et appareils à saisie limitée ») : identifiants posés dans l'archive par tools/build.php. */
    private const CLIENT_ID = '';
    private const CLIENT_SECRET = '';
    /** Morceaux de 4 Mio (multiple de 256 Kio, imposé par Google). */
    private const CHUNK = 4194304;
    public const SCHEDULES = ['off' => 0, 'daily' => 86400, 'weekly' => 604800];
    private const VAULT = 'google_drive';
    /**
     * Fenêtre de la connexion en cours : 15 minutes après son lancement, qui suit toujours la confirmation du mot de passe
     * (AdminDrive). Comptée depuis le lancement et non depuis la dernière saisie du mot de passe : une connexion lancée juste
     * avant la fin de ces 15 minutes ne s'éteint pas une minute plus tard (le code de Google, lui, vaut 30 minutes).
     */
    private const FLOW_TTL = 900;
    /** Application Google du site (ID et code secret), enregistrée dans l'administration quand l'archive n'en fournit pas. */
    private const APP = 'google_app';

    /* ─────────────────────────────── État ─────────────────────────────── */

    /**
     * Identifiants OAuth, par ordre de priorité : ceux d'eclair-config.php (fork, tests), l'application Google du site
     * (enregistrée dans l'administration), puis l'application Éclair livrée dans l'archive.
     */
    private static function client(): array
    {
        if (defined('ECLAIR_GOOGLE_CLIENT_ID')) {
            return [(string) ECLAIR_GOOGLE_CLIENT_ID, defined('ECLAIR_GOOGLE_CLIENT_SECRET') ? (string) ECLAIR_GOOGLE_CLIENT_SECRET : ''];
        }
        $own = json_decode(Vault::get(self::APP), true);
        if (is_array($own) && (string) ($own['id'] ?? '') !== '' && (string) ($own['secret'] ?? '') !== '') {
            return [(string) $own['id'], (string) $own['secret']];
        }
        return [self::CLIENT_ID, self::CLIENT_SECRET];
    }

    /** Extensions nécessaires présentes (curl, zip, chiffrement du coffre) : la fonction peut être proposée. */
    public static function ready(): bool
    {
        return function_exists('curl_init') && class_exists('ZipArchive') && Vault::available();
    }

    /** Fonction utilisable sur ce serveur ? (application Google connue, extensions nécessaires) */
    public static function available(): bool
    {
        [$id, $secret] = self::client();
        return $id !== '' && $secret !== '' && self::ready();
    }

    /** Application Google d'Éclair livrée dans l'archive (sans elle, chaque site enregistre la sienne). */
    public static function builtIn(): bool
    {
        return self::CLIENT_ID !== '' && self::CLIENT_SECRET !== '';
    }

    /** ID client de l'application Google du site ('' si aucune) : affichable, contrairement au code secret. */
    public static function ownApp(): string
    {
        $own = json_decode(Vault::get(self::APP), true);
        return is_array($own) ? (string) ($own['id'] ?? '') : '';
    }

    /**
     * Enregistre l'application Google du site (code secret chiffré dans le coffre). Renvoie un message d'erreur, ou ''.
     * Un compte déjà connecté l'était avec une autre application : il est déconnecté, son jeton ne servirait plus.
     */
    public static function setApp(string $id, string $secret): string
    {
        $id = trim($id);
        $secret = trim($secret);
        if (!preg_match('~^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$~i', $id)) {
            return t('Cet ID client ne ressemble pas à ceux de Google : il se termine par .apps.googleusercontent.com.');
        }
        if (!preg_match('~^[A-Za-z0-9_-]{10,100}$~', $secret)) {
            return t('Code secret du client invalide : copie-le tel quel depuis la console Google.');
        }
        if (self::connected()) {
            self::disconnect();
        }
        try {
            Vault::set(self::APP, (string) json_encode(['id' => $id, 'secret' => $secret]));
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        self::save([], ['pending', 'error']);
        Log::security('drive_app_saved');
        return '';
    }

    /** Retire l'application Google du site (et déconnecte le compte qui l'utilisait). */
    public static function removeApp(): void
    {
        if (self::connected()) {
            self::disconnect();
        }
        Vault::set(self::APP, '');
        self::save([], ['pending', 'error']);
        Log::security('drive_app_removed');
    }

    public static function connected(): bool
    {
        return Vault::has(self::VAULT);
    }

    public static function state(): array
    {
        $s = Store::read('drive', []);
        return is_array($s) ? $s : [];
    }

    private static function save(array $patch, array $drop = []): array
    {
        return Store::locked(static function () use ($patch, $drop): array {
            Store::forget();
            $s = array_diff_key(array_replace(self::state(), $patch), array_flip($drop));
            Store::write('drive', $s);
            return $s;
        });
    }

    /**
     * Résumé pour l'administration. Le code de connexion n'est rendu qu'à la session qui l'a demandé, tant que son mot de
     * passe est récent ; les autres administrateurs voient seulement qu'une connexion est en cours (busy).
     */
    public static function status(): array
    {
        self::guard();
        $s = self::state();
        $job = is_array($s['job'] ?? null) ? $s['job'] : null;
        $pending = is_array($s['pending'] ?? null) && (int) $s['pending']['expires'] > time() ? $s['pending'] : null;
        $user = Auth::user();
        $mine = $pending !== null && $user !== null && self::initiated($pending, $user);
        $by = Users::find(self::binder($s));
        return [
            'ready' => self::ready(),
            'available' => self::available(),
            'builtin' => self::builtIn(),
            'own' => self::ownApp(),
            'connected' => self::connected(),
            'pending' => $mine && self::live($pending) ? array_intersect_key($pending, array_flip(['code', 'url', 'expires', 'interval'])) : null,
            'busy' => $pending !== null && !$mine && is_array($pending['by'] ?? null),
            'by' => self::connected() && $by !== null ? (string) $by['name'] : '',
            // Connexion dont l'administrateur n'est pas connu (faite avant la 1.10.3), encore là seulement si les comptes
            // sont illisibles (guard n'a alors rien décidé) : la page invite à la refaire.
            'unbound' => self::connected() && self::binder($s) === '',
            'email' => (string) ($s['email'] ?? ''),
            'folder' => (string) ($s['folder_name'] ?? ''),
            'job' => $job !== null ? ['percent' => self::percent($job), 'started' => (int) $job['started']] : null,
            'last' => is_array($s['last'] ?? null) ? $s['last'] : null,
            'ok' => (int) ($s['ok'] ?? 0),
            'schedule' => self::schedule(),
            'keep' => self::keep(),
            'cron' => self::cronUrl(),
            'error' => (string) ($s['error'] ?? ''),
        ];
    }

    public static function schedule(): string
    {
        $s = (string) Settings::get('backup.schedule', 'off');
        return isset(self::SCHEDULES[$s]) ? $s : 'off';
    }

    public static function keep(): int
    {
        return max(1, min(50, (int) Settings::get('backup.keep', 8)));
    }

    /** Du travail à faire : une sauvegarde en cours, ou une sauvegarde automatique arrivée à échéance. */
    public static function due(): bool
    {
        if (self::guard() || !self::connected() || !self::available()) {
            return false;
        }
        $s = self::state();
        if (is_array($s['job'] ?? null)) {
            // Envoi en cours : il continue ; après un raté, pas avant dix minutes.
            return empty($s['error']) || time() >= (int) ($s['attempt'] ?? 0) + 600;
        }
        $every = self::SCHEDULES[self::schedule()];
        if ($every === 0) {
            return false;
        }
        // Une heure de marge : la sauvegarde quotidienne ne glisse pas d'un jour à l'autre. Après un échec, nouvel essai dans l'heure.
        return time() >= max((int) ($s['ok'] ?? 0) + $every - 3600, (int) ($s['attempt'] ?? 0) + 3600);
    }

    /* ─────────────────────────────── Connexion ─────────────────────────────── */

    /**
     * Demande un code à Google : l'administrateur le saisit sur google.com/device. Le flux est lié à sa session (compte,
     * version de session, empreinte de l'identifiant de session) : appelé seulement après la confirmation du mot de passe.
     */
    public static function start(array $user): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => t('Sauvegarde Google Drive indisponible sur cette installation.')];
        }
        $sid = self::sid();
        if ($sid === '') {
            return ['ok' => false, 'error' => t('Session expirée : réessaie.')];
        }
        [$id] = self::client();
        $res = self::http('POST', self::endpoint('device'), [], null, ['client_id' => $id, 'scope' => self::SCOPE]);
        $j = $res['json'];
        if ($res['status'] !== 200 || !isset($j['device_code'], $j['user_code'], $j['verification_url'])) {
            return ['ok' => false, 'error' => t('Google ne répond pas pour l’instant : réessaie dans quelques minutes.') . self::detail($res)];
        }
        $url = (string) $j['verification_url'];
        self::save(['pending' => [
            'device' => (string) $j['device_code'],
            'code' => (string) $j['user_code'],
            'url' => preg_match('~^https://~', $url) || self::local() ? $url : 'https://www.google.com/device',
            'expires' => time() + max(60, min(1800, (int) ($j['expires_in'] ?? 1800))),
            'interval' => max(5, min(60, (int) ($j['interval'] ?? 5))),
            'next' => 0,
            'by' => ['uid' => (string) $user['id'], 'ver' => (int) ($user['ver'] ?? 1), 'sid' => $sid, 'at' => time()],
        ]], ['error']);
        return ['ok' => true];
    }

    /**
     * Google a-t-il reçu l'autorisation ? Renvoie connected, pending, denied, expired, error, ou other (flux lancé par une
     * autre session : celle-ci n'y touche pas). Respecte l'intervalle imposé par Google entre deux questions (sinon il
     * ralentit la cadence). Seule la session qui a lancé le flux, dans les 15 minutes qui suivent, peut le terminer.
     */
    public static function poll(array $user): string
    {
        $p = self::state()['pending'] ?? null;
        if (!is_array($p)) {
            return self::connected() ? 'connected' : 'expired';
        }
        if (!self::initiated($p, $user)) {
            return 'other';
        }
        if ((int) $p['expires'] <= time() || !self::live($p)) {
            // Code échu, ou connexion lancée il y a plus de 15 minutes : le code n'est plus affiché, la connexion est à relancer.
            self::flow($p, null);
            return 'expired';
        }
        if ((int) $p['next'] > time()) {
            return 'pending';
        }
        [$id, $secret] = self::client();
        $res = self::http('POST', self::endpoint('token'), [], null, [
            'client_id' => $id, 'client_secret' => $secret, 'device_code' => (string) $p['device'],
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
        ]);
        $j = $res['json'];
        $error = (string) ($j['error'] ?? '');
        if ($error === 'authorization_pending' || $error === 'slow_down') {
            $interval = (int) $p['interval'] + ($error === 'slow_down' ? 5 : 0);
            self::flow($p, ['interval' => $interval, 'next' => time() + $interval]);
            return 'pending';
        }
        if ($error === 'access_denied' || $error === 'expired_token') {
            self::flow($p, null);
            return $error === 'access_denied' ? 'denied' : 'expired';
        }
        if ($res['status'] !== 200 || empty($j['refresh_token']) || empty($j['access_token'])) {
            self::flow($p, ['next' => time() + (int) $p['interval']]);
            return 'error';
        }
        $refresh = (string) $j['refresh_token'];
        // Le flux est retiré sous verrou s'il est toujours le même (ni annulé, ni remplacé, ni terminé par un autre passage),
        // puis l'administrateur qui l'a lancé est relu : même compte, toujours administrateur, même version de session.
        $claimed = self::flow($p, null);
        $now = Users::find((string) $user['id']);
        if (!$claimed || $now === null || ($now['role'] ?? '') !== 'admin' || (int) ($now['ver'] ?? 1) !== (int) ($p['by']['ver'] ?? -1)) {
            // Jeton refusé, révoqué aussitôt (sauf s'il est déjà celui de la connexion, terminée par un passage concurrent).
            if (!hash_equals(Vault::get(self::VAULT), $refresh)) {
                self::http('POST', self::endpoint('revoke'), [], null, ['token' => $refresh], 10);
            }
            return !$claimed && self::connected() ? 'connected' : 'expired';
        }
        $token = (string) $j['access_token'];
        $about = self::http('GET', self::endpoint('api') . '/about?fields=user(emailAddress)', ['Authorization: Bearer ' . $token]);
        $email = (string) ($about['json']['user']['emailAddress'] ?? '');
        // Un compte déjà connecté est remplacé : l'envoi en cours vers lui est abandonné, et son jeton révoqué s'il s'agit
        // d'un autre compte Google. Pour le même compte, rien n'est révoqué : chez Google, révoquer un jeton retire toute
        // l'autorisation, y compris le jeton qui vient d'être reçu.
        $old = Vault::get(self::VAULT);
        $same = $email !== '' && strcasecmp($email, (string) (self::state()['email'] ?? '')) === 0;
        self::drop(hash_equals($old, $refresh) || $same ? '' : $old);
        Vault::set(self::VAULT, $refresh);
        // Le dossier déjà créé est repris s'il appartient au même compte (sinon Google répond 404 et un nouveau est créé).
        // L'administrateur qui a connecté est noté avec la date de cette connexion : une connexion refaite autrement (par
        // une version d'avant la 1.10.3, après un retour en arrière) ne lui est jamais attribuée.
        $since = time();
        self::save(['email' => $email, 'since' => $since,
            'by' => ['uid' => (string) $now['id'], 'email' => (string) $now['email'], 'since' => $since]], ['pending', 'error']);
        self::folder($token);
        // Première connexion : sauvegarde hebdomadaire proposée d'office (modifiable aussitôt).
        if (!Settings::get('backup.chosen')) {
            Settings::save(['backup' => ['schedule' => 'weekly', 'keep' => self::keep(), 'chosen' => true]]);
        }
        Log::security('drive_connected');
        return 'connected';
    }

    /** Annule la connexion en cours : seulement depuis la session qui l'a lancée. */
    public static function cancel(array $user): void
    {
        $p = self::state()['pending'] ?? null;
        if (is_array($p) && self::initiated($p, $user)) {
            self::flow($p, null);
        }
    }

    /** Déconnexion : jeton révoqué chez Google (si possible) puis effacé ; les sauvegardes déjà envoyées restent sur le Drive. */
    public static function disconnect(): void
    {
        self::drop(Vault::get(self::VAULT));
        Vault::set(self::VAULT, '');
        self::save([], ['pending', 'job', 'email', 'error', 'since', 'by']);
        Log::security('drive_disconnected');
    }

    /** Jeton de renouvellement révoqué chez Google (si possible), envoi en cours et archive préparée abandonnés. */
    private static function drop(string $refresh): void
    {
        if ($refresh !== '') {
            self::http('POST', self::endpoint('revoke'), [], null, ['token' => $refresh], 10);
        }
        $job = self::state()['job'] ?? null;
        if (is_array($job)) {
            @unlink(self::dir() . '/' . basename((string) $job['file']));
        }
        self::save([], ['job']);
    }

    /**
     * Le compte qui a connecté le Drive n'existe plus, n'est plus administrateur ou n'est pas connu (connexion faite avant
     * la 1.10.3, ou refaite par une version antérieure) : la connexion est retirée (jeton révoqué, envoi abandonné),
     * l'événement est journalisé et la page Sécurité invite un administrateur à reconnecter, ce qui note son compte.
     * Renvoie true si c'est le cas : aucune archive ne part avant cette reconnexion.
     */
    private static function guard(): bool
    {
        $s = self::state();
        $uid = self::binder($s);
        // Comptes illisibles pour l'instant (data/users.php vide ou en cours de réparation) : rien n'est décidé.
        $all = Users::all();
        if ($all === [] || !self::connected()) {
            return false;
        }
        $owner = $uid !== '' ? ($all[$uid] ?? null) : null;
        if (is_array($owner) && ($owner['role'] ?? '') === 'admin') {
            return false;
        }
        self::drop(Vault::get(self::VAULT));
        Vault::set(self::VAULT, '');
        self::save(['error' => $uid === ''
            ? t('Google Drive a été déconnecté : cette connexion date d’avant la 1.10.3 et Éclair ne sait pas quel administrateur l’a établie. Un administrateur doit le reconnecter pour reprendre les sauvegardes.')
            : t('Google Drive a été déconnecté : le compte qui l’avait connecté a été supprimé ou n’est plus administrateur. Reconnecte-le pour reprendre les sauvegardes.')],
            ['pending', 'email', 'since', 'by']);
        Log::security('drive_unbound', ['email' => (string) ($s['by']['email'] ?? ''), 'binder' => (string) ($s['by']['email'] ?? '')]);
        return true;
    }

    /**
     * Met à jour ($patch) ou retire (null) le flux en attente, sous verrou et seulement s'il est toujours le même (même
     * code d'appareil) : un passage concurrent ne fait jamais réapparaître un flux déjà terminé ou remplacé.
     */
    private static function flow(array $p, ?array $patch): bool
    {
        return (bool) Store::locked(static function () use ($p, $patch): bool {
            Store::forget();
            $s = self::state();
            $now = $s['pending'] ?? null;
            if (!is_array($now) || !hash_equals((string) ($now['device'] ?? ''), (string) ($p['device'] ?? ''))) {
                return false;
            }
            if ($patch === null) {
                unset($s['pending']);
            } else {
                $s['pending'] = $patch + $now;
            }
            Store::write('drive', $s);
            return true;
        });
    }

    /** Flux encore dans sa fenêtre de 15 minutes depuis son lancement (un flux sans heure de lancement est échu). */
    private static function live(array $p): bool
    {
        return time() - (int) ($p['by']['at'] ?? 0) < self::FLOW_TTL;
    }

    /**
     * Administrateur qui a établi la connexion en place ('' s'il n'est pas connu) : noté avec la date de la connexion, il ne
     * compte que pour celle-ci.
     */
    private static function binder(array $s): string
    {
        $by = is_array($s['by'] ?? null) ? $s['by'] : [];
        return isset($by['since'], $s['since']) && (int) $by['since'] === (int) $s['since'] ? (string) ($by['uid'] ?? '') : '';
    }

    /** Flux lancé par cette session : même compte, même version de session, même identifiant de session. */
    private static function initiated(array $p, array $user): bool
    {
        $by = is_array($p['by'] ?? null) ? $p['by'] : [];
        $sid = self::sid();
        return $sid !== '' && hash_equals((string) ($by['sid'] ?? ''), $sid) && hash_equals((string) ($by['uid'] ?? ''), (string) $user['id'])
            && (int) ($by['ver'] ?? -1) === (int) ($user['ver'] ?? 1);
    }

    /** Empreinte de l'identifiant de la session en cours ('' sans session) : jamais l'identifiant lui-même. */
    private static function sid(): string
    {
        $id = Session::resume() ? (string) session_id() : '';
        return $id !== '' ? hash('sha256', 'drive-flow|' . $id) : '';
    }

    /* ─────────────────────────────── Sauvegarde ─────────────────────────────── */

    public static function dir(): string
    {
        return ECLAIR_ROOT . '/cache/backups';
    }

    /**
     * Fait avancer la sauvegarde pendant $budget secondes au plus : archive, puis envoi par morceaux. $now : en lancer une
     * même si aucune n'est due. Renvoie status (done, progress, idle, busy, error), percent, error.
     */
    public static function run(int $budget, bool $now = false): array
    {
        // Sauvegarde à la demande, automatique ou par la tâche planifiée : jamais vers un compte choisi par quelqu'un qui
        // n'est plus administrateur.
        if (self::guard()) {
            return ['status' => 'error', 'percent' => 0, 'error' => (string) (self::state()['error'] ?? '')];
        }
        if (!self::connected() || !self::available()) {
            return ['status' => 'idle', 'percent' => 0];
        }
        if (!$now && !self::due()) {
            return ['status' => 'idle', 'percent' => 0];
        }
        try {
            Store::ensureDir(self::dir());
            @file_put_contents(self::dir() . '/index.html', '');
        } catch (\Throwable) {
            return ['status' => 'error', 'percent' => 0, 'error' => t('Écriture impossible dans {p} : donne les droits d’écriture (755 pour les dossiers, 644 pour les fichiers) via ton FTP.', ['p' => '/cache'])];
        }
        $lock = @fopen(self::dir() . '/lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['status' => 'busy', 'percent' => self::percent(self::state()['job'] ?? null)];
        }
        // Un autre passage vient peut-être de terminer la sauvegarde due : on relit l'état sous verrou.
        Store::forget();
        if (!$now && !self::due()) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return ['status' => 'idle', 'percent' => 0];
        }
        // Durée d'exécution imposée par l'hébergeur : on s'arrête avant elle (le travail reprendra au passage suivant).
        if (!extend_time($budget + 60) && ($max = (int) ini_get('max_execution_time')) > 0) {
            $budget = max(5, min($budget, $max - 10));
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        try {
            return self::step(microtime(true) + $budget);
        } catch (\Throwable $e) {
            Log::error($e);
            return self::fail(t('Erreur inattendue : réessaie, ou télécharge la sauvegarde.'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function step(float $deadline): array
    {
        $s = self::state();
        $job = is_array($s['job'] ?? null) ? $s['job'] : null;
        // Travail abandonné depuis plus d'un jour (ou archive disparue) : on repart de zéro.
        if ($job !== null && ((int) $job['started'] < time() - 86400 || !is_file(self::dir() . '/' . basename((string) $job['file'])))) {
            @unlink(self::dir() . '/' . basename((string) $job['file']));
            $job = null;
        }
        // Accès au Drive vérifié avant de préparer l'archive (rien n'est préparé pour rien).
        $token = self::token();
        if ($token === null) {
            return self::connected()
                ? self::fail(t('Google ne répond pas pour l’instant : nouvel essai automatique.'), true)
                : self::fail(t('Accès à Google Drive retiré : reconnecte ton compte.'));
        }
        $folder = self::folder($token);
        if ($folder === null) {
            return self::fail(t('Impossible de créer le dossier de sauvegarde sur ton Drive.'), true);
        }
        if ($job === null) {
            foreach (glob(self::dir() . '/*.zip') ?: [] as $old) {
                @unlink($old);
            }
            $file = 'sauvegarde-' . bin2hex(random_bytes(8)) . '.zip';
            self::save(['attempt' => time()]);
            try {
                Backup::create(self::dir() . '/' . $file);
            } catch (\Throwable $e) {
                @unlink(self::dir() . '/' . $file);
                return self::fail($e->getMessage());
            }
            $job = ['file' => $file, 'name' => Backup::name(true), 'size' => (int) filesize(self::dir() . '/' . $file), 'uri' => '', 'offset' => 0, 'started' => time(), 'tries' => 0];
            self::save(['job' => $job]);
            if (microtime(true) > $deadline - 5) {
                return ['status' => 'progress', 'percent' => 0];
            }
        }
        $path = self::dir() . '/' . basename((string) $job['file']);
        $size = (int) $job['size'];

        // Session d'envoi : ouverte une fois, puis reprise là où Google s'est arrêté.
        if ($job['uri'] === '') {
            $res = self::http('POST', self::endpoint('upload') . '/files?uploadType=resumable&fields=id', [
                'Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: application/zip', 'X-Upload-Content-Length: ' . $size,
            ], (string) json_encode(['name' => (string) $job['name'], 'parents' => [$folder], 'mimeType' => 'application/zip'], JSON_UNESCAPED_UNICODE));
            $uri = (string) ($res['headers']['location'] ?? '');
            if ($res['status'] !== 200 || !self::trusted($uri)) {
                return self::retry($job, t('Google Drive refuse l’envoi pour l’instant.') . self::detail($res));
            }
            $job = ['uri' => $uri, 'offset' => 0] + $job;
            self::save(['job' => $job]);
        } else {
            $res = self::http('PUT', (string) $job['uri'], ['Content-Range: bytes */' . $size], '');
            if (in_array($res['status'], [200, 201], true)) {
                return self::finish($job, $token, $folder);
            }
            if ($res['status'] !== 308) {
                // Session expirée (une semaine) ou inconnue : nouvel envoi complet au prochain passage.
                return self::retry(['uri' => '', 'offset' => 0] + $job, t('Envoi interrompu : il reprend au prochain passage.'));
            }
            $job = ['offset' => self::received($res['headers']['range'] ?? '')] + $job;
        }

        $h = @fopen($path, 'rb');
        if ($h === false) {
            return self::retry(['uri' => '', 'offset' => 0] + $job, t('Archive de sauvegarde illisible.'));
        }
        try {
            while (microtime(true) < $deadline - 2) {
                $offset = (int) $job['offset'];
                $length = min(self::CHUNK, $size - $offset);
                fseek($h, $offset);
                $chunk = $length > 0 ? (string) fread($h, $length) : '';
                if (strlen($chunk) !== $length) {
                    return self::retry(['uri' => '', 'offset' => 0] + $job, t('Archive de sauvegarde illisible.'));
                }
                $range = $length > 0 ? 'bytes ' . $offset . '-' . ($offset + $length - 1) . '/' . $size : 'bytes */' . $size;
                $res = self::http('PUT', (string) $job['uri'], ['Content-Type: application/zip', 'Content-Range: ' . $range], $chunk, null, 300);
                if (in_array($res['status'], [200, 201], true)) {
                    return self::finish($job, $token, $folder);
                }
                if ($res['status'] !== 308) {
                    return self::retry($job, t('Envoi vers Google Drive interrompu : il reprendra au prochain passage.') . self::detail($res));
                }
                $job = ['offset' => self::received($res['headers']['range'] ?? ''), 'tries' => 0] + $job;
                self::save(['job' => $job]);
            }
        } finally {
            fclose($h);
        }
        return ['status' => 'progress', 'percent' => self::percent($job)];
    }

    /** Envoi terminé : archive locale effacée, anciennes sauvegardes du dossier mises à la corbeille au-delà de la limite. */
    private static function finish(array $job, string $token, string $folder): array
    {
        @unlink(self::dir() . '/' . basename((string) $job['file']));
        $q = "'" . $folder . "' in parents and trashed = false and mimeType = 'application/zip'";
        $list = self::http('GET', self::endpoint('api') . '/files?' . http_build_query([
            'q' => $q, 'orderBy' => 'createdTime desc', 'pageSize' => 100, 'fields' => 'files(id,name,createdTime)', 'spaces' => 'drive',
        ]), ['Authorization: Bearer ' . $token]);
        $removed = 0;
        foreach (array_slice((array) ($list['json']['files'] ?? []), self::keep()) as $file) {
            $id = (string) ($file['id'] ?? '');
            if (preg_match('/^[A-Za-z0-9_-]+$/', $id) && str_starts_with((string) ($file['name'] ?? ''), 'sauvegarde-')) {
                $res = self::http('PATCH', self::endpoint('api') . '/files/' . $id . '?fields=id', ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], '{"trashed":true}');
                $removed += $res['status'] === 200 ? 1 : 0;
            }
        }
        self::save(['ok' => time(), 'last' => ['date' => time(), 'ok' => true, 'size' => (int) $job['size'], 'name' => (string) $job['name'], 'removed' => $removed]], ['job', 'error']);
        Log::security('drive_backup', ['size' => (int) $job['size']]);
        return ['status' => 'done', 'percent' => 100];
    }

    /** Raté passager : le travail est gardé et reprend plus tard ; au cinquième raté de suite, il est abandonné. */
    private static function retry(array $job, string $error): array
    {
        $job['tries'] = (int) ($job['tries'] ?? 0) + 1;
        if ($job['tries'] >= 5) {
            @unlink(self::dir() . '/' . basename((string) $job['file']));
            self::save([], ['job']);
            return self::fail($error);
        }
        self::save(['job' => $job, 'error' => $error, 'attempt' => time()]);
        return ['status' => 'error', 'percent' => self::percent($job), 'error' => $error];
    }

    /** Échec : noté (affiché dans l'administration) ; une sauvegarde automatique retentera dans l'heure. */
    private static function fail(string $error, bool $keepJob = false): array
    {
        $s = self::state();
        if (!$keepJob && is_array($s['job'] ?? null)) {
            @unlink(self::dir() . '/' . basename((string) $s['job']['file']));
        }
        self::save(['attempt' => time(), 'error' => $error, 'last' => ['date' => time(), 'ok' => false, 'error' => $error]], $keepJob ? [] : ['job']);
        return ['status' => 'error', 'percent' => 0, 'error' => $error];
    }

    private static function percent(mixed $job): int
    {
        return is_array($job) && (int) ($job['size'] ?? 0) > 0 ? (int) min(99, floor((int) $job['offset'] / (int) $job['size'] * 100)) : 0;
    }

    /** Octets reçus par Google d'après l'en-tête « Range: bytes=0-N ». */
    private static function received(string $range): int
    {
        return preg_match('/bytes=0-(\d+)/', $range, $m) ? (int) $m[1] + 1 : 0;
    }

    /** Jeton d'accès (une heure) obtenu avec le jeton de renouvellement ; accès retiré par l'utilisateur : déconnexion. */
    private static function token(): ?string
    {
        $refresh = Vault::get(self::VAULT);
        if ($refresh === '') {
            return null;
        }
        [$id, $secret] = self::client();
        $res = self::http('POST', self::endpoint('token'), [], null, ['client_id' => $id, 'client_secret' => $secret, 'refresh_token' => $refresh, 'grant_type' => 'refresh_token']);
        if ($res['status'] === 200 && !empty($res['json']['access_token'])) {
            return (string) $res['json']['access_token'];
        }
        if (($res['json']['error'] ?? '') === 'invalid_grant') {
            // Accès retiré depuis le compte Google (ou jeton expiré) : déconnecté ; l'appelant note l'erreur et range le travail.
            Vault::set(self::VAULT, '');
            Log::security('drive_revoked');
        }
        return null;
    }

    /** Dossier « Éclair – <site> » sur le Drive (recréé s'il a été supprimé). */
    private static function folder(string $token): ?string
    {
        $id = (string) (self::state()['folder'] ?? '');
        if ($id !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            $res = self::http('GET', self::endpoint('api') . '/files/' . $id . '?fields=id,trashed', ['Authorization: Bearer ' . $token]);
            if ($res['status'] === 200 && empty($res['json']['trashed'])) {
                return $id;
            }
            if ($res['status'] !== 404 && $res['status'] !== 200) {
                return null;
            }
        }
        $name = 'Éclair – ' . (mb_substr(trim((string) Settings::get('name')), 0, 80) ?: 'site');
        $res = self::http('POST', self::endpoint('api') . '/files?fields=id', ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8'],
            (string) json_encode(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'], JSON_UNESCAPED_UNICODE));
        $id = (string) ($res['json']['id'] ?? '');
        if ($res['status'] !== 200 || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            return null;
        }
        self::save(['folder' => $id, 'folder_name' => $name]);
        return $id;
    }

    /* ─────────────────────────────── Tâche planifiée ─────────────────────────────── */

    /** Adresse à appeler régulièrement (cron de l'hébergeur, cron-job.org…) : elle ne lance une sauvegarde que si elle est due. */
    public static function cronUrl(): string
    {
        return absolute_url('/_eclair/backup/' . self::cronKey());
    }

    private static function cronKey(): string
    {
        return substr(Keys::sign('drive-cron'), 0, 32);
    }

    /** /_eclair/backup/<clé> : fait avancer la sauvegarde due, puis répond en texte (rien d'autre n'est révélé). */
    public static function cron(string $key): never
    {
        $headers = ['Content-Type: text/plain; charset=utf-8', 'Cache-Control: no-store', 'X-Robots-Tag: noindex'];
        if (!hash_equals(self::cronKey(), $key) || !RateLimit::attempt('drive-cron:' . Req::ip(), 30, 3600, 3600)) {
            Http::send('Not found', 404, $headers);
        }
        $r = self::run(25);
        Http::send($r['status'] . ($r['status'] === 'progress' ? ' ' . $r['percent'] . '%' : '') . "\n", 200, $headers);
    }

    /* ─────────────────────────────── Transport ─────────────────────────────── */

    private static function endpoint(string $name): string
    {
        $custom = defined('ECLAIR_GOOGLE_ENDPOINTS') ? (array) constant('ECLAIR_GOOGLE_ENDPOINTS') : [];
        return rtrim((string) ($custom[$name] ?? self::ENDPOINTS[$name]), '/');
    }

    /** Points d'accès remplacés (tests) : HTTP local permis. */
    private static function local(): bool
    {
        return defined('ECLAIR_GOOGLE_ENDPOINTS');
    }

    /** Adresse de session d'envoi renvoyée par Google : en HTTPS chez Google (ou sur le serveur de test). */
    private static function trusted(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        if (self::local() && $host === (string) parse_url(self::endpoint('upload'), PHP_URL_HOST)) {
            return true;
        }
        return $scheme === 'https' && ($host === 'www.googleapis.com' || str_ends_with($host, '.googleapis.com'));
    }

    private static function detail(array $res): string
    {
        $message = (string) ($res['json']['error']['message'] ?? $res['json']['error_description'] ?? '');
        return $message !== '' ? ' (' . mb_substr($message, 0, 160) . ')' : '';
    }

    /** Requête vers Google : statut (0 si injoignable), en-têtes en minuscules, réponse JSON décodée. */
    private static function http(string $method, string $url, array $headers = [], ?string $body = null, ?array $form = null, int $timeout = 30): array
    {
        $head = [];
        $ch = curl_init($url);
        if ($form !== null) {
            $body = http_build_query($form);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $timeout, CURLOPT_USERAGENT => 'Eclair/' . ECLAIR_VERSION,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (self::local() ? CURLPROTO_HTTP : 0),
            CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: application/json', 'Expect:']),
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$head): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $head[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ] + ($body !== null ? [CURLOPT_POSTFIELDS => $body] : []));
        $raw = curl_exec($ch);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return ['status' => $raw === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $head, 'json' => is_array($json) ? $json : []];
    }
}
