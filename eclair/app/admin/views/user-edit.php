<?php
defined('ECLAIR') || exit;

use Eclair\Admin;
use Eclair\Icons;
use Eclair\Security;

$u = $v['target'];
$self = $u['id'] === $v['user']['id'];
?>
<div class="page-narrow">
  <div class="topbar"><div><h1><?= e($u['name']) ?></h1><p><?= e($u['email']) ?></p></div><a class="btn" href="<?= e(admin_url('users')) ?>"><?= Icons::svg('back') ?><?= e(t('Retour')) ?></a></div>
  <form class="card" method="post" action="<?= e(admin_url('users/' . $u['id'])) ?>">
    <?= Security::csrfField() ?>
    <label class="field"><span class="label"><?= e(t('Nom')) ?></span><input type="text" name="name" value="<?= e($u['name']) ?>" required></label>
    <label class="field"><span class="label"><?= e(t('E-mail')) ?></span><input type="email" name="email" value="<?= e($u['email']) ?>" required></label>
    <label class="field"><span class="label"><?= e(t('Rôle')) ?></span><select name="role"<?= $self ? ' disabled' : '' ?>>
      <option value="editor"<?= $u['role'] === 'editor' ? ' selected' : '' ?>><?= e(t('Rédacteur')) ?></option>
      <option value="admin"<?= $u['role'] === 'admin' ? ' selected' : '' ?>><?= e(t('Administrateur')) ?></option></select></label>
    <?= Admin::partial('partials/confirm', []) ?>
    <button class="btn btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button>
  </form>
  <form class="card" method="post" action="<?= e(admin_url('users/' . $u['id'])) ?>">
    <h2><?= e(t('Accès')) ?></h2>
    <?= Security::csrfField() ?>
    <?= Admin::partial('partials/confirm', []) ?>
    <div class="row">
      <button class="btn" type="submit" name="action" value="reset" data-confirm="<?= e(t('Générer un nouveau mot de passe provisoire ? L’ancien ne fonctionnera plus.')) ?>"><?= Icons::svg('key') ?><?= e(t('Réinitialiser le mot de passe')) ?></button>
      <?php if (!empty($u['totp']['on'])): ?>
      <button class="btn" type="submit" name="action" value="disable2fa" data-confirm="<?= e(t('Désactiver la double authentification de ce compte (téléphone perdu) ?')) ?>"><?= Icons::svg('lock') ?><?= e(t('Désactiver la 2FA')) ?></button>
      <?php endif ?>
      <?php if (!$self): ?>
      <button class="btn btn-danger" type="submit" name="action" value="delete" data-confirm="<?= e(t('Supprimer ce compte ? Ses contenus resteront en ligne.')) ?>"><?= Icons::svg('trash') ?><?= e(t('Supprimer le compte')) ?></button>
      <?php endif ?>
    </div>
  </form>
</div>
