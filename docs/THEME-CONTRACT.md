# Contrat de thème d’Éclair (contrat de rendu 1)

Ce document décrit ce qu’un thème du site peut faire, ce qu’il reçoit et ce que le noyau garantit. Il s’adresse aux intégrateurs, à Éclair Design et aux futurs skills qui produisent des thèmes. Schéma du manifeste : [`schemas/theme.schema.json`](schemas/theme.schema.json). Exemple minimal : [`examples/themes/atelier-lumiere`](../examples/themes/atelier-lumiere). Validateur : `php tools/validate.php <dossier>`.

## 1. Où vit un thème, et pourquoi il ne disparaît jamais

| Emplacement | Propriétaire | Écrit par |
|---|---|---|
| `app/`, `assets/`, `index.php` | Noyau | Les mises à jour d’Éclair uniquement |
| `app/theme/` | Noyau : **thème de base** (parent de tous les thèmes) | Les mises à jour d’Éclair |
| `site/themes/<id>/` | **Le site** : thème propre | L’administration (Thèmes), un import, la migration 1.x |
| `site/assets/<id>/` | Le site : actifs publiés d’un thème | Activation du thème |
| `site/extensions/<id>/`, `site/assets/ext-<id>/` | Le site : fonctions optionnelles | Administration › Fonctions |
| `data/` | Contenus, réglages, comptes (privé) | L’application |
| `media/` | Médias envoyés | L’application |
| `cache/` | Régénérable, staging des mises à jour | L’application |

La protection est appliquée par le code, pas par une convention :

* l’updater (`Update::extract`) **refuse en entier** toute archive du noyau qui contient une entrée `site/`, `data/` ou `media/` (hors fichiers de protection), et ne peut de toute façon écrire que `app/`, `assets/` et quelques fichiers racine ;
* `tools/build.php` exclut `site/` de toute archive du noyau ;
* le retour arrière ne déplace que `app/` et `assets/` ;
* les tests vérifient l’empreinte complète de `site/` avant et après une mise à jour, une archive piégée et un retour arrière (`tests/transition.php`).

## 2. Manifeste `theme.json`

```json
{
  "schema": "eclair-theme/1",
  "id": "mon-theme",
  "name": "Mon thème",
  "version": "1.0.0",
  "contract": 1,
  "parent": "eclair",
  "requires": { "eclair": ">=2.0.0", "php": ">=8.1" },
  "templates": ["single", "404"],
  "styles": ["styles/site.css"],
  "overrides": { "motion.css": "styles/motion.css" },
  "assets": ["assets/logo.svg"]
}
```

* `id` = nom du dossier `site/themes/<id>/` ; minuscules, chiffres, tirets.
* `contract` : contrat de rendu suivi. Un thème d’un contrat non pris en charge est **refusé avant activation** et jamais exécuté.
* `parent` : seulement le thème de base du noyau (`eclair`, ou `eclair@1`). Un thème ne dépend jamais d’un autre thème du site : ce qu’il reprend, il l’embarque. Ainsi aucun parent ne peut être remplacé en silence par une version qui change son apparence.
* Contraintes de version : `>=2.0.0`, `<3.0.0`, `^2.0`, plusieurs séparées par une espace.

## 3. Ce que le thème peut remplacer

### Gabarits (`templates/<nom>.php`)

| Gabarit | Rôle | Variables `$v` |
|---|---|---|
| `layout` | Enveloppe HTML de toutes les pages publiques | `lang`, `head` (en-tête SEO déjà construit), `css` (CSS en ligne déjà calculé), `content`, `current`, `id`, `class`, `nonce` (page avec publicité) |
| `single` | Page ou article | `item`, `html` (corps rendu), `toc`, `author`, `category`, `crumbs`, `related`, `translations`, `featured`, `words`, `h1_in_content`, `lang` |
| `list` | Blog, catégorie, auteur, recherche, boutique | `title`, `description`, `entries`, `crumbs`, `page`, `pages`, `prev`, `next`, `query`, `lang`, `empty` |
| `cards` | Grille de cartes d’articles | `entries`, `lang`, `level` |
| `product` | Fiche produit | `item`, `entry`, `html`, `images`, `offers`, `info`, `legal`, `category`, `crumbs`, `related`, `translations`, `lang` |
| `products` | Grille de cartes produit | `entries`, `lang`, `level` |
| `404` | Page introuvable | `lang` |
| `extension` | Page publique d’une fonction optionnelle (2.0) | `lang`, `inner` |

Un gabarit absent du manifeste reste celui du noyau. Les morceaux réutilisés par vos gabarits vont dans `templates/parts/*.php` (inclus par vos soins, jamais appelés par le noyau).

**Fonctions du contrat utilisables dans un gabarit** : `e()`, `path_to()`, `asset()`, `absolute_url()`, `Eclair\Theme::t()`, `Theme::words()`, `Theme::look()`, `Theme::bodyClass()`, `Theme::preload()`, `Theme::cards()`, `Theme::products()`, `Eclair\Themes::asset()`, `Eclair\Menus::items()`, `Eclair\Media::get()`, `Media::url()`, `Eclair\Settings::get()` / `all()` (lecture). Tout le reste des classes internes peut changer sans préavis.

**Ce qu’un gabarit ne fait pas** : fabriquer des balises canonical, robots, hreflang ou JSON-LD (déjà dans `$v['head']`), envoyer des en-têtes, lire les sessions, écrire des données. Le noyau garde la main sur le SEO, la sécurité (CSP, en-têtes) et le contrôle d’accès.

### Styles

* `styles` : feuilles ajoutées **après** le thème de base et le préréglage d’apparence, **avant** le CSS personnalisé (qui garde le dernier mot). Nettoyées comme le CSS personnalisé (pas de `@import`, pas de `<`), insérées en ligne, 256 Ko au plus.
* `overrides` : remplacent un fichier CSS du noyau parmi `theme.css`, `motion.css`, `ads.css`, `themes/<préréglage>.css`. À réserver aux refontes profondes : un thème qui remplace `theme.css` ne reçoit plus les corrections de styles du noyau.
* **Variables CSS contractuelles** : `--accent`, `--on-accent`, `--link`, `--link-dark`, `--font`, `--font-head`, `--bg`, `--bg-soft`, `--text`, `--muted`, `--line`, `--card`, `--radius`, `--shadow`, `--code`, `--text-w`, `--wide-w`.
* **Classes contractuelles** : `wrap`, `site-header`, `header-inner`, `brand`, `site-nav`, `site-footer`, `footer-grid`, `footer-brand`, `footer-text`, `footer-bottom`, `skip`, `sr-only`, `crumbs`, `entry-header`, `byline`, `meta`, `featured`, `toc`, `key`, `key-title`, `cards`, `card`, `card-img`, `card-body`, `card-title`, `card-excerpt`, `card-meta`, `card-price`, `listing`, `listing-header`, `pager`, `chips`, `chip`, `btn`, `btn-primary`, `search-form`, `related`, `author`, `empty`, `notfound`, `ext-page`, ainsi que les classes de `<body>` (`theme-<préréglage>`, `hdr-center`, `cards-list`, `is-404`, `is-message`, `is-extension`).
* Les repères `/*dark*/ … /*/dark*/` et `/*shop*/ … /*/shop*/` du CSS du noyau restent gérés par le noyau (mode sombre, boutique fermée).

### Actifs

Les fichiers déclarés dans `assets` (CSS, JS, SVG, images, polices WOFF2, icônes) sont publiés dans `site/assets/<id>/` à l’activation et servis en statique. Adresse : `Themes::asset('assets/logo.svg')`. Rien d’autre du dossier du thème n’est accessible depuis le web (`site/themes/` est refusé par le `.htaccess`, `site/themes/.htaccess` et les règles Nginx).

## 4. Confiance et sécurité

Les gabarits PHP **s’exécutent avec les droits de l’application**. Le manifeste, la validation et l’import vérifié évitent les erreurs (chemins, liens symboliques, scripts non déclarés, syntaxe refusée par le PHP du serveur, tailles), **ils ne constituent pas un bac à sable**. N’installez que des thèmes de confiance ; importer un thème demande le droit Sécurité et la confirmation du mot de passe.

Préférez les réglages déclaratifs quand ils suffisent : un thème sans `templates` (seulement `styles` et `assets`) n’exécute aucun code.

## 5. Cycle de vie

| Action | Où | Garantie |
|---|---|---|
| Installer | Thèmes › Importer (ZIP), migration, Éclair Design | Validé avant toute écriture ; jamais activé automatiquement (sauf thème « legacy » de la migration) |
| Prévisualiser | Thèmes › Aperçu avec tes contenus | Vraies pages du site, jamais mises en cache, jamais publiques |
| Activer | Thèmes › Activer | Validation complète avant ; version précédente notée |
| Nouvelle version | Import d’un ZIP de même `id` | L’ancienne est gardée dans `site/themes/.versions/<id>/` |
| Revenir en arrière | Thèmes › Rétablir le thème précédent | Remet aussi l’ancienne version des fichiers si besoin |
| Revenir au thème de base | Thèmes › Revenir au thème de base | Les fichiers du thème restent en place |
| Erreur dans un gabarit | Automatique | La page est rendue avec le gabarit du noyau, l’erreur est journalisée, le thème est signalé |
| Secours | `define('ECLAIR_SAFE_THEME', true);` dans `eclair-config.php` | Le site s’affiche avec le thème de base, l’administration n’utilise jamais le thème du site |

Sauvegarde : `site/` (thèmes, actifs publiés, versions gardées) fait partie de chaque sauvegarde ; la restauration sur une autre installation est testée par empreinte (`tests/themes.php`).

## 6. Compatibilité et maintenance

* Le contrat 1 correspond exactement aux gabarits et variables de la 1.14.1 : les gabarits personnalisés d’une installation 1.x fonctionnent tels quels (c’est ce que fait la migration « legacy »).
* Un nouveau contrat (2) sera ajouté **à côté** du contrat 1 (`Themes::SUPPORTED`). Le noyau fournira alors un adaptateur qui prépare les variables du contrat 1 pour les thèmes qui le déclarent.
* Politique : un contrat reste pris en charge au moins **deux versions majeures** après l’arrivée du suivant, et sa fin est annoncée dans le CHANGELOG une version majeure à l’avance. Un thème d’un contrat abandonné n’empêche jamais une mise à jour de sécurité : il est désactivé (le site revient au thème de base) et signalé dans Thèmes.
* Les nouvelles variables d’un contrat existant sont toujours **ajoutées** (jamais retirées ni renommées) ; un gabarit doit donc ignorer les clés qu’il ne connaît pas.
