<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Administration des contenus : liste, éditeur, enregistrement, aperçu, révisions. */
final class AdminContent
{
    public static function list(array $user): never
    {
        $type = Req::query('type');
        $status = Req::query('status');
        $q = mb_strtolower(trim(Req::query('q')));
        $entries = array_filter(Content::index(), function (array $e) use ($type, $status, $q): bool {
            return ($type === '' || $e['type'] === $type)
                && ($status === '' || ($status === 'scheduled' ? $e['status'] === 'published' && $e['date'] > time() : $e['status'] === $status))
                && ($q === '' || str_contains(mb_strtolower($e['title'] . ' ' . $e['slug']), $q));
        });
        uasort($entries, fn ($a, $b) => $b['updated'] <=> $a['updated']);
        Admin::view('content', ['entries' => $entries, 'type' => $type, 'status' => $status, 'q' => Req::query('q')], t('Contenus'));
    }

    public static function create(array $user, string $type): never
    {
        self::editor($user, null, $type);
    }

    public static function edit(array $user, string $id): never
    {
        $item = Content::get($id);
        if (!$item) {
            Admin::view('error', ['title' => t('Contenu introuvable'), 'text' => t('Il a peut-être été supprimé.')], t('Contenu introuvable'), 404);
        }
        self::editor($user, $item, $item['type']);
    }

    private static function editor(array $user, ?array $item, string $type): never
    {
        $isNew = $item === null;
        $item ??= [
            'id' => '', 'type' => $type, 'status' => 'draft', 'title' => '', 'slug' => '', 'lang' => Settings::get('lang'),
            'tgroup' => '', 'blocks' => [], 'summary' => '', 'image' => '', 'category' => '', 'author' => $user['id'], 'date' => 0, 'updated' => 0,
            'seo' => ['title' => '', 'description' => '', 'keyword' => '', 'canonical' => '', 'noindex' => false],
            'options' => ['hide_title' => false, 'toc' => 'auto', 'wide' => false],
        ];
        if ($item['type'] === 'product') {
            $item['product'] = Shop::normalize($item['product'] ?? []);
        }
        // Dates affichées dans le fuseau du site (et non celui du navigateur).
        $item['date_local'] = $item['date'] ? date('Y-m-d\TH:i', (int) $item['date']) : '';
        $mediaIds = array_merge([$item['image']], $item['product']['gallery'] ?? [], Blocks::media($item['blocks']));
        $media = [];
        foreach (array_filter($mediaIds) as $id) {
            $m = Media::get($id);
            if ($m) {
                $media[$id] = AdminMedia::export($m);
            }
        }
        $translations = [];
        foreach (Content::index() as $e) {
            if ($e['id'] !== $item['id']) {
                $translations[] = ['id' => $e['id'], 'title' => $e['title'], 'lang' => $e['lang'], 'tgroup' => $e['tgroup'], 'type' => $e['type']];
            }
        }
        $entry = $isNew ? null : Content::entry($item);
        Admin::view('editor', [
            'layout' => 'bare',
            'item' => $item,
            'js' => ['editor' => [
                'item' => $item,
                'isNew' => $isNew,
                'path' => $entry ? Content::path($entry) : '',
                'visible' => $entry ? Content::visible($entry) : false,
                'media' => $media,
                'categories' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'kind' => $c['kind']], Categories::all()),
                'users' => array_values(array_map(fn ($u) => ['id' => $u['id'], 'name' => $u['name']], Users::all())),
                'langs' => Content::LANGS,
                'translations' => $translations,
                'site' => [
                    'name' => Settings::get('name'),
                    'url' => Settings::get('url'),
                    'host' => (string) parse_url((string) Settings::get('url'), PHP_URL_HOST),
                    'blog' => Content::blogPath(),
                    'home' => Settings::get('home'),
                    'format' => Settings::get('seo.title_format'),
                    'blogOn' => (bool) Settings::get('blog.enabled'),
                    'shop' => Shop::path(),
                    'shopOn' => Shop::enabled(),
                    'currency' => Shop::CURRENCIES[Shop::currency()],
                ],
                'gd' => Media::gd(),
                'analysis' => Analyzer::run($item),
            ]],
        ], $isNew ? t('Nouveau contenu') : $item['title']);
    }

    public static function save(array $user): never
    {
        $data = Req::json();
        $id = (string) ($data['id'] ?? '');
        if ($id !== '' && !Content::get($id)) {
            Http::json(['ok' => false, 'error' => t('Ce contenu n’existe plus.')], 404);
        }
        $item = Content::save($data, $user);
        $link = preg_replace('/[^a-z0-9]/', '', (string) ($data['translation_of'] ?? '')) ?? '';
        if ($link !== '') {
            Content::linkTranslation($item['id'], $link);
            $item = Content::get($item['id']) ?? $item;
        }
        $entry = Content::entry($item);
        $path = Content::path($entry);
        if (Content::visible($entry) && !$item['seo']['noindex'] && IndexNow::enabled()) {
            register_shutdown_function(static function () use ($path): void {
                Http::finish();
                IndexNow::ping([$path]);
            });
        }
        Http::json([
            'ok' => true,
            'item' => $item + ['date_local' => date('Y-m-d\TH:i', (int) $item['date'])],
            'path' => $path,
            'url' => path_to($path),
            'visible' => Content::visible($entry),
            'analysis' => Analyzer::run($item),
        ]);
    }

    public static function analyze(array $user): never
    {
        $data = Req::json();
        $old = ($data['id'] ?? '') !== '' ? Content::get((string) $data['id']) : null;
        $item = Content::build($data, $user, $old);
        Http::json(['ok' => true, 'analysis' => Analyzer::run($item), 'slug' => $item['slug'], 'path' => Content::path(Content::entry($item))]);
    }

    public static function preview(array $user): never
    {
        $data = json_decode(Req::input('data'), true);
        if (!is_array($data)) {
            Http::send('Bad request', 400, ['Content-Type: text/plain; charset=utf-8']);
        }
        $old = ($data['id'] ?? '') !== '' ? Content::get((string) $data['id']) : null;
        $item = Content::build($data, $user, $old);
        Site::single(Content::entry($item), true, $item);
    }

    public static function previewSaved(array $user, string $id): never
    {
        $item = Content::get($id);
        if (!$item) {
            Site::notFound();
        }
        Site::single(Content::entry($item), true, $item);
    }

    public static function delete(array $user): never
    {
        $data = Req::wantsJson() ? Req::json() : $_POST;
        $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
        Content::delete($id, Sanitize::text($data['redirect'] ?? '', 300));
        if (Req::wantsJson()) {
            Http::json(['ok' => true]);
        }
        Admin::back('content', t('Contenu supprimé.'));
    }

    public static function duplicate(array $user): never
    {
        $data = Req::json();
        $copy = Content::duplicate(preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '', $user);
        Http::json($copy ? ['ok' => true, 'id' => $copy['id'], 'edit' => admin_url('edit/' . $copy['id'])] : ['ok' => false], $copy ? 200 : 404);
    }

    public static function revisions(array $user, string $id): never
    {
        Http::json(['ok' => true, 'revisions' => array_map(fn ($r) => $r + ['when' => fmt_date($r['updated'], I18n::lang(), true)], Content::revisions($id))]);
    }

    public static function revision(array $user, string $id, string $name): never
    {
        $rev = Content::revision($id, $name);
        Http::json($rev ? ['ok' => true, 'item' => $rev] : ['ok' => false], $rev ? 200 : 404);
    }

    /** Suggestions de liens internes pour l'éditeur (maillage interne). */
    public static function links(array $user): never
    {
        $q = mb_strtolower(trim(Req::query('q')));
        $out = [];
        foreach (Content::published() as $e) {
            if ($q === '' || str_contains(mb_strtolower($e['title'] . ' ' . $e['slug']), $q)) {
                $out[] = ['title' => $e['title'], 'path' => Content::path($e), 'type' => ['post' => t('Article'), 'product' => t('Produit')][$e['type']] ?? t('Page')];
            }
        }
        foreach (Categories::all() as $c) {
            if ($q === '' || str_contains(mb_strtolower($c['name']), $q)) {
                $out[] = ['title' => $c['name'], 'path' => Categories::path($c), 'type' => t('Catégorie')];
            }
        }
        Http::json(['ok' => true, 'links' => array_slice($out, 0, 8)]);
    }

    public static function ping(array $user): never
    {
        Http::json(['ok' => true]);
    }
}
