<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Sauvegardes Google Drive : vue de la fonction « drive » (lib/AdminDrive.php la rend dans la page d'administration). */
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Sauvegardes sur Google Drive')) ?></h1><p><?= e(t('Envoie automatiquement une sauvegarde de ton site dans ton Google Drive. Fonction facultative : la sauvegarde téléchargeable de la page Sécurité fonctionne sans elle.')) ?></p></div></div>
  <?php $d = $v['drive']; ?>
  <?php ob_start(); ?>
      <ol class="drive-steps drive-guide">
        <li><?= e(t('Crée un projet dans la console Google Cloud, par exemple « Sauvegardes de mon site ».')) ?> <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener"><?= e(t('Ouvrir')) ?><?= Icons::svg('external') ?></a></li>
        <li><?= e(t('Active l’API Google Drive pour ce projet.')) ?> <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener"><?= e(t('Ouvrir')) ?><?= Icons::svg('external') ?></a></li>
        <li><?= e(t('Dans Google Auth Platform › Branding, clique sur « Get started » : nom de l’application, ton adresse e-mail, audience « External », puis valide.')) ?> <a href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noopener"><?= e(t('Ouvrir')) ?><?= Icons::svg('external') ?></a></li>
        <li><?= e(t('Dans « Data Access », ajoute le champ d’accès …/auth/drive.file, puis enregistre.')) ?> <a href="https://console.cloud.google.com/auth/scopes" target="_blank" rel="noopener"><?= e(t('Ouvrir')) ?><?= Icons::svg('external') ?></a></li>
        <li><?= e(t('Dans « Audience », clique sur « Publish app » : en mode test, Google coupe l’accès au bout de 7 jours.')) ?> <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noopener"><?= e(t('Ouvrir')) ?><?= Icons::svg('external') ?></a></li>
        <li><?= e(t('Dans « Clients », clique sur « Create client », choisis le type « TVs and Limited Input devices », puis copie l’ID client et le code secret.')) ?> <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noopener"><?= e(t('Ouvrir')) ?><?= Icons::svg('external') ?></a></li>
        <li><?= e(t('Colle-les ci-dessous. À la connexion, si Google indique qu’il n’a pas validé l’application, clique sur « Paramètres avancés » puis continue : c’est la tienne.')) ?></li>
      </ol>
      <form method="post" action="<?= e(admin_url('x/drive')) ?>" class="drive-app"><?= Security::csrfField() ?><input type="hidden" name="action" value="app">
        <div class="grid-2">
          <label class="field"><span class="label"><?= e(t('ID client')) ?></span>
            <input type="text" name="client_id" required autocomplete="off" spellcheck="false" placeholder="1234567890-abc.apps.googleusercontent.com"></label>
          <label class="field"><span class="label"><?= e(t('Code secret du client')) ?></span>
            <input type="password" name="client_secret" required autocomplete="off" spellcheck="false"></label>
        </div>
        <p class="hint"><?= e(t('Le code secret est chiffré sur ton serveur et n’est plus jamais affiché.')) ?></p>
        <?= \Eclair\Admin::partial('partials/confirm', []) ?>
        <button class="btn btn-primary" type="submit"><?= Icons::svg('key') ?><?= e(t('Enregistrer l’application Google')) ?></button>
      </form>
  <?php $appSetup = (string) ob_get_clean(); ?>
  <section class="card" id="drive" aria-labelledby="drive-title">
    <div class="card-head"><h2 id="drive-title"><?= e(t('Sauvegardes sur Google Drive')) ?></h2>
      <?php if ($d['connected']): ?><span class="badge ok"><?= e(t('Connecté')) ?></span><?php endif ?></div>
    <?php if ($d['busy']): ?>
      <div class="notice" role="status"><span><?= Icons::svg('clock') ?></span><p><?= e(t('Connexion à Google Drive en cours dans la session d’un autre administrateur : le code ne s’affiche que là.')) ?></p></div>
    <?php endif ?>
    <?php if (!$d['ready'] && !$d['connected']): ?>
      <p class="small muted"><?= e(t('Indisponible sur ce serveur : les extensions PHP curl, zip et sodium (ou openssl) sont nécessaires. Le téléchargement de sauvegarde reste disponible ci-dessus.')) ?></p>
    <?php elseif (!$d['available'] && !$d['connected']): ?>
      <p class="small muted"><?= e(t('Envoie automatiquement une copie de ton site (contenus, réglages, comptes, images) dans ton Google Drive, chaque jour ou chaque semaine, ou quand tu veux. Éclair n’a accès qu’aux fichiers qu’il crée : il ne voit rien d’autre de ton Drive.')) ?></p>
      <div class="notice"><span><?= Icons::svg('key') ?></span><p><?= e(t('Pour se connecter à ton Drive, ton site a besoin d’une application Google à ton nom : gratuite, elle se crée une seule fois, en une dizaine de minutes. Seul ton site l’utilise.')) ?></p></div>
      <?= $appSetup ?>
    <?php elseif ($d['pending']): ?>
      <ol class="drive-steps">
        <li><?= e(t('Ouvre cette page Google, sur ton ordinateur ou ton téléphone :')) ?> <a href="<?= e($d['pending']['url']) ?>" target="_blank" rel="noopener"><strong><?= e(preg_replace('~^https?://~', '', $d['pending']['url'])) ?></strong></a></li>
        <li><?= e(t('Saisis ce code :')) ?> <strong class="drive-code"><?= e($d['pending']['code']) ?></strong>
          <button class="btn btn-sm btn-ghost" type="button" data-copy="<?= e($d['pending']['code']) ?>"><?= Icons::svg('copy') ?><?= e(t('Copier')) ?></button></li>
        <li><?= e(t('Choisis ton compte Google et autorise Éclair à enregistrer ses sauvegardes.')) ?></li>
      </ol>
      <p class="small muted" role="status" data-drive-poll="<?= (int) $d['pending']['interval'] ?>"><?= e(t('En attente de ton autorisation… Le code est valable jusqu’à {h}.', ['h' => date('H:i', (int) $d['pending']['expires'])])) ?></p>
      <div class="row">
        <form method="post" action="<?= e(admin_url('x/drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="poll">
          <button class="btn btn-primary" type="submit"><?= Icons::svg('check') ?><?= e(t('J’ai autorisé')) ?></button></form>
        <form method="post" action="<?= e(admin_url('x/drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="cancel">
          <button class="btn btn-ghost" type="submit"><?= e(t('Annuler')) ?></button></form>
      </div>
    <?php elseif (!$d['connected']): ?>
      <p class="small muted"><?= e(t('Envoie automatiquement une copie de ton site (contenus, réglages, comptes, images) dans ton Google Drive, chaque jour ou chaque semaine, ou quand tu veux. Éclair n’a accès qu’aux fichiers qu’il crée : il ne voit rien d’autre de ton Drive.')) ?></p>
      <?php if ($d['error'] !== ''): ?><div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e($d['error']) ?></p></div><?php endif ?>
      <form method="post" action="<?= e(admin_url('x/drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="connect">
        <?= \Eclair\Admin::partial('partials/confirm', []) ?>
        <button class="btn btn-primary" type="submit"><?= Icons::svg('link') ?><?= e(t('Connecter Google Drive')) ?></button></form>
    <?php else: ?>
      <p><?= $d['email'] !== '' ? e(t('Compte Google : {e}', ['e' => $d['email']])) : e(t('Compte Google connecté')) ?><?php if ($d['folder'] !== ''): ?> · <?= e(t('dossier « {f} »', ['f' => $d['folder']])) ?><?php endif ?><?php if ($d['by'] !== ''): ?> · <?= e(t('connecté par {name}', ['name' => $d['by']])) ?><?php endif ?></p>
      <?php if (!empty($d['unbound'])): ?><div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Connexion faite avant la 1.10.3 : déconnecte puis reconnecte Google Drive pour qu’Éclair sache quel administrateur l’a établie.')) ?></p></div><?php endif ?>
      <?php if ($d['job']): ?>
        <div class="drive-run" data-drive-run>
          <p class="small" data-drive-label><?= e(t('Envoi de la sauvegarde : {n} %', ['n' => $d['job']['percent']])) ?></p>
          <progress max="100" value="<?= (int) $d['job']['percent'] ?>"></progress>
        </div>
      <?php endif ?>
      <?php $last = $d['last']; ?>
      <?php if ($last): ?>
        <p class="small"><span class="dot <?= $last['ok'] ? 'ok' : 'warn' ?>"></span>
          <?= e($last['ok'] ? t('Dernière sauvegarde : {d} ({s}).', ['d' => fmt_date((int) $last['date'], \Eclair\I18n::lang(), true), 's' => human_size((int) ($last['size'] ?? 0))])
            : t('Dernière tentative ({d}) : {e}', ['d' => fmt_date((int) $last['date'], \Eclair\I18n::lang(), true), 'e' => (string) ($last['error'] ?? '')])) ?></p>
      <?php else: ?>
        <p class="small muted"><?= e(t('Aucune sauvegarde envoyée pour l’instant.')) ?></p>
      <?php endif ?>
      <form method="post" action="<?= e(admin_url('x/drive')) ?>" class="drive-settings"><?= Security::csrfField() ?><input type="hidden" name="action" value="save">
        <div class="grid-2">
          <label class="field"><span class="label"><?= e(t('Sauvegardes automatiques')) ?></span><select name="schedule">
            <?php foreach (['off' => t('Désactivées'), 'daily' => t('Chaque jour'), 'weekly' => t('Chaque semaine')] as $k => $l): ?>
              <option value="<?= e($k) ?>"<?= $d['schedule'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></label>
          <label class="field"><span class="label"><?= e(t('Garder les dernières')) ?></span><input type="number" name="keep" min="1" max="50" value="<?= (int) $d['keep'] ?>">
            <span class="hint"><?= e(t('Les plus anciennes partent dans la corbeille de ton Drive.')) ?></span></label>
        </div>
        <button class="btn" type="submit"><?= Icons::svg('check') ?><?= e(t('Enregistrer')) ?></button>
      </form>
      <div class="row">
        <form method="post" action="<?= e(admin_url('x/drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="now">
          <button class="btn btn-primary" type="submit" data-busy="<?= e(t('Sauvegarde en cours…')) ?>"<?= $d['job'] ? ' disabled' : '' ?>><?= Icons::svg('upload') ?><?= e(t('Sauvegarder maintenant')) ?></button></form>
      </div>
      <details class="small drive-more"><summary><?= e(t('Sauvegardes à heure fixe (facultatif)')) ?></summary>
        <p class="muted"><?= e(t('Sans réglage, les sauvegardes automatiques partent quand un administrateur ouvre l’administration. Pour qu’elles partent même sans visite, fais appeler cette adresse toutes les heures par la tâche planifiée (cron) de ton hébergeur ou par un service gratuit comme cron-job.org : elle ne lance une sauvegarde que lorsqu’elle est due.')) ?></p>
        <label class="field"><input type="text" readonly value="<?= e($d['cron']) ?>" data-copy="<?= e($d['cron']) ?>" aria-label="<?= e(t('Adresse de la tâche planifiée')) ?>">
          <span class="hint"><?= e(t('Garde cette adresse pour toi.')) ?></span></label>
      </details>
      <form method="post" action="<?= e(admin_url('x/drive')) ?>" data-confirm="<?= e(t('Déconnecter Google Drive ? Les sauvegardes déjà envoyées restent dans ton Drive.')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="disconnect">
        <?= \Eclair\Admin::partial('partials/confirm', ['required' => false]) ?>
        <button class="btn btn-sm btn-ghost" type="submit"><?= Icons::svg('logout') ?><?= e(t('Déconnecter Google Drive')) ?></button></form>
    <?php endif ?>
    <?php if ($d['ready'] && $d['own'] !== ''): ?>
      <details class="small drive-more"><summary><?= e(t('Application Google de ce site')) ?></summary>
        <p class="muted"><?= e(t('ID client : {id}', ['id' => $d['own']])) ?></p>
        <form method="post" action="<?= e(admin_url('x/drive')) ?>" data-confirm="<?= e(t('Retirer l’application Google ? Google Drive sera déconnecté ; les sauvegardes déjà envoyées restent dans ton Drive.')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="app-remove">
          <?= \Eclair\Admin::partial('partials/confirm', ['required' => false]) ?>
          <button class="btn btn-sm btn-ghost" type="submit"><?= Icons::svg('trash') ?><?= e(t('Retirer l’application Google')) ?></button></form>
      </details>
    <?php elseif ($d['ready'] && $d['available'] && $d['builtin']): ?>
      <details class="small drive-more"><summary><?= e(t('Utiliser ta propre application Google (facultatif)')) ?></summary>
        <?= $appSetup ?>
      </details>
    <?php endif ?>
  </section>

</div>
