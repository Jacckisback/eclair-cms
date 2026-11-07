# Mesures d’Éclair

Mesures produites par `php tools/measure.php` le 2026-11-07 19:24 UTC, PHP 8.3.6, serveur intégré de PHP, sans OPcache (coût de compilation compris : majorant), site neuf (type blog).
Durées indicatives (machine de mesure) ; mémoire = pointe PHP (pages publiques : processus PHP neuf par requête ; administration : serveur intégré, mémoire du processus avant la requête retirée) ; fichiers = fichiers PHP inclus (`get_included_files`).
« _cold » : cache de pages vidé (accueil : le plus léger de trois rendus à froid) ; « _warm » : page servie par le chemin rapide d'index.php.

| Mesure | 1.14.1 | Sources | Écart |
|---|---:|---:|---:|
| Archive ZIP du noyau | 730,7 Ko | 782,8 Ko | +7.1 % |
| Taille installée (noyau, hors fonctions livrées) | 1 360,8 Ko | 1 414,2 Ko | +3.9 % |
| Fonctions livrées non chargées (app/bundled) | 0,0 Ko | 67,2 Ko |  |
| Fichiers installés | 156 | 172 | +10.3 % |
| Classes PHP (app/lib) | 74 | 77 | +4.1 % |
| Code PHP (app/lib) | 692,9 Ko | 725,7 Ko | +4.7 % |
| Traductions de l’administration (toutes) | 248,8 Ko | 266,9 Ko | +7.3 % |
| Traductions : une langue | 41,5 Ko | 44,5 Ko | +7.3 % |
| Actifs de l’administration | 269,1 Ko | 270,9 Ko | +0.7 % |
| Thème de base (app/theme) | 59,3 Ko | 59,4 Ko | +0.1 % |
| Accueil : HTML | 21,0 Ko | 21,0 Ko | +0.0 % |
| Accueil : CSS en ligne | 16,9 Ko | 16,9 Ko | +0.0 % |
| Accueil : JavaScript | 2,5 Ko | 2,5 Ko | +0.0 % |
| Accueil : polices | 0 | 0 |  |

| Parcours | 1.14.1 : fichiers PHP / mémoire / durée | Sources : fichiers PHP / mémoire / durée |
|---|---|---|
| home_cold | 25 / 149,8 Ko / 1.6 ms (200) | 26 / 154,6 Ko / 1.4 ms (200) |
| home_warm | 1 / 37,2 Ko / 0.1 ms (200) | 1 / 37,2 Ko / 0.1 ms (200) |
| post_cold | 25 / 1 990,1 Ko / 1.5 ms (200) | 26 / 2 044,9 Ko / 1.4 ms (200) |
| post_warm | 1 / 37,8 Ko / 0.1 ms (200) | 1 / 37,8 Ko / 0.1 ms (200) |
| 404 | 24 / 2 041,5 Ko / 0.9 ms (404) | 25 / 2 125,5 Ko / 0.9 ms (404) |
| sitemap | 19 / 2 038,8 Ko / 0.9 ms (200) | 20 / 2 256,5 Ko / 1.1 ms (200) |
| login | 18 / 89,0 Ko / 0.6 ms (200) | 19 / 93,5 Ko / 0.4 ms (200) |
| admin | 36 / 326,4 Ko / 2.4 ms (200) | 36 / 332,1 Ko / 1.5 ms (200) |
| editor | 33 / 378,0 Ko / 2.5 ms (200) | 35 / 382,8 Ko / 2.1 ms (200) |

Budgets vérifiés automatiquement : `tools/budgets.json` (`php tools/measure.php --check`, lancé par la CI).
