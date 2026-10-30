<?php
/** Grille de cartes d'articles (et de produits dans la recherche). Variables : entries, lang, level (niveau de titre). */
defined('ECLAIR') || exit;

use Eclair\Categories;
use Eclair\Content;
use Eclair\Media;
use Eclair\Shop;

$tag = 'h' . max(2, min(4, (int) $v['level']));
?>
<div class="cards">
<?php foreach ($v['entries'] as $entry): ?>
  <?php $media = Media::get($entry['image']); $cat = Categories::get($entry['category']); ?>
  <article class="card">
    <?php if ($media): ?>
      <div class="card-img"><?= Media::picture($media, '', '(min-width: 72rem) 22rem, (min-width: 40rem) 45vw, 100vw') ?></div>
    <?php endif ?>
    <div class="card-body">
      <p class="card-meta">
        <?php if ($cat): ?><a class="chip" href="<?= e(path_to(Categories::path($cat))) ?>"><?= e($cat['name']) ?></a><?php endif ?>
        <?php if ($entry['type'] === 'product'): ?>
          <?php if (($entry['price'] ?? '') !== ''): ?><span class="card-price"><?= e(Shop::money((string) $entry['price'], $v['lang'])) ?></span><?php endif ?>
        <?php else: ?>
          <time datetime="<?= e(date('c', (int) $entry['date'])) ?>"><?= e(fmt_date((int) $entry['date'], $v['lang'])) ?></time>
        <?php endif ?>
      </p>
      <<?= $tag ?> class="card-title"><a href="<?= e(path_to(Content::path($entry))) ?>"><?= e($entry['title']) ?></a></<?= $tag ?>>
      <?php if ($entry['excerpt'] !== ''): ?><p class="card-excerpt"><?= e($entry['excerpt']) ?></p><?php endif ?>
    </div>
  </article>
<?php endforeach ?>
</div>
