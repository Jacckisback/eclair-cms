<?php
/** Grille de cartes produit (photo, nom, prix). Variables : entries, lang, level (niveau de titre). */
defined('ECLAIR') || exit;

use Eclair\Content;
use Eclair\Media;
use Eclair\Shop;
use Eclair\Theme;

$tag = 'h' . max(2, min(4, (int) $v['level']));
$lang = $v['lang'];
?>
<div class="cards products">
<?php foreach ($v['entries'] as $entry): ?>
  <?php $media = Media::get($entry['image']); $price = (string) ($entry['price'] ?? ''); $sale = $price !== '' && ($entry['compare'] ?? '') !== '' && (float) $entry['compare'] > (float) $price; ?>
  <article class="card product-card">
    <?php if ($media): ?>
      <div class="card-img"><?= Media::picture($media, '', '(min-width: 72rem) 17rem, (min-width: 40rem) 30vw, 50vw') ?></div>
    <?php endif ?>
    <div class="card-body">
      <<?= $tag ?> class="card-title"><a href="<?= e(path_to(Content::path($entry))) ?>"><?= e($entry['title']) ?></a></<?= $tag ?>>
      <p class="card-price">
        <?php if ($price !== ''): ?><span class="price-now"><?= e(!empty($entry['from']) ? Theme::t('from_price', $lang, ['price' => Shop::money($price, $lang)]) : Shop::money($price, $lang)) ?></span><?php endif ?>
        <?php if ($sale): ?><s class="price-was"><span class="sr-only"><?= e(Theme::t('was_price', $lang)) ?> : </span><?= e(Shop::money((string) $entry['compare'], $lang)) ?></s><?php endif ?>
        <?php if (($entry['availability'] ?? '') === 'out_of_stock'): ?><span class="badge"><?= e(Theme::t('out_of_stock', $lang)) ?></span><?php elseif ($sale): ?><span class="badge badge-sale"><?= e(Theme::t('sale', $lang)) ?></span><?php endif ?>
      </p>
    </div>
  </article>
<?php endforeach ?>
</div>
