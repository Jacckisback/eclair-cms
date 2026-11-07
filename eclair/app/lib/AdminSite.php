<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
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
        $menus = [];
        foreach (Menus::names() as $name) {
            $menus[$name] = array_map($resolve, Menus::raw($name));
        }
        Admin::view('menus', ['menus' => $menus, 'options' => $options], t('Menus'));
    }

    public static function saveMenus(array $user): never
    {
        $payload = json_decode(Req::input('menus'), true);
        if (is_array($payload)) {
            foreach (Menus::names() as $name) {
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
        try {
            Categories::save([
                'id' => Req::input('id'),
                'name' => Req::input('name'),
                'slug' => Req::input('slug'),
                'description' => Req::input('description'),
                'seo_title' => Req::input('seo_title'),
                'kind' => Req::input('kind'),
                'lang' => Req::input('lang'),
            ]);
        } catch (\RuntimeException $e) {
            Admin::back('categories', $e->getMessage(), 'bad');
        }
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
        Admin::view('redirects', ['redirects' => array_reverse(Redirects::listing())], t('Redirections'));
    }

    /** Robots IA : quelles IA lisent le site, quelles pages, pour quel usage (journal noté par index.php). */
    public static function aiVisits(array $user): never
    {
        $days = in_array((int) Req::query('days'), [7, 30, 90], true) ? (int) Req::query('days') : 30;
        $report = AiVisits::report($days);
        Admin::view('ai-visits', [
            'days' => $days,
            'report' => $report,
            'unread' => $report['total'] ? AiVisits::unread($report) : [],
            'on' => (bool) Settings::get('geo.ai_log'),
            'policy' => (string) Settings::get('geo.ai'),
        ], t('Robots IA'));
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
        // /.well-known/ (validations de domaine), /_eclair/ et /__eclair/ appartiennent à l'hébergement et à Éclair.
        $admin = Auth::can('settings');
        if (!Redirects::allowed($from, $admin)) {
            Admin::back('redirects', t('Cette adresse est déjà utilisée : choisis-en une autre.'), 'bad');
        }
        if (mb_strlen($from) > Redirects::MAX_LENGTH || (!preg_match('~^https?://~i', $to) && mb_strlen(Redirects::normalize($to)) > Redirects::MAX_LENGTH)) {
            Admin::back('redirects', t('Adresse trop longue : {n} caractères au maximum.', ['n' => Redirects::MAX_LENGTH]), 'bad');
        }
        if (Redirects::full() && Redirects::find($from) === null) {
            Admin::back('redirects', t('Limite de {n} redirections atteinte : supprime celles qui ne servent plus.', ['n' => Redirects::MAX]), 'bad');
        }
        if (!Redirects::add($from, $to, false, Req::input('code') === '302' ? 302 : 301, $admin)) {
            Admin::back('redirects', t('Indique une ancienne adresse et une nouvelle.'), 'bad');
        }
        Cache::purge();
        Admin::back('redirects', t('Redirection ajoutée.'));
    }
}
