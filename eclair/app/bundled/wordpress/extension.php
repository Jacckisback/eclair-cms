<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Fonction « Import WordPress », livrée avec Éclair : reprise d'un site WordPress depuis son export WXR. */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Wxr.php';
require_once __DIR__ . '/lib/Importer.php';
require_once __DIR__ . '/lib/Package.php';
require_once __DIR__ . '/lib/PackageImporter.php';
require_once __DIR__ . '/lib/Report.php';
require_once __DIR__ . '/lib/Receiver.php';
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

    public function report(array $user, array $ctx): never
    {
        AdminWordpress::report($user);
    }

    /** Bilan du dernier import, lu par l'assistant et par Claude ou ChatGPT (outil get_migration). */
    public function migration(): ?array
    {
        return EclairExt\Wordpress\PackageImporter::brief();
    }

    /** Réception directe d'un paquet envoyé par l'extension WordPress (/_eclair/import, clé de liaison). */
    public function receive(): void
    {
        EclairExt\Wordpress\Receiver::handle();
    }
};
