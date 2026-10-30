/* ⚡ Éclair — aperçu de l'apparence (Réglages › Apparence) : les liens du site restent dans l'aperçu, avec l'apparence essayée. */
(() => {
  const base = (document.currentScript || {}).dataset?.base || '';
  const current = new URLSearchParams(location.search).get('path') || '/';
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const url = new URL(a.href, location.href);
    const same = url.origin === location.origin;
    const path = same && url.pathname.startsWith(base) ? url.pathname.slice(base.length) || '/' : url.pathname;
    // Ancres de la page affichée et fichiers (images) : comportement normal.
    if (same && (/^\/(media|assets)\//.test(path) || (url.hash && (url.pathname === location.pathname || path === current)))) return;
    e.preventDefault();
    if (!same) return;
    const q = new URLSearchParams(location.search);
    q.set('path', path);
    location.search = q.toString();
  });
  document.addEventListener('submit', (e) => e.preventDefault());
})();
