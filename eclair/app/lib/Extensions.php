<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Capacités optionnelles (site/extensions/<id>/) : registre minimal et cycle de vie.
 *
 * Une capacité déclare dans extension.json ce qu'elle apporte (routes publiques et d'administration, entrées de menu,
 * cartes du tableau de bord, réglages, blocs, actifs, tâches, migrations) ; le noyau garde la main sur tout ce qui protège :
 * contrôle d'accès et CSRF des routes d'administration, validation des réglages et des champs de blocs, en-têtes SEO et
 * de sécurité des pages, échappement des cartes. Le code de la capacité (un fichier qui renvoie un objet) n'est chargé que
 * si elle est active et que la requête en a besoin ; une capacité absente ne coûte qu'une lecture de fichier
 * (data/extensions.php) aux requêtes non servies par le cache, et rien au chemin rapide.
 *
 * Le code d'une capacité s'exécute avec les droits de l'application : le manifeste décrit un contrat (droits applicatifs
 * de ses routes, fichiers et empreintes, provenance), il ne crée pas de bac à sable. Désactiver garde les données ;
 * supprimer le code est une opération distincte, et effacer les données une troisième.
 */
final class Extensions
{
    public const SCHEMA = 'eclair-extension/1';
    private const ID = '~^[a-z][a-z0-9-]{1,39}$~';
    private const SEMVER = '~^\d{1,3}\.\d{1,3}\.\d{1,3}$~';
    /** Droits applicatifs qu'une route d'administration peut exiger (ceux du noyau, Auth::can). */
    public const RIGHTS = ['content', 'media', 'menus', 'categories', 'messages', 'redirects', 'settings', 'users', 'security'];
    /** Champs déclaratifs (réglages, blocs) : le noyau les valide, la capacité ne reçoit que des valeurs propres. */
    public const FIELDS = ['text', 'textarea', 'url', 'number', 'bool', 'select'];
    private const MIN_EVERY = 3600;

    private static ?array $state = null;
    /** Objets chargés (id => objet renvoyé par le fichier d'entrée). */
    private static array $loaded = [];

    public static function dir(): string
    {
        return Themes::siteDir() . '/extensions';
    }

    /* ─────────────────────────────── État ─────────────────────────────── */

    public static function state(): array
    {
        if (self::$state === null) {
            $s = Store::read('extensions', []);
            self::$state = is_array($s) ? $s : [];
        }
        return self::$state;
    }

    public static function forget(): void
    {
        self::$state = null;
        self::$loaded = [];
    }

    private static function save(array $state): void
    {
        Store::write('extensions', $state);
        self::$state = $state;
    }

    /**
     * Capacités actives et compatibles avec ce noyau : id => manifeste (celui enregistré à l'activation, aucun dossier
     * parcouru). Une capacité devenue incompatible après une mise à jour du noyau n'est jamais chargée.
     */
    public static function active(): array
    {
        $out = [];
        foreach ((array) (self::state()['items'] ?? []) as $id => $item) {
            $m = $item['manifest'] ?? null;
            if (($item['status'] ?? '') === 'active' && is_array($m) && self::compatible($m) === '') {
                $out[(string) $id] = $m;
            }
        }
        return $out;
    }

    public static function enabled(string $id): bool
    {
        return isset(self::active()[$id]);
    }

    /** Raison d'une incompatibilité avec ce noyau ou ce PHP ('' : compatible). */
    public static function compatible(array $m): string
    {
        $running = defined('ECLAIR_VERSION') ? (string) ECLAIR_VERSION : '0.0.0';
        $core = (string) ($m['requires']['eclair'] ?? '');
        if ($core !== '' && preg_match('~^\d~', $running) && !Themes::satisfies($running, $core)) {
            return t('Cette fonction demande Éclair {v}.', ['v' => $core]);
        }
        $php = (string) ($m['requires']['php'] ?? '');
        if ($php !== '' && !Themes::satisfies(PHP_VERSION, $php)) {
            return t('Cette fonction demande PHP {v}.', ['v' => $php]);
        }
        return '';
    }

    /* ─────────────────────────────── Manifeste ─────────────────────────────── */

    /** Manifeste ramené aux champs du contrat (types sûrs, listes bornées). */
    public static function normalize(array $m): array
    {
        $list = static fn (mixed $v, int $max = 100): array => array_values(array_filter(array_slice(is_array($v) ? $v : [], 0, $max), 'is_array'));
        $fields = static function (mixed $v) use ($list): array {
            $out = [];
            foreach ($list($v, 40) as $f) {
                if (is_string($f['key'] ?? null) && preg_match('~^[a-z][a-z0-9_]{0,39}$~', $f['key']) && in_array($f['type'] ?? 'text', self::FIELDS, true)) {
                    $out[] = ['key' => $f['key'], 'type' => (string) ($f['type'] ?? 'text'), 'label' => mb_substr((string) ($f['label'] ?? $f['key']), 0, 80),
                        'max' => max(1, min(20000, (int) ($f['max'] ?? 300))), 'default' => is_scalar($f['default'] ?? null) ? $f['default'] : '',
                        'options' => array_slice(array_filter((array) ($f['options'] ?? []), 'is_string'), 0, 50)];
                }
            }
            return $out;
        };
        $routes = static function (mixed $v) use ($list): array {
            return array_map(static fn (array $r): array => ['method' => strtoupper((string) ($r['method'] ?? 'GET')) === 'POST' ? 'POST' : 'GET',
                'path' => (string) ($r['path'] ?? ''), 'handler' => (string) ($r['handler'] ?? ''), 'right' => (string) ($r['right'] ?? 'settings'),
                'cache' => !empty($r['cache'])], $list($v, 30));
        };
        return [
            'schema' => (string) ($m['schema'] ?? ''),
            'id' => (string) ($m['id'] ?? ''),
            'name' => mb_substr(is_string($m['name'] ?? null) ? $m['name'] : (string) ($m['id'] ?? ''), 0, 80),
            'description' => mb_substr(is_string($m['description'] ?? null) ? $m['description'] : '', 0, 400),
            'version' => (string) ($m['version'] ?? ''),
            'requires' => is_array($m['requires'] ?? null) ? array_map('strval', array_filter($m['requires'], 'is_scalar')) : [],
            'depends' => is_array($m['depends'] ?? null) ? array_map('strval', array_filter($m['depends'], 'is_scalar')) : [],
            'provides' => array_values(array_filter((array) ($m['provides'] ?? []), 'is_string')),
            'entry' => (string) ($m['entry'] ?? 'extension.php'),
            'files' => is_array($m['files'] ?? null) ? array_filter($m['files'], 'is_string') : [],
            'routes' => ['public' => $routes($m['routes']['public'] ?? []), 'admin' => $routes($m['routes']['admin'] ?? [])],
            'nav' => array_map(static fn (array $n): array => ['label' => mb_substr((string) ($n['label'] ?? ''), 0, 40), 'path' => (string) ($n['path'] ?? ''),
                'icon' => preg_match('~^[a-z-]{1,20}$~', (string) ($n['icon'] ?? '')) ? (string) $n['icon'] : 'panel', 'right' => (string) ($n['right'] ?? 'settings')], $list($m['nav'] ?? [], 10)),
            'dashboard' => array_map(static fn (array $d): array => ['handler' => (string) ($d['handler'] ?? ''), 'right' => (string) ($d['right'] ?? 'content')], $list($m['dashboard'] ?? [], 5)),
            'settings' => $fields($m['settings'] ?? []),
            'blocks' => array_map(static fn (array $b): array => ['type' => (string) ($b['type'] ?? ''), 'label' => mb_substr((string) ($b['label'] ?? ''), 0, 40),
                'render' => (string) ($b['render'] ?? ''), 'fields' => $fields($b['fields'] ?? [])], $list($m['blocks'] ?? [], 20)),
            'assets' => array_values(array_filter((array) ($m['assets'] ?? []), 'is_string')),
            'tasks' => array_map(static fn (array $t): array => ['handler' => (string) ($t['handler'] ?? ''), 'every' => max(self::MIN_EVERY, (int) ($t['every'] ?? 86400))], $list($m['tasks'] ?? [], 5)),
            'migrations' => array_values(array_filter((array) ($m['migrations'] ?? []), static fn ($x): bool => is_string($x) && preg_match('~^[a-z0-9-]{1,40}$~', $x))),
            'data' => ['schema' => (int) ($m['data']['schema'] ?? 1)],
            'provenance' => is_array($m['provenance'] ?? null) ? array_map('strval', array_filter($m['provenance'], 'is_scalar')) : [],
        ];
    }

    /**
     * Vérifie une capacité avant installation ou activation : contrat du manifeste, compatibilité, chemins et types de
     * fichiers, code PHP seulement dans les fichiers déclarés, empreintes déclarées (provenance), routes et droits
     * permis, types de blocs préfixés par l'identifiant. Renvoie les problèmes, expliqués simplement (vide : valide).
     */
    public static function validate(string $dir, ?array &$manifest = null): array
    {
        if (is_link($dir) || !is_dir($dir)) {
            return [t('Dossier de la fonction introuvable.')];
        }
        $raw = json_decode((string) Disk::read($dir . '/extension.json'), true);
        if (!is_array($raw)) {
            return [t('Fichier extension.json absent ou illisible.')];
        }
        $m = self::normalize($raw);
        $manifest = $m;
        $errors = [];
        if ($m['schema'] !== self::SCHEMA) {
            $errors[] = t('Format de manifeste inconnu (attendu : {s}).', ['s' => self::SCHEMA]);
        }
        if (!preg_match(self::ID, $m['id'])) {
            $errors[] = t('Identifiant de fonction invalide : lettres minuscules, chiffres et tirets (2 à 40).');
        }
        if (!preg_match(self::SEMVER, $m['version'])) {
            $errors[] = t('Version invalide (format 1.0.0).');
        }
        if (($why = self::compatible($m)) !== '') {
            $errors[] = $why;
        }
        $files = Themes::files($dir, $problem);
        if ($problem !== '') {
            return array_merge($errors, [$problem]);
        }
        if (!Themes::safePath($m['entry']) || !str_ends_with($m['entry'], '.php') || !isset($files[$m['entry']])) {
            $errors[] = t('Fichier d’entrée absent : {f}.', ['f' => $m['entry']]);
        }
        foreach (array_keys($files) as $rel) {
            if (preg_match('~\.(php\d?|phtml|phar|inc)$~i', $rel) && $rel !== $m['entry'] && !preg_match('~^lib/[A-Za-z0-9_-]+\.php$~', $rel)) {
                $errors[] = t('Script non déclaré refusé : {f}.', ['f' => $rel]);
            }
            if (str_ends_with($rel, '.php') && function_exists('token_get_all')) {
                try {
                    token_get_all((string) Disk::read($dir . '/' . $rel), TOKEN_PARSE);
                } catch (\Throwable) {
                    $errors[] = t('Fichier illisible par PHP {v} : {f}.', ['v' => PHP_VERSION, 'f' => $rel]);
                }
            }
        }
        // Empreintes déclarées : chaque fichier listé doit correspondre ; un fichier de code non listé est refusé.
        if ($m['files'] !== []) {
            foreach ($m['files'] as $rel => $sha) {
                if (!isset($files[$rel]) || !hash_equals(strtolower($sha), (string) hash_file('sha256', $dir . '/' . $rel))) {
                    $errors[] = t('Empreinte différente de celle déclarée : {f}.', ['f' => (string) $rel]);
                }
            }
            foreach (array_keys($files) as $rel) {
                if ($rel !== 'extension.json' && !isset($m['files'][$rel])) {
                    $errors[] = t('Fichier absent de la liste déclarée : {f}.', ['f' => $rel]);
                }
            }
        }
        foreach ($m['routes']['public'] as $r) {
            if (!preg_match('~^/[a-z0-9][a-z0-9/-]{0,99}$~', $r['path']) || preg_match('~(^|/)(app|data|cache|site|media|assets|_eclair|\.well-known)(/|$)~', $r['path'])
                || !preg_match('~^[a-zA-Z][a-zA-Z0-9]{0,40}$~', $r['handler'])) {
                $errors[] = t('Route publique refusée : {p}.', ['p' => $r['path']]);
            }
        }
        foreach ($m['routes']['admin'] as $r) {
            if (!preg_match('~^(/[a-z0-9-]{1,40}){0,3}$~', $r['path']) || !in_array($r['right'], self::RIGHTS, true) || !preg_match('~^[a-zA-Z][a-zA-Z0-9]{0,40}$~', $r['handler'])) {
                $errors[] = t('Route d’administration refusée : {p}.', ['p' => '/x/' . $m['id'] . $r['path']]);
            }
        }
        foreach (array_merge($m['nav'], $m['dashboard']) as $n) {
            if (!in_array($n['right'], self::RIGHTS, true)) {
                $errors[] = t('Droit inconnu : {r}.', ['r' => $n['right']]);
            }
        }
        foreach ($m['blocks'] as $b) {
            if (!preg_match('~^' . preg_quote($m['id'], '~') . '/[a-z][a-z0-9-]{0,39}$~', $b['type']) || !preg_match('~^[a-zA-Z][a-zA-Z0-9]{0,40}$~', $b['render'])) {
                $errors[] = t('Bloc refusé : son type doit commencer par « {id}/ » ({t}).', ['id' => $m['id'], 't' => $b['type']]);
            }
        }
        foreach ($m['assets'] as $file) {
            if (!Themes::safePath($file) || !isset($files[$file]) || !isset(Themes::ASSET_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
                $errors[] = t('Type d’actif refusé : {f}.', ['f' => $file]);
            }
        }
        return array_values(array_unique($errors));
    }

    /** Capacités installées dans site/extensions/, avec leur état et leurs problèmes (administration). */
    public static function installed(): array
    {
        $out = [];
        $items = (array) (self::state()['items'] ?? []);
        foreach (glob(self::dir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $id = basename($dir);
            if (!preg_match(self::ID, $id)) {
                continue;
            }
            $errors = self::validate($dir, $m);
            $out[$id] = ($m ?? ['id' => $id, 'name' => $id]) + ['errors' => $errors, 'status' => (string) ($items[$id]['status'] ?? 'installed')];
        }
        foreach ($items as $id => $item) {
            if (!isset($out[$id]) && is_array($item['manifest'] ?? null)) {
                $out[$id] = $item['manifest'] + ['errors' => [t('Code de la fonction absent (supprimé) : ses données sont gardées.')], 'status' => 'missing'];
            }
        }
        ksort($out);
        return $out;
    }

    /* ─────────────────────────────── Cycle de vie ─────────────────────────────── */

    /** Installe (ou met à jour) le code d'une capacité dans site/extensions/<id>/, sans l'activer. */
    public static function install(string $source): array
    {
        $errors = self::validate($source, $m);
        if ($errors) {
            return ['ok' => false, 'error' => t('Fonction refusée : {e}', ['e' => implode(' ', $errors)]), 'errors' => $errors];
        }
        Themes::ensure();
        $id = (string) $m['id'];
        $target = self::dir() . '/' . $id;
        $stage = self::dir() . '/.stage-' . bin2hex(random_bytes(4));
        if (!Themes::copyTree($source, $stage)) {
            Themes::removeTree($stage);
            return ['ok' => false, 'error' => t('Copie impossible : vérifie l’espace disque et les droits d’écriture de {p}.', ['p' => '/site/extensions'])];
        }
        if (is_dir($target) && !@rename($target, self::dir() . '/.old-' . $id . '-' . bin2hex(random_bytes(4)))) {
            Themes::removeTree($stage);
            return ['ok' => false, 'error' => t('Impossible de mettre de côté la version installée : rien n’a été modifié.')];
        }
        if (!@rename($stage, $target)) {
            Themes::removeTree($stage);
            return ['ok' => false, 'error' => t('Installation impossible : vérifie les droits d’écriture de {p}.', ['p' => '/site/extensions'])];
        }
        foreach (glob(self::dir() . '/.old-' . $id . '-*', GLOB_ONLYDIR) ?: [] as $old) {
            Themes::removeTree($old);
        }
        Log::security('extension_installed', ['id' => $id, 'version' => (string) $m['version']]);
        // Capacité déjà active : sa nouvelle version est réactivée (migrations, manifeste enregistré).
        if ((self::state()['items'][$id]['status'] ?? '') === 'active') {
            return self::activate($id);
        }
        return ['ok' => true, 'id' => $id, 'version' => (string) $m['version']];
    }

    /**
     * Active une capacité installée : validation, dépendances actives et de version compatible (résolues dans l'ordre),
     * routes publiques libres, migrations de ses données (identifiées, journalisées, une seule fois), actifs publiés.
     */
    public static function activate(string $id, array $seen = []): array
    {
        $dir = self::dir() . '/' . $id;
        if (!preg_match(self::ID, $id) || !is_dir($dir)) {
            return ['ok' => false, 'error' => t('Fonction introuvable : {id}.', ['id' => $id])];
        }
        $errors = self::validate($dir, $m);
        if (($m['id'] ?? '') !== $id) {
            $errors[] = t('L’identifiant du manifeste doit être le nom du dossier ({id}).', ['id' => $id]);
        }
        if ($errors) {
            return ['ok' => false, 'error' => t('Fonction non activée : {e}', ['e' => implode(' ', $errors)]), 'errors' => $errors];
        }
        // Dépendances : activées automatiquement si elles sont installées et compatibles.
        foreach ($m['depends'] as $dep => $constraint) {
            if (isset($seen[$dep])) {
                return ['ok' => false, 'error' => t('Dépendances circulaires : {id}.', ['id' => $dep])];
            }
            $have = self::active()[$dep] ?? null;
            if ($have === null) {
                $r = self::activate((string) $dep, $seen + [$id => true]);
                if (!$r['ok']) {
                    return ['ok' => false, 'error' => t('Cette fonction a besoin de « {d} » : {e}', ['d' => $dep, 'e' => $r['error']])];
                }
                $have = self::active()[$dep] ?? null;
            }
            if ($have === null || !Themes::satisfies((string) $have['version'], $constraint)) {
                return ['ok' => false, 'error' => t('Cette fonction a besoin de « {d} » {v}.', ['d' => $dep, 'v' => $constraint])];
            }
        }
        if (($conflict = self::conflicts($m)) !== '') {
            return ['ok' => false, 'error' => $conflict];
        }
        $state = self::state();
        $done = (array) ($state['items'][$id]['migrations'] ?? []);
        $object = self::object($id, $m);
        foreach ($m['migrations'] as $migration) {
            if (in_array($migration, $done, true)) {
                continue;
            }
            try {
                if ($object !== null && method_exists($object, 'migrate')) {
                    $object->migrate($migration, self::context($id));
                }
            } catch (\Throwable $e) {
                Log::error($e);
                return ['ok' => false, 'error' => t('Migration « {m} » de la fonction impossible : rien n’a été activé. Détail dans Sécurité › Dernières erreurs techniques.', ['m' => $migration])];
            }
            $done[] = $migration;
            Log::security('extension_migrated', ['id' => $id, 'migration' => $migration]);
        }
        $assets = Themes::assetsDir() . '/ext-' . $id;
        foreach ($m['assets'] as $file) {
            $content = Disk::read($dir . '/' . $file);
            if (!is_string($content) || !Disk::place($assets . '/' . $file, $content)) {
                return ['ok' => false, 'error' => t('Publication des fichiers de la fonction impossible : vérifie l’espace disque et les droits d’écriture de {p}.', ['p' => '/site/assets'])];
            }
            Disk::mode($assets . '/' . $file, 0644);
        }
        $state = self::state();
        $state['token'] ??= bin2hex(random_bytes(16));
        $state['items'][$id] = ['status' => 'active', 'version' => $m['version'], 'date' => time(), 'manifest' => $m, 'migrations' => $done]
            + (array) ($state['items'][$id] ?? []);
        $state['items'][$id]['status'] = 'active';
        $state['items'][$id]['manifest'] = $m;
        $state['items'][$id]['migrations'] = $done;
        $state['items'][$id]['version'] = $m['version'];
        self::save($state);
        Cache::purge();
        Log::security('extension_activated', ['id' => $id, 'version' => (string) $m['version']]);
        return ['ok' => true, 'id' => $id, 'version' => (string) $m['version']];
    }

    /** Route publique déjà prise (contenu, route du noyau, autre capacité) : explication, ou ''. */
    private static function conflicts(array $m): string
    {
        $reserved = array_map(static fn (string $r): string => '/' . trim($r, '/'), Settings::reserved());
        foreach ($m['routes']['public'] as $r) {
            $first = '/' . explode('/', trim($r['path'], '/'))[0];
            if (in_array($r['path'], $reserved, true) || in_array($first, $reserved, true) || Content::findByPath($r['path']) !== null) {
                return t('L’adresse {p} est déjà utilisée par ton site : la fonction n’a pas été activée.', ['p' => $r['path']]);
            }
            foreach (self::active() as $other => $om) {
                foreach ($om['routes']['public'] as $or) {
                    if ($other !== $m['id'] && $or['path'] === $r['path']) {
                        return t('L’adresse {p} est déjà utilisée par la fonction « {id} ».', ['p' => $r['path'], 'id' => $other]);
                    }
                }
            }
        }
        return '';
    }

    /**
     * Ce que la désactivation changerait sur le site public : adresses de la capacité, contenus qui utilisent ses blocs.
     * Sert à prévenir avant de désactiver (jamais une boutique transformée en série de 404 sans avertissement).
     */
    public static function impact(string $id): array
    {
        $m = self::active()[$id] ?? (self::state()['items'][$id]['manifest'] ?? null);
        if (!is_array($m)) {
            return ['routes' => [], 'contents' => [], 'dependents' => []];
        }
        $types = array_column($m['blocks'], 'type');
        $contents = [];
        if ($types !== []) {
            foreach (Content::index() as $entry) {
                $item = Content::get((string) $entry['id']);
                foreach ((array) ($item['blocks'] ?? []) as $b) {
                    if (in_array($b['type'] ?? '', $types, true)) {
                        $contents[] = ['id' => (string) $entry['id'], 'title' => (string) ($entry['title'] ?? ''), 'status' => (string) ($entry['status'] ?? '')];
                        break;
                    }
                }
            }
        }
        $dependents = [];
        foreach (self::active() as $other => $om) {
            if ($other !== $id && isset($om['depends'][$id])) {
                $dependents[] = $other;
            }
        }
        return ['routes' => array_column($m['routes']['public'], 'path'), 'contents' => $contents, 'dependents' => $dependents];
    }

    /**
     * Désactive une capacité : son code n'est plus chargé, ses routes, cartes et tâches disparaissent, ses blocs sont
     * conservés sans être affichés, ses données restent. Refusé si une autre capacité active en dépend ; demande une
     * confirmation ($confirmed) si des adresses publiques ou des contenus publiés en dépendent.
     */
    public static function deactivate(string $id, bool $confirmed = false): array
    {
        $state = self::state();
        if (($state['items'][$id]['status'] ?? '') !== 'active') {
            return ['ok' => true, 'id' => $id];
        }
        $impact = self::impact($id);
        if ($impact['dependents']) {
            return ['ok' => false, 'error' => t('Désactive d’abord ce qui en dépend : {list}.', ['list' => implode(', ', $impact['dependents'])])];
        }
        if (!$confirmed && ($impact['routes'] || array_filter($impact['contents'], static fn (array $c): bool => $c['status'] === 'published'))) {
            return ['ok' => false, 'confirm' => true, 'impact' => $impact,
                'error' => t('Des pages publiques utilisent cette fonction ({r} adresses, {c} contenus) : elles ne l’afficheront plus. Confirme pour désactiver quand même.',
                    ['r' => count($impact['routes']), 'c' => count($impact['contents'])])];
        }
        $state['items'][$id]['status'] = 'disabled';
        $state['items'][$id]['date'] = time();
        self::save($state);
        Cache::purge();
        Log::security('extension_deactivated', ['id' => $id]);
        return ['ok' => true, 'id' => $id];
    }

    /** Supprime le code d'une capacité désactivée (ses données restent ; elles se suppriment avec purge()). */
    public static function remove(string $id): array
    {
        if (self::enabled($id)) {
            return ['ok' => false, 'error' => t('Désactive d’abord la fonction.')];
        }
        Themes::removeTree(self::dir() . '/' . $id);
        Themes::removeTree(Themes::assetsDir() . '/ext-' . $id);
        Log::security('extension_removed', ['id' => $id]);
        return ['ok' => !is_dir(self::dir() . '/' . $id), 'id' => $id];
    }

    /** Efface définitivement les données d'une capacité supprimée (réglages, données dans data/ext/<id>/). */
    public static function purge(string $id): array
    {
        if (self::enabled($id) || is_dir(self::dir() . '/' . $id)) {
            return ['ok' => false, 'error' => t('Supprime d’abord le code de la fonction.')];
        }
        foreach (Store::names('ext/' . $id) as $name) {
            Store::delete($name);
        }
        $state = self::state();
        unset($state['items'][$id]);
        self::save($state);
        Log::security('extension_purged', ['id' => $id]);
        return ['ok' => true, 'id' => $id];
    }

    /* ─────────────────────────────── Fonctions livrées ─────────────────────────────── */

    /**
     * Fonctions livrées avec le noyau (app/bundled/<id>/) : présentes dans l'archive, signées avec elle, mais jamais
     * chargées tant que le site ne les a pas ajoutées (copie dans site/extensions/). id => manifeste.
     */
    public static function bundled(): array
    {
        $out = [];
        foreach (glob(ECLAIR_ROOT . '/app/bundled/*/extension.json') ?: [] as $file) {
            $m = json_decode((string) Disk::read($file), true);
            $id = basename(dirname($file));
            if (is_array($m) && ($m['id'] ?? '') === $id && preg_match(self::ID, $id)) {
                $out[$id] = self::normalize($m) + ['dir' => dirname($file)];
            }
        }
        ksort($out);
        return $out;
    }

    /** Ajoute une fonction livrée avec Éclair : installation depuis app/bundled/<id>/, puis activation. */
    public static function add(string $id): array
    {
        $b = self::bundled()[$id] ?? null;
        if ($b === null) {
            return ['ok' => false, 'error' => t('Fonction introuvable : {id}.', ['id' => $id])];
        }
        if (!is_dir(self::dir() . '/' . $id)) {
            $r = self::install($b['dir']);
            if (!$r['ok']) {
                return $r;
            }
        }
        return self::activate($id);
    }

    /**
     * Après une mise à jour du noyau : les fonctions livrées que le site a ajoutées reçoivent la version livrée avec ce
     * noyau (si elle est plus récente). Une fonction retirée ou jamais ajoutée n'est jamais installée par ce biais.
     */
    public static function syncBundled(): array
    {
        $done = [];
        foreach (self::bundled() as $id => $b) {
            $installed = is_file(self::dir() . '/' . $id . '/extension.json') ? json_decode((string) Disk::read(self::dir() . '/' . $id . '/extension.json'), true) : null;
            if (is_array($installed) && version_compare((string) $b['version'], (string) ($installed['version'] ?? '0'), '>')) {
                $r = self::install($b['dir']);
                $done[$id] = $r['ok'] ? (string) $b['version'] : (string) $r['error'];
            }
        }
        return $done;
    }

    /* ─────────────────────────────── Exécution ─────────────────────────────── */

    /** Objet de la capacité (fichier d'entrée chargé une fois), ou null. */
    private static function object(string $id, ?array $m = null): ?object
    {
        if (array_key_exists($id, self::$loaded)) {
            return self::$loaded[$id];
        }
        $m ??= self::active()[$id] ?? null;
        $file = self::dir() . '/' . $id . '/' . (string) ($m['entry'] ?? '');
        if ($m === null || !Themes::safePath((string) $m['entry']) || !is_file($file) || is_link($file)) {
            return self::$loaded[$id] = null;
        }
        $object = (static function (string $__file) {
            return require $__file;
        })($file);
        return self::$loaded[$id] = is_object($object) ? $object : null;
    }

    /** Contexte remis à la capacité : ses réglages validés, son dossier de données, la langue. Jamais de secret du noyau. */
    public static function context(string $id): array
    {
        return ['id' => $id, 'settings' => self::settings($id), 'lang' => (string) Settings::get('lang', 'fr'), 'data' => 'ext/' . $id];
    }

    /** Appelle une méthode déclarée de la capacité ; une erreur est journalisée et la capacité ne casse pas la page. */
    private static function call(string $id, string $method, array $args): mixed
    {
        $o = self::object($id);
        if ($o === null || !preg_match('~^[a-zA-Z][a-zA-Z0-9]{0,40}$~', $method) || !method_exists($o, $method)) {
            return null;
        }
        try {
            return $o->$method(...$args);
        } catch (\Throwable $e) {
            Log::error($e);
            return null;
        }
    }

    /**
     * Point d'accroche du noyau vers une fonction livrée active (adresse historique d'une tâche, envoi dû en arrière-plan) :
     * null si la fonction est absente ou n'a pas cette méthode. Réservé aux fonctions livrées avec Éclair, dont le noyau
     * connaît le contrat ; les autres fonctions passent par leur manifeste.
     */
    public static function invoke(string $id, string $method, array $args = []): mixed
    {
        if (!self::enabled($id) || !isset(self::bundled()[$id])) {
            return null;
        }
        return self::call($id, $method, $args);
    }

    /** Route publique d'une capacité active : page rendue dans le thème, en-têtes SEO et de sécurité du noyau. */
    public static function route(string $path): void
    {
        foreach (self::active() as $id => $m) {
            foreach ($m['routes']['public'] as $r) {
                if ($r['path'] === $path && in_array(Req::method(), ['GET', 'HEAD'], true)) {
                    $page = self::call($id, $r['handler'], [self::context($id)]);
                    if (is_array($page) && is_string($page['content'] ?? null)) {
                        Site::extension($path, $page, $r['cache']);
                    }
                }
            }
        }
    }

    /**
     * Routes d'administration des capacités actives (/x/<id>/…), au format de la table d'Admin::handle : le contrôle d'accès
     * (droit déclaré) et la vérification CSRF des POST restent ceux du noyau.
     */
    public static function adminRoutes(): array
    {
        $out = [];
        foreach (self::active() as $id => $m) {
            foreach ($m['routes']['admin'] as $r) {
                $out[$r['method'] . ' /x/' . $id . preg_quote($r['path'], '~')] = [$id, $r['handler'], $r['right']];
            }
            if ($m['settings']) {
                $out['GET /x/' . $id . '/settings'] = [$id, '@settings', 'settings'];
                $out['POST /x/' . $id . '/settings'] = [$id, '@settings', 'settings'];
            }
        }
        return $out;
    }

    /** Exécute une route d'administration d'une capacité (droit déjà vérifié par Admin). */
    public static function admin(string $id, string $handler, array $user): never
    {
        if ($handler === '@settings') {
            self::settingsPage($id);
        }
        $page = self::call($id, $handler, [$user, self::context($id)]);
        if (!is_array($page)) {
            Admin::view('error', ['title' => t('Erreur'), 'text' => t('Cette page de la fonction n’a pas pu s’afficher. Détail dans Sécurité › Dernières erreurs techniques.')], t('Erreur'), 500);
        }
        if (isset($page['redirect']) && is_string($page['redirect'])) {
            Admin::back('x/' . $id . '/' . ltrim($page['redirect'], '/'), (string) ($page['message'] ?? ''));
        }
        Admin::view('extension', ['html' => (string) ($page['html'] ?? ''), 'title' => (string) ($page['title'] ?? ''), 'ext' => $id], (string) ($page['title'] ?? ''));
    }

    /** Entrées du menu de l'administration, filtrées par droit. */
    public static function nav(): array
    {
        $out = [];
        foreach (self::active() as $id => $m) {
            foreach ($m['nav'] as $n) {
                if (Auth::can($n['right'])) {
                    $out[] = ['x/' . $id . ($n['path'] !== '' ? '/' . trim($n['path'], '/') : ''), $n['icon'], $n['label'], $n['right']];
                }
            }
            if ($m['settings'] && Auth::can('settings') && !$m['nav']) {
                $out[] = ['x/' . $id . '/settings', 'settings', $m['name'], 'settings'];
            }
        }
        return $out;
    }

    /** Cartes du tableau de bord : titre, texte et lien fournis par la capacité, échappés par la vue du noyau. */
    public static function cards(array $user): array
    {
        $out = [];
        foreach (self::active() as $id => $m) {
            foreach ($m['dashboard'] as $d) {
                if (!Auth::can($d['right'])) {
                    continue;
                }
                $card = self::call($id, $d['handler'], [$user, self::context($id)]);
                if (is_array($card) && is_string($card['title'] ?? null)) {
                    $link = (string) ($card['link'] ?? '');
                    $out[] = ['title' => mb_substr($card['title'], 0, 80), 'text' => mb_substr((string) ($card['text'] ?? ''), 0, 300),
                        'link' => preg_match('~^[a-z0-9/_-]*$~', $link) ? $link : '', 'ext' => $id];
                }
            }
        }
        return $out;
    }

    /* ─────────────────────────────── Blocs ─────────────────────────────── */

    /** Déclaration d'un bloc d'une capacité active, ou null. */
    public static function block(string $type): ?array
    {
        $id = explode('/', $type, 2)[0];
        foreach ((array) (self::active()[$id]['blocks'] ?? []) as $b) {
            if ($b['type'] === $type) {
                return $b;
            }
        }
        return null;
    }

    /** Bloc d'une capacité active, ses champs validés par le noyau (déclaration) ; null s'il n'est pas géré ici. */
    public static function normalizeBlock(array $b): ?array
    {
        $def = self::block((string) ($b['type'] ?? ''));
        if ($def === null) {
            return null;
        }
        $b = Blocks::original($b);
        return ['type' => $def['type']] + self::clean($def['fields'], $b);
    }

    /** HTML d'un bloc d'une capacité active (le rendu est celui de la capacité ; vide si elle est absente). */
    public static function renderBlock(array $b, array $ctx): string
    {
        // Bloc conservé pendant que la fonction était absente : repris tel quel dès qu'elle est de nouveau active.
        if (!empty($b['preserved'])) {
            $b = self::normalizeBlock($b);
            if ($b === null) {
                return '';
            }
        }
        $def = self::block((string) ($b['type'] ?? ''));
        if ($def === null) {
            return '';
        }
        $id = explode('/', $def['type'], 2)[0];
        $html = self::call($id, $def['render'], [$b, $ctx + self::context($id)]);
        return is_string($html) ? $html : '';
    }

    /** Blocs proposés par les capacités actives, pour l'éditeur (type, libellé, champs). */
    public static function editorBlocks(): array
    {
        $out = [];
        foreach (self::active() as $m) {
            foreach ($m['blocks'] as $b) {
                $out[$b['type']] = ['label' => $b['label'], 'fields' => $b['fields']];
            }
        }
        return $out;
    }

    /* ─────────────────────────────── Réglages ─────────────────────────────── */

    /** Valeurs propres des champs déclarés (texte borné, adresse sûre, nombre, case, choix parmi la liste). */
    public static function clean(array $fields, array $input): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $input[$f['key']] ?? $f['default'];
            $out[$f['key']] = match ($f['type']) {
                'bool' => (bool) $v && $v !== '0',
                'number' => is_numeric($v) ? max(-1e9, min(1e9, $v + 0)) : (is_numeric($f['default']) ? $f['default'] + 0 : 0),
                'select' => in_array($v, $f['options'], true) ? $v : (string) ($f['options'][0] ?? ''),
                'url' => preg_match('~^(https?://|/)[^\s<>"\']{0,500}$~', (string) $v) ? (string) $v : '',
                'textarea' => Sanitize::text(is_string($v) ? $v : '', $f['max']),
                default => Sanitize::text(is_scalar($v) ? (string) $v : '', $f['max']),
            };
        }
        return $out;
    }

    public static function settings(string $id): array
    {
        $m = self::active()[$id] ?? (self::state()['items'][$id]['manifest'] ?? null);
        $stored = Store::read('ext/' . $id . '/settings', []);
        return is_array($m) ? self::clean($m['settings'], is_array($stored) ? $stored : []) : [];
    }

    /** Page des réglages d'une capacité : formulaire généré par le noyau à partir du manifeste. */
    private static function settingsPage(string $id): never
    {
        $m = self::active()[$id];
        if (Req::method() === 'POST') {
            $input = [];
            foreach ($m['settings'] as $f) {
                $input[$f['key']] = $f['type'] === 'bool' ? (Req::input($f['key']) === '1') : Req::input($f['key']);
            }
            Store::write('ext/' . $id . '/settings', self::clean($m['settings'], $input));
            Cache::purge();
            Admin::back('x/' . $id . '/settings', t('Réglages enregistrés.'));
        }
        Admin::view('extension', ['ext' => $id, 'title' => $m['name'], 'fields' => $m['settings'], 'values' => self::settings($id)], $m['name']);
    }

    /* ─────────────────────────────── Tâches ─────────────────────────────── */

    /** Adresse secrète de la tâche planifiée des capacités (à appeler chaque heure par le cron de l'hébergeur). */
    public static function taskUrl(): string
    {
        $token = (string) (self::state()['token'] ?? '');
        return $token !== '' ? absolute_url('/_eclair/tasks/' . $token) : '';
    }

    /** Tâches dues des capacités actives (adresse secrète), chacune au plus une fois par période déclarée. */
    public static function tick(string $token): never
    {
        $state = self::state();
        if (!is_string($state['token'] ?? null) || !hash_equals($state['token'], $token)) {
            Http::send('Not Found', 404, ['Content-Type: text/plain; charset=utf-8']);
        }
        $ran = [];
        foreach (self::active() as $id => $m) {
            foreach ($m['tasks'] as $task) {
                $key = $id . ':' . $task['handler'];
                if ((int) ($state['ran'][$key] ?? 0) + $task['every'] > time()) {
                    continue;
                }
                $state['ran'][$key] = time();
                self::save($state);
                self::call($id, $task['handler'], [self::context($id)]);
                $ran[] = $key;
            }
        }
        Http::json(['ok' => true, 'ran' => $ran]);
    }

    /** Adresse publique d'un actif déclaré d'une capacité active. */
    public static function asset(string $id, string $file): string
    {
        if (!in_array($file, (array) (self::active()[$id]['assets'] ?? []), true)) {
            return '';
        }
        $base = str_starts_with(Themes::siteDir(), ECLAIR_ROOT . '/') ? substr(Themes::siteDir(), strlen(ECLAIR_ROOT)) : '/site';
        return Req::base() . $base . '/assets/ext-' . $id . '/' . $file . '?v=' . substr(md5((string) self::active()[$id]['version']), 0, 8);
    }

    /* ─────────────────────────────── Découverte ─────────────────────────────── */

    /**
     * Description structurée de ce que ce site sait faire, pour les outils et les futurs skills : versions, contrats,
     * blocs, capacités, thèmes, droits. Aucun secret, aucune donnée personnelle, aucun chemin du serveur.
     */
    public static function describe(): array
    {
        $exts = [];
        foreach (self::installed() as $id => $m) {
            $exts[] = ['id' => $id, 'name' => (string) ($m['name'] ?? $id), 'version' => (string) ($m['version'] ?? ''), 'status' => (string) $m['status'],
                'provides' => (array) ($m['provides'] ?? []), 'blocks' => array_column((array) ($m['blocks'] ?? []), 'type'),
                'routes' => array_column((array) ($m['routes']['public'] ?? []), 'path'), 'compatible' => $m['errors'] === []];
        }
        $themes = [];
        $active = (string) (Themes::state()['active']['id'] ?? '');
        foreach (Themes::installed() as $id => $t) {
            $themes[] = ['id' => $id, 'version' => (string) ($t['version'] ?? ''), 'contract' => (int) ($t['contract'] ?? 0), 'active' => $id === $active, 'valid' => $t['errors'] === []];
        }
        return [
            'eclair' => defined('ECLAIR_VERSION') ? ECLAIR_VERSION : '',
            'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'contracts' => ['theme' => Themes::SUPPORTED, 'theme_schema' => Themes::SCHEMA, 'extension_schema' => self::SCHEMA,
                'templates' => Themes::TEMPLATES, 'overrides' => Themes::OVERRIDES, 'fields' => self::FIELDS],
            'blocks' => ['core' => Blocks::TYPES, 'extensions' => array_keys(self::editorBlocks())],
            'extensions' => $exts,
            'themes' => $themes,
            'rights' => self::RIGHTS,
            'languages' => array_keys(I18n::LANGS),
        ];
    }
}
