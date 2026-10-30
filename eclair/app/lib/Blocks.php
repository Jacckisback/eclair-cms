<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Blocs de contenu : validation, rendu HTML, export Markdown (GEO) et extraction
 * (texte, sommaire, FAQ, images, liens) pour l'analyse SEO.
 */
final class Blocks
{
    public const TYPES = [
        'paragraph', 'heading', 'list', 'quote', 'image', 'callout', 'faq', 'table',
        'button', 'video', 'code', 'divider', 'hero', 'features', 'team', 'posts', 'products', 'contact',
    ];
    private const CALLOUTS = ['info' => 'ℹ️', 'tip' => '💡', 'warning' => '⚠️', 'success' => '✅', 'note' => '📝'];
    private const RESERVED_IDS = ['main', 'top', 'toc', 'menu', 'contenu', 'content', 'search', 'site-nav'];

    /* ───────────────────────────── Validation ───────────────────────────── */

    public static function normalize(mixed $blocks): array
    {
        $out = [];
        foreach (is_array($blocks) ? array_slice($blocks, 0, 2000) : [] as $block) {
            if (is_array($block) && ($clean = self::one($block)) !== null) {
                $out[] = $clean;
            }
        }
        return $out;
    }

    private static function one(array $b): ?array
    {
        $type = (string) ($b['type'] ?? '');
        $s = static fn (string $k, int $max = 300): string => Sanitize::text($b[$k] ?? '', $max);
        $rich = static fn (string $k): string => Sanitize::inline(is_string($b[$k] ?? null) ? $b[$k] : '');
        $pick = static fn (string $k, array $allowed, string $default): string =>
            in_array($b[$k] ?? null, $allowed, true) ? (string) $b[$k] : $default;

        switch ($type) {
            case 'paragraph':
                $text = $rich('text');
                return $text === '' ? null : ['type' => 'paragraph', 'text' => $text];

            case 'heading':
                $text = $rich('text');
                $level = (int) ($b['level'] ?? 2);
                return $text === '' ? null : [
                    'type' => 'heading',
                    'level' => in_array($level, [2, 3, 4], true) ? $level : 2,
                    'text' => $text,
                    'anchor' => slugify($s('anchor', 80)),
                ];

            case 'list':
                $items = [];
                foreach (array_slice((array) ($b['items'] ?? []), 0, 300) as $item) {
                    $item = is_string($item) ? Sanitize::inline($item) : '';
                    if ($item !== '') {
                        $items[] = $item;
                    }
                }
                return $items ? ['type' => 'list', 'style' => $pick('style', ['ul', 'ol'], 'ul'), 'items' => $items] : null;

            case 'quote':
                $text = $rich('text');
                return $text === '' ? null : ['type' => 'quote', 'text' => $text, 'cite' => $s('cite', 200)];

            case 'image':
                $media = preg_replace('/[^a-z0-9]/', '', (string) ($b['media'] ?? '')) ?? '';
                return $media === '' ? null : [
                    'type' => 'image',
                    'media' => $media,
                    'alt' => $s('alt', 250),
                    'caption' => $rich('caption'),
                    'link' => Sanitize::url((string) ($b['link'] ?? '')),
                    'size' => $pick('size', ['normal', 'wide', 'small'], 'normal'),
                ];

            case 'callout':
                $text = $rich('text');
                return $text === '' ? null : [
                    'type' => 'callout',
                    'variant' => $pick('variant', array_keys(self::CALLOUTS), 'info'),
                    'title' => $s('title', 150),
                    'text' => $text,
                ];

            case 'faq':
                $items = [];
                foreach (array_slice((array) ($b['items'] ?? []), 0, 50) as $item) {
                    $q = Sanitize::text(is_array($item) ? ($item['q'] ?? '') : '', 300);
                    $a = Sanitize::inline(is_array($item) && is_string($item['a'] ?? null) ? $item['a'] : '');
                    if ($q !== '' && $a !== '') {
                        $items[] = ['q' => $q, 'a' => $a];
                    }
                }
                return $items ? ['type' => 'faq', 'title' => $s('title', 150), 'items' => $items] : null;

            case 'table':
                $rows = [];
                foreach (array_slice((array) ($b['rows'] ?? []), 0, 200) as $row) {
                    $cells = [];
                    foreach (array_slice(is_array($row) ? $row : [], 0, 12) as $cell) {
                        $cells[] = is_string($cell) ? Sanitize::inline($cell) : '';
                    }
                    if (array_filter($cells, fn ($c) => $c !== '')) {
                        $rows[] = $cells;
                    }
                }
                return $rows ? ['type' => 'table', 'head' => !empty($b['head']), 'caption' => $s('caption', 200), 'rows' => $rows] : null;

            case 'button':
                $text = $s('text', 80);
                $url = Sanitize::url((string) ($b['url'] ?? ''));
                return $text === '' || $url === '' ? null : [
                    'type' => 'button',
                    'text' => $text,
                    'url' => $url,
                    'style' => $pick('style', ['primary', 'secondary'], 'primary'),
                    'align' => $pick('align', ['left', 'center'], 'left'),
                ];

            case 'video':
                $url = Sanitize::url((string) ($b['url'] ?? ''));
                return self::video($url) === null ? null : ['type' => 'video', 'url' => $url, 'caption' => $s('caption', 200)];

            case 'code':
                $code = Sanitize::code($b['code'] ?? '');
                return trim($code) === '' ? null : [
                    'type' => 'code',
                    'code' => $code,
                    'lang' => preg_replace('/[^a-z0-9+#\-]/', '', strtolower($s('lang', 20))) ?? '',
                ];

            case 'divider':
                return ['type' => 'divider'];

            case 'hero':
                $title = $s('title', 200);
                return $title === '' ? null : [
                    'type' => 'hero',
                    'title' => $title,
                    'text' => $rich('text'),
                    'button' => $s('button', 60),
                    'url' => Sanitize::url((string) ($b['url'] ?? '')),
                    'button2' => $s('button2', 60),
                    'url2' => Sanitize::url((string) ($b['url2'] ?? '')),
                    'media' => preg_replace('/[^a-z0-9]/', '', (string) ($b['media'] ?? '')) ?? '',
                ];

            case 'features':
                $items = [];
                foreach (array_slice((array) ($b['items'] ?? []), 0, 12) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $title = Sanitize::text($item['title'] ?? '', 120);
                    $text = Sanitize::inline(is_string($item['text'] ?? null) ? $item['text'] : '');
                    if ($title !== '' || $text !== '') {
                        $items[] = ['icon' => Sanitize::text($item['icon'] ?? '', 8), 'title' => $title, 'text' => $text];
                    }
                }
                return $items ? ['type' => 'features', 'title' => $s('title', 200), 'items' => $items] : null;

            case 'team':
                $items = [];
                foreach (array_slice((array) ($b['items'] ?? []), 0, 60) as $item) {
                    $name = is_array($item) ? Sanitize::text($item['name'] ?? '', 120) : '';
                    if ($name !== '') {
                        $items[] = [
                            'name' => $name,
                            'role' => Sanitize::text($item['role'] ?? '', 120),
                            'bio' => Sanitize::inline(is_string($item['bio'] ?? null) ? $item['bio'] : ''),
                            'media' => preg_replace('/[^a-z0-9]/', '', (string) ($item['media'] ?? '')) ?? '',
                            'link' => Sanitize::url((string) ($item['link'] ?? '')),
                        ];
                    }
                }
                return $items ? ['type' => 'team', 'title' => $s('title', 200), 'items' => $items] : null;

            case 'posts':
            case 'products':
                return [
                    'type' => $type,
                    'title' => $s('title', 150),
                    'count' => max(1, min(12, (int) ($b['count'] ?? ($type === 'posts' ? 3 : 4)))),
                    'category' => preg_replace('/[^a-z0-9]/', '', (string) ($b['category'] ?? '')) ?? '',
                ];

            case 'contact':
                return ['type' => 'contact', 'title' => $s('title', 150), 'button' => $s('button', 40)];
        }
        return null;
    }

    /* ─────────────────────────────── Rendu HTML ────────────────────────────── */

    /**
     * @param array $ctx lang, h1 (le premier bandeau porte le H1), eager (première image prioritaire), page (id)
     */
    public static function html(array $blocks, array $ctx = []): string
    {
        $lang = (string) ($ctx['lang'] ?? Settings::get('lang'));
        $anchors = self::anchors($blocks);
        $eager = (bool) ($ctx['eager'] ?? false);
        $h1 = (bool) ($ctx['h1'] ?? false);
        $out = '';
        foreach ($blocks as $i => $b) {
            $out .= match ($b['type']) {
                'paragraph' => '<p>' . self::rich($b['text']) . "</p>\n",
                'heading' => sprintf("<h%d id=\"%s\">%s</h%1\$d>\n", $b['level'], e($anchors[$i]), self::rich($b['text'])),
                'list' => '<' . $b['style'] . '>' . implode('', array_map(fn ($li) => '<li>' . self::rich($li) . '</li>', $b['items'])) . '</' . $b['style'] . ">\n",
                'quote' => '<blockquote><p>' . self::rich($b['text']) . '</p>'
                    . ($b['cite'] !== '' ? '<p class="cite">— ' . e($b['cite']) . '</p>' : '') . "</blockquote>\n",
                'image' => self::image($b, $eager && $i < 2),
                'callout' => '<aside class="callout callout-' . $b['variant'] . '"><p class="callout-title"><span aria-hidden="true">'
                    . self::CALLOUTS[$b['variant']] . '</span> ' . e($b['title'] !== '' ? $b['title'] : Theme::t('callout_' . $b['variant'], $lang))
                    . '</p><p>' . self::rich($b['text']) . "</p></aside>\n",
                'faq' => self::faqHtml($b, $anchors[$i] ?? '', $lang),
                'table' => self::table($b),
                'button' => '<p class="btns' . ($b['align'] === 'center' ? ' center' : '') . '"><a class="btn btn-' . $b['style']
                    . '" href="' . e(self::href($b['url'])) . '">' . e($b['text']) . "</a></p>\n",
                'video' => self::videoHtml($b, $lang),
                'code' => '<pre class="code"><code' . ($b['lang'] !== '' ? ' class="lang-' . e($b['lang']) . '"' : '') . '>'
                    . e($b['code']) . "</code></pre>\n",
                'divider' => "<hr>\n",
                'hero' => self::hero($b, $h1 && $i === 0, $lang),
                'features' => self::features($b, $anchors[$i] ?? ''),
                'team' => self::team($b, $anchors[$i] ?? '', $lang),
                'posts' => self::postsHtml($b, $anchors[$i] ?? '', $lang),
                'products' => self::productsHtml($b, $anchors[$i] ?? '', $lang),
                'contact' => self::contactHtml($b, (string) ($ctx['page'] ?? ''), $lang),
                default => '',
            };
        }
        return $out;
    }

    /** Préfixe les liens internes (« /contact ») du dossier d'installation. */
    public static function rich(string $html): string
    {
        $base = Req::base();
        return $base === '' ? $html : (string) preg_replace('~ href="/(?!/)~', ' href="' . e($base) . '/', $html);
    }

    public static function href(string $url): string
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') ? path_to($url) : $url;
    }

    private static function image(array $b, bool $eager): string
    {
        $media = Media::get($b['media']);
        if (!$media) {
            return '';
        }
        $alt = $b['alt'] !== '' ? $b['alt'] : (string) ($media['alt'] ?? '');
        $sizes = $b['size'] === 'small' ? '(min-width: 30rem) 24rem, 100vw' : ($b['size'] === 'wide' ? '(min-width: 72rem) 68rem, 100vw' : '(min-width: 46rem) 42rem, 100vw');
        $img = Media::picture($media, $alt, $sizes, $eager);
        if ($b['link'] !== '') {
            $img = '<a href="' . e(self::href($b['link'])) . '">' . $img . '</a>';
        }
        $caption = $b['caption'] !== '' ? '<figcaption>' . self::rich($b['caption']) . '</figcaption>' : '';
        return '<figure class="img img-' . $b['size'] . '">' . $img . $caption . "</figure>\n";
    }

    private static function faqHtml(array $b, string $anchor, string $lang): string
    {
        $title = $b['title'] !== '' ? $b['title'] : Theme::t('faq', $lang);
        $out = '<section class="faq"><h2 id="' . e($anchor) . '">' . e($title) . '</h2>';
        foreach ($b['items'] as $item) {
            $out .= '<details><summary>' . e($item['q']) . '</summary><div><p>' . self::rich($item['a']) . '</p></div></details>';
        }
        return $out . "</section>\n";
    }

    private static function table(array $b): string
    {
        $rows = $b['rows'];
        $cols = max(array_map('count', $rows));
        $cell = static function (array $row, string $tag) use ($cols): string {
            $html = '';
            for ($c = 0; $c < $cols; $c++) {
                $html .= "<$tag>" . self::rich($row[$c] ?? '') . "</$tag>";
            }
            return '<tr>' . $html . '</tr>';
        };
        $out = '<div class="table"><table>' . ($b['caption'] !== '' ? '<caption>' . e($b['caption']) . '</caption>' : '');
        if ($b['head']) {
            $out .= '<thead>' . $cell(array_shift($rows), 'th') . '</thead>';
        }
        $out .= '<tbody>' . implode('', array_map(fn ($r) => $cell($r, 'td'), $rows)) . '</tbody>';
        return $out . "</table></div>\n";
    }

    private static function videoHtml(array $b, string $lang): string
    {
        $v = self::video($b['url']);
        if (!$v) {
            return '';
        }
        $label = $b['caption'] !== '' ? $b['caption'] : Theme::t('video', $lang);
        return '<figure class="video"><a class="video-lite" href="' . e($v['watch']) . '" data-embed="' . e($v['embed'])
            . '" target="_blank" rel="noopener"><span class="video-play" aria-hidden="true"></span><span class="video-label">'
            . e(Theme::t('play_video', $lang)) . ' · ' . e($label) . '</span></a>'
            . ($b['caption'] !== '' ? '<figcaption>' . e($b['caption']) . '</figcaption>' : '') . "</figure>\n";
    }

    private static function hero(array $b, bool $isH1, string $lang): string
    {
        $tag = $isH1 ? 'h1' : 'h2';
        $buttons = '';
        if ($b['button'] !== '' && $b['url'] !== '') {
            $buttons .= '<a class="btn btn-primary btn-lg" href="' . e(self::href($b['url'])) . '">' . e($b['button']) . '</a>';
        }
        if ($b['button2'] !== '' && $b['url2'] !== '') {
            $buttons .= '<a class="btn btn-secondary btn-lg" href="' . e(self::href($b['url2'])) . '">' . e($b['button2']) . '</a>';
        }
        $media = $b['media'] !== '' ? Media::get($b['media']) : null;
        return '<section class="hero' . ($media ? ' hero-media' : '') . '"><div class="hero-text">'
            . "<$tag class=\"hero-title\">" . e($b['title']) . "</$tag>"
            . ($b['text'] !== '' ? '<p class="hero-lead">' . self::rich($b['text']) . '</p>' : '')
            . ($buttons !== '' ? '<p class="btns">' . $buttons . '</p>' : '') . '</div>'
            . ($media ? '<div class="hero-img">' . Media::picture($media, (string) $media['alt'], '(min-width: 60rem) 34rem, 100vw', true) . '</div>' : '')
            . "</section>\n";
    }

    private static function features(array $b, string $anchor): string
    {
        $out = '<section class="features">' . ($b['title'] !== '' ? '<h2 id="' . e($anchor) . '">' . e($b['title']) . '</h2>' : '') . '<div class="grid">';
        foreach ($b['items'] as $item) {
            $out .= '<div class="feature">' . ($item['icon'] !== '' ? '<span class="feature-icon" aria-hidden="true">' . e($item['icon']) . '</span>' : '')
                . ($item['title'] !== '' ? '<h3>' . e($item['title']) . '</h3>' : '')
                . ($item['text'] !== '' ? '<p>' . self::rich($item['text']) . '</p>' : '') . '</div>';
        }
        return $out . "</div></section>\n";
    }

    /** Équipe : portraits ronds (ou initiales), nom, fonction, présentation et lien de profil. */
    private static function team(array $b, string $anchor, string $lang): string
    {
        $out = '<section class="team"><h2 id="' . e($anchor) . '">' . e($b['title'] !== '' ? $b['title'] : Theme::t('team', $lang)) . '</h2><div class="grid">';
        foreach ($b['items'] as $p) {
            $m = $p['media'] !== '' ? Media::get($p['media']) : null;
            $initials = mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', $p['name']) ?: [], 0, 2))));
            $out .= '<div class="feature member">'
                . ($m ? Media::picture($m, (string) ($m['alt'] !== '' ? $m['alt'] : $p['name']), '8rem') : '<span class="member-initials" aria-hidden="true">' . e($initials) . '</span>')
                . '<h3>' . e($p['name']) . '</h3>'
                . ($p['role'] !== '' ? '<p class="member-role">' . e($p['role']) . '</p>' : '')
                . ($p['bio'] !== '' ? '<p>' . self::rich($p['bio']) . '</p>' : '')
                . ($p['link'] !== '' ? '<p class="member-link"><a href="' . e(self::href($p['link'])) . '">'
                    . e((string) preg_replace('/^www\./', '', (string) (parse_url($p['link'], PHP_URL_HOST) ?: $p['link']))) . ' ↗</a></p>' : '')
                . '</div>';
        }
        return $out . "</div></section>\n";
    }

    private static function postsHtml(array $b, string $anchor, string $lang): string
    {
        if (!Settings::get('blog.enabled')) {
            return '';
        }
        $posts = Content::latest($b['count'], $b['category']);
        if (!$posts) {
            return '';
        }
        $title = $b['title'] !== '' ? $b['title'] : Theme::t('latest_posts', $lang);
        return '<section class="posts-block"><h2' . ($anchor !== '' ? ' id="' . e($anchor) . '"' : '') . '>' . e($title) . '</h2>'
            . Theme::cards($posts, $lang)
            . '<p class="more"><a href="' . e(path_to(Content::blogPath())) . '">' . e(Theme::t('all_posts', $lang)) . " →</a></p></section>\n";
    }

    /** Derniers produits de la boutique (tous, ou ceux d'une catégorie). */
    public static function products(array $b): array
    {
        if (!Shop::enabled()) {
            return [];
        }
        $list = array_filter(Content::published('product'), fn ($p) => $b['category'] === '' || $p['category'] === $b['category']);
        return array_slice(array_values($list), 0, $b['count']);
    }

    private static function productsHtml(array $b, string $anchor, string $lang): string
    {
        $products = self::products($b);
        if (!$products) {
            return '';
        }
        $title = $b['title'] !== '' ? $b['title'] : Theme::t('products', $lang);
        return '<section class="posts-block products-block"><h2' . ($anchor !== '' ? ' id="' . e($anchor) . '"' : '') . '>' . e($title) . '</h2>'
            . Theme::products($products, $lang)
            . '<p class="more"><a href="' . e(path_to(Shop::path())) . '">' . e(Theme::t('all_products', $lang)) . " →</a></p></section>\n";
    }

    private static function contactHtml(array $b, string $page, string $lang): string
    {
        $t = static fn (string $k): string => e(Theme::t($k, $lang));
        $title = $b['title'] !== '' ? '<h2>' . e($b['title']) . '</h2>' : '';
        $privacy = Content::findBySlugAny(['politique-de-confidentialite', 'confidentialite', 'politica-de-privacidad', 'privacy-policy', 'privacy']);
        $notice = $t('form_notice') . ($privacy ? ' <a href="' . e(path_to(Content::path($privacy))) . '">' . $t('privacy') . '</a>.' : '');
        return '<section class="contact-block">' . $title
            . '<form class="contact" method="post" action="' . e(path_to('/_eclair/contact')) . '" data-k="' . e(Contact::token())
            . '" data-sending="' . $t('form_sending') . '" data-error="' . $t('form_error') . '">'
            . '<input type="hidden" name="page" value="' . e($page) . '"><input type="hidden" name="k" value="">'
            . '<div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>'
            . '<p><label for="cf-name">' . $t('form_name') . '</label><input id="cf-name" name="name" required maxlength="100" autocomplete="name"></p>'
            . '<p><label for="cf-email">' . $t('form_email') . '</label><input id="cf-email" type="email" name="email" required maxlength="190" autocomplete="email"></p>'
            . '<p><label for="cf-msg">' . $t('form_message') . '</label><textarea id="cf-msg" name="message" required rows="6" maxlength="5000"></textarea></p>'
            . '<p class="form-foot"><button class="btn btn-primary" type="submit">' . e($b['button'] !== '' ? $b['button'] : Theme::t('form_send', $lang)) . '</button>'
            . '<span class="form-status" role="status" aria-live="polite"></span></p>'
            . '<p class="form-notice">' . $notice . '</p></form></section>' . "\n";
    }

    /* ───────────────────────────── Extraction ───────────────────────────── */

    /** Identifiants uniques et stables des titres (ancres du sommaire). */
    public static function anchors(array $blocks): array
    {
        $used = array_fill_keys(self::RESERVED_IDS, true);
        $out = [];
        foreach ($blocks as $i => $b) {
            $text = match ($b['type']) {
                'heading' => $b['anchor'] !== '' ? $b['anchor'] : plain($b['text']),
                'faq' => $b['title'] !== '' ? $b['title'] : 'faq',
                'features', 'posts', 'products' => $b['title'] ?? '',
                'team' => $b['title'] !== '' ? $b['title'] : 'team',
                default => null,
            };
            if ($text === null || $text === '') {
                continue;
            }
            $id = slugify($text, 'fr', 60) ?: 'section';
            $candidate = $id;
            for ($n = 2; isset($used[$candidate]); $n++) {
                $candidate = $id . '-' . $n;
            }
            $used[$candidate] = true;
            $out[$i] = $candidate;
        }
        return $out;
    }

    /** Sommaire : titres H2/H3 (+ FAQ). */
    public static function toc(array $blocks, string $lang = 'fr'): array
    {
        $anchors = self::anchors($blocks);
        $out = [];
        foreach ($blocks as $i => $b) {
            if ($b['type'] === 'heading' && $b['level'] <= 3) {
                $out[] = ['level' => $b['level'], 'text' => plain($b['text']), 'id' => $anchors[$i]];
            } elseif ($b['type'] === 'faq') {
                $out[] = ['level' => 2, 'text' => $b['title'] !== '' ? $b['title'] : Theme::t('faq', $lang), 'id' => $anchors[$i]];
            }
        }
        return $out;
    }

    /** Questions/réponses de tous les blocs FAQ (schéma FAQPage). */
    public static function faq(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'faq') {
                foreach ($b['items'] as $item) {
                    $out[] = ['q' => $item['q'], 'a' => plain($item['a'])];
                }
            }
        }
        return $out;
    }

    /** Texte brut du contenu (analyse, recherche, nombre de mots). */
    public static function text(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $b) {
            $parts[] = match ($b['type']) {
                'paragraph', 'heading', 'quote' => plain($b['text']),
                'callout' => $b['title'] . ' ' . plain($b['text']),
                'list' => implode(' ', array_map('plain', $b['items'])),
                'faq' => implode(' ', array_map(fn ($i) => $i['q'] . ' ' . plain($i['a']), $b['items'])),
                'table' => implode(' ', array_map(fn ($r) => implode(' ', array_map('plain', $r)), $b['rows'])),
                'hero' => $b['title'] . ' ' . plain($b['text']),
                'features' => $b['title'] . ' ' . implode(' ', array_map(fn ($i) => $i['title'] . ' ' . plain($i['text']), $b['items'])),
                'team' => $b['title'] . ' ' . implode(' ', array_map(fn ($p) => $p['name'] . ' ' . $p['role'] . ' ' . plain($p['bio']), $b['items'])),
                'image' => plain($b['caption']),
                default => '',
            };
        }
        return trim((string) preg_replace('/\s+/u', ' ', implode(' ', $parts)));
    }

    /** Premier paragraphe (introduction). */
    public static function intro(array $blocks): string
    {
        foreach ($blocks as $b) {
            if ($b['type'] === 'paragraph') {
                return plain($b['text']);
            }
            if ($b['type'] === 'hero' && $b['text'] !== '') {
                return plain($b['text']);
            }
        }
        return '';
    }

    /** Identifiants des médias utilisés. */
    public static function media(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $b) {
            if (in_array($b['type'], ['image', 'hero'], true) && ($b['media'] ?? '') !== '') {
                $ids[] = $b['media'];
            }
            if ($b['type'] === 'team') {
                $ids = array_merge($ids, array_filter(array_column($b['items'], 'media')));
            }
        }
        return array_values(array_unique($ids));
    }

    /** Toutes les URL de liens (contenu enrichi + boutons). */
    public static function links(array $blocks): array
    {
        $html = '';
        $urls = [];
        array_walk_recursive($blocks, function ($value, $key) use (&$html, &$urls): void {
            if (is_string($value)) {
                if (in_array($key, ['url', 'url2', 'link'], true) && $value !== '') {
                    $urls[] = $value;
                } else {
                    $html .= $value . ' ';
                }
            }
        });
        preg_match_all('~<a href="([^"]+)"~', $html, $m);
        foreach ($m[1] as $href) {
            $urls[] = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $urls;
    }

    /** Lien vidéo YouTube / Vimeo → URLs de lecture et d'intégration respectueuses de la vie privée. */
    public static function video(string $url): ?array
    {
        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return [
                'provider' => 'youtube',
                'watch' => 'https://www.youtube.com/watch?v=' . $m[1],
                'embed' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0',
            ];
        }
        if (preg_match('~^https?://(?:www\.)?(?:vimeo\.com/|player\.vimeo\.com/video/)(\d{5,12})~', $url, $m)) {
            return [
                'provider' => 'vimeo',
                'watch' => 'https://vimeo.com/' . $m[1],
                'embed' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1',
            ];
        }
        return null;
    }

    /* ─────────────────────────────── Markdown ─────────────────────────────── */

    public static function markdown(array $blocks, string $lang = 'fr'): string
    {
        $out = [];
        foreach ($blocks as $b) {
            $out[] = match ($b['type']) {
                'paragraph' => self::md($b['text']),
                'heading' => str_repeat('#', $b['level']) . ' ' . self::md($b['text']),
                'list' => implode("\n", array_map(
                    fn ($li, $n) => ($b['style'] === 'ol' ? ($n + 1) . '. ' : '- ') . self::md($li),
                    $b['items'],
                    array_keys($b['items'])
                )),
                'quote' => '> ' . str_replace("\n", "\n> ", self::md($b['text'])) . ($b['cite'] !== '' ? "\n>\n> — " . self::mdText($b['cite']) : ''),
                'image' => self::mdImage($b),
                'callout' => '> **' . self::mdText($b['title'] !== '' ? $b['title'] : Theme::t('callout_' . $b['variant'], $lang)) . '** — '
                    . str_replace("\n", "\n> ", self::md($b['text'])),
                'faq' => '## ' . self::mdText($b['title'] !== '' ? $b['title'] : Theme::t('faq', $lang)) . "\n\n"
                    . implode("\n\n", array_map(fn ($i) => '### ' . self::mdText($i['q']) . "\n\n" . self::md($i['a']), $b['items'])),
                'table' => self::mdTable($b),
                'button' => self::mdLink($b['text'], $b['url']),
                'video' => ($v = self::video($b['url'])) ? self::mdLink('▶ ' . ($b['caption'] !== '' ? $b['caption'] : Theme::t('video', $lang)), $v['watch']) : '',
                'code' => "```" . $b['lang'] . "\n" . rtrim($b['code']) . "\n```",
                'divider' => '---',
                'hero' => '## ' . self::mdText($b['title']) . ($b['text'] !== '' ? "\n\n" . self::md($b['text']) : '')
                    . ($b['button'] !== '' && $b['url'] !== '' ? "\n\n" . self::mdLink($b['button'], $b['url']) : ''),
                'features' => ($b['title'] !== '' ? '## ' . self::mdText($b['title']) . "\n\n" : '')
                    . implode("\n\n", array_map(fn ($i) => '### ' . self::mdText(trim($i['icon'] . ' ' . $i['title'])) . ($i['text'] !== '' ? "\n\n" . self::md($i['text']) : ''), $b['items'])),
                'team' => '## ' . self::mdText($b['title'] !== '' ? $b['title'] : Theme::t('team', $lang)) . "\n\n"
                    . implode("\n\n", array_map(fn ($p) => '### ' . self::mdText($p['name']) . ($p['role'] !== '' ? ' — ' . self::mdText($p['role']) : '')
                        . ($p['bio'] !== '' ? "\n\n" . self::md($p['bio']) : '') . ($p['link'] !== '' ? "\n\n" . self::mdLink($p['link'], $p['link']) : ''), $b['items'])),
                'posts' => self::mdPosts($b, $lang),
                'products' => self::mdProducts($b, $lang),
                default => '',
            };
        }
        return trim(implode("\n\n", array_filter($out, fn ($s) => $s !== ''))) . "\n";
    }

    /**
     * HTML en ligne (déjà assaini) → Markdown. Chaque valeur n'est décodée qu'une fois, l'adresse des liens
     * est revalidée, et « < » reste du texte : un lecteur Markdown n'y verra jamais de balise active.
     */
    public static function md(string $html): string
    {
        $links = [];
        $md = (string) preg_replace_callback('~<a href="([^"]*)"[^>]*>(.*?)</a>~s', static function (array $m) use (&$links): string {
            $url = Sanitize::url(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '') {
                return $m[2];
            }
            $links[] = str_replace([' ', '(', ')', '<', '>'], ['%20', '%28', '%29', '%3C', '%3E'], self::abs($url));
            return '[' . $m[2] . "]\x01" . (count($links) - 1) . "\x02";
        }, str_replace(["\x01", "\x02"], '', $html));
        $md = strtr($md, [
            '<strong>' => '**', '</strong>' => '**', '<em>' => '*', '</em>' => '*', '<code>' => '`', '</code>' => '`',
            '<s>' => '~~', '</s>' => '~~', '<br>' => "  \n",
        ]);
        $md = self::mdText(html_entity_decode(strip_tags($md), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return trim((string) preg_replace_callback("~\x01(\\d+)\x02~", static fn (array $m): string => '(' . $links[(int) $m[1]] . ')', $md));
    }

    /** Texte brut inséré dans du Markdown : « < » hors code devient « &lt; ». */
    public static function mdText(string $text): string
    {
        return (string) preg_replace_callback('~(`[^`\n]*`)|<~', static fn (array $m): string => ($m[1] ?? '') !== '' ? $m[1] : '&lt;', $text);
    }

    /** Lien Markdown à partir d'un texte brut et d'une adresse (revalidée). */
    private static function mdLink(string $text, string $url): string
    {
        $url = Sanitize::url($url);
        $text = str_replace(['[', ']'], '', self::mdText($text));
        return $url === '' ? $text : '[' . $text . '](' . str_replace([' ', '(', ')', '<', '>'], ['%20', '%28', '%29', '%3C', '%3E'], self::abs($url)) . ')';
    }

    private static function abs(string $url): string
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') ? absolute_url($url) : $url;
    }

    private static function mdImage(array $b): string
    {
        $media = Media::get($b['media']);
        if (!$media) {
            return '';
        }
        $alt = $b['alt'] !== '' ? $b['alt'] : (string) $media['alt'];
        return '![' . str_replace(['[', ']'], '', self::mdText($alt)) . '](' . absolute_url(Media::url($media)) . ')'
            . ($b['caption'] !== '' ? "\n*" . self::md($b['caption']) . '*' : '');
    }

    private static function mdTable(array $b): string
    {
        $rows = $b['rows'];
        $cols = max(array_map('count', $rows));
        $line = static function (array $row) use ($cols): string {
            $cells = [];
            for ($c = 0; $c < $cols; $c++) {
                $cells[] = str_replace('|', '\|', self::md($row[$c] ?? ''));
            }
            return '| ' . implode(' | ', $cells) . ' |';
        };
        $head = $b['head'] ? array_shift($rows) : array_fill(0, $cols, '');
        $out = [$line($head), '|' . str_repeat(' --- |', $cols)];
        foreach ($rows as $row) {
            $out[] = $line($row);
        }
        return ($b['caption'] !== '' ? '**' . $b['caption'] . "**\n\n" : '') . implode("\n", $out);
    }

    private static function mdPosts(array $b, string $lang): string
    {
        $posts = Settings::get('blog.enabled') ? Content::latest($b['count'], $b['category']) : [];
        if (!$posts) {
            return '';
        }
        $lines = array_map(fn ($p) => '- ' . self::mdLink($p['title'], absolute_url(Content::path($p))), $posts);
        return '## ' . self::mdText($b['title'] !== '' ? $b['title'] : Theme::t('latest_posts', $lang)) . "\n\n" . implode("\n", $lines);
    }

    private static function mdProducts(array $b, string $lang): string
    {
        $products = self::products($b);
        if (!$products) {
            return '';
        }
        $lines = array_map(fn ($p) => '- ' . self::mdLink($p['title'], absolute_url(Content::path($p))) . ' — ' . Shop::summary($p, $lang), $products);
        return '## ' . self::mdText($b['title'] !== '' ? $b['title'] : Theme::t('products', $lang)) . "\n\n" . implode("\n", $lines);
    }
}
