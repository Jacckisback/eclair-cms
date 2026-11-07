<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Traductions de l'administration. Le texte français sert de référence : les sources lisibles
 * i18n/<langue>.php du dépôt sont compilées par tools/i18n.php en app/lang/<langue>.json.gz
 * (JSON compressé), indexées par l'empreinte CRC32 du texte français. Sans l'extension zlib
 * (très rare), l'administration reste en français. Les textes propres à une fonction livrée sont dans son dossier
 * (<fonction>/lang/<langue>.json.gz), lus seulement quand son code est chargé (domain()).
 */
final class I18n
{
    public const LANGS = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português', 'zh' => '简体中文'];

    private static string $lang = 'fr';
    private static ?array $dict = null;
    /** Dossiers des fonctions chargées qui ont leurs propres traductions, et leur dictionnaire réuni (lu au premier besoin). */
    private static array $domains = [];
    private static ?array $extra = null;

    /** Traductions d'une fonction ($dir/lang/<langue>.json.gz), consultées après celles du noyau. */
    public static function domain(string $dir): void
    {
        if (!isset(self::$domains[$dir]) && is_dir($dir . '/lang')) {
            self::$domains[$dir] = true;
            self::$extra = null;
        }
    }

    private static function load(string $file): array
    {
        $raw = @file_get_contents($file);
        $json = is_string($raw) && function_exists('gzdecode') ? @gzdecode($raw) : false;
        $dict = is_string($json) ? json_decode($json, true) : null;
        return is_array($dict) ? $dict : [];
    }

    public static function setLang(string $lang): void
    {
        $lang = isset(self::LANGS[$lang]) ? $lang : 'fr';
        if ($lang !== self::$lang) {
            self::$lang = $lang;
            self::$dict = self::$extra = null;
        }
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function t(string $text, array $vars = []): string
    {
        if (self::$lang !== 'fr') {
            self::$dict ??= self::load(ECLAIR_ROOT . '/app/lang/' . self::$lang . '.json.gz');
            $key = hash('crc32b', $text);
            if (isset(self::$dict[$key])) {
                $text = self::$dict[$key];
            } elseif (self::$domains) {
                if (self::$extra === null) {
                    self::$extra = [];
                    foreach (array_keys(self::$domains) as $dir) {
                        self::$extra += self::load($dir . '/lang/' . self::$lang . '.json.gz');
                    }
                }
                $text = self::$extra[$key] ?? $text;
            }
        }
        if ($vars) {
            $pairs = [];
            foreach ($vars as $k => $v) {
                $pairs['{' . $k . '}'] = (string) $v;
            }
            $text = strtr($text, $pairs);
        }
        return $text;
    }

    /** Langue préférée du navigateur parmi celles disponibles (pour l'installateur). */
    public static function detect(): string
    {
        $accept = strtolower(Req::header('Accept-Language'));
        foreach (preg_split('/\s*,\s*/', $accept) ?: [] as $part) {
            $code = substr($part, 0, 2);
            if (isset(self::LANGS[$code])) {
                return $code;
            }
        }
        return 'fr';
    }
}
