// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/* ⚡ Éclair — mode Visuel : la page rendue par le vrai thème, modifiable sur place.
   Chargé seulement par l'aperçu d'édition de l'administration (POST /preview avec edit=1), jamais sur le site public.
   Il ne fait qu'envoyer des intentions à l'éditeur (postMessage { op, block, field, value }) : l'éditeur reste la seule
   source de vérité, valide et enregistre. Origine et fenêtre vérifiées des deux côtés ; rien de reçu n'est inséré en HTML. */
(() => {
  const P = window.parent;
  const O = location.origin;
  if (P === window) return;
  const D = document;
  const send = (m) => P.postMessage(m, O);
  // Champs simples : texte brut seulement (repli sur « true » + garde-fous si le navigateur ne connaît pas plaintext-only).
  const PT = (() => { const d = D.createElement('div'); try { d.contentEditable = 'plaintext-only'; } catch (e) { /* ancien navigateur */ } return d.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true'; })();
  // Outils du bloc en icônes dessinées, comme dans l'administration (tracés Lucide, licence ISC) : aucun caractère spécial
  // qui s'afficherait en petit carré selon la police. Groupes séparés par un filet ; la croix de suppression est à part, au
  // bout, rouge au survol. « | » : filet. Les tracés sont des constantes de ce fichier, jamais un message reçu.
  const OPS = [
    ['up', '<path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>'],
    ['down', '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>'],
    ['|'],
    ['above', '<path d="M12 3v6"/><path d="M9 6h6"/><rect x="4" y="13" width="16" height="8" rx="2"/>'],
    ['below', '<rect x="4" y="3" width="16" height="8" rx="2"/><path d="M12 15v6"/><path d="M9 18h6"/>'],
    ['dup', '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M4 16V5a1 1 0 0 1 1-1h11"/>'],
    ['ai', '<path d="M12 3l1.9 5.6 5.6 1.9-5.6 1.9L12 18l-1.9-5.6-5.6-1.9 5.6-1.9z"/><path d="M19 3v4"/><path d="M21 5h-4"/>'],
    ['|'],
    ['del', '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'],
  ];
  let L = {};
  let cur = null;
  let last = null;
  let range = null;
  let timer = 0;
  const bar = D.createElement('div');
  bar.className = 'ev-bar';
  bar.setAttribute('role', 'toolbar');
  bar.hidden = true;

  const blockOf = (el) => (el && el.closest ? el.closest('[data-eb]') : null);
  const num = (b) => (b ? +b.dataset.eb : -1);
  const fieldOf = (el) => (el && el.closest ? el.closest('[data-ef]') : null);
  const isMedia = (f) => /(^|\.)(media|image)$/.test(f.dataset.ef);
  const isRich = (f) => f.hasAttribute('data-rich');
  const multi = (f) => /^(summary|code)$/.test(f.dataset.ef);
  const texts = (root) => [...root.querySelectorAll('[data-ef]')].concat(root.matches('[data-ef]') ? [root] : []).filter((f) => !isMedia(f));
  const rect = (el) => { const r = el.getBoundingClientRect(); return [r.left, r.top, r.width, r.height]; };
  const value = (f) => (isRich(f) ? f.innerHTML.trim() : multi(f) ? f.innerText.replace(/\n$/, '') : f.innerText.replace(/\s+/g, ' ').trim());
  const editable = (f, on) => {
    if (!on) { f.removeAttribute('contenteditable'); return; }
    f.contentEditable = isRich(f) ? 'true' : PT;
    f.dataset.ph = L.write || '';
    f._v = value(f);
  };

  /* Envoi d'une modification de texte (pendant la frappe et en quittant le champ). */
  const commit = (f) => {
    clearTimeout(timer);
    if (!f || !f.isContentEditable) return;
    const v = value(f);
    if (v === f._v) return;
    f._v = v;
    send({ op: 'set', block: num(blockOf(f)), field: f.dataset.ef, value: v });
  };

  /* Sélection d'un bloc : contour, champs modifiables, barre d'outils. */
  const place = () => {
    if (!cur) return;
    const r = cur.getBoundingClientRect();
    bar.style.top = Math.max(0, r.top + scrollY - bar.offsetHeight - 8) + 'px';
    bar.style.left = Math.max(4, Math.min(r.left + scrollX, scrollX + innerWidth - bar.offsetWidth - 4)) + 'px';
  };
  const select = (b, quiet) => {
    if (b === cur) return;
    commit(last);
    if (cur) { cur.classList.remove('ev-sel'); texts(cur).forEach((f) => editable(f, false)); }
    cur = b;
    last = null;
    bar.hidden = !b;
    if (b) {
      b.classList.add('ev-sel');
      texts(b).forEach((f) => editable(f, true));
      b.querySelectorAll('details').forEach((d) => { d.open = true; });
      const ai = bar.querySelector('[data-op=ai]');
      if (ai) ai.hidden = !b.querySelector('[data-rich]') && !b.matches('[data-rich]');
      place();
    }
    if (!quiet) send({ op: 'select', block: num(b) });
  };
  const act = (op, btn) => {
    if (!cur) return;
    commit(last);
    const m = { op, block: num(cur), rect: rect(btn) };
    if (op === 'up' || op === 'down') Object.assign(m, { op: 'move', value: op === 'up' ? -1 : 1 });
    if (op === 'above' || op === 'below') Object.assign(m, { op: 'add', value: op === 'above' ? 0 : 1 });
    if (op === 'ai') m.field = (last && cur.contains(last) && isRich(last) ? last : texts(cur).find(isRich) || {}).dataset?.ef || '';
    send(m);
  };

  /* Lien (Ctrl/Cmd+K) : l'éditeur ouvre son outil de lien, puis renvoie l'adresse choisie. */
  const link = (f) => {
    const s = getSelection();
    if (!s.rangeCount || s.isCollapsed) return;
    range = s.getRangeAt(0).cloneRange();
    const n = s.anchorNode.nodeType === 1 ? s.anchorNode : s.anchorNode.parentElement;
    const a = n.closest('a');
    send({ op: 'link', block: num(blockOf(f)), field: f.dataset.ef, value: a ? a.getAttribute('href') : '', rect: rect(range) });
  };

  /* Liens, boutons, formulaires et scripts du thème inactifs : un clic sélectionne, il ne navigue jamais. */
  D.addEventListener('mousedown', (e) => { if (!bar.contains(e.target)) select(blockOf(e.target)); }, true);
  D.addEventListener('click', (e) => {
    if (bar.contains(e.target)) return;
    const f = fieldOf(e.target);
    if (f && isMedia(f)) send({ op: 'pick', block: num(blockOf(f)), field: f.dataset.ef });
    e.preventDefault();
    e.stopPropagation();
  }, true);
  ['submit', 'dragstart', 'drop'].forEach((type) => D.addEventListener(type, (e) => { e.preventDefault(); e.stopPropagation(); }, true));
  D.addEventListener('focusin', (e) => { const f = fieldOf(e.target); if (f && f.isContentEditable) last = f; });
  D.addEventListener('focusout', (e) => commit(fieldOf(e.target)));
  D.addEventListener('input', (e) => {
    const f = fieldOf(e.target);
    if (f) { clearTimeout(timer); timer = setTimeout(() => commit(f), 300); }
  });
  D.addEventListener('paste', (e) => {
    const f = fieldOf(e.target);
    if (!f || !f.isContentEditable) return;
    e.preventDefault();
    const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
    D.execCommand('insertText', false, multi(f) ? text : text.replace(/\s*\n\s*/g, ' '));
  });
  D.addEventListener('keydown', (e) => {
    const mod = e.ctrlKey || e.metaKey;
    const k = (e.key || '').toLowerCase();
    const f = fieldOf(e.target);
    if (mod && (k === 'z' || k === 'y' || k === 's')) { e.preventDefault(); commit(f); send({ op: k === 's' ? 'save' : k === 'y' || e.shiftKey ? 'redo' : 'undo' }); return; }
    // Hors d'un champ (après Échap), Suppr ou Retour arrière supprime le bloc sélectionné ; Ctrl/Cmd+Z le rétablit.
    if (!f || !f.isContentEditable) {
      if (k === 'escape') select(null);
      else if ((k === 'delete' || k === 'backspace') && cur && !mod) { e.preventDefault(); act('del', bar.querySelector('[data-op=del]') || cur); }
      return;
    }
    if (mod && (k === 'b' || k === 'i' || k === 'k')) {
      e.preventDefault();
      if (isRich(f)) { if (k === 'k') link(f); else D.execCommand(k === 'b' ? 'bold' : 'italic'); }
      return;
    }
    if (k === 'escape') { f.blur(); return; }
    if (k === 'enter' && !e.isComposing && !multi(f)) {
      e.preventDefault();
      if (isRich(f) && e.shiftKey) { D.execCommand('insertLineBreak'); return; }
      commit(f);
      if (isRich(f) && cur && (f === cur || f.parentElement === cur)) send({ op: 'enter', block: num(cur) }); else f.blur();
      return;
    }
    if (k === ' ' && f.tagName === 'SUMMARY') { e.preventDefault(); D.execCommand('insertText', false, ' '); }
  });
  addEventListener('resize', place);

  /* Messages de l'éditeur : réglages de départ (libellés, bloc sélectionné, défilement), adresse d'un lien. */
  addEventListener('message', (e) => {
    const m = e.data;
    if (e.origin !== O || e.source !== P || !m || typeof m !== 'object') return;
    if (m.op === 'init' && m.labels && typeof m.labels === 'object') {
      L = m.labels;
      bar.setAttribute('aria-label', String(L.bar || ''));
      bar.replaceChildren(...OPS.filter(([op]) => op !== 'ai' || L.ai).map(([op, d]) => {
        const b = D.createElement(op === '|' ? 'span' : 'button');
        if (op === '|') { b.className = 'ev-sep'; return b; }
        b.type = 'button';
        b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
        b.dataset.op = op;
        b.title = String(L[op] || op);
        b.setAttribute('aria-label', b.title);
        b.addEventListener('click', () => act(op, b));
        return b;
      }));
      D.body.append(bar);
      D.querySelectorAll('.eb-empty').forEach((el) => { el.textContent = '+ ' + String((L.types || {})[el.dataset.et] || ''); });
      D.querySelectorAll('[data-ef]').forEach((f) => { if (!blockOf(f) && !isMedia(f)) editable(f, true); });
      scrollTo(0, +m.scroll || 0);
      const b = D.querySelector('[data-eb="' + (m.block | 0) + '"]');
      if (b && m.block >= 0) {
        select(b, true);
        const f = m.focus && texts(b)[0];
        if (f) { f.focus(); getSelection().selectAllChildren(f); getSelection().collapseToEnd(); }
      }
      send({ op: 'ready' });
    }
    if (m.op === 'link' && range) {
      const f = fieldOf(range.commonAncestorContainer.nodeType === 1 ? range.commonAncestorContainer : range.commonAncestorContainer.parentElement);
      const href = typeof m.value === 'string' && /^(https?:|mailto:|tel:|\/|#|$)/i.test(m.value) ? m.value : null;
      if (f && href !== null) {
        f.focus();
        getSelection().removeAllRanges();
        getSelection().addRange(range);
        D.execCommand(href ? 'createLink' : 'unlink', false, href);
        commit(f);
      }
      range = null;
    }
  });
  send({ op: 'hello' });
})();
