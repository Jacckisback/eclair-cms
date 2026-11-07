# Mesures d’Éclair

Mesures produites par `php tools/measure.php` le 2026-11-07 15:10 UTC, PHP 8.3.6, serveur intégré de PHP, site neuf (type blog).
Durées indicatives (machine de mesure) ; mémoire = pointe PHP de la requête ; fichiers = fichiers PHP inclus (`get_included_files`).
« _cold » : cache de pages vidé ; « _warm » : page servie par le chemin rapide d'index.php.

| Mesure | 1.14.1 | Sources | Écart |
|---|---:|---:|---:|
| Archive ZIP du noyau | 730,7 Ko | 755,9 Ko | +3.4 % |
| Taille installée | 1 360,8 Ko | 1 411,9 Ko | +3.8 % |
| Fichiers installés | 156 | 160 | +2.6 % |
| Classes PHP (app/lib) | 74 | 77 | +4.1 % |
| Code PHP (app/lib) | 692,9 Ko | 731,1 Ko | +5.5 % |
| Traductions de l’administration (toutes) | 248,8 Ko | 259,3 Ko | +4.2 % |
| Traductions : une langue | 41,5 Ko | 43,2 Ko | +4.2 % |
| Actifs de l’administration | 269,1 Ko | 269,1 Ko | +0.0 % |
| Thème de base (app/theme) | 59,3 Ko | 59,3 Ko | +0.0 % |
| Accueil : HTML | 21,0 Ko | 21,0 Ko | +0.0 % |
| Accueil : CSS en ligne | 16,9 Ko | 16,9 Ko | +0.0 % |
| Accueil : JavaScript | 2,5 Ko | 2,5 Ko | +0.0 % |
| Accueil : polices | 0 | 0 |  |

| Parcours | 1.14.1 : fichiers PHP / mémoire / durée | Sources : fichiers PHP / mémoire / durée |
|---|---|---|
| home_cold | 25 / 1 102,9 Ko / 6.9 ms (200) | 27 / 1 110,0 Ko / 5.8 ms (200) |
| home_warm | 1 / 379,1 Ko / 0.1 ms (200) | 1 / 379,1 Ko / 0.1 ms (200) |
| post_cold | 25 / 495,0 Ko / 1.4 ms (200) | 27 / 498,0 Ko / 1.5 ms (200) |
| post_warm | 1 / 380,5 Ko / 0.2 ms (200) | 1 / 380,5 Ko / 0.1 ms (200) |
| 404 | 24 / 493,0 Ko / 0.9 ms (404) | 26 / 496,0 Ko / 0.9 ms (404) |
| sitemap | 19 / 422,0 Ko / 0.8 ms (200) | 20 / 424,9 Ko / 1.2 ms (200) |
| login | 18 / 431,7 Ko / 0.5 ms (200) | 19 / 434,3 Ko / 0.8 ms (200) |
| admin | 36 / 671,1 Ko / 1.3 ms (200) | 37 / 674,0 Ko / 1.7 ms (200) |
| editor | 33 / 722,7 Ko / 2.9 ms (200) | 34 / 725,6 Ko / 2.7 ms (200) |

Budgets vérifiés automatiquement : `tools/budgets.json` (`php tools/measure.php --check`, lancé par la CI).
