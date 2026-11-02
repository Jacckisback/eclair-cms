<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Mise à jour en un clic.
 *
 * Le canal (dist/latest.json sur GitHub) annonce la dernière version : numéro, adresse, taille et empreinte SHA-256 de
 * l'archive. Cette annonce est signée (Ed25519) avec la clé privée du mainteneur, qui n'est jamais sur GitHub : Éclair
 * refuse toute annonce dont la signature ne correspond pas à la clé publique ci-dessous. Un dépôt piraté ne suffit donc
 * pas à pousser du code sur les sites.
 *
 * L'installation ne remplace que le code (app/, assets/, index.php, et .htaccess s'il n'a pas été retouché) : contenus,
 * réglages, comptes, images et CSS personnalisé ne sont jamais touchés. Avant de toucher à quoi que ce soit, l'archive est
 * vérifiée (empreinte annoncée, manifeste, syntaxe PHP lue par la version de PHP du serveur). L'ancien code est gardé de
 * côté, et la mise à jour s'annule d'elle-même si le site ou l'administration ne répondent plus comme avant.
 */
final class Update
{
    private const CHANNEL = 'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/latest.json';
    /** Clé publique du mainteneur (Ed25519, base64) : seule une annonce signée par sa clé privée est acceptée. */
    private const KEY = 'H+pk0ph+U+csV7aoGTUZp8hsWBrjvtxavotdKqJuYEs=';
    private const HOSTS = ['raw.githubusercontent.com', 'github.com'];
    private const MAX = 8000000;
    private const UNPACKED = 40000000;
    private const EVERY = 43200;
    private const RETRY = 3600;
    /** Entrées de l'archive qu'une mise à jour peut poser : le code et les fichiers de protection des dossiers (jamais les données). */
    private const SHIPPED = '~^(app/|assets/|index\.php$|\.htaccess$|eclair-config\.exemple\.php$|(data|cache|cache/pages|media)/(\.htaccess|index\.html)$)~';
    private const CODE = ['app', 'assets'];

    public static function dir(): string
    {
        return ECLAIR_ROOT . '/cache/update';
    }

    /* ─────────────────────────────── Annonce ─────────────────────────────── */

    /** Dernière version annoncée et vérifiée ; relue sur GitHub si $refresh ou si la dernière lecture date de plus de 12 h. */
    public static function latest(bool $refresh = false): ?array
    {
        $state = self::state();
        if ($refresh || (int) ($state['next'] ?? 0) <= time()) {
            $state = self::refresh($state);
        }
        return is_array($state['release'] ?? null) ? $state['release'] : null;
    }

    /** Version plus récente déjà connue, sans appel réseau (menu, tableau de bord) ; null s'il n'y en a pas. */
    public static function pending(): ?array
    {
        $r = self::state()['release'] ?? null;
        return is_array($r) && version_compare((string) $r['version'], ECLAIR_VERSION, '>') ? $r : null;
    }

    /** Faut-il relire l'annonce en arrière-plan (vérification automatique active et dernière lecture de plus de 12 h) ? */
    public static function stale(): bool
    {
        return (bool) Settings::get('updates.check', true) && (int) (self::state()['next'] ?? 0) <= time();
    }

    /** État enregistré : annonce lue (release), date (checked), erreur, dernière mise à jour (last), dernière annulée (failed). */
    public static function state(): array
    {
        $s = Store::read('update', []);
        return is_array($s) ? $s : [];
    }

    /** Version installée sur le disque (manifeste), même si ce processus a démarré avec l'ancien code. */
    public static function installed(): string
    {
        return (string) (self::manifest(ECLAIR_ROOT . '/app/manifest.php')['version'] ?? ECLAIR_VERSION);
    }

    private static function refresh(array $state): array
    {
        $error = '';
        $release = self::fetch($error);
        $state = ['checked' => time(), 'next' => time() + ($release !== null ? self::EVERY : self::RETRY), 'error' => $error] + $state;
        if ($release !== null) {
            // L'annonce la plus récente fait foi, même si elle revient en arrière (version retirée par le mainteneur).
            $state['release'] = $release;
        }
        try {
            Store::write('update', $state);
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $state;
    }

    /** Lit et vérifie l'annonce. $error : network, signature, format ou sodium. */
    private static function fetch(string &$error): ?array
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            $error = 'sodium';
            return null;
        }
        $raw = self::get(self::channel(), 65536);
        $outer = $raw !== null ? json_decode($raw, true) : null;
        if (!is_array($outer) || !is_string($outer['payload'] ?? null)) {
            $error = 'network';
            return null;
        }
        if (!self::signed($outer['payload'], (string) ($outer['signature'] ?? ''))) {
            $error = 'signature';
            Log::security('update_rejected');
            return null;
        }
        $r = json_decode($outer['payload'], true);
        $semver = '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/';
        if (!is_array($r) || !preg_match($semver, (string) ($r['version'] ?? '')) || !preg_match('/^[a-f0-9]{64}$/', (string) ($r['sha256'] ?? ''))
            || !self::trusted((string) ($r['url'] ?? '')) || !is_int($r['size'] ?? null) || $r['size'] < 1000 || $r['size'] > self::MAX) {
            $error = 'format';
            return null;
        }
        return [
            'version' => $r['version'], 'url' => $r['url'], 'sha256' => $r['sha256'], 'size' => $r['size'],
            'php' => preg_match('/^\d\.\d{1,2}$/', (string) ($r['php'] ?? '')) ? $r['php'] : '8.1',
            // Version minimale pour la mise à jour en un clic : en dessous, la page demande une installation manuelle.
            'from' => preg_match($semver, (string) ($r['from'] ?? '')) ? $r['from'] : '',
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($r['date'] ?? '')) ? $r['date'] : '',
            'notes' => self::trusted((string) ($r['notes'] ?? '')) ? (string) $r['notes'] : '',
        ];
    }

    private static function channel(): string
    {
        return defined('ECLAIR_UPDATE_URL') ? (string) ECLAIR_UPDATE_URL : self::CHANNEL;
    }

    /** Signature Ed25519 de l'annonce par la clé du mainteneur. */
    private static function signed(string $payload, string $signature): bool
    {
        $key = base64_decode(defined('ECLAIR_UPDATE_KEY') ? (string) ECLAIR_UPDATE_KEY : self::KEY, true);
        $sig = base64_decode($signature, true);
        return function_exists('sodium_crypto_sign_verify_detached') && is_string($key) && strlen($key) === 32 && is_string($sig) && strlen($sig) === 64
            && sodium_crypto_sign_verify_detached($sig, $payload, $key);
    }

    /** Adresse permise : GitHub en HTTPS, ou le serveur du canal (canal de test ou de fork déclaré dans eclair-config.php). */
    private static function trusted(string $url): bool
    {
        $u = parse_url($url);
        $c = parse_url(self::channel());
        if (!is_array($u) || !isset($u['scheme'], $u['host']) || isset($u['user']) || isset($u['pass'])) {
            return false;
        }
        return ($u['scheme'] === 'https' && in_array($u['host'], self::HOSTS, true))
            || ($u['scheme'] === ($c['scheme'] ?? '') && $u['host'] === ($c['host'] ?? '') && ($u['port'] ?? null) === ($c['port'] ?? null));
    }

    /** Téléchargement borné, sans redirection ; dans un fichier si $file est donné ('' en cas de succès). Null en cas d'échec. */
    private static function get(string $url, int $max, ?string $file = null): ?string
    {
        if (!function_exists('curl_init') || !self::trusted($url)) {
            return null;
        }
        $out = $file !== null ? @fopen($file, 'wb') : null;
        if ($out === false) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => $file !== null ? 120 : 15, CURLOPT_FAILONERROR => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (str_starts_with(self::channel(), 'http://') ? CURLPROTO_HTTP : 0),
            CURLOPT_USERAGENT => 'Eclair/' . ECLAIR_VERSION, CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static fn ($h, $total, $done): int => $done > $max ? 1 : 0,
        ] + ($out !== null ? [CURLOPT_FILE => $out] : [CURLOPT_RETURNTRANSFER => true]));
        $body = curl_exec($ch);
        $ok = $body !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        if ($out !== null) {
            fclose($out);
        }
        return $ok ? ($out !== null ? '' : (string) $body) : null;
    }

    /* ─────────────────────────────── Installation ─────────────────────────────── */

    /** Ce qui empêche une mise à jour en un clic ici (vide si tout va bien), avant de toucher à quoi que ce soit. */
    public static function blockers(?array $release = null): array
    {
        $out = [];
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            $out[] = t('Extension PHP sodium absente : la signature des mises à jour ne peut pas être vérifiée.');
        }
        foreach (['zip' => class_exists('ZipArchive'), 'curl' => function_exists('curl_init')] as $ext => $ok) {
            if (!$ok) {
                $out[] = t('Extension PHP {n} absente : active-la dans le panneau PHP de ton hébergeur.', ['n' => $ext]);
            }
        }
        if ($release !== null && version_compare(PHP_VERSION, (string) $release['php'], '<')) {
            $out[] = t('Cette version demande PHP {v} ou plus récent : change la version de PHP chez ton hébergeur, puis reviens ici.', ['v' => $release['php']]);
        }
        if ($release !== null && ($release['from'] ?? '') !== '' && version_compare(self::installed(), (string) $release['from'], '<')) {
            $out[] = t('Cette version s’installe à la main depuis la tienne : suis le guide de mise à jour (FTP).');
        }
        if (is_dir(ECLAIR_ROOT . '/.git') || is_dir(dirname(ECLAIR_ROOT) . '/.git')) {
            $out[] = t('Installation de développement (dépôt Git) : mets-la à jour avec Git.');
        }
        foreach (self::CODE as $dir) {
            if (is_link(ECLAIR_ROOT . '/' . $dir) || !is_dir(ECLAIR_ROOT . '/' . $dir)) {
                $out[] = t('Installation particulière (dossier {p} déplacé ou lien symbolique) : mets-la à jour à la main.', ['p' => '/' . $dir]);
                break;
            }
        }
        foreach (['', '/app', '/assets', '/cache', '/index.php'] as $path) {
            if (!is_writable(ECLAIR_ROOT . $path)) {
                $out[] = t('Écriture impossible dans {p} : donne les droits d’écriture (755 pour les dossiers, 644 pour les fichiers) via ton FTP.', ['p' => $path === '' ? '/' : $path]);
                break;
            }
        }
        $free = function_exists('disk_free_space') ? @disk_free_space(ECLAIR_ROOT) : false;
        if (is_float($free) && $free < 30000000) {
            $out[] = t('Espace disque insuffisant : il faut au moins 30 Mo libres.');
        }
        return $out;
    }

    /** Fichiers du code retouchés ou ajoutés à la main : la mise à jour les remplace ou les écarte (ils restent dans la version gardée). */
    public static function modified(): array
    {
        $files = (array) (self::manifest(ECLAIR_ROOT . '/app/manifest.php')['files'] ?? []);
        $out = [];
        foreach ($files ? Integrity::scan(self::CODE) : [] as $path) {
            if ($path !== 'app/manifest.php' && !isset($files[$path . '.gz']) && (!isset($files[$path]) || !self::same(ECLAIR_ROOT . '/' . $path, (string) $files[$path]))) {
                $out[] = $path;
            }
        }
        return $out;
    }

    /**
     * Installe la version annoncée. Renvoie ['ok' => true, 'version', 'kept' => fichiers retouchés gardés, 'checked' => site
     * contrôlé après coup] ou ['ok' => false, 'error' => message] ; en cas d'échec, le site reste (ou revient) tel qu'avant.
     */
    public static function install(array $release): array
    {
        if ($blocked = self::blockers($release)) {
            return ['ok' => false, 'error' => $blocked[0]];
        }
        try {
            Store::ensureDir(self::dir());
            @file_put_contents(self::dir() . '/index.html', '');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => t('Écriture impossible dans {p} : donne les droits d’écriture (755 pour les dossiers, 644 pour les fichiers) via ton FTP.', ['p' => '/cache'])];
        }
        $lock = @fopen(self::dir() . '/lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'error' => t('Une mise à jour est déjà en cours.')];
        }
        extend_time(300);
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        try {
            // Sous verrou, la version sur le disque fait foi (un autre administrateur a pu mettre à jour entre-temps).
            if (!version_compare((string) $release['version'], self::installed(), '>')) {
                return ['ok' => false, 'error' => t('Éclair est déjà à jour (version {v}).', ['v' => self::installed()])];
            }
            return self::run($release);
        } catch (\Throwable $e) {
            Log::error($e);
            return ['ok' => false, 'error' => t('Erreur inattendue : rien n’a été modifié. Détail dans Sécurité › Dernières erreurs techniques.')];
        } finally {
            self::remove(self::dir() . '/stage');
            foreach (glob(self::dir() . '/*.zip') ?: [] as $zip) {
                @unlink($zip);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function run(array $r): array
    {
        $version = (string) $r['version'];
        $zip = self::dir() . '/eclair-' . $version . '.zip';
        $fetched = self::get((string) $r['url'], self::MAX, $zip) !== null;
        clearstatcache(true, $zip);
        if (!$fetched || filesize($zip) !== $r['size'] || !hash_equals((string) $r['sha256'], (string) hash_file('sha256', $zip))) {
            Log::security('update_download_failed', ['version' => $version]);
            return ['ok' => false, 'error' => t('Archive téléchargée incomplète ou altérée : rien n’a été modifié. Réessaie dans quelques minutes.')];
        }
        $stage = self::dir() . '/stage';
        self::remove($stage);
        $roots = [];
        if (($error = self::extract($zip, $stage, $version, $roots)) !== null) {
            Log::security('update_archive_rejected', ['version' => $version]);
            return ['ok' => false, 'error' => $error];
        }

        // Fichiers hors code (.htaccess, protections des dossiers, modèle de configuration) : remplacés seulement s'ils
        // n'ont pas été retouchés, d'après le manifeste de la version installée (ou s'ils manquent).
        $old = (array) (self::manifest(ECLAIR_ROOT . '/app/manifest.php')['files'] ?? []);
        $extras = [];
        $kept = [];
        foreach ($roots as $path => $content) {
            $here = ECLAIR_ROOT . '/' . $path;
            if ($path === 'index.php' || !is_dir(dirname($here))) {
                continue;
            }
            if (!is_file($here) || (isset($old[$path]) && self::same($here, (string) $old[$path]))) {
                $extras[] = $path;
            } elseif (!self::same($here, hash('sha256', $content))) {
                $kept[] = $path;
            }
        }

        // Classes encore utiles après l'échange : chargées maintenant, depuis l'ancien code.
        self::preload();

        // Ancien code mis de côté : dossiers déplacés d'un coup (renommage), fichiers de la racine copiés en .txt
        // (aucun point d'entrée exécutable dans le cache), comme les fichiers hors code que l'échange va remplacer.
        $prev = self::dir() . '/previous';
        self::remove($prev);
        Store::ensureDir($prev);
        if (!@copy(ECLAIR_ROOT . '/index.php', $prev . '/index.php.txt') || (is_file(ECLAIR_ROOT . '/.htaccess') && !@copy(ECLAIR_ROOT . '/.htaccess', $prev . '/htaccess.txt'))
            || !self::keep($extras, $prev)) {
            self::remove($prev);
            return ['ok' => false, 'error' => t('Impossible de mettre de côté la version actuelle : rien n’a été modifié.')];
        }
        try {
            return self::swap($r, $stage, $prev, $roots, $extras, $kept);
        } catch (\Throwable $e) {
            // Imprévu pendant l'échange : l'ancienne version est remise en place avant tout.
            self::restore();
            Log::error($e);
            return ['ok' => false, 'error' => t('Erreur inattendue pendant la mise à jour : Éclair est revenu à la version {v}. Détail dans Sécurité › Dernières erreurs techniques.', ['v' => ECLAIR_VERSION])];
        }
    }

    /** Échange du code, contrôle de santé, retour automatique si besoin. */
    private static function swap(array $r, string $stage, string $prev, array $roots, array $extras, array $kept): array
    {
        $version = (string) $r['version'];
        $before = self::health(ECLAIR_VERSION);
        foreach (self::CODE as $dir) {
            if (!@rename(ECLAIR_ROOT . '/' . $dir, $prev . '/' . $dir) || !@rename($stage . '/' . $dir, ECLAIR_ROOT . '/' . $dir)) {
                self::restore();
                return ['ok' => false, 'error' => t('Impossible de remplacer {p} : rien n’a été modifié.', ['p' => '/' . $dir])];
            }
        }
        if (!self::put(ECLAIR_ROOT . '/index.php', $roots['index.php'])) {
            self::restore();
            return ['ok' => false, 'error' => t('Impossible de remplacer {p} : rien n’a été modifié.', ['p' => '/index.php'])];
        }
        foreach ($extras as $path) {
            if (!self::put(ECLAIR_ROOT . '/' . $path, $roots[$path])) {
                $kept[] = $path;
            }
        }
        if (in_array('.htaccess', $kept, true)) {
            foreach (glob(self::dir() . '/htaccess-*.txt') ?: [] as $file) {
                @unlink($file);
            }
            @file_put_contents(self::dir() . '/htaccess-' . $version . '.txt', $roots['.htaccess']);
        }
        self::fresh();

        // Le site et l'administration répondent-ils comme avant, avec le nouveau code ? Sinon, retour immédiat.
        $after = self::health($version, $before, 8);
        $broken = array_keys(array_filter($before, static fn (int $code, string $k): bool => $code !== 0 && $after[$k] !== $code, ARRAY_FILTER_USE_BOTH));
        if ($broken) {
            // Un raté passager (réseau, serveur occupé) ne doit pas annuler une bonne mise à jour : second essai.
            usleep(1500000);
            $again = self::health($version, array_intersect_key($before, array_flip($broken)), 3);
            $broken = array_values(array_filter($broken, static fn (string $k): bool => $again[$k] !== $before[$k]));
        }
        if ($broken) {
            self::restore();
            Log::security('update_rolled_back', ['version' => $version, 'auto' => implode(',', $broken)]);
            self::remember(['failed' => ['version' => $version, 'date' => time()]]);
            return ['ok' => false, 'error' => t('La version {v} ne fonctionnait pas correctement sur ton serveur : Éclair est revenu tout seul à la version {old}. Rien n’est perdu.', ['v' => $version, 'old' => ECLAIR_VERSION])];
        }
        $checked = !in_array(0, $before, true);
        self::remember(['last' => ['from' => ECLAIR_VERSION, 'to' => $version, 'date' => time(), 'kept' => $kept, 'checked' => $checked]], ['failed']);
        Log::security('update_installed', ['from' => ECLAIR_VERSION, 'to' => $version]);
        return ['ok' => true, 'version' => $version, 'kept' => $kept, 'checked' => $checked];
    }

    /** Note l'issue d'une opération (un disque plein ne doit pas faire croire à un échec : le code, lui, a déjà changé). */
    private static function remember(array $patch, array $drop = []): void
    {
        try {
            Store::write('update', array_diff_key($patch + self::state(), array_flip($drop)));
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /** Version gardée lors de la dernière mise à jour (pour y revenir), ou ''. */
    public static function previous(): string
    {
        $prev = self::dir() . '/previous';
        return is_dir($prev . '/assets') && is_file($prev . '/index.php.txt') ? (string) (self::manifest($prev . '/app/manifest.php')['version'] ?? '') : '';
    }

    /** Revient à la version gardée lors de la dernière mise à jour. */
    public static function rollback(): array
    {
        $version = self::previous();
        if ($version === '') {
            return ['ok' => false, 'error' => t('Aucune version précédente à restaurer.')];
        }
        $lock = @fopen(self::dir() . '/lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'error' => t('Une mise à jour est déjà en cours.')];
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        try {
            self::preload();
            if (!self::restore()) {
                return ['ok' => false, 'error' => t('Retour incomplet : vérifie les droits d’écriture, puis réessaie (ou remets en place par FTP le contenu du dossier cache/update/previous).')];
            }
            self::remember(['last' => ['from' => ECLAIR_VERSION, 'to' => $version, 'date' => time(), 'rollback' => true]]);
            Log::security('update_rolled_back', ['version' => $version]);
            return ['ok' => true, 'version' => $version];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Fichiers hors code que l'échange va remplacer (protections des dossiers, modèle de configuration) : copiés en .txt
     * dans extra/, et ceux qu'il va ajouter notés dans extra/new.txt, pour qu'un retour remette tout comme avant.
     */
    private static function keep(array $extras, string $prev): bool
    {
        $added = [];
        try {
            foreach ($extras as $path) {
                if (!self::extra($path)) {
                    continue;
                }
                if (!is_file(ECLAIR_ROOT . '/' . $path)) {
                    $added[] = $path;
                    continue;
                }
                Store::ensureDir($prev . '/extra');
                if (!@copy(ECLAIR_ROOT . '/' . $path, $prev . '/extra/' . str_replace('/', '~', $path) . '.txt')) {
                    return false;
                }
            }
            if ($added) {
                Store::ensureDir($prev . '/extra');
                return @file_put_contents($prev . '/extra/new.txt', implode("\n", $added)) !== false;
            }
        } catch (\Throwable) {
            return false;
        }
        return true;
    }

    /** Fichier hors code qu'une mise à jour peut poser (ni le code, ni index.php, ni le .htaccess principal, gardés à part). */
    private static function extra(string $path): bool
    {
        return $path !== '' && $path !== 'index.php' && $path !== '.htaccess' && !str_starts_with($path, 'app/') && !str_starts_with($path, 'assets/')
            && !str_contains($path, '..') && preg_match(self::SHIPPED, $path) === 1;
    }

    /** Remet en place le code mis de côté (dossiers, index.php, .htaccess, fichiers hors code). */
    private static function restore(): bool
    {
        $prev = self::dir() . '/previous';
        $junk = self::dir() . '/discarded';
        self::remove($junk);
        try {
            Store::ensureDir($junk);
        } catch (\Throwable) {
            return false;
        }
        $ok = true;
        foreach (self::CODE as $dir) {
            if (!is_dir($prev . '/' . $dir)) {
                continue;
            }
            if (file_exists(ECLAIR_ROOT . '/' . $dir) && !@rename(ECLAIR_ROOT . '/' . $dir, $junk . '/' . $dir)) {
                $ok = false;
                continue;
            }
            $ok = @rename($prev . '/' . $dir, ECLAIR_ROOT . '/' . $dir) && $ok;
        }
        foreach (['index.php.txt' => 'index.php', 'htaccess.txt' => '.htaccess'] as $saved => $name) {
            $content = @file_get_contents($prev . '/' . $saved);
            if (is_string($content)) {
                $ok = self::put(ECLAIR_ROOT . '/' . $name, $content) && $ok;
            }
        }
        // Fichiers hors code remplacés par l'échange : remis comme avant ; ceux qu'il a ajoutés sont retirés.
        foreach (glob($prev . '/extra/*.txt') ?: [] as $file) {
            $path = str_replace('~', '/', substr(basename($file), 0, -4));
            if (basename($file) !== 'new.txt' && self::extra($path)) {
                $content = @file_get_contents($file);
                $ok = is_string($content) && self::put(ECLAIR_ROOT . '/' . $path, $content) && $ok;
            }
        }
        foreach (explode("\n", (string) @file_get_contents($prev . '/extra/new.txt')) as $path) {
            if (self::extra($path) && is_file(ECLAIR_ROOT . '/' . $path)) {
                $ok = @unlink(ECLAIR_ROOT . '/' . $path) && $ok;
            }
        }
        self::fresh();
        self::remove($junk);
        if ($ok) {
            self::remove($prev);
        }
        return $ok;
    }

    /**
     * Décompresse ce qu'une mise à jour peut poser, puis vérifie tout : version et empreinte de chaque fichier d'après le
     * manifeste de l'archive, aucun fichier en trop, syntaxe PHP acceptée par la version de PHP du serveur. Seuls app/ et
     * assets/ sont écrits (dans $stage) ; les autres fichiers restent en mémoire ($roots) : aucun point d'entrée dans le cache.
     */
    private static function extract(string $file, string $stage, string $version, array &$roots): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return t('Archive illisible : rien n’a été modifié.');
        }
        $staged = [];
        $total = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (preg_match('~(^|/)\.\.(/|$)|\\\\|[\x00-\x1f]|^/|^[a-z]:~i', $name)) {
                    return t('Archive refusée : chemin suspect.');
                }
                $rel = substr($name, 7);
                if (!str_starts_with($name, 'eclair/') || !preg_match(self::SHIPPED, $rel)) {
                    continue;
                }
                $total += (int) ($stat['size'] ?? 0);
                $data = $total <= self::UNPACKED ? $zip->getFromIndex($i) : false;
                if (!is_string($data)) {
                    return t('Archive refusée : contenu illisible ou trop volumineux.');
                }
                if (!str_starts_with($rel, 'app/') && !str_starts_with($rel, 'assets/')) {
                    $roots[$rel] = $data;
                    continue;
                }
                $target = $stage . '/' . $rel;
                if ((!is_dir(dirname($target)) && !@mkdir(dirname($target), 0755, true)) || @file_put_contents($target, $data) !== strlen($data)) {
                    return t('Décompression impossible : espace disque ou droits insuffisants. Rien n’a été modifié.');
                }
                @chmod($target, 0644);
                $staged[$rel] = true;
            }
        } finally {
            $zip->close();
        }
        // Dossiers lisibles par le serveur web (fichiers statiques d'assets/), quel que soit le masque de l'hébergeur.
        foreach (array_keys($staged) as $rel) {
            for ($dir = dirname($rel); $dir !== '.'; $dir = dirname($dir)) {
                @chmod($stage . '/' . $dir, 0755);
            }
        }
        $manifest = self::manifest($stage . '/app/manifest.php');
        $files = $manifest['files'] ?? null;
        if (($manifest['version'] ?? '') !== $version || !is_array($files) || !isset($files['index.php'], $files['app/bootstrap.php'], $roots['index.php'])) {
            return t('Archive refusée : elle ne correspond pas à la version annoncée.');
        }
        foreach ($files as $path => $hash) {
            $path = (string) $path;
            if (!preg_match(self::SHIPPED, $path)) {
                return t('Cette version s’installe à la main depuis la tienne : suis le guide de mise à jour (FTP).');
            }
            $ok = isset($roots[$path]) ? self::same(null, (string) $hash, $roots[$path]) : isset($staged[$path]) && self::same($stage . '/' . $path, (string) $hash);
            if (!$ok) {
                return t('Archive refusée : un fichier ne correspond pas à son empreinte ({f}).', ['f' => $path]);
            }
        }
        foreach (array_keys($staged + $roots) as $path) {
            if (!isset($files[$path]) && $path !== 'app/manifest.php') {
                return t('Archive refusée : fichier inattendu ({f}).', ['f' => $path]);
            }
        }
        // Lu par le PHP du serveur sans être exécuté : une syntaxe trop récente pour lui est refusée avant tout changement.
        if (function_exists('token_get_all')) {
            foreach (array_keys($staged + $roots) as $path) {
                if (!preg_match('~\.php(\.gz)?$~', $path)) {
                    continue;
                }
                $code = $roots[$path] ?? (string) file_get_contents($stage . '/' . $path);
                $code = str_ends_with($path, '.gz') ? (string) @gzdecode($code) : $code;
                try {
                    token_get_all($code, TOKEN_PARSE);
                } catch (\Throwable) {
                    return t('Cette version ne fonctionne pas avec PHP {v}, celui de ton serveur ({f}) : rien n’a été modifié.', ['v' => PHP_VERSION, 'f' => $path]);
                }
            }
        }
        return null;
    }

    /**
     * État du site vu de l'extérieur, par des appels du serveur à lui-même : l'installation répond-elle avec cette version
     * (preuve signée, plusieurs essais le temps que PHP recharge le code) ? Statut de la page d'accueil et de la connexion.
     * 0 : pas de réponse exploitable (appel du serveur à lui-même bloqué chez certains hébergeurs, page tronquée…).
     */
    private static function health(string $version, array $need = ['ping' => 1, 'home' => 1, 'admin' => 1], int $tries = 1): array
    {
        $url = rtrim((string) Settings::get('url'), '/');
        $out = ['ping' => 0, 'home' => 0, 'admin' => 0];
        if (!preg_match('~^https?://~', $url)) {
            return $out;
        }
        for ($i = 0; !empty($need['ping']) && $i < $tries && $out['ping'] === 0; $i++) {
            if ($i > 0) {
                usleep(700000);
            }
            $out['ping'] = Loopback::runs($url, $version) ? 1 : 0;
        }
        // Seul ce qui répondait avant est redemandé après (un hébergeur qui bloque ces appels ne fait pas attendre).
        $out['home'] = !empty($need['home']) ? self::status($url . '/') : 0;
        $out['admin'] = !empty($need['admin']) ? self::status($url . '/' . Settings::get('admin_path', 'admin') . '/login') : 0;
        return $out;
    }

    /** Statut HTTP d'une page affichée en entier (ou redirigée) ; 0 si elle est tronquée ou injoignable. */
    private static function status(string $url): int
    {
        if (!function_exists('curl_init')) {
            return 0;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Eclair/' . ECLAIR_VERSION . ' (verification)', CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static fn ($h, $total, $done): int => $done > 4000000 ? 1 : 0,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $complete = is_string($body) && ($code >= 300 && $code < 400 || stripos($body, '</html>') !== false);
        return $complete && $code < 500 ? $code : 0;
    }

    /** Charge depuis l'ancien code les classes dont la fin de la requête aura besoin (le dossier app/ va changer). */
    private static function preload(): void
    {
        foreach ([Loopback::class, Keys::class, Log::class, Store::class, Settings::class, Session::class, Http::class, Req::class, I18n::class, Auth::class, Admin::class] as $class) {
            class_exists($class);
        }
        t('Mises à jour');
    }

    /** Après un échange de code : pages en cache et paramètres du chemin rapide effacés (régénérés par le nouveau code), OPcache vidé. */
    private static function fresh(): void
    {
        foreach (glob(ECLAIR_ROOT . '/cache/pages/*') ?: [] as $file) {
            if (preg_match('~/[a-f0-9]{32}$~', $file)) {
                @unlink($file);
            }
        }
        @unlink(ECLAIR_ROOT . '/cache/runtime.php');
        if (function_exists('opcache_invalidate') && is_dir(ECLAIR_ROOT . '/app')) {
            @opcache_invalidate(ECLAIR_ROOT . '/index.php', true);
            try {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ECLAIR_ROOT . '/app', \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $path => $info) {
                    if ($info->isFile() && str_ends_with((string) $path, '.php')) {
                        @opcache_invalidate((string) $path, true);
                    }
                }
            } catch (\Throwable) {
                // dossier illisible : opcache_reset() ci-dessous suffit
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** Écrit un fichier de code de façon atomique, lisible par le serveur web (644). */
    private static function put(string $file, string $content): bool
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content) !== strlen($content)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    private static function manifest(string $file): array
    {
        $raw = @file_get_contents($file);
        $m = $raw === false ? null : json_decode(Store::strip($raw), true);
        return is_array($m) ? $m : [];
    }

    /** Fichier (ou contenu) identique à l'empreinte du manifeste : SHA-256 complet ou ses 128 premiers bits. */
    private static function same(?string $file, string $hash, ?string $content = null): bool
    {
        if (strlen($hash) < 32 || ($content === null && ($file === null || !is_file($file)))) {
            return false;
        }
        $sha = $content !== null ? hash('sha256', $content) : (string) hash_file('sha256', (string) $file);
        return hash_equals($hash, substr($sha, 0, strlen($hash)));
    }

    /** Suppression récursive, limitée au dossier des mises à jour. */
    private static function remove(string $path): void
    {
        if (!str_starts_with($path, self::dir() . '/') || str_contains($path, '..') || (!file_exists($path) && !is_link($path))) {
            return;
        }
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::remove($path . '/' . $entry);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}
