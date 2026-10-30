<?php
/**
 * Fiche produit : galerie, prix, variantes (sans JavaScript), bouton de paiement, réassurance, puis la description.
 * Variables : item, entry, html, images, offers, info, legal, category, crumbs, related, translations, lang
 */
defined('ECLAIR') || exit;

use Eclair\Categories;
use Eclair\Content;
use Eclair\Media;
use Eclair\Shop;
use Eclair\Theme;

$item = $v['item'];
$entry = $v['entry'];
$lang = $v['lang'];
$p = $item['product'];
$offers = $v['offers'];
$price = (string) ($entry['price'] ?? '');
$sale = $price !== '' && $p['compare'] !== '' && (float) $p['compare'] > (float) $price;
$button = Shop::button($lang);
?>
<article class="content product">
  <header class="entry-header">
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
  </header>

  <div class="product-main">
    <?php if ($v['images']): ?>
      <div class="product-gallery" role="group" aria-label="<?= e(Theme::t('product_photos', $lang)) ?>">
        <?php foreach ($v['images'] as $i => $m): ?>
          <figure class="product-img"><?= Media::picture($m, (string) ($m['alt'] !== '' ? $m['alt'] : $item['title']), '(min-width: 56rem) 38rem, 100vw', $i === 0) ?></figure>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <div class="product-info">
      <?php if ($v['category']): ?><p><a class="chip" href="<?= e(path_to(Categories::path($v['category']))) ?>"><?= e($v['category']['name']) ?></a></p><?php endif ?>
      <h1 class="product-title"><?= e($item['title']) ?></h1>
      <?php if ($price !== ''): ?>
        <p class="price">
          <span class="price-now"><?= e(!empty($entry['from']) ? Theme::t('from_price', $lang, ['price' => Shop::money($price, $lang)]) : Shop::money($price, $lang)) ?></span>
          <?php if ($sale): ?>
            <s class="price-was"><span class="sr-only"><?= e(Theme::t('was_price', $lang)) ?> : </span><?= e(Shop::money($p['compare'], $lang)) ?></s>
            <span class="badge badge-sale">−<?= (int) round((1 - (float) $price / (float) $p['compare']) * 100) ?> %</span>
          <?php endif ?>
        </p>
      <?php endif ?>
      <p class="stock stock-<?= e($entry['availability']) ?>"><?= e(Theme::t($entry['availability'], $lang)) ?></p>
      <?php if ($item['summary'] !== ''): ?><p class="product-lead"><?= nl2br(e($item['summary'])) ?></p><?php endif ?>

      <?php if (count($offers) > 1): ?>
        <fieldset class="variants">
          <legend><?= e(Theme::t('choose_option', $lang)) ?></legend>
          <div class="variant-list">
            <?php $first = array_key_first(array_filter($offers, fn ($o) => $o['href'] !== '')) ?? 0; ?>
            <?php foreach ($offers as $i => $o): ?>
              <input class="sr-only" type="radio" name="variant" id="variant-<?= $i ?>"<?= $i === $first ? ' checked' : '' ?>>
              <label for="variant-<?= $i ?>"<?= $o['availability'] === 'out_of_stock' ? ' class="is-out"' : '' ?>><?= e($o['label']) ?><?= $o['availability'] === 'out_of_stock' ? ' <small>' . e(Theme::t('out_of_stock', $lang)) . '</small>' : ($o['money'] !== '' && $o['price'] !== $price ? ' <small>' . e($o['money']) . '</small>' : '') ?></label>
              <?php if ($o['href'] !== ''): ?>
                <a class="btn btn-primary btn-lg buy" href="<?= e($o['href']) ?>" rel="nofollow noopener"><?= e($button) ?> · <?= e($o['money']) ?></a>
              <?php else: ?>
                <span class="btn btn-primary btn-lg buy is-disabled" aria-disabled="true"><?= e($o['label'] . ' · ' . $o['state']) ?></span>
              <?php endif ?>
            <?php endforeach ?>
          </div>
        </fieldset>
      <?php elseif ($offers[0]['href'] !== ''): ?>
        <p><a class="btn btn-primary btn-lg buy" href="<?= e($offers[0]['href']) ?>" rel="nofollow noopener"><?= e($button) ?></a></p>
      <?php else: ?>
        <p><span class="btn btn-primary btn-lg buy is-disabled" aria-disabled="true"><?= e($offers[0]['state']) ?></span></p>
      <?php endif ?>

      <ul class="reassure">
        <?php foreach ($v['info'] as [$icon, $text]): ?><li><span aria-hidden="true"><?= $icon ?></span> <?= e($text) ?></li><?php endforeach ?>
      </ul>
      <?php if ($v['legal']): ?>
        <p class="legal-links"><?php foreach ($v['legal'] as $i => $l): ?><?= $i ? ' · ' : '' ?><a href="<?= e(path_to($l['path'])) ?>"><?= e($l['name']) ?></a><?php endforeach ?></p>
      <?php endif ?>
      <?php if ($v['translations']): ?>
        <p class="langs"><span><?= e(Theme::t('read_in', $lang)) ?> :</span>
          <?php foreach ($v['translations'] as $code => $t): ?>
            <?php if ($t['id'] !== $item['id']): ?><a href="<?= e(path_to(Content::path($t))) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>"><?= e(Content::LANGS[$code] ?? $code) ?></a><?php endif ?>
          <?php endforeach ?>
        </p>
      <?php endif ?>
    </div>
  </div>

  <?= $v['html'] ?>

  <?php if ($v['related']): ?>
    <section class="related" aria-labelledby="related-title">
      <h2 id="related-title"><?= e(Theme::t('related_products', $lang)) ?></h2>
      <?= Theme::products($v['related'], $lang, 3) ?>
    </section>
  <?php endif ?>
</article>
