<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Fonction « Import WordPress », livrée avec Éclair : reprise d'un site WordPress depuis son export WXR. */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Wxr.php';
require_once __DIR__ . '/lib/Importer.php';
require_once __DIR__ . '/lib/Package.php';
require_once __DIR__ . '/lib/PackageImporter.php';
require_once __DIR__ . '/lib/AdminWordpress.php';

use EclairExt\Wordpress\AdminWordpress;

return new class {
    public function page(array $user, array $ctx): never
    {
        AdminWordpress::page($user);
    }

    public function action(array $user, array $ctx): never
    {
        AdminWordpress::action($user);
    }

    public function step(array $user, array $ctx): never
    {
        AdminWordpress::step($user);
    }
};
