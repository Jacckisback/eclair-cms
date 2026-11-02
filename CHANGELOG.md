# Changelog

All notable changes to Éclair CMS are recorded here. Release notes for each version, with screenshots and upgrade instructions, are published on [GitHub Releases](https://github.com/Jacckisback/eclair-cms/releases).

## 1.10.2 — 2026-11-02

Sites running 1.10.0 or 1.10.1 update in one click from Administration › Updates.

### Fixed

- **Archive refused by some hosts' antivirus.** o2switch's file manager blocked the archive with `{HEX}Malware.Expert.php.unlink.file.get.contents…chmod.UNOFFICIAL FOUND`. It was a false positive on the one-click update code: one PHP file deleted, read, wrote and changed the permissions of files in the same order as some malware does. These file operations now live in a small separate class (`app/lib/Disk.php`), and a test checks that no shipped PHP file contains that sequence any more. Sites see no difference.
- **Google Drive backups now open for every site.** The connection relied on Google credentials built into the archive, which were never configured, so the Google Drive card stayed closed. Each site can now use its own Google app: Security › Google Drive shows a step-by-step guide (free, about ten minutes, once), then you paste the client ID and secret. The secret is encrypted on your server and never shown again. Credentials built into an archive or set in `eclair-config.php` still work.
- Dashboard: with the assistant open, the side column moves below the main column instead of squeezing it.
- Settings › Appearance: the "Site style", "Art direction" and "Theme" groups no longer show the browser's default frame.

### Changed

- Editor: the block list, the formatting bar, the AI menu, the block boxes and the save status use drawn icons instead of emoji. Emoji in your own content (for example in a Features block) are unchanged.
- The archive grows from 594 KB to 602 KB (Google app guide, drawn icons in the editor, translations).
- README and guides: the screenshots of the editor, the assistant, the visual mode, MCP, the art directions, the appearance settings, the product editor, the AI & GEO settings and the media library show the new design, in all 7 languages. The banner and the installation visual give the new archive size.

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
