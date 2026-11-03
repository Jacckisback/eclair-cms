<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Autorisation d'une application MCP (Claude, ChatGPT) : relais, connexion, double authentification, consentement.
 * Page publique sans le JavaScript de l'administration : son adresse n'y apparaît jamais. Seul consent.js (sans donnée)
 * y garde « Autoriser » désactivé tant que la personne n'a pas vraiment la page sous les yeux (double clickjacking).
 * Variables : mode, app (application reconnue, sinon domaine de retour), host, known, here, error, user (consentement), message (erreur)
 */
defined('ECLAIR') || exit;

use Eclair\Auth;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Security;
use Eclair\Settings;

$mode = $v['mode'];
?><!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php if ($mode === 'relay'): ?><meta http-equiv="refresh" content="0;url=<?= e($v['here']) ?>"><?php endif ?>
<title><?= e(t('Autoriser une application')) ?> · <?= e(Settings::get('name')) ?></title>
<?= admin_css() ?>
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
<?php if ($mode === 'consent'): ?><script src="<?= e(asset('admin/consent.js')) ?>" defer></script><?php endif ?>
</head>
<body>
<div class="auth">
<form class="auth-card" method="post" action="<?= e($v['here'] ?? '') ?>">
  <span class="logo"><span class="logo-mark"><?= Icons::bolt() ?></span><span><b>Éclair</b><small><?= e(Settings::get('name')) ?></small></span></span>
  <?php if ($mode === 'error'): ?>
    <h1><?= e(t('Autorisation impossible')) ?></h1>
    <div class="notice bad" role="alert"><p><?= e($v['message']) ?></p></div>
  <?php elseif ($mode === 'relay'): ?>
    <h1><?= e(t('Un instant…')) ?></h1>
    <p class="sub"><a href="<?= e($v['here']) ?>"><?= e(t('Continuer')) ?></a></p>
  <?php else: ?>
    <h1><?= e(t('Connecter {app} à ton site', ['app' => $v['app']])) ?></h1>
    <?php if ($v['error'] !== ''): ?><div class="notice bad" role="alert"><p><?= e($v['error']) ?></p></div><?php endif ?>
    <?php if ($mode === 'login'): ?>
      <p class="sub"><?= e(t('Connecte-toi pour donner ton accord.')) ?></p>
      <?= Security::formField() ?>
      <input type="hidden" name="step" value="login">
      <label class="field"><span class="label"><?= e(t('E-mail')) ?></span><input type="email" name="email" autocomplete="username" required autofocus></label>
      <label class="field"><span class="label"><?= e(t('Mot de passe')) ?></span><input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn btn-primary btn-lg btn-block" type="submit"><?= e(t('Se connecter')) ?></button>
    <?php elseif ($mode === 'totp'): ?>
      <p class="sub"><?= e(t('Saisis le code à 6 chiffres affiché par ton application d’authentification.')) ?></p>
      <?= Security::csrfField() ?>
      <input type="hidden" name="step" value="totp">
      <label class="field"><span class="sr-only"><?= e(t('Code')) ?></span><input class="otp" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" required autofocus placeholder="000000"></label>
      <button class="btn btn-primary btn-lg btn-block" type="submit"><?= e(t('Vérifier')) ?></button>
    <?php else: ?>
      <p class="sub"><?= e(t('{app} demande à travailler sur « {site} » en ton nom ({name}).', ['app' => $v['app'], 'site' => Settings::get('name'), 'name' => $v['user']['name']])) ?></p>
      <?php if (!$v['known']): ?><div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Application inconnue : n’accepte que si tu viens de l’ajouter toi-même.')) ?></p></div>
      <?php else: ?><div class="notice"><span><?= Icons::svg('shield') ?></span><p><?= e(t('N’accepte que si tu viens de lancer cette connexion toi-même depuis {app}.', ['app' => $v['app']])) ?></p></div><?php endif ?>
      <ul class="grant">
        <li><?= Icons::svg('check') ?><?= e(t('Lire tes pages, articles, produits et ta médiathèque')) ?></li>
        <li><?= Icons::svg('check') ?><?= e(t('Créer des brouillons et modifier tes brouillons')) ?></li>
        <li><?= Icons::svg('check') ?><?= e(t('Proposer des modifications de tes contenus publiés, que tu vérifies et enregistres')) ?></li>
        <li class="no"><?= Icons::svg('ban') ?><?= e(t('Jamais publier, supprimer, ni toucher aux réglages et aux comptes')) ?></li>
      </ul>
      <p class="small muted"><?= e(t('Retour vers {host}. Tu peux couper cet accès à tout moment dans Réglages › Assistant IA.', ['host' => $v['host']])) ?></p>
      <?= Security::csrfField() ?>
      <?php // Application inconnue : mot de passe toujours demandé ; application reconnue : seulement si la connexion date de plus de 15 minutes. ?>
      <?php if (!$v['known'] || !Auth::fresh()): ?>
        <label class="field"><span class="label"><?= e(t('Confirme ton mot de passe pour autoriser cette application.')) ?></span>
          <input type="password" name="confirm_password" autocomplete="current-password" required></label>
      <?php endif ?>
      <noscript><div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Active JavaScript pour autoriser cette application.')) ?></p></div></noscript>
      <div class="row">
        <button class="btn btn-lg" type="submit" name="step" value="deny" formnovalidate><?= e(t('Refuser')) ?></button>
        <?php // Désactivé au chargement : consent.js ne l'active qu'après un court délai et un geste dans la page (autocomplete : Firefox ne le restaure pas actif). ?>
        <button class="btn btn-primary btn-lg" type="submit" name="step" value="allow" disabled data-gate autocomplete="off"><?= e(t('Autoriser')) ?></button>
      </div>
    <?php endif ?>
  <?php endif ?>
</form>
</div>
</body>
</html>
