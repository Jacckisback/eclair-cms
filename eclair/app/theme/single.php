<?php
/**
 * Page ou article.
 * Variables : item, html, toc, author, category, crumbs, related, translations, featured, words, h1_in_content, lang
 */
defined('ECLAIR') || exit;

use Eclair\Blocks;
use Eclair\Categories;
use Eclair\Content;
use Eclair\Media;
use Eclair\Theme;

$item = $v['item'];
$lang = $v['lang'];
$isPost = $item['type'] === 'post';
$author = $v['author'];
$category = $v['category'];
$edit = !empty($v['edit']);
?>
<article class="content <?= $isPost ? 'post' : 'page' ?>">
  <header class="entry-header">
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
    <?php if ($isPost && $category): ?>
      <p><a class="chip" href="<?= e(path_to(Categories::path($category))) ?>"><?= e($category['name']) ?></a></p>
    <?php endif ?>
    <?php if (!$v['h1_in_content']): ?>
      <h1<?= $item['options']['hide_title'] ? ' class="sr-only"' : '' ?><?= Blocks::ea('ef', 'title', $edit) ?>><?= e($item['title']) ?></h1>
    <?php endif ?>
    <?php if ($isPost): ?>
      <p class="meta">
        <?php if ($author): ?>
          <span class="byline">
            <?php $avatar = Media::get($author['avatar']); ?>
            <?php if ($avatar): ?><img src="<?= e(path_to(Media::thumb($avatar))) ?>" alt="" width="36" height="36"><?php endif ?>
            <span><?= e(Theme::t('by', $lang)) ?> <a href="<?= e(path_to(Theme::authorPath($author))) ?>" rel="author"><?= e($author['name']) ?></a></span>
          </span>
        <?php endif ?>
        <span><?= e(Theme::t('published_on', $lang)) ?> <time datetime="<?= e(date('c', (int) $item['date'])) ?>"><?= e(fmt_date((int) $item['date'], $lang)) ?></time></span>
        <?php if ((int) $item['updated'] - (int) $item['date'] > 86400): ?>
          <span><?= e(Theme::t('updated_on', $lang)) ?> <time datetime="<?= e(date('c', (int) $item['updated'])) ?>"><?= e(fmt_date((int) $item['updated'], $lang)) ?></time></span>
        <?php endif ?>
        <span><?= e(Theme::t('reading', $lang, ['n' => Content::readingTime($v['words'])])) ?></span>
      </p>
    <?php endif ?>
    <?php if ($v['translations']): ?>
      <p class="langs"><span><?= e(Theme::t('read_in', $lang)) ?> :</span>
        <?php foreach ($v['translations'] as $code => $t): ?>
          <?php if ($t['id'] !== $item['id']): ?>
            <a href="<?= e(path_to(Content::path($t))) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>"><?= e(Content::LANGS[$code] ?? $code) ?></a>
          <?php endif ?>
        <?php endforeach ?>
      </p>
    <?php endif ?>
  </header>

  <?php if ($v['featured']): ?>
    <figure class="featured"<?= Blocks::ea('ef', 'image', $edit) ?>><?= Media::picture($v['featured'], (string) $v['featured']['alt'], '(min-width: 72rem) 68rem, 100vw', true) ?></figure>
  <?php endif ?>

  <?php if ($item['summary'] !== ''): ?>
    <aside class="key" aria-label="<?= e(Theme::t('key_points', $lang)) ?>">
      <p class="key-title"><?= e(Theme::t('key_points', $lang)) ?></p>
      <p<?= Blocks::ea('ef', 'summary', $edit) ?>><?= nl2br(e($item['summary'])) ?></p>
    </aside>
  <?php endif ?>

  <?php if ($v['toc']): ?>
    <details class="toc"<?= count($v['toc']) <= 8 ? ' open' : '' ?>>
      <summary><?= e(Theme::t('toc', $lang)) ?></summary>
      <ol>
        <?php foreach ($v['toc'] as $h): ?>
          <li<?= $h['level'] === 3 ? ' class="l3"' : '' ?>><a href="#<?= e($h['id']) ?>"><?= e($h['text']) ?></a></li>
        <?php endforeach ?>
      </ol>
    </details>
  <?php endif ?>

  <?= $v['html'] ?>

  <?php if ($isPost && $author): ?>
    <aside class="author" aria-label="<?= e(Theme::t('about_author', $lang)) ?>">
      <?php $avatar = Media::get($author['avatar']); ?>
      <?php if ($avatar): ?><img src="<?= e(path_to(Media::thumb($avatar))) ?>" alt="<?= e($author['name']) ?>" width="72" height="72" loading="lazy"><?php endif ?>
      <div>
        <p class="author-name"><a href="<?= e(path_to(Theme::authorPath($author))) ?>"><?= e($author['name']) ?></a><?= $author['job'] !== '' ? ' · ' . e($author['job']) : '' ?></p>
        <?php if ($author['bio'] !== ''): ?><p><?= nl2br(e($author['bio'])) ?></p><?php endif ?>
        <?php if ($author['links']): ?>
          <p class="author-links">
            <?php foreach ($author['links'] as $link): ?>
              <a href="<?= e($link) ?>" rel="me noopener"><?= e(preg_replace('~^www\.~', '', (string) parse_url($link, PHP_URL_HOST))) ?></a>
            <?php endforeach ?>
          </p>
        <?php endif ?>
      </div>
    </aside>
  <?php endif ?>

  <?php if ($v['related']): ?>
    <section class="related" aria-labelledby="related-title">
      <h2 id="related-title"><?= e(Theme::t('related', $lang)) ?></h2>
      <?= Theme::cards($v['related'], $lang, 3) ?>
    </section>
  <?php endif ?>
</article>
