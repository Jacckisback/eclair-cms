<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Enregistrement impossible pour une raison du serveur (verrou des données inutilisable) : son message, traduit et sans
 * détail interne, dit quoi faire. App::onException le montre tel quel au lieu de l'erreur générique.
 */
final class StorageException extends \RuntimeException
{
}
