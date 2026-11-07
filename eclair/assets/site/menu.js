// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/* ⚡ Éclair — menu mobile plein écran (Réglages › Apparence), chargé en différé seulement s'il est activé. */
(() => {
  const body = document.body;
  // Menu mobile plein écran (Réglages › Apparence) : ouverture, fermeture (bouton, Échap, retour arrière), focus gardé dedans.
  const toggle = document.querySelector('.nav-toggle');
  const sheet = document.getElementById('mnav');
  if (toggle && sheet) {
    const closer = sheet.querySelector('.mnav-close');
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');
    let back = null;
    let timer = 0;
    const open = () => {
      clearTimeout(timer);
      back = document.activeElement;
      sheet.hidden = false;
      sheet.classList.remove('is-closing');
      sheet.offsetWidth; // l'état fermé est appliqué avant la transition
      sheet.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      body.classList.add('mnav-open');
      closer.focus({ preventScroll: true });
    };
    const close = (now) => {
      if (sheet.hidden) return;
      sheet.classList.remove('is-open');
      sheet.classList.add('is-closing');
      toggle.setAttribute('aria-expanded', 'false');
      body.classList.remove('mnav-open');
      const done = () => { sheet.hidden = true; sheet.classList.remove('is-closing'); };
      if (now === true || still.matches) done(); else timer = setTimeout(done, 170);
      if (back && back.focus) back.focus({ preventScroll: true });
    };
    toggle.addEventListener('click', open);
    closer.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
      if (sheet.hidden) return;
      if (event.key === 'Escape') close();
      if (event.key !== 'Tab') return;
      const items = [...sheet.querySelectorAll('a[href], button')];
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    window.matchMedia('(min-width: 52.01rem)').addEventListener('change', (event) => { if (event.matches) close(true); });
    window.addEventListener('pageshow', (event) => { if (event.persisted) close(true); });
  }
})();
