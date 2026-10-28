<?php
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Redirections')) ?></h1><p><?= e(t('Quand tu changes l’adresse d’un contenu publié, Éclair crée une redirection 301 automatiquement : aucun lien cassé, aucun référencement perdu.')) ?></p></div></div>
  <section class="card">
    <h2><?= e(t('Ajouter une redirection')) ?></h2>
    <form method="post" action="<?= e(admin_url('redirects')) ?>" class="grid-3">
      <?= Security::csrfField() ?>
      <label class="field"><span class="label"><?= e(t('Ancienne adresse')) ?></span><input type="text" name="from" placeholder="/ancienne-page" required></label>
      <label class="field"><span class="label"><?= e(t('Nouvelle adresse')) ?></span><input type="text" name="to" placeholder="/nouvelle-page" required></label>
      <label class="field"><span class="label"><?= e(t('Type')) ?></span><select name="code"><option value="301"><?= e(t('301 — définitive (recommandé)')) ?></option><option value="302"><?= e(t('302 — temporaire')) ?></option></select></label>
      <div><button class="btn btn-primary" type="submit"><?= Icons::svg('plus') ?><?= e(t('Ajouter')) ?></button></div>
    </form>
    <p class="hint"><?= e(t('Pratique pour reprendre les anciennes URL d’un site WordPress ou Wix sans perdre ton référencement.')) ?></p>
  </section>
  <?php if ($v['redirects']): ?>
  <div class="table-wrap">
    <table class="list">
      <thead><tr><th><?= e(t('De')) ?></th><th><?= e(t('Vers')) ?></th><th><?= e(t('Type')) ?></th><th><?= e(t('Utilisations')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($v['redirects'] as $r): ?>
        <tr><td class="mono small"><?= e($r['from']) ?></td><td class="mono small"><?= e($r['to']) ?></td>
          <td><span class="badge"><?= (int) $r['code'] ?></span><?= !empty($r['auto']) ? ' <span class="badge brand">' . e(t('auto')) . '</span>' : '' ?></td>
          <td class="small"><?= (int) $r['hits'] ?></td>
          <td><form method="post" action="<?= e(admin_url('redirects')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="from" value="<?= e($r['from']) ?>">
            <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="submit" aria-label="<?= e(t('Supprimer')) ?>"><?= Icons::svg('trash') ?></button></form></td></tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="card empty-state"><span class="emoji">🔀</span><p><?= e(t('Aucune redirection pour l’instant.')) ?></p></div>
  <?php endif ?>
</div>
