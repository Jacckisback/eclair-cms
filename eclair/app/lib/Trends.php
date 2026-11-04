<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Tendances du jour de Google Trends (menu Discover) : le flux public « Tendances du moment » d'un pays, lu au plus une fois
 * par heure, et des idées d'articles que l'IA en tire pour le planning, seulement quand une tendance touche le sujet du site.
 *
 * L'API officielle de Google Trends est en test fermé (developers.google.com/search/blog/2025/07/trends-api) : Éclair lit
 * le flux RSS public (trends.google.com/trending/rss?geo=FR), sans compte ni clé. La plupart des tendances du jour sont de
 * l'actualité (personnes, sport, faits divers) : l'IA ne propose jamais un article d'actualité, seulement un angle utile et
 * durable, relié à ce que fait le site, et ne rapporte aucun fait sur l'actualité elle-même.
 *
 * Données : data/trends.php = ['geo' => 'FR', 'fetched' => horodatage, 'items' => [tendance…]].
 */
final class Trends
{
    /** Pays proposés (ceux des langues d'Éclair). */
    public const GEOS = ['FR', 'BE', 'CH', 'CA', 'LU', 'ES', 'MX', 'AR', 'CO', 'CL', 'PE', 'US', 'GB', 'IE', 'AU', 'DE', 'AT', 'IT', 'PT', 'BR'];
    /** Idées d'articles au plus par demande à l'IA. */
    public const IDEAS = 4;
    private const TTL = 3600;
    private const MAX_ITEMS = 25;
    private const MAX_BYTES = 2_000_000;

    /** Pays des tendances : celui choisi, sinon celui de la langue du site. */
    public static function geo(): string
    {
        $geo = strtoupper((string) Settings::get('discover.geo'));
        if (in_array($geo, self::GEOS, true)) {
            return $geo;
        }
        return ['fr' => 'FR', 'es' => 'ES', 'en' => 'US', 'de' => 'DE', 'it' => 'IT', 'pt' => 'PT'][(string) Settings::get('lang')] ?? 'US';
    }

    /** Tendances lues il y a moins d'une heure pour ce pays, sinon null. */
    public static function cached(string $geo): ?array
    {
        $d = Store::read('trends', []);
        return is_array($d) && ($d['geo'] ?? '') === $geo && is_array($d['items'] ?? null) && time() - (int) ($d['fetched'] ?? 0) < self::TTL ? $d : null;
    }

    /** Lit le flux du pays, au plus une fois par heure. Renvoie ok + geo, fetched, items ; ou ok=false + error. */
    public static function fetch(string $geo): array
    {
        if (!in_array($geo, self::GEOS, true)) {
            return ['ok' => false, 'error' => t('Pays inconnu.')];
        }
        if ($cached = self::cached($geo)) {
            return ['ok' => true] + $cached;
        }
        $res = Ai::request('GET', Ai::endpoint('trends') . '/trending/rss?geo=' . $geo, ['Accept: application/rss+xml, application/xml;q=0.9'], null, 20);
        if ($res['status'] !== 200) {
            Log::error(new \RuntimeException('Google Trends (' . $res['status'] . ') : ' . $res['error']));
            return ['ok' => false, 'error' => t('Google Trends ne répond pas pour le moment : réessaie dans quelques minutes.')];
        }
        $items = strlen($res['body']) <= self::MAX_BYTES ? self::parse($res['body']) : null;
        if ($items === null) {
            return ['ok' => false, 'error' => t('Réponse inattendue de Google Trends : réessaie plus tard.')];
        }
        $d = ['geo' => $geo, 'fetched' => time(), 'items' => $items];
        Store::write('trends', $d);
        return ['ok' => true] + $d;
    }

    /** Tendances du flux : recherche, volume approximatif, heure et jusqu'à 3 articles de presse ; null si le flux est illisible. */
    public static function parse(string $xml): ?array
    {
        if (!str_contains($xml, '<rss')) {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($doc === false || !isset($doc->channel)) {
            return null;
        }
        $ns = (string) ($doc->getDocNamespaces(true)['ht'] ?? 'https://trends.google.com/trending/rss');
        $text = static fn (mixed $value, int $max): string => Sanitize::text(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $max);
        $out = [];
        foreach ($doc->channel->item as $item) {
            $title = $text($item->title, 120);
            if ($title === '') {
                continue;
            }
            $ht = $item->children($ns);
            $news = [];
            foreach ($ht->news_item as $n) {
                $c = $n->children($ns);
                $url = Sanitize::url(trim((string) $c->news_item_url));
                $headline = $text($c->news_item_title, 200);
                if ($headline !== '' && preg_match('~^https?://[^\s"<>]+$~i', $url) && strlen($url) <= 600) {
                    $news[] = ['title' => $headline, 'url' => $url, 'source' => $text($c->news_item_source, 80)];
                }
                if (count($news) === 3) {
                    break;
                }
            }
            $date = strtotime((string) $item->pubDate);
            $out[] = ['title' => $title, 'traffic' => $text($ht->approx_traffic, 20), 'date' => $date === false ? 0 : $date, 'news' => $news];
            if (count($out) === self::MAX_ITEMS) {
                break;
            }
        }
        return $out;
    }

    /**
     * Idées d'articles tirées des tendances (au plus 4), seulement celles qui touchent le sujet du site, placées dans le
     * planning dès aujourd'hui (une tendance retombe vite). Renvoie ok + added (0 si aucune ne convient), cost, tokens ;
     * ou ok=false + error. Ne compte pas la dépense.
     */
    public static function propose(array $trends, string $geo): array
    {
        $trends = array_slice(array_values(array_filter($trends, fn (mixed $t): bool => is_array($t) && ($t['title'] ?? '') !== '')), 0, self::MAX_ITEMS);
        if (!$trends) {
            return ['ok' => false, 'error' => t('Charge d’abord les tendances du jour.'), 'cost' => 0.0, 'tokens' => 0];
        }
        $lang = (string) Settings::get('lang');
        $index = Content::index();
        $titles = implode("\n", array_map(fn (array $e): string => '- ' . $e['title'], array_slice(array_filter($index,
            fn (array $e): bool => $e['status'] !== 'draft' && in_array($e['type'], ['post', 'page'], true)), 0, 80))) ?: '(none)';
        $planned = implode("\n", array_map(fn (array $i): string => '- ' . $i['title'], array_slice(Plan::all(), 0, 60))) ?: '(none)';
        $cats = implode("\n", array_map(fn (array $c): string => '- ' . $c['id'] . ' | ' . $c['name'], Categories::of('post'))) ?: '(none)';
        $list = implode("\n", array_map(fn (array $t, int $i): string => ($i + 1) . '. ' . $t['title'] . ($t['traffic'] !== '' ? ' (' . $t['traffic'] . ' searches)' : '')
            . ($t['news'] ? ' — headlines: ' . implode(' | ', array_map(fn (array $n): string => $n['title'] . ($n['source'] !== '' ? ' (' . $n['source'] . ')' : ''),
                array_slice($t['news'], 0, 2))) : ''), $trends, array_keys($trends)));
        $task = "Below are today's Google Trends searches in " . $geo . ". Keep only the trends that clearly match this website's topic and audience, and propose at most "
            . self::IDEAS . " article ideas the website can genuinely write with its own expertise.\n"
            . "- Most daily trends are news about people, sport or events: skip them unless the website really covers that subject. Proposing no idea at all is a correct answer.\n"
            . "- Never propose a news report and never state facts about the news itself (the headlines are only there to understand the trend): the angle must be useful and lasting (explain, guide, background, practical advice) and link the trend to what the website does.\n"
            . "- title: 40 to 60 characters, natural, never sensational, misleading or clickbait; keyword: 2 to 4 words people really type; brief: 1 to 3 sentences saying what the article answers and for whom; category: an id from the list, or empty; trend: the trend it builds on, exactly as listed; why: under 15 words.\n"
            . '- Never invent facts about the business. Write in ' . (Content::LANGS[$lang] ?? $lang) . '.';
        $input = '# Site: ' . Settings::get('name') . ' — ' . Settings::get('tagline') . "\n" . (Settings::get('geo.about') ? 'About: ' . Settings::get('geo.about') . "\n" : '')
            . "\n# Article categories (id | name)\n" . $cats . "\n\n# Content already published\n" . $titles . "\n\n# Articles already planned\n" . $planned
            . "\n\n# Today's Google Trends (" . $geo . ")\n" . $list;
        $str = ['type' => 'string'];
        $res = AiChat::ask($task, $input, ['type' => 'object', 'properties' => [
            'ideas' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['title' => $str, 'keyword' => $str, 'brief' => $str, 'category' => $str, 'trend' => $str, 'why' => $str],
                'required' => ['title', 'keyword', 'brief', 'category', 'trend', 'why'], 'additionalProperties' => false]],
        ], 'required' => ['ideas'], 'additionalProperties' => false]);
        $tokens = Plan::tokens($res['usage'] ?? []);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'], 'cost' => (float) $res['cost'], 'tokens' => $tokens];
        }
        if (!is_array($res['data']['ideas'] ?? null)) {
            return ['ok' => false, 'error' => t('Réponse inattendue de l’IA : réessaie.'), 'cost' => (float) $res['cost'], 'tokens' => $tokens];
        }
        $ideas = array_slice(array_values(array_filter($res['data']['ideas'], 'is_array')), 0, self::IDEAS);
        $known = array_column($trends, 'title');
        $added = 0;
        foreach ($ideas as $idea) {
            $trend = Sanitize::text((string) ($idea['trend'] ?? ''), 120);
            $why = Sanitize::text((string) ($idea['why'] ?? ''), 200);
            $brief = Sanitize::multiline((string) ($idea['brief'] ?? ''), 1100) . ($why !== '' ? "\n" . $why : '')
                . (in_array($trend, $known, true) ? "\n" . t('Tendance Google du jour ({geo}) : {trend}', ['geo' => $geo, 'trend' => $trend]) : '');
            $r = Plan::put(['title' => $idea['title'] ?? '', 'keyword' => $idea['keyword'] ?? '', 'brief' => $brief, 'category' => $idea['category'] ?? '',
                'date' => date('Y-m-d'), 'source' => 'ai', 'cost' => (float) $res['cost'] / count($ideas), 'tokens' => intdiv($tokens, count($ideas))]);
            $added += is_array($r) ? 1 : 0;
        }
        return ['ok' => true, 'added' => $added, 'cost' => (float) $res['cost'], 'tokens' => $tokens];
    }

    /** « Ajouter au planning » : la tendance devient une idée datée d'aujourd'hui, avec ses titres de presse pour contexte. */
    public static function plan(array $trend, string $geo): array|string
    {
        $news = implode("\n", array_map(fn (array $n): string => '- ' . $n['title'] . ($n['source'] !== '' ? ' (' . $n['source'] . ')' : '') . ' ' . $n['url'],
            array_slice((array) ($trend['news'] ?? []), 0, 3)));
        return Plan::put([
            'title' => (string) ($trend['title'] ?? ''),
            'keyword' => mb_substr((string) ($trend['title'] ?? ''), 0, 80),
            'brief' => t('Tendance Google du jour ({geo}) : {trend}', ['geo' => $geo, 'trend' => (string) ($trend['title'] ?? '')])
                . ((string) ($trend['traffic'] ?? '') !== '' ? ' · ' . t('{n} recherches', ['n' => (string) $trend['traffic']]) : '') . "\n"
                . t('Angle à trouver : relie-la à ce que fait ton site, sans rapporter l’actualité elle-même.') . ($news !== '' ? "\n" . $news : ''),
            'date' => date('Y-m-d'),
        ]);
    }
}
