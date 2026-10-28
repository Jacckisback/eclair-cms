<?php
defined('ECLAIR') || exit;

use Eclair\Content;
use Eclair\Icons;
use Eclair\Settings;

$cls = static fn (int $s): string => $s >= 80 ? 'good' : ($s >= 50 ? 'ok' : 'bad');
$filters = [
    '' => t('Tout'), 'type=page' => t('Pages'), 'type=post' => t('Articles'),
    'status=draft' => t('Brouillons'), 'status=scheduled' => t('Programmés'),
];
$currentFilter = $v['type'] !== '' ? 'type=' . $v['type'] : ($v['status'] !== '' ? 'status=' . $v['status'] : '');
$home = (string) Settings::get('home');
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Contenus')) ?></h1><p><?= e(t('Pages et articles de ton site.')) ?></p></div>
    <div class="row">
      <a class="btn" href="<?= e(admin_url('new/page')) ?>"><?= Icons::svg('plus') ?><?= e(t('Page')) ?></a>
      <?php if (Settings::get('blog.enabled')): ?><a class="btn btn-primary" href="<?= e(admin_url('new/post')) ?>"><?= Icons::svg('pen') ?><?= e(t('Article')) ?></a><?php endif ?>
    </div>
  </div>
  <div class="row between">
    <nav class="filters" aria-label="<?= e(t('Filtres')) ?>">
      <?php foreach ($filters as $query => $label): ?>
        <a href="<?= e(admin_url('content' . ($query !== '' ? '?' . $query : ''))) ?>"<?= $query === $currentFilter ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach ?>
    </nav>
    <form method="get" action="<?= e(admin_url('content')) ?>" role="search">
      <label class="sr-only" for="q"><?= e(t('Rechercher')) ?></label>
      <input id="q" type="search" name="q" value="<?= e($v['q']) ?>" placeholder="<?= e(t('Rechercher…')) ?>">
    </form>
  </div>
  <?php if (!$v['entries']): ?>
    <div class="card empty-state"><span class="emoji">✍️</span><p><?= e(t('Rien par ici pour l’instant.')) ?></p>
      <a class="btn btn-primary" href="<?= e(admin_url('new/' . (Settings::get('blog.enabled') ? 'post' : 'page'))) ?>"><?= e(t('Créer un contenu')) ?></a></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="list">
      <thead><tr><th><?= e(t('Titre')) ?></th><th><?= e(t('Statut')) ?></th><th>SEO</th><th>GEO</th><th><?= e(t('Modifié')) ?></th><th><span class="sr-only"><?= e(t('Actions')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($v['entries'] as $e): ?>
        <?php $visible = Content::visible($e); $path = Content::path($e); ?>
        <tr data-row="<?= e($e['id']) ?>">
          <td class="title-cell">
            <a href="<?= e(admin_url('edit/' . $e['id'])) ?>"><?= e($e['title']) ?></a>
            <small><?= e($e['type'] === 'post' ? t('Article') : t('Page')) ?><?= $e['id'] === $home ? ' · 🏠 ' . e(t('Accueil')) : '' ?> · <?= e($path) ?><?= $e['lang'] !== Settings::get('lang') ? ' · ' . e(strtoupper($e['lang'])) : '' ?><?= $e['noindex'] ? ' · noindex' : '' ?></small>
          </td>
          <td><?= $visible ? '<span class="badge ok">' . e(t('Publié')) . '</span>' : ($e['status'] === 'published' ? '<span class="badge brand">' . e(t('Programmé')) . ' · ' . e(fmt_date((int) $e['date'], \Eclair\I18n::lang())) . '</span>' : '<span class="badge">' . e(t('Brouillon')) . '</span>') ?></td>
          <td><span class="score <?= $cls((int) $e['score']['seo']) ?>"><?= (int) $e['score']['seo'] ?></span></td>
          <td><span class="score <?= $cls((int) $e['score']['geo']) ?>"><?= (int) $e['score']['geo'] ?></span></td>
          <td class="small muted"><?= e(ago((int) $e['updated'])) ?></td>
          <td>
            <div class="actions">
              <?php if ($visible): ?><a class="btn btn-sm btn-ghost btn-icon" href="<?= e(path_to($path)) ?>" target="_blank" rel="noopener" title="<?= e(t('Voir')) ?>" aria-label="<?= e(t('Voir')) ?>"><?= Icons::svg('eye') ?></a><?php endif ?>
              <button class="btn btn-sm btn-ghost btn-icon" type="button" data-duplicate="<?= e($e['id']) ?>" title="<?= e(t('Dupliquer')) ?>" aria-label="<?= e(t('Dupliquer')) ?>"><?= Icons::svg('copy') ?></button>
              <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="button" data-delete="<?= e($e['id']) ?>" data-title="<?= e($e['title']) ?>" data-visible="<?= $visible ? '1' : '' ?>" title="<?= e(t('Supprimer')) ?>" aria-label="<?= e(t('Supprimer')) ?>"><?= Icons::svg('trash') ?></button>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
</div>
