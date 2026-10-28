<?php
defined('ECLAIR') || exit;

use Eclair\Admin;
use Eclair\I18n;
use Eclair\Icons;
use Eclair\Security;

$me = $v['me'];
$on = !empty($me['totp']['on']);
?>
<div class="page-narrow">
  <div class="topbar"><div><h1><?= e(t('Mon profil')) ?></h1><p><?= e(t('Ces informations apparaissent sous tes articles : elles renforcent ta crédibilité (E-E-A-T) auprès de Google et des IA.')) ?></p></div></div>
  <form class="card" method="post" action="<?= e(admin_url('profile')) ?>" data-dirty>
    <?= Security::csrfField() ?>
    <div class="grid-2">
      <label class="field"><span class="label"><?= e(t('Nom affiché')) ?></span><input type="text" name="name" value="<?= e($me['name']) ?>" required></label>
      <label class="field"><span class="label"><?= e(t('E-mail de connexion')) ?></span><input type="email" name="email" value="<?= e($me['email']) ?>" required></label>
    </div>
    <label class="field"><span class="label"><?= e(t('Fonction / expertise')) ?></span><input type="text" name="job" value="<?= e($me['job'] ?? '') ?>" placeholder="<?= e(t('ex. : Formateur IA certifié, 12 ans d’expérience')) ?>"></label>
    <label class="field"><span class="label"><?= e(t('Biographie')) ?></span><textarea name="bio" rows="4" placeholder="<?= e(t('Ton parcours, tes diplômes, tes résultats : ce qui prouve que tu sais de quoi tu parles.')) ?>"><?= e($me['bio'] ?? '') ?></textarea></label>
    <?= Admin::partial('partials/media-field', ['name' => 'avatar', 'value' => (string) ($me['avatar'] ?? ''), 'label' => t('Photo'), 'hint' => t('Une vraie photo de toi inspire confiance.'), 'media' => $v['avatar'] ? [$v['avatar']['id'] => $v['avatar']] : []]) ?>
    <label class="field"><span class="label"><?= e(t('Tes profils en ligne (un lien par ligne)')) ?></span><textarea name="links" rows="3" placeholder="https://www.linkedin.com/in/…"><?= e(implode("\n", (array) ($me['links'] ?? []))) ?></textarea></label>
    <label class="field"><span class="label"><?= e(t('Langue de l’administration')) ?></span><select name="lang">
      <?php foreach (I18n::LANGS as $code => $name): ?><option value="<?= e($code) ?>"<?= ($me['lang'] ?? 'fr') === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach ?></select></label>
    <button class="btn btn-primary" type="submit"><?= e(t('Enregistrer le profil')) ?></button>
  </form>

  <form class="card" id="mot-de-passe" method="post" action="<?= e(admin_url('profile')) ?>">
    <?= Security::csrfField() ?><input type="hidden" name="action" value="password">
    <h2><?= e(t('Mot de passe')) ?></h2>
    <label class="field"><span class="label"><?= e(t('Mot de passe actuel')) ?></span><input type="password" name="current" autocomplete="current-password" required></label>
    <label class="field"><span class="label"><?= e(t('Nouveau mot de passe')) ?></span>
      <span class="password"><input type="password" name="password" autocomplete="new-password" minlength="10" required data-strength><button type="button" data-reveal><?= e(t('Afficher')) ?></button></span>
      <span class="strength" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
      <span class="hint"><?= e(t('10 caractères minimum. Astuce : une phrase de 4 mots est solide et facile à retenir.')) ?></span></label>
    <button class="btn" type="submit"><?= e(t('Changer le mot de passe')) ?></button>
  </form>

  <section class="card" id="securite">
    <div class="card-head"><h2><?= e(t('Double authentification (2FA)')) ?></h2><?= $on ? '<span class="badge ok">' . e(t('Activée')) . '</span>' : '<span class="badge warn">' . e(t('Désactivée')) . '</span>' ?></div>
    <?php if ($v['recovery']): ?>
      <div class="notice warn"><span>🔑</span><div><p><b><?= e(t('Tes codes de secours (affichés une seule fois)')) ?></b></p><p><?= e(t('Chaque code fonctionne une fois si tu perds ton téléphone. Imprime-les ou range-les dans ton gestionnaire de mots de passe.')) ?></p></div></div>
      <div class="recovery"><?php foreach ($v['recovery'] as $code): ?><span><?= e($code) ?></span><?php endforeach ?></div>
      <p><button class="btn btn-sm" type="button" data-copy="<?= e(implode("\n", $v['recovery'])) ?>"><?= Icons::svg('copy') ?><?= e(t('Copier les codes')) ?></button></p>
    <?php endif ?>
    <?php if (!$on && $v['setup'] === ''): ?>
      <p><?= e(t('Même si ton mot de passe fuite, personne ne pourra se connecter sans ton téléphone. Compatible Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden…')) ?></p>
      <form method="post" action="<?= e(admin_url('profile')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="2fa-start">
        <button class="btn btn-primary" type="submit"><?= Icons::svg('shield') ?><?= e(t('Activer la double authentification')) ?></button></form>
    <?php elseif (!$on): ?>
      <ol>
        <li><?= e(t('Ouvre ton application d’authentification et scanne ce QR code :')) ?></li>
      </ol>
      <div class="qr"><?= $v['qr'] ?><div><p class="small muted"><?= e(t('Ou saisis cette clé manuellement :')) ?></p><p><code><?= e(trim(chunk_split($v['setup'], 4, ' '))) ?></code></p></div></div>
      <form method="post" action="<?= e(admin_url('profile')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="2fa-confirm">
        <label class="field"><span class="label"><?= e(t('Saisis le code à 6 chiffres affiché pour confirmer')) ?></span><input class="otp" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="000000"></label>
        <button class="btn btn-primary" type="submit"><?= e(t('Confirmer et activer')) ?></button></form>
    <?php else: ?>
      <p><?= e(t('Codes de secours restants : {n}', ['n' => count((array) ($me['totp']['recovery'] ?? []))])) ?></p>
      <form method="post" action="<?= e(admin_url('profile')) ?>" class="row">
        <?= Security::csrfField() ?>
        <label class="grow"><span class="sr-only"><?= e(t('Mot de passe actuel')) ?></span><input type="password" name="current" placeholder="<?= e(t('Mot de passe actuel')) ?>" autocomplete="current-password" required></label>
        <button class="btn" type="submit" name="action" value="recovery"><?= e(t('Nouveaux codes de secours')) ?></button>
        <button class="btn btn-danger" type="submit" name="action" value="2fa-disable"><?= e(t('Désactiver')) ?></button>
      </form>
    <?php endif ?>
    <hr>
    <form method="post" action="<?= e(admin_url('profile')) ?>"><?= Security::csrfField() ?><input type="hidden" name="action" value="revoke">
      <button class="btn btn-sm" type="submit"><?= Icons::svg('logout') ?><?= e(t('Déconnecter mes autres appareils')) ?></button></form>
  </section>
</div>
