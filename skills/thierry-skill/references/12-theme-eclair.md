# 12 — Livrer sur Éclair CMS

À lire quand le site tourne sur **Éclair CMS** (ou doit y tourner), ou quand on te demande « un thème Éclair ». Tout ce qui suit est vérifié dans le code d'Éclair 2.x. Contrat complet : `docs/THEME-CONTRACT.md` du dépôt eclair-cms.

**Sommaire** : 1. Deux niveaux, toujours dans cet ordre · 2. Découvrir le site · 3. Le paquet de thème · 4. Traduire la fiche DA en variables Éclair · 5. Mode sombre · 6. Polices · 7. Ce que le CSS d'un thème ne peut pas faire · 8. Gabarits : seulement si la composition l'exige · 9. Le contenu n'est pas dans le thème · 10. Valider, livrer, faire activer · 11. Regarder et noter sur Éclair · 12. Liste de contrôle

## 1. Deux niveaux, toujours dans cet ordre

1. **Les réglages d'Éclair d'abord** (Réglages › Apparence) : une *direction artistique* règle palette, police de titre, taille des titres, coins, densité, composition de la bannière, des atouts, des sections, des boutons et des icônes. Aucun code, contrastes garantis par Éclair (texte à 7:1, liens et boutons à 4,5:1), police auto-hébergée et préchargée. Si la fiche DA s'exprime avec ces réglages, **arrête-toi là** et donne les réglages exacts à saisir.
2. **Un thème du site** (`site/themes/<id>/`) seulement pour ce que les réglages ne peuvent pas dire : la signature de la fiche DA (un filet, une grille, un traitement des titres, une texture), une échelle typographique précise, des états soignés. Le thème s'ajoute **par-dessus** les réglages : garde-les cohérents avec lui.

Un thème ne remplace jamais le contenu, le SEO ni la sécurité : ce sont des responsabilités du noyau.

## 2. Découvrir le site

Avant de produire quoi que ce soit, lis ce que le site prend en charge (session d'un compte qui a le droit Réglages) :

```
GET /<admin>/api/capabilities
```

Retiens `contracts.theme` (contrat de rendu, aujourd'hui `1`), les gabarits remplaçables et la version d'Éclair. Refuse de livrer un thème pour un contrat que le site ne connaît pas. Sans accès au site, vise `"contract": 1` et `"requires": {"eclair": ">=2.0.0"}`.

## 3. Le paquet de thème

Pars du squelette livré avec ce skill : `assets/eclair-theme/mon-theme/`. Copie le dossier, **renomme-le** avec l'identifiant du thème (minuscules, chiffres, tirets : le nom du dossier doit être exactement le champ `id`), puis remplis-le.

```
<id>/
  theme.json          manifeste (schéma eclair-theme/1)
  styles/site.css     la feuille du thème : variables, signature, finitions
  assets/…            images, SVG, polices WOFF2 déclarés dans "assets"
  templates/…         seulement si nécessaire (voir §8)
```

```json
{
  "schema": "eclair-theme/1",
  "id": "carnet-atelier",
  "name": "Carnet d'atelier",
  "version": "1.0.0",
  "contract": 1,
  "parent": "eclair",
  "requires": { "eclair": ">=2.0.0", "php": ">=8.1" },
  "description": "La phrase concept de la fiche DA.",
  "templates": [],
  "styles": ["styles/site.css"],
  "overrides": {},
  "assets": []
}
```

- `parent` vaut toujours `eclair` : un thème ne dépend jamais d'un autre thème du site.
- Chaque fichier utilisé doit être déclaré (`styles`, `assets`, `templates`) ; un fichier non déclaré ou un lien symbolique fait refuser le paquet.
- `overrides` remplace un fichier CSS du noyau (`theme.css`, `motion.css`…). À éviter : le thème ne recevrait plus les corrections du noyau. Une feuille `styles` suffit presque toujours.
- Nouvelle version d'un thème : même `id`, `version` augmentée. Éclair garde l'ancienne et permet de la rétablir en un clic.

## 4. Traduire la fiche DA en variables Éclair

Le socle `assets/tokens.css` reste ton outil de travail. Sur Éclair, ses rôles se branchent sur les **variables du contrat**, que le thème de base et tous les blocs utilisent :

| Rôle dans la fiche DA (`tokens.css`) | Variable Éclair | Remarque |
| --- | --- | --- |
| `--fond` | `--bg` | fond de page |
| `--surface` | `--bg-soft` | bandes, encadrés |
| `--surface-2` | `--card` | cartes |
| `--encre` | `--text` | texte courant |
| `--encre-2` | `--muted` | texte secondaire, ≥ 4,5:1 sur `--bg` |
| `--filet` | `--line` | filets, bordures |
| `--accent` | `--accent` | une seule couleur d'accent |
| `--sur-accent` | `--on-accent` | texte sur l'accent, ≥ 4,5:1 |
| couleur des liens | `--link` (`--link-dark` en sombre) | ≥ 4,5:1 sur le fond ; Éclair la calcule si tu ne la fixes pas |
| `--police-texte` | `--font` | pile complète, avec repli système |
| `--police-titre` | `--font-head` | pile complète, avec repli |
| `--r-2` | `--radius` | rayon des cartes et boutons |
| ombre de la fiche | `--shadow` | |
| `--mesure` | `--text-w` | largeur de lecture |
| `--conteneur` | `--wide-w` | largeur des compositions larges |

Les autres valeurs de la fiche (échelle typographique, espaces, durées, courbes) restent des variables à toi : garde les noms du socle et utilise-les dans tes règles.

Vise les **classes du contrat** (stables d'une version à l'autre) : `site-header`, `brand`, `site-nav`, `wrap`, `entry-header`, `byline`, `featured`, `toc`, `cards`, `card`, `card-title`, `card-excerpt`, `listing`, `pager`, `chips`, `btn`, `btn-primary`, `site-footer`, `footer-grid`, `skip`, `crumbs`, `related`, `author`, `notfound`, et les classes de `<body>`. Le reste du HTML peut changer sans préavis : ne t'y accroche pas.

## 5. Mode sombre

Éclair affiche un mode sombre automatique (réglage « Automatique ») ou reste clair (« Toujours clair »). Dès que ton thème fixe des couleurs, **il doit fournir son propre mode sombre**, sinon ses couleurs claires écrasent le mode sombre du site. Encadre ce bloc avec les repères d'Éclair : il disparaît tout seul quand le site est réglé sur « Toujours clair ».

```css
/*dark*/@media (prefers-color-scheme: dark) {
  :root { --bg: …; --bg-soft: …; --card: …; --text: …; --muted: …; --line: …; }
}/*/dark*/
```

Si la direction est pensée uniquement claire ou uniquement sombre, dis-le à la personne et fais régler Réglages › Apparence › Mode sombre sur « Toujours clair ».

## 6. Polices

- **Titres** : commence par les polices qu'Éclair auto-héberge (Réglages › Apparence, une graisse, 12 à 27 Ko, préchargées) : Bodoni Moda, Cormorant Garamond, DM Serif Display, Young Serif, Gloock, Libre Caslon Display, Newsreader, Zilla Slab, Bricolage Grotesque, Schibsted Grotesk, Familjen Grotesk, Archivo Black, Big Shoulders Display, Barlow Condensed, Syne, Unbounded, Fredoka, Sora. Inter, Roboto, Fraunces ou Space Grotesk n'y sont pas, exprès (`08-anti-generique.md`).
- **Texte** : les piles système d'Éclair (system, humanist, geometric, rounded, serif, oldstyle) ne coûtent aucun téléchargement.
- **Autre police** : seulement si la fiche DA l'exige. Fichier WOFF2 sous licence libre (SIL OFL…), sous-ensemble latin, déclaré dans `assets` ; `@font-face` avec `font-display: swap` et l'adresse publiée :
  ```css
  @font-face { font-family: "Ma Police"; src: url("/site/assets/<id>/assets/fonts/ma-police.woff2") format("woff2"); font-weight: 700; font-display: swap; }
  ```
  Cette adresse suppose un site installé à la racine du domaine (cas courant) ; signale-le si le site est dans un sous-dossier. **Jamais Google Fonts ni un autre service** : la politique de sécurité d'Éclair (`font-src 'self'`) le bloque, et c'est voulu.

## 7. Ce que le CSS d'un thème ne peut pas faire

La feuille est nettoyée comme le CSS personnalisé avant d'être insérée dans la page :

- `@import` est retiré : tout doit être dans la feuille ou dans des actifs déclarés ;
- le caractère `<` est retiré : n'en mets nulle part, même dans un commentaire ou un `content:` ;
- 256 Ko au plus pour l'ensemble des feuilles ;
- le CSS personnalisé du site (Réglages › Apparence) passe après le thème et garde le dernier mot : c'est le bon endroit pour les retouches de la personne, pas pour la direction.

Mouvement : CSS seulement, `transform` et `opacity`, toujours sous `@media (prefers-reduced-motion: no-preference)` (`06-mouvement.md`). Un thème n'ajoute pas de JavaScript sans gabarit pour le charger ; ne le fais que si l'idée l'exige vraiment.

## 8. Gabarits : seulement si la composition l'exige

Un gabarit (`templates/single.php`, `list.php`, `layout.php`, `cards.php`, `404.php`…) **s'exécute avec les droits du site**. Préfère un thème sans gabarit : il n'exécute aucun code et suit toutes les évolutions du noyau. Si la composition est impossible en CSS :

- pars du gabarit du noyau (`eclair/app/theme/<nom>.php`) et modifie le moins possible ;
- utilise seulement les variables `$v` du contrat et les fonctions listées dans `THEME-CONTRACT.md` (`e()`, `path_to()`, `Theme::t()`…) ; échappe tout avec `e()` ;
- n'écris jamais de balise SEO, d'en-tête ni de JSON-LD : `$v['head']` les contient déjà ;
- garde un seul `<h1>`, le lien d'évitement `.skip` et le focus visible.

Une erreur dans un gabarit n'abîme pas le site : Éclair rend la page avec son propre gabarit et signale le thème. Ce n'est pas une raison pour livrer sans vérifier.

## 9. Le contenu n'est pas dans le thème

Textes, sections et images vivent dans les **contenus** (blocs : bannière, atouts, équipe, FAQ, contact…), créés dans l'éditeur ou proposés en brouillon par le connecteur MCP d'Éclair. Le thème les met en forme ; il ne les contient pas. Les emplacements `[À fournir : …]` et `[Exemple à confirmer]` de ta méthode vont dans ces brouillons, jamais dans le CSS. Rien n'est publié sans une personne.

## 10. Valider, livrer, faire activer

Depuis une copie du dépôt eclair-cms :

```bash
php tools/validate.php chemin/vers/<id>
```

Corrige jusqu'au ✓, puis livre un `.zip` dont la racine contient le dossier `<id>/` (ou directement `theme.json`) :

```bash
cd chemin/vers && zip -r <id>.zip <id>
```

Explique à la personne :

1. **Thèmes › Importer un thème** : choisir le `.zip` et confirmer avec son mot de passe (droit Sécurité) ;
2. **Aperçu avec tes contenus** : le site réel, visible d'elle seule ;
3. **Activer** ;
4. pour revenir en arrière : **Rétablir le thème précédent** ou **Revenir au thème de base**. En cas de souci grave : `define('ECLAIR_SAFE_THEME', true);` dans `eclair-config.php`.

Un thème ne s'active jamais tout seul.

## 11. Regarder et noter sur Éclair

Temps 7 de la méthode, sur de vraies pages :

- sur un site de test local, depuis le dépôt : `php -S localhost:8000 -t eclair tools/router.php`, installation en trois minutes, contenus de départ, thème importé et activé ;
- captures à trois largeurs de l'accueil, d'une page, d'un article et de la liste du blog : `node scripts/regard.mjs http://localhost:8000/ --out captures`, puis la même chose avec `--sombre` si le thème fournit un mode sombre ;
- analyse du code du thème (tics génériques, durées, contrastes déclarés) : `node scripts/audit.mjs chemin/vers/<id>` ;
- l'aperçu de l'administration demande une session : pour les captures, active le thème sur le site de test.

Note avec la grille de `10-revue.md`. Le Score Design de l'éditeur d'Éclair (hiérarchie des titres, appels à l'action, rythme, formules creuses) complète ta revue sur le contenu ; il ne la remplace pas.

## 12. Liste de contrôle

- [ ] La fiche DA a été essayée d'abord avec les réglages d'Éclair (§1) ; le thème n'apporte que ce qu'ils ne savent pas faire.
- [ ] Nom du dossier = `id` ; `contract` pris en charge par le site ; tous les fichiers déclarés.
- [ ] Variables du contrat renseignées (§4), contrastes vérifiés (texte 4,5:1 au moins, 7:1 visé pour le texte courant).
- [ ] Mode sombre fourni entre `/*dark*/` et `/*/dark*/`, ou site réglé sur « Toujours clair ».
- [ ] Aucune police ni ressource externe ; aucun `@import` ; aucun `<` dans le CSS.
- [ ] Mouvement sous `prefers-reduced-motion: no-preference` ; focus visible ; un seul `<h1>`.
- [ ] Aucun gabarit, ou des gabarits minimaux qui échappent tout et n'écrivent aucune balise SEO.
- [ ] `php tools/validate.php` affiche ✓ ; captures regardées à trois largeurs ; note ≥ 85 ou corrections listées.
- [ ] La personne sait importer, prévisualiser, activer et revenir en arrière.
