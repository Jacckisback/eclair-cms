<?php
/** Structure de l'administration : menu latéral + contenu. */
defined('ECLAIR') || exit;

use Eclair\Auth;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Media;
use Eclair\Messages;
use Eclair\Security;
use Eclair\Settings;

$user = $v['user'];
$section = $v['section'];
$nav = [
    ['dashboard', '', 'home', t('Tableau de bord'), 'content'],
    ['content', 'content', 'file', t('Contenus'), 'content'],
    ['media', 'media', 'image', t('Médias'), 'media'],
    ['categories', 'categories', 'tag', t('Catégories'), 'categories'],
    ['menus', 'menus', 'menu', t('Menus'), 'menus'],
    ['messages', 'messages', 'mail', t('Messages'), 'messages'],
    ['redirects', 'redirects', 'redirect', t('Redirections'), 'redirects'],
];
$adminNav = [
    ['settings', 'settings', 'settings', t('Réglages'), 'settings'],
    ['users', 'users', 'users', t('Utilisateurs'), 'users'],
    ['security', 'security', 'shield', t('Sécurité'), 'security'],
];
$unread = Messages::unread();
$avatar = Media::get((string) ($user['avatar'] ?? ''));
$initials = mb_strtoupper(mb_substr((string) $user['name'], 0, 1) . mb_substr((string) (explode(' ', (string) $user['name'])[1] ?? ''), 0, 1));
?><!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($v['page_title'] ?: 'Éclair') ?> · <?= e(Settings::get('name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
<?= $v['scripts'] ?? '' ?>
</head>
<body>
<div class="shell" id="shell">
  <aside class="sidebar" aria-label="<?= e(t('Navigation de l’administration')) ?>">
    <a class="logo" href="<?= e(admin_url()) ?>">
      <span class="logo-mark"><?= Icons::bolt() ?></span>
      <span><b>Éclair</b><small><?= e(Settings::get('name')) ?></small></span>
    </a>
    <nav class="nav">
      <?php foreach ($nav as [$key, $path, $icon, $label, $area]): ?>
        <?php if (($key !== 'categories' || Settings::get('blog.enabled')) && Auth::can($area)): ?>
          <a href="<?= e(admin_url($path)) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= Icons::svg($icon) ?><?= e($label) ?>
            <?php if ($key === 'messages' && $unread > 0): ?><span class="count"><?= $unread ?></span><?php endif ?></a>
        <?php endif ?>
      <?php endforeach ?>
      <?php if ($user['role'] === 'admin'): ?>
        <span class="nav-title"><?= e(t('Administration')) ?></span>
        <?php foreach ($adminNav as [$key, $path, $icon, $label]): ?>
          <a href="<?= e(admin_url($path)) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= Icons::svg($icon) ?><?= e($label) ?></a>
        <?php endforeach ?>
      <?php endif ?>
      <span class="nav-title"><?= e(t('Raccourcis')) ?></span>
      <a href="<?= e(path_to('/')) ?>" target="_blank" rel="noopener"><?= Icons::svg('external') ?><?= e(t('Voir le site')) ?></a>
    </nav>
    <div class="sidebar-foot">
      <a class="me" href="<?= e(admin_url('profile')) ?>"<?= $section === 'profile' ? ' aria-current="page"' : '' ?>>
        <span class="avatar"><?php if ($avatar): ?><img src="<?= e(path_to(Media::thumb($avatar))) ?>" alt=""><?php else: ?><?= e($initials) ?><?php endif ?></span>
        <span><b><?= e($user['name']) ?></b><small><?= e($user['role'] === 'admin' ? t('Administrateur') : t('Rédacteur')) ?></small></span>
      </a>
      <form method="post" action="<?= e(admin_url('logout')) ?>">
        <?= Security::csrfField() ?>
        <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= Icons::svg('logout') ?><?= e(t('Se déconnecter')) ?></button>
      </form>
    </div>
  </aside>
  <main class="main" id="main">
    <button class="btn btn-sm menu-toggle" type="button" data-toggle-nav><?= Icons::svg('menu') ?><?= e(t('Menu')) ?></button>
    <?php foreach ($v['flash'] as [$type, $message]): ?>
      <?php if ($type === 'secret'): ?>
        <div class="notice warn" role="alert"><span>🔑</span><p><?= e($message) ?></p></div>
      <?php else: ?>
        <div class="notice <?= e($type) ?>" role="status" data-flash><span><?= $type === 'bad' ? '⚠️' : ($type === 'warn' ? '⚠️' : '✅') ?></span><p><?= e($message) ?></p></div>
      <?php endif ?>
    <?php endforeach ?>
    <?= $v['content'] ?>
  </main>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<?= \Eclair\Admin::jsData($v['js'] ?? []) ?>
</body>
</html>
