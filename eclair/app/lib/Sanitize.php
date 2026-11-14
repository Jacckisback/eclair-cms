<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
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
        // « \hôte » ou « /\hôte » : les navigateurs y lisent « //hôte », un autre site déguisé en lien interne.
        if ($url === '' || mb_strlen($url) > 2000 || preg_match('~^[/\\\\]*\\\\~', $url)) {
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

    /**
     * Adresse e-mail ordinaire, sur une seule ligne. FILTER_VALIDATE_EMAIL accepte une partie locale entre guillemets
     * où « \ » échappe n'importe quel caractère, retour à la ligne compris : une telle adresse, reprise dans un en-tête
     * (Reply-To), y ajouterait des lignes. Caractères de contrôle, espaces, guillemets et antislashs sont donc refusés.
     */
    public static function email(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || preg_match('/[\x00-\x20\x7F"\\\\]/', $value)) {
            return '';
        }
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

    /* ─────────────────────────────── Section libre ─────────────────────────────── */

    /** Balises de structure et de texte permises dans une section libre (bloc « html »). */
    private const SECTION_TAGS = [
        'section', 'div', 'article', 'aside', 'header', 'footer', 'figure', 'figcaption', 'h2', 'h3', 'h4', 'p', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'blockquote', 'cite', 'q', 'span', 'strong', 'em', 'b', 'i', 'small', 'mark', 'sup', 'sub', 'abbr', 'time', 'address', 'br', 'hr', 'a', 'img',
        'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'details', 'summary',
    ];
    /** Dessin vectoriel : formes seulement (ni script, ni style, ni image, ni lien, ni contenu étranger). */
    private const SECTION_SVG = ['svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'title', 'desc'];
    private const SECTION_SVG_ATTRS = [
        'viewbox', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'width', 'height', 'points', 'fill', 'stroke', 'stroke-width',
        'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'fill-rule', 'clip-rule', 'opacity', 'fill-opacity', 'stroke-opacity', 'transform',
        'preserveaspectratio',
    ];
    /** Supprimés avec tout leur contenu. */
    private const SECTION_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'form', 'input', 'button', 'select', 'textarea',
        'link', 'meta', 'base', 'head', 'title', 'video', 'audio', 'source', 'canvas', 'math', 'foreignobject', 'use', 'image', 'animate', 'set', 'animatetransform'];
    /** Classes du noyau qui portent un comportement ou une signification : jamais reprises. */
    private const SECTION_CLASS_DENY = '~^(?:skip|hp|sr-only|edit-fab|site-header|site-footer|site-nav|mnav.*|eb-.*|ad|ad-label)$~';
    private const SECTION_ROLES = ['img', 'list', 'listitem', 'presentation', 'none', 'group', 'figure', 'note', 'separator'];
    private const SECTION_MAX_NODES = 1500;
    private const SECTION_MAX_DEPTH = 14;

    /**
     * HTML d'une section libre : composition sur mesure (frise, grille, carnet, illustration au trait) écrite à la main ou
     * par un assistant, jamais exécutable. Liste blanche de balises et d'attributs ; aucun script, style, formulaire, cadre,
     * média externe ni gestionnaire d'événement ; liens vérifiés comme partout ; images du site seulement ; « h1 » ramené à
     * « h2 » (le titre principal reste celui de la page). Borné en taille, en nombre d'éléments et en profondeur.
     */
    public static function section(mixed $html, int $max = 40000): string
    {
        $html = is_string($html) ? self::utf8($html) : '';
        if (trim($html) === '' || strlen($html) > 200000) {
            return '';
        }
        if (!str_contains($html, '<')) {
            return '<p>' . e(trim($html)) . '</p>';
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="eclair-root">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('eclair-root');
        if (!$root) {
            return '';
        }
        $count = 0;
        $out = trim(self::sectionWalk($root, 0, false, $count));
        if ($out !== '' && !str_contains($out, '<')) {
            $out = '<p>' . $out . '</p>';           // il ne reste que du texte : un paragraphe
        }
        return strlen($out) > $max ? '' : $out;
    }

    private static function sectionWalk(\DOMNode $node, int $depth, bool $svg, int &$count): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                // Entre deux balises de structure, les blancs ne portent rien ; dans le texte, ils sont gardés.
                $out .= trim($child->nodeValue) === '' ? (str_contains($child->nodeValue, "\n") ? "\n" : ' ') : e($child->nodeValue);
                continue;
            }
            if (!$child instanceof \DOMElement || ++$count > self::SECTION_MAX_NODES) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::SECTION_DROP, true) && !($svg && $tag === 'title')) {
                continue;
            }
            if ($tag === 'h1') {
                $tag = 'h2';
            }
            $inSvg = $svg || $tag === 'svg';
            $allowed = $inSvg ? in_array($tag, self::SECTION_SVG, true) : in_array($tag, self::SECTION_TAGS, true);
            $inner = $depth >= self::SECTION_MAX_DEPTH ? e($child->textContent) : self::sectionWalk($child, $depth + 1, $inSvg, $count);
            if (!$allowed) {
                $out .= $inner;                     // balise inconnue : son texte est gardé, pas elle
                continue;
            }
            if ($tag === 'br' || $tag === 'hr') {
                $out .= '<' . $tag . '>';
                continue;
            }
            if ($tag === 'img') {
                $out .= self::sectionImg($child);
                continue;
            }
            if ($tag === 'a') {
                // L'adresse reste le premier attribut (« <a href= »), comme dans tout le contenu : les autres suivent.
                $link = self::link($child, $inner);
                $out .= str_starts_with($link, '<a ') ? substr_replace($link, self::sectionAttrs($child, false), (int) strpos($link, '>'), 0) : $link;
                continue;
            }
            $out .= '<' . $tag . self::sectionAttrs($child, $inSvg) . '>' . $inner . '</' . $tag . '>';
        }
        return $out;
    }

    /** Attributs gardés : classes, identifiant, accessibilité, données simples ; pour le dessin, géométrie et couleurs. */
    private static function sectionAttrs(\DOMElement $el, bool $svg): string
    {
        $out = '';
        $classes = [];
        foreach (preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [] as $c) {
            if (preg_match('~^[a-zA-Z][\w-]{0,40}$~', $c) && !preg_match(self::SECTION_CLASS_DENY, $c) && count($classes) < 12) {
                $classes[] = $c;
            }
        }
        if ($classes) {
            $out .= ' class="' . e(implode(' ', array_unique($classes))) . '"';
        }
        $id = $el->getAttribute('id');
        if (!$svg && preg_match('~^[a-z][a-z0-9-]{1,40}$~', $id) && !in_array($id, ['main', 'top', 'toc', 'menu', 'contenu', 'content', 'search', 'site-nav'], true)) {
            $out .= ' id="' . e($id) . '"';
        }
        foreach ($el->attributes ?? [] as $attr) {
            $name = strtolower($attr->nodeName);
            $value = self::utf8((string) $attr->nodeValue);
            if ($svg && in_array($name, self::SECTION_SVG_ATTRS, true)) {
                // Géométrie et couleurs : chiffres, lettres et ponctuation simple (ni adresse, ni fonction autre qu'une couleur).
                if (strlen($value) <= ($name === 'd' || $name === 'points' ? 4000 : 120) && preg_match('~^[\w\s.,#%()+\-/]*$~', $value) && !preg_match('~url|expression|javascript~i', $value)) {
                    $out .= ' ' . ($name === 'viewbox' ? 'viewBox' : ($name === 'preserveaspectratio' ? 'preserveAspectRatio' : $name)) . '="' . e($value) . '"';
                }
                continue;
            }
            if (in_array($name, ['aria-label', 'aria-hidden', 'aria-labelledby', 'aria-describedby', 'lang', 'datetime', 'cite'], true) || preg_match('~^data-[a-z][a-z0-9-]{0,30}$~', $name)) {
                if ($name === 'cite' && self::url($value) === '') {
                    continue;
                }
                if (mb_strlen($value) <= 200 && !preg_match('~^data-e[bf]~', $name)) {
                    $out .= ' ' . $name . '="' . e($value) . '"';
                }
            } elseif ($name === 'role' && in_array(strtolower($value), self::SECTION_ROLES, true)) {
                $out .= ' role="' . strtolower($value) . '"';
            } elseif (in_array($name, ['colspan', 'rowspan'], true) && ctype_digit($value) && (int) $value >= 1 && (int) $value <= 20) {
                $out .= ' ' . $name . '="' . (int) $value . '"';
            } elseif ($name === 'scope' && in_array($value, ['col', 'row', 'colgroup', 'rowgroup'], true)) {
                $out .= ' scope="' . $value . '"';
            } elseif ($name === 'open' && strtolower($el->nodeName) === 'details') {
                $out .= ' open';
            } elseif ($svg && in_array($name, ['role', 'focusable'], true) && in_array($value, ['img', 'presentation', 'none', 'false'], true)) {
                $out .= ' ' . $name . '="' . $value . '"';
            }
        }
        return $out;
    }

    /** Image d'une section libre : un fichier du site seulement (/media/…), dimensions entières, chargement différé. */
    private static function sectionImg(\DOMElement $img): string
    {
        $src = trim($img->getAttribute('src'));
        if (!preg_match('~^/media/[\w./-]{1,200}\.(?:jpe?g|png|webp|avif|gif|svg)$~i', $src) || str_contains($src, '..')) {
            return '';
        }
        $out = '<img src="' . e($src) . '" alt="' . e(mb_substr(self::utf8($img->getAttribute('alt')), 0, 300)) . '"';
        foreach (['width', 'height'] as $k) {
            if (ctype_digit($img->getAttribute($k)) && (int) $img->getAttribute($k) > 0 && (int) $img->getAttribute($k) <= 8000) {
                $out .= ' ' . $k . '="' . (int) $img->getAttribute($k) . '"';
            }
        }
        return $out . self::sectionAttrs($img, false) . ' loading="lazy" decoding="async">';
    }
}
