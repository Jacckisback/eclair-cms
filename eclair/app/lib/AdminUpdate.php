<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Mises à jour : nouvelle version annoncée, installation en un clic (au choix de l'administrateur), retour en arrière. */
final class AdminUpdate
{
    public static function page(array $user): never
    {
        Update::latest();
        $pending = Update::pending();
        Admin::view('update', [
            'state' => Update::state(),
            'pending' => $pending,
            'blockers' => $pending !== null ? Update::blockers($pending) : [],
            'modified' => $pending !== null ? Update::modified() : [],
            'previous' => Update::previous(),
            'zip' => class_exists('ZipArchive'),
        ], t('Mises à jour'));
    }

    public static function action(array $user): never
    {
        switch (Req::input('action')) {
            case 'check':
                Update::latest(true);
                $error = (string) (Update::state()['error'] ?? '');
                if ($error !== '') {
                    Admin::back('update', t('Vérification impossible : {e}. Nouvel essai automatique dans une heure.', ['e' => self::reason($error)]), 'warn');
                }
                $pending = Update::pending();
                Admin::back('update', $pending !== null ? t('Nouvelle version disponible : Éclair {v}.', ['v' => $pending['version']]) : t('Éclair est à jour (version {v}).', ['v' => ECLAIR_VERSION]));
            case 'install':
                $pending = Update::pending();
                if ($pending === null) {
                    Admin::back('update', t('Aucune nouvelle version à installer.'), 'warn');
                }
                if (!Req::bool('saved')) {
                    Admin::back('update', t('Coche d’abord « J’ai une sauvegarde récente de mon site ».'), 'bad');
                }
                if (!Auth::confirmed($user)) {
                    Admin::back('update', t('Mot de passe incorrect.'), 'bad');
                }
                $result = Update::install($pending);
                // Le code vient peut-être de changer : aucune vue n'est affichée par cette requête, la suivante utilise le nouveau code.
                if (!$result['ok']) {
                    Admin::back('update', $result['error'], 'bad');
                }
                if ($result['kept']) {
                    Session::flash('warn', t('Fichiers personnalisés conservés tels quels : {f}.', ['f' => implode(', ', $result['kept'])]));
                }
                if (!$result['checked']) {
                    Session::flash('warn', t('Ton hébergeur empêche Éclair de contrôler le site lui-même : ouvre-le pour vérifier que tout va bien. En cas de souci, reviens à la version précédente ci-dessous.'));
                }
                Admin::back('update', t('Éclair est passé à la version {v} ✅ Tes contenus, images et réglages n’ont pas bougé.', ['v' => $result['version']]));
            case 'rollback':
                if (!Auth::confirmed($user)) {
                    Admin::back('update', t('Mot de passe incorrect.'), 'bad');
                }
                $result = Update::rollback();
                $result['ok']
                    ? Admin::back('update', t('Retour à la version {v} effectué. Tes contenus n’ont pas bougé.', ['v' => $result['version']]))
                    : Admin::back('update', $result['error'], 'bad');
        }
        Admin::back('update');
    }

    /** Vérification en arrière-plan depuis l'administration : au plus une lecture de l'annonce toutes les 12 h. */
    public static function check(array $user): never
    {
        if (Update::stale()) {
            Update::latest(true);
        }
        $pending = Update::pending();
        Http::json(['ok' => true, 'available' => $pending !== null, 'version' => $pending['version'] ?? '',
            'text' => $pending !== null ? t('Éclair {v} est disponible (tu as la {current}).', ['v' => $pending['version'], 'current' => ECLAIR_VERSION]) : '']);
    }

    public static function reason(string $error): string
    {
        return match ($error) {
            'signature' => t('annonce refusée, sa signature ne correspond pas à celle du mainteneur'),
            'format' => t('annonce illisible'),
            'sodium' => t('extension PHP sodium absente'),
            default => t('GitHub injoignable depuis ton serveur'),
        };
    }
}
