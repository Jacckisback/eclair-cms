<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Sauvegardes Google Drive : connexion par code, réglages, sauvegarde à la demande, avancement en arrière-plan. */
final class AdminDrive
{
    private const BACK = 'security#drive';

    public static function action(array $user): never
    {
        switch (Req::input('action')) {
            case 'connect':
                // Les sauvegardes partiront vers ce compte : même exigence que pour télécharger une sauvegarde.
                if (!Auth::confirmed($user)) {
                    Admin::back(self::BACK, t('Mot de passe incorrect.'), 'bad');
                }
                $r = Drive::start();
                $r['ok'] ? Admin::back(self::BACK) : Admin::back(self::BACK, $r['error'], 'bad');
            case 'poll':
                $state = Drive::poll();
                [$message, $type] = self::polled($state);
                Admin::back(self::BACK, $message, $type);
            case 'cancel':
                Drive::cancel();
                Admin::back(self::BACK);
            case 'disconnect':
                if (!Auth::confirmed($user)) {
                    Admin::back(self::BACK, t('Mot de passe incorrect.'), 'bad');
                }
                Drive::disconnect();
                Admin::back(self::BACK, t('Google Drive déconnecté. Les sauvegardes déjà envoyées restent dans ton Drive.'));
            case 'save':
                $schedule = Req::input('schedule');
                Settings::save(['backup' => [
                    'schedule' => isset(Drive::SCHEDULES[$schedule]) ? $schedule : 'off',
                    'keep' => max(1, min(50, (int) Req::input('keep'))),
                    'chosen' => true,
                ]]);
                Admin::back(self::BACK, t('Réglages des sauvegardes enregistrés.'));
            case 'now':
                $r = Drive::run(20, true);
                match ($r['status']) {
                    'done' => Admin::back(self::BACK, t('Sauvegarde envoyée sur Google Drive ✅')),
                    'progress', 'busy' => Admin::back(self::BACK, t('Sauvegarde en cours d’envoi : garde cette page ouverte, elle continue toute seule.')),
                    default => Admin::back(self::BACK, (string) ($r['error'] ?? t('Sauvegarde impossible.')), 'bad'),
                };
        }
        Admin::back(self::BACK);
    }

    /** Attente de l'autorisation Google (appelé par la page toutes les quelques secondes). */
    public static function poll(array $user): never
    {
        $state = Drive::poll();
        if ($state !== 'pending') {
            // Message affiché par la page rechargée.
            [$message, $type] = self::polled($state);
            Session::flash($type, $message);
        }
        Http::json(['ok' => true, 'state' => $state]);
    }

    /** Fait avancer la sauvegarde en arrière-plan ($now : en lancer une ; sinon seulement si elle est due). */
    public static function tick(array $user): never
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        // La session n'est plus utile : les autres onglets ne l'attendent pas pendant l'envoi.
        session_write_close();
        $r = Drive::run(20, !empty($body['now']));
        Http::json(['ok' => $r['status'] !== 'error', 'status' => $r['status'], 'percent' => $r['percent'], 'error' => $r['error'] ?? '',
            'text' => $r['status'] === 'done' ? t('Sauvegarde envoyée sur Google Drive ✅') : ($r['status'] === 'progress' ? t('Envoi de la sauvegarde : {n} %', ['n' => $r['percent']]) : '')]);
    }

    private static function polled(string $state): array
    {
        return match ($state) {
            'connected' => [t('Google Drive connecté ✅ Choisis maintenant la fréquence des sauvegardes.'), 'ok'],
            'pending' => [t('Google attend encore ton autorisation : saisis le code sur la page Google, puis réessaie.'), 'warn'],
            'denied' => [t('Autorisation refusée sur Google : rien n’a été connecté.'), 'warn'],
            'expired' => [t('Le code a expiré : recommence la connexion.'), 'warn'],
            default => [t('Google ne répond pas pour l’instant : réessaie dans quelques minutes.'), 'bad'],
        };
    }
}
