<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Page d'administration d'une capacité : HTML fourni par la capacité (code de confiance, qui échappe ses données avec e()),
 * ou formulaire de ses réglages généré par le noyau à partir du manifeste (valeurs validées par Extensions::clean()).
 */
defined('ECLAIR') || exit;

use Eclair\Security;
if (!empty($v['raw'])) {
    // Page complète fournie par la fonction (sa propre mise en page, comme les pages du noyau).
    echo $v['html'];
    return;
}
?>
<div class="page">
  <div class="topbar"><div><h1><?= e((string) $v['title']) ?></h1></div></div>
  <?php if (isset($v['fields'])): ?>
  <section class="card">
    <form method="post" action="<?= e(admin_url('x/' . $v['ext'] . '/settings')) ?>"><?= Security::csrfField() ?>
      <?php foreach ($v['fields'] as $f): $val = $v['values'][$f['key']] ?? ''; ?>
        <label class="field"><span class="label"><?= e($f['label']) ?></span>
        <?php if ($f['type'] === 'textarea'): ?>
          <textarea name="<?= e($f['key']) ?>" maxlength="<?= (int) $f['max'] ?>" rows="4"><?= e((string) $val) ?></textarea>
        <?php elseif ($f['type'] === 'bool'): ?>
          <input type="checkbox" name="<?= e($f['key']) ?>" value="1"<?= $val ? ' checked' : '' ?>>
        <?php elseif ($f['type'] === 'select'): ?>
          <select name="<?= e($f['key']) ?>"><?php foreach ($f['options'] as $o): ?><option<?= $o === $val ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach ?></select>
        <?php else: ?>
          <input type="<?= $f['type'] === 'number' ? 'number' : ($f['type'] === 'url' ? 'url' : 'text') ?>" name="<?= e($f['key']) ?>" value="<?= e((string) $val) ?>" maxlength="<?= (int) $f['max'] ?>">
        <?php endif ?></label>
      <?php endforeach ?>
      <button class="btn btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button>
    </form>
  </section>
  <?php else: ?>
    <?= $v['html'] ?>
  <?php endif ?>
</div>
