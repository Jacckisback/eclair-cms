<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Administration › Thèmes : thèmes propres au site (site/themes/), aperçu avec les vrais contenus, activation, retour à la
 * version précédente, désactivation. Activer un thème installé demande le droit « settings » ; importer un paquet (du code
 * qui s'exécutera avec les droits du site) demande en plus le droit « security » et la confirmation du mot de passe.
 */
final class AdminThemes
{
    public static function page(array $user): never
    {
        $state = Themes::state();
        Admin::view('themes', [
            'themes' => Themes::installed(),
            'active' => (string) ($state['active']['id'] ?? ''),
            'previous' => is_array($state['previous'] ?? null) ? $state['previous'] : null,
            'broken' => is_array($state['broken'] ?? null) ? $state['broken'] : null,
            'safe' => defined('ECLAIR_SAFE_THEME') && ECLAIR_SAFE_THEME,
            'report' => (array) (Migrations::state()['report'] ?? []),
            'canInstall' => Auth::can('security'),
        ], t('Thèmes'));
    }

    public static function action(array $user): never
    {
        $id = Req::input('id');
        switch (Req::input('action')) {
            case 'activate':
                $r = Themes::activate($id);
                Admin::back('themes', $r['ok'] ? t('Thème « {n} » activé. Ton site l’utilise dès maintenant.', ['n' => $id]) : (string) $r['error'], $r['ok'] ? 'ok' : 'bad');
            case 'deactivate':
                Themes::deactivate();
                Admin::back('themes', t('Thème de base d’Éclair rétabli. Les fichiers de ton thème restent en place.'));
            case 'revert':
                $r = Themes::revert();
                Admin::back('themes', $r['ok'] ? t('Thème précédent rétabli.') : (string) $r['error'], $r['ok'] ? 'ok' : 'bad');
            case 'upload':
                if (!Auth::can('security')) {
                    Admin::forbidden();
                }
                if (!Auth::confirmed($user)) {
                    Admin::back('themes', t('Mot de passe incorrect.'), 'bad');
                }
                $tmp = (string) ($_FILES['package']['tmp_name'] ?? '');
                if ($tmp === '' || (!is_uploaded_file($tmp) && !(defined('ECLAIR_TESTING') && is_file($tmp)))) {
                    Admin::back('themes', t('Choisis d’abord le fichier .zip du thème.'), 'bad');
                }
                $r = Themes::installZip($tmp);
                Admin::back('themes', $r['ok'] ? t('Thème « {n} » {v} installé. Essaie-le avec l’aperçu, puis active-le.', ['n' => $r['id'], 'v' => $r['version']]) : (string) $r['error'], $r['ok'] ? 'ok' : 'bad');
        }
        Admin::back('themes');
    }
}
