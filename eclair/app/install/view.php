<?php
/** Assistant d'installation (5 étapes). Variables : checks, blocking, lang */
defined('ECLAIR') || exit;

use Eclair\Content;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Security;

$lang = $v['lang'];
$siteLang = array_key_exists($lang, Content::LANGS) ? $lang : 'fr';
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(t('Installation d’Éclair')) ?></title>
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('admin/install.js')) ?>" defer></script>
</head>
<body>
<div class="auth">
  <noscript><div class="notice warn"><p><?= e(t('Active JavaScript pour installer Éclair.')) ?></p></div></noscript>
  <form class="wizard" id="wizard" method="post" action="<?= e(path_to('/')) ?>" novalidate>
    <input type="hidden" name="_csrf" value="<?= e(Security::csrfToken()) ?>">
    <input type="hidden" name="ui" value="<?= e($lang) ?>">
    <div class="wizard-top">
      <div class="row between">
        <span class="logo"><span class="logo-mark"><?= Icons::bolt() ?></span><span><b>Éclair</b><small><?= e(t('Installation')) ?></small></span></span>
        <nav class="lang-switch" aria-label="<?= e(t('Langue')) ?>">
          <?php foreach (I18n::LANGS as $code => $name): ?>
            <a href="?lang=<?= e($code) ?>"<?= $code === $lang ? ' aria-current="page"' : '' ?> lang="<?= e($code) ?>"><?= e(strtoupper($code)) ?></a>
          <?php endforeach ?>
        </nav>
      </div>
      <div class="steps" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i><i></i></div>
    </div>

    <section class="step" data-step="0">
      <span class="big-emoji">⚡</span>
      <h1><?= e(t('Bienvenue sur Éclair')) ?></h1>
      <p class="lead"><?= e(t('Le CMS ultra léger, ultra rapide et taillé pour Google et les IA. Ton site sera prêt dans 2 minutes, promis.')) ?></p>
      <?php
        $okChecks = array_filter($v['checks'], fn ($c) => $c['level'] === 'ok');
        $item = static fn (array $c): string => '<li class="' . e($c['level']) . '"><span class="ico">' . ($c['level'] === 'ok' ? '✓' : ($c['level'] === 'warn' ? '!' : '✕')) . '</span><span>'
            . e($c['label']) . ($c['detail'] !== '' ? '<small>' . e($c['detail']) . '</small>' : '') . '</span></li>';
      ?>
      <ul class="checks">
        <?php foreach ($v['checks'] as $c): ?><?= $c['level'] !== 'ok' ? $item($c) : '' ?><?php endforeach ?>
        <li class="warn" id="rewrite" data-url="<?= e(path_to('/__eclair/ping')) ?>"><span class="ico">…</span><span><?= e(t('Adresses propres (réécriture d’URL)')) ?><small data-ok="<?= e(t('Actives : tes URL seront courtes et lisibles.')) ?>" data-ko="<?= e(t('Inactives : vérifie que le fichier .htaccess a bien été envoyé (fichier caché).')) ?>"><?= e(t('Vérification…')) ?></small></span></li>
      </ul>
      <details class="checks-more">
        <summary>✅ <?= e(t('{n} points vérifiés : ton hébergement est prêt', ['n' => count($okChecks)])) ?></summary>
        <ul class="checks"><?php foreach ($okChecks as $c): ?><?= $item($c) ?><?php endforeach ?></ul>
      </details>
      <?php if ($v['blocking']): ?>
        <div class="notice bad"><p><?= e(t('Corrige les points en rouge chez ton hébergeur, puis recharge cette page.')) ?></p></div>
      <?php endif ?>
      <div class="step-foot"><span></span><button class="btn btn-primary btn-lg" type="button" data-next<?= $v['blocking'] ? ' disabled' : '' ?>><?= e(t('C’est parti')) ?> →</button></div>
    </section>

    <section class="step" data-step="1" hidden>
      <h1>🏠 <?= e(t('Ton site')) ?></h1>
      <p class="lead"><?= e(t('Tout se modifie plus tard, pas de pression.')) ?></p>
      <label class="field"><span class="label"><?= e(t('Nom du site')) ?></span><input type="text" name="site_name" required maxlength="100" placeholder="<?= e(t('ex. : Atelier Lumière')) ?>"></label>
      <label class="field"><span class="label"><?= e(t('Slogan (facultatif)')) ?></span><input type="text" name="site_tagline" maxlength="160" placeholder="<?= e(t('ex. : Photographe de mariage à Lyon')) ?>"></label>
      <label class="field"><span class="label"><?= e(t('Langue du site')) ?></span><select name="site_lang">
        <?php foreach (Content::LANGS as $code => $name): ?><option value="<?= e($code) ?>"<?= $code === $siteLang ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select></label>
      <div class="field"><span class="label"><?= e(t('Quel genre de site ?')) ?></span>
        <div class="choices">
          <?php foreach ([
              'business' => ['🏢', t('Site vitrine'), t('Entreprise, artisan, association : accueil, services, contact, blog.')],
              'blog' => ['✍️', t('Blog'), t('Des articles avant tout, avec catégories et flux RSS.')],
              'freelance' => ['🧑‍💻', t('Freelance / portfolio'), t('Ta présentation, tes compétences, ton blog d’expert.')],
              'landing' => ['🎯', t('Page unique'), t('Une page de vente ou de lancement, avec formulaire.')],
          ] as $k => [$emoji, $label, $desc]): ?>
            <label class="choice"><input type="radio" name="site_type" value="<?= e($k) ?>"<?= $k === 'business' ? ' checked' : '' ?>>
              <span><span class="emoji"><?= $emoji ?></span><b><?= e($label) ?></b><small><?= e($desc) ?></small></span></label>
          <?php endforeach ?>
        </div></div>
      <div class="step-foot"><button class="btn btn-ghost" type="button" data-prev>← <?= e(t('Retour')) ?></button><button class="btn btn-primary btn-lg" type="button" data-next><?= e(t('Continuer')) ?> →</button></div>
    </section>

    <section class="step" data-step="2" hidden>
      <h1>🔐 <?= e(t('Ton compte')) ?></h1>
      <p class="lead"><?= e(t('C’est avec lui que tu te connecteras à l’administration.')) ?></p>
      <label class="field"><span class="label"><?= e(t('Ton nom')) ?></span><input type="text" name="user_name" required maxlength="80" autocomplete="name"></label>
      <label class="field"><span class="label"><?= e(t('Ton e-mail')) ?></span><input type="email" name="user_email" required autocomplete="email"></label>
      <label class="field"><span class="label"><?= e(t('Mot de passe')) ?></span>
        <span class="password"><input type="password" name="user_pass" required minlength="10" autocomplete="new-password" data-strength><button type="button" data-reveal><?= e(t('Afficher')) ?></button></span>
        <span class="strength" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="hint"><?= e(t('10 caractères minimum. Une phrase de 4 mots, c’est solide et facile à retenir.')) ?></span></label>
      <div class="step-foot"><button class="btn btn-ghost" type="button" data-prev>← <?= e(t('Retour')) ?></button><button class="btn btn-primary btn-lg" type="button" data-next><?= e(t('Continuer')) ?> →</button></div>
    </section>

    <section class="step" data-step="3" hidden>
      <h1>🌍 <?= e(t('Visibilité')) ?></h1>
      <p class="lead"><?= e(t('Qui peut découvrir ton site ?')) ?></p>
      <label class="switch"><span><strong><?= e(t('Google et Bing peuvent indexer mon site')) ?></strong><small><?= e(t('Décoche si tu préfères construire ton site tranquillement avant de le montrer.')) ?></small></span>
        <input type="checkbox" name="index" value="1" checked></label>
      <div class="field"><span class="label"><?= e(t('Les intelligences artificielles (ChatGPT, Perplexity, Gemini…)')) ?></span>
        <div class="choices">
          <?php foreach ([
              'open' => ['🌍', t('Bienvenue'), t('Elles peuvent lire et citer ton site : visibilité maximale.'), t('Recommandé')],
              'search' => ['🔎', t('Citations seulement'), t('Elles te citent, mais n’entraînent pas leurs modèles avec tes textes.'), ''],
              'closed' => ['🔒', t('Non merci'), t('Aucun robot IA connu n’est autorisé.'), ''],
          ] as $k => [$emoji, $label, $desc, $tag]): ?>
            <label class="choice"><input type="radio" name="ai" value="<?= e($k) ?>"<?= $k === 'open' ? ' checked' : '' ?>>
              <span><?= $tag !== '' ? '<em class="tag">' . e($tag) . '</em>' : '' ?><span class="emoji"><?= $emoji ?></span><b><?= e($label) ?></b><small><?= e($desc) ?></small></span></label>
          <?php endforeach ?>
        </div></div>
      <label class="field"><span class="label"><?= e(t('Adresse de ton administration')) ?></span>
        <span class="input-group"><span><?= e(rtrim(\Eclair\Req::origin(), '/')) ?>/</span><input type="text" name="admin_path" value="admin" required pattern="[a-z0-9-]{3,40}" autocomplete="off" spellcheck="false"></span>
        <span class="hint"><?= e(t('Une adresse personnelle éloigne les robots pirates.')) ?> <button class="link-btn" type="button" id="secret-path"><?= e(t('Générer une adresse secrète')) ?></button></span></label>
      <div class="step-foot"><button class="btn btn-ghost" type="button" data-prev>← <?= e(t('Retour')) ?></button><button class="btn btn-primary btn-lg" type="button" data-next><?= e(t('Continuer')) ?> →</button></div>
    </section>

    <section class="step" data-step="4" hidden>
      <span class="big-emoji">🚀</span>
      <h1><?= e(t('Prêt pour le décollage ?')) ?></h1>
      <p class="lead"><?= e(t('Éclair va créer ton site, tes premières pages et un article d’exemple. Tu pourras tout modifier.')) ?></p>
      <ul class="checks" id="recap"></ul>
      <div class="notice bad" id="install-error" hidden><p></p></div>
      <div class="step-foot"><button class="btn btn-ghost" type="button" data-prev>← <?= e(t('Retour')) ?></button>
        <button class="btn btn-primary btn-lg" type="submit" id="install-btn"><?= e(t('Créer mon site')) ?> ⚡</button></div>
    </section>

    <section class="success" id="success" hidden>
      <span class="big-emoji">🎉</span>
      <h1><?= e(t('Ton site est en ligne !')) ?></h1>
      <p class="lead"><?= e(t('Bravo. Tout est prêt : sitemap, données structurées, llms.txt, sécurité… Il ne reste qu’à écrire.')) ?></p>
      <p class="small muted" id="admin-address"></p>
      <div class="row center">
        <a class="btn btn-lg" id="go-site" href="#" target="_blank" rel="noopener"><?= e(t('Voir mon site')) ?></a>
        <a class="btn btn-primary btn-lg" id="go-admin" href="#"><?= e(t('Aller au tableau de bord')) ?> →</a>
      </div>
    </section>
  </form>
</div>
<script type="application/json" id="install-data"><?= json_script([
    'recap' => [
        'site' => t('Site'), 'type' => t('Type'), 'account' => t('Compte'), 'visibility' => t('Visibilité'), 'admin' => t('Administration'),
        'indexed' => t('visible sur Google'), 'hidden' => t('masqué pendant la construction'), 'ai' => t('IA'),
    ],
    'errors' => ['required' => t('Ce champ est obligatoire.'), 'email' => t('Adresse e-mail invalide.'), 'short' => t('10 caractères minimum.'), 'network' => t('Erreur réseau : réessaie.')],
    'adminAddress' => t('Ton administration : {url} — garde-la en favori !'),
]) ?></script>
</body>
</html>
