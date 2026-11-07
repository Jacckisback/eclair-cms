<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$labels = ['active' => t('Active'), 'disabled' => t('Désactivée'), 'installed' => t('Installée, pas encore activée'), 'missing' => t('Code supprimé, données gardées')];
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Fonctions')) ?></h1><p><?= e(t('Ajoute seulement les fonctions utiles à ton site. Une fonction désactivée garde ses données et ses blocs : tu peux la réactiver à tout moment.')) ?></p></div></div>
  <?php if ($v['bundled']): ?>
  <section class="card">
    <h2><?= e(t('Fonctions livrées avec Éclair')) ?></h2>
    <p class="hint"><?= e(t('Maintenues avec Éclair et mises à jour avec lui, elles ne sont chargées que si tu les ajoutes.')) ?></p>
    <?php foreach ($v['bundled'] as $id => $b): ?>
      <div class="row">
        <div class="grow"><strong><?= e((string) $b['name']) ?></strong><br><span class="small muted"><?= e((string) $b['description']) ?></span></div>
        <form method="post" action="<?= e(admin_url('extensions')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= e($id) ?>">
          <button class="btn btn-sm btn-primary" type="submit"><?= Icons::svg('plus') ?><?= e(t('Ajouter')) ?></button></form>
      </div>
    <?php endforeach ?>
  </section>
  <?php endif ?>
  <?php if (!$v['items']): ?>
    <div class="card empty-state"><span class="ico"><?= Icons::svg('plus') ?></span><p><?= e(t('Aucune fonction ajoutée : ton site utilise le noyau d’Éclair, complet pour publier.')) ?></p></div>
  <?php endif ?>
  <?php foreach ($v['items'] as $id => $m): $imp = $v['impact'][$id] ?? null; ?>
  <section class="card">
    <h2><?= e((string) ($m['name'] ?? $id)) ?> <span class="badge"><?= e((string) ($m['version'] ?? '')) ?></span> <span class="badge<?= $m['status'] === 'active' ? ' brand' : '' ?>"><?= e($labels[$m['status']] ?? $m['status']) ?></span></h2>
    <?php if (!empty($m['description'])): ?><p class="hint"><?= e((string) $m['description']) ?></p><?php endif ?>
    <?php if ($m['errors']): ?><ul class="small"><?php foreach ($m['errors'] as $err): ?><li><?= e((string) $err) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if ($imp && ($imp['routes'] || $imp['contents'])): ?>
      <p class="small"><?= e(t('Utilisée par : {r} adresse(s) publique(s), {c} contenu(s).', ['r' => count($imp['routes']), 'c' => count($imp['contents'])])) ?></p>
    <?php endif ?>
    <div class="row">
    <?php if ($m['status'] !== 'active' && $m['status'] !== 'missing' && !$m['errors']): ?>
      <form method="post" action="<?= e(admin_url('extensions')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= e($id) ?>">
        <button class="btn btn-sm btn-primary" type="submit"><?= Icons::svg('check') ?><?= e(t('Activer')) ?></button></form>
    <?php endif ?>
    <?php if ($m['status'] === 'active'): ?>
      <form method="post" action="<?= e(admin_url('extensions')) ?>" data-confirm="<?= e(t('Désactiver cette fonction ? Ses données et ses blocs sont gardés.')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="confirm" value="1">
        <button class="btn btn-sm" type="submit"><?= e(t('Désactiver')) ?></button></form>
    <?php elseif ($v['canInstall'] && $m['status'] !== 'missing'): ?>
      <form method="post" action="<?= e(admin_url('extensions')) ?>" data-confirm="<?= e(t('Supprimer le code de cette fonction ? Ses données restent, pour une réinstallation.')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= e($id) ?>">
        <?= \Eclair\Admin::partial('partials/confirm', []) ?><button class="btn btn-sm btn-danger" type="submit"><?= Icons::svg('trash') ?><?= e(t('Supprimer le code')) ?></button></form>
    <?php elseif ($v['canInstall']): ?>
      <form method="post" action="<?= e(admin_url('extensions')) ?>" data-confirm="<?= e(t('Effacer définitivement les données de cette fonction ?')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="purge"><input type="hidden" name="id" value="<?= e($id) ?>">
        <?= \Eclair\Admin::partial('partials/confirm', []) ?><button class="btn btn-sm btn-danger" type="submit"><?= Icons::svg('trash') ?><?= e(t('Effacer les données')) ?></button></form>
    <?php endif ?>
    </div>
  </section>
  <?php endforeach ?>
  <?php if ($v['tasks'] !== ''): ?>
    <p class="hint"><?= e(t('Tâches planifiées des fonctions : demande à ton hébergeur d’appeler cette adresse chaque heure (cron) : {u}', ['u' => $v['tasks']])) ?></p>
  <?php endif ?>
  <?php if ($v['canInstall']): ?>
  <section class="card">
    <h2><?= e(t('Ajouter une fonction')) ?></h2>
    <p class="hint"><?= e(t('Une fonction contient du code qui s’exécute avec les droits de ton site : n’ajoute que des fonctions de confiance. Elle est vérifiée avant l’ajout et reste inactive tant que tu ne l’as pas activée.')) ?></p>
    <form method="post" action="<?= e(admin_url('extensions')) ?>" enctype="multipart/form-data" class="grid-3"><?= Security::csrfField() ?><input type="hidden" name="action" value="upload">
      <label class="field"><span class="label"><?= e(t('Fichier .zip de la fonction')) ?></span><input type="file" name="package" accept=".zip" required></label>
      <label class="field"><span class="label"><?= e(t('Ton mot de passe')) ?></span><input type="password" name="confirm_password" autocomplete="current-password"></label>
      <div><button class="btn btn-primary" type="submit"><?= Icons::svg('upload') ?><?= e(t('Importer')) ?></button></div>
    </form>
  </section>
  <?php endif ?>
</div>
