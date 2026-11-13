# Couleur

Une palette réussie contient peu de couleurs et beaucoup de décisions. La plupart des beaux sites tiennent en : un fond, une encre, un accent, et une rampe de neutres teintés.

## Sommaire

1. Travailler en OKLCH
2. D'où vient la couleur
3. La structure d'une palette
4. Les neutres teintés
5. L'accent
6. Proportions
7. Contraste et accessibilité
8. Mode sombre
9. Dégradés, ombres, transparences
10. Couleurs d'état
11. Fautes fréquentes

---

## 1. Travailler en OKLCH

`oklch(clarté chroma teinte)` : la clarté (0 à 1) correspond à ce que l'œil perçoit, ce qui n'est pas vrai en HSL. On peut donc construire des rampes régulières et prévoir les contrastes.

- **Clarté (L)** : 0 noir, 1 blanc. Deux couleurs de même L ont la même luminosité apparente.
- **Chroma (C)** : 0 gris, environ 0,37 au maximum. Au-delà de 0,25, la couleur sort souvent de l'espace sRGB ; le navigateur la ramène, vérifie le rendu.
- **Teinte (H)** : 0–360. Repères : rouge 25, orange 55, jaune 95, vert 145, turquoise 195, bleu 255, violet 300, rose 350.

```css
:root {
  --fond:    oklch(0.975 0.008 160);   /* exemple : la teinte vient du sujet */
  --encre:   oklch(0.22 0.025 170);
  --accent:  oklch(0.5 0.13 155);
  /* dérivations sans nouvelle valeur à maintenir */
  --accent-doux:  color-mix(in oklch, var(--accent) 14%, var(--fond));
  --encre-2:      color-mix(in oklch, var(--encre) 68%, var(--fond));
  --filet:        color-mix(in oklch, var(--encre) 14%, var(--fond));
  --accent-appui: oklch(from var(--accent) calc(l - 0.06) c h);
}
```

## 2. D'où vient la couleur

Jamais d'un générateur de palettes. Cherche dans cet ordre :

1. **La marque existante** : logo, enseigne, emballage.
2. **La matière du sujet** : la couleur réelle du produit, du lieu, des outils (le vert d'une bouteille, l'ocre d'un mur, le bleu d'un bleu de travail).
3. **Le territoire** : lumière, pierre, mer, végétation du lieu.
4. **La culture du métier** : le rouge des reliures juridiques, le jaune des chantiers, le vert des tableaux d'école.
5. **Les photos du client** : prélève les deux teintes dominantes et construis autour.
6. **Le sentiment** visé, en dernier recours.

Puis **écarte la couleur attendue du secteur** si tous les concurrents la portent (bleu pour la finance, vert pour le bien-être) ou garde-la en la déplaçant franchement : plus sombre, plus sourde, plus saturée, ou cantonnée à un détail.

## 3. La structure d'une palette

| Rôle | Nombre | Remarques |
| --- | --- | --- |
| Fond | 1 à 3 niveaux | fond de page, surface, surface surélevée ; écart de clarté 0,02 à 0,04 |
| Encre | 3 niveaux | principal, secondaire, discret |
| Filets et bordures | 1 à 2 | tirés de l'encre, opacité ou mélange |
| Accent | 1 | plus ses dérivés : survol, appui, fond doux, texte sur accent |
| Seconde couleur | 0 ou 1 | seulement si l'idée l'exige ; rôle strictement défini |
| États | 4 | succès, alerte, erreur, information ; accordés à la palette |

Nomme les variables par **rôle** (`--fond`, `--encre`, `--accent`, `--filet`) et non par teinte (`--bleu-500`). Le mode sombre et les thèmes ne changent alors que les valeurs.

## 4. Les neutres teintés

Un gris pur (`chroma 0`) est mort à l'écran. Teinte tous les neutres dans la famille de l'accent ou dans sa complémentaire, avec une chroma de 0,004 à 0,025.

Rampe type, à teinte constante. L'exemple utilise la teinte 85 : remplace-la par celle de ta palette, les clartés restent valables.

| Variable | Clair | Usage |
| --- | --- | --- |
| `--n-0` | oklch(0.99 0.006 85) | surface surélevée |
| `--n-1` | oklch(0.975 0.01 85) | fond de page |
| `--n-2` | oklch(0.95 0.012 85) | fond alterné |
| `--n-3` | oklch(0.9 0.012 85) | filets |
| `--n-4` | oklch(0.8 0.012 85) | bordures de champs |
| `--n-5` | oklch(0.62 0.014 85) | texte discret sur fond clair (limite 4,5:1) |
| `--n-6` | oklch(0.48 0.016 85) | texte secondaire |
| `--n-7` | oklch(0.34 0.018 85) | texte |
| `--n-8` | oklch(0.22 0.02 85) | encre principale |
| `--n-9` | oklch(0.15 0.02 85) | fond sombre |

Ni blanc pur ni noir pur : un fond à L 0,975–0,99 et une encre à L 0,15–0,25 donnent un contraste confortable et une matière.

## 5. L'accent

- **Un seul**, et rare. Sa force vient de sa rareté : il désigne l'action principale et la signature, pas la décoration.
- **Clarté 0,5 à 0,65** s'il doit porter du texte blanc ; **0,75 à 0,9** s'il porte du texte sombre (jaunes, verts acides). Vérifie le contraste du libellé du bouton.
- **Chroma selon le curseur Contraste** : 0,08–0,12 pour un site doux, 0,14–0,18 standard, 0,2+ pour un site graphique.
- **Dérive ses variantes** par calcul : survol (L −0,04), appui (L −0,08), fond doux (12–16 % dans le fond), filet (40 %).

Si tu as besoin d'une deuxième couleur, choisis une relation nette : complémentaire (teinte +180) pour la tension, analogue (±30) pour l'harmonie, ou un neutre très coloré. Et donne-lui un rôle unique, par exemple « uniquement les étiquettes de catégorie ».

## 6. Proportions

La règle 60 / 30 / 10 est un bon point de départ : 60 % de fond, 30 % d'encre et de surfaces, 10 % d'accent. Deux écarts volontaires donnent de la personnalité :

- **L'accent en aplat** : une section entière, ou tout le premier écran, dans la couleur d'accent. Une fois par page.
- **L'accent infime** : 1 à 2 % de la surface, sur un point précis. Très fort sur une page presque monochrome.

Ce qui affadit un site : l'accent partout à petite dose (liens, icônes, bordures, fonds, titres) : il ne désigne plus rien.

## 7. Contraste et accessibilité

Seuils WCAG 2.2, niveau AA :

| Élément | Rapport minimal |
| --- | --- |
| Texte courant | 4,5:1 |
| Grand texte (≥ 24 px, ou ≥ 18,66 px en gras) | 3:1 |
| Composants et états (bordure de champ, icône seule, focus) | 3:1 |
| Texte désactivé, logo, décor | pas d'exigence |

Vise 7:1 pour le texte long. `scripts/regard.mjs` mesure les contrastes réels de la page rendue.

- **Ne transmets jamais une information par la couleur seule** : ajoute une icône, un libellé, un soulignement, une forme.
- **Texte sur image** : jamais directement. Voile dégradé sombre ou clair, aplat derrière le texte, ou zone calme de la photo. Vérifie le contraste au point le plus défavorable.
- **Texte sur couleur** : pas de gris. Prends l'encre mélangée à la couleur du fond (`color-mix`), ou la couleur du fond très assombrie ou éclaircie.
- **Daltonisme** : évite d'opposer rouge et vert à clarté égale ; fais varier la clarté.

## 8. Mode sombre

Un mode sombre n'est pas une inversion. Règles :

- **Fond** : jamais noir pur. L 0,14–0,2, teinté (chroma 0,008–0,02).
- **Surfaces** : plus claires quand elles sont plus proches de l'œil (L +0,03 par niveau). En sombre, l'élévation se lit par la clarté, pas par l'ombre.
- **Encre** : L 0,9–0,94, pas blanc pur. Texte secondaire L 0,7–0,75.
- **Accent** : remonte la clarté de 0,08–0,15 et baisse la chroma de 10–20 %, sinon il vibre.
- **Filets** : encre à 10–14 % d'opacité.
- **Images** : baisse légèrement leur luminosité (`filter: brightness(0.9)`) si elles éblouissent.
- **Graisses** : le texte clair sur sombre paraît plus gras ; descends d'un cran si la police est variable.

```css
:root { color-scheme: light dark; }
@media (prefers-color-scheme: dark) {
  :root {
    --fond:   oklch(0.17 0.012 85);
    --encre:  oklch(0.93 0.012 85);
    --accent: oklch(0.72 0.14 35);
  }
}
```

Décide si le site **a besoin** d'un mode sombre. Un site dont l'idée est « papier » peut n'en proposer aucun ; un site dont l'idée est « nuit » peut être sombre uniquement. Un seul thème parfaitement réglé vaut mieux que deux approximatifs.

## 9. Dégradés, ombres, transparences

**Dégradés.** Utiles pour une lumière, un voile, une profondeur ; presque jamais comme décor principal.

- Interpole en OKLCH pour éviter la zone grise au milieu : `linear-gradient(in oklch, …)`.
- Reste dans une plage de teinte étroite (±40) ou d'une couleur vers sa version plus claire.
- Un fondu vers le transparent sur une image doit être **adouci** : plusieurs arrêts suivant une courbe, sinon on voit une barre.

```css
.voile {
  background: linear-gradient(to top,
    oklch(0.15 0.02 60 / 0.82) 0%,
    oklch(0.15 0.02 60 / 0.6) 19%,
    oklch(0.15 0.02 60 / 0.38) 37%,
    oklch(0.15 0.02 60 / 0.2) 54%,
    oklch(0.15 0.02 60 / 0.08) 70%,
    oklch(0.15 0.02 60 / 0.02) 85%,
    transparent 100%);
}
```

**Ombres.** Une ombre est de la lumière absente, donc colorée par le fond, jamais noire.

```css
--ombre-1: 0 1px 2px oklch(0.2 0.02 60 / 0.06), 0 2px 6px oklch(0.2 0.02 60 / 0.05);
--ombre-2: 0 1px 2px oklch(0.2 0.02 60 / 0.05), 0 6px 16px oklch(0.2 0.02 60 / 0.07),
           0 18px 40px oklch(0.2 0.02 60 / 0.06);
```

Plusieurs couches superposées, de plus en plus larges et faibles. Une seule direction de lumière sur tout le site. Sur un site à ornement 0–3 : pas d'ombre du tout, des filets.

**Transparences.** Le flou d'arrière-plan (`backdrop-filter`) se justifie pour une barre posée sur un contenu qui défile. Comme décoration générale de cartes, c'est un tic.

**Grain.** Un bruit très léger (opacité 3–6 %, en `mix-blend-mode: multiply` ou `overlay`) donne de la matière à un aplat ou à un dégradé et supprime les bandes. Un seul calque fixe, en `pointer-events: none`.

## 10. Couleurs d'état

Accorde-les à la palette au lieu de prendre le rouge et le vert par défaut : même chroma et même clarté que l'accent, teinte décalée.

| État | Teinte de départ | Toujours accompagné de |
| --- | --- | --- |
| Erreur | 25–30 | icône, message explicite, bordure du champ |
| Alerte | 70–85 | icône, texte |
| Succès | 145–155 | icône, texte |
| Information | 240–255 | icône, texte |

Si l'accent du site est rouge, change aussi la variable `--erreur` du socle, qui est rouge par défaut. L'erreur doit s'en distinguer : forme différente, icône, fond doux, texte plus sombre.

## 11. Fautes fréquentes

| Faute | Correction |
| --- | --- |
| Noir `#000` sur blanc `#fff` | encre L 0,2 et fond L 0,98, teintés |
| Gris neutres sans teinte | chroma 0,005–0,02 dans la teinte de la palette |
| Cinq couleurs « de marque » | un accent, une seconde couleur au plus |
| Dégradé violet → bleu | aplat, ou dégradé étroit tiré du sujet |
| Accent utilisé partout | le réserver à l'action et à la signature |
| Gris sur fond coloré | encre mélangée à la couleur du fond |
| Texte posé sur photo | voile, aplat ou zone calme |
| Ombres noires opaques | ombres teintées, en couches, alpha 0,05–0,1 |
| Mode sombre = inversion | palette sombre construite, accent rééclairci |
| Bordures plus contrastées que le contenu | filets à 10–15 % de l'encre |
| Rouge et vert seuls pour dire erreur / succès | icône et texte en plus |
