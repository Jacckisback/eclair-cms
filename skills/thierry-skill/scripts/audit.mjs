#!/usr/bin/env node
/**
 * thierry-skill — audit statique du code.
 * Repère les tics génériques et les fautes d'exécution dans les fichiers de style et de gabarit.
 *
 * Usage : node audit.mjs <dossier-ou-fichier> [--json] [--tout]
 *   --json  sortie JSON
 *   --tout  affiche toutes les occurrences (par défaut : 8 par règle)
 *
 * Les résultats sont des indices à vérifier, pas des verdicts : une règle déclenchée
 * peut être un choix justifié. Aucune dépendance.
 */
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, extname, relative, resolve } from "node:path";

const args = process.argv.slice(2);
const cible = args.find((a) => !a.startsWith("--"));
const enJson = args.includes("--json");
const tout = args.includes("--tout");

if (!cible) {
  console.error("Usage : node audit.mjs <dossier-ou-fichier> [--json] [--tout]");
  process.exit(2);
}

const EXTENSIONS = new Set([
  ".css", ".scss", ".sass", ".less", ".html", ".htm", ".jsx", ".tsx", ".js", ".ts",
  ".mjs", ".vue", ".svelte", ".astro", ".mdx", ".php", ".liquid", ".njk", ".hbs",
]);
const IGNORES = new Set([
  "node_modules", ".git", ".next", "dist", "build", "out", ".output", ".nuxt", ".svelte-kit",
  ".vercel", ".netlify", "coverage", "vendor", ".cache", ".turbo", "public",
]);
const TAILLE_MAX = 600_000;

// Extensions où l'on cherche du texte affiché (et pas seulement du style).
const GABARITS = new Set([".html", ".htm", ".jsx", ".tsx", ".vue", ".svelte", ".astro", ".mdx", ".php", ".liquid", ".njk", ".hbs"]);

/**
 * Chaque règle : id, gravité (A casse / B affaiblit / C finition), famille, motif, conseil.
 * `seulement` restreint aux gabarits ; `filtre(ligne, fichier)` écarte les faux positifs.
 */
const REGLES = [
  // ---------- Mouvement ----------
  { id: "transition-all", g: "B", f: "Mouvement",
    re: /transition(?:-property)?\s*:\s*all\b|\btransition-all\b/,
    conseil: "Nommer les propriétés animées (transform, opacity, background-color…)." },
  { id: "scale-zero", g: "B", f: "Mouvement",
    re: /scale\(\s*0\s*\)|\bscale\s*:\s*0\s*[;,}]|\bscale-0\b|scale:\s*0\s*[,}]/,
    conseil: "Entrer depuis scale(0.94–0.98) avec opacity 0 : rien n'apparaît depuis un point." },
  { id: "ease-in", g: "B", f: "Mouvement",
    re: /(?:transition|animation)[^;{}\n]*[\s,]ease-in(?!-out)\b|timing-function\s*:\s*ease-in(?!-out)\b|\bease-in(?!-out)\b(?=["'\s`])/,
    filtre: (l) => !/ease-in-out/.test(l) || /ease-in(?!-out)/.test(l.replace(/ease-in-out/g, "")),
    conseil: "Courbe de décélération pour une apparition ; l'accélération est réservée à ce qui quitte l'écran." },
  { id: "courbe-par-defaut", g: "C", f: "Mouvement",
    re: /(?:transition|animation)\s*:[^;{}\n]*\b(?:ease|ease-out|ease-in-out)\b(?!\s*\))/,
    filtre: (l) => !/var\(--/.test(l),
    conseil: "Les mots-clés ease/ease-out sont mous : utiliser les courbes du système (var(--c-sortie)…)." },
  { id: "animation-de-mise-en-page", g: "B", f: "Mouvement",
    re: /transition(?:-property)?\s*:[^;{}\n]*\b(?:width|height|top|left|right|bottom|margin|padding)\b|\btransition-\[(?:width|height|top|left|margin|padding)/,
    conseil: "Animer transform, opacity, clip-path ou grid-template-rows plutôt que la mise en page." },
  { id: "duree-longue", g: "C", f: "Mouvement",
    re: /transition(?:-duration)?\s*:[^;{}\n]*?\b(?:[1-9]\d{3,}ms|(?:[1-9]|\d\.\d*[1-9]\d*)s)\b|\bduration-(?:1000|\[\d{4,}ms\])/,
    filtre: (l) => !/0\.\d+s/.test(l) || /\b[1-9](?:\.\d+)?s\b/.test(l),
    conseil: "Une transition d'interface au-delà d'une seconde fait attendre : la réserver à un moment rare." },
  { id: "survol-sans-garde", g: "C", f: "Mouvement",
    re: /:hover\s*\{[^}]*transform|:hover[^{]*\{\s*$/,
    seulementCss: true,
    filtre: (l, ctx) => !ctx.contenu.includes("hover: hover"),
    conseil: "Placer les effets de survol derrière @media (hover: hover) and (pointer: fine)." },
  { id: "pas-de-mouvement-reduit", g: "A", f: "Mouvement", global: true,
    test: (projet) => projet.aDesAnimations && !projet.aMouvementReduit,
    conseil: "Le projet anime sans jamais consulter prefers-reduced-motion (ni useReducedMotion / MotionConfig)." },
  { id: "defilement-detourne", g: "B", f: "Mouvement",
    re: /\b(?:lenis|locomotive-scroll|smooth-scrollbar|fullpage\.js|scroll-jacking)\b/i,
    conseil: "Le défilement lissé ou détourné par script retire le contrôle au visiteur : défilement natif." },
  { id: "curseur-personnalise", g: "C", f: "Mouvement",
    re: /cursor\s*:\s*none\b|\bcursor-none\b/,
    conseil: "Un curseur remplacé ralentit le pointeur et gêne l'accessibilité : garder le pointeur natif." },

  // ---------- Accessibilité ----------
  { id: "outline-supprime", g: "A", f: "Accessibilité",
    re: /outline\s*:\s*(?:none|0)\b|\boutline-none\b/,
    filtre: (l, ctx) => !/focus-visible|:focus:not\(:focus-visible\)|focus-visible:|focus:ring|focus:border|focus-within:ring/.test(ctx.fenetre),
    conseil: "Ne jamais retirer l'anneau de focus sans le remplacer (:focus-visible avec un contour visible)." },
  { id: "zoom-bloque", g: "A", f: "Accessibilité",
    re: /user-scalable\s*=\s*(?:no|0)|maximum-scale\s*=\s*1(?:\.0)?\b/,
    conseil: "Ne pas bloquer le zoom : régler plutôt les champs à 16 px pour éviter le zoom d'iOS." },
  { id: "div-cliquable", g: "B", f: "Accessibilité", seulement: true,
    re: /<(?:div|span)\b[^>]*\bonClick=|<(?:div|span)\b[^>]*\bonclick=/,
    filtre: (l) => !/role=["'](?:button|link|tab|menuitem)/.test(l),
    conseil: "Utiliser <button> ou <a> : un div cliquable n'est ni focalisable ni annoncé." },
  { id: "image-sans-alt", g: "B", f: "Accessibilité", seulement: true,
    re: /<img\b(?![^>]*\balt\s*=)[^>]*>/,
    conseil: "Ajouter alt (vide si l'image est décorative)." },
  { id: "tabindex-positif", g: "B", f: "Accessibilité", seulement: true,
    re: /tab[iI]ndex\s*=\s*[{"']?\s*[1-9]/,
    conseil: "Pas de tabindex positif : l'ordre de tabulation suit l'ordre du document." },
  { id: "100vh", g: "C", f: "Mobile",
    re: /(?:height|min-height)\s*:\s*100vh\b|\b(?:min-)?h-screen\b/,
    conseil: "100vh déborde sous la barre du navigateur mobile : utiliser 100svh ou 100dvh." },

  // ---------- Couleur ----------
  { id: "noir-ou-blanc-pur", g: "C", f: "Couleur",
    re: /(?:color|background(?:-color)?)\s*:\s*(?:#000(?:000)?|#fff(?:fff)?|black|white)\s*[;}]/i,
    conseil: "Encre et papier teintés (oklch avec un peu de chroma) plutôt que noir et blanc purs." },
  { id: "degrade-violet", g: "B", f: "Couleur",
    re: /from-(?:purple|violet|indigo|fuchsia)-\d+|to-(?:purple|violet|indigo|fuchsia|pink)-\d+|gradient[^;\n]*(?:#(?:6366f1|7c3aed|8b5cf6|a855f7|9333ea|4f46e5|c026d3|d946ef)\b|purple|violet|indigo)/i,
    conseil: "Le dégradé violet-indigo est le premier signe du gabarit : une couleur tirée du sujet." },
  { id: "texte-en-degrade", g: "B", f: "Couleur",
    re: /background-clip\s*:\s*text|\bbg-clip-text\b/,
    conseil: "Le mot en dégradé est un tic : italique, autre police, ou rien." },
  { id: "verre-depoli", g: "C", f: "Couleur",
    re: /backdrop-filter\s*:\s*blur|\bbackdrop-blur(?:-\w+)?\b/,
    conseil: "Flou d'arrière-plan : justifié pour une barre sur un contenu qui défile, pas comme décor de cartes." },
  { id: "halo-flou", g: "C", f: "Couleur",
    re: /\bblur-(?:2xl|3xl|\[\d{2,}px\])|filter\s*:\s*blur\(\s*(?:[4-9]\d|\d{3,})px/,
    conseil: "Halos colorés flous en fond : tic répandu et coûteux à peindre." },
  { id: "couleurs-en-dur", g: "C", f: "Couleur", global: true,
    test: (projet) => projet.couleursEnDur > 40 && projet.variablesCss < projet.couleursEnDur / 4,
    conseil: (projet) => `${projet.couleursEnDur} couleurs écrites en dur pour ${projet.variablesCss} usages de variables : poser la palette en variables par rôle.` },

  // ---------- Typographie ----------
  { id: "police-generique-en-titre", g: "B", f: "Typographie",
    re: /font-family\s*:\s*["']?(?:Inter|Roboto|Poppins|Montserrat|Open Sans|Lato|Arial|Helvetica)\b|(?:Inter|Roboto|Poppins|Montserrat|Open_Sans|Lato)\s*\(\s*\{|family=(?:Inter|Roboto|Poppins|Montserrat|Open\+Sans|Lato)\b/,
    conseil: "Police très répandue : acceptable en texte courant, à remplacer en titre par une police choisie pour le sujet." },
  { id: "trop-de-polices", g: "B", f: "Typographie", global: true,
    test: (projet) => projet.familles.size > 3,
    conseil: (projet) => `${projet.familles.size} familles de polices déclarées (${[...projet.familles].slice(0, 6).join(", ")}) : deux, trois avec une monospace.` },
  { id: "texte-justifie", g: "C", f: "Typographie",
    re: /text-align\s*:\s*justify|\btext-justify\b/,
    conseil: "Le justifié crée des rivières à l'écran : aligner à gauche." },
  { id: "capitales-non-espacees", g: "C", f: "Typographie",
    re: /text-transform\s*:\s*uppercase/, seulementCss: true,
    filtre: (l, ctx) => !/letter-spacing/.test(ctx.bloc),
    conseil: "Des capitales sans interlettrage paraissent serrées : letter-spacing 0.06em ou plus." },
  { id: "faux-texte", g: "A", f: "Contenu", seulement: true,
    re: /lorem ipsum|dolor sit amet/i,
    conseil: "Texte de remplissage : écrire le vrai texte ou marquer l'emplacement « à fournir »." },
  { id: "libelle-vague", g: "C", f: "Contenu", seulement: true,
    re: />\s*(?:En savoir plus|Learn more|Click here|Cliquez ici|Read more|Lire la suite|Saber más|Leer más|Mehr erfahren)\s*</i,
    conseil: "Libellé qui ne dit pas ce qui arrive après le clic : nommer le résultat." },
  { id: "slogan-creux", g: "C", f: "Contenu", seulement: true,
    re: /\b(?:Boostez|Révolutionnez|Libérez (?:votre|le) potentiel|niveau supérieur|tout-en-un|Unlock your|Supercharge|next level|all-in-one|Bienvenue sur notre site|Welcome to our website|Potencia tu|Impulsa tu)\b/i,
    conseil: "Formule interchangeable : dire ce que ça fait, pour qui, avec un fait." },
  { id: "ponctuation-francaise", g: "C", f: "Contenu", seulement: true, langue: "fr",
    re: /[a-zàâçéèêëîïôûùüÿœ»)] [;!?](?=[\s<"'`]|$)|[a-zàâçéèêëîïôûùüÿœ] :(?=\s[^/])|« |[^\s] »/i,
    filtre: (l) => /[àâçéèêëîïôûùüÿœ]|(?:\b(?:le|la|les|des|une|est|pour|avec|vous|nous)\b)/i.test(l) && !/^\s*(?:\/\/|\/\*|\*|<!--|import |export |const |let |return |if |\{)/.test(l) && !/\?\s*[^:]+\s:/.test(l),
    conseil: "Espace ordinaire avant ; : ! ? ou dans « » : utiliser l'insécable (U+202F / U+00A0)." },
  { id: "emoji-dans-interface", g: "C", f: "Contenu", seulement: true,
    re: /[\u{1F300}-\u{1FAFF}\u{2728}\u{2B50}\u{26A1}\u{1F680}]/u,
    conseil: "Emoji utilisé comme icône ou décor : une famille d'icônes au trait, ou des mots." },

  // ---------- Composition et technique ----------
  { id: "image-sans-dimensions", g: "B", f: "Performance", seulement: true,
    re: /<img\b(?![^>]*\bwidth\s*=)[^>]*>/,
    filtre: (l) => !/\bfill\b/.test(l),
    conseil: "Ajouter width et height (ou aspect-ratio) pour éviter les sauts de mise en page." },
  { id: "important-abusif", g: "C", f: "Technique", global: true,
    test: (projet) => projet.important > 25,
    conseil: (projet) => `${projet.important} déclarations !important : signe d'une cascade mal tenue.` },
  { id: "z-index-extreme", g: "C", f: "Technique",
    re: /z-index\s*:\s*\d{4,}|\bz-\[\d{4,}\]/,
    conseil: "Poser une échelle de plans (5 niveaux nommés) plutôt que des z-index arbitraires." },
  { id: "restes", g: "C", f: "Technique", seulement: true,
    re: /href\s*=\s*["']#["']|\bTODO\b|\bFIXME\b/,
    conseil: "Lien vide ou note de travail restée dans le code." },
];

// ---------- Parcours des fichiers ----------
const racine = resolve(cible);
const fichiers = [];
(function parcourir(chemin) {
  let info;
  try { info = statSync(chemin); } catch { return; }
  if (info.isDirectory()) {
    for (const nom of readdirSync(chemin)) {
      if (IGNORES.has(nom) || (nom.startsWith(".") && nom !== ".")) continue;
      parcourir(join(chemin, nom));
    }
  } else if (EXTENSIONS.has(extname(chemin)) && info.size <= TAILLE_MAX && !/\.min\.(?:js|css)$/.test(chemin)) {
    fichiers.push(chemin);
  }
})(racine);

if (!fichiers.length) {
  console.error(`Aucun fichier analysable dans ${cible}`);
  process.exit(2);
}

const projet = {
  aDesAnimations: false, aMouvementReduit: false, couleursEnDur: 0, variablesCss: 0,
  important: 0, familles: new Set(), francais: false,
};
const constats = new Map(); // id → { regle, occurrences: [] }

const estCss = (ext) => [".css", ".scss", ".sass", ".less"].includes(ext);
// Fichiers de gabarit qui portent souvent leur CSS dans une balise <style>.
const porteDuStyle = (ext, contenu) => [".html", ".htm", ".vue", ".svelte", ".astro"].includes(ext) && /<style[\s>]/.test(contenu);
const sourceRelative = (f) => relative(statSync(racine).isDirectory() ? racine : resolve(racine, ".."), f) || f;

const contenus = fichiers.map((f) => ({ f, ext: extname(f), contenu: readFileSync(f, "utf8") }));

// Passe 1 : faits globaux.
for (const { ext, contenu } of contenus) {
  if (/@keyframes|transition\s*:|animation\s*:|framer-motion|from ["']motion|gsap|\banimate-\w+/.test(contenu)) projet.aDesAnimations = true;
  if (/prefers-reduced-motion|useReducedMotion|reducedMotion|motion-reduce:|motion-safe:/.test(contenu)) projet.aMouvementReduit = true;
  if (/lang=["']fr|locale:\s*["']fr|"fr-FR"/.test(contenu)) projet.francais = true;
  projet.important += (contenu.match(/!important/g) || []).length;
  if (estCss(ext) || GABARITS.has(ext)) {
    projet.couleursEnDur += (contenu.match(/#[0-9a-fA-F]{3,8}\b(?![^{]*\{)|rgba?\(\s*\d/g) || []).length;
    projet.variablesCss += (contenu.match(/var\(--/g) || []).length;
  }
  for (const m of contenu.matchAll(/font-family\s*:\s*([^;}{\n]+)/g)) {
    const premiere = m[1].split(",")[0].trim().replace(/["']/g, "");
    if (premiere && !/^(?:inherit|var\(|initial|unset|ui-|system-ui|sans-serif|serif|monospace|-apple)/.test(premiere) && !/repli|fallback/i.test(premiere)) projet.familles.add(premiere);
  }
  for (const m of contenu.matchAll(/fonts\.googleapis\.com\/css2?\?([^"'\s)]+)/g)) {
    for (const fam of m[1].matchAll(/family=([^:&]+)/g)) projet.familles.add(decodeURIComponent(fam[1]).replace(/\+/g, " "));
  }
  for (const m of contenu.matchAll(/import\s*\{([^}]+)\}\s*from\s*["']next\/font\/google["']/g)) {
    for (const nom of m[1].split(",")) { const n = nom.trim().replace(/_/g, " "); if (n) projet.familles.add(n); }
  }
}

// Passe 2 : règles ligne à ligne.
for (const { f, ext, contenu } of contenus) {
  const lignes = contenu.split("\n");
  const css = estCss(ext) || porteDuStyle(ext, contenu);
  const gabarit = GABARITS.has(ext);
  for (const regle of REGLES) {
    if (regle.global) continue;
    if (regle.seulement && !gabarit) continue;
    if (regle.seulementCss && !css) continue;
    if (regle.langue === "fr" && !projet.francais) continue;
    for (let i = 0; i < lignes.length; i++) {
      const ligne = lignes[i];
      if (ligne.length > 1500 || !regle.re.test(ligne)) continue;
      const ctx = {
        contenu,
        fenetre: lignes.slice(Math.max(0, i - 6), i + 7).join("\n"),
        bloc: lignes.slice(Math.max(0, i - 8), i + 9).join("\n"),
      };
      if (regle.filtre && !regle.filtre(ligne, ctx)) continue;
      if (!constats.has(regle.id)) constats.set(regle.id, { regle, occurrences: [] });
      constats.get(regle.id).occurrences.push({ fichier: sourceRelative(f), ligne: i + 1, extrait: ligne.trim().slice(0, 130) });
    }
  }
}

// Règles globales.
for (const regle of REGLES.filter((r) => r.global)) {
  if (regle.test(projet)) constats.set(regle.id, { regle, occurrences: [] });
}

// ---------- Sortie ----------
const ordre = { A: 0, B: 1, C: 2 };
const liste = [...constats.values()].sort(
  (a, b) => ordre[a.regle.g] - ordre[b.regle.g] || b.occurrences.length - a.occurrences.length,
);
const conseilDe = (r) => (typeof r.conseil === "function" ? r.conseil(projet) : r.conseil);
const compte = { A: 0, B: 0, C: 0 };
for (const c of liste) compte[c.regle.g]++;

if (enJson) {
  console.log(JSON.stringify({
    fichiersAnalyses: fichiers.length,
    resume: compte,
    polices: [...projet.familles],
    constats: liste.map((c) => ({
      id: c.regle.id, priorite: c.regle.g, famille: c.regle.f, conseil: conseilDe(c.regle),
      nombre: c.occurrences.length, occurrences: tout ? c.occurrences : c.occurrences.slice(0, 8),
    })),
  }, null, 2));
} else {
  console.log(`\nthierry-skill · audit du code — ${fichiers.length} fichier${fichiers.length > 1 ? "s analysés" : " analysé"}`);
  console.log(`Polices déclarées : ${projet.familles.size ? [...projet.familles].join(", ") : "aucune repérée"}`);
  console.log(`Constats : ${compte.A} de priorité A · ${compte.B} de priorité B · ${compte.C} de priorité C\n`);
  if (!liste.length) console.log("Aucun tic repéré. Cela ne dit rien de la beauté : regarder le rendu avec regard.mjs.\n");
  for (const c of liste) {
    const n = c.occurrences.length;
    console.log(`[${c.regle.g}] ${c.regle.f} · ${c.regle.id}${n ? ` (${n})` : ""}`);
    console.log(`    → ${conseilDe(c.regle)}`);
    for (const o of tout ? c.occurrences : c.occurrences.slice(0, 8)) {
      console.log(`      ${o.fichier}:${o.ligne}  ${o.extrait}`);
    }
    if (!tout && n > 8) console.log(`      … et ${n - 8} autres (--tout pour tout voir)`);
    console.log("");
  }
  if (liste.length) console.log("Ces constats sont des indices : chacun se corrige ou se justifie par le sujet du site.\n");
}

process.exit(compte.A > 0 ? 1 : 0);
