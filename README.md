# ⚡ Éclair CMS

**Le CMS ultra léger, ultra rapide et taillé pour Google et les IA.**
Il s'installe sur n'importe quel hébergement mutualisé comme WordPress, mais sans base de données, sans extensions et sans maintenance.

| | WordPress classique | Éclair |
|---|---|---|
| Base de données | MySQL obligatoire | **Aucune** (fichiers protégés) |
| Poids à installer | ≈ 70 Mo décompressés | **< 1 Mo** |
| SEO, sitemap, données structurées | Extensions à installer et à maintenir | **Intégré** |
| GEO (llms.txt, Markdown, robots IA) | Rarement disponible | **Intégré** |
| Pages publiques | Générées à chaque visite | **Servies depuis un cache statique** |
| Cookies sur le site public | Souvent nombreux | **Aucun** |
| Double authentification, anti-bruteforce | Extensions | **Intégré** |

Mesures sur l'environnement de test (serveur PHP 8.3 local, sans réseau) :

- **Lighthouse 100 / 100 / 100 / 100** (performance, accessibilité, bonnes pratiques, SEO) sur l'accueil et sur un article, en mobile et en desktop.
- **≈ 0,3 ms** de traitement serveur pour une page servie depuis le cache, ≈ 2 ms à la première génération.
- **6,5 à 8 Ko** de HTML compressé par page, CSS compris (zéro feuille de style ou police à télécharger, ≈ 2 Ko de JavaScript différé).

Sur un vrai mutualisé, ajoute la latence réseau et le démarrage de PHP de ton hébergeur.

---

## Sommaire

1. [Installation en 3 minutes](#installation-en-3-minutes)
2. [Ce qui est inclus](#ce-qui-est-inclus)
3. [Utiliser Éclair au quotidien](#utiliser-éclair-au-quotidien)
4. [Sécurité](#sécurité)
5. [Mettre à jour, sauvegarder, déménager](#mettre-à-jour-sauvegarder-déménager)
6. [Dépannage](#dépannage)
7. [Pour les développeurs](#pour-les-développeurs)

---

## Installation en 3 minutes

### Prérequis

- **PHP 8.1 ou plus** (8.3 recommandé), avec les extensions `mbstring`, `json`, `dom` et `session` (présentes par défaut presque partout).
- Recommandé : `gd` (images WebP), `zip` (sauvegardes en un clic), `curl` (IndexNow).
- Un serveur **Apache ou LiteSpeed** (le cas de la quasi-totalité des mutualisés : OVH, o2switch, Hostinger, IONOS, PlanetHoster…). Pour Nginx, voir [plus bas](#nginx).
- Un certificat HTTPS (gratuit avec Let's Encrypt chez presque tous les hébergeurs).

L'assistant vérifie tout cela pour toi et explique quoi corriger.

### Étapes

1. Télécharge [`dist/eclair-cms-1.0.0.zip`](dist/eclair-cms-1.0.0.zip) (bouton « Download raw file » sur GitHub) et décompresse-le.
2. Envoie **le contenu du dossier `eclair`** dans le dossier public de ton hébergement (`public_html`, `www`…) avec ton logiciel FTP (FileZilla…) ou le gestionnaire de fichiers de ton hébergeur.
   > ⚠️ Les fichiers `.htaccess` sont cachés : active « Afficher les fichiers cachés » pour être sûr de les envoyer.
3. Ouvre l'adresse de ton site : **l'assistant d'installation démarre** (français, anglais ou espagnol).
4. Réponds à 4 questions (ton site, ton compte, ta visibilité, l'adresse de ton administration). Confettis 🎉 : ton site est en ligne, avec des pages et un article d'exemple.

> 🔒 Par sécurité, l'installation doit être faite **dans les 2 heures** qui suivent la première visite (sinon n'importe qui pourrait s'emparer d'un site déposé puis oublié). Passé ce délai, supprime le fichier `data/install-window.php` par FTP et recharge la page.

Installation dans un sous-dossier (`exemple.fr/site/`) : envoie simplement les fichiers dans ce sous-dossier, tout est détecté automatiquement.

### Les 4 types de site proposés

| Type | Ce qui est créé |
|---|---|
| 🏢 Site vitrine | Accueil (bannière, atouts, derniers articles), Services (avec FAQ), À propos, Contact, Blog, mentions légales, confidentialité |
| ✍️ Blog | Accueil = derniers articles, À propos, Contact, catégorie « Guides », pages légales |
| 🧑‍💻 Freelance / portfolio | Accueil à ton nom, compétences, À propos, Blog, Contact, pages légales |
| 🎯 Page unique | Une page de vente (bannière, atouts, FAQ, formulaire) + pages légales, sans blog |

Les contenus de départ existent en français, espagnol et anglais. Les pages légales contiennent un squelette à compléter (LCEN en France, LSSI en Espagne, RGPD).

### Juste après l'installation

Le tableau de bord affiche une checklist « Premiers pas » : logo et couleur, page d'accueil, premier article, profil d'auteur, double authentification, mentions légales, Google Search Console.

---

## Ce qui est inclus

### ⚡ Vitesse

- **Cache statique automatique** : chaque page est générée une fois, puis servie sans charger l'application. Le cache se vide tout seul à chaque modification (et à l'heure prévue pour un article programmé).
- **CSS intégré dans la page** (≈ 12 Ko minifiés), polices système : aucune requête bloquante, aucun décalage de mise en page.
- **Images optimisées à l'envoi** : redimensionnées (2000 px max), déclinées en **WebP responsive** (480, 800, 1200, 1600 px), dimensions explicites, chargement différé, priorité donnée à l'image principale.
- ETag et réponses `304 Not Modified`, compression Brotli/Gzip et cache navigateur d'un an pour les fichiers statiques (via `.htaccess`).

### 🔎 SEO

- URL propres, **redirection 301 automatique** quand une adresse change, gestionnaire de redirections (pratique pour reprendre un ancien site WordPress ou Wix).
- Titre, méta-description et **aperçu Google en direct** dans l'éditeur, avec compteurs.
- **Analyse SEO en temps réel** avec score : longueur du titre et de la description, expression-clé (titre, URL, introduction, intertitres, densité), nombre de mots, structure, textes alternatifs, maillage interne, image à la une…
- **Données structurées JSON-LD** complètes : WebSite (+ recherche), Organization / Person / LocalBusiness (adresse, horaires, profils sociaux), WebPage / AboutPage / ContactPage, BlogPosting (auteur, dates, section), BreadcrumbList, FAQPage, ProfilePage.
- Sitemap XML avec images et `hreflang`, robots.txt, flux RSS complet, Open Graph et Twitter Cards (**image de partage 1200×630 générée automatiquement**), fil d'Ariane, URL canoniques.
- Une seule adresse par contenu : redirections HTTPS, www/sans www, slash final, majuscules, `index.php`.
- **IndexNow** : Bing, Yandex, Seznam… sont prévenus à chaque publication.
- Vérification Google Search Console et Bing Webmaster Tools (colle la balise `<meta>`, Éclair extrait le code).
- Multilingue : langue par contenu, liaison des traductions et balises `hreflang` automatiques.
- Typographie française automatique (espaces insécables avant `: ; ! ?` et dans les guillemets).

### 🤖 GEO (moteurs de réponse IA)

- **`/llms.txt`** (standard llmstxt.org) et **`/llms-full.txt`** générés automatiquement.
- **Version Markdown de chaque page** : ajoute `.md` à n'importe quelle URL (avec en-tête de métadonnées, `noindex` et URL canonique pour ne pas créer de contenu dupliqué).
- **Politique robots IA en un clic** : ouvert (recommandé pour être cité), citations seulement (moteurs de réponse autorisés, entraînement refusé) ou fermé. Gère GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Meta-ExternalAgent… Directive `Content-Signal` en option.
- Bloc **« L'essentiel »** : le résumé que les IA citent volontiers, affiché en tête d'article.
- **Score GEO** dans l'éditeur : résumé, introduction directe, FAQ, intertitres en questions, listes et tableaux, chiffres concrets, sources externes, auteur identifié (E-E-A-T), paragraphes courts, fraîcheur.
- Pages auteur avec biographie et profils (`sameAs`), dates de publication et de mise à jour visibles.

### ✍️ Éditeur de blocs

- 16 blocs : texte, intertitre (H2-H4), liste, citation, image, encadré, FAQ, tableau, bouton, vidéo YouTube/Vimeo (sans cookie avant le clic), code, séparateur, bannière, atouts, derniers articles, formulaire de contact.
- Tape **`/`** sur une ligne vide pour choisir un bloc, glisser-déposer, barre de mise en forme (gras, italique, lien, code, surlignage).
- **Liens internes assistés** : en tapant un lien, l'éditeur propose tes pages et articles (maillage interne en 2 secondes).
- **Collage intelligent** depuis Word, Google Docs ou une page web : titres, listes, tableaux et gras sont convertis en blocs propres.
- Enregistrement `Ctrl+S`, brouillon local de secours (rien n'est perdu en cas de coupure), aperçu réel avant publication, **historique des 20 dernières versions**, publication programmée.

### 🧩 Le reste

Médiathèque (glisser-déposer, textes alternatifs, détection des images utilisées), menus en glisser-déposer (en-tête et pied de page), catégories « piliers » pour un cocon sémantique, recherche sur le site, formulaire de contact anti-spam **sans captcha** (champ piège, jeton, limitation par IP) avec boîte de réception, comptes administrateur et rédacteur, mode sombre automatique, 6 polices système, couleur principale avec contraste WCAG garanti, interface en français, anglais et espagnol, site public en 6 langues (fr, es, en, de, it, pt).

---

## Utiliser Éclair au quotidien

| Je veux… | Où |
|---|---|
| Écrire un article | Tableau de bord › **Écrire un article** |
| Modifier une page depuis le site | Bouton **✏️ Modifier** (visible seulement quand tu es connecté) |
| Changer logo, couleur, polices | Réglages › Apparence |
| Choisir la page d'accueil | Réglages › Général |
| Autoriser ou bloquer les IA | Réglages › IA & GEO |
| Relier Google Search Console | Réglages › SEO |
| Ajouter un lien au menu | Menus |
| Rediriger une ancienne URL | Redirections |
| Activer la double authentification | Mon profil › Double authentification |
| Télécharger une sauvegarde | Sécurité › Sauvegarde |

Raccourcis de l'éditeur : `/` insérer un bloc · `Ctrl+S` enregistrer · `Ctrl+K` lien · `Ctrl+B` gras · `Ctrl+I` italique · `Entrée` nouveau paragraphe · `Maj+Entrée` retour à la ligne.

---

## Sécurité

Éclair est conçu pour un environnement hostile (mutualisé, robots qui testent des mots de passe en permanence). Il a fait l'objet d'une revue de sécurité offensive (courses entre requêtes, force brute, redirections, injections, fichiers piégés) : chaque faille trouvée a été corrigée, puis l'attaque rejouée pour le prouver (`tests/run.php`).

**Données et serveur**
- **Aucune base de données** : pas d'injection SQL possible.
- **Données doublement protégées** : dossiers `data/`, `app/` et `cache/` interdits par `.htaccess` ; chaque fichier de données commence par `<?php exit; ?>` (même si un serveur ignorait les `.htaccess`, rien ne s'affiche) et aucune séquence `<?php` ne peut y être écrite. Fichiers privés (droits 0600), écritures atomiques.
- **Installation protégée** : elle n'est possible que dans les 2 heures suivant la première visite, et l'adresse du site n'est utilisée pour rediriger les visiteurs qu'après avoir été **vérifiée par le serveur lui-même** (une adresse erronée ou piégée ne détourne rien).

**Connexion**
- **Mots de passe** chiffrés avec Argon2id (19 Mio, 2 passes : sûr et supportable sur un mutualisé ; bcrypt en repli), 10 caractères minimum, mots de passe courants refusés.
- **Anti-bruteforce** : chaque tentative est réservée avant d'être vérifiée (impossible de dépasser la limite en parallèle) ; limite par réseau (adresse IPv4 ou bloc /64 en IPv6) et par compte visé. **Ton appareil habituel n'est jamais bloqué** par un attaquant qui rate exprès. Deux vérifications de mot de passe à la fois au maximum : un afflux de requêtes n'épuise pas la mémoire.
- **Double authentification TOTP** (Google Authenticator, 1Password, Bitwarden…) avec QR code et 8 codes de secours affichés une seule fois ; chaque code ne sert qu'une fois, même envoyé en parallèle ; après 10 codes faux, la 2FA du compte est bloquée 15 minutes et son titulaire est prévenu par e-mail.
- **Comptes modifiés de façon atomique** : une connexion en cours ne peut jamais annuler un changement de mot de passe, une déconnexion générale, l'activation de la 2FA, une rétrogradation ou une suppression.
- **Mot de passe redemandé** pour les actions sensibles (sauvegarde, gestion des comptes, réglages avancés, changement d'e-mail) au-delà de 15 minutes après la connexion. Les mots de passe provisoires doivent être remplacés à la première connexion.
- **Sessions durcies** : aucune session avant la connexion (les robots ne remplissent pas le disque), cookies `HttpOnly`, `Secure`, `SameSite=Strict`, régénération à la connexion, déconnexion après inactivité, liées au navigateur, « déconnecter tous les appareils ». L'administration est forcée en HTTPS dès que le site l'est.
- **Jetons CSRF** sur toutes les actions et vérification de l'origine des requêtes.

**Site public**
- **En-têtes stricts** : Content-Security-Policy (aucun script en ligne, empreinte SHA-256 pour le style), anti-clickjacking, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS en option. Ils dépendent des réglages, jamais de la requête : le cache ne peut pas être empoisonné.
- **Aucune redirection ouverte** : les adresses piégées (`/\exemple.com`, caractères de contrôle…) donnent une 404.
- **Contenus assainis** par liste blanche (le HTML est analysé puis entièrement réécrit) ; liens `javascript:` et `data:` impossibles, même déguisés (`javascript&colon;`). Les versions Markdown, `llms.txt` et RSS restent inertes.
- **Formulaire de contact** sans captcha : champ piège, jeton, 5 messages par réseau toutes les 10 minutes et 100 par jour pour tout le site ; boîte plafonnée à 500 messages (le spam part en premier), paginée.
- **Images ré-encodées à l'envoi** (supprime les charges cachées et les données EXIF/GPS) ; GIF animés réécrits bloc par bloc ; toute image contenant du code est refusée ; 40 mégapixels maximum ; SVG refusés, aucun script ni liste de fichiers dans `/media`.

**Administration**
- **Adresse d'administration personnalisable** (moins de robots), rôles administrateur / rédacteur ; le bouton « Modifier » du site ne révèle pas cette adresse.
- **Journaux bornés** (champs tronqués, 1 Mo maximum) : impossible de les faire gonfler.
- **Contrôle d'intégrité** : compare chaque fichier à son empreinte d'origine et repère les fichiers suspects, y compris un `.php` sans ligne de protection dans `data/` ou `cache/`.
- **Test de protection en direct** : la page Sécurité vérifie depuis ton navigateur que les fichiers internes sont bien inaccessibles, et que l'adresse du site est vérifiée.

---

## Mettre à jour, sauvegarder, déménager

**Sauvegarder** : Sécurité › Télécharger une sauvegarde (archive ZIP de `data/` et `media/`). Sans l'extension zip, copie ces deux dossiers par FTP.

**Mettre à jour Éclair** :
1. Fais une sauvegarde.
2. Remplace par FTP `index.php`, `.htaccess`, et les dossiers `app/` et `assets/` par ceux de la nouvelle version.
3. **Ne touche pas** à `data/` (contenus, réglages, comptes) ni à `media/` (images).
4. Dans l'administration : Réglages › Avancé › Vider le cache.

**Déménager vers un autre hébergement ou domaine** : copie tout le dossier, puis change l'adresse dans Réglages › Général. Les liens internes s'adaptent automatiquement, y compris en sous-dossier.

---

## Dépannage

**Mot de passe oublié (ou téléphone 2FA perdu) et aucun autre administrateur ?**
Crée par FTP un fichier `data/reset.php` contenant exactement ces trois lignes :

```
<?php exit; ?>
ton-adresse@email.fr
TonNouveauMotDePasse2026
```

Dans les 24 heures, **connecte-toi avec cet e-mail et ce nouveau mot de passe** : le mot de passe est remplacé, la double authentification de ce compte est désactivée, tes autres sessions sont fermées et le fichier est supprimé automatiquement. Personne d'autre ne peut le déclencher (il faut connaître son contenu) ; un fichier sans la première ligne est refusé et supprimé.

**« Installation verrouillée »** : la fenêtre de 2 heures après la première visite est passée. Supprime `data/install-window.php` par FTP et recharge la page.

**« Adresse non vérifiée » (page Sécurité)** : ton serveur n'arrive pas à s'appeler lui-même sur l'adresse du site (pare-feu de l'hébergeur, DNS pas encore propagé…). Le site fonctionne normalement, mais sans redirection vers l'adresse officielle (www / sans www). Clique sur « Vérifier l'adresse du site » une fois le domaine en place.

**Erreur 404 sur toutes les pages sauf l'accueil** : le fichier `.htaccess` n'a pas été envoyé (fichier caché) ou la réécriture d'URL est désactivée. Si ton hébergeur l'exige, décommente la ligne `RewriteBase /` du `.htaccess`.

**Erreur 500 juste après l'envoi** : une directive du `.htaccess` est refusée par ton hébergeur (très rare : seules des directives tolérées partout sont utilisées). Supprime le bloc concerné ou contacte ton hébergeur.

**Les images ne sont pas converties en WebP** : l'extension GD (ou son support WebP) manque. Active-la dans le panneau PHP de ton hébergeur ; en attendant, les images restent en JPG/PNG.

**Je ne reçois pas les e-mails du formulaire** : les messages sont aussi enregistrés dans l'administration (Messages). Vérifie l'adresse dans Réglages › Contact ; certains hébergeurs exigent que l'expéditeur appartienne au domaine.

**Mode débogage** : crée un fichier `eclair-config.php` à la racine (voir `eclair-config.exemple.php`) avec `define('ECLAIR_DEBUG', true);`, puis supprime-le une fois le problème réglé.

### Nginx

Éclair n'a pas besoin de `.htaccess` sous Nginx, mais il faut reproduire ces règles :

```nginx
location ~ ^/(app|data|cache)(/|$) { return 404; }
location ~ /\.(?!well-known/) { return 404; }
location ^~ /media/ {
    location ~* \.(php\d?|phtml|phar|s?html?|svgz?|xml|js)$ { return 404; }
    add_header X-Content-Type-Options nosniff;
    expires 1y;
}
location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)$ { expires 1y; add_header Cache-Control "public, immutable"; }
location / { try_files $uri /index.php$is_args$args; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { return 404; }
```

---

## Pour les développeurs

```
eclair/                  ← à envoyer sur l'hébergement
├── index.php            contrôleur frontal (sert le cache sans charger l'application)
├── .htaccess            réécriture, protections, compression, cache navigateur
├── app/lib/             code (classes Eclair\…) : Content, Blocks, Seo, Analyzer, Media, Auth…
├── app/admin/views/     écrans de l'administration
├── app/theme/           gabarits du site public + theme.css (inséré dans chaque page)
├── app/lang/            traductions de l'administration (en, es) — le français sert de clé
├── assets/admin/        CSS et JavaScript de l'administration (aucune dépendance)
├── assets/site/site.js  le seul script public (≈ 2 Ko)
├── data/                contenus, réglages, comptes (créé à l'installation, jamais public)
├── cache/               pages générées
└── media/               images envoyées
tests/run.php            tests unitaires + intégration HTTP (dont les attaques de la revue de sécurité)
tools/router.php         routeur pour le serveur PHP intégré (développement)
tools/build.php          empreintes d'intégrité + archive dist/eclair-cms-<version>.zip
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # serveur de développement
php tests/run.php                                  # tests (unitaires + HTTP)
php tools/build.php                                # archive prête à distribuer
```

`PHP_CLI_SERVER_WORKERS` permet au site de s'appeler lui-même (vérification de son adresse) : avec un seul processus, la vérification échoue simplement et les redirections vers l'adresse officielle restent désactivées.

Le thème se personnalise en modifiant `app/theme/*.php` et `app/theme/theme.css` (garde une sauvegarde : le contrôle d'intégrité signalera ces fichiers comme modifiés, c'est normal).

## Licence

Éclair est distribué sous **licence MIT** (voir [`LICENSE`](LICENSE)) : tu peux l'utiliser, le modifier et le redistribuer, y compris pour un usage commercial, à condition de conserver la mention de copyright et la licence.

Une partie des icônes de l'administration est dérivée de [Lucide](https://lucide.dev) (licence ISC) : voir [`THIRD-PARTY-NOTICES.txt`](THIRD-PARTY-NOTICES.txt).
