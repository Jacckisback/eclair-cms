<?php
/** Mise en page plein écran (éditeur). */
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
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
<script src="<?= e(asset('admin/editor.js')) ?>" defer></script>
<?php if (\Eclair\Ai::allowed(\Eclair\Auth::user())): ?><script src="<?= e(asset('admin/assistant.js')) ?>" defer></script><?php endif ?>
</head>
<body>
<?= $v['content'] ?>
<div class="toasts" id="toasts" aria-live="polite"></div>
<?= \Eclair\Admin::jsData($v['js'] ?? []) ?>
</body>
</html>
