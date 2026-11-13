# Mouvement

Le mouvement est la dernière couche, pas la première. Un site doit être beau à l'arrêt. Le mouvement lui ajoute trois choses : de la **clarté** (d'où vient ceci, où est passé cela), de la **réponse** (l'interface m'a entendu) et du **caractère** (ce site a un tempérament).

## Sommaire

1. Faut-il animer ?
2. Le tempérament : accorder le mouvement à la marque
3. Courbes
4. Durées
5. Ressorts
6. Propriétés et performance
7. Entrées, sorties, interruptions
8. Chorégraphie
9. Le défilement
10. Transitions de page
11. Micro-interactions
12. Gestes
13. Le moment signature
14. Mouvement réduit et tactile
15. Recettes
16. Mise au point
17. Fautes fréquentes

---

## 1. Faut-il animer ?

Pose trois questions, dans l'ordre. Un « non » arrête tout.

**À quelle fréquence le visiteur verra-t-il cela ?**

| Fréquence | Décision |
| --- | --- |
| Des dizaines de fois par session (navigation au clavier, survol de liste, saisie, onglets) | instantané ou presque (≤ 100 ms), sans déplacement |
| Plusieurs fois par visite (menus, fenêtres, filtres) | bref et fonctionnel |
| Une fois par visite (arrivée, changement de page) | peut être soigné |
| Rare (première visite, réussite, fin de parcours) | peut être expressif |

**À quoi sert-il ?** Une seule réponse valable parmi : *orienter* (montrer d'où vient et où va un élément), *confirmer* (l'action a été prise en compte), *relier* (deux états sont le même objet), *expliquer* (montrer un fonctionnement), *adoucir* (éviter un changement brutal), *caractériser* (le moment signature, rare). « C'est joli » n'est pas une réponse.

**Gêne-t-il ?** S'il retarde la lecture, bloque une action, détourne l'œil du contenu ou se répète à chaque passage, on le supprime.

Une action déclenchée au clavier ne s'anime pas. Un contenu que le visiteur est venu lire n'attend pas une animation pour apparaître.

## 2. Le tempérament : accorder le mouvement à la marque

Le même menu ne s'ouvre pas de la même façon chez un notaire et dans un festival. Choisis un tempérament, tiens-le partout.

| Tempérament | Courbe | Durée de base | Déplacement | Cascade | Convient à |
| --- | --- | --- | --- | --- | --- |
| **Posé** | décélération très longue | 600–900 ms | 8–12 px, fondus | 90–120 ms | luxe, hôtel, culture, maison de tradition |
| **Précis** | décélération nette | 180–260 ms | 4–8 px | 30–40 ms | SaaS, outil, finance, institution |
| **Souple** | décélération standard | 300–450 ms | 12–24 px | 50–70 ms | services, formation, commerce |
| **Vif** | ressort, léger rebond | 200–350 ms perçus | 24–60 px, rotation légère | 25–45 ms | événement, enfance, marque jeune |
| **Mécanique** | linéaire ou par paliers (`steps()`) | 80–200 ms | par crans | 20–40 ms | technique, rétro, données, terminal |
| **Éditorial** | décélération longue, masques | 500–800 ms | révélations par `clip-path` | 60–100 ms | mode, magazine, portfolio |

La « durée de base » est celle d'un contenu qui entre à l'écran (un titre, une image, un panneau). Elle ne s'applique pas aux retours d'interface (appui, survol, menu), qui restent brefs quel que soit le tempérament (section 4).

Définis le tempérament en variables, une fois :

```css
:root {
  --d-1: 120ms;   /* retour immédiat : appui, survol */
  --d-2: 220ms;   /* petits éléments : bulles, menus */
  --d-3: 360ms;   /* éléments moyens : panneaux, cartes */
  --d-4: 600ms;   /* grands éléments : pages, rideaux */
  --c-sortie:  cubic-bezier(0.22, 1, 0.36, 1);   /* décélération : tout ce qui apparaît */
  --c-douce:   cubic-bezier(0.33, 1, 0.68, 1);   /* décélération légère : survols, couleurs */
  --c-longue:  cubic-bezier(0.16, 1, 0.3, 1);    /* décélération très étirée : grands gestes */
  --c-trajet:  cubic-bezier(0.76, 0, 0.24, 1);   /* accélère puis freine : déplacement à l'écran */
  --c-depart:  cubic-bezier(0.5, 0, 0.75, 0);    /* accélération : ce qui quitte l'écran pour de bon */
}
```

Pour un site « Posé », multiplie les durées par 1,8 et utilise `--c-longue`. Pour un site « Précis », multiplie par 0,75.

## 3. Courbes

| Situation | Courbe | Raison |
| --- | --- | --- |
| Un élément apparaît | décélération (`--c-sortie`) | il réagit tout de suite, puis se pose |
| Un élément disparaît en restant dans le contexte (menu, bulle) | décélération, plus courte | la réponse à la fermeture doit être immédiate aussi |
| Un élément quitte définitivement l'écran (carte jetée, page sortante) | accélération (`--c-depart`) | il prend de la vitesse en partant |
| Un élément se déplace d'un point à un autre | accélération-décélération (`--c-trajet`) | départ et arrivée visibles |
| Couleur, opacité, survol | décélération légère (`--c-douce`) | discret |
| Mouvement continu (défilement de bandeau, progression, rotation) | `linear` | vitesse constante |
| Suivi direct du doigt ou du défilement | aucune | la position suit l'entrée, sans lissage |

Les mots-clés `ease`, `ease-in`, `ease-out` du navigateur sont trop mous : ils donnent un mouvement sans intention. Utilise les courbes ci-dessus. `ease-in` sur une apparition est une faute : le mouvement démarre lentement au moment précis où l'œil attend la réponse.

Pour un ressort ou un rebond en CSS pur, `linear()` permet de décrire la courbe par points :

```css
--c-ressort: linear(0, 0.006, 0.025, 0.101, 0.539, 0.721, 0.849, 0.937, 0.991, 1.021,
                    1.034, 1.036, 1.03, 1.017, 1.008, 1.002, 1);
```

## 4. Durées

La durée dépend de la **taille de l'élément** et de la **distance parcourue**, pas de l'envie de le montrer.

| Élément | Durée |
| --- | --- |
| Appui, survol, bascule, case à cocher | 80–160 ms |
| Bulle d'aide, petit menu | 120–200 ms |
| Menu déroulant, sélecteur, accordéon | 180–280 ms |
| Fenêtre modale, notification | 220–350 ms |
| Tiroir, panneau latéral, feuille mobile | 300–500 ms |
| Transition de page | 300–600 ms |
| Révélation d'un grand titre, ouverture du site | 600–1 200 ms |
| Séquence explicative | aussi longue que nécessaire, et contrôlable |

- **La sortie est plus courte que l'entrée** : 60 à 75 % de sa durée.
- **Une interface utilisée souvent reste sous 300 ms.** Au-delà, on attend.
- **Ce qui est lent doit être rare.** Une ouverture de 1 s se supporte une fois, pas à chaque page : ne la joue qu'à la première visite de la session.
- **Rien ne bloque** : pendant une animation, tout reste cliquable et lisible.

## 5. Ressorts

Un ressort ne suit pas une durée, il se stabilise selon une physique. Il a un avantage décisif : **interrompu, il garde sa vitesse** et repart naturellement vers sa nouvelle cible.

Utilise un ressort pour : ce qui suit un geste (glisser, lâcher, lancer), ce qui peut changer de cible en cours de route, les éléments qui doivent paraître « vivants », le suivi lissé du pointeur.

Reste sur une courbe pour : les fondus, les couleurs, les éléments de texte, tout ce qui est fonctionnel et fréquent.

Réglages avec Motion (anciennement Framer Motion) :

```js
// par durée perçue et rebond : le plus simple à régler
{ type: "spring", visualDuration: 0.35, bounce: 0 }      // net, sans dépassement
{ type: "spring", visualDuration: 0.4,  bounce: 0.15 }   // souple
{ type: "spring", visualDuration: 0.5,  bounce: 0.3 }    // joueur, à réserver

// par physique
{ type: "spring", stiffness: 400, damping: 40 }   // rapide et amorti (interface)
{ type: "spring", stiffness: 200, damping: 22 }   // souple
{ type: "spring", stiffness: 120, damping: 12 }   // rebondissant
```

Le rebond est un trait de caractère : zéro pour un site sérieux, 0,1 à 0,2 pour un site souple, jusqu'à 0,3 pour un site joueur et seulement sur des éléments secondaires. Jamais sur du texte qu'on lit, jamais sur une fenêtre modale d'un site institutionnel.

## 6. Propriétés et performance

À 60 images par seconde, le navigateur dispose d'environ 16 ms par image ; à 120, de 8 ms.

| Propriété | Coût | Verdict |
| --- | --- | --- |
| `transform`, `opacity` | composition seule | à utiliser par défaut |
| `clip-path`, `filter`, `mask` | peinture, souvent accélérée | bien, avec mesure |
| `background-color`, `color`, `box-shadow` | peinture | acceptable sur de petits éléments |
| `width`, `height`, `top`, `left`, `margin`, `padding`, `font-size` | recalcul de mise en page | à éviter |

Substitutions :

| Au lieu d'animer | Anime |
| --- | --- |
| `top` / `left` | `transform: translate()` |
| `width` / `height` d'un fond | `transform: scale()` sur un pseudo-élément, ou `clip-path: inset()` |
| `height: auto` (accordéon) | `grid-template-rows: 0fr → 1fr`, ou `interpolate-size: allow-keywords` en amélioration progressive |
| `box-shadow` | l'`opacity` d'un pseudo-élément portant l'ombre finale |
| une grande zone floutée | l'`opacity` d'une version déjà floutée |

Autres règles :

- **Pas de `transition: all`** : liste les propriétés.
- **`will-change`** seulement sur un élément qui saccade réellement, et retiré ensuite. Posé partout, il gaspille la mémoire.
- **Flou** (`filter: blur`, `backdrop-filter`) : coûteux, surtout dans Safari. Rayon modéré, petite surface, jamais animé sur un grand fond.
- **En React, n'anime pas par l'état** : un `setState` par image redessine le composant. Écris dans le style par une référence, ou utilise des valeurs de mouvement (`useMotionValue`, `useTransform`).
- **Variables CSS** : modifier une variable sur un parent recalcule tous ses descendants. Pour un suivi à chaque image, écris la transformation directement sur l'élément.
- **CSS plutôt que JavaScript quand la page est occupée** : une animation CSS sur `transform` et `opacity` continue pendant que le fil principal charge ; une animation pilotée image par image en JavaScript saccade.
- **API Web Animations** (`element.animate()`) pour une animation déclenchée par script sans bibliothèque.
- **Pause hors écran** : toute boucle (vidéo, canvas, animation infinie) s'arrête quand elle n'est pas visible (`IntersectionObserver`) et quand l'onglet est caché.

## 7. Entrées, sorties, interruptions

**Entrer** : opacité de 0 à 1, plus un déplacement court (8 à 24 px) **ou** une échelle de 0,94–0,98 à 1. Jamais depuis `scale(0)` : rien dans le monde n'apparaît à partir d'un point. Jamais un grand trajet pour un petit élément.

**Origine** : un élément ancré (menu, bulle, popover) grandit depuis son déclencheur. Règle `transform-origin` sur le point d'ancrage ; les bibliothèques de primitives exposent une variable pour cela (`--radix-popover-content-transform-origin`, `--transform-origin` chez Base UI). Une fenêtre modale, non ancrée, garde l'origine au centre.

**Sortir** : symétrique de l'entrée, plus court, vers l'endroit d'où l'élément est venu. Un panneau entré par la droite repart par la droite.

**Entrée sans JavaScript** :

```css
.panneau {
  opacity: 1; translate: 0 0;
  transition: opacity var(--d-2) var(--c-sortie), translate var(--d-2) var(--c-sortie),
              display var(--d-2) allow-discrete, overlay var(--d-2) allow-discrete;
  @starting-style { opacity: 0; translate: 0 8px; }
}
.panneau:not([open], :popover-open) { opacity: 0; translate: 0 4px; display: none; }
```

**Interruptions** : tout ce que le visiteur peut déclencher deux fois de suite doit pouvoir changer de direction en cours de route. Une **transition** CSS repart de l'état courant ; une **animation par images clés** repart de zéro. Donc : transitions (ou ressorts) pour tout ce qui est interactif, images clés pour ce qui se joue une fois.

**Bulles d'aide** : délai à la première (300 à 500 ms), puis immédiates et sans animation tant que le pointeur passe de l'une à l'autre.

**Asymétrie voulue** : lent quand le visiteur décide, rapide quand le système répond. Un bouton « maintenir pour supprimer » se remplit en 1,5 à 2 s en linéaire et se vide en 200 ms au relâchement.

## 8. Chorégraphie

Quand plusieurs éléments bougent, ils forment **un seul geste**.

1. **Distribue les rôles** : un élément mène (le plus important), les autres suivent, la plupart ne bougent pas. Si tout bouge, rien ne guide.
2. **Ensemble par défaut** : ce qui appartient au même changement bouge en même temps, même durée, même courbe.
3. **Cascade** pour un groupe d'éléments semblables : 30 à 80 ms d'écart, dans l'ordre de lecture. **Plafonne la durée totale** à 400–500 ms : au-delà de six ou sept éléments, réduis l'écart ou anime par rangées.
4. **Séquence** (attendre la fin de l'un pour lancer l'autre) seulement quand le second dépend du premier : le fond s'ouvre, puis le contenu arrive. Avec un recouvrement de 30 à 50 %.
5. **Même direction** : tous les éléments d'un groupe arrivent du même côté. Les directions opposées donnent le tournis.
6. **Le contenu qui remplace un autre** part d'abord (court), puis le nouveau arrive ; ou fondu croisé avec un léger flou (2 à 4 px) pendant le passage pour masquer la superposition.
7. **Le conteneur s'adapte** : s'il change de taille, sa taille s'anime en même temps que son contenu change.

```css
.liste > * {
  animation: entree var(--d-3) var(--c-sortie) both;
  animation-delay: calc(min(var(--i), 7) * 55ms);
}
@keyframes entree { from { opacity: 0; translate: 0 14px; } }
```

Donne l'indice à chaque élément : `style="--i: 3"`.

## 9. Le défilement

Le défilement appartient au visiteur. On ne le détourne jamais : pas de défilement lissé imposé par script, pas de sections aimantées sur une page de contenu, pas de défilement horizontal piégé, pas d'animation qui bloque la descente.

**Apparitions à la descente**

- Réservées à quelques moments : un grand titre, une image importante, une série. **Pas chaque section**, pas chaque paragraphe.
- Une seule fois : ce qui est apparu reste là.
- Discrètes : 12 à 24 px, 400 à 600 ms.
- Déclenchées quand l'élément est entré de 15 à 25 % dans l'écran.
- **Le contenu existe sans script** : l'état caché n'est appliqué que si le script tourne (classe `js` sur `<html>`), sinon un visiteur sans JavaScript ou un robot voit une page vide.

**Animations liées au défilement** (la progression suit la position) : en CSS natif, en amélioration progressive.

```css
@supports (animation-timeline: view()) {
  @media (prefers-reduced-motion: no-preference) {
    .image-revelee {
      animation: reveler linear both;
      animation-timeline: view();
      animation-range: entry 10% cover 35%;
    }
  }
}
@keyframes reveler { from { clip-path: inset(12% 8% 12% 8%); scale: 1.08; } }
```

Usages qui valent la peine : barre de progression de lecture, en-tête qui se compacte, image qui se dévoile, léger décalage de profondeur entre deux plans (parallaxe de 5 à 10 %, pas davantage), élément collant qui change au fil d'un récit.

**Élément collant narratif** : une image ou un schéma reste fixe (`position: sticky`) pendant que des blocs de texte défilent et le font changer d'état. C'est la forme la plus efficace pour expliquer un fonctionnement, et elle reste lisible sans animation.

## 10. Transitions de page

Les transitions de vue du navigateur relient deux pages sans bibliothèque :

```css
@view-transition { navigation: auto; }                /* entre deux documents */
::view-transition-old(root) { animation: 180ms var(--c-depart) both sortie-page; }
::view-transition-new(root) { animation: 320ms var(--c-sortie) both entree-page; }
.vignette-projet { view-transition-name: projet-12; }  /* élément partagé entre deux pages */
```

- Une transition de page reste courte (300 à 500 ms au total) : on navigue souvent.
- L'**élément partagé** (la vignette qui devient l'image de la page) est la transition la plus parlante : elle dit « c'est le même objet ».
- Chaque `view-transition-name` est unique dans la page.
- C'est une amélioration progressive : sans prise en charge, la navigation est simplement immédiate.

## 11. Micro-interactions

Les états qui donnent la sensation de qualité :

- **Appui** : tout élément cliquable réagit à l'instant où on le presse, `scale(0.97)` en 100–140 ms, ou un enfoncement de 1 px. C'est le retour le plus important de tous.
- **Survol** : changement de fond ou de couleur en 120–180 ms. Déplacement minime (1–2 px) ou aucun. Limité aux appareils qui survolent (voir section 14).
- **Focus clavier** : anneau visible, immédiat, sans animation d'apparition.
- **Lien** : soulignement qui se trace (`scale` sur un pseudo-élément, origine à gauche à l'entrée, à droite à la sortie).
- **Bouton avec flèche** : la flèche avance de 3–4 px au survol.
- **Chargement** : le bouton garde sa largeur, le libellé cède la place à un indicateur ; au-delà d'une seconde, un squelette du contenu plutôt qu'une roue.
- **Réussite** : une confirmation visible à l'endroit même de l'action (la coche se trace, le libellé change).
- **Erreur** : le champ est marqué, le message apparaît sans faire sauter la page ; une secousse brève (2 allers-retours de 4 px, 250 ms) est acceptable une fois.
- **Compteur** : les chiffres changent en `tabular-nums` ; un roulement vertical si le nombre est le sujet.
- **Copie** : l'icône devient une coche pendant 1,5 s.

## 12. Gestes

Un geste a deux phases. **Pendant le contact**, l'élément suit le doigt exactement, sans courbe ni retard. **Au relâchement**, la physique prend le relais avec la vitesse du doigt.

- **Suivi 1:1** : écris la transformation directement, capture le pointeur (`setPointerCapture`) pour ne pas perdre le geste hors de l'élément.
- **Décision par la vitesse** : un coup bref et rapide ferme autant qu'un long glissement. Seuils de départ : distance supérieure à 35–50 % de la taille, ou vitesse supérieure à environ 0,4–0,5 px/ms.
- **Projection** : anime vers l'endroit où le geste **allait**, en projetant la vitesse, pas vers l'endroit où le doigt s'est levé.
- **Résistance aux limites** : au-delà de la borne, l'élément suit de moins en moins (effet élastique), il ne bute pas contre un mur.
- **Un seul doigt** : ignore les contacts supplémentaires pendant un glissement.
- **Axe verrouillé** : décide horizontal ou vertical après 8–10 px, puis tiens l'axe ; `touch-action: pan-y` sur un carrousel horizontal pour laisser défiler la page.
- **Toujours une autre voie** : tout ce qu'on fait par un geste se fait aussi par un bouton et au clavier.
- **Ne réinvente pas** un tiroir, une feuille mobile ou un glisser-déposer : utilise une bibliothèque éprouvée (Vaul, dnd-kit, Embla) et règle-la.

## 13. Le moment signature

Un site peut avoir **un** moment de mouvement mémorable, lié à l'idée. Il se place à l'ouverture, ou sur l'action principale, ou au passage d'une page à l'autre. Exemples :

- le titre se compose lettre par lettre comme sur une machine (site « document ») ;
- un rideau de couleur se lève sur la première image (site « éditorial ») ;
- le trait d'un plan se dessine (`stroke-dashoffset`) ;
- un objet du métier suit le défilement et change d'état ;
- le prix se calcule sous les yeux du visiteur ;
- la page tourne comme un feuillet.

Règles : il dure moins de 1,2 s s'il précède le contenu ; il ne retarde jamais l'affichage du texte principal de plus de 300 ms ; il ne rejoue pas à chaque page ; il a une version réduite ; **pas d'écran de chargement inventé** pour le mettre en scène.

Tout le reste du site est sobre, pour qu'il reste le seul.

## 14. Mouvement réduit et tactile

**Mouvement réduit** : certaines personnes ont des nausées ou des vertiges devant les grands mouvements. Réduire ne veut pas dire tout supprimer : on garde les fondus et les changements de couleur qui aident à comprendre, on retire les déplacements, les zooms, la parallaxe, les rotations, les boucles.

Écris les mouvements **dans** la requête positive, pour que le défaut soit calme :

```css
@media (prefers-reduced-motion: no-preference) {
  .carte { transition: transform var(--d-2) var(--c-sortie); }
}
/* filet de sécurité global */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important; animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important; scroll-behavior: auto !important;
  }
}
```

Avec Motion : `<MotionConfig reducedMotion="user">`, ou `useReducedMotion()` pour choisir une variante en fondu.

**Vidéo et boucles** : toute animation qui dure plus de 5 secondes a un bouton de pause. Pas de lecture automatique avec le son. Rien ne clignote plus de trois fois par seconde.

**Tactile** : sur un écran tactile, le survol reste collé après le toucher. Garde les effets de survol derrière la requête :

```css
@media (hover: hover) and (pointer: fine) {
  .carte:hover { transform: translateY(-2px); }
}
```

Aucune information, aucune action ne doit dépendre du survol.

## 15. Recettes

**Bouton**

```css
.bouton {
  transition: scale var(--d-1) var(--c-sortie), background-color var(--d-1) var(--c-douce);
}
.bouton:active { scale: 0.97; }
```

**Soulignement qui se trace**

```css
.lien { position: relative; text-decoration: none; }
.lien::after {
  content: ""; position: absolute; inset: auto 0 -0.12em 0; height: 0.06em;
  background: currentColor; scale: 0 1; transform-origin: right;
  transition: scale var(--d-2) var(--c-sortie);
}
@media (hover: hover) {
  .lien:hover::after { scale: 1 1; transform-origin: left; }
}
.lien:focus-visible::after { scale: 1 1; }
```

Pour un lien dans un texte courant, garde le soulignement natif toujours visible : celui-ci convient à la navigation.

**Accordéon sans mesurer la hauteur**

```css
.repli { display: grid; grid-template-rows: 0fr; transition: grid-template-rows var(--d-3) var(--c-sortie); }
.repli[data-ouvert] { grid-template-rows: 1fr; }
.repli > * { overflow: hidden; min-height: 0; }
```

**Titre révélé ligne par ligne** (chaque ligne dans un conteneur qui masque)

```css
.ligne { display: block; overflow: clip; }
.ligne > span {
  display: block;
  animation: monter var(--d-4) var(--c-longue) both;
  animation-delay: calc(var(--i) * 90ms);
}
@keyframes monter { from { translate: 0 105%; } }
```

Le titre reste un seul élément lisible par les lecteurs d'écran : mets le texte complet dans `aria-label` du titre et `aria-hidden="true"` sur les lignes découpées, ou découpe sans dupliquer.

**Image dévoilée**

```css
.devoile { clip-path: inset(0 0 100% 0); transition: clip-path 900ms var(--c-longue); }
.devoile.visible { clip-path: inset(0); }
.devoile img { scale: 1.12; transition: scale 1400ms var(--c-longue); }
.devoile.visible img { scale: 1; }
```

**Indicateur d'onglet qui glisse** : un seul élément positionné en absolu, déplacé par `translate` et redimensionné par `scale` (ou `layoutId` avec Motion), jamais un fond qui saute d'onglet en onglet.

**Apparition à la descente, robuste**

```js
document.documentElement.classList.add("js");
const vu = new IntersectionObserver((entrees) => {
  for (const e of entrees) if (e.isIntersecting) { e.target.classList.add("visible"); vu.unobserve(e.target); }
}, { rootMargin: "0px 0px -15% 0px" });
document.querySelectorAll("[data-apparait]").forEach((el) => vu.observe(el));
```

```css
@media (prefers-reduced-motion: no-preference) {
  .js [data-apparait] { opacity: 0; translate: 0 16px;
    transition: opacity var(--d-4) var(--c-sortie), translate var(--d-4) var(--c-sortie); }
  .js [data-apparait].visible { opacity: 1; translate: 0 0; }
}
```

**Suivi du pointeur lissé** (décoratif seulement)

```js
const x = useMotionValue(0);
const xLisse = useSpring(x, { stiffness: 150, damping: 18 });
```

## 16. Mise au point

- **Ralentis** : multiplie les durées par 5, ou utilise le panneau Animations des outils de développement. Les défauts invisibles à vitesse réelle apparaissent : origine fausse, propriétés désynchronisées, deux états superposés.
- **Image par image** pour vérifier les enchaînements.
- **Bride le processeur** (×4, ×6) et le réseau : c'est l'expérience d'un téléphone moyen.
- **Sur un vrai téléphone** pour tout ce qui est geste.
- **Revois le lendemain** : l'œil frais voit ce que l'habitude masquait.
- **Coupe le mouvement** (mouvement réduit activé) : le site fonctionne-t-il et reste-t-il beau ?
- **Enregistre l'écran** et regarde sans le son de tes propres clics : le mouvement attire-t-il l'œil au bon endroit ?

## 17. Fautes fréquentes

| Faute | Correction |
| --- | --- |
| `transition: all 0.3s ease` | propriétés nommées, durée et courbe du système |
| Entrée depuis `scale(0)` | `scale(0.96)` et `opacity: 0` |
| `ease-in` sur une apparition | courbe de décélération |
| Menu qui grandit depuis son centre | `transform-origin` sur le déclencheur |
| Entrée et sortie de même durée | sortie à 60–75 % |
| Chaque section qui apparaît en glissant | deux ou trois moments par page |
| Cascade de 12 éléments à 100 ms | plafonner le total à 500 ms |
| Images clés sur un élément interactif | transition ou ressort |
| Animation de `height`, `width`, `top`, `left` | `transform`, `clip-path`, `grid-template-rows` |
| Rebond sur une interface sérieuse | `bounce: 0` |
| Survol actif sur tactile | requête `(hover: hover) and (pointer: fine)` |
| Rien pour le mouvement réduit | variantes en fondu, filet global |
| Défilement lissé imposé par script | défilement natif |
| Parallaxe forte | 5 à 10 % d'écart au plus, ou rien |
| Écran de chargement avec pourcentage | afficher le contenu |
| Contenu invisible sans JavaScript | état caché conditionné à la classe `js` |
| Bandeau de texte qui défile sans fin | supprimer, ou pause au survol et au focus |
| Curseur personnalisé qui retarde le pointeur | pointeur natif |
| Animation jouée à chaque visite de page | une fois par session |
