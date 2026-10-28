<?php
/** Page introuvable (statut HTTP 404). */
defined('ECLAIR') || exit;

use Eclair\Settings;
use Eclair\Theme;

$lang = $v['lang'];
?>
<section class="notfound wrap">
  <p class="big" aria-hidden="true">404</p>
  <h1><?= e(Theme::t('not_found', $lang)) ?></h1>
  <p><?= e(Theme::t('not_found_text', $lang)) ?></p>
  <?php if (Settings::get('search')): ?>
    <form class="search-form" action="<?= e(path_to('/' . Theme::words($lang)['search'])) ?>" method="get" role="search">
      <label class="sr-only" for="q404"><?= e(Theme::t('search_label', $lang)) ?></label>
      <input id="q404" type="search" name="q" maxlength="100" required>
      <button class="btn btn-primary" type="submit"><?= e(Theme::t('search', $lang)) ?></button>
    </form>
  <?php endif ?>
  <p><a class="btn btn-secondary" href="<?= e(path_to('/')) ?>"><?= e(Theme::t('back_home', $lang)) ?></a></p>
</section>
