<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Administration › Fonctions : capacités optionnelles installées dans site/extensions/. Activer ou désactiver demande le
 * droit « settings » ; importer, supprimer le code ou effacer les données demande en plus « security » et le mot de passe.
 */
final class AdminExtensions
{
    public static function page(array $user): never
    {
        $items = Extensions::installed();
        $impact = [];
        foreach ($items as $id => $m) {
            if ($m['status'] === 'active') {
                $impact[$id] = Extensions::impact($id);
            }
        }
        Admin::view('extensions', ['items' => $items, 'impact' => $impact, 'canInstall' => Auth::can('security'), 'tasks' => Extensions::taskUrl()], t('Fonctions'));
    }

    public static function action(array $user): never
    {
        $id = Req::input('id');
        $sensitive = in_array(Req::input('action'), ['upload', 'remove', 'purge'], true);
        if ($sensitive && !Auth::can('security')) {
            Admin::forbidden();
        }
        if ($sensitive && !Auth::confirmed($user)) {
            Admin::back('extensions', t('Mot de passe incorrect.'), 'bad');
        }
        $r = match (Req::input('action')) {
            'activate' => Extensions::activate($id),
            'deactivate' => Extensions::deactivate($id, Req::input('confirm') === '1'),
            'remove' => Extensions::remove($id),
            'purge' => Extensions::purge($id),
            'upload' => self::upload(),
            default => ['ok' => false, 'error' => t('Action inconnue.')],
        };
        Admin::back('extensions', $r['ok'] ? t('C’est fait.') : (string) $r['error'], $r['ok'] ? 'ok' : (!empty($r['confirm']) ? 'warn' : 'bad'));
    }

    /** Import d'une capacité (.zip) : décompressée par Themes::installZip dans un dossier de travail, puis validée et installée. */
    private static function upload(): array
    {
        $tmp = (string) ($_FILES['package']['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && !(defined('ECLAIR_TESTING') && is_file($tmp)))) {
            return ['ok' => false, 'error' => t('Choisis d’abord le fichier .zip de la fonction.')];
        }
        return Packages::installZip($tmp, 'extension');
    }

    /** Découverte structurée (versions, contrats, blocs, capacités, thèmes, droits) : JSON, sans secret. */
    public static function describe(array $user): never
    {
        Http::json(['ok' => true] + Extensions::describe());
    }
}
