<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Vue de la fonction « Import WordPress » : envoi de l'export, avancement, bilan (voir Importer.php). */
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;

$job = $v['job'];
$r = $job['report'] ?? [];
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Import WordPress')) ?></h1><p><?= e(t('Reprends les articles, pages, catégories et images d’un site WordPress, en gardant leurs adresses : les liens et le référencement de l’ancien site continuent de mener aux mêmes pages.')) ?></p></div>
  </div>

  <?php
  $pj = $v['pjob'];
  $prun = $pj !== null && !$pj['done'];
  $pr = $pj['report'] ?? [];
  $prs = array_filter($pr, 'is_scalar');
  $stages = ['prepare' => t('Préparation'), 'terms' => t('Catégories et auteurs'), 'media' => t('Médias'), 'content' => t('Contenus'), 'links' => t('Liens internes'), 'menus' => t('Menus'), 'redirects' => t('Redirections'), 'done' => t('Terminé')];
  ?>
  <?php if ($prun): ?>
  <section class="card" aria-labelledby="wp-prun" data-wp-run>
    <h2 id="wp-prun"><?= Icons::svg('refresh') ?><?= e($pj['dry'] ? t('Simulation de « {site} »', ['site' => $pj['title'] ?: $pj['source']]) : t('Import de « {site} »', ['site' => $pj['title'] ?: $pj['source']])) ?></h2>
    <?php [$pn, $ptotal, $pstage] = EclairExt\Wordpress\PackageImporter::progress($pj) ?>
    <p><progress max="<?= (int) $ptotal ?>" value="<?= (int) $pn ?>"></progress> <span data-wp-label><?= e($pn . ' / ' . $ptotal) ?></span> · <?= e($stages[$pstage] ?? $pstage) ?></p>
    <div class="row">
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="pstep">
        <button class="btn btn-primary" type="submit" data-wp-step data-wait="<?= e(t('Import en cours…')) ?>"><?= e($pn ? t('Continuer l’import') : ($pj['dry'] ? t('Lancer la simulation') : t('Lancer l’import'))) ?></button></form>
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="pcancel">
        <button class="btn btn-ghost" type="submit"><?= e(t('Abandonner')) ?></button></form>
    </div>
    <p class="small muted"><?= e(t('L’import avance par lots de quelques secondes ; tu peux fermer la page et le reprendre plus tard. Un contenu déjà importé n’est jamais créé deux fois.')) ?></p>
  </section>
  <?php endif ?>

  <?php if ($pj !== null && $pj['done']): ?>
  <section class="card" aria-labelledby="wp-pdone">
    <h2 id="wp-pdone"><?= Icons::svg('circle-check') ?><?= e($pj['dry'] ? t('Simulation : voici ce que l’import ferait') : t('Dernier import du paquet')) ?></h2>
    <ul class="health">
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{posts} articles, {pages} pages, {products} produits, {custom} autres contenus ({skipped} déjà présents, {drafts} en brouillon)', $prs)) ?></span></li>
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{routes} adresses gardées à l’identique ; {moved} adresses changées, redirigées en 301', $prs)) ?></span></li>
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{images} images et {documents} documents ; {categories} catégories créées ; {seo} titres et descriptions SEO repris', $prs)) ?></span></li>
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{redirects} redirections, {aliases} anciennes adresses à paramètres (?p=), {links} liens internes réécrits, {menu_items} liens de menu', $prs)) ?></span></li>
      <?php if ($pr['media_skipped'] || $pr['media_missing'] || $pr['redirects_skipped'] || $pr['shortcodes'] || $pr['seo_partial'] || $pr['menu_flattened'] || $pr['authors_mine']): ?>
      <li class="warn"><?= Icons::svg('alert') ?><span><?= e(t('À relire : {media_skipped} fichiers non repris, {media_missing} introuvables, {redirects_skipped} redirections écartées, {shortcodes} codes courts restés en texte, {seo_partial} pages avec Open Graph ou nofollow non repris, {menu_flattened} sous-menus mis à plat, {authors_mine} auteurs rattachés à ton compte', $prs)) ?></span></li>
      <?php endif ?>
      <?php foreach ($pr['errors'] as $err): ?>
        <li class="warn"><?= Icons::svg('alert') ?><span><?= e($err) ?></span></li>
      <?php endforeach ?>
    </ul>
    <?php if ($pr['not_migrated']): ?>
    <details><summary><?= e(t('Éléments non migrés ({n})', ['n' => count($pr['not_migrated'])])) ?></summary>
      <ul class="small"><?php foreach ($pr['not_migrated'] as [$what, $name, $why]): ?><li><strong><?= e($name) ?></strong> — <?= e($why) ?></li><?php endforeach ?></ul>
    </details>
    <?php endif ?>
    <?php $b = $v['branding']; $sp = $v['speed']; ?>
    <h3><?= e(t('Rapport de migration pour le client')) ?></h3>
    <p class="small muted"><?= e(t('Page aux couleurs d’Éclair : contenus, adresses vérifiées une par une, redirections, éléments non migrés, liste de contrôle SEO et vitesse avant/après. Pour un PDF, ouvre-la puis imprime-la.')) ?></p>
    <div class="row">
      <?php foreach (EclairExt\Wordpress\Report::LANGS as $code => $label): ?>
        <a class="btn" href="<?= e(admin_url('x/wordpress/report?lang=' . $code)) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
        <a class="btn btn-ghost" href="<?= e(admin_url('x/wordpress/report?lang=' . $code . '&download=1')) ?>"><?= e(t('Télécharger')) ?></a>
      <?php endforeach ?>
    </div>
    <details><summary><?= e(t('Vitesse avant/après (Google PageSpeed)')) ?></summary>
      <p class="small muted"><?= e(t('Mesure l’ancien site tant qu’il est encore en ligne, avant de faire pointer le domaine vers Éclair ; mesure ensuite le nouveau. Chaque mesure prend environ une minute.')) ?></p>
      <?php foreach (['old' => $pj['source'], 'new' => rtrim((string) Eclair\Settings::get('url'), '/') . '/'] as $which => $default): ?>
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>" class="row"><?= Security::csrfField() ?><input type="hidden" name="action" value="speed"><input type="hidden" name="which" value="<?= e($which) ?>">
        <label class="field"><span class="label"><?= e($which === 'old' ? t('Ancien site (WordPress)') : t('Nouveau site (Éclair)')) ?></span><input type="url" name="url" value="<?= e($sp[$which]['url'] ?? $default) ?>" required></label>
        <button class="btn" type="submit"><?= e(t('Mesurer')) ?></button>
        <?php if (isset($sp[$which]['score'])): ?><span class="small"><?= e(t('Score {n}/100', ['n' => (int) $sp[$which]['score']])) ?></span><?php endif ?>
      </form>
      <?php endforeach ?>
    </details>
    <details><summary><?= e(t('Bloc « Préparé par » du rapport')) ?></summary>
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="branding">
        <?php foreach (['name' => t('Nom'), 'company' => t('Entreprise'), 'email' => t('E-mail'), 'phone' => t('Téléphone'), 'website' => t('Site web')] as $k => $label): ?>
          <label class="field"><span class="label"><?= e($label) ?></span><input type="text" name="<?= e($k) ?>" value="<?= e($b[$k]) ?>"></label>
        <?php endforeach ?>
        <label class="field"><span class="label"><?= e(t('Message du bloc « Et maintenant ? » (vide : texte par défaut)')) ?></span><textarea name="note" rows="2"><?= e($b['note']) ?></textarea></label>
        <div><button class="btn" type="submit"><?= e(t('Enregistrer')) ?></button></div>
      </form>
    </details>
    <?php if ($pj['dry'] && $v['parts']): ?>
    <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="import">
      <?php foreach (['keep', 'site', 'menus', 'remote'] as $opt): ?><input type="hidden" name="<?= e($opt) ?>" value="<?= !empty($pj[$opt]) ? '1' : '' ?>"><?php endforeach ?>
      <button class="btn btn-primary" type="submit"><?= Icons::svg('upload') ?><?= e(t('Importer pour de vrai')) ?></button></form>
    <?php endif ?>
  </section>
  <?php endif ?>

  <?php if (!$prun && ($job === null || $job['done'])): ?>
  <section class="card" aria-labelledby="wp-pkg">
    <h2 id="wp-pkg"><?= Icons::svg('upload') ?><?= e(t('Paquet Eclair Export (recommandé)')) ?></h2>
    <p class="small muted"><?= e(t('Installe l’extension « Eclair Export » sur le site WordPress (Outils › Exporter vers Éclair), puis envoie ici toutes les parties du paquet. Il reprend aussi le SEO, les redirections, les menus et les documents.')) ?></p>
    <form method="post" action="<?= e(admin_url('x/wordpress')) ?>" enctype="multipart/form-data"><?= Security::csrfField() ?><input type="hidden" name="action" value="package">
      <label class="field"><span class="label"><?= e(t('Parties du paquet (.zip, {n} au plus chacune)', ['n' => $v['limit']])) ?></span><input type="file" name="parts[]" accept=".zip,application/zip" multiple required></label>
      <div><button class="btn" type="submit"><?= Icons::svg('upload') ?><?= e(t('Envoyer')) ?></button></div>
    </form>
    <h3><?= e(t('Envoi direct depuis WordPress')) ?></h3>
    <?php if ($v['link'] === null): ?>
    <p class="small muted"><?= e(t('Le plus simple : WordPress envoie le paquet ici tout seul, sans téléchargement ni FTP, quelle que soit sa taille. Crée une clé, colle-la dans l’extension Eclair Export.')) ?></p>
    <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="link">
      <div><button class="btn btn-primary" type="submit"><?= Icons::svg('link') ?><?= e(t('Créer une clé de liaison')) ?></button></div></form>
    <?php else: ?>
    <?php if ($v['key'] !== ''): ?>
    <label class="field"><span class="label"><?= e(t('Clé de liaison (valable jusqu’à {time}, pour un seul envoi)', ['time' => date('H:i', (int) $v['link']['expires'])])) ?></span><input type="text" readonly value="<?= e($v['key']) ?>" onfocus="this.select()"></label>
    <p class="small muted"><?= e(t('Colle-la dans WordPress : Outils › Exporter vers Éclair › Envoyer vers Éclair. Elle ne permet que de déposer le paquet ici ; l’import se lance ensuite sur cette page.')) ?></p>
    <?php else: ?>
    <p class="small muted"><?= e(t('Une clé de liaison est active jusqu’à {time}. Elle n’est montrée qu’à sa création : crées-en une nouvelle si tu ne l’as plus.', ['time' => date('H:i', (int) $v['link']['expires'])])) ?></p>
    <?php endif ?>
    <div class="row">
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="link"><button class="btn" type="submit"><?= e(t('Nouvelle clé')) ?></button></form>
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="unlink"><button class="btn btn-ghost" type="submit"><?= e(t('Supprimer la clé')) ?></button></form>
    </div>
    <?php endif ?>
    <p class="small muted"><?= e(t('Archives trop lourdes pour ton hébergeur : dépose-les par FTP dans le dossier data/import/ du site, puis recharge cette page.')) ?></p>
    <?php if ($v['parts']): ?>
    <ul class="small"><?php foreach ($v['parts'] as $name => $size): ?><li><?= e($name) ?> — <?= e(human_size((int) $size)) ?></li><?php endforeach ?></ul>
    <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?>
      <label class="check"><input type="checkbox" name="keep" value="1" checked><span><?= e(t('Publier ce qui était publié (sinon, tout arrive en brouillon)')) ?></span></label>
      <label class="check"><input type="checkbox" name="site" value="1" checked><span><?= e(t('Reprendre la page d’accueil et le nom du site')) ?></span></label>
      <label class="check"><input type="checkbox" name="menus" value="1" checked><span><?= e(t('Remplacer les menus par ceux de WordPress')) ?></span></label>
      <label class="check"><input type="checkbox" name="remote" value="1" checked><span><?= e(t('Télécharger depuis l’ancien site les médias absents du paquet (il doit être encore en ligne)')) ?></span></label>
      <div class="row">
        <button class="btn" type="submit" name="action" value="simulate"><?= e(t('Simuler (rien n’est écrit)')) ?></button>
        <button class="btn btn-primary" type="submit" name="action" value="import"><?= e(t('Importer')) ?></button>
        <button class="btn btn-ghost" type="submit" name="action" value="pclear"><?= e(t('Retirer le paquet')) ?></button>
      </div>
    </form>
    <?php endif ?>
  </section>
  <?php endif ?>

  <?php if (!$prun && ($job === null || $job['done'])): ?>
  <section class="card" aria-labelledby="wp-up">
    <h2 id="wp-up"><?= Icons::svg('upload') ?><?= e(t('Envoyer l’export')) ?></h2>
    <p class="small muted"><?= e(t('Dans WordPress : Outils › Exporter › Tout le contenu › Télécharger le fichier d’export. Envoie ici le fichier .xml obtenu.')) ?></p>
    <form method="post" action="<?= e(admin_url('x/wordpress')) ?>" enctype="multipart/form-data"><?= Security::csrfField() ?><input type="hidden" name="action" value="upload">
      <label class="field"><span class="label"><?= e(t('Fichier d’export (.xml, {n} au plus)', ['n' => $v['limit']])) ?></span><input type="file" name="wxr" accept=".xml,text/xml,application/xml" required></label>
      <label class="check"><input type="checkbox" name="keep" value="1" checked><span><?= e(t('Publier ce qui était publié (sinon, tout arrive en brouillon)')) ?></span></label>
      <label class="check"><input type="checkbox" name="images" value="1" checked><span><?= e(t('Copier les images de l’ancien site dans la médiathèque (il doit être encore en ligne)')) ?></span></label>
      <div><button class="btn btn-primary" type="submit"><?= Icons::svg('upload') ?><?= e(t('Lire l’export')) ?></button></div>
    </form>
  </section>
  <?php endif ?>

  <?php if ($job !== null && !$job['done']): ?>
  <section class="card" aria-labelledby="wp-run" data-wp-run>
    <h2 id="wp-run"><?= Icons::svg('refresh') ?><?= e(t('Import de « {site} »', ['site' => $job['title'] !== '' ? $job['title'] : $job['site']])) ?></h2>
    <p><progress max="<?= (int) $job['total'] ?>" value="<?= (int) $job['next'] ?>"></progress> <span data-wp-label><?= e(t('{n} contenus sur {total}', ['n' => $job['next'], 'total' => $job['total']])) ?></span></p>
    <div class="row">
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="step">
        <button class="btn btn-primary" type="submit" data-wp-step data-wait="<?= e(t('Import en cours…')) ?>"><?= e($job['next'] ? t('Continuer l’import') : t('Lancer l’import')) ?></button></form>
      <form method="post" action="<?= e(admin_url('x/wordpress')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="cancel">
        <button class="btn btn-ghost" type="submit"><?= e(t('Abandonner')) ?></button></form>
    </div>
    <p class="small muted"><?= e(t('L’import avance par lots de quelques secondes ; tu peux fermer la page et le reprendre plus tard. Un contenu déjà importé n’est jamais créé deux fois.')) ?></p>
  </section>
  <?php endif ?>

  <?php if ($job !== null && $job['done']): ?>
  <section class="card" aria-labelledby="wp-done">
    <h2 id="wp-done"><?= Icons::svg('circle-check') ?><?= e(t('Dernier import')) ?></h2>
    <ul class="health">
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{posts} articles et {pages} pages importés ({skipped} déjà présents)', $r)) ?></span></li>
      <li class="ok"><?= Icons::svg('circle-check') ?><span><?= e(t('{routes} adresses reprises à l’identique, {aliases} anciennes adresses à paramètres (?p=) redirigées', $r)) ?></span></li>
      <li class="<?= $r['missed'] ? 'warn' : 'ok' ?>"><?= Icons::svg($r['missed'] ? 'alert' : 'circle-check') ?><span><?= e(t('{images} images copiées dans la médiathèque, {missed} laissées en lien vers leur site', $r)) ?></span></li>
      <?php if ($r['shortcodes'] || $r['dropped']): ?>
        <li class="warn"><?= Icons::svg('alert') ?><span><?= e(t('À relire : {shortcodes} codes courts WordPress ([…]) restés en texte, {dropped} éléments non repris (scripts, formulaires, intégrations)', $r)) ?></span></li>
      <?php endif ?>
      <?php foreach ($r['errors'] as $err): ?>
        <li class="warn"><?= Icons::svg('alert') ?><span><?= e($err) ?></span></li>
      <?php endforeach ?>
    </ul>
    <p class="small muted"><?= e(t('Vérifie quelques pages, puis fais pointer ton domaine vers ce site. Les commentaires, extensions et réglages de WordPress ne sont pas repris.')) ?> <a href="<?= e(admin_url('content')) ?>"><?= e(t('Voir les contenus')) ?></a></p>
  </section>
  <?php endif ?>
</div>
