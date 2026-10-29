<?php
defined('ECLAIR') || exit;

use Eclair\Admin;
use Eclair\Ai;
use Eclair\Content;
use Eclair\Icons;
use Eclair\Security;
use Eclair\Seo;
use Eclair\Theme;

$s = $v['s'];
$tab = $v['tab'];
$tabs = [
    'general' => t('Général'), 'appearance' => t('Apparence'), 'seo' => t('SEO'),
    'geo' => t('IA & GEO'), 'ai' => t('Assistant IA'), 'contact' => t('Contact'), 'advanced' => t('Avancé'),
];
$sw = static function (string $name, bool $on, string $title, string $hint = ''): string {
    return '<label class="switch"><span><strong>' . e($title) . '</strong>' . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</span>'
        . '<input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '></label>';
};
$media = static fn (string $name, string $value, string $label, string $hint = '') => Admin::partial('partials/media-field', ['name' => $name, 'value' => $value, 'label' => $label, 'hint' => $hint, 'media' => $v['media']]);
?>
<div class="page-narrow">
  <div class="topbar"><div><h1><?= e(t('Réglages')) ?></h1><p><?= e(t('Tout est pré-réglé pour un site rapide et bien référencé : ajuste seulement ce dont tu as besoin.')) ?></p></div></div>
  <nav class="tabs" aria-label="<?= e(t('Sections des réglages')) ?>">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="<?= e(admin_url('settings?tab=' . $key)) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach ?>
  </nav>
  <form method="post" action="<?= e(admin_url('settings')) ?>" class="card" data-dirty>
    <?= Security::csrfField() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">

  <?php if ($tab === 'general'): ?>
    <label class="field"><span class="label"><?= e(t('Nom du site')) ?></span><input type="text" name="name" value="<?= e($s['name']) ?>" required maxlength="100"></label>
    <label class="field"><span class="label"><?= e(t('Slogan')) ?></span><input type="text" name="tagline" value="<?= e($s['tagline']) ?>" maxlength="160">
      <span class="hint"><?= e(t('Une phrase qui dit ce que tu fais. Elle apparaît dans le titre de l’accueil.')) ?></span></label>
    <label class="field"><span class="label"><?= e(t('Description de l’accueil (méta-description)')) ?></span><textarea name="description" rows="3" maxlength="320"><?= e($s['description']) ?></textarea></label>
    <label class="field"><span class="label"><?= e(t('Adresse officielle du site')) ?></span><input type="url" name="url" value="<?= e($s['url']) ?>" required>
      <span class="hint"><?= e(t('Utilisée pour les URL canoniques, le sitemap et les partages. Choisis la version avec ou sans « www » une fois pour toutes.')) ?></span></label>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Langue du site')) ?></span><select name="lang">
        <?php foreach (Content::LANGS as $code => $name): ?><option value="<?= e($code) ?>"<?= $s['lang'] === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select></label>
      <label class="field"><span class="label"><?= e(t('Fuseau horaire')) ?></span><select name="timezone">
        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option<?= $s['timezone'] === $tz ? ' selected' : '' ?>><?= e($tz) ?></option><?php endforeach ?></select></label>
    </div>
    <label class="field"><span class="label"><?= e(t('Page d’accueil')) ?></span><select name="home">
      <option value=""><?= e(t('Les derniers articles du blog')) ?></option>
      <?php foreach ($v['pages'] as $p): ?><option value="<?= e($p['id']) ?>"<?= $s['home'] === $p['id'] ? ' selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach ?>
    </select></label>
    <hr>
    <?= $sw('blog_enabled', (bool) $s['blog']['enabled'], t('Blog activé'), t('Articles, catégories, flux RSS et pages auteurs.')) ?>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Adresse du blog')) ?></span><span class="input-group"><span>/</span><input type="text" name="blog_base" value="<?= e($s['blog']['base']) ?>"></span>
        <span class="hint"><?= e(t('Si tu la changes, les anciennes adresses sont redirigées.')) ?></span></label>
      <label class="field"><span class="label"><?= e(t('Articles par page')) ?></span><input type="number" name="per_page" min="1" max="48" value="<?= (int) $s['blog']['per_page'] ?>"></label>
    </div>
    <label class="field"><span class="label"><?= e(t('Titre du blog')) ?></span><input type="text" name="blog_title" value="<?= e($s['blog']['title']) ?>" maxlength="80"></label>
    <label class="field"><span class="label"><?= e(t('Présentation du blog')) ?></span><textarea name="blog_description" rows="2"><?= e($s['blog']['description']) ?></textarea></label>
    <?= $sw('search', (bool) $s['search'], t('Recherche sur le site'), t('Ajoute une loupe dans le menu et une page de résultats.')) ?>

  <?php elseif ($tab === 'appearance'): ?>
    <?= $media('logo', (string) $s['appearance']['logo'], t('Logo'), t('PNG ou WebP sur fond transparent, environ 400 px de large.')) ?>
    <?= $media('favicon', (string) $s['appearance']['favicon'], t('Icône du site (favicon)'), t('Image carrée d’au moins 512 px. Sans icône, un éclair à ta couleur est généré.')) ?>
    <div class="field"><span class="label"><?= e(t('Couleur principale')) ?></span>
      <div class="row"><input type="color" name="accent" value="<?= e($s['appearance']['accent']) ?>" id="accent">
        <?php foreach (['#5b4cf0', '#2563eb', '#0f766e', '#16a34a', '#ea580c', '#dc2626', '#db2777', '#111827'] as $c): ?>
          <button class="btn btn-sm btn-icon" type="button" data-color="<?= e($c) ?>" aria-label="<?= e($c) ?>"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="<?= e($c) ?>"/></svg></button>
        <?php endforeach ?></div>
      <span class="hint"><?= e(t('Le contraste des textes est ajusté automatiquement pour rester lisible (normes WCAG).')) ?></span></div>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Police des titres')) ?></span><select name="font_head">
        <?php foreach (Theme::FONTS as $key => [$label]): ?><option value="<?= e($key) ?>"<?= $s['appearance']['font_head'] === $key ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach ?></select></label>
      <label class="field"><span class="label"><?= e(t('Police du texte')) ?></span><select name="font_body">
        <?php foreach (Theme::FONTS as $key => [$label]): ?><option value="<?= e($key) ?>"<?= $s['appearance']['font_body'] === $key ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach ?></select></label>
    </div>
    <p class="hint"><?= e(t('Polices système : zéro téléchargement, affichage instantané.')) ?></p>
    <label class="field"><span class="label"><?= e(t('Mode sombre')) ?></span><select name="dark">
      <option value="auto"<?= $s['appearance']['dark'] === 'auto' ? ' selected' : '' ?>><?= e(t('Automatique (selon l’appareil du visiteur)')) ?></option>
      <option value="light"<?= $s['appearance']['dark'] === 'light' ? ' selected' : '' ?>><?= e(t('Toujours clair')) ?></option></select></label>
    <label class="field"><span class="label"><?= e(t('Texte du pied de page')) ?></span><textarea name="footer" rows="2"><?= e($s['appearance']['footer']) ?></textarea></label>
    <?= $sw('credit', (bool) $s['appearance']['credit'], t('Afficher « Propulsé par ⚡ Éclair »')) ?>

  <?php elseif ($tab === 'seo'): ?>
    <?= $sw('index', (bool) $s['seo']['index'], t('Visible par les moteurs de recherche'), t('Décoche pendant la construction du site : tout passe en noindex.')) ?>
    <label class="field"><span class="label"><?= e(t('Format des titres')) ?></span><input type="text" name="title_format" value="<?= e($s['seo']['title_format']) ?>">
      <span class="hint"><?= e(t('{title} = titre du contenu, {site} = nom du site. Ex. : « {title} | {site} ».')) ?></span></label>
    <?= $media('image', (string) $s['seo']['image'], t('Image de partage par défaut'), t('Utilisée sur les réseaux sociaux quand un contenu n’a pas d’image.')) ?>
    <hr>
    <h2><?= e(t('Qui est derrière le site ? (données structurées)')) ?></h2>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Type')) ?></span><select name="entity">
        <?php foreach (['Organization' => t('Entreprise / organisation'), 'Person' => t('Personne (freelance, auteur)'), 'LocalBusiness' => t('Commerce local (adresse physique)')] as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= $s['seo']['entity'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></label>
      <label class="field"><span class="label"><?= e(t('Nom officiel')) ?></span><input type="text" name="entity_name" value="<?= e($s['seo']['entity_name']) ?>" placeholder="<?= e($s['name']) ?>"></label>
      <label class="field"><span class="label"><?= e(t('E-mail public')) ?></span><input type="email" name="email" value="<?= e($s['seo']['email']) ?>"></label>
      <label class="field"><span class="label"><?= e(t('Téléphone')) ?></span><input type="tel" name="phone" value="<?= e($s['seo']['phone']) ?>"></label>
    </div>
    <details<?= $s['seo']['entity'] === 'LocalBusiness' ? ' open' : '' ?>><summary class="small"><?= e(t('Adresse et horaires (commerce local)')) ?></summary>
      <div class="grid-2">
        <label class="field"><span class="label"><?= e(t('Rue')) ?></span><input type="text" name="street" value="<?= e($s['seo']['street']) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Ville')) ?></span><input type="text" name="city" value="<?= e($s['seo']['city']) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Code postal')) ?></span><input type="text" name="zip" value="<?= e($s['seo']['zip']) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Région')) ?></span><input type="text" name="region" value="<?= e($s['seo']['region']) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Pays (code, ex. FR)')) ?></span><input type="text" name="country" value="<?= e($s['seo']['country']) ?>"></label>
        <label class="field"><span class="label"><?= e(t('Gamme de prix')) ?></span><input type="text" name="price_range" value="<?= e($s['seo']['price_range']) ?>" placeholder="€€"></label>
      </div>
      <label class="field"><span class="label"><?= e(t('Horaires')) ?></span><input type="text" name="hours" value="<?= e($s['seo']['hours']) ?>" placeholder="Mo-Fr 09:00-18:00"></label>
    </details>
    <hr>
    <h2><?= e(t('Profils sociaux')) ?></h2>
    <p class="hint"><?= e(t('Ils relient ton site à ton identité en ligne (sameAs) : un signal de confiance pour Google et les IA.')) ?></p>
    <div class="grid-2">
      <?php foreach (['linkedin' => 'LinkedIn', 'x' => 'X (Twitter)', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'github' => 'GitHub', 'other' => t('Autre')] as $k => $l): ?>
        <label class="field"><span class="label"><?= e($l) ?></span><input type="url" name="social_<?= e($k) ?>" value="<?= e($s['social'][$k]) ?>" placeholder="https://"></label>
      <?php endforeach ?>
      <label class="field"><span class="label"><?= e(t('Compte X pour les cartes de partage')) ?></span><input type="text" name="twitter" value="<?= e($s['seo']['twitter']) ?>" placeholder="@moncompte"></label>
    </div>
    <hr>
    <h2><?= e(t('Outils pour webmasters')) ?></h2>
    <label class="field"><span class="label">Google Search Console</span><input type="text" name="google" value="<?= e($s['seo']['google']) ?>" placeholder="<?= e(t('Code ou balise <meta> complète')) ?>">
      <span class="hint"><?= e(t('Search Console › Ajouter une propriété › Préfixe d’URL › Balise HTML : colle la balise ici, Éclair extrait le code.')) ?></span></label>
    <label class="field"><span class="label">Bing Webmaster Tools</span><input type="text" name="bing" value="<?= e($s['seo']['bing']) ?>" placeholder="<?= e(t('Code ou balise <meta> complète')) ?>"></label>
    <?= $sw('indexnow', (bool) $s['indexnow'], t('IndexNow : signaler chaque publication à Bing, Yandex… instantanément'), t('Bing alimente aussi Copilot et la recherche de ChatGPT. Clé : {key}', ['key' => $v['indexnow']])) ?>

  <?php elseif ($tab === 'geo'): ?>
    <div class="notice"><span>🤖</span><p><?= e(t('Le GEO (Generative Engine Optimization) aide les moteurs de réponse — ChatGPT, Perplexity, Gemini, Claude, les AI Overviews de Google — à comprendre ton site et à le citer comme source.')) ?></p></div>
    <div class="field"><span class="label"><?= e(t('Robots des intelligences artificielles')) ?></span>
      <div class="choices">
        <?php foreach ([
            'open' => ['🌍', t('Ouvert'), t('Toutes les IA peuvent lire et citer ton site. Visibilité maximale.'), t('Recommandé')],
            'search' => ['🔎', t('Citations seulement'), t('Les moteurs de réponse peuvent te citer, mais pas entraîner leurs modèles sur tes textes.'), ''],
            'closed' => ['🔒', t('Fermé'), t('Aucun robot IA connu n’est autorisé. Tu disparais des réponses des IA.'), ''],
        ] as $k => [$emoji, $label, $desc, $tag]): ?>
          <label class="choice"><input type="radio" name="ai" value="<?= e($k) ?>"<?= $s['geo']['ai'] === $k ? ' checked' : '' ?>>
            <span><?= $tag !== '' ? '<em class="tag">' . e($tag) . '</em>' : '' ?><span class="emoji"><?= $emoji ?></span><b><?= e($label) ?></b><small><?= e($desc) ?></small></span></label>
        <?php endforeach ?>
      </div>
      <span class="hint"><?= e(t('Robots gérés : {list}…', ['list' => implode(', ', array_slice(array_merge(Seo::AI_SEARCH, Seo::AI_TRAINING), 0, 10))])) ?></span></div>
    <?= $sw('llms', (bool) $s['geo']['llms'], t('Publier /llms.txt'), t('La carte de ton site pour les IA (standard llmstxt.org).')) ?>
    <?= $sw('llms_full', (bool) $s['geo']['llms_full'], t('Publier /llms-full.txt'), t('Tout ton contenu en Markdown, dans un seul fichier.')) ?>
    <?= $sw('markdown', (bool) $s['geo']['markdown'], t('Version Markdown de chaque page'), t('Ajoute .md à n’importe quelle URL : un texte propre que les IA lisent sans effort.')) ?>
    <?= $sw('signal', (bool) $s['geo']['signal'], t('Ajouter la directive Content-Signal au robots.txt'), t('Proposition de Cloudflare pour exprimer tes préférences d’usage par les IA. Non standard : Lighthouse la signale comme inconnue.')) ?>
    <label class="field"><span class="label"><?= e(t('Présentation de ton activité pour les IA')) ?></span>
      <textarea name="about" rows="6" placeholder="<?= e(t('Qui es-tu, que proposes-tu, pour qui, où, depuis quand ? Des faits vérifiables, des chiffres, tes points forts.')) ?>"><?= e($s['geo']['about']) ?></textarea>
      <span class="hint"><?= e(t('Insérée en tête de llms.txt : c’est souvent ce que les IA retiennent de toi.')) ?></span></label>
    <p class="small"><a href="<?= e(path_to('/llms.txt')) ?>" target="_blank" rel="noopener">llms.txt ↗</a> · <a href="<?= e(path_to('/llms-full.txt')) ?>" target="_blank" rel="noopener">llms-full.txt ↗</a> · <a href="<?= e(path_to('/robots.txt')) ?>" target="_blank" rel="noopener">robots.txt ↗</a> · <a href="<?= e(path_to('/index.md')) ?>" target="_blank" rel="noopener">index.md ↗</a></p>

  <?php elseif ($tab === 'ai'):
    $ai = $s['ai'];
    $state = $v['ai'];
    $usage = $state['usage'];
    $key = static function (string $name, string $label, string $help, string $placeholder = '') use ($state): string {
        $field = str_replace('.', '_', $name);
        $hint = $state['hints'][$name] ?? '';
        return '<label class="field"><span class="label">' . e($label) . '</span><input type="password" name="key_' . e($field) . '" autocomplete="off" spellcheck="false" placeholder="'
            . e($hint !== '' ? t('Clé enregistrée : {hint} (colle une nouvelle clé pour la remplacer)', ['hint' => $hint]) : $placeholder) . '">'
            . '<span class="hint">' . $help . '</span></label>'
            . ($hint !== '' ? '<label class="check small"><input type="checkbox" name="remove_' . e($field) . '" value="1"><span>' . e(t('Supprimer la clé enregistrée')) . '</span></label>' : '');
    };
    $link = static fn (string $url, string $text): string => '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($text) . ' ↗</a>';
    $tags = [
        'claude-opus-5-5' => t('le plus doué'), 'claude-sonnet-5-5' => t('équilibré'), 'claude-haiku-5-5' => t('rapide et économique'),
        'gpt-6-astra' => t('le plus doué'), 'gpt-6.1-sol' => t('équilibré'), 'gpt-6.1-terra' => t('rapide'), 'gpt-6-luna' => t('économique'),
    ];
  ?>
    <div id="ai-settings">
    <div class="notice"><span>✨</span><p><?= e(t('L’assistant rédige des brouillons, améliore tes textes et crée des images, dans le ton de ton site. Tu gardes la main : rien n’est publié sans ton clic.')) ?></p></div>
    <?php if (!$state['crypto']): ?><div class="notice bad"><span>⚠️</span><p><?= e(t('Chiffrement indisponible sur ce serveur (extensions sodium et openssl absentes) : impossible d’enregistrer la clé.')) ?></p></div><?php endif ?>
    <h2><?= e(t('Intelligence artificielle')) ?></h2>
    <div class="field"><span class="label"><?= e(t('Fournisseur')) ?></span>
      <div class="choices">
        <?php foreach ([
            '' => ['⏸️', t('Désactivé'), t('Aucune donnée n’est envoyée à une IA.'), ''],
            'anthropic' => ['✳️', 'Claude', t('Par Anthropic. Excellent en rédaction, nuance et respect des consignes.'), t('Recommandé')],
            'openai' => ['🤖', 'ChatGPT', t('Par OpenAI. Polyvalent ; sa clé sert aussi aux images GPT Image.'), ''],
        ] as $k => [$emoji, $label, $desc, $tag]): ?>
          <label class="choice"><input type="radio" name="provider" value="<?= e($k) ?>" data-ai-provider<?= $ai['provider'] === $k ? ' checked' : '' ?>>
            <span><?= $tag !== '' ? '<em class="tag">' . e($tag) . '</em>' : '' ?><span class="emoji"><?= $emoji ?></span><b><?= e($label) ?></b><small><?= e($desc) ?></small></span></label>
        <?php endforeach ?>
      </div></div>
    <?php foreach (Ai::PROVIDERS as $p => $name): ?>
      <div class="ai-box" data-ai-for="<?= e($p) ?>"<?= $ai['provider'] === $p ? '' : ' hidden' ?>>
        <?= $key('ai.' . $p, t('Clé d’API {name}', ['name' => $name]), $p === 'anthropic'
            ? e(t('À créer sur')) . ' ' . $link('https://console.anthropic.com/settings/keys', 'console.anthropic.com') . ' — ' . e(t('chiffrée sur ton serveur, jamais affichée.'))
            : e(t('À créer sur')) . ' ' . $link('https://platform.openai.com/api-keys', 'platform.openai.com') . ' — ' . e(t('chiffrée sur ton serveur, jamais affichée.')), $p === 'anthropic' ? 'sk-ant-…' : 'sk-…') ?>
        <label class="field"><span class="label"><?= e(t('Modèle')) ?></span><select name="model_<?= e($p) ?>">
          <?php foreach (Ai::MODELS[$p] as $id => [$label, $in, $out]): ?>
            <option value="<?= e($id) ?>"<?= Ai::model($p) === $id ? ' selected' : '' ?>><?= e($label . ' — ' . ($tags[$id] ?? '') . ' · ' . t('≈ {cost} par article', ['cost' => Ai::money(round((25000 * $in + 7000 * $out) / 1_000_000, 3))])) ?></option>
          <?php endforeach ?></select></label>
        <p class="row"><button class="btn btn-sm" type="button" data-ai-test="<?= e($p) ?>"><?= e(t('Tester la connexion')) ?></button><span class="small" data-ai-result role="status"></span></p>
      </div>
    <?php endforeach ?>
    <hr>
    <h2><?= e(t('Images')) ?></h2>
    <div class="field"><span class="label"><?= e(t('Générateur d’images')) ?></span>
      <div class="choices">
        <?php foreach ([
            '' => ['🖼️', t('Aucun'), t('L’assistant puise dans ta médiathèque et dans les photos que tu lui joins.')],
            'openai' => ['🎨', 'GPT Image 2.5', t('Par OpenAI. Polyvalent, suit très bien les consignes.')],
            'bfl' => ['📸', 'FLUX 3', t('Par Black Forest Labs. Photos très réalistes.')],
            'ideogram' => ['🔤', 'Ideogram 3', t('Idéal pour les visuels avec du texte (affiches, bannières).')],
        ] as $k => [$emoji, $label, $desc]): ?>
          <label class="choice"><input type="radio" name="image" value="<?= e($k) ?>" data-ai-image<?= $ai['image'] === $k ? ' checked' : '' ?>>
            <span><span class="emoji"><?= $emoji ?></span><b><?= e($label) ?></b><small><?= e($desc) ?><?= $k !== '' ? ' ' . e(t('≈ {cost} l’image.', ['cost' => Ai::money(Ai::IMAGES[$k][2])])) : '' ?></small></span></label>
        <?php endforeach ?>
      </div></div>
    <div class="ai-box" data-ai-image-for="openai"<?= $ai['image'] === 'openai' ? '' : ' hidden' ?>>
      <?= $key('image.openai', t('Clé d’API OpenAI pour les images'), e(t('Facultative si ChatGPT est ton fournisseur : sa clé est utilisée.')) . ' ' . $link('https://platform.openai.com/api-keys', 'platform.openai.com'), 'sk-…') ?></div>
    <div class="ai-box" data-ai-image-for="bfl"<?= $ai['image'] === 'bfl' ? '' : ' hidden' ?>>
      <?= $key('image.bfl', t('Clé d’API Black Forest Labs'), e(t('À créer sur')) . ' ' . $link('https://dashboard.bfl.ai', 'dashboard.bfl.ai')) ?></div>
    <div class="ai-box" data-ai-image-for="ideogram"<?= $ai['image'] === 'ideogram' ? '' : ' hidden' ?>>
      <?= $key('image.ideogram', t('Clé d’API Ideogram'), e(t('À créer sur')) . ' ' . $link('https://ideogram.ai/manage-api', 'ideogram.ai')) ?></div>
    <hr>
    <h2><?= e(t('Profil éditorial')) ?></h2>
    <p class="hint"><?= e(t('Ce que l’IA doit savoir pour écrire comme toi. Laisse-la le rédiger à partir de ton site, puis ajuste-le.')) ?></p>
    <p class="row"><button class="btn" type="button" data-ai-profile<?= Ai::enabled() ? '' : ' disabled' ?>>✨ <?= e(t('Rédiger mon profil à partir de mon site')) ?></button>
      <span class="small muted" data-ai-profile-status role="status"><?= Ai::enabled() ? '' : e(t('Enregistre d’abord un fournisseur d’IA et sa clé.')) ?></span></p>
    <?php foreach ([
        'about' => [t('Qui tu es et ce que tu proposes'), t('Atelier de céramique à Lyon depuis 2012 : cours pour adultes, stages d’été, pièces sur commande.')],
        'audience' => [t('Pour qui tu écris'), t('Débutants curieux de 25 à 55 ans qui cherchent une activité créative et apaisante.')],
        'tone' => [t('Ton et style'), t('Chaleureux et expert, vouvoiement, phrases courtes, une pointe d’humour.')],
        'values' => [t('Valeurs'), t('Patience, fait main, matériaux locaux, transmission.')],
        'rules' => [t('Règles à respecter'), t('Toujours proposer un lien vers la page contact. Ne jamais annoncer de prix.')],
        'visual' => [t('Style des images'), t('Photos lumineuses et naturelles, tons chauds, mains au travail, sans texte dans l’image.')],
    ] as $k => [$label, $placeholder]): ?>
      <label class="field"><span class="label"><?= e($label) ?></span><textarea name="profile_<?= e($k) ?>" rows="2" maxlength="2000" placeholder="<?= e($placeholder) ?>"><?= e((string) ($ai['profile'][$k] ?? '')) ?></textarea></label>
    <?php endforeach ?>
    <hr>
    <h2><?= e(t('Limites et confidentialité')) ?></h2>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Budget mensuel maximum (dollars)')) ?></span><input type="number" name="budget" min="0" step="1" value="<?= e((string) $ai['budget']) ?>">
        <span class="hint"><?= e(t('Ce mois-ci : {spent} ({calls} demandes, {images} images). 0 = sans limite.', ['spent' => Ai::money((float) $usage['cost']), 'calls' => (int) $usage['calls'], 'images' => (int) $usage['images']])) ?></span></label>
      <label class="field"><span class="label"><?= e(t('Demandes par personne et par jour')) ?></span><input type="number" name="daily" min="0" step="1" value="<?= (int) $ai['daily'] ?>">
        <span class="hint"><?= e(t('0 = sans limite.')) ?></span></label>
    </div>
    <?php if ((float) $ai['budget'] > 0): ?><?php $pct = (int) min(100, round((float) $usage['cost'] / (float) $ai['budget'] * 100)) ?><div class="progress" role="progressbar" aria-label="<?= e(t('Budget utilisé ce mois-ci')) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>"><i data-width="<?= $pct ?>"></i></div><?php endif ?>
    <?= $sw('editors', (bool) $ai['editors'], t('Les rédacteurs peuvent utiliser l’assistant'), t('Les administrateurs y ont toujours accès.')) ?>
    <div class="notice"><span>🔒</span><p><?= e(t('Tes clés sont chiffrées sur ton serveur : elles ne sont jamais envoyées au navigateur ni incluses dans les sauvegardes. Les textes confiés à l’assistant sont transmis au fournisseur choisi pour être traités : n’y mets pas de données sensibles.')) ?></p></div>
    </div>

  <?php elseif ($tab === 'contact'): ?>
    <label class="field"><span class="label"><?= e(t('Recevoir les messages à l’adresse')) ?></span><input type="email" name="email" value="<?= e($s['contact']['email']) ?>" placeholder="<?= e(t('par défaut : l’e-mail du premier administrateur')) ?>"></label>
    <?= $sw('store', (bool) $s['contact']['store'], t('Conserver aussi les messages dans l’administration'), t('Recommandé : aucun message perdu, même si l’e-mail n’arrive pas.')) ?>
    <label class="field"><span class="label"><?= e(t('Message de confirmation')) ?></span><input type="text" name="thanks" value="<?= e($s['contact']['thanks']) ?>" placeholder="<?= e(Theme::t('form_thanks')) ?>"></label>
    <p class="hint"><?= e(t('Protection anti-spam sans captcha : champ piège invisible, jeton vérifié, limite de 5 messages par 10 minutes et par visiteur.')) ?></p>

  <?php else: ?>
    <div class="notice warn"><span>⚠️</span><p><?= e(t('Réglages techniques : modifie-les seulement si tu sais pourquoi.')) ?></p></div>
    <?= \Eclair\Admin::partial('partials/confirm', []) ?>
    <label class="field"><span class="label"><?= e(t('Adresse de l’administration')) ?></span>
      <span class="input-group"><span><?= e(rtrim((string) $s['url'], '/')) ?>/</span><input type="text" name="admin_path" value="<?= e($s['admin_path']) ?>" pattern="[a-z0-9\-]{3,40}" required></span>
      <span class="hint"><?= e(t('Une adresse personnalisée éloigne les robots qui testent des mots de passe. Note-la bien !')) ?></span></label>
    <?= $sw('https', (bool) $s['security']['https'], t('Forcer HTTPS'), t('Redirige toutes les visites en http:// vers https://.')) ?>
    <?= $sw('canonical', (bool) $s['security']['canonical'], t('Rediriger vers l’adresse officielle'), t('www / sans www et http / https : une seule version, pas de contenu dupliqué.') . ' ' . (\Eclair\Settings::verified() ? t('Adresse vérifiée ✓') : t('Active dès que l’adresse est vérifiée (page Sécurité).'))) ?>
    <?= $sw('hsts', (bool) $s['security']['hsts'], t('HSTS'), t('Les navigateurs n’utiliseront plus jamais http:// pour ton site (1 an). À activer quand HTTPS fonctionne parfaitement.')) ?>
    <?= $sw('cache', (bool) $s['cache'], t('Cache statique des pages'), t('Les pages sont servies en quelques millisecondes. Le cache se vide tout seul à chaque modification.')) ?>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Déconnexion après inactivité (minutes)')) ?></span><input type="number" name="idle" min="15" max="1440" value="<?= (int) $s['security']['idle'] ?>"></label>
      <label class="field"><span class="label"><?= e(t('Site derrière un proxy / CDN')) ?></span><select name="proxy">
        <?php foreach (['' => t('Non (recommandé)'), 'HTTP_CF_CONNECTING_IP' => 'Cloudflare', 'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For', 'HTTP_X_REAL_IP' => 'X-Real-IP'] as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= $s['security']['proxy'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
        <span class="hint"><?= e(t('Pour connaître la vraie adresse IP des visiteurs (limitation des tentatives).')) ?></span></label>
    </div>
  <?php endif ?>
    <div class="row"><button class="btn btn-primary" type="submit"><?= Icons::svg('check') ?><?= e(t('Enregistrer')) ?></button></div>
  </form>
  <?php if ($tab === 'advanced'): ?>
    <section class="card">
      <h2><?= e(t('Maintenance')) ?></h2>
      <div class="row">
        <form method="post" action="<?= e(admin_url('cache')) ?>"><?= Security::csrfField() ?><input type="hidden" name="back" value="settings?tab=advanced">
          <button class="btn" type="submit"><?= Icons::svg('refresh') ?><?= e(t('Vider le cache')) ?></button></form>
        <a class="btn" href="<?= e(admin_url('security')) ?>"><?= Icons::svg('download') ?><?= e(t('Sauvegarde et sécurité')) ?></a>
      </div>
    </section>
  <?php endif ?>
</div>
