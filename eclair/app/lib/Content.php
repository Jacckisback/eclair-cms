<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Pages et articles. Chaque contenu est un fichier data/content/<id>.php ;
 * data/index.php garde un résumé de tous les contenus pour lister et router vite.
 */
final class Content
{
    public const LANGS = ['fr' => 'Français', 'es' => 'Español', 'en' => 'English', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português'];
    private const MAX_REVISIONS = 20;
    /**
     * Nombre maximal de contenus (pages, articles, produits, brouillons compris), bien au-delà de ce qu'atteint le site d'une
     * petite entreprise. data/index.php les résume tous : relu à chaque page générée et réécrit à chaque enregistrement, il ne
     * doit pas pouvoir grossir sans fin (boucle d'une IA ou d'un connecteur, compte malveillant) jusqu'à bloquer le site.
     */
    public const MAX_ITEMS = 3000;
    /**
     * Taille maximale de l'index de recherche (data/search.php : le texte des contenus publiés), relu en entier à chaque
     * recherche d'un visiteur. 16 Mo, c'est plus de 1 500 longs articles ; au-delà, la recherche épuiserait la mémoire de PHP.
     */
    public const MAX_SEARCH_BYTES = 16_000_000;
    /** Texte cherchable gardé par contenu (caractères). */
    private const SEARCH_TEXT = 30000;
    /** Termes d'une recherche pris en compte : chacun parcourt tout l'index de recherche. */
    private const MAX_TERMS = 8;

    private static ?array $index = null;

    /* ─────────────────────────────── Lecture ─────────────────────────────── */

    /** Index complet : id → résumé. */
    public static function index(): array
    {
        if (self::$index === null) {
            $index = Store::read('index');
            self::$index = is_array($index) ? $index : self::reindex();
        }
        return self::$index;
    }

    /** Oublie l'index gardé pour la requête : la prochaine lecture repart du disque (changements faits par d'autres requêtes). */
    public static function forget(): void
    {
        self::$index = null;
    }

    public static function get(string $id): ?array
    {
        if (!preg_match('/^[a-z0-9]{1,32}$/', $id)) {
            return null;
        }
        $item = Store::read('content/' . $id);
        return is_array($item) ? $item : null;
    }

    public static function visible(array $entry): bool
    {
        // Les produits ne sont en ligne que si la boutique est ouverte.
        return $entry['status'] === 'published' && (int) $entry['date'] <= time() && ($entry['type'] !== 'product' || Shop::enabled());
    }

    /** Contenus visibles d'un type, du plus récent au plus ancien. */
    public static function published(?string $type = null): array
    {
        $out = array_filter(self::index(), fn (array $e): bool => self::visible($e) && ($type === null || $e['type'] === $type));
        uasort($out, fn (array $a, array $b): int => $b['date'] <=> $a['date']);
        return $out;
    }

    public static function latest(int $count, string $category = '', string $exclude = ''): array
    {
        $posts = array_filter(self::published('post'), fn ($p) => ($category === '' || $p['category'] === $category) && $p['id'] !== $exclude);
        return array_slice(array_values($posts), 0, $count);
    }

    /** Chemin public d'un contenu (sans le dossier d'installation). */
    public static function path(array $entry): string
    {
        if ($entry['type'] === 'page' && $entry['id'] === Settings::get('home')) {
            return '/';
        }
        if (($entry['route'] ?? '') !== '') {
            return (string) $entry['route'];
        }
        return match ($entry['type']) {
            'post' => self::blogPath() . '/' . $entry['slug'],
            'product' => Shop::path() . '/' . $entry['slug'],
            default => '/' . $entry['slug'],
        };
    }

    public static function blogPath(): string
    {
        return '/' . Settings::get('blog.base', 'blog');
    }

    /** Contenu publié correspondant à un chemin. */
    public static function findByPath(string $path): ?array
    {
        foreach (self::published() as $entry) {
            if (self::path($entry) === $path) {
                return $entry;
            }
        }
        return null;
    }

    /** Contenu en ligne dont l'adresse exacte (route) est $path, ou null. */
    public static function byRoute(string $path): ?array
    {
        foreach (self::index() as $entry) {
            if (($entry['route'] ?? '') === $path && $entry['id'] !== Settings::get('home') && self::visible($entry)) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Adresse exacte valide d'un ancien site, ou '' : commence par « / », segments de lettres (accents compris), chiffres,
     * « - », « _ », « . » et « ~ », « / » final permis, au plus 8 segments et 300 octets ; jamais « . » ni « .. », jamais
     * une adresse réservée d'Éclair (administration, médias, actifs, plan du site…), jamais un script (.php).
     */
    public static function cleanRoute(string $raw): string
    {
        $path = trim($raw);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 300 || !mb_check_encoding($path, 'UTF-8') || str_contains($path, '//')) {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $path = (string) \Normalizer::normalize($path, \Normalizer::FORM_C);
        }
        $segments = explode('/', trim($path, '/'));
        if ($path === '/' || count($segments) > 8) {
            return '';
        }
        foreach ($segments as $seg) {
            if ($seg === '.' || $seg === '..' || !preg_match('#^[\p{L}\p{M}\p{N}._~-]{1,120}$#u', $seg)) {
                return '';
            }
        }
        $first = strtolower($segments[0]);
        if (in_array($first, Settings::reserved(), true) || in_array(pathinfo($first, PATHINFO_FILENAME), Settings::reserved(), true)
            || in_array($first, ['site', '.well-known'], true) || preg_match('~\.(php\d?|phtml|phar|inc|md|xml|txt)$~i', $path)) {
            return '';
        }
        return $path;
    }

    /** Adresse déjà servie par un autre contenu (adresse exacte ou adresse habituelle, avec ou sans « / » final). */
    public static function routeTaken(string $route, string $id, ?array $index = null): bool
    {
        $bare = rtrim($route, '/');
        foreach ($index ?? self::index() as $e) {
            if ($e['id'] !== $id && in_array(rtrim(self::path($e), '/'), [$bare], true)) {
                return true;
            }
        }
        return false;
    }

    /** Première page publiée trouvée parmi plusieurs slugs possibles. */
    public static function findBySlugAny(array $slugs): ?array
    {
        foreach (self::published('page') as $entry) {
            if (in_array($entry['slug'], $slugs, true)) {
                return $entry;
            }
        }
        return null;
    }

    /** Traductions publiées (même groupe, autre langue) → [lang => entrée]. */
    public static function translations(array $entry): array
    {
        if (($entry['tgroup'] ?? '') === '') {
            return [];
        }
        $out = [];
        foreach (self::published() as $other) {
            if (($other['tgroup'] ?? '') === $entry['tgroup'] && !($other['noindex'] ?? false)) {
                $out[$other['lang']] = $other;
            }
        }
        return count($out) > 1 ? $out : [];
    }

    /** Date de parution du prochain article programmé (0 si aucun). */
    public static function nextScheduled(): int
    {
        $next = 0;
        foreach (self::index() as $e) {
            if ($e['status'] === 'published' && (int) $e['date'] > time() && ($next === 0 || (int) $e['date'] < $next)) {
                $next = (int) $e['date'];
            }
        }
        return $next;
    }

    /** Termes d'une recherche : en minuscules, au moins 2 caractères, sans doublon, MAX_TERMS au plus. */
    public static function terms(string $query): array
    {
        $terms = array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [], fn ($t) => mb_strlen($t) >= 2);
        return array_slice(array_values(array_unique($terms)), 0, self::MAX_TERMS);
    }

    /** Recherche plein texte simple, pondérée (titre > résumé > texte). */
    public static function search(string $query, int $limit = 30): array
    {
        $terms = self::terms($query);
        if (!$terms) {
            return [];
        }
        $texts = Store::read('search', []);
        $results = [];
        foreach (self::published() as $id => $entry) {
            if ($entry['noindex'] ?? false) {
                continue;
            }
            $title = mb_strtolower($entry['title']);
            $summary = mb_strtolower($entry['excerpt'] . ' ' . ($entry['summary'] ?? ''));
            $body = mb_strtolower((string) ($texts[$id] ?? ''));
            $score = 0;
            foreach ($terms as $term) {
                $hit = (str_contains($title, $term) ? 6 : 0) + (str_contains($summary, $term) ? 3 : 0) + min(5, substr_count($body, $term));
                if ($hit === 0) {
                    continue 2;
                }
                $score += $hit;
            }
            $results[] = ['score' => $score, 'entry' => $entry];
        }
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['entry']['date'] <=> $a['entry']['date']);
        return array_map(fn ($r) => $r['entry'], array_slice($results, 0, $limit));
    }

    /* ────────────────────────────── Écriture ─────────────────────────────── */

    /**
     * Crée ou met à jour un contenu à partir des données de l'éditeur.
     * Gère : slug unique, redirection 301 si l'URL change, révisions, index, cache.
     * $expect (enregistrement conditionnel, outils de l'IA) reçoit la version enregistrée, relue sous verrou (null pour un
     * nouveau contenu) : s'il ne l'accepte pas, rien n'est écrit (UnexpectedValueException).
     * Un nouveau contenu au-delà de MAX_ITEMS, ou un texte qui ferait déborder l'index de recherche, est refusé (OverflowException).
     */
    public static function save(array $data, array $user, ?callable $expect = null): array
    {
        // Dès la première écriture, le cache est vidé même si une écriture suivante échoue (index de recherche sur un disque
        // plein…) : une page dépubliée n'y reste jamais servie.
        $written = false;
        try {
            $result = Store::locked(function () use ($data, $user, $expect, &$written): array {
                self::$index = null;
                $index = self::index();
                $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
                $old = $id !== '' && isset($index[$id]) ? self::get($id) : null;
                if ($expect !== null && !$expect($old)) {
                    throw new \UnexpectedValueException('Contenu modifié entre-temps.');
                }
                if ($old === null && count($index) >= self::MAX_ITEMS) {
                    throw new \OverflowException(t('Le site compte déjà {n} contenus, le maximum : supprime ceux qui ne servent plus avant d’en créer d’autres.', ['n' => self::MAX_ITEMS]));
                }
                $item = self::build($data, $user, $old, $index);
                $id = $item['id'];
                $entry = self::entry($item);
                $oldEntry = $index[$id] ?? null;
                $index[$id] = $entry;
                // Index de recherche préparé (et sa taille vérifiée) avant toute écriture : un refus ne laisse rien à moitié enregistré.
                $search = self::searchTexts($index, $id, $item['status'] === 'published' ? Blocks::text($item['blocks']) : null);
                $written = true;
                if ($old !== null) {
                    self::addRevision($old);
                    if ($oldEntry !== null && self::visible($oldEntry)) {
                        $from = self::path($oldEntry);
                        $to = self::path($entry);
                        if ($from !== $to && $from !== '/') {
                            Redirects::add($from, $to, true);
                        }
                    }
                }
                // L'index décide de ce qui est en ligne. Un contenu qui le quitte (brouillon, date future) y est inscrit avant d'être
                // écrit : une écriture interrompue (disque plein…) laisse toujours la version la moins visible, jamais un brouillon public.
                if ($old !== null && !self::visible($entry)) {
                    Store::write('index', $index);
                    Store::write('content/' . $id, $item);
                } else {
                    Store::write('content/' . $id, $item);
                    Store::write('index', $index);
                }
                self::$index = $index;
                if ($search !== null) {
                    Store::write('search', $search);
                }
                if ($item['status'] !== 'draft') {
                    Redirects::remove(self::path($entry));
                }
                return $item;
            });
        } finally {
            if ($written) {
                Cache::purge();
            }
        }
        return $result;
    }

    /**
     * Construit un contenu propre et validé à partir des données de l'éditeur, sans l'enregistrer
     * (sert aussi à l'analyse SEO en direct et à l'aperçu).
     */
    public static function build(array $data, array $user, ?array $old = null, ?array $index = null): array
    {
        $index ??= self::index();
        $id = $old['id'] ?? random_id();
        $now = time();
        $type = $old['type'] ?? (in_array($data['type'] ?? '', ['page', 'product'], true) ? (string) $data['type'] : 'post');
        $lang = array_key_exists($data['lang'] ?? '', self::LANGS) ? (string) $data['lang'] : (string) Settings::get('lang');
        $title = Sanitize::text($data['title'] ?? '', 200);
        $title = $title !== '' ? $title : t('Sans titre');
        $status = ($data['status'] ?? '') === 'published' ? 'published' : 'draft';

        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $type === 'page' ? Sanitize::path($slug !== '' ? $slug : $title, $lang) : slugify($slug !== '' ? $slug : $title, $lang);
        // Un contenu existant garde son adresse tant qu'on ne la change pas, même si le mot est devenu réservé depuis.
        if ($old === null || $slug !== $old['slug']) {
            $slug = self::uniqueSlug($slug !== '' ? $slug : $id, $type, $id, $index);
        }

        $date = (int) ($old['date'] ?? 0);
        if (!empty($data['date'])) {
            $parsed = strtotime((string) $data['date']);
            $date = $parsed !== false ? $parsed : $date;
        } elseif ($status === 'published' && ($old['status'] ?? 'draft') === 'draft') {
            $date = $now;
        }
        if ($date === 0) {
            $date = $now;
        }

        // Adresse exacte (migration d'un ancien site : /2024/03/article/, /page.html, accents) : gardée tant qu'on ne la change
        // pas, abandonnée si elle n'est plus valide ou déjà prise par un autre contenu.
        $route = array_key_exists('route', $data) ? self::cleanRoute((string) $data['route']) : (string) ($old['route'] ?? '');
        if ($route !== '' && self::routeTaken($route, $id, $index)) {
            $route = '';
        }

        $seo = is_array($data['seo'] ?? null) ? $data['seo'] : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $category = preg_replace('/[^a-z0-9]/', '', (string) ($data['category'] ?? '')) ?? '';
        $author = preg_replace('/[^a-z0-9]/', '', (string) ($data['author'] ?? '')) ?? '';
        $toc = (string) ($options['toc'] ?? 'auto');

        $item = [
            'id' => $id,
            'type' => $type,
            'status' => $status,
            'title' => $title,
            'slug' => $slug,
            'route' => $route,
            'lang' => $lang,
            'tgroup' => preg_replace('/[^a-z0-9]/', '', (string) ($data['tgroup'] ?? $old['tgroup'] ?? '')) ?? '',
            'blocks' => Blocks::normalize($data['blocks'] ?? []),
            'summary' => Sanitize::multiline($data['summary'] ?? '', 800),
            'image' => preg_replace('/[^a-z0-9]/', '', (string) ($data['image'] ?? '')) ?? '',
            'category' => $type !== 'page' && (Categories::get($category)['kind'] ?? '') === $type ? $category : '',
            'author' => Users::find($author) ? $author : (string) ($old['author'] ?? $user['id']),
            'created' => (int) ($old['created'] ?? $now),
            'date' => $date,
            'updated' => $now,
            'seo' => [
                'title' => Sanitize::text($seo['title'] ?? '', 120),
                'description' => Sanitize::text($seo['description'] ?? '', 320),
                'keyword' => Sanitize::text($seo['keyword'] ?? '', 80),
                'canonical' => Sanitize::url((string) ($seo['canonical'] ?? '')),
                'noindex' => !empty($seo['noindex']),
            ],
            'options' => [
                'hide_title' => !empty($options['hide_title']),
                'toc' => in_array($toc, ['auto', 'on', 'off'], true) ? $toc : 'auto',
                'wide' => !empty($options['wide']),
                // « Sans publicité » (éditeur, quand la publicité est en service) : aucune annonce sur ce contenu.
                'noads' => !empty($options['noads']),
            ],
        ];
        if ($type === 'product') {
            $item['product'] = Shop::normalize($data['product'] ?? []);
        }
        $analysis = Analyzer::run($item);
        $item['score'] = ['seo' => $analysis['seo'], 'geo' => $analysis['geo']];
        return $item;
    }

    /** Résumé d'un contenu pour l'index. */
    public static function entry(array $item): array
    {
        $text = Blocks::text($item['blocks']);
        $excerpt = $item['seo']['description'] !== '' ? $item['seo']['description'] : excerpt(Blocks::intro($item['blocks']) ?: $text, 220);
        return [
            'id' => $item['id'],
            'type' => $item['type'],
            'status' => $item['status'],
            'title' => $item['title'],
            'slug' => $item['slug'],
            'lang' => $item['lang'],
            'tgroup' => $item['tgroup'],
            'date' => $item['date'],
            'updated' => $item['updated'],
            'created' => $item['created'],
            'category' => $item['category'],
            'author' => $item['author'],
            'image' => $item['image'],
            'excerpt' => $excerpt,
            'summary' => excerpt($item['summary'], 300),
            'noindex' => $item['seo']['noindex'],
            'words' => word_count($text),
            'score' => $item['score'] ?? ['seo' => 0, 'geo' => 0],
        ] + (($item['route'] ?? '') !== '' ? ['route' => $item['route']] : []) + (isset($item['product']) ? self::productEntry($item) : []);
    }

    /** Résumé d'un produit pour l'index : prix le plus bas (« à partir de » si les variantes diffèrent), disponibilité. */
    private static function productEntry(array $item): array
    {
        $offers = Shop::offers($item);
        $prices = array_values(array_unique(array_filter(array_column($offers, 'price'), fn ($p) => $p !== '')));
        sort($prices, SORT_NUMERIC);
        // Disponibilité affichée : la meilleure des offres (« En stock » dès qu'une variante l'est, « Épuisé » si toutes le sont).
        $states = array_column($offers, 'availability');
        return [
            'price' => $prices[0] ?? '',
            'from' => count($prices) > 1,
            'compare' => $item['product']['compare'],
            'availability' => in_array('in_stock', $states, true) ? 'in_stock' : (in_array('preorder', $states, true) ? 'preorder' : 'out_of_stock'),
        ];
    }

    public static function delete(string $id, string $redirectTo = ''): void
    {
        // Dès la première écriture, le cache est vidé même si une écriture suivante échoue (voir save()).
        $written = false;
        try {
            Store::locked(function () use ($id, $redirectTo, &$written): void {
                self::$index = null;
                $index = self::index();
                if (!isset($index[$id])) {
                    return;
                }
                $entry = $index[$id];
                $written = true;
                // Le fichier d'abord : s'il ne peut pas être supprimé (droits du dossier), rien ne change et l'erreur est signalée,
                // au lieu d'un fichier orphelin qu'une reconstruction de l'index remettrait en ligne.
                Store::delete('content/' . $id);
                if (is_file(Store::file('content/' . $id))) {
                    throw new \RuntimeException("Impossible de supprimer « content/" . $id . '.php » : vérifie les droits du dossier.');
                }
                if (self::visible($entry) && $redirectTo !== '') {
                    Redirects::add(self::path($entry), $redirectTo, false);
                }
                unset($index[$id]);
                Store::write('index', $index);
                self::$index = $index;
                foreach (Store::names('revisions/' . $id) as $rev) {
                    Store::delete($rev);
                }
                @rmdir(ECLAIR_DATA . '/revisions/' . $id);
                $search = self::searchTexts($index, $id, null);
                if ($search !== null) {
                    Store::write('search', $search);
                }
                if (Settings::get('home') === $id) {
                    Settings::save(['home' => '']);
                }
            });
        } finally {
            if ($written) {
                Cache::purge();
            }
        }
    }

    public static function duplicate(string $id, array $user): ?array
    {
        $item = self::get($id);
        if (!$item) {
            return null;
        }
        $item['id'] = '';
        $item['status'] = 'draft';
        $item['title'] = $item['title'] . ' ' . t('(copie)');
        $item['slug'] = $item['slug'] . '-copie';
        $item['tgroup'] = '';
        $item['date'] = '';
        return self::save($item, $user);
    }

    /**
     * Relie deux contenus comme traductions l'un de l'autre. Sous verrou, à partir des versions enregistrées : un
     * enregistrement concurrent (un contenu dépublié pendant ce temps) n'est jamais écrasé par une copie périmée.
     */
    public static function linkTranslation(string $id, string $otherId): void
    {
        $linked = Store::locked(function () use ($id, $otherId): bool {
            self::$index = null;
            $index = self::index();
            $a = isset($index[$id]) ? self::get($id) : null;
            $b = isset($index[$otherId]) ? self::get($otherId) : null;
            if (!$a || !$b || $id === $otherId) {
                return false;
            }
            $group = $b['tgroup'] !== '' ? $b['tgroup'] : ($a['tgroup'] !== '' ? $a['tgroup'] : $b['id']);
            foreach ([$a, $b] as $item) {
                $item['tgroup'] = $group;
                Store::write('content/' . $item['id'], $item);
                $index[$item['id']] = self::entry($item);
            }
            Store::write('index', $index);
            self::$index = $index;
            return true;
        });
        if ($linked) {
            Cache::purge();
        }
    }

    /**
     * Reconstruit l'index à partir des fichiers (réparation). Sous verrou, les contenus relus sous ce verrou : un
     * enregistrement concurrent (un contenu dépublié pendant la reconstruction) n'est jamais écrasé par l'état d'avant.
     */
    public static function reindex(): array
    {
        return Store::locked(function (): array {
            $previous = Store::read('index');
            $index = [];
            foreach (Store::names('content') as $name) {
                $item = Store::read($name);
                if (is_array($item) && isset($item['id'], $item['blocks'])) {
                    // Fichier que l'index lisible ne connaît pas (laissé par une suppression ratée avant la 1.10.3) : il revient
                    // en brouillon, à relire, et jamais en ligne avec son ancien statut. Sans index lisible, rien ne change.
                    if (is_array($previous) && !isset($previous[$item['id']]) && ($item['status'] ?? '') !== 'draft') {
                        $item['status'] = 'draft';
                        Store::write($name, $item);
                    }
                    $index[$item['id']] = self::entry($item);
                }
            }
            if (Settings::installed()) {
                Store::write('index', $index);
            }
            return self::$index = $index;
        });
    }

    /* ────────────────────────────── Révisions ─────────────────────────────── */

    private static function addRevision(array $item): void
    {
        Store::write('revisions/' . $item['id'] . '/' . $item['updated'] . '-' . substr(bin2hex(random_bytes(2)), 0, 4), $item);
        $names = Store::names('revisions/' . $item['id']);
        sort($names);
        foreach (array_slice($names, 0, max(0, count($names) - self::MAX_REVISIONS)) as $name) {
            Store::delete($name);
        }
    }

    public static function revisions(string $id): array
    {
        $out = [];
        foreach (Store::names('revisions/' . preg_replace('/[^a-z0-9]/', '', $id)) as $name) {
            $rev = Store::read($name);
            if (is_array($rev)) {
                $out[] = ['name' => basename($name), 'updated' => (int) $rev['updated'], 'title' => $rev['title'], 'words' => word_count(Blocks::text($rev['blocks']))];
            }
        }
        usort($out, fn ($a, $b) => $b['updated'] <=> $a['updated']);
        return $out;
    }

    public static function revision(string $id, string $name): ?array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) {
            return null;
        }
        $rev = Store::read('revisions/' . preg_replace('/[^a-z0-9]/', '', $id) . '/' . $name);
        return is_array($rev) ? $rev : null;
    }

    /* ─────────────────────────────── Outils ─────────────────────────────── */

    /** Slug libre dans son espace d'URL (racine pour les pages, blog pour les articles). */
    public static function uniqueSlug(string $slug, string $type, string $id, ?array $index = null): string
    {
        $index ??= self::index();
        $taken = [];
        foreach ($index as $e) {
            if ($e['id'] !== $id && $e['type'] === $type) {
                $taken[$e['slug']] = true;
            }
        }
        if ($type === 'page') {
            foreach (Settings::reserved() as $word) {
                $taken[$word] = true;
            }
        } else {
            foreach (Categories::of($type) as $cat) {
                $taken[$cat['slug']] = true;
            }
            $words = Theme::words();
            $taken[$words['page']] = $taken[$words['author']] = $taken['feed'] = true;
        }
        $first = $type === 'page' ? explode('/', $slug)[0] : $slug;
        if (!isset($taken[$slug]) && !($type === 'page' && in_array($first, Settings::reserved(), true))) {
            return $slug;
        }
        for ($n = 2; ; $n++) {
            if (!isset($taken[$slug . '-' . $n])) {
                return $slug . '-' . $n;
            }
        }
    }

    /**
     * Index de recherche (data/search.php) après l'écriture d'un contenu, ou null s'il ne change pas. Seul le texte des
     * contenus publiés (programmés compris) y figure : la recherche publique ne lit jamais celui d'un brouillon, et l'assistant
     * retrouve les brouillons par leur titre, leur adresse et leur extrait. Les textes que d'anciennes versions gardaient pour
     * les brouillons partent au passage. Un texte qui ferait dépasser MAX_SEARCH_BYTES est refusé (OverflowException) ;
     * retirer ou raccourcir un texte reste toujours possible.
     */
    private static function searchTexts(array $index, string $id, ?string $text): ?array
    {
        $current = Store::read('search', []);
        $current = is_array($current) ? $current : [];
        $all = array_intersect_key($current, array_filter($index, fn (array $e): bool => $e['status'] === 'published'));
        if ($text === null) {
            unset($all[$id]);
        } else {
            $all[$id] = mb_substr($text, 0, self::SEARCH_TEXT);
            $size = static fn (string $s): int => strlen((string) json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP));
            $grows = $size($all[$id]) - (isset($current[$id]) ? $size((string) $current[$id]) : 0);
            // Estimation par la taille du fichier actuel, puis mesure exacte seulement si elle dépasse la limite.
            if ($grows > 0 && (int) @filesize(Store::file('search')) + $grows + strlen($id) + 8 > self::MAX_SEARCH_BYTES
                && strlen((string) json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP)) > self::MAX_SEARCH_BYTES) {
                throw new \OverflowException(t('L’index de recherche du site est plein : supprime ou raccourcis des contenus publiés avant d’en publier d’autres.'));
            }
        }
        return $all === $current ? null : $all;
    }

    /** Durée de lecture en minutes. */
    public static function readingTime(int $words): int
    {
        return max(1, (int) round($words / 220));
    }
}
