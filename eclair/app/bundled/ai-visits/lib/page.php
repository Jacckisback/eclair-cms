<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Robots IA : vue de la fonction « ai-visits » (visites notées par index.php, voir lib/AiVisits.php). */
defined('ECLAIR') || exit;

use Eclair\Icons;

$r = $v['report'];
$kinds = [
    'user' => [t('Consultations à la demande'), t('Quelqu’un a posé une question à une IA, qui est venue lire ta page pour répondre : une citation en puissance.')],
    'search' => [t('Indexation des moteurs de réponse'), t('ChatGPT, Claude ou Perplexity rangent ta page dans leur index, pour la citer plus tard.')],
    'train' => [t('Entraînement des modèles'), t('Collecte de textes pour entraîner de futurs modèles d’IA.')],
];
$max = max([1, ...array_values(array_map('array_sum', $r['days']))]);
$when = static fn (int $ts): string => $ts ? ago($ts) : '—';
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Robots IA')) ?></h1><p><?= e(t('Quelles IA lisent ton site, quelles pages, et pour quel usage.')) ?></p></div>
    <nav class="filters" aria-label="<?= e(t('Période')) ?>">
      <?php foreach ([7, 30, 90] as $d): ?>
        <a href="<?= e(admin_url('x/ai-visits?days=' . $d)) ?>"<?= $d === $v['days'] ? ' aria-current="page"' : '' ?>><?= e(t('{n} jours', ['n' => $d])) ?></a>
      <?php endforeach ?>
    </nav>
  </div>

  <?php if (!$v['on']): ?>
    <div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('La mesure est coupée : active-la dans Réglages › IA & GEO pour voir les visites des robots IA.')) ?> <a href="<?= e(admin_url('settings?tab=geo')) ?>"><?= e(t('Réglages')) ?></a></p></div>
  <?php endif ?>

  <div class="grid-3 ai-kinds">
    <?php foreach ($kinds as $k => [$label, $hint]): ?>
      <section class="card ai-kind" data-kind="<?= $k ?>">
        <h2><span class="dot"></span><?= e($label) ?></h2>
        <p class="ai-num num"><?= (int) $r['kinds'][$k] ?></p>
        <p class="small muted"><?= e($hint) ?></p>
      </section>
    <?php endforeach ?>
  </div>

  <?php if (!$r['total']): ?>
    <div class="card empty-state"><span class="ico"><?= Icons::svg('bot') ?></span>
      <p><?= e(t('Aucune visite de robot IA sur cette période. Les premières arrivent souvent en quelques jours : un site indexé par Google, un llms.txt et des pages bien structurées les attirent.')) ?></p></div>
  <?php else: ?>
    <?php if ($v['policy'] !== 'open' && $r['kinds']['train'] > 0): ?>
      <div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Ton robots.txt demande aux robots d’entraînement de ne pas lire tes pages, et certains sont venus quand même : ils ignorent la consigne, ou se font passer pour d’autres.')) ?></p></div>
    <?php endif ?>

    <section class="card">
      <div class="card-head"><h2><?= e(t('Visites par jour')) ?></h2><span class="small muted"><?= e(t('{n} visites', ['n' => $r['total']])) ?> · robots.txt : <?= (int) $r['robots_txt'] ?></span></div>
      <div class="ai-days" role="img" aria-label="<?= e(t('Visites par jour')) ?>">
        <?php foreach ($r['days'] as $day => $c): ?>
          <span class="ai-day" title="<?= e(fmt_date((int) strtotime($day), \Eclair\I18n::lang()) . ' · ' . array_sum($c)) ?>">
            <?php foreach (['train', 'search', 'user'] as $k): ?><i data-kind="<?= $k ?>" data-height="<?= round($c[$k] / $max * 100, 1) ?>"></i><?php endforeach ?>
          </span>
        <?php endforeach ?>
      </div>
    </section>

    <div class="grid-2">
      <section class="card">
        <h2><?= e(t('Par robot')) ?></h2>
        <div class="table-wrap"><table class="list">
          <thead><tr><th><?= e(t('Robot')) ?></th><th><?= e(t('Usage')) ?></th><th class="right"><?= e(t('Visites')) ?></th><th><?= e(t('Dernière')) ?></th></tr></thead>
          <tbody>
          <?php foreach ($r['bots'] as $name => $b): ?>
            <tr><td class="ai-bot"><b><?= e($name) ?></b><small class="muted"> · <?= e($b['owner']) ?></small></td>
              <td><span class="ai-tag" data-kind="<?= e($b['kind']) ?>"><?= e($kinds[$b['kind']][0]) ?></span></td>
              <td class="right num"><?= (int) $b['count'] ?></td><td class="small muted"><?= e($when((int) $b['last'])) ?></td></tr>
          <?php endforeach ?>
          </tbody></table></div>
      </section>
      <section class="card">
        <h2><?= e(t('Pages les plus lues')) ?></h2>
        <div class="table-wrap"><table class="list">
          <thead><tr><th><?= e(t('Page')) ?></th><th class="right" title="<?= e($kinds['user'][0]) ?>"><?= e(t('À la demande')) ?></th><th class="right"><?= e(t('Visites')) ?></th></tr></thead>
          <tbody>
          <?php foreach (array_slice($r['pages'], 0, 15, true) as $path => $p): ?>
            <tr><td class="ai-path"><a href="<?= e(path_to((string) $path)) ?>" target="_blank" rel="noopener"><?= e((string) $path) ?></a></td>
              <td class="right num"><?= (int) $p['user'] ?></td><td class="right num"><?= (int) $p['count'] ?></td></tr>
          <?php endforeach ?>
          </tbody></table></div>
      </section>
    </div>

    <?php if ($v['unread']): ?>
      <section class="card">
        <h2><?= e(t('Pages encore jamais lues par une IA')) ?></h2>
        <p class="small muted"><?= e(t('Relie-les depuis tes pages les plus lues, ajoute-leur une FAQ et un résumé « L’essentiel » : elles ont plus de chances d’être lues et citées.')) ?></p>
        <ul class="ai-unread">
          <?php foreach ($v['unread'] as $u): ?>
            <li><a href="<?= e(admin_url('edit/' . $u['id'])) ?>"><?= e($u['title']) ?></a> <small class="muted"><?= e($u['path']) ?></small></li>
          <?php endforeach ?>
        </ul>
      </section>
    <?php endif ?>
  <?php endif ?>

  <p class="small muted"><?= e(t('Identité déclarée par chaque robot : un faux robot peut se faire passer pour un vrai. Aucune adresse IP ni aucun visiteur humain n’est enregistré ; journal limité à 4 Mo par mois, effacé après 13 mois.')) ?></p>
</div>
