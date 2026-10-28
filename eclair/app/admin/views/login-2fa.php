<?php
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;
?>
<form class="auth-card" method="post" action="<?= e(admin_url('login/2fa')) ?>">
  <span class="logo"><span class="logo-mark"><?= Icons::bolt() ?></span></span>
  <h1><?= e(t('Code de vérification')) ?></h1>
  <p class="sub"><?= e(t('Saisis le code à 6 chiffres affiché par ton application d’authentification.')) ?></p>
  <?php if ($v['error'] !== ''): ?><div class="notice bad" role="alert"><p><?= e($v['error']) ?></p></div><?php endif ?>
  <?= Security::csrfField() ?>
  <label class="field"><span class="sr-only"><?= e(t('Code')) ?></span>
    <input class="otp" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" required autofocus placeholder="000000"></label>
  <button class="btn btn-primary btn-lg btn-block" type="submit"><?= e(t('Vérifier')) ?></button>
  <p class="small muted center"><?= e(t('Téléphone perdu ? Saisis l’un de tes codes de secours (format abcde-12345).')) ?></p>
</form>
