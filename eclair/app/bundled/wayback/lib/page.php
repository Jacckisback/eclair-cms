<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Ancien site (Wayback Machine) : vue de la fonction « wayback » (lib/AdminWayback.php la rend dans la page d'administration). */
defined('ECLAIR') || exit;

use Eclair\Ai;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Security;
use EclairExt\Wayback\Wayback;

$lang = I18n::lang();
$d = $v['data'];
$states = [
    'todo' => [t('À reconstruire'), ''], 'draft' => [t('Brouillon'), 'warn'], 'published' => [t('Publié'), 'ok'],
    'live' => [t('Déjà sur le site'), 'brand'], 'error' => [t('Erreur'), 'bad'],
];
$tokens = static fn (int $n): string => number_format($n, 0, ',', "\u{202F}");
$open = $v['counts']['todo'] + $v['counts']['error'];
$posts = count(array_filter($v['rows'], fn (array $u): bool => $u['type'] === 'post'));
?>
<div class="page" data-wayback data-api="x/wayback/">
  <div class="topbar">
    <div><h1><?= e(t('Ancien site')) ?></h1><p><?= e(t('Retrouve dans la Wayback Machine les adresses que ce domaine avait autrefois et redonne une page à chacune : les liens qui y mènent encore aboutissent de nouveau.')) ?></p></div>
  </div>

  <?php if ($v['domain'] === ''): ?>
    <div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Indique d’abord l’adresse de ton site dans Réglages : seule l’archive de ce domaine peut être reconstruite.')) ?> <a href="<?= e(admin_url('settings')) ?>"><?= e(t('Réglages')) ?></a></p></div>
  <?php endif ?>
  <?php if (!$v['ai']): ?>
    <div class="notice"><span><?= Icons::svg('spark') ?></span><p><?= e(t('Avec l’assistant IA, chaque ancienne adresse retrouve une page neuve, écrite en brouillon.')) ?> <a href="<?= e(admin_url('settings?tab=ai')) ?>"><?= e(t('Activer l’assistant IA')) ?></a></p></div>
  <?php endif ?>

  <section class="card wb-card" aria-labelledby="wb-scan">
    <h2 id="wb-scan"><?= Icons::svg('search') ?><?= e(t('Chercher dans l’archive')) ?></h2>
    <form class="wb-scan" data-wb-scan data-wait="<?= e(t('Recherche dans l’archive… (jusqu’à une minute)')) ?>">
      <label class="field"><span class="label"><?= e(t('Domaine')) ?></span><input type="text" class="mono" readonly value="<?= e($v['domain']) ?>"></label>
      <label class="field"><span class="label"><?= e(t('Pages archivées jusqu’au')) ?></span><input type="date" name="date" value="<?= e($d['date']) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
      <div><button class="btn btn-primary" type="submit"<?= $v['domain'] === '' ? ' disabled' : '' ?>><?= Icons::svg('search') ?><?= e(t('Chercher')) ?></button></div>
    </form>
    <ul class="wb-rules small">
      <li><?= e(t('Seulement ce domaine : c’est vers lui que pointent les anciens liens.')) ?></li>
      <li><?= e(t('Des textes neufs : l’IA écrit à partir du seul titre de l’ancienne page. Ni son texte ni ses images ne sont recopiés.')) ?></li>
      <li><?= e(t('Des brouillons : rien n’est publié sans toi. La page garde l’ancienne adresse quand c’est possible ; sinon, l’ancienne y redirige (301).')) ?></li>
      <li><?= e(t('Google pénalise les domaines expirés remplis de pages sans valeur pour le visiteur : relis et enrichis chaque page avant de la publier.')) ?>
        <a href="https://developers.google.com/search/docs/essentials/spam-policies#expired-domains" target="_blank" rel="noopener noreferrer"><?= e(t('Règles de Google')) ?></a></li>
    </ul>
  </section>

  <?php if ($d['urls']): ?>
    <section class="card" aria-labelledby="wb-list">
      <div class="card-head"><h2 id="wb-list"><?= e(t('Anciennes adresses ({n})', ['n' => count($d['urls'])])) ?></h2>
        <span class="small muted"><?= e(t('Recherche du {date}', ['date' => fmt_date($d['scanned'], $lang)])) ?></span></div>
      <?php if ($posts && !$v['blog']): ?>
        <div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Le blog est désactivé : les anciens articles seront en ligne une fois le blog activé dans Réglages.')) ?></p></div>
      <?php endif ?>
      <div class="table-wrap">
        <table class="list wb-table">
          <thead><tr><th><input type="checkbox" data-wb-all aria-label="<?= e(t('Tout cocher')) ?>"></th><th><?= e(t('Ancienne adresse')) ?></th>
            <th><?= e(t('Archivée le')) ?></th><th><?= e(t('Nouvelle page')) ?></th><th><?= e(t('Statut')) ?></th></tr></thead>
          <tbody>
          <?php foreach ($v['rows'] as $path => $u): $free = $u['state'] === 'todo' || $u['state'] === 'error'; ?>
            <tr data-wb-state="<?= e($u['state']) ?>">
              <td><input type="checkbox" value="<?= e((string) $path) ?>" data-wb-path aria-label="<?= e((string) $path) ?>"<?= $free && $u['pick'] ? ' checked' : '' ?><?= $free ? '' : ' disabled' ?>></td>
              <td class="wb-path"><a class="mono" href="<?= e('https://web.archive.org/web/' . $u['ts'] . '/' . $u['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $path) ?></a>
                <?php if ($u['title'] !== ''): ?><small class="muted"><?= e($u['title']) ?></small><?php endif ?></td>
              <td class="small"><?= e(fmt_date((int) strtotime(substr($u['ts'], 0, 8)), $lang)) ?></td>
              <td class="small"><?= e(($u['type'] === 'post' ? t('Article') : t('Page')) . ' · ' . ($u['exact'] ? t('même adresse') : t('redirigée vers {path}', ['path' => Wayback::target($u)]))) ?></td>
              <td><span class="badge <?= $states[$u['state']][1] ?>" data-wb-badge><?= e($states[$u['state']][0]) ?></span>
                <?php if ($u['state'] === 'draft' || $u['state'] === 'published'): ?><a class="small" href="<?= e(admin_url('edit/' . $u['content'])) ?>"><?= e(t('Relire')) ?></a><?php endif ?>
                <small class="wb-error" data-wb-error><?= e($u['error']) ?></small>
                <?php if ($u['tokens'] > 0): ?><small class="muted"><?= e(t('IA : {tokens} jetons · {cost}', ['tokens' => $tokens((int) $u['tokens']), 'cost' => Ai::money((float) $u['cost'])])) ?></small><?php endif ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php if ($v['ai'] && $open): ?>
        <div class="wb-go">
          <?php if ($v['imageCost'] !== ''): ?>
            <label class="switch"><span><strong><?= e(t('Créer aussi une image par page')) ?></strong>
              <small><?= e(t('Image de couverture neuve, environ {cost} l’image.', ['cost' => $v['imageCost']])) ?></small></span><input type="checkbox" data-wb-image></label>
          <?php endif ?>
          <div class="wb-buttons">
            <button class="btn btn-primary" type="button" data-wb-build><?= Icons::svg('spark') ?><?= e(t('Reconstruire les adresses cochées')) ?> <small>· ~<?= e($v['writeCost']) ?> <?= e(t('la page')) ?></small></button>
            <button class="btn btn-ghost" type="button" data-wb-stop hidden><?= e(t('Arrêter après la page en cours')) ?></button>
          </div>
          <p class="small muted" data-wb-status aria-live="polite"><?= e(t('Modèle : {model}. Une page à la fois, dans le budget IA du mois.', ['model' => $v['model']])) ?></p>
        </div>
      <?php endif ?>
    </section>

    <div class="grid-2">
      <section class="card" aria-labelledby="wb-publish">
        <h2 id="wb-publish"><?= e(t('Publier')) ?></h2>
        <p class="small muted"><?= e(t('{n} pages reconstruites en brouillon, {p} publiées.', ['n' => $v['counts']['draft'], 'p' => $v['counts']['published']])) ?></p>
        <?php if ($v['counts']['draft']): ?>
          <form method="post" action="<?= e(admin_url('x/wayback')) ?>" data-confirm="<?= e(t('Publier les brouillons reconstruits ? Ceux qui contiennent encore « À compléter » restent en brouillon.')) ?>"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="publish"><button class="btn btn-primary" type="submit"><?= e(t('Publier les brouillons relus')) ?></button></form>
        <?php endif ?>
        <dl class="plan-spend small">
          <div><dt><?= e(t('Dépensé pour l’ancien site')) ?></dt><dd><?= e(t('{tokens} jetons · {cost}', ['tokens' => $tokens((int) $v['tokens']), 'cost' => Ai::money((float) $v['cost'])])) ?></dd></div>
          <div><dt><?= e(t('Budget IA du mois')) ?></dt><dd><?= e(Ai::money((float) $v['usage']['cost']) . ($v['budget'] > 0 ? ' / ' . Ai::money($v['budget']) : '')) ?></dd></div>
        </dl>
      </section>
      <section class="card" aria-labelledby="wb-reset">
        <h2 id="wb-reset"><?= e(t('Nouvelle recherche')) ?></h2>
        <p class="small muted"><?= e(t('Effacer la liste ne supprime aucune page : les brouillons et les redirections déjà créés restent.')) ?></p>
        <form method="post" action="<?= e(admin_url('x/wayback')) ?>" data-confirm="<?= e(t('Effacer la liste des anciennes adresses ?')) ?>"><?= Security::csrfField() ?>
          <input type="hidden" name="action" value="reset"><button class="btn btn-ghost" type="submit"><?= Icons::svg('trash') ?><?= e(t('Effacer la liste')) ?></button></form>
      </section>
    </div>
  <?php endif ?>
</div>
