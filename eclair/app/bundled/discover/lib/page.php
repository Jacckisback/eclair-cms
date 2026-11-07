<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Vue de la fonction « Google Discover » : prêt pour Discover, tendances du jour (voir Discover.php, Trends.php). */
defined('ECLAIR') || exit;

use EclairExt\Discover\Discover;
use EclairExt\Discover\Trends;
use Eclair\Icons;
use Eclair\Security;

$site = $v['site'];
$posts = $v['posts'];
$n = count($posts);
$countries = [
    'FR' => t('France'), 'BE' => t('Belgique'), 'CH' => t('Suisse'), 'CA' => t('Canada'), 'LU' => t('Luxembourg'),
    'ES' => t('Espagne'), 'MX' => t('Mexique'), 'AR' => t('Argentine'), 'CO' => t('Colombie'), 'CL' => t('Chili'), 'PE' => t('Pérou'),
    'US' => t('États-Unis'), 'GB' => t('Royaume-Uni'), 'IE' => t('Irlande'), 'AU' => t('Australie'),
    'DE' => t('Allemagne'), 'AT' => t('Autriche'), 'IT' => t('Italie'), 'PT' => t('Portugal'), 'BR' => t('Brésil'),
];
$siteChecks = [
    'index' => [t('Site ouvert aux moteurs de recherche'), t('Coupé dans Réglages › SEO : Google n’indexe rien, Discover ne peut rien montrer.'), 'settings?tab=seo'],
    'https' => [t('Adresse en HTTPS'), t('Active « Forcer HTTPS » dans Réglages › Avancé (ton hébergeur doit fournir le certificat).'), 'settings?tab=advanced'],
    'logo' => [t('Logo de l’éditeur'), t('Ajoute ton logo dans Réglages › Apparence : les données structurées des articles désignent ainsi leur éditeur.'), 'settings?tab=appearance'],
    'about' => [t('Page « À propos »'), t('Dis qui écrit et pourquoi : Google cherche des sites dont on sait qui est derrière.'), 'new/page'],
    'contact' => [t('Page « Contact »'), t('Montre qu’une vraie personne répond derrière le site.'), 'new/page'],
];
$imageBadge = static fn (array $p): array => match ($p['image']) {
    'ok' => ['ok', t('{n} px', ['n' => $p['width']])],
    'small' => ['warn', t('{n} px : trop petite', ['n' => $p['width']])],
    'logo' => ['warn', t('logo du site')],
    default => ['bad', t('aucune')],
};
$titleBadge = static fn (string $why): array => match ($why) {
    'caps' => ['warn', t('trop de majuscules')],
    'punct' => ['warn', t('« !! » ou « ?! »')],
    'long' => ['warn', t('trop long')],
    default => ['ok', t('sans excès')],
};
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Google Discover')) ?></h1><p><?= e(t('Prépare tes articles pour le fil Discover de Google et trouve des idées dans les tendances du jour.')) ?></p></div>
  </div>

  <?php if (!$v['blog']): ?>
    <div class="notice warn"><span><?= Icons::svg('alert') ?></span><p><?= e(t('Le blog est désactivé : Discover met surtout en avant des articles. Active-le dans Réglages.')) ?></p></div>
  <?php endif ?>

  <section class="card" aria-labelledby="dc-ready-title">
    <div class="card-head">
      <h2 id="dc-ready-title"><?= Icons::svg('compass') ?><?= e(t('Prêt pour Discover')) ?></h2>
      <?php if ($n): ?><span class="score <?= $v['ready'] === $n ? 'good' : ($v['ready'] * 2 >= $n ? 'ok' : 'bad') ?>"><?= (int) $v['ready'] ?>/<?= $n ?></span><?php endif ?>
    </div>
    <p class="small muted"><?= e(t('Google n’offre aucun réglage pour « se positionner » sur Discover : un article indexé y est éligible automatiquement. Il recommande de grandes images (1200 px de large au moins, jamais le logo), des titres qui résument le contenu sans sensationnalisme, et des articles utiles dont on sait qui les écrit. Éclair sert déjà la balise des grandes images (max-image-preview:large) et les données structurées ; voici ce qui dépend de toi.')) ?>
      <a href="https://developers.google.com/search/docs/appearance/google-discover?hl=fr" target="_blank" rel="noopener"><?= e(t('Recommandations de Google')) ?></a></p>
    <ul class="health dc-site">
      <?php foreach ($siteChecks as $key => [$label, $fix, $link]): ?>
        <li class="<?= $site[$key] ? 'ok' : 'warn' ?>"><?= Icons::svg($site[$key] ? 'circle-check' : 'alert') ?>
          <span><?= e($label) ?><?php if (!$site[$key]): ?><small class="muted"><?= e($fix) ?> <a href="<?= e(admin_url($link)) ?>"><?= e(t('Corriger')) ?></a></small><?php endif ?></span></li>
      <?php endforeach ?>
    </ul>
    <?php if (!$n): ?>
      <p class="muted"><?= e(t('Aucun article publié pour l’instant.')) ?></p>
    <?php else: ?>
      <p class="small"><?= e(t('{ready} articles sur {n} ont tout ce que Google recommande (les {max} plus récents sont contrôlés).', ['ready' => $v['ready'], 'n' => $n, 'max' => Discover::MAX])) ?></p>
      <div class="table-wrap"><table class="list dc-posts">
        <thead><tr><th><?= e(t('Article')) ?></th><th><?= e(t('Image')) ?></th><th><?= e(t('Titre')) ?></th><th><?= e(t('Auteur')) ?></th><th><?= e(t('Longueur')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($posts as $id => $p): ?>
            <?php [$ic, $it] = $imageBadge($p); [$tc, $tt] = $titleBadge($p['title']); ?>
            <tr>
              <td><a href="<?= e(admin_url('edit/' . $id)) ?>"><?= e($p['entry']['title']) ?></a><?php if (!$p['index']): ?> <span class="badge bad"><?= e(t('non indexé')) ?></span><?php endif ?></td>
              <td><span class="badge <?= $ic ?>"><?= e($it) ?></span></td>
              <td><span class="badge <?= $tc ?>"><?= e($tt) ?></span></td>
              <td><span class="badge <?= $p['author'] ? 'ok' : 'warn' ?>"><?= e($p['author'] ? t('présenté') : t('sans présentation')) ?></span></td>
              <td><span class="badge <?= $p['words'] >= Discover::WORDS ? 'ok' : 'warn' ?>"><?= e(t('{n} mots', ['n' => number_format($p['words'], 0, ',', "\u{202F}")])) ?></span></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table></div>
      <p class="small muted"><?= e(t('Image : celle de l’article, 1200 px de large au moins. Auteur : une présentation dans son profil (Mon profil › Biographie). Longueur : un repère d’Éclair, pas une règle de Google.')) ?></p>
    <?php endif ?>
  </section>

  <section class="card" id="trends" aria-labelledby="dc-trends-title">
    <div class="card-head">
      <h2 id="dc-trends-title"><?= Icons::svg('chart') ?><?= e(t('Tendances du jour')) ?></h2>
      <form method="post" action="<?= e(admin_url('x/discover')) ?>" class="dc-geo"><?= Security::csrfField() ?><input type="hidden" name="action" value="geo">
        <label class="sr-only" for="dc-geo"><?= e(t('Pays')) ?></label>
        <select id="dc-geo" name="geo"><?php foreach ($countries as $code => $name): ?><option value="<?= $code ?>"<?= $code === $v['geo'] ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select>
        <button class="btn btn-sm" type="submit"><?= e(t('Changer')) ?></button>
      </form>
    </div>
    <p class="small muted"><?= e(t('Les recherches qui montent aujourd’hui sur Google ({country}), d’après le flux public de Google Trends. La plupart sont de l’actualité (personnes, sport, faits divers) : garde seulement celles qui touchent ton sujet, et relie-les à ton expertise au lieu de rapporter l’actualité.', ['country' => $countries[$v['geo']] ?? $v['geo']])) ?></p>
    <?php if ($v['trends']): ?>
      <?php if (!$v['trends']['items']): ?>
        <p class="muted"><?= e(t('Aucune tendance pour ce pays en ce moment.')) ?></p>
      <?php else: ?>
        <ol class="dc-trends">
          <?php foreach ($v['trends']['items'] as $i => $tr): ?>
            <li>
              <div class="dc-trend">
                <b><?= e($tr['title']) ?></b><?php if ($tr['traffic'] !== ''): ?> <span class="badge"><?= e(t('{n} recherches', ['n' => $tr['traffic']])) ?></span><?php endif ?>
                <?php foreach (array_slice($tr['news'], 0, 2) as $news): ?>
                  <a class="small" href="<?= e($news['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($news['title']) ?><?= $news['source'] !== '' ? ' · ' . e($news['source']) : '' ?></a>
                <?php endforeach ?>
              </div>
              <?php if ($v['planning']): ?>
              <form method="post" action="<?= e(admin_url('x/discover')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="trend"><input type="hidden" name="n" value="<?= (int) $i ?>">
                <button class="btn btn-sm" type="submit"><?= Icons::svg('plus') ?><?= e(t('Ajouter au planning')) ?></button></form>
              <?php endif ?>
            </li>
          <?php endforeach ?>
        </ol>
      <?php endif ?>
      <p class="small muted"><?= e(t('Source : Google Trends, lu à {time}. Une nouvelle lecture est possible une heure après.', ['time' => date('H:i', (int) $v['trends']['fetched'])])) ?></p>
      <?php if (!$v['planning'] && $v['trends']['items']): ?>
        <p class="small muted"><?= e(t('Pour garder une tendance comme idée d’article, ajoute la fonction « Planning éditorial ».')) ?> <a href="<?= e(admin_url('extensions')) ?>"><?= e(t('Ajouter la fonction')) ?></a></p>
      <?php endif ?>
      <?php if ($v['ai'] && $v['planning'] && $v['trends']['items']): ?>
        <div class="dc-ideas">
          <button class="btn btn-primary" type="button" data-dc-ideas data-wait="<?= e(t('L’IA trie les tendances…')) ?>"><?= Icons::svg('spark') ?><?= e(t('Proposer des idées pour mon site')) ?> <small>· ~<?= e($v['ideasCost']) ?></small></button>
          <p class="small muted"><?= e(t('L’IA ne garde que les tendances qui touchent ton sujet (souvent aucune) et propose au plus {n} idées d’articles utiles et durables, jamais un article d’actualité. Elles arrivent dans le planning, à la date d’aujourd’hui.', ['n' => Trends::IDEAS])) ?></p>
        </div>
      <?php endif ?>
    <?php else: ?>
      <div><button class="btn" type="button" data-dc-trends data-wait="<?= e(t('Lecture des tendances…')) ?>"><?= Icons::svg('refresh') ?><?= e(t('Charger les tendances du jour')) ?></button></div>
    <?php endif ?>
  </section>

  <p class="small muted"><?= e($v['adsActive'] ? t('Publicité AdSense active sur les articles.') : t('Publicité AdSense : inactive.')) ?><?php if ($v['adsAdmin']): ?> <a href="<?= e(admin_url('ads')) ?>"><?= e(t('Régler la publicité')) ?></a><?php endif ?></p>
</div>
