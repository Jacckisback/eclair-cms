# Changelog

All notable changes to Éclair CMS are recorded here. Release notes for each version, with screenshots and upgrade instructions, are published on [GitHub Releases](https://github.com/Jacckisback/eclair-cms/releases).

## 1.10.3 — 2026-11-03

Security release. It fixes every issue found by a full security audit of 1.10.2: 15 confirmed flaws (3 of medium severity, none critical or high) and 8 leads that depend on the server or the browser, all closed in code. It also applies more than fifty of the audit's hardening recommendations. Sites running 1.10.0, 1.10.1 or 1.10.2 update in one click from Administration › Updates. On sites with several accounts, **save any open edits before updating**: their unsaved editor backups from 1.10.2 are not kept (see "Editor" below).

### Security fixes

Medium severity:

- **"Log out my other devices" and "Log out everyone" no longer renew the password confirmation.** They used to re-run the login, which reset the 15-minute password confirmation and the 48-hour session lifetime: someone holding a session without the password could then create an administrator or download a backup.
- **Editors can no longer redirect `/.well-known/` or Éclair's own `/_eclair/` addresses.** A redirect on `/.well-known/acme-challenge/…` could make a certificate authority validate the domain for someone else. Existing redirects from these addresses are disabled ("Désactivée") and can be deleted. Admins can still redirect other `/.well-known/` addresses, such as `change-password`.
- **The content store is capped.** An account, the AI assistant or an MCP client could create drafts until saving, deleting and visitor search failed. A site now holds at most 3,000 contents; the AI stops creating drafts at 500; the search index keeps only published text, up to 16 MB (more than 1,500 long articles). Existing contents always stay editable.

Low severity:

- Deleting an account now clears the page cache: the author's profile no longer stays public in cached pages, feeds and exports.
- The AI assistant and MCP clients edit directly only a draft that is still a draft: content published or unpublished during their work gets a proposal instead of a live change.
- Usual-device cookies are now tied to the password and the account's sessions: a new password, "Log out my other devices", "Log out everyone", an admin reset or the FTP reset make other browsers forget them.
- Anonymous logins no longer create one file per e-mail address tried, and the Security page can no longer be made to fail by them.
- Backups never contain the vault (API keys, Google Drive token), sessions, limits or the update state, even when `ECLAIR_DATA` is written with a trailing slash.
- A page rendered while its content was being unpublished can no longer stay in the page cache.
- Rebuilding the content index (translation link, repair) can no longer put a draft back online, and public pages, `.md` copies, RSS, `llms-full.txt`, the sitemap and the product feed check each content's own saved status.
- Anonymous login failures can no longer push real events out of the security log.
- The contact form can no longer add header lines to the notification e-mail through the visitor's address.
- Rate limits no longer refill early. The contact form's 100 messages per day for the whole site and the MCP connector's 60 changes per hour are now strict, and login limits never accept a second batch at the end of a window.
- Logging out can no longer be undone by a request of the same session still in progress.
- Update announcements now carry a signed serial number and expiry date: a channel replaying an older announcement can no longer hide a new version or offer a withdrawn one again.

Leads closed in code (they depended on the server or the browser):

- A login lock file that cannot be opened no longer lifts the limit on simultaneous password checks, and one check is now reserved for your usual devices and signed-in users: a flood of attempts can no longer keep the owner out.
- The data lock (`data/.lock`) is never bypassed any more: Éclair recreates an unusable lock file, or refuses to save with a clear message instead of saving without protection.
- Following a redirect no longer rewrites the whole redirect list under the site-wide lock, and redirects (5,000), images (10,000) and categories (1,000) are capped.
- The Markdown exports (`.md`, `llms.txt`, `llms-full.txt`) can no longer carry HTML, images or links typed as text; real links keep their checked address.
- Google Drive: the connection is tied to the admin session that starts it, and is removed if the admin who connected it is deleted or demoted.
- MCP consent: connecting an app other than Claude or ChatGPT always asks for the password, and the "Autoriser" button is protected against double-clickjacking.
- Editor backup copies belong to the signed-in account and are erased at logout.

### Changed

- **Login and 2FA.**
  - Two simultaneous password checks for unknown devices, plus one reserved for your usual devices and signed-in users.
  - After the update, each usual device becomes "usual" again at its next successful login.
  - While 2FA is on, it can no longer be re-enrolled from the profile: to change phones, disable it with the current password, then enable it again. Enabling it asks for the password when the login is older than 15 minutes, and wrong setup codes are limited to 10 per 15 minutes.
  - A wrong current password in the profile counts toward the same limit as the password confirmation (10 per 15 minutes).
  - Users › your own account no longer offers "Désactiver la 2FA": use My profile, which asks for the current password.
  - The FTP reset refuses the example password printed in the guides and a reset file dated in the future.
- **Contact form.** Once the 100 messages per day for the whole site are reached, the form refuses everyone until 24 hours after the first of them (it reopened after 6 hours before). E-mail addresses with quotes, backslashes, spaces or line breaks are refused everywhere.
- **MCP connector.** Unknown apps always need the password. On every consent page, "Autoriser" starts greyed out and turns on after a moment once you move the mouse, touch the screen or use the keyboard; JavaScript is required. Claude and ChatGPT show a "start this yourself" notice. Searches longer than 200 characters are refused.
- **Updates page.** "Tu as la dernière version" appears only after a successful check of a fresh signed announcement. An older announcement is refused and reported; an announcement not renewed for 180 days shows a warning (installing stays possible). If an automatic rollback cannot complete, the page says to restore `cache/update/previous` by FTP.
- **Google Drive.** The connection code is shown only in the session that started the connection, for 15 minutes after it starts; other admins see that a connection is in progress. The card shows who connected the account. Reconnecting another Google account revokes the previous connection. A connection made before 1.10.3 is flagged: disconnect and reconnect it so Éclair records who set it up.
- **Security page.** Real events and the latest anonymous login failures are shown together; anonymous failures are kept in their own file. Logs roll over to a previous segment instead of losing their older half. Blocked account counters show the accounts they cover.
- **Editor.** The local backup copy of unsaved work now belongs to the signed-in account and is erased at logout; another account in the same browser is never offered it. Copies saved by 1.10.2 are kept on a site with a single account, and erased when there are several accounts. Two Éclair sites on the same domain (one in a sub-folder) never touch each other's copies.
- **AI assistant.** It stops mid-request when the account is deleted, its password reset or its AI access removed. A proposal becomes outdated as soon as its content changes, even within the same second, and applying it never changes the publication state.
- **Public site.** An author page exists only for an account with at least one published article. Page numbers with leading zeros (`/blog/page/01`) return 404. The Markdown copies escape Markdown characters typed as text (`\[` in the raw file). Published pages are otherwise unchanged, byte for byte.
- **Dashboard.** The site-health list is shown to administrators only.
- **Media.** `media/` no longer serves `.js` or `.mjs` files (update the Nginx rules in the guide if you use Nginx).
- The archive grows from 602 KB to 619 KB (security code and translations). README, guides, banner and installation visual give the new size.
- **Data lock.** On storage without working file locks, Éclair refuses to save with a clear message instead of saving unprotected; the message is shown everywhere (pages, contact form, admin), not as a generic error.
- **Installer.** Re-running it after `data/site.php` was lost refuses the e-mail of an existing account instead of creating a duplicate, keeps the existing keys, and a failed installation never deletes accounts or content that were already there.
- **Redirects.** A list that already holds more than 5,000 redirects (from 1.10.2) is never cut in bulk: each new automatic redirect replaces the oldest one, and existing redirects stay editable.

### For maintainers

- `dist/latest.json` keeps its original signature, read by sites on 1.10.0 to 1.10.2, and gains a second one (`v2`) covering a serial number and a 180-day expiry. Re-sign the channel before it expires, even without a release: `ECLAIR_SIGNING_KEY=… php tools/build.php --resign`. A withdrawal announces an older archive again: `--resign=<version>`.
- Signed builds run only in a Git checkout, include only tracked files and record the source commit in the manifest. Translation sources (`i18n/*.php`) are read as data and must stay plain arrays of literal strings.
- Forks with their own update channel must publish announcements built by the 1.10.3 (or later) `tools/build.php`: 1.10.3 sites treat an announcement without the `v2` signature as a replay. Signed builds refuse symbolic links and mark the manifest `dirty` when tracked files have uncommitted changes.
- New security regression tests: `tests/sec-A.php`, `tests/sec-B1.php`, `tests/sec-B2.php`, `tests/sec-C1.php`, `tests/sec-C2.php` and `tests/sec-review.php` (918 checks in total). The CLA workflow no longer starts for plain issue comments.

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
