<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Fonction « Ancien site » (Wayback Machine), livrée avec Éclair et installée seulement sur les sites qui l'ajoutent
 * (ou qui l'utilisaient avant la 2.0, par migration). Ses données restent dans data/wayback.php, comme en 1.x : un retour
 * à une version 1.x les retrouve telles quelles.
 */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Wayback.php';
require_once __DIR__ . '/lib/AdminWayback.php';

use EclairExt\Wayback\AdminWayback;

return new class {
    public function page(array $user, array $ctx): never
    {
        AdminWayback::page($user);
    }

    public function action(array $user, array $ctx): never
    {
        AdminWayback::action($user);
    }

    public function scan(array $user, array $ctx): never
    {
        AdminWayback::scan($user);
    }

    public function build(array $user, array $ctx): never
    {
        AdminWayback::build($user);
    }
};
