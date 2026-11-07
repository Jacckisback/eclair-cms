<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Publicité Google AdSense, sur les articles seulement (menu Publicité).
 *
 * - Rien ne change sur le site tant qu'elle n'est pas en service : il faut l'identifiant d'éditeur, l'activation et la
 *   confirmation que le message de consentement (RGPD) est publié dans AdSense › Confidentialité et messages. Google exige
 *   une plateforme de consentement certifiée (TCF) pour les visiteurs de l'EEE, du Royaume-Uni et de Suisse ; la sienne
 *   s'affiche par le code AdSense lui-même (support.google.com/adsense/answer/13554116).
 * - Blocs placés par Éclair : entre deux blocs du texte, jamais dans un paragraphe, avec l'étiquette permise par Google
 *   (support.google.com/adsense/answer/4533986) et une place réservée (pas de décalage de la page).
 * - Sécurité : la politique que Google documente pour AdSense (nonce et 'strict-dynamic',
 *   support.google.com/adsense/answer/16283098), sur les seules pages qui ont de la publicité. Le nonce est tiré à chaque
 *   rendu ; la page en cache le garde, et tout changement de contenu vide le cache (nouveau rendu, nouveau nonce).
 * - ads.txt : la ligne de Google (support.google.com/adsense/answer/12171612), puis celles ajoutées par la personne.
 */
final class Ads
{
    public const POSITIONS = ['intro', 'middle', 'end'];
    private const SCRIPT = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';
    /** Article d'au moins 3 paragraphes, et 3 paragraphes au moins entre deux blocs d'annonces. */
    private const MIN_PARAGRAPHS = 3;
    private const GAP = 3;
    /** Bloc « au milieu » : seulement dans un article long. */
    private const MIDDLE_FROM = 8;

    /** Identifiant d'éditeur normalisé (« pub- » et 16 chiffres, sans « ca- »), ou chaîne vide s'il n'est pas valide. */
    public static function client(?string $raw = null): string
    {
        $id = strtolower(trim($raw ?? (string) Settings::get('ads.client')));
        $id = (string) preg_replace('/^ca-/', '', $id);
        return preg_match('/^pub-\d{16}$/', $id) ? $id : '';
    }

    /** Bloc d'annonces (data-ad-slot : des chiffres), ou chaîne vide. */
    public static function slot(?string $raw = null): string
    {
        $slot = trim($raw ?? (string) Settings::get('ads.slot'));
        return preg_match('/^\d{6,20}$/', $slot) ? $slot : '';
    }

    /** Publicité en service : identifiant valide, activée, consentement confirmé, et des annonces à afficher. */
    public static function active(): bool
    {
        return Settings::get('ads.enabled') && Settings::get('ads.consent') && self::client() !== ''
            && (Settings::get('ads.auto') || self::slot() !== '');
    }

    /** Cette page reçoit-elle de la publicité ? Un article en ligne, hors aperçu, sauf « Sans publicité » dans l'éditeur. */
    public static function wanted(array $item, bool $preview): bool
    {
        return !$preview && $item['type'] === 'post' && empty($item['options']['noads']) && self::active();
    }

    /**
     * Où placer les blocs d'Éclair : index des blocs après lesquels en insérer un. Seulement entre deux blocs (jamais dans un
     * paragraphe, une liste ou un encadré), dans un article d'au moins 3 paragraphes, avec 3 paragraphes au moins entre deux.
     */
    public static function places(array $blocks): array
    {
        if (self::slot() === '') {
            return [];
        }
        $paragraphs = array_keys(array_filter($blocks, fn (mixed $b): bool => is_array($b) && ($b['type'] ?? '') === 'paragraph'));
        $n = count($paragraphs);
        if ($n < self::MIN_PARAGRAPHS) {
            return [];
        }
        $wanted = array_intersect(self::POSITIONS, (array) Settings::get('ads.positions'));
        // Rang dans la lecture (nombre de paragraphes lus) => bloc après lequel insérer.
        $candidates = [];
        if (in_array('intro', $wanted, true)) {
            $candidates[2] = $paragraphs[1];
        }
        if (in_array('middle', $wanted, true) && $n >= self::MIDDLE_FROM) {
            $k = intdiv($n + 1, 2);
            $candidates[$k] = $paragraphs[$k - 1];
        }
        if (in_array('end', $wanted, true)) {
            $candidates[$n] = (int) array_key_last($blocks);
        }
        ksort($candidates);
        $out = [];
        $last = null;
        foreach ($candidates as $rank => $block) {
            if ($last === null || $rank - $last >= self::GAP) {
                $out[$block] = true;
                $last = $rank;
            }
        }
        return array_keys($out);
    }

    /**
     * Un bloc d'annonces, avec l'étiquette permise par Google dans la langue de la page, suivi de l'appel que Google fournit
     * avec chaque bloc ; ce script en ligne porte le nonce de la page.
     */
    public static function unit(string $lang, string $nonce): string
    {
        $label = e(Theme::t('ad_label', $lang));
        return '<aside class="ad" aria-label="' . $label . '"><p class="ad-label">' . $label . '</p>'
            . '<ins class="adsbygoogle" data-ad-client="ca-' . e(self::client()) . '" data-ad-slot="' . e(self::slot())
            . '" data-ad-format="auto" data-full-width-responsive="true"></ins>'
            . '<script nonce="' . e($nonce) . '">(adsbygoogle = window.adsbygoogle || []).push({});</script></aside>' . "\n";
    }

    /** Code AdSense de la page (dans <head>), avec le nonce de la page. */
    public static function head(string $nonce): string
    {
        return '<script async src="' . e(self::SCRIPT . '?client=ca-' . self::client()) . '" crossorigin="anonymous" nonce="' . e($nonce) . '"></script>';
    }

    /** Jeton aléatoire de la politique de sécurité (nonce), tiré à chaque rendu de page. */
    public static function nonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    /** Contenu de /ads.txt : la ligne de Google, puis celles ajoutées dans l'administration ; null sans identifiant. */
    public static function txt(): ?string
    {
        $client = self::client();
        if ($client === '') {
            return null;
        }
        $extra = self::cleanTxt((string) Settings::get('ads.txt'));
        return 'google.com, ' . $client . ", DIRECT, f08c47fec0942fa0\n" . ($extra !== '' ? $extra . "\n" : '');
    }

    /** Lignes ajoutées à ads.txt, nettoyées : texte brut, 50 lignes de 300 caractères au plus. */
    public static function cleanTxt(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', Sanitize::utf8($raw)) ?: [] as $line) {
            $line = trim((string) preg_replace('/[\x00-\x1F\x7F<>]/u', '', $line));
            if ($line !== '') {
                $lines[] = mb_substr($line, 0, 300);
            }
        }
        return implode("\n", array_slice($lines, 0, 50));
    }
}
