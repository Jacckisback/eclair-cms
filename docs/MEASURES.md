# Mesures d’Éclair

Mesures produites par `php tools/measure.php` le 2026-11-07 15:36 UTC, PHP 8.3.6, serveur intégré de PHP, site neuf (type blog).
Durées indicatives (machine de mesure) ; mémoire = pointe PHP de la requête ; fichiers = fichiers PHP inclus (`get_included_files`).
« _cold » : cache de pages vidé (accueil : le plus léger de deux rendus à froid) ; « _warm » : page servie par le chemin rapide d'index.php.

| Mesure | 1.14.1 | Sources | Écart |
|---|---:|---:|---:|
| Archive ZIP du noyau | 730,7 Ko | 775,3 Ko | +6.1 % |
| Taille installée | 1 360,8 Ko | 1 453,3 Ko | +6.8 % |
| Fichiers installés | 156 | 166 | +6.4 % |
| Classes PHP (app/lib) | 74 | 80 | +8.1 % |
| Code PHP (app/lib) | 692,9 Ko | 761,6 Ko | +9.9 % |
| Traductions de l’administration (toutes) | 248,8 Ko | 265,7 Ko | +6.8 % |
| Traductions : une langue | 41,5 Ko | 44,3 Ko | +6.8 % |
| Actifs de l’administration | 269,1 Ko | 270,8 Ko | +0.7 % |
| Thème de base (app/theme) | 59,3 Ko | 59,4 Ko | +0.1 % |
| Accueil : HTML | 21,0 Ko | 21,0 Ko | +0.0 % |
| Accueil : CSS en ligne | 16,9 Ko | 16,9 Ko | +0.0 % |
| Accueil : JavaScript | 2,5 Ko | 2,5 Ko | +0.0 % |
| Accueil : polices | 0 | 0 |  |

| Parcours | 1.14.1 : fichiers PHP / mémoire / durée | Sources : fichiers PHP / mémoire / durée |
|---|---|---|
| home_cold | 25 / 491,7 Ko / 2.9 ms (200) | 28 / 494,7 Ko / 2.2 ms (200) |
| home_warm | 1 / 379,1 Ko / 0.2 ms (200) | 1 / 379,1 Ko / 0.1 ms (200) |
| post_cold | 25 / 495,0 Ko / 3.3 ms (200) | 28 / 498,0 Ko / 1.8 ms (200) |
| post_warm | 1 / 380,5 Ko / 0.1 ms (200) | 1 / 380,5 Ko / 0.6 ms (200) |
| 404 | 24 / 493,0 Ko / 1.2 ms (404) | 27 / 496,0 Ko / 1 ms (404) |
| sitemap | 19 / 422,0 Ko / 1.1 ms (200) | 21 / 424,9 Ko / 1 ms (200) |
| login | 18 / 431,6 Ko / 0.5 ms (200) | 19 / 434,2 Ko / 0.5 ms (200) |
| admin | 36 / 671,1 Ko / 1.3 ms (200) | 38 / 674,9 Ko / 1.9 ms (200) |
| editor | 33 / 722,6 Ko / 4.2 ms (200) | 35 / 725,5 Ko / 3.6 ms (200) |

Budgets vérifiés automatiquement : `tools/budgets.json` (`php tools/measure.php --check`, lancé par la CI).
