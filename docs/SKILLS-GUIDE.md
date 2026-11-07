# Concevoir les futurs skills pour Éclair

Ce guide fixe les règles pour les skills (Claude ou autres agents) qui personnaliseront des sites Éclair. Il s’appuie sur les contrats livrés avec la 2.0 : [contrat de thème](THEME-CONTRACT.md), [contrat des fonctions](EXTENSION-CONTRACT.md), [schémas](schemas/), validateur `tools/validate.php`, exemples dans [`examples/`](../examples/).

## 1. Règle d’or

Un skill **ne modifie jamais** `app/`, `assets/`, `index.php`, l’authentification ou l’updater. Tout ce qu’il produit appartient au site : réglages et contenus (`data/`, via l’administration ou le connecteur MCP), thème (`site/themes/`), fonction (`site/extensions/`). Ces emplacements survivent à toutes les mises à jour et font partie des sauvegardes.

Le site doit fonctionner sans IA connectée : un skill prépare, une personne autorisée valide et active.

## 2. Les quatre familles de skills

| Famille | Ce qu’il produit | Par où | Droit nécessaire | Exemple |
|---|---|---|---|---|
| **Configurer** les fonctions existantes | Réglages, menus, contenus en brouillon | Administration ; MCP (`get_site`, `create_draft`, `update_content`…) | Celui de la personne connectée ; le MCP crée des brouillons et des propositions, jamais de publication directe | **Éclair Design** (direction artistique, compositions, CSS de finition) |
| **Produire un thème ou des blocs** | Un paquet `theme.json` + gabarits/styles/actifs | Fichier .zip importé dans Thèmes (droit Sécurité + mot de passe), puis aperçu et activation par une personne | Réglages pour activer ; Sécurité pour importer | Thème « Atelier » sur mesure |
| **Installer une capacité serveur** maintenue séparément | Un paquet `extension.json` + code | Fichier .zip importé dans Fonctions (droit Sécurité + mot de passe), activation par une personne | Réglages pour activer ; Sécurité pour importer | Réservation, catalogue, connecteur métier |
| **Outil temporaire** d’import ou de migration | Contenus, médias, redirections | Services d’import (à venir : voir `STATUS-2.md`, étape 7-8), simulation d’abord | Sécurité | Migration WordPress |

Distinguer « proposer un changement d’apparence » et « rédiger un article » : un skill de rédaction n’a besoin que du droit Contenus ; un skill d’apparence produit un paquet de thème qu’une personne ayant le droit Réglages prévisualise puis active. **Un thème ou une fonction ne s’active jamais tout seul.**

## 3. Découvrir le site avant d’agir

```
GET /<admin>/api/capabilities        (session d'un compte avec le droit Réglages ; JSON)
```

Renvoie la version d’Éclair, la version de PHP, les contrats pris en charge (`contracts.theme`, schémas, gabarits remplaçables, types de champs), les blocs du noyau et des fonctions actives, les fonctions installées (état, compatibilité), les thèmes, les droits et les langues. Aucun secret ni chemin du serveur. Un skill **doit** lire cette réponse et refuser de produire un paquet pour un contrat que le site ne prend pas en charge.

## 4. Produire un thème

1. Partir de [`examples/themes/atelier-lumiere`](../examples/themes/atelier-lumiere).
2. Préférer `styles` + `assets` (aucun code exécuté). N’ajouter des `templates` que pour une vraie composition impossible autrement, en respectant les variables du contrat.
3. Ne jamais générer de balises SEO, d’en-têtes ou de JSON-LD : `$v['head']` les contient déjà.
4. Respecter l’accessibilité : un seul `<h1>`, lien d’évitement (`.skip`), focus visible, `prefers-reduced-motion`, contrastes (les variables `--link`/`--on-accent` sont déjà calculées pour 4,5:1).
5. Valider : `php tools/validate.php mon-theme` (ou `--json`), puis livrer un .zip dont la racine (ou l’unique dossier) contient `theme.json`.
6. Indiquer à la personne : importer → « Aperçu avec tes contenus » → « Activer », et comment revenir en arrière.

## 5. Produire une fonction

1. Partir de [`examples/extensions/bonjour`](../examples/extensions/bonjour).
2. Déclarer tout ce qui est apporté (routes, droits, blocs, champs, réglages, tâches, migrations, actifs) ; lister les empreintes dans `files` pour une provenance vérifiable.
3. Échapper toute donnée affichée avec `e()` ; lire les réglages dans `$ctx['settings']` (déjà validés) ; ranger les données sous `$ctx['data']` et les secrets dans `data/ext/<id>/secrets.php`.
4. Migrations additives et idempotentes ; ne jamais supprimer de données dans une migration.
5. Valider avec `tools/validate.php`, livrer un .zip.

## 6. Ce qu’un skill ne doit jamais faire

* Écrire hors de `site/` et `data/` (et seulement via l’administration ou le MCP pour `data/`).
* Désactiver une protection (CSP, `.htaccess`, limites, double authentification).
* Publier sans validation humaine, ou rendre public un contenu privé.
* Présenter un score interne, `llms.txt` ou une conformité technique comme une garantie de classement, de citation par une IA ou d’apparition dans Discover.
* Recréer un service externe obligatoire pour les visiteurs.

## 7. Compatibilité avec Éclair Design

Les parcours d’Éclair Design (direction artistique dans Réglages › Apparence, compositions de blocs en brouillon via MCP, CSS personnalisé de finition rangé dans `data/`) fonctionnent à l’identique en 2.0 : aucun de ces réglages, blocs ou outils MCP n’a changé. Sa règle « ne jamais modifier `app/theme/` » reste juste ; la 2.0 ajoute une voie durable pour aller plus loin : un paquet de thème dans `site/themes/`. Mettre à jour le badge « Éclair CMS 1.14+ » du README d’Éclair Design n’est pas nécessaire tant qu’il n’utilise pas ces nouveautés.
