# Finitions : ce qui sépare le bon de l'exceptionnel

Aucun de ces détails ne se remarque seul. Ensemble, ils font qu'un site « respire la qualité » sans qu'on sache dire pourquoi.

## Sommaire

1. Le socle CSS
2. États des éléments interactifs
3. Focus et clavier
4. Formulaires
5. Détails de texte et de données
6. Détails visuels
7. États de l'interface : vide, chargement, erreur
8. Résister aux vrais contenus
9. Sentir le natif sur mobile
10. Accessibilité
11. Performance
12. Référencement et partage
13. La dernière passe

---

## 1. Le socle CSS

```css
*, *::before, *::after { box-sizing: border-box; }
html {
  -webkit-text-size-adjust: 100%; text-size-adjust: 100%;
  -webkit-tap-highlight-color: transparent; /* on fournit notre propre retour d'appui */
  scroll-padding-top: 6rem;                 /* les ancres ne passent pas sous l'en-tête fixe */
}
body { margin: 0; min-height: 100svh; line-height: 1.5; text-rendering: optimizeLegibility; }
img, picture, video, canvas, svg { display: block; max-width: 100%; height: auto; }
input, button, textarea, select { font: inherit; color: inherit; }
button { cursor: pointer; touch-action: manipulation; }
p, h1, h2, h3, h4 { overflow-wrap: break-word; }
::selection { background: var(--accent); color: var(--sur-accent); }
:target { scroll-margin-top: 6rem; }
@media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
```

La couleur de sélection, la barre de défilement (`scrollbar-color`), le curseur de saisie (`caret-color`) et la couleur d'accent des champs natifs (`accent-color`) se règlent aux couleurs du site : quatre lignes, et tout paraît dessiné.

## 2. États des éléments interactifs

Chaque élément cliquable possède **tous** ses états, dessinés et non laissés au navigateur :

| État | Traitement |
| --- | --- |
| Repos | reconnaissable comme cliquable sans survol |
| Survol | changement de fond ou de couleur, 120–180 ms, souris seulement |
| Focus clavier | anneau visible à fort contraste (section 3) |
| Appui | `scale(0.97)` ou enfoncement, immédiat |
| Sélectionné / courant | signe distinct du survol, pas seulement une couleur |
| Désactivé | opacité 0,5, `cursor: not-allowed`, et la raison expliquée à côté |
| Chargement | largeur conservée, indicateur, double clic empêché |
| Réussite / échec | confirmation à l'endroit de l'action |

- **Zone cliquable** : au moins 44 × 44 px, même si le dessin est plus petit. Agrandis avec un pseudo-élément :

```css
.icone-bouton { position: relative; }
.icone-bouton::after { content: ""; position: absolute; inset: -10px; }
```

- **Carte cliquable** : toute la carte réagit, un seul lien réel à l'intérieur étendu par un pseudo-élément, pas un lien enveloppant tout le contenu.
- **Hiérarchie des boutons** : un seul bouton principal par écran. Les autres sont secondaires (contour ou fond doux) ou tertiaires (texte).
- **Libellés** : un verbe et son objet. « Réserver une table », pas « Valider ».
- **Liens externes** signalés, téléchargements avec format et poids.

## 3. Focus et clavier

```css
:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 3px;
  border-radius: inherit;
}
:focus:not(:focus-visible) { outline: none; }
```

- L'anneau apparaît **sans transition** : au clavier, tout délai désoriente.
- Contraste de l'anneau : 3:1 au moins avec ce qui l'entoure. Sur un fond de la couleur d'accent, prends l'encre.
- **Ordre de tabulation** identique à l'ordre visuel.
- **Lien d'évitement** « Aller au contenu » comme premier élément focalisable.
- **Fenêtre modale** : le focus y entre, y reste, revient au déclencheur à la fermeture ; `Échap` ferme. Utilise `<dialog>` ou une primitive éprouvée (Radix, Base UI, React Aria).
- **Tout ce qui se fait à la souris se fait au clavier** : menus, onglets (flèches), carrousels, curseurs.
- Jamais de `tabindex` positif. Jamais un `<div>` cliquable à la place d'un `<button>` ou d'un `<a>`.

## 4. Formulaires

Un formulaire est souvent le seul endroit où le site gagne de l'argent. Il mérite autant de soin que le premier écran.

- **Étiquette visible** au-dessus de chaque champ, reliée par `for`/`id`. Le texte indicatif (`placeholder`) n'est pas une étiquette : il disparaît à la saisie.
- **Corps 16 px minimum** dans les champs : en dessous, iOS zoome sur la page.
- **Hauteur 44–52 px**, marge intérieure généreuse, bordure à 3:1 de contraste.
- **Bon clavier, bon remplissage** : `type`, `inputmode`, `autocomplete`, `enterkeyhint`.

```html
<label for="courriel">Adresse électronique</label>
<input id="courriel" name="email" type="email" inputmode="email"
       autocomplete="email" autocapitalize="off" spellcheck="false" required
       aria-describedby="courriel-aide">
<p id="courriel-aide">Nous répondons sous 24 h, sans démarchage.</p>
```

- **Validation au bon moment** : à la sortie du champ, pas à chaque frappe ; l'erreur disparaît dès qu'elle est corrigée.
- **Message d'erreur** sous le champ, relié par `aria-describedby`, qui dit quoi faire (« Il manque le @ dans l'adresse »), accompagné d'une icône. Le champ reçoit `aria-invalid="true"`.
- **Jamais de saisie perdue** : une erreur à l'envoi conserve tout ce qui a été tapé et place le focus sur le premier champ fautif.
- **Peu de champs** : chaque champ retiré augmente le taux d'envoi. Marque les champs facultatifs, pas les obligatoires, s'ils sont minoritaires.
- **Tolère les formats** : espaces dans un numéro de téléphone, majuscules dans une adresse électronique, virgule ou point dans un nombre.
- **Bouton d'envoi** libellé par le résultat, désactivé seulement pendant l'envoi, avec retour visible.
- **Après l'envoi** : une confirmation claire qui dit ce qui va se passer et quand.
- **Cases et boutons radio** : cibles larges (toute la ligne cliquable), état coché net.
- **Sélecteur** : natif sur mobile ; sur bureau, une primitive accessible si on le redessine.

## 5. Détails de texte et de données

| Problème | Solution |
| --- | --- |
| « Il y a 340 jours » | date absolue au-delà d'une semaine, `<time datetime>` |
| « 1 résultats » | accord par `Intl.PluralRules` |
| Chiffres qui sautent | `font-variant-numeric: tabular-nums` |
| Nom long qui casse la mise en page | `min-width: 0` sur l'enfant flex, puis `text-overflow: ellipsis` ou `line-clamp` |
| Adresse ou URL qui déborde | `overflow-wrap: anywhere` |
| Avatar écrasé en ovale | `flex-shrink: 0; aspect-ratio: 1; object-fit: cover` |
| Prix, dates, nombres mis en forme à la main | `Intl.NumberFormat`, `Intl.DateTimeFormat` avec la langue du site |
| Titre coupé au mauvais endroit | `text-wrap: balance`, espaces insécables |
| Bouton dont le libellé passe sur deux lignes | `white-space: nowrap` et libellé plus court |
| Texte tronqué sans accès au complet | attribut `title` ou dépliage |
| Téléphone non cliquable | `<a href="tel:+34…">` ; de même `mailto:` et lien vers l'itinéraire |
| Heures d'ouverture ambiguës | « Ouvert, ferme à 23 h » calculé pour aujourd'hui |

## 6. Détails visuels

- **Alignement optique** des icônes et du texte : vérifie à l'œil, corrige de 0,5 à 1 px.
- **Marge intérieure asymétrique** des boutons avec icône : un peu moins du côté de l'icône.
- **Bouton** : marge horizontale environ deux fois la verticale ; hauteur alignée sur celle des champs voisins.
- **Rayons concentriques** entre contenant et contenu.
- **Bord des images claires** : contour intérieur à 8 % d'encre.
- **Filets** à 10–15 % de l'encre, jamais plus contrastés que le contenu qu'ils séparent.
- **Fondu de bord** sur une zone qui défile (masque dégradé) pour signaler qu'il y a une suite.
- **Séparateur de liste** absent après le dernier élément.
- **Transition de thème** clair/sombre sans éclair blanc : couleur de fond déclarée dans le HTML initial, `color-scheme` déclaré.
- **Icône d'onglet** (favicon) en SVG, adaptée au thème sombre, plus `apple-touch-icon` ; `theme-color` accordé au fond.
- **En-tête fixe** : bordure ou ombre qui n'apparaît qu'après le début du défilement.
- **Page 404** dessinée, dans la voix du site, avec un chemin de retour.
- **Curseur** : `pointer` sur ce qui se clique, `text` sur ce qui se sélectionne, jamais de curseur personnalisé qui remplace le pointeur.
- **Sélection de texte** possible partout sauf sur les libellés de boutons (`user-select: none`).
- **Impression** : pour une carte de restaurant, un devis, un article, une feuille `@media print` propre.

## 7. États de l'interface : vide, chargement, erreur

Une interface n'a pas un état, elle en a cinq : **idéal, vide, en chargement, partiel, en erreur**. Les quatre derniers sont ceux qu'on oublie.

- **Vide** : dit pourquoi c'est vide et propose l'action suivante. C'est une occasion de voix.
- **Chargement** : sous 300 ms, rien ; entre 300 ms et 1 s, un indicateur discret ; au-delà, un **squelette** de la forme exacte du contenu à venir, pour qu'il n'y ait aucun saut à l'arrivée.
- **Erreur** : dit ce qui s'est passé avec des mots simples, ce que le visiteur peut faire, et propose de réessayer. Jamais de code technique seul.
- **Hors ligne, lent** : le site reste lisible ; les images ont une couleur de fond ou un aperçu flou en attendant.
- **Réussite** : confirmée, puis effacée d'elle-même si elle est sans conséquence.

## 8. Résister aux vrais contenus

Avant de livrer, remplace les données d'exemple par les pires cas réalistes et regarde ce qui casse :

| Donnée | Cas à essayer |
| --- | --- |
| Nom | « Li », « Marie-Charlotte de La Rochefoucauld-Montmorency », « Ægir Þórsson », « محمد » |
| Titre | un mot ; 140 caractères ; un mot de 40 lettres sans espace |
| Texte | vide ; une ligne ; dix paragraphes |
| Nombre | 0 ; 1 ; 1 000 000 ; négatif ; 12,5 |
| Prix | gratuit ; 9 999,99 € ; « sur devis » |
| Liste | 0, 1, 2, 7, 500 éléments |
| Image | absente ; panoramique ; verticale ; très claire sur fond clair |
| Langue | la plus longue du site (souvent l'allemand) ; une langue de droite à gauche si prévue |
| Écran | 320 px de large ; 600 px de haut ; zoom 200 % ; texte du système agrandi |

Corrections types : `min-width: 0` dans les grilles et les flex, `overflow-wrap`, `line-clamp`, `aspect-ratio`, largeurs minimales et maximales plutôt que fixes, propriétés logiques (`margin-inline`, `padding-block`) pour les langues de droite à gauche.

## 9. Sentir le natif sur mobile

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f7f4ec" media="(prefers-color-scheme: light)">
```

Ne bloque jamais le zoom (`maximum-scale=1`, `user-scalable=no`) : c'est une barrière d'accessibilité.

| Symptôme | Correction |
| --- | --- |
| La hauteur plein écran déborde sous la barre du navigateur | `100svh` (ou `100dvh` si la zone doit suivre la barre) au lieu de `100vh` |
| La page zoome quand on touche un champ | `font-size: 16px` minimum dans les champs |
| Le survol reste collé après un toucher | effets de survol derrière `(hover: hover) and (pointer: fine)` |
| Un rectangle gris clignote au toucher | `-webkit-tap-highlight-color: transparent`, plus un vrai état d'appui |
| Le contenu passe sous l'encoche ou la barre du bas | `padding-bottom: max(1rem, env(safe-area-inset-bottom))` |
| Le défilement d'un panneau entraîne celui de la page | `overscroll-behavior: contain` sur le panneau |
| Un appui long sélectionne le texte d'un bouton | `user-select: none; -webkit-touch-callout: none` sur le bouton |
| Le carrousel horizontal fait défiler la page | `touch-action: pan-y` et `scroll-snap-type: x mandatory` |
| Le clavier virtuel cache le champ ou le bouton | disposition en `dvh`, bouton d'envoi dans le flux, `scrollIntoView` au focus |
| La page de fond défile sous une fenêtre ouverte | `<dialog>` natif, ou `overflow: hidden` sur la racine en compensant la largeur de la barre de défilement |
| Le toucher semble lent | `touch-action: manipulation` |

Sur mobile, les actions vitales d'un commerce local (appeler, itinéraire, réserver) sont dans une barre fixe en bas, respectant la zone sûre.

## 10. Accessibilité

Socle non négociable (WCAG 2.2 niveau AA) :

- **Structure** : un seul `<h1>`, niveaux de titres sans saut, régions `<header>`, `<nav>`, `<main>`, `<footer>`, attribut `lang` sur `<html>` et sur les passages dans une autre langue.
- **Images** : `alt` utile, ou `alt=""` si décorative. Icône seule dans un bouton : `aria-label` sur le bouton et `aria-hidden="true"` sur l'icône.
- **Contraste** : 4,5:1 pour le texte, 3:1 pour les grands textes et les composants.
- **Clavier** : tout est atteignable et utilisable, focus toujours visible, jamais masqué par un élément fixe.
- **Cibles** : 24 × 24 px au minimum, 44 × 44 px recommandés.
- **Couleur** : jamais seule porteuse d'une information.
- **Mouvement** : réduit sur demande, pause disponible, rien qui clignote.
- **Zoom** à 200 % et réagencement à 320 px de large sans défilement horizontal.
- **Formulaires** : étiquettes, erreurs décrites et reliées, saisie conservée.
- **Annonces** : les changements dynamiques importants (erreur, ajout au panier, résultat) sont annoncés par une région `aria-live`.
- **Médias** : sous-titres pour la vidéo, transcription pour l'audio.
- **HTML d'abord** : la sémantique native avant ARIA. Un mauvais ARIA est pire que pas d'ARIA.

Rappel : en Europe, l'accessibilité des services numériques vendus au public est une obligation légale depuis juin 2025 pour de nombreux acteurs (directive (UE) 2019/882). Vérifie le périmètre applicable au client avant de l'affirmer.

## 11. Performance

La vitesse est une qualité esthétique : un site lent paraît mal fait, quel que soit son dessin.

Seuils « bons » des signaux web essentiels : **LCP ≤ 2,5 s** (affichage du plus grand élément), **INP ≤ 200 ms** (réactivité), **CLS ≤ 0,1** (stabilité visuelle).

**Affichage (LCP)**

- L'image du premier écran : format moderne, dimensionnée, `fetchpriority="high"`, jamais en `loading="lazy"`, jamais injectée par script.
- Polices : préchargées, peu nombreuses, repli ajusté (`03-typographie.md`, section 9).
- CSS critique dans le document ou servi tôt ; pas de feuille de style bloquante inutile.
- Scripts tiers différés ; chaque outil de mesure, chat ou carte se justifie ou se charge au clic.

**Stabilité (CLS)**

- `width` et `height` (ou `aspect-ratio`) sur toutes les images, vidéos, cadres intégrés.
- Espace réservé pour tout ce qui arrive tard : bandeau, publicité, contenu chargé.
- Pas d'insertion de contenu au-dessus de ce que le visiteur lit.
- Animations sur `transform` et `opacity` seulement.

**Réactivité (INP)**

- Retour visuel immédiat à chaque action, avant tout traitement.
- Pas de gros script exécuté au chargement ; découpe et diffère.
- Listes longues virtualisées ; `content-visibility: auto` sur les sections lointaines.

**Budget indicatif** pour une page vitrine : moins de 150 Ko de JavaScript compressé, moins de 100 Ko de polices, images du premier écran sous 200 Ko, moins de 50 requêtes. Une page de contenu peut fonctionner sans JavaScript.

**Carte, vidéo, chat** : remplace par une image cliquable qui charge le composant à la demande.

## 12. Référencement et partage

La beauté ne sert à rien si personne ne trouve le site, et la refonte ne doit jamais détruire l'acquis.

- **Balise de titre** unique par page, environ 50 à 60 caractères, le sujet d'abord ; **description** qui donne envie de cliquer.
- **Un `<h1>`** qui dit le sujet de la page avec les mots du visiteur ; hiérarchie de titres logique.
- **Contenu dans le HTML** servi (rendu serveur ou statique), pas seulement après exécution de script.
- **Texte en vrai texte** : jamais un titre ou un prix dans une image.
- **URL** lisibles et stables ; redirections permanentes pour toute URL modifiée lors d'une refonte.
- **Liens** en vraies balises `<a href>`, texte d'ancre descriptif.
- **Images** : nom de fichier et `alt` descriptifs.
- **Données structurées** (JSON-LD) adaptées : `LocalBusiness`, `Restaurant` avec menu et horaires, `Product`, `Course`, `Book`, `FAQPage`, `BreadcrumbList`, `Organization`.
- **Multilingue** : une URL par langue, balises `hreflang` réciproques, attribut `lang`, sélecteur de langue libellé dans chaque langue (« Español », pas un drapeau seul).
- **Plan du site** et `robots.txt` à jour ; balise canonique.
- **Aperçu de partage** : image Open Graph de 1 200 × 630 px dessinée dans l'identité du site, titre et description dédiés. C'est souvent la première impression réelle.
- **Commerce local** : nom, adresse et téléphone identiques partout, en texte, avec lien d'itinéraire.

## 13. La dernière passe

À faire lentement, page par page, avant de dire que c'est fini :

1. Parcours tout le site **au clavier seul**.
2. Regarde chaque page à **320, 390, 768, 1 440 et 1 920 px**.
3. Active le **mouvement réduit** et le **thème sombre** du système.
4. Zoome à **200 %**.
5. Bride le réseau en **4G lente** et recharge.
6. Clique chaque lien, envoie chaque formulaire avec des données fausses puis justes.
7. Lis **tout le texte à voix haute** : fautes, répétitions, phrases creuses.
8. Vérifie la **typographie de la langue** : espaces insécables, guillemets, apostrophes (`09-ecriture.md`).
9. Passe `scripts/audit.mjs` et `scripts/regard.mjs`, traite chaque alerte ou justifie-la.
10. Regarde l'**onglet du navigateur** (titre, icône) et l'**aperçu de partage**.
11. Cherche les restes : `Lorem`, `TODO`, `console.log`, liens `#`, images d'exemple.
12. Reviens le lendemain et regarde encore.
