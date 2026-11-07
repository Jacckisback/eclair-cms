<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Site multilingue (Réglages › Général › Langues du site). La langue du site garde ses adresses (/, /blog, /contact) ;
 * chaque langue ajoutée a les siennes sous un préfixe : /en (accueil), /en/blog, /en/blog/<article>, /en/blog/<catégorie>,
 * /en/search. Les pages gardent l'adresse choisie (en/about par exemple). Tant qu'aucune langue n'est ajoutée, rien ne
 * change : mêmes adresses, même rendu, à l'octet près.
 */
final class Langs
{
    /** Textes du site traduits par langue ajoutée (sinon ceux de la langue du site). */
    public const TEXTS = ['name', 'tagline', 'description', 'footer', 'blog_title', 'blog_description'];

    private static ?array $all = null;

    /** Langues publiques : celle du site d'abord, puis les langues ajoutées. */
    public static function all(): array
    {
        if (self::$all === null) {
            $main = (string) Settings::get('lang', 'fr');
            $out = [$main];
            foreach ((array) Settings::get('languages', []) as $code) {
                if (is_string($code) && isset(Content::LANGS[$code]) && !in_array($code, $out, true)) {
                    $out[] = $code;
                }
            }
            self::$all = $out;
        }
        return self::$all;
    }

    public static function forget(): void
    {
        self::$all = null;
    }

    public static function enabled(): bool
    {
        return count(self::all()) > 1;
    }

    public static function main(): string
    {
        return self::all()[0];
    }

    /** Langue ajoutée (préfixée), donc différente de celle du site. */
    public static function extra(string $lang): bool
    {
        return $lang !== self::main() && in_array($lang, self::all(), true);
    }

    /** Langue publique valide, sinon celle du site. */
    public static function valid(string $lang): string
    {
        return in_array($lang, self::all(), true) ? $lang : self::main();
    }

    /** Préfixe des adresses d'une langue : '' pour la langue du site, '/en' pour une langue ajoutée. */
    public static function prefix(string $lang): string
    {
        return self::extra($lang) ? '/' . $lang : '';
    }

    /** Accueil d'une langue. */
    public static function home(string $lang): string
    {
        return self::extra($lang) ? '/' . $lang : '/';
    }

    /** Page d'accueil choisie pour une langue ajoutée ('' : la liste des articles de cette langue). */
    public static function homeId(string $lang): string
    {
        if (!self::extra($lang)) {
            return (string) Settings::get('home');
        }
        return (string) (Settings::get('i18n')[$lang]['home'] ?? '');
    }

    /** Adresse du blog d'une langue. */
    public static function blogPath(string $lang): string
    {
        return self::prefix($lang) . Content::blogPath();
    }

    /** Langue d'une adresse et adresse sans son préfixe : '/en/blog' → ['en', '/blog'], '/blog' → ['fr', '/blog']. */
    public static function split(string $path): array
    {
        if (self::enabled() && preg_match('~^/([a-z]{2})(/.*)?$~', $path, $m) && self::extra($m[1])) {
            return [$m[1], ($m[2] ?? '') === '' ? '/' : $m[2]];
        }
        return [self::main(), $path];
    }

    /** Texte du site dans une langue (nom, slogan, description, pied de page, titre et présentation du blog). */
    public static function text(string $key, string $lang): string
    {
        $own = self::extra($lang) ? trim((string) (Settings::get('i18n')[$lang][$key] ?? '')) : '';
        if ($own !== '') {
            return $own;
        }
        return (string) match ($key) {
            'footer' => Settings::get('appearance.footer'),
            'blog_title' => Settings::get('blog.title'),
            'blog_description' => Settings::get('blog.description'),
            default => Settings::get($key),
        };
    }

    /** Langue d'une catégorie (celle du site si aucune). */
    public static function ofCategory(array $cat): string
    {
        return self::valid((string) ($cat['lang'] ?? ''));
    }

    /** Le contenu ou la catégorie est-il de cette langue ? Toujours vrai sur un site à une seule langue. */
    public static function matches(array $entry, string $lang): bool
    {
        return !self::enabled() || self::valid((string) ($entry['lang'] ?? '')) === $lang;
    }

    /**
     * Liens du sélecteur de langue : [code => [name, path, current]]. La traduction du contenu affiché si elle existe,
     * sinon le blog de la langue pour une liste d'articles, sinon son accueil.
     */
    public static function links(string $lang, string $id = '', string $current = ''): array
    {
        // Traductions publiées, pages non indexées comprises (mentions légales, remerciements) : le lecteur reste sur la même page.
        $translations = [];
        $group = $id !== '' ? (string) (Content::index()[$id]['tgroup'] ?? '') : '';
        if ($group !== '') {
            foreach (Content::published() as $other) {
                if (($other['tgroup'] ?? '') === $group) {
                    $translations[$other['lang']] = Content::path($other);
                }
            }
        }
        $blog = Settings::get('blog.enabled') && ($current === self::blogPath($lang) || str_starts_with($current, self::blogPath($lang) . '/'));
        $out = [];
        foreach (self::all() as $code) {
            $path = $translations[$code] ?? ($blog ? self::blogPath($code) : self::home($code));
            $out[$code] = ['name' => Content::LANGS[$code], 'path' => $path, 'current' => $code === $lang];
        }
        return $out;
    }

    /** Nettoie les réglages envoyés par l'administration : langues ajoutées et textes traduits. */
    public static function clean(string $main, mixed $languages, mixed $i18n): array
    {
        $codes = [];
        foreach (is_array($languages) ? $languages : [] as $code) {
            if (is_string($code) && isset(Content::LANGS[$code]) && $code !== $main && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        $texts = [];
        $index = Content::index();
        foreach ($codes as $code) {
            $row = is_array($i18n[$code] ?? null) ? $i18n[$code] : [];
            $home = (string) ($row['home'] ?? '');
            $texts[$code] = [
                'home' => ($index[$home]['type'] ?? '') === 'page' ? $home : '',
                'name' => Sanitize::text($row['name'] ?? '', 100),
                'tagline' => Sanitize::text($row['tagline'] ?? '', 160),
                'description' => Sanitize::text($row['description'] ?? '', 320),
                'footer' => Sanitize::multiline($row['footer'] ?? '', 500),
                'blog_title' => Sanitize::text($row['blog_title'] ?? '', 80),
                'blog_description' => Sanitize::multiline($row['blog_description'] ?? '', 500),
            ];
        }
        return ['languages' => $codes, 'i18n' => $texts];
    }
}
