/* ⚡ Éclair — éditeur de blocs (aucune dépendance). */
(() => {
  const E = window.Eclair;
  const root = document.getElementById('editor');
  if (!E || !root || !E.D.editor) return;
  const { D, t, api, toast, pickMedia, upload, h, $, $$, dropEvents } = E;
  const cfg = D.editor;
  const media = cfg.media || {};
  let item = cfg.item;
  const container = $('#blocks');
  const titleEl = $('#doc-title');
  const summaryEl = $('#doc-summary');

  /* ───────────── Icônes ───────────── */
  const svg = (d) => {
    const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.setAttribute('viewBox', '0 0 24 24');
    el.setAttribute('fill', 'none');
    el.setAttribute('stroke', 'currentColor');
    el.setAttribute('stroke-width', '2');
    el.setAttribute('stroke-linecap', 'round');
    el.setAttribute('stroke-linejoin', 'round');
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = d;
    return el;
  };
  const ICON = {
    plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
    grip: '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
    up: '<path d="m18 15-6-6-6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    copy: '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M4 16V5a1 1 0 0 1 1-1h11"/>',
    trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
  };
  const iconBtn = (name, label, onclick) => h('button', { type: 'button', title: label, 'aria-label': label, onclick }, svg(ICON[name]));

  /* ───────────── Types de blocs ───────────── */
  const TYPES = {
    paragraph: ['¶', t('Texte'), t('Paragraphe de texte'), () => ({ type: 'paragraph', text: '' })],
    heading: ['H', t('Intertitre'), t('Titre de section (H2, H3, H4)'), () => ({ type: 'heading', level: 2, text: '', anchor: '' })],
    list: ['•', t('Liste'), t('À puces ou numérotée'), () => ({ type: 'list', style: 'ul', items: [''] })],
    quote: ['❝', t('Citation'), t('Une phrase mise en avant'), () => ({ type: 'quote', text: '', cite: '' })],
    image: ['🖼', t('Image'), t('Depuis la médiathèque'), () => ({ type: 'image', media: '', alt: '', caption: '', link: '', size: 'normal' })],
    callout: ['💡', t('Encadré'), t('Astuce, info, attention'), () => ({ type: 'callout', variant: 'tip', title: '', text: '' })],
    faq: ['❓', t('FAQ'), t('Questions-réponses (FAQPage)'), () => ({ type: 'faq', title: '', items: [{ q: '', a: '' }] })],
    table: ['▦', t('Tableau'), t('Données en lignes et colonnes'), () => ({ type: 'table', head: true, caption: '', rows: [['', ''], ['', '']] })],
    button: ['➜', t('Bouton'), t('Appel à l’action'), () => ({ type: 'button', text: '', url: '', style: 'primary', align: 'left' })],
    video: ['▶', t('Vidéo'), t('YouTube ou Vimeo, sans cookies'), () => ({ type: 'video', url: '', caption: '' })],
    code: ['</>', t('Code'), t('Extrait de code source'), () => ({ type: 'code', code: '', lang: '' })],
    divider: ['—', t('Séparateur'), t('Ligne de séparation'), () => ({ type: 'divider' })],
    hero: ['★', t('Bannière'), t('Grand titre d’accueil avec boutons'), () => ({ type: 'hero', title: '', text: '', button: '', url: '', button2: '', url2: '', media: '' })],
    features: ['▤', t('Atouts'), t('Grille de services ou d’avantages'), () => ({ type: 'features', title: '', items: [{ icon: '🚀', title: '', text: '' }, { icon: '🤝', title: '', text: '' }, { icon: '✅', title: '', text: '' }] })],
    team: ['👥', t('Équipe'), t('Portraits, fonctions et présentations'), () => ({ type: 'team', title: '', items: [{ name: '', role: '', bio: '', media: '', link: '' }] })],
    posts: ['📰', t('Derniers articles'), t('Liste automatique des articles'), () => ({ type: 'posts', title: '', count: 3, category: '' })],
    products: ['🛍', t('Produits'), t('Vitrine automatique de la boutique'), () => ({ type: 'products', title: '', count: 4, category: '' })],
    contact: ['✉', t('Formulaire de contact'), t('Formulaire anti-spam intégré'), () => ({ type: 'contact', title: '', button: '' })],
  };
  const RICH = ['paragraph', 'heading', 'quote'];

  /* ───────────── Outils de saisie ───────────── */
  const rich = (html, placeholder, cls = '') => {
    const el = h('div', { contenteditable: 'true', class: 'rt ' + cls, 'data-placeholder': placeholder, spellcheck: 'true' });
    el.innerHTML = html || '';
    return el;
  };
  const input = (value, placeholder, attrs = {}) => h('input', { type: 'text', value: value || '', placeholder, 'aria-label': placeholder, ...attrs });
  const select = (value, options, label) => {
    const s = h('select', { 'aria-label': label || '' });
    Object.entries(options).forEach(([v, l]) => s.append(h('option', { value: v, text: l, selected: String(v) === String(value) })));
    return s;
  };
  const cleanHtml = (el) => el.innerHTML.replace(/<br>$/, '').replace(/&nbsp;/g, ' ').trim();
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ───────────── Création d'un bloc ───────────── */
  const makeBlock = (data) => {
    const type = TYPES[data.type] ? data.type : 'paragraph';
    const el = h('div', { class: 'block b-' + type, 'data-type': type });
    const body = h('div', { class: 'block-body' });
    const bar = h('div', { class: 'block-bar' });
    const tools = h('div', { class: 'block-tools' },
      iconBtn('plus', t('Ajouter un bloc'), (e) => openInserter(e.currentTarget, el, false)),
      Object.assign(iconBtn('grip', t('Déplacer'), null), { className: 'grip' }));
    el.append(h('span', { class: 'block-label', text: TYPES[type][1] }), tools, bar, body);
    const extra = h('span', { class: 'row' });
    bar.append(extra,
      iconBtn('up', t('Monter'), () => move(el, -1)),
      iconBtn('down', t('Descendre'), () => move(el, 1)),
      iconBtn('copy', t('Dupliquer'), () => { const copy = makeBlock(el._read()); el.after(copy); changed(); }),
      iconBtn('trash', t('Supprimer'), () => removeBlock(el)));
    RENDER[type](el, body, data, extra);
    bindDrag(el);
    return el;
  };

  const RENDER = {
    paragraph(el, body, d) {
      const ed = rich(d.text, t('Écris ou tape « / » pour choisir un bloc…'));
      body.append(ed);
      el._read = () => ({ type: 'paragraph', text: cleanHtml(ed) });
      el._focus = (end) => focusEditable(ed, end);
      el._editable = ed;
    },
    heading(el, body, d, extra) {
      const ed = rich(d.text, t('Intertitre'));
      el.dataset.level = d.level || 2;
      const level = select(d.level || 2, { 2: 'H2', 3: 'H3', 4: 'H4' }, t('Taille'));
      level.addEventListener('change', () => { el.dataset.level = level.value; changed(); });
      extra.append(level);
      body.append(ed);
      el._read = () => ({ type: 'heading', level: +level.value, text: cleanHtml(ed), anchor: d.anchor || '' });
      el._focus = (end) => focusEditable(ed, end);
      el._editable = ed;
    },
    list(el, body, d, extra) {
      let style = d.style === 'ol' ? 'ol' : 'ul';
      const build = (items) => {
        const list = h(style, { contenteditable: 'true' });
        (items.length ? items : ['']).forEach((i) => { const li = document.createElement('li'); li.innerHTML = i || '<br>'; list.append(li); });
        list.addEventListener('keydown', listKeys);
        return list;
      };
      let list = build(d.items || []);
      const toggle = select(style, { ul: '• ' + t('Liste'), ol: '1. ' + t('Liste') }, t('Liste'));
      toggle.addEventListener('change', () => { style = toggle.value; const fresh = build(read().items); list.replaceWith(fresh); list = fresh; changed(); });
      extra.append(toggle);
      body.append(list);
      const read = () => ({ type: 'list', style, items: $$('li', list).map((li) => cleanHtml(li)).filter((x) => x !== '') });
      el._read = read;
      el._focus = (end) => focusEditable(end ? list.lastElementChild || list : list.firstElementChild || list, end);
      el._editable = list;
    },
    quote(el, body, d) {
      const ed = rich(d.text, t('Citation…'), 'q');
      const cite = input(d.cite, t('Auteur de la citation'));
      body.append(ed, h('div', { class: 'sub-fields' }, cite));
      el._read = () => ({ type: 'quote', text: cleanHtml(ed), cite: cite.value.trim() });
      el._focus = (end) => focusEditable(ed, end);
      el._editable = ed;
    },
    image(el, body, d) {
      const state = { ...d };
      const alt = input(state.alt, t('Texte alternatif (décris l’image)'));
      const link = input(state.link, t('Lien (facultatif)'));
      const size = select(state.size, { normal: t('Normale'), wide: t('Large'), small: t('Petite') }, t('Taille'));
      const caption = rich(state.caption, t('Légende (facultatif)'), 'cap');
      const draw = () => {
        body.textContent = '';
        const m = media[state.media];
        if (!state.media || !m) {
          const empty = h('div', { class: 'img-empty', tabindex: '0', role: 'button' }, h('b', { text: '🖼️ ' + t('Ajouter une image') }), h('span', { class: 'small', text: t('Glisse une image ici ou clique pour choisir') }));
          empty.addEventListener('click', choose);
          empty.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(); } });
          dropEvents(empty, async (files) => {
            const res = await upload(files[0]);
            if (res.ok) { media[res.media.id] = res.media; state.media = res.media.id; draw(); changed(); }
          });
          body.append(empty);
          return;
        }
        alt.placeholder = m.alt ? m.alt : t('Texte alternatif (décris l’image)');
        body.append(h('figure', {}, h('img', { src: m.url, alt: m.alt || '' })), caption,
          h('div', { class: 'sub-fields' }, alt, link, size),
          h('div', { class: 'row' }, h('button', { class: 'btn btn-sm', type: 'button', text: t('Changer l’image'), onclick: choose })));
      };
      const choose = async () => { const m = await pickMedia(); if (m) { media[m.id] = m; state.media = m.id; draw(); changed(); } };
      draw();
      el._read = () => ({ type: 'image', media: state.media, alt: alt.value.trim(), caption: cleanHtml(caption), link: link.value.trim(), size: size.value });
      el._focus = () => (body.querySelector('.img-empty') || alt).focus();
    },
    callout(el, body, d, extra) {
      el.dataset.variant = d.variant || 'tip';
      const variant = select(d.variant || 'tip', { tip: '💡 ' + t('Astuce'), info: 'ℹ️ ' + t('Bon à savoir'), warning: '⚠️ ' + t('Attention'), success: '✅ ' + t('À retenir'), note: '📝 ' + t('Note') }, t('Encadré'));
      variant.addEventListener('change', () => { el.dataset.variant = variant.value; changed(); });
      extra.append(variant);
      const title = input(d.title, t('Titre de l’encadré (facultatif)'));
      const ed = rich(d.text, t('Écris ici…'));
      body.append(h('div', { class: 'callout-box' }, title, ed));
      el._read = () => ({ type: 'callout', variant: variant.value, title: title.value.trim(), text: cleanHtml(ed) });
      el._focus = (end) => focusEditable(ed, end);
      el._editable = ed;
    },
    faq(el, body, d) {
      const title = input(d.title, t('Titre de la section (facultatif)') + ' — ' + t('FAQ'));
      const items = h('div', { class: 'items' });
      const add = (q = '', a = '') => {
        const qi = input(q, t('Question'), { class: 'q' });
        const ai = rich(a, t('Réponse'));
        const row = h('div', { class: 'item-row' }, qi, ai, h('span', { class: 'item-del' }, iconBtn('x', t('Supprimer'), () => { row.remove(); changed(); })));
        items.append(row);
        return qi;
      };
      (d.items && d.items.length ? d.items : [{ q: '', a: '' }]).forEach((i) => add(i.q, i.a));
      body.append(title, items, h('button', { class: 'btn btn-sm', type: 'button', text: '+ ' + t('Ajouter une question'), onclick: () => { add().focus(); changed(); } }));
      el._read = () => ({ type: 'faq', title: title.value.trim(), items: $$('.item-row', items).map((r) => ({ q: $('input', r).value.trim(), a: cleanHtml($('.rt', r)) })) });
      el._focus = () => $('input.q', items)?.focus();
    },
    table(el, body, d) {
      const head = h('input', { type: 'checkbox', checked: d.head !== false });
      const caption = input(d.caption, t('Légende (facultatif)'));
      const table = h('table');
      const draw = (rows) => {
        table.textContent = '';
        rows.forEach((r, i) => {
          const tr = h('tr', { class: i === 0 && head.checked ? 'head' : '' });
          r.forEach((c) => { const td = h('td', { contenteditable: 'true' }); td.innerHTML = c; tr.append(td); });
          table.append(tr);
        });
      };
      const read = () => $$('tr', table).map((tr) => $$('td', tr).map((td) => cleanHtml(td)));
      draw(d.rows && d.rows.length ? d.rows : [['', ''], ['', '']]);
      head.addEventListener('change', () => { draw(read()); changed(); });
      const op = (fn) => () => { const rows = read(); fn(rows); if (rows.length && rows[0].length) draw(rows); changed(); };
      body.append(h('div', { class: 'table-scroll' }, table),
        h('div', { class: 'row small' },
          h('button', { class: 'btn btn-sm', type: 'button', text: '+ ' + t('Ajouter une ligne'), onclick: op((r) => r.push(r[0].map(() => ''))) }),
          h('button', { class: 'btn btn-sm', type: 'button', text: '+ ' + t('Ajouter une colonne'), onclick: op((r) => r.forEach((row) => row.push(''))) }),
          h('button', { class: 'btn btn-sm btn-ghost', type: 'button', text: t('Supprimer la dernière ligne'), onclick: op((r) => r.length > 1 && r.pop()) }),
          h('button', { class: 'btn btn-sm btn-ghost', type: 'button', text: t('Supprimer la dernière colonne'), onclick: op((r) => r[0].length > 1 && r.forEach((row) => row.pop())) }),
          h('label', { class: 'check' }, head, h('span', { text: t('Première ligne = en-têtes') }))),
        h('div', { class: 'sub-fields' }, caption));
      el._read = () => ({ type: 'table', head: head.checked, caption: caption.value.trim(), rows: read() });
      el._focus = () => $('td', table)?.focus();
    },
    button(el, body, d) {
      const text = input(d.text, t('Texte du bouton'));
      const url = input(d.url, t('Adresse (URL)'));
      const style = select(d.style, { primary: t('Principal'), secondary: t('Secondaire') }, t('Bouton'));
      const align = select(d.align, { left: t('À gauche'), center: t('Centré') }, t('Bouton'));
      linkAssist(url);
      body.append(h('div', { class: 'sub-fields' }, text, url, style, align));
      el._read = () => ({ type: 'button', text: text.value.trim(), url: url.value.trim(), style: style.value, align: align.value });
      el._focus = () => text.focus();
    },
    video(el, body, d) {
      const url = input(d.url, t('Lien YouTube ou Vimeo'), { type: 'url' });
      const caption = input(d.caption, t('Légende (facultatif)'));
      const hint = h('p', { class: 'small muted' });
      const check = () => {
        const ok = !url.value || /^(https?:\/\/)?(www\.|m\.)?(youtube\.com\/(watch\?(.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)[\w-]{11}|vimeo\.com\/(video\/)?\d+/.test(url.value);
        hint.textContent = ok ? '' : '⚠️ ' + t('Lien vidéo non reconnu');
      };
      url.addEventListener('input', check);
      check();
      body.append(h('div', { class: 'video-box' }, h('b', { text: '▶ ' + t('Vidéo') }), h('div', { class: 'sub-fields' }, url, caption), hint));
      el._read = () => ({ type: 'video', url: url.value.trim(), caption: caption.value.trim() });
      el._focus = () => url.focus();
    },
    code(el, body, d) {
      const area = h('textarea', { spellcheck: 'false', 'aria-label': t('Code') });
      area.value = d.code || '';
      const lang = input(d.lang, t('Langage (facultatif)'));
      area.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') { e.preventDefault(); document.execCommand('insertText', false, '  '); }
      });
      body.append(area, h('div', { class: 'sub-fields' }, lang));
      el._read = () => ({ type: 'code', code: area.value, lang: lang.value.trim() });
      el._focus = () => area.focus();
    },
    divider(el, body) {
      body.append(h('hr'));
      el._read = () => ({ type: 'divider' });
      el._focus = () => {};
    },
    hero(el, body, d) {
      const state = { media: d.media || '' };
      const title = input(d.title, t('Grand titre'), { class: 'h-title' });
      const text = rich(d.text, t('Phrase d’accroche'), 'h-text');
      const b1 = input(d.button, t('Bouton principal'));
      const u1 = input(d.url, t('Lien du bouton'));
      const b2 = input(d.button2, t('Bouton secondaire'));
      const u2 = input(d.url2, t('Lien du bouton secondaire'));
      [u1, u2].forEach(linkAssist);
      const pic = h('div', { class: 'row' });
      const drawPic = () => {
        pic.textContent = '';
        const m = media[state.media];
        if (m) pic.append(h('img', { src: m.thumb, alt: '', class: 'thumb' }));
        pic.append(h('button', { class: 'btn btn-sm', type: 'button', text: m ? t('Changer l’image') : '🖼️ ' + t('Ajouter une image'), onclick: async () => { const x = await pickMedia(); if (x) { media[x.id] = x; state.media = x.id; drawPic(); changed(); } } }));
        if (m) pic.append(h('button', { class: 'btn btn-sm btn-ghost', type: 'button', text: t('Retirer l’image'), onclick: () => { state.media = ''; drawPic(); changed(); } }));
      };
      drawPic();
      body.append(h('div', { class: 'hero-box' }, title, text, h('div', { class: 'sub-fields' }, b1, u1, b2, u2), pic));
      el._read = () => ({ type: 'hero', title: title.value.trim(), text: cleanHtml(text), button: b1.value.trim(), url: u1.value.trim(), button2: b2.value.trim(), url2: u2.value.trim(), media: state.media });
      el._focus = () => title.focus();
    },
    features(el, body, d) {
      const title = input(d.title, t('Titre de la section (facultatif)'));
      const grid = h('div', { class: 'item-grid' });
      const add = (i = { icon: '✨', title: '', text: '' }) => {
        const icon = input(i.icon, t('Icône'), { maxlength: '8', class: 'icon-input' });
        const ti = input(i.title, t('Titre'));
        const tx = rich(i.text, t('Écris ici…'));
        const row = h('div', { class: 'item-row' }, h('div', { class: 'row' }, icon, ti), tx, h('span', { class: 'item-del' }, iconBtn('x', t('Supprimer'), () => { row.remove(); changed(); })));
        row._read = () => ({ icon: icon.value.trim(), title: ti.value.trim(), text: cleanHtml(tx) });
        grid.append(row);
      };
      (d.items || []).forEach(add);
      body.append(title, grid, h('button', { class: 'btn btn-sm', type: 'button', text: '+ ' + t('Ajouter un atout'), onclick: () => { add(); changed(); } }));
      el._read = () => ({ type: 'features', title: title.value.trim(), items: $$('.item-row', grid).map((r) => r._read()) });
      el._focus = () => title.focus();
    },
    team(el, body, d) {
      const title = input(d.title, t('Titre de la section (facultatif)'));
      const grid = h('div', { class: 'item-grid' });
      const add = (p = { name: '', role: '', bio: '', media: '', link: '' }) => {
        const state = { media: p.media || '' };
        const name = input(p.name, t('Prénom et nom'));
        const role = input(p.role, t('Fonction'));
        const bio = rich(p.bio, t('Présentation en une ou deux phrases'));
        const link = input(p.link, t('Lien du profil (LinkedIn…)'));
        linkAssist(link);
        const pic = h('button', { type: 'button', class: 'member-pic', title: t('Choisir la photo'), 'aria-label': t('Choisir la photo') });
        const drop = h('button', { type: 'button', class: 'link-btn small', text: t('Retirer la photo') });
        const drawPic = () => {
          const m = media[state.media];
          pic.replaceChildren(m ? h('img', { src: m.thumb, alt: '' }) : h('span', { text: '📷' }));
          drop.hidden = !m;
        };
        pic.addEventListener('click', async () => { const x = await pickMedia(); if (x) { media[x.id] = x; state.media = x.id; drawPic(); changed(); } });
        drop.addEventListener('click', () => { state.media = ''; drawPic(); changed(); });
        drawPic();
        const row = h('div', { class: 'item-row member-row' }, h('div', { class: 'member-side' }, pic, drop),
          h('div', { class: 'member-fields' }, h('div', { class: 'row' }, name, role), bio, link),
          h('span', { class: 'item-del' }, iconBtn('x', t('Supprimer'), () => { row.remove(); changed(); })));
        row._read = () => ({ name: name.value.trim(), role: role.value.trim(), bio: cleanHtml(bio), media: state.media, link: link.value.trim() });
        grid.append(row);
      };
      (d.items || []).forEach(add);
      body.append(title, grid, h('button', { class: 'btn btn-sm', type: 'button', text: '+ ' + t('Ajouter une personne'), onclick: () => { add(); changed(); } }));
      el._read = () => ({ type: 'team', title: title.value.trim(), items: $$('.item-row', grid).map((r) => r._read()) });
      el._focus = () => title.focus();
    },
    posts(el, body, d) {
      const title = input(d.title, t('Titre de la section (facultatif)'));
      const count = h('input', { type: 'number', min: '1', max: '12', value: d.count || 3, 'aria-label': t('Nombre d’articles') });
      const cats = { '': t('Toutes les catégories') };
      cfg.categories.filter((c) => c.kind !== 'product').forEach((c) => { cats[c.id] = c.name; });
      const category = select(d.category || '', cats, t('Catégorie'));
      body.append(h('div', { class: 'posts-box' }, h('b', { text: '📰 ' + t('Derniers articles') }), h('div', { class: 'sub-fields' }, title, count, category)));
      el._read = () => ({ type: 'posts', title: title.value.trim(), count: +count.value || 3, category: category.value });
      el._focus = () => title.focus();
    },
    products(el, body, d) {
      const title = input(d.title, t('Titre de la section (facultatif)'));
      const count = h('input', { type: 'number', min: '1', max: '12', value: d.count || 4, 'aria-label': t('Nombre de produits') });
      const cats = { '': t('Toutes les catégories') };
      cfg.categories.filter((c) => c.kind === 'product').forEach((c) => { cats[c.id] = c.name; });
      const category = select(d.category || '', cats, t('Catégorie'));
      body.append(h('div', { class: 'posts-box' }, h('b', { text: '🛍️ ' + t('Produits') }), h('div', { class: 'sub-fields' }, title, count, category),
        cfg.site.shopOn ? '' : h('p', { class: 'small muted', text: t('La boutique est fermée : ce bloc reste invisible.') })));
      el._read = () => ({ type: 'products', title: title.value.trim(), count: +count.value || 4, category: category.value });
      el._focus = () => title.focus();
    },
    contact(el, body, d) {
      const title = input(d.title, t('Titre de la section (facultatif)'));
      const button = input(d.button, t('Texte du bouton (facultatif)'));
      body.append(h('div', { class: 'contact-box' }, h('b', { text: '✉️ ' + t('Formulaire de contact') }),
        h('p', { class: 'small muted', text: t('Le formulaire de contact s’affichera ici, avec protection anti-spam.') }), h('div', { class: 'sub-fields' }, title, button)));
      el._read = () => ({ type: 'contact', title: title.value.trim(), button: button.value.trim() });
      el._focus = () => title.focus();
    },
  };

  /* ───────────── Gestion des blocs ───────────── */
  const blocks = () => $$(':scope > .block', container);
  // Le focus est donné immédiatement : aucune frappe rapide ne doit se perdre.
  const insertBlock = (data, after = null, focus = true) => {
    const el = makeBlock(data);
    if (after) after.after(el); else container.append(el);
    if (focus && el._focus) el._focus(false);
    changed();
    return el;
  };
  const replaceBlock = (old, data) => {
    const el = makeBlock(data);
    old.replaceWith(el);
    if (el._focus) el._focus(false);
    changed();
    return el;
  };
  const move = (el, dir) => {
    const target = dir < 0 ? el.previousElementSibling : el.nextElementSibling;
    if (!target) return;
    if (dir < 0) target.before(el); else target.after(el);
    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    changed();
  };
  const removeBlock = (el, silent = false) => {
    const prev = el.previousElementSibling;
    const next = el.nextElementSibling;
    const data = el._read();
    el.remove();
    if (!blocks().length) insertBlock(TYPES.paragraph[3](), null, false);
    changed();
    if (!silent) {
      toast(t('Bloc supprimé.'), '', { label: t('Annuler'), run: () => { const back = makeBlock(data); if (prev && prev.isConnected) prev.after(back); else if (next && next.isConnected) next.before(back); else container.prepend(back); changed(); } });
    }
    return prev;
  };
  const focusEditable = (el, end) => {
    if (!el) return;
    el.focus();
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(!end);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  };
  const caretAt = (el, where) => {
    const sel = window.getSelection();
    if (!sel.rangeCount || !sel.isCollapsed) return false;
    const range = sel.getRangeAt(0);
    const probe = document.createRange();
    probe.selectNodeContents(el);
    if (where === 'start') probe.setEnd(range.startContainer, range.startOffset);
    else probe.setStart(range.endContainer, range.endOffset);
    return probe.toString().length === 0 && !probe.cloneContents().querySelector('img');
  };

  /* Glisser-déposer par la poignée. */
  let dragged = null;
  function bindDrag(el) {
    const grip = $('.grip', el);
    grip.addEventListener('mousedown', () => { el.draggable = true; });
    grip.addEventListener('touchstart', () => { el.draggable = true; }, { passive: true });
    el.addEventListener('dragstart', (e) => { dragged = el; el.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); });
    el.addEventListener('dragend', () => { el.draggable = false; el.classList.remove('dragging'); $$('.drop-before,.drop-after', container).forEach((b) => b.classList.remove('drop-before', 'drop-after')); dragged = null; });
    el.addEventListener('dragover', (e) => {
      if (!dragged || dragged === el) return;
      e.preventDefault();
      const after = e.clientY > el.getBoundingClientRect().top + el.offsetHeight / 2;
      $$('.drop-before,.drop-after', container).forEach((b) => b.classList.remove('drop-before', 'drop-after'));
      el.classList.add(after ? 'drop-after' : 'drop-before');
    });
    el.addEventListener('drop', (e) => {
      if (!dragged || dragged === el) return;
      e.preventDefault();
      if (el.classList.contains('drop-after')) el.after(dragged); else el.before(dragged);
      changed();
    });
  }

  /* ───────────── Clavier dans les zones de texte ───────────── */
  container.addEventListener('keydown', (e) => {
    const block = e.target.closest('.block');
    if (!block || !e.target.isContentEditable) return;
    const type = block.dataset.type;
    const ed = e.target.closest('[contenteditable]');
    const inMain = ed === block._editable;

    if (e.key === '/' && type === 'paragraph' && ed.textContent.trim() === '') {
      e.preventDefault();
      openInserter(ed, block, true);
      return;
    }
    if (!inMain || !RICH.includes(type)) return;
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      const sel = window.getSelection();
      const range = sel.getRangeAt(0);
      range.deleteContents();
      const tail = document.createRange();
      tail.selectNodeContents(ed);
      tail.setStart(range.endContainer, range.endOffset);
      const box = document.createElement('div');
      box.append(tail.extractContents());
      const rest = box.textContent.trim() === '' && !box.querySelector('img') ? '' : box.innerHTML;
      insertBlock({ type: 'paragraph', text: rest }, block);
      return;
    }
    if (e.key === 'Backspace' && caretAt(ed, 'start')) {
      const prev = block.previousElementSibling;
      if (ed.textContent === '' && !ed.querySelector('img')) {
        e.preventDefault();
        removeBlock(block, true);
        if (prev && prev._focus) prev._focus(true);
        return;
      }
      if (prev && RICH.includes(prev.dataset.type) && prev._editable) {
        e.preventDefault();
        const target = prev._editable;
        focusEditable(target, true);
        const marker = document.createRange();
        marker.selectNodeContents(target);
        marker.collapse(false);
        const html = ed.innerHTML;
        block.remove();
        document.execCommand('insertHTML', false, html);
        changed();
      }
      return;
    }
    if (e.key === 'ArrowUp' && caretAt(ed, 'start')) {
      const prev = block.previousElementSibling;
      if (prev && prev._focus) { e.preventDefault(); prev._focus(true); }
    }
    if (e.key === 'ArrowDown' && caretAt(ed, 'end')) {
      const next = block.nextElementSibling;
      if (next && next._focus) { e.preventDefault(); next._focus(false); }
    }
  });

  function listKeys(e) {
    const li = window.getSelection().anchorNode?.parentElement?.closest('li') || window.getSelection().anchorNode?.closest?.('li');
    const block = e.target.closest('.block');
    if (e.key === 'Enter' && !e.shiftKey && li && li.textContent.trim() === '' && !li.nextElementSibling) {
      e.preventDefault();
      li.remove();
      insertBlock({ type: 'paragraph', text: '' }, block);
    }
    if (e.key === 'Backspace' && li && li.textContent === '' && e.target.querySelectorAll('li').length === 1) {
      e.preventDefault();
      replaceBlock(block, { type: 'paragraph', text: '' });
    }
  }

  /* ───────────── Collage intelligent (Word, Google Docs, pages web) ───────────── */
  const INLINE = { STRONG: 'strong', B: 'strong', EM: 'em', I: 'em', A: 'a', CODE: 'code', BR: 'br', MARK: 'mark', SUB: 'sub', SUP: 'sup', S: 's', DEL: 's' };
  const inlineClean = (node) => {
    let out = '';
    node.childNodes.forEach((n) => {
      if (n.nodeType === 3) { out += esc(n.textContent.replace(/\s+/g, ' ')); return; }
      if (n.nodeType !== 1) return;
      const tag = INLINE[n.tagName];
      if (['SCRIPT', 'STYLE', 'META', 'LINK', 'TITLE'].includes(n.tagName)) return;
      const inner = inlineClean(n);
      if (tag === 'br') out += '<br>';
      else if (tag === 'a' && /^(https?:|mailto:|tel:|\/|#)/i.test(n.getAttribute('href') || '')) out += '<a href="' + esc(n.getAttribute('href')) + '">' + inner + '</a>';
      else if (tag && tag !== 'a' && inner.trim()) out += '<' + tag + '>' + inner + '</' + tag + '>';
      else if (n.tagName === 'SPAN' && /font-weight:\s*(bold|[6-9]00)/.test(n.getAttribute('data-s') || '') && inner.trim()) out += '<strong>' + inner + '</strong>';
      else if (n.tagName === 'SPAN' && /font-style:\s*italic/.test(n.getAttribute('data-s') || '') && inner.trim()) out += '<em>' + inner + '</em>';
      else out += inner;
    });
    return out;
  };
  const htmlToBlocks = (html) => {
    // Les attributs style sont renommés avant analyse : la politique CSP stricte de l'admin les bloquerait.
    const doc = new DOMParser().parseFromString(html.replace(/\sstyle\s*=\s*(["'])([\s\S]*?)\1/gi, ' data-s=$1$2$1'), 'text/html');
    const out = [];
    const walk = (parent) => {
      parent.childNodes.forEach((n) => {
        if (n.nodeType === 3) { if (n.textContent.trim()) out.push({ type: 'paragraph', text: esc(n.textContent.trim()) }); return; }
        if (n.nodeType !== 1) return;
        const tag = n.tagName;
        if (/^H[1-6]$/.test(tag)) out.push({ type: 'heading', level: Math.min(4, Math.max(2, +tag[1])), text: inlineClean(n).trim() });
        else if (tag === 'P') { const text = inlineClean(n).trim(); if (text) out.push({ type: 'paragraph', text }); }
        else if (tag === 'UL' || tag === 'OL') out.push({ type: 'list', style: tag.toLowerCase(), items: $$(':scope > li', n).map((li) => inlineClean(li).trim()).filter(Boolean) });
        else if (tag === 'BLOCKQUOTE') out.push({ type: 'quote', text: inlineClean(n).trim(), cite: '' });
        else if (tag === 'PRE') out.push({ type: 'code', code: n.textContent, lang: '' });
        else if (tag === 'HR') out.push({ type: 'divider' });
        else if (tag === 'TABLE') {
          const rows = $$('tr', n).map((tr) => $$('th,td', tr).map((c) => inlineClean(c).trim()));
          if (rows.length) out.push({ type: 'table', head: !!n.querySelector('th'), caption: '', rows });
        } else if (['DIV', 'SECTION', 'ARTICLE', 'MAIN', 'BODY', 'SPAN', 'FONT', 'B', 'CENTER'].includes(tag) && n.querySelector('p,h1,h2,h3,h4,h5,h6,ul,ol,table,blockquote,pre,div')) walk(n);
        else if (!['SCRIPT', 'STYLE', 'META', 'LINK', 'IMG', 'TITLE'].includes(tag)) { const text = inlineClean(n).trim(); if (text) out.push({ type: 'paragraph', text }); }
      });
    };
    walk(doc.body);
    return out.filter((b) => b.type !== 'list' || b.items.length);
  };
  container.addEventListener('paste', (e) => {
    const ed = e.target.closest('[contenteditable]');
    if (!ed) return;
    const block = ed.closest('.block');
    const html = e.clipboardData.getData('text/html');
    const text = e.clipboardData.getData('text/plain');
    e.preventDefault();
    let parsed = [];
    if (html) parsed = htmlToBlocks(html);
    else if (text.includes('\n\n') || /\n[-*•] /.test(text)) parsed = text.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean).map((p) => (/^[-*•] /.test(p)
      ? { type: 'list', style: 'ul', items: p.split('\n').map((l) => esc(l.replace(/^[-*•]\s+/, ''))) }
      : /^#{1,4} /.test(p) ? { type: 'heading', level: Math.min(4, Math.max(2, p.match(/^#+/)[0].length)), text: esc(p.replace(/^#+\s*/, '')) }
        : { type: 'paragraph', text: esc(p).replace(/\n/g, '<br>') }));
    const multi = parsed.length > 1 || (parsed.length === 1 && parsed[0].type !== 'paragraph');
    if (multi && block && block._editable === ed && block.dataset.type === 'paragraph') {
      let anchor = block;
      const replaceCurrent = ed.textContent.trim() === '';
      parsed.forEach((data) => { anchor = insertBlock(data, anchor, false); });
      if (replaceCurrent) block.remove();
      anchor._focus && anchor._focus(true);
      return;
    }
    if (html && parsed.length) document.execCommand('insertHTML', false, parsed.map((b) => b.text || (b.items || []).join('<br>')).join('<br>'));
    else document.execCommand('insertText', false, text);
  });

  /* ───────────── Sélecteur de bloc (bouton + ou « / ») ───────────── */
  let inserter = null;
  function openInserter(anchorEl, block, replace) {
    closeInserter();
    const searchBox = h('input', { type: 'search', placeholder: t('Rechercher un bloc…'), 'aria-label': t('Rechercher un bloc…') });
    const list = h('div', { role: 'listbox' });
    inserter = h('div', { class: 'inserter' }, searchBox, list);
    let active = 0;
    const draw = () => {
      const q = searchBox.value.trim().toLowerCase();
      list.textContent = '';
      const entries = Object.entries(TYPES).filter(([k, v]) => (k !== 'products' || cfg.site.shopOn) && (!q || (k + ' ' + v[1] + ' ' + v[2]).toLowerCase().includes(q)));
      if (!entries.length) list.append(h('p', { class: 'small muted', text: t('Aucun bloc trouvé') }));
      active = Math.min(active, Math.max(0, entries.length - 1));
      entries.forEach(([k, v], i) => list.append(h('button', {
        type: 'button', role: 'option', class: i === active ? 'active' : '', 'data-type': k,
        onclick: () => choose(k),
      }, h('span', { class: 'ico', text: v[0] }), h('span', {}, h('b', { text: v[1] }), h('small', { text: v[2] })))));
    };
    const choose = (type) => {
      const data = TYPES[type][3]();
      if (replace && block) replaceBlock(block, data);
      else insertBlock(data, block || null);
      closeInserter();
    };
    searchBox.addEventListener('input', () => { active = 0; draw(); });
    searchBox.addEventListener('keydown', (e) => {
      const items = $$('button', list);
      if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % items.length; draw(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + items.length) % items.length; draw(); }
      if (e.key === 'Enter') { e.preventDefault(); items[active]?.click(); }
      if (e.key === 'Escape') { closeInserter(); block?._focus?.(true); }
    });
    draw();
    document.body.append(inserter);
    const r = anchorEl.getBoundingClientRect();
    const top = r.bottom + 6 + inserter.offsetHeight > window.innerHeight ? Math.max(8, r.top - inserter.offsetHeight - 6) : r.bottom + 6;
    inserter.style.top = top + 'px';
    inserter.style.left = Math.min(Math.max(8, r.left), window.innerWidth - inserter.offsetWidth - 8) + 'px';
    searchBox.focus();
  }
  function closeInserter() { if (inserter) { inserter.remove(); inserter = null; } }
  document.addEventListener('mousedown', (e) => { if (inserter && !inserter.contains(e.target)) closeInserter(); });
  $('#add-end').addEventListener('click', (e) => openInserter(e.currentTarget, blocks().at(-1) || null, false));

  /* ───────────── Barre de mise en forme et liens ───────────── */
  const bar = h('div', { class: 'format-bar', hidden: true });
  const linkInput = h('input', { type: 'text', placeholder: t('Coller une adresse ou chercher une page…'), 'aria-label': t('Lien'), hidden: true });
  const suggest = h('div', { class: 'link-suggest', hidden: true });
  let savedRange = null;
  const cmd = (name, label, title, fn) => h('button', { type: 'button', title, 'aria-label': title, text: label, 'data-cmd': name, onmousedown: (e) => { e.preventDefault(); fn(); } });
  const wrap = (tag) => {
    const sel = window.getSelection();
    if (!sel.rangeCount || sel.isCollapsed) return;
    const parent = sel.anchorNode.parentElement.closest(tag);
    if (parent) { parent.replaceWith(...parent.childNodes); changed(); return; }
    document.execCommand('insertHTML', false, '<' + tag + '>' + esc(sel.toString()) + '</' + tag + '>');
  };
  bar.append(
    cmd('bold', 'B', t('Gras') + ' (Ctrl+B)', () => document.execCommand('bold')),
    cmd('italic', 'I', t('Italique') + ' (Ctrl+I)', () => document.execCommand('italic')),
    cmd('link', '🔗', t('Lien') + ' (Ctrl+K)', () => startLink()),
    cmd('code', '</>', t('Code'), () => wrap('code')),
    cmd('mark', '🖍', t('Surligner'), () => wrap('mark')),
    cmd('unlink', '⨯', t('Retirer le lien'), () => { document.execCommand('unlink'); document.execCommand('removeFormat'); }),
    linkInput);
  document.body.append(bar, suggest);
  const showBar = () => {
    const sel = window.getSelection();
    if (!sel.rangeCount || sel.isCollapsed || linkInput === document.activeElement) { if (linkInput !== document.activeElement) bar.hidden = true; return; }
    const node = sel.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement);
    const ed = node && node.closest('#blocks [contenteditable]');
    if (!ed) { bar.hidden = true; return; }
    const r = sel.getRangeAt(0).getBoundingClientRect();
    bar.hidden = false;
    bar.style.top = Math.max(8, r.top - bar.offsetHeight - 8) + 'px';
    bar.style.left = Math.min(Math.max(8, r.left + r.width / 2 - bar.offsetWidth / 2), window.innerWidth - bar.offsetWidth - 8) + 'px';
  };
  document.addEventListener('selectionchange', () => requestAnimationFrame(showBar));
  function startLink() {
    const sel = window.getSelection();
    if (!sel.rangeCount || sel.isCollapsed) return;
    savedRange = sel.getRangeAt(0).cloneRange();
    const existing = sel.anchorNode.parentElement.closest('a');
    linkInput.value = existing ? existing.getAttribute('href') : '';
    $$('button', bar).forEach((b) => { b.hidden = true; });
    linkInput.hidden = false;
    linkInput.focus();
    linkInput.select();
    searchLinks();
  }
  const endLink = (apply) => {
    const value = linkInput.value.trim();
    linkInput.hidden = true;
    suggest.hidden = true;
    $$('button', bar).forEach((b) => { b.hidden = false; });
    bar.hidden = true;
    if (!savedRange) return;
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(savedRange);
    savedRange = null;
    if (!apply) return;
    if (!value) { document.execCommand('unlink'); changed(); return; }
    let url = value;
    if (!/^(https?:|mailto:|tel:|\/|#)/i.test(url)) url = /^[\w-]+(\.[\w-]+)+/.test(url) ? 'https://' + url : '/' + url.replace(/^\/+/, '');
    document.execCommand('createLink', false, url);
    changed();
  };
  let linkTimer = null;
  function searchLinks() {
    clearTimeout(linkTimer);
    linkTimer = setTimeout(async () => {
      const q = linkInput.value.trim();
      if (/^(https?:|mailto:|tel:)/.test(q)) { suggest.hidden = true; return; }
      const res = await api('api/links?q=' + encodeURIComponent(q));
      suggest.textContent = '';
      (res.links || []).forEach((l) => suggest.append(h('button', { type: 'button', onmousedown: (e) => { e.preventDefault(); linkInput.value = l.path; endLink(true); } },
        h('span', { text: l.title }), h('small', { text: l.type + ' · ' + l.path }))));
      const r = linkInput.getBoundingClientRect();
      suggest.hidden = !(res.links || []).length;
      suggest.style.top = r.bottom + 8 + 'px';
      suggest.style.left = Math.min(r.left, window.innerWidth - 330) + 'px';
    }, 180);
  }
  linkInput.addEventListener('input', searchLinks);
  linkInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); endLink(true); }
    if (e.key === 'Escape') { e.preventDefault(); endLink(false); }
  });
  linkInput.addEventListener('blur', () => setTimeout(() => { if (!linkInput.hidden) endLink(true); }, 120));

  /* Aide à la saisie d'un lien dans un champ (bouton, bannière). */
  function linkAssist(field) {
    field.setAttribute('list', 'eclair-links');
  }
  const datalist = h('datalist', { id: 'eclair-links' });
  document.body.append(datalist);
  api('api/links?q=').then((res) => (res.links || []).forEach((l) => datalist.append(h('option', { value: l.path, label: l.title }))));

  /* ───────────── Panneau latéral ───────────── */
  const f = (id) => document.getElementById(id);
  const fill = (sel, options, value) => {
    sel.textContent = '';
    Object.entries(options).forEach(([v, l]) => sel.append(h('option', { value: v, text: l, selected: v === value })));
  };
  if (f('f-category')) {
    const cats = { '': t('Aucune') };
    cfg.categories.filter((c) => (c.kind || 'post') === (item.type === 'product' ? 'product' : 'post')).forEach((c) => { cats[c.id] = c.name; });
    fill(f('f-category'), cats, item.category || '');
  }

  /* ───────────── Fiche produit : prix, lien de paiement, photos, variantes ───────────── */
  const isProduct = item.type === 'product';
  const photos = []; // la première est l'image principale (item.image), les suivantes la galerie
  const AVAIL = { in_stock: t('En stock'), out_of_stock: t('Épuisé'), preorder: t('Précommande') };
  // Même règle que le serveur (Shop::amount) : « 29,90 », « 29.9 € » ; null si la saisie est invalide.
  const amount = (v) => {
    const x = String(v || '').replace(/[\s\u00a0\u202f€$£]|CHF/g, '').replace(',', '.');
    return x === '' ? '' : /^\d{1,7}(\.\d{1,2})?$/.test(x) ? x : null;
  };
  const comma = !['en', 'zh'].includes(document.documentElement.lang);
  const showAmount = (v) => (v && comma ? String(v).replace('.', ',') : v || '');
  const checkAmount = (el) => {
    const bad = amount(el.value) === null;
    el.classList.toggle('invalid', bad);
    el.setAttribute('aria-invalid', String(bad));
  };
  root.addEventListener('input', (e) => { if (e.target.matches('#p-price, #p-compare, .v-price')) checkAmount(e.target); });
  function drawGallery() {
    const box = f('p-gallery');
    if (!box) return;
    box.textContent = '';
    photos.forEach((id, i) => {
      const m = media[id];
      box.append(h('figure', { class: 'thumb' + (i ? '' : ' is-main') },
        m ? h('img', { src: m.thumb, alt: m.alt || '' }) : h('span', { class: 'small muted', text: '?' }),
        h('figcaption', {},
          i ? iconBtn('star', t('Choisir comme photo principale'), () => { photos.unshift(...photos.splice(i, 1)); drawGallery(); changed(); }) : h('span', { class: 'badge brand', text: t('Principale') }),
          iconBtn('x', t('Retirer'), () => { photos.splice(i, 1); drawGallery(); changed(); }))));
    });
    if (photos.length < 9) {
      box.append(h('button', { class: 'thumb add', type: 'button', onclick: async () => {
        const m = await pickMedia();
        if (m && !photos.includes(m.id)) { media[m.id] = m; photos.push(m.id); drawGallery(); changed(); }
      } }, svg(ICON.plus), h('span', { text: t('Ajouter') })));
    }
  }
  function variantRow(v = {}) {
    const label = input(v.label, t('Libellé (ex. : Taille M)'), { maxlength: '80' });
    const price = input(showAmount(v.price), t('Prix'), { inputmode: 'decimal', class: 'v-price' });
    const link = input(v.link, t('Lien de paiement'), { type: 'url', spellcheck: 'false' });
    const avail = select(v.availability || 'in_stock', AVAIL, t('Disponibilité'));
    const row = h('div', { class: 'variant-row' }, label, price, link, avail, iconBtn('x', t('Supprimer'), () => { row.remove(); changed(); }));
    row._read = () => ({ label: label.value.trim(), price: price.value.trim(), link: link.value.trim(), availability: avail.value });
    return row;
  }
  f('p-variant-add')?.addEventListener('click', () => { const row = variantRow(); f('p-variants').append(row); row.firstChild.focus(); changed(); });
  const loadProduct = (it) => {
    if (!isProduct) return;
    const p = it.product || {};
    f('p-price').value = showAmount(p.price);
    f('p-compare').value = showAmount(p.compare);
    f('p-availability').value = p.availability || 'in_stock';
    f('p-link').value = p.link || '';
    ['brand', 'sku', 'gtin'].forEach((k) => { f('p-' + k).value = p[k] || ''; });
    f('p-condition').value = p.condition || 'new';
    f('p-digital').checked = !!p.digital;
    photos.length = 0;
    [it.image, ...(p.gallery || [])].forEach((id) => { if (id && !photos.includes(id)) photos.push(id); });
    f('p-variants').textContent = '';
    (p.variants || []).forEach((v) => f('p-variants').append(variantRow(v)));
    if ((p.variants || []).length) f('p-variants-box').open = true;
    $$('#p-price, #p-compare, .v-price').forEach(checkAmount);
    drawGallery();
  };
  const readProduct = () => ({
    price: f('p-price').value.trim(),
    compare: f('p-compare').value.trim(),
    availability: f('p-availability').value,
    link: f('p-link').value.trim(),
    gallery: photos.slice(1),
    variants: $$('.variant-row', f('p-variants')).map((r) => r._read()).filter((v) => v.label),
    brand: f('p-brand').value.trim(),
    sku: f('p-sku').value.trim(),
    gtin: f('p-gtin').value.trim(),
    condition: f('p-condition').value,
    digital: f('p-digital').checked,
  });
  fill(f('f-author'), Object.fromEntries(cfg.users.map((u) => [u.id, u.name])), item.author);
  fill(f('f-lang'), cfg.langs, item.lang);
  const trans = { '': t('Aucun') };
  const linked = [];
  cfg.translations.forEach((x) => {
    if (item.tgroup && x.tgroup === item.tgroup) linked.push(x.title + ' (' + x.lang.toUpperCase() + ')');
    else trans[x.id] = x.title + ' (' + x.lang.toUpperCase() + ')';
  });
  fill(f('f-translation'), trans, '');
  if (linked.length) f('f-translation').closest('.field').querySelector('.hint').textContent = t('Lié à :') + ' ' + linked.join(', ');

  const toLocal = (ts) => {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    const p = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  };
  let slugTouched = !!item.id;
  const load = (it) => {
    titleEl.value = it.title || '';
    summaryEl.value = it.summary || '';
    f('f-status').value = it.status;
    f('f-date').value = it.status === 'published' || it.date > Date.now() / 1000 ? (it.date_local || toLocal(it.date)) : '';
    f('f-slug').value = it.slug || '';
    f('f-seo-title').value = it.seo.title || '';
    f('f-seo-desc').value = it.seo.description || '';
    f('f-keyword').value = it.seo.keyword || '';
    f('f-canonical').value = it.seo.canonical || '';
    f('f-noindex').checked = !!it.seo.noindex;
    f('f-hide-title').checked = !!it.options.hide_title;
    f('f-wide').checked = !!it.options.wide;
    f('f-toc').value = it.options.toc || 'auto';
    if (f('f-category')) f('f-category').value = it.category || '';
    f('f-author').value = it.author;
    f('f-lang').value = it.lang;
    container.textContent = '';
    (it.blocks.length ? it.blocks : [TYPES.paragraph[3]()]).forEach((b) => container.append(makeBlock(b)));
    $('#summary-card').hidden = !it.summary;
    $('#summary-add').hidden = !!it.summary;
    if ('image' in it) item.image = it.image;
    drawImage();
    loadProduct(it);
    autosize();
  };

  const serialize = () => ({
    id: item.id,
    type: item.type,
    title: titleEl.value.trim(),
    slug: f('f-slug').value.trim(),
    status: f('f-status').value,
    date: f('f-date').value,
    lang: f('f-lang').value,
    tgroup: item.tgroup,
    translation_of: f('f-translation').value,
    summary: summaryEl.value.trim(),
    image: isProduct ? photos[0] || '' : item.image || '',
    category: f('f-category') ? f('f-category').value : '',
    product: isProduct ? readProduct() : undefined,
    author: f('f-author').value,
    seo: {
      title: f('f-seo-title').value.trim(),
      description: f('f-seo-desc').value.trim(),
      keyword: f('f-keyword').value.trim(),
      canonical: f('f-canonical').value.trim(),
      noindex: f('f-noindex').checked,
    },
    options: { hide_title: f('f-hide-title').checked, wide: f('f-wide').checked, toc: f('f-toc').value },
    blocks: blocks().map((b) => b._read()),
  });

  /* Image à la une. */
  function drawImage() {
    const box = f('f-image-preview');
    if (!box) return;
    box.textContent = '';
    const m = media[item.image];
    if (m) box.append(h('img', { src: m.thumb, alt: '' }), h('span', { class: 'small muted', text: m.alt || m.name }));
  }
  f('f-image-pick')?.addEventListener('click', async () => { const m = await pickMedia(); if (m) { media[m.id] = m; item.image = m.id; drawImage(); changed(); } });
  f('f-image-clear')?.addEventListener('click', () => { item.image = ''; drawImage(); changed(); });

  /* Onglets. */
  $$('.side [role=tab]').forEach((tab) => tab.addEventListener('click', () => {
    $$('.side [role=tab]').forEach((x) => x.setAttribute('aria-selected', String(x === tab)));
    $$('.side-pane').forEach((p) => { p.hidden = p.dataset.pane !== tab.dataset.tab; });
  }));
  $('#btn-side').addEventListener('click', () => root.classList.toggle('side-open'));

  /* Slug automatique tant qu'il n'a pas été modifié à la main. */
  const slugify = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/ß/g, 'ss')
    .replace(/[^a-z0-9/]+/g, '-').replace(/\/+/g, '/').replace(/(^[-/]+|[-/]+$)/g, '').replace(/-+/g, '-').slice(0, 80);
  f('f-slug').addEventListener('input', () => { slugTouched = true; });
  f('f-slug').addEventListener('change', () => { const v = slugify(f('f-slug').value); f('f-slug').value = item.type === 'page' ? v : v.replace(/\//g, '-'); });

  const autosize = () => { titleEl.style.height = 'auto'; titleEl.style.height = titleEl.scrollHeight + 'px'; };
  titleEl.addEventListener('input', () => {
    autosize();
    if (!slugTouched) f('f-slug').value = slugify(titleEl.value).replace(/\//g, '-');
  });
  titleEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); blocks()[0]?._focus?.(false); } });
  $('#summary-add').addEventListener('click', () => { $('#summary-card').hidden = false; $('#summary-add').hidden = true; summaryEl.focus(); });

  /* ───────────── Aperçu Google et compteurs ───────────── */
  const prefix = () => ({ post: cfg.site.blog + '/', product: cfg.site.shop + '/' }[item.type] || '/');
  const serp = () => {
    const title = titleEl.value.trim() || t('Sans titre');
    const seoTitle = f('f-seo-title').value.trim() || (item.id && item.id === cfg.site.home ? cfg.site.name : (cfg.site.format || '{title} · {site}').replace('{title}', title).replace('{site}', cfg.site.name));
    const intro = blocks().map((b) => b._read()).find((b) => b.type === 'paragraph');
    const tmp = document.createElement('div');
    tmp.innerHTML = intro ? intro.text : '';
    const desc = f('f-seo-desc').value.trim() || summaryEl.value.trim() || tmp.textContent;
    const slug = f('f-slug').value.trim();
    const path = item.id && item.id === cfg.site.home ? '' : (prefix() + slug).replace(/^\//, '');
    $('#serp-name').textContent = cfg.site.name;
    $('#serp-url').textContent = cfg.site.host + (path ? ' › ' + path.split('/').join(' › ') : '');
    $('#serp-title').textContent = seoTitle.length > 62 ? seoTitle.slice(0, 60) + '…' : seoTitle;
    $('#serp-desc').textContent = desc.length > 158 ? desc.slice(0, 155) + '…' : desc;
    $('#slug-prefix').textContent = prefix();
    const ct = f('f-seo-title').value.trim().length || seoTitle.length;
    const cd = f('f-seo-desc').value.trim().length;
    $('#c-title').textContent = ct + ' / 60';
    $('#c-title').className = 'counter ' + (ct >= 30 && ct <= 60 ? 'good' : ct > 60 ? 'bad' : '');
    $('#c-desc').textContent = cd + ' / 160';
    $('#c-desc').className = 'counter ' + (cd >= 110 && cd <= 160 ? 'good' : cd > 160 || cd === 0 ? 'bad' : '');
    const sw = summaryEl.value.trim() ? summaryEl.value.trim().split(/\s+/).length : 0;
    $('#summary-count').textContent = sw + ' ' + t('Mots').toLowerCase();
    $('#summary-count').className = 'counter ' + (sw >= 25 && sw <= 90 ? 'good' : sw > 90 ? 'bad' : '');
  };

  /* ───────────── Analyse SEO / GEO en direct ───────────── */
  const ring = (el, score) => {
    el.className = 'ring ' + (score >= 80 ? '' : score >= 50 ? 'ok' : 'bad');
    $('.val', el).setAttribute('stroke-dasharray', score + ' 100');
    $('b', el).textContent = score;
  };
  const showAnalysis = (a) => {
    ring($('#ring-seo'), a.seo);
    ring($('#ring-geo'), a.geo);
    const avg = Math.round((a.seo + a.geo) / 2);
    $('#tab-score').textContent = avg;
    $('#tab-score').className = 'score ' + (avg >= 80 ? 'good' : avg >= 50 ? 'ok' : 'bad');
    $('#words-info').textContent = a.words + ' ' + t('Mots').toLowerCase() + ' · ' + a.reading + ' ' + t('min de lecture');
    ['seo', 'geo'].forEach((g) => {
      const list = $('#checks-' + g);
      list.textContent = '';
      a.checks.filter((c) => c.group === g).sort((x, y) => ({ bad: 0, ok: 1, good: 2 }[x.status] - { bad: 0, ok: 1, good: 2 }[y.status]))
        .forEach((c) => list.append(h('li', {}, h('span', { class: 'dot ' + (c.status === 'good' ? 'ok' : c.status === 'ok' ? 'warn' : 'bad') }), h('span', { text: c.label }))));
    });
  };
  let analyzeTimer = null;
  const analyze = () => {
    clearTimeout(analyzeTimer);
    analyzeTimer = setTimeout(async () => {
      const res = await api('api/analyze', serialize(), { quiet: true }).catch(() => null);
      if (res && res.ok) {
        showAnalysis(res.analysis);
        $('#slug-hint').textContent = 'URL : ' + res.path + (res.slug !== f('f-slug').value.trim() && f('f-slug').value.trim() ? ' (' + res.slug + ')' : '');
      }
    }, 1200);
  };

  /* ───────────── État, sauvegarde, brouillon local ───────────── */
  const storeKey = 'eclair-draft-' + (item.id || 'new-' + item.type);
  let saved = '';
  let localTimer = null;
  const isDirty = () => JSON.stringify(serialize()) !== saved;
  const status = (text) => { $('#save-status').textContent = text; };
  function changed() {
    serp();
    analyze();
    status(isDirty() ? '● ' + t('Modifications non enregistrées') : '');
    clearTimeout(localTimer);
    localTimer = setTimeout(() => {
      try { if (isDirty()) localStorage.setItem(storeKey, JSON.stringify({ t: Date.now(), data: serialize() })); } catch (e) { /* stockage indisponible */ }
    }, 800);
  }
  const updateBadge = () => {
    const st = item.status === 'published' ? (item.date > Date.now() / 1000 ? 'scheduled' : 'published') : 'draft';
    const badge = $('#status-badge');
    badge.className = 'badge ' + (st === 'published' ? 'ok' : st === 'scheduled' ? 'brand' : '');
    badge.textContent = st === 'published' ? t('Publié') : st === 'scheduled' ? t('Programmé') : t('Brouillon');
    $('#btn-publish').textContent = f('f-status').value === 'published' && item.status === 'published' ? t('Mettre à jour') : t('Publier');
    const view = $('#view-link');
    view.hidden = !(cfg.visible && cfg.path);
    view.href = D.base + cfg.path;
  };

  let saving = false;
  async function save(publish) {
    if (saving) return;
    saving = true;
    if (publish) f('f-status').value = 'published';
    const data = serialize();
    status(t('Enregistrement…'));
    $('#btn-save').disabled = $('#btn-publish').disabled = true;
    const res = await api('api/save', data).catch(() => null);
    saving = false;
    $('#btn-save').disabled = $('#btn-publish').disabled = false;
    if (!res || !res.ok) { status('⚠️ ' + t('Modifications non enregistrées')); return; }
    const wasNew = !item.id;
    item = res.item;
    cfg.path = res.path;
    cfg.visible = res.visible;
    f('f-slug').value = item.slug;
    if (item.status === 'published') f('f-date').value = item.date_local || '';
    f('f-translation').value = '';
    slugTouched = true;
    saved = JSON.stringify(serialize());
    try { localStorage.removeItem(storeKey); localStorage.removeItem('eclair-draft-new-' + item.type); } catch (e) { /* rien */ }
    if (wasNew) history.replaceState(null, '', D.admin + '/edit/' + item.id);
    showAnalysis(res.analysis);
    updateBadge();
    status('✓ ' + t('Enregistré') + ' · ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
    if (data.status === 'published') toast(item.date > Date.now() / 1000 ? t('Programmé ✓') : t('Publié ✓'), 'ok');
  }
  $('#btn-save').addEventListener('click', () => save(false));
  $('#btn-publish').addEventListener('click', () => save(true));
  f('f-status').addEventListener('change', updateBadge);
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(false); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && document.activeElement?.isContentEditable) { e.preventDefault(); startLink(); }
  });
  window.addEventListener('beforeunload', (e) => { if (isDirty()) { e.preventDefault(); e.returnValue = ''; } });
  root.addEventListener('input', changed);
  root.addEventListener('change', changed);

  /* Aperçu dans un nouvel onglet (rendu réel du thème, sans enregistrer). */
  $('#btn-preview').addEventListener('click', () => {
    const form = $('#preview-form');
    form.elements.data.value = JSON.stringify(serialize());
    form.submit();
  });

  /* Historique, duplication, suppression. */
  $('#btn-revisions').addEventListener('click', async () => {
    const dialog = $('#revisions-dialog');
    const list = $('#revisions-list');
    list.textContent = '';
    if (!item.id) list.append(h('p', { class: 'muted', text: t('Aucune révision pour l’instant.') }));
    else {
      const res = await api('api/revisions/' + item.id);
      if (!res.revisions?.length) list.append(h('p', { class: 'muted', text: t('Aucune révision pour l’instant.') }));
      (res.revisions || []).forEach((r) => list.append(h('div', { class: 'row between item-row' },
        h('span', {}, h('b', { text: r.when }), h('span', { class: 'small muted', text: ' · ' + r.title + ' · ' + r.words + ' ' + t('Mots').toLowerCase() })),
        h('button', { class: 'btn btn-sm', type: 'button', text: t('Restaurer'), onclick: async () => {
          if (!confirm(t('Restaurer cette version ?'))) return;
          const rev = await api('api/revision/' + item.id + '/' + r.name);
          if (rev.ok) { load({ ...rev.item, id: item.id, status: f('f-status').value }); dialog.close(); changed(); toast(t('Version restaurée.'), 'ok'); }
        } }))));
    }
    dialog.showModal();
  });
  $('#btn-duplicate').addEventListener('click', async () => {
    if (!item.id) return;
    const res = await api('api/duplicate', { id: item.id });
    if (res.ok) location.href = res.edit;
  });
  $('#btn-delete').addEventListener('click', async () => {
    if (!item.id) { location.href = D.admin + '/content'; return; }
    if (!confirm(t('Supprimer ce contenu ?'))) return;
    const redirect = cfg.visible ? (prompt(t('Rediriger l’ancienne adresse vers (facultatif) :'), '/') || '') : '';
    const res = await api('api/delete', { id: item.id, redirect: redirect === '/' ? '' : redirect });
    if (res.ok) { saved = JSON.stringify(serialize()); location.href = D.admin + '/content'; }
  });

  /* Garde la session ouverte tant que l'on écrit. */
  let activity = Date.now();
  root.addEventListener('keydown', () => { activity = Date.now(); });
  setInterval(() => { if (Date.now() - activity < 15 * 60 * 1000) api('api/ping', {}, { quiet: true }).catch(() => {}); }, 10 * 60 * 1000);

  /* ───────────── Assistant IA ───────────── */
  // L'assistant (assets/admin/assistant.js) lit le contenu en cours et y applique ses modifications, annulables.
  window.EclairEditor = {
    read: serialize,
    apply(patch, extraMedia = {}) {
      const before = serialize();
      Object.assign(media, extraMedia);
      const next = { ...item, ...before, date_local: before.date };
      ['title', 'summary', 'image', 'blocks'].forEach((k) => { if (patch[k] != null) next[k] = patch[k]; });
      if (patch.seo) next.seo = { ...before.seo, ...patch.seo };
      if (patch.product && isProduct) next.product = { ...before.product, ...patch.product };
      load(next);
      changed();
      return () => { load({ ...item, ...before, date_local: before.date }); changed(); };
    },
  };
  // Modification proposée par Claude ou ChatGPT (connecteur MCP) : appliquée ici, à vérifier puis enregistrer.
  if (cfg.proposal) {
    if (cfg.proposal.stale) toast(t('Ce contenu a été modifié depuis la proposition : demande une nouvelle proposition à l’assistant.'), 'warn');
    else {
      const undo = window.EclairEditor.apply(cfg.proposal.patch, cfg.proposal.media);
      toast(t('Modification proposée par l’IA : vérifie-la, puis enregistre pour l’appliquer.') + (cfg.proposal.note ? ' (' + cfg.proposal.note + ')' : ''), 'ok', { label: t('Annuler'), run: undo });
    }
  }
  if (D.ai) {
    const assist = async (task, extra = {}, btn = null) => {
      if (btn) btn.setAttribute('aria-busy', 'true');
      const res = await api('api/ai/assist', { task, item: serialize(), ...extra }).catch(() => ({ ok: false }));
      if (btn) btn.removeAttribute('aria-busy');
      return res;
    };
    const mini = (label, run) => {
      const b = h('button', { type: 'button', class: 'ai-mini', text: '✨ ' + label });
      b.addEventListener('click', () => { if (!b.hasAttribute('aria-busy')) run(b); });
      return b;
    };
    const setField = (el, value) => { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); };

    /* Réécrire la sélection (barre de mise en forme). */
    let menu = null;
    const closeMenu = () => { if (menu) { menu.remove(); menu = null; } };
    const keep = (e) => e.preventDefault();
    const rewrite = async (mode, lang, range, ed) => {
      closeMenu();
      const holder = document.createElement('div');
      holder.append(range.cloneContents());
      const before = ed.innerHTML;
      toast(t('L’IA retravaille le passage…'));
      const res = await api('api/ai/assist', { task: 'rewrite', mode, lang, html: holder.innerHTML, item: { id: item.id, type: item.type, title: titleEl.value } }).catch(() => ({}));
      if (!res.ok || !res.html || !ed.isConnected) return;
      ed.focus();
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
      document.execCommand('insertHTML', false, res.html);
      changed();
      toast(t('Passage réécrit.'), 'ok', { label: t('Annuler'), run: () => { ed.innerHTML = before; changed(); } });
    };
    const openMenu = () => {
      const sel = window.getSelection();
      if (!sel.rangeCount || sel.isCollapsed) return;
      const range = sel.getRangeAt(0).cloneRange();
      const node = range.commonAncestorContainer;
      const ed = (node.nodeType === 1 ? node : node.parentElement).closest('#blocks [contenteditable]');
      if (!ed) return;
      closeMenu();
      const choice = (label, run) => h('button', { type: 'button', role: 'menuitem', onmousedown: keep, onclick: run }, label);
      const modes = [['improve', '✨ ' + t('Améliorer')], ['fix', '✔️ ' + t('Corriger les fautes')], ['shorten', '✂️ ' + t('Raccourcir')], ['expand', '➕ ' + t('Développer')],
        ['simplify', '🧩 ' + t('Simplifier')], ['warmer', '🤝 ' + t('Plus chaleureux')], ['formal', '👔 ' + t('Plus professionnel')]];
      menu = h('div', { class: 'ai-menu', role: 'menu', 'aria-label': t('Retoucher avec l’IA') },
        modes.map(([mode, label]) => choice(label, () => rewrite(mode, '', range, ed))), h('hr'),
        choice('🌍 ' + t('Traduire…'), () => menu.replaceChildren(...Object.entries(cfg.langs).map(([code, name]) => choice(name, () => rewrite('translate', code, range, ed))))));
      document.body.append(menu);
      const r = range.getBoundingClientRect();
      menu.style.top = Math.max(8, Math.min(r.bottom + 10, innerHeight - menu.offsetHeight - 8)) + 'px';
      menu.style.left = Math.max(8, Math.min(r.left, innerWidth - menu.offsetWidth - 8)) + 'px';
    };
    bar.append(cmd('ai', '✨', t('Retoucher avec l’IA'), openMenu));
    // Le clic qui ouvre le menu (bouton ✨) ne doit pas le refermer aussitôt.
    document.addEventListener('mousedown', (e) => { if (menu && !menu.contains(e.target) && !e.target.closest('[data-cmd=ai]')) closeMenu(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

    /* « L'essentiel ». */
    const writeSummary = async (b) => {
      const res = await assist('summary', {}, b);
      if (!res.ok) return;
      $('#summary-card').hidden = false;
      $('#summary-add').hidden = true;
      setField(summaryEl, res.summary);
    };
    $('#summary-card .row').append(mini(t('Rédiger'), writeSummary));
    $('#summary-add').after(mini(t('Rédiger « L’essentiel » avec l’IA'), writeSummary));

    /* Titres, méta-description et expression-clé (onglet SEO). */
    const picks = h('div', { class: 'ai-picks' });
    $('[data-pane=seo]').prepend(h('div', { class: 'row wrap' },
      mini(t('Titre SEO et description'), async (b) => {
        const res = await assist('meta', {}, b);
        if (res.ok) { setField(f('f-seo-title'), res.seo_title); setField(f('f-seo-desc'), res.seo_description); }
      }),
      mini(t('Expression-clé'), async (b) => { const res = await assist('keyword', {}, b); if (res.ok) setField(f('f-keyword'), res.keyword); }),
      mini(t('Idées de titres'), async (b) => {
        const res = await assist('titles', {}, b);
        if (!res.ok) return;
        picks.replaceChildren(...res.titles.map((x) => h('button', { type: 'button', text: x, onclick: () => { setField(titleEl, x); picks.textContent = ''; toast(t('Titre mis à jour.'), 'ok'); } })));
      })), picks);

    /* Points faibles de l'analyse : FAQ, textes alternatifs, assistant. */
    $('[data-pane=score]').prepend(h('div', { class: 'row wrap' },
      mini(t('Ajouter une FAQ'), async (b) => {
        const res = await assist('faq', {}, b);
        if (!res.ok || !res.items.length) return;
        const existing = blocks().find((x) => x.dataset.type === 'faq');
        if (existing) {
          const data = existing._read();
          replaceBlock(existing, { ...data, items: [...data.items.filter((q) => q.q), ...res.items] });
        } else {
          insertBlock({ type: 'faq', title: '', items: res.items }, blocks().filter((x) => !['contact', 'posts', 'products'].includes(x.dataset.type)).at(-1) || null, false);
        }
        toast(t('FAQ ajoutée : relis les réponses.'), 'ok');
      }),
      mini(t('Textes alternatifs'), async (b) => {
        const missing = [...new Set([...blocks().filter((x) => x.dataset.type === 'image').map((x) => x._read()).filter((d) => d.media && !d.alt).map((d) => d.media), ...photos])]
          .filter((id) => !(media[id] || {}).alt);
        if (!missing.length) { toast(t('Toutes les images ont un texte alternatif.'), 'ok'); return; }
        for (const id of missing) {
          const res = await assist('alt', { media: id, lang: f('f-lang').value }, b);
          if (res.ok) media[id] = res.media;
        }
        drawGallery();
        analyze();
        toast(t('Textes alternatifs ajoutés.'), 'ok');
      }),
      mini(t('Améliorer avec l’assistant'), () => window.EclairAI && window.EclairAI.send(t('Analyse ce contenu et améliore ses points faibles pour le SEO et le GEO.')))));

    /* Image à la une créée par l'IA. */
    if (D.ai.images && f('f-image-pick')) {
      f('f-image-pick').after(mini(t('Créer avec l’IA'), async (b) => {
        toast(t('Création de l’image (10 à 60 secondes)…'));
        const res = await assist('cover', {}, b);
        if (res.ok) { media[res.media.id] = res.media; item.image = res.media.id; drawImage(); changed(); toast(t('Image à la une créée.'), 'ok'); }
      }));
    }
  }

  /* ───────────── Démarrage ───────────── */
  load(item);
  saved = JSON.stringify(serialize());
  serp();
  updateBadge();
  if (cfg.analysis) showAnalysis(cfg.analysis);
  try {
    const backup = JSON.parse(localStorage.getItem(storeKey) || 'null');
    if (backup && backup.t > (item.updated || 0) * 1000 && JSON.stringify(backup.data) !== saved) {
      toast(t('Une version non enregistrée a été retrouvée. La restaurer ?'), '', { label: t('Restaurer'), run: () => {
        load({ ...item, ...backup.data, seo: backup.data.seo, options: backup.data.options, blocks: backup.data.blocks });
        changed();
        toast(t('Version restaurée.'), 'ok');
      } });
    }
  } catch (e) { /* stockage indisponible */ }
  if (!item.id) titleEl.focus();
})();
