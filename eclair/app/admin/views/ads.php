<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Publicité Google AdSense sur les articles (voir app/lib/Ads.php). Page du noyau jusqu'à l'extraction de la publicité en fonction. */
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$ads = $v['ads'];
$switch = static fn (string $name, bool $on, string $title, string $hint = ''): string => '<label class="switch"><span><strong>' . e($title) . '</strong>'
    . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</span><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '></label>';
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Publicité')) ?></h1></div>
  </div>

  <section class="card" id="ads" aria-labelledby="dc-ads-title">
    <div class="card-head">
      <h2 id="dc-ads-title"><?= Icons::svg('target') ?><?= e(t('Publicité (Google AdSense)')) ?></h2>
      <span class="badge <?= $v['adsActive'] ? 'ok' : '' ?>"><?= e($v['adsActive'] ? t('Active sur les articles') : t('Inactive')) ?></span>
    </div>
    <p class="small muted"><?= e(t('Des annonces Google sur tes articles, jamais sur les autres pages ni dans l’aperçu. Tant que la publicité n’est pas activée, aucune annonce ni aucun script de Google n’est chargé.')) ?></p>
    <?php if ($v['admin']): ?>
      <form method="post" action="<?= e(admin_url('ads')) ?>" class="dc-ads"><?= Security::csrfField() ?><input type="hidden" name="action" value="ads">
        <label class="field"><span class="label"><?= e(t('Identifiant d’éditeur')) ?></span>
          <input type="text" name="client" maxlength="40" placeholder="pub-1234567890123456" value="<?= e($v['adsClient'] !== '' ? $v['adsClient'] : (string) $ads['client']) ?>" autocomplete="off" spellcheck="false">
          <span class="hint"><?= e(t('Dans AdSense › Compte › Informations sur le compte. Éclair publie aussitôt /ads.txt et la balise qui permet à Google de valider ton site.')) ?></span></label>
        <?= $switch('consent', (bool) $ads['consent'], t('Message de consentement publié dans AdSense'), t('AdSense › Confidentialité et messages › Réglementations européennes : crée le message et publie-le. Google l’exige pour les visiteurs de l’Espace économique européen, du Royaume-Uni et de Suisse ; il s’affiche grâce au code AdSense, sans rien d’autre à installer.')) ?>
        <?= $switch('auto', (bool) $ads['auto'], t('Annonces automatiques'), t('Google choisit les emplacements et les formats ; ils se règlent dans AdSense › Annonces.')) ?>
        <label class="field"><span class="label"><?= e(t('Bloc d’annonces placé par Éclair (facultatif)')) ?></span>
          <input type="text" name="slot" maxlength="20" inputmode="numeric" placeholder="1234567890" value="<?= e((string) $ads['slot']) ?>" autocomplete="off">
          <span class="hint"><?= e(t('Crée dans AdSense un bloc « Annonces display » adaptatif et colle son numéro (data-ad-slot). Éclair le place entre deux blocs du texte, avec l’étiquette « {label} » exigée par Google et sa place réservée.', ['label' => \Eclair\Theme::t('ad_label')])) ?></span></label>
        <fieldset class="field dc-positions"><legend class="label"><?= e(t('Emplacements du bloc')) ?></legend>
          <?php foreach (['intro' => t('Après le 2e paragraphe'), 'middle' => t('Au milieu des articles longs'), 'end' => t('À la fin de l’article')] as $key => $label): ?>
            <label class="check"><input type="checkbox" name="positions[]" value="<?= $key ?>"<?= in_array($key, (array) $ads['positions'], true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach ?>
          <span class="hint"><?= e(t('Jamais dans un paragraphe, une liste ou un encadré ; au moins 3 paragraphes entre deux blocs ; rien dans un article de moins de 3 paragraphes.')) ?></span>
        </fieldset>
        <?= $switch('enabled', (bool) $ads['enabled'], t('Afficher la publicité sur les articles'), t('Chaque article peut s’en passer : case « Sans publicité sur cet article » dans l’éditeur.')) ?>
        <label class="field"><span class="label"><?= e(t('Lignes à ajouter à ads.txt (autres régies, facultatif)')) ?></span>
          <textarea name="txt" rows="3" maxlength="15000" spellcheck="false" placeholder="example.com, 12345, DIRECT"><?= e((string) $ads['txt']) ?></textarea></label>
        <div><button class="btn btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button></div>
      </form>
    <?php endif ?>
    <ul class="aff-rules small muted">
      <?php if ($v['adsClient'] !== ''): ?>
        <li><?= e(t('Fichier ads.txt :')) ?> <a href="<?= e(path_to('/ads.txt')) ?>" target="_blank" rel="noopener"><?= e(absolute_url('/ads.txt')) ?></a></li>
      <?php endif ?>
      <?php if ($v['adsActive'] && $v['testUrl'] !== ''): ?>
        <li><?= e(t('Vérifier le message de consentement :')) ?> <a href="<?= e($v['testUrl']) ?>" target="_blank" rel="noopener"><?= e(t('ouvrir un article avec le message affiché')) ?></a> <?= e(t('(en navigation privée, comme Google le conseille)')) ?></li>
      <?php endif ?>
      <li><?= e(t('Ne clique jamais sur tes propres annonces, même pour vérifier : Google l’interdit et peut suspendre le compte.')) ?></li>
      <li><?= e(t('Les scripts de Google ralentissent les pages qui ont de la publicité : Lighthouse n’y sera plus à 100. Éclair réserve la place des annonces pour que la page ne saute pas, et ne charge rien ailleurs.')) ?></li>
      <li><?= e(t('Sur ces pages, la politique de sécurité devient celle que Google documente pour AdSense ; toutes les autres gardent la politique stricte d’Éclair.')) ?></li>
    </ul>
  </section>
</div>
