<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Google Discover dans l'administration : « prêt pour Discover », tendances du jour, publicité AdSense (administrateurs). */
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
        $latest = array_key_first($posts);
        Admin::view('discover', [
            'site' => Discover::site(),
            'posts' => $posts,
            'ready' => count(array_filter($posts, fn (array $p): bool => $p['ready'])),
            'geo' => $geo,
            'trends' => Trends::cached($geo),
            'ai' => $ai,
            'ideasCost' => $ai ? Plan::ideasCost(Ai::provider()) : '',
            'admin' => Auth::can('settings'),
            'ads' => Settings::get('ads'),
            'adsActive' => Ads::active(),
            'adsClient' => Ads::client(),
            // Adresse d'un article en ligne, pour tester le message de consentement de Google.
            'testUrl' => $latest !== null ? absolute_url(Content::path($posts[$latest]['entry'])) . '?fc=alwaysshow&fctype=gdpr' : '',
            'blog' => (bool) Settings::get('blog.enabled'),
        ], t('Google Discover'));
    }

    /** Formulaires sans JavaScript : pays des tendances, tendance ajoutée au planning, publicité (administrateurs). */
    public static function action(array $user): never
    {
        $action = Req::input('action');
        if ($action === 'geo') {
            $geo = strtoupper(Req::input('geo'));
            Settings::save(['discover' => ['geo' => in_array($geo, Trends::GEOS, true) ? $geo : '']]);
            Admin::back('discover#trends');
        }
        if ($action === 'trend') {
            $geo = Trends::geo();
            $trend = (Trends::cached($geo)['items'] ?? [])[(int) Req::input('n')] ?? null;
            if (!is_array($trend)) {
                Admin::back('discover#trends', t('Cette tendance n’est plus à jour : recharge les tendances du jour.'), 'bad');
            }
            $r = Trends::plan($trend, $geo);
            if (is_string($r)) {
                Admin::back('discover#trends', $r, 'bad');
            }
            Admin::back('discover#trends', t('« {title} » ajouté au planning, à la date d’aujourd’hui.', ['title' => $r['title']]));
        }
        if ($action === 'ads') {
            if (!Auth::can('settings')) {
                Admin::forbidden();
            }
            $raw = Req::input('client');
            $client = Ads::client($raw);
            if ($raw !== '' && $client === '') {
                Admin::back('discover#ads', t('Identifiant d’éditeur invalide : il ressemble à pub-1234567890123456 (dans AdSense › Compte).'), 'bad');
            }
            $rawSlot = Req::input('slot');
            $slot = Ads::slot($rawSlot);
            if ($rawSlot !== '' && $slot === '') {
                Admin::back('discover#ads', t('Bloc d’annonces invalide : indique seulement son numéro (data-ad-slot), par exemple 1234567890.'), 'bad');
            }
            $enabled = Req::bool('enabled');
            $consent = Req::bool('consent');
            $auto = Req::bool('auto');
            if ($enabled && $client === '') {
                Admin::back('discover#ads', t('Indique d’abord ton identifiant d’éditeur AdSense.'), 'bad');
            }
            if ($enabled && !$consent) {
                Admin::back('discover#ads', t('Publie d’abord le message de consentement dans AdSense, puis coche la case qui le confirme : sans lui, la publicité n’est pas permise pour les visiteurs européens.'), 'bad');
            }
            if ($enabled && !$auto && $slot === '') {
                Admin::back('discover#ads', t('Choisis les annonces automatiques ou indique un bloc d’annonces.'), 'bad');
            }
            $positions = array_values(array_intersect(Ads::POSITIONS, Req::inputArray('positions')));
            Settings::save(['ads' => [
                'client' => $client,
                'enabled' => $enabled,
                'consent' => $consent,
                'auto' => $auto,
                'slot' => $slot,
                'positions' => $positions,
                'txt' => Ads::cleanTxt(Req::input('txt')),
            ]]);
            Admin::back('discover#ads', $enabled ? t('Publicité activée sur les articles.') : t('Réglages de la publicité enregistrés.'));
        }
        Admin::back('discover');
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
        if (count(Plan::all()) >= Plan::MAX) {
            Http::json(['ok' => false, 'error' => t('Le planning compte déjà {n} idées : supprime celles qui ne servent plus.', ['n' => Plan::MAX])], 422);
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
