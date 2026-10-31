/* ⚡ Éclair — « Crée ta page avec l'IA » : un brouillon complet (structure, textes, SEO), ouvert ensuite en mode Visuel.
   Chargé seulement pour un nouveau contenu, quand l'assistant IA est actif pour la personne. */
(() => {
  const form = document.getElementById('compose');
  if (!form || !window.Eclair) return;
  const state = form.querySelector('[role=status]');
  const bar = form.querySelector('progress');
  const btn = form.querySelector('[type=submit]');
  // Ces champs ne font pas partie du contenu : l'éditeur ne les voit pas.
  ['input', 'change'].forEach((type) => form.addEventListener(type, (e) => e.stopPropagation()));
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (btn.disabled) return;
    btn.disabled = true;
    bar.hidden = false;
    const start = Date.now();
    const tick = () => { state.textContent = form.dataset.wait + ' ' + Math.round((Date.now() - start) / 1000) + ' s'; };
    tick();
    const timer = setInterval(tick, 1000);
    const res = await window.Eclair.api('api/ai/compose', Object.fromEntries(new FormData(form))).catch(() => null);
    clearInterval(timer);
    if (res && res.ok) { location.href = res.edit; return; }
    btn.disabled = false;
    bar.hidden = true;
    state.textContent = (res && res.error) || '';
  });
})();
