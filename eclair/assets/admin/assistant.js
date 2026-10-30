/* ⚡ Éclair — assistant IA : tiroir de conversation (réponses en flux, cartes d'action, sans dépendance). */
(() => {
  const E = window.Eclair;
  if (!E || !E.D.ai) return;
  const { D, t, api, toast, upload, h, $, $$ } = E;
  const AI = D.ai;
  const mem = {
    get: (k) => { try { return sessionStorage.getItem('eclair-ai-' + k); } catch (e) { return null; } },
    set: (k, v) => { try { if (v == null) sessionStorage.removeItem('eclair-ai-' + k); else sessionStorage.setItem('eclair-ai-' + k, v); } catch (e) { /* stockage indisponible */ } },
  };

  /* ───────── Icônes ───────── */
  const PATHS = {
    spark: '<path d="M12 3l1.9 5.6L19.5 10.5l-5.6 1.9L12 18l-1.9-5.6L4.5 10.5l5.6-1.9z"/><path d="M19 3v4M21 5h-4"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    clip: '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
    send: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    stop: '<rect x="7" y="7" width="10" height="10" rx="2"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
  };
  const icon = (name) => {
    const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.setAttribute('viewBox', '0 0 24 24');
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = PATHS[name];
    return el;
  };
  const iconBtn = (name, label, onclick, cls = 'btn btn-ghost btn-icon') => h('button', { type: 'button', class: cls, title: label, 'aria-label': label, onclick }, icon(name));

  /* ───────── Markdown des réponses → éléments (jamais de HTML venu du modèle) ───────── */
  const safeHref = (url) => (/^(https?:\/\/|\/(?!\/)|#)/i.test(url) ? url : null);
  const inline = (text) => {
    const frag = document.createDocumentFragment();
    const re = /(\*\*([^*]+)\*\*|\*([^*\s][^*]*?)\*|`([^`]+)`|\[([^\]]+)\]\(([^)\s]+)\))/g;
    let last = 0;
    let m;
    while ((m = re.exec(text))) {
      if (m.index > last) frag.append(text.slice(last, m.index));
      if (m[2]) frag.append(h('strong', { text: m[2] }));
      else if (m[3]) frag.append(h('em', { text: m[3] }));
      else if (m[4]) frag.append(h('code', { text: m[4] }));
      else {
        const href = safeHref(m[6]);
        frag.append(href ? h('a', { href: href.startsWith('/') && D.base && !href.startsWith(D.base + '/') ? D.base + href : href, target: '_blank', rel: 'noopener', text: m[5] }) : m[5]);
      }
      last = re.lastIndex;
    }
    if (last < text.length) frag.append(text.slice(last));
    return frag;
  };
  const markdown = (src) => {
    const out = document.createDocumentFragment();
    const lines = src.replace(/\r/g, '').split('\n');
    let list = null;
    let para = [];
    const flush = () => { if (para.length) { const p = h('p'); para.forEach((l, i) => { if (i) p.append(h('br')); p.append(inline(l)); }); out.append(p); para = []; } };
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      const item = line.match(/^\s*([-*•]|\d+[.)])\s+(.*)$/);
      if (/^```/.test(line.trim())) {
        flush(); list = null;
        const code = [];
        for (i++; i < lines.length && !/^```/.test(lines[i].trim()); i++) code.push(lines[i]);
        out.append(h('pre', {}, h('code', { text: code.join('\n') })));
      } else if (item) {
        flush();
        const tag = /\d/.test(item[1]) ? 'ol' : 'ul';
        if (!list || list.tagName.toLowerCase() !== tag) { list = h(tag); out.append(list); }
        list.append(h('li', {}, inline(item[2])));
      } else if (/^#{1,6}\s/.test(line)) {
        flush(); list = null;
        out.append(h('p', {}, h('strong', {}, inline(line.replace(/^#+\s*/, '')))));
      } else if (!line.trim()) {
        flush(); list = null;
      } else {
        list = null;
        para.push(line);
      }
    }
    flush();
    return out;
  };

  /* ───────── Tiroir ───────── */
  const log = h('div', { class: 'ai-log', role: 'log', 'aria-live': 'polite' });
  const history = h('div', { class: 'ai-history', hidden: true });
  const input = h('textarea', { rows: 1, placeholder: t('Demande un article, une page, une image…'), 'aria-label': t('Ton message à l’assistant') });
  const fileInput = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, hidden: true });
  const chips = h('div', { class: 'ai-attachments' });
  const sendBtn = iconBtn('send', t('Envoyer (Entrée)'), null, 'btn btn-primary btn-icon');
  sendBtn.type = 'submit';
  const stopBtn = iconBtn('stop', t('Arrêter'), () => controller && controller.abort(), 'btn btn-icon ai-stop');
  stopBtn.hidden = true;
  const costEl = h('span', { class: 'ai-cost' });
  const form = h('form', { class: 'ai-form' },
    chips,
    h('div', { class: 'ai-input' }, iconBtn('clip', t('Joindre une image'), () => fileInput.click()), input, sendBtn, stopBtn, fileInput),
    h('p', { class: 'ai-foot' }, h('span', { text: t('L’IA peut se tromper : relis avant de publier.') }), costEl));
  // Claude ou ChatGPT : sélecteur quand les deux clés sont enregistrées (le choix est retenu pour la personne).
  let provider = AI.provider || '';
  const picks = Object.keys(AI.providers || {});
  const drawer = h('aside', { class: 'ai-drawer', id: 'ai-drawer', 'aria-label': t('Assistant IA'), hidden: true },
    h('header', { class: 'ai-head' },
      h('span', { class: 'ai-logo' }, icon('spark')),
      h('div', { class: 'ai-title' }, h('b', { text: t('Assistant') }), picks.length > 1
        ? h('select', { class: 'ai-pick', 'aria-label': t('Choisir l’IA'), onchange: (e) => { provider = e.target.value; } },
          picks.map((p) => h('option', { value: p, text: AI.providers[p], selected: p === provider })))
        : h('small', { text: AI.model + (AI.images ? ' · ' + AI.images : '') })),
      iconBtn('clock', t('Conversations'), () => toggleHistory()),
      iconBtn('plus', t('Nouvelle conversation'), () => reset(true)),
      iconBtn('x', t('Fermer (Échap)'), () => close())),
    history, log, form);
  document.body.append(drawer);

  let chatId = mem.get('chat') || '';
  let busy = false;
  let controller = null;
  let loading = null;
  let attachments = [];
  // L'éditeur se déclare (window.EclairEditor) : l'assistant lit le contenu en cours et y applique ses modifications.
  const editor = window.EclairEditor || null;

  const open = (focus = true) => {
    drawer.hidden = false;
    document.documentElement.classList.add('ai-open');
    mem.set('open', '1');
    if (!log.childElementCount) {
      if (chatId) loading = loadChat(chatId).finally(() => { loading = null; });
      else empty();
    }
    if (focus) requestAnimationFrame(() => input.focus());
  };
  const close = () => {
    drawer.hidden = true;
    document.documentElement.classList.remove('ai-open');
    mem.set('open', null);
  };
  const toggle = () => (drawer.hidden ? open() : close());
  const scroll = () => { log.scrollTop = log.scrollHeight; };

  /* Accueil : suggestions adaptées à la page. */
  const empty = () => {
    log.textContent = '';
    // [icône, libellé, demande, à compléter par la personne ?]
    const ideas = editor
      ? [['❓', t('Ajouter une FAQ'), t('Ajoute une FAQ à ce contenu.')], ['✂️', t('Introduction plus percutante'), t('Rends l’introduction plus percutante.')],
        ['🔎', t('Optimiser pour Google et les IA'), t('Optimise ce contenu pour Google et les IA (titre, L’essentiel, méta-description).')],
        ['🖼️', t('Créer l’image à la une'), t('Crée une image à la une pour ce contenu.')]]
      : [['✍️', t('Écrire un article'), t('Écris un article sur '), true], ['👥', t('Ajouter un membre à l’équipe'), t('Ajoute à la page équipe : Prénom Nom, fonction, avec la photo jointe.'), true],
        ['💡', t('5 idées d’articles'), t('Propose-moi 5 idées d’articles pour attirer de nouveaux clients.')], ['🚀', t('Améliorer ma page d’accueil'), t('Relis ma page d’accueil et propose des améliorations.')]];
    log.append(h('div', { class: 'ai-empty' },
      h('div', { class: 'ai-hello' }, icon('spark')),
      h('h3', { text: t('Bonjour {name} ! Que préparons-nous aujourd’hui ?').replace('{name}', AI.name) }),
      h('p', { class: 'muted', text: t('J’écris dans le ton de ton site et je prépare des brouillons : tu relis, tu publies.') }),
      h('div', { class: 'ai-chips' }, ideas.map(([emoji, label, text, fill]) => h('button', {
        type: 'button', class: 'ai-chip', title: text, onclick: () => (fill ? prefill(text) : send(text)),
      }, emoji + ' ' + label + (fill ? '…' : ''))))));
  };
  const prefill = (text) => { input.value = text; autosize(); input.focus(); input.setSelectionRange(text.length, text.length); };

  const reset = (focus) => {
    if (busy) return;
    chatId = '';
    mem.set('chat', null);
    costEl.textContent = '';
    history.hidden = true;
    empty();
    if (focus) input.focus();
  };

  /* Historique des conversations. */
  const toggleHistory = async () => {
    if (!history.hidden) { history.hidden = true; return; }
    history.hidden = false;
    history.textContent = t('Chargement…');
    const res = await api('api/ai/chats', null, { quiet: true }).catch(() => ({}));
    history.textContent = '';
    if (!res.ok || !res.chats.length) { history.append(h('p', { class: 'muted small', text: t('Aucune conversation pour l’instant.') })); return; }
    res.chats.forEach((c) => {
      const row = h('div', { class: 'ai-history-row' + (c.id === chatId ? ' current' : '') },
        h('button', { type: 'button', class: 'ai-history-open', onclick: () => { history.hidden = true; loadChat(c.id); } }, h('b', { text: c.title }), h('small', { text: c.when })),
        iconBtn('trash', t('Supprimer'), async () => {
          if (!confirm(t('Supprimer cette conversation ?'))) return;
          await api('api/ai/forget', { id: c.id });
          row.remove();
          if (c.id === chatId) reset(false);
        }));
      history.append(row);
    });
  };

  const loadChat = async (id) => {
    log.textContent = '';
    const res = await api('api/ai/chat/' + id, null, { quiet: true }).catch(() => ({}));
    if (!res.ok) { chatId = ''; mem.set('chat', null); empty(); return; }
    chatId = id;
    mem.set('chat', id);
    costEl.textContent = ' · ' + res.chat.cost;
    res.chat.messages.forEach((m) => {
      if (m.r === 'u') addUser(m.text, m.media);
      else {
        const bot = addBot();
        bot.setText(m.text);
        m.cards.forEach((c) => bot.card(c));
        if (m.error) bot.error(m.error);
        bot.end();
      }
    });
    if (!res.chat.messages.length) empty();
    scroll();
  };

  /* ───────── Messages ───────── */
  const addUser = (text, media = []) => {
    log.querySelector('.ai-empty')?.remove();
    const el = h('div', { class: 'ai-msg ai-user' }, h('div', { class: 'ai-bubble', text }));
    if (media.length) el.append(h('div', { class: 'ai-thumbs' }, media.map((m) => h('img', { src: m.thumb, alt: m.alt || m.name }))));
    log.append(el);
    scroll();
  };
  const addBot = () => {
    const body = h('div', { class: 'ai-text' });
    const status = h('div', { class: 'ai-status', hidden: true });
    const cards = h('div', { class: 'ai-cards' });
    const el = h('div', { class: 'ai-msg ai-bot', 'aria-busy': 'true' }, body, cards, status);
    log.append(el);
    let text = '';
    let frame = 0;
    const draw = () => { frame = 0; body.replaceChildren(markdown(text)); scroll(); };
    return {
      el,
      setText(v) { text = v; draw(); },
      append(d) { text += d; status.hidden = true; if (!frame) frame = requestAnimationFrame(draw); },
      status(label) { status.hidden = false; status.replaceChildren(h('span', { class: 'ai-dots', 'aria-hidden': 'true' }), h('span', { text: label })); scroll(); },
      card(c) {
        status.hidden = true;
        // Même brouillon ou même proposition modifiés une seconde fois : la carte récapitulative remplace l'ancienne.
        const el = renderCard(c);
        const old = c.key && [...cards.children].find((x) => x.dataset.key === c.key);
        old ? old.replaceWith(el) : cards.append(el);
        scroll();
      },
      error(msg) { status.hidden = true; el.append(h('p', { class: 'ai-error', role: 'alert', text: '⚠️ ' + msg })); scroll(); },
      end() { status.hidden = true; el.removeAttribute('aria-busy'); if (frame) { cancelAnimationFrame(frame); draw(); } if (!text.trim() && !cards.childElementCount && !el.querySelector('.ai-error')) body.append(h('p', { class: 'muted', text: '…' })); },
    };
  };

  /* ───────── Cartes d'action ───────── */
  const typeLabel = (c) => ({ page: t('Page'), product: t('Produit') }[c.type] || t('Article'));
  const stateLabel = (s) => ({ draft: t('Brouillon'), published: t('Publié'), scheduled: t('Programmé') }[s] || s);
  const btn = (label, onclick, cls = 'btn btn-sm') => h('button', { type: 'button', class: cls, text: label, onclick });
  const link = (label, href, cls = 'btn btn-sm', blank = false) => h('a', { class: cls, href, text: label, target: blank ? '_blank' : null, rel: blank ? 'noopener' : null });
  // Liste des changements, sauf si la note de l'IA dit déjà tout (un seul changement).
  const changesList = (c) => (c.changes && (c.changes.length > 1 || !c.note) ? h('ul', { class: 'ai-changes' }, c.changes.map((x) => h('li', { text: x }))) : null);
  const undoBtn = (token, card) => btn(t('Annuler'), async (e) => {
    e.target.disabled = true;
    const res = await api('api/ai/undo', { token });
    if (res.ok) { card.classList.add('done'); card.querySelector('.ai-card-actions')?.replaceChildren(h('span', { class: 'small muted', text: t('Modification annulée.') })); }
    else if (res.error) e.target.replaceWith(h('small', { class: 'muted', text: res.error }));
    else e.target.disabled = false;
  });

  function renderCard(c) {
    const card = h('div', { class: 'ai-card ai-card-' + c.kind, 'data-key': c.key || null });
    const actions = h('div', { class: 'ai-card-actions' });
    if (c.gone) {
      card.append(h('p', { class: 'small muted', text: '🗑️ ' + t('Supprimé depuis.') + ' ' + (c.title || '') }));
      return card;
    }
    if (c.kind === 'draft') {
      const meta = [typeLabel(c), stateLabel(c.state), c.words + ' ' + t('mots'), 'SEO ' + c.seo, 'GEO ' + c.geo].join(' · ');
      card.append(
        c.thumb ? h('img', { class: 'ai-card-thumb', src: c.thumb, alt: '' }) : h('span', { class: 'ai-card-icon', text: '📝' }),
        h('div', { class: 'ai-card-main' }, h('b', { text: c.title }), h('small', { text: meta })), actions);
      actions.append(link(t('Ouvrir'), c.edit, 'btn btn-sm btn-primary'), link(t('Aperçu'), c.preview, 'btn btn-sm', true));
      if (c.state === 'draft') actions.append(btn(t('Publier…'), () => publishPanel(c, card)));
    } else if (c.kind === 'image') {
      const m = c.media;
      card.append(h('a', { href: m.url, target: '_blank', rel: 'noopener', class: 'ai-card-image' }, h('img', { src: m.thumb, alt: m.alt })),
        h('div', { class: 'ai-card-main' }, h('b', { text: t('Image ajoutée à la médiathèque') }), h('small', { text: m.alt || m.name })), actions);
      if (editor) actions.append(btn(t('Image à la une'), () => { editor.apply({ image: m.id }, { [m.id]: m }); toast(t('Image à la une mise à jour.'), 'ok'); }));
    } else if (c.kind === 'proposal') {
      card.append(h('span', { class: 'ai-card-icon', text: '✏️' }),
        h('div', { class: 'ai-card-main' }, h('b', { text: t('Modification proposée : « {title} »').replace('{title}', c.title) }), h('small', { text: c.note || '' }), changesList(c)), actions);
      if (c.applied) actions.append(...[h('span', { class: 'ai-ok', text: '✅ ' + t('Appliquée') }), c.undo && undoBtn(c.undo, card)].filter(Boolean));
      else if (c.dismissed) actions.append(h('span', { class: 'small muted', text: t('Ignorée') }));
      else if (c.expired) actions.append(h('span', { class: 'small muted', text: t('Proposition expirée') }));
      else {
        actions.append(link(t('Aperçu'), D.admin + '/ai/preview/' + c.pid, 'btn btn-sm', true),
          btn(t('Appliquer'), async (e) => {
            e.target.disabled = true;
            const res = await api('api/ai/apply', { pid: c.pid });
            if (!res.ok) { e.target.disabled = false; return; }
            actions.replaceChildren(h('span', { class: 'ai-ok', text: '✅ ' + t('Appliquée') }), link(t('Voir'), res.url, 'btn btn-sm', true), undoBtn(res.undo, card));
          }, 'btn btn-sm btn-primary'),
          btn(t('Ignorer'), async () => { await api('api/ai/dismiss', { pid: c.pid }); actions.replaceChildren(h('span', { class: 'small muted', text: t('Ignorée') })); }));
      }
    } else if (c.kind === 'updated') {
      card.append(h('span', { class: 'ai-card-icon', text: '✅' }),
        h('div', { class: 'ai-card-main' }, h('b', { text: t('« {title} » mis à jour').replace('{title}', c.title) }), h('small', { text: c.note || '' }), changesList(c)), actions);
      actions.append(...[link(t('Ouvrir'), c.edit, 'btn btn-sm btn-primary'), link(t('Aperçu'), c.preview, 'btn btn-sm', true), c.undo && undoBtn(c.undo, card)].filter(Boolean));
    } else if (c.kind === 'editor') {
      card.append(h('span', { class: 'ai-card-icon', text: '✅' }),
        h('div', { class: 'ai-card-main' }, h('b', { text: t('Modifié dans l’éditeur : pense à enregistrer') }), h('small', { text: c.note || '' }), changesList(c)), actions);
      if (c.patch && editor) {
        const undo = editor.apply(c.patch, c.media || {});
        if (undo) actions.append(btn(t('Annuler'), () => { undo(); actions.replaceChildren(h('span', { class: 'small muted', text: t('Modification annulée.') })); }));
      }
    }
    return card;
  }

  /* Publier tout de suite ou programmer : c'est toujours la personne qui décide. */
  function publishPanel(c, card) {
    if (card.querySelector('.ai-publish')) return;
    const d = new Date(Date.now() + 86400000);
    d.setHours(9, 0, 0, 0);
    const pad = (n) => String(n).padStart(2, '0');
    const when = h('input', { type: 'datetime-local', value: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T09:00', 'aria-label': t('Date de publication') });
    const go = async (date) => {
      const res = await api('api/ai/publish', { id: c.id, date });
      if (!res.ok) return;
      card.replaceWith(renderCard(res.card));
      toast(res.visible ? t('Publié ✓') : t('Programmé ✓'), 'ok', res.visible ? { label: t('Voir'), run: () => window.open(res.url, '_blank', 'noopener') } : null);
    };
    card.append(h('div', { class: 'ai-publish' },
      btn(t('Publier maintenant'), () => confirm(t('Publier « {title} » maintenant ?').replace('{title}', c.title)) && go(''), 'btn btn-sm btn-primary'),
      h('span', { class: 'small muted', text: t('ou') }), when, btn(t('Programmer'), () => when.value && go(when.value))));
  }

  /* ───────── Envoi et lecture du flux ───────── */
  async function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    open(false);
    if (loading) await loading;
    history.hidden = true;
    busy = true;
    const media = attachments;
    attachments = [];
    chips.textContent = '';
    input.value = '';
    autosize();
    addUser(text, media);
    const bot = addBot();
    bot.status(t('Réflexion…'));
    sendBtn.hidden = true;
    stopBtn.hidden = false;
    controller = new AbortController();
    const payload = { chat: chatId, message: text, media: media.map((m) => m.id), provider };
    if (editor) payload.context = { item: editor.read() };
    try {
      const res = await fetch(D.admin + '/api/ai/chat', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/x-ndjson', 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });
      if (!res.ok || !res.body) {
        const json = await res.json().catch(() => ({}));
        bot.error(json.error || t('Erreur réseau : vérifie ta connexion.'));
      } else {
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        for (;;) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          let i;
          while ((i = buffer.indexOf('\n')) >= 0) {
            const line = buffer.slice(0, i).trim();
            buffer = buffer.slice(i + 1);
            if (line) handle(JSON.parse(line), bot);
          }
        }
      }
    } catch (e) {
      bot.error(e.name === 'AbortError' ? t('Demande interrompue.') : t('Erreur réseau : vérifie ta connexion.'));
    }
    bot.end();
    busy = false;
    controller = null;
    sendBtn.hidden = false;
    stopBtn.hidden = true;
    input.focus();
  }

  function handle(ev, bot) {
    if (ev.t === 'start') { chatId = ev.chat; mem.set('chat', ev.chat); }
    else if (ev.t === 'text') bot.append(ev.d);
    else if (ev.t === 'tool') bot.status(ev.label);
    else if (ev.t === 'card') bot.card(ev.card);
    else if (ev.t === 'error') bot.error(ev.error);
    else if (ev.t === 'done' && ev.total) costEl.textContent = ' · ' + t('Conversation : {cost}').replace('{cost}', ev.total);
  }

  /* ───────── Saisie, pièces jointes, raccourcis ───────── */
  const autosize = () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 180) + 'px'; };
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); }
  });
  form.addEventListener('submit', (e) => { e.preventDefault(); send(input.value); });

  const attach = async (files) => {
    for (const file of files) {
      const chip = h('span', { class: 'ai-chip-file uploading', text: file.name });
      chips.append(chip);
      const res = await upload(file);
      chip.remove();
      if (!res.ok) continue;
      const m = res.media;
      attachments.push(m);
      const el = h('span', { class: 'ai-chip-file' }, h('img', { src: m.thumb, alt: '' }), h('span', { text: m.name }),
        iconBtn('x', t('Retirer'), () => { attachments = attachments.filter((x) => x !== m); el.remove(); }, 'ai-chip-x'));
      chips.append(el);
    }
    input.focus();
  };
  fileInput.addEventListener('change', () => { attach([...fileInput.files]); fileInput.value = ''; });
  input.addEventListener('paste', (e) => {
    const files = [...(e.clipboardData?.files || [])].filter((f) => f.type.startsWith('image/'));
    if (files.length) { e.preventDefault(); attach(files); }
  });
  E.dropEvents(drawer, attach);

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'j') { e.preventDefault(); toggle(); }
    if (e.key === 'Escape' && !drawer.hidden && drawer.contains(document.activeElement)) close();
  });
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-ai-open]');
    if (opener) { e.preventDefault(); toggle(); }
  });

  /* Champ « Que veux-tu créer aujourd'hui ? » du tableau de bord. */
  const quick = $('[data-ai-prompt]');
  if (quick) {
    const field = $('textarea', quick);
    quick.addEventListener('submit', (e) => { e.preventDefault(); reset(false); send(field.value); field.value = ''; });
    field.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); quick.requestSubmit(); } });
    $$('[data-ai-idea]', quick).forEach((b) => b.addEventListener('click', () => { field.value = b.dataset.aiIdea; field.focus(); field.setSelectionRange(field.value.length, field.value.length); }));
  }

  window.EclairAI = { open, send };
  if (mem.get('open') === '1' && window.innerWidth > 900) open(false);
})();
