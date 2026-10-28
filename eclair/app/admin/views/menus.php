<?php
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$labels = ['main' => t('Menu principal (en-tête)'), 'footer' => t('Menu du pied de page')];
?>
<div class="page-narrow">
  <div class="topbar"><div><h1><?= e(t('Menus')) ?></h1><p><?= e(t('Choisis les liens affichés dans l’en-tête et le pied de page. Glisse-dépose ou utilise les flèches pour réordonner.')) ?></p></div></div>
  <form method="post" action="<?= e(admin_url('menus')) ?>" id="menus-form">
    <?= Security::csrfField() ?>
    <input type="hidden" name="menus" id="menus-json">
    <?php foreach ($v['menus'] as $name => $items): ?>
      <section class="card" data-menu="<?= e($name) ?>">
        <div class="card-head"><h2><?= e($labels[$name]) ?></h2></div>
        <ul class="sortable" data-list>
          <?php foreach ($items as $item): ?>
            <li data-type="<?= e($item['type']) ?>" data-id="<?= e($item['id']) ?>" data-url="<?= e($item['url'] ?? '') ?>" draggable="true">
              <span class="grip" aria-hidden="true">⋮⋮</span>
              <span class="grow"><b><?= e($item['title']) ?></b> <span class="type">· <?= e($item['kind']) ?></span></span>
              <input type="text" data-label value="<?= e($item['label']) ?>" placeholder="<?= e(t('Libellé (facultatif)')) ?>" aria-label="<?= e(t('Libellé')) ?>">
              <button class="btn btn-sm btn-ghost btn-icon" type="button" data-up aria-label="<?= e(t('Monter')) ?>"><?= Icons::svg('up') ?></button>
              <button class="btn btn-sm btn-ghost btn-icon" type="button" data-down aria-label="<?= e(t('Descendre')) ?>"><?= Icons::svg('down') ?></button>
              <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="button" data-remove aria-label="<?= e(t('Retirer')) ?>"><?= Icons::svg('x') ?></button>
            </li>
          <?php endforeach ?>
        </ul>
        <div class="row" data-add-row>
          <select data-add-select aria-label="<?= e(t('Ajouter un élément')) ?>">
            <option value=""><?= e(t('+ Ajouter une page, un article, une catégorie…')) ?></option>
            <?php foreach ($v['options'] as $o): ?>
              <option value="<?= e($o['type'] . ':' . $o['id']) ?>" data-title="<?= e($o['label']) ?>" data-kind="<?= e($o['kind']) ?>"><?= e($o['label']) ?> (<?= e($o['kind']) ?><?= !empty($o['draft']) ? ', ' . e(t('brouillon')) : '' ?>)</option>
            <?php endforeach ?>
          </select>
          <button class="btn btn-sm" type="button" data-add-link><?= Icons::svg('link') ?><?= e(t('Lien personnalisé')) ?></button>
        </div>
      </section>
    <?php endforeach ?>
    <p><button class="btn btn-primary" type="submit"><?= e(t('Enregistrer les menus')) ?></button></p>
  </form>
</div>
<template id="menu-item">
  <li draggable="true"><span class="grip" aria-hidden="true">⋮⋮</span><span class="grow"><b></b> <span class="type"></span></span>
    <input type="text" data-label placeholder="<?= e(t('Libellé (facultatif)')) ?>" aria-label="<?= e(t('Libellé')) ?>">
    <button class="btn btn-sm btn-ghost btn-icon" type="button" data-up aria-label="<?= e(t('Monter')) ?>"><?= Icons::svg('up') ?></button>
    <button class="btn btn-sm btn-ghost btn-icon" type="button" data-down aria-label="<?= e(t('Descendre')) ?>"><?= Icons::svg('down') ?></button>
    <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="button" data-remove aria-label="<?= e(t('Retirer')) ?>"><?= Icons::svg('x') ?></button></li>
</template>
