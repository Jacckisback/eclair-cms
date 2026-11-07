<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Gabarit 404 du thème d'exemple (contrat 1). Variables reçues : $v['lang'] (et celles du gabarit layout, qui l'enveloppe).
 * Fonctions du contrat utilisées : e(), path_to(), Theme::t(), Themes::asset().
 */
defined('ECLAIR') || exit;

use Eclair\Theme;
use Eclair\Themes;

$lang = $v['lang'];
$motif = Themes::asset('assets/img/motif.svg');
?>
<section class="wrap atelier-404">
  <?php if ($motif !== ''): ?><img class="motif" src="<?= e($motif) ?>" alt="" width="72" height="72"><?php endif ?>
  <h1><?= e(Theme::t('not_found', $lang)) ?></h1>
  <p><?= e(Theme::t('not_found_text', $lang)) ?></p>
  <p><a href="<?= e(path_to('/')) ?>"><?= e(Theme::t('back_home', $lang)) ?></a></p>
</section>
