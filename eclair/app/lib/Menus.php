<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Menus « principal » (en-tête) et « pied de page ». Sur un site multilingue, chaque langue ajoutée peut avoir les siens
 * (main.en, footer.en) ; sinon ceux de la langue du site s'affichent, chaque page remplacée par sa traduction.
 */
final class Menus
{
    public const NAMES = ['main', 'footer'];
    /** Icônes proposées pour les éléments du menu (menu mobile) ; '' : choisie selon le type d'élément. */
    public const ICONS = ['home', 'file', 'book', 'pen', 'graduation', 'briefcase', 'chat', 'euro', 'tag', 'user', 'users', 'mail', 'phone',
        'calendar', 'star', 'heart', 'map', 'play', 'image', 'camera', 'bag', 'store', 'search', 'globe', 'info', 'help', 'shield-check', 'link', 'compass'];

    /** Noms de menu valides : main, footer, et main.<langue>, footer.<langue> pour chaque langue ajoutée. */
    public static function names(): array
    {
        $out = self::NAMES;
        foreach (array_slice(Langs::all(), 1) as $code) {
            $out[] = 'main.' . $code;
            $out[] = 'footer.' . $code;
        }
        return $out;
    }

    public static function raw(string $name): array
    {
        $menus = Store::read('menus', []);
        return is_array($menus[$name] ?? null) ? $menus[$name] : [];
    }

    /**
     * Éléments prêts à afficher : [label, href (chemin), path (pour l'état actif), icon]. $lang : langue de la page affichée
     * (site multilingue) ; son propre menu s'il existe, sinon celui de la langue du site traduit élément par élément.
     */
    public static function items(string $name, string $lang = ''): array
    {
        $out = [];
        $index = Content::index();
        $raw = self::raw($name);
        $translate = false;
        if ($lang !== '' && Langs::extra($lang)) {
            $own = self::raw($name . '.' . $lang);
            $translate = $own === [];
            $raw = $own ?: $raw;
        } else {
            $lang = Langs::main();
        }
        foreach ($raw as $item) {
            $label = (string) ($item['label'] ?? '');
            $icon = (string) ($item['icon'] ?? '');
            switch ($item['type'] ?? '') {
                case 'content':
                    $entry = $index[$item['id'] ?? ''] ?? null;
                    if ($entry && $translate && ($other = Content::translations($entry)[$lang] ?? null)) {
                        // Menu de la langue du site sur une page traduite : la traduction, sous son propre titre.
                        [$entry, $label] = [$other, ''];
                    }
                    if ($entry && Content::visible($entry)) {
                        $path = Content::path($entry);
                        $home = $path === Langs::home(Langs::valid((string) ($entry['lang'] ?? '')));
                        $out[] = ['label' => $label !== '' ? $label : $entry['title'], 'href' => path_to($path), 'path' => $path,
                            'icon' => $icon ?: ($home ? 'home' : ($entry['type'] === 'post' ? 'book' : ($entry['type'] === 'product' ? 'bag' : 'file')))];
                    }
                    break;
                case 'blog':
                    if (Settings::get('blog.enabled')) {
                        $path = Langs::homeId($lang) === '' ? Langs::home($lang) : Langs::blogPath($lang);
                        $out[] = ['label' => $label !== '' ? $label : Langs::text('blog_title', $lang), 'href' => path_to($path), 'path' => $path, 'icon' => $icon ?: 'pen'];
                    }
                    break;
                case 'shop':
                    if (Shop::enabled()) {
                        $path = Shop::path();
                        $out[] = ['label' => $label !== '' ? $label : Shop::title(), 'href' => path_to($path), 'path' => $path, 'icon' => $icon ?: 'bag'];
                    }
                    break;
                case 'category':
                    $cat = Categories::get((string) ($item['id'] ?? ''));
                    if ($cat && ($cat['kind'] !== 'product' || Shop::enabled())) {
                        $path = Categories::path($cat);
                        $out[] = ['label' => $label !== '' ? $label : $cat['name'], 'href' => path_to($path), 'path' => $path, 'icon' => $icon ?: 'tag'];
                    }
                    break;
                case 'link':
                    $url = Sanitize::url((string) ($item['url'] ?? ''));
                    if ($url !== '' && $label !== '') {
                        $internal = str_starts_with($url, '/') && !str_starts_with($url, '//');
                        $out[] = ['label' => $label, 'href' => $internal ? path_to($url) : $url, 'path' => $internal ? $url : '', 'external' => !$internal,
                            'icon' => $icon ?: ($internal ? 'link' : 'external')];
                    }
                    break;
            }
        }
        return $out;
    }

    public static function save(string $name, array $items): void
    {
        if (!in_array($name, self::names(), true)) {
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
            ] + (in_array($item['icon'] ?? '', self::ICONS, true) ? ['icon' => (string) $item['icon']] : []);
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
