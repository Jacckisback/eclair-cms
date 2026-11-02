# Contributing to Éclair CMS

[English](#english) · [Français](#français)

---

## English

Thank you for helping improve Éclair CMS! Bug reports, fixes, translations, documentation and ideas are all welcome.

### Sign the CLA first

**Every contributor must sign the [Contributor License Agreement](CLA.md) before any pull request can be merged.** When you open your first pull request, a bot posts a comment: reply with the sentence it gives you. You sign once, and it covers all your future contributions.

Why a CLA?

1. **Long-term sustainability:** one clear owner of the licensing rights means the project can always be maintained, relicensed if ever needed, and defended.
2. **Dual licensing:** Éclair CMS is free software under the AGPL-3.0, and a commercial license is also offered to companies that cannot meet the AGPL's conditions. That revenue funds development.
3. **You keep your copyright:** you grant a license on your contribution, you do not give it away.

### Before you start

- For anything bigger than a small fix, **open an issue first** to discuss it. A short conversation saves hours of work on a change that might not fit the project.
- **Security issues:** do not open a public issue. Write to thierry.gustin@gmail.com instead.

### Conventions

- **Zero dependencies:** PHP 8.1+ without a framework or Composer, native JavaScript, no build step for development. Do not add libraries.
- **Code style:** follow the surrounding code: `declare(strict_types=1)`, typed signatures, small static classes in `eclair/app/lib/`. Comments and interface texts are written in French; they are the keys of the translations.
- **License header:** every new source file starts with:
  - PHP and JavaScript: `// SPDX-License-Identifier: AGPL-3.0-only` then `// Copyright (c) 2026 Thierry Gustin`
  - CSS: the same two lines in a `/* … */` comment
- **Interface texts:** every new `t('…')` text must be added to the six files in `i18n/` (English, Spanish, German, Italian, Portuguese, Chinese), then compiled with `php tools/i18n.php`.
- **Public site:** pages must stay fast and accessible. Unless your change is meant to change the public site, its HTML must stay byte-identical; the tests check this.
- **Tests:** `php tests/run.php` must pass, and new behavior needs tests. Run the suite on the built archive too: `php tools/build.php`, unzip `dist/eclair-cms-<version>.zip`, then `ECLAIR_TEST_ROOT=<folder>/eclair php tests/run.php`.
- **Commits:** one logical change per commit, with a clear message (`fix: …`, `feat: …`, `docs: …`, `chore: …`, `ci: …`). Pull requests target the `main` branch.

### Trademark

The AGPL covers the code, not the Éclair name or logo: see [TRADEMARK.md](TRADEMARK.md).

---

## Français

Merci de vouloir améliorer Éclair CMS ! Signalements de bugs, corrections, traductions, documentation et idées sont les bienvenus.

### D'abord, signer le CLA

**Chaque contributeur doit signer l'[accord de licence de contribution](CLA.md) (CLA) avant qu'une pull request puisse être fusionnée.** À ta première pull request, un bot publie un commentaire : réponds avec la phrase qu'il t'indique. Tu signes une fois, pour toutes tes contributions futures.

Pourquoi un CLA ?

1. **La pérennité du projet :** un titulaire unique des droits de licence permet de toujours maintenir le projet, de changer de licence si un jour c'est nécessaire, et de le défendre.
2. **La double licence :** Éclair CMS est un logiciel libre sous AGPL-3.0, et une licence commerciale est aussi proposée aux entreprises qui ne peuvent pas en respecter les conditions. Ces revenus financent le développement.
3. **Tu gardes tes droits d'auteur :** tu accordes une licence sur ta contribution, tu ne la cèdes pas.

### Avant de commencer

- Pour tout ce qui dépasse une petite correction, **ouvre d'abord un ticket** pour en discuter : quelques échanges évitent des heures de travail sur une modification qui ne conviendrait pas au projet.
- **Failles de sécurité :** n'ouvre pas de ticket public. Écris plutôt à thierry.gustin@gmail.com.

### Conventions

- **Zéro dépendance :** PHP 8.1+ sans framework ni Composer, JavaScript natif, aucune compilation pour développer. N'ajoute pas de bibliothèque.
- **Style de code :** suis le code existant : `declare(strict_types=1)`, signatures typées, petites classes statiques dans `eclair/app/lib/`. Les commentaires et les textes de l'interface sont écrits en français ; ils servent de clés aux traductions.
- **En-tête de licence :** chaque nouveau fichier source commence par :
  - PHP et JavaScript : `// SPDX-License-Identifier: AGPL-3.0-only` puis `// Copyright (c) 2026 Thierry Gustin`
  - CSS : les mêmes deux lignes dans un commentaire `/* … */`
- **Textes de l'interface :** chaque nouveau texte `t('…')` doit être ajouté aux six fichiers de `i18n/` (anglais, espagnol, allemand, italien, portugais, chinois), puis compilé avec `php tools/i18n.php`.
- **Site public :** les pages doivent rester rapides et accessibles. Sauf si ta modification doit changer le site public, son HTML doit rester identique à l'octet près ; les tests le vérifient.
- **Tests :** `php tests/run.php` doit passer, et tout nouveau comportement doit être testé. Lance aussi la suite sur l'archive construite : `php tools/build.php`, décompresse `dist/eclair-cms-<version>.zip`, puis `ECLAIR_TEST_ROOT=<dossier>/eclair php tests/run.php`.
- **Commits :** une modification logique par commit, avec un message clair (`fix: …`, `feat: …`, `docs: …`, `chore: …`, `ci: …`). Les pull requests visent la branche `main`.

### Marque

L'AGPL couvre le code, pas le nom ni le logo d'Éclair : voir [TRADEMARK.md](TRADEMARK.md).
