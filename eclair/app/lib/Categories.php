<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Catégories d'articles : de vraies pages « piliers » pour structurer le cocon sémantique. */
final class Categories
{
    public static function all(): array
    {
        $cats = Store::read('categories', []);
        return is_array($cats) ? $cats : [];
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

    public static function bySlug(string $slug): ?array
    {
        foreach (self::all() as $cat) {
            if ($cat['slug'] === $slug) {
                return $cat;
            }
        }
        return null;
    }

    public static function path(array $cat): string
    {
        return Content::blogPath() . '/' . $cat['slug'];
    }

    public static function save(array $data): array
    {
        $cat = Store::locked(function () use ($data): array {
            $cats = self::all();
            $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
            $name = Sanitize::text($data['name'] ?? '', 80);
            $slug = slugify((string) ($data['slug'] ?? '') ?: $name) ?: 'categorie';
            $taken = array_column(array_filter($cats, fn ($c) => $c['id'] !== $id), 'slug');
            foreach (Content::published('post') as $post) {
                $taken[] = $post['slug'];
            }
            $words = Theme::words();
            array_push($taken, $words['page'], $words['author'], 'feed');
            $base = $slug;
            for ($n = 2; in_array($slug, $taken, true); $n++) {
                $slug = $base . '-' . $n;
            }
            $cat = [
                'id' => $id !== '' ? $id : random_id(),
                'name' => $name !== '' ? $name : t('Sans titre'),
                'slug' => $slug,
                'description' => Sanitize::multiline($data['description'] ?? '', 1000),
                'seo_title' => Sanitize::text($data['seo_title'] ?? '', 120),
            ];
            $found = false;
            foreach ($cats as $i => $existing) {
                if ($existing['id'] === $cat['id']) {
                    if ($existing['slug'] !== $cat['slug']) {
                        Redirects::add(self::path($existing), self::path($cat), true);
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

    /** Nombre d'articles publiés par catégorie. */
    public static function counts(): array
    {
        $counts = [];
        foreach (Content::published('post') as $post) {
            if ($post['category'] !== '') {
                $counts[$post['category']] = ($counts[$post['category']] ?? 0) + 1;
            }
        }
        return $counts;
    }
}
