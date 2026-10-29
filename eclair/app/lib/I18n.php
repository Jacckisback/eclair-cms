<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Traductions de l'administration. Le texte français sert de référence : les sources lisibles
 * i18n/<langue>.php du dépôt sont compilées par tools/i18n.php en app/lang/<langue>.json.gz
 * (JSON compressé), indexées par l'empreinte CRC32 du texte français. Sans l'extension zlib
 * (très rare), l'administration reste en français.
 */
final class I18n
{
    public const LANGS = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português', 'zh' => '简体中文'];

    private static string $lang = 'fr';
    private static ?array $dict = null;

    public static function setLang(string $lang): void
    {
        $lang = isset(self::LANGS[$lang]) ? $lang : 'fr';
        if ($lang !== self::$lang) {
            self::$lang = $lang;
            self::$dict = null;
        }
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function t(string $text, array $vars = []): string
    {
        if (self::$lang !== 'fr') {
            if (self::$dict === null) {
                $raw = @file_get_contents(ECLAIR_ROOT . '/app/lang/' . self::$lang . '.json.gz');
                $json = is_string($raw) && function_exists('gzdecode') ? @gzdecode($raw) : false;
                $dict = is_string($json) ? json_decode($json, true) : null;
                self::$dict = is_array($dict) ? $dict : [];
            }
            $text = self::$dict[hash('crc32b', $text)] ?? $text;
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
