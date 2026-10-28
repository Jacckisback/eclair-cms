<?php
/** Champ de sélection d'image (logo, icône, photo…). Variables : name, value, label, hint, media */
defined('ECLAIR') || exit;

use Eclair\Icons;

$m = $v['media'][$v['value']] ?? null;
?>
<div class="field" data-media-field>
  <span class="label"><?= e($v['label']) ?></span>
  <input type="hidden" name="<?= e($v['name']) ?>" value="<?= e($v['value']) ?>">
  <div class="picker-preview" data-preview><?php if ($m): ?><img src="<?= e($m['thumb']) ?>" alt=""><span class="small muted"><?= e($m['name']) ?></span><?php endif ?></div>
  <div class="row">
    <button class="btn btn-sm" type="button" data-pick><?= Icons::svg('image') ?><?= e(t('Choisir une image')) ?></button>
    <button class="btn btn-sm btn-ghost" type="button" data-clear><?= e(t('Retirer')) ?></button>
  </div>
  <?php if (!empty($v['hint'])): ?><span class="hint"><?= e($v['hint']) ?></span><?php endif ?>
</div>
