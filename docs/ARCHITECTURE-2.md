# Architecture d’Éclair 2.0 : cartographie, propriété des fichiers, garanties

Référence de départ : **1.14.1**, commit `90bb96815880da5289834f2f1ba6cec0732aaf7b` (branche `main`, 5 novembre 2026). Suite de tests à ce commit : **1 159 vérifications réussies** (PHP 8.3.6, `php tests/run.php`, 70 s). Mesures avant/après : [MEASURES.md](MEASURES.md).

## 1. Propriété des fichiers

| Emplacement | Propriétaire | Qui écrit | Mise à jour du noyau | Sauvegarde |
|---|---|---|---|---|
| `index.php`, `app/`, `assets/` | Noyau | Updater, FTP | Remplacés | Non |
| `app/theme/` | Noyau (thème de base, parent unique) | Updater | Remplacé | Non |
| `.htaccess`, `*/.htaccess`, `*/index.html` | Noyau, personnalisables | Updater si non modifiés | Gardés s’ils ont été modifiés | Non (ceux de l’installation cible restent) |
| `site/themes/<id>/` | Site | Thèmes, migration | **Jamais** (archive refusée) | Oui |
| `site/extensions/<id>/` | Site | Fonctions | **Jamais** | Oui |
| `site/assets/<id>/`, `site/assets/ext-<id>/` | Site (actifs publiés) | Activation | **Jamais** | Oui |
| `data/` | Site (privé) | Application | Jamais (migrations additives) | Oui, sauf secrets, sessions, compteurs, état des services |
| `media/` | Site | Application | Jamais | Oui |
| `cache/` | Régénérable, staging | Application | Purgé | Non |

`ECLAIR_DATA` et `ECLAIR_SITE` (dans `eclair-config.php`) permettent de ranger `data/` et `site/` hors du dossier public.

## 2. Parcours d’une requête

1. **Chemin rapide** (`index.php`) : page en cache de la génération courante → servie sans charger l’application (**1 fichier PHP**, inchangé par la 2.0).
2. `App::run` : génération du cache, gestion d’erreurs, `/__eclair/ping`, installation, **migrations** (une lecture de `data/migrations.php` quand tout est fait), administration, contact, tâches secrètes (Drive, planning, **fonctions**), MCP, **routes publiques des fonctions actives** (une lecture de `data/extensions.php`), puis `Site::handle`.
3. Rendu : `Theme::render` → gabarit du **thème du site** s’il le déclare (repli sur le noyau en cas d’erreur), sinon gabarit du noyau ; CSS = base (ou remplacement déclaré) + préréglage + animations + publicité + **styles du thème du site** + CSS personnalisé.

## 3. Responsabilités (état 2.0.0)

| Domaine | Classes | Statut |
|---|---|---|
| Installation, diagnostic | `Installer`, `Loopback`, `Disk` | Noyau |
| Comptes, sessions, TOTP, réauthentification, révocation | `Auth`, `Session`, `Users`, `Totp`, `Keys` | Noyau |
| CSRF, validation, échappement, limites, journaux, coffre, intégrité | `Security`, `Sanitize`, `RateLimit`, `Log`, `Vault`, `Integrity` | Noyau |
| Contenus, révisions, médias, menus, catégories, recherche | `Content`, `Blocks`, `Media`, `Menus`, `Categories` | Noyau |
| Stockage, verrous, écritures atomiques | `Store`, `Disk`, `StorageException` | Noyau |
| Cache et invalidation | `Cache` | Noyau |
| Routage, canonical, redirections, erreurs | `Site`, `Redirects`, `Req`, `Http` | Noyau |
| SEO, sitemap, robots, JSON-LD, hreflang, RSS, GEO (llms, Markdown, robots IA) | `Seo`, `Markdown` | Noyau |
| Thème de base, apparence | `Theme`, `Design` (rendu des directions), `Icons` | Noyau |
| **Thèmes du site** | `Themes`, `AdminThemes`, `Packages` | **Nouveau (2.0)** |
| **Fonctions optionnelles** | `Extensions`, `AdminExtensions`, `Packages` | **Nouveau (2.0)** |
| **Migrations** | `Migrations` | **Nouveau (2.0)** |
| Sauvegarde **et restauration** locales | `Backup` | Noyau (restauration ajoutée en 2.0) |
| Mises à jour signées, récupération | `Update`, `AdminUpdate` | Noyau (périmètre renforcé en 2.0) |
| Discover, Trends, AdSense, affiliation, Wayback, planning, robots IA (statistiques), boutique, Google Drive, fournisseurs d’IA, assistant, MCP | `AdminDiscover`, `Discover`, `Trends`, `Ads`, `Affiliate`, `Wayback`, `Plan`, `AiVisits`, `Shop`, `Drive`, `Ai*`, `Mcp` | **Encore dans le noyau** : à extraire (voir STATUS-2.md) |

Dépendances transverses relevées (à défaire pendant l’extraction) : `Theme` → `Ads`, `Shop`, `Design` ; `Site` → `Shop`, `Ads`, `IndexNow` ; `Cache::runtime` → `AiVisits` ; `App` → `Drive`, `Plan`, `Mcp` ; `Admin` (table des routes) → toutes les pages spécialisées ; `Settings::reserved` → `Shop` ; `Update::preload` → `Admin`.

## 4. Correspondance des protections existantes

Toutes les protections de la 1.14.1 restent **à leur place** (aucune classe de sécurité n’a été déplacée) ; les suites qui les vérifient tournent sans modification de leurs assertions, sur les sources et sur l’archive construite.

| Protection | Emplacement 2.0 | Test de non-régression |
|---|---|---|
| Limites de tentatives, places de vérification, verrou des données, sessions, appareils de confiance, journal | `RateLimit`, `Auth`, `Session`, `Store`, `Log` | `tests/sec-A.php` |
| Index et recherche bornés, IA limitée aux brouillons, génération du cache | `Content`, `AiTools`, `Cache` | `tests/sec-B1.php` |
| Redirections réservées et plafonnées, exports Markdown sûrs, médiathèque bornée | `Redirects`, `Markdown`, `Media` | `tests/sec-B2.php` |
| Secrets exclus des sauvegardes, clés, canal de mise à jour, annonce signée (Ed25519, série, échéance, rejeu) | `Backup`, `Keys`, `Update`, `tools/build.php` | `tests/sec-C1.php`, `tests/update.php` |
| En-têtes sur une ligne, consentement MCP, contact | `Mail`, `Mcp`, `Contact` | `tests/sec-C2.php` |
| Index reconstruit sans republier, archives privées, accès retirés, installateur verrouillé, `.htaccess` gardé au retour | `Content`, `Backup`, `Installer`, `Update` | `tests/sec-r2-a.php` |
| Compteurs MCP bornés, `--resign` limité aux archives attestées | `Mcp`, `tools/build.php` | `tests/sec-r2-b.php` |
| Drive, comptes bloqués, CSS personnalisé, canal d’un fork, archives sans lien symbolique | divers | `tests/sec-review.php` |
| XSS du texte enrichi, slugs, TOTP RFC 6238, CSP et en-têtes, rendu public à l’octet près (4 sites) | `Sanitize`, `Totp`, `Security`, `Theme` | `tests/run.php` |
| **Nouveau** : archive du noyau qui écrit dans `site/`, `data/`, `media/` refusée | `Update::OWNED` | `tests/transition.php` |
| **Nouveau** : personnalisations 1.x sauvées avant toute purge de l’ancien code | `Migrations::rescue`, `Update::run` | `tests/transition.php` |
| **Nouveau** : thème/fonction validés (chemins, liens symboliques, scripts non déclarés, syntaxe, tailles, empreintes) | `Themes::validate`, `Extensions::validate`, `Packages` | `tests/themes.php`, `tests/extensions.php` |
| **Nouveau** : restauration sans secrets, sans protections de l’archive, refus hors `data/ media/ site/` | `Backup::restore` | `tests/themes.php` |
| **Nouveau** : routes d’administration des fonctions derrière les droits et le CSRF du noyau | `Admin::handle`, `Extensions::adminRoutes` | `tests/extensions.php` |
| **Nouveau** : construction signée seulement avec `--sign` | `tools/build.php` | `tests/sec-C1.php` |

## 5. Hébergement et PHP

* Minimum inchangé : **PHP 8.1** (pour ne casser aucune installation en place). Extensions : sodium, zip, curl, zlib, mbstring.
* État des branches au 7 novembre 2026, d’après https://www.php.net/supported-versions.php (consulté ce jour) : **8.1 n’est plus prise en charge** (absente du tableau) ; 8.2 : correctifs de sécurité jusqu’au 31/12/2026 ; 8.3 : 31/12/2027 ; 8.4 : 31/12/2028 ; 8.5 : 31/12/2029.
* CI : PHP 8.1 à 8.5 (`.github/workflows/tests.yml`), sur les sources et l’archive.
* Politique proposée : la 2.x reste installable en 8.1 avec un avertissement dans l’administration (Sécurité signale déjà une version de PHP non maintenue) ; la 3.0 exigera au minimum la plus ancienne branche encore maintenue à sa sortie (8.3 si elle sort après le 31/12/2026, 8.2 n’étant alors plus maintenue), annoncée une version majeure à l’avance. L’updater refuse déjà une version qui demande un PHP plus récent que celui du serveur (`Update::blockers`).
