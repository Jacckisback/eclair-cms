<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
defined('ECLAIR') || exit; ?>
<div class="page-narrow">
  <div class="card empty-state">
    <span class="emoji">🧭</span>
    <h1><?= e($v['title']) ?></h1>
    <p><?= e($v['text']) ?></p>
    <p><a class="btn btn-primary" href="<?= e(admin_url()) ?>"><?= e(t('Retour au tableau de bord')) ?></a></p>
  </div>
</div>
