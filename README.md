<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/banner-dark.webp">
    <img src="docs/images/banner-light.webp" alt="Éclair CMS : le CMS ultra léger, ultra rapide, taillé pour Google et les IA" width="100%">
  </picture>
</p>

<p align="center">
  <a href="https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.0.0.zip"><img src="https://img.shields.io/badge/t%C3%A9l%C3%A9charger-v1.0.0-5b4cf0?style=for-the-badge" alt="Télécharger Éclair 1.0.0"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1 ou plus">
  <img src="https://img.shields.io/badge/Lighthouse-100-0cce6b?style=for-the-badge&logo=lighthouse&logoColor=white" alt="Lighthouse 100">
  <img src="https://img.shields.io/badge/base_de_donn%C3%A9es-aucune-14122b?style=for-the-badge" alt="Aucune base de données">
  <a href="LICENSE"><img src="https://img.shields.io/badge/licence-MIT-22c55e?style=for-the-badge" alt="Licence MIT"></a>
</p>

<h3 align="center">Un site professionnel en 3 minutes, sur n'importe quel hébergement mutualisé.<br>Sans base de données. Sans extensions. Sans maintenance.</h3>

<p align="center">
  <a href="#installation-en-3-minutes"><b>Installer</b></a> ·
  <a href="#pourquoi-éclair"><b>Pourquoi Éclair</b></a> ·
  <a href="#aperçu"><b>Aperçu</b></a> ·
  <a href="#sécurité"><b>Sécurité</b></a> ·
  <a href="docs/GUIDE.md"><b>Guide complet</b></a>
</p>

<br>

<p align="center">
  <img src="docs/images/showcase.webp" alt="L'éditeur d'Éclair avec l'analyse SEO et GEO à 100 sur 100, et l'article publié affiché sur un téléphone" width="100%">
</p>

## Pourquoi Éclair

WordPress sait tout faire, mais il lui faut une base de données, des extensions pour le SEO, la sécurité et le cache… et des mises à jour sans fin. Éclair réunit l'essentiel dans un CMS de **moins de 1 Mo**, pensé pour les indépendants, les artisans et les petites entreprises.

<table>
  <tr>
    <td width="33%" valign="top">
      <h3>⚡ Ultra rapide</h3>
      Chaque page est servie depuis un cache statique, sans charger l'application : <b>100/100</b> sur Lighthouse et <b>0,7 ms</b> de temps de réponse serveur (mesuré en local).
    </td>
    <td width="33%" valign="top">
      <h3>🔎 SEO intégré</h3>
      Données structurées, sitemap, redirections, aperçu Google et <b>score SEO en direct</b> dans l'éditeur, sans aucune extension.
    </td>
    <td width="33%" valign="top">
      <h3>🤖 Prêt pour les IA</h3>
      <code>llms.txt</code>, version Markdown de chaque page, règles pour 30 robots d'IA et <b>score GEO</b> pour être cité par ChatGPT, Perplexity ou Gemini.
    </td>
  </tr>
  <tr>
    <td width="33%" valign="top">
      <h3>🛡️ Sécurisé par défaut</h3>
      Double authentification, anti-bruteforce, en-têtes stricts, contenus assainis. <b>Une revue de sécurité offensive</b>, toutes les failles corrigées.
    </td>
    <td width="33%" valign="top">
      <h3>🪶 Ultra léger</h3>
      Aucune base de données : tes contenus sont des fichiers protégés. Une archive de <b>257 Ko</b>, zéro dépendance, zéro cookie sur le site public.
    </td>
    <td width="33%" valign="top">
      <h3>😊 Simple comme bonjour</h3>
      Installation guidée en 4 questions, éditeur de blocs avec <code>/</code>, checklist de démarrage. Administration en <b>français, anglais et espagnol</b>, site en 6 langues.
    </td>
  </tr>
</table>

## Éclair face à WordPress

| | WordPress classique | ⚡ Éclair |
|---|---|---|
| **Installation** | Créer une base MySQL, puis lancer l'assistant | ✅ Envoyer le dossier, répondre à 4 questions |
| **Base de données** | MySQL obligatoire | ✅ Aucune (fichiers protégés) |
| **Archive à télécharger** | ≈ 25 Mo[^wp] | ✅ 257 Ko |
| **SEO, sitemap, données structurées** | Extension à installer et à maintenir | ✅ Intégré |
| **GEO : llms.txt, Markdown, robots IA** | Extensions, rarement complet | ✅ Intégré |
| **Pages publiques** | Générées à chaque visite, sauf extension de cache | ✅ Servies depuis un cache statique |
| **Cookies sur le site public** | Souvent nombreux | ✅ Aucun |
| **Double authentification, anti-bruteforce** | Extensions | ✅ Intégrés |
| **Mises à jour d'extensions** | Régulières, première source de failles | ✅ Aucune extension à mettre à jour |

## Performances

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/performance-dark.webp">
  <img src="docs/images/performance-light.webp" alt="Lighthouse : 100 en performance, accessibilité, bonnes pratiques et SEO. 0,7 ms de temps de réponse, 7 à 10 Ko de HTML compressé, 1,2 Ko de JavaScript, 0 cookie" width="100%">
</picture>

Mesuré en local sur le site de démonstration (PHP 8.3, page d'accueil illustrée et article de 600 mots), avec Lighthouse 12 en mobile et sur ordinateur ; un score peut varier d'un point d'une passe à l'autre. Comment c'est possible :

- **Cache statique automatique** : chaque page est générée une fois, puis servie directement par `index.php`. Le cache se vide tout seul à chaque modification.
- **CSS intégré dans la page**, polices système : aucune requête bloquante, aucun décalage de mise en page.
- **Images optimisées à l'envoi** : redimensionnées, converties en **WebP responsive** (480 à 1600 px), dimensions explicites et chargement différé.
- **Un seul script public**, différé, de 1,2 Ko compressé. Compression Brotli ou Gzip et cache navigateur d'un an via `.htaccess`.

Sur un vrai mutualisé, ajoute la latence réseau et le démarrage de PHP de ton hébergeur.

## SEO et GEO : trouvé sur Google, cité par les IA

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/geo-dark.webp">
  <img src="docs/images/geo-light.webp" alt="Un article publié devient une page HTML avec JSON-LD, un sitemap avec IndexNow, un flux RSS, llms.txt, une version Markdown et un robots.txt, lus par Google, Bing, ChatGPT, Perplexity, Gemini, Claude et Copilot" width="100%">
</picture>

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>🔎 SEO</h3>
      <ul>
        <li><b>Analyse en temps réel</b> avec score : titre, méta-description, expression-clé, structure, maillage interne, textes alternatifs…</li>
        <li><b>Données structurées JSON-LD</b> : Organization ou LocalBusiness, BlogPosting, FAQPage, BreadcrumbList, auteur…</li>
        <li>Sitemap avec images et <code>hreflang</code>, flux RSS complet, Open Graph avec <b>image de partage générée</b>.</li>
        <li><b>Redirections 301 automatiques</b> quand une adresse change, et un gestionnaire de redirections pour reprendre un ancien site.</li>
        <li><b>IndexNow</b> : Bing et les autres moteurs compatibles sont prévenus à chaque publication.</li>
      </ul>
    </td>
    <td width="50%" valign="top">
      <h3>🤖 GEO (moteurs de réponse IA)</h3>
      <ul>
        <li><code>/llms.txt</code> et <code>/llms-full.txt</code> générés automatiquement (proposition <a href="https://llmstxt.org/">llmstxt.org</a>).</li>
        <li><b>Version Markdown de chaque page</b> : ajoute <code>.md</code> à n'importe quelle adresse.</li>
        <li><b>Politique robots IA en un clic</b> : ouvert, citations seulement ou fermé, pour 30 robots (GPTBot, ClaudeBot, PerplexityBot…).</li>
        <li>Bloc <b>« L'essentiel »</b> en tête d'article : le résumé que les IA reprennent volontiers.</li>
        <li><b>Score GEO</b> : FAQ, intertitres en questions, chiffres concrets, sources, auteur identifié (E-E-A-T)…</li>
      </ul>
    </td>
  </tr>
</table>

## Aperçu

<table>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/frame-dashboard.webp" alt="Tableau de bord d'Éclair : statistiques, pages à améliorer et santé du site">
      <p align="center"><b>Tableau de bord</b><br><sub>Ce qu'il faut améliorer et la santé du site, d'un coup d'œil.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/frame-geo.webp" alt="Réglages IA et GEO : choix de la politique pour les robots d'intelligence artificielle">
      <p align="center"><b>Visibilité IA</b><br><sub>Autorise, limite ou bloque les robots d'IA en un clic.</sub></p>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/frame-media.webp" alt="Médiathèque d'Éclair avec les images envoyées">
      <p align="center"><b>Médiathèque</b><br><sub>Images redimensionnées et converties en WebP automatiquement.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/frame-dark.webp" alt="Article du site public affiché en mode sombre">
      <p align="center"><b>Mode sombre automatique</b><br><sub>Le site s'adapte au thème de chaque visiteur.</sub></p>
    </td>
  </tr>
</table>

<sub>Captures réalisées sur « Atelier Nova », un site de démonstration fictif créé avec Éclair (illustrations originales).</sub>

## Sécurité

Éclair est conçu pour un environnement hostile : hébergement mutualisé, robots qui testent des mots de passe en permanence.

- **Aucune base de données**, donc aucune injection SQL. Les fichiers de données sont doublement protégés (`.htaccess` et ligne `<?php exit; ?>`), privés et écrits de façon atomique.
- **Connexion blindée** : mots de passe en Argon2id, limites par réseau et par compte (ton appareil habituel n'est jamais bloqué par un attaquant), **double authentification** avec codes de secours.
- **Mot de passe redemandé** pour les actions sensibles, mots de passe provisoires à changer, sessions courtes et révocables.
- **Site public verrouillé** : Content-Security-Policy stricte, contenus assainis par liste blanche, aucune redirection ouverte, images ré-encodées.
- **Installation protégée** : fenêtre de 2 heures, et adresse du site vérifiée par le serveur lui-même avant toute redirection.
- **Page Sécurité** : contrôle d'intégrité des fichiers, test de protection des dossiers, journal, sauvegarde en un clic.

> [!NOTE]
> **Revue de sécurité offensive.** Courses entre requêtes, force brute, redirections, injections, fichiers piégés : 17 failles ont été trouvées (2 graves, 7 moyennes, 8 mineures) et toutes corrigées. Les 13 scénarios d'attaque ont ensuite été rejoués sur la version corrigée : tous neutralisés. Les tests automatiques (`php tests/run.php`) en reprennent une grande partie.

Détails complets dans le [guide, section Sécurité](docs/GUIDE.md#sécurité).

## Installation en 3 minutes

<p align="center">
  <img src="docs/images/install.webp" alt="Installation en trois étapes : envoyer le dossier, répondre à quatre questions, le site est en ligne" width="100%">
</p>

1. **Télécharge** l'archive [`eclair-cms-1.0.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.0.0.zip) (257 Ko) et décompresse-la.
2. **Envoie le contenu du dossier `eclair`** dans le dossier public de ton hébergement (`public_html`, `www`…), fichiers cachés `.htaccess` compris.
3. **Ouvre l'adresse de ton site** : l'assistant démarre. Quatre questions, des confettis, et ton site est en ligne avec ses premières pages.

**Prérequis** : PHP 8.1 ou plus (8.3 recommandé), Apache ou LiteSpeed (la quasi-totalité des mutualisés : OVH, o2switch, Hostinger, IONOS…), un certificat HTTPS. L'assistant vérifie tout et explique quoi corriger. Nginx est aussi possible ([configuration](docs/GUIDE.md#nginx)).

> [!TIP]
> Par sécurité, l'installation doit être faite dans les **2 heures** qui suivent la première visite. Passé ce délai, supprime `data/install-window.php` par FTP et recharge la page.

## Documentation

Le [guide complet](docs/GUIDE.md) détaille tout le reste :

- [Installation](docs/GUIDE.md#installation) : prérequis, types de site, premiers pas
- [Fonctionnalités en détail](docs/GUIDE.md#fonctionnalités-en-détail) : vitesse, SEO, GEO, éditeur de blocs
- [Utiliser Éclair au quotidien](docs/GUIDE.md#utiliser-éclair-au-quotidien)
- [Mettre à jour, sauvegarder, déménager](docs/GUIDE.md#mettre-à-jour-sauvegarder-déménager)
- [Dépannage](docs/GUIDE.md#dépannage) : mot de passe oublié, erreurs 404 ou 500, Nginx…

## Pour les développeurs

PHP 8.1+ sans framework ni Composer : des classes simples dans `eclair/app/lib/`, des vues PHP et du JavaScript natif.

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # serveur de développement
php tests/run.php                                                           # 174 tests unitaires et HTTP
php tools/build.php                                                         # archive prête à distribuer
```

Structure du projet et personnalisation du thème : voir [Pour les développeurs](docs/GUIDE.md#pour-les-développeurs).

## Licence

Éclair est distribué sous **licence MIT** ([`LICENSE`](LICENSE)) : utilise-le, modifie-le et redistribue-le librement, y compris pour un usage commercial, en conservant la mention de copyright. Une partie des icônes de l'administration est dérivée de [Lucide](https://lucide.dev) (licence ISC, voir [`THIRD-PARTY-NOTICES.txt`](THIRD-PARTY-NOTICES.txt)).

<br>

<p align="center"><sub>⚡ Éclair, pour les indépendants et les petites entreprises qui veulent un site rapide, bien référencé et tranquille.</sub></p>

[^wp]: Archive de WordPress 6.9 : 24,8 Mo, d'après le [ticket Trac 64734](https://core.trac.wordpress.org/ticket/64734).
