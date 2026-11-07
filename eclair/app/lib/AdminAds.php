<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Publicité Google AdSense dans l'administration (droit Réglages). Page du noyau depuis la 2.0 : ses réglages étaient sur la
 * page Discover, devenue une fonction optionnelle. Les réglages (Settings 'ads') et leur effet sur les pages ne changent pas.
 */
final class AdminAds
{
    public static function page(array $user): never
    {
        $latest = Content::published('post');
        $first = $latest ? reset($latest) : null;
        Admin::view('ads', [
            'ads' => Settings::get('ads'),
            'adsActive' => Ads::active(),
            'adsClient' => Ads::client(),
            'admin' => true,
            // Adresse d'un article en ligne, pour tester le message de consentement de Google.
            'testUrl' => is_array($first) ? absolute_url(Content::path($first)) . '?fc=alwaysshow&fctype=gdpr' : '',
        ], t('Publicité'));
    }

    public static function save(array $user): never
    {
        $raw = Req::input('client');
        $client = Ads::client($raw);
        if ($raw !== '' && $client === '') {
            Admin::back('ads', t('Identifiant d’éditeur invalide : il ressemble à pub-1234567890123456 (dans AdSense › Compte).'), 'bad');
        }
        $rawSlot = Req::input('slot');
        $slot = Ads::slot($rawSlot);
        if ($rawSlot !== '' && $slot === '') {
            Admin::back('ads', t('Bloc d’annonces invalide : indique seulement son numéro (data-ad-slot), par exemple 1234567890.'), 'bad');
        }
        $enabled = Req::bool('enabled');
        $consent = Req::bool('consent');
        $auto = Req::bool('auto');
        if ($enabled && $client === '') {
            Admin::back('ads', t('Indique d’abord ton identifiant d’éditeur AdSense.'), 'bad');
        }
        if ($enabled && !$consent) {
            Admin::back('ads', t('Publie d’abord le message de consentement dans AdSense, puis coche la case qui le confirme : sans lui, la publicité n’est pas permise pour les visiteurs européens.'), 'bad');
        }
        if ($enabled && !$auto && $slot === '') {
            Admin::back('ads', t('Choisis les annonces automatiques ou indique un bloc d’annonces.'), 'bad');
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
        Admin::back('ads', $enabled ? t('Publicité activée sur les articles.') : t('Réglages de la publicité enregistrés.'));
    }
}
