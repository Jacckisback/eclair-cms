# Passer à Éclair 2.0

La 2.0 range le thème de ton site dans un dossier qui lui appartient (`site/`), que les mises à jour ne touchent jamais. Tes contenus, réglages, comptes, images, CSS personnalisé et adresses ne changent pas. Si tu avais modifié directement des fichiers du thème d’Éclair (`app/theme/…`) ou ajouté des fichiers dans `assets/site/`, la mise à jour les retrouve et les range dans un thème « legacy », activé automatiquement : ton site garde son apparence.

> Avant toute mise à jour : **Sécurité › Télécharger une sauvegarde**.

## 1. Mise à jour en un clic (depuis la 1.10 ou plus récente)

> **Hébergement en PHP 8.1** : la 1.14.1 ne fonctionne pas sous PHP 8.1 (pages publiques en erreur 500 hors cache, mise à jour en un clic impossible ; corrigé dans la 2.0). Passe d'abord en PHP 8.2 ou plus récent dans l'espace client de ton hébergeur (recommandé : 8.1 n'est plus maintenu par le projet PHP) puis mets à jour en un clic, ou mets à jour par FTP (§2), testé aussi en PHP 8.1.

1. Mises à jour › Vérifier, puis Installer.
2. L’ancien updater pose le nouveau code, garde l’ancien dans `cache/update/previous/`, vérifie que le site répond, et revient tout seul en arrière sinon.
3. Au premier passage du nouveau code, les migrations s’exécutent (une seule fois, journalisées dans `data/migrations.php`) :
   * création de `site/` et de ses protections ;
   * recherche, dans la copie gardée de l’ancien code, des gabarits, CSS du thème et actifs **modifiés ou ajoutés** (comparés au manifeste signé de ton ancienne version) ;
   * copie brute de tout ce qui est trouvé dans `site/themes/.legacy-originals/`, puis création et activation du thème `legacy`.
4. Va dans **Thèmes** : un message indique ce qui a été repris, et ce qui est « à vérifier » (fichiers qu’aucun gabarit du contrat ne couvre).

Ce parcours est testé avec la **vraie archive 1.14.1** et son propre updater (`tests/transition.php`).

## 2. Mise à jour par FTP

Une mise à jour par FTP remplace `app/` et `assets/` : sans précaution, des gabarits modifiés à la main seraient perdus.

1. Télécharge une sauvegarde (Sécurité).
2. **Si tu as modifié des fichiers dans `app/theme/` ou ajouté des fichiers dans `assets/site/`** : avec ton logiciel FTP, déplace le dossier `app` dans `cache` et renomme-le `legacy-app` (tu obtiens `cache/legacy-app/`), et de même `assets` → `cache/legacy-assets/`. Ces dossiers ne sont pas accessibles depuis le web.
3. Envoie le contenu du dossier `eclair` de l’archive 2.0 (y compris `.htaccess` si tu ne l’as jamais modifié ; sinon garde le tien et compare-le à celui de l’archive, voir §4).
4. Ouvre l’administration : les migrations rangent tes personnalisations dans le thème `legacy`. Vérifie la page **Thèmes**, puis supprime `cache/legacy-app/` et `cache/legacy-assets/`.

## 3. Revenir en arrière

* **Mises à jour › Revenir à la version précédente** remet l’ancien code. `site/` n’est jamais touché ; avec la 1.14.1, l’ancien code retrouve ses propres gabarits (gardés dans `cache/update/previous/`). Testé dans `tests/transition.php`.
* Les contenus créés depuis la mise à jour restent ; les sessions révoquées et les secrets remplacés ne reviennent pas.
* Un thème ne s’affiche pas comme prévu ? **Thèmes › Revenir au thème de base**, ou, si l’administration est inaccessible, ajoute dans `eclair-config.php` : `define('ECLAIR_SAFE_THEME', true);`

## 4. Règles serveur

### Apache / LiteSpeed (`.htaccess`)

La 2.0 ajoute une règle au `.htaccess` principal :

```apache
RewriteRule ^site/(themes|extensions)(/|$) - [R=404,L]
```

Si ton `.htaccess` a été modifié (par toi ou ton hébergeur), la mise à jour **le garde** et range la version 2.0 dans `cache/update/htaccess-2.0.0.txt` : ajoute la ligne ci-dessus juste après `RewriteRule ^(app|data|cache)(/|$) - [R=404,L]`. Même sans elle, `site/themes/.htaccess` et `site/extensions/.htaccess` (créés par la migration) refusent tout accès, et l’exécution de PHP reste interdite partout sauf `index.php`.

### Nginx

```nginx
location ~ ^/(app|data|cache)(/|$)            { return 404; }
location ~ ^/site/(themes|extensions)(/|$)    { return 404; }
location ~ ^/site/assets/.*\.(php\d?|phtml|phar|inc|html?)$ { return 404; }
location ~ /\.(?!well-known/)                 { return 404; }
location ~ \.(php\d?|phtml|phar|inc)$         { return 404; }   # sauf index.php, servi par le bloc ci-dessous
location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass php; }
location / { try_files $uri /index.php$is_args$args; }
```

Les règles des médias (`media/`) du guide existant restent valables.

## 5. Ce qui a changé pour les intégrateurs

* Ne modifie plus `app/theme/` : crée un thème dans `site/themes/<id>/` ([contrat](THEME-CONTRACT.md)) ou importe-le dans **Thèmes**.
* Les fonctions optionnelles se rangent dans `site/extensions/<id>/` ([contrat](EXTENSION-CONTRACT.md)).
* Les blocs d’un type inconnu ne sont plus supprimés à l’enregistrement : ils sont conservés et affichés comme « Bloc conservé » dans l’éditeur.
* `tools/build.php` ne signe plus qu’avec `--sign` (ou `--resign`).
