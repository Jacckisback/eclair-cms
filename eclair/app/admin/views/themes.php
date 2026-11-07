<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$preview = static fn (string $id): string => admin_url('appearance/preview') . '?site_theme=' . rawurlencode($id === '' ? '-' : $id) . '&path=/';
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Thèmes')) ?></h1><p><?= e(t('Le thème de ton site lui appartient : il est rangé dans le dossier site/ et les mises à jour d’Éclair n’y touchent jamais.')) ?></p></div></div>
  <?php if ($v['safe']): ?>
    <div class="notice warn"><?= Icons::svg('alert') ?><p><?= e(t('Mode de secours actif (ECLAIR_SAFE_THEME dans eclair-config.php) : le site s’affiche avec le thème de base. Retire cette ligne quand le problème est réglé.')) ?></p></div>
  <?php endif ?>
  <?php if ($v['broken']): ?>
    <div class="notice bad"><?= Icons::svg('alert') ?><p><?= e(t('Le thème « {n} » a provoqué une erreur ({f}) : les pages concernées s’affichent avec le gabarit de base. Détail dans Sécurité › Dernières erreurs techniques.', ['n' => (string) $v['broken']['id'], 'f' => (string) $v['broken']['template']])) ?></p></div>
  <?php endif ?>
  <?php if (!empty($v['report']['templates']) || !empty($v['report']['styles']) || !empty($v['report']['assets'])): ?>
    <div class="notice"><?= Icons::svg('info') ?><p><?= e(t('Tes personnalisations de l’ancienne version ({n} fichiers) ont été rangées dans le thème « legacy » pour que ton site garde son apparence.', ['n' => count($v['report']['templates']) + count($v['report']['styles']) + count($v['report']['assets'])])) ?>
      <?php if (!empty($v['report']['ignored'])): ?><br><?= e(t('À vérifier : {f}', ['f' => implode(', ', array_slice((array) $v['report']['ignored'], 0, 6))])) ?><?php endif ?></p></div>
  <?php endif ?>

  <section class="card">
    <h2><?= e(t('Thème de base d’Éclair')) ?><?= $v['active'] === '' ? ' <span class="badge brand">' . e(t('Actif')) . '</span>' : '' ?></h2>
    <p class="hint"><?= e(t('Toujours disponible, mis à jour avec Éclair. Ses couleurs et polices se règlent dans Réglages › Apparence.')) ?></p>
    <p><a class="btn btn-sm" href="<?= e($preview('')) ?>" target="_blank" rel="noopener"><?= Icons::svg('eye') ?><?= e(t('Aperçu')) ?></a>
    <?php if ($v['active'] !== ''): ?>
      <form method="post" action="<?= e(admin_url('themes')) ?>" style="display:inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="deactivate"><button class="btn btn-sm" type="submit"><?= e(t('Revenir au thème de base')) ?></button></form>
    <?php endif ?></p>
  </section>

  <?php foreach ($v['themes'] as $id => $theme): ?>
  <section class="card">
    <h2><?= e((string) $theme['name']) ?> <span class="badge"><?= e((string) $theme['version']) ?></span><?= $v['active'] === $id ? ' <span class="badge brand">' . e(t('Actif')) . '</span>' : '' ?></h2>
    <?php if (!empty($theme['description'])): ?><p class="hint"><?= e((string) $theme['description']) ?></p><?php endif ?>
    <?php if ($theme['errors']): ?>
      <ul class="small"><?php foreach ($theme['errors'] as $err): ?><li><?= e((string) $err) ?></li><?php endforeach ?></ul>
    <?php else: ?>
      <p class="small"><?= e(t('Gabarits remplacés : {n}', ['n' => $theme['templates'] ? implode(', ', $theme['templates']) : t('aucun')])) ?></p>
    <?php endif ?>
    <p><a class="btn btn-sm" href="<?= e($preview($id)) ?>" target="_blank" rel="noopener"><?= Icons::svg('eye') ?><?= e(t('Aperçu avec tes contenus')) ?></a>
    <?php if ($v['active'] !== $id && !$theme['errors']): ?>
      <form method="post" action="<?= e(admin_url('themes')) ?>" style="display:inline"><?= Security::csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn-sm btn-primary" type="submit"><?= Icons::svg('check') ?><?= e(t('Activer')) ?></button></form>
    <?php endif ?></p>
  </section>
  <?php endforeach ?>

  <?php if ($v['previous'] !== null): ?>
  <section class="card">
    <h2><?= e(t('Revenir en arrière')) ?></h2>
    <p class="hint"><?= e(t('Thème précédent : {n}.', ['n' => ($v['previous']['id'] ?? '') === '' ? t('thème de base') : (string) $v['previous']['id'] . ' ' . (string) ($v['previous']['version'] ?? '')])) ?></p>
    <form method="post" action="<?= e(admin_url('themes')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="revert"><button class="btn btn-sm" type="submit"><?= Icons::svg('undo') ?><?= e(t('Rétablir le thème précédent')) ?></button></form>
  </section>
  <?php endif ?>

  <?php if ($v['canInstall']): ?>
  <section class="card">
    <h2><?= e(t('Importer un thème')) ?></h2>
    <p class="hint"><?= e(t('Un thème contient du code qui s’exécute avec les droits de ton site : n’importe que des thèmes de confiance. Il est vérifié avant l’import, mais n’est pas activé tant que tu ne l’as pas essayé.')) ?></p>
    <form method="post" action="<?= e(admin_url('themes')) ?>" enctype="multipart/form-data" class="grid-3"><?= Security::csrfField() ?><input type="hidden" name="action" value="upload">
      <label class="field"><span class="label"><?= e(t('Fichier .zip du thème')) ?></span><input type="file" name="package" accept=".zip" required></label>
      <label class="field"><span class="label"><?= e(t('Ton mot de passe')) ?></span><input type="password" name="confirm_password" autocomplete="current-password"></label>
      <div><button class="btn btn-primary" type="submit"><?= Icons::svg('upload') ?><?= e(t('Importer')) ?></button></div>
    </form>
  </section>
  <?php endif ?>
</div>
