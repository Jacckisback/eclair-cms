# 13 — Le web moderne au service de la direction

À lire avant d'écrire le CSS d'un site (thème, feuille de signature, CSS personnalisé). Les modèles connaissent surtout le web d'il y a dix ans : ce fichier dit comment obtenir le mouvement, la typographie et la couleur décidés dans la fiche DA avec ce que les navigateurs savent faire **aujourd'hui**, sans JavaScript. Chaque technique indique sa prise en charge : ce qui n'est pas partout s'écrit en **amélioration progressive** (le site reste juste sans elle).

Synthèse adaptée de *Modern Web Guidance* (équipes Chrome, Edge, Firefox et MDN, licence Apache 2.0, github.com/GoogleChrome/modern-web-guidance), données de prise en charge de novembre 2026.

**Sommaire** : 1. Ce qu'Éclair fait déjà · 2. Transitions entre les pages · 3. Animations au défilement · 4. Apparitions et disparitions · 5. Ouvrir à la hauteur naturelle · 6. Typographie · 7. Couleur · 8. Mise en page qui s'adapte · 9. Interactions · 10. Liste de contrôle

## 1. Ce qu'Éclair fait déjà (2.4.0)

Ne le réécris pas, compose avec :

- **Transitions entre les pages** : `@view-transition { navigation: auto }` sous `prefers-reduced-motion: no-preference`, fondu de 0,2 s, l'en-tête (`.site-header`, nom `site-header`) reste en place.
- **Polices sans saut** : `body { font-size-adjust: from-font }` ; pendant le chargement d'une police, le texte de secours prend sa hauteur d'x.
- **Pages instantanées** : règles de chargement anticipé (préchargement au survol, préparation au clic) sur les liens du site.
- **Habillage des titres** : `text-wrap: balance` sur h1–h4, `text-wrap: pretty` sur les paragraphes.

## 2. Transitions entre les pages

Prise en charge : Chrome et Edge 126, Safari 18.2 ; Firefox navigue sans transition (aucune casse).

- Pour qu'un élément **se transforme d'une page à l'autre** au lieu de fondre (le logo, une bannière, la photo d'une carte qui devient la photo de l'article), donne-lui un `view-transition-name` **unique dans la page**. Deux éléments avec le même nom annulent la transition.
- Le fondu par défaut suffit presque toujours. Un geste plus marqué (glissement, rideau, zoom) seulement s'il découle de l'idée du site, et court : 0,2 à 0,35 s.
- Personnalise avec `::view-transition-old(nom)` et `::view-transition-new(nom)`, toujours dans `@media (prefers-reduced-motion: no-preference)`.

```css
@media (prefers-reduced-motion: no-preference) {
  .brand { view-transition-name: marque; }
  ::view-transition-old(root) { animation: .25s cubic-bezier(.2, 0, 0, 1) both page-sort; }
  ::view-transition-new(root) { animation: .3s cubic-bezier(.2, 0, 0, 1) both page-entre; }
  @keyframes page-sort { to { opacity: 0; transform: translateY(-.5rem); } }
  @keyframes page-entre { from { opacity: 0; transform: translateY(.75rem); } }
}
```

## 3. Animations au défilement

Prise en charge : Chrome 115, Safari 26, Firefox 159 (Baseline depuis octobre 2026). Décoratives : pas de solution de repli, pas de bibliothèque JavaScript.

- Une section qui apparaît en entrant dans l'écran : `animation-timeline: view()` et `animation-range` sur l'élément lui-même.
- **Toujours** sous `@supports ((animation-timeline: view()) and (animation-range: entry))` : le second test écarte les navigateurs à prise en charge partielle. Et sous `prefers-reduced-motion: no-preference`.
- Déclare `animation-timeline` et `animation-range` **après** le raccourci `animation`, sinon il les remet à zéro.
- N'anime que `transform` et `opacity`. L'état final est l'état naturel : sans animation, tout est visible.
- Avec parcimonie : une ou deux familles d'éléments par site (les intertitres, les cartes), pas chaque bloc. Un défilement qui fait tout bouger fatigue.

```css
@media (prefers-reduced-motion: no-preference) {
  @supports ((animation-timeline: view()) and (animation-range: entry)) {
    .features .feature {
      animation: apparait linear both;
      animation-timeline: view();
      animation-range: entry 10% cover 30%;
    }
    @keyframes apparait { from { opacity: 0; transform: translateY(1.5rem); } }
  }
}
```

## 4. Apparitions et disparitions

Prise en charge : `@starting-style` et `transition-behavior` dans Chrome 117, Firefox 129, Safari 17.5. La transition de `display` elle-même manque à Firefox (l'élément apparaît alors sans fondu).

- Pour animer l'**ouverture** d'un élément qui passe de `display: none` à visible (menu, panneau, `dialog`, `[popover]`) : l'état de départ dans `@starting-style`, `display` dans la liste des transitions et `transition-behavior: allow-discrete` déclaré **après** le raccourci `transition`.
- Sous `prefers-reduced-motion: reduce`, garde un fondu court sans déplacement.

```css
.panneau { opacity: 1; translate: 0; transition: display .3s, opacity .3s, translate .3s; transition-behavior: allow-discrete; }
@starting-style { .panneau { opacity: 0; translate: 0 -.75rem; } }
.panneau[hidden] { display: none; opacity: 0; translate: 0 -.75rem; }
```

## 5. Ouvrir à la hauteur naturelle

Prise en charge : `interpolate-size` dans Chrome et Edge 129 seulement. Amélioration pure : ailleurs, l'ouverture est instantanée.

- Une FAQ ou un accordéon qui s'ouvre en glissant jusqu'à sa hauteur naturelle : `interpolate-size: allow-keywords` sur `:root`, puis une transition de `block-size` vers `auto`.
- Jamais de hauteur maximale arbitraire (`max-height: 999px`) pour simuler l'effet : la vitesse devient fausse et le contenu long est coupé.

## 6. Typographie

- `text-wrap: balance` pour les titres et ce qui y ressemble (Chrome 114, Firefox 121, Safari 17.5) ; jamais sur un titre posé dans une boîte visible (fond, bordure) : la boîte garde sa largeur et un vide apparaît.
- `text-wrap: pretty` pour les paragraphes longs (Chrome 117, Safari 26 ; Firefox l'ignore). Jamais sur `*` : il a un coût.
- Tailles en `rem` (jamais en `px`), échelles fluides en `clamp(min, rem + vw, max)`, jamais en `vw` seul. Interlignage sans unité.
- Lignes de lecture de 60 à 75 caractères (`max-inline-size: 65ch`).
- Titres en capitales : `font-size-adjust: cap-height from-font` aligne la police de secours sur la hauteur des capitales.
- Pas de hauteur fixe sur ce qui contient du texte (bouton, étiquette, carte) : `min-block-size` si un minimum est nécessaire.
- L'emphase en gras ou en italique, pas en capitales.

## 7. Couleur

- Toute nuance se dérive des variables de la direction : `color-mix(in oklch, var(--accent) 14%, var(--bg))`, ou couleur relative `oklch(from var(--accent) calc(l + .1) c h)` (Chrome 125, Firefox 128, Safari 18).
- Texte posé sur un fond variable : `contrast-color(var(--fond))` choisit le noir ou le blanc lisible (Baseline depuis avril 2026 : Chrome 147, Firefox 146, Safari 26). Seulement pour une couleur de **texte** ou de bordure, jamais pour un fond. Sur Éclair, déclare d'abord `color: var(--on-accent)`, puis `contrast-color()` : un navigateur plus ancien garde la première ligne.
- Respecte le mode « couleurs forcées » (Windows contraste élevé) : pas d'information portée par la seule couleur de fond.

## 8. Mise en page qui s'adapte

- **Requêtes de conteneur** (partout depuis 2023) : un bloc qui change de composition selon la place qu'il a, pas selon l'écran. `container-type: inline-size` sur le parent, puis `@container (min-width: 32rem) { … }`. C'est ce qui rend une carte juste à la fois dans une grille de trois et seule sur mobile.
- **`:has()`** pour styler un parent selon son contenu (une carte qui a une image, une section qui contient un tableau) au lieu d'ajouter des classes.
- **Formulaires** : `:user-invalid` (partout) colore une erreur seulement après que la personne a touché le champ, jamais à l'ouverture de la page.

## 9. Interactions

- Effets de survol derrière `@media (hover: hover) and (pointer: fine)` : un téléphone ne survole pas, et l'effet resterait collé après un toucher.
- Retour d'appui (`:active`) court : 80 à 120 ms, `transform: scale(.97)` au plus.
- Focus visible toujours dessiné (`:focus-visible`), dans la couleur d'accent, jamais retiré.

## 10. Liste de contrôle

- [ ] Rien de ce que fait déjà le noyau (§1) n'est réécrit ou contredit.
- [ ] Tout mouvement est sous `prefers-reduced-motion: no-preference` ; tout effet au défilement est aussi sous son `@supports` complet.
- [ ] Les techniques à prise en charge partielle (`interpolate-size`, transitions de `display`, `text-wrap: pretty`, `contrast-color()`) ont un état sans elles qui reste juste.
- [ ] Noms de transition uniques ; seulement `transform` et `opacity` animés.
- [ ] Couleurs dérivées des variables ; `contrast-color()` seulement pour du texte, après une valeur de repli.
- [ ] Composants adaptés par requêtes de conteneur plutôt que par largeur d'écran quand ils vivent dans des grilles.
