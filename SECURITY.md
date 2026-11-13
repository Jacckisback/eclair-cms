# Politique de sécurité / Security policy

## Français

### Versions prises en charge

Seule la **dernière version publiée** d'Éclair CMS reçoit les correctifs de sécurité. Elle s'installe en un clic depuis Administration › Mises à jour (après une sauvegarde). Une faille n'est jamais corrigée « en silence » : chaque correctif de sécurité est décrit dans le [CHANGELOG](CHANGELOG.md).

### Signaler une vulnérabilité

**N'ouvre pas d'issue publique pour une faille.** Utilise le signalement privé de GitHub : onglet **Security › Report a vulnerability** de ce dépôt. Si cette option n'apparaît pas, passe par l'adresse de contact indiquée dans le [README](README.md) (section Licence), avec « sécurité » dans l'objet.

Indique si possible :

- la version d'Éclair (Administration › Mises à jour) et la version de PHP ;
- le serveur (Apache, LiteSpeed, Nginx) ;
- les étapes pour reproduire, et ce que tu observes ;
- l'impact que tu estimes (lecture de données, exécution de code, prise de compte…).

Un contenu de test minimal suffit : pas besoin d'exploiter la faille sur un site qui ne t'appartient pas. **N'accède pas aux données d'autres personnes** et ne teste pas sur un site en production dont tu n'es pas responsable.

### Ce que tu peux attendre

Éclair est maintenu par une seule personne. Objectifs, pas garanties :

- accusé de réception sous **7 jours** ;
- évaluation et réponse sous **14 jours** ;
- correctif ou plan de correction sous **30 jours** pour une faille confirmée, plus vite si elle est grave ;
- publication d'une version corrective, puis mention dans le CHANGELOG, avec ton nom si tu le souhaites.

### Périmètre

Dans le périmètre : le code d'Éclair (`eclair/`), l'archive publiée, la mise à jour en un clic et sa signature, l'administration, les fonctions livrées (`eclair/app/bundled/`), le connecteur MCP, les outils (`tools/`) et la configuration de la CI.

Hors périmètre, sauf si Éclair en est la cause :

- un thème ou une fonction **importé par l'administrateur** : ils s'exécutent avec les droits du site, et leur import demande le droit Sécurité et le mot de passe (voir `docs/THEME-CONTRACT.md` et `docs/EXTENSION-CONTRACT.md`) ;
- la configuration de l'hébergement (PHP, serveur web, droits des fichiers, certificat) ;
- un compte administrateur dont le mot de passe a été divulgué ;
- le déni de service par volume de requêtes.

## English

### Supported versions

Only the **latest published release** of Éclair CMS gets security fixes. It installs in one click from Administration › Updates (after a backup). A vulnerability is never fixed silently: every security fix is described in the [CHANGELOG](CHANGELOG.md).

### Reporting a vulnerability

**Do not open a public issue for a vulnerability.** Use GitHub's private reporting: **Security › Report a vulnerability** on this repository. If that option is not shown, use the contact address given in the [README](README.md) (License section), with "security" in the subject.

Please include, if you can: the Éclair and PHP versions, the web server, the steps to reproduce and what you observe, and the impact you estimate (data read, code execution, account takeover…). A minimal proof of concept is enough. **Do not access other people's data** and do not test on a production site you are not responsible for.

### What to expect

Éclair is maintained by one person. These are goals, not guarantees: acknowledgement within **7 days**; assessment and reply within **14 days**; a fix or a fix plan within **30 days** for a confirmed vulnerability, sooner if it is serious; then a fixed release and a mention in the CHANGELOG, with your name if you wish.

### Scope

In scope: Éclair's code (`eclair/`), the published archive, the one-click update and its signature, the administration, the shipped features (`eclair/app/bundled/`), the MCP connector, the tools (`tools/`) and the CI configuration.

Out of scope, unless Éclair is the cause: a theme or feature **imported by the administrator** (it runs with the site's rights, and importing one requires the Security right and the password: see `docs/THEME-CONTRACT.md` and `docs/EXTENSION-CONTRACT.md`); hosting configuration (PHP, web server, file permissions, certificate); an administrator account whose password leaked; denial of service by request volume.
