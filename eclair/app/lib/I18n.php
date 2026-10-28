<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Traductions de l'administration. Le texte français sert de clé ;
 * app/lang/en.php et app/lang/es.php contiennent les correspondances.
 */
final class I18n
{
    public const LANGS = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español'];

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
                $file = ECLAIR_ROOT . '/app/lang/' . self::$lang . '.php';
                $dict = is_file($file) ? include $file : [];
                self::$dict = is_array($dict) ? $dict : [];
            }
            $text = self::$dict[$text] ?? $text;
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
