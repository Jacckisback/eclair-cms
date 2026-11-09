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
    public const MAX = 3000;
    public const MAX_BYTES = 40 * 1048576;

    /**
     * Export lu : ['site' => adresse de l'ancien site, 'title', 'categories' => [nicename => nom], 'attachments' => [id => url],
     * 'items' => [contenu…]] ; ou un message d'erreur.
     */
    public static function parse(string $xml): array|string
    {
        if (!function_exists('simplexml_load_string') || !class_exists(\DOMDocument::class)) {
            return t('L’import demande les extensions PHP SimpleXML et DOM, absentes de ton hébergement.');
        }
        if (strlen($xml) > self::MAX_BYTES) {
            return t('Fichier trop volumineux ({n} Mo au plus).', ['n' => self::MAX_BYTES / 1048576]);
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return t('Fichier refusé : un export WordPress ne contient pas de déclaration DOCTYPE.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $ns = $doc instanceof \SimpleXMLElement ? $doc->getDocNamespaces(true) : [];
        if (!$doc instanceof \SimpleXMLElement || !isset($doc->channel, $ns['wp'])) {
            return t('Ce fichier n’est pas un export WordPress (Outils › Exporter, « Tout le contenu »).');
        }
        $wpNs = (string) $ns['wp'];
        $contentNs = (string) ($ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/');
        $excerptNs = (string) ($ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/');
        $channel = $doc->channel;
        $cw = $channel->children($wpNs);
        $site = rtrim((string) ($cw->base_blog_url ?: $cw->base_site_url ?: $channel->link), '/');
        $out = ['site' => $site, 'title' => trim((string) $channel->title), 'categories' => [], 'attachments' => [], 'items' => []];
        foreach ($cw->category as $cat) {
            $c = $cat->children($wpNs);
            $slug = rawurldecode(trim((string) $c->category_nicename));
            if ($slug !== '') {
                $out['categories'][$slug] = trim((string) $c->cat_name) ?: $slug;
            }
        }
        foreach ($channel->item as $item) {
            $w = $item->children($wpNs);
            $type = (string) $w->post_type;
            $id = (int) $w->post_id;
            if ($type === 'attachment' && $id > 0) {
                $out['attachments'][$id] = trim((string) $w->attachment_url);
                continue;
            }
            $status = (string) $w->status;
            if (!in_array($type, ['post', 'page'], true) || $id < 1 || !in_array($status, ['publish', 'future', 'draft', 'pending', 'private'], true)) {
                continue;
            }
            if (count($out['items']) >= self::MAX) {
                return t('Plus de {n} contenus dans ce fichier : exporte-les en plusieurs fois (par type ou par période).', ['n' => self::MAX]);
            }
            $cats = [];
            foreach ($item->category as $c) {
                $attrs = $c->attributes();
                if ((string) ($attrs['domain'] ?? '') === 'category') {
                    $cats[rawurldecode((string) ($attrs['nicename'] ?? ''))] = trim((string) $c);
                }
            }
            $thumb = 0;
            foreach ($w->postmeta as $meta) {
                $m = $meta->children($wpNs);
                if ((string) $m->meta_key === '_thumbnail_id') {
                    $thumb = (int) $m->meta_value;
                }
            }
            $out['items'][] = [
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
            ];
        }
        return $out;
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
        $stats = ['images' => 0, 'missed' => 0, 'shortcodes' => preg_match_all('~\[/?[a-z][a-z0-9_-]*(\s[^\]]*)?\]~i', $html), 'dropped' => 0];
        $html = (string) preg_replace('~<!--.*?-->~s', '', $html);
        // Éditeur classique : paragraphes séparés par des lignes vides, sans balise (wpautop de WordPress).
        if (!preg_match('~<(p|h[1-6]|ul|ol|blockquote|figure|div|table|pre)\b~i', $html)) {
            $paras = preg_split('~\R\s*\R~', trim($html)) ?: [];
            $html = implode('', array_map(static fn (string $p): string => '<p>' . nl2br(trim($p), false) . '</p>', array_filter($paras, static fn (string $p): bool => trim($p) !== '')));
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

    private static function walk(\DOMNode $parent, array &$blocks, array &$stats, callable $image): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMText) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $blocks[] = ['type' => 'paragraph', 'text' => e($text)];
                }
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
                    if (trim(strip_tags($inner)) !== '') {
                        $blocks[] = ['type' => 'paragraph', 'text' => $inner];
                    }
                    break;
                case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                    $blocks[] = ['type' => 'heading', 'level' => max(2, min(4, (int) $tag[1])), 'text' => trim(self::inner($node))];
                    break;
                case 'ul': case 'ol':
                    $items = [];
                    foreach ($node->childNodes as $li) {
                        if ($li instanceof \DOMElement && strtolower($li->tagName) === 'li') {
                            foreach (array_merge(iterator_to_array($li->getElementsByTagName('ul'), false), iterator_to_array($li->getElementsByTagName('ol'), false)) as $sub) {
                                $sub->parentNode?->removeChild($sub);
                            }
                            $items[] = trim(self::inner($li));
                        }
                    }
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
