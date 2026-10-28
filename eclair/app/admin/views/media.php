<?php
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Media;

?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Médias')) ?></h1><p><?= e(t('Tes images sont optimisées automatiquement : redimensionnées, converties en WebP et débarrassées des données EXIF.')) ?></p></div>
  </div>
  <?php if (!Media::gd()): ?>
    <div class="notice warn"><span>⚠️</span><p><?= e(t('L’extension GD est absente sur ton hébergement : les images sont gardées telles quelles. Pense à les compresser avant envoi.')) ?></p></div>
  <?php endif ?>
  <label class="dropzone" id="media-drop">
    <span class="emoji">🖼️</span>
    <b><?= e(t('Glisse tes images ici ou clique pour choisir')) ?></b>
    <span class="small muted"><?= e(t('JPG, PNG, WebP ou GIF · 20 Mo maximum · max. {max} par envoi sur ton hébergement', ['max' => ini_get('upload_max_filesize')])) ?></span>
    <input type="file" id="media-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
  </label>
  <div class="row between">
    <p class="small muted" id="media-count"></p>
    <input type="search" id="media-search" placeholder="<?= e(t('Rechercher une image…')) ?>" aria-label="<?= e(t('Rechercher une image…')) ?>">
  </div>
  <div class="media-grid" id="media-grid"></div>
</div>
<dialog id="media-detail">
  <div class="dialog-head"><h2 id="md-name"></h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="<?= e(t('Fermer')) ?>"><?= Icons::svg('x') ?></button></div>
  <div class="dialog-body media-detail">
    <img id="md-img" src="" alt="">
    <div>
      <label class="field"><span class="label"><?= e(t('Texte alternatif')) ?></span>
        <textarea id="md-alt" rows="3"></textarea>
        <span class="hint"><?= e(t('Décris l’image pour les personnes malvoyantes et pour Google Images. Ex. : « Équipe en réunion devant un tableau blanc ».')) ?></span></label>
      <p class="small muted" id="md-info"></p>
      <label class="field"><span class="label"><?= e(t('Adresse de l’image')) ?></span>
        <span class="input-group"><input type="text" id="md-url" readonly><button class="btn" type="button" id="md-copy"><?= Icons::svg('copy') ?></button></span></label>
      <div class="row">
        <button class="btn btn-primary" type="button" id="md-save"><?= e(t('Enregistrer')) ?></button>
        <button class="btn btn-danger" type="button" id="md-delete"><?= Icons::svg('trash') ?><?= e(t('Supprimer')) ?></button>
      </div>
    </div>
  </div>
</dialog>
