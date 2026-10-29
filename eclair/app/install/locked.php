<?php
/** Fenêtre d'installation expirée. Variables : lang */
defined('ECLAIR') || exit;

use Eclair\Icons;
?><!doctype html>
<html lang="<?= e($v['lang']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(t('Installation d’Éclair')) ?></title>
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="auth">
  <div class="auth-card">
    <span class="logo"><span class="logo-mark"><?= Icons::bolt() ?></span><span><b>Éclair</b><small><?= e(t('Installation')) ?></small></span></span>
    <h1>🔒 <?= e(t('Installation verrouillée')) ?></h1>
    <p><?= e(t('Par sécurité, l’installation doit être faite dans les 2 heures qui suivent la première visite : sinon, n’importe qui pourrait prendre le contrôle d’un site oublié.')) ?></p>
    <p><b><?= e(t('Pour la rouvrir :')) ?></b> <?= e(t('supprime le fichier data/install-window.php avec ton logiciel FTP (ou le gestionnaire de fichiers de ton hébergeur), puis recharge cette page.')) ?></p>
  </div>
</div>
</body>
</html>
