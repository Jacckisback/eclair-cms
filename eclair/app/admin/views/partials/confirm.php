<?php
/**
 * Mot de passe redemandé pour une action sensible, sauf s'il a été saisi il y a moins de 15 minutes.
 * Variables facultatives : required (bool), hint (texte).
 */
defined('ECLAIR') || exit;

use Eclair\Auth;

if (Auth::fresh()) {
    return;
}
$required = $v['required'] ?? true;
?>
<label class="field confirm-password"><span class="label">🔒 <?= e(t('Confirme avec ton mot de passe')) ?></span>
  <input type="password" name="confirm_password" autocomplete="current-password"<?= $required ? ' required' : '' ?>>
  <span class="hint"><?= e($v['hint'] ?? t('Par sécurité, cette action le demande si tu t’es connecté il y a plus de 15 minutes.')) ?></span></label>
