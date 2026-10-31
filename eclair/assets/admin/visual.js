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
  const OPS = [['up', '↑'], ['down', '↓'], ['dup', '⧉'], ['del', '✕'], ['above', '+↑'], ['below', '+↓'], ['ai', '✨']];
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
    if (mod && !e.shiftKey && (k === 'z' || k === 's')) { e.preventDefault(); commit(f); send({ op: k === 'z' ? 'undo' : 'save' }); return; }
    if (!f || !f.isContentEditable) { if (k === 'escape') select(null); return; }
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
      bar.replaceChildren(...OPS.filter(([op]) => op !== 'ai' || L.ai).map(([op, text]) => {
        const b = D.createElement('button');
        b.type = 'button';
        b.textContent = text;
        b.dataset.op = op;
        b.title = String(L[op] || op);
        b.setAttribute('aria-label', b.title);
        b.addEventListener('click', () => act(op, b));
        return b;
      }));
      D.body.append(bar);
      D.querySelectorAll('.eb-empty').forEach((el) => { el.textContent = '＋ ' + String((L.types || {})[el.dataset.et] || ''); });
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
