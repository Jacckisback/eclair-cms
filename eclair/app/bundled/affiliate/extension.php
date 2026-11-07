<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Fonction « Affiliation », livrée avec Éclair : gestion des liens et articles rédigés par l'IA. Le marquage des pages
 * (rel="sponsored nofollow", mention publicitaire, phrase du Partenariat Amazon) reste dans le noyau (Eclair\Affiliate) :
 * retirer la fonction ne retire jamais une mention légalement exigée d'une page publiée.
 */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Links.php';
require_once __DIR__ . '/lib/AdminAffiliate.php';

use EclairExt\Affiliate\AdminAffiliate;

return new class {
    public function page(array $user, array $ctx): never
    {
        AdminAffiliate::page($user);
    }

    public function action(array $user, array $ctx): never
    {
        AdminAffiliate::action($user);
    }

    public function write(array $user, array $ctx): never
    {
        AdminAffiliate::write($user);
    }
};
