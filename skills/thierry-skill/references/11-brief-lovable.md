# Brief pour Lovable (ou tout générateur de sites)

Un générateur produit du générique quand on lui demande « un beau site moderne ». Il produit du sur-mesure quand on lui donne **des décisions** : polices nommées, couleurs chiffrées, structure section par section, textes réels, interdits explicites.

## Sommaire

1. Principes d'un bon prompt
2. Le gabarit
3. Exemple rempli
4. Prompts de suite
5. Vérifier ce qui revient

---

## 1. Principes d'un bon prompt

- **Fais d'abord la fiche DA** (Temps 1 à 4 du `SKILL.md`). Le prompt n'est que sa traduction.
- **Des valeurs, pas des adjectifs** : « Fraunces 600, 96 px, interlignage 0,95, interlettrage −0,03em » au lieu de « un titre élégant ».
- **Les textes réels** dans le prompt, dans la langue du site, avec leur typographie correcte. Sinon le générateur invente des slogans.
- **Les interdits** écrits noir sur blanc : c'est ce qui empêche le retour au gabarit.
- **Une page à la fois.** Le premier prompt pose le système et la page d'accueil. Les pages suivantes arrivent dans des prompts séparés qui renvoient au système.
- **Reste dans la pile du générateur** (Lovable : React, Vite, TypeScript, Tailwind, shadcn/ui). Demande de restyler les composants existants plutôt que d'en imposer d'autres.
- **Dis ce qui est vrai et ce qui manque** : les emplacements de photos ou de témoignages à fournir sont marqués, pas inventés.
- **Longueur** : un prompt de 400 à 900 mots, structuré par titres. Trop court, il laisse le générateur décider ; trop long, il en ignore la moitié.

## 2. Le gabarit

Remplis chaque partie, supprime celles qui ne s'appliquent pas, et livre le tout dans **un seul bloc de code** prêt à coller.

```text
Construis le site de [NOM], [ce que c'est en une ligne, avec la ville].

## Idée
[La phrase métaphore.] Le visiteur doit ressentir : [trois adjectifs].
Élément signature : [le geste unique, où il apparaît, comment il se comporte sur mobile].

## Système de design (à poser dans les variables CSS et la config Tailwind avant tout composant)
Polices (Google Fonts) :
- Titres : [Nom], graisse [n]. Titre principal : clamp([min], [fluide], [max]), interlignage [n], interlettrage [n]em.
- Texte : [Nom], 400 et 600. Corps 17 px sur mobile, 19 px sur bureau, interlignage 1,6, largeur maximale 65 caractères.
- [Étiquettes : Nom mono, 12 px, capitales, interlettrage 0,08em.]
Couleurs (variables CSS, en OKLCH) :
- --fond: oklch(…) ; --surface: oklch(…) ; --encre: oklch(…) ; --encre-2: oklch(…) ; --filet: oklch(…) ; --accent: oklch(…) ; --sur-accent: oklch(…).
- L'accent sert uniquement à : [bouton principal, signature]. Nulle part ailleurs.
Forme : rayons [valeurs] ; [filets 1 px / pas d'ombre | ombres douces à deux couches] ; [texture éventuelle].
Espace : sections séparées de clamp(4rem, 10vw, 9rem) ; grille de 12 colonnes, gouttière 24 px, largeur maximale [n] px ; marges mobiles 22 px.
Mouvement : tempérament [nom]. Durée de base [n] ms, courbe cubic-bezier(0.22, 1, 0.36, 1). [Moment signature]. Aucune autre animation d'apparition. Respecter prefers-reduced-motion. Survols limités à (hover: hover).

## Page d'accueil, section par section
1. [Nom de la section] — [forme précise : colonnes, alignement, ce qui domine]. Texte : « [texte réel] ».
2. …
3. …
[Pied de page] — [contenu et dernier geste].

## Textes (à utiliser tels quels, ne rien inventer)
Titre principal : « … »
Phrase d'appui : « … »
Bouton principal : « … » → [action]
[Tous les autres textes, par section.]
Voix : [tu/vous, traits]. Typographie française : espaces insécables avant ; : ! ?, guillemets « », apostrophes courbes.

## Images
[Ce qui existe et où le placer.] Pour ce qui manque : un bloc de couleur --surface au format [4:5] avec la légende « Photo à fournir : [sujet] ». Aucune photo de banque d'images, aucune illustration générique.

## Mobile
[Ce qui change : ordre, tailles, barre d'actions fixe en bas avec « … » et « … », cibles de 44 px.] Le titre, la phrase d'appui et le bouton principal sont visibles sans défiler à 360 px de large.

## Qualité
HTML sémantique (un seul h1, header, nav, main, footer), attribut lang="[xx]". Focus clavier visible (contour 2 px --accent, décalage 3 px). Contraste du texte ≥ 4,5:1. Étiquettes visibles sur tous les champs, corps 16 px dans les champs. Images avec width, height et alt. Balises title et meta description : « … » / « … ».

## Interdits
Pas de dégradé violet ou bleu, pas de halo flou, pas de verre dépoli. Pas de grille de trois cartes avec icônes. Pas d'emoji. Pas de pastille au-dessus du titre. Pas de texte centré hors [exception]. Pas d'animation d'apparition sur chaque section. Pas d'Inter, de Poppins ni de police système en titre. Pas de témoignage, de logo client ni de chiffre inventé. Ne pas ajouter de section non listée.
```

## 3. Exemple rempli

```text
Construis le site de La Cave d'Inès, cave à vins nature et bar à dégustation à Madrid (Lavapiés).

## Idée
Le site est une étiquette de bouteille dépliée : tout est composé comme sur une belle étiquette, avec cartouches, filets doubles et mentions en petites capitales. Le visiteur doit ressentir : gourmand, cultivé, un peu insolent.
Élément signature : un grand cartouche à filet double qui encadre le titre du premier écran, repris en petit autour de chaque vin de la semaine. Sur mobile, le cartouche occupe toute la largeur moins 22 px de marge.

## Système de design (à poser dans les variables CSS et la config Tailwind avant tout composant)
Polices (Google Fonts) :
- Titres : Bodoni Moda, graisse 500, italique pour les noms de cuvées. Titre principal : clamp(2.75rem, 1.2rem + 6.6vw, 7rem), interlignage 0,96, interlettrage -0,02em.
- Texte : Jost, 400 et 500. Corps 17 px sur mobile, 19 px sur bureau, interlignage 1,6, largeur maximale 62 caractères.
- Mentions : Jost 500, 12 px, capitales, interlettrage 0,12em.
Couleurs (variables CSS, en OKLCH) :
- --fond: oklch(0.955 0.024 88) ; --surface: oklch(0.925 0.028 88) ; --encre: oklch(0.24 0.05 20) ; --encre-2: oklch(0.42 0.045 25) ; --filet: oklch(0.24 0.05 20 / 0.35) ; --accent: oklch(0.42 0.14 18) ; --sur-accent: oklch(0.955 0.024 88).
- L'accent (lie-de-vin) sert uniquement au bouton « Réserver une table » et aux millésimes. Nulle part ailleurs.
Forme : rayons 0 partout. Filets de 1 px, filet double (deux filets séparés de 3 px) pour les cartouches. Aucune ombre.
Espace : sections séparées de clamp(4rem, 10vw, 9rem) ; grille de 12 colonnes, gouttière 24 px, largeur maximale 1240 px ; marges mobiles 22 px.
Mouvement : tempérament posé. Durée de base 600 ms, courbe cubic-bezier(0.16, 1, 0.3, 1). À l'arrivée, le filet double du cartouche se trace en 900 ms, puis le titre apparaît en fondu. Aucune autre animation d'apparition. Respecter prefers-reduced-motion. Survols limités à (hover: hover).

## Page d'accueil, section par section
1. Premier écran — le cartouche occupe les colonnes 2 à 11, texte centré à l'intérieur (seule exception au texte aligné à gauche). Au-dessus du cartouche, une ligne de mentions : « VINS NATURE · LAVAPIÉS · DEPUIS 2019 ». Sous le cartouche, à gauche les horaires du jour, à droite le bouton principal.
2. Les vins de la semaine — liste de 6 lignes sur toute la largeur, séparées par des filets : millésime (accent, chiffres tabulaires), nom de cuvée en italique Bodoni, domaine et région en Jost, prix au verre aligné à droite avec points de conduite.
3. La maison — deux colonnes asymétriques : texte sur les colonnes 1 à 5, photo au format 4:5 sur les colonnes 7 à 12 qui touche le bord droit de l'écran.
4. Venir — adresse, métro, horaires de la semaine en tableau, lien « Ouvrir l'itinéraire ».
Pied de page — le nom « La Cave d'Inès » en Bodoni italique à 14vw, puis une ligne de mentions légales en petites capitales.

## Textes (à utiliser tels quels, ne rien inventer)
Titre principal : « Du vin vivant, servi sans cérémonie »
Phrase d'appui : « Soixante cuvées nature, douze au verre, et quelqu'un pour vous dire laquelle. »
Bouton principal : « Réserver une table » → ancre vers le formulaire
La maison : « Inès goûte tout, garde peu, et change la carte le jeudi. Ici on vient pour un verre et on repart avec une bouteille, ou l'inverse. »
Venir : « Calle del Ave María, 12 · Métro Lavapiés (ligne 3) »
Voix : tutoiement implicite, phrases courtes, un peu d'insolence, jamais de jargon d'œnologue. Typographie française : espaces insécables avant ; : ! ?, guillemets « », apostrophes courbes.

## Images
Une seule photo pour l'instant, section « La maison » : bloc de couleur --surface au format 4:5 avec la légende « Photo à fournir : Inès derrière le comptoir, lumière du soir ». Aucune photo de banque d'images, aucune illustration générique. Les vins de la semaine sont des données d'exemple à remplacer : les marquer comme telles dans le code.

## Mobile
La liste des vins passe sur deux lignes par vin (millésime et nom, puis domaine et prix). Barre fixe en bas avec « Réserver » et « Itinéraire », hauteur 56 px plus la zone sûre. Cibles de 44 px. Le titre, la phrase d'appui et le bouton principal sont visibles sans défiler à 360 px de large.

## Qualité
HTML sémantique (un seul h1, header, nav, main, footer), attribut lang="fr". Focus clavier visible (contour 2 px --accent, décalage 3 px). Contraste du texte ≥ 4,5:1. Étiquettes visibles sur tous les champs, corps 16 px dans les champs. Images avec width, height et alt. Balise title : « La Cave d'Inès — vins nature et bar à dégustation à Lavapiés, Madrid ». Meta description : « Soixante cuvées nature, douze au verre. Cave et bar à dégustation à Lavapiés. Ouvert du mardi au samedi. »

## Interdits
Pas de dégradé, pas de halo flou, pas de verre dépoli, pas d'ombre. Pas de grille de cartes avec icônes. Pas d'emoji. Pas de pastille au-dessus du titre. Pas de texte centré hors du cartouche. Pas d'animation d'apparition sur les sections. Pas d'Inter, de Poppins ni de police système en titre. Pas de fond noir ni de néon. Pas de témoignage, de note ni de chiffre inventé. Ne pas ajouter de section non listée.
```

## 4. Prompts de suite

Un changement par prompt, formulé avec précision.

**Ajouter une page**

```text
Ajoute la page [Nom] à l'adresse /[chemin]. Réutilise strictement les variables, polices et composants existants ; n'introduis aucune couleur, police ni rayon nouveaux.
Structure : 1. … 2. … 3. …
Textes : « … »
```

**Corriger un écart**

```text
Dans [composant], le titre est à [valeur constatée]. Remets-le à [valeur du système]. Ne modifie rien d'autre.
```

**Retirer un tic réapparu**

```text
Supprime [la grille de cartes à icônes de la section X] et remplace-la par [une liste numérotée sur deux colonnes, numéros en Bodoni Moda 48 px couleur --accent, texte en Jost 19 px]. Ne modifie aucune autre section.
```

**Régler le mobile**

```text
À 390 px de large : [constat précis]. Corrige en [solution précise]. Vérifie qu'il n'y a aucun défilement horizontal.
```

**Verrouiller le système** (à envoyer dès que la page d'accueil est juste)

```text
Le système de design actuel est validé. Pour toutes les demandes suivantes : n'ajoute aucune couleur, police, rayon, ombre ni animation qui ne soit déjà définie dans les variables. Si une demande semble l'exiger, signale-le au lieu de l'ajouter.
```

## 5. Vérifier ce qui revient

Les générateurs dérivent. Après chaque génération importante :

1. Capture le résultat avec `scripts/regard.mjs <url-de-prévisualisation>`.
2. Compare à la fiche DA : polices réellement chargées (`famillesDePolices` dans `mesures.json`), couleurs, signature présente.
3. Cherche les tics réapparus (`08-anti-generique.md`, section 1) : cartes à icônes, dégradés, sections ajoutées, textes inventés.
4. Corrige par des prompts de suite ciblés, un écart à la fois.
5. Note avec `10-revue.md`. Sous 85, continue.
