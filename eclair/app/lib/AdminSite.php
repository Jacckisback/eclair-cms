<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Menus, catégories, messages et redirections. */
final class AdminSite
{
    public static function menus(array $user): never
    {
        $options = [];
        foreach (Content::index() as $e) {
            $options[] = ['type' => 'content', 'id' => $e['id'], 'label' => $e['title'], 'kind' => ['post' => t('Article'), 'product' => t('Produit')][$e['type']] ?? t('Page'), 'draft' => !Content::visible($e)];
        }
        foreach (Categories::all() as $c) {
            $options[] = ['type' => 'category', 'id' => $c['id'], 'label' => $c['name'], 'kind' => t('Catégorie')];
        }
        if (Settings::get('blog.enabled')) {
            $options[] = ['type' => 'blog', 'id' => '', 'label' => (string) Settings::get('blog.title'), 'kind' => t('Blog')];
        }
        if (Shop::enabled()) {
            $options[] = ['type' => 'shop', 'id' => '', 'label' => Shop::title(), 'kind' => t('Boutique')];
        }
        $resolve = function (array $item) use ($options): array {
            foreach ($options as $o) {
                if ($o['type'] === $item['type'] && $o['id'] === $item['id']) {
                    return $item + ['title' => $o['label'], 'kind' => $o['kind']];
                }
            }
            return $item + ['title' => $item['url'] ?? '', 'kind' => t('Lien')];
        };
        Admin::view('menus', [
            'menus' => ['main' => array_map($resolve, Menus::raw('main')), 'footer' => array_map($resolve, Menus::raw('footer'))],
            'options' => $options,
        ], t('Menus'));
    }

    public static function saveMenus(array $user): never
    {
        $payload = json_decode(Req::input('menus'), true);
        if (is_array($payload)) {
            foreach (Menus::NAMES as $name) {
                Menus::save($name, is_array($payload[$name] ?? null) ? $payload[$name] : []);
            }
        }
        Admin::back('menus', t('Menus enregistrés.'));
    }

    public static function categories(array $user): never
    {
        Admin::view('categories', [
            'categories' => Categories::all(),
            'counts' => Categories::counts('post') + Categories::counts('product'),
            'edit' => Categories::get(Req::query('edit')),
            'shop' => Shop::enabled() || Categories::of('product'),
            'kind' => Req::query('kind') === 'product' ? 'product' : 'post',
        ], t('Catégories'));
    }

    public static function saveCategory(array $user): never
    {
        if (Req::input('action') === 'delete') {
            Categories::delete(Req::input('id'));
            Admin::back('categories', t('Catégorie supprimée.'));
        }
        if (Sanitize::text(Req::input('name'), 80) === '') {
            Admin::back('categories', t('Donne un nom à la catégorie.'), 'bad');
        }
        Categories::save([
            'id' => Req::input('id'),
            'name' => Req::input('name'),
            'slug' => Req::input('slug'),
            'description' => Req::input('description'),
            'seo_title' => Req::input('seo_title'),
            'kind' => Req::input('kind'),
        ]);
        Admin::back('categories', t('Catégorie enregistrée.'));
    }

    public static function messages(array $user): never
    {
        $pages = max(1, (int) ceil(Messages::count() / Messages::PER_PAGE));
        $page = max(1, min($pages, (int) Req::query('p', '1')));
        Admin::view('messages', ['messages' => Messages::page($page), 'page' => $page, 'pages' => $pages], t('Messages'));
    }

    public static function messageAction(array $user): never
    {
        $id = Req::input('id');
        if (Req::input('action') === 'delete') {
            Messages::delete($id);
            Admin::back('messages', t('Message supprimé.'));
        }
        Messages::markRead($id);
        Admin::back('messages');
    }

    public static function redirects(array $user): never
    {
        Admin::view('redirects', ['redirects' => array_reverse(Redirects::all())], t('Redirections'));
    }

    public static function saveRedirect(array $user): never
    {
        if (Req::input('action') === 'delete') {
            Redirects::remove(Req::input('from'));
            Cache::purge();
            Admin::back('redirects', t('Redirection supprimée.'));
        }
        $from = Redirects::normalize(Req::input('from'));
        $to = trim(Req::input('to'));
        if ($from === '/' || $to === '') {
            Admin::back('redirects', t('Indique une ancienne adresse et une nouvelle.'), 'bad');
        }
        Redirects::add($from, $to, false, Req::input('code') === '302' ? 302 : 301);
        Cache::purge();
        Admin::back('redirects', t('Redirection ajoutée.'));
    }
}
