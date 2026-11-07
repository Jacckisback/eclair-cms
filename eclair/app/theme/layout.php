<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Gabarit principal du site public.
 * Variables : $v['lang'], $v['head'], $v['css'], $v['content'], $v['current'], $v['id'], $v['class'], $v['nonce'] (page avec publicité)
 */
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Langs;
use Eclair\Media;
use Eclair\Menus;
use Eclair\Settings;
use Eclair\Theme;

$lang = $v['lang'];
$site = Settings::all();
$logo = Media::get((string) $site['appearance']['logo']);
$current = (string) ($v['current'] ?? '');
$socialNames = ['linkedin' => 'LinkedIn', 'x' => 'X', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'github' => 'GitHub'];
$hasIcon = is_file(ECLAIR_ROOT . '/media/site/icon-32.png');
// 1.15 : site multilingue, menu mobile plein écran et écran de démarrage (sans eux, la page reste à l'octet près).
$multi = Langs::enabled();
$mobile = !empty($site['appearance']['mobile_menu']);
$splash = !empty($site['appearance']['splash']);
$name = Langs::text('name', $lang);
$root = Langs::home($lang);
$searchPath = Langs::prefix($lang) . '/' . Theme::words($lang)['search'];
$langs = $multi ? Langs::links($lang, (string) ($v['id'] ?? ''), $current) : [];
$active = static fn (array $item): bool => $item['path'] !== '' && ($item['path'] === $current || ($item['path'] !== '/' && str_starts_with($current, $item['path'] . '/')));
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1<?= $mobile ? ', viewport-fit=cover' : '' ?>">
<?= $v['head'] ?>

<meta name="theme-color" content="<?= e(Theme::look()['accent']) ?>">
<?php if ($hasIcon): ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(path_to('/media/site/icon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(path_to('/media/site/icon-180.png')) ?>">
<?php endif ?>
<?php if ($splash): ?>
<link rel="manifest" href="<?= e(path_to('/manifest.webmanifest')) ?>">
<script src="<?= e(asset('site/splash.js')) ?>"<?= !empty($v['nonce']) ? ' nonce="' . e($v['nonce']) . '"' : '' ?>></script>
<?php endif ?>
<?= Theme::preload() ?><style><?= $v['css'] ?></style>
<script src="<?= e(asset('site/site.js')) ?>" defer<?= !empty($v['nonce']) ? ' nonce="' . e($v['nonce']) . '"' : '' ?>></script>
<?php if ($mobile): ?><script src="<?= e(asset('site/menu.js')) ?>" defer<?= !empty($v['nonce']) ? ' nonce="' . e($v['nonce']) . '"' : '' ?>></script>
<?php endif ?>
</head>
<body class="<?= e(trim(($v['class'] ?? '') . ' ' . Theme::bodyClass())) ?>"<?= !empty($v['id']) ? ' data-id="' . e($v['id']) . '"' : '' ?> data-edit="<?= e(Theme::t('edit', $lang)) ?>">
<?php if ($splash): ?>
<div class="splash" aria-hidden="true"><div class="splash-in"><?php if ($hasIcon): ?><img src="<?= e(path_to('/media/site/icon-192.png')) ?>" alt="" width="96" height="96"><?php endif ?><span class="splash-name"><?= e($name) ?></span></div></div>
<?php endif ?>
<a class="skip" href="#main"><?= e(Theme::t('skip', $lang)) ?></a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(path_to($root)) ?>"<?= $current === $root ? ' aria-current="page"' : '' ?>>
      <?php if ($logo): ?>
        <img src="<?= e(path_to(Media::url($logo))) ?>" alt="<?= e($name) ?>" width="<?= (int) round($logo['w'] * 38 / max(1, $logo['h'])) ?>" height="38">
      <?php else: ?>
        <span><?= e($name) ?></span>
      <?php endif ?>
    </a>
    <?php $items = Menus::items('main', $lang); ?>
    <?php if ($items || $site['search']): ?>
    <nav class="site-nav" aria-label="<?= e(Theme::t('menu', $lang)) ?>">
      <ul>
        <?php foreach ($items as $item): ?>
          <li><a href="<?= e($item['href']) ?>"<?= $active($item) ? ' aria-current="page"' : '' ?><?= !empty($item['external']) ? ' rel="noopener"' : '' ?>><?= e($item['label']) ?></a></li>
        <?php endforeach ?>
        <?php if ($site['search']): ?>
          <li><a class="search-link" href="<?= e(path_to($searchPath)) ?>" aria-label="<?= e(Theme::t('search', $lang)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg></a></li>
        <?php endif ?>
      </ul>
    </nav>
    <?php endif ?>
<?php if ($langs): ?>
    <nav class="lang-switch" aria-label="<?= e(Theme::t('language', $lang)) ?>">
      <?php foreach ($langs as $code => $l): ?><a href="<?= e(path_to($l['path'])) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" title="<?= e($l['name']) ?>"<?= $l['current'] ? ' aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?></a><?php endforeach ?>
    </nav>
<?php endif ?>
<?php if ($mobile && ($items || $langs || $site['search'])): ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mnav" aria-label="<?= e(Theme::t('open_menu', $lang)) ?>"><span></span><span></span></button>
<?php endif ?>
  </div>
</header>
<?php if ($mobile && ($items || $langs || $site['search'])): ?>
<div class="mnav" id="mnav" hidden>
  <div class="mnav-panel" role="dialog" aria-modal="true" aria-label="<?= e(Theme::t('menu', $lang)) ?>">
    <div class="mnav-head">
      <a class="mnav-brand" href="<?= e(path_to($root)) ?>"><?php if ($hasIcon): ?><img src="<?= e(path_to('/media/site/icon-192.png')) ?>" alt="" width="32" height="32"><?php endif ?><span><?= e($name) ?></span></a>
      <button class="mnav-close" type="button" aria-label="<?= e(Theme::t('close_menu', $lang)) ?>"><?= Icons::svg('x') ?></button>
    </div>
    <nav class="mnav-body" aria-label="<?= e(Theme::t('menu', $lang)) ?>">
      <ul class="mnav-list">
        <?php foreach ($items as $i => $item): ?>
          <li><a href="<?= e($item['href']) ?>"<?= $active($item) ? ' aria-current="page"' : '' ?><?= !empty($item['external']) ? ' rel="noopener"' : '' ?>><span class="mnav-ic"><?= Icons::svg(Icons::has($item['icon'] ?? '') ? $item['icon'] : 'file') ?></span><span class="mnav-label"><?= e($item['label']) ?></span><span class="mnav-go"><?= Icons::svg('chevron') ?></span></a></li>
        <?php endforeach ?>
        <?php if ($site['search']): ?>
          <li><a href="<?= e(path_to($searchPath)) ?>"><span class="mnav-ic"><?= Icons::svg('search') ?></span><span class="mnav-label"><?= e(Theme::t('search', $lang)) ?></span><span class="mnav-go"><?= Icons::svg('chevron') ?></span></a></li>
        <?php endif ?>
      </ul>
      <?php if ($langs): ?>
      <div class="mnav-langs" role="group" aria-label="<?= e(Theme::t('language', $lang)) ?>">
        <span class="mnav-ic"><?= Icons::svg('globe') ?></span>
        <?php foreach ($langs as $code => $l): ?><a href="<?= e(path_to($l['path'])) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>"<?= $l['current'] ? ' aria-current="true"' : '' ?>><?= e($l['name']) ?></a><?php endforeach ?>
      </div>
      <?php endif ?>
    </nav>
    <?php $tag = Langs::text('tagline', $lang); ?>
    <?php if ($tag !== ''): ?><p class="mnav-note"><?= e($tag) ?></p><?php endif ?>
  </div>
</div>
<?php endif ?>
<main id="main" tabindex="-1">
<?= $v['content'] ?>
</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-brand"><?= e($name) ?></p>
      <?php $footerText = trim(Langs::text('footer', $lang) ?: Langs::text('tagline', $lang)); ?>
      <?php if ($footerText !== ''): ?><p class="footer-text"><?= nl2br(e($footerText)) ?></p><?php endif ?>
    </div>
    <?php $footerItems = Menus::items('footer', $lang); ?>
    <?php if ($footerItems): ?>
    <nav aria-label="<?= e(Theme::t('footer_menu', $lang)) ?>">
      <ul>
        <?php foreach ($footerItems as $item): ?>
          <li><a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a></li>
        <?php endforeach ?>
      </ul>
    </nav>
    <?php endif ?>
    <?php $social = array_filter((array) $site['social']); ?>
    <?php if ($social): ?>
    <nav aria-label="<?= e(Theme::t('social', $lang)) ?>">
      <ul>
        <?php foreach ($social as $key => $url): ?>
          <li><a href="<?= e($url) ?>" rel="me noopener"><?= e($socialNames[$key] ?? (string) parse_url((string) $url, PHP_URL_HOST)) ?></a></li>
        <?php endforeach ?>
      </ul>
    </nav>
    <?php endif ?>
  </div>
  <div class="wrap footer-bottom">
    <p>© <?= date('Y') ?> <?= e($site['seo']['entity_name'] ?: $site['name']) ?></p>
<?php if ($langs): ?>
    <nav class="footer-langs" aria-label="<?= e(Theme::t('language', $lang)) ?>">
      <?php foreach ($langs as $code => $l): ?><a href="<?= e(path_to($l['path'])) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>"<?= $l['current'] ? ' aria-current="true"' : '' ?>><?= e($l['name']) ?></a><?php endforeach ?>
    </nav>
<?php endif ?>
    <?php if ($site['appearance']['credit']): ?><p><?= e(Theme::t('powered', $lang)) ?> ⚡ Éclair</p><?php endif ?>
  </div>
</footer>
</body>
</html>
