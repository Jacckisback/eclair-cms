# Revue : noter et critiquer un rendu

Une critique utile est **vue** (sur capture, pas seulement sur code), **chiffrée**, **classée** et **actionnable**.

## Sommaire

1. Préparer la revue
2. La grille sur 100
3. Lire les mesures de `regard.mjs`
4. Les cinq tests visuels
5. Rédiger la revue
6. Barème et décision
7. Exemple de revue

---

## 1. Préparer la revue

1. **Lance le site** ou ouvre le fichier, puis capture :

   ```bash
   node <dossier-du-skill>/scripts/regard.mjs http://localhost:3000 --out revue
   node <dossier-du-skill>/scripts/regard.mjs http://localhost:3000/tarifs --out revue/tarifs
   node <dossier-du-skill>/scripts/audit.mjs ./src
   ```

   Capture au moins la page d'accueil, une page de contenu et une page avec formulaire. Options de `regard.mjs` : `--sombre` (thème sombre), `--largeurs 320,360,1920`, `--hauteur 640`, `--attente 2000` (millisecondes après chargement : à augmenter s'il y a une animation d'ouverture, sinon elle est photographiée en cours).

   Le script vérifie aussi ce qui reste animé ou invisible en mouvement réduit (`mouvementReduit` dans `mesures.json`). Il **ne teste pas** le focus clavier, les états de formulaire ni les lecteurs d'écran : parcours la page au clavier et envoie le formulaire toi-même. Sur un fichier local, `poidsPage` est vide : le poids ne se mesure que sur une URL.

2. **Ouvre chaque image** produite : `*-ecran.png` (premier écran), `*-tranche-N.png` (la page découpée, lisible à taille réelle), `*-page.png` (vue d'ensemble pour le rythme), `*-gris.png`, `*-flou.png`, et lis `mesures.json`.

3. **Lis le code** des points que les captures désignent, pas tout le dépôt.

Si tu ne peux ni lancer ni capturer, dis-le en tête de revue (« Revue sur code seul, non vérifiée à l'écran ») et baisse ta confiance : ne note pas les axes visuels au-dessus de ce que le code permet d'affirmer.

## 2. La grille sur 100

| Axe | Points | Ce qu'on juge |
| --- | --- | --- |
| **Idée et personnalité** | 20 | Y a-t-il un concept ? Une signature ? Le site pourrait-il appartenir à un concurrent ? |
| **Typographie** | 15 | Choix des polices, échelle, réglages, lisibilité, micro-typographie |
| **Composition** | 15 | Hiérarchie, grille, rythme, espace, premier écran, adaptation mobile |
| **Couleur et forme** | 10 | Palette tirée du sujet, contrastes, cohérence des rayons, filets, ombres |
| **Contenu et voix** | 10 | Titres précis, textes vrais, preuves réelles, typographie de la langue |
| **Image** | 5 | Pertinence, traitement commun, cadrage, qualité technique |
| **Mouvement** | 5 | Utilité, tempérament, justesse des courbes et durées, mouvement réduit |
| **Finitions** | 10 | États, focus, formulaires, détails, résistance aux contenus réels |
| **Accessibilité et performance** | 10 | Contrastes mesurés, clavier, sémantique, poids, stabilité |

Repères par axe :

**Idée et personnalité (20)**

- 0–5 : gabarit reconnaissable, aucun parti pris.
- 6–10 : propre, cohérent, interchangeable.
- 11–15 : un caractère perceptible, mais pas de signature nette ou une idée non tenue jusqu'au bout.
- 16–20 : concept clair, signature mémorable, tenue du premier écran au pied de page. Le test du logo échangé échoue pour un concurrent.

**Typographie (15)**

- 0–4 : police par défaut, tailles arbitraires, réglages absents.
- 5–9 : police correcte, échelle approximative, grands titres mal réglés.
- 10–12 : choix juste, échelle tenue, quelques réglages manquants.
- 13–15 : la page est belle en texte seul ; interlignages et interlettrages réglés par rôle ; typographie de la langue correcte.

**Composition (15)**

- 0–4 : tout centré, sections identiques, pas de hiérarchie.
- 5–9 : lisible, sans rythme ; mobile empilé.
- 10–12 : hiérarchie nette, grille tenue, rythme correct.
- 13–15 : un dominant par écran, asymétrie maîtrisée, rythme varié, mobile recomposé.

**Couleur et forme (10)** : 0–3 palette par défaut ou incohérente ; 4–6 correcte et neutre ; 7–8 tirée du sujet, accent maîtrisé ; 9–10 palette reconnaissable, contrastes exacts, logique de forme unique.

**Contenu et voix (10)** : 0–3 texte de remplissage ou slogans creux ; 4–6 clair et banal ; 7–8 précis, avec preuves ; 9–10 une voix identifiable, chaque titre dit un fait, typographie irréprochable.

**Image (5)** : 0–1 banque d'images ou incohérence ; 2–3 correct ; 4–5 images vraies ou pertinentes, traitement commun. Un site sans image par choix peut avoir 5.

**Mouvement (5)** : 0–1 décoratif et envahissant, ou fautes techniques ; 2–3 correct ; 4–5 rare, juste, accordé au tempérament, version réduite. Un site presque immobile par choix peut avoir 5.

**Finitions (10)** : 0–3 états manquants, débordements ; 4–6 l'essentiel ; 7–8 tous les états, formulaires soignés ; 9–10 résiste aux pires contenus, détails optiques réglés.

**Accessibilité et performance (10)** : 0–3 contrastes en échec, clavier impossible ; 4–6 fautes ponctuelles ; 7–8 conforme sur les points mesurés ; 9–10 conforme, léger, stable.

**Quand le contenu du client manque** (brief d'une phrase, photos et prix à venir) : ne compense pas par du décor et n'invente rien. Note **Image** à 4 si les emplacements sont au bon format, légendés « à fournir » et que la composition tient sans eux ; note **Contenu et voix** sur ce qui est écrit, sans pénaliser les faits marqués « à confirmer ». Indique en tête de revue « noté hors contenus à fournir » et joins la liste des manques. Ajouter un second élément graphique pour gagner un point contredit la loi du geste unique.

**Plafonds** : quelle que soit la somme, la note est plafonnée à **60** si le texte courant est sous 4,5:1 de contraste ou si le site est inutilisable au clavier ; à **70** s'il n'y a aucune idée identifiable ; à **75** si le contenu est du remplissage.

## 3. Lire les mesures de `regard.mjs`

`mesures.json` contient, pour chaque largeur :

| Champ | Seuil | Si dépassé |
| --- | --- | --- |
| `debordementHorizontal` | `false` | chercher l'élément le plus large (listé dans `elementsTropLarges`) |
| `contrastes.echecs` | 0 | chaque entrée donne le texte, les couleurs et le rapport mesuré |
| `ciblesTropPetites` | 0 sous 24 px ; aussi peu que possible sous 44 px | agrandir la zone cliquable |
| `famillesDePolices` | ≤ 3 | une famille en trop, ou une police de repli qui s'affiche à la place de la bonne |
| `taillesDeTexte` | ≤ 8 | tailles hors échelle |
| `lignesTropLongues` | 0 au-delà de 80 caractères | `max-width: 65ch` |
| `texteTropPetit` | 0 sous 12 px | remonter le corps |
| `imagesSansDimensions` | 0 | ajouter `width` et `height` |
| `imagesSansAlt` | 0 | ajouter `alt` (vide si décorative) |
| `titres.h1` | 1 | un seul `<h1>` |
| `titres.sauts` | 0 | niveaux de titres sans saut |
| `champsSansEtiquette` | 0 | relier une étiquette |
| `langue` | présent | attribut `lang` sur `<html>` |
| `poidsPage.total` | selon le type | voir le budget dans `07-finitions.md` |
| `clsApprox` | ≤ 0,1 | dimensions manquantes, polices, contenu inséré |

Les mesures sont des **indices**, pas des verdicts : une taille de texte en plus peut être une correction optique voulue ; un contraste « en échec » sur un texte posé sur une image doit être vérifié à l'œil ; une famille de police « en trop » peut être la monospace voulue. Chaque alerte est traitée ou justifiée.

## 4. Les cinq tests visuels

À faire sur les captures, dans cet ordre :

1. **Cinq secondes** (`*-ecran.png` bureau puis mobile) : regarde, détourne les yeux, écris ce que tu as retenu. Est-ce l'offre et l'idée ? Sait-on quoi faire ?
2. **Yeux plissés** (`*-flou.png`) : combien de masses distinctes ? Il doit y avoir un dominant, un ou deux secondaires. Si tout a le même poids, la hiérarchie est plate.
3. **Niveaux de gris** (`*-gris.png`) : la hiérarchie tient-elle ? Le bouton principal ressort-il encore ? Sinon, elle reposait sur la teinte seule.
4. **Logo échangé** : le site pourrait-il appartenir à un concurrent direct ? À une entreprise d'un autre secteur ?
5. **Sans mouvement** : la capture, qui est immobile, est-elle belle ? Le mouvement ne sauve pas une composition faible.

Complément utile : compare la capture bureau et la capture mobile. La signature est-elle encore là sur mobile ?

## 5. Rédiger la revue

Structure imposée :

```markdown
## Revue — [Nom du site]

**Note : 72 / 100** — [une phrase de verdict]
Vérifié à l'écran : oui (390, 768, 1440 px) | non (code seul)

| Axe | Note | En un mot |
| --- | --- | --- |
| Idée et personnalité | 11 / 20 | … |
| … | | |

### Les trois corrections qui changent tout
1. …
2. …
3. …

### Détail

| Priorité | Où | Constat | Correction exacte | Pourquoi |
| --- | --- | --- | --- | --- |
| A | `Hero.tsx:24` | Titre à 48 px, interlignage 1,5 | `font-size: clamp(2.75rem, 1.4rem + 5.6vw, 6.5rem); line-height: 0.98; letter-spacing: -0.035em` | Le titre porte l'idée, il doit dominer l'écran |

### À garder
- …
```

Règles de rédaction :

- **Un tableau**, jamais une liste de « avant / après » empilés.
- **Priorité A** : casse l'idée ou l'usage (pas de concept, contraste en échec, action introuvable, débordement mobile). **B** : affaiblit nettement. **C** : finition.
- **Correction exacte** : la valeur, la propriété, le fichier et la ligne. Jamais « améliorer le contraste », toujours « passer `--encre-2` de `oklch(0.7 …)` à `oklch(0.5 …)` : 3,1:1 → 6,2:1 ».
- **Pourquoi** : le principe, en une ligne, relié à l'idée du site.
- **Les trois corrections qui changent tout** en tête : le lecteur pressé n'ira pas plus loin.
- **À garder** : ce qui est réussi, pour qu'une correction ne le détruise pas.
- **Pas de flatterie**, pas de sévérité de posture. Si c'est bon, dis-le en une ligne.
- Au-delà de quinze lignes au tableau, regroupe : une ligne par famille de problème avec le nombre d'occurrences.

En mode **Critiquer**, ne modifie rien. En mode **Polir**, applique les B et les C sans toucher au concept, puis recapture. En mode **Désintoxiquer**, suis `08-anti-generique.md`, section 10.

## 6. Barème et décision

| Note | Verdict | Suite |
| --- | --- | --- |
| 90–100 | Remarquable | livrer ; noter ce qui pourrait encore surprendre |
| 85–89 | Très bon | livrer après les corrections B rapides |
| 70–84 | Correct, pas encore à niveau | corriger les trois faiblesses majeures, recapturer |
| 50–69 | Générique ou fragile | reprendre le concept et le système |
| < 50 | À refaire | repartir du Temps 1 |

Quand c'est toi qui as construit le site : **ne t'arrête pas sous 85**. Corrige, recapture, renote. Trois tours au plus. Si la note stagne parce que l'axe Idée est faible, le problème est en amont : reviens au Temps 2. Si elle plafonne parce que le contenu du client manque, livre avec la note honnête et la liste précise de ce qui la ferait monter.

Note avec honnêteté : une note gonflée n'aide personne. Un site propre et sans idée vaut 60 à 70, pas 85.

## 7. Exemple de revue

```markdown
## Revue — Atelier Morel, ébéniste

**Note : 57 / 100** — Propre et lisible, mais pourrait être le site de n'importe quel artisan.
Vérifié à l'écran : oui (390, 768, 1440 px)

| Axe | Note | En un mot |
| --- | --- | --- |
| Idée et personnalité | 9 / 20 | aucun parti pris, gabarit reconnaissable |
| Typographie | 8 / 15 | Poppins partout, titres timides |
| Composition | 10 / 15 | claire, monotone |
| Couleur et forme | 5 / 10 | bleu et orange sans lien avec le bois |
| Contenu et voix | 6 / 10 | « qualité, proximité, réactivité » |
| Image | 4 / 5 | belles photos d'atelier, sous-exploitées |
| Mouvement | 3 / 5 | apparition sur chaque section |
| Finitions | 7 / 10 | focus absent sur les cartes |
| Accessibilité et performance | 5 / 10 | texte gris à 2,5:1 |

### Les trois corrections qui changent tout
1. Faire des photos d'atelier le cœur du site : chaque réalisation devient une fiche (essence, dimensions, durée), composée comme un bon de commande d'atelier.
2. Remplacer Poppins par Zodiak en titres (120 px au premier écran) et Supreme en texte.
3. Tirer la palette du bois : fond oklch(0.96 0.02 80), encre oklch(0.24 0.03 50), accent laiton oklch(0.68 0.12 80).

### Détail

| Priorité | Où | Constat | Correction exacte | Pourquoi |
| --- | --- | --- | --- | --- |
| A | `Hero.tsx:12` | Titre « Votre artisan de confiance » | « Meubles en chêne massif, dessinés et fabriqués à Annecy » | Le titre dit le métier, la matière et le lieu |
| A | `globals.css:8` | `--muted: #9ca3af` sur blanc, 2,5:1 | `oklch(0.5 0.02 60)`, 6,1:1 | Texte courant illisible |
| B | `Services.tsx` | Trois cartes à icônes | Liste de réalisations en fiches, photo 4:5 | Montrer le travail plutôt que le décrire |
| B | `Reveal.tsx` | Chaque section glisse à l'apparition | Garder l'effet sur la première photo seulement | Le mouvement doit rester rare |
| C | `Button.tsx:5` | `transition: all .3s` | `transition: scale 120ms var(--c-sortie), background-color 120ms` | Propriétés nommées, réponse immédiate |

### À garder
- Les photos de l'atelier, prises en lumière naturelle.
- Le formulaire de devis, court et clair.
```
