<?php
/** Éditeur de blocs. Toute l'interactivité est dans assets/admin/editor.js. */
defined('ECLAIR') || exit;

use Eclair\Content;
use Eclair\Icons;
use Eclair\Settings;

$item = $v['item'];
$isPost = $item['type'] === 'post';
?>
<div class="editor-shell" id="editor">
  <header class="editor-top">
    <a class="btn btn-ghost btn-icon" href="<?= e(admin_url('content')) ?>" title="<?= e(t('Retour aux contenus')) ?>" aria-label="<?= e(t('Retour aux contenus')) ?>"><?= Icons::svg('back') ?></a>
    <span class="badge" id="status-badge"></span>
    <span class="status" id="save-status" aria-live="polite"></span>
    <span class="spacer"></span>
    <a class="btn btn-ghost btn-sm" id="view-link" href="#" target="_blank" rel="noopener" hidden><?= Icons::svg('external') ?><span><?= e(t('Voir')) ?></span></a>
    <button class="btn btn-sm" type="button" id="btn-preview"><?= Icons::svg('eye') ?><span><?= e(t('Aperçu')) ?></span></button>
    <button class="btn btn-sm" type="button" id="btn-save"><?= e(t('Enregistrer')) ?></button>
    <button class="btn btn-primary btn-sm" type="button" id="btn-publish"><?= e(t('Publier')) ?></button>
    <button class="btn btn-sm side-toggle btn-icon" type="button" id="btn-side" aria-label="<?= e(t('Réglages')) ?>"><?= Icons::svg('panel') ?></button>
  </header>

  <div class="canvas">
    <div class="doc">
      <textarea class="doc-title" id="doc-title" rows="1" placeholder="<?= e($isPost ? t('Titre de l’article') : t('Titre de la page')) ?>" aria-label="<?= e(t('Titre')) ?>"></textarea>
      <div class="key-box" id="summary-box">
        <button class="link-btn small" type="button" id="summary-add">✨ <?= e(t('Ajouter « L’essentiel » : le résumé que Google et les IA adorent citer')) ?></button>
        <div class="card" id="summary-card" hidden>
          <div class="row between"><label class="label" for="doc-summary">✨ <?= e(t('L’essentiel')) ?></label><span class="counter" id="summary-count"></span></div>
          <textarea id="doc-summary" rows="3" placeholder="<?= e(t('En 2-3 phrases : la réponse directe à la question que se pose ton lecteur.')) ?>"></textarea>
        </div>
      </div>
      <div class="blocks" id="blocks" aria-label="<?= e(t('Contenu')) ?>"></div>
      <div class="add-block"><button class="btn" type="button" id="add-end"><?= Icons::svg('plus') ?><?= e(t('Ajouter un bloc')) ?></button></div>
      <p class="center small muted"><?= e(t('Astuce : tape « / » sur une ligne vide pour insérer un bloc · Ctrl+S pour enregistrer · Ctrl+K pour un lien')) ?></p>
    </div>
  </div>

  <aside class="side" id="side" aria-label="<?= e(t('Réglages du contenu')) ?>">
    <div class="tabs" role="tablist">
      <button type="button" role="tab" aria-selected="true" data-tab="doc"><?= e(t('Document')) ?></button>
      <button type="button" role="tab" aria-selected="false" data-tab="seo">SEO</button>
      <button type="button" role="tab" aria-selected="false" data-tab="score"><?= e(t('Analyse')) ?> <span class="score good" id="tab-score">–</span></button>
    </div>

    <div class="side-pane" data-pane="doc">
      <div class="side-section">
        <h3><?= e(t('Publication')) ?></h3>
        <label class="field"><span class="label"><?= e(t('Statut')) ?></span>
          <select id="f-status"><option value="draft"><?= e(t('Brouillon')) ?></option><option value="published"><?= e(t('Publié')) ?></option></select></label>
        <label class="field"><span class="label"><?= e(t('Date de publication')) ?></span>
          <input type="datetime-local" id="f-date">
          <span class="hint"><?= e(t('Laisse vide pour « maintenant ». Une date future programme la publication.')) ?> <?= e(t('Heure du site : {tz}.', ['tz' => date_default_timezone_get()])) ?></span></label>
      </div>
      <div class="side-section">
        <h3><?= e(t('Adresse (URL)')) ?></h3>
        <label class="field"><span class="sr-only"><?= e(t('Slug')) ?></span>
          <span class="input-group"><span id="slug-prefix">/</span><input type="text" id="f-slug" spellcheck="false" autocomplete="off"></span>
          <span class="hint" id="slug-hint"><?= e(t('Courte, en minuscules, avec ton mot-clé. Si tu la changes, une redirection 301 est créée automatiquement.')) ?></span></label>
      </div>
      <?php if ($isPost): ?>
      <div class="side-section">
        <h3><?= e(t('Classement')) ?></h3>
        <label class="field"><span class="label"><?= e(t('Catégorie')) ?></span><select id="f-category"></select>
          <span class="hint"><a href="<?= e(admin_url('categories')) ?>"><?= e(t('Gérer les catégories')) ?></a></span></label>
        <div class="field"><span class="label"><?= e(t('Image à la une')) ?></span>
          <div class="picker-preview" id="f-image-preview"></div>
          <div class="row"><button class="btn btn-sm" type="button" id="f-image-pick"><?= Icons::svg('image') ?><?= e(t('Choisir')) ?></button>
          <button class="btn btn-sm btn-ghost" type="button" id="f-image-clear"><?= e(t('Retirer')) ?></button></div>
          <span class="hint"><?= e(t('Affichée en haut de l’article et sur les réseaux sociaux (1200×630 généré automatiquement).')) ?></span></div>
      </div>
      <?php endif ?>
      <div class="side-section">
        <h3><?= e(t('Auteur et langue')) ?></h3>
        <label class="field"><span class="label"><?= e(t('Auteur')) ?></span><select id="f-author"></select></label>
        <label class="field"><span class="label"><?= e(t('Langue du contenu')) ?></span><select id="f-lang"></select></label>
        <label class="field"><span class="label"><?= e(t('Traduction de…')) ?></span><select id="f-translation"></select>
          <span class="hint"><?= e(t('Relie les versions d’un même contenu : les balises hreflang sont ajoutées automatiquement.')) ?></span></label>
      </div>
      <div class="side-section">
        <h3><?= e(t('Mise en page')) ?></h3>
        <label class="check"><input type="checkbox" id="f-hide-title"><span><?= e(t('Masquer le titre (le titre du premier bandeau devient le H1)')) ?></span></label>
        <label class="check"><input type="checkbox" id="f-wide"><span><?= e(t('Pleine largeur')) ?></span></label>
        <label class="field"><span class="label"><?= e(t('Sommaire')) ?></span>
          <select id="f-toc"><option value="auto"><?= e(t('Automatique (articles de 3 intertitres et plus)')) ?></option><option value="on"><?= e(t('Toujours')) ?></option><option value="off"><?= e(t('Jamais')) ?></option></select></label>
      </div>
      <div class="side-section side-actions">
        <button class="btn btn-sm" type="button" id="btn-revisions"><?= Icons::svg('clock') ?><?= e(t('Historique des versions')) ?></button>
        <button class="btn btn-sm" type="button" id="btn-duplicate"><?= Icons::svg('copy') ?><?= e(t('Dupliquer')) ?></button>
        <button class="btn btn-sm btn-danger" type="button" id="btn-delete"><?= Icons::svg('trash') ?><?= e(t('Supprimer')) ?></button>
      </div>
    </div>

    <div class="side-pane" data-pane="seo" hidden>
      <div class="serp" aria-label="<?= e(t('Aperçu dans Google')) ?>">
        <div class="serp-site"><span class="serp-ico">⚡</span><span><b id="serp-name"></b><small id="serp-url"></small></span></div>
        <p class="serp-title" id="serp-title"></p>
        <p class="serp-desc" id="serp-desc"></p>
      </div>
      <label class="field"><span class="label"><?= e(t('Titre SEO')) ?> <span class="counter" id="c-title"></span></span>
        <input type="text" id="f-seo-title" maxlength="120">
        <span class="hint"><?= e(t('Vide = titre automatique. Idéal : 30 à 60 caractères.')) ?></span></label>
      <label class="field"><span class="label"><?= e(t('Méta-description')) ?> <span class="counter" id="c-desc"></span></span>
        <textarea id="f-seo-desc" rows="4" maxlength="320"></textarea>
        <span class="hint"><?= e(t('Le texte sous le titre dans Google. Idéal : 110 à 160 caractères, avec une promesse claire.')) ?></span></label>
      <label class="field"><span class="label"><?= e(t('Expression-clé principale')) ?></span>
        <input type="text" id="f-keyword" maxlength="80" placeholder="<?= e(t('ex. : formation IA entreprise')) ?>"></label>
      <details>
        <summary class="small"><?= e(t('Options avancées')) ?></summary>
        <label class="field"><span class="label"><?= e(t('URL canonique (facultatif)')) ?></span>
          <input type="url" id="f-canonical" placeholder="https://…"><span class="hint"><?= e(t('Seulement si ce contenu est une copie d’une autre page.')) ?></span></label>
        <label class="check"><input type="checkbox" id="f-noindex"><span><?= e(t('Ne pas indexer cette page (noindex)')) ?></span></label>
      </details>
    </div>

    <div class="side-pane" data-pane="score" hidden>
      <div class="scores">
        <div class="ring" id="ring-seo"><svg viewBox="0 0 36 36"><circle class="track" cx="18" cy="18" r="15.9"/><circle class="val" cx="18" cy="18" r="15.9" stroke-dasharray="0 100"/></svg><div><b>–</b><small>SEO</small></div></div>
        <div class="ring" id="ring-geo"><svg viewBox="0 0 36 36"><circle class="track" cx="18" cy="18" r="15.9"/><circle class="val" cx="18" cy="18" r="15.9" stroke-dasharray="0 100"/></svg><div><b>–</b><small><?= e(t('GEO · IA')) ?></small></div></div>
      </div>
      <p class="small muted" id="words-info"></p>
      <h3 class="small muted">SEO</h3>
      <ul class="checklist" id="checks-seo"></ul>
      <h3 class="small muted"><?= e(t('GEO · moteurs de réponse IA')) ?></h3>
      <ul class="checklist" id="checks-geo"></ul>
      <p class="small muted"><?= e(t('Le GEO (Generative Engine Optimization) rend ton contenu facile à comprendre et à citer par ChatGPT, Perplexity, Gemini ou les AI Overviews de Google.')) ?></p>
    </div>
  </aside>
</div>

<dialog id="revisions-dialog">
  <div class="dialog-head"><h2><?= e(t('Historique des versions')) ?></h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="<?= e(t('Fermer')) ?>"><?= Icons::svg('x') ?></button></div>
  <div class="dialog-body" id="revisions-list"></div>
</dialog>
<form id="preview-form" method="post" action="<?= e(admin_url('preview')) ?>" target="eclair-preview" hidden>
  <input type="hidden" name="_csrf" value="<?= e(\Eclair\Security::csrfToken()) ?>">
  <input type="hidden" name="data" value="">
</form>
