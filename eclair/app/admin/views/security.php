<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$i = $v['integrity'];
$events = [
    'login' => t('Connexion réussie'), 'login_failed' => t('Échec de connexion'), 'login_locked' => t('Adresse bloquée'),
    'login_blocked' => t('Tentative pendant un blocage'), 'logout' => t('Déconnexion'), 'totp_failed' => t('Code 2FA erroné'),
    'recovery_code_used' => t('Code de secours utilisé'), '2fa_enabled' => t('2FA activée'), '2fa_disabled' => t('2FA désactivée'),
    '2fa_reset' => t('2FA réinitialisée'), 'password_changed' => t('Mot de passe modifié'), 'password_reset' => t('Mot de passe réinitialisé'),
    'user_created' => t('Compte créé'), 'user_deleted' => t('Compte supprimé'), 'settings_saved' => t('Réglages modifiés'),
    'sessions_revoked' => t('Sessions fermées'), 'backup_downloaded' => t('Sauvegarde téléchargée'), 'install' => t('Installation'),
    'ftp_reset' => t('Mot de passe réinitialisé par FTP'), 'ftp_reset_refused' => t('Réinitialisation par FTP refusée'),
    'totp_locked' => t('2FA bloquée (trop de codes faux)'), 'sudo_failed' => t('Confirmation du mot de passe refusée'),
    'update_installed' => t('Mise à jour d’Éclair installée'), 'update_rolled_back' => t('Retour à la version précédente d’Éclair'),
    'update_rejected' => t('Annonce de mise à jour refusée (signature)'), 'update_download_failed' => t('Archive de mise à jour altérée'),
    'update_archive_rejected' => t('Archive de mise à jour refusée'), 'update_replayed' => t('Annonce de mise à jour refusée (plus ancienne que celle déjà reçue)'),
    'drive_connected' => t('Google Drive connecté'), 'drive_disconnected' => t('Google Drive déconnecté'),
    'drive_revoked' => t('Accès à Google Drive retiré'), 'drive_backup' => t('Sauvegarde envoyée sur Google Drive'),
    'drive_app_saved' => t('Application Google enregistrée'), 'drive_app_removed' => t('Application Google retirée de ce site'),
    'drive_unbound' => t('Google Drive déconnecté : le compte qui l’avait connecté n’est plus administrateur'),
];
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Sécurité')) ?></h1><p><?= e(t('Ton site est protégé par défaut. Voici son état et les outils pour aller plus loin.')) ?></p></div></div>
  <div class="grid-2">
    <section class="card">
      <div class="card-head"><h2><?= e(t('Niveau de protection')) ?></h2><span class="score <?= $v['score'] >= 80 ? 'good' : ($v['score'] >= 50 ? 'ok' : 'bad') ?>"><?= (int) $v['score'] ?> %</span></div>
      <ul class="health">
        <?php foreach ($v['checks'] as $c): ?><li class="<?= $c['ok'] ? 'ok' : 'warn' ?>"><?= Icons::svg($c['ok'] ? 'circle-check' : 'alert') ?><span><?= e($c['label']) ?></span></li><?php endforeach ?>
        <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('Aucune base de données : pas d’injection SQL possible')) ?></span></li>
        <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('En-têtes de sécurité stricts (CSP, anti-clickjacking…)')) ?></span></li>
        <li class="<?= $v['gd'] ? 'ok' : 'warn' ?>"><?= Icons::svg($v['gd'] ? 'circle-check' : 'alert') ?><span><?= e($v['gd'] ? t('Images ré-encodées à l’envoi, scripts interdits dans /media') : t('Images contrôlées à l’envoi (sans GD, elles ne sont pas ré-encodées), scripts interdits dans /media')) ?></span></li>
      </ul>
      <?php if (!$v['verified']): ?>
        <form method="post" action="<?= e(admin_url('security')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="verify-url">
          <p class="hint"><?= e(t('Tant que l’adresse du site n’est pas vérifiée, Éclair ne redirige personne vers elle (protection contre une adresse erronée ou piégée).')) ?></p>
          <button class="btn btn-sm" type="submit"><?= Icons::svg('shield') ?><?= e(t('Vérifier l’adresse du site')) ?></button></form>
      <?php endif ?>
    </section>
    <section class="card">
      <h2><?= e(t('Test de protection des dossiers')) ?></h2>
      <p class="small muted"><?= e(t('Ton navigateur tente d’ouvrir des fichiers internes : ils doivent rester inaccessibles.')) ?></p>
      <ul class="health" id="exposure" data-files="<?= e(json_encode(['/data/site.php', '/data/users.php', '/app/bootstrap.php', '/cache/runtime.php', '/data/.htaccess'])) ?>"></ul>
      <p class="small muted" id="rewrite-check" data-url="<?= e(path_to('/__eclair/ping')) ?>"></p>
    </section>
  </div>

  <section class="card" id="integrite">
    <div class="card-head"><h2><?= e(t('Intégrité des fichiers')) ?></h2>
      <form method="post" action="<?= e(admin_url('security')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="integrity">
        <button class="btn btn-sm" type="submit"><?= Icons::svg('search') ?><?= e(t('Analyser maintenant')) ?></button></form></div>
    <p class="small muted"><?= e(t('Compare chaque fichier d’Éclair à son empreinte d’origine et cherche des fichiers suspects (scripts cachés dans les médias…).')) ?></p>
    <?php if ($i): ?>
      <?php if (!$i['manifest']): ?>
        <div class="notice warn"><p><?= e(t('Empreintes de référence absentes (app/manifest.php) : seule la recherche de fichiers suspects a été faite.')) ?></p></div>
      <?php endif ?>
      <?php $clean = !$i['modified'] && !$i['missing'] && !$i['unknown'] && !$i['suspicious']; ?>
      <div class="notice <?= $clean ? 'ok' : 'bad' ?>"><p><?= e($clean ? t('Aucune anomalie détectée.') : t('Des différences ont été trouvées : vérifie-les ci-dessous.')) ?></p></div>
      <?php foreach (['modified' => t('Fichiers modifiés'), 'missing' => t('Fichiers manquants'), 'unknown' => t('Fichiers inconnus'), 'suspicious' => t('Fichiers suspects')] as $k => $label): ?>
        <?php if ($i[$k]): ?><h3><?= e($label) ?> (<?= count($i[$k]) ?>)</h3><ul class="small mono"><?php foreach (array_slice($i[$k], 0, 50) as $f): ?><li><?= e($f) ?></li><?php endforeach ?></ul><?php endif ?>
      <?php endforeach ?>
    <?php endif ?>
  </section>

  <div class="grid-2">
    <section class="card">
      <h2><?= e(t('Sauvegarde')) ?></h2>
      <p class="small muted"><?= e(t('Télécharge une archive de tout ton site (contenus, réglages, comptes, images). Fais-le avant chaque grosse modification.')) ?></p>
      <form method="post" action="<?= e(admin_url('backup')) ?>"><?= Security::csrfField() ?>
        <?= \Eclair\Admin::partial('partials/confirm', []) ?>
        <button class="btn btn-primary" type="submit"<?= $v['zip'] ? '' : ' disabled' ?>><?= Icons::svg('download') ?><?= e(t('Télécharger une sauvegarde')) ?></button></form>
      <?php if (!$v['zip']): ?><p class="hint"><?= e(t('Extension zip indisponible : copie les dossiers data/ et media/ par FTP.')) ?></p><?php endif ?>
      <h3><?= e(t('Restaurer une sauvegarde')) ?></h3>
      <p class="small muted"><?= e(t('Remet en place les contenus, réglages, comptes, images et thèmes d’une sauvegarde, ici ou sur une nouvelle installation. Les clés d’API, les connexions et les sessions de cette installation ne changent pas.')) ?></p>
      <form method="post" action="<?= e(admin_url('restore')) ?>" enctype="multipart/form-data" data-confirm="<?= e(t('Restaurer cette sauvegarde ? Les fichiers qu’elle contient remplacent ceux du site.')) ?>"><?= Security::csrfField() ?>
        <label class="field"><span class="label"><?= e(t('Fichier de sauvegarde (.zip)')) ?></span><input type="file" name="backup" accept=".zip" required></label>
        <?= \Eclair\Admin::partial('partials/confirm', []) ?>
        <button class="btn" type="submit"<?= $v['zip'] ? '' : ' disabled' ?>><?= Icons::svg('upload') ?><?= e(t('Restaurer')) ?></button></form>
    </section>
    <section class="card">
      <h2><?= e(t('Sessions et blocages')) ?></h2>
      <?php if ($v['blocked']): ?>
        <ul class="health"><?php foreach ($v['blocked'] as $b): ?>
          <li><span class="dot bad"></span><span class="grow mono small"><?= e($b['label'] ?? $b['key']) ?></span>
            <form method="post" action="<?= e(admin_url('security')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="unblock"><input type="hidden" name="key" value="<?= e($b['key']) ?>">
              <button class="btn btn-sm" type="submit"><?= e(t('Débloquer')) ?></button></form></li>
        <?php endforeach ?></ul>
      <?php else: ?><p class="small muted"><?= e(t('Aucune adresse bloquée en ce moment.')) ?></p><?php endif ?>
      <form method="post" action="<?= e(admin_url('security')) ?>" data-confirm="<?= e(t('Fermer toutes les sessions ouvertes (sauf la tienne) ?')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="revoke-all">
        <button class="btn btn-sm" type="submit"><?= Icons::svg('logout') ?><?= e(t('Déconnecter tout le monde')) ?></button></form>
    </section>
  </div>

  <?php $driveOn = \Eclair\Extensions::enabled('drive'); ?>
  <section class="card" id="drive">
    <h2><?= e(t('Sauvegardes sur Google Drive')) ?></h2>
    <p class="small muted"><?= e(t('Envoie automatiquement une sauvegarde de ton site dans ton Google Drive. Fonction facultative : la sauvegarde téléchargeable de la page Sécurité fonctionne sans elle.')) ?></p>
    <p><a class="btn btn-sm" href="<?= e(admin_url($driveOn ? 'x/drive' : 'extensions')) ?>"><?= e($driveOn ? t('Ouvrir') : t('Ajouter la fonction')) ?></a></p>
  </section>

  <section class="card">
    <h2><?= e(t('Journal de sécurité')) ?></h2>
    <?php if (!$v['log']): ?><p class="muted small"><?= e(t('Rien à signaler.')) ?></p><?php else: ?>
    <div class="table-wrap"><table class="list log">
      <thead><tr><th><?= e(t('Date')) ?></th><th><?= e(t('Événement')) ?></th><th><?= e(t('Compte')) ?></th><th>IP</th></tr></thead>
      <tbody><?php foreach ($v['log'] as $row): ?>
        <tr><td><?= e(fmt_date((int) $row['t'], \Eclair\I18n::lang(), true)) ?></td>
          <td><?= in_array($row['event'], ['login_failed', 'login_locked', 'login_blocked', 'totp_failed'], true) ? '<span class="dot warn"></span> ' : '' ?><?= e($events[$row['event']] ?? $row['event']) ?></td>
          <td class="small"><?= e($row['user'] ?? '') ?></td><td class="mono small"><?= e($row['ip'] ?? '') ?></td></tr>
      <?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
  </section>

  <?php if ($v['errors']): ?>
  <section class="card">
    <h2><?= e(t('Dernières erreurs techniques')) ?></h2>
    <ul class="small mono"><?php foreach ($v['errors'] as $err): ?><li><?= e(date('Y-m-d H:i', (int) $err['t'])) ?> · <?= e($err['id'] ?? '') ?> · <?= e($err['msg'] ?? '') ?> (<?= e($err['at'] ?? '') ?>)</li><?php endforeach ?></ul>
    <form method="post" action="<?= e(admin_url('security')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="reindex">
      <button class="btn btn-sm" type="submit"><?= Icons::svg('refresh') ?><?= e(t('Reconstruire l’index et vider le cache')) ?></button></form>
  </section>
  <?php endif ?>
</div>
