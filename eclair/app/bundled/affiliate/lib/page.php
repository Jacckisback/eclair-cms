<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Vue de la fonction « Affiliation » : liens, article rédigé par l'IA, mention publicitaire (voir Links.php ; marquage des pages : app/lib/Affiliate.php). */
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$host = static fn (string $url): string => (string) preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Affiliation')) ?></h1><p><?= e(t('Tes liens d’affiliation au même endroit. Sur chaque page qui en contient, Éclair affiche la mention publicitaire et signale ces liens à Google comme liens rémunérés.')) ?></p></div>
    <a class="btn" href="#aff-add"><?= Icons::svg('plus') ?><?= e(t('Ajouter un lien')) ?></a>
  </div>

  <?php if ($v['links'] && $v['ai']): ?>
    <section class="card aff-card" aria-labelledby="aff-write-title">
      <h2 id="aff-write-title"><?= Icons::svg('spark') ?><?= e(t('Écrire un article avec l’IA')) ?></h2>
      <p class="small muted"><?= e(t('Coche des liens dans la liste. L’IA n’écrit qu’à partir de leurs noms et de tes notes : elle n’invente ni prix, ni avis, ni résultat de test, et laisse « À compléter » là où ton avis compte. Le brouillon s’ouvre ensuite dans l’éditeur ; rien n’est publié.')) ?></p>
      <form class="aff-write" data-aff-write data-wait="<?= e(t('Rédaction en cours… (1 à 2 minutes)')) ?>">
        <label class="field"><span class="label"><?= e(t('Type d’article')) ?></span><select name="format">
          <option value="compare"><?= e(t('Comparatif (2 à 10 liens)')) ?></option>
          <option value="review"><?= e(t('Présentation d’un produit (1 lien)')) ?></option>
          <option value="guide"><?= e(t('Guide d’achat')) ?></option></select></label>
        <label class="field"><span class="label"><?= e(t('Mot-clé principal (facultatif)')) ?></span><input type="text" name="keyword" maxlength="120" placeholder="<?= e(t('Ex. : meilleure machine à pain')) ?>"></label>
        <div><button class="btn btn-primary" type="submit"><?= Icons::svg('spark') ?><?= e(t('Rédiger avec les liens cochés')) ?> <small>· ~<?= e($v['writeCost']) ?></small></button></div>
      </form>
      <p class="small muted"><?= e(t('Modèle : {model}. Dans le budget IA du mois.', ['model' => $v['model']])) ?></p>
    </section>
  <?php elseif ($v['links']): ?>
    <div class="notice"><span><?= Icons::svg('spark') ?></span><p><?= e(t('Avec l’assistant IA, Éclair tire de tes liens un comparatif, une présentation de produit ou un guide d’achat, en brouillon.')) ?><?php if ($v['admin']): ?> <a href="<?= e(admin_url('settings?tab=ai')) ?>"><?= e(t('Activer l’assistant IA')) ?></a><?php endif ?></p></div>
  <?php endif ?>

  <section class="card" aria-labelledby="aff-list-title">
    <div class="card-head"><h2 id="aff-list-title"><?= e(t('Tes liens ({n})', ['n' => count($v['links'])])) ?></h2></div>
    <?php if (!$v['links']): ?>
      <p class="muted"><?= e(t('Aucun lien pour l’instant : ajoute ton premier lien d’affiliation.')) ?></p>
    <?php else: ?>
      <ul class="aff-list">
        <?php foreach ($v['links'] as $l): ?>
          <li class="aff-item">
            <input type="checkbox" value="<?= e($l['id']) ?>" data-aff-pick aria-label="<?= e($l['name']) ?>">
            <div class="aff-what">
              <b><?= e($l['name']) ?></b><?= $l['program'] === 'amazon' ? ' <span class="badge brand">Amazon</span>' : '' ?>
              <a class="small mono aff-url" href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer sponsored nofollow"><?= e($host($l['url'])) ?></a>
              <?php if ($l['notes'] !== ''): ?><p class="small"><?= nl2br(e(mb_strimwidth($l['notes'], 0, 220, '…'))) ?></p>
              <?php else: ?><p class="small muted"><?= e(t('Pas de notes : l’IA ne dira rien d’autre que son nom.')) ?></p><?php endif ?>
            </div>
            <div class="plan-actions">
              <details class="plan-edit">
                <summary class="btn btn-sm btn-ghost"><?= e(t('Modifier')) ?></summary>
                <form method="post" action="<?= e(admin_url('x/affiliate')) ?>" class="plan-form"><?= Security::csrfField() ?>
                  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($l['id']) ?>">
                  <label class="field plan-wide"><span class="label"><?= e(t('Produit ou offre')) ?></span><input type="text" name="name" maxlength="120" required value="<?= e($l['name']) ?>"></label>
                  <label class="field plan-wide"><span class="label"><?= e(t('Lien d’affiliation')) ?></span><input type="url" name="url" required value="<?= e($l['url']) ?>"></label>
                  <label class="field plan-wide"><span class="label"><?= e(t('Notes pour l’IA')) ?></span><textarea name="notes" rows="4" maxlength="1500"><?= e($l['notes']) ?></textarea></label>
                  <div><button class="btn btn-sm btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button></div>
                </form>
              </details>
              <form method="post" action="<?= e(admin_url('x/affiliate')) ?>" data-confirm="<?= e(t('Retirer ce lien de la liste ? Les pages qui le contiennent encore n’afficheront plus la mention publicitaire, sauf pour un lien Amazon avec ton identifiant de partenaire.')) ?>"><?= Security::csrfField() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($l['id']) ?>">
                <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="submit" aria-label="<?= e(t('Retirer le lien')) ?>"><?= Icons::svg('x') ?></button></form>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <div class="grid-2">
    <section class="card" id="aff-add" aria-labelledby="aff-add-title">
      <h2 id="aff-add-title"><?= e(t('Ajouter un lien')) ?></h2>
      <form method="post" action="<?= e(admin_url('x/affiliate')) ?>" class="aff-form"><?= Security::csrfField() ?>
        <input type="hidden" name="action" value="add">
        <label class="field"><span class="label"><?= e(t('Produit ou offre')) ?></span><input type="text" name="name" maxlength="120" required placeholder="<?= e(t('Ex. : Machine à pain Moulinex Pain Doré')) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Lien d’affiliation')) ?></span><input type="url" name="url" required placeholder="https://"></label>
        <label class="field"><span class="label"><?= e(t('Notes pour l’IA')) ?></span><textarea name="notes" rows="4" maxlength="1500" placeholder="<?= e(t('Ce que tu sais du produit : à qui il s’adresse, points forts, limites, ton avis… L’IA n’écrit rien d’autre.')) ?>"></textarea></label>
        <div><button class="btn btn-primary" type="submit"><?= Icons::svg('plus') ?><?= e(t('Ajouter')) ?></button></div>
      </form>
    </section>

    <section class="card" aria-labelledby="aff-note-title">
      <h2 id="aff-note-title"><?= e(t('Mention publicitaire')) ?></h2>
      <p class="small muted"><?= e(t('Une publicité doit être clairement identifiable comme telle, en France (LCEN, art. 20) comme en Espagne (LSSI, art. 20). Éclair place cette mention en tête de chaque page qui contient un lien affilié, et dans le flux RSS.')) ?></p>
      <?php if ($v['admin']): ?>
        <form method="post" action="<?= e(admin_url('x/affiliate')) ?>" class="aff-form"><?= Security::csrfField() ?>
          <input type="hidden" name="action" value="disclosure">
          <label class="field"><span class="label"><?= e(t('Texte de la mention (vide : texte par défaut, dans la langue de chaque page)')) ?></span>
            <textarea name="disclosure" rows="3" maxlength="600" placeholder="<?= e($v['note']) ?>"><?= e($v['disclosure']) ?></textarea></label>
          <div><button class="btn btn-sm" type="submit"><?= e(t('Enregistrer')) ?></button></div>
        </form>
      <?php else: ?>
        <p class="small"><?= e($v['disclosure'] !== '' ? $v['disclosure'] : $v['note']) ?></p>
      <?php endif ?>
      <ul class="aff-rules small muted">
        <li><?= e(t('Liens Amazon : la phrase exigée par le Partenariat Amazon est ajoutée automatiquement, dans la langue de la boutique, par exemple : « {text} »', ['text' => $v['amazon']])) ?></li>
        <li><?= e(t('Chaque lien affilié est marqué rel="sponsored nofollow", comme Google le demande pour les liens rémunérés.')) ?></li>
        <li><?= e(t('Aucun lien masqué ni redirection : chaque lien mène directement au marchand.')) ?></li>
      </ul>
    </section>
  </div>
</div>
