<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Fonction « Sauvegardes Google Drive », livrée avec Éclair. État dans data/drive.php (jamais sauvegardé ni restauré,
 * comme en 1.x) ; l'adresse de tâche planifiée /_eclair/backup/<clé> de la 1.x reste valable (App::run → cron()).
 */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Drive.php';
require_once __DIR__ . '/lib/AdminDrive.php';

use EclairExt\Drive\AdminDrive;
use EclairExt\Drive\Drive;

return new class {
    public function page(array $user, array $ctx): never
    {
        AdminDrive::page($user);
    }

    public function action(array $user, array $ctx): never
    {
        AdminDrive::action($user);
    }

    public function poll(array $user, array $ctx): never
    {
        AdminDrive::poll($user);
    }

    public function tick(array $user, array $ctx): never
    {
        AdminDrive::tick($user);
    }

    /** Envoi dû : l'administration le lance discrètement en arrière-plan. */
    public function due(): bool
    {
        return Drive::due();
    }

    /** Tâche planifiée (adresse secrète). */
    public function cron(string $key): never
    {
        Drive::cron($key);
    }
};
