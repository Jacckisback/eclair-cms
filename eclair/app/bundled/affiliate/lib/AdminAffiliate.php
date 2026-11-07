<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Affiliate;

use Eclair\Admin;
use Eclair\AdminAi;
use Eclair\Affiliate;
use Eclair\Ai;
use Eclair\AiTools;
use Eclair\Auth;
use Eclair\Http;
use Eclair\Req;
use Eclair\Sanitize;
use Eclair\Session;
use Eclair\Settings;
use Eclair\Theme;

defined('ECLAIR') || exit;

/** Fonction « Affiliation » dans l'administration : liens, mention publicitaire, article rédigé par l'IA (voir Links). */
final class AdminAffiliate
{
    public static function page(array $user): never
    {
        $ai = Ai::allowed($user);
        if ($ai) {
            Ai::choose((string) ($user['ai'] ?? ''));
        }
        $lang = (string) Settings::get('lang');
        $v = [
            'links' => Affiliate::links(),
            'disclosure' => Affiliate::data()['disclosure'],
            'note' => Theme::t('aff_note', $lang),
            'amazon' => Affiliate::amazonStatement('', $lang),
            'ai' => $ai,
            'writeCost' => $ai ? AdminAi::composeCost(Ai::provider()) : '',
            'model' => $ai ? Ai::label() : '',
            'admin' => Auth::can('settings'),
            'blog' => (bool) Settings::get('blog.enabled'),
        ];
        ob_start();
        try {
            (static function (array $v): void {
                include __DIR__ . '/page.php';
            })($v);
        } finally {
            $html = (string) ob_get_clean();
        }
        Admin::view('extension', ['html' => $html, 'raw' => true, 'title' => t('Affiliation'), 'ext' => 'affiliate'], t('Affiliation'));
    }

    /** Formulaires sans JavaScript : ajouter, modifier, retirer un lien ; mention publicitaire (administrateurs). */
    public static function action(array $user): never
    {
        $action = Req::input('action');
        $id = preg_replace('/[^a-z0-9]/', '', Req::input('id')) ?? '';
        if ($action === 'add' || $action === 'save') {
            $r = Links::put(['name' => Req::input('name'), 'url' => Req::input('url'), 'notes' => Req::input('notes')], $action === 'save' ? $id : '');
            if (is_string($r)) {
                Admin::back('x/affiliate', $r, 'bad');
            }
            Admin::back('x/affiliate', $action === 'add' ? t('Lien ajouté.') : t('Lien enregistré.'));
        }
        if ($action === 'delete') {
            Links::remove($id);
            Admin::back('x/affiliate', t('Lien retiré de la liste.'));
        }
        if ($action === 'disclosure') {
            if (!Auth::can('settings')) {
                Admin::forbidden();
            }
            Links::setDisclosure(Req::input('disclosure'));
            Admin::back('x/affiliate', t('Mention publicitaire enregistrée.'));
        }
        Admin::back('x/affiliate');
    }

    /** « Rédiger avec les liens cochés » : brouillon écrit par l'IA, ouvert ensuite dans l'éditeur. */
    public static function write(array $user): never
    {
        if (!Ai::allowed($user)) {
            Http::json(['ok' => false, 'error' => t('L’assistant IA n’est pas activé pour ton compte.')], 403);
        }
        Ai::choose((string) ($user['ai'] ?? ''));
        $data = Req::json();
        if (AiTools::draftsFull()) {
            Http::json(['ok' => false, 'error' => t('Le site compte déjà {n} brouillons : publie ou supprime-en avant d’en créer d’autres avec l’IA.', ['n' => AiTools::MAX_SITE_DRAFTS])], 422);
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
        $res = Links::write(is_array($data['links'] ?? null) ? $data['links'] : [], is_string($data['format'] ?? null) ? $data['format'] : '',
            Sanitize::text(is_string($data['keyword'] ?? null) ? $data['keyword'] : '', 120), $user);
        Ai::charge($user['id'], (float) $res['cost']);
        if (!$res['ok']) {
            Http::json(['ok' => false, 'error' => $res['error']], $res['cost'] > 0 ? 502 : 422);
        }
        Session::flash('ok', t('Brouillon rédigé ({spent}). Relis-le, complète les « À compléter », puis publie-le.', ['spent' => t('{tokens} jetons · {cost}', [
            'tokens' => number_format((int) $res['tokens'], 0, ',', "\u{202F}"), 'cost' => Ai::money((float) $res['cost']),
        ])]));
        Http::json(['ok' => true, 'edit' => admin_url('edit/' . $res['item']['id'])]);
    }
}
