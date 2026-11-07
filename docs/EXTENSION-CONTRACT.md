# Contrat des fonctions optionnelles (capacités) d’Éclair — `eclair-extension/1`

Une **fonction** (nom affiché dans l’administration) ou **capacité** (nom technique) ajoute à un site ce dont lui seul a besoin, sans alourdir les autres installations. Schéma : [`schemas/extension.schema.json`](schemas/extension.schema.json). Exemple complet et testé : [`examples/extensions/bonjour`](../examples/extensions/bonjour) (route publique, réglage, bloc, carte du tableau de bord, page d’administration, tâche, migration, désactivation sans perte). Validateur : `php tools/validate.php <dossier ou .zip>`.

## 1. Principes

* **Rien n’est chargé quand rien n’est actif.** Les pages publiques ne lisent le registre que si une fonction active déclare une route publique (index `data/ext-public.php`) ; l’administration lit un seul fichier (`data/extensions.php`) ; rien sur le chemin rapide. Aucun dossier n’est parcouru à chaque requête : le manifeste est enregistré à l’activation. Le code d’une fonction n’est chargé que si une de ses routes, cartes, blocs ou tâches est demandé.
* **Le noyau garde les protections.** Contrôle d’accès et CSRF des routes d’administration, validation des réglages et des champs de blocs, en-têtes SEO (titre, canonical unique, robots, JSON-LD) et de sécurité (CSP) des pages publiques, échappement des cartes du tableau de bord.
* **Désactiver ne détruit rien.** Supprimer le code est une deuxième opération, effacer les données une troisième.
* **Le code d’une fonction est du code de confiance** : il s’exécute avec les droits de l’application. Le manifeste décrit un contrat (droits applicatifs, fichiers et empreintes, provenance) ; ce n’est **pas** un bac à sable. Importer une fonction demande le droit Sécurité et le mot de passe.

## 2. Fichiers

```
site/extensions/<id>/
  extension.json      manifeste
  extension.php       fichier d'entrée : renvoie un objet (voir §4)
  lib/*.php           code complémentaire (seuls fichiers PHP permis en plus de l'entrée)
  assets/…            actifs déclarés, publiés dans site/assets/ext-<id>/
  lang/<langue>.json.gz  traductions propres à la fonction (format de tools/i18n.php), lues quand son code est chargé
```

Données : `data/ext/<id>/…` via `Eclair\Store` (préfixe fourni dans `$ctx['data']`), incluses dans les sauvegardes. Les secrets (clés d’API…) vont dans `data/ext/<id>/secrets.php` (ou un dossier `secrets/`) : jamais sauvegardés ni restaurés, comme le coffre du noyau.

## 3. Manifeste

| Champ | Rôle |
|---|---|
| `schema`, `id`, `version`, `name`, `description` | Identité ; `id` = dossier, 2 à 40 caractères `[a-z0-9-]` |
| `requires.eclair`, `requires.php` | Compatibilité ; une fonction devenue incompatible après une mise à jour du noyau n’est plus chargée et est signalée |
| `depends` | `{ "autre": "^1.0" }` : activée automatiquement si installée et compatible ; désactivation refusée tant qu’une fonction active en dépend |
| `provides` | Capacités offertes (découverte, futurs choix automatiques) |
| `entry` | Fichier PHP qui renvoie l’objet |
| `files` | Empreintes SHA-256 : si présent, tout fichier différent ou non listé est refusé (provenance vérifiable) |
| `routes.public[]` | `{ "path": "/bonjour", "handler": "page", "cache": true }` — GET seulement ; adresses internes refusées ; conflit avec un contenu, une route réservée ou une autre fonction refusé à l’activation |
| `routes.admin[]` | `{ "method": "GET", "path": "/liste", "handler": "liste", "right": "content" }` — servi sous `/<admin>/x/<id>/liste` |
| `nav[]` | Entrée du menu, filtrée par droit |
| `dashboard[]` | Carte du tableau de bord (`handler`, `right`) |
| `settings[]` | Champs déclaratifs (`text`, `textarea`, `url`, `number`, `bool`, `select`) : formulaire, validation et stockage par le noyau, page `/<admin>/x/<id>/settings` |
| `blocks[]` | `{ "type": "<id>/<nom>", "label", "render", "fields": [...] }` — champs validés par le noyau, rendu par la fonction |
| `assets[]` | Actifs publiés (`Extensions::asset(id, fichier)`) |
| `tasks[]` | `{ "handler", "every" }` (≥ 3 600 s), via l’adresse secrète `/_eclair/tasks/<jeton>` à appeler chaque heure |
| `migrations[]` | Identifiants exécutés une seule fois, dans l’ordre, journalisés (`migrate($id, $ctx)`) |
| `data.schema` | Version du schéma de données de la fonction |
| `provenance` | Source, commit, licence (informatif ; la vérification porte sur `files`) |

Droits applicatifs permis : `content`, `media`, `menus`, `categories`, `messages`, `redirects`, `settings`, `users`, `security` (ceux du noyau, `Auth::can`).

## 4. L’objet de la fonction

```php
<?php
defined('ECLAIR') || exit;

return new class {
    public function page(array $ctx): array                       // route publique
    { return ['title' => '…', 'description' => '…', 'content' => '<h1>…</h1>']; }   // + 'noindex' => true facultatif

    public function liste(array $user, array $ctx): array         // route d'administration
    { return ['title' => '…', 'html' => '…']; }                    // ou ['redirect' => 'chemin', 'message' => '…']

    public function card(array $user, array $ctx): array          // carte du tableau de bord (échappée par le noyau)
    { return ['title' => '…', 'text' => '…', 'link' => 'x/<id>']; }

    public function render(array $block, array $ctx): string      // rendu d'un bloc (champs déjà validés)
    { return '<p>' . e($block['name']) . '</p>'; }

    public function daily(array $ctx): void {}                    // tâche planifiée
    public function migrate(string $id, array $ctx): void {}      // migration (idempotente, additive)
};
```

`$ctx` : `id`, `settings` (valeurs validées), `lang`, `data` (préfixe `Store`). Jamais de secret du noyau.

Une page d’administration complète peut être rendue par `Eclair\Admin::extensionPage($id, $fichierDeVue, $v, $titre)` : la vue reçoit `$v` et prend place dans la mise en page de l’administration.

**Tout ce que la fonction affiche doit être échappé avec `e()`** (le HTML qu’elle renvoie est inséré tel quel). Une exception dans un appel est journalisée ; la page continue de s’afficher sans l’apport de la fonction.

## 5. Blocs et désactivation sans perte

* Bloc d’une fonction **active** : champs déclarés validés par le noyau à chaque enregistrement ; formulaire d’édition généré dans l’éditeur.
* Fonction **absente, désactivée ou incompatible** : le bloc est conservé sous la forme `{ "type": "<id>/<nom>", "preserved": true, "data": {…} }`, borné (32 Ko de JSON, 8 niveaux), **jamais rendu ni exécuté**, rendu intact à chaque enregistrement. L’éditeur l’affiche comme « Bloc conservé » avec une explication. Dès que la fonction revient, le bloc est de nouveau affiché, même sans réenregistrer le contenu.
* Avant de désactiver une fonction qui sert des pages publiques ou des contenus publiés, l’administration demande une confirmation et indique combien d’adresses et de contenus sont concernés (`Extensions::impact`). Une boutique ne devient jamais silencieusement une série de 404.

## 6. Cycle de vie

| Étape | Effet |
|---|---|
| Installer (ZIP ou dossier) | Validation complète puis copie dans `site/extensions/<id>/` ; rien n’est activé |
| Activer | Validation, dépendances, conflits d’adresses, migrations, publication des actifs, cache vidé |
| Mettre à jour (même `id`) | Nouvelle version installée ; si active, réactivée (nouvelles migrations seulement) |
| Désactiver | Routes, menu, cartes, tâches et rendu des blocs retirés ; données et blocs gardés |
| Supprimer le code | Seulement désactivée ; données gardées |
| Effacer les données | Seulement code supprimé ; définitif |

Les mises à jour du noyau ne touchent jamais `site/extensions/` : une fonction retirée n’est jamais réinstallée par le noyau.

## 7. Fonctions livrées avec Éclair

Certaines fonctions sont maintenues avec le noyau et livrées dans son archive signée, sous `app/bundled/<id>/` (aujourd’hui : `wayback` « Ancien site », `drive` « Sauvegardes Google Drive », `ai-visits` « Robots IA », `discover` « Google Discover », `affiliate` « Affiliation », `planning` « Planning éditorial », `wordpress` « Import WordPress »). Elles ne sont **jamais chargées** tant que le site ne les a pas ajoutées (Fonctions › Ajouter, ou préréglage d’un usage, ou migration pour les sites qui les utilisaient). Une mise à jour du noyau met à jour celles que le site a ajoutées (`Extensions::syncBundled`) et ne réinstalle jamais celles qui ont été retirées. Pour des raisons de compatibilité avec la 1.x, le noyau peut appeler une fonction livrée active par `Extensions::invoke()` (adresse historique d’une tâche planifiée, envoi dû en arrière-plan, réglages du journal des robots IA tenu par `index.php`, rapport affiché au tableau de bord et dans le planning, contrôles Discover des tuiles du tableau de bord, idées ajoutées au planning par Discover) ; ce point d’accroche n’est pas ouvert aux autres fonctions.

## 8. Découverte

`GET /<admin>/api/capabilities` (droit Réglages, JSON) : version d’Éclair, version mineure de PHP, contrats (thème, schémas, gabarits, champs), blocs du noyau et des fonctions, fonctions installées (état, compatibilité, routes, blocs), thèmes, droits et langues. Aucun secret, aucun chemin du serveur, aucune donnée personnelle.

## 9. Limites actuelles (2.0.0)

* **Contributions SEO** (balises, données structurées) : pas encore ouvertes aux fonctions. Une page publique d’une fonction reçoit l’en-tête SEO standard du noyau (une seule canonical, JSON-LD `WebPage`).
* **Types de contenu** propres à une fonction : non pris en charge ; utilisez des blocs et des pages.
* **Politique CSP par fonction** (ex. publicité) : la publicité existante garde son mécanisme actuel (`Ads`, nonce par page) ; l’ouverture de ce mécanisme aux fonctions n’est pas faite.
* Les fonctions historiques du noyau AdSense, boutique, IA et MCP **ne sont pas encore extraites** (Wayback, Google Drive, robots IA, Discover avec les tendances, la gestion de l’affiliation et le planning le sont)
* **Filtre de rendu** : non ouvert aux fonctions. L’affiliation n’en a pas eu besoin : le marquage de ses liens (mention publicitaire, `rel="sponsored"`) est une obligation légale attachée aux pages publiées, gardée volontairement au noyau pour qu’aucun retrait de fonction ne la supprime : voir `docs/STATUS-2.md`.
