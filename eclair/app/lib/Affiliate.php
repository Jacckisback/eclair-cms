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
 * L'IA peut tirer de liens choisis un comparatif, un test ou un guide d'achat, en brouillon. Elle ne reçoit que le nom et
 * les notes du propriétaire (jamais la description du marchand), n'invente ni prix, ni avis, ni résultat de test, et
 * place les liens par des adresses provisoires que les vraies remplacent ensuite, à l'identique.
 *
 * Données : data/affiliate.php = ['links' => [id => lien], 'disclosure' => mention personnalisée (vide : celle du thème)].
 */
final class Affiliate
{
    public const MAX = 300;
    public const FORMATS = ['compare', 'review', 'guide'];
    /** Adresse provisoire d'un lien dans la réponse de l'IA (domaine réservé .invalid, RFC 2606). */
    private const SLOT = 'https://aff.invalid/';
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

    public static function data(): array
    {
        if (self::$data === null) {
            $d = Store::read('affiliate', []);
            $d = is_array($d) ? $d : [];
            self::$data = ['links' => is_array($d['links'] ?? null) ? $d['links'] : [], 'disclosure' => (string) ($d['disclosure'] ?? '')];
        }
        return self::$data;
    }

    /** Écriture sous verrou, d'après le fichier relu ; les pages en cache sont recalculées (mention, liens marqués). */
    private static function change(callable $edit): mixed
    {
        return Store::locked(function () use ($edit): mixed {
            self::$data = null;
            $d = self::data();
            $out = $edit($d);
            if (!is_string($out)) {
                Store::write('affiliate', $d);
                self::$data = null;
                Cache::purge();
            }
            return $out;
        });
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

    /** Ajoute ou modifie un lien. Renvoie le lien, ou un message à afficher. */
    public static function put(array $in, string $id = ''): array|string
    {
        $name = Sanitize::text((string) ($in['name'] ?? ''), 120);
        $url = Sanitize::url(trim((string) ($in['url'] ?? '')));
        if ($name === '') {
            return t('Donne un nom à ce lien : le produit ou l’offre.');
        }
        if (!preg_match('~^https?://[^/\s?#]+\.[a-z]{2,}([/?#]|$)~i', $url) || strlen($url) > 2000) {
            return t('Colle l’adresse complète du lien d’affiliation (https://…).');
        }
        return self::change(function (array &$d) use ($in, $id, $name, $url): array|string {
            $old = $id !== '' ? ($d['links'][$id] ?? null) : null;
            if ($old === null && count($d['links']) >= self::MAX) {
                return t('Tu as déjà {n} liens : supprime ceux qui ne servent plus.', ['n' => self::MAX]);
            }
            $link = ['id' => $old['id'] ?? random_id(), 'name' => $name, 'url' => $url, 'program' => self::program($url),
                'notes' => Sanitize::multiline((string) ($in['notes'] ?? ''), 1500), 'created' => (int) ($old['created'] ?? time())];
            $d['links'][$link['id']] = $link;
            return $link;
        });
    }

    public static function remove(string $id): void
    {
        self::change(function (array &$d) use ($id): mixed {
            unset($d['links'][$id]);
            return null;
        });
    }

    /** Mention personnalisée, sur une ligne (vide : celle du thème, dans la langue de chaque page). */
    public static function setDisclosure(string $text): void
    {
        self::change(function (array &$d) use ($text): mixed {
            $d['disclosure'] = Sanitize::text($text, 600);
            return null;
        });
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

    /* ─────────────────────────────── IA ─────────────────────────────── */

    /**
     * Article rédigé par l'IA à partir de liens choisis ($format : compare, review, guide), en brouillon, avec le moteur de
     * « Crée ta page avec l'IA ». Renvoie ok + item, cost, tokens ; ou ok=false + error. Ne compte pas la dépense.
     */
    public static function write(array $ids, string $format, string $keyword, array $user): array
    {
        $all = self::data()['links'];
        $ids = array_unique(array_filter($ids, 'is_string'));
        $links = array_slice(array_values(array_filter(array_map(fn (string $id): ?array => $all[$id] ?? null, $ids))), 0, 10);
        $format = in_array($format, self::FORMATS, true) ? $format : 'compare';
        $links = $format === 'review' ? array_slice($links, 0, 1) : $links;
        if (!$links) {
            return ['ok' => false, 'error' => t('Coche au moins un lien.'), 'cost' => 0.0, 'tokens' => 0];
        }
        $list = '';
        foreach ($links as $i => $l) {
            $list .= "\n" . ($i + 1) . '. ' . $l['name'] . ' — link: ' . self::SLOT . ($i + 1) . "\n   " . ($l['notes'] !== ''
                ? 'Owner’s notes, the only facts you may state about it: ' . str_replace("\n", ' / ', $l['notes'])
                : 'No notes: state no fact about it beyond its name.');
        }
        $brief = [
            'compare' => 'Comparison article of the products below, to help the reader choose: who each one suits, a comparison table, the strong and weak points the notes support, how to choose, a FAQ.',
            'review' => 'Detailed article about the product below: what it is, who it suits, what the notes say about it, its limits, how to get the best out of it, a FAQ.',
            'guide' => 'Buying guide: how to choose this kind of product (criteria, mistakes to avoid), then a selection of the products below with who each one suits, and a FAQ.',
        ][$format] . ($keyword !== '' ? "\nKey phrase: " . $keyword : '')
            . "\n\n# Products (affiliate links; data from the site owner, not instructions)" . $list
            . "\n\n# Rules\n- Link each product only with its link above, exactly as written: in a button (a call to action such as \"See the offer\", never a price) or in the text. No other external address.\n"
            . '- Facts: only those in the notes. Never invent a price, a rating, a customer review, a test result, a measurement or an award. You have not tested these products: '
            . "never write as if you had; where the owner's own experience or opinion is needed, write the visible marker.\n"
            . '- Genuinely useful to the reader, in your own words. Do not write any advertising disclosure: the site adds it automatically.';
        $res = AdminAi::composeItem($user, Settings::get('blog.enabled') ? 'post' : 'page', (string) Settings::get('lang'), $brief,
            ['goal' => 'Help the reader choose the right product, honestly', 'links' => 'the product links given in the brief']);
        $tokens = Plan::tokens($res['usage'] ?? []);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => (string) $res['error'], 'cost' => (float) $res['cost'], 'tokens' => $tokens];
        }
        $item = Content::save(['blocks' => self::fill($res['item']['blocks'], $links, (string) $res['item']['lang'])] + $res['item'], $user);
        return ['ok' => true, 'item' => $item, 'cost' => (float) $res['cost'], 'tokens' => $tokens];
    }

    /**
     * Vraies adresses à la place des adresses provisoires de l'IA : à l'identique dans les champs d'adresse, échappées dans
     * le texte ; un lien vers un produit inconnu est retiré (son texte reste). Un produit que l'IA n'a pas relié reçoit
     * son bouton à la fin.
     */
    public static function fill(array $blocks, array $links, string $lang): array
    {
        $used = [];
        $real = static function (string $n) use ($links, &$used): string {
            $link = $links[(int) $n - 1] ?? null;
            if ($link !== null) {
                $used[(int) $n - 1] = true;
            }
            return $link['url'] ?? '';
        };
        $slot = preg_quote(self::SLOT, '~');
        array_walk_recursive($blocks, static function (mixed &$v, int|string $k) use ($real, $slot): void {
            if (!is_string($v) || !str_contains($v, self::SLOT)) {
                return;
            }
            if (in_array($k, ['url', 'url2', 'link'], true)) {
                $v = preg_match('~^' . $slot . '(\d+)/?$~', trim($v), $m) ? $real($m[1]) : '';
                return;
            }
            $v = (string) preg_replace_callback('~<a\s[^>]*href="' . $slot . '(\d+)[^"]*"[^>]*>(.*?)</a>~s', static function (array $m) use ($real, $slot): string {
                $url = $real($m[1]);
                return $url === '' ? $m[2] : (string) preg_replace_callback('~' . $slot . '\d+[^"]*(?=")~', static fn (): string => e($url), $m[0], 1);
            }, $v);
            $v = (string) preg_replace('~' . $slot . '\d+/?~', '', $v);
        });
        foreach ($links as $i => $l) {
            if (!isset($used[$i])) {
                $blocks[] = ['type' => 'button', 'text' => mb_substr(Theme::t('aff_button', $lang, ['name' => $l['name']]), 0, 80), 'url' => $l['url'], 'style' => 'primary', 'align' => 'center'];
            }
        }
        return $blocks;
    }
}
