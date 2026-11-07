<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Fonction « Robots IA », livrée avec Éclair. Le journal reste tenu par index.php (chemin rapide, pages en cache comprises)
 * à partir des réglages que cette fonction fournit à cache/runtime.php ; sans elle, rien n'est noté. Journal dans data/ai/,
 * comme en 1.x (un retour à la 1.x le retrouve).
 */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/AiVisits.php';

use Eclair\Admin;
use Eclair\Req;
use Eclair\Settings;
use EclairExt\AiVisits\AiVisits;

return new class {
    /** Page de l'administration : visites des 7, 30 ou 90 derniers jours. */
    public function page(array $user, array $ctx): never
    {
        $days = in_array((int) Req::query('days'), [7, 30, 90], true) ? (int) Req::query('days') : 30;
        $report = AiVisits::report($days);
        $v = ['days' => $days, 'report' => $report, 'unread' => $report['total'] ? AiVisits::unread($report) : [],
            'on' => (bool) Settings::get('geo.ai_log'), 'policy' => (string) Settings::get('geo.ai')];
        Admin::extensionPage('ai-visits', __DIR__ . '/lib/page.php', $v, t('Robots IA'));
    }

    /** Rapport des $days derniers jours (tableau de bord, planning éditorial). */
    public function report(int $days): array
    {
        return AiVisits::report($days);
    }

    /** Réglages du journal tenu par index.php (cache/runtime.php) : dossier et expression des robots connus. */
    public function runtime(): array
    {
        return ['ai' => AiVisits::dir(), 'ai_re' => AiVisits::pattern()];
    }
};
