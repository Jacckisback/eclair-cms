# Composition : grille, espace, premier écran, image

## Sommaire

1. L'espace
2. La grille
3. La hiérarchie
4. Le premier écran
5. Le déroulé d'une page
6. Catalogue de sections et leurs alternatives
7. Forme : rayons, filets, surfaces
8. L'image
9. Icônes et illustration
10. Navigation et pied de page
11. Du mobile au grand écran
12. Fautes fréquentes

---

## 1. L'espace

L'espace est le matériau le moins cher et le plus luxueux. Il se règle avec une **échelle**, jamais au jugé.

```css
--e-1: 0.25rem;  --e-2: 0.5rem;   --e-3: 0.75rem;  --e-4: 1rem;
--e-5: 1.5rem;   --e-6: 2rem;     --e-7: 3rem;     --e-8: 4rem;
--e-9: 6rem;     --e-10: 8rem;    --e-11: 12rem;
/* espacements de section fluides */
--section:   clamp(4rem, 2.6rem + 6vw, 9rem);
--gouttiere: clamp(1rem, 0.65rem + 1.5vw, 2rem);
--marge:     clamp(1.25rem, 0.2rem + 4.5vw, 5rem);
```

Principes :

- **La proximité dit le lien.** Ce qui va ensemble est proche, ce qui est distinct est loin. L'espace à l'intérieur d'un groupe est toujours inférieur à l'espace entre groupes, avec un rapport d'au moins 1 à 2.
- **Progression géométrique.** Entre deux niveaux d'espacement utilisés côte à côte, saute au moins un cran de l'échelle, sinon la différence paraît une erreur.
- **Varie le rythme vertical.** Toutes les sections séparées par la même distance produisent une page monotone. Rapproche les sections liées, isole celle qui compte par un grand vide.
- **Le vide a une forme.** Regarde les blancs comme des objets : un blanc résiduel coincé entre deux blocs est une faute, un grand blanc franc est un choix.
- **Les marges extérieures** sont plus grandes que les gouttières intérieures.

## 2. La grille

- **12 colonnes** sur bureau, 6 ou 8 sur tablette, 4 sur mobile, avec gouttière fluide. Tout élément commence et finit sur une ligne de colonne.
- **Largeur du conteneur** : 1 200 à 1 360 px pour un site de contenu, jusqu'à 1 600 px pour un site d'images. Le texte courant ne dépasse jamais 65 à 75 caractères, quelle que soit la largeur de la grille.
- **Sous-grille** (`grid-template-columns: subgrid`) pour aligner le contenu de cartes voisines sur les mêmes lignes.

```css
.grille {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  column-gap: var(--gouttiere);
  width: min(100% - 2 * var(--marge), 85rem);
  margin-inline: auto;
}
```

**Casser la grille** est ce qui donne du caractère, à condition que la grille existe :

- un élément qui déborde jusqu'au bord de l'écran d'un seul côté ;
- un bloc de texte étroit décalé (colonnes 2 à 6, puis 7 à 11 à la section suivante) ;
- un chevauchement image et titre ;
- une colonne latérale fixe qui porte un index, une date, une légende ;
- une colonne laissée vide exprès.

Asymétrie plutôt que centrage : un contenu placé sur 7 colonnes sur 12 avec 5 colonnes de respiration a plus de tenue qu'un bloc centré.

## 3. La hiérarchie

À chaque écran : **un** élément dominant, un ou deux secondaires, le reste en retrait. Si tout est important, rien ne l'est.

Leviers, du plus fort au plus discret : taille, graisse, couleur et contraste, position (haut et gauche), espace autour, forme (encadré, aplat), mouvement. Utilise un ou deux leviers par niveau, pas tous : un titre grand **et** gras **et** coloré **et** encadré crie.

**Désaccentuer** est souvent plus efficace qu'accentuer : pour faire ressortir un élément, baisse le contraste de ses voisins.

**Alignements** : limite le nombre d'axes verticaux. Deux ou trois lignes de force sur lesquelles tout s'accroche. Chaque alignement « presque juste » (2 px de décalage) se voit.

**Alignement optique** : un texte et une icône, un texte et un bord arrondi, une capitale et un bloc ne s'alignent pas mathématiquement. Corrige à l'œil : remonte l'icône de 1 px, rentre le texte dans un bouton pilule, sors le guillemet.

## 4. Le premier écran

Il doit dire en cinq secondes : **ce que c'est, pour qui, quoi faire**, et porter la signature. Il contient au plus : un titre, une phrase d'appui, une action principale, une action secondaire discrète (un lien texte, jamais un second bouton), un élément de preuve ou d'image.

Dix architectures, à choisir selon l'idée, pas par habitude :

| Architecture | Description | Bon pour |
| --- | --- | --- |
| **Typographique pur** | un titre monumental, presque rien d'autre | agence, manifeste, auteur, événement |
| **Scindé asymétrique** | texte sur 5 colonnes, image sur 7 qui touche le bord | services, hôtel, artisan |
| **Image pleine avec cartouche** | photo plein écran, texte dans un bloc opaque ancré dans un coin | restaurant, tourisme, luxe |
| **Produit en scène** | l'objet ou l'interface réelle, grand, manipulable | SaaS, boutique, application |
| **Éditorial** | comme une une de journal : titre, chapô, colonnes, date | média, cabinet, association |
| **Index** | la liste du contenu est le premier écran | portfolio, catalogue, collection |
| **Démonstration** | le visiteur fait quelque chose tout de suite (champ, curseur, essai) | outil, formation, calculateur |
| **Lettre** | un texte à la première personne, signé | indépendant, fondateur |
| **Objet-document** | le premier écran imite la forme d'un document du métier | carte, billet, étiquette, plan |
| **Séquence** | une courte suite animée ou défilée qui raconte | marque à histoire, lancement |

Règles du premier écran :

- **Pas de hauteur forcée à 100 % de l'écran** par principe. Laisse dépasser le début de la section suivante pour inviter à défiler. Si tu remplis l'écran, utilise `100svh`, jamais `100vh`.
- **Le titre dit l'offre**, il ne la contourne pas. « Cours de français à Madrid, par un professeur natif » bat « Révélez votre potentiel ».
- **Une action principale**, libellée par son résultat (« Voir la carte », « Réserver une table »), pas « En savoir plus ».
- **L'image principale** est chargée en priorité, dimensionnée, et n'est jamais un carrousel.
- **Sur mobile**, titre, phrase et bouton sont visibles sans défiler sur un écran de 360 × 640.

## 5. Le déroulé d'une page

Écris le déroulé avant toute maquette. Chaque section répond à une question que le visiteur se pose à ce moment-là :

1. C'est quoi ? → premier écran
2. Est-ce pour moi ? → le problème ou le public, dit dans ses mots
3. Comment ça marche ? → le produit, la méthode, montrés
4. Pourquoi vous croire ? → preuves vraies : cas, chiffres, visages, presse
5. Combien, et comment ? → prix, modalités, étapes
6. Et si… ? → objections, questions
7. Maintenant ? → l'action, répétée simplement

Adapte l'ordre au sujet (un restaurant : ambiance, carte, infos pratiques, réserver). Supprime ce qui ne répond à aucune question.

**Rythme** : alterne trois types de sections, sans en répéter un trois fois de suite.

- **Dense** : beaucoup d'information organisée (tableau, grille, liste).
- **Ample** : une seule idée, très grande, beaucoup d'espace (citation, chiffre, image).
- **Narrative** : texte et image qui se répondent.

Fais varier aussi : la largeur (étroit, conteneur, pleine largeur), l'alignement (gauche, décalé, centré une fois), la valeur du fond (clair, teinté, sombre une fois). Une seule section « inversée » par page a de l'effet ; trois, et l'effet disparaît.

**Transitions entre sections** : pas de vagues ni de diagonales décoratives. Un changement de fond net, un filet, ou simplement de l'espace.

## 6. Catalogue de sections et leurs alternatives

Pour chaque section convenue, des formes moins attendues :

| Section convenue | Alternatives |
| --- | --- |
| Trois cartes avec icônes | liste numérotée en grand ; tableau à deux colonnes ; un seul avantage par écran avec sa preuve ; texte courant bien composé ; grille « bento » à cellules inégales si le contenu est réellement hiérarchisé |
| Bandeau de logos clients | deux ou trois cas nommés et chiffrés ; une citation longue et précise ; rien, si les logos ne sont pas vrais |
| Carrousel de témoignages | une seule citation forte en grand ; un mur de citations courtes ; un entretien court |
| Compteurs animés | un seul chiffre, immense, sourcé, avec sa phrase d'explication |
| Grille de prix à trois colonnes | tableau comparatif typographié ; un prix unique expliqué ; un calculateur |
| Questions fréquentes en accordéon | questions-réponses en deux colonnes toutes visibles ; intégrées là où l'objection surgit |
| « Notre équipe » en ronds | portraits cadrés à l'identique, format portrait, avec une phrase personnelle |
| Frise chronologique à pastilles | liste datée composée comme un index ; carte ; récit |
| Bandeau final « Prêt à démarrer ? » | une question précise, un formulaire court directement dans la page |
| Galerie en grille uniforme | mise en page de magazine : une grande, deux petites, un vide |

## 7. Forme : rayons, filets, surfaces

Choisis **une logique** et tiens-la :

- **Rayons** : une échelle de trois valeurs au plus (par exemple 4, 10, 20 px), ou zéro partout, ou pilule partout. Le choix suit les curseurs Époque et Registre.
- **Rayons concentriques** : quand un élément arrondi en contient un autre, `rayon intérieur = rayon extérieur − marge intérieure`. Sinon les courbes ne sont pas parallèles et cela se voit.
- **Filets ou ombres** : sépare soit par des filets fins, soit par des surfaces et des ombres, rarement les deux. Une carte avec bordure **et** ombre **et** fond différent est surchargée.
- **Bordures** : 1 px, encre à 10–15 %. Sur écran haute densité, un filet de 0,5 px (`border-width: 0.5px`) donne une finesse d'imprimé.
- **Cartes** : ne mets pas tout dans des cartes. Une carte est justifiée quand l'élément est cliquable, déplaçable ou réellement distinct. Le reste se sépare par l'espace et les filets.
- **Bordure intérieure des images** : `outline: 1px solid oklch(0 0 0 / 0.08); outline-offset: -1px` empêche une image claire de se fondre dans un fond clair.

## 8. L'image

**Décide d'abord s'il en faut.** Un site typographique sans image est préférable à un site illustré de photos de banque d'images.

Ordre de préférence :

1. **Photos réelles du client**, même imparfaites, recadrées et traitées de façon cohérente.
2. **Le produit ou le travail lui-même** : captures vraies, objets, réalisations.
3. **Illustration ou schéma sur mesure** en SVG, dans les couleurs du site.
4. **Typographie et forme** comme image : un chiffre, un mot, un motif.
5. **Image générée**, si un outil est disponible, avec une direction précise (sujet, cadrage, lumière, palette, grain) et le même traitement pour toute la série. Jamais pour représenter un vrai lieu, une vraie personne ou un vrai produit du client.
6. **Banque d'images** en dernier recours, choisie contre le cliché.

**Un traitement unique** fait d'images disparates une série : même rapport de format, même température, même contraste, éventuellement bichromie ou grain. En CSS : `filter: saturate(0.9) contrast(1.05)`, ou un calque de couleur en `mix-blend-mode: multiply`.

**Cadrage** : serré plutôt que large ; le détail (une main, une matière, un outil) en dit plus que la vue d'ensemble. Fixe le point d'intérêt avec `object-position` pour que le recadrage mobile ne coupe pas le sujet.

**Formats** : limite-toi à deux ou trois rapports (4:5, 3:2, 16:9, 1:1) déclarés avec `aspect-ratio`.

**Technique** : AVIF ou WebP ; `srcset` et `sizes` ; attributs `width` et `height` toujours présents ; `loading="lazy"` sauf pour l'image du premier écran, qui reçoit `fetchpriority="high"` ; `alt` qui décrit ce que l'image apporte, ou `alt=""` si elle est décorative.

**Emplacements sans image** : si le client n'a pas fourni ses photos, pose un bloc à la couleur de surface, au bon format, avec une légende visible « Photo à fournir : [sujet, cadrage] ». Pas une image d'emprunt trompeuse.

## 9. Icônes et illustration

- **Une seule famille d'icônes**, même épaisseur de trait, même grille, même style de terminaison (Lucide, Phosphor, Tabler, ou dessinées).
- **L'épaisseur du trait** s'accorde à la graisse du texte voisin : 1,5 px avec un texte en 400, 2 px avec du 500–600.
- **Taille** : alignée sur la hauteur de ligne du texte (16, 20 ou 24 px), alignement optique vérifié.
- Une icône **accompagne** un libellé, elle ne le remplace pas (sauf loupe, fermer, menu, toujours avec un nom accessible).
- **N'illustre pas une idée abstraite par une icône** (« innovation » = ampoule). Si le concept est abstrait, écris-le bien.
- **Pas d'emoji** comme icônes d'interface.
- Les illustrations suivent la palette et un seul style de trait ; mieux vaut une seule illustration forte que dix vignettes.

## 10. Navigation et pied de page

**Navigation**

- Cinq entrées au plus au premier niveau, libellées par des mots du visiteur.
- Une action principale à droite, visuellement distincte. Elle peut être la même que celle du premier écran : dans ce cas, elle reste discrète dans l'en-tête (lien ou contour) tant que le bouton du premier écran est visible, pour ne pas montrer deux boutons pleins identiques.
- L'entrée courante est marquée (`aria-current="page"` et un signe visible).
- Fixe en haut seulement si le site est long et que l'action doit rester accessible ; dans ce cas compacte, et qui se masque à la descente pour réapparaître à la remontée.
- Sur mobile : un menu plein écran lisible, grandes cibles, ou une barre d'actions en bas pour les deux gestes essentiels (appeler, réserver). Un site d'une seule page courte peut se passer de menu : les ancres en ligne et l'action suffisent.
- La navigation est un lieu d'expression : sa composition peut porter la signature (un index numéroté, une ligne d'horaires, une date).

**Pied de page**

C'est la dernière impression. Il contient l'utile (adresse, contact, horaires, liens légaux, langues) et peut finir sur un geste : le nom en très grand, une phrase, un détail du métier. Composé sur la grille, pas quatre colonnes de liens par défaut.

## 11. Du mobile au grand écran

Conçois les deux extrêmes en même temps, puis les intermédiaires.

- **Seuils** dictés par le contenu : ajoute un point de rupture là où la mise en page casse, pas à des largeurs d'appareils. Repères usuels : 480, 768, 1 024, 1 280, 1 536 px.
- **Requêtes de conteneur** (`@container`) pour les composants réutilisés à différentes largeurs.
- **Le mobile n'est pas un bureau empilé** : change l'ordre si nécessaire, raccourcis les titres, transforme un tableau en liste de fiches, un survol en toucher.
- **Marges mobiles** : 20 à 24 px. À 16 px le site paraît à l'étroit, à 12 px négligé.
- **Cibles tactiles** : 44 × 44 px visés, 24 × 24 px au minimum absolu, espacées de 8 px.
- **Pouce** : les actions principales dans la moitié basse de l'écran.
- **Très grand écran** (au-delà de 1 600 px) : le conteneur se limite, les fonds et les images peuvent s'étendre, les corps de texte plafonnent.
- **Paysage et petits écrans hauts de 600 px** : vérifie que rien de fixe ne mange l'écran.
- **Zoom à 200 %** et texte agrandi : la mise en page tient, rien n'est coupé.
- **Langues** : l'allemand allonge les textes d'environ 30 %, le français et l'espagnol de 15 à 25 % par rapport à l'anglais. Aucun bouton à largeur fixe, aucun titre qui ne tient que dans une langue.

## 12. Fautes fréquentes

| Faute | Correction |
| --- | --- |
| Tout centré | aligner à gauche sur la grille, décaler |
| Même espace entre toutes les sections | rapprocher les liées, isoler la principale |
| Espaces arbitraires (13, 22, 37 px) | l'échelle, et rien qu'elle |
| Tout dans des cartes | espace et filets ; cartes pour l'interactif |
| Carte avec bordure, ombre et fond | un seul moyen de séparation |
| Premier écran à 100vh qui cache la suite | hauteur libre ou `svh`, amorce de la section suivante |
| Carrousel en tête de page | une image, un message |
| Image étirée ou écrasée | `aspect-ratio` et `object-fit: cover` |
| Icônes de familles mélangées | une seule famille, un seul trait |
| Rayons non concentriques | intérieur = extérieur − marge |
| Mobile = bureau rétréci | recomposer, réordonner |
| Grille de 3 × 2 blocs identiques | hiérarchiser : un grand, des petits |
| Alignements à 2 px près | une ligne de force, tout dessus |
