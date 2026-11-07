# Refonte 2.0 : bilan, contrôles exécutés et état de reprise

Branche : `claude/hopeful-thompson-8kqj2g`, partie de `main` @ `90bb968` (1.14.1). Version des sources : **2.0.0, non publiée**. La fusion dans `main`, l’annonce sur le canal public et la release restent des décisions du mainteneur.

**La refonte n’est pas terminée.** Les étapes 1 à 5 sont livrées et testées ; les étapes 6 à 9 restent à faire (voir §3). Cette branche est un état intermédiaire utilisable : elle peut être publiée telle quelle comme 2.0 « transition » (aucune fonction retirée, apparence identique), ou poursuivie avant publication.

## 1. Contrôles exécutés (7 novembre 2026, PHP 8.3.6)

| Contrôle | Résultat |
|---|---|
| Suite d’origine sur 1.14.1 (avant toute modification) | 1 159 vérifications réussies |
| `php tests/run.php` (sources, branche) | **1 312 vérifications réussies** |
| Même suite sur l’archive construite sans clé, décompressée (`ECLAIR_TEST_ROOT`) | **1 310 vérifications réussies** (les 2 tests du validateur CLI ne s’appliquent qu’au dépôt) |
| CI GitHub, PHP 8.1 à 8.5 (sources, archive, budgets) | Verte en 8.2–8.5 dès le commit `9621438` ; **deux défauts de la 1.14.1 sous PHP 8.1 trouvés et corrigés** (type de retour `null` seul ; `CURLOPT_XFERINFOFUNCTION`, qui empêche la mise à jour en un clic de la 1.14.1 sous PHP 8.1) |
| Transition depuis la **vraie** archive `dist/eclair-cms-1.14.1.zip`, mise à jour par **son propre updater** | Réussie : gabarit, CSS du thème et actif personnalisés conservés et servis, `.htaccess` modifié gardé, statuts des pages identiques |
| Mise à jour suivante par le nouvel updater, archive piégée écrivant dans `site/`, retour 2.0.1 → 2.0.0 | `site/` identique à l’octet près (empreinte) ; archive piégée refusée |
| Retour 2.0.0 → 1.14.1 | L’ancien code retrouve ses gabarits personnalisés ; `site/` conservé |
| Sauvegarde sur une installation, restauration sur une **autre** | Thème identique (empreinte), contenus et comptes de la source, coffre de la cible gardé, archive piégée refusée |
| Fonction absente / désactivée / réactivée | Blocs conservés sans rendu ni exécution, aucune perte de données, confirmation demandée avant de casser des pages publiques |
| Rendu public à l’octet près (4 sites de départ, test existant) | Inchangé |
| `php tools/measure.php --check` | Budgets respectés (après relèvement justifié de l’archive, voir CHANGELOG) |
| Lint JavaScript de l’éditeur (`node`) | Syntaxe valide |

Non exécutés ici (à faire avant publication) :

* **Apache / LiteSpeed / Nginx réels** : seuls le serveur intégré de PHP et `tools/router.php` (qui reproduit les règles) ont été utilisés. Les règles `.htaccess` ajoutées (`site/themes`, `site/extensions`, `site/assets`) et les règles Nginx de `UPGRADE-2.md` restent à vérifier sur de vrais serveurs.
* **Parcours FTP** avec `cache/legacy-app/` : code écrit (`Migrations::LEGACY`), **non couvert par un test automatique**.
* **Tests visuels** (captures avant/après), **accessibilité** automatisée (clavier, contrastes, mouvement réduit) des nouveaux écrans d’administration (Thèmes, Fonctions, restauration) : non faits. Le rendu public, lui, est vérifié à l’octet près.
* **Navigateur** : l’éditeur (bloc conservé, blocs de fonctions) n’a été vérifié que par les tests HTTP et le lint, pas dans un navigateur.
* **Performances sur plusieurs tailles de site** : l’outil le permet (`--pages=N`), seule la mesure « site neuf » a été produite.

## 2. Points découverts pendant le travail

### Sites en PHP 8.1

La 1.14.1 publiée annonce PHP 8.1 mais **ne fonctionne pas sous PHP 8.1** : ses pages publiques répondent en erreur 500 hors cache (type de retour `null` seul dans `Affiliate.php`, chargé au rendu), et la mise à jour en un clic comme l'assistant IA s'arrêtent sur une constante cURL de PHP 8.2. Vérifié par la CI avec la vraie archive 1.14.1. Les sites concernés doivent passer en PHP 8.2+ puis mettre à jour, ou mettre à jour par FTP (testé en 8.1). Corrigé dans la branche ; **à signaler aux utilisateurs dès que possible**, indépendamment de la 2.0 (une 1.14.2 de correctif serait justifiée).

### Clé de signature

La **clé privée de signature du mainteneur** (`ECLAIR_SIGNING_KEY`) est présente dans l’environnement d’exécution de cette session cloud. Avec l’ancien `tools/build.php`, sa seule présence suffisait à signer : une construction de test a ainsi produit une annonce authentiquement signée pour une 2.0.0 non relue, **dans une copie temporaire, supprimée aussitôt, jamais poussée**. Corrections :

* `tools/build.php` ne signe plus qu’avec `--sign` (ou `--resign`) ; sinon la clé est ignorée et l’archive reste une archive de test non annoncée (test ajouté dans `tests/sec-C1.php`) ;
* la CI n’utilise aucun secret.

**Action recommandée au mainteneur** : retirer cette variable de l’environnement cloud (elle n’a rien à faire dans un environnement où du code non relu s’exécute), et envisager une rotation de la clé si cet environnement a pu être utilisé par d’autres sessions.

## 3. Avancement par étape de la mission

| Étape | État |
|---|---|
| 1. Référence, cartographie, validation initiale | Fait (`ARCHITECTURE-2.md`, `MEASURES.md`) |
| 2. CI sources + archives, fixtures, caractérisation | Workflow écrit, non exécuté ; tests de caractérisation de la transition faits |
| 3. Protection du thème, sauvegarde/restauration, transition de l’updater | **Fait et testé** |
| 4. Contrats de contenu, blocs, interfaces communes | Blocs inconnus conservés ; schéma de contenu ; **routes communes non faites** |
| 5. Registre minimal et cycle de vie des capacités | **Fait et testé** (routes, menu, cartes, réglages, blocs, actifs, tâches, migrations, dépendances, impact). Manquent : contributions SEO, types de contenu, CSP par fonction |
| 6. Extraction des fonctions, langues et actifs | **Commencé** : mécanisme des fonctions livrées (`app/bundled/`), **Ancien site (Wayback)**, **Sauvegardes Google Drive**, **Robots IA**, **Google Discover + tendances** et **Affiliation** (gestion et rédaction par l’IA ; le marquage légal des pages reste au noyau) extraits, migrés et testés (y compris par la vraie transition 1.14.1) ; réglages AdSense déplacés sur une page « Publicité » du noyau ; usages devenus préréglages (appliqués aussi à l'installation). Restent : AdSense, planning, boutique, IA et MCP, traductions par langue (voir §4) |
| 7. Routage pour la migration | **Commencé** : alias `?p=`/`?page_id=`/`?attachment_id=` résolus avant le cache, paramètres marketing sans variante de cache (`tests/routing.php`). Restent : chemin exact, `.html`, Unicode, définition commune des routes, import WXR |
| 8. Contrats des skills et démonstrations | Fait : contrats, schémas, validateur, exemples, guide, découverte. Manque : outil MCP de proposition d’apparence (droit distinct de la rédaction) |
| 9. Validation globale, packaging, documentation | Documentation de transition faite ; archive candidate construite sans clé (non versionnée) ; procédure de signature inchangée hormis `--sign` |

Le gain de légèreté visé par la mission viendra des étapes 6 et 7 : aujourd’hui l’archive pèse **775 Ko (+6 %)** car le noyau porte encore toutes les fonctions spécialisées **plus** les nouveaux mécanismes. Les traductions (266 Ko pour 6 langues, 44 Ko utiles par site) et les fonctions spécialisées (IA ~190 Ko de PHP, Drive 40 Ko, boutique, Wayback, Discover…) sont les gisements principaux.

## 4. Reprise : ordre recommandé

Analyse de couplage faite (nombre de fichiers du noyau qui appellent chaque classe) : Trends 2, Discover 4, Affiliate 4, Drive 4 (fait), Plan 7, Ads 7, Shop 22. Les prochaines extractions demandent d’abord d’ouvrir au contrat des fonctions trois points d’accroche, aujourd’hui absents :

1. ~~**Filtre de rendu**~~ : pas nécessaire, le marquage légal de l’affiliation (`Affiliate::mark` dans `Site` et `Seo`) reste au noyau ;
2. **Politique CSP par page** (AdSense : `Ads` dans `Theme`, `Site`, `Blocks`, `Seo`, l’éditeur) ;
3. **Réglages hébergés ailleurs** (les réglages AdSense sont sur la page Discover ; `Discover::WIDTH` sert au rendu des grandes images, à garder dans le noyau).

Ordre proposé ensuite (Robots IA fait : journal de quelques lignes gardé dans `index.php`, activé seulement par `cache/runtime.php` quand la fonction est active), Discover+Trends et affiliation faits ; le filtre de rendu n’a pas été nécessaire, le marquage légal reste au noyau) planning, AdSense (CSP), boutique (22 fichiers : en dernier), IA et MCP (séparer les opérations de contenu sûres des fournisseurs). Puis **traductions par langue** (paquets signés, repli sur le français) et la suite de l’étape 7.

## 5. Incompatibilités connues

* Un bloc d’un type inconnu n’est plus supprimé à l’enregistrement : il est conservé (`preserved`). Un outil externe qui relisait les contenus en supposant que seuls des blocs du noyau existent doit ignorer ces blocs.
* `tools/build.php` exige `--sign` pour signer (les scripts de publication du mainteneur doivent l’ajouter).
* Une fonction peut déclarer une route publique : elle est prioritaire sur un contenu créé ensuite à la même adresse (le conflit n’est vérifié qu’à l’activation).
