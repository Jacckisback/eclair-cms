<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Routeur et rendu du site public. Chaque page générée est mise en cache statique. */
final class Site
{
    public static function handle(string $path): never
    {
        if (!in_array(Req::method(), ['GET', 'HEAD'], true)) {
            Http::send('Method Not Allowed', 405, ['Allow: GET, HEAD', 'Content-Type: text/plain; charset=utf-8']);
        }
        Cache::checkExpiry();
        self::canonical($path);

        switch ($path) {
            case '/robots.txt':
                self::file($path, Seo::robots(), 'text/plain; charset=utf-8');
            case '/sitemap.xml':
                self::file($path, Seo::sitemap(), 'application/xml; charset=utf-8');
            case '/llms.txt':
                if (Settings::get('geo.llms')) {
                    self::file($path, Seo::llms(false), 'text/plain; charset=utf-8', ['X-Robots-Tag: noindex']);
                }
                break;
            case '/llms-full.txt':
                if (Settings::get('geo.llms') && Settings::get('geo.llms_full')) {
                    self::file($path, Seo::llms(true), 'text/plain; charset=utf-8', ['X-Robots-Tag: noindex']);
                }
                break;
            case '/feed.xml':
                if (Settings::get('blog.enabled')) {
                    self::file($path, Seo::feed(), 'application/rss+xml; charset=utf-8', ['X-Robots-Tag: noindex, follow']);
                }
                break;
            case '/favicon.ico':
                $icon = ECLAIR_ROOT . '/media/site/icon-32.png';
                if (is_file($icon)) {
                    self::file($path, (string) file_get_contents($icon), 'image/png', ['Cache-Control: public, max-age=604800']);
                }
                break;
        }
        if (IndexNow::enabled() && $path === '/' . IndexNow::key() . '.txt') {
            self::file($path, IndexNow::key(), 'text/plain; charset=utf-8');
        }
        if (str_ends_with($path, '.md') && Settings::get('geo.markdown')) {
            $target = substr($path, 0, -3);
            $entry = Content::findByPath($target === '/index' ? '/' : $target);
            if ($entry && !$entry['noindex'] && ($item = Content::get($entry['id']))) {
                self::file($path, Seo::markdown($item), 'text/markdown; charset=utf-8', [
                    'X-Robots-Tag: noindex',
                    'Link: <' . absolute_url(Content::path($entry)) . '>; rel="canonical"',
                ]);
            }
        }
        self::route($path);
    }

    /** Une seule URL par contenu : protocole, domaine, slash final, majuscules, index.php. */
    private static function canonical(string $path): void
    {
        $query = Req::queryString();
        $suffix = $query !== '' ? '?' . $query : '';
        $clean = $path;
        if ($clean === '/index.php' || str_starts_with($clean, '/index.php/')) {
            $clean = substr($clean, 10) ?: '/';
        }
        $clean = (string) preg_replace('~/{2,}~', '/', $clean);
        if ($clean !== '/' && str_ends_with($clean, '/')) {
            $clean = rtrim($clean, '/') ?: '/';
        }
        if (preg_match('/[A-Z]/', $clean)) {
            $clean = strtolower($clean);
        }
        $url = rtrim((string) Settings::get('url'), '/');
        if ($url !== '' && Settings::get('security.canonical')) {
            $p = parse_url($url) ?: [];
            $host = strtolower(($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''));
            $https = ($p['scheme'] ?? '') === 'https';
            if ($host !== '' && (Req::host() !== $host || ($https && !Req::isHttps()))) {
                Http::redirect($url . $clean . $suffix, 301);
            }
        } elseif (Settings::get('security.https') && !Req::isHttps()) {
            Http::redirect('https://' . Req::host() . Req::base() . $clean . $suffix, 301);
        }
        if ($clean !== $path) {
            Http::redirect(path_to($clean) . $suffix, 301);
        }
    }

    private static function route(string $path): never
    {
        $words = Theme::words();
        $home = (string) Settings::get('home');
        $index = Content::index();
        $blogOn = (bool) Settings::get('blog.enabled');
        $blog = Content::blogPath();

        if ($path === '/') {
            if ($home !== '' && isset($index[$home]) && Content::visible($index[$home])) {
                self::single($index[$home]);
            }
            self::listing('blog', 1);
        }
        if ($home !== '' && isset($index[$home]) && '/' . $index[$home]['slug'] === $path) {
            Http::redirect(path_to('/'), 301);
        }
        if ($path === '/' . $words['search'] && Settings::get('search')) {
            self::search();
        }
        if ($blogOn && ($path === $blog || str_starts_with($path, $blog . '/'))) {
            $rest = substr($path, strlen($blog));
            $page = preg_quote($words['page'], '~');
            if ($rest === '') {
                if ($home === '') {
                    Http::redirect(path_to('/'), 301);
                }
                self::listing('blog', 1);
            }
            if (preg_match('~^/' . $page . '/(\d{1,6})$~', $rest, $m)) {
                self::listing('blog', (int) $m[1]);
            }
            if (preg_match('~^/' . preg_quote($words['author'], '~') . '/([a-z0-9-]+)(?:/' . $page . '/(\d{1,6}))?$~', $rest, $m)) {
                $user = Users::findBySlug($m[1]);
                if ($user) {
                    self::listing('author', (int) ($m[2] ?? 1), Users::profile($user));
                }
            }
            if (preg_match('~^/([a-z0-9-]+)(?:/' . $page . '/(\d{1,6}))?$~', $rest, $m)) {
                $cat = Categories::bySlug($m[1]);
                if ($cat) {
                    self::listing('category', (int) ($m[2] ?? 1), $cat);
                }
            }
        }
        $entry = Content::findByPath($path);
        if ($entry) {
            self::single($entry);
        }
        $redirect = Redirects::find($path);
        if ($redirect) {
            Redirects::hit($path);
            $to = $redirect['to'];
            Http::redirect(str_starts_with($to, '/') ? path_to($to) : $to, (int) $redirect['code']);
        }
        self::notFound();
    }

    /* ───────────────────────────── Page / article ───────────────────────────── */

    public static function single(array $entry, bool $preview = false, ?array $item = null): never
    {
        $item ??= Content::get($entry['id']);
        if (!$item) {
            self::notFound();
        }
        $lang = $item['lang'];
        $path = Content::path($entry);
        $isPost = $item['type'] === 'post';
        $blocks = $item['blocks'];
        $author = Users::profile(Users::find($item['author']));
        $category = $isPost ? Categories::get($item['category']) : null;
        $featured = $isPost ? Media::get($item['image']) : null;
        $h1InContent = ($blocks[0]['type'] ?? '') === 'hero' && $item['options']['hide_title'];
        $html = Blocks::html($blocks, ['lang' => $lang, 'h1' => $h1InContent, 'eager' => !$featured, 'page' => $item['id']]);
        $toc = Blocks::toc($blocks, $lang);
        $tocMode = $item['options']['toc'];
        $showToc = $tocMode === 'on' ? (bool) $toc : ($tocMode === 'auto' && $isPost && count($toc) >= 3);

        $crumbs = [['name' => (string) Settings::get('name'), 'path' => '/']];
        if ($isPost) {
            if (Settings::get('home') !== '') {
                $crumbs[] = ['name' => (string) Settings::get('blog.title'), 'path' => Content::blogPath()];
            }
            if ($category) {
                $crumbs[] = ['name' => $category['name'], 'path' => Categories::path($category)];
            }
        } else {
            $segments = explode('/', $item['slug']);
            for ($i = 1; $i < count($segments); $i++) {
                $parent = Content::findByPath('/' . implode('/', array_slice($segments, 0, $i)));
                if ($parent) {
                    $crumbs[] = ['name' => $parent['title'], 'path' => Content::path($parent)];
                }
            }
        }
        if ($path !== '/') {
            $crumbs[] = ['name' => $item['title'], 'path' => $path];
        }

        $related = [];
        if ($isPost) {
            $related = $category ? Content::latest(3, $category['id'], $item['id']) : [];
            if (count($related) < 3) {
                $ids = array_column($related, 'id');
                foreach (Content::latest(6, '', $item['id']) as $p) {
                    if (count($related) < 3 && !in_array($p['id'], $ids, true)) {
                        $related[] = $p;
                    }
                }
            }
        }
        $translations = $preview ? [] : Content::translations($entry);
        $words = word_count(Blocks::text($blocks));
        $description = $item['seo']['description'] !== '' ? $item['seo']['description'] : excerpt($item['summary'] ?: (Blocks::intro($blocks) ?: Blocks::text($blocks)), 158);

        $ogMedia = $featured ?? Media::get($item['image']) ?? Media::get((string) (Blocks::media($blocks)[0] ?? '')) ?? Media::get((string) Settings::get('seo.image'));
        $image = $ogMedia ? Media::og($ogMedia) + ['alt' => (string) $ogMedia['alt']] : null;

        $head = Seo::head([
            'kind' => 'item',
            'item' => $item,
            'lang' => $lang,
            'title' => Seo::title($item),
            'og_title' => $item['title'],
            'description' => $description,
            'canonical' => $item['seo']['canonical'] !== '' ? $item['seo']['canonical'] : absolute_url($path),
            'noindex' => $item['seo']['noindex'] || $preview,
            'image' => $image,
            'type' => $isPost ? 'article' : 'website',
            'published' => $item['date'],
            'modified' => max((int) $item['date'], (int) $item['updated']),
            'breadcrumbs' => $crumbs,
            'alternates' => array_map(fn ($t) => Content::path($t), $translations),
            'author' => $isPost ? $author : null,
            'category' => $category,
            'faq' => Blocks::faq($blocks),
            'markdown' => $item['seo']['noindex'] ? '' : Seo::mdPath($path),
            'words' => $words,
        ]);
        $page = Theme::render('single', [
            'lang' => $lang,
            'head' => $head,
            'css' => Theme::css(),
            'current' => $path,
            'id' => $item['id'],
            'class' => trim(($isPost ? 'is-post' : 'is-page') . ($item['options']['wide'] ? ' is-wide' : '')),
            'item' => $item,
            'html' => $html,
            'toc' => $showToc ? $toc : [],
            'author' => $author,
            'category' => $category,
            'crumbs' => $crumbs,
            'related' => $related,
            'translations' => $translations,
            'featured' => $featured,
            'words' => $words,
            'h1_in_content' => $h1InContent,
        ]);
        self::html($page, 200, $preview ? null : $path);
    }

    /* ─────────────────────────────── Listes ─────────────────────────────── */

    private static function listing(string $kind, int $page, ?array $subject = null): never
    {
        $lang = (string) Settings::get('lang');
        $words = Theme::words();
        $blog = Content::blogPath();
        $isHome = $kind === 'blog' && Settings::get('home') === '';
        $posts = array_values(Content::published('post'));
        $crumbs = [['name' => (string) Settings::get('name'), 'path' => '/']];
        $blogCrumb = Settings::get('home') !== '' ? ['name' => (string) Settings::get('blog.title'), 'path' => $blog] : null;

        if ($kind === 'category') {
            $posts = array_values(array_filter($posts, fn ($p) => $p['category'] === $subject['id']));
            $base = Categories::path($subject);
            $title = $subject['name'];
            $seoTitle = $subject['seo_title'] !== '' ? $subject['seo_title'] : Seo::format($title);
            $description = $subject['description'];
            $crumbs = array_merge($crumbs, array_filter([$blogCrumb]), [['name' => $title, 'path' => $base]]);
        } elseif ($kind === 'author') {
            $posts = array_values(array_filter($posts, fn ($p) => $p['author'] === $subject['id']));
            $base = Theme::authorPath($subject);
            $title = $subject['name'];
            $seoTitle = Seo::format(Theme::t('posts_by', $lang, ['name' => $subject['name']]));
            $description = trim($subject['job'] . ($subject['job'] !== '' && $subject['bio'] !== '' ? "\n" : '') . $subject['bio']);
            $crumbs = array_merge($crumbs, array_filter([$blogCrumb]), [['name' => $title, 'path' => $base]]);
        } else {
            $base = $isHome ? '/' : $blog;
            $title = $isHome ? (string) Settings::get('name') : (string) Settings::get('blog.title');
            $tagline = (string) Settings::get('tagline');
            $seoTitle = $isHome ? ($tagline !== '' ? $title . ' · ' . $tagline : $title) : Seo::format($title);
            $description = (string) ($isHome ? (Settings::get('description') ?: Settings::get('tagline')) : Settings::get('blog.description'));
            if (!$isHome) {
                $crumbs[] = ['name' => $title, 'path' => $base];
            }
        }
        $per = max(1, (int) Settings::get('blog.per_page', 9));
        $pages = max(1, (int) ceil(count($posts) / $per));
        if ($page < 1 || $page > $pages) {
            self::notFound();
        }
        $pageBase = $kind === 'blog' ? $blog : $base;
        $pagePath = fn (int $n): string => $n <= 1 ? $base : $pageBase . '/' . $words['page'] . '/' . $n;
        if ($page === 1 && Req::path() !== $base && Req::path() === $pageBase . '/' . $words['page'] . '/1') {
            Http::redirect(path_to($base), 301);
        }
        $path = $pagePath($page);
        $entries = array_slice($posts, ($page - 1) * $per, $per);
        if ($page > 1) {
            $seoTitle .= ' · ' . Theme::t('page_n', $lang, ['n' => $page]);
        }
        $first = $entries[0] ?? null;
        $ogMedia = $kind === 'author' ? Media::get($subject['avatar']) : null;
        $ogMedia ??= Media::get((string) Settings::get('seo.image')) ?? ($first ? Media::get($first['image']) : null);
        $head = Seo::head([
            'kind' => $isHome ? 'home' : $kind,
            'lang' => $lang,
            'title' => $seoTitle,
            'og_title' => $title,
            'description' => excerpt($description, 158),
            'canonical' => absolute_url($path),
            'image' => $ogMedia ? Media::og($ogMedia) + ['alt' => (string) $ogMedia['alt']] : null,
            'type' => $kind === 'author' ? 'profile' : 'website',
            'breadcrumbs' => $crumbs,
            'author' => $kind === 'author' ? $subject : null,
            'prev' => $page > 1 ? $pagePath($page - 1) : null,
            'next' => $page < $pages ? $pagePath($page + 1) : null,
        ]);
        $html = Theme::render('list', [
            'lang' => $lang,
            'head' => $head,
            'css' => Theme::css(),
            'current' => $base,
            'class' => 'is-list',
            'title' => $title,
            'description' => $description,
            'entries' => $entries,
            'crumbs' => $crumbs,
            'page' => $page,
            'pages' => $pages,
            'prev' => $page > 1 ? $pagePath($page - 1) : null,
            'next' => $page < $pages ? $pagePath($page + 1) : null,
            'query' => null,
            'empty' => '',
        ]);
        self::html($html, 200, $path);
    }

    private static function search(): never
    {
        $lang = (string) Settings::get('lang');
        $query = Sanitize::text(Req::query('q'), 100);
        $results = $query !== '' ? Content::search($query) : [];
        $title = Theme::t('search', $lang);
        $head = Seo::head([
            'kind' => 'search',
            'lang' => $lang,
            'title' => Seo::format($query !== '' ? $title . ' : ' . $query : $title),
            'description' => '',
            'canonical' => absolute_url('/' . Theme::words()['search']),
            'noindex' => true,
            'breadcrumbs' => [],
        ]);
        $html = Theme::render('list', [
            'lang' => $lang,
            'head' => $head,
            'css' => Theme::css(),
            'current' => '/' . Theme::words()['search'],
            'class' => 'is-search',
            'title' => $title,
            'description' => '',
            'entries' => array_values(array_filter($results, fn ($e) => $e['type'] === 'post' || $e['type'] === 'page')),
            'crumbs' => [],
            'page' => 1,
            'pages' => 1,
            'prev' => null,
            'next' => null,
            'query' => $query,
            'empty' => $query !== '' ? Theme::t('no_results', $lang) : '',
        ]);
        self::html($html, 200, null);
    }

    public static function notFound(): never
    {
        $lang = (string) Settings::get('lang');
        $head = Seo::head([
            'kind' => '404',
            'lang' => $lang,
            'title' => Seo::format(Theme::t('not_found', $lang)),
            'description' => '',
            'canonical' => absolute_url(Req::path()),
            'noindex' => true,
            'breadcrumbs' => [],
        ]);
        self::html(Theme::render('404', ['lang' => $lang, 'head' => $head, 'css' => Theme::css(), 'current' => '', 'class' => 'is-404']), 404, null);
    }

    /** Petite page de message (confirmation d'envoi sans JavaScript…). */
    public static function message(string $title, string $text, int $status = 200): never
    {
        $lang = (string) Settings::get('lang');
        $head = Seo::head(['kind' => 'message', 'lang' => $lang, 'title' => Seo::format($title), 'description' => '',
            'canonical' => absolute_url('/'), 'noindex' => true, 'breadcrumbs' => []]);
        $back = Req::header('Referer');
        $content = '<section class="notfound wrap"><h1>' . e($title) . '</h1><p>' . e($text) . '</p><p><a class="btn btn-secondary" href="'
            . e(str_starts_with($back, Req::origin()) ? $back : path_to('/')) . '">← ' . e(Theme::t('back_home', $lang)) . '</a></p></section>';
        $html = Theme::partial('layout', ['lang' => $lang, 'head' => $head, 'css' => Theme::css(), 'current' => '', 'class' => 'is-message', 'content' => $content]);
        self::html($html, $status, null);
    }

    /* ─────────────────────────────── Sortie ─────────────────────────────── */

    /** Envoie une page HTML ; si $cachePath est fourni, la page est mise en cache statique. */
    private static function html(string $html, int $status, ?string $cachePath): never
    {
        $headers = array_merge(
            ['Content-Type: text/html; charset=utf-8', 'Cache-Control: ' . ($cachePath !== null ? 'public, max-age=0, must-revalidate' : 'no-store')],
            Security::headers('site', Theme::hash(Theme::css()))
        );
        if ($status === 200 && $cachePath !== null) {
            Cache::put($cachePath, $html, $headers);
        }
        self::send($html, $status, $headers);
    }

    /** Fichiers texte générés (robots.txt, sitemap, llms.txt, flux, Markdown). */
    private static function file(string $path, string $body, string $type, array $extra = []): never
    {
        $headers = array_merge(['Content-Type: ' . $type, 'Cache-Control: public, max-age=0, must-revalidate'], Security::headers('text'), $extra);
        Cache::put($path, $body, $headers);
        self::send($body, 200, $headers);
    }

    private static function send(string $body, int $status, array $headers): never
    {
        $etag = '"' . substr(md5($body), 0, 16) . '"';
        $headers[] = 'ETag: ' . $etag;
        if ($status === 200 && trim(Req::header('If-None-Match')) === $etag) {
            Http::send('', 304, $headers);
        }
        Http::send($body, $status, $headers);
    }
}
