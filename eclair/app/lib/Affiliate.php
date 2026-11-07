<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Affiliation : les liens d'affiliation du site, au même endroit. Sur chaque page qui en contient, et dans le flux,
 * Éclair place la mention publicitaire en tête du contenu (une publicité doit être clairement identifiable comme telle :
 * LCEN, art. 20, en France ; LSSI, art. 20, en Espagne), y ajoute pour un lien Amazon la phrase exigée par le Partenariat
 * Amazon (article 5 du contrat, dans la langue de la boutique) et marque ces liens rel="sponsored nofollow" (Google).
 * Tant qu'aucun lien n'est enregistré, rien ne change ; une page sans lien affilié reste identique à l'octet près.
 *
 * Depuis la 2.0, la gestion des liens et la rédaction par l'IA sont la fonction optionnelle « Affiliation »
 * (app/bundled/affiliate) ; ce marquage reste au noyau : retirer la fonction ne retire jamais une mention légalement
 * exigée d'une page déjà publiée.
 *
 * Données : data/affiliate.php = ['links' => [id => lien], 'disclosure' => mention personnalisée (vide : celle du thème)].
 */
final class Affiliate
{
    /** Phrase exigée par le Partenariat Amazon, article 5 du contrat de chaque boutique. */
    private const AMAZON = [
        'fr' => 'En tant que Partenaire Amazon, je réalise un bénéfice sur les achats remplissant les conditions requises.',
        'es' => 'En calidad de Afiliado de Amazon, obtengo ingresos por las compras adscritas que cumplen los requisitos aplicables.',
        'en' => 'As an Amazon Associate I earn from qualifying purchases.',
        'de' => 'Als Amazon-Partner verdiene ich an qualifizierten Verkäufen.',
        'it' => 'In qualità di Affiliato Amazon io ricevo un guadagno dagli acquisti idonei.',
    ];
    private const STORES = ['amazon.fr' => 'fr', 'amazon.es' => 'es', 'amazon.com' => 'en', 'amazon.co.uk' => 'en', 'amazon.de' => 'de', 'amazon.it' => 'it'];

    private static ?array $data = null;

    /** Données relues au prochain appel (après une écriture par la fonction « Affiliation »). */
    public static function forget(): void
    {
        self::$data = null;
    }

    public static function data(): array
    {
        if (self::$data === null) {
            $d = Store::read('affiliate', []);
            $d = is_array($d) ? $d : [];
            self::$data = ['links' => is_array($d['links'] ?? null) ? $d['links'] : [], 'disclosure' => (string) ($d['disclosure'] ?? '')];
        }
        return self::$data;
    }

    /** Liens, du plus récent au plus ancien. */
    public static function links(): array
    {
        $links = self::data()['links'];
        uasort($links, fn (array $a, array $b): int => $b['created'] <=> $a['created']);
        return $links;
    }

    /** « amazon » pour une boutique Amazon ou un lien court amzn.to, sinon « other ». */
    public static function program(string $url): string
    {
        $host = (string) preg_replace('/^(www|smile)\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        return preg_match('/^(amazon\.[a-z]{2,3}(\.[a-z]{2})?|amzn\.(to|eu|com))$/', $host) ? 'amazon' : 'other';
    }

    /* ─────────────────────────────── Site public ─────────────────────────────── */

    /** Programme d'un lien s'il est affilié (enregistré, ou Amazon avec identifiant de partenaire, ou amzn.to), sinon null. */
    private static function affiliate(string $url, array $known): ?string
    {
        $url = trim($url);
        if (isset($known[$url])) {
            return $known[$url];
        }
        if (self::program($url) !== 'amazon') {
            return null;
        }
        return str_starts_with(strtolower((string) parse_url($url, PHP_URL_HOST)), 'amzn.') || preg_match('/[?&]tag=[^&#]+/', $url) ? 'amazon' : null;
    }

    /**
     * HTML d'un contenu (page, article, produit, flux) : s'il contient des liens affiliés, ils sont marqués
     * rel="sponsored nofollow" et la mention publicitaire est placée en tête. Sinon, le HTML reste tel quel.
     */
    public static function mark(string $html, array $blocks, string $lang): string
    {
        $links = self::data()['links'];
        if (!$links) {
            return $html;
        }
        $known = array_column($links, 'program', 'url');
        $found = [];
        foreach (Blocks::links($blocks) as $url) {
            if (($program = self::affiliate($url, $known)) !== null) {
                $found[trim($url)] = $program;
            }
        }
        if (!$found) {
            return $html;
        }
        $html = (string) preg_replace_callback('~<a\s([^>]*)>~', static function (array $m) use ($known): string {
            if (!preg_match('~\bhref="([^"]*)"~', $m[1], $h) || self::affiliate(html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $known) === null) {
                return $m[0];
            }
            $has = preg_match('~\brel="([^"]*)"~', $m[1], $r) === 1;
            $rel = implode(' ', array_unique(array_filter(array_merge($has ? (preg_split('/\s+/', $r[1]) ?: []) : [], ['sponsored', 'nofollow']))));
            return '<a ' . ($has ? str_replace($r[0], 'rel="' . $rel . '"', $m[1]) : $m[1] . ' rel="' . $rel . '"') . '>';
        }, $html);
        $notice = self::notice($found, $lang);
        // Bandeau d'ouverture (le titre de la page) sans lien affilié : la mention vient juste après lui, avant tout lien.
        $hero = ($blocks[0]['type'] ?? '') === 'hero' && str_starts_with($html, '<section class="hero') ? strpos($html, "</section>\n") : false;
        if ($hero !== false && !array_filter(Blocks::links([$blocks[0]]), static fn (string $url): bool => self::affiliate($url, $known) !== null)) {
            $hero += strlen("</section>\n");
            return substr($html, 0, $hero) . $notice . substr($html, $hero);
        }
        return $notice . $html;
    }

    /** Mention publicitaire, dans l'encadré « info » du thème, avec la phrase Amazon de chaque boutique concernée. */
    public static function notice(array $found, string $lang): string
    {
        $custom = self::data()['disclosure'];
        $text = $custom !== '' ? $custom : Theme::t('aff_note', $lang);
        foreach (array_keys($found, 'amazon', true) as $url) {
            $statement = self::amazonStatement((string) $url, $lang);
            $text .= str_contains($text, $statement) ? '' : ' ' . $statement;
        }
        return '<aside class="callout callout-info"><p class="callout-title"><span aria-hidden="true">' . Blocks::CALLOUTS['info'] . '</span> '
            . e(Theme::t('aff_title', $lang)) . '</p><p>' . e($text) . "</p></aside>\n";
    }

    /** Phrase du Partenariat Amazon de la boutique du lien ; pour un lien court, celle de la langue de la page. */
    public static function amazonStatement(string $url, string $lang): string
    {
        $host = (string) preg_replace('/^(www|smile)\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        return self::AMAZON[self::STORES[$host] ?? $lang] ?? self::AMAZON['en'];
    }
}
