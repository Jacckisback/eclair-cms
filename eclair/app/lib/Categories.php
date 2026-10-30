<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Catégories : de vraies pages « piliers » pour structurer le cocon sémantique.
 * Chacune regroupe un seul type de contenu : des articles (blog) ou des produits (boutique).
 */
final class Categories
{
    public const KINDS = ['post', 'product'];

    public static function all(): array
    {
        $cats = Store::read('categories', []);
        return is_array($cats) ? array_map(fn (array $c): array => $c + ['kind' => 'post'], $cats) : [];
    }

    /** Catégories d'articles (post) ou de produits (product). */
    public static function of(string $kind): array
    {
        return array_values(array_filter(self::all(), fn (array $c): bool => $c['kind'] === $kind));
    }

    public static function get(string $id): ?array
    {
        foreach (self::all() as $cat) {
            if ($cat['id'] === $id) {
                return $cat;
            }
        }
        return null;
    }

    public static function bySlug(string $slug, string $kind = 'post'): ?array
    {
        foreach (self::of($kind) as $cat) {
            if ($cat['slug'] === $slug) {
                return $cat;
            }
        }
        return null;
    }

    public static function path(array $cat): string
    {
        return (($cat['kind'] ?? 'post') === 'product' ? Shop::path() : Content::blogPath()) . '/' . $cat['slug'];
    }

    public static function save(array $data): array
    {
        $cat = Store::locked(function () use ($data): array {
            $cats = self::all();
            $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
            $existing = $id !== '' ? self::get($id) : null;
            // Le type d'une catégorie existante ne change pas : ses contenus garderaient une catégorie de l'autre type.
            $kind = $existing['kind'] ?? (($data['kind'] ?? '') === 'product' ? 'product' : 'post');
            $name = Sanitize::text($data['name'] ?? '', 80);
            $slug = slugify((string) ($data['slug'] ?? '') ?: $name) ?: 'categorie';
            $taken = array_column(array_filter($cats, fn ($c) => $c['id'] !== $id && $c['kind'] === $kind), 'slug');
            foreach (Content::index() as $entry) {
                if ($entry['type'] === $kind) {
                    $taken[] = $entry['slug'];
                }
            }
            $words = Theme::words();
            array_push($taken, $words['page'], $words['author'], 'feed');
            $base = $slug;
            for ($n = 2; in_array($slug, $taken, true); $n++) {
                $slug = $base . '-' . $n;
            }
            $cat = [
                'id' => $id !== '' ? $id : random_id(),
                'kind' => $kind,
                'name' => $name !== '' ? $name : t('Sans titre'),
                'slug' => $slug,
                'description' => Sanitize::multiline($data['description'] ?? '', 1000),
                'seo_title' => Sanitize::text($data['seo_title'] ?? '', 120),
            ];
            $found = false;
            foreach ($cats as $i => $old) {
                if ($old['id'] === $cat['id']) {
                    if ($old['slug'] !== $cat['slug']) {
                        Redirects::add(self::path($old), self::path($cat), true);
                    }
                    $cats[$i] = $cat;
                    $found = true;
                }
            }
            if (!$found) {
                $cats[] = $cat;
            }
            Store::write('categories', array_values($cats));
            return $cat;
        });
        Cache::purge();
        return $cat;
    }

    public static function delete(string $id): void
    {
        Store::locked(function () use ($id): void {
            Store::write('categories', array_values(array_filter(self::all(), fn ($c) => $c['id'] !== $id)));
        });
        Cache::purge();
    }

    /** Nombre de contenus publiés par catégorie (articles par défaut, ou produits). */
    public static function counts(string $kind = 'post'): array
    {
        $counts = [];
        foreach (Content::published($kind) as $entry) {
            if ($entry['category'] !== '') {
                $counts[$entry['category']] = ($counts[$entry['category']] ?? 0) + 1;
            }
        }
        return $counts;
    }
}
