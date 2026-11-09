<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Wordpress;

defined('ECLAIR') || exit;

/**
 * Lecture d'un export WordPress (WXR, Outils › Exporter) et conversion de son HTML en blocs Éclair. Lu comme des données :
 * aucun DOCTYPE (ni entité externe, ni expansion d'entités), aucun accès réseau pendant la lecture, au plus MAX contenus.
 * Le HTML converti est ensuite assaini par Blocks::normalize, comme tout ce qui entre dans un contenu.
 */
final class Wxr
{
    /** Contenus d'un export au plus : le plafond de contenus d'un site (Content::MAX_ITEMS). */
    public const MAX = 10_000;
    /**
     * Taille d'un export au plus. Le fichier est lu en flux, contenu par contenu (jamais chargé en entier) : sa taille ne
     * compte plus pour la mémoire, seulement pour l'envoi (limité par l'hébergeur ; au-delà, dépôt par FTP ou ligne de commande).
     */
    public const MAX_BYTES = 512 * 1048576;

    /** Champs personnalisés de référencement lus dans l'export (Yoast SEO, Rank Math, SEOPress, All in One SEO 3). */
    private const SEO_KEYS = [
        '_yoast_wpseo_title' => 1, '_yoast_wpseo_metadesc' => 1, '_yoast_wpseo_focuskw' => 1, '_yoast_wpseo_canonical' => 1, '_yoast_wpseo_meta-robots-noindex' => 1,
        'rank_math_title' => 1, 'rank_math_description' => 1, 'rank_math_focus_keyword' => 1, 'rank_math_canonical_url' => 1, 'rank_math_robots' => 1,
        '_seopress_titles_title' => 1, '_seopress_titles_desc' => 1, '_seopress_analysis_target_kw' => 1, '_seopress_robots_canonical' => 1, '_seopress_robots_index' => 1,
        '_aioseop_title' => 1, '_aioseop_description' => 1, '_aioseop_noindex' => 1,
    ];
    /** Balises de bloc : jamais enveloppées dans un paragraphe par autop(). */
    private const BLOCK_TAGS = 'address|article|aside|audio|blockquote|details|div|dl|dd|dt|embed|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|iframe|li|main|nav|noscript|object|ol|p|pre|script|section|style|summary|svg|table|tbody|td|tfoot|th|thead|tr|ul|video';

    /**
     * Export lu : ['site' => adresse de l'ancien site, 'title', 'categories' => [nicename => nom], 'attachments' => [id => url],
     * 'items' => [contenu…]] ; ou un message d'erreur. Pour un texte déjà en mémoire (petits exports, tests) ; un fichier se
     * lit avec parseFile(), qui ne garde rien en mémoire.
     */
    public static function parse(string $xml): array|string
    {
        if (strlen($xml) > self::MAX_BYTES) {
            return t('Fichier trop volumineux ({n} Mo au plus).', ['n' => self::MAX_BYTES / 1048576]);
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return t('Fichier refusé : un export WordPress ne contient pas de déclaration DOCTYPE.');
        }
        if (!class_exists(\XMLReader::class)) {
            return t('L’import demande les extensions PHP SimpleXML et DOM, absentes de ton hébergement.');
        }
        $items = [];
        $reader = new \XMLReader();
        $previous = libxml_use_internal_errors(true);
        try {
            $head = @$reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT) ? self::scan($reader, static function (array $item) use (&$items): void {
                $items[] = $item;
            }) : t('Ce fichier n’est pas un export WordPress (Outils › Exporter, « Tout le contenu »).');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return is_string($head) ? $head : $head + ['items' => $items];
    }

    /**
     * Lit un fichier d'export en flux : $item reçoit chaque contenu (article ou page) dès qu'il est lu. Renvoie l'en-tête
     * (['site', 'title', 'categories', 'attachments', 'count']) ou un message d'erreur. La mémoire ne dépend pas de la taille
     * du fichier : un export de 10 000 articles se lit comme un export de 10.
     */
    public static function parseFile(string $file, callable $item): array|string
    {
        if (!class_exists(\XMLReader::class) || !function_exists('simplexml_import_dom') || !class_exists(\DOMDocument::class)) {
            return t('L’import demande les extensions PHP SimpleXML et DOM, absentes de ton hébergement.');
        }
        if (!is_file($file) || (int) filesize($file) > self::MAX_BYTES) {
            return t('Fichier trop volumineux ({n} Mo au plus).', ['n' => self::MAX_BYTES / 1048576]);
        }
        // Aucune déclaration DOCTYPE ni entité, où que ce soit dans le fichier : cherché par morceaux, avant toute lecture XML.
        $h = fopen($file, 'rb');
        $tail = '';
        while ($h !== false && !feof($h)) {
            $chunk = $tail . (string) fread($h, 1048576);
            if (stripos($chunk, '<!DOCTYPE') !== false || stripos($chunk, '<!ENTITY') !== false) {
                fclose($h);
                return t('Fichier refusé : un export WordPress ne contient pas de déclaration DOCTYPE.');
            }
            $tail = substr($chunk, -16);
        }
        $h !== false && fclose($h);
        $reader = new \XMLReader();
        $previous = libxml_use_internal_errors(true);
        try {
            return @$reader->open($file, null, LIBXML_NONET | LIBXML_COMPACT) ? self::scan($reader, $item)
                : t('Ce fichier n’est pas un export WordPress (Outils › Exporter, « Tout le contenu »).');
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Parcours de l'export : en-tête du canal, catégories, fichiers joints, et chaque contenu remis à $sink. */
    private static function scan(\XMLReader $r, callable $sink): array|string
    {
        if (!function_exists('simplexml_import_dom') || !class_exists(\DOMDocument::class)) {
            return t('L’import demande les extensions PHP SimpleXML et DOM, absentes de ton hébergement.');
        }
        $not = t('Ce fichier n’est pas un export WordPress (Outils › Exporter, « Tout le contenu »).');
        // Jusqu'à <channel> : la racine doit déclarer l'espace de noms de WordPress.
        $wpNs = null;
        while (@$r->read()) {
            if ($r->nodeType === \XMLReader::DOC_TYPE) {
                return t('Fichier refusé : un export WordPress ne contient pas de déclaration DOCTYPE.');
            }
            if ($r->nodeType === \XMLReader::ELEMENT && $r->depth === 0) {
                $wpNs = $r->lookupNamespace('wp');
                $contentNs = $r->lookupNamespace('content') ?: 'http://purl.org/rss/1.0/modules/content/';
                $excerptNs = $r->lookupNamespace('excerpt') ?: 'http://wordpress.org/export/1.2/excerpt/';
            }
            if ($r->nodeType === \XMLReader::ELEMENT && $r->depth === 1 && $r->localName === 'channel') {
                break;
            }
        }
        if (!$wpNs || $r->localName !== 'channel') {
            return $not;
        }
        $wpNs = (string) $wpNs;
        $doc = new \DOMDocument();
        $element = static function () use ($r, $doc): ?\SimpleXMLElement {
            $node = @$r->expand();
            $sx = $node instanceof \DOMNode ? simplexml_import_dom($doc->importNode($node, true)) : null;
            return $sx instanceof \SimpleXMLElement ? $sx : null;
        };
        $out = ['site' => '', 'title' => '', 'categories' => [], 'attachments' => [], 'count' => 0];
        $link = $blog = $base = '';
        $ok = @$r->read();
        while ($ok && $r->depth >= 2) {
            if ($r->nodeType !== \XMLReader::ELEMENT || $r->depth !== 2) {
                $ok = @$r->read();
                continue;
            }
            $name = $r->name;
            if ($name === 'title' || $name === 'link' || $name === 'wp:base_blog_url' || $name === 'wp:base_site_url') {
                $value = trim($r->readString());
                match ($name) {
                    'title' => $out['title'] = $value,
                    'link' => $link = $value,
                    'wp:base_blog_url' => $blog = $value,
                    default => $base = $value,
                };
            } elseif ($name === 'wp:category' && ($cat = $element()) !== null) {
                $c = $cat->children($wpNs);
                $slug = rawurldecode(trim((string) $c->category_nicename));
                if ($slug !== '') {
                    $out['categories'][$slug] = trim((string) $c->cat_name) ?: $slug;
                }
            } elseif ($name === 'item' && ($item = $element()) !== null) {
                $out['site'] = rtrim($blog ?: $base ?: $link, '/');
                $w = $item->children($wpNs);
                $type = (string) $w->post_type;
                $id = (int) $w->post_id;
                $status = (string) $w->status;
                if ($type === 'attachment' && $id > 0) {
                    $out['attachments'][$id] = trim((string) $w->attachment_url);
                } elseif (in_array($type, ['post', 'page'], true) && $id >= 1 && in_array($status, ['publish', 'future', 'draft', 'pending', 'private'], true)) {
                    if ($out['count'] >= self::MAX) {
                        return t('Plus de {n} contenus dans ce fichier : exporte-les en plusieurs fois (par type ou par période).', ['n' => self::MAX]);
                    }
                    $out['count']++;
                    $sink(self::item($item, $w, $id, $type, $status, $contentNs, $excerptNs, $wpNs, $out['title'], $out['site']));
                }
            }
            $ok = @$r->next();
        }
        // Fichier tronqué ou mal formé : rien n'est importé à moitié sans le dire.
        if (libxml_get_last_error() !== false) {
            return $out['count'] === 0 && $out['attachments'] === [] ? $not : t('Fichier d’export incomplet ou abîmé : exporte-le de nouveau depuis WordPress.');
        }
        $out['site'] = rtrim($blog ?: $base ?: $link, '/');
        return $out;
    }

    /** Un contenu de l'export, prêt pour l'import. */
    private static function item(\SimpleXMLElement $item, \SimpleXMLElement $w, int $id, string $type, string $status, string $contentNs, string $excerptNs,
        string $wpNs, string $siteName, string $site): array
    {
        $cats = [];
        foreach ($item->category as $c) {
            $attrs = $c->attributes();
            if ((string) ($attrs['domain'] ?? '') === 'category') {
                $cats[rawurldecode((string) ($attrs['nicename'] ?? ''))] = trim((string) $c);
            }
        }
        $thumb = 0;
        $meta = [];
        foreach ($w->postmeta as $row) {
            $m = $row->children($wpNs);
            $key = (string) $m->meta_key;
            if ($key === '_thumbnail_id') {
                $thumb = (int) $m->meta_value;
            } elseif (isset(self::SEO_KEYS[$key])) {
                $meta[$key] = mb_substr(trim((string) $m->meta_value), 0, 1000);
            }
        }
        return [
            'wp' => $id,
            'type' => $type,
            'status' => in_array($status, ['publish', 'future'], true) ? 'published' : 'draft',
            'title' => trim((string) $item->title),
            'slug' => rawurldecode(trim((string) $w->post_name)),
            'route' => self::route(trim((string) $item->link), $site),
            'date' => trim((string) ($w->post_date_gmt && (string) $w->post_date_gmt !== '0000-00-00 00:00:00' ? $w->post_date_gmt . ' UTC' : $w->post_date)),
            'html' => (string) $item->children($contentNs)->encoded,
            'excerpt' => trim(strip_tags((string) $item->children($excerptNs)->encoded)),
            'cats' => array_filter($cats, static fn (string $name, string $slug): bool => $slug !== '', ARRAY_FILTER_USE_BOTH),
            'thumb' => $thumb,
            'seo' => self::seo($meta, trim((string) $item->title), $siteName, $site),
        ];
    }

    /**
     * Référencement saisi dans Yoast SEO, Rank Math, SEOPress ou All in One SEO (avant sa version 4), lu dans les champs
     * personnalisés de l'export : titre, description, mot-clé principal, noindex et canonical. Un titre ou une description
     * qui n'est que le modèle de l'extension (« %%title%% %%sep%% %%sitename%% ») n'est pas repris : Éclair applique le sien.
     * Une canonical qui vise l'ancien site est ignorée (Éclair calcule la sienne) ; vers un autre site, elle est gardée.
     */
    private static function seo(array $meta, string $title, string $siteName, string $site): array
    {
        $pick = static function (array $keys) use ($meta): string {
            foreach ($keys as $k) {
                if (($meta[$k] ?? '') !== '') {
                    return $meta[$k];
                }
            }
            return '';
        };
        $norm = static fn (string $t): string => mb_strtolower((string) preg_replace('~[^\p{L}\p{N}]+~u', '', $t));
        $resolve = static function (string $value) use ($title, $siteName, $norm): string {
            if ($value === '' || !str_contains($value, '%')) {
                return $value;
            }
            $known = ['title' => $title, 'sitename' => $siteName, 'sitetitle' => $siteName, 'sep' => '·', 'page' => '', 'pagenumber' => '', 'pagetotal' => ''];
            $text = (string) preg_replace_callback('~%%?([a-z_]+)%%?~i', static fn (array $m): string => $known[strtolower($m[1])] ?? $m[0], $value);
            $text = (string) preg_replace('~^[ ·|\-–—]+|[ ·|\-–—]+$~u', '', (string) preg_replace('~\s+~u', ' ', $text));
            // Variable inconnue (%%excerpt%%…) ou simple modèle « titre · site » : rien de personnel à reprendre.
            return preg_match('~%%?[a-z_]+%%?~i', $text) || in_array($norm($text), [$norm($title), $norm($title . $siteName), $norm($siteName . $title)], true) ? '' : $text;
        };
        $canonical = $pick(['_yoast_wpseo_canonical', 'rank_math_canonical_url', '_seopress_robots_canonical']);
        $host = static fn (string $url): string => (string) preg_replace('~^www\.~', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        if (!preg_match('~^https?://~i', $canonical) || $host($canonical) === $host($site)) {
            $canonical = '';
        }
        return [
            'title' => $resolve($pick(['_yoast_wpseo_title', 'rank_math_title', '_seopress_titles_title', '_aioseop_title'])),
            'description' => $resolve($pick(['_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc', '_aioseop_description'])),
            'keyword' => trim(explode(',', $pick(['_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_seopress_analysis_target_kw']))[0]),
            'canonical' => $canonical,
            'noindex' => ($meta['_yoast_wpseo_meta-robots-noindex'] ?? '') === '1' || str_contains($meta['rank_math_robots'] ?? '', 'noindex')
                || ($meta['_seopress_robots_index'] ?? '') === 'yes' || ($meta['_aioseop_noindex'] ?? '') === 'on',
        ];
    }

    /**
     * Adresse exacte d'un contenu d'après son lien dans l'export, s'il appartient à l'ancien site et n'est pas une adresse à
     * paramètres (/?p=123 : reprise par un alias) ; '' sinon.
     */
    public static function route(string $link, string $site): string
    {
        $p = parse_url($link);
        $host = strtolower((string) ($p['host'] ?? ''));
        $siteHost = strtolower((string) parse_url($site, PHP_URL_HOST));
        if ($host === '' || preg_replace('~^www\.~', '', $host) !== preg_replace('~^www\.~', '', $siteHost) || isset($p['query'])) {
            return '';
        }
        $path = rawurldecode((string) ($p['path'] ?? ''));
        // Site WordPress installé dans un sous-dossier : l'adresse est reprise sans lui.
        $base = rtrim((string) parse_url($site, PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        return $path === '/' ? '' : $path;
    }

    /**
     * HTML d'un contenu WordPress → blocs. $image(url, alt) renvoie l'identifiant d'un média ou null (image laissée en lien).
     * Renvoie [blocs, ['images' => n, 'missed' => n, 'shortcodes' => n, 'dropped' => n]].
     */
    public static function blocks(string $html, callable $image): array
    {
        // Éditeur de blocs : chaque paragraphe a déjà sa balise. Éditeur classique : WordPress les ajoute à l'affichage (wpautop).
        $classic = !str_contains($html, '<!-- wp:');
        $html = self::shortcodes($html);
        // Compté après la conversion des codes courts de WordPress lui-même : ne restent que ceux qui seront laissés en texte.
        $stats = ['images' => 0, 'missed' => 0, 'shortcodes' => preg_match_all('~\[/?[a-z][a-z0-9_-]*(\s[^\]]*)?\]~i', $html), 'dropped' => 0];
        $html = (string) preg_replace('~<!--.*?-->~s', '', $html);
        if ($classic) {
            $html = self::autop($html);
        }
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $dom->getElementsByTagName('body')->item(0);
        $blocks = [];
        if ($body !== null) {
            self::walk($body, $blocks, $stats, $image);
        }
        return [$blocks, $stats];
    }

    /**
     * Codes courts de WordPress lui-même : [caption] (image et sa légende, éditeur classique) devient une figure, [embed]
     * laisse son adresse seule sur sa ligne (une vidéo YouTube ou Vimeo devient alors un bloc vidéo).
     */
    private static function shortcodes(string $html): string
    {
        $html = (string) preg_replace_callback('~\[caption\b([^\]]*)\](.*?)\[/caption\]~is', static function (array $m): string {
            if (!preg_match('~^\s*((?:<a\b[^>]*>\s*)?<img\b[^>]*>(?:\s*</a>)?)(.*)$~is', $m[2], $p)) {
                return $m[2];
            }
            // Légende : le texte qui suit l'image, ou l'attribut caption="…" (avant WordPress 3.4).
            $caption = trim($p[2]);
            if ($caption === '' && preg_match('~\bcaption=(["\'])(.*?)\1~s', $m[1], $c)) {
                $caption = e(html_entity_decode($c[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            return "\n\n<figure>" . $p[1] . ($caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '') . "</figure>\n\n";
        }, $html);
        return (string) preg_replace('~\[embed\b[^\]]*\]\s*(https?://[^\s\[\]<>"]+)\s*\[/embed\]~i', "\n\n$1\n\n", $html);
    }

    /**
     * Paragraphes de l'éditeur classique, comme wpautop de WordPress : le texte séparé par des lignes vides devient des
     * paragraphes, un simple retour à la ligne un <br>, sans toucher aux blocs (listes, citations, tableaux, <pre>…).
     * Sans cela, un article classique qui contient ne serait-ce qu'une liste arrivait en un seul paragraphe, ou morcelé à
     * chaque mot en gras.
     */
    private static function autop(string $html): string
    {
        $kept = [];
        $html = (string) preg_replace_callback('~<(pre|script|style)\b.*?</\1>~is', static function (array $m) use (&$kept): string {
            $kept[] = $m[0];
            return "\n\n\x02" . (count($kept) - 1) . "\x03\n\n";
        }, str_replace(["\x02", "\x03"], '', $html));
        $html = (string) preg_replace('~(<(?:' . self::BLOCK_TAGS . ')\b[^>]*>)~i', "\n\n$1", $html);
        $html = (string) preg_replace('~(</(?:' . self::BLOCK_TAGS . ')>)~i', "$1\n\n", $html);
        $out = '';
        foreach (preg_split('~\R\s*\R~', $html) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('~^(?:</?(?:' . self::BLOCK_TAGS . ')\b|\x02\d+\x03$)~i', $chunk)) {
                $out .= $chunk . "\n";
            } else {
                $out .= '<p>' . preg_replace('~(?<!>)\R|(?<!<br>|<br/>|<br />)(?<=>)\R~i', "<br>\n", $chunk) . "</p>\n";
            }
        }
        return (string) preg_replace_callback('~\x02(\d+)\x03~', static fn (array $m): string => $kept[(int) $m[1]] ?? '', $out);
    }

    /** Texte seul : un paragraphe, ou un bloc vidéo si ce n'est que l'adresse d'une vidéo YouTube ou Vimeo (oEmbed de WordPress). */
    private static function text(string $plain, string $html, array &$blocks): void
    {
        $plain = trim($plain);
        if ($plain !== '' && !preg_match('~\s~u', $plain) && \Eclair\Blocks::video($plain) !== null) {
            $blocks[] = ['type' => 'video', 'url' => $plain, 'caption' => ''];
        } elseif ($plain !== '') {
            $blocks[] = ['type' => 'paragraph', 'text' => $html];
        }
    }

    /** Éléments d'une liste ; ceux d'une sous-liste suivent leur élément parent (Éclair n'imbrique pas les listes) au lieu d'être perdus. */
    private static function items(\DOMElement $list, array &$items): void
    {
        foreach (iterator_to_array($list->childNodes) as $li) {
            if (!$li instanceof \DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }
            $subs = [];
            foreach (array_merge(iterator_to_array($li->getElementsByTagName('ul'), false), iterator_to_array($li->getElementsByTagName('ol'), false)) as $sub) {
                // Seulement les sous-listes de premier niveau : les suivantes sont parcourues avec leur propre parent.
                for ($up = $sub->parentNode; $up !== null && $up !== $li && !in_array(strtolower($up->nodeName), ['ul', 'ol'], true); $up = $up->parentNode);
                if ($up === $li) {
                    $subs[] = $sub;
                }
            }
            foreach ($subs as $sub) {
                $sub->parentNode?->removeChild($sub);
            }
            $items[] = trim(self::inner($li));
            foreach ($subs as $sub) {
                self::items($sub, $items);
            }
        }
    }

    private static function walk(\DOMNode $parent, array &$blocks, array &$stats, callable $image): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMText) {
                self::text($node->textContent, e(trim($node->textContent)), $blocks);
                continue;
            }
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            switch ($tag) {
                case 'p':
                    // Images d'un paragraphe : blocs à part, avant le texte qui reste.
                    foreach (iterator_to_array($node->getElementsByTagName('img')) as $img) {
                        self::image($img, '', $blocks, $stats, $image);
                        $img->parentNode?->removeChild($img);
                    }
                    $inner = trim(self::inner($node));
                    self::text(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $inner, $blocks);
                    break;
                case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                    $blocks[] = ['type' => 'heading', 'level' => max(2, min(4, (int) $tag[1])), 'text' => trim(self::inner($node))];
                    break;
                case 'ul': case 'ol':
                    $items = [];
                    self::items($node, $items);
                    $blocks[] = ['type' => 'list', 'style' => $tag, 'items' => $items];
                    break;
                case 'blockquote':
                    $cite = '';
                    foreach (iterator_to_array($node->getElementsByTagName('cite')) as $c) {
                        $cite = trim($c->textContent);
                        $c->parentNode?->removeChild($c);
                    }
                    $parts = [];
                    foreach ($node->childNodes as $child) {
                        $t = trim($child instanceof \DOMElement ? self::inner($child) : $child->textContent);
                        if ($t !== '') {
                            $parts[] = $t;
                        }
                    }
                    $blocks[] = ['type' => 'quote', 'text' => implode('<br>', $parts), 'cite' => $cite];
                    break;
                case 'pre':
                    $blocks[] = ['type' => 'code', 'code' => $node->textContent, 'lang' => ''];
                    break;
                case 'hr':
                    $blocks[] = ['type' => 'divider'];
                    break;
                case 'img':
                    self::image($node, '', $blocks, $stats, $image);
                    break;
                case 'figure':
                    $imgs = iterator_to_array($node->getElementsByTagName('img'), false);
                    if ($imgs) {
                        // Une galerie (plusieurs images) donne une image par bloc ; la légende va à la dernière.
                        $cap = $node->getElementsByTagName('figcaption')->item(0);
                        foreach ($imgs as $i => $img) {
                            self::image($img, $cap && $i === count($imgs) - 1 ? trim(self::inner($cap)) : '', $blocks, $stats, $image, self::link($img));
                        }
                    } elseif ($node->getElementsByTagName('iframe')->length) {
                        self::walk($node, $blocks, $stats, $image);
                    } else {
                        self::walk($node, $blocks, $stats, $image);
                    }
                    break;
                case 'iframe':
                    $src = (string) $node->getAttribute('src');
                    if (preg_match('~^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com)/~', $src)) {
                        $blocks[] = ['type' => 'video', 'url' => preg_replace('~youtube(-nocookie)?\.com/embed/([\w-]+).*~', 'youtube.com/watch?v=$2', $src) ?? $src, 'caption' => ''];
                    } else {
                        $stats['dropped']++;
                    }
                    break;
                case 'table':
                    // Vrai tableau (12 colonnes au plus, comme le bloc d'Éclair) ; au-delà, une ligne de texte par rangée.
                    $rows = [];
                    $head = false;
                    $wide = false;
                    foreach (iterator_to_array($node->getElementsByTagName('tr')) as $r => $tr) {
                        $cells = [];
                        foreach ($tr->childNodes as $td) {
                            if ($td instanceof \DOMElement && in_array(strtolower($td->tagName), ['td', 'th'], true)) {
                                $cells[] = trim(self::inner($td));
                                $head = $head || ($r === 0 && strtolower($td->tagName) === 'th');
                            }
                        }
                        $wide = $wide || count($cells) > 12;
                        if (array_filter($cells, 'strlen')) {
                            $rows[] = $cells;
                        }
                    }
                    if ($wide || !$rows) {
                        foreach ($rows as $cells) {
                            $blocks[] = ['type' => 'paragraph', 'text' => e(implode(' · ', array_map(static fn (string $c): string => trim(strip_tags($c)), array_filter($cells, 'strlen'))))];
                        }
                    } else {
                        $cap = $node->getElementsByTagName('caption')->item(0);
                        $blocks[] = ['type' => 'table', 'head' => $head, 'caption' => $cap ? trim($cap->textContent) : '', 'rows' => $rows];
                    }
                    break;
                case 'details':
                    // Questions-réponses (accordéons Elementor, bascules Divi, bloc Détails) : un bloc FAQ, les suivantes ajoutées.
                    $summary = $node->getElementsByTagName('summary')->item(0);
                    $q = $summary ? trim($summary->textContent) : '';
                    $summary?->parentNode?->removeChild($summary);
                    $a = trim(self::inner($node));
                    if ($q !== '' && trim(strip_tags($a)) !== '') {
                        $last = count($blocks) - 1;
                        if ($last >= 0 && $blocks[$last]['type'] === 'faq' && count($blocks[$last]['items']) < 50) {
                            $blocks[$last]['items'][] = ['q' => $q, 'a' => $a];
                        } else {
                            $blocks[] = ['type' => 'faq', 'title' => '', 'items' => [['q' => $q, 'a' => $a]]];
                        }
                    } else {
                        self::walk($node, $blocks, $stats, $image);
                    }
                    break;
                case 'video':
                case 'audio':
                    // Fichier vidéo ou son : un lien (Éclair intègre YouTube et Vimeo, pas les fichiers lourds).
                    $src = (string) $node->getAttribute('src');
                    foreach ($src === '' ? $node->getElementsByTagName('source') : [] as $source) {
                        $src = $src !== '' ? $src : (string) $source->getAttribute('src');
                    }
                    if (preg_match('~^https?://~i', $src)) {
                        $blocks[] = ['type' => 'paragraph', 'text' => '<a href="' . e($src) . '">' . e(basename((string) parse_url($src, PHP_URL_PATH)) ?: $src) . '</a>'];
                    } else {
                        $stats['dropped']++;
                    }
                    break;
                case 'script': case 'style': case 'form': case 'noscript': case 'object': case 'embed': case 'svg':
                    $stats['dropped']++;
                    break;
                default:
                    // Conteneurs (div, section, colonnes, groupes…) : leur contenu est repris.
                    self::walk($node, $blocks, $stats, $image);
            }
        }
    }

    /** Lien qui entoure une image (<a href><img></a>), ou ''. */
    private static function link(\DOMElement $img): string
    {
        $a = $img->parentNode;
        return $a instanceof \DOMElement && strtolower($a->tagName) === 'a' ? (string) $a->getAttribute('href') : '';
    }

    private static function image(\DOMElement $img, string $caption, array &$blocks, array &$stats, callable $image, string $link = ''): void
    {
        $src = trim((string) $img->getAttribute('src'));
        // Image à chargement différé (extension de performance) : la vraie adresse est dans un attribut data-.
        foreach ($src === '' || str_starts_with($src, 'data:') ? ['data-src', 'data-lazy-src', 'data-orig-file'] : [] as $attr) {
            $src = trim((string) $img->getAttribute($attr)) ?: $src;
        }
        $alt = trim((string) $img->getAttribute('alt'));
        $media = $src !== '' ? $image($src, $alt) : null;
        if (is_string($media) && $media !== '') {
            $stats['images']++;
            $blocks[] = ['type' => 'image', 'media' => $media, 'alt' => $alt, 'caption' => $caption, 'size' => 'normal'] + ($link !== '' ? ['link' => $link] : []);
            return;
        }
        $stats['missed']++;
        if ($src !== '' && preg_match('~^https?://~i', $src)) {
            $blocks[] = ['type' => 'paragraph', 'text' => '<a href="' . e($src) . '">' . e($alt !== '' ? $alt : t('Image de l’ancien site')) . '</a>'];
        }
    }

    private static function inner(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= (string) $node->ownerDocument?->saveHTML($child);
        }
        return $html;
    }
}
