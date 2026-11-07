<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Archive de sauvegarde du site (contenus, réglages, comptes, médias, thèmes et capacités du dossier site/) : téléchargée,
 * ou envoyée sur Google Drive ; restaurable sur cette installation ou sur une autre (restore()).
 */
final class Backup
{
    /** Jamais dans une sauvegarde : sessions, compteurs, conversations IA, secrets (coffre des clés), état des services. */
    private const SKIP = '~^data/(sessions/|limits/|ai/|reset\.php$|vault\.php$|drive\.php$|update\.php$)~';
    /** Les mêmes, désignés sur le disque (dossiers terminés par « / ») : comparés à l'emplacement réel de chaque fichier. */
    private const SECRET = ['sessions/', 'limits/', 'ai/', 'reset.php', 'vault.php', 'drive.php', 'update.php'];
    /** Dossiers de travail du site (installation en cours d'un thème) : jamais sauvegardés. */
    private const TRANSIENT = '~^site/.*/\.(stage|upload|old)-~';
    /** Médias jamais restaurés (mêmes extensions que celles refusées par media/.htaccess). */
    private const MEDIA_DENY = '~\.(php\d?|phtml|phar|inc|pl|py|cgi|sh|asp|aspx|jsp|s?html?|xht(ml)?|svgz?|xml|m?js|htaccess)$~i';
    private const RESTORE_MAX = 2147483648;

    /**
     * Dossier des données, sans barre finale : ECLAIR_DATA est recopié tel qu'écrit dans eclair-config.php
     * (« /home/compte/donnees/ » y est aussi valable que « /home/compte/donnees »).
     */
    public static function dataDir(): string
    {
        $dir = rtrim(ECLAIR_DATA, '/\\');
        return $dir !== '' ? $dir : ECLAIR_DATA;
    }

    /** Fichiers à sauvegarder : chemin dans l'archive => chemin sur le disque. */
    public static function files(): array
    {
        $out = [];
        $secret = self::secret();
        foreach (['data' => self::dataDir(), 'media' => ECLAIR_ROOT . '/media', 'site' => Themes::siteDir()] as $prefix => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $path => $info) {
                // Chemin relatif donné par l'itérateur lui-même : il ne dépend pas de l'écriture du dossier (barre finale, « .. »).
                $rel = $prefix . '/' . str_replace('\\', '/', $it->getSubPathname());
                if ($info->isFile() && !preg_match(self::SKIP, $rel) && !str_ends_with($rel, '.lock') && !str_contains($rel, '.tmp')
                    && !preg_match(self::TRANSIENT, $rel)
                    && !self::excluded((string) $path, $secret)) {
                    $out[$rel] = (string) $path;
                }
            }
        }
        ksort($out);
        return $out;
    }

    /** Emplacements réels des secrets et des états exclus (ceux qui existent) : fichiers, et dossiers suivis de « / ». */
    private static function secret(): array
    {
        $out = [];
        foreach (self::SECRET as $name) {
            $real = realpath(self::dataDir() . '/' . rtrim($name, '/'));
            if (is_string($real)) {
                $out[] = $real . (str_ends_with($name, '/') ? DIRECTORY_SEPARATOR : '');
            }
        }
        return $out;
    }

    /**
     * Exclusion qui échoue fermée : un fichier dont l'emplacement réel ne peut être établi, ou qui mène (lien symbolique,
     * autre écriture du chemin) à un secret exclu, n'entre jamais dans l'archive.
     */
    private static function excluded(string $path, array $secret): bool
    {
        $real = realpath($path);
        if (!is_string($real)) {
            return true;
        }
        foreach ($secret as $s) {
            if ($real === $s || (str_ends_with($s, DIRECTORY_SEPARATOR) && str_starts_with($real, $s))) {
                return true;
            }
        }
        return false;
    }

    /** Crée l'archive $file. Les images, déjà compressées, y sont rangées telles quelles (rapide, peu de calcul). */
    public static function create(string $file): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException(t('L’extension zip n’est pas disponible : copie les dossiers data et media par FTP.'));
        }
        $files = self::files();
        $size = array_sum(array_map(static fn (string $path): int => (int) @filesize($path), $files));
        $free = function_exists('disk_free_space') ? @disk_free_space(dirname($file)) : false;
        if (is_float($free) && $free < $size * 1.1 + 20000000) {
            throw new \RuntimeException(t('Espace disque insuffisant pour préparer la sauvegarde ({n} Mo nécessaires).', ['n' => (int) ceil($size * 1.1 / 1048576) + 20]));
        }
        Store::ensureDir(dirname($file));
        // Comptes et clés du site : l'archive (et le fichier temporaire de la bibliothèque zip) est créée privée, sous le
        // masque 0077, jamais lisible un instant par un autre compte avant ses droits définitifs.
        $mask = umask(0077);
        try {
            $zip = new \ZipArchive();
            if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException(t('Impossible de créer la sauvegarde.'));
            }
            foreach ($files as $rel => $path) {
                $zip->addFile($path, $rel);
                if (str_starts_with($rel, 'media/') && preg_match('~\.(jpe?g|png|gif|webp|avif|woff2)$~i', $rel)) {
                    $zip->setCompressionName($rel, \ZipArchive::CM_STORE);
                }
            }
            $zip->addFromString('LISEZ-MOI.txt', "Sauvegarde Éclair du " . date('Y-m-d H:i') . " (" . Settings::get('name') . ")\n\n"
                . "Pour restaurer : Sécurité › Restaurer une sauvegarde, dans l'administration (même sur une nouvelle installation).\n"
                . "Sans administration : remplace les dossiers data/, media/ et site/ de ton installation par ceux-ci (par FTP), puis vide le cache.\n"
                . "Les clés d'API (assistant IA) et la connexion Google Drive ne sont jamais sauvegardées : saisis-les de nouveau après une restauration.\n");
            $closed = $zip->close();
        } finally {
            umask($mask);
        }
        if (!$closed || !is_file($file)) {
            @unlink($file);
            throw new \RuntimeException(t('Impossible de créer la sauvegarde.'));
        }
        // Comptes et clés du site : l'archive n'est lisible que par PHP, comme les fichiers de données (0600).
        @chmod($file, 0600);
    }

    /**
     * Restaure une sauvegarde sur cette installation (la même, ou une autre). Tout est vérifié avant d'écrire quoi que ce
     * soit : seules des entrées data/, media/ et site/ ; chemins sûrs, aucun lien symbolique ; données au format d'Éclair ;
     * aucun script dans les médias ; jamais de secret, de session, de compteur ni d'état de service (ceux de l'installation
     * restent : une restauration ne réveille aucune session et ne remet aucune clé périmée). Les fichiers sont décompressés
     * dans cache/, puis mis en place un par un (renommage atomique) : une interruption laisse chaque fichier entier, et une
     * nouvelle restauration de la même archive termine le travail. Les fichiers absents de l'archive ne sont jamais
     * supprimés ; un contenu inconnu de l'index restauré repasse en brouillon (Content::reindex), jamais en ligne.
     * Renvoie ['ok' => true, 'files' => n, 'skipped' => [...]] ou ['ok' => false, 'error' => message].
     */
    public static function restore(string $file): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => t('L’extension zip n’est pas disponible : copie les dossiers data et media par FTP.')];
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return ['ok' => false, 'error' => t('Archive illisible : rien n’a été modifié.')];
        }
        $targets = ['data' => self::dataDir(), 'media' => ECLAIR_ROOT . '/media', 'site' => Themes::siteDir()];
        $plan = [];
        $skipped = [];
        $total = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                if ($name === '' || str_ends_with($name, '/') || $name === 'LISEZ-MOI.txt') {
                    continue;
                }
                $zip->getExternalAttributesIndex($i, $os, $attr);
                $prefix = explode('/', $name, 2)[0];
                $rel = (string) (explode('/', $name, 2)[1] ?? '');
                if (!isset($targets[$prefix]) || !Themes::safePath($rel) && !preg_match('~^([a-z0-9._-]+/)*\.htaccess$~', $rel) || str_contains($name, '..')
                    || ($os === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000)) {
                    return ['ok' => false, 'error' => t('Sauvegarde refusée : entrée inattendue ({f}). Rien n’a été modifié.', ['f' => mb_substr($name, 0, 120)])];
                }
                // Protections des dossiers (.htaccess, index.html) : celles de l'installation cible restent, jamais celles de l'archive.
                if (in_array(basename($rel), ['.htaccess', 'index.html'], true)) {
                    $skipped[] = $name;
                    continue;
                }
                if (preg_match(self::SKIP, $name) || ($prefix === 'data' && in_array(explode('/', $rel)[0] . (str_contains($rel, '/') ? '/' : ''), self::SECRET, true))
                    || str_ends_with($rel, '.lock') || str_contains($rel, '.tmp') || preg_match(self::TRANSIENT, $name)) {
                    $skipped[] = $name; // secret ou état propre à une installation : jamais restauré
                    continue;
                }
                if ($prefix === 'media' && preg_match(self::MEDIA_DENY, $rel) && !in_array(basename($rel), ['index.html'], true)) {
                    return ['ok' => false, 'error' => t('Sauvegarde refusée : entrée inattendue ({f}). Rien n’a été modifié.', ['f' => mb_substr($name, 0, 120)])];
                }
                $total += (int) ($stat['size'] ?? 0);
                if ($total > self::RESTORE_MAX) {
                    return ['ok' => false, 'error' => t('Sauvegarde trop volumineuse pour une restauration en ligne : copie les dossiers par FTP.')];
                }
                $plan[$i] = [$prefix, $rel];
            }
            if (!array_filter($plan, static fn (array $p): bool => $p[0] === 'data')) {
                return ['ok' => false, 'error' => t('Ce fichier n’est pas une sauvegarde d’Éclair (aucune donnée).')];
            }
            $free = function_exists('disk_free_space') ? @disk_free_space(ECLAIR_ROOT . '/cache') : false;
            if (is_float($free) && $free < $total * 2 + 20000000) {
                return ['ok' => false, 'error' => t('Espace disque insuffisant pour préparer la sauvegarde ({n} Mo nécessaires).', ['n' => (int) ceil($total * 2 / 1048576) + 20])];
            }
            // 1. Tout est décompressé et vérifié dans cache/ avant de toucher à l'installation.
            $stage = ECLAIR_ROOT . '/cache/restore-' . bin2hex(random_bytes(6));
            Store::ensureDir($stage);
            foreach ($plan as $i => [$prefix, $rel]) {
                $data = $zip->getFromIndex($i);
                if (!is_string($data) || ($prefix === 'data' && str_ends_with($rel, '.php') && !str_starts_with($data, Store::GUARD))) {
                    self::wipe($stage);
                    return ['ok' => false, 'error' => t('Sauvegarde refusée : entrée inattendue ({f}). Rien n’a été modifié.', ['f' => $prefix . '/' . $rel])];
                }
                if (!Disk::place($stage . '/' . $prefix . '/' . $rel, $data)) {
                    self::wipe($stage);
                    return ['ok' => false, 'error' => t('Décompression impossible : espace disque ou droits insuffisants. Rien n’a été modifié.')];
                }
            }
        } finally {
            $zip->close();
        }
        // Adresse du site et sa vérification : propres à l'hébergement de cette installation, gardées (une sauvegarde
        // restaurée ailleurs ne redirige jamais vers l'ancien domaine).
        $host = Settings::installed() ? ['url' => (string) Settings::get('url'), 'verified' => (string) Settings::get('security.verified')] : null;
        // 2. Mise en place, fichier par fichier, sous le verrou des données.
        $done = 0;
        $failed = '';
        Store::locked(static function () use ($plan, $stage, $targets, &$done, &$failed): void {
            foreach ($plan as [$prefix, $rel]) {
                $to = $targets[$prefix] . '/' . $rel;
                try {
                    Store::ensureDir(dirname($to));
                } catch (\Throwable) {
                    $failed = $prefix . '/' . $rel;
                    return;
                }
                if (!@rename($stage . '/' . $prefix . '/' . $rel, $to)) {
                    $failed = $prefix . '/' . $rel;
                    return;
                }
                Disk::mode($to, $prefix === 'data' ? 0600 : 0644);
                $done++;
            }
        });
        self::wipe($stage);
        Store::forget();
        Settings::forget();
        Content::forget();
        Themes::forget();
        if ($failed !== '') {
            Log::security('backup_restore_partial', ['done' => $done]);
            return ['ok' => false, 'error' => t('Restauration interrompue ({f}) : {n} fichiers remis en place. Vérifie l’espace disque et les droits, puis relance la même restauration pour terminer.', ['f' => $failed, 'n' => $done])];
        }
        if ($host !== null && $host['url'] !== '' && Settings::installed()) {
            Settings::save(['url' => $host['url'], 'security' => ['verified' => $host['verified']]]);
        }
        Content::reindex();
        Cache::purge();
        Log::security('backup_restored', ['files' => $done]);
        return ['ok' => true, 'files' => $done, 'skipped' => $skipped];
    }

    /** Suppression récursive d'un dossier de travail de la restauration (dans cache/ seulement). */
    private static function wipe(string $path): void
    {
        if (!str_starts_with($path, ECLAIR_ROOT . '/cache/restore-') || is_link($path)) {
            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::wipe($path . '/' . $entry);
                }
            }
            @rmdir($path);
        } elseif (file_exists($path)) {
            @unlink($path);
        }
    }

    /** Nom du fichier de sauvegarde : sauvegarde-<site>-<date>.zip */
    public static function name(bool $time = false): string
    {
        return 'sauvegarde-' . (slugify((string) Settings::get('name')) ?: 'site') . '-' . date($time ? 'Y-m-d-His' : 'Y-m-d') . '.zip';
    }
}
