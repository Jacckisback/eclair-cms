<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Menus « principal » (en-tête) et « pied de page ». */
final class Menus
{
    public const NAMES = ['main', 'footer'];

    public static function raw(string $name): array
    {
        $menus = Store::read('menus', []);
        return is_array($menus[$name] ?? null) ? $menus[$name] : [];
    }

    /** Éléments prêts à afficher : [label, href (chemin), path (pour l'état actif)]. */
    public static function items(string $name): array
    {
        $out = [];
        $index = Content::index();
        foreach (self::raw($name) as $item) {
            $label = (string) ($item['label'] ?? '');
            switch ($item['type'] ?? '') {
                case 'content':
                    $entry = $index[$item['id'] ?? ''] ?? null;
                    if ($entry && Content::visible($entry)) {
                        $path = Content::path($entry);
                        $out[] = ['label' => $label !== '' ? $label : $entry['title'], 'href' => path_to($path), 'path' => $path];
                    }
                    break;
                case 'blog':
                    if (Settings::get('blog.enabled')) {
                        $path = Settings::get('home') === '' ? '/' : Content::blogPath();
                        $out[] = ['label' => $label !== '' ? $label : (string) Settings::get('blog.title'), 'href' => path_to($path), 'path' => $path];
                    }
                    break;
                case 'shop':
                    if (Shop::enabled()) {
                        $path = Shop::path();
                        $out[] = ['label' => $label !== '' ? $label : Shop::title(), 'href' => path_to($path), 'path' => $path];
                    }
                    break;
                case 'category':
                    $cat = Categories::get((string) ($item['id'] ?? ''));
                    if ($cat && ($cat['kind'] !== 'product' || Shop::enabled())) {
                        $path = Categories::path($cat);
                        $out[] = ['label' => $label !== '' ? $label : $cat['name'], 'href' => path_to($path), 'path' => $path];
                    }
                    break;
                case 'link':
                    $url = Sanitize::url((string) ($item['url'] ?? ''));
                    if ($url !== '' && $label !== '') {
                        $internal = str_starts_with($url, '/') && !str_starts_with($url, '//');
                        $out[] = ['label' => $label, 'href' => $internal ? path_to($url) : $url, 'path' => $internal ? $url : '', 'external' => !$internal];
                    }
                    break;
            }
        }
        return $out;
    }

    public static function save(string $name, array $items): void
    {
        if (!in_array($name, self::NAMES, true)) {
            return;
        }
        $clean = [];
        foreach (array_slice($items, 0, 30) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            if (!in_array($type, ['content', 'blog', 'shop', 'category', 'link'], true)) {
                continue;
            }
            $clean[] = [
                'type' => $type,
                'id' => preg_replace('/[^a-z0-9]/', '', (string) ($item['id'] ?? '')) ?? '',
                'label' => Sanitize::text($item['label'] ?? '', 60),
                'url' => $type === 'link' ? Sanitize::url((string) ($item['url'] ?? '')) : '',
            ];
        }
        Store::locked(function () use ($name, $clean): void {
            $menus = Store::read('menus', []);
            $menus = is_array($menus) ? $menus : [];
            $menus[$name] = $clean;
            Store::write('menus', $menus);
        });
        Cache::purge();
    }
}
