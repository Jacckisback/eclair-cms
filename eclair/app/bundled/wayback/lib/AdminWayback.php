<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Ancien site (Wayback Machine) dans l'administration : recherche, reconstruction page par page, publication (voir Wayback). */
final class AdminWayback
{
    public static function page(array $user): never
    {
        $ai = Ai::allowed($user);
        if ($ai) {
            Ai::choose((string) ($user['ai'] ?? ''));
        }
        $d = Wayback::data();
        $index = Content::index();
        $paths = [];
        foreach ($index as $e) {
            $paths[Content::path($e)] = true;
        }
        $rows = [];
        $counts = ['todo' => 0, 'draft' => 0, 'published' => 0, 'live' => 0, 'error' => 0];
        $cost = 0.0;
        $tokens = 0;
        foreach ($d['urls'] as $path => $u) {
            $u['state'] = Wayback::state($u, $index, $paths);
            $counts[$u['state']]++;
            $cost += (float) $u['cost'];
            $tokens += (int) $u['tokens'];
            $rows[$path] = $u;
        }
        $imager = $ai ? Ai::imager() : '';
        Admin::view('wayback', [
            'domain' => Wayback::domain(),
            'data' => $d,
            'rows' => $rows,
            'counts' => $counts,
            'cost' => $cost,
            'tokens' => $tokens,
            'ai' => $ai,
            'writeCost' => $ai ? AdminAi::composeCost(Ai::provider()) : '',
            'model' => $ai ? Ai::label() : '',
            'imageCost' => $imager !== '' ? Ai::money((float) Ai::IMAGES[$imager][2]) : '',
            'usage' => Ai::usage(),
            'budget' => (float) Ai::config()['budget'],
            'blog' => (bool) Settings::get('blog.enabled'),
        ], t('Ancien site'));
    }

    /** Formulaires sans JavaScript : publier les brouillons reconstruits, effacer la liste. */
    public static function action(array $user): never
    {
        $action = Req::input('action');
        if ($action === 'publish') {
            [$done, $waiting] = Wayback::publish($user);
            Admin::back('wayback', trim(($done ? t('{n} pages reconstruites publiées.', ['n' => $done]) : t('Aucune page publiée.'))
                . ($waiting ? ' ' . t('{n} attendent ta relecture : elles contiennent encore « À compléter ».', ['n' => $waiting]) : '')), $done ? 'ok' : 'warn');
        }
        if ($action === 'reset') {
            Wayback::reset();
            Admin::back('wayback', t('Liste effacée. Les brouillons déjà créés sont gardés.'));
        }
        Admin::back('wayback');
    }

    /** « Chercher dans l'archive » : l'index d'Internet Archive, pour le domaine de ce site seulement. */
    public static function scan(array $user): never
    {
        extend_time(180);
        $res = Wayback::scan(is_string(Req::json()['date'] ?? null) ? Req::json()['date'] : '');
        if (!$res['ok']) {
            Http::json(['ok' => false, 'error' => $res['error']], 502);
        }
        Session::flash($res['count'] ? 'ok' : 'warn', $res['count']
            ? t('{n} anciennes adresses trouvées dans l’archive.', ['n' => $res['count']])
            : t('Aucune ancienne page de ce domaine dans l’archive à cette date.'));
        Http::json(['ok' => true]);
    }

    /** Une adresse à la fois (le navigateur enchaîne) : brouillon écrit par l'IA, dans le budget et le quota de l'assistant. */
    public static function build(array $user): never
    {
        if (!Ai::allowed($user)) {
            Http::json(['ok' => false, 'stop' => true, 'error' => t('L’assistant IA n’est pas activé pour ton compte.')], 403);
        }
        Ai::choose((string) ($user['ai'] ?? ''));
        $data = Req::json();
        $slot = Ai::slot($user['id']);
        if ($slot === null) {
            Http::json(['ok' => false, 'stop' => true, 'error' => t('Une demande à l’assistant est déjà en cours : attends qu’elle se termine.')], 429);
        }
        if (($error = Ai::reserve($user)) !== null) {
            Http::json(['ok' => false, 'stop' => true, 'error' => $error], 429);
        }
        session_write_close();
        extend_time(300);
        $res = Wayback::build(is_string($data['path'] ?? null) ? $data['path'] : '', $user, !empty($data['image']));
        Ai::charge($user['id'], (float) $res['cost']);
        if (!$res['ok']) {
            Http::json(['ok' => false, 'stop' => $res['stop'], 'error' => $res['error']], $res['stop'] ? 502 : 422);
        }
        Http::json(['ok' => true, 'edit' => admin_url('edit/' . $res['item']['id']), 'redirect' => $res['redirect'], 'note' => $res['note']]);
    }
}
