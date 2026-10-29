<?php
defined('ECLAIR') || exit;

use Eclair\Admin;
use Eclair\Icons;
use Eclair\Security;
?>
<div class="page">
  <div class="topbar"><div><h1><?= e(t('Utilisateurs')) ?></h1><p><?= e(t('Les administrateurs gèrent tout ; les rédacteurs écrivent et publient des contenus.')) ?></p></div></div>
  <div class="grid-2">
    <div class="table-wrap">
      <table class="list">
        <thead><tr><th><?= e(t('Nom')) ?></th><th><?= e(t('Rôle')) ?></th><th>2FA</th><th><?= e(t('Dernière connexion')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($v['users'] as $u): ?>
          <tr><td class="title-cell"><a href="<?= e(admin_url('users/' . $u['id'])) ?>"><?= e($u['name']) ?></a><small><?= e($u['email']) ?></small></td>
            <td><span class="badge<?= $u['role'] === 'admin' ? ' brand' : '' ?>"><?= e($u['role'] === 'admin' ? t('Administrateur') : t('Rédacteur')) ?></span></td>
            <td><?= !empty($u['totp']['on']) ? '<span class="badge ok">' . e(t('Activée')) . '</span>' : '<span class="badge warn">' . e(t('Non')) . '</span>' ?></td>
            <td class="small muted"><?= !empty($u['last_login']) ? e(ago((int) $u['last_login'])) : '—' ?></td></tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <section class="card">
      <h2><?= e(t('Inviter quelqu’un')) ?></h2>
      <form method="post" action="<?= e(admin_url('users')) ?>">
        <?= Security::csrfField() ?>
        <label class="field"><span class="label"><?= e(t('Nom')) ?></span><input type="text" name="name" required maxlength="80"></label>
        <label class="field"><span class="label"><?= e(t('E-mail')) ?></span><input type="email" name="email" required></label>
        <label class="field"><span class="label"><?= e(t('Rôle')) ?></span><select name="role"><option value="editor"><?= e(t('Rédacteur')) ?></option><option value="admin"><?= e(t('Administrateur')) ?></option></select></label>
        <?= Admin::partial('partials/confirm', []) ?>
        <button class="btn btn-primary" type="submit"><?= Icons::svg('plus') ?><?= e(t('Créer le compte')) ?></button>
        <p class="hint"><?= e(t('Un mot de passe provisoire sécurisé est généré : transmets-le de vive voix ou par un canal sûr.')) ?></p>
      </form>
    </section>
  </div>
</div>
