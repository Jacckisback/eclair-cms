<?php
/**
 * Gabarit principal du site public.
 * Variables : $v['lang'], $v['head'], $v['css'], $v['content'], $v['current'], $v['id'], $v['class']
 */
defined('ECLAIR') || exit;

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
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= $v['head'] ?>

<meta name="theme-color" content="<?= e(Theme::look()['accent']) ?>">
<?php if ($hasIcon): ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(path_to('/media/site/icon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(path_to('/media/site/icon-180.png')) ?>">
<?php endif ?>
<style><?= $v['css'] ?></style>
<script src="<?= e(asset('site/site.js')) ?>" defer></script>
<?php if ($script = Theme::script()): ?><script src="<?= e($script) ?>" defer></script><?php endif ?>
</head>
<body class="<?= e(trim(($v['class'] ?? '') . ' ' . Theme::bodyClass())) ?>"<?= !empty($v['id']) ? ' data-id="' . e($v['id']) . '"' : '' ?> data-edit="<?= e(Theme::t('edit', $lang)) ?>">
<a class="skip" href="#main"><?= e(Theme::t('skip', $lang)) ?></a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(path_to('/')) ?>"<?= $current === '/' ? ' aria-current="page"' : '' ?>>
      <?php if ($logo): ?>
        <img src="<?= e(path_to(Media::url($logo))) ?>" alt="<?= e($site['name']) ?>" width="<?= (int) round($logo['w'] * 38 / max(1, $logo['h'])) ?>" height="38">
      <?php else: ?>
        <span><?= e($site['name']) ?></span>
      <?php endif ?>
    </a>
    <?php $items = Menus::items('main'); ?>
    <?php if ($items || $site['search']): ?>
    <nav class="site-nav" aria-label="<?= e(Theme::t('menu', $lang)) ?>">
      <ul>
        <?php foreach ($items as $item): ?>
          <li><a href="<?= e($item['href']) ?>"<?= $item['path'] !== '' && ($item['path'] === $current || ($item['path'] !== '/' && str_starts_with($current, $item['path'] . '/'))) ? ' aria-current="page"' : '' ?><?= !empty($item['external']) ? ' rel="noopener"' : '' ?>><?= e($item['label']) ?></a></li>
        <?php endforeach ?>
        <?php if ($site['search']): ?>
          <li><a class="search-link" href="<?= e(path_to('/' . Theme::words($lang)['search'])) ?>" aria-label="<?= e(Theme::t('search', $lang)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg></a></li>
        <?php endif ?>
      </ul>
    </nav>
    <?php endif ?>
  </div>
</header>
<main id="main" tabindex="-1">
<?= $v['content'] ?>
</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-brand"><?= e($site['name']) ?></p>
      <?php $footerText = trim((string) ($site['appearance']['footer'] ?: $site['tagline'])); ?>
      <?php if ($footerText !== ''): ?><p class="footer-text"><?= nl2br(e($footerText)) ?></p><?php endif ?>
    </div>
    <?php $footerItems = Menus::items('footer'); ?>
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
    <?php if ($site['appearance']['credit']): ?><p><?= e(Theme::t('powered', $lang)) ?> ⚡ Éclair</p><?php endif ?>
  </div>
</footer>
</body>
</html>
