<?php
defined('ECLAIR') || exit;

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
    <button class="btn btn-primary" type="submit"><?= e(t('Enregistrer')) ?></button>
  </form>
  <section class="card">
    <h2><?= e(t('Accès')) ?></h2>
    <div class="row">
      <form method="post" action="<?= e(admin_url('users/' . $u['id'])) ?>" data-confirm="<?= e(t('Générer un nouveau mot de passe provisoire ? L’ancien ne fonctionnera plus.')) ?>">
        <?= Security::csrfField() ?><input type="hidden" name="action" value="reset"><button class="btn" type="submit"><?= Icons::svg('key') ?><?= e(t('Réinitialiser le mot de passe')) ?></button></form>
      <?php if (!empty($u['totp']['on'])): ?>
      <form method="post" action="<?= e(admin_url('users/' . $u['id'])) ?>" data-confirm="<?= e(t('Désactiver la double authentification de ce compte (téléphone perdu) ?')) ?>">
        <?= Security::csrfField() ?><input type="hidden" name="action" value="disable2fa"><button class="btn" type="submit"><?= Icons::svg('lock') ?><?= e(t('Désactiver la 2FA')) ?></button></form>
      <?php endif ?>
      <?php if (!$self): ?>
      <form method="post" action="<?= e(admin_url('users/' . $u['id'])) ?>" data-confirm="<?= e(t('Supprimer ce compte ? Ses contenus resteront en ligne.')) ?>">
        <?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger" type="submit"><?= Icons::svg('trash') ?><?= e(t('Supprimer le compte')) ?></button></form>
      <?php endif ?>
    </div>
  </section>
</div>
