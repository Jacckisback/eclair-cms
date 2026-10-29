<?php
defined('ECLAIR') || exit;

use Eclair\Icons;
use Eclair\Security;
?>
<div class="page-narrow">
  <div class="topbar"><div><h1><?= e(t('Messages')) ?></h1><p><?= e(t('Les messages du formulaire de contact. Ils sont aussi envoyés par e-mail (voir Réglages › Contact).')) ?></p></div></div>
  <?php if (!$v['messages']): ?>
    <div class="card empty-state"><span class="emoji">📭</span><p><?= e(t('Aucun message pour l’instant. Ajoute un bloc « Formulaire de contact » à une page pour en recevoir.')) ?></p></div>
  <?php endif ?>
  <?php foreach ($v['messages'] as $id => $m): ?>
    <article class="message<?= empty($m['read']) ? ' unread' : '' ?><?= !empty($m['suspect']) ? ' suspect' : '' ?>">
      <div class="row between">
        <div><b><?= e($m['name']) ?></b> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
          <?php if (!empty($m['suspect'])): ?><span class="badge warn"><?= e(t('Spam probable')) ?></span><?php endif ?>
          <div class="small muted"><?= e(fmt_date((int) $m['t'], \Eclair\I18n::lang(), true)) ?><?= $m['page'] !== '' ? ' · ' . e($m['page']) : '' ?></div></div>
        <div class="row">
          <a class="btn btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . \Eclair\Settings::get('name')) ?>"><?= Icons::svg('mail') ?><?= e(t('Répondre')) ?></a>
          <?php if (empty($m['read'])): ?>
          <form method="post" action="<?= e(admin_url('messages')) ?>"><?= Security::csrfField() ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="action" value="read">
            <button class="btn btn-sm btn-ghost" type="submit"><?= Icons::svg('check') ?><?= e(t('Lu')) ?></button></form>
          <?php endif ?>
          <form method="post" action="<?= e(admin_url('messages')) ?>" data-confirm="<?= e(t('Supprimer ce message ?')) ?>"><?= Security::csrfField() ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="action" value="delete">
            <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="submit" aria-label="<?= e(t('Supprimer')) ?>"><?= Icons::svg('trash') ?></button></form>
        </div>
      </div>
      <pre><?= e($m['message']) ?></pre>
    </article>
  <?php endforeach ?>
  <?php if ($v['pages'] > 1): ?>
    <nav class="row between" aria-label="<?= e(t('Pagination')) ?>">
      <?php if ($v['page'] > 1): ?><a class="btn btn-sm" href="<?= e(admin_url('messages?p=' . ($v['page'] - 1))) ?>">← <?= e(t('Plus récents')) ?></a><?php else: ?><span></span><?php endif ?>
      <span class="small muted"><?= e(t('Page {n} sur {total}', ['n' => $v['page'], 'total' => $v['pages']])) ?></span>
      <?php if ($v['page'] < $v['pages']): ?><a class="btn btn-sm" href="<?= e(admin_url('messages?p=' . ($v['page'] + 1))) ?>"><?= e(t('Plus anciens')) ?> →</a><?php else: ?><span></span><?php endif ?>
    </nav>
  <?php endif ?>
</div>
