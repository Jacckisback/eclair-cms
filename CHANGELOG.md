# Changelog

All notable changes to Éclair CMS are recorded here. Release notes for each version, with screenshots and upgrade instructions, are published on [GitHub Releases](https://github.com/Jacckisback/eclair-cms/releases).

## 1.10.1 — 2026-11-02

First version released under the GNU Affero General Public License v3.0 only. Sites running 1.10.0 update in one click from Administration › Updates.

### Changed

- **New design for the administration**, following the typography, color, layout and onboarding guidelines of [Impeccable](https://github.com/pbakaus/impeccable):
  - One typeface, Schibsted Grotesk (SIL Open Font License 1.1), served by the site itself (no third-party request), on a fixed type scale with tabular figures for numbers.
  - A new OKLCH palette: an ink-violet navigation rail, a paper background, and the yellow of the lightning bolt kept for what needs attention (the next step, unread counts). Light and dark themes are designed separately, and every text meets WCAG AA contrast.
  - Dashboard in two columns: first steps, recent content and the AI prompt on the left; site figures, average SEO and GEO scores and site health on the right.
  - First steps: the next step is highlighted, each step says why it matters, and progress is shown step by step.
  - Installer: the five steps are listed on the left, with drawn icons for site types and AI choices. The login page is simpler.
  - Drawn icons replace emoji across the administration (notices, empty states, choices, assistant suggestions).
- The archive grows from 528 KB to 594 KB: the font file (47 KB) and the full text of the AGPL (11 KB more than the MIT License).
- **License: Éclair CMS is now licensed under the GNU Affero General Public License v3.0 only (AGPL-3.0-only)**, instead of the MIT License. Code up to and including commit `402be69` (tag `last-mit`, version 1.10.0) remains available under the MIT License. See [`NOTICE`](NOTICE) and [`LICENSE`](LICENSE).
- Every source file now starts with an SPDX license header (`AGPL-3.0-only`, copyright Thierry Gustin). The public site's pages are unchanged.
- The distributed archive now includes `NOTICE.txt` next to `LICENSE.txt` and `THIRD-PARTY-NOTICES.txt`.

### Fixed

- One-click updates: rolling back now also restores the files outside the code that an update replaces (folder protections, configuration template) and removes the ones it added. Before, they stayed in their new version and the integrity check reported them by mistake. Rolling back from 1.10.1 to 1.10.0 is handled too.
- Updates page: missing space after the date of the last check.

### Added

- [`TRADEMARK.md`](TRADEMARK.md): trademark policy for "Éclair CMS", "Éclair" applied to the software and the logo. The AGPL covers the code, not the name; forks must use another name and logo.
- [`CLA.md`](CLA.md): individual Contributor License Agreement. Contributors keep their copyright and grant a license that also allows commercial licensing (dual licensing).
- CLA Assistant workflow (`.github/workflows/cla.yml`): contributors sign the CLA with a comment on their pull request.
- [`CONTRIBUTING.md`](CONTRIBUTING.md): how to contribute, in English and French.
- A commercial license is available for products or services that cannot meet the AGPL's obligations: contact thierry.gustin@gmail.com.

## 1.10.0 — 2026-11-02

Last version released under the MIT License (tag `last-mit`). One-click signed updates with automatic rollback, and Google Drive backups. See the [release notes](https://github.com/Jacckisback/eclair-cms/releases/tag/1.10.0).
