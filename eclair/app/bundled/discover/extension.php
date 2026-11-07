<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Fonction « Google Discover », livrée avec Éclair : contrôles « prêt pour Discover » et tendances du jour (Google Trends).
 * Données comme en 1.x (data/trends.php, réglage discover.geo) : un retour à la 1.x les retrouve. La publicité AdSense,
 * qui était sur la même page, reste dans le noyau (Publicité).
 */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Discover.php';
require_once __DIR__ . '/lib/Trends.php';
require_once __DIR__ . '/lib/AdminDiscover.php';

use EclairExt\Discover\AdminDiscover;
use EclairExt\Discover\Discover;

return new class {
    public function page(array $user, array $ctx): never
    {
        AdminDiscover::page($user);
    }

    public function action(array $user, array $ctx): never
    {
        AdminDiscover::action($user);
    }

    public function trends(array $user, array $ctx): never
    {
        AdminDiscover::trends($user);
    }

    public function ideas(array $user, array $ctx): never
    {
        AdminDiscover::ideas($user);
    }

    /** Contrôles des derniers articles (tableau de bord : tuile et premier pas d'un média). */
    public function posts(): array
    {
        return Discover::posts();
    }
};
