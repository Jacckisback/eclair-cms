<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Planning éditorial dans l'administration : calendrier, idées, rédaction par l'IA, mode automatique (voir Plan). */
final class AdminPlan
{
    public static function page(array $user): never
    {
        $m = (string) Req::query('m');
        $first = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? (int) strtotime($m . '-01') : (int) strtotime(date('Y-m-01'));
        $ai = Ai::allowed($user);
        if ($ai) {
            Ai::choose((string) ($user['ai'] ?? ''));
        }
        $items = Plan::all();
        $index = Content::index();
        $cost = 0.0;
        $tokens = 0;
        foreach ($items as $id => $item) {
            $items[$id]['state'] = Plan::state($item, $index);
            $items[$id]['entry'] = $item['content'] !== '' ? ($index[$item['content']] ?? null) : null;
            $cost += (float) $item['cost'];
            $tokens += (int) $item['tokens'];
        }
        $auto = Plan::auto();
        Admin::view('plan', [
            'first' => $first,
            'days' => Plan::month(date('Y-m', $first)),
            // À venir : idées et brouillons ; les articles publiés restent dans le calendrier.
            'items' => array_filter($items, fn ($i) => $i['state'] === 'idea' || $i['state'] === 'draft'),
            'done' => count(array_filter($items, fn ($i) => $i['state'] === 'scheduled' || $i['state'] === 'published')),
            'ai' => $ai,
            'ideasCost' => $ai ? Plan::ideasCost(Ai::provider()) : '',
            'writeCost' => $ai ? AdminAi::composeCost(Ai::provider()) : '',
            'model' => $ai ? Ai::label() : '',
            'cost' => $cost,
            'tokens' => $tokens,
            'usage' => Ai::usage(),
            'budget' => (float) Ai::config()['budget'],
            'auto' => $auto,
            'autoBy' => $auto['by'] !== '' ? (Users::find((string) $auto['by'])['name'] ?? '') : '',
            'admin' => Auth::can('settings'),
            'cron' => Auth::can('settings') ? Plan::cronUrl() : '',
            'categories' => Categories::of('post'),
            'blog' => (bool) Settings::get('blog.enabled'),
        ], t('Planning éditorial'));
    }

    /** Formulaires sans JavaScript : ajouter, modifier, supprimer une idée ; réglages du mode automatique. */
    public static function action(array $user): never
    {
        $action = Req::input('action');
        $id = preg_replace('/[^a-z0-9]/', '', Req::input('id')) ?? '';
        $back = 'plan' . (preg_match('/^\d{4}-\d{2}$/', Req::input('m')) ? '?m=' . Req::input('m') : '');
        if ($action === 'delete') {
            Plan::remove($id);
            Admin::back($back, t('Idée supprimée.'));
        }
        if ($action === 'auto') {
            if (!Auth::can('settings')) {
                Admin::forbidden();
            }
            $on = Req::bool('on');
            if ($on && !Ai::allowed($user)) {
                Admin::back($back, t('Active d’abord l’assistant IA dans Réglages › Assistant IA.'), 'bad');
            }
            $lead = (int) Req::input('lead');
            Plan::setAuto(['on' => $on, 'lead' => in_array($lead, [1, 2, 3, 5, 7, 14], true) ? $lead : 3, 'publish' => Req::bool('publish'), 'by' => $on ? $user['id'] : '']);
            Admin::back($back, $on ? t('Rédaction automatique activée.') : t('Rédaction automatique coupée.'));
        }
        if ($action === 'save' || $action === 'add') {
            $r = Plan::put(['title' => Req::input('title'), 'keyword' => Req::input('keyword'), 'brief' => Req::input('brief'),
                'category' => Req::input('category'), 'date' => Req::input('date')], $action === 'save' ? $id : '');
            if (is_string($r)) {
                Admin::back($back, $r, 'bad');
            }
            Admin::back($back, $action === 'add' ? t('Idée ajoutée au planning.') : t('Idée enregistrée.'));
        }
        Admin::back($back);
    }

    private static function guard(array $user): void
    {
        if (!Ai::allowed($user)) {
            Http::json(['ok' => false, 'error' => t('L’assistant IA n’est pas activé pour ton compte.')], 403);
        }
        Ai::choose((string) ($user['ai'] ?? ''));
    }

    /** Verrou de la demande, budget et quota du jour (comme l'assistant). */
    private static function start(array $user)
    {
        $slot = Ai::slot($user['id']);
        if ($slot === null) {
            Http::json(['ok' => false, 'error' => t('Une demande à l’assistant est déjà en cours : attends qu’elle se termine.')], 429);
        }
        if (($error = Ai::reserve($user)) !== null) {
            Http::json(['ok' => false, 'error' => $error], 429);
        }
        session_write_close();
        extend_time(300);
        return $slot;
    }

    private static function spent(array $res): string
    {
        return t('{tokens} jetons · {cost}', ['tokens' => number_format((int) $res['tokens'], 0, ',', "\u{202F}"), 'cost' => Ai::money((float) $res['cost'])]);
    }

    /** « Proposer des idées » : l'IA remplit le planning d'après le site. */
    public static function ideas(array $user): never
    {
        self::guard($user);
        $data = Req::json();
        if (count(Plan::all()) >= Plan::MAX) {
            Http::json(['ok' => false, 'error' => t('Le planning compte déjà {n} idées : supprime celles qui ne servent plus.', ['n' => Plan::MAX])], 422);
        }
        $slot = self::start($user);
        $res = Plan::propose((int) ($data['count'] ?? 6), (int) ($data['week'] ?? 2), Sanitize::text(is_string($data['theme'] ?? null) ? $data['theme'] : '', 300));
        Ai::charge($user['id'], (float) $res['cost']);
        if (!$res['ok']) {
            Http::json(['ok' => false, 'error' => $res['error']], 502);
        }
        Session::flash('ok', t('{n} idées ajoutées au planning ({spent}).', ['n' => $res['added'], 'spent' => self::spent($res)]));
        Http::json(['ok' => true]);
    }

    /** « Rédiger » : l'IA écrit l'article en brouillon, qui s'ouvre ensuite dans l'éditeur. */
    public static function write(array $user): never
    {
        self::guard($user);
        $id = preg_replace('/[^a-z0-9]/', '', (string) (Req::json()['id'] ?? '')) ?? '';
        if (AiTools::draftsFull()) {
            Http::json(['ok' => false, 'error' => t('Le site compte déjà {n} brouillons : publie ou supprime-en avant d’en créer d’autres avec l’IA.', ['n' => AiTools::MAX_SITE_DRAFTS])], 422);
        }
        $slot = self::start($user);
        $res = Plan::write($id, $user);
        Ai::charge($user['id'], (float) $res['cost']);
        if (!$res['ok']) {
            Http::json(['ok' => false, 'error' => $res['error']], 502);
        }
        Session::flash('ok', t('Brouillon rédigé ({spent}). Relis-le, complète-le, puis programme-le.', ['spent' => self::spent($res)]));
        Http::json(['ok' => true, 'edit' => admin_url('edit/' . $res['item']['id'])]);
    }

    /** Mode automatique : rédige la prochaine idée due pendant qu'un administrateur a l'administration ouverte. */
    public static function tick(array $user): never
    {
        session_write_close();
        ignore_user_abort(true);
        extend_time(300);
        $r = Plan::run();
        Http::json(['ok' => $r['status'] !== 'error', 'status' => $r['status'],
            'text' => $r['status'] === 'done' ? t('Planning : « {title} » rédigé par l’IA.', ['title' => $r['title']]) : ''] + ($r['status'] === 'error' ? ['error' => $r['error']] : []));
    }
}
