<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Discover;

use Eclair\Admin;
use Eclair\Ads;
use Eclair\Ai;
use Eclair\Auth;
use Eclair\Http;
use Eclair\Extensions;
use Eclair\Req;
use Eclair\Session;
use Eclair\Settings;

defined('ECLAIR') || exit;

/** Fonction « Google Discover » dans l'administration : « prêt pour Discover » et tendances du jour. */
final class AdminDiscover
{
    public static function page(array $user): never
    {
        $ai = Ai::allowed($user);
        if ($ai) {
            Ai::choose((string) ($user['ai'] ?? ''));
        }
        $geo = Trends::geo();
        $posts = Discover::posts();
        $v = [
            'site' => Discover::site(),
            'posts' => $posts,
            'ready' => count(array_filter($posts, fn (array $p): bool => $p['ready'])),
            'geo' => $geo,
            'trends' => Trends::cached($geo),
            'ai' => $ai,
            'ideasCost' => $ai ? Ai::money(round(Ai::cost(Ai::provider(), Ai::model(Ai::provider()), ['in' => 5000, 'out' => 2500]), 3)) : '',
            'planning' => Extensions::enabled('planning'),
            'adsActive' => Ads::active(),
            'adsAdmin' => Auth::can('settings'),
            'blog' => (bool) Settings::get('blog.enabled'),
        ];
        Admin::extensionPage('discover', __DIR__ . '/page.php', $v, t('Google Discover'));
    }

    /** Formulaires sans JavaScript : pays des tendances, tendance ajoutée au planning. */
    public static function action(array $user): never
    {
        $action = Req::input('action');
        if ($action === 'geo') {
            $geo = strtoupper(Req::input('geo'));
            Settings::save(['discover' => ['geo' => in_array($geo, Trends::GEOS, true) ? $geo : '']]);
            Admin::back('x/discover#trends');
        }
        if ($action === 'trend') {
            $geo = Trends::geo();
            $trend = (Trends::cached($geo)['items'] ?? [])[(int) Req::input('n')] ?? null;
            if (!is_array($trend)) {
                Admin::back('x/discover#trends', t('Cette tendance n’est plus à jour : recharge les tendances du jour.'), 'bad');
            }
            $r = Trends::plan($trend, $geo);
            if (is_string($r)) {
                Admin::back('x/discover#trends', $r, 'bad');
            }
            Admin::back('x/discover#trends', t('« {title} » ajouté au planning, à la date d’aujourd’hui.', ['title' => $r['title']]));
        }
        Admin::back('x/discover');
    }

    /** « Charger les tendances du jour » : lecture du flux de Google Trends (au plus une fois par heure). */
    public static function trends(array $user): never
    {
        session_write_close();
        $res = Trends::fetch(Trends::geo());
        Http::json($res['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $res['error']], $res['ok'] ? 200 : 502);
    }

    /** « Proposer des idées pour mon site » : l'IA ne garde que les tendances qui touchent le sujet du site. */
    public static function ideas(array $user): never
    {
        if (!Ai::allowed($user)) {
            Http::json(['ok' => false, 'error' => t('L’assistant IA n’est pas activé pour ton compte.')], 403);
        }
        Ai::choose((string) ($user['ai'] ?? ''));
        $geo = Trends::geo();
        $trends = Trends::cached($geo)['items'] ?? [];
        if (!$trends) {
            Http::json(['ok' => false, 'error' => t('Charge d’abord les tendances du jour.')], 422);
        }
        if (Extensions::invoke('planning', 'full') === true) {
            Http::json(['ok' => false, 'error' => t('Le planning compte déjà {n} idées : supprime celles qui ne servent plus.', ['n' => 300])], 422);
        }
        $slot = Ai::slot($user['id']);
        if ($slot === null) {
            Http::json(['ok' => false, 'error' => t('Une demande à l’assistant est déjà en cours : attends qu’elle se termine.')], 429);
        }
        if (($error = Ai::reserve($user)) !== null) {
            Http::json(['ok' => false, 'error' => $error], 429);
        }
        session_write_close();
        extend_time(300);
        $res = Trends::propose($trends, $geo);
        Ai::charge($user['id'], (float) $res['cost']);
        if (!$res['ok']) {
            Http::json(['ok' => false, 'error' => $res['error']], 502);
        }
        $spent = t('{tokens} jetons · {cost}', ['tokens' => number_format((int) $res['tokens'], 0, ',', "\u{202F}"), 'cost' => Ai::money((float) $res['cost'])]);
        Session::flash('ok', $res['added'] > 0
            ? t('{n} idées ajoutées au planning, à la date d’aujourd’hui ({spent}).', ['n' => $res['added'], 'spent' => $spent])
            : t('Aucune tendance du jour ne correspond à ton site : rien n’a été ajouté ({spent}).', ['spent' => $spent]));
        Http::json(['ok' => true, 'added' => $res['added']]);
    }
}
