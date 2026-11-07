<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Vue de la fonction « Import WordPress » : envoi de l'export, avancement, bilan (voir Importer.php). */
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$job = $v['job'];
$r = $job['report'] ?? [];
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Import WordPress')) ?></h1><p><?= e(t('Reprends les articles, pages, catégories et images d’un site WordPress, en gardant leurs adresses : les liens et le référencement de l’ancien site continuent de mener aux mêmes pages.')) ?></p></div>
  </div>

  <?php if ($job === null || $job['done']): ?>
  <section class="card" aria-labelledby="wp-up">
    <h2 id="wp-up"><?= Icons::svg('upload') ?><?= e(t('Envoyer l’export')) ?></h2>
    <p class="small muted"><?= e(t('Dans WordPress : Outils › Exporter › Tout le contenu › Télécharger le fichier d’export. Envoie ici le fichier .xml obtenu.')) ?></p>
    <form method="post" action="<?= e(admin_url('x/wordpress')) ?>" enctype="multipart/form-data"><?= Security::csrfField() ?><input type="hidden" name="action" value="upload">
      <label class="field"><span class="label"><?= e(t('Fichier d’export (.xml, {n} au plus)', ['n' => $v['limit']])) ?></span><input type="file" name="wxr" accept=".xml,text/xml,application/xml" required></label>
      <label class="check"><input type="checkbox" name="keep" value="1" checked><span><?= e(t('Publier ce qui était publié (sinon, tout arrive en brouillon)')) ?></span></label>
      <label class="check"><input type="checkbox" name="images" value="1" checked><span><?= e(t('Copier les images de l’ancien site dans la médiathèque (il doit être encore en ligne)')) ?></span></label>
      <div><button class="btn btn-primary" type="submit"><?= Icons::svg('upload') ?><?= e(t('Lire l’export')) ?></button></div>
    </form>
  </section>
  <?php endif ?>

  <?php if ($job !== null && !$job['done']): ?>
  <section class="card" aria-labelledby="wp-run" data-wp-run>
    <h2 id="wp-run"><?= Icons::svg('refresh') ?><?= e(t('Import de « {site} »', ['site' => $job['title'] !== '' ? $job['title'] : $job['site']])) ?></h2>
    <p><progress max="<?= (int) $job['total'] ?>" value="<?= (int) $job['next'] ?>"></progress> <span data-wp-label><?= e(t('{n} contenus sur {total}', ['n' => $job['next'], 'total' => $job['total']])) ?></span></p>
    <div class="row">
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="step">
        <button class="btn btn-primary" type="submit" data-wp-step data-wait="<?= e(t('Import en cours…')) ?>"><?= e($job['next'] ? t('Continuer l’import') : t('Lancer l’import')) ?></button></form>
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="cancel">
        <button class="btn btn-ghost" type="submit"><?= e(t('Abandonner')) ?></button></form>
    </div>
    <p class="small muted"><?= e(t('L’import avance par lots de quelques secondes ; tu peux fermer la page et le reprendre plus tard. Un contenu déjà importé n’est jamais créé deux fois.')) ?></p>
  </section>
  <?php endif ?>

  <?php if ($job !== null && $job['done']): ?>
  <section class="card" aria-labelledby="wp-done">
    <h2 id="wp-done"><?= Icons::svg('circle-check') ?><?= e(t('Dernier import')) ?></h2>
    <ul class="health">
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{posts} articles et {pages} pages importés ({skipped} déjà présents)', $r)) ?></span></li>
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{routes} adresses reprises à l’identique, {aliases} anciennes adresses à paramètres (?p=) redirigées', $r)) ?></span></li>
      <li class="<?= $r['missed'] ? 'warn' : 'ok' ?>"><?= Icons::svg($r['missed'] ? 'alert' : 'circle-check') ?><span><?= e(t('{images} images copiées dans la médiathèque, {missed} laissées en lien vers leur site', $r)) ?></span></li>
      <?php if ($r['shortcodes'] || $r['dropped']): ?>
        <li class="warn"><?= Icons::svg('alert') ?><span><?= e(t('À relire : {shortcodes} codes courts WordPress ([…]) restés en texte, {dropped} éléments non repris (scripts, formulaires, intégrations)', $r)) ?></span></li>
      <?php endif ?>
      <?php foreach ($r['errors'] as $err): ?>
        <li class="warn"><?= Icons::svg('alert') ?><span><?= e($err) ?></span></li>
      <?php endforeach ?>
    </ul>
    <p class="small muted"><?= e(t('Vérifie quelques pages, puis fais pointer ton domaine vers ce site. Les commentaires, extensions et réglages de WordPress ne sont pas repris.')) ?> <a href="<?= e(admin_url('content')) ?>"><?= e(t('Voir les contenus')) ?></a></p>
  </section>
  <?php endif ?>
</div>
