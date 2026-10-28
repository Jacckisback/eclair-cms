<?php
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
        return $entry['status'] === 'published' && (int) $entry['date'] <= time();
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
        return $entry['type'] === 'post' ? self::blogPath() . '/' . $entry['slug'] : '/' . $entry['slug'];
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

    /** Recherche plein texte simple, pondérée (titre > résumé > texte). */
    public static function search(string $query, int $limit = 30): array
    {
        $terms = array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [], fn ($t) => mb_strlen($t) >= 2);
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
     */
    public static function save(array $data, array $user): array
    {
        $result = Store::locked(function () use ($data, $user): array {
            self::$index = null;
            $index = self::index();
            $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
            $old = $id !== '' && isset($index[$id]) ? self::get($id) : null;
            $item = self::build($data, $user, $old, $index);
            $id = $item['id'];
            if ($old !== null) {
                self::addRevision($old);
                $oldEntry = $index[$id];
                if (self::visible($oldEntry)) {
                    $from = self::path($oldEntry);
                    $to = self::path(self::entry($item));
                    if ($from !== $to && $from !== '/') {
                        Redirects::add($from, $to, true);
                    }
                }
            }
            Store::write('content/' . $id, $item);
            $index[$id] = self::entry($item);
            Store::write('index', $index);
            self::$index = $index;
            self::storeSearchText($id, Blocks::text($item['blocks']));
            Redirects::remove(self::path($index[$id]));
            return $item;
        });
        Cache::purge();
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
        $type = $old['type'] ?? (($data['type'] ?? '') === 'page' ? 'page' : 'post');
        $lang = array_key_exists($data['lang'] ?? '', self::LANGS) ? (string) $data['lang'] : (string) Settings::get('lang');
        $title = Sanitize::text($data['title'] ?? '', 200);
        $title = $title !== '' ? $title : t('Sans titre');
        $status = ($data['status'] ?? '') === 'published' ? 'published' : 'draft';

        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $type === 'page' ? Sanitize::path($slug !== '' ? $slug : $title, $lang) : slugify($slug !== '' ? $slug : $title, $lang);
        $slug = self::uniqueSlug($slug !== '' ? $slug : $id, $type, $id, $index);

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
            'lang' => $lang,
            'tgroup' => preg_replace('/[^a-z0-9]/', '', (string) ($data['tgroup'] ?? $old['tgroup'] ?? '')) ?? '',
            'blocks' => Blocks::normalize($data['blocks'] ?? []),
            'summary' => Sanitize::multiline($data['summary'] ?? '', 800),
            'image' => preg_replace('/[^a-z0-9]/', '', (string) ($data['image'] ?? '')) ?? '',
            'category' => $type === 'post' && Categories::get($category) ? $category : '',
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
            ],
        ];
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
        ];
    }

    public static function delete(string $id, string $redirectTo = ''): void
    {
        Store::locked(function () use ($id, $redirectTo): void {
            self::$index = null;
            $index = self::index();
            if (!isset($index[$id])) {
                return;
            }
            $entry = $index[$id];
            if (self::visible($entry) && $redirectTo !== '') {
                Redirects::add(self::path($entry), $redirectTo, false);
            }
            unset($index[$id]);
            Store::write('index', $index);
            self::$index = $index;
            Store::delete('content/' . $id);
            foreach (Store::names('revisions/' . $id) as $rev) {
                Store::delete($rev);
            }
            @rmdir(ECLAIR_DATA . '/revisions/' . $id);
            self::storeSearchText($id, null);
            if (Settings::get('home') === $id) {
                Settings::save(['home' => '']);
            }
        });
        Cache::purge();
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

    /** Relie deux contenus comme traductions l'un de l'autre. */
    public static function linkTranslation(string $id, string $otherId): void
    {
        $a = self::get($id);
        $b = self::get($otherId);
        if (!$a || !$b || $id === $otherId) {
            return;
        }
        $group = $b['tgroup'] !== '' ? $b['tgroup'] : ($a['tgroup'] !== '' ? $a['tgroup'] : $b['id']);
        foreach ([$a, $b] as $item) {
            $item['tgroup'] = $group;
            Store::write('content/' . $item['id'], $item);
        }
        self::reindex();
        Cache::purge();
    }

    /** Reconstruit l'index à partir des fichiers (réparation). */
    public static function reindex(): array
    {
        $index = [];
        foreach (Store::names('content') as $name) {
            $item = Store::read($name);
            if (is_array($item) && isset($item['id'], $item['blocks'])) {
                $index[$item['id']] = self::entry($item);
            }
        }
        if (Settings::installed()) {
            Store::write('index', $index);
        }
        return self::$index = $index;
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
            foreach (Categories::all() as $cat) {
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

    private static function storeSearchText(string $id, ?string $text): void
    {
        $all = Store::read('search', []);
        $all = is_array($all) ? $all : [];
        if ($text === null) {
            unset($all[$id]);
        } else {
            $all[$id] = mb_substr($text, 0, 30000);
        }
        Store::write('search', $all);
    }

    /** Durée de lecture en minutes. */
    public static function readingTime(int $words): int
    {
        return max(1, (int) round($words / 220));
    }
}
