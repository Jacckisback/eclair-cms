# Typographie

La typographie porte l'essentiel de la personnalité. Avant d'ajouter une image ou un effet, vérifie que la page est déjà belle en texte seul.

## Sommaire

1. Choisir les polices
2. Associer deux familles
3. L'échelle
4. Les réglages par rôle
5. Micro-typographie CSS
6. Titres : le grand format
7. Texte long : le confort
8. Chiffres, données, code
9. Chargement des polices
10. Fautes fréquentes

---

## 1. Choisir les polices

Pars du sentiment, pas d'une liste. Chaque classe de caractères porte une connotation :

| Classe | Ce qu'elle dit | Exemples gratuits |
| --- | --- | --- |
| Garaldes, humanes | tradition, culture, lecture, chaleur | EB Garamond, Cormorant, Crimson Pro, Gambetta (F) |
| Réales, transitionnelles | sérieux, presse, institution | Newsreader, Source Serif 4, Libre Baskerville, Literata |
| Didones | luxe, mode, contraste, élégance froide | Bodoni Moda, Playfair Display, Boska (F) |
| Serifs contemporaines | caractère, modernité chaleureuse | Fraunces, Instrument Serif, Gloock, Young Serif, Zodiak (F), Erode (F) |
| Mécanes | solidité, atelier, industrie | Zilla Slab, Roboto Slab, Bitter |
| Grotesques neutres | clarté, produit, neutralité | Geist, Inter Tight, Switzer (F), Hanken Grotesk, Public Sans |
| Grotesques de caractère | contemporain, affirmé | Bricolage Grotesque, Familjen Grotesk, Schibsted Grotesk, Cabinet Grotesk (F), General Sans (F), Satoshi (F), Host Grotesk |
| Géométriques | modernisme, simplicité, enfance | Jost, Outfit, Sora, Urbanist, Supreme (F) |
| Display expressives | affiche, événement, audace | Syne, Unbounded, Anton, Big Shoulders Display, Clash Display (F), Tanker (F), Panchang (F) |
| Étroites | presse, sport, densité, impact | Barlow Condensed, Archivo (axe largeur), Oswald, Bebas Neue |
| Monospaces | technique, donnée, honnêteté brute | JetBrains Mono, Geist Mono, IBM Plex Mono, DM Mono, Martian Mono, Space Mono |
| Arrondies | douceur, jeu, proximité | Nunito, Fredoka, Baloo 2, Rubik |
| Très lisibles | accessibilité, service public | Atkinson Hyperlegible, Lexend, Source Sans 3 |

(F) = Fontshare, les autres sont sur Google Fonts. Vérifie toujours que la police couvre les langues du site (accents français, ñ et ¿ espagnols, ß et trémas allemands) et qu'elle possède les graisses et l'italique dont tu as besoin.

**Critères de qualité** avant d'adopter une police de titre : des formes reconnaissables (regarde a, g, R, Q, &, ?), un vrai italique, des chiffres soignés, un bon rendu en grand corps. Pour une police de lecture : une hauteur d'x confortable, des formes ouvertes, lisible à 16 px.

**Sur les polices très répandues** (Inter, Poppins, Montserrat, Roboto, Open Sans, Lato, Playfair Display) : elles ne sont pas mauvaises, elles sont invisibles à force d'être vues. N'en fais jamais la police de titre d'un site qui doit avoir une personnalité. En texte courant, elles restent acceptables si le titre porte le caractère.

**Si le client a une police de marque**, utilise-la. Le sur-mesure se joue alors sur l'échelle, les réglages et la composition.

## 2. Associer deux familles

Trois stratégies, par ordre de sûreté :

1. **Une seule famille**, en jouant graisses, corps et italique. Le plus cohérent ; excellent avec une famille variable riche (Fraunces, Bricolage Grotesque, Archivo).
2. **Contraste de classe** : une serif de caractère pour les titres, une sans-serif calme pour le texte (ou l'inverse). Les deux doivent partager quelque chose : proportions, époque ou hauteur d'x.
3. **Trois voix** : titre, texte, plus une monospace ou une étroite pour les étiquettes, légendes et données. La troisième reste minuscule et rare.

Si tu insères quelques mots d'une famille dans un texte de l'autre (un nom en italique serif dans une phrase en sans-serif), ajuste son corps à l'œil (`font-size: 1.06em` par exemple) pour égaliser les hauteurs d'x : c'est une correction optique, pas une taille hors échelle.

À éviter : deux serifs proches, deux sans-serifs proches (l'œil lit une erreur), deux polices expressives ensemble.

Associations éprouvées, à croiser avec le sujet :

| Titre | Texte | Climat |
| --- | --- | --- |
| Fraunces | Instrument Sans | chaleureux, artisanal moderne |
| Instrument Serif | Geist | éditorial net, produit élégant |
| Gloock | DM Sans | gourmand, commerçant |
| Newsreader | Newsreader + IBM Plex Mono | presse, expertise |
| Bodoni Moda | Jost | mode, luxe |
| Bricolage Grotesque | Bricolage Grotesque | vivant, contemporain |
| Cabinet Grotesk (F) | Satoshi (F) | studio, jeune marque |
| Archivo étroit | IBM Plex Sans | technique, bâtiment |
| Zodiak (F) | Switzer (F) | cabinet, conseil |
| Space Grotesk | Space Mono | outil, laboratoire |
| Cormorant Garamond | Hanken Grotesk | hôtel, culture |
| Syne | Onest | événement, création |

## 3. L'échelle

Choisis un **rapport** selon le curseur Contraste, puis génère les tailles. N'invente pas de taille hors échelle.

| Rapport | Nom | Usage |
| --- | --- | --- |
| 1,2 | tierce mineure | interfaces denses, tableaux de bord |
| 1,25 | tierce majeure | sites de contenu, équilibre |
| 1,333 | quarte | éditorial, marketing |
| 1,5 | quinte | fort contraste, peu de niveaux |
| 1,618 | nombre d'or | affiches, très grands titres |

Sept niveaux suffisent presque toujours : légende, petit, texte, chapô, titre 3, titre 2, titre 1. Ajoute un niveau « monumental » pour la signature si besoin.

**Tailles fluides.** Le rapport doit être plus faible sur mobile que sur bureau, sinon les titres débordent. Utilise `clamp()` :

```css
/* de 375 px à 1440 px de large */
--t-texte:  clamp(1rem,     0.965rem + 0.15vw, 1.125rem);   /* 16 → 18 */
--t-chapo:  clamp(1.1875rem, 1.1rem + 0.38vw, 1.4375rem);   /* 19 → 23 */
--t-h3:     clamp(1.375rem, 1.2rem + 0.75vw,  1.875rem);    /* 22 → 30 */
--t-h2:     clamp(1.875rem, 1.43rem + 1.88vw, 3.125rem);    /* 30 → 50 */
--t-h1:     clamp(2.5rem,   1.44rem + 4.5vw,  5.5rem);      /* 40 → 88 */
--t-geant:  clamp(3.25rem,  0.87rem + 10.1vw, 10rem);       /* 52 → 160 */
```

Formule : `pente = (max − min) / (largeurMax − largeurMin)` ; `valeur = min − pente × largeurMin`, exprimée en rem, plus `pente × 100vw`. Garde toujours un terme en `rem` pour que le zoom du navigateur fonctionne.

## 4. Les réglages par rôle

Plus le corps est grand, plus on serre l'interlignage et l'interlettrage. C'est la règle la plus souvent ignorée.

| Rôle | Corps | Interlignage | Interlettrage | Graisse |
| --- | --- | --- | --- | --- |
| Monumental | 96–200 px | 0,85–0,95 | −0,04 à −0,06em | selon la police |
| Titre 1 | 48–96 px | 0,95–1,05 | −0,025 à −0,04em | selon la police |
| Titre 2 | 30–50 px | 1,05–1,15 | −0,015 à −0,025em | 500–700 |
| Titre 3 | 22–30 px | 1,2–1,3 | −0,01em | 500–600 |
| Chapô | 19–24 px | 1,4–1,5 | 0 | 400 |
| Texte | 16–20 px | 1,5–1,7 | 0 | 400 |
| Petit, légende | 13–14 px | 1,4–1,5 | +0,005 à +0,01em | 400–500 |
| Étiquette en capitales | 11–13 px | 1,2 | +0,06 à +0,12em | 500–600 |

Graisse des titres : beaucoup de serifs de titre n'existent qu'en 400 (Instrument Serif, Gloock, Young Serif) et sont dessinées pour cela ; une grotesque demande plutôt 500 à 700. Ne force jamais une graisse que la police n'a pas.

Ajustements : les serifs à fort contraste supportent moins de resserrement que les grotesques ; une police étroite se serre peu ; un texte clair sur fond sombre demande une graisse un cran plus légère et un interlettrage légèrement plus ouvert (+0,005em), car il paraît plus gras.

Les capitales s'espacent toujours (+0,05em minimum). Les minuscules ne s'espacent jamais positivement dans un texte courant.

## 5. Micro-typographie CSS

Socle à poser sur tout projet :

```css
html {
  -webkit-text-size-adjust: 100%;
  text-size-adjust: 100%;
  font-optical-sizing: auto;
  font-kerning: normal;
  font-synthesis: none;              /* interdit les faux gras et faux italiques */
  text-rendering: optimizeLegibility;
}
h1, h2, h3, .titre   { text-wrap: balance; }   /* lignes équilibrées */
p, li, blockquote    { text-wrap: pretty; }    /* évite les mots orphelins */
p                    { hyphens: auto; hyphenate-limit-chars: 7 3 3; }  /* exige l'attribut lang */
.chiffres            { font-variant-numeric: tabular-nums lining-nums; }
.texte-long          { font-variant-numeric: oldstyle-nums proportional-nums; }
.sigle               { font-variant-caps: all-small-caps; letter-spacing: 0.04em; }
```

Affinages selon la police (à activer seulement si elle les contient) :

```css
.titre  { font-feature-settings: "ss01", "dlig"; }       /* variantes stylistiques, ligatures */
.frac   { font-variant-numeric: diagonal-fractions; }    /* 1/2 → ½ */
q       { quotes: "«\202F" "\202F»" "“" "”"; }           /* guillemets français */
```

Améliorations progressives utiles : `text-box: trim-both cap alphabetic` supprime le blanc au-dessus et au-dessous des capitales, pour un alignement optique exact avec une image ou un bouton ; `hanging-punctuation: first` fait sortir le guillemet ouvrant de la marge (Safari). Place-les derrière `@supports`.

`font-synthesis: none` est essentiel : sans lui, le navigateur fabrique un faux italique penché et un faux gras empâté quand la graisse n'est pas chargée.

## 6. Titres : le grand format

- **Va plus grand que ce qui te semble raisonnable.** Un titre à 56 px sur un écran de 1 440 px est timide. Essaie 96, 128, 160. Un titre monumental demande un interlignage inférieur à 1 et un interlettrage négatif, sinon il se disloque.
- **Coupe les lignes toi-même** quand le sens l'exige : un titre se coupe entre deux groupes de sens, jamais après un article ou une préposition. `text-wrap: balance` aide, un `<br>` conditionnel ou une espace insécable règle les cas précis.
- **Un seul accent typographique par titre** : un mot en italique, ou dans l'autre police, ou en couleur. Pas les trois.
- **Aligne optiquement** : une grande capitale ou un guillemet en début de ligne paraît rentré. Compense par une marge négative de 0,04 à 0,08em.
- **Largeur de titre** : 12 à 20 caractères par ligne pour un titre monumental, 20 à 35 pour un titre 2.
- **Le mobile** : vérifie le mot le plus long du titre à 360 px de large dans toutes les langues du site. L'allemand et les mots composés débordent ; prévois `overflow-wrap: anywhere` en dernier recours, et un corps minimal plus petit.

## 7. Texte long : le confort

- **Longueur de ligne** : 55 à 75 caractères (`max-width: 65ch` sur le paragraphe). En dessous de 45, le rythme se hache ; au-dessus de 80, l'œil perd la ligne suivante.
- **Corps** : 18 à 20 px pour un article sur bureau, 16 à 17 px sur mobile. Jamais sous 16 px pour un texte qu'on lit vraiment.
- **Interlignage** : 1,5 à 1,7, proportionnel à la longueur de ligne.
- **Espacement des paragraphes** : un demi à un interlignage (`margin-block: 0.75em` à `1em`). Pas d'alinéa en plus.
- **Hiérarchie dans l'article** : un titre intermédiaire est plus proche du paragraphe qui le suit que de celui qui le précède (par exemple `margin-top: 2em; margin-bottom: 0.5em`).
- **Alignement** : à gauche, en drapeau. Le justifié sur le web produit des rivières, sauf colonne large avec césure activée.
- **Liens dans le texte** : soulignés, avec `text-decoration-thickness: 0.06em; text-underline-offset: 0.18em`. La couleur seule ne suffit pas.
- **Citations** : mises en retrait, corps supérieur ou italique, filet à gauche ou guillemets suspendus. Attribution en petit.
- **Listes** : puces alignées sur la marge ou sorties à gauche, espace entre items inférieur à l'espace entre paragraphes.

## 8. Chiffres, données, code

- Dans un tableau, un prix, un compteur, une horloge : `tabular-nums`, alignés à droite, pour que les colonnes ne dansent pas. Vérifie sur capture que la police possède des chiffres tabulaires : sinon les chiffres changent d'aspect ou de graisse, et il vaut mieux composer les données dans la police de texte ou la monospace.
- Dans un texte courant avec une serif : `oldstyle-nums`, plus doux à l'œil.
- Les grands chiffres d'une section « preuves » méritent la police de titre, avec l'unité plus petite et surélevée.
- Séparateurs de milliers, décimales et symbole monétaire suivent la langue (voir `09-ecriture.md`). Utilise `Intl.NumberFormat` et `Intl.DateTimeFormat`, jamais une mise en forme à la main.
- Code : une monospace assortie au reste (hauteur d'x voisine), corps à 0,9em du texte, `tab-size: 2`, ligatures de programmation désactivées sauf demande.

## 9. Chargement des polices

Une belle police qui arrive en retard et fait sauter la page détruit l'impression de qualité.

Pour une **maquette ou un fichier HTML seul**, une balise `<link>` vers Google Fonts ou Fontshare avec `rel="preconnect"` et `display=swap` suffit ; ce qui suit vaut pour un site mis en production.

- Format **WOFF2**, fichiers **auto-hébergés** ou servis par l'outil du cadre de travail (`next/font`, `@fontsource`, Fontsource variable).
- **Deux familles, quatre fichiers au plus** au premier écran. Une police variable remplace plusieurs graisses par un seul fichier.
- **Sous-ensemble** : latin étendu seulement, sauf besoin.
- `font-display: swap` pour le texte ; `optional` si un saut de mise en page est inacceptable.
- **Précharge** la police du premier titre : `<link rel="preload" as="font" type="font/woff2" crossorigin>`.
- **Police de repli ajustée** pour annuler le saut : déclare un `@font-face` local avec `size-adjust`, `ascent-override`, `descent-override`, `line-gap-override` calés sur la police finale (`next/font` le fait automatiquement).

```css
@font-face {
  font-family: "Titre Repli";
  src: local("Georgia");
  size-adjust: 104%;
  ascent-override: 92%;
  descent-override: 24%;
  line-gap-override: 0%;
}
:root { --police-titre: "Fraunces", "Titre Repli", Georgia, serif; }
```

## 10. Fautes fréquentes

| Faute | Correction |
| --- | --- |
| Grand titre avec interlignage 1,5 | 0,9 à 1,1 selon le corps |
| Grand titre sans resserrement | `letter-spacing: -0.02em` à `-0.05em` |
| Capitales non espacées | `letter-spacing: 0.06em` minimum |
| Texte gris clair illisible | contraste 4,5:1 minimum, viser 7:1 pour le texte courant |
| Faux gras, faux italique | charger la vraie graisse, `font-synthesis: none` |
| Sept tailles voisines (15, 16, 17, 18…) | s'en tenir à l'échelle |
| Trois graisses proches (400, 500, 600) | deux graisses franchement distinctes |
| Texte centré sur plus de trois lignes | aligner à gauche |
| Lignes de 120 caractères | `max-width: 65ch` |
| Mot seul sur la dernière ligne | `text-wrap: pretty`, ou espace insécable avant le dernier mot |
| Guillemets droits, apostrophes droites | « », “ ”, ’ selon la langue |
| Chiffres qui sautent dans un compteur | `tabular-nums` |
| Titre et sous-titre de même poids visuel | écart d'au moins deux niveaux d'échelle ou changement de classe |
| Soulignement collé aux lettres | `text-underline-offset: 0.18em` |
