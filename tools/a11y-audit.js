// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Audit de l'administration dans un vrai navigateur (Chromium via Playwright, axe-core) : règles WCAG 2.1 A et AA sur
 * chaque écran, défilement horizontal sur téléphone (390 px), erreurs JavaScript, bloc conservé dans l'éditeur.
 * À lancer avant une version, sur un site de test déjà installé (compte camille@example.com, administration /gestion,
 * comme tests/servers.php l'installe) : le script y ajoute toutes les fonctions livrées et un brouillon.
 *
 *   npm install --no-save playwright axe-core && node tools/a11y-audit.js http://127.0.0.1:8082
 *
 * Captures d'écran dans le dossier courant (shot-*.png, mobile-*.png) ; rapport JSON sur la sortie standard.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const axe = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const base = process.argv[2];
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, bypassCSP: true });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(page.url() + ' : ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(page.url() + ' : ' + m.text()); });
  await page.goto(base + '/gestion/login');
  await page.fill('input[name=email]', 'camille@example.com');
  await page.fill('input[name=password]', 'Un-mot-de-passe-solide');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  // Toutes les fonctions livrées, et un contenu avec un bloc d'une fonction absente (bloc conservé).
  for (const id of ['wayback', 'ai-visits', 'discover', 'affiliate', 'planning', 'wordpress', 'drive']) {
    await page.goto(base + '/gestion/extensions');
    const tok = await page.$eval('input[name=_csrf]', (i) => i.value);
    await page.request.post(base + '/gestion/extensions', { form: { _csrf: tok, action: 'add', id } });
  }
  await page.goto(base + '/gestion/');
  const csrf = await page.evaluate(() => (document.body.innerHTML.match(/"csrf":"([a-f0-9]+)"/) || [])[1]);
  const saved = await page.request.post(base + '/gestion/api/save', { headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { type: 'post', title: 'Article avec bloc conservé', status: 'draft', blocks: [{ type: 'paragraph', text: 'Avant' }, { type: 'boutique-x/carte', titre: 'Carte' }] } });
  const id = (await saved.json()).item.id;
  const pages = ['', 'themes', 'extensions', 'ads', 'security', 'x/wayback', 'x/ai-visits', 'x/discover', 'x/affiliate', 'x/planning', 'x/wordpress', 'x/drive', 'edit/' + id];
  const report = {};
  for (const p of pages) {
    const res = await page.goto(base + '/gestion/' + p);
    await page.waitForTimeout(300);
    await page.addScriptTag({ content: axe });
    const r = await page.evaluate(async () => (await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] })).violations
      .map((v) => ({ id: v.id, impact: v.impact, n: v.nodes.length, help: v.help, target: v.nodes.slice(0, 3).map((x) => x.target.join(' ')) })));
    report[p || 'dashboard'] = { status: res.status(), violations: r };
    await page.screenshot({ path: 'shot-' + (p || 'dashboard').replace(/\//g, '_') + '.png', fullPage: true });
  }
  // Éditeur : le bloc conservé est-il affiché et expliqué ?
  await page.goto(base + '/gestion/edit/' + id);
  await page.waitForTimeout(800);
  report.preserved = await page.evaluate(() => document.body.innerText.includes('boutique-x/carte') && document.body.innerText.includes('conservé'));
  // Clavier : le premier Tab mène au lien d'évitement, visible au focus.
  await page.goto(base + '/gestion/extensions');
  await page.keyboard.press('Tab');
  report.firstTab = await page.evaluate(() => { const a = document.activeElement; return a ? (a.textContent || '').trim().slice(0, 40) + ' | ' + getComputedStyle(a).outlineStyle + ' ' + a.getBoundingClientRect().width : ''; });
  // Téléphone (390 px) : pas de défilement horizontal.
  await page.setViewportSize({ width: 390, height: 844 });
  report.mobileOverflow = {};
  for (const p of ['extensions', 'themes', 'x/wordpress', 'x/planning']) {
    await page.goto(base + '/gestion/' + p);
    report.mobileOverflow[p] = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    await page.screenshot({ path: 'mobile-' + p.replace(/\//g, '_') + '.png' });
  }
  report.jsErrors = errors;
  console.log(JSON.stringify(report, null, 1));
  await browser.close();
})();
