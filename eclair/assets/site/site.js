/* ⚡ Éclair — le seul script du site public (≈ 1 Ko, chargé en différé). */
(() => {
  const body = document.body;

  // Bouton « Modifier » pour les personnes connectées (le lien ne révèle pas l'adresse de l'administration).
  const script = document.currentScript;
  const base = script ? new URL(script.src).pathname.replace(/\/assets\/site\/site\.js$/, '') : '';
  if (body.dataset.id && /(?:^|;\s*)eclair_edit=1(?:;|$)/.test(document.cookie)) {
    const a = document.createElement('a');
    a.className = 'edit-fab';
    a.href = base + '/_eclair/edit/' + encodeURIComponent(body.dataset.id);
    a.textContent = '✏️ ' + (body.dataset.edit || 'Modifier');
    body.appendChild(a);
  }

  // Vidéos : le lecteur (et ses cookies) ne se charge qu'au clic.
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a.video-lite');
    const src = link ? link.dataset.embed || '' : '';
    if (!/^https:\/\/(www\.youtube-nocookie\.com\/embed\/|player\.vimeo\.com\/video\/)/.test(src)) return;
    event.preventDefault();
    const frame = document.createElement('iframe');
    frame.src = src;
    frame.title = link.textContent.trim();
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    link.replaceWith(frame);
  });

  // Formulaire de contact : jeton anti-robot et envoi sans rechargement.
  document.querySelectorAll('form.contact').forEach((form) => {
    const arm = () => { form.elements.k.value = form.dataset.k || ''; };
    form.addEventListener('focusin', arm, { once: true });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      arm();
      const status = form.querySelector('.form-status');
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      status.textContent = form.dataset.sending || '…';
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const data = await response.json();
        status.textContent = data.message || '';
        if (data.ok) form.reset();
      } catch (error) {
        status.textContent = form.dataset.error || 'Erreur';
      }
      button.disabled = false;
    });
  });
})();
