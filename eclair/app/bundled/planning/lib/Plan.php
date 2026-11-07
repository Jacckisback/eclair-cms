<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Planning;

use Eclair\AdminAi;
use Eclair\Ai;
use Eclair\AiChat;
use Eclair\AiTools;
use Eclair\Analyzer;
use Eclair\Blocks;
use Eclair\Categories;
use Eclair\Content;
use Eclair\Extensions;
use Eclair\Http;
use Eclair\Keys;
use Eclair\RateLimit;
use Eclair\Req;
use Eclair\Sanitize;
use Eclair\Settings;
use Eclair\Store;
use Eclair\Users;

defined('ECLAIR') || exit;

/**
 * Fonction « Planning éditorial » (livrée avec Éclair) : les articles à venir, chacun avec sa date prévue. Une idée s'ajoute à la main ou est proposée par
 * l'IA d'après le site (activité, catégories, articles déjà écrits, pages que les IA lisent le plus) ; l'IA la rédige
 * ensuite en brouillon, sur demande ou toute seule quelques jours avant sa date (mode automatique). Le coût de chaque
 * appel (jetons et dollars) est noté sur l'idée et compté dans le budget de l'assistant.
 *
 * Données : data/plan.php = ['items' => [id => idée], 'auto' => réglages du mode automatique]. Une idée devient un contenu
 * (brouillon, programmé, publié) : elle garde son id ('content') et suit dès lors l'état de ce contenu.
 */
final class Plan
{
    public const MAX = 300;
    /** Délai avant de réessayer la rédaction automatique d'une idée qui a échoué (fournisseur indisponible, budget…). */
    private const RETRY = 21600;
    public const AUTO = ['on' => false, 'lead' => 3, 'publish' => false, 'by' => ''];

    private static function data(): array
    {
        $d = Store::read('plan', []);
        $d = is_array($d) ? $d : [];
        return ['items' => is_array($d['items'] ?? null) ? $d['items'] : [], 'auto' => (is_array($d['auto'] ?? null) ? $d['auto'] : []) + self::AUTO];
    }

    /** Idées, de la plus proche à la plus lointaine ; celles sans date à la fin. */
    public static function all(): array
    {
        $items = self::data()['items'];
        uasort($items, fn (array $a, array $b): int => [$a['date'] === '' ? 1 : 0, $a['date'], $a['created']] <=> [$b['date'] === '' ? 1 : 0, $b['date'], $b['created']]);
        return $items;
    }

    public static function get(string $id): ?array
    {
        return self::data()['items'][$id] ?? null;
    }

    public static function auto(): array
    {
        return self::data()['auto'];
    }

    public static function setAuto(array $auto): void
    {
        Store::locked(function () use ($auto): void {
            $d = self::data();
            $d['auto'] = array_intersect_key($auto + $d['auto'], self::AUTO);
            Store::write('plan', $d);
        });
    }

    /** Date valide (AAAA-MM-JJ) ou chaîne vide. */
    public static function day(string $raw): string
    {
        return valid_day($raw);
    }

    /** Ajoute ou modifie une idée (champs nettoyés). Renvoie l'idée, ou un message si le planning est plein. */
    public static function put(array $in, string $id = ''): array|string
    {
        return Store::locked(function () use ($in, $id): array|string {
            $d = self::data();
            $old = $id !== '' ? ($d['items'][$id] ?? null) : null;
            if ($old === null && count($d['items']) >= self::MAX) {
                return t('Le planning compte déjà {n} idées : supprime celles qui ne servent plus.', ['n' => self::MAX]);
            }
            $category = preg_replace('/[^a-z0-9]/', '', (string) ($in['category'] ?? $old['category'] ?? '')) ?? '';
            $item = [
                'id' => $old['id'] ?? random_id(),
                'title' => Sanitize::text((string) ($in['title'] ?? $old['title'] ?? ''), 200),
                'keyword' => Sanitize::text((string) ($in['keyword'] ?? $old['keyword'] ?? ''), 80),
                'brief' => Sanitize::multiline((string) ($in['brief'] ?? $old['brief'] ?? ''), 1500),
                'category' => (Categories::get($category)['kind'] ?? '') === 'post' ? $category : '',
                'date' => self::day((string) ($in['date'] ?? $old['date'] ?? '')),
                'content' => (string) ($old['content'] ?? ''),
                'source' => $old['source'] ?? (($in['source'] ?? '') === 'ai' ? 'ai' : 'manual'),
                'cost' => (float) ($old['cost'] ?? 0) + max(0.0, (float) ($in['cost'] ?? 0)),
                'tokens' => (int) ($old['tokens'] ?? 0) + max(0, (int) ($in['tokens'] ?? 0)),
                'error' => '',
                'tried' => 0,
                'created' => (int) ($old['created'] ?? time()),
            ];
            if ($item['title'] === '') {
                return t('Donne un titre à ton idée.');
            }
            $d['items'][$item['id']] = $item;
            Store::write('plan', $d);
            return $item;
        });
    }

    /** Met à jour quelques champs internes d'une idée (contenu rédigé, coût, erreur). */
    private static function patch(string $id, array $fields): ?array
    {
        return Store::locked(function () use ($id, $fields): ?array {
            $d = self::data();
            if (!isset($d['items'][$id])) {
                return null;
            }
            $d['items'][$id] = array_replace($d['items'][$id], $fields);
            Store::write('plan', $d);
            return $d['items'][$id];
        });
    }

    public static function remove(string $id): ?array
    {
        return Store::locked(function () use ($id): ?array {
            $d = self::data();
            $item = $d['items'][$id] ?? null;
            unset($d['items'][$id]);
            Store::write('plan', $d);
            return $item;
        });
    }

    /** Remet une idée supprimée (bouton « Annuler »). */
    public static function restore(array $item): void
    {
        Store::locked(function () use ($item): void {
            $d = self::data();
            if (count($d['items']) < self::MAX && preg_match('/^[a-z0-9]{1,32}$/', (string) ($item['id'] ?? ''))) {
                $d['items'][$item['id']] = $item;
                Store::write('plan', $d);
            }
        });
    }

    /** État d'une idée : idea, draft, scheduled, published (d'après son contenu), ou idea si le contenu a été supprimé. */
    public static function state(array $item, ?array $index = null): string
    {
        $index ??= Content::index();
        $e = $item['content'] !== '' ? ($index[$item['content']] ?? null) : null;
        if ($e === null) {
            return 'idea';
        }
        return $e['status'] === 'draft' ? 'draft' : ((int) $e['date'] > time() ? 'scheduled' : 'published');
    }

    /**
     * Calendrier d'un mois (AAAA-MM) : jours => éléments [kind, title, id, url]. Réunit les idées datées et les articles
     * (publiés, programmés) du mois. Les idées déjà rédigées s'affichent à la date de leur contenu.
     */
    public static function month(string $ym): array
    {
        $index = Content::index();
        $days = [];
        $linked = [];
        foreach (self::all() as $item) {
            $state = self::state($item, $index);
            if ($state !== 'idea') {
                $linked[$item['content']] = $item;
            }
            $date = $state === 'idea' || $state === 'draft' ? $item['date'] : date('Y-m-d', (int) $index[$item['content']]['date']);
            if ($date !== '' && str_starts_with($date, $ym . '-')) {
                $days[$date][] = ['kind' => $state, 'title' => $state === 'idea' ? $item['title'] : $index[$item['content']]['title'], 'plan' => $item['id'],
                    'content' => $state === 'idea' ? '' : $item['content']];
            }
        }
        foreach ($index as $id => $e) {
            if (isset($linked[$id]) || $e['type'] !== 'post' || $e['status'] !== 'published') {
                continue;
            }
            $date = date('Y-m-d', (int) $e['date']);
            if (str_starts_with($date, $ym . '-')) {
                $days[$date][] = ['kind' => (int) $e['date'] > time() ? 'scheduled' : 'published', 'title' => $e['title'], 'plan' => '', 'content' => $id];
            }
        }
        return $days;
    }

    /**
     * Prochaines dates libres pour $n idées, $perWeek par semaine, à partir de demain (ou après la dernière date prévue) :
     * jamais deux le même jour, ni un jour qui a déjà un article prévu ou publié.
     */
    public static function slots(int $n, int $perWeek, ?int $from = null): array
    {
        $perWeek = max(1, min(7, $perWeek));
        $taken = [];
        $last = '';
        foreach (self::all() as $item) {
            if ($item['date'] !== '') {
                $taken[$item['date']] = true;
                $last = max($last, $item['date']);
            }
        }
        foreach (Content::index() as $e) {
            if ($e['type'] === 'post' && $e['status'] === 'published') {
                $taken[date('Y-m-d', (int) $e['date'])] = true;
            }
        }
        $start = strtotime('tomorrow', $from ?? time());
        if ($last !== '' && ($t = strtotime($last . ' +1 day')) > $start) {
            $start = $t;
        }
        $gap = 7 / $perWeek;
        $out = [];
        $prev = $start - 86400;
        for ($i = 0; $i < $n; $i++) {
            $t = max((int) strtotime('+' . (int) round($i * $gap) . ' days', $start), $prev + 86400);
            while (isset($taken[date('Y-m-d', $t)])) {
                $t = (int) strtotime('+1 day', $t);
            }
            $taken[date('Y-m-d', $t)] = true;
            $out[] = date('Y-m-d', $t);
            $prev = $t;
        }
        return $out;
    }

    /* ─────────────────────────────── IA ─────────────────────────────── */

    /** Coûts indicatifs : une série d'idées, un article. */
    public static function ideasCost(string $provider): string
    {
        return Ai::money(round(Ai::cost($provider, Ai::model($provider), ['in' => 5000, 'out' => 2500]), 3));
    }

    public static function tokens(array $usage): int
    {
        return Ai::tokens($usage);
    }

    /**
     * Demande $n idées d'articles à l'IA, d'après ce que le site contient déjà, et les place dans le planning
     * ($perWeek par semaine). Renvoie ok + added (nombre), cost, tokens ; ou ok=false + error. Ne compte pas la dépense.
     */
    public static function propose(int $n, int $perWeek, string $theme): array
    {
        $n = max(1, min(12, $n));
        $lang = (string) Settings::get('lang');
        $index = Content::index();
        $titles = static fn (string $type): string => implode("\n", array_map(fn ($e) => '- ' . $e['title'],
            array_slice(array_filter($index, fn ($e) => $e['type'] === $type && $e['status'] !== 'draft'), 0, 80))) ?: '(none)';
        $planned = implode("\n", array_map(fn ($i) => '- ' . $i['title'], array_slice(self::all(), 0, 80))) ?: '(none)';
        $cats = implode("\n", array_map(fn ($c) => '- ' . $c['id'] . ' | ' . $c['name'], Categories::of('post'))) ?: '(none)';
        $read = '';
        $report = Settings::get('geo.ai_log') && Store::exists('extensions') ? Extensions::invoke('ai-visits', 'report', [30]) : null;
        if (is_array($report)) {
            $read = implode("\n", array_map(fn ($p, $s) => '- ' . $p . ' (' . $s['count'] . ' visits, ' . $s['user'] . ' on demand)', array_keys(array_slice($report['pages'], 0, 8, true)), array_slice($report['pages'], 0, 8, true)));
        }
        $dates = self::slots($n, $perWeek);
        $task = "Propose $n blog article ideas for this website's editorial calendar, to bring qualified visitors from search engines and to be quoted by AI answer engines.\n"
            . "- Each idea answers one precise question or need of the site's audience, related to what the business really does. One distinct search intent per idea: no duplicates of existing or planned articles, no near-duplicates between ideas.\n"
            . "- Mix formats: complete guides, how-to, comparisons, mistakes to avoid, checklists, questions people ask before buying or booking. Take the season into account: the articles are planned for " . ($dates[0] ?? '') . ' to ' . (end($dates) ?: '') . ".\n"
            . "- title: 40 to 60 characters, natural, containing the key phrase; keyword: 2 to 4 words people really type; brief: 1 to 3 sentences saying what the article answers, for whom, and what makes it more useful than the rest; category: an id from the list, or empty; why: under 15 words, why this article is worth writing now.\n"
            . '- Never invent facts about the business. Write in ' . (Content::LANGS[$lang] ?? $lang) . '.';
        $input = '# Site: ' . Settings::get('name') . ' — ' . Settings::get('tagline') . "\n" . (Settings::get('geo.about') ? 'About: ' . Settings::get('geo.about') . "\n" : '')
            . ($theme !== '' ? "\n# Theme wished by the person\n" . $theme . "\n" : '')
            . "\n# Article categories (id | name)\n" . $cats . "\n\n# Articles already published\n" . $titles('post') . "\n\n# Site pages\n" . $titles('page')
            . "\n\n# Articles already planned\n" . $planned . ($read !== '' ? "\n\n# Pages AI bots read most (last 30 days): build on these topics\n" . $read : '');
        $str = ['type' => 'string'];
        $res = AiChat::ask($task, $input, ['type' => 'object', 'properties' => [
            'ideas' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['title' => $str, 'keyword' => $str, 'brief' => $str, 'category' => $str, 'why' => $str],
                'required' => ['title', 'keyword', 'brief', 'category', 'why'], 'additionalProperties' => false]],
        ], 'required' => ['ideas'], 'additionalProperties' => false]);
        $tokens = self::tokens($res['usage'] ?? []);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'], 'cost' => (float) $res['cost'], 'tokens' => $tokens];
        }
        $ideas = array_values(array_filter(is_array($res['data']['ideas'] ?? null) ? $res['data']['ideas'] : [], 'is_array'));
        $ideas = array_slice($ideas, 0, $n);
        $share = $ideas ? (float) $res['cost'] / count($ideas) : 0.0;
        $added = 0;
        foreach ($ideas as $i => $idea) {
            $why = Sanitize::text((string) ($idea['why'] ?? ''), 200);
            $brief = Sanitize::multiline((string) ($idea['brief'] ?? ''), 1200) . ($why !== '' ? "\n" . $why : '');
            $r = self::put(['title' => $idea['title'] ?? '', 'keyword' => $idea['keyword'] ?? '', 'brief' => $brief, 'category' => $idea['category'] ?? '',
                'date' => $dates[$i] ?? '', 'source' => 'ai', 'cost' => $share, 'tokens' => $ideas ? intdiv($tokens, count($ideas)) : 0]);
            $added += is_array($r) ? 1 : 0;
        }
        return $added
            ? ['ok' => true, 'added' => $added, 'cost' => (float) $res['cost'], 'tokens' => $tokens]
            : ['ok' => false, 'error' => t('Réponse inattendue de l’IA : réessaie.'), 'cost' => (float) $res['cost'], 'tokens' => $tokens];
    }

    /**
     * Rédige une idée en brouillon (un appel, comme « Crée ta page avec l'IA ») et le lie à l'idée. Avec $publish, le
     * brouillon est programmé à la date prévue, sauf s'il reste des passages « À compléter » : il attend alors une relecture.
     * Renvoie ok + item (contenu) + published, cost, tokens ; ou ok=false + error. Ne compte pas la dépense.
     */
    public static function write(string $id, array $user, bool $publish = false): array
    {
        $idea = self::get($id);
        if ($idea === null || self::state($idea) !== 'idea') {
            return ['ok' => false, 'error' => t('Cette idée a déjà été rédigée ou supprimée.'), 'cost' => 0.0, 'tokens' => 0];
        }
        $lang = (string) Settings::get('lang');
        $brief = "Blog article: " . $idea['title'] . ($idea['keyword'] !== '' ? "\nKey phrase: " . $idea['keyword'] : '')
            . ($idea['brief'] !== '' ? "\nAngle: " . $idea['brief'] : '');
        $res = AdminAi::composeItem($user, 'post', $lang, $brief, ['goal' => 'Inform the audience and bring qualified visitors from search engines and AI answers'],
            ['category' => $idea['category']]);
        $tokens = self::tokens($res['usage'] ?? []);
        if (!$res['ok']) {
            self::patch($id, ['error' => (string) $res['error'], 'tried' => time(), 'cost' => (float) $idea['cost'] + (float) $res['cost'], 'tokens' => (int) $idea['tokens'] + $tokens]);
            return ['ok' => false, 'error' => $res['error'], 'cost' => (float) $res['cost'], 'tokens' => $tokens];
        }
        $item = $res['item'];
        $published = false;
        $todo = Analyzer::TODO[$lang] ?? Analyzer::TODO['en'];
        if ($publish && !str_contains(Blocks::text($item['blocks']) . ' ' . $item['summary'], '[' . $todo)) {
            $at = strtotime(max($idea['date'], date('Y-m-d')) . ' 08:00');
            $item = Content::save(['status' => 'published', 'date' => date('Y-m-d H:i', max($at, time()))] + $item, $user);
            $published = true;
        }
        self::patch($id, ['content' => $item['id'], 'error' => '', 'tried' => time(), 'cost' => (float) $idea['cost'] + (float) $res['cost'], 'tokens' => (int) $idea['tokens'] + $tokens]);
        return ['ok' => true, 'item' => $item, 'published' => $published, 'cost' => (float) $res['cost'], 'tokens' => $tokens];
    }

    /* ─────────────────────────── Mode automatique ─────────────────────────── */

    /** Prochaine idée à rédiger toute seule : date prévue dans les « lead » jours, pas d'échec récent. */
    public static function due(?int $now = null): ?array
    {
        $auto = self::auto();
        if (!$auto['on']) {
            return null;
        }
        $now ??= time();
        $limit = date('Y-m-d', $now + max(0, (int) $auto['lead']) * 86400);
        $index = Content::index();
        foreach (self::all() as $item) {
            if ($item['date'] !== '' && $item['date'] <= $limit && $item['date'] >= date('Y-m-d', $now - 7 * 86400)
                && self::state($item, $index) === 'idea' && (int) $item['tried'] < $now - self::RETRY) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Rédige la prochaine idée due (une par appel), au nom de la personne qui a activé le mode automatique. Appelé quand
     * un administrateur ouvre l'administration et par l'adresse de tâche planifiée. Renvoie status : idle, done, error.
     */
    public static function run(): array
    {
        $item = self::due();
        if ($item === null) {
            return ['status' => 'idle'];
        }
        $auto = self::auto();
        $user = Users::find((string) $auto['by']);
        if (!Ai::allowed($user)) {
            self::setAuto(['on' => false]);
            return ['status' => 'error', 'error' => t('Rédaction automatique arrêtée : le compte qui l’a activée n’a plus accès à l’assistant IA.')];
        }
        Ai::choose((string) ($user['ai'] ?? ''));
        $estimate = Ai::cost(Ai::provider(), Ai::model(), ['in' => 9000, 'out' => 6000]);
        if (($error = Ai::overBudget($estimate)) !== null || AiTools::draftsFull()) {
            self::patch($item['id'], ['error' => $error ?? t('Trop de brouillons sur le site.'), 'tried' => time()]);
            return ['status' => 'error', 'error' => $error ?? t('Trop de brouillons sur le site.')];
        }
        $lock = @fopen(ECLAIR_DATA . '/plan.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['status' => 'busy'];
        }
        try {
            if (self::due() === null) {
                return ['status' => 'idle']; // rédigée entre-temps par un autre onglet
            }
            self::patch($item['id'], ['tried' => time()]);
            $res = self::write($item['id'], $user, (bool) $auto['publish']);
            Ai::charge($user['id'], (float) $res['cost']);
            if (!$res['ok']) {
                return ['status' => 'error', 'error' => $res['error']];
            }
            return ['status' => 'done', 'title' => $res['item']['title'], 'published' => $res['published']];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Adresse à appeler régulièrement (cron de l'hébergeur…) : elle ne rédige que si une idée est due. */
    public static function cronUrl(): string
    {
        return absolute_url('/_eclair/plan/' . self::cronKey());
    }

    private static function cronKey(): string
    {
        return substr(Keys::sign('plan-cron'), 0, 32);
    }

    public static function cron(string $key): never
    {
        if (!hash_equals(self::cronKey(), $key) || !RateLimit::attempt('plan-cron:' . Req::ip(), 30, 3600, 3600)) {
            Http::json(['ok' => false], 404);
        }
        ignore_user_abort(true);
        extend_time(300);
        $r = self::run();
        Http::json(['ok' => $r['status'] !== 'error', 'status' => $r['status']]);
    }
}
