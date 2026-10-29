<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Nettoyage des entrées. Le texte enrichi est analysé puis entièrement ré-écrit
 * à partir d'une liste blanche : aucune balise ni attribut inattendu ne peut passer.
 */
final class Sanitize
{
    private const INLINE = ['strong', 'em', 'a', 'code', 'br', 'mark', 'sub', 'sup', 's'];
    private const ALIASES = ['b' => 'strong', 'i' => 'em', 'del' => 's', 'strike' => 's', 'kbd' => 'code', 'tt' => 'code'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'noscript',
        'textarea', 'select', 'button', 'input', 'form', 'head', 'title', 'meta', 'link', 'video', 'audio', 'canvas', 'img'];
    private const BLOCKS = ['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'tr', 'section', 'article'];

    /** Texte enrichi en ligne : gras, italique, liens, code, surlignage, exposants. */
    public static function inline(string $html): string
    {
        $html = self::utf8($html);
        if (trim($html) === '') {
            return '';
        }
        if (!str_contains($html, '<') && !str_contains($html, '&')) {
            return e(trim($html));
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="eclair-root">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('eclair-root') ?? $doc->getElementsByTagName('body')->item(0);
        if (!$root) {
            return e(plain($html));
        }
        $out = self::trimBreaks(self::walk($root));
        return (string) (preg_replace('~(?:<br>\s*+){3,}~', '<br><br>', $out) ?? $out);
    }

    /** Retire espaces, espaces insécables et <br> aux deux extrémités (parcours linéaire, sans expression régulière). */
    private static function trimBreaks(string $html): string
    {
        $blank = static function (string $s, int $i, bool $back): int {
            $c = $back ? $s[$i - 1] : $s[$i];
            if (str_contains(" \t\n\r\x0B\x0C\0", $c)) {
                return 1;
            }
            foreach (["\xC2\xA0", '<br>'] as $token) {
                $n = strlen($token);
                $at = $back ? $i - $n : $i;
                if ($at >= 0 && $at + $n <= strlen($s) && substr_compare($s, $token, $at, $n) === 0) {
                    return $n;
                }
            }
            return 0;
        };
        $start = 0;
        $end = strlen($html);
        while ($start < $end && ($n = $blank($html, $start, false)) > 0) {
            $start += $n;
        }
        while ($end > $start && ($n = $blank($html, $end, true)) > 0) {
            $end -= $n;
        }
        return substr($html, $start, $end - $start);
    }

    private static function walk(\DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= e($child->nodeValue);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::DROP, true)) {
                continue;
            }
            $tag = self::ALIASES[$tag] ?? $tag;
            if ($tag === 'br') {
                $out .= '<br>';
                continue;
            }
            $inner = self::walk($child);
            if (in_array($tag, self::BLOCKS, true)) {
                $out .= ($out !== '' && !str_ends_with($out, '<br>') ? '<br>' : '') . $inner;
                continue;
            }
            if (!in_array($tag, self::INLINE, true) || $inner === '') {
                $out .= $inner;
                continue;
            }
            if ($tag === 'a') {
                $out .= self::link($child, $inner);
                continue;
            }
            $out .= '<' . $tag . '>' . $inner . '</' . $tag . '>';
        }
        return $out;
    }

    private static function link(\DOMElement $a, string $inner): string
    {
        $href = self::url($a->getAttribute('href'));
        if ($href === '') {
            return $inner;
        }
        $attrs = ' href="' . e($href) . '"';
        $rel = array_values(array_intersect(
            preg_split('/\s+/', strtolower($a->getAttribute('rel'))) ?: [],
            ['nofollow', 'sponsored', 'ugc']
        ));
        if ($a->getAttribute('target') === '_blank') {
            $attrs .= ' target="_blank"';
            $rel[] = 'noopener';
        }
        if ($rel) {
            $attrs .= ' rel="' . e(implode(' ', array_unique($rel))) . '"';
        }
        return '<a' . $attrs . '>' . $inner . '</a>';
    }

    /** URL sûre : http(s), mailto, tel ou lien interne. Renvoie « » si dangereuse. */
    public static function url(string $url): string
    {
        $url = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', self::utf8($url)));
        if ($url === '' || mb_strlen($url) > 2000) {
            return '';
        }
        // Le schéma est aussi vérifié sur la forme décodée et compactée (« javascript&colon; », « java&#9;script: »…).
        $probe = $url;
        for ($i = 0; $i < 3; $i++) {
            $probe = html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $probe = (string) preg_replace('/[\x00-\x20\x7F]+/u', '', $probe);
        foreach ([$url, $probe] as $candidate) {
            if (preg_match('~^([a-z][a-z0-9+.\-]*):~i', $candidate, $m) && !in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true)) {
                return '';
            }
        }
        return $url;
    }

    /** Texte brut sur une ligne. */
    public static function text(mixed $value, int $max = 300): string
    {
        $text = is_scalar($value) ? self::utf8((string) $value) : '';
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        return mb_substr($text, 0, $max);
    }

    /** Texte brut multiligne (paragraphes conservés). */
    public static function multiline(mixed $value, int $max = 5000): string
    {
        $text = is_scalar($value) ? self::utf8((string) $value) : '';
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags($text));
        $text = (string) preg_replace("/[ \t]+/u", ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);
        return mb_substr(trim($text), 0, $max);
    }

    /** Code source : conservé tel quel (il sera échappé à l'affichage). */
    public static function code(mixed $value, int $max = 50000): string
    {
        $text = is_scalar($value) ? self::utf8((string) $value) : '';
        return mb_substr(str_replace(["\r\n", "\r"], "\n", $text), 0, $max);
    }

    public static function color(mixed $value, string $default): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $default;
    }

    public static function email(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 190) : '';
    }

    /** Slug de page : segments [a-z0-9-] séparés par « / » (ex. « services/formation-ia »). */
    public static function path(string $value, string $lang = 'fr'): string
    {
        $parts = [];
        foreach (explode('/', strtolower($value)) as $segment) {
            $segment = slugify($segment, $lang, 80);
            if ($segment !== '') {
                $parts[] = $segment;
            }
        }
        return implode('/', array_slice($parts, 0, 4));
    }

    public static function utf8(string $s): string
    {
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
}
