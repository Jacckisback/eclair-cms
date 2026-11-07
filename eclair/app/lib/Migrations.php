<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Migrations des données et de l'installation : identifiées, idempotentes, journalisées dans data/migrations.php.
 *
 * Lancées par l'application (jamais par le chemin rapide du cache) au premier passage d'une nouvelle version, sous verrou,
 * l'une après l'autre. Une migration ne fait qu'ajouter (dossiers, fichiers, réglages) : un retour à la version précédente
 * du code n'efface rien de ce qui a été créé depuis, et ne réveille ni session ni secret. Une migration ratée est notée,
 * retentée au plus tôt dix minutes plus tard, et n'empêche jamais le site de s'afficher.
 */
final class Migrations
{
    /** Identifiant => méthode, dans l'ordre d'exécution. Un identifiant ne change jamais une fois publié. */
    public const ALL = [
        '2026-11-site-dir' => 'siteDir',
        '2026-11-legacy-theme' => 'legacyTheme',
        '2026-11-wayback-extension' => 'waybackExtension',
        '2026-11-drive-extension' => 'driveExtension',
    ];
    private const RETRY = 600;

    /**
     * Anciens dossiers du code où chercher des gabarits et des actifs personnalisés : copie gardée par la mise à jour en un
     * clic (1.10 à 1.14), et dossier rangé à la main avant une mise à jour par FTP (docs/UPGRADE-2.md).
     */
    public const LEGACY = [
        ['app' => 'cache/update/previous/app', 'assets' => 'cache/update/previous/assets'],
        ['app' => 'cache/legacy-app', 'assets' => 'cache/legacy-assets'],
    ];

    /** Lance les migrations en attente (rapide quand tout est fait : une lecture de fichier). */
    public static function run(): void
    {
        $state = self::state();
        if (($state['code'] ?? '') === ECLAIR_VERSION && !array_diff_key(self::ALL, (array) ($state['done'] ?? []))) {
            return;
        }
        if ((int) ($state['retry'] ?? 0) > time()) {
            return;
        }
        $lock = @fopen(Backup::dataDir() . '/migrations.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return; // une autre requête s'en occupe
        }
        try {
            $state = self::state(); // relu sous verrou
            foreach (self::ALL as $id => $method) {
                if (isset($state['done'][$id])) {
                    continue;
                }
                try {
                    $result = self::$method();
                    $state['done'][$id] = ['date' => time(), 'version' => ECLAIR_VERSION, 'result' => $result];
                    unset($state['failed'][$id]);
                    Log::security('migration_done', ['id' => $id]);
                } catch (\Throwable $e) {
                    Log::error($e);
                    $state['failed'][$id] = ['date' => time(), 'error' => mb_substr($e->getMessage(), 0, 300)];
                    $state['retry'] = time() + self::RETRY;
                    self::save($state);
                    return;
                }
            }
            // Nouvelle version du noyau : les fonctions livrées que le site a ajoutées passent à la version livrée.
            if (($state['code'] ?? '') !== ECLAIR_VERSION) {
                try {
                    $synced = Extensions::syncBundled();
                    if ($synced) {
                        $state['bundled'][ECLAIR_VERSION] = $synced;
                    }
                } catch (\Throwable $e) {
                    Log::error($e);
                }
            }
            unset($state['retry']);
            $state['code'] = ECLAIR_VERSION;
            self::save($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function state(): array
    {
        $s = Store::read('migrations', []);
        return is_array($s) ? $s : [];
    }

    private static function save(array $state): void
    {
        try {
            Store::write('migrations', $state);
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /* ─────────────────────────────── Migrations ─────────────────────────────── */

    /** Dossier propre au site (site/themes, site/extensions, site/assets) et ses protections. */
    private static function siteDir(): array
    {
        Themes::ensure();
        return ['site' => true];
    }

    /** Gabarits, styles et actifs personnalisés d'une installation 1.x : rangés dans un thème du site, activé. */
    private static function legacyTheme(): array
    {
        return self::rescue();
    }

    /**
     * « Ancien site » (Wayback) est devenu une fonction optionnelle : les sites qui l'utilisaient (données présentes, ou
     * usage « Faire revivre un ancien site ») la reçoivent installée et activée ; les autres ne la chargent plus.
     */
    private static function waybackExtension(): array
    {
        $data = Store::read('wayback', []);
        $used = is_array($data) && (!empty($data['urls']) || (int) ($data['scanned'] ?? 0) > 0);
        if (!$used && (string) Settings::get('usage') !== 'revival') {
            return ['installed' => false];
        }
        Themes::ensure();
        $r = Extensions::add('wayback');
        if (!$r['ok']) {
            throw new \RuntimeException('Fonction « Ancien site » non installée : ' . (string) $r['error']);
        }
        return ['installed' => true];
    }

    /**
     * Sauvegardes Google Drive devenues une fonction optionnelle : les sites qui avaient commencé à la configurer
     * (data/drive.php : application, connexion ou envoi) la reçoivent installée et activée, leur réglage intact.
     */
    private static function driveExtension(): array
    {
        $file = Backup::dataDir() . '/drive.php';
        if (!is_file($file)) {
            return ['installed' => false];
        }
        Themes::ensure();
        $r = Extensions::add('drive');
        if (!$r['ok']) {
            throw new \RuntimeException('Fonction « Sauvegardes Google Drive » non installée : ' . (string) $r['error']);
        }
        return ['installed' => true];
    }

    /* ─────────────────────────────── Sauvetage 1.x ─────────────────────────────── */

    /**
     * Cherche dans les anciens dossiers du code (LEGACY) les fichiers du thème modifiés ou ajoutés par rapport au manifeste
     * de leur version, et les range dans le thème « legacy » du site (site/themes/legacy/), activé s'il est valide et
     * qu'aucun autre thème du site n'est actif. Une copie brute de tout ce qui est trouvé est gardée avant tout
     * (site/themes/.legacy-originals/) : rien ne dépend ensuite de la copie de l'ancien code, qu'une mise à jour peut purger.
     * Idempotent : une source déjà sauvée (même empreinte) n'est pas traitée deux fois. Appelé aussi par Update avant de
     * remplacer la copie gardée de l'ancien code.
     */
    public static function rescue(): array
    {
        $report = ['sources' => [], 'templates' => [], 'styles' => [], 'assets' => [], 'ignored' => [], 'activated' => false, 'errors' => []];
        $state = self::state();
        $rescued = (array) ($state['rescued'] ?? []);
        foreach (self::LEGACY as $source) {
            $app = ECLAIR_ROOT . '/' . $source['app'];
            if (!is_dir($app . '/theme') || is_link($app)) {
                continue;
            }
            $manifest = self::manifest($app . '/manifest.php');
            $found = self::customized($app, ECLAIR_ROOT . '/' . $source['assets'], $manifest);
            if ($found === []) {
                continue;
            }
            $print = hash('sha256', (string) json_encode(array_map(static fn (string $f): string => (string) hash_file('sha256', $f), $found)));
            if (isset($rescued[$print])) {
                continue;
            }
            $version = (string) ($manifest['version'] ?? 'inconnue');
            Themes::ensure();
            // 1. Copie brute, avant toute transformation.
            $originals = Themes::dir() . '/.legacy-originals/' . preg_replace('~[^0-9a-z.]~', '', $version) . '-' . substr($print, 0, 12);
            foreach ($found as $rel => $file) {
                $content = Disk::read($file);
                if (!is_string($content) || !Disk::place($originals . '/' . $rel, $content)) {
                    throw new \RuntimeException('Copie des personnalisations impossible (espace disque ou droits) : ' . $rel);
                }
            }
            // 2. Thème du site construit à partir de ces fichiers.
            $report['sources'][] = ['from' => $source['app'], 'version' => $version, 'files' => count($found), 'verified' => $manifest !== []];
            $report = self::build($found, $version, $manifest !== [], $report);
            $rescued[$print] = ['date' => time(), 'from' => $source['app'], 'version' => $version, 'originals' => substr($originals, strlen(ECLAIR_ROOT) + 1)];
        }
        if ($rescued !== ($state['rescued'] ?? [])) {
            $state = self::state();
            $state['rescued'] = $rescued;
            $state['report'] = $report;
            self::save($state);
            Log::security('legacy_theme_rescued', ['templates' => count($report['templates']), 'activated' => $report['activated'] ? 1 : 0]);
        }
        return $report;
    }

    /**
     * Fichiers personnalisés : chemin relatif à la racine d'Éclair (app/theme/…, assets/site/…) => fichier sur le disque.
     * Sans manifeste (version inconnue), un fichier compte comme personnalisé s'il diffère de celui du noyau actuel.
     */
    private static function customized(string $app, string $assets, array $manifest): array
    {
        $files = (array) ($manifest['files'] ?? []);
        $out = [];
        foreach (['app/theme' => $app . '/theme', 'assets/site' => $assets . '/site'] as $prefix => $dir) {
            if (!is_dir($dir) || is_link($dir)) {
                continue;
            }
            foreach (array_keys(Themes::files($dir)) as $sub) {
                $rel = $prefix . '/' . $sub;
                $file = $dir . '/' . $sub;
                if ($sub === 'index.html' || str_ends_with($sub, '.gz')) {
                    continue;
                }
                $sha = (string) hash_file('sha256', $file);
                $shipped = isset($files[$rel]) && hash_equals((string) $files[$rel], substr($sha, 0, strlen((string) $files[$rel])));
                $current = ECLAIR_ROOT . '/' . $rel;
                $same = is_file($current) && hash_equals($sha, (string) hash_file('sha256', $current));
                if (($files !== [] && !$shipped) || ($files === [] && !$same)) {
                    $out[$rel] = $file;
                }
            }
        }
        ksort($out);
        return $out;
    }

    /** Range les fichiers personnalisés dans site/themes/legacy (nouvelle version du thème si elle existe déjà), puis l'active. */
    private static function build(array $found, string $version, bool $verified, array $report): array
    {
        $stage = Themes::dir() . '/.stage-legacy-' . bin2hex(random_bytes(4));
        $templates = [];
        $overrides = [];
        $assets = [];
        try {
            foreach ($found as $rel => $file) {
                $content = (string) Disk::read($file);
                $sub = substr($rel, strlen(str_starts_with($rel, 'app/theme/') ? 'app/theme/' : 'assets/'));
                if (str_starts_with($rel, 'app/theme/') && str_ends_with($sub, '.php')) {
                    $name = substr($sub, 0, -4);
                    if (in_array($name, Themes::TEMPLATES, true)) {
                        $templates[] = $name;
                        $target = 'templates/' . $name . '.php';
                    } else {
                        $target = 'templates/parts/' . preg_replace('~[^a-z0-9-]~', '-', strtolower($name)) . '.php';
                        $report['ignored'][] = $rel . ' → ' . $target;
                    }
                    $report['templates'][] = $rel;
                } elseif (str_starts_with($rel, 'app/theme/') && in_array($sub, Themes::OVERRIDES, true)) {
                    $target = 'styles/' . str_replace('/', '-', $sub);
                    $overrides[$sub] = $target;
                    $report['styles'][] = $rel;
                } elseif (str_starts_with($rel, 'app/theme/')) {
                    $target = 'extra/' . $sub;
                    $report['ignored'][] = $rel;
                } else {
                    // Actif du site (assets/site/…) : servi de nouveau à son ancienne adresse (Site::legacyAsset).
                    $target = 'assets/' . $sub;
                    if (isset(Themes::ASSET_TYPES[strtolower(pathinfo($sub, PATHINFO_EXTENSION))])) {
                        $assets[] = $target;
                        $report['assets'][] = $rel;
                    } else {
                        $report['ignored'][] = $rel;
                    }
                }
                if (!Disk::place($stage . '/' . $target, $content)) {
                    throw new \RuntimeException('Écriture impossible : ' . $target);
                }
            }
            $existing = Themes::read('legacy');
            $next = $existing !== null ? self::bump($existing['version']) : '1.0.0';
            $manifest = [
                'schema' => Themes::SCHEMA, 'id' => 'legacy', 'name' => 'Personnalisations ' . $version, 'version' => $next,
                'contract' => 1, 'parent' => 'eclair', 'legacy' => true,
                'description' => 'Gabarits, styles et fichiers modifiés dans l’ancienne installation (' . $version . ($verified ? '' : ', sans manifeste') . '), conservés lors du passage à la nouvelle architecture.',
                'templates' => array_values(array_unique($templates)), 'styles' => [], 'overrides' => (object) $overrides, 'assets' => $assets,
            ];
            if (!Disk::place($stage . '/theme.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                throw new \RuntimeException('Écriture impossible : theme.json');
            }
            $installed = Themes::install($stage);
        } finally {
            Themes::removeTree($stage);
        }
        if (!$installed['ok']) {
            $report['errors'][] = (string) $installed['error'];
            return $report;
        }
        $active = (string) (Themes::state()['active']['id'] ?? '');
        if (($active === '' || $active === 'legacy') && ($templates !== [] || $overrides !== [] || $assets !== [])) {
            $done = Themes::activate('legacy');
            $report['activated'] = $done['ok'];
            if (!$done['ok']) {
                $report['errors'][] = (string) $done['error'];
            }
        }
        return $report;
    }

    private static function bump(string $version): string
    {
        $p = array_map('intval', explode('.', $version)) + [0, 0, 0];
        return $p[0] . '.' . ($p[1] + 1) . '.0';
    }

    private static function manifest(string $file): array
    {
        $raw = Disk::read($file);
        $m = $raw === false ? null : json_decode(Store::strip($raw), true);
        return is_array($m) && is_array($m['files'] ?? null) ? $m : [];
    }
}
