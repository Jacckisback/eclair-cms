/* ⚡ Éclair — interface d'administration (sans dépendance, sans script en ligne). */
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
    const drop = h('label', { class: 'dropzone' }, h('span', { class: 'emoji', text: '🖼️' }), h('b', { text: t('Glisse tes images ici ou clique pour choisir') }), input);
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

  window.Eclair = { D, t, api, toast, upload, pickMedia, h, $, $$, dropEvents };

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
  // Confirmation avant une action irréversible (sur le formulaire, ou sur le bouton qui l'envoie).
  $$('form').forEach((form) => form.addEventListener('submit', (e) => {
    const text = (e.submitter && e.submitter.dataset.confirm) || form.dataset.confirm;
    if (text && !confirm(text)) e.preventDefault();
  }));
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
      const gen = h('button', { class: 'ai-mini', type: 'button', text: '✨ ' + t('Décrire avec l’IA') });
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

  /* ── Réglages › Apparence : thèmes et aperçu en direct ── */
  const look = $('#theme-preview');
  if (look) {
    const form = look.closest('form');
    const frame = $('iframe', look);
    const field = (name) => form.elements[name];
    // CSS personnalisé : l'aperçu est rendu sans celui enregistré et reçoit celui du formulaire, à chaque frappe.
    // Feuille construite (adoptedStyleSheets) : la CSP du site, qui n'autorise que son propre <style>, ne la bloque pas.
    const custom = field('custom_css');
    const live = !!custom && 'adoptedStyleSheets' in Document.prototype && 'replaceSync' in CSSStyleSheet.prototype;
    const restyle = () => {
      const doc = frame.contentDocument;
      if (!doc) return;
      const sheet = new frame.contentWindow.CSSStyleSheet();
      // Trop lourd, il est ignoré par le site : l'aperçu fait de même.
      sheet.replaceSync(custom.value.length > custom.maxLength ? '' : custom.value);
      doc.adoptedStyleSheets = [sheet];
    };
    let timer = 0;
    const show = () => {
      const q = new URLSearchParams({
        theme: (form.querySelector('[name=theme]:checked') || {}).value || 'eclair',
        accent: field('accent').value, font_head: field('font_head').value, font_body: field('font_body').value,
        header: field('header').value, cards: field('cards').value, dark: field('dark').value, path: $('#preview-page').value,
      });
      if (live) q.set('custom', '0');
      frame.src = look.dataset.src + '?' + q;
    };
    if (live) {
      let typing = 0;
      frame.addEventListener('load', restyle);
      custom.addEventListener('input', () => { clearTimeout(typing); typing = setTimeout(restyle, 150); });
    }
    const refresh = () => { clearTimeout(timer); timer = setTimeout(show, 250); };
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
    $('#preview-page').addEventListener('change', show);
    $$('[data-device]', look).forEach((b) => b.addEventListener('click', () => {
      look.classList.toggle('mobile', b.dataset.device === 'mobile');
      $$('[data-device]', look).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    }));
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
      out.textContent = res.ok ? '✅ ' + res.message : '⚠️ ' + (res.error || t('Erreur réseau : vérifie ta connexion.'));
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
      if (!res.ok) { status.textContent = '⚠️ ' + (res.error || t('Erreur réseau : vérifie ta connexion.')); return; }
      fields.forEach((x) => { const k = x.name.slice(8); if (res.profile[k] != null) x.value = res.profile[k]; });
      aiSettings.closest('form').dispatchEvent(new Event('input', { bubbles: true }));
      status.textContent = '✅ ' + t('Profil rédigé : relis-le, ajuste-le, puis enregistre.');
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
      .then((ok) => { rewrite.textContent = (ok ? '✅ ' : '⚠️ ') + (ok ? t('Réécriture d’URL active') : t('Réécriture d’URL inactive')); });
  }
})();
