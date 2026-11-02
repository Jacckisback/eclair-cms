<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Mise en page centrée (connexion). */
defined('ECLAIR') || exit;

use Eclair\I18n;
use Eclair\Settings;
?><!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($v['page_title']) ?> · <?= e(Settings::get('name')) ?></title>
<?= admin_css() ?>
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
</head>
<body>
<div class="auth">
<?php foreach ($v['flash'] as [$type, $message]): ?>
  <div class="notice <?= e($type) ?>" role="status"><span><?= \Eclair\Icons::svg(in_array($type, ['bad', 'warn'], true) ? 'alert' : 'circle-check') ?></span><p><?= e($message) ?></p></div>
<?php endforeach ?>
<?= $v['content'] ?>
</div>
<?= \Eclair\Admin::jsData() ?>
</body>
</html>
