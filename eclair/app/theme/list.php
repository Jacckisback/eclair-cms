<?php
/**
 * Listes : accueil du blog, catégorie, auteur, recherche.
 * Variables : title, description, entries, crumbs, page, pages, prev, next, query (recherche), lang, empty
 */
defined('ECLAIR') || exit;

use Eclair\Theme;

$lang = $v['lang'];
?>
<section class="listing wrap">
  <header class="listing-header">
    <?php if (count($v['crumbs']) > 1): ?>
      <nav class="crumbs" aria-label="<?= e(Theme::t('breadcrumb', $lang)) ?>">
        <ol>
          <?php foreach ($v['crumbs'] as $i => $crumb): ?>
            <?php if ($i === array_key_last($v['crumbs'])): ?>
              <li aria-current="page"><?= e($crumb['name']) ?></li>
            <?php else: ?>
              <li><a href="<?= e(path_to($crumb['path'])) ?>"><?= e($crumb['name']) ?></a></li>
            <?php endif ?>
          <?php endforeach ?>
        </ol>
      </nav>
    <?php endif ?>
    <h1><?= e($v['title']) ?><?= $v['page'] > 1 ? ' <span class="sr-only">— ' . e(Theme::t('page_n', $lang, ['n' => $v['page']])) . '</span>' : '' ?></h1>
    <?php if ($v['description'] !== ''): ?><p><?= nl2br(e($v['description'])) ?></p><?php endif ?>
    <?php if ($v['query'] !== null): ?>
      <form class="search-form" action="<?= e(path_to('/' . Theme::words($lang)['search'])) ?>" method="get" role="search">
        <label class="sr-only" for="q"><?= e(Theme::t('search_label', $lang)) ?></label>
        <input id="q" type="search" name="q" value="<?= e($v['query']) ?>" maxlength="100" required>
        <button class="btn btn-primary" type="submit"><?= e(Theme::t('search', $lang)) ?></button>
      </form>
      <?php if ($v['query'] !== ''): ?>
        <p role="status"><?= e(Theme::t('search_results', $lang, ['n' => count($v['entries']), 'q' => $v['query']])) ?></p>
      <?php endif ?>
    <?php endif ?>
  </header>

  <?php if ($v['entries']): ?>
    <?= Theme::cards($v['entries'], $lang, 2) ?>
  <?php elseif ($v['empty'] !== ''): ?>
    <p class="empty"><?= e($v['empty']) ?></p>
  <?php endif ?>

  <?php if ($v['pages'] > 1): ?>
    <nav class="pager" aria-label="<?= e(Theme::t('pagination', $lang)) ?>">
      <?= $v['prev'] ? '<a href="' . e(path_to($v['prev'])) . '" rel="prev">← ' . e(Theme::t('newer', $lang)) . '</a>' : '<span></span>' ?>
      <span><?= e(Theme::t('page_n', $lang, ['n' => $v['page']])) ?> / <?= (int) $v['pages'] ?></span>
      <?= $v['next'] ? '<a href="' . e(path_to($v['next'])) . '" rel="next">' . e(Theme::t('older', $lang)) . ' →</a>' : '<span></span>' ?>
    </nav>
  <?php endif ?>
</section>
