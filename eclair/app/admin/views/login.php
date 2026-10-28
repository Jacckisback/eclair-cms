<?php
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;
use Eclair\Settings;
?>
<form class="auth-card" method="post" action="<?= e(admin_url('login')) ?>" novalidate>
  <a class="logo" href="<?= e(path_to('/')) ?>"><span class="logo-mark"><?= Icons::bolt() ?></span><span><b>Éclair</b><small><?= e(Settings::get('name')) ?></small></span></a>
  <h1><?= e(t('Bon retour 👋')) ?></h1>
  <p class="sub"><?= e(t('Connecte-toi pour gérer ton site.')) ?></p>
  <?php if ($v['error'] !== ''): ?><div class="notice bad" role="alert"><p><?= e($v['error']) ?></p></div><?php endif ?>
  <?= Security::csrfField() ?>
  <input type="hidden" name="next" value="<?= e($v['next']) ?>">
  <label class="field"><span class="label"><?= e(t('E-mail')) ?></span>
    <input type="email" name="email" value="<?= e($v['email']) ?>" autocomplete="username" required autofocus></label>
  <label class="field"><span class="label"><?= e(t('Mot de passe')) ?></span>
    <span class="password"><input type="password" name="password" autocomplete="current-password" required><button type="button" data-reveal><?= e(t('Afficher')) ?></button></span></label>
  <button class="btn btn-primary btn-lg btn-block" type="submit"><?= e(t('Se connecter')) ?></button>
  <p class="small muted center"><?= e(t('Mot de passe oublié ? Un administrateur peut le réinitialiser depuis « Utilisateurs ».')) ?></p>
</form>
