<?php
defined('ECLAIR') || exit;

use Eclair\Categories;
use Eclair\Icons;
use Eclair\Security;

$edit = $v['edit'];
$kind = $edit['kind'] ?? $v['kind'];
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Catégories')) ?></h1><p><?= e($v['shop'] ? t('Chaque catégorie est une page « pilier » qui regroupe des articles ou des produits sur un même thème : idéal pour un cocon sémantique.') : t('Chaque catégorie est une page « pilier » qui regroupe des articles sur un même thème : idéal pour un cocon sémantique.')) ?></p></div></div>
  <div class="grid-2">
    <section class="card">
      <h2><?= e($edit ? t('Modifier la catégorie') : t('Nouvelle catégorie')) ?></h2>
      <form method="post" action="<?= e(admin_url('categories')) ?>">
        <?= Security::csrfField() ?>
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <?php if ($v['shop'] && !$edit): ?>
          <label class="field"><span class="label"><?= e(t('Elle regroupe')) ?></span>
            <select name="kind"><option value="post"<?= $kind === 'post' ? ' selected' : '' ?>><?= e(t('Des articles (blog)')) ?></option><option value="product"<?= $kind === 'product' ? ' selected' : '' ?>><?= e(t('Des produits (boutique)')) ?></option></select></label>
        <?php endif ?>
        <label class="field"><span class="label"><?= e(t('Nom')) ?></span><input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required maxlength="80"></label>
        <label class="field"><span class="label"><?= e(t('Slug (URL)')) ?></span><input type="text" name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="<?= e(t('automatique')) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Description')) ?></span><textarea name="description" rows="4"><?= e($edit['description'] ?? '') ?></textarea>
          <span class="hint"><?= e(t('Affichée en haut de la page de la catégorie : 2 à 4 phrases riches en vocabulaire du thème.')) ?></span></label>
        <label class="field"><span class="label"><?= e(t('Titre SEO (facultatif)')) ?></span><input type="text" name="seo_title" value="<?= e($edit['seo_title'] ?? '') ?>" maxlength="120"></label>
        <div class="row"><button class="btn btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button>
          <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(admin_url('categories')) ?>"><?= e(t('Annuler')) ?></a><?php endif ?></div>
      </form>
    </section>
    <section>
      <?php if (!$v['categories']): ?>
        <div class="card empty-state"><span class="emoji">🏷️</span><p><?= e(t('Aucune catégorie pour l’instant.')) ?></p></div>
      <?php else: ?>
      <div class="table-wrap"><table class="list"><tbody>
        <?php foreach ($v['categories'] as $c): ?>
          <tr><td class="title-cell"><a href="<?= e(admin_url('categories?edit=' . $c['id'])) ?>"><?= e($c['name']) ?></a><small><?= e(Categories::path($c)) ?> · <?= (int) ($v['counts'][$c['id']] ?? 0) ?> <?= e($c['kind'] === 'product' ? t('produit(s)') : t('article(s)')) ?></small></td>
            <td><div class="actions">
              <a class="btn btn-sm btn-ghost btn-icon" href="<?= e(path_to(Categories::path($c))) ?>" target="_blank" rel="noopener" aria-label="<?= e(t('Voir')) ?>"><?= Icons::svg('eye') ?></a>
              <form method="post" action="<?= e(admin_url('categories')) ?>" data-confirm="<?= e($c['kind'] === 'product' ? t('Supprimer cette catégorie ? Les produits resteront publiés, sans catégorie.') : t('Supprimer cette catégorie ? Les articles resteront publiés, sans catégorie.')) ?>">
                <?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="submit" aria-label="<?= e(t('Supprimer')) ?>"><?= Icons::svg('trash') ?></button></form>
            </div></td></tr>
        <?php endforeach ?>
      </tbody></table></div>
      <?php endif ?>
    </section>
  </div>
</div>
