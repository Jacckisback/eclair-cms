#!/usr/bin/env node
/**
 * thierry-skill — regarder le rendu.
 * Capture une page à plusieurs largeurs et mesure ce que l'œil rate.
 *
 * Usage : node regard.mjs <url-ou-fichier.html> [options]
 *   --out <dossier>        dossier de sortie (défaut : ./regard)
 *   --largeurs 390,768,1440  largeurs d'écran à capturer
 *   --attente <ms>         attente après chargement (défaut : 800)
 *   --sombre               émule le thème sombre du système
 *   --dpr <n>              densité de pixels des captures (défaut : 1)
 *   --hauteur <px>         hauteur d'écran imposée (ex. 640 pour tester un petit téléphone)
 *
 * Produit par largeur : <l>-ecran.png (premier écran), <l>-page.png (page entière),
 * <l>-gris.png (niveaux de gris), <l>-flou.png (yeux plissés), plus mesures.json.
 * Une page longue est aussi découpée en tranches lisibles : <l>-tranche-1.png, -2, …
 *
 * Nécessite Playwright avec Chromium (npm i -D playwright && npx playwright install chromium
 * si absent). Les mesures sont des indices à vérifier à l'œil, pas des verdicts.
 */
import { mkdirSync, writeFileSync, existsSync } from "node:fs";
import { resolve, join } from "node:path";
import { pathToFileURL } from "node:url";
import { createRequire } from "node:module";

const args = process.argv.slice(2);
const option = (nom, defaut) => {
  const i = args.indexOf(`--${nom}`);
  return i >= 0 && args[i + 1] && !args[i + 1].startsWith("--") ? args[i + 1] : defaut;
};
const valeursOptions = new Set(["out", "largeurs", "attente", "dpr", "hauteur"].map((n) => option(n, null)).filter(Boolean));
const cible = args.find((a) => !a.startsWith("--") && !valeursOptions.has(a));

if (!cible) {
  console.error("Usage : node regard.mjs <url-ou-fichier.html> [--out dossier] [--largeurs 390,768,1440] [--attente 800] [--sombre] [--dpr 1]");
  process.exit(2);
}

const sortie = resolve(option("out", "regard"));
const largeurs = option("largeurs", "390,768,1440").split(",").map((n) => parseInt(n, 10)).filter(Boolean);
const attente = parseInt(option("attente", "800"), 10);
const dpr = parseFloat(option("dpr", "1"));
const sombre = args.includes("--sombre");

let url = cible;
if (!/^https?:\/\//.test(cible)) {
  const chemin = resolve(cible);
  if (!existsSync(chemin)) {
    console.error(`Fichier introuvable : ${chemin}`);
    process.exit(2);
  }
  url = pathToFileURL(chemin).href;
}

let chromium;
try {
  const require = createRequire(import.meta.url);
  ({ chromium } = require("playwright"));
} catch {
  try {
    ({ chromium } = await import("playwright"));
  } catch {
    console.error("Playwright est introuvable. Installer : npm i -D playwright && npx playwright install chromium");
    process.exit(2);
  }
}

mkdirSync(sortie, { recursive: true });

const hauteurImposee = parseInt(option("hauteur", "0"), 10);
const hauteurPour = (l) => hauteurImposee || (l < 600 ? 844 : l < 1100 ? 1024 : 900);

/** Mesures exécutées dans la page. */
function mesurer() {
  const L = window.innerWidth;
  const toile = document.createElement("canvas");
  toile.width = toile.height = 1;
  const ctx = toile.getContext("2d", { willReadFrequently: true });
  const cache = new Map();
  const rgba = (couleur) => {
    if (cache.has(couleur)) return cache.get(couleur);
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = "rgba(0,0,0,0)";
    ctx.fillStyle = couleur;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    const r = [d[0], d[1], d[2], d[3] / 255];
    cache.set(couleur, r);
    return r;
  };
  const poser = (dessus, dessous) => {
    const a = dessus[3];
    return [0, 1, 2].map((i) => dessus[i] * a + dessous[i] * (1 - a)).concat(1);
  };
  const luminance = (c) => {
    const [r, g, b] = c.slice(0, 3).map((v) => {
      const s = v / 255;
      return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const rapport = (a, b) => {
    const la = luminance(a), lb = luminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  };
  const hex = (c) => "#" + c.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, "0")).join("");
  const visible = (el, style, rect) =>
    rect.width > 0 && rect.height > 0 && style.visibility !== "hidden" && style.display !== "none" && parseFloat(style.opacity) > 0.05;
  const decrire = (el) => {
    let s = el.tagName.toLowerCase();
    if (el.id) s += `#${el.id}`;
    else if (typeof el.className === "string" && el.className.trim()) s += "." + el.className.trim().split(/\s+/).slice(0, 2).join(".");
    return s;
  };

  /** Fond effectif : remonte les ancêtres ; null si une image ou un dégradé s'interpose. */
  const fondEffectif = (el) => {
    const couches = [];
    let n = el;
    while (n && n.nodeType === 1) {
      const st = getComputedStyle(n);
      if (st.backgroundImage && st.backgroundImage !== "none") return null;
      const c = rgba(st.backgroundColor);
      if (c[3] > 0) {
        couches.push(c);
        if (c[3] >= 0.999) break;
      }
      n = n.parentElement;
    }
    let fond = sombreSysteme ? [18, 18, 18, 1] : [255, 255, 255, 1];
    for (let i = couches.length - 1; i >= 0; i--) fond = poser(couches[i], fond);
    return fond;
  };
  const sombreSysteme = matchMedia("(prefers-color-scheme: dark)").matches &&
    getComputedStyle(document.documentElement).colorScheme.includes("dark");

  const opaciteCumulee = (el) => {
    let o = 1, n = el;
    while (n && n.nodeType === 1) { o *= parseFloat(getComputedStyle(n).opacity) || 1; n = n.parentElement; }
    return o;
  };

  // ----- Texte : contrastes, tailles, familles -----
  const echecs = new Map();
  let textesMesures = 0, textesSurImage = 0;
  const tailles = new Map(), familles = new Map();
  const tropPetits = [];
  const marcheur = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const vus = new Set();
  while (marcheur.nextNode()) {
    const noeud = marcheur.currentNode;
    const texte = noeud.nodeValue.replace(/\s+/g, " ").trim();
    if (texte.length < 1) continue;
    const el = noeud.parentElement;
    if (!el || vus.has(el) || el.closest("script,style,noscript,template,svg,[aria-hidden='true']")) continue;
    vus.add(el);
    const st = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    if (!visible(el, st, rect) || opaciteCumulee(el) < 0.1) continue;
    const taille = parseFloat(st.fontSize);
    const graisse = parseInt(st.fontWeight, 10) || 400;
    const cle = Math.round(taille * 2) / 2;
    tailles.set(cle, (tailles.get(cle) || 0) + texte.length);
    const famille = st.fontFamily.split(",")[0].trim().replace(/["']/g, "");
    if (!/repli|fallback/i.test(famille)) familles.set(famille, (familles.get(famille) || 0) + texte.length);
    if (taille < 12) tropPetits.push({ element: decrire(el), taille, texte: texte.slice(0, 50) });

    const fond = fondEffectif(el);
    if (!fond) { textesSurImage++; continue; }
    let encre = rgba(st.color);
    encre = [encre[0], encre[1], encre[2], encre[3] * opaciteCumulee(el)];
    const couleur = poser(encre, fond);
    const r = rapport(couleur, fond);
    const grand = taille >= 24 || (taille >= 18.66 && graisse >= 700);
    const seuil = grand ? 3 : 4.5;
    textesMesures++;
    if (r < seuil - 0.05) {
      const id = `${hex(couleur)}|${hex(fond)}|${grand}`;
      const e = echecs.get(id) || { texte: texte.slice(0, 60), element: decrire(el), couleur: hex(couleur), fond: hex(fond), rapport: Math.round(r * 100) / 100, seuil, taille, occurrences: 0 };
      e.occurrences++;
      echecs.set(id, e);
    }
  }

  // ----- Débordement horizontal -----
  const racine = document.documentElement;
  const debordementHorizontal = racine.scrollWidth > L + 1;
  const elementsTropLarges = [];
  if (debordementHorizontal) {
    for (const el of document.body.querySelectorAll("*")) {
      const r = el.getBoundingClientRect();
      if (r.width > 0 && (r.right > L + 1 || r.left < -1) && getComputedStyle(el).position !== "fixed") {
        elementsTropLarges.push({ element: decrire(el), gauche: Math.round(r.left), droite: Math.round(r.right) });
        if (elementsTropLarges.length >= 6) break;
      }
    }
  }

  // ----- Cibles tactiles -----
  const ciblesTropPetites = [];
  let ciblesSous44 = 0, ciblesTotal = 0;
  for (const el of document.querySelectorAll("a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [role=link], [role=tab]")) {
    const st = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    if (!visible(el, st, r)) continue;
    if (el.tagName === "A" && st.display === "inline" && el.parentElement && el.parentElement.textContent.trim().length > el.textContent.trim().length + 20) continue;
    ciblesTotal++;
    // Zone cliquable étendue par l'étiquette associée ou par un pseudo-élément.
    let w = r.width, h = r.height;
    for (const etiquette of el.labels || []) {
      const re = etiquette.getBoundingClientRect();
      w = Math.max(w, re.width); h = Math.max(h, re.height);
    }
    for (const pseudo of ["::before", "::after"]) {
      const ps = getComputedStyle(el, pseudo);
      if (ps.content !== "none" && ps.position === "absolute") {
        const etendre = (v) => (parseFloat(v) < 0 ? -parseFloat(v) : 0);
        w = Math.max(w, r.width + etendre(ps.left) + etendre(ps.right));
        h = Math.max(h, r.height + etendre(ps.top) + etendre(ps.bottom));
      }
    }
    if (w < 24 || h < 24) ciblesTropPetites.push({ element: decrire(el), largeur: Math.round(w), hauteur: Math.round(h), texte: (el.textContent || el.getAttribute("aria-label") || "").trim().slice(0, 40) });
    else if (w < 44 || h < 44) ciblesSous44++;
  }

  // ----- Longueur des lignes -----
  const lignesTropLongues = [];
  for (const el of document.querySelectorAll("p, li, blockquote, dd")) {
    const texte = el.textContent.replace(/\s+/g, " ").trim();
    if (texte.length < 220 || el.querySelector("p, li")) continue;
    const st = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    if (!visible(el, st, r)) continue;
    let interligne = parseFloat(st.lineHeight);
    if (!interligne) interligne = parseFloat(st.fontSize) * 1.2;
    const lignes = Math.max(1, Math.round(r.height / interligne));
    const parLigne = Math.round(texte.length / lignes);
    if (lignes >= 2 && parLigne > 80) lignesTropLongues.push({ element: decrire(el), caracteresParLigne: parLigne, texte: texte.slice(0, 50) });
  }

  // ----- Images -----
  const images = [...document.images].filter((i) => i.getBoundingClientRect().width > 1);
  const imagesSansDimensions = images.filter((i) => {
    if (i.hasAttribute("width") && i.hasAttribute("height")) return false;
    const st = getComputedStyle(i);
    return st.aspectRatio === "auto" && !(st.position === "absolute" && st.inset !== "auto");
  }).map((i) => (i.currentSrc || i.src).split("/").pop().slice(0, 60));
  const imagesSansAlt = images.filter((i) => !i.hasAttribute("alt")).map((i) => (i.currentSrc || i.src).split("/").pop().slice(0, 60));
  const imagesSurdimensionnees = images.filter((i) => i.naturalWidth > 0 && i.naturalWidth > i.getBoundingClientRect().width * devicePixelRatio * 2.2 && i.naturalWidth > 800)
    .map((i) => ({ fichier: (i.currentSrc || i.src).split("/").pop().slice(0, 60), naturelle: i.naturalWidth, affichee: Math.round(i.getBoundingClientRect().width) }));

  // ----- Structure -----
  const titres = [...document.querySelectorAll("h1,h2,h3,h4,h5,h6")].filter((h) => h.getBoundingClientRect().height > 0);
  let sauts = 0, precedent = 0;
  for (const h of titres) {
    const n = +h.tagName[1];
    if (precedent && n > precedent + 1) sauts++;
    precedent = n;
  }
  const champsSansEtiquette = [...document.querySelectorAll("input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=image]), select, textarea")]
    .filter((c) => c.getBoundingClientRect().width > 0 && !(c.labels && c.labels.length) && !c.getAttribute("aria-label") && !c.getAttribute("aria-labelledby") && !c.getAttribute("title"))
    .map(decrire);
  const boutonsSansNom = [...document.querySelectorAll("button, a[href], [role=button]")]
    .filter((b) => b.getBoundingClientRect().width > 0 && !(b.textContent || "").trim() && !b.getAttribute("aria-label") && !b.getAttribute("aria-labelledby") && !b.getAttribute("title") && !b.querySelector("img[alt]:not([alt=''])"))
    .map(decrire);
  const reperes = { header: !!document.querySelector("header"), nav: !!document.querySelector("nav"), main: !!document.querySelector("main"), footer: !!document.querySelector("footer") };
  const description = document.querySelector('meta[name="description"]');
  const champsPetitCorps = [...document.querySelectorAll("input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea")]
    .filter((c) => c.getBoundingClientRect().width > 0 && parseFloat(getComputedStyle(c).fontSize) < 16).length;

  const trier = (m) => [...m.entries()].sort((a, b) => b[1] - a[1]);
  const policesNonChargees = trier(familles).map(([f]) => f)
    .filter((f) => !/^(?:serif|sans-serif|monospace|system-ui|ui-|-apple|BlinkMac|Segoe|Arial|Helvetica|Georgia|Times|Courier|Menlo|Consolas)/i.test(f))
    .filter((f) => { try { return !document.fonts.check(`16px "${f}"`); } catch { return false; } });

  return {
    debordementHorizontal,
    elementsTropLarges,
    contrastes: { textesMesures, textesSurImageNonMesures: textesSurImage, echecs: [...echecs.values()].sort((a, b) => a.rapport - b.rapport).slice(0, 15) },
    ciblesTropPetites: ciblesTropPetites.slice(0, 12),
    ciblesEntre24Et44: ciblesSous44,
    ciblesTotal,
    famillesDePolices: trier(familles).map(([f, n]) => ({ famille: f, caracteres: n })),
    policesNonChargees,
    taillesDeTexte: trier(tailles).map(([t]) => t).sort((a, b) => a - b),
    texteTropPetit: tropPetits.slice(0, 8),
    lignesTropLongues: lignesTropLongues.slice(0, 8),
    imagesSansDimensions: imagesSansDimensions.slice(0, 12),
    imagesSansAlt: imagesSansAlt.slice(0, 12),
    imagesSurdimensionnees: imagesSurdimensionnees.slice(0, 8),
    titres: { h1: titres.filter((h) => h.tagName === "H1").length, total: titres.length, sauts },
    champsSansEtiquette,
    champsSousSeizePixels: champsPetitCorps,
    boutonsSansNom: boutonsSansNom.slice(0, 8),
    reperes,
    langue: document.documentElement.getAttribute("lang") || null,
    titrePage: { texte: document.title, longueur: document.title.length },
    description: description ? { longueur: (description.content || "").length } : null,
    hauteurPage: Math.round(racine.scrollHeight),
    clsApprox: Math.round((window.__regard?.cls || 0) * 1000) / 1000,
    lcpMs: window.__regard?.lcp ? Math.round(window.__regard.lcp) : null,
  };
}

/** Fait défiler toute la page pour déclencher les apparitions et les images différées. */
async function parcourir(page) {
  await page.evaluate(async () => {
    const pause = (ms) => new Promise((r) => setTimeout(r, ms));
    const pas = Math.round(window.innerHeight * 0.7);
    for (let y = 0; y < document.documentElement.scrollHeight; y += pas) {
      window.scrollTo(0, y);
      await pause(110);
    }
    window.scrollTo(0, document.documentElement.scrollHeight);
    await pause(200);
    window.scrollTo(0, 0);
    await pause(350);
  });
}

async function filtrer(navigateur, image, filtre, destination, largeur) {
  const page = await navigateur.newPage({ viewport: { width: largeur, height: 600 }, deviceScaleFactor: 1 });
  await page.setContent(
    `<body style="margin:0;background:#fff"><div id="i" style="width:${largeur}px;overflow:hidden"><img src="data:image/png;base64,${image.toString("base64")}" style="display:block;width:100%;filter:${filtre};transform:scale(${filtre.startsWith("blur") ? 1.06 : 1})"></div></body>`,
  );
  await page.locator("#i").screenshot({ path: destination });
  await page.close();
}

const navigateur = await chromium.launch();
const rapport = { url, date: new Date().toISOString(), theme: sombre ? "sombre" : "clair", largeurs: {} };
const alertes = [];

try {
  for (const largeur of largeurs) {
    const contexte = await navigateur.newContext({
      viewport: { width: largeur, height: hauteurPour(largeur) },
      deviceScaleFactor: dpr,
      colorScheme: sombre ? "dark" : "light",
      isMobile: largeur < 600,
      hasTouch: largeur < 600,
    });
    await contexte.addInitScript(() => {
      window.__regard = { cls: 0, lcp: 0 };
      try {
        new PerformanceObserver((liste) => {
          for (const e of liste.getEntries()) if (!e.hadRecentInput) window.__regard.cls += e.value;
        }).observe({ type: "layout-shift", buffered: true });
        new PerformanceObserver((liste) => {
          const e = liste.getEntries().at(-1);
          if (e) window.__regard.lcp = e.startTime;
        }).observe({ type: "largest-contentful-paint", buffered: true });
      } catch {}
    });
    const page = await contexte.newPage();
    const erreursConsole = [];
    const poids = { total: 0, script: 0, stylesheet: 0, font: 0, image: 0, requetes: 0 };
    const enAttente = [];
    page.on("console", (m) => { if (m.type() === "error") erreursConsole.push(m.text().slice(0, 160)); });
    page.on("pageerror", (e) => erreursConsole.push(String(e).slice(0, 160)));
    page.on("requestfinished", (requete) => {
      enAttente.push((async () => {
        try {
          const t = await requete.sizes();
          const octets = t.responseBodySize + t.responseHeadersSize;
          const type = requete.resourceType();
          poids.total += octets;
          poids.requetes++;
          if (type in poids) poids[type] += octets;
        } catch {}
      })());
    });

    try {
      await page.goto(url, { waitUntil: "networkidle", timeout: 45000 });
    } catch {
      await page.goto(url, { waitUntil: "load", timeout: 45000 }).catch(() => {});
    }
    await page.evaluate(() => document.fonts.ready).catch(() => {});
    await page.waitForTimeout(attente);

    const ecran = await page.screenshot({ path: join(sortie, `${largeur}-ecran.png`) });
    await filtrer(navigateur, ecran, "grayscale(1)", join(sortie, `${largeur}-gris.png`), largeur * dpr);
    await filtrer(navigateur, ecran, `blur(${Math.max(4, Math.round((largeur * dpr) / 110))}px)`, join(sortie, `${largeur}-flou.png`), largeur * dpr);

    await parcourir(page);
    await page.screenshot({ path: join(sortie, `${largeur}-page.png`), fullPage: true });

    // Tranches lisibles quand la page est longue (une image très haute est réduite à l'ouverture).
    const hEcran = hauteurPour(largeur);
    const hPage = await page.evaluate(() => document.documentElement.scrollHeight);
    let tranches = 0;
    if (hPage > hEcran * 2.5) {
      const pas = Math.round(hEcran * 1.6);
      for (let y = 0; y < hPage && tranches < 12; y += pas) {
        tranches++;
        await page.screenshot({ path: join(sortie, `${largeur}-tranche-${tranches}.png`), fullPage: true, clip: { x: 0, y, width: largeur, height: Math.min(pas, hPage - y) } });
      }
    }

    const mesures = await page.evaluate(mesurer);
    mesures.tranches = tranches;
    await Promise.all(enAttente);
    const ko = (n) => Math.round(n / 102.4) / 10;
    mesures.poidsPage = url.startsWith("file:") ? null : {
      totalKo: ko(poids.total), scriptsKo: ko(poids.script), stylesKo: ko(poids.stylesheet),
      policesKo: ko(poids.font), imagesKo: ko(poids.image), requetes: poids.requetes,
    };
    mesures.erreursConsole = [...new Set(erreursConsole)].slice(0, 8);
    rapport.largeurs[largeur] = mesures;

    const a = (texte) => alertes.push(`${largeur} px · ${texte}`);
    if (mesures.debordementHorizontal) a(`débordement horizontal (${mesures.elementsTropLarges.map((e) => e.element).slice(0, 3).join(", ") || "élément à chercher"})`);
    if (mesures.contrastes.echecs.length) a(`${mesures.contrastes.echecs.length} paire(s) de couleurs sous le seuil de contraste, la pire à ${mesures.contrastes.echecs[0].rapport}:1 (« ${mesures.contrastes.echecs[0].texte} »)`);
    if (mesures.ciblesTropPetites.length) a(`${mesures.ciblesTropPetites.length} cible(s) cliquable(s) sous 24 px`);
    if (largeur < 600 && mesures.ciblesEntre24Et44 > 0) a(`${mesures.ciblesEntre24Et44} cible(s) entre 24 et 44 px sur mobile`);
    if (mesures.famillesDePolices.length > 3) a(`${mesures.famillesDePolices.length} familles de polices à l'écran`);
    if (mesures.policesNonChargees.length) a(`police(s) déclarée(s) mais non chargée(s) : ${mesures.policesNonChargees.join(", ")}`);
    if (mesures.taillesDeTexte.length > 8) a(`${mesures.taillesDeTexte.length} tailles de texte différentes (${mesures.taillesDeTexte.join(", ")})`);
    if (mesures.lignesTropLongues.length) a(`${mesures.lignesTropLongues.length} bloc(s) de texte au-delà de 80 caractères par ligne`);
    if (mesures.texteTropPetit.length) a(`${mesures.texteTropPetit.length} texte(s) sous 12 px`);
    if (mesures.imagesSansDimensions.length) a(`${mesures.imagesSansDimensions.length} image(s) sans dimensions`);
    if (mesures.imagesSansAlt.length) a(`${mesures.imagesSansAlt.length} image(s) sans attribut alt`);
    if (mesures.titres.h1 !== 1) a(`${mesures.titres.h1} élément(s) h1 (un seul attendu)`);
    if (mesures.titres.sauts) a(`${mesures.titres.sauts} saut(s) de niveau de titre`);
    if (mesures.champsSansEtiquette.length) a(`${mesures.champsSansEtiquette.length} champ(s) sans étiquette`);
    if (largeur < 600 && mesures.champsSousSeizePixels) a(`${mesures.champsSousSeizePixels} champ(s) sous 16 px (zoom d'iOS)`);
    if (mesures.boutonsSansNom.length) a(`${mesures.boutonsSansNom.length} bouton(s) ou lien(s) sans nom accessible`);
    if (!mesures.langue) a("attribut lang absent sur <html>");
    if (!mesures.reperes.main) a("pas d'élément <main>");
    if (mesures.clsApprox > 0.1) a(`décalage de mise en page ${mesures.clsApprox} (seuil 0,1)`);
    if (mesures.erreursConsole.length) a(`${mesures.erreursConsole.length} erreur(s) dans la console`);

    await contexte.close();
  }

  // Mouvement réduit : que reste-t-il d'animé ?
  {
    const l = largeurs.at(-1);
    const contexte = await navigateur.newContext({ viewport: { width: l, height: hauteurPour(l) }, reducedMotion: "reduce", colorScheme: sombre ? "dark" : "light" });
    const page = await contexte.newPage();
    await page.goto(url, { waitUntil: "load", timeout: 45000 }).catch(() => {});
    await page.waitForTimeout(Math.max(attente, 600));
    const bilan = await page.evaluate(() => {
      const actives = document.getAnimations().filter((a) => {
        if (a.playState !== "running") return false;
        const t = a.effect?.getComputedTiming?.() || {};
        return t.iterations === Infinity || (typeof t.duration === "number" && t.duration > 200);
      });
      const invisibles = [...document.querySelectorAll("main *, body > section *")].filter((el) => {
        if (!el.textContent.trim() || el.children.length > 3) return false;
        const st = getComputedStyle(el);
        return parseFloat(st.opacity) < 0.05 && el.getBoundingClientRect().height > 0 && !el.closest("[aria-hidden='true'], [hidden], dialog, [role=dialog], [role=tooltip], [role=menu]");
      });
      return {
        animationsActives: actives.length,
        exemples: actives.slice(0, 5).map((a) => (a.effect?.target?.tagName || "?").toLowerCase() + (a.animationName ? `:${a.animationName}` : "")),
        contenusRestesInvisibles: invisibles.length,
      };
    }).catch(() => null);
    rapport.mouvementReduit = bilan;
    if (bilan?.animationsActives) alertes.push(`mouvement réduit · ${bilan.animationsActives} animation(s) encore active(s) (${bilan.exemples.join(", ")})`);
    if (bilan?.contenusRestesInvisibles) alertes.push(`mouvement réduit · ${bilan.contenusRestesInvisibles} élément(s) de contenu restés invisibles`);
    await contexte.close();
  }
} finally {
  await navigateur.close();
}

rapport.alertes = alertes;
writeFileSync(join(sortie, "mesures.json"), JSON.stringify(rapport, null, 2));

console.log(`\nthierry-skill · regard — ${url}`);
console.log(`Captures et mesures : ${sortie}`);
for (const l of largeurs) console.log(`  ${l}-ecran.png · ${l}-page.png · ${l}-gris.png · ${l}-flou.png${rapport.largeurs[l]?.tranches ? ` · ${l}-tranche-1…${rapport.largeurs[l].tranches}.png` : ""}`);
if (url.startsWith("file:")) console.log("  (fichier local : poids de page non mesuré)");
console.log("  mesures.json\n");
if (alertes.length) {
  console.log(`${alertes.length} alerte(s) à traiter ou à justifier :`);
  for (const a of alertes) console.log(`  - ${a}`);
} else {
  console.log("Aucune alerte mesurable.");
}
console.log("\nÀ faire maintenant : ouvrir chaque capture et passer les cinq tests visuels (references/10-revue.md).\n");
