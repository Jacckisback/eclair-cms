<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Thèmes propres au site (site/themes/<id>/), jamais touchés par une mise à jour du noyau.
 *
 * Le noyau livre le thème de base (app/theme/) ; un thème du site le complète : gabarits remplacés parmi ceux du contrat,
 * feuilles de style ajoutées, fichiers CSS du noyau remplacés (« overrides »), actifs déclarés publiés dans
 * site/assets/<id>/. Le contrat est versionné (CONTRACT) : un thème déclare celui qu'il suit, et un thème d'un contrat
 * non pris en charge est refusé avant activation. Tout ce qui touche à la sécurité, au SEO et à l'accès reste dans les
 * services communs : un gabarit reçoit des données déjà préparées ($v) et ne fabrique ni en-têtes, ni canonical, ni JSON-LD.
 *
 * Les gabarits PHP d'un thème s'exécutent avec les droits de l'application : un thème est du code de confiance, installé
 * par une personne autorisée. Ni le manifeste ni la validation ne sont un bac à sable ; ils évitent les erreurs, pas les
 * intentions malveillantes. Une erreur pendant le rendu d'un gabarit du site renvoie au gabarit du noyau (la page reste
 * servie) et le thème est signalé dans l'administration.
 */
final class Themes
{
    /** Contrat de rendu courant et contrats encore pris en charge (adaptateurs : voir docs/THEME-CONTRACT.md). */
    public const CONTRACT = 1;
    public const SUPPORTED = [1];
    public const SCHEMA = 'eclair-theme/1';
    /** Gabarits que le contrat 1 permet de remplacer, avec les variables qu'ils reçoivent (docs/THEME-CONTRACT.md). */
    public const TEMPLATES = ['layout', 'single', 'list', 'cards', 'product', 'products', '404', 'extension'];
    /** Fichiers CSS du noyau qu'un thème peut remplacer (le reste du CSS reste celui du noyau). */
    public const OVERRIDES = ['theme.css', 'motion.css', 'ads.css', 'themes/atelier.css', 'themes/boutique.css', 'themes/contraste.css', 'themes/editorial.css', 'themes/studio.css'];
    /** Actifs publiables (jamais de script serveur, jamais de HTML). */
    public const ASSET_TYPES = ['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'woff2' => 'font/woff2', 'ico' => 'image/x-icon'];
    public const MAX_FILES = 300;
    public const MAX_FILE = 2097152;
    public const MAX_TOTAL = 20971520;
    private const MAX_CSS = 262144;
    private const ID = '~^[a-z0-9][a-z0-9-]{0,39}$~';
    private const SEMVER = '~^\d{1,3}\.\d{1,3}\.\d{1,3}$~';

    /** Thème du site en cours de requête (false : pas encore lu). */
    private static array|false|null $active = false;
    /** Thème essayé dans l'aperçu (Réglages › Apparence) : '' pour le thème du noyau, null sans aperçu. */
    private static ?string $preview = null;
    private static ?string $css = null;

    /* ─────────────────────────────── Emplacements ─────────────────────────────── */

    /** Dossier propre au site (thèmes, capacités, actifs) : ECLAIR_SITE dans eclair-config.php, sinon site/ à côté d'index.php. */
    public static function siteDir(): string
    {
        return rtrim(defined('ECLAIR_SITE') ? (string) ECLAIR_SITE : ECLAIR_ROOT . '/site', '/\\');
    }

    public static function dir(): string
    {
        return self::siteDir() . '/themes';
    }

    public static function assetsDir(): string
    {
        return self::siteDir() . '/assets';
    }

    /**
     * Crée site/ et ses protections : les thèmes et les capacités ne sont jamais servis par le serveur web (gabarits PHP,
     * manifestes), les actifs publiés le sont, sans exécution de scripts. Les fichiers .htaccess posés ici appartiennent au
     * site : une mise à jour du noyau ne les écrit pas.
     */
    public static function ensure(): void
    {
        $deny = "# Dossier privé du site Éclair : aucun accès depuis le web.\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
        $static = "# Actifs publiés des thèmes et capacités : fichiers statiques seulement.\n<IfModule mod_headers.c>\n    Header set X-Content-Type-Options \"nosniff\"\n</IfModule>\n"
            . "<FilesMatch \"\\.(php\\d?|phtml|phar|inc|html?|shtml|cgi|pl|py)$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n</FilesMatch>\n";
        foreach (['' => null, '/themes' => $deny, '/extensions' => $deny, '/assets' => $static] as $sub => $htaccess) {
            $dir = self::siteDir() . $sub;
            Store::ensureDir($dir);
            Disk::mode($dir, 0755);
            if (!is_file($dir . '/index.html')) {
                Disk::write($dir . '/index.html', '');
            }
            if ($htaccess !== null && !is_file($dir . '/.htaccess')) {
                Disk::write($dir . '/.htaccess', $htaccess);
            }
        }
    }

    /* ─────────────────────────────── État ─────────────────────────────── */

    /** État enregistré : thème actif (id, version, date), précédent, et dernier thème en panne. */
    public static function state(): array
    {
        $s = Store::read('theme', []);
        return is_array($s) ? $s : [];
    }

    /**
     * Thème du site en vigueur pour cette requête (manifeste lu et son dossier), ou null : thème du noyau.
     * Une seule lecture de fichier par requête non servie par le cache ; aucun accès réseau, aucune écriture.
     */
    public static function active(): ?array
    {
        if (self::$active !== false) {
            return self::$active;
        }
        if (defined('ECLAIR_SAFE_THEME') && ECLAIR_SAFE_THEME) {
            return self::$active = null; // accès de secours (eclair-config.php) : thème du noyau
        }
        $id = self::$preview ?? (string) (self::state()['active']['id'] ?? '');
        $theme = $id !== '' ? self::read($id) : null;
        // Thème d'un contrat que ce noyau ne prend plus en charge : jamais exécuté, le site garde le thème du noyau.
        return self::$active = $theme !== null && in_array($theme['contract'], self::SUPPORTED, true) ? $theme : null;
    }

    /** Essaie un thème dans l'aperçu (jamais enregistré, jamais mis en cache) ; '' : thème du noyau. */
    public static function preview(string $id): void
    {
        self::$preview = $id === '' || self::read($id) !== null ? $id : '';
        self::$active = false;
        self::$css = null;
    }

    /** Oublie l'état lu (tests, après activation). */
    public static function forget(): void
    {
        self::$active = false;
        self::$css = null;
        Theme::forgetSite();
    }

    /* ─────────────────────────────── Lecture et validation ─────────────────────────────── */

    /** Manifeste d'un thème installé (site/themes/<id>/theme.json), normalisé, ou null s'il est absent ou illisible. */
    public static function read(string $id, ?string $dir = null): ?array
    {
        if (!preg_match(self::ID, $id)) {
            return null;
        }
        $dir ??= self::dir() . '/' . $id;
        if (is_link($dir) || !is_file($dir . '/theme.json')) {
            return null;
        }
        $m = json_decode((string) Disk::read($dir . '/theme.json'), true);
        if (!is_array($m) || ($m['id'] ?? null) !== $id) {
            return null;
        }
        return self::normalize($m) + ['dir' => $dir];
    }

    /** Manifeste ramené aux champs du contrat (valeurs de type sûr, listes bornées). */
    public static function normalize(array $m): array
    {
        $list = static fn (mixed $v): array => array_values(array_filter(array_slice(is_array($v) ? $v : [], 0, 200), 'is_string'));
        $overrides = [];
        foreach (is_array($m['overrides'] ?? null) ? $m['overrides'] : [] as $core => $file) {
            if (in_array($core, self::OVERRIDES, true) && is_string($file)) {
                $overrides[$core] = $file;
            }
        }
        return [
            'schema' => (string) ($m['schema'] ?? ''),
            'id' => (string) ($m['id'] ?? ''),
            'name' => mb_substr(is_string($m['name'] ?? null) ? $m['name'] : (string) ($m['id'] ?? ''), 0, 80),
            'version' => (string) ($m['version'] ?? ''),
            'contract' => is_int($m['contract'] ?? null) ? $m['contract'] : 0,
            'parent' => is_string($m['parent'] ?? null) ? $m['parent'] : 'eclair',
            'templates' => array_values(array_intersect($list($m['templates'] ?? []), self::TEMPLATES)),
            'styles' => $list($m['styles'] ?? []),
            'overrides' => $overrides,
            'assets' => $list($m['assets'] ?? []),
            'requires' => is_array($m['requires'] ?? null) ? $m['requires'] : [],
            'legacy' => !empty($m['legacy']),
            'description' => mb_substr(is_string($m['description'] ?? null) ? $m['description'] : '', 0, 300),
        ];
    }

    /**
     * Vérifie un thème avant installation ou activation : manifeste du contrat, compatibilité (contrat, noyau, PHP),
     * parent présent à la version exacte, fichiers déclarés présents dans le dossier (ni « .. », ni lien symbolique, ni
     * fichier en trop exécutable), syntaxe PHP acceptée par ce serveur, tailles bornées. Renvoie la liste des problèmes,
     * expliqués simplement (vide : le thème peut être activé).
     */
    public static function validate(string $dir, ?array &$manifest = null): array
    {
        $errors = [];
        if (is_link($dir) || !is_dir($dir)) {
            return [t('Dossier du thème introuvable.')];
        }
        $raw = json_decode((string) Disk::read($dir . '/theme.json'), true);
        if (!is_array($raw)) {
            return [t('Fichier theme.json absent ou illisible.')];
        }
        $m = self::normalize($raw);
        $manifest = $m;
        if ($m['schema'] !== self::SCHEMA) {
            $errors[] = t('Format de manifeste inconnu (attendu : {s}).', ['s' => self::SCHEMA]);
        }
        if (!preg_match(self::ID, $m['id'])) {
            $errors[] = t('Identifiant de thème invalide : lettres minuscules, chiffres et tirets (40 au plus).');
        }
        if (!preg_match(self::SEMVER, $m['version'])) {
            $errors[] = t('Version du thème invalide (format 1.0.0).');
        }
        if (!in_array($m['contract'], self::SUPPORTED, true)) {
            $errors[] = t('Ce thème suit le contrat de rendu {c}, que cette version d’Éclair ne prend pas en charge ({s}).', ['c' => $m['contract'], 's' => implode(', ', self::SUPPORTED)]);
        }
        $core = (string) ($m['requires']['eclair'] ?? '');
        // Copie de développement (version « dev », « test ») : la contrainte sur le noyau n'est pas vérifiable, elle est ignorée.
        $running = defined('ECLAIR_VERSION') ? (string) ECLAIR_VERSION : '0.0.0';
        if ($core !== '' && preg_match('~^\d~', $running) && !self::satisfies($running, $core)) {
            $errors[] = t('Ce thème demande Éclair {v}.', ['v' => $core]);
        }
        $php = (string) ($m['requires']['php'] ?? '');
        if ($php !== '' && !self::satisfies(PHP_VERSION, $php)) {
            $errors[] = t('Ce thème demande PHP {v}.', ['v' => $php]);
        }
        if ($m['parent'] !== 'eclair' && $m['parent'] !== 'eclair@' . self::CONTRACT) {
            // Parent : uniquement le thème de base du noyau dans le contrat 1. Un thème enfant d'un autre thème du site
            // embarque ce qu'il en reprend : jamais de parent remplacé en silence par une version qui change son apparence.
            $errors[] = t('Parent inconnu : {p}. Le contrat 1 accepte seulement « eclair » (thème de base du noyau).', ['p' => $m['parent']]);
        }
        foreach (array_keys(array_diff_key(array_flip(is_array($raw['templates'] ?? null) ? array_filter($raw['templates'], 'is_string') : []), array_flip(self::TEMPLATES))) as $name) {
            $errors[] = t('Gabarit non prévu par le contrat : {f}.', ['f' => (string) $name]);
        }
        foreach (array_keys(is_array($raw['overrides'] ?? null) ? $raw['overrides'] : []) as $core) {
            if (!in_array($core, self::OVERRIDES, true)) {
                $errors[] = t('Fichier du noyau non remplaçable : {f}.', ['f' => (string) $core]);
            }
        }

        // Fichiers du dossier : bornés, sans lien symbolique ni nom suspect.
        $files = self::files($dir, $problem);
        if ($problem !== '') {
            $errors[] = $problem;
            return $errors;
        }
        $declared = ['theme.json' => true];
        $need = static function (string $rel, string $kind) use (&$errors, &$declared, $files): void {
            if (!self::safePath($rel)) {
                $errors[] = t('Chemin refusé : {f}.', ['f' => $rel]);
                return;
            }
            $declared[$rel] = true;
            if (!isset($files[$rel])) {
                $errors[] = t('Fichier déclaré mais absent ({k}) : {f}.', ['k' => $kind, 'f' => $rel]);
            }
        };
        foreach ($m['templates'] as $name) {
            $need('templates/' . $name . '.php', 'gabarit');
        }
        foreach ($m['styles'] as $file) {
            $need($file, 'style');
            if (!str_ends_with($file, '.css')) {
                $errors[] = t('Une feuille de style doit finir par .css : {f}.', ['f' => $file]);
            }
        }
        foreach ($m['overrides'] as $file) {
            $need($file, 'style');
        }
        foreach ($m['assets'] as $file) {
            $need($file, 'actif');
            if (!isset(self::ASSET_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
                $errors[] = t('Type d’actif refusé : {f}.', ['f' => $file]);
            }
        }
        $css = 0;
        foreach (array_merge($m['styles'], array_values($m['overrides'])) as $file) {
            $css += $files[$file] ?? 0;
        }
        if ($css > self::MAX_CSS) {
            $errors[] = t('Feuilles de style trop lourdes ({n} Ko au plus, insérées dans chaque page).', ['n' => (int) (self::MAX_CSS / 1024)]);
        }
        foreach (array_keys($files) as $rel) {
            // Code serveur : seulement les gabarits déclarés et leurs morceaux (templates/parts/*.php), jamais ailleurs.
            if (preg_match('~\.(php\d?|phtml|phar|inc)$~i', $rel) && !isset($declared[$rel]) && !preg_match('~^templates/parts/[a-z0-9-]+\.php$~', $rel)) {
                $errors[] = t('Script non déclaré refusé : {f}.', ['f' => $rel]);
            }
            if (preg_match('~\.php$~', $rel) && function_exists('token_get_all')) {
                try {
                    token_get_all((string) Disk::read($dir . '/' . $rel), TOKEN_PARSE);
                } catch (\Throwable) {
                    $errors[] = t('Gabarit illisible par PHP {v} : {f}.', ['v' => PHP_VERSION, 'f' => $rel]);
                }
            }
        }
        return $errors;
    }

    /** Fichiers d'un dossier de thème ou de capacité : chemin relatif => taille. $problem explique un refus. */
    public static function files(string $dir, ?string &$problem = ''): array
    {
        $problem = '';
        $out = [];
        $total = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $path => $info) {
                $rel = str_replace('\\', '/', $it->getSubPathname());
                if ($info->isLink()) {
                    $problem = t('Lien symbolique refusé : {f}.', ['f' => $rel]);
                    return [];
                }
                if (!$info->isFile()) {
                    continue;
                }
                if (!self::safePath($rel) && $rel !== '.htaccess') {
                    $problem = t('Chemin refusé : {f}.', ['f' => $rel]);
                    return [];
                }
                $size = (int) $info->getSize();
                $total += $size;
                if (count($out) >= self::MAX_FILES || $size > self::MAX_FILE || $total > self::MAX_TOTAL) {
                    $problem = t('Paquet trop volumineux ({n} fichiers, 2 Mo par fichier, 20 Mo en tout au plus).', ['n' => self::MAX_FILES]);
                    return [];
                }
                $out[$rel] = $size;
            }
        } catch (\Throwable) {
            $problem = t('Dossier illisible.');
            return [];
        }
        ksort($out);
        return $out;
    }

    /** Chemin relatif sûr : segments simples, sans « .. », sans fichier caché, sans caractère de contrôle. */
    public static function safePath(string $rel): bool
    {
        return $rel !== '' && strlen($rel) <= 200 && (bool) preg_match('~^[A-Za-z0-9_-][A-Za-z0-9_.-]*(/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*$~', $rel)
            && !preg_match('~(^|/)\.\.?(/|$)~', $rel);
    }

    /** Version qui respecte une contrainte simple : « >=1.15.0 », « <2.0.0 », plusieurs séparées par une espace. */
    public static function satisfies(string $version, string $constraint): bool
    {
        foreach (preg_split('~[\s,]+~', trim($constraint)) ?: [] as $part) {
            if (!preg_match('~^(>=|<=|>|<|=|\^)?(\d+(?:\.\d+){0,2})$~', $part, $m)) {
                return false;
            }
            $op = $m[1] ?: '>=';
            if ($op === '^') {
                $major = (int) explode('.', $m[2])[0];
                if (!version_compare($version, $m[2], '>=') || !version_compare($version, ($major + 1) . '.0.0', '<')) {
                    return false;
                }
                continue;
            }
            if (!version_compare($version, $m[2], $op === '=' ? '==' : $op)) {
                return false;
            }
        }
        return true;
    }

    /** Empreinte de tout le dossier d'un thème (chemins et contenus) : preuve de conservation après une mise à jour. */
    public static function fingerprint(string $dir): string
    {
        $ctx = hash_init('sha256');
        foreach (array_keys(self::files($dir)) as $rel) {
            hash_update($ctx, $rel . "\0" . hash_file('sha256', $dir . '/' . $rel) . "\n");
        }
        return hash_final($ctx);
    }

    /** Thèmes installés dans site/themes/, avec leurs problèmes éventuels (pour l'administration). */
    public static function installed(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $id = basename($dir);
            if (!preg_match(self::ID, $id)) {
                continue;
            }
            $errors = self::validate($dir, $m);
            if (($m['id'] ?? '') !== $id) {
                $errors[] = t('L’identifiant du manifeste doit être le nom du dossier ({id}).', ['id' => $id]);
            }
            $out[$id] = ($m ?? ['id' => $id, 'name' => $id, 'version' => '']) + ['errors' => $errors, 'versions' => self::versions($id)];
        }
        ksort($out);
        return $out;
    }

    /* ─────────────────────────────── Rendu ─────────────────────────────── */

    /** Gabarit du thème du site qui remplace celui du noyau, ou null (thème du noyau). */
    public static function template(string $name): ?string
    {
        $t = self::active();
        if ($t === null || !in_array($name, $t['templates'], true)) {
            return null;
        }
        $file = $t['dir'] . '/templates/' . $name . '.php';
        return is_file($file) && !is_link($file) ? $file : null;
    }

    /** Fichier CSS du noyau, remplacé par le thème du site s'il le déclare (« overrides »). */
    public static function coreCss(string $name): string
    {
        $t = self::active();
        $file = $t !== null && isset($t['overrides'][$name]) && self::safePath($t['overrides'][$name]) ? $t['dir'] . '/' . $t['overrides'][$name] : '';
        if ($file !== '' && is_file($file) && !is_link($file)) {
            return (string) Disk::read($file);
        }
        return (string) @file_get_contents(ECLAIR_ROOT . '/app/theme/' . $name);
    }

    /** Feuilles de style ajoutées par le thème du site (nettoyées comme le CSS personnalisé, sans @import ni « < »). */
    public static function css(): string
    {
        if (self::$css !== null) {
            return self::$css;
        }
        $t = self::active();
        $css = '';
        foreach ($t['styles'] ?? [] as $file) {
            if (self::safePath($file) && is_file($t['dir'] . '/' . $file) && !is_link($t['dir'] . '/' . $file)) {
                $css .= "\n" . Theme::cleanCss((string) Disk::read($t['dir'] . '/' . $file), self::MAX_CSS);
            }
        }
        return self::$css = $css;
    }

    /** Adresse publique d'un actif déclaré du thème actif (publié dans site/assets/<id>/), ou '' s'il n'est pas déclaré. */
    public static function asset(string $file): string
    {
        $t = self::active();
        if ($t === null || !in_array($file, $t['assets'], true)) {
            return '';
        }
        $published = self::assetsDir() . '/' . $t['id'] . '/' . $file;
        $mtime = @filemtime($published) ?: 0;
        $base = substr(self::siteDir(), 0, strlen(ECLAIR_ROOT) + 1) === ECLAIR_ROOT . '/' ? substr(self::siteDir(), strlen(ECLAIR_ROOT)) : '/site';
        return Req::base() . $base . '/assets/' . $t['id'] . '/' . $file . '?v=' . substr(md5($t['version'] . $mtime), 0, 8);
    }

    /**
     * Erreur pendant le rendu d'un gabarit du site : journalisée, thème noté en panne (signalé dans l'administration) et
     * ignoré jusqu'à la fin de la requête ; la page est rendue avec le gabarit du noyau.
     */
    public static function failed(string $name, \Throwable $e): void
    {
        $t = self::active();
        Log::error($e);
        self::$active = null;
        self::$css = null;
        if ($t !== null && self::$preview === null) {
            try {
                Store::write('theme', ['broken' => ['id' => $t['id'], 'version' => $t['version'], 'template' => $name, 'date' => time(),
                    'error' => mb_substr($e->getMessage(), 0, 200)]] + self::state());
            } catch (\Throwable) {
                // disque plein : la page est servie quand même
            }
        }
    }

    /* ─────────────────────────────── Cycle de vie ─────────────────────────────── */

    /**
     * Active un thème installé, après validation complète. La version précédente (thème et version) est gardée pour un
     * retour en un clic. Les actifs déclarés sont publiés dans site/assets/<id>/ avant l'activation.
     */
    public static function activate(string $id): array
    {
        $dir = self::dir() . '/' . $id;
        if (!preg_match(self::ID, $id) || !is_dir($dir)) {
            return ['ok' => false, 'error' => t('Thème introuvable.')];
        }
        $errors = self::validate($dir, $m);
        if (($m['id'] ?? '') !== $id) {
            $errors[] = t('L’identifiant du manifeste doit être le nom du dossier ({id}).', ['id' => $id]);
        }
        if ($errors) {
            return ['ok' => false, 'error' => t('Thème non activé : {e}', ['e' => implode(' ', $errors)]), 'errors' => $errors];
        }
        if (!self::publish($id, $dir, (array) $m['assets'])) {
            return ['ok' => false, 'error' => t('Publication des fichiers du thème impossible : vérifie l’espace disque et les droits d’écriture de {p}.', ['p' => '/site/assets'])];
        }
        $state = self::state();
        $current = $state['active'] ?? null;
        $state['previous'] = is_array($current) ? $current : ['id' => '', 'version' => ''];
        $state['active'] = ['id' => $id, 'version' => $m['version'], 'date' => time(), 'hash' => self::fingerprint($dir)];
        unset($state['broken']);
        Store::write('theme', $state);
        self::forget();
        Cache::purge();
        Log::security('theme_activated', ['id' => $id, 'version' => $m['version']]);
        return ['ok' => true, 'id' => $id, 'version' => $m['version']];
    }

    /** Revient au thème du noyau (les fichiers du thème du site restent en place). */
    public static function deactivate(): array
    {
        $state = self::state();
        $current = $state['active'] ?? null;
        if (!is_array($current) || ($current['id'] ?? '') === '') {
            return ['ok' => true, 'id' => ''];
        }
        $state['previous'] = $current;
        $state['active'] = ['id' => '', 'version' => '', 'date' => time()];
        unset($state['broken']);
        Store::write('theme', $state);
        self::forget();
        Cache::purge();
        Log::security('theme_deactivated', ['id' => (string) $current['id']]);
        return ['ok' => true, 'id' => ''];
    }

    /**
     * Revient au thème et à la version précédemment actifs : la version gardée dans site/themes/.versions/ est remise en
     * place si la version installée a changé depuis (installation d'une nouvelle version du thème).
     */
    public static function revert(): array
    {
        $state = self::state();
        $prev = $state['previous'] ?? null;
        if (!is_array($prev)) {
            return ['ok' => false, 'error' => t('Aucun thème précédent à rétablir.')];
        }
        $id = (string) ($prev['id'] ?? '');
        if ($id === '') {
            return self::deactivate();
        }
        $installed = self::read($id);
        if ($installed === null || $installed['version'] !== (string) ($prev['version'] ?? '')) {
            $restored = self::restoreVersion($id, (string) ($prev['version'] ?? ''));
            if (!$restored['ok']) {
                return $restored;
            }
        }
        return self::activate($id);
    }

    /**
     * Installe un thème (dossier déjà décompressé et validé par l'appelant, ou non) dans site/themes/<id>/. Une version déjà
     * installée est gardée dans site/themes/.versions/<id>/<version>/ (retour possible) ; rien n'est activé.
     */
    public static function install(string $source): array
    {
        $errors = self::validate($source, $m);
        if ($errors) {
            return ['ok' => false, 'error' => t('Thème refusé : {e}', ['e' => implode(' ', $errors)]), 'errors' => $errors];
        }
        $id = (string) $m['id'];
        self::ensure();
        $target = self::dir() . '/' . $id;
        $stage = self::dir() . '/.stage-' . bin2hex(random_bytes(4));
        if (!self::copyTree($source, $stage)) {
            self::removeTree($stage);
            return ['ok' => false, 'error' => t('Copie du thème impossible : vérifie l’espace disque et les droits d’écriture de {p}.', ['p' => '/site/themes'])];
        }
        if (is_dir($target)) {
            $old = self::read($id);
            $keep = self::dir() . '/.versions/' . $id . '/' . ($old['version'] ?? 'inconnue') . '-' . date('YmdHis');
            Store::ensureDir(dirname($keep));
            if (!@rename($target, $keep)) {
                self::removeTree($stage);
                return ['ok' => false, 'error' => t('Impossible de mettre de côté la version installée du thème : rien n’a été modifié.')];
            }
        }
        if (!@rename($stage, $target)) {
            // Interruption entre les deux renommages : la version gardée reste dans .versions/ (restoreVersion la remet).
            self::removeTree($stage);
            return ['ok' => false, 'error' => t('Installation du thème impossible : la version précédente reste dans site/themes/.versions/.')];
        }
        Log::security('theme_installed', ['id' => $id, 'version' => (string) $m['version']]);
        self::forget();
        // Thème actif mis à jour : ses nouveaux actifs sont publiés, le cache est vidé.
        if ((string) (self::state()['active']['id'] ?? '') === $id) {
            self::publish($id, $target, (array) $m['assets']);
            Cache::purge();
        }
        return ['ok' => true, 'id' => $id, 'version' => (string) $m['version']];
    }

    /** Installe un thème depuis une archive ZIP vérifiée (voir Packages::installZip). */
    public static function installZip(string $file): array
    {
        return Packages::installZip($file, 'theme');
    }

    /** Versions gardées d'un thème (les plus récentes d'abord). */
    public static function versions(string $id): array
    {
        $out = [];
        foreach (glob(self::dir() . '/.versions/' . $id . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $m = self::read($id, $dir);
            if ($m !== null) {
                $out[basename($dir)] = $m['version'];
            }
        }
        krsort($out);
        return $out;
    }

    /** Remet en place une version gardée d'un thème (la version installée est gardée à son tour). */
    private static function restoreVersion(string $id, string $version): array
    {
        foreach (self::versions($id) as $folder => $v) {
            if ($v === $version) {
                return self::install(self::dir() . '/.versions/' . $id . '/' . $folder);
            }
        }
        return ['ok' => false, 'error' => t('La version {v} du thème n’est plus disponible.', ['v' => $version])];
    }

    /** Publie les actifs déclarés dans site/assets/<id>/ (copie, puis échange du dossier). */
    private static function publish(string $id, string $dir, array $assets): bool
    {
        try {
            self::ensure();
            $target = self::assetsDir() . '/' . $id;
            $stage = self::assetsDir() . '/.stage-' . bin2hex(random_bytes(4));
            Store::ensureDir($stage);
            foreach ($assets as $file) {
                if (!self::safePath($file) || !isset(self::ASSET_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
                    continue;
                }
                $content = Disk::read($dir . '/' . $file);
                if (!is_string($content) || !Disk::place($stage . '/' . $file, $content)) {
                    self::removeTree($stage);
                    return false;
                }
                for ($d = dirname($stage . '/' . $file); $d !== $stage && strlen($d) > strlen($stage); $d = dirname($d)) {
                    Disk::mode($d, 0755);
                }
                Disk::mode($stage . '/' . $file, 0644);
            }
            Disk::mode($stage, 0755);
            $old = $target . '.old-' . bin2hex(random_bytes(4));
            if (is_dir($target) && !@rename($target, $old)) {
                self::removeTree($stage);
                return false;
            }
            if (!@rename($stage, $target)) {
                if (is_dir($old)) {
                    @rename($old, $target);
                }
                self::removeTree($stage);
                return false;
            }
            self::removeTree($old);
            return true;
        } catch (\Throwable $e) {
            Log::error($e);
            return false;
        }
    }

    /** Copie récursive (fichiers ordinaires seulement, chemins sûrs). */
    public static function copyTree(string $from, string $to): bool
    {
        try {
            Store::ensureDir($to);
            foreach (array_keys(self::files($from, $problem)) as $rel) {
                $content = Disk::read($from . '/' . $rel);
                if (!is_string($content) || !Disk::place($to . '/' . $rel, $content)) {
                    return false;
                }
            }
            return $problem === '';
        } catch (\Throwable) {
            return false;
        }
    }

    /** Suppression récursive limitée au dossier propre au site (jamais au-delà, jamais en suivant un lien). */
    public static function removeTree(string $path): void
    {
        $root = self::siteDir() . '/';
        if (!str_starts_with($path, $root) || str_contains(substr($path, strlen($root)), '..') || (!file_exists($path) && !is_link($path))) {
            return;
        }
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::removeTree($path . '/' . $entry);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}
