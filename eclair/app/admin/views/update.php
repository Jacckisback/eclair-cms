<?php
/** Mises à jour : version disponible, sauvegarde conseillée, installation en un clic, retour à la version précédente. */
defined('ECLAIR') || exit;

use Eclair\Admin;
use Eclair\AdminUpdate;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Security;
use Eclair\Settings;

$s = $v['state'];
$p = $v['pending'];
$lang = I18n::lang();
$failed = is_array($s['failed'] ?? null) && $p && ($s['failed']['version'] ?? '') === $p['version'] ? $s['failed'] : null;
$last = is_array($s['last'] ?? null) ? $s['last'] : null;
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Mises à jour')) ?></h1>
    <p><?= e(t('Éclair t’avertit dès qu’une nouvelle version sort. Tu choisis quand l’installer : un clic, en toute sécurité.')) ?></p></div></div>

  <section class="card">
    <div class="card-head"><h2><?= e(t('Version installée : {v}', ['v' => ECLAIR_VERSION])) ?></h2>
      <form method="post" action="<?= e(admin_url('update')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="check">
        <button class="btn btn-sm" type="submit"><?= Icons::svg('refresh') ?><?= e(t('Vérifier maintenant')) ?></button></form></div>
    <?php if ($p): ?>
      <p class="update-new">⚡ <strong><?= e(t('Éclair {v} est disponible.', ['v' => $p['version']])) ?></strong>
        <?php if ($p['date'] !== ''): ?><span class="muted"><?= e(t('Publiée le {d}.', ['d' => fmt_date((int) strtotime($p['date']), $lang)])) ?></span><?php endif ?>
        <?php if ($p['notes'] !== ''): ?><a href="<?= e($p['notes']) ?>" target="_blank" rel="noopener"><?= e(t('Voir les nouveautés')) ?> →</a><?php endif ?></p>
    <?php elseif (is_array($s['release'] ?? null)): ?>
      <p>✅ <?= e(t('Tu as la dernière version d’Éclair.')) ?></p>
    <?php endif ?>
    <p class="small muted">
      <?php if (!empty($s['checked'])): ?><?= e(t('Dernière vérification : {d}.', ['d' => fmt_date((int) $s['checked'], $lang, true)])) ?><?php endif ?>
      <?= e(Settings::get('updates.check', true) ? t('Éclair vérifie deux fois par jour, sans rien installer sans ton accord (réglable dans Réglages › Avancé).') : t('Vérification automatique désactivée (Réglages › Avancé) : clique sur « Vérifier maintenant ».')) ?></p>
    <?php if (!empty($s['error'])): ?>
      <p class="hint">⚠️ <?= e(t('Dernière vérification impossible : {e}.', ['e' => AdminUpdate::reason((string) $s['error'])])) ?></p>
    <?php endif ?>
    <?php if ($failed): ?>
      <div class="notice warn"><span>↩️</span><p><?= e(t('Le {d}, la version {v} ne fonctionnait pas correctement sur ton serveur : Éclair est revenu tout seul à ta version. Vérifie la version de PHP et ses extensions chez ton hébergeur, ou réessaie plus tard.', ['d' => fmt_date((int) $failed['date'], $lang, true), 'v' => $p['version']])) ?></p></div>
    <?php endif ?>
  </section>

  <?php if ($p): ?>
  <section class="card" id="installer" aria-labelledby="install-title">
    <h2 id="install-title"><?= e(t('Installer Éclair {v}', ['v' => $p['version']])) ?></h2>
    <div class="notice warn" role="note"><span>💾</span><p><strong><?= e(t('Avant toute mise à jour, fais une sauvegarde.')) ?></strong>
      <?= e(t('La mise à jour ne touche ni à tes contenus, ni à tes images, ni à tes réglages, et Éclair revient tout seul en arrière si quelque chose ne va pas. Une sauvegarde récente reste ta meilleure assurance.')) ?></p></div>
    <form method="post" action="<?= e(admin_url('backup')) ?>"><?= Security::csrfField() ?><input type="hidden" name="back" value="update">
      <?= Admin::partial('partials/confirm', []) ?>
      <button class="btn" type="submit"<?= $v['zip'] ? '' : ' disabled' ?>><?= Icons::svg('download') ?><?= e(t('Télécharger une sauvegarde')) ?></button>
      <?php if (!$v['zip']): ?><p class="hint"><?= e(t('Extension zip indisponible : copie les dossiers data/ et media/ par FTP.')) ?></p><?php endif ?></form>
    <hr>
    <?php if ($v['blockers']): ?>
      <div class="notice bad" role="alert"><span>⛔</span><div>
        <p><strong><?= e(t('Mise à jour en un clic impossible sur ce serveur :')) ?></strong></p>
        <ul><?php foreach ($v['blockers'] as $b): ?><li><?= e($b) ?></li><?php endforeach ?></ul>
        <p><?= e(t('Tu peux toujours mettre à jour par FTP :')) ?> <a href="https://github.com/Jacckisback/eclair-cms/blob/main/docs/GUIDE.md#mettre-à-jour-sauvegarder-déménager" target="_blank" rel="noopener"><?= e(t('guide de mise à jour')) ?></a></p>
      </div></div>
    <?php else: ?>
      <form method="post" action="<?= e(admin_url('update')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="install">
        <label class="check"><input type="checkbox" name="saved" value="1" required><span><?= e(t('J’ai une sauvegarde récente de mon site.')) ?></span></label>
        <?= Admin::partial('partials/confirm', ['required' => false, 'hint' => t('Inutile si tu viens de le saisir (moins de 15 minutes), par exemple pour la sauvegarde.')]) ?>
        <button class="btn btn-primary" type="submit" data-busy="<?= e(t('Mise à jour en cours… ne ferme pas cette page.')) ?>"><?= Icons::svg('download') ?><?= e(t('Mettre à jour vers {v}', ['v' => $p['version']])) ?></button>
      </form>
    <?php endif ?>
    <details class="small update-how"><summary><?= e(t('Comment ça marche ?')) ?></summary>
      <ol>
        <li><?= e(t('Éclair télécharge la nouvelle version depuis GitHub et vérifie sa signature : seule une version publiée par le mainteneur d’Éclair est acceptée.')) ?></li>
        <li><?= e(t('Il contrôle chaque fichier, et sa compatibilité avec ta version de PHP, avant de toucher à quoi que ce soit.')) ?></li>
        <li><?= e(t('Il met l’ancienne version de côté et installe la nouvelle en un instant : contenus, images, comptes et réglages ne bougent pas.')) ?></li>
        <li><?= e(t('Il vérifie que ton site et ton administration s’affichent comme avant ; sinon, il revient tout seul à l’ancienne version.')) ?></li>
      </ol></details>
  </section>
  <?php endif ?>

  <?php if ($v['previous'] !== ''): ?>
  <section class="card">
    <h2><?= e(t('Revenir à la version précédente')) ?></h2>
    <p class="small muted"><?= e(t('La version {v} a été gardée lors de la dernière mise à jour. Si quelque chose ne te convient pas, reviens-y en un clic : tes contenus ne changent pas.', ['v' => $v['previous']])) ?></p>
    <form method="post" action="<?= e(admin_url('update')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="rollback">
      <?= Admin::partial('partials/confirm', []) ?>
      <button class="btn" type="submit" data-confirm="<?= e(t('Revenir à la version {v} ?', ['v' => $v['previous']])) ?>" data-busy="<?= e(t('Retour en cours…')) ?>"><?= Icons::svg('back') ?><?= e(t('Revenir à la version {v}', ['v' => $v['previous']])) ?></button></form>
  </section>
  <?php endif ?>

  <?php if ($last): ?>
    <?php $vars = ['from' => (string) $last['from'], 'to' => (string) $last['to'], 'd' => fmt_date((int) $last['date'], $lang, true)]; ?>
    <p class="small muted"><?= e(!empty($last['rollback']) ? t('Dernière opération : retour de {from} à {to}, le {d}.', $vars) : t('Dernière mise à jour : de {from} à {to}, le {d}.', $vars)) ?></p>
  <?php endif ?>
</div>
