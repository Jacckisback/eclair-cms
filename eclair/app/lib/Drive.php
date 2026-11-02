<?php
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

    /* ─────────────────────────────── État ─────────────────────────────── */

    /** Identifiants OAuth : ceux d'eclair-config.php (fork, tests) ou ceux de l'application Éclair livrés dans l'archive. */
    private static function client(): array
    {
        return [
            defined('ECLAIR_GOOGLE_CLIENT_ID') ? (string) ECLAIR_GOOGLE_CLIENT_ID : self::CLIENT_ID,
            defined('ECLAIR_GOOGLE_CLIENT_SECRET') ? (string) ECLAIR_GOOGLE_CLIENT_SECRET : self::CLIENT_SECRET,
        ];
    }

    /** Fonction proposée sur ce serveur ? (application Google configurée, extensions nécessaires) */
    public static function available(): bool
    {
        [$id, $secret] = self::client();
        return $id !== '' && $secret !== '' && function_exists('curl_init') && class_exists('ZipArchive') && Vault::available();
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

    /** Résumé pour l'administration. */
    public static function status(): array
    {
        $s = self::state();
        $job = is_array($s['job'] ?? null) ? $s['job'] : null;
        $pending = is_array($s['pending'] ?? null) && (int) $s['pending']['expires'] > time() ? $s['pending'] : null;
        return [
            'available' => self::available(),
            'connected' => self::connected(),
            'pending' => $pending,
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
        if (!self::connected() || !self::available()) {
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

    /** Demande un code à Google : l'administrateur le saisit sur google.com/device. */
    public static function start(): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => t('Sauvegarde Google Drive indisponible sur cette installation.')];
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
        ]], ['error']);
        return ['ok' => true];
    }

    /**
     * Google a-t-il reçu l'autorisation ? Renvoie connected, pending, denied, expired ou error. Respecte l'intervalle
     * imposé par Google entre deux questions (sinon il ralentit la cadence).
     */
    public static function poll(): string
    {
        $p = self::state()['pending'] ?? null;
        if (!is_array($p)) {
            return self::connected() ? 'connected' : 'expired';
        }
        if ((int) $p['expires'] <= time()) {
            self::save([], ['pending']);
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
            self::save(['pending' => ['interval' => $interval, 'next' => time() + $interval] + $p]);
            return 'pending';
        }
        if ($error === 'access_denied' || $error === 'expired_token') {
            self::save([], ['pending']);
            return $error === 'access_denied' ? 'denied' : 'expired';
        }
        if ($res['status'] !== 200 || empty($j['refresh_token']) || empty($j['access_token'])) {
            self::save(['pending' => ['next' => time() + (int) $p['interval']] + $p]);
            return 'error';
        }
        Vault::set(self::VAULT, (string) $j['refresh_token']);
        $token = (string) $j['access_token'];
        $about = self::http('GET', self::endpoint('api') . '/about?fields=user(emailAddress)', ['Authorization: Bearer ' . $token]);
        // Le dossier déjà créé est repris s'il appartient au même compte (sinon Google répond 404 et un nouveau est créé).
        self::save(['email' => (string) ($about['json']['user']['emailAddress'] ?? ''), 'since' => time()], ['pending', 'error']);
        self::folder($token);
        // Première connexion : sauvegarde hebdomadaire proposée d'office (modifiable aussitôt).
        if (!Settings::get('backup.chosen')) {
            Settings::save(['backup' => ['schedule' => 'weekly', 'keep' => self::keep(), 'chosen' => true]]);
        }
        Log::security('drive_connected');
        return 'connected';
    }

    public static function cancel(): void
    {
        self::save([], ['pending']);
    }

    /** Déconnexion : jeton révoqué chez Google (si possible) puis effacé ; les sauvegardes déjà envoyées restent sur le Drive. */
    public static function disconnect(): void
    {
        $refresh = Vault::get(self::VAULT);
        if ($refresh !== '') {
            self::http('POST', self::endpoint('revoke'), [], null, ['token' => $refresh], 10);
        }
        Vault::set(self::VAULT, '');
        $job = self::state()['job'] ?? null;
        if (is_array($job)) {
            @unlink(self::dir() . '/' . basename((string) $job['file']));
        }
        self::save([], ['pending', 'job', 'email', 'error', 'since']);
        Log::security('drive_disconnected');
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
        // Durée d'exécution imposée par l'hébergeur : on s'arrête avant elle (le travail reprendra au passage suivant).
        if (!(function_exists('set_time_limit') && @set_time_limit($budget + 60)) && ($max = (int) ini_get('max_execution_time')) > 0) {
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
