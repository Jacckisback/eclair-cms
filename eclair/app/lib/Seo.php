<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Tout le référencement, généré automatiquement :
 * balises <head>, Open Graph, hreflang, JSON-LD (schema.org), sitemap XML,
 * robots.txt (avec règles pour les robots IA), llms.txt, Markdown et flux RSS.
 */
final class Seo
{
    public const LOCALES = ['fr' => 'fr_FR', 'es' => 'es_ES', 'en' => 'en_US', 'de' => 'de_DE', 'it' => 'it_IT', 'pt' => 'pt_PT'];

    /** Robots d'entraînement de modèles d'IA. */
    public const AI_TRAINING = [
        'GPTBot', 'ClaudeBot', 'anthropic-ai', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Bytespider',
        'Meta-ExternalAgent', 'FacebookBot', 'cohere-training-data-crawler', 'cohere-ai', 'Amazonbot', 'Diffbot',
        'Omgilibot', 'omgili', 'AI2Bot', 'PanguBot', 'Timpibot', 'ImagesiftBot', 'img2dataset',
    ];
    /** Robots des moteurs de réponse IA (recherche et consultation à la demande d'un utilisateur). */
    public const AI_SEARCH = [
        'OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User',
        'DuckAssistBot', 'MistralAI-User', 'Meta-ExternalFetcher', 'YouBot',
    ];

    /** Titre SEO d'un contenu (titre personnalisé ou modèle « {title} · {site} »). */
    public static function title(array $item): string
    {
        $custom = trim((string) ($item['seo']['title'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $site = (string) Settings::get('name');
        if (($item['id'] ?? '') !== '' && $item['id'] === Settings::get('home')) {
            $tagline = (string) Settings::get('tagline');
            return $tagline !== '' ? $site . ' · ' . $tagline : $site;
        }
        return self::format((string) ($item['title'] ?? ''));
    }

    public static function format(string $title): string
    {
        $format = (string) Settings::get('seo.title_format', '{title} · {site}');
        return trim(strtr($format, ['{title}' => $title, '{site}' => (string) Settings::get('name')]), ' ·|-–—');
    }

    /* ──────────────────────────────── <head> ─────────────────────────────── */

    public static function head(array $ctx): string
    {
        $lang = (string) $ctx['lang'];
        $site = (string) Settings::get('name');
        $canonical = (string) $ctx['canonical'];
        $index = (bool) Settings::get('seo.index') && empty($ctx['noindex']);
        $h = [];
        $h[] = '<title>' . e($ctx['title']) . '</title>';
        if (($ctx['description'] ?? '') !== '') {
            $h[] = '<meta name="description" content="' . e($ctx['description']) . '">';
        }
        $h[] = '<link rel="canonical" href="' . e($canonical) . '">';
        $h[] = '<meta name="robots" content="' . ($index ? 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1' : 'noindex, follow') . '">';
        foreach ($ctx['alternates'] ?? [] as $code => $path) {
            $h[] = '<link rel="alternate" hreflang="' . e($code) . '" href="' . e(absolute_url($path)) . '">';
        }
        if (!empty($ctx['alternates'])) {
            $default = $ctx['alternates'][Settings::get('lang')] ?? reset($ctx['alternates']);
            $h[] = '<link rel="alternate" hreflang="x-default" href="' . e(absolute_url((string) $default)) . '">';
        }
        foreach (['prev', 'next'] as $rel) {
            if (!empty($ctx[$rel])) {
                $h[] = '<link rel="' . $rel . '" href="' . e(absolute_url($ctx[$rel])) . '">';
            }
        }
        $h[] = '<meta property="og:locale" content="' . e(self::LOCALES[$lang] ?? 'fr_FR') . '">';
        $h[] = '<meta property="og:type" content="' . e($ctx['type'] ?? 'website') . '">';
        $h[] = '<meta property="og:title" content="' . e($ctx['og_title'] ?? $ctx['title']) . '">';
        if (($ctx['description'] ?? '') !== '') {
            $h[] = '<meta property="og:description" content="' . e($ctx['description']) . '">';
        }
        $h[] = '<meta property="og:url" content="' . e($canonical) . '">';
        $h[] = '<meta property="og:site_name" content="' . e($site) . '">';
        $image = $ctx['image'] ?? null;
        if ($image) {
            $h[] = '<meta property="og:image" content="' . e(absolute_url($image['url'])) . '">';
            $h[] = '<meta property="og:image:width" content="' . (int) $image['w'] . '">';
            $h[] = '<meta property="og:image:height" content="' . (int) $image['h'] . '">';
            if (($image['alt'] ?? '') !== '') {
                $h[] = '<meta property="og:image:alt" content="' . e($image['alt']) . '">';
            }
        }
        if (($ctx['type'] ?? '') === 'product' && ($ctx['price'] ?? '') !== '') {
            $h[] = '<meta property="product:price:amount" content="' . e($ctx['price']) . '">';
            $h[] = '<meta property="product:price:currency" content="' . e(Shop::currency()) . '">';
        }
        if (($ctx['type'] ?? '') === 'article') {
            $h[] = '<meta property="article:published_time" content="' . e(date('c', (int) $ctx['published'])) . '">';
            $h[] = '<meta property="article:modified_time" content="' . e(date('c', (int) $ctx['modified'])) . '">';
            if (!empty($ctx['category'])) {
                $h[] = '<meta property="article:section" content="' . e($ctx['category']['name']) . '">';
            }
        }
        $h[] = '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">';
        $twitter = ltrim((string) Settings::get('seo.twitter'), '@');
        if ($twitter !== '') {
            $h[] = '<meta name="twitter:site" content="@' . e($twitter) . '">';
        }
        foreach (['google' => 'google-site-verification', 'bing' => 'msvalidate.01'] as $key => $name) {
            $code = (string) Settings::get('seo.' . $key);
            if ($code !== '') {
                $h[] = '<meta name="' . $name . '" content="' . e($code) . '">';
            }
        }
        if (Settings::get('blog.enabled')) {
            $h[] = '<link rel="alternate" type="application/rss+xml" title="' . e($site) . '" href="' . e(path_to('/feed.xml')) . '">';
        }
        if (!empty($ctx['markdown']) && Settings::get('geo.markdown')) {
            $h[] = '<link rel="alternate" type="text/markdown" href="' . e(path_to($ctx['markdown'])) . '">';
        }
        $schema = self::schema($ctx);
        $h[] = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) . '</script>';
        return implode("\n", $h);
    }

    /* ─────────────────────────────── JSON-LD ─────────────────────────────── */

    public static function schema(array $ctx): array
    {
        $root = absolute_url('/');
        $canonical = (string) $ctx['canonical'];
        $lang = (string) $ctx['lang'];
        $entity = self::entity();
        $graph = [];
        $graph[] = array_filter([
            '@type' => 'WebSite',
            '@id' => $root . '#website',
            'url' => $root,
            'name' => Settings::get('name'),
            'description' => Settings::get('description') ?: Settings::get('tagline'),
            'inLanguage' => Settings::get('lang'),
            'publisher' => ['@id' => $entity['@id']],
            'potentialAction' => Settings::get('search') ? [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => absolute_url('/' . Theme::words()['search']) . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ] : null,
        ]);
        $graph[] = $entity;

        $kind = (string) ($ctx['kind'] ?? 'item');
        $item = $ctx['item'] ?? null;
        $pageType = match ($kind) {
            'blog', 'category', 'shop' => 'CollectionPage',
            'author' => 'ProfilePage',
            'search' => 'SearchResultsPage',
            default => ($item['type'] ?? '') === 'product' ? 'ItemPage' : self::pageType($item),
        };
        $page = [
            '@type' => $pageType,
            '@id' => $canonical . '#webpage',
            'url' => $canonical,
            'name' => $ctx['og_title'] ?? $ctx['title'],
            'isPartOf' => ['@id' => $root . '#website'],
            'inLanguage' => $lang,
        ];
        if (($ctx['description'] ?? '') !== '') {
            $page['description'] = $ctx['description'];
        }
        if (!empty($ctx['published'])) {
            $page['datePublished'] = date('c', (int) $ctx['published']);
            $page['dateModified'] = date('c', (int) ($ctx['modified'] ?? $ctx['published']));
        }
        if (!empty($ctx['image'])) {
            $page['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => absolute_url($ctx['image']['url'])];
        }
        if (count($ctx['breadcrumbs'] ?? []) > 1) {
            $page['breadcrumb'] = ['@id' => $canonical . '#breadcrumb'];
        }
        if ($kind === 'author' && !empty($ctx['author'])) {
            $page['mainEntity'] = self::person($ctx['author']);
        }
        if (!empty($ctx['product'])) {
            $page['mainEntity'] = ['@id' => $ctx['product']['@id']];
        }
        $graph[] = $page;
        if (!empty($ctx['product'])) {
            $graph[] = $ctx['product'] + ['mainEntityOfPage' => ['@id' => $canonical . '#webpage']];
        }

        if ($item && $item['type'] === 'post' && $kind === 'item') {
            $article = [
                '@type' => 'BlogPosting',
                '@id' => $canonical . '#article',
                'headline' => mb_substr($item['title'], 0, 110),
                'description' => $ctx['description'] ?? '',
                'datePublished' => date('c', (int) $item['date']),
                'dateModified' => date('c', max((int) $item['date'], (int) $item['updated'])),
                'inLanguage' => $lang,
                'mainEntityOfPage' => ['@id' => $canonical . '#webpage'],
                'publisher' => ['@id' => $entity['@id']],
                'wordCount' => (int) ($ctx['words'] ?? 0),
            ];
            if (!empty($ctx['author'])) {
                $article['author'] = self::person($ctx['author']);
            }
            if (!empty($ctx['image'])) {
                $article['image'] = [absolute_url($ctx['image']['url'])];
            }
            if (!empty($ctx['category'])) {
                $article['articleSection'] = $ctx['category']['name'];
            }
            if (($item['summary'] ?? '') !== '') {
                $article['abstract'] = $item['summary'];
            }
            $graph[] = $article;
        }
        if (count($ctx['breadcrumbs'] ?? []) > 1) {
            $list = [];
            foreach ($ctx['breadcrumbs'] as $i => $crumb) {
                $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb['name'], 'item' => absolute_url($crumb['path'])];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $canonical . '#breadcrumb', 'itemListElement' => $list];
        }
        if (!empty($ctx['faq'])) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $canonical . '#faq',
                'isPartOf' => ['@id' => $canonical . '#webpage'],
                'mainEntity' => array_map(fn ($q) => [
                    '@type' => 'Question',
                    'name' => $q['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']],
                ], $ctx['faq']),
            ];
        }
        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private static function pageType(?array $item): string
    {
        $slug = (string) ($item['slug'] ?? '');
        $last = basename($slug);
        return match (true) {
            in_array($last, ['a-propos', 'qui-sommes-nous', 'about', 'about-us', 'sobre-nosotros', 'quienes-somos', 'sobre-mi', 'uber-uns', 'chi-siamo', 'sobre'], true) => 'AboutPage',
            in_array($last, ['contact', 'contacto', 'contatto', 'kontakt', 'contato', 'nous-contacter'], true) => 'ContactPage',
            default => 'WebPage',
        };
    }

    /** L'entité derrière le site : organisation, personne ou commerce local. */
    public static function entity(): array
    {
        $type = (string) Settings::get('seo.entity', 'Organization');
        $type = in_array($type, ['Organization', 'Person', 'LocalBusiness'], true) ? $type : 'Organization';
        $root = absolute_url('/');
        $entity = [
            '@type' => $type,
            '@id' => $root . '#' . ($type === 'Person' ? 'person' : 'organization'),
            'name' => (string) (Settings::get('seo.entity_name') ?: Settings::get('name')),
            'url' => $root,
        ];
        $logo = Media::get((string) Settings::get('appearance.logo'));
        if ($logo) {
            $image = ['@type' => 'ImageObject', 'url' => absolute_url(Media::url($logo)), 'width' => $logo['w'], 'height' => $logo['h']];
            $entity[$type === 'Person' ? 'image' : 'logo'] = $image;
            if ($type === 'LocalBusiness') {
                $entity['image'] = absolute_url(Media::url($logo));
            }
        }
        if ($type !== 'Person' && ($policy = Shop::returnPolicy())) {
            $entity['hasMerchantReturnPolicy'] = $policy;
        }
        $sameAs = array_values(array_filter(array_map('strval', (array) Settings::get('social'))));
        if ($sameAs) {
            $entity['sameAs'] = $sameAs;
        }
        foreach (['email' => 'email', 'phone' => 'telephone'] as $key => $prop) {
            $value = (string) Settings::get('seo.' . $key);
            if ($value !== '') {
                $entity[$prop] = $value;
            }
        }
        if ($type === 'LocalBusiness') {
            $address = array_filter([
                'streetAddress' => Settings::get('seo.street'),
                'addressLocality' => Settings::get('seo.city'),
                'postalCode' => Settings::get('seo.zip'),
                'addressRegion' => Settings::get('seo.region'),
                'addressCountry' => Settings::get('seo.country'),
            ]);
            if ($address) {
                $entity['address'] = ['@type' => 'PostalAddress'] + $address;
            }
            foreach (['hours' => 'openingHours', 'price_range' => 'priceRange'] as $key => $prop) {
                $value = (string) Settings::get('seo.' . $key);
                if ($value !== '') {
                    $entity[$prop] = $value;
                }
            }
        }
        return $entity;
    }

    public static function person(array $author): array
    {
        $person = ['@type' => 'Person', 'name' => $author['name']];
        if (($author['slug'] ?? '') !== '' && Settings::get('blog.enabled')) {
            $person['url'] = absolute_url(Theme::authorPath($author));
        }
        if ($author['job'] !== '') {
            $person['jobTitle'] = $author['job'];
        }
        if ($author['bio'] !== '') {
            $person['description'] = excerpt($author['bio'], 300);
        }
        if ($author['links']) {
            $person['sameAs'] = $author['links'];
        }
        $avatar = Media::get($author['avatar']);
        if ($avatar) {
            $person['image'] = absolute_url(Media::thumb($avatar));
        }
        return $person;
    }

    /* ──────────────────────────── Fichiers générés ─────────────────────────── */

    public static function sitemap(): string
    {
        $out = ['<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml">'];
        if (Settings::get('seo.index')) {
            $published = Content::published();
            $latest = $published ? max(array_map(fn ($e) => max($e['date'], $e['updated']), $published)) : time();
            $seen = [];
            $add = static function (string $path, int $lastmod, array $images = [], array $alternates = []) use (&$out, &$seen): void {
                if (isset($seen[$path])) {
                    return;
                }
                $seen[$path] = true;
                $x = '<url><loc>' . self::xml(absolute_url($path)) . '</loc><lastmod>' . date('c', $lastmod) . '</lastmod>';
                foreach ($alternates as $code => $alt) {
                    $x .= '<xhtml:link rel="alternate" hreflang="' . self::xml($code) . '" href="' . self::xml(absolute_url($alt)) . '"/>';
                }
                foreach (array_slice($images, 0, 20) as $img) {
                    $x .= '<image:image><image:loc>' . self::xml(absolute_url($img)) . '</image:loc></image:image>';
                }
                $out[] = $x . '</url>';
            };
            if (Settings::get('home') === '' || !isset($published[Settings::get('home')])) {
                $add('/', $latest);
            }
            uasort($published, fn ($a, $b) => (Content::path($b) === '/') <=> (Content::path($a) === '/') ?: $b['date'] <=> $a['date']);
            foreach ($published as $entry) {
                if ($entry['noindex']) {
                    continue;
                }
                $images = [];
                $item = Content::get($entry['id']);
                foreach (array_merge([$entry['image']], $item['product']['gallery'] ?? [], $item ? Blocks::media($item['blocks']) : []) as $mid) {
                    $m = Media::get((string) $mid);
                    if ($m) {
                        $images[] = Media::url($m);
                    }
                }
                $alternates = array_map(fn ($t) => Content::path($t), Content::translations($entry));
                $add(Content::path($entry), max($entry['date'], $entry['updated']), array_values(array_unique($images)), $alternates);
            }
            $products = Content::published('product');
            if ($products) {
                $newest = max(array_map(fn ($e) => max($e['date'], $e['updated']), $products));
                $add(Shop::path(), $newest);
                $counts = Categories::counts('product');
                foreach (Categories::of('product') as $cat) {
                    if (!empty($counts[$cat['id']])) {
                        $add(Categories::path($cat), $newest);
                    }
                }
            }
            if (Settings::get('blog.enabled')) {
                $add(Settings::get('home') === '' ? '/' : Content::blogPath(), $latest);
                $counts = Categories::counts();
                foreach (Categories::of('post') as $cat) {
                    if (!empty($counts[$cat['id']])) {
                        $add(Categories::path($cat), $latest);
                    }
                }
                $authors = array_unique(array_column(Content::published('post'), 'author'));
                foreach ($authors as $uid) {
                    $profile = Users::profile(Users::find((string) $uid));
                    if ($profile) {
                        $add(Theme::authorPath($profile), $latest);
                    }
                }
            }
        }
        $out[] = '</urlset>';
        return implode("\n", $out) . "\n";
    }

    public static function robots(): string
    {
        $policy = (string) Settings::get('geo.ai', 'open');
        $search = '/' . Theme::words()['search'];
        $rules = "Allow: /\nDisallow: " . $search . "\nDisallow: /_eclair/\n";
        $signal = match ($policy) {
            'search' => 'search=yes, ai-input=yes, ai-train=no',
            'closed' => 'search=yes, ai-input=no, ai-train=no',
            default => 'search=yes, ai-input=yes, ai-train=yes',
        };
        // Content-Signal (proposition Cloudflare) : non standard, donc en option (Lighthouse le signale).
        $lines = ["# robots.txt — généré par Éclair CMS\nUser-agent: *\n" . $rules . (Settings::get('geo.signal') ? 'Content-Signal: ' . $signal : '')];
        $group = static fn (array $bots, string $body): string => implode("\n", array_map(fn ($b) => 'User-agent: ' . $b, $bots)) . "\n" . $body;
        if ($policy === 'open') {
            $lines[] = "# Moteurs de réponse et robots IA : bienvenus (GEO)\n" . $group(array_merge(self::AI_SEARCH, self::AI_TRAINING), $rules);
        } elseif ($policy === 'search') {
            $lines[] = "# Moteurs de réponse IA : autorisés (citations et sources)\n" . $group(self::AI_SEARCH, $rules);
            $lines[] = "# Entraînement de modèles d'IA : refusé\n" . $group(self::AI_TRAINING, "Disallow: /\n");
        } else {
            $lines[] = "# Tous les robots IA : refusés\n" . $group(array_merge(self::AI_SEARCH, self::AI_TRAINING), "Disallow: /\n");
        }
        $lines[] = 'Sitemap: ' . absolute_url('/sitemap.xml');
        return implode("\n\n", array_map('rtrim', $lines)) . "\n";
    }

    /** llms.txt (format llmstxt.org) : la carte du site pour les IA. */
    public static function llms(bool $full = false): string
    {
        $lang = (string) Settings::get('lang');
        $md = (bool) Settings::get('geo.markdown');
        $link = static fn (array $e): string => absolute_url($md ? self::mdPath(Content::path($e)) : Content::path($e));
        $out = '# ' . Blocks::mdText((string) Settings::get('name')) . "\n\n";
        $summary = trim((string) (Settings::get('description') ?: Settings::get('tagline')));
        if ($summary !== '') {
            $out .= '> ' . Blocks::mdText(str_replace("\n", ' ', $summary)) . "\n\n";
        }
        $about = trim((string) Settings::get('geo.about'));
        if ($about !== '') {
            $out .= Blocks::mdText($about) . "\n\n";
        }
        $facts = ['- ' . Theme::t('website', $lang) . ' : ' . absolute_url('/')];
        $email = (string) (Settings::get('seo.email') ?: '');
        if ($email !== '') {
            $facts[] = '- ' . Theme::t('contact', $lang) . ' : ' . $email;
        }
        $out .= implode("\n", $facts) . "\n\n";

        $published = array_filter(Content::published(), fn ($e) => !$e['noindex']);
        if ($full) {
            foreach ($published as $entry) {
                $item = Content::get($entry['id']);
                if ($item) {
                    $out .= "---\n\n" . self::markdown($item, false) . "\n";
                }
            }
            return $out;
        }
        foreach (['page' => Theme::t('pages', $lang), 'product' => Theme::t('products', $lang), 'post' => Theme::t('posts', $lang)] as $type => $label) {
            $list = array_filter($published, fn ($e) => $e['type'] === $type);
            uasort($list, fn ($a, $b) => (Content::path($b) === '/') <=> (Content::path($a) === '/'));
            if (!$list) {
                continue;
            }
            $out .= '## ' . $label . "\n\n";
            foreach ($list as $e) {
                $note = $e['summary'] !== '' ? $e['summary'] : $e['excerpt'];
                if ($type === 'product') {
                    $note = Shop::summary($e, $lang) . ($note !== '' ? ' — ' . $note : '');
                }
                $out .= '- [' . Blocks::mdText(str_replace(['[', ']'], '', $e['title'])) . '](' . $link($e) . ')'
                    . ($note !== '' ? ': ' . Blocks::mdText(str_replace("\n", ' ', $note)) : '') . "\n";
            }
            $out .= "\n";
        }
        $counts = Categories::counts();
        $cats = array_filter(Categories::of('post'), fn ($c) => !empty($counts[$c['id']]));
        if ($cats && Settings::get('blog.enabled')) {
            $out .= '## ' . Theme::t('categories', $lang) . "\n\n";
            foreach ($cats as $c) {
                $out .= '- [' . Blocks::mdText(str_replace(['[', ']'], '', $c['name'])) . '](' . absolute_url(Categories::path($c)) . ')'
                    . ($c['description'] !== '' ? ': ' . Blocks::mdText(excerpt(str_replace("\n", ' ', $c['description']), 200)) : '') . "\n";
            }
            $out .= "\n";
        }
        $out .= "## Optional\n\n";
        if (Settings::get('geo.llms_full')) {
            $out .= '- [' . Theme::t('full_content', $lang) . '](' . absolute_url('/llms-full.txt') . ")\n";
        }
        $out .= '- [Sitemap](' . absolute_url('/sitemap.xml') . ")\n";
        if (Settings::get('blog.enabled')) {
            $out .= '- [RSS](' . absolute_url('/feed.xml') . ")\n";
        }
        if (Shop::enabled() && Content::published('product')) {
            $out .= '- [' . Theme::t('products', $lang) . ' (Google Merchant RSS)](' . absolute_url('/products.xml') . ")\n";
        }
        return $out;
    }

    /** Version Markdown d'un contenu (lisible par les IA, avec en-tête de métadonnées). */
    public static function markdown(array $item, bool $frontMatter = true): string
    {
        $entry = Content::entry($item);
        $url = absolute_url(Content::path($entry));
        $author = Users::profile(Users::find($item['author']));
        $lang = $item['lang'];
        $desc = $item['seo']['description'] !== '' ? $item['seo']['description'] : $entry['excerpt'];
        $out = '';
        if ($frontMatter) {
            $meta = array_filter([
                'title' => $item['title'],
                'description' => $desc,
                'url' => $url,
                'lang' => $lang,
                'author' => $author['name'] ?? '',
                'published' => date('Y-m-d', (int) $item['date']),
                'updated' => date('Y-m-d', max((int) $item['date'], (int) $item['updated'])),
                'price' => ($entry['price'] ?? '') !== '' ? $entry['price'] . ' ' . Shop::currency() : '',
                'availability' => $entry['availability'] ?? '',
            ]);
            $out .= "---\n";
            foreach ($meta as $k => $v) {
                $out .= $k . ': ' . json_encode((string) $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "\n";
            }
            $out .= "---\n\n";
        }
        $out .= '# ' . Blocks::mdText($item['title']) . "\n\n";
        if (!$frontMatter) {
            $out .= 'URL : ' . $url . "\n\n";
        }
        if ($item['summary'] !== '') {
            $out .= '> **' . Theme::t('key_points', $lang) . '** — ' . str_replace("\n", "\n> ", Blocks::mdText($item['summary'])) . "\n\n";
        }
        if (isset($item['product'])) {
            $out .= self::productMarkdown($item, $entry, $url);
        }
        $out .= Blocks::markdown($item['blocks'], $lang);
        if ($author && $item['type'] === 'post') {
            $out .= "\n— " . Blocks::mdText($author['name'] . ($author['job'] !== '' ? ', ' . $author['job'] : '')) . ' · ' . fmt_date(max((int) $item['date'], (int) $item['updated']), $lang) . "\n";
        }
        return $out;
    }

    /** Prix, disponibilité, variantes et lien d'achat d'un produit, en tête de sa version Markdown. */
    private static function productMarkdown(array $item, array $entry, string $url): string
    {
        $lang = $item['lang'];
        $p = $item['product'];
        $lines = ['- ' . Theme::t('price', $lang) . ' : ' . Shop::summary($entry, $lang)];
        if ($p['compare'] !== '' && (float) $p['compare'] > (float) ($entry['price'] ?? 0)) {
            $lines[] = '- ' . Theme::t('was_price', $lang) . ' : ' . Shop::money($p['compare'], $lang);
        }
        foreach (array_filter(['Brand' => $p['brand'], 'SKU' => $p['sku'], 'GTIN' => $p['gtin']]) as $k => $v) {
            $lines[] = '- ' . $k . ' : ' . Blocks::mdText($v);
        }
        if ($p['variants']) {
            $lines[] = '- ' . Theme::t('choose_option', $lang) . ' :';
            foreach (Shop::offers($item) as $o) {
                $lines[] = '  - ' . Blocks::mdText($o['label']) . ($o['price'] !== '' ? ' — ' . Shop::money($o['price'], $lang) : '') . ' · ' . Theme::t($o['availability'], $lang);
            }
        }
        if (Shop::enabled() && $entry['availability'] !== 'out_of_stock' && array_filter(array_column(Shop::offers($item), 'link'))) {
            $lines[] = '- ' . Shop::button($lang) . ' : ' . $url;
        }
        return implode("\n", $lines) . "\n\n";
    }

    public static function mdPath(string $path): string
    {
        return $path === '/' ? '/index.md' : $path . '.md';
    }

    /** Flux RSS 2.0 avec le contenu complet des articles. */
    public static function feed(): string
    {
        $origin = self::origin();
        $absolutize = static function (string $html) use ($origin): string {
            $html = (string) preg_replace('~<source[^>]*>~', '', $html);
            $html = (string) preg_replace('~ (?:srcset|sizes)="[^"]*"~', '', $html);
            return (string) preg_replace('~ (href|src)="/(?!/)~', ' $1="' . $origin . '/', $html);
        };
        $posts = array_slice(array_filter(Content::published('post'), fn ($e) => !$e['noindex']), 0, 20);
        $items = '';
        foreach ($posts as $entry) {
            $item = Content::get($entry['id']);
            if (!$item) {
                continue;
            }
            $url = absolute_url(Content::path($entry));
            $author = Users::find($item['author']);
            $cat = Categories::get($item['category']);
            $html = $absolutize(Blocks::html($item['blocks'], ['lang' => $item['lang']]));
            $items .= '<item><title>' . self::xml($item['title']) . '</title><link>' . self::xml($url) . '</link>'
                . '<guid isPermaLink="true">' . self::xml($url) . '</guid><pubDate>' . date(DATE_RSS, (int) $item['date']) . '</pubDate>'
                . ($author ? '<dc:creator>' . self::xml($author['name']) . '</dc:creator>' : '')
                . ($cat ? '<category>' . self::xml($cat['name']) . '</category>' : '')
                // Les lecteurs RSS lisent la description comme du HTML : le texte est échappé pour HTML, puis pour XML.
                . '<description>' . self::xml(e($entry['excerpt'])) . '</description>'
                . '<content:encoded><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . ']]></content:encoded></item>' . "\n";
        }
        $title = (string) Settings::get('name');
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
            . '<channel><title>' . self::xml($title) . '</title><link>' . self::xml(absolute_url('/')) . '</link>'
            . '<description>' . self::xml(e((string) (Settings::get('description') ?: Settings::get('tagline') ?: $title))) . '</description>'
            . '<language>' . self::xml((string) Settings::get('lang')) . '</language>'
            . '<lastBuildDate>' . date(DATE_RSS, $posts ? (int) reset($posts)['date'] : time()) . '</lastBuildDate>'
            . '<atom:link href="' . self::xml(absolute_url('/feed.xml')) . '" rel="self" type="application/rss+xml"/>' . "\n"
            . $items . '</channel></rss>' . "\n";
    }

    /** Schéma + hôte de l'URL officielle (sans le dossier d'installation). */
    public static function origin(): string
    {
        $url = (string) Settings::get('url');
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return rtrim(Req::origin(), '/');
        }
        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public static function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
