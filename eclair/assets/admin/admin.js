// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/* Éclair — interface d'administration (sans dépendance, sans script en ligne). */
(() => {
  const dataEl = document.getElementById('eclair-data');
  const D = dataEl ? JSON.parse(dataEl.textContent) : { i18n: {} };
  const t = (s) => (D.i18n && D.i18n[s]) || s;
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const h = (tag, attrs = {}, ...children) => {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (v !== false && v != null) el.setAttribute(k, v === true ? '' : v);
    }
    children.flat().forEach((c) => c != null && el.append(c));
    return el;
  };

  /* Icônes dessinées (les mêmes que celles des pages, voir Icons.php) */
  const ICO = {
    'zap': '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
    'arrow': '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
    'circle-check': '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
    'alert': '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    'image': '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
    'spark': '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.6 5.6 1.9-5.6 1.9L12 18l-1.9-5.6-5.6-1.9 5.6-1.9z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg>',
  };
  const icon = (name, cls = '') => { const el = h('span', cls ? { class: cls } : {}); el.innerHTML = ICO[name]; return el; };
  /* Ligne d'état (réussite ou problème) : icône + texte, couleur portée par data-state. */
  const state = (el, ok, text) => { el.dataset.state = ok ? 'ok' : 'bad'; el.replaceChildren(icon(ok ? 'circle-check' : 'alert'), document.createTextNode(text)); };

  /* ── Notifications ── */
  const toast = (message, type = '', action = null) => {
    const box = $('#toasts') || document.body.appendChild(h('div', { class: 'toasts', id: 'toasts' }));
    const el = h('div', { class: 'toast ' + type, role: type === 'bad' ? 'alert' : 'status' }, h('span', { text: message }));
    if (action) el.append(h('button', { type: 'button', text: action.label, onclick: () => { action.run(); el.remove(); } }));
    box.append(el);
    setTimeout(() => el.remove(), action ? 8000 : 4200);
  };

  /* ── Appels à l'API (JSON + jeton CSRF) ── */
  const api = async (path, body = null, opts = {}) => {
    const method = opts.method || (body ? 'POST' : 'GET');
    let response;
    try {
      response = await fetch(D.admin + '/' + path.replace(/^\//, ''), {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf },
        body: body ? JSON.stringify(body) : undefined,
      });
    } catch (e) {
      if (!opts.quiet) toast(t('Erreur réseau : vérifie ta connexion.'), 'bad');
      throw e;
    }
    const json = await response.json().catch(() => ({ ok: false }));
    if (opts.quiet) return json;
    if (response.status === 401) toast(t('Session expirée : reconnecte-toi dans un autre onglet, puis réessaie.'), 'bad');
    else if (!json.ok && json.error) toast(json.error, 'bad');
    return json;
  };

  /* Envoi de fichier avec progression. */
  const upload = (file, onProgress) => new Promise((resolve) => {
    const form = new FormData();
    form.append('file', file);
    form.append('_csrf', D.csrf);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', D.admin + '/api/media/upload');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-Token', D.csrf);
    xhr.upload.onprogress = (e) => e.lengthComputable && onProgress && onProgress(e.loaded / e.total);
    xhr.onload = () => {
      let json = { ok: false, error: t('Échec de l’envoi') };
      try { json = JSON.parse(xhr.responseText); } catch (e) { /* réponse non JSON */ }
      if (!json.ok) toast(file.name + ' : ' + (json.error || t('Échec de l’envoi')), 'bad');
      resolve(json);
    };
    xhr.onerror = () => { toast(t('Erreur réseau : vérifie ta connexion.'), 'bad'); resolve({ ok: false }); };
    xhr.send(form);
  });

  /* ── Médiathèque en fenêtre (sélecteur d'image) ── */
  let libraryCache = null;
  const pickMedia = () => new Promise((resolve) => {
    const grid = h('div', { class: 'media-grid' });
    const search = h('input', { type: 'search', placeholder: t('Rechercher une image…'), 'aria-label': t('Rechercher une image…') });
    const input = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, hidden: true });
    const drop = h('label', { class: 'dropzone' }, icon('image', 'ico'), h('b', { text: t('Glisse tes images ici ou clique pour choisir') }), input);
    const dialog = h('dialog', {},
      h('div', { class: 'dialog-head' }, h('h2', { text: t('Médiathèque') }), h('button', { class: 'btn btn-ghost', type: 'button', text: t('Fermer'), onclick: () => dialog.close() })),
      h('div', { class: 'dialog-body' }, drop, h('p', {}, search), grid));
    let chosen = null;
    const render = () => {
      const q = search.value.trim().toLowerCase();
      grid.textContent = '';
      const list = (libraryCache || []).filter((m) => !q || (m.name + ' ' + m.alt).toLowerCase().includes(q));
      if (!list.length) grid.append(h('p', { class: 'muted', text: t('Aucune image pour l’instant.') }));
      list.forEach((m) => grid.append(h('button', {
        class: 'media-item' + (m.alt ? '' : ' no-alt'), type: 'button', title: m.alt || m.name,
        onclick: () => { chosen = m; dialog.close(); },
      }, h('img', { src: m.thumb, alt: m.alt, loading: 'lazy' }), h('span', { text: m.name }))));
    };
    const send = async (files) => {
      for (const file of files) {
        const tile = h('div', { class: 'media-item uploading' }, h('span', { text: t('Envoi en cours…') + ' ' + file.name }));
        grid.prepend(tile);
        const res = await upload(file, (p) => { tile.firstChild.textContent = Math.round(p * 100) + ' % · ' + file.name; });
        tile.remove();
        if (res.ok) { libraryCache = [res.media, ...(libraryCache || [])]; render(); }
      }
    };
    input.addEventListener('change', () => send([...input.files]));
    dropEvents(drop, send);
    search.addEventListener('input', render);
    dialog.addEventListener('close', () => { dialog.remove(); resolve(chosen); });
    document.body.append(dialog);
    dialog.showModal();
    if (libraryCache) render();
    api('api/media').then((res) => { if (res.ok) { libraryCache = res.media; render(); } });
  });

  function dropEvents(zone, onFiles) {
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.remove('over'); }));
    zone.addEventListener('drop', (e) => { const files = [...(e.dataTransfer?.files || [])].filter((f) => f.type.startsWith('image/')); if (files.length) onFiles(files); });
  }

  /* ── Brouillons de secours de l'éditeur (navigateur) : propres au site et au compte connecté, effacés à la déconnexion ── */
  // Clé « eclair-draft-<site>|<compte>-<contenu> » : <site> est le dossier du site (deux sites Éclair à la même adresse,
  // l'un en sous-dossier, ne touchent jamais aux brouillons l'un de l'autre) ; D.me est le compte connecté (vide sans
  // session). La copie d'un autre compte n'est jamais proposée : elle est effacée dès qu'une page de l'administration
  // s'ouvre. Celles d'avant la 1.10.3 (sans site ni compte) d'un contenu existant sont reprises par le seul compte d'un site
  // qui n'en a qu'un (D.solo) ; les autres sont effacées. Seules ces clés sont touchées : la préférence « eclair-mode » et le
  // site public restent.
  const DRAFT = 'eclair-draft-';
  const SITE = DRAFT + encodeURIComponent(D.base || '/') + '|';
  const legacy = (k) => k.startsWith(DRAFT) && !k.includes('|');
  const drafts = {
    key: (item) => (D.me ? SITE + D.me + '-' + (item.id || 'new-' + item.type) : ''),
    get: (key) => {
      try {
        const b = key ? JSON.parse(localStorage.getItem(key) || 'null') : null;
        return b && b.u === D.me && typeof b.t === 'number' && b.data && typeof b.data === 'object' ? b : null;
      } catch (e) { return null; }
    },
    set: (key, data) => { try { if (key) localStorage.setItem(key, JSON.stringify({ t: Date.now(), u: D.me, data })); } catch (e) { /* stockage indisponible ou plein */ } },
    drop: (...keys) => { try { keys.forEach((k) => k && localStorage.removeItem(k)); } catch (e) { /* stockage indisponible */ } },
    // Efface les brouillons de ce site (et ceux d'avant la 1.10.3), sauf ceux du compte « keep » s'il est donné.
    clear: (keep = '') => {
      try {
        const all = [];
        for (let i = 0; i < localStorage.length; i++) all.push(localStorage.key(i));
        all.filter((k) => k && (legacy(k) || (k.startsWith(SITE) && !(keep && k.startsWith(SITE + keep + '-'))))).forEach((k) => localStorage.removeItem(k));
      } catch (e) { /* stockage indisponible */ }
    },
    // Site à un seul compte : les copies d'avant la 1.10.3 d'un contenu existant (« eclair-draft-<id> ») lui reviennent.
    // Celles d'un nouveau contenu (« eclair-draft-new-<type> ») sont effacées : sans site ni compte, elles peuvent venir d'un
    // autre site Éclair à la même adresse ou d'un compte supprimé, et ne sont jamais attribuées à ce compte.
    adopt: () => {
      try {
        const all = [];
        for (let i = 0; i < localStorage.length; i++) all.push(localStorage.key(i));
        all.filter((k) => k && legacy(k)).forEach((k) => {
          const b = JSON.parse(localStorage.getItem(k) || 'null');
          const id = k.slice(DRAFT.length);
          const target = SITE + D.me + '-' + id;
          if (!id.startsWith('new-') && b && typeof b.t === 'number' && b.data && typeof b.data === 'object' && !localStorage.getItem(target)) {
            localStorage.setItem(target, JSON.stringify({ t: b.t, u: D.me, data: b.data }));
          }
          localStorage.removeItem(k);
        });
      } catch (e) { /* stockage indisponible */ }
    },
  };
  // Compte connecté : seuls ses brouillons restent. Page de connexion qui suit une déconnexion : plus aucun pour ce site
  // (aussi quand le formulaire de déconnexion est parti sans script).
  if (D.me) {
    if (D.solo) drafts.adopt();
    drafts.clear(D.me);
  } else if (/[?&]bye=1(&|$)/.test(location.search)) drafts.clear();

  window.Eclair = { D, t, api, toast, upload, pickMedia, h, $, $$, dropEvents, drafts };

  /* ── Comportements généraux ── */
  document.addEventListener('click', (e) => {
    const nav = e.target.closest('[data-toggle-nav]');
    if (nav) $('#shell')?.classList.toggle('nav-open');
    const reveal = e.target.closest('[data-reveal]');
    if (reveal) {
      const input = reveal.previousElementSibling;
      input.type = input.type === 'password' ? 'text' : 'password';
      reveal.textContent = input.type === 'password' ? t('Afficher') : t('Masquer');
    }
    const copy = e.target.closest('[data-copy]');
    if (copy) navigator.clipboard.writeText(copy.dataset.copy).then(() => toast(t('Copié !'), 'ok'));
    const color = e.target.closest('[data-color]');
    if (color && $('#accent')) { $('#accent').value = color.dataset.color; $('#accent').dispatchEvent(new Event('input', { bubbles: true })); }
    if (e.target.closest('dialog [data-close]')) e.target.closest('dialog').close();
  });
  // Déconnexion : les brouillons de secours partent avec la session.
  $$('form[data-logout]').forEach((form) => form.addEventListener('submit', () => drafts.clear()));
  // Confirmation avant une action irréversible (sur le formulaire, ou sur le bouton qui l'envoie).
  $$('form').forEach((form) => form.addEventListener('submit', (e) => {
    const text = (e.submitter && e.submitter.dataset.confirm) || form.dataset.confirm;
    if (text && !confirm(text)) { e.preventDefault(); return; }
    // Opération longue (mise à jour) : bouton occupé, pas de second envoi.
    const busy = e.submitter && e.submitter.dataset.busy;
    if (busy) setTimeout(() => { e.submitter.disabled = true; e.submitter.textContent = busy; });
  }));

  /* ── Sauvegardes Google Drive : autorisation par code, envoi qui avance tout seul ── */
  const drivePoll = $('[data-drive-poll]');
  if (drivePoll) {
    const every = Math.max(5, Number(drivePoll.dataset.drivePoll) || 5) * 1000;
    const ask = () => api('api/drive/poll', {}, { quiet: true })
      .then((json) => (json && json.state && json.state !== 'pending' ? location.reload() : setTimeout(ask, every)))
      .catch(() => setTimeout(ask, every * 2));
    setTimeout(ask, every);
  }
  const driveRun = $('[data-drive-run]');
  const driveTick = () => api('api/drive/tick', {}, { quiet: true });
  if (driveRun) {
    const bar = $('progress', driveRun);
    const label = $('[data-drive-label]', driveRun);
    const step = () => driveTick().then((json) => {
      if (!json || json.status === 'idle') return;
      if (json.status === 'done') { toast(json.text, 'ok'); setTimeout(() => location.reload(), 1500); return; }
      if (json.status === 'error') { toast(json.error, 'bad'); return; }
      if (bar) bar.value = json.percent;
      if (label && json.text) label.textContent = json.text;
      setTimeout(step, json.status === 'busy' ? 5000 : 300);
    }).catch(() => setTimeout(step, 10000));
    step();
  } else if (D.driveDue) {
    driveTick().catch(() => {});
  }

  /* ── Nouvelle version d'Éclair : vérification discrète, deux fois par jour au plus (administrateurs) ── */
  if (D.updateCheck) {
    api('api/update/check', {}, { quiet: true }).then((json) => {
      if (!json || !json.ok || !json.available) return;
      const link = $('.nav a[href$="/update"]');
      if (link && !$('.count', link)) link.append(h('span', { class: 'count', title: json.text, text: '1' }));
      const hello = $('.page > .hello');
      if (hello && !$('[data-update-banner]')) {
        const more = h('a', { href: D.admin + '/update', text: t('Voir la mise à jour') });
        more.insertAdjacentHTML('beforeend', ICO.arrow);
        hello.after(h('div', { class: 'notice', 'data-update-banner': true }, icon('zap'), h('p', {}, json.text + ' ', more)));
      }
    }).catch(() => {});
  }
  $$('[data-width]').forEach((el) => requestAnimationFrame(() => { el.style.width = el.dataset.width + '%'; }));
  $$('[data-strength]').forEach((input) => input.addEventListener('input', () => {
    const v = input.value;
    let score = (v.length >= 10) + (v.length >= 14) + (/[A-Z]/.test(v) && /[a-z]/.test(v)) + (/\d|[^\w]/.test(v));
    if (v.length >= 20) score = 4;
    if (new Set(v).size < 5) score = Math.min(score, 1);
    const meter = input.closest('.field').querySelector('.strength');
    if (meter) meter.dataset.level = v ? Math.max(1, score) : 0;
  }));
  $$('form[data-dirty]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });

  /* Champs « image » (logo, icône, photo…). */
  $$('[data-media-field]').forEach((field) => {
    const input = $('input[type=hidden]', field);
    const preview = $('[data-preview]', field);
    $('[data-pick]', field).addEventListener('click', async () => {
      const m = await pickMedia();
      if (!m) return;
      input.value = m.id;
      preview.replaceChildren(h('img', { src: m.thumb, alt: '' }), h('span', { class: 'small muted', text: m.name }));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    $('[data-clear]', field).addEventListener('click', () => { input.value = ''; preview.textContent = ''; input.dispatchEvent(new Event('input', { bubbles: true })); });
  });

  /* ── Liste des contenus : dupliquer / supprimer ── */
  document.addEventListener('click', async (e) => {
    const dup = e.target.closest('[data-duplicate]');
    if (dup) {
      const res = await api('api/duplicate', { id: dup.dataset.duplicate });
      if (res.ok) location.href = res.edit;
    }
    const del = e.target.closest('[data-delete]');
    if (del) {
      if (!confirm(t('Supprimer ce contenu ?') + '\n« ' + del.dataset.title + ' »')) return;
      let redirect = '';
      if (del.dataset.visible) redirect = prompt(t('Rediriger l’ancienne adresse vers (facultatif) :'), '/') || '';
      const res = await api('api/delete', { id: del.dataset.delete, redirect: redirect === '/' ? '' : redirect });
      if (res.ok) { del.closest('tr')?.remove(); toast(t('Contenu supprimé.'), 'ok'); }
    }
  });

  /* ── Page Médias ── */
  const mediaGrid = $('#media-grid');
  if (mediaGrid && D.media) {
    let media = D.media;
    let current = null;
    const detail = $('#media-detail');
    const count = $('#media-count');
    const render = () => {
      const q = ($('#media-search').value || '').trim().toLowerCase();
      mediaGrid.textContent = '';
      const list = media.filter((m) => !q || (m.name + ' ' + m.alt).toLowerCase().includes(q));
      count.textContent = media.length + ' image(s)';
      if (!list.length) mediaGrid.append(h('p', { class: 'muted', text: t('Aucune image pour l’instant.') }));
      list.forEach((m) => mediaGrid.append(h('button', { class: 'media-item' + (m.alt ? '' : ' no-alt'), type: 'button', title: m.alt || m.name, onclick: () => open(m) },
        h('img', { src: m.thumb, alt: m.alt, loading: 'lazy' }), h('span', { text: m.name }))));
    };
    const open = (m) => {
      current = m;
      $('#md-name').textContent = m.name;
      $('#md-img').src = m.url;
      $('#md-img').alt = m.alt;
      $('#md-alt').value = m.alt;
      $('#md-url').value = m.abs;
      $('#md-info').textContent = m.w + ' × ' + m.h + ' px · ' + m.size + ' · ' + (m.webp ? m.webp + ' version(s) WebP · ' : '') + m.date;
      detail.showModal();
    };
    $('#md-save').addEventListener('click', async () => {
      const res = await api('api/media/update', { id: current.id, alt: $('#md-alt').value });
      if (res.ok) { Object.assign(current, res.media); render(); toast(t('Texte alternatif enregistré.'), 'ok'); detail.close(); }
    });
    $('#md-copy').addEventListener('click', () => navigator.clipboard.writeText($('#md-url').value).then(() => toast(t('Lien copié !'), 'ok')));
    if (D.ai) {
      const gen = h('button', { class: 'ai-mini', type: 'button' }, icon('spark'), t('Décrire avec l’IA'));
      gen.addEventListener('click', async () => {
        if (gen.hasAttribute('aria-busy')) return;
        gen.setAttribute('aria-busy', 'true');
        const res = await api('api/ai/assist', { task: 'alt', media: current.id }).catch(() => ({}));
        gen.removeAttribute('aria-busy');
        if (res.ok) { Object.assign(current, res.media); $('#md-alt').value = res.media.alt; render(); toast(t('Texte alternatif enregistré.'), 'ok'); }
      });
      $('#md-alt').after(gen);
    }
    $('#md-delete').addEventListener('click', async () => {
      if (!confirm(t('Supprimer définitivement ?'))) return;
      let res = await api('api/media/delete', { id: current.id });
      if (!res.ok && res.used) {
        if (!confirm(t('Cette image est utilisée ici :') + '\n• ' + res.used.join('\n• ') + '\n\n' + t('Supprimer quand même ?'))) return;
        res = await api('api/media/delete', { id: current.id, force: true });
      }
      if (res.ok) { media = media.filter((m) => m.id !== current.id); render(); detail.close(); }
    });
    const send = async (files) => {
      for (const file of files) {
        const tile = h('div', { class: 'media-item uploading' }, h('span', { text: file.name }));
        mediaGrid.prepend(tile);
        const res = await upload(file, (p) => { tile.firstChild.textContent = Math.round(p * 100) + ' % · ' + file.name; });
        tile.remove();
        if (res.ok) { media = [res.media, ...media]; render(); toast(t('Envoi terminé') + ' : ' + file.name, 'ok'); }
      }
    };
    $('#media-input').addEventListener('change', (e) => send([...e.target.files]));
    $('#media-search').addEventListener('input', render);
    dropEvents($('#media-drop'), send);
    render();
  }

  /* ── Menus : glisser-déposer, ajout, sérialisation ── */
  const menusForm = $('#menus-form');
  if (menusForm) {
    let dragged = null;
    const bind = (li) => {
      li.addEventListener('dragstart', () => { dragged = li; li.classList.add('dragging'); });
      li.addEventListener('dragend', () => { li.classList.remove('dragging'); dragged = null; });
      li.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (!dragged || dragged === li || dragged.parentNode !== li.parentNode) return;
        const after = e.clientY > li.getBoundingClientRect().top + li.offsetHeight / 2;
        li.parentNode.insertBefore(dragged, after ? li.nextSibling : li);
      });
    };
    $$('.sortable li', menusForm).forEach(bind);
    menusForm.addEventListener('click', (e) => {
      const li = e.target.closest('li');
      if (e.target.closest('[data-up]') && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
      if (e.target.closest('[data-down]') && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
      if (e.target.closest('[data-remove]')) li.remove();
      const addLink = e.target.closest('[data-add-link]');
      if (addLink) {
        const label = prompt(t('Texte du bouton'));
        if (!label) return;
        const url = prompt(t('Adresse (URL)'), 'https://');
        if (url) add(addLink.closest('[data-menu]'), { type: 'link', id: '', url, title: url, kind: t('Lien'), label });
      }
    });
    const add = (section, item) => {
      const li = $('#menu-item').content.firstElementChild.cloneNode(true);
      li.dataset.type = item.type;
      li.dataset.id = item.id;
      li.dataset.url = item.url || '';
      $('b', li).textContent = item.title;
      $('.type', li).textContent = '· ' + item.kind;
      $('[data-label]', li).value = item.label || '';
      $('[data-list]', section).append(li);
      bind(li);
    };
    $$('[data-add-select]', menusForm).forEach((select) => select.addEventListener('change', () => {
      const opt = select.selectedOptions[0];
      if (!select.value) return;
      const [type, id] = select.value.split(':');
      add(select.closest('[data-menu]'), { type, id, title: opt.dataset.title, kind: opt.dataset.kind });
      select.value = '';
    }));
    menusForm.addEventListener('submit', () => {
      const out = {};
      $$('[data-menu]', menusForm).forEach((section) => {
        out[section.dataset.menu] = $$('[data-list] li', section).map((li) => ({
          type: li.dataset.type, id: li.dataset.id, url: li.dataset.url, label: $('[data-label]', li).value,
        }));
      });
      $('#menus-json').value = JSON.stringify(out);
    });
  }

  /* ── Réglages › Apparence : directions artistiques, thèmes classiques et aperçu en direct ── */
  const look = $('#theme-preview');
  if (look) {
    const form = look.closest('form');
    const frame = $('iframe', look);
    const field = (name) => form.elements[name];
    const box = $('#directions');
    const grid = $('#dir-grid');
    const fonts = JSON.parse(box.dataset.fonts || '{}');
    const hex = /^#[0-9a-f]{6}$/i;
    let timer = 0;
    const style = () => (form.querySelector('[name=style]:checked') || {}).value || 'classic';
    const picked = () => form.querySelector('[name=direction_id]:checked');
    // Direction en cours : la carte choisie, ajustée par le panneau « Ajuster ».
    const current = () => {
      const card = picked();
      const d = card ? JSON.parse(card.dataset.direction) : {};
      $$('[data-d]', box).forEach((el) => { d[el.dataset.d] = el.value; });
      delete d.mine;
      return d;
    };
    const encode = (d) => btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(d)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const fill = (d) => $$('[data-d]', box).forEach((el) => { if (d[el.dataset.d] !== undefined) el.value = d[el.dataset.d]; });
    // Vignette dessinée de la direction : couleurs, police (par famille), coins et composition de la bannière.
    const FAMILY = { serif: 'Georgia,serif', slab: 'Rockwell,Georgia,serif', sans: "'Helvetica Neue',Arial,sans-serif", condensed: "'Arial Narrow',sans-serif", rounded: "ui-rounded,'Arial Rounded MT',sans-serif" };
    const SYSTEM = { serif: 'serif', oldstyle: 'serif', rounded: 'rounded' };
    const tag = (name, a, inner) => '<' + name + Object.keys(a).map((k) => ' ' + k + '="' + a[k] + '"').join('') + (inner === undefined ? '/>' : '>' + inner + '</' + name + '>');
    const mock = (d) => {
      if (!['ground', 'surface', 'ink', 'muted', 'accent'].every((k) => hex.test(d[k] || ''))) return '';
      const [cat, weight] = fonts[d.display] || [SYSTEM[d.display] || 'sans', 700];
      const r = { none: 0, soft: 3, round: 7 }[d.radius] ?? 3;
      const on = hex.test(d.on_accent || '') ? d.on_accent : '#ffffff';
      const font = (x, y, size, fill, anchor) => tag('text', Object.assign({ x, y, 'font-size': size, fill, 'text-anchor': anchor || 'start', 'font-family': FAMILY[cat], 'font-weight': weight >= 600 ? 700 : 400 },
        cat === 'condensed' ? { 'font-stretch': 'condensed' } : {}, d.case === 'upper' ? { 'letter-spacing': 1 } : {}), d.case === 'upper' ? 'AG' : 'Ag');
      const bar = (x, y, w, fill, h) => tag('rect', { x, y, width: w, height: h || 3, rx: 1, fill });
      const pic = (x, y, w, h, fill) => tag('rect', { x, y, width: w, height: h, rx: r, fill: fill || d.surface }) + tag('path', { fill: d.muted, opacity: 0.35,
        d: 'M' + (x + w * 0.12) + ' ' + (y + h * 0.82) + 'l' + w * 0.26 + ' ' + -h * 0.34 + ' ' + w * 0.18 + ' ' + h * 0.2 + ' ' + w * 0.16 + ' ' + -h * 0.14 + ' ' + w * 0.16 + ' ' + h * 0.28 + 'z' });
      const btn = (x, y, w = 30, h = 9) => (d.buttons === 'underline'
        ? tag('rect', { x, y: y + h / 2 - 2.4, width: w * 0.7, height: 2.2, fill: d.ink }) + tag('rect', { x, y: y + h / 2 + 1.2, width: w * 0.7, height: 1.2, fill: d.accent })
        : tag('rect', d.buttons === 'outline' ? { x: x + 0.6, y: y + 0.6, width: w - 1.2, height: h - 1.2, rx: Math.min(r, h / 2), fill: 'none', stroke: d.accent, 'stroke-width': 1.2 }
          : { x, y, width: w, height: h, rx: Math.min(r, h / 2), fill: d.accent })
          + tag('rect', { x: x + w * 0.25, y: y + h / 2 - 0.8, width: w * 0.5, height: 1.6, fill: d.buttons === 'outline' ? d.accent : on }));
      let s = tag('rect', { width: 160, height: 100, fill: d.ground }) + bar(10, 8, 22, d.ink, 4) + bar(104, 9, 12, d.muted, 2.5) + bar(122, 9, 12, d.muted, 2.5) + bar(140, 9, 10, d.muted, 2.5);
      if (d.sections === 'bands' && ['split', 'editorial'].includes(d.hero)) s += tag('rect', { y: 88, width: 160, height: 12, fill: d.surface });
      if (d.hero === 'fullbleed') {
        s += pic(6, 20, 148, 74) + tag('rect', { x: 6, y: 58, width: 148, height: 36, fill: '#000', opacity: 0.5 }) + font(14, 79, 20, '#ffffff') + btn(14, 83, 24, 7);
      } else if (d.hero === 'poster') {
        s += font(10, 50, 34, d.ink) + bar(10, 57, 92, d.muted) + bar(10, 63, 64, d.muted) + btn(10, 70) + pic(10, 84, 140, 14);
      } else if (d.hero === 'editorial') {
        s += pic(98, 18, 52, 68) + font(10, 66, 24, d.ink) + bar(10, 72, 62, d.muted) + bar(10, 78, 42, d.muted);
      } else if (d.hero === 'reverse') {
        s += pic(10, 20, 62, 56) + font(82, 44, 24, d.ink) + bar(82, 51, 60, d.muted) + bar(82, 57, 44, d.muted) + btn(82, 64);
      } else if (d.hero === 'framed') {
        s += tag('rect', { x: 6, y: 18, width: 148, height: 66, rx: Math.max(r, 2), fill: d.surface }) + font(14, 44, 22, d.ink) + bar(14, 51, 54, d.muted)
          + bar(14, 57, 38, d.muted) + btn(14, 64) + pic(90, 25, 56, 52, d.ground);
      } else if (d.hero === 'centered') {
        s += font(80, 42, 24, d.ink, 'middle') + bar(50, 49, 60, d.muted) + bar(58, 55, 44, d.muted) + btn(65, 62) + pic(26, 78, 108, 22);
      } else {
        s += font(10, 44, 24, d.ink) + bar(10, 51, 60, d.muted) + bar(10, 57, 44, d.muted) + btn(10, 64) + pic(88, 20, 62, 56);
      }
      return tag('svg', { viewBox: '0 0 160 100', 'aria-hidden': 'true' }, s);
    };
    const draw = (input, d) => { input.parentNode.querySelector('.dir-mock').innerHTML = mock(d || JSON.parse(input.dataset.direction)); };
    $$('[name=direction_id]', grid).forEach((input) => draw(input));
    // Retouches en un clic : la direction choisie, transformée d'un coup (aperçu immédiat ; « Annuler la retouche » revient en arrière).
    const steer = $('#dir-steer');
    if (steer) {
      const undo = [];
      const back = $('[data-steer=undo]', steer);
      const rgb = (c) => [1, 3, 5].map((i) => parseInt(c.slice(i, i + 2), 16));
      const hsl = (c) => {
        const [r, g, b] = rgb(c).map((v) => v / 255);
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const l = (max + min) / 2;
        const k = max - min;
        if (!k) return [0, 0, l];
        const h = max === r ? ((g - b) / k + 6) % 6 : max === g ? (b - r) / k + 2 : (r - g) / k + 4;
        return [h / 6, k / (1 - Math.abs(2 * l - 1)), l];
      };
      const toHex = ([h, s, l]) => {
        const a = s * Math.min(l, 1 - l);
        const f = (n) => { const k = (n + h * 12) % 12; return Math.round(255 * (l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1)))).toString(16).padStart(2, '0'); };
        return '#' + f(0) + f(8) + f(4);
      };
      const clamp = (v) => Math.min(1, Math.max(0, v));
      const saturate = (c, n) => { const [h, s, l] = hsl(c); return toHex([h, clamp(s + n), l]); };
      const blend = (a, b, t) => '#' + rgb(a).map((v, i) => Math.round(v * t + rgb(b)[i] * (1 - t)).toString(16).padStart(2, '0')).join('');
      const step = (list, v, n) => list[Math.max(0, Math.min(list.length - 1, list.indexOf(v) + n))];
      // Polices de titre dans un ordre qui alterne les familles (empattements, grotesque, condensée…) : chaque clic change de ton.
      const groups = {};
      Object.keys(fonts).forEach((id) => { (groups[fonts[id][0]] = groups[fonts[id][0]] || []).push(id); });
      const cycle = [];
      for (let i = 0; Object.values(groups).some((g) => g[i]); i++) Object.values(groups).forEach((g) => { if (g[i]) cycle.push(g[i]); });
      const rules = {
        bolder: (d) => ({ scale: step(['quiet', 'regular', 'bold'], d.scale, 1), density: step(['compact', 'regular', 'airy'], d.density, 1),
          hero: { split: 'poster', reverse: 'poster', centered: 'poster', framed: 'poster', editorial: 'fullbleed', poster: 'fullbleed' }[d.hero] || d.hero,
          sections: 'bands', buttons: 'solid', accent: saturate(d.accent, 0.15) }),
        quieter: (d) => ({ scale: step(['quiet', 'regular', 'bold'], d.scale, -1), case: 'normal', surfaces: 'line', accent: saturate(d.accent, -0.25),
          hero: { poster: 'split', fullbleed: 'editorial', framed: 'split' }[d.hero] || d.hero, sections: d.sections === 'bands' ? 'rules' : d.sections }),
        colorful: (d) => ({ ground: blend(d.accent, d.ground, 0.08), surface: blend(d.accent, d.surface, 0.18), sections: 'bands', accent: saturate(d.accent, 0.1),
          surfaces: d.surfaces === 'line' ? 'flat' : d.surfaces }),
        distill: (d) => ({ surfaces: 'line', icons: 'none', sections: 'plain', density: 'airy', radius: d.radius === 'round' ? 'soft' : 'none',
          features: d.features === 'cards' ? 'columns' : d.features, hero: d.hero === 'framed' ? 'split' : d.hero }),
        invert: (d) => Object.fromEntries(['ground', 'surface', 'ink', 'muted'].map((k) => { const [h, s, l] = hsl(d[k]); return [k, toHex([h, s, 1 - l])]; })),
        typeset: (d) => ({ display: cycle[(cycle.indexOf(d.display) + 1) % cycle.length] }),
      };
      steer.addEventListener('click', (e) => {
        const b = e.target.closest('[data-steer]');
        if (!b || !picked()) return;
        const d = current();
        if (b.dataset.steer === 'undo') {
          if (undo.length) fill(undo.pop());
        } else {
          undo.push(d);
          fill(Object.assign({}, d, rules[b.dataset.steer](d)));
        }
        back.disabled = !undo.length;
        form.dispatchEvent(new Event('input'));
        show();
      });
      grid.addEventListener('change', () => { undo.length = 0; back.disabled = true; });
    }
    // « Imagine ma direction » : trois propositions de l'IA, ajoutées en tête des cartes (rien n'est enregistré avant « Enregistrer »).
    const imagine = $('#dir-imagine');
    if (imagine) {
      const state = $('#dir-state');
      $('#dir-brief').addEventListener('input', (e) => e.stopPropagation());
      imagine.addEventListener('click', async () => {
        if (imagine.disabled) return;
        imagine.disabled = true;
        const start = Date.now();
        const tick = () => { state.textContent = imagine.dataset.wait + ' ' + Math.round((Date.now() - start) / 1000) + ' s'; };
        tick();
        const timer = setInterval(tick, 1000);
        const res = await api('api/ai/directions', { brief: $('#dir-brief').value }).catch(() => null);
        clearInterval(timer);
        imagine.disabled = false;
        state.textContent = '';
        if (!res || !res.ok) return;
        $$('.dir-ai-card', grid).forEach((c) => c.remove());
        res.directions.slice().reverse().forEach((d) => {
          const input = h('input', { type: 'radio', name: 'direction_id', value: d.id, 'data-direction': JSON.stringify(d) });
          grid.prepend(h('label', { class: 'theme-card dir-card dir-ai-card' }, input, h('span', { class: 'dir-mock', 'aria-hidden': 'true' }),
            h('span', { class: 'theme-name', text: d.name }), h('span', { class: 'theme-desc', text: d.idea })));
          draw(input);
        });
        const first = $('[name=direction_id]', grid);
        first.checked = true;
        fill(JSON.parse(first.dataset.direction));
        form.dispatchEvent(new Event('input'));
        show();
        grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }
    const show = () => {
      const q = new URLSearchParams({ header: field('header').value, cards: field('cards').value, path: $('#preview-page').value });
      if (style() === 'direction' && picked()) {
        const d = current();
        field('direction').value = encode(d);
        q.set('direction', field('direction').value);
        draw(picked(), d);
      } else {
        q.set('theme', (form.querySelector('[name=theme]:checked') || {}).value || 'eclair');
        ['accent', 'font_head', 'font_body', 'dark'].forEach((name) => q.set(name, field(name).value));
      }
      frame.src = look.dataset.src + '?' + q;
    };
    const refresh = () => { clearTimeout(timer); timer = setTimeout(show, 250); };
    // Style : direction artistique ou thème classique (les réglages de l'autre restent enregistrés).
    const toggle = () => {
      const s = style();
      $$('[data-style]', form).forEach((el) => { el.hidden = el.dataset.style !== s; });
      if (s === 'direction' && !picked()) {
        const first = $('[name=direction_id]', grid);
        first.checked = true;
        fill(JSON.parse(first.dataset.direction));
      }
    };
    $$('[name=style]', form).forEach((radio) => radio.addEventListener('change', () => { toggle(); show(); }));
    // Choisir une direction remplit le panneau « Ajuster » avec ses valeurs.
    grid.addEventListener('change', (e) => {
      if (e.target.name !== 'direction_id') return;
      fill(JSON.parse(e.target.dataset.direction));
      refresh();
    });
    $$('[data-d]', box).forEach((el) => { el.addEventListener('input', refresh); el.addEventListener('change', refresh); });
    // Choisir un thème applique ses réglages conseillés (modifiables ensuite).
    $$('[name=theme]', form).forEach((radio) => radio.addEventListener('change', () => {
      const d = radio.dataset;
      field('accent').value = d.accent;
      field('font_head').value = d.fontHead;
      field('font_body').value = d.fontBody;
      field('header').value = d.header;
      field('cards').value = d.cards;
      refresh();
    }));
    ['accent', 'font_head', 'font_body', 'header', 'cards', 'dark'].forEach((name) => {
      field(name).addEventListener('input', refresh);
      field(name).addEventListener('change', refresh);
    });
    form.addEventListener('submit', () => { if (style() === 'direction' && picked()) field('direction').value = encode(current()); });
    $('#preview-page').addEventListener('change', show);
    $$('[data-device]', look).forEach((b) => b.addEventListener('click', () => {
      look.classList.toggle('mobile', b.dataset.device === 'mobile');
      $$('[data-device]', look).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    }));
    toggle();
    show();
  }

  /* ── Réglages › Assistant IA ── */
  const aiSettings = $('#ai-settings');
  if (aiSettings) {
    const sync = () => {
      const provider = ($('[data-ai-provider]:checked', aiSettings) || {}).value || '';
      const image = ($('[data-ai-image]:checked', aiSettings) || {}).value || '';
      $$('[data-ai-for]', aiSettings).forEach((box) => { box.hidden = !provider; });
      $$('[data-ai-image-for]', aiSettings).forEach((box) => { box.hidden = box.dataset.aiImageFor !== image; });
    };
    aiSettings.addEventListener('change', sync);
    $$('[data-ai-test]', aiSettings).forEach((btn) => btn.addEventListener('click', async () => {
      const box = btn.closest('[data-ai-for]');
      const out = $('[data-ai-result]', box);
      btn.disabled = true;
      out.textContent = t('Vérification…');
      const res = await api('api/ai/test', { provider: btn.dataset.aiTest, key: $('input[type=password]', box).value.trim(), model: $('select', box).value }, { quiet: true }).catch(() => ({}));
      btn.disabled = false;
      state(out, res.ok, res.ok ? res.message : (res.error || t('Erreur réseau : vérifie ta connexion.')));
    }));
    const profile = $('[data-ai-profile]', aiSettings);
    if (profile) profile.addEventListener('click', async () => {
      const status = $('[data-ai-profile-status]', aiSettings);
      const fields = $$('textarea[name^=profile_]', aiSettings);
      if (fields.some((x) => x.value.trim()) && !confirm(t('Remplacer le profil actuel par celui rédigé par l’IA ?'))) return;
      profile.disabled = true;
      status.textContent = t('L’IA lit ton site… (20 à 60 secondes)');
      const res = await api('api/ai/profile', {}, { quiet: true }).catch(() => ({}));
      profile.disabled = false;
      if (!res.ok) { state(status, false, res.error || t('Erreur réseau : vérifie ta connexion.')); return; }
      fields.forEach((x) => { const k = x.name.slice(8); if (res.profile[k] != null) x.value = res.profile[k]; });
      aiSettings.closest('form').dispatchEvent(new Event('input', { bubbles: true }));
      state(status, true, t('Profil rédigé : relis-le, ajuste-le, puis enregistre.'));
    });
  }

  /* ── Sécurité : les fichiers internes doivent être inaccessibles ── */
  const exposure = $('#exposure');
  if (exposure) {
    JSON.parse(exposure.dataset.files).forEach(async (file) => {
      const li = h('li', {}, h('span', { class: 'dot' }), h('span', { class: 'mono small grow', text: file }), h('span', { class: 'badge', text: t('Vérification…') }));
      exposure.append(li);
      let exposed = false;
      try {
        const res = await fetch(D.base + file, { cache: 'no-store', credentials: 'omit', redirect: 'manual' });
        const text = res.ok ? await res.text() : '';
        exposed = res.ok && /"(name|email|pass|secret|on)"|defined\(|namespace /.test(text);
      } catch (e) { exposed = false; }
      li.querySelector('.dot').className = 'dot ' + (exposed ? 'bad' : 'ok');
      li.querySelector('.badge').className = 'badge ' + (exposed ? 'bad' : 'ok');
      li.querySelector('.badge').textContent = exposed ? t('Exposé !') : t('Protégé');
    });
  }
  const rewrite = $('#rewrite-check');
  if (rewrite) {
    fetch(rewrite.dataset.url, { cache: 'no-store' }).then((r) => r.text()).then((x) => x.trim() === 'ok').catch(() => false)
      .then((ok) => state(rewrite, ok, ok ? t('Réécriture d’URL active') : t('Réécriture d’URL inactive')));
  }
})();
