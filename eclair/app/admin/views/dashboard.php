<?php
defined('ECLAIR') || exit;

use Eclair\Content;
use Eclair\Icons;
use Eclair\Security;
use Eclair\Settings;

$user = $v['user'];
$first = explode(' ', (string) $user['name'])[0];
$done = count(array_filter($v['todo'], fn ($t) => $t['done']));
$total = count($v['todo']);
$pct = (int) round($done / $total * 100);
$hour = (int) date('G');
$hello = $hour < 5 || $hour >= 18 ? t('Bonsoir') : t('Bonjour');
$cls = static fn (int $s): string => $s >= 80 ? 'good' : ($s >= 50 ? 'ok' : 'bad');
?>
<div class="page">
  <div class="hello">
    <div>
      <h1><?= e($hello) ?> <?= e($first) ?> 👋</h1>
      <p class="muted"><?= e(fmt_date(time(), \Eclair\I18n::lang())) ?> · <?= e(t('Que veux-tu publier aujourd’hui ?')) ?></p>
    </div>
    <div class="row">
      <a class="btn" href="<?= e(admin_url('new/page')) ?>"><?= Icons::svg('file') ?><?= e(t('Nouvelle page')) ?></a>
      <?php if (Settings::get('blog.enabled')): ?>
        <a class="btn btn-primary" href="<?= e(admin_url('new/post')) ?>"><?= Icons::svg('pen') ?><?= e(t('Écrire un article')) ?></a>
      <?php endif ?>
    </div>
  </div>

  <?php if (\Eclair\Ai::allowed($user)): ?>
  <form class="card ai-prompt" data-ai-prompt>
    <label class="ai-prompt-title" for="ai-quick"><?= Icons::svg('spark') ?><?= e(t('Que veux-tu créer aujourd’hui ?')) ?></label>
    <div class="ai-input">
      <textarea id="ai-quick" rows="2" placeholder="<?= e(t('Ex. : écris un article sur l’entretien d’un vélo électrique, avec une image.')) ?>"></textarea>
      <button class="btn btn-primary" type="submit"><?= e(t('Envoyer')) ?></button>
    </div>
    <div class="ai-chips">
      <?php foreach ([
          ['✍️', t('Écrire un article'), t('Écris un article sur ')],
          ['👥', t('Ajouter un membre à l’équipe'), t('Ajoute à la page équipe : Prénom Nom, fonction, avec la photo jointe.')],
          ['💡', t('5 idées d’articles'), t('Propose-moi 5 idées d’articles pour attirer de nouveaux clients.')],
          ['📄', t('Créer une page'), t('Crée une page « Nos services » qui présente ')],
      ] as [$emoji, $label, $idea]): ?>
        <button class="ai-chip" type="button" data-ai-idea="<?= e($idea) ?>"><?= $emoji ?> <?= e($label) ?></button>
      <?php endforeach ?>
    </div>
  </form>
  <?php elseif ($user['role'] === 'admin' && \Eclair\Settings::get('ai.provider') === ''): ?>
  <div class="notice"><span>✨</span><p><?= e(t('Nouveau : l’assistant IA rédige articles et pages dans le ton de ton site, avec images. Tu relis, tu publies.')) ?>
    <a href="<?= e(admin_url('settings?tab=ai')) ?>"><?= e(t('Activer l’assistant')) ?> →</a></p></div>
  <?php endif ?>

  <?php if ($v['onboarding']): ?>
  <section class="card onboard" aria-labelledby="onboard-title">
    <div class="card-head">
      <div>
        <h2 id="onboard-title">🚀 <?= e(t('Premiers pas')) ?> — <?= $done ?>/<?= $total ?></h2>
        <p class="muted small"><?= e($done === $total ? t('Tout est prêt, bravo ! Ton site est au top.') : t('Quelques minutes suffisent pour un site complet, rapide et bien référencé.')) ?></p>
      </div>
      <form method="post" action="<?= e(admin_url('onboarding')) ?>"><?= Security::csrfField() ?>
        <button class="btn btn-sm btn-ghost" type="submit"><?= e($done === $total ? t('Terminer') : t('Masquer')) ?></button></form>
    </div>
    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>"><i data-width="<?= $pct ?>"></i></div>
    <ul class="todo">
      <?php foreach ($v['todo'] as $item): ?>
        <li class="<?= $item['done'] ? 'done' : '' ?>">
          <span class="tick" aria-hidden="true"><?= $item['done'] ? '✓' : '' ?></span>
          <span class="todo-label"><?= e($item['label']) ?></span>
          <?php if (!$item['done'] && $item['url'] !== ''): ?><a class="btn btn-sm" href="<?= e($item['url']) ?>"><?= e(t('C’est parti')) ?> →</a><?php endif ?>
        </li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <div class="stats">
    <div class="stat"><a href="<?= e(admin_url('content?type=post')) ?>"><b><?= $v['stats']['posts'] ?></b><span><?= e(t('Articles publiés')) ?></span></a></div>
    <div class="stat"><a href="<?= e(admin_url('content?type=page')) ?>"><b><?= $v['stats']['pages'] ?></b><span><?= e(t('Pages publiées')) ?></span></a></div>
    <div class="stat"><a href="<?= e(admin_url('content?status=draft')) ?>"><b><?= $v['stats']['drafts'] ?></b><span><?= e(t('Brouillons')) ?></span></a></div>
    <div class="stat"><a href="<?= e(admin_url('messages')) ?>"><b><?= $v['stats']['messages'] ?></b><span><?= e(t('Messages non lus')) ?></span></a></div>
    <div class="stat"><b><span class="score <?= $cls($v['stats']['seo']) ?>"><?= $v['stats']['seo'] ?></span></b><span><?= e(t('Score SEO moyen')) ?></span></div>
    <div class="stat"><b><span class="score <?= $cls($v['stats']['geo']) ?>"><?= $v['stats']['geo'] ?></span></b><span><?= e(t('Score GEO (IA) moyen')) ?></span></div>
  </div>

  <div class="grid-2">
    <section class="card">
      <div class="card-head"><h2><?= e(t('À améliorer en priorité')) ?></h2></div>
      <?php if (!$v['improve']): ?>
        <p class="muted">🎯 <?= e(t('Tous tes contenus publiés ont d’excellentes notes. Continue comme ça !')) ?></p>
      <?php else: ?>
        <table class="list"><tbody>
        <?php foreach ($v['improve'] as $e): ?>
          <tr><td class="title-cell"><a href="<?= e(admin_url('edit/' . $e['id'])) ?>"><?= e($e['title']) ?></a></td>
            <td class="right"><span class="score <?= $cls($e['score']['seo']) ?>" title="SEO"><?= (int) $e['score']['seo'] ?></span>
            <span class="score <?= $cls($e['score']['geo']) ?>" title="GEO"><?= (int) $e['score']['geo'] ?></span></td></tr>
        <?php endforeach ?>
        </tbody></table>
      <?php endif ?>
    </section>
    <section class="card">
      <div class="card-head"><h2><?= e(t('Santé du site')) ?></h2></div>
      <ul class="health">
        <?php foreach ($v['health'] as $h): ?>
          <li><span class="dot <?= $h['ok'] ? 'ok' : 'warn' ?>"></span><?= e($h['label']) ?></li>
        <?php endforeach ?>
      </ul>
      <p class="small muted"><?= e(t('Fichiers générés automatiquement :')) ?>
        <a href="<?= e(path_to('/sitemap.xml')) ?>" target="_blank" rel="noopener">sitemap.xml</a> ·
        <a href="<?= e(path_to('/robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a> ·
        <a href="<?= e(path_to('/llms.txt')) ?>" target="_blank" rel="noopener">llms.txt</a> ·
        <a href="<?= e(path_to('/feed.xml')) ?>" target="_blank" rel="noopener">RSS</a></p>
    </section>
  </div>

  <section class="card">
    <div class="card-head"><h2><?= e(t('Modifié récemment')) ?></h2><a class="btn btn-sm" href="<?= e(admin_url('content')) ?>"><?= e(t('Tout voir')) ?></a></div>
    <table class="list"><tbody>
      <?php foreach ($v['recent'] as $e): ?>
        <tr>
          <td class="title-cell"><a href="<?= e(admin_url('edit/' . $e['id'])) ?>"><?= e($e['title']) ?></a><small><?= e($e['type'] === 'post' ? t('Article') : t('Page')) ?> · <?= e(ago((int) $e['updated'])) ?></small></td>
          <td class="right"><?= Content::visible($e) ? '<span class="badge ok">' . e(t('Publié')) . '</span>' : ($e['status'] === 'published' ? '<span class="badge brand">' . e(t('Programmé')) . '</span>' : '<span class="badge">' . e(t('Brouillon')) . '</span>') ?></td>
        </tr>
      <?php endforeach ?>
    </tbody></table>
  </section>
</div>
