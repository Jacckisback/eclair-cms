<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Fonction « Planning éditorial », livrée avec Éclair. Données dans data/plan.php comme en 1.x (un retour à la 1.x les
 * retrouve) ; l'adresse de tâche planifiée /_eclair/plan/<clé> de la 1.x reste valable (App::run → cron()). Les autres
 * fonctions livrées (Discover) y ajoutent des idées par put(), all() et full().
 */
defined('ECLAIR') || exit;

require_once __DIR__ . '/lib/Plan.php';
require_once __DIR__ . '/lib/AdminPlan.php';

use EclairExt\Planning\AdminPlan;
use EclairExt\Planning\Plan;

return new class {
    public function page(array $user, array $ctx): never
    {
        AdminPlan::page($user);
    }

    public function action(array $user, array $ctx): never
    {
        AdminPlan::action($user);
    }

    public function ideas(array $user, array $ctx): never
    {
        AdminPlan::ideas($user);
    }

    public function write(array $user, array $ctx): never
    {
        AdminPlan::write($user);
    }

    public function tick(array $user, array $ctx): never
    {
        AdminPlan::tick($user);
    }

    /** Une idée est à rédiger (mode automatique) : l'administration lance discrètement tick. */
    public function due(): bool
    {
        return Plan::due() !== null;
    }

    /** Tâche planifiée (adresse secrète de la 1.x). */
    public function cron(string $key): never
    {
        Plan::cron($key);
    }

    /** Idées et brouillons à venir (tuile du tableau de bord). */
    public function upcoming(): int
    {
        return count(array_filter(Plan::all(), fn (array $i): bool => in_array(Plan::state($i), ['idea', 'draft'], true)));
    }

    /** Pour Discover : idées existantes, ajout d'une idée, planning plein. */
    public function all(): array
    {
        return Plan::all();
    }

    public function put(array $in): array|string
    {
        return Plan::put($in);
    }

    public function full(): bool
    {
        return count(Plan::all()) >= Plan::MAX;
    }
};
