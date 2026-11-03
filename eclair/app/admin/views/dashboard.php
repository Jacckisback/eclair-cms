<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
defined('ECLAIR') || exit;

use Eclair\Content;
use Eclair\Icons;
use Eclair\Security;
use Eclair\Settings;

$user = $v['user'];
$first = explode(' ', (string) $user['name'])[0];
$done = count(array_filter($v['todo'], fn ($t) => $t['done']));
$total = count($v['todo']);
$next = null;
foreach ($v['todo'] as $i => $item) {
    if (!$item['done'] && $item['url'] !== '') {
        $next = $i;
        break;
    }
}
$hour = (int) date('G');
$hello = $hour < 5 || $hour >= 18 ? t('Bonsoir') : t('Bonjour');
$cls = static fn (int $s): string => $s >= 80 ? 'good' : ($s >= 50 ? 'ok' : 'bad');
$meter = static fn (int $s): string => '<meter min="0" max="100" low="50" high="80" optimum="100" value="' . $s . '">' . $s . '/100</meter>';
?>
<div class="page dash">
  <header class="dash-head hello">
    <div>
      <h1><?= e($hello) ?> <?= e($first) ?></h1>
      <p><?= e(fmt_date(time(), \Eclair\I18n::lang())) ?> · <?= e(t('Que veux-tu publier aujourd’hui ?')) ?></p>
    </div>
    <div class="row">
      <a class="btn" href="<?= e(admin_url('new/page')) ?>"><?= Icons::svg('file') ?><?= e(t('Nouvelle page')) ?></a>
      <?php if (\Eclair\Shop::enabled()): ?>
        <a class="btn" href="<?= e(admin_url('new/product')) ?>"><?= Icons::svg('bag') ?><?= e(t('Nouveau produit')) ?></a>
      <?php endif ?>
      <?php if (Settings::get('blog.enabled')): ?>
        <a class="btn btn-primary" href="<?= e(admin_url('new/post')) ?>"><?= Icons::svg('pen') ?><?= e(t('Écrire un article')) ?></a>
      <?php endif ?>
    </div>
  </header>

  <?php if ($user['role'] === 'admin' && ($update = \Eclair\Update::pending())): ?>
  <div class="notice" data-update-banner><span><?= Icons::svg('zap') ?></span><p><?= e(t('Éclair {v} est disponible (tu as la {current}).', ['v' => $update['version'], 'current' => ECLAIR_VERSION])) ?>
    <a href="<?= e(admin_url('update')) ?>"><?= e(t('Voir la mise à jour')) ?><?= Icons::svg('arrow') ?></a></p></div>
  <?php endif ?>

  <div class="dash-grid">
    <div class="dash-main">
      <?php if ($v['onboarding']): ?>
      <section class="card onboard" aria-labelledby="onboard-title">
        <div class="card-head">
          <div>
            <h2 id="onboard-title"><?= e(t('Premiers pas')) ?><span class="count-of"><?= $done ?>/<?= $total ?></span></h2>
            <p class="muted small"><?= e($done === $total ? t('Tout est prêt, bravo ! Ton site est au top.') : t('Quelques minutes suffisent pour un site complet, rapide et bien référencé.')) ?></p>
          </div>
          <form method="post" action="<?= e(admin_url('onboarding')) ?>"><?= Security::csrfField() ?>
            <button class="btn btn-sm btn-ghost" type="submit"><?= e($done === $total ? t('Terminer') : t('Masquer')) ?></button></form>
        </div>
        <div class="segments" role="progressbar" aria-label="<?= e(t('Premiers pas')) ?>" aria-valuemin="0" aria-valuemax="<?= $total ?>" aria-valuenow="<?= $done ?>">
          <?php foreach ($v['todo'] as $i => $item): ?><i class="<?= $item['done'] ? 'on' : ($i === $next ? 'next' : '') ?>"></i><?php endforeach ?>
        </div>
        <ol class="todo">
          <?php foreach ($v['todo'] as $i => $item): ?>
            <li class="<?= $item['done'] ? 'done' : ($i === $next ? 'next' : '') ?>">
              <span class="tick"<?= $item['done'] ? ' role="img" aria-label="' . e(t('Fait')) . '"' : ' aria-hidden="true"' ?>><?= $item['done'] ? Icons::svg('check') : '' ?></span>
              <span class="todo-text">
                <?php if (!$item['done'] && $item['url'] !== ''): ?>
                  <a class="todo-label" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
                <?php else: ?>
                  <span class="todo-label"><?= e($item['label']) ?></span>
                <?php endif ?>
                <?php if (!$item['done'] && ($item['hint'] ?? '') !== ''): ?><small><?= e($item['hint']) ?></small><?php endif ?>
              </span>
              <?php if ($i === $next): ?>
                <a class="btn btn-primary btn-sm" href="<?= e($item['url']) ?>" tabindex="-1" aria-hidden="true"><?= e(t('Commencer')) ?><?= Icons::svg('arrow') ?></a>
              <?php elseif (!$item['done'] && $item['url'] !== ''): ?>
                <?= Icons::svg('chevron') ?>
              <?php endif ?>
            </li>
          <?php endforeach ?>
        </ol>
      </section>
      <?php endif ?>

      <?php if (\Eclair\Ai::allowed($user)): ?>
      <form class="card ai-prompt" data-ai-prompt>
        <label class="ai-prompt-title" for="ai-quick"><?= Icons::svg('spark') ?><?= e(t('Que veux-tu créer aujourd’hui ?')) ?></label>
        <div class="ai-input">
          <textarea id="ai-quick" rows="2" placeholder="<?= e(t('Ex. : écris un article sur l’entretien d’un vélo électrique, avec une image.')) ?>"></textarea>
          <button class="btn btn-primary" type="submit"><?= e(t('Envoyer')) ?></button>
        </div>
        <div class="ai-chips">
          <?php foreach (array_merge([
              ['pen', t('Écrire un article'), t('Écris un article sur ')],
              ['users', t('Ajouter un membre à l’équipe'), t('Ajoute à la page équipe : Prénom Nom, fonction, avec la photo jointe.')],
              ['bulb', t('5 idées d’articles'), t('Propose-moi 5 idées d’articles pour attirer de nouveaux clients.')],
              ['file', t('Créer une page'), t('Crée une page « Nos services » qui présente ')],
          ], \Eclair\Shop::enabled() ? [['bag', t('Fiche produit'), t('Crée la fiche produit de la photo jointe. Prix : ')]] : []) as [$icon, $label, $idea]): ?>
            <button class="ai-chip" type="button" data-ai-idea="<?= e($idea) ?>"><?= Icons::svg($icon) ?><?= e($label) ?></button>
          <?php endforeach ?>
        </div>
      </form>
      <?php elseif ($user['role'] === 'admin' && \Eclair\Settings::get('ai.provider') === ''): ?>
      <div class="notice"><span><?= Icons::svg('spark') ?></span><p><?= e(t('Nouveau : l’assistant IA rédige articles et pages dans le ton de ton site, avec images. Tu relis, tu publies.')) ?>
        <a href="<?= e(admin_url('settings?tab=ai')) ?>"><?= e(t('Activer l’assistant')) ?><?= Icons::svg('arrow') ?></a></p></div>
      <?php endif ?>

      <section class="card recent">
        <div class="card-head"><h2><?= e(t('Modifié récemment')) ?></h2><a class="btn btn-sm" href="<?= e(admin_url('content')) ?>"><?= e(t('Tout voir')) ?></a></div>
        <table class="list"><tbody>
          <?php foreach ($v['recent'] as $e): ?>
            <tr>
              <td class="title-cell"><a href="<?= e(admin_url('edit/' . $e['id'])) ?>"><?= e($e['title']) ?></a><small><?= e(['post' => t('Article'), 'product' => t('Produit')][$e['type']] ?? t('Page')) ?> · <?= e(ago((int) $e['updated'])) ?></small></td>
              <td class="right"><?= Content::visible($e) ? '<span class="badge ok">' . e(t('Publié')) . '</span>' : ($e['status'] !== 'published' ? '<span class="badge">' . e(t('Brouillon')) . '</span>'
                  : ((int) $e['date'] > time() ? '<span class="badge brand">' . e(t('Programmé')) . '</span>' : '<span class="badge warn">' . e(t('Boutique fermée')) . '</span>')) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody></table>
      </section>
    </div>

    <aside class="dash-side">
      <section class="card" aria-labelledby="site-title">
        <h2 id="site-title"><?= e(t('Ton site')) ?></h2>
        <ul class="kv">
          <li><a href="<?= e(admin_url('content?type=post')) ?>"><span><?= e(t('Articles publiés')) ?></span><b><?= $v['stats']['posts'] ?></b></a></li>
          <li><a href="<?= e(admin_url('content?type=page')) ?>"><span><?= e(t('Pages publiées')) ?></span><b><?= $v['stats']['pages'] ?></b></a></li>
          <li><a href="<?= e(admin_url('content?status=draft')) ?>"><span><?= e(t('Brouillons')) ?></span><b><?= $v['stats']['drafts'] ?></b></a></li>
          <li><a href="<?= e(admin_url('messages')) ?>"><span><?= e(t('Messages non lus')) ?></span><b><?= $v['stats']['messages'] ?></b></a></li>
        </ul>
        <div class="gauges">
          <div class="gauge"><span><?= e(t('Score SEO moyen')) ?></span><b><?= $v['stats']['seo'] ?><small>/100</small></b><?= $meter($v['stats']['seo']) ?></div>
          <div class="gauge"><span><?= e(t('Score GEO (IA) moyen')) ?></span><b><?= $v['stats']['geo'] ?><small>/100</small></b><?= $meter($v['stats']['geo']) ?></div>
        </div>
      </section>

      <section class="card" aria-labelledby="improve-title">
        <h2 id="improve-title"><?= e(t('À améliorer en priorité')) ?></h2>
        <?php if (!$v['improve']): ?>
          <p class="muted small"><?= e(t('Tous tes contenus publiés ont d’excellentes notes. Continue comme ça !')) ?></p>
        <?php else: ?>
          <ul class="rank">
            <?php foreach ($v['improve'] as $e): ?>
              <li><a href="<?= e(admin_url('edit/' . $e['id'])) ?>" title="<?= e($e['title']) ?>"><?= e($e['title']) ?></a>
                <span class="scores-pair"><span class="score <?= $cls($e['score']['seo']) ?>"><small>SEO</small><?= (int) $e['score']['seo'] ?></span><span class="score <?= $cls($e['score']['geo']) ?>"><small>GEO</small><?= (int) $e['score']['geo'] ?></span></span></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      </section>

      <section class="card" aria-labelledby="health-title">
        <h2 id="health-title"><?= e(t('Santé du site')) ?></h2>
        <?php if ($v['health']): ?>
        <ul class="health">
          <?php foreach ($v['health'] as $h): ?>
            <li class="<?= $h['ok'] ? 'ok' : 'warn' ?>"><?= Icons::svg($h['ok'] ? 'circle-check' : 'alert') ?><span><?= e($h['label']) ?></span></li>
          <?php endforeach ?>
        </ul>
        <?php endif ?>
        <p class="files-line"><?= e(t('Fichiers générés automatiquement :')) ?>
          <a href="<?= e(path_to('/sitemap.xml')) ?>" target="_blank" rel="noopener">sitemap.xml</a> ·
          <a href="<?= e(path_to('/robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a> ·
          <a href="<?= e(path_to('/llms.txt')) ?>" target="_blank" rel="noopener">llms.txt</a> ·
          <a href="<?= e(path_to('/feed.xml')) ?>" target="_blank" rel="noopener">RSS</a></p>
      </section>
    </aside>
  </div>
</div>
