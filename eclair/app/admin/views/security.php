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
    'update_archive_rejected' => t('Archive de mise à jour refusée'),
    'drive_connected' => t('Google Drive connecté'), 'drive_disconnected' => t('Google Drive déconnecté'),
    'drive_revoked' => t('Accès à Google Drive retiré'), 'drive_backup' => t('Sauvegarde envoyée sur Google Drive'),
    'drive_app_saved' => t('Application Google enregistrée'), 'drive_app_removed' => t('Application Google retirée de ce site'),
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
      <form method="post" action="<?= e(admin_url('drive')) ?>" class="drive-app"><?= Security::csrfField() ?><input type="hidden" name="action" value="app">
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
        <form method="post" action="<?= e(admin_url('drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="poll">
          <button class="btn btn-primary" type="submit"><?= Icons::svg('check') ?><?= e(t('J’ai autorisé')) ?></button></form>
        <form method="post" action="<?= e(admin_url('drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="cancel">
          <button class="btn btn-ghost" type="submit"><?= e(t('Annuler')) ?></button></form>
      </div>
    <?php elseif (!$d['connected']): ?>
      <p class="small muted"><?= e(t('Envoie automatiquement une copie de ton site (contenus, réglages, comptes, images) dans ton Google Drive, chaque jour ou chaque semaine, ou quand tu veux. Éclair n’a accès qu’aux fichiers qu’il crée : il ne voit rien d’autre de ton Drive.')) ?></p>
      <?php if ($d['error'] !== ''): ?><div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e($d['error']) ?></p></div><?php endif ?>
      <form method="post" action="<?= e(admin_url('drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="connect">
        <?= \Eclair\Admin::partial('partials/confirm', []) ?>
        <button class="btn btn-primary" type="submit"><?= Icons::svg('link') ?><?= e(t('Connecter Google Drive')) ?></button></form>
    <?php else: ?>
      <p><?= $d['email'] !== '' ? e(t('Compte Google : {e}', ['e' => $d['email']])) : e(t('Compte Google connecté')) ?><?php if ($d['folder'] !== ''): ?> · <?= e(t('dossier « {f} »', ['f' => $d['folder']])) ?><?php endif ?></p>
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
      <form method="post" action="<?= e(admin_url('drive')) ?>" class="drive-settings"><?= Security::csrfField() ?><input type="hidden" name="action" value="save">
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
        <form method="post" action="<?= e(admin_url('drive')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="now">
          <button class="btn btn-primary" type="submit" data-busy="<?= e(t('Sauvegarde en cours…')) ?>"<?= $d['job'] ? ' disabled' : '' ?>><?= Icons::svg('upload') ?><?= e(t('Sauvegarder maintenant')) ?></button></form>
      </div>
      <details class="small drive-more"><summary><?= e(t('Sauvegardes à heure fixe (facultatif)')) ?></summary>
        <p class="muted"><?= e(t('Sans réglage, les sauvegardes automatiques partent quand un administrateur ouvre l’administration. Pour qu’elles partent même sans visite, fais appeler cette adresse toutes les heures par la tâche planifiée (cron) de ton hébergeur ou par un service gratuit comme cron-job.org : elle ne lance une sauvegarde que lorsqu’elle est due.')) ?></p>
        <label class="field"><input type="text" readonly value="<?= e($d['cron']) ?>" data-copy="<?= e($d['cron']) ?>" aria-label="<?= e(t('Adresse de la tâche planifiée')) ?>">
          <span class="hint"><?= e(t('Garde cette adresse pour toi.')) ?></span></label>
      </details>
      <form method="post" action="<?= e(admin_url('drive')) ?>" data-confirm="<?= e(t('Déconnecter Google Drive ? Les sauvegardes déjà envoyées restent dans ton Drive.')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="disconnect">
        <?= \Eclair\Admin::partial('partials/confirm', ['required' => false]) ?>
        <button class="btn btn-sm btn-ghost" type="submit"><?= Icons::svg('logout') ?><?= e(t('Déconnecter Google Drive')) ?></button></form>
    <?php endif ?>
    <?php if ($d['ready'] && $d['own'] !== ''): ?>
      <details class="small drive-more"><summary><?= e(t('Application Google de ce site')) ?></summary>
        <p class="muted"><?= e(t('ID client : {id}', ['id' => $d['own']])) ?></p>
        <form method="post" action="<?= e(admin_url('drive')) ?>" data-confirm="<?= e(t('Retirer l’application Google ? Google Drive sera déconnecté ; les sauvegardes déjà envoyées restent dans ton Drive.')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="app-remove">
          <?= \Eclair\Admin::partial('partials/confirm', ['required' => false]) ?>
          <button class="btn btn-sm btn-ghost" type="submit"><?= Icons::svg('trash') ?><?= e(t('Retirer l’application Google')) ?></button></form>
      </details>
    <?php elseif ($d['ready'] && $d['available'] && $d['builtin']): ?>
      <details class="small drive-more"><summary><?= e(t('Utiliser ta propre application Google (facultatif)')) ?></summary>
        <?= $appSetup ?>
      </details>
    <?php endif ?>
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
