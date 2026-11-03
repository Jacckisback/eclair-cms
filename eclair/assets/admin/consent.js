// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/*
 * Éclair — autorisation d'une application (Claude, ChatGPT…) : « Autoriser » ne répond qu'à un geste délibéré.
 * Parade au « double clickjacking » : une page amenée au premier plan sous un double-clic (fenêtre échangée au premier
 * clic) ne doit jamais recevoir le second. Désactivé dans le HTML, le bouton s'active quand la page est visible et a le
 * focus depuis un court délai, puis qu'un geste (souris, doigt, clavier) a lieu dans la page ; il se désactive dès
 * qu'elle passe en arrière-plan. Le clic dont l'appui a commencé avant l'activation ne compte pas.
 */
(() => {
  const allow = document.querySelector('[data-gate]');
  if (!allow || !allow.form) return;
  const DELAY = 800; // ms de page visible et active avant qu'un geste compte : un double-clic dure bien moins
  const now = () => performance.now();
  let since = null; // début de la période où la page est visible et active (null : elle ne l'est pas)
  let opened = null; // activation du bouton (null : désactivé)
  let opener = null; // geste qui a activé le bouton : il n'autorise rien lui-même
  let down = null; // dernier appui (souris, doigt)
  const lock = () => { since = null; opened = null; opener = null; allow.disabled = true; };
  const watch = () => {
    if (document.visibilityState !== 'visible' || !document.hasFocus()) lock();
    else if (since === null) since = now();
  };
  const gesture = (e) => {
    if (e.type === 'pointerdown') down = now();
    watch();
    if (since !== null && opened === null && now() - since >= DELAY) {
      opened = now();
      opener = e;
      allow.disabled = false;
    }
  };
  document.addEventListener('visibilitychange', watch);
  window.addEventListener('focus', watch);
  window.addEventListener('blur', lock);
  window.addEventListener('pagehide', lock);
  window.addEventListener('pageshow', () => { lock(); watch(); });
  ['pointermove', 'pointerdown', 'mousemove', 'touchstart', 'wheel', 'keydown', 'focusin'].forEach((type) => window.addEventListener(type, gesture, { capture: true, passive: true }));
  // Clic de souris ou de doigt (detail > 0) dont l'appui précède l'activation : ignoré. Le clavier (detail 0) reste permis.
  allow.addEventListener('click', (e) => {
    if (e.detail > 0 && (down === null || opened === null || down <= opened)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  });
  // Entrée dans le champ du mot de passe : autorise si le bouton était déjà actif, jamais « Refuser » par défaut.
  allow.form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || e.isComposing || !e.target.matches('input')) return;
    e.preventDefault();
    if (!allow.disabled && opener !== e) allow.click();
  });
  lock();
  watch();
})();
