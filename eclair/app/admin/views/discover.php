<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Google Discover : prêt pour Discover, tendances du jour, publicité AdSense (voir app/lib/Discover.php, Trends.php, Ads.php). */
defined('ECLAIR') || exit;

use Eclair\Ads;
use Eclair\Discover;
use Eclair\Icons;
use Eclair\Security;

$site = $v['site'];
$posts = $v['posts'];
$ads = $v['ads'];
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
$switch = static fn (string $name, bool $on, string $title, string $hint = ''): string => '<label class="switch"><span><strong>' . e($title) . '</strong>'
    . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</span><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '></label>';
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Google Discover')) ?></h1><p><?= e(t('Prépare tes articles pour le fil Discover de Google, trouve des idées dans les tendances du jour et, si tu le souhaites, affiche de la publicité AdSense.')) ?></p></div>
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
      <form method="post" action="<?= e(admin_url('discover')) ?>" class="dc-geo"><?= Security::csrfField() ?><input type="hidden" name="action" value="geo">
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
              <form method="post" action="<?= e(admin_url('discover')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="trend"><input type="hidden" name="n" value="<?= (int) $i ?>">
                <button class="btn btn-sm" type="submit"><?= Icons::svg('plus') ?><?= e(t('Ajouter au planning')) ?></button></form>
            </li>
          <?php endforeach ?>
        </ol>
      <?php endif ?>
      <p class="small muted"><?= e(t('Source : Google Trends, lu à {time}. Une nouvelle lecture est possible une heure après.', ['time' => date('H:i', (int) $v['trends']['fetched'])])) ?></p>
      <?php if ($v['ai'] && $v['trends']['items']): ?>
        <div class="dc-ideas">
          <button class="btn btn-primary" type="button" data-dc-ideas data-wait="<?= e(t('L’IA trie les tendances…')) ?>"><?= Icons::svg('spark') ?><?= e(t('Proposer des idées pour mon site')) ?> <small>· ~<?= e($v['ideasCost']) ?></small></button>
          <p class="small muted"><?= e(t('L’IA ne garde que les tendances qui touchent ton sujet (souvent aucune) et propose au plus {n} idées d’articles utiles et durables, jamais un article d’actualité. Elles arrivent dans le planning, à la date d’aujourd’hui.', ['n' => \Eclair\Trends::IDEAS])) ?></p>
        </div>
      <?php endif ?>
    <?php else: ?>
      <div><button class="btn" type="button" data-dc-trends data-wait="<?= e(t('Lecture des tendances…')) ?>"><?= Icons::svg('refresh') ?><?= e(t('Charger les tendances du jour')) ?></button></div>
    <?php endif ?>
  </section>

  <section class="card" id="ads" aria-labelledby="dc-ads-title">
    <div class="card-head">
      <h2 id="dc-ads-title"><?= Icons::svg('target') ?><?= e(t('Publicité (Google AdSense)')) ?></h2>
      <span class="badge <?= $v['adsActive'] ? 'ok' : '' ?>"><?= e($v['adsActive'] ? t('Active sur les articles') : t('Inactive')) ?></span>
    </div>
    <p class="small muted"><?= e(t('Des annonces Google sur tes articles, jamais sur les autres pages ni dans l’aperçu. Tant que la publicité n’est pas activée, aucune annonce ni aucun script de Google n’est chargé.')) ?></p>
    <?php if ($v['admin']): ?>
      <form method="post" action="<?= e(admin_url('discover')) ?>" class="dc-ads"><?= Security::csrfField() ?><input type="hidden" name="action" value="ads">
        <label class="field"><span class="label"><?= e(t('Identifiant d’éditeur')) ?></span>
          <input type="text" name="client" maxlength="40" placeholder="pub-1234567890123456" value="<?= e($v['adsClient'] !== '' ? $v['adsClient'] : (string) $ads['client']) ?>" autocomplete="off" spellcheck="false">
          <span class="hint"><?= e(t('Dans AdSense › Compte › Informations sur le compte. Éclair publie aussitôt /ads.txt et la balise qui permet à Google de valider ton site.')) ?></span></label>
        <?= $switch('consent', (bool) $ads['consent'], t('Message de consentement publié dans AdSense'), t('AdSense › Confidentialité et messages › Réglementations européennes : crée le message et publie-le. Google l’exige pour les visiteurs de l’Espace économique européen, du Royaume-Uni et de Suisse ; il s’affiche grâce au code AdSense, sans rien d’autre à installer.')) ?>
        <?= $switch('auto', (bool) $ads['auto'], t('Annonces automatiques'), t('Google choisit les emplacements et les formats ; ils se règlent dans AdSense › Annonces.')) ?>
        <label class="field"><span class="label"><?= e(t('Bloc d’annonces placé par Éclair (facultatif)')) ?></span>
          <input type="text" name="slot" maxlength="20" inputmode="numeric" placeholder="1234567890" value="<?= e((string) $ads['slot']) ?>" autocomplete="off">
          <span class="hint"><?= e(t('Crée dans AdSense un bloc « Annonces display » adaptatif et colle son numéro (data-ad-slot). Éclair le place entre deux blocs du texte, avec l’étiquette « {label} » exigée par Google et sa place réservée.', ['label' => \Eclair\Theme::t('ad_label')])) ?></span></label>
        <fieldset class="field dc-positions"><legend class="label"><?= e(t('Emplacements du bloc')) ?></legend>
          <?php foreach (['intro' => t('Après le 2e paragraphe'), 'middle' => t('Au milieu des articles longs'), 'end' => t('À la fin de l’article')] as $key => $label): ?>
            <label class="check"><input type="checkbox" name="positions[]" value="<?= $key ?>"<?= in_array($key, (array) $ads['positions'], true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach ?>
          <span class="hint"><?= e(t('Jamais dans un paragraphe, une liste ou un encadré ; au moins 3 paragraphes entre deux blocs ; rien dans un article de moins de 3 paragraphes.')) ?></span>
        </fieldset>
        <?= $switch('enabled', (bool) $ads['enabled'], t('Afficher la publicité sur les articles'), t('Chaque article peut s’en passer : case « Sans publicité sur cet article » dans l’éditeur.')) ?>
        <label class="field"><span class="label"><?= e(t('Lignes à ajouter à ads.txt (autres régies, facultatif)')) ?></span>
          <textarea name="txt" rows="3" maxlength="15000" spellcheck="false" placeholder="example.com, 12345, DIRECT"><?= e((string) $ads['txt']) ?></textarea></label>
        <div><button class="btn btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button></div>
      </form>
    <?php endif ?>
    <ul class="aff-rules small muted">
      <?php if ($v['adsClient'] !== ''): ?>
        <li><?= e(t('Fichier ads.txt :')) ?> <a href="<?= e(path_to('/ads.txt')) ?>" target="_blank" rel="noopener"><?= e(absolute_url('/ads.txt')) ?></a></li>
      <?php endif ?>
      <?php if ($v['adsActive'] && $v['testUrl'] !== ''): ?>
        <li><?= e(t('Vérifier le message de consentement :')) ?> <a href="<?= e($v['testUrl']) ?>" target="_blank" rel="noopener"><?= e(t('ouvrir un article avec le message affiché')) ?></a> <?= e(t('(en navigation privée, comme Google le conseille)')) ?></li>
      <?php endif ?>
      <li><?= e(t('Ne clique jamais sur tes propres annonces, même pour vérifier : Google l’interdit et peut suspendre le compte.')) ?></li>
      <li><?= e(t('Les scripts de Google ralentissent les pages qui ont de la publicité : Lighthouse n’y sera plus à 100. Éclair réserve la place des annonces pour que la page ne saute pas, et ne charge rien ailleurs.')) ?></li>
      <li><?= e(t('Sur ces pages, la politique de sécurité devient celle que Google documente pour AdSense ; toutes les autres gardent la politique stricte d’Éclair.')) ?></li>
    </ul>
  </section>
</div>
