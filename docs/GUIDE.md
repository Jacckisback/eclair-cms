<p align="center">
  <b>Français</b> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Guide d'Éclair CMS

Tout ce qu'il faut savoir pour installer, utiliser, sécuriser et maintenir Éclair. Pour la présentation, retourne au [README](../README.md).

## Sommaire

1. [Installation](#installation)
2. [Fonctionnalités en détail](#fonctionnalités-en-détail)
3. [Utiliser Éclair au quotidien](#utiliser-éclair-au-quotidien)
4. [Thèmes](#thèmes)
5. [Boutique](#boutique)
6. [Assistant IA](#assistant-ia)
7. [Connecteur MCP (Claude et ChatGPT)](#connecteur-mcp-claude-et-chatgpt)
8. [Sécurité](#sécurité)
9. [Mettre à jour, sauvegarder, déménager](#mettre-à-jour-sauvegarder-déménager)
10. [Dépannage](#dépannage) (dont [Nginx](#nginx))
11. [Pour les développeurs](#pour-les-développeurs)

---

## Installation

### Prérequis

- **PHP 8.1 ou plus** (8.3 recommandé), avec les extensions `mbstring`, `json`, `dom` et `session` (présentes par défaut presque partout).
- Recommandé : `gd` (images WebP), `zip` (sauvegardes en un clic), `curl` (IndexNow).
- Un serveur **Apache ou LiteSpeed** (le cas de la quasi-totalité des mutualisés : OVH, o2switch, Hostinger, IONOS, PlanetHoster…). Pour Nginx, voir [plus bas](#nginx).
- Un certificat HTTPS (gratuit avec Let's Encrypt chez presque tous les hébergeurs).

L'assistant vérifie tout cela pour toi et explique quoi corriger.

### Étapes

1. Télécharge l'archive [`eclair-cms-1.4.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.4.0.zip) (432 Ko) et décompresse-la.
2. Envoie **le contenu du dossier `eclair`** dans le dossier public de ton hébergement (`public_html`, `www`…) avec ton logiciel FTP (FileZilla…) ou le gestionnaire de fichiers de ton hébergeur.
   > ⚠️ Les fichiers `.htaccess` sont cachés : active « Afficher les fichiers cachés » pour être sûr de les envoyer.
3. Ouvre l'adresse de ton site : **l'assistant d'installation démarre** (en 7 langues).
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

## Fonctionnalités en détail

### ⚡ Vitesse

- **Cache statique automatique** : chaque page est générée une fois, puis servie sans charger l'application. Le cache se vide tout seul à chaque modification (et à l'heure prévue pour un article programmé).
- **CSS intégré dans la page** (17 à 23 Ko selon le thème et la boutique, 5 à 6 Ko compressés), polices système : aucune requête bloquante, aucun décalage de mise en page.
- **Images optimisées à l'envoi** : redimensionnées (2000 px max), déclinées en **WebP responsive** (480, 800, 1200, 1600 px), dimensions explicites, chargement différé, priorité donnée à l'image principale.
- ETag et réponses `304 Not Modified`, compression Brotli/Gzip et cache navigateur d'un an pour les fichiers statiques (via `.htaccess`).

### 🔎 SEO

- URL propres, **redirection 301 automatique** quand une adresse change, gestionnaire de redirections (pratique pour reprendre un ancien site WordPress ou Wix).
- Titre, méta-description et **aperçu Google en direct** dans l'éditeur, avec compteurs.
- **Analyse SEO en temps réel** avec score : longueur du titre et de la description, expression-clé (titre, URL, introduction, intertitres, densité), nombre de mots, structure, textes alternatifs, maillage interne, image à la une…
- **Données structurées JSON-LD** complètes : WebSite (+ recherche), Organization / Person / LocalBusiness (adresse, horaires, profils sociaux, politique de retour), WebPage / AboutPage / ContactPage, BlogPosting (auteur, dates, section), BreadcrumbList, FAQPage, ProfilePage, et pour la boutique Product / ProductGroup / Offer (prix, stock, livraison).
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

- 18 blocs : texte, intertitre (H2-H4), liste, citation, image, encadré, FAQ, tableau, bouton, vidéo YouTube/Vimeo (sans cookie avant le clic), code, séparateur, bannière, atouts, équipe (portraits, fonctions, présentations), derniers articles, produits (vitrine de la boutique), formulaire de contact.
- Tape **`/`** sur une ligne vide pour choisir un bloc, glisser-déposer, barre de mise en forme (gras, italique, lien, code, surlignage).
- **Liens internes assistés** : en tapant un lien, l'éditeur propose tes pages et articles (maillage interne en 2 secondes).
- **Collage intelligent** depuis Word, Google Docs ou une page web : titres, listes, tableaux et gras sont convertis en blocs propres.
- Enregistrement `Ctrl+S`, brouillon local de secours (rien n'est perdu en cas de coupure), aperçu réel avant publication, **historique des 20 dernières versions**, publication programmée.

### 🧩 Le reste

Médiathèque (glisser-déposer, textes alternatifs, détection des images utilisées), menus en glisser-déposer (en-tête et pied de page), catégories « piliers » pour un cocon sémantique, recherche sur le site, formulaire de contact anti-spam **sans captcha** (champ piège, jeton, limites par réseau et par jour) avec boîte de réception, comptes administrateur et rédacteur, 6 thèmes avec aperçu en direct, mode sombre automatique, 6 polices système, couleur principale avec contraste WCAG garanti, administration en 7 langues (français, anglais, espagnol, allemand, italien, portugais, chinois), site public en 6 langues (fr, es, en, de, it, pt).

---

## Utiliser Éclair au quotidien

| Je veux… | Où |
|---|---|
| Écrire un article | Tableau de bord › **Écrire un article** |
| Demander un article, une page, une image à l'IA | Champ **« Que veux-tu créer aujourd'hui ? »** du tableau de bord, ou **✨ Assistant IA** (`Ctrl+J`) |
| Modifier une page depuis le site | Bouton **✏️ Modifier** (visible seulement quand tu es connecté) |
| Changer de thème, logo, couleur, polices | Réglages › Apparence |
| Ouvrir la boutique | Réglages › Boutique |
| Ajouter un produit | Contenus › **+ Produit** |
| Choisir la page d'accueil | Réglages › Général |
| Autoriser ou bloquer les IA | Réglages › IA & GEO |
| Activer l'assistant IA | Réglages › Assistant IA |
| Relier Google Search Console | Réglages › SEO |
| Ajouter un lien au menu | Menus |
| Rediriger une ancienne URL | Redirections |
| Activer la double authentification | Mon profil › Double authentification |
| Télécharger une sauvegarde | Sécurité › Sauvegarde |

Raccourcis de l'éditeur : `/` insérer un bloc · `Ctrl+S` enregistrer · `Ctrl+K` lien · `Ctrl+B` gras · `Ctrl+I` italique · `Entrée` nouveau paragraphe · `Maj+Entrée` retour à la ligne · `Ctrl+J` assistant IA.

---

## Thèmes

Éclair est livré avec **6 thèmes**. Choisis-en un dans **Réglages › Apparence** : l'**aperçu en direct** montre ton vrai site (accueil, blog, un article, la boutique, un produit) sur ordinateur ou sur mobile, et rien n'est publié tant que tu n'as pas enregistré. Tu peux naviguer dans l'aperçu : les liens restent dans l'aperçu.

| Thème | Esprit | Pour qui |
|---|---|---|
| **Éclair** | Clair, moderne et polyvalent : le thème d'origine | Tous les sites |
| **Éditorial** | Un magazine : typographie à empattements, filets fins, lettrine, article à la une | Blogs, médias, auteurs |
| **Atelier** | Chaleureux et artisanal : tons crème et terre cuite, formes douces | Artisans, restaurants, bien-être |
| **Studio** | Portfolio minimaliste : noir et blanc, grands titres, images à vif | Photographes, designers, agences |
| **Boutique** | Pensé pour vendre : photos carrées, en-tête fixe, boutons francs | Boutiques, créateurs |
| **Contraste** | Lisibilité maximale : grand texte, contrastes forts, liens soulignés | Services publics, associations, seniors |

Choisir un thème applique sa couleur et ses polices conseillées : tu peux les changer ensuite. Deux réglages de mise en page complètent le thème : l'**en-tête** (logo à gauche et menu à droite, ou tout centré) et les **listes d'articles et de produits** (grille de cartes, ou liste avec l'image à gauche).

**Pourquoi c'est léger** : un thème Éclair n'est qu'une feuille de style de 1,5 à 3 Ko, ajoutée à celle de base. Le HTML ne change pas d'un thème à l'autre : la vitesse, l'accessibilité, les données structurées et le référencement restent identiques. Seul le thème actif est inséré dans la page, et le mode sombre automatique fonctionne avec chacun.

---

## Boutique

Le **mode boutique léger** sert à vendre quelques dizaines de produits sans la lourdeur d'un e-commerce : pas de panier, pas de comptes clients, pas de base de données, aucune donnée bancaire sur ton serveur. Chaque produit a sa fiche, optimisée pour Google et les IA, et son bouton **Acheter** mène à un **lien de paiement Stripe** (Payment Links) : Stripe encaisse, envoie les reçus et gère les remboursements.

### Ouvrir la boutique

1. **Publie tes mentions légales** (l'installation en a créé un squelette à compléter) **et tes conditions générales de vente**. La boutique refuse de s'ouvrir sans elles : fais-les relire, elles engagent ta responsabilité de vendeur.
2. Dans **Réglages › Boutique** : choisis ces deux pages, la devise, les frais et délais de livraison, les pays livrés et le délai de retour (dans l'UE, la vente à distance donne au moins 14 jours pour se rétracter, sauf exceptions comme un produit personnalisé).
3. Clique sur **Créer la page de remerciement** : Stripe y renverra tes clients après le paiement.
4. Active **Boutique ouverte**, enregistre, puis ajoute la boutique au menu (Menus › Boutique).

Le catalogue est à l'adresse `/boutique` (`/tienda`, `/shop`, `/negozio` ou `/loja` selon la langue du site), modifiable ; si tu la changes, les anciennes adresses sont redirigées.

### Créer une fiche produit

Contenus › **+ Produit**. En haut de l'éditeur, la fiche produit :

- **Prix** et **prix barré** (facultatif : la remise s'affiche en pourcentage), **disponibilité** (en stock, épuisé, précommande).
- **Lien de paiement** : le lien Stripe du produit (`https://buy.stripe.com/…`).
- **Photos** : la première est la principale (Google, réseaux sociaux) ; vise au moins 3 vues.
- **Variantes** (taille, couleur…) : chacune peut avoir son prix et son lien. Sur le site, le choix se fait sans JavaScript.
- **Détails pour Google Shopping** : marque, référence, code-barres (GTIN/EAN), état (neuf, occasion, reconditionné), produit numérique ou service (pas de livraison).

Dessous, décris le produit avec les blocs habituels, et range-le dans une **catégorie de produits** (Catégories › « Elle regroupe : des produits »). L'analyse SEO vérifie en plus le prix, le lien de paiement, le nombre de photos et les identifiants. Pour mettre des produits en avant sur une page, ajoute le bloc **Produits**.

Avec l'assistant IA : joins une photo et demande « Crée la fiche produit de la photo jointe. Prix : 29,90 € ». Il rédige un brouillon complet, mais **n'invente jamais un prix, une remise ou un lien de paiement** : ce qui manque, tu le complètes.

### Relier Stripe (Payment Links)

1. Dans Stripe, ouvre **Liens de paiement** (Payment Links) et crée un lien : produit, prix, et collecte de l'adresse de livraison si besoin.
2. Dans l'onglet **Après le paiement**, choisis de rediriger le client vers ta page de remerciement (son adresse est affichée dans Réglages › Boutique).
3. Dans les réglages de Stripe (**E-mails clients**), active les reçus des paiements réussis.
4. Copie le lien et colle-le dans la fiche produit. Éclair y ajoute la référence du produit (`client_reference_id`) : chaque paiement indique ce qui a été acheté.

Chaque commande t'arrive dans Stripe (et par e-mail si tu l'as activé) : tu prépares et expédies, Stripe gère le remboursement si besoin. Un autre prestataire fonctionne aussi, du moment que son lien de paiement commence par `https://`.

### Ce qui est fait pour Google et les IA

- **Données structurées** Product et Offer sur chaque fiche (ProductGroup pour les variantes) : prix, devise, stock, état, marque, frais et délais de livraison, et **politique de retour** au niveau de ton entreprise. Ce sont les informations que Google affiche dans les résultats produits.
- **Flux produits** `/products.xml` au format Google Merchant Center : ajoute-le comme source de produits dans Merchant Center pour apparaître gratuitement dans l'onglet Shopping.
- Catégories de produits en pages piliers, sitemap avec toutes les photos, fil d'Ariane.
- Pour les IA : prix et disponibilité de chaque produit dans `llms.txt`, et dans la version Markdown de chaque fiche (ajoute `.md` à son adresse).

### Combien ça coûte ?

Rien côté Éclair. Stripe ne prend ni abonnement ni frais d'installation, et les liens de paiement sont inclus : tu paies seulement une commission par paiement, par exemple **1,5 % + 0,25 €** pour une carte standard de l'Espace économique européen (2,8 % + 0,25 € pour une carte premium, 3,15 % + 0,25 € pour une carte hors EEE, plus 2 % en cas de conversion de devise). Tarifs relevés le 30 octobre 2026 sur [stripe.com/fr/pricing](https://stripe.com/fr/pricing) ; ils peuvent évoluer.

### Ce que la boutique ne fait pas

Pas de panier multi-produits, de codes promo, de comptes clients ni de gestion de stock automatique : l'idée est de rester simple. Si tu vends des centaines de références ou que tu as besoin de ces fonctions, un e-commerce complet (Shopify, WooCommerce, PrestaShop) sera plus adapté. Les prix affichés sont ceux que tu saisis : affiche-les TTC si tu vends à des particuliers.

---

## Assistant IA

L'assistant rédige des brouillons, améliore tes textes et crée des images **dans le ton de ton site**. Tu le pilotes en langage naturel, comme un collègue : « Écris un article sur le choix des couleurs d'une marque, avec une image. » Il cherche d'abord si le sujet existe déjà, crée l'image, rédige le brouillon complet (titre, L'essentiel, intertitres, listes ou tableaux, FAQ, méta-description, liens internes), puis te le présente avec ses scores SEO et GEO. **Rien n'est publié sans ton clic.**

Il fonctionne avec **Claude** (Anthropic) ou **ChatGPT** (OpenAI), avec ta propre clé d'API : pas d'abonnement Éclair, tu paies directement le fournisseur, au réel. Tu préfères utiliser ton abonnement Claude ou ChatGPT ? Passe par le [connecteur MCP](#connecteur-mcp-claude-et-chatgpt).

### Activer l'assistant (5 minutes)

1. Crée une clé d'API chez le fournisseur choisi : [console.anthropic.com](https://console.anthropic.com/settings/keys) pour Claude, [platform.openai.com](https://platform.openai.com/api-keys) pour ChatGPT, et ajoute quelques dollars de crédit.
2. Dans **Réglages › Assistant IA**, choisis le fournisseur, colle la clé, choisis le modèle, puis clique sur **Tester la connexion** (gratuit : aucun texte n'est généré).
3. Pour les images, choisis un générateur : **GPT Image 2.5** (même clé que ChatGPT), **FLUX 3** ([dashboard.bfl.ai](https://dashboard.bfl.ai)) ou **Ideogram 3** ([ideogram.ai](https://ideogram.ai/manage-api), idéal pour les visuels avec du texte). Sans générateur, l'assistant utilise ta médiathèque et les photos que tu lui joins.
4. Clique sur **Rédiger mon profil à partir de mon site**, relis, ajuste, enregistre.

L'assistant apparaît alors dans le menu (**✨ Assistant IA**, `Ctrl+J`), dans l'éditeur et sur le tableau de bord. Les rédacteurs y ont accès si tu le permets.

**Claude ou ChatGPT, au choix** : enregistre les deux clés et un sélecteur apparaît en haut de l'assistant pour choisir l'IA à chaque conversation. Ton choix est retenu pour ton compte, retouches de l'éditeur comprises ; le fournisseur choisi dans les réglages reste celui par défaut.

### Le profil éditorial

C'est ce qui fait écrire l'IA **comme toi** : qui tu es et ce que tu proposes, pour qui tu écris, ton et style (tutoiement ou vouvoiement, longueur des phrases, humour), valeurs, règles à respecter (« toujours un lien vers la page contact », « jamais de prix ») et style des images. Un clic le rédige à partir de tes pages et articles ; tu gardes le dernier mot. À chaque demande, l'assistant suit aussi les règles d'analyse d'Éclair : ses brouillons visent un score SEO et GEO élevé dès le départ.

### Ce que tu peux demander

- « Écris un article sur l'entretien d'un vélo électrique au printemps, avec une image. »
- « Ajoute Léa Martin, directrice artistique, à la page équipe avec la photo jointe. » (joins la photo avec 📎, un glisser-déposer ou un collage)
- « Crée une page Nos services qui présente nos trois formules. »
- « Propose-moi 5 idées d'articles pour attirer de nouveaux clients. »
- « Relis ma page d'accueil et propose des améliorations. »
- Dans l'éditeur : « Ajoute une FAQ à cet article », « Rends l'introduction plus percutante ».

Chaque action produit une **carte** sous la réponse :

| Carte | Ce qui s'est passé | Tes boutons |
|---|---|---|
| 📝 Brouillon | Nouvel article ou nouvelle page, jamais publié | Ouvrir · Aperçu · Publier maintenant ou programmer |
| ✏️ Modification proposée | Pour un contenu **publié** : rien ne change avant ton accord | Aperçu · Appliquer · Ignorer, puis Annuler si besoin |
| ✅ Mis à jour | Un **brouillon** modifié directement | Ouvrir · Aperçu · Annuler |
| ✅ Dans l'éditeur | Le contenu ouvert est modifié dans l'éditeur | Annuler (puis tu enregistres) |
| 🖼️ Image | Image créée, optimisée (WebP) et rangée dans la médiathèque | Image à la une (dans l'éditeur) |

« Annuler » reste possible tant que personne n'a retouché le contenu depuis ; sinon, ses versions précédentes restent dans l'**historique des versions** de l'éditeur. L'historique garde tes 30 dernières conversations. L'assistant n'invente jamais de faits sur ton activité (prix, horaires, chiffres, témoignages) : quand une information manque, il laisse un repère visible comme **[prix à confirmer]** et te le signale.

### Retouches dans l'éditeur

- **Sélectionne un passage**, puis ✨ dans la barre de mise en forme : améliorer, corriger les fautes, raccourcir, développer, simplifier, plus chaleureux, plus professionnel, traduire. Un clic sur « Annuler » rétablit le texte.
- **L'essentiel** : bouton « Rédiger ».
- **Onglet SEO** : titre SEO et méta-description, expression-clé, idées de titres.
- **Onglet Analyse** : ajouter une FAQ, textes alternatifs manquants, « Améliorer avec l'assistant ».
- **Image à la une** : « Créer avec l'IA » (articles).
- **Médiathèque** : « Décrire avec l'IA » écrit le texte alternatif d'une image en la regardant.

### Combien ça coûte ?

Tu paies le fournisseur selon l'usage réel. Éclair calcule chaque dépense à partir des jetons facturés et l'affiche dans Réglages › Assistant IA. Estimation pour un article complet (environ 25 000 jetons lus et 7 000 écrits, recherches et vérifications comprises) :

| Modèle | Prix pour 1 million de jetons (lus / écrits) | Article complet |
|---|---|---|
| Claude Opus 5.5 (le plus doué) | 4 $ / 20 $ | ≈ 0,24 $ |
| Claude Sonnet 5.5 | 2 $ / 10 $ | ≈ 0,12 $ |
| Claude Haiku 5.5 | 0,10 $ / 0,50 $ | < 0,01 $ |
| GPT-6 Astra | 10 $ / 50 $ | ≈ 0,60 $ |
| GPT-6.1 Sol | 2 $ / 10 $ | ≈ 0,12 $ |
| GPT-6.1 Terra | 1 $ / 5 $ | ≈ 0,06 $ |
| GPT-6 Luna | 0,10 $ / 0,50 $ | < 0,01 $ |

Une image coûte environ 0,05 $ (FLUX 3 en 1 mégapixel : 0,048 $). Tarifs publics relevés le 29 octobre 2026 sur les pages officielles d'[Anthropic](https://platform.claude.com/docs/en/about-claude/pricing), d'[OpenAI](https://developers.openai.com/api/docs/pricing) et de [Black Forest Labs](https://docs.bfl.ml/release-notes) ; ils peuvent évoluer. Les instructions fixes de l'assistant sont mises en cache chez le fournisseur, ce qui réduit le coût des demandes suivantes.

**Garde-fous** : budget mensuel maximum (10 $ par défaut), vérifié avant chaque étape d'une demande : une fois atteint, l'assistant s'arrête jusqu'au mois suivant. S'y ajoutent un nombre de demandes par personne et par jour (60 par défaut) et deux demandes à la fois au plus par personne. Budget et quota se règlent dans Réglages › Assistant IA.

### Sécurité et confidentialité

- **L'IA prépare, tu décides** : elle peut chercher et lire tes contenus, créer des brouillons, modifier un brouillon (annulable), proposer une modification d'un contenu publié et créer des images. Elle ne peut **ni publier, ni supprimer, ni toucher aux réglages, aux menus ou aux comptes**. Publier reste un clic humain.
- **Clés chiffrées** sur ton serveur (libsodium, ou AES-256-GCM), jamais envoyées au navigateur, jamais affichées (seul un indice « sk-ant…ab12 » l'est), **exclues des sauvegardes** comme l'historique des conversations. Pour une protection renforcée, ajoute `define('ECLAIR_SECRET', 'une-longue-phrase-secrète');` dans `eclair-config.php` (à faire avant d'enregistrer tes clés).
- **Ce qui part chez le fournisseur** : ta demande, les images que tu joins, le profil éditorial et le contexte utile (liste de tes pages et articles, contenu ouvert dans l'éditeur). N'y mets pas de données sensibles. Selon leurs conditions (vérifiées le 29 octobre 2026), [Anthropic](https://www.anthropic.com/legal/commercial-terms) n'entraîne pas ses modèles sur les données envoyées par API, et [OpenAI](https://help.openai.com/en/articles/5722486-api-data-usage-policies) non plus par défaut.
- **Contenus assainis** : tout ce que l'IA écrit passe par la même liste blanche que l'éditeur, et ses retouches ne touchent que le texte visible (jamais l'adresse d'un lien). Les images générées sont téléchargées en HTTPS depuis une adresse publique vérifiée, puis ré-encodées comme un envoi.
- **Injections de consignes** : les contenus lus par l'IA sont traités comme des données, jamais comme des ordres ; et même si une page piégée tentait de la détourner, elle ne pourrait que préparer un brouillon ou une proposition, que tu verrais avant tout effet.

### Si l'assistant ne répond pas

- **« Clé d'API refusée »** : la clé a été copiée incomplète ou révoquée : colle-la de nouveau et teste la connexion.
- **« Crédit épuisé »** : recharge ton compte chez le fournisseur.
- **« Budget IA du mois atteint »** : augmente le budget dans Réglages › Assistant IA, ou attends le mois suivant.
- **La réponse arrive d'un bloc au lieu de s'afficher au fil de l'eau** : un tampon de ton hébergeur retient le flux. Tout fonctionne, mais sans l'effet « en direct ». Sur Nginx, ajoute `fastcgi_buffering off;` dans le bloc PHP.
- **« Impossible de joindre… »** : ton hébergeur bloque les connexions sortantes. Demande-lui d'autoriser `api.anthropic.com` ou `api.openai.com` (et le générateur d'images choisi).
- **L'assistant ne s'affiche pas** : il faut l'extension PHP cURL et le chiffrement (sodium ou openssl), présents chez presque tous les hébergeurs ; l'assistant d'installation les vérifie.

---

## Connecteur MCP (Claude et ChatGPT)

Tu préfères travailler dans **Claude** ou **ChatGPT**, avec l'abonnement que tu as déjà ? Le connecteur MCP (*Model Context Protocol*, le standard ouvert qui relie les IA à des outils) leur ouvre ton site : ils lisent tes contenus, rédigent des brouillons dans ton ton et préparent des modifications. **Aucune clé d'API, aucun coût pour le site** : c'est ton abonnement qui travaille. Comme avec l'assistant intégré, **rien n'est publié ni supprimé** sans toi.

### Activer le connecteur (3 minutes)

1. Ton site doit être en **HTTPS** : Claude et ChatGPT refusent les autres adresses.
2. Dans **Réglages › Assistant IA**, en bas de page, active **Autoriser les connexions MCP**, enregistre, puis copie l'**adresse du connecteur** (`https://ton-site.fr/_eclair/mcp`).
3. Ajoute-la dans ton IA :
   - **Claude** (web et application de bureau) : *Personnaliser › Connecteurs › Ajouter › Ajouter un connecteur personnalisé*, donne un nom (« Mon site »), colle l'adresse, **Continuer**, garde les réglages d'authentification détectés, puis **Ajouter**. Sur les offres Team et Enterprise, c'est un propriétaire de l'organisation qui l'ajoute ; les membres cliquent ensuite sur **Connecter**.
   - **ChatGPT** (sur le web) : *Plugins › + › Ajouter un serveur MCP personnalisé*, donne un nom, colle l'adresse dans **Connexion**, choisis l'authentification **OAuth**, accepte l'avertissement, puis crée le plugin.
4. Une page de ton site s'ouvre : connecte-toi si besoin (double authentification comprise), vérifie l'application et son adresse de retour (`claude.ai` ou `chatgpt.com`), puis **Autoriser**.

C'est prêt. Demande par exemple : « Regarde mon site et propose-moi 5 idées d'articles », « Écris en brouillon un article sur l'entretien d'un vélo électrique » ou « Relis ma page d'accueil et propose une accroche plus directe ».

**Offres compatibles** (vérifiées le 30 octobre 2026) :

| Offre | Lire le site | Brouillons et propositions |
|---|---|---|
| **Claude** Free, Pro, Max, Team, Enterprise | ✅ | ✅ (Free : un seul connecteur personnalisé) |
| **ChatGPT** Business, Enterprise, Edu | ✅ | ✅ |
| **ChatGPT** Pro | ✅ | ❌ (lecture seule) |
| **ChatGPT**, autres offres | non prévu dans l'aide d'OpenAI | |

Sources : [aide Claude sur les connecteurs personnalisés](https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp), [aide OpenAI sur les serveurs MCP personnalisés](https://help.openai.com/en/articles/12584461-connecting-custom-mcp-servers-in-chatgpt) et [documentation OpenAI](https://developers.openai.com/api/docs/guides/custom-mcp-server). Ces offres évoluent vite : vérifie dans ton application.

### Ce que l'IA peut faire

Elle commence par `get_site`, puis utilise les outils de l'assistant intégré, sauf la création d'images (payée par la clé du site) :

| Outil | Rôle |
|---|---|
| `get_site` | inventaire du site (pages, articles, produits, catégories et leurs identifiants), profil éditorial, règles SEO et GEO |
| `search_content` · `get_content` | chercher un contenu, le lire en Markdown |
| `find_media` | chercher une image dans la médiathèque |
| `create_draft` | créer un article, une page ou une fiche produit **en brouillon** |
| `update_content` | modifier un brouillon ; pour un contenu publié, préparer une **proposition** |

Chaque brouillon revient avec un lien pour l'**ouvrir dans Éclair** ; chaque proposition, avec un lien qui l'**applique dans l'éditeur** sans l'enregistrer : tu vérifies, puis tu enregistres (ou tu fermes, et rien ne change). Claude et ChatGPT te demandent en plus ton accord avant chaque action d'écriture, selon leurs propres réglages.

### Sécurité du connecteur

- **Désactivé par défaut** : tant qu'il l'est, aucune de ses adresses ne répond.
- **OAuth 2.1** : PKCE obligatoire, enregistrement automatique de l'application sans rien stocker, adresse de retour vérifiée à l'identique.
- **Ton accord explicite**, après connexion à ton compte (double authentification comprise), sur une page qui ne révèle jamais l'adresse de ton administration.
- **Jetons d'une heure**, renouvelés automatiquement et remplacés à chaque renouvellement ; après 60 jours sans usage, il faut redonner ton accord. Seules leurs empreintes sont stockées, hors des sauvegardes.
- **Couper l'accès** : un bouton par application dans Réglages › Assistant IA. Désactiver le connecteur coupe toutes les applications ; changer de mot de passe ou déconnecter tous tes appareils aussi.
- **Mêmes droits que l'assistant intégré** : les rédacteurs n'y ont accès que si « Les rédacteurs peuvent utiliser l'assistant » est coché ; au plus 60 modifications par heure et par application, contre les boucles.
- **Ce qui part chez Anthropic ou OpenAI** : les contenus que l'IA lit pour te répondre, selon les conditions de ton abonnement.

### Si la connexion échoue

- **L'IA ne joint pas le site, ou l'autorisation échoue** : vérifie que le site est en HTTPS et que l'adresse se termine par `/_eclair/mcp`. Sur Apache, garde le `.htaccess` d'Éclair à jour : il transmet l'en-tête d'autorisation que certains hébergements retirent.
- **Éclair est installé dans un sous-dossier** : certaines applications cherchent l'autorisation à la racine du domaine. Installe plutôt Éclair à la racine d'un domaine ou d'un sous-domaine.
- **Pare-feu ou protection anti-robots** (Cloudflare…) : laisse passer `/_eclair/` et `/.well-known/`.
- **Une proposition ne s'applique plus** : le contenu a changé depuis ; redemande la modification à l'IA.

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
- **Assistant IA borné** : brouillons et propositions seulement, clés d'API chiffrées et exclues des sauvegardes, budget mensuel et quota quotidien (détails dans [Assistant IA](#sécurité-et-confidentialité)).
- **Journaux bornés** (champs tronqués, 1 Mo maximum) : impossible de les faire gonfler.
- **Contrôle d'intégrité** : compare chaque fichier à son empreinte d'origine et repère les fichiers suspects, y compris un `.php` sans ligne de protection dans `data/` ou `cache/`.
- **Test de protection en direct** : la page Sécurité vérifie depuis ton navigateur que les fichiers internes sont bien inaccessibles, et que l'adresse du site est vérifiée.

---

## Mettre à jour, sauvegarder, déménager

**Sauvegarder** : Sécurité › Télécharger une sauvegarde (archive ZIP de `data/` et `media/`). Sans l'extension zip, copie ces deux dossiers par FTP.

**Mettre à jour Éclair** :
1. Fais une sauvegarde.
2. Par FTP, **supprime** les dossiers `app/` et `assets/`, puis envoie ceux de la nouvelle version avec `index.php` et `.htaccess` (supprimer d'abord évite de garder d'anciens fichiers, que le contrôle d'intégrité signalerait).
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
├── app/theme/themes/    les 5 thèmes optionnels (une feuille CSS chacun, ajoutée à theme.css)
├── app/lang/            traductions de l'administration (6 langues, JSON compressé) — le français sert de clé
├── assets/admin/        CSS et JavaScript de l'administration (aucune dépendance)
├── assets/site/site.js  le seul script public (2,4 Ko, 1,2 Ko compressé)
├── data/                contenus, réglages, comptes (créé à l'installation, jamais public)
├── cache/               pages générées
└── media/               images envoyées
i18n/                    sources lisibles des traductions (compilées par tools/i18n.php)
tests/run.php            tests unitaires + intégration HTTP (dont les attaques de la revue de sécurité)
tests/fake-ai.php        faux fournisseurs d'IA (Claude, ChatGPT, images) : l'assistant est testé sans clé ni dépense
tests/shop.php           thèmes et boutique (fiches, Stripe, JSON-LD, flux Merchant Center)
tests/mcp.php            connecteur MCP (OAuth, PKCE, protocole, révocation) et choix de l'IA
tools/router.php         routeur pour le serveur PHP intégré (développement)
tools/build.php          empreintes d'intégrité + archive dist/eclair-cms-<version>.zip (PHP compacté)
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # serveur de développement
php tests/run.php                                  # tests (unitaires + HTTP)
php tools/build.php                                # archive prête à distribuer
```

`PHP_CLI_SERVER_WORKERS` permet au site de s'appeler lui-même (vérification de son adresse) : avec un seul processus, la vérification échoue simplement et les redirections vers l'adresse officielle restent désactivées.

Le thème se personnalise en modifiant `app/theme/*.php`, `app/theme/theme.css` et `app/theme/themes/*.css` (garde une sauvegarde : le contrôle d'intégrité signalera ces fichiers comme modifiés, c'est normal). Dans l'archive, le PHP est compacté (`php -w`, sans commentaires) pour rester sous 1 Mo : les sources commentées sont sur GitHub.

---

Éclair est distribué sous [licence MIT](../LICENSE). Une partie des icônes de l'administration est dérivée de [Lucide](https://lucide.dev) (licence ISC) : voir [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
