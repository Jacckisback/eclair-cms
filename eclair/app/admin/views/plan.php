<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Planning éditorial : calendrier du mois, idées à venir, propositions et rédaction par l'IA (voir app/lib/Plan.php). */
defined('ECLAIR') || exit;

use Eclair\Ai;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Security;

$lang = I18n::lang();
$first = $v['first'];
$ym = date('Y-m', $first);
$prev = date('Y-m', (int) strtotime('-1 month', $first));
$next = date('Y-m', (int) strtotime('+1 month', $first));
$today = date('Y-m-d');
$week = [
    'fr' => ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'], 'es' => ['lun.', 'mar.', 'mié.', 'jue.', 'vie.', 'sáb.', 'dom.'],
    'de' => ['Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.', 'So.'], 'it' => ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'],
    'pt' => ['seg.', 'ter.', 'qua.', 'qui.', 'sex.', 'sáb.', 'dom.'], 'zh' => ['一', '二', '三', '四', '五', '六', '日'],
][$lang] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$states = [
    'idea' => [t('Idée'), ''], 'draft' => [t('Brouillon'), 'warn'], 'scheduled' => [t('Programmé'), 'brand'], 'published' => [t('Publié'), 'ok'],
];
$link = static fn (array $e): string => $e['content'] !== '' ? admin_url('edit/' . $e['content']) : '#idea-' . $e['plan'];
$tokens = static fn (int $n): string => number_format($n, 0, ',', "\u{202F}");
$cats = array_column($v['categories'], 'name', 'id');
$offset = (int) date('N', $first) - 1;
$count = (int) date('t', $first);
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Planning éditorial')) ?></h1><p><?= e(t('Tes prochains articles, jour par jour : ajoute tes idées, laisse l’IA en proposer d’après ton site et les rédiger en brouillon.')) ?></p></div>
    <a class="btn" href="#plan-add"><?= Icons::svg('plus') ?><?= e(t('Ajouter une idée')) ?></a>
  </div>

  <?php if (!$v['blog']): ?>
    <div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Le blog est désactivé : active-le dans Réglages pour publier les articles du planning.')) ?></p></div>
  <?php endif ?>

  <?php if ($v['ai']): ?>
    <section class="card plan-ai">
      <h2><?= Icons::svg('spark') ?><?= e(t('Proposer des idées avec l’IA')) ?></h2>
      <p class="small muted"><?= e(t('L’IA part de ton activité, de tes catégories, des articles déjà écrits et des pages que les IA lisent le plus, puis place les idées dans le calendrier. Rien n’est publié.')) ?></p>
      <form class="plan-ideas" data-plan-ideas data-wait="<?= e(t('L’IA prépare tes idées…')) ?>">
        <label class="field"><span class="label"><?= e(t('Combien d’idées')) ?></span><select name="count"><?php foreach ([4, 6, 8, 12] as $n): ?><option value="<?= $n ?>"<?= $n === 6 ? ' selected' : '' ?>><?= $n ?></option><?php endforeach ?></select></label>
        <label class="field"><span class="label"><?= e(t('Rythme')) ?></span><select name="week"><?php foreach ([1, 2, 3, 5] as $n): ?><option value="<?= $n ?>"<?= $n === 2 ? ' selected' : '' ?>><?= e(t('{n} par semaine', ['n' => $n])) ?></option><?php endforeach ?></select></label>
        <label class="field plan-theme"><span class="label"><?= e(t('Thème (facultatif)')) ?></span><input type="text" name="theme" maxlength="300" placeholder="<?= e(t('Ex. : préparer l’hiver, nos best-sellers…')) ?>"></label>
        <div><button class="btn btn-primary" type="submit"><?= Icons::svg('spark') ?><?= e(t('Proposer')) ?> <small>· ~<?= e($v['ideasCost']) ?></small></button></div>
      </form>
      <p class="small muted"><?= e(t('Modèle : {model}. Coût indicatif : environ {ideas} la série d’idées, {write} par article rédigé.', ['model' => $v['model'], 'ideas' => $v['ideasCost'], 'write' => $v['writeCost']])) ?></p>
    </section>
  <?php else: ?>
    <div class="notice"><span><?= Icons::svg('spark') ?></span><p><?= e(t('Avec l’assistant IA, le planning propose des idées d’articles d’après ton site et les rédige en brouillon.')) ?><?php if ($v['admin']): ?> <a href="<?= e(admin_url('settings?tab=ai')) ?>"><?= e(t('Activer l’assistant IA')) ?></a><?php endif ?></p></div>
  <?php endif ?>

  <section class="card plan-cal" aria-labelledby="plan-month">
    <div class="card-head">
      <h2 id="plan-month"><?= e(fmt_month($first, $lang)) ?></h2>
      <nav class="plan-nav" aria-label="<?= e(t('Mois')) ?>">
        <a class="btn btn-sm btn-ghost btn-icon" href="<?= e(admin_url('plan?m=' . $prev)) ?>" aria-label="<?= e(t('Mois précédent')) ?>"><?= Icons::svg('chevron-left') ?></a>
        <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('plan')) ?>"><?= e(t('Aujourd’hui')) ?></a>
        <a class="btn btn-sm btn-ghost btn-icon" href="<?= e(admin_url('plan?m=' . $next)) ?>" aria-label="<?= e(t('Mois suivant')) ?>"><?= Icons::svg('chevron') ?></a>
      </nav>
    </div>
    <ol class="plan-grid">
      <?php foreach ($week as $d): ?><li class="plan-wd" aria-hidden="true"><?= e($d) ?></li><?php endforeach ?>
      <?php for ($i = 0; $i < $offset; $i++): ?><li class="plan-pad" aria-hidden="true"></li><?php endfor ?>
      <?php for ($d = 1; $d <= $count; $d++): $day = $ym . '-' . sprintf('%02d', $d); $list = $v['days'][$day] ?? []; ?>
        <li class="plan-day<?= $day === $today ? ' today' : '' ?><?= $list ? '' : ' empty' ?><?= $day < $today ? ' past' : '' ?>">
          <span class="plan-date"><b><?= $d ?></b><span class="plan-full"> <?= e(fmt_date((int) strtotime($day), $lang)) ?></span></span>
          <?php foreach ($list as $e): ?>
            <a class="plan-chip" data-kind="<?= e($e['kind']) ?>" href="<?= e($link($e)) ?>" title="<?= e($states[$e['kind']][0] . ' · ' . $e['title']) ?>"><?= e($e['title']) ?></a>
          <?php endforeach ?>
        </li>
      <?php endfor ?>
    </ol>
    <p class="plan-legend small muted">
      <?php foreach ($states as $k => [$label]): ?><span><i class="plan-dot" data-kind="<?= $k ?>"></i><?= e($label) ?></span><?php endforeach ?>
    </p>
  </section>

  <section class="card" aria-labelledby="plan-next">
    <div class="card-head plan-head"><h2 id="plan-next"><?= e(t('À venir')) ?></h2>
      <span class="small muted"><?= e(t('{n} articles du planning déjà en ligne ou programmés', ['n' => $v['done']])) ?></span></div>
    <?php if (!$v['items']): ?>
      <p class="muted"><?= e(t('Aucune idée en attente. Ajoutes-en une ou demande des propositions à l’IA.')) ?></p>
    <?php else: ?>
      <ul class="plan-list">
        <?php foreach ($v['items'] as $item): $draft = $item['state'] === 'draft'; ?>
          <li id="idea-<?= e($item['id']) ?>" class="plan-item">
            <div class="plan-when"><?= $item['date'] !== '' ? e(fmt_date((int) strtotime($item['date']), $lang)) : '<span class="muted">' . e(t('Sans date')) . '</span>' ?>
              <?= $item['date'] !== '' && $item['date'] < $today && !$draft ? '<span class="badge warn">' . e(t('En retard')) . '</span>' : '' ?></div>
            <div class="plan-what">
              <b><?= e($draft && $item['entry'] ? $item['entry']['title'] : $item['title']) ?></b>
              <span class="badge <?= $states[$item['state']][1] ?>"><?= e($states[$item['state']][0]) ?></span>
              <?= $item['source'] === 'ai' ? '<span class="badge brand">' . e(t('IA')) . '</span>' : '' ?>
              <?php if ($item['keyword'] !== '' || $item['category'] !== ''): ?>
                <small class="muted"><?= e(implode(' · ', array_filter([$item['keyword'] !== '' ? t('Mot-clé : {k}', ['k' => $item['keyword']]) : '', $cats[$item['category']] ?? '']))) ?></small>
              <?php endif ?>
              <?php if ($item['brief'] !== '' && !$draft): ?><p class="small"><?= nl2br(e($item['brief'])) ?></p><?php endif ?>
              <?php if ($item['error'] !== '' && !$draft): ?><p class="small plan-error"><?= Icons::svg('alert') ?><?= e($item['error']) ?></p><?php endif ?>
              <?php if ($item['tokens'] > 0): ?><small class="muted"><?= e(t('IA : {tokens} jetons · {cost}', ['tokens' => $tokens((int) $item['tokens']), 'cost' => Ai::money((float) $item['cost'])])) ?></small><?php endif ?>
            </div>
            <div class="plan-actions">
              <?php if ($draft): ?>
                <a class="btn btn-sm btn-primary" href="<?= e(admin_url('edit/' . $item['content'])) ?>"><?= Icons::svg('pen') ?><?= e(t('Relire et programmer')) ?></a>
              <?php else: ?>
                <?php if ($v['ai']): ?>
                  <button class="btn btn-sm btn-primary" type="button" data-plan-write="<?= e($item['id']) ?>" data-wait="<?= e(t('Rédaction en cours… (1 à 2 minutes)')) ?>"><?= Icons::svg('spark') ?><?= e(t('Rédiger')) ?> <small>· ~<?= e($v['writeCost']) ?></small></button>
                <?php endif ?>
                <details class="plan-edit">
                  <summary class="btn btn-sm btn-ghost"><?= e(t('Modifier')) ?></summary>
                  <form method="post" action="<?= e(admin_url('plan')) ?>" class="plan-form"><?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><input type="hidden" name="m" value="<?= e($ym) ?>">
                    <label class="field"><span class="label"><?= e(t('Titre')) ?></span><input type="text" name="title" maxlength="200" required value="<?= e($item['title']) ?>"></label>
                    <label class="field"><span class="label"><?= e(t('Date prévue')) ?></span><input type="date" name="date" value="<?= e($item['date']) ?>"></label>
                    <label class="field"><span class="label"><?= e(t('Mot-clé principal')) ?></span><input type="text" name="keyword" maxlength="80" value="<?= e($item['keyword']) ?>"></label>
                    <?php if ($v['categories']): ?>
                      <label class="field"><span class="label"><?= e(t('Catégorie')) ?></span><select name="category"><option value=""><?= e(t('Aucune')) ?></option>
                        <?php foreach ($v['categories'] as $c): ?><option value="<?= e($c['id']) ?>"<?= $c['id'] === $item['category'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label>
                    <?php endif ?>
                    <label class="field plan-wide"><span class="label"><?= e(t('Angle et notes pour la rédaction')) ?></span><textarea name="brief" rows="3" maxlength="1500"><?= e($item['brief']) ?></textarea></label>
                    <div><button class="btn btn-sm btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button></div>
                  </form>
                </details>
              <?php endif ?>
              <form method="post" action="<?= e(admin_url('plan')) ?>" data-confirm="<?= e(t('Retirer cette idée du planning ? Le brouillon déjà rédigé, s’il existe, est gardé.')) ?>"><?= Security::csrfField() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><input type="hidden" name="m" value="<?= e($ym) ?>">
                <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="submit" aria-label="<?= e(t('Retirer du planning')) ?>"><?= Icons::svg('x') ?></button></form>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <div class="grid-2">
    <section class="card" id="plan-add" aria-labelledby="plan-add-title">
      <h2 id="plan-add-title"><?= e(t('Ajouter une idée')) ?></h2>
      <form method="post" action="<?= e(admin_url('plan')) ?>" class="plan-form"><?= Security::csrfField() ?>
        <input type="hidden" name="action" value="add"><input type="hidden" name="m" value="<?= e($ym) ?>">
        <label class="field plan-wide"><span class="label"><?= e(t('Titre ou sujet')) ?></span><input type="text" name="title" maxlength="200" required></label>
        <label class="field"><span class="label"><?= e(t('Date prévue')) ?></span><input type="date" name="date" min="<?= e($today) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Mot-clé principal')) ?></span><input type="text" name="keyword" maxlength="80"></label>
        <?php if ($v['categories']): ?>
          <label class="field"><span class="label"><?= e(t('Catégorie')) ?></span><select name="category"><option value=""><?= e(t('Aucune')) ?></option>
            <?php foreach ($v['categories'] as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <label class="field plan-wide"><span class="label"><?= e(t('Angle et notes pour la rédaction')) ?></span><textarea name="brief" rows="3" maxlength="1500" placeholder="<?= e(t('À qui s’adresse l’article, la question à laquelle il répond, ce que tu veux absolument y dire…')) ?>"></textarea></label>
        <div><button class="btn btn-primary" type="submit"><?= Icons::svg('plus') ?><?= e(t('Ajouter au planning')) ?></button></div>
      </form>
    </section>

    <section class="card" aria-labelledby="plan-auto-title">
      <h2 id="plan-auto-title"><?= e(t('Rédaction automatique')) ?></h2>
      <p class="small muted"><?= e(t('Quelques jours avant la date prévue, l’IA rédige l’idée en brouillon, sans que tu aies à cliquer. Tu relis, tu complètes, tu programmes.')) ?></p>
      <?php if ($v['admin']): ?>
        <form method="post" action="<?= e(admin_url('plan')) ?>" class="plan-auto"><?= Security::csrfField() ?>
          <input type="hidden" name="action" value="auto"><input type="hidden" name="m" value="<?= e($ym) ?>">
          <label class="switch"><span><strong><?= e(t('Rédiger automatiquement')) ?></strong>
            <small><?= e($v['auto']['on'] && $v['autoBy'] !== '' ? t('Activée par {name}, dans le budget IA du mois.', ['name' => $v['autoBy']]) : t('Dans le budget IA du mois, une idée à la fois.')) ?></small></span>
            <input type="checkbox" name="on" value="1"<?= $v['auto']['on'] ? ' checked' : '' ?><?= $v['ai'] ? '' : ' disabled' ?>></label>
          <label class="field"><span class="label"><?= e(t('Combien de jours avant la date')) ?></span><select name="lead">
            <?php foreach ([1, 2, 3, 5, 7, 14] as $n): ?><option value="<?= $n ?>"<?= $n === (int) $v['auto']['lead'] ? ' selected' : '' ?>><?= e(t('{n} jours', ['n' => $n])) ?></option><?php endforeach ?></select></label>
          <label class="switch"><span><strong><?= e(t('Programmer sans relecture')) ?></strong>
            <small><?= e(t('Déconseillé : Google pénalise les textes publiés en série sans regard humain. Un article qui contient encore « À compléter » attend toujours ta relecture.')) ?></small></span>
            <input type="checkbox" name="publish" value="1"<?= $v['auto']['publish'] ? ' checked' : '' ?>></label>
          <div><button class="btn btn-sm" type="submit"><?= e(t('Enregistrer')) ?></button></div>
        </form>
        <details class="plan-cron small">
          <summary><?= e(t('Rédiger même quand l’administration est fermée')) ?></summary>
          <p class="muted"><?= e(t('L’IA rédige quand un administrateur ouvre l’administration. Pour ne pas en dépendre, fais appeler cette adresse toutes les heures par une tâche planifiée (cron de ton hébergeur, cron-job.org…) :')) ?></p>
          <input type="text" readonly value="<?= e($v['cron']) ?>" class="mono" aria-label="<?= e(t('Adresse de la tâche planifiée')) ?>">
        </details>
      <?php else: ?>
        <p class="small"><?= e($v['auto']['on'] ? t('Activée par un administrateur.') : t('Un administrateur peut l’activer.')) ?></p>
      <?php endif ?>
      <dl class="plan-spend small">
        <div><dt><?= e(t('Dépensé par le planning')) ?></dt><dd><?= e(t('{tokens} jetons · {cost}', ['tokens' => $tokens((int) $v['tokens']), 'cost' => Ai::money((float) $v['cost'])])) ?></dd></div>
        <div><dt><?= e(t('Budget IA du mois')) ?></dt><dd><?= e(Ai::money((float) $v['usage']['cost']) . ($v['budget'] > 0 ? ' / ' . Ai::money($v['budget']) : '')) ?></dd></div>
      </dl>
    </section>
  </div>
</div>
