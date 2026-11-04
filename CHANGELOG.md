# Changelog

All notable changes to Éclair CMS are recorded here. Release notes for each version, with screenshots and upgrade instructions, are published on [GitHub Releases](https://github.com/Jacckisback/eclair-cms/releases).

## Unreleased

### Added

- **Old site (Administration › Old site, administrators).** Find in the Wayback Machine the addresses this domain used to have and give each one a page again, so that the links still pointing to them lead somewhere again. Only the site's own domain is searched (Internet Archive's CDX index: HTML pages archived with a 200 answer, up to a chosen date). The home page, addresses with parameters, other domains and subdomains, addresses Éclair reserves and old `.php` addresses (refused by the `.htaccess`) are left out; listing pages (tags, categories, authors, pagination, cart…) start unticked. For each ticked address, the AI writes a brand-new page or article from the archived page's title alone: title, main heading, description and language are the only things read from the capture (fetched with `id_`, without the archive's banner), so neither the old text nor the old images are ever copied, and the old brand is never reused. The draft keeps the old address when Éclair can serve it as is; otherwise the old address redirects to the new page (301). One address at a time from the browser, within the monthly AI budget and the daily quota, with a stop button; the cost is shown before (per page) and after (tokens and dollars, per address and in total). Optionally, a brand-new cover image per page from the assistant's image generator. Nothing is published on its own: "Publish the reviewed drafts" publishes them in one go, except those that still contain "To complete" markers. The page recalls Google's expired-domain policy.

### Changed

- Date fields in the admin (Planner, Old site) now look like the other fields.

### For maintainers

- New files: `app/lib/Wayback.php`, `app/lib/AdminWayback.php`, `app/admin/views/wayback.php`. New data: `data/wayback.php` (old addresses, their state and cost).
- New routes (`settings` right): `GET /wayback`, `POST /wayback`, `POST /api/wayback/scan`, `POST /api/wayback/build`. The archive address (`https://web.archive.org`) can be replaced through `ECLAIR_AI_ENDPOINTS['wayback']`, as the tests do.
- 35 new checks in `tests/wayback.php`, with a fake Wayback Machine (`tests/fake-wayback.php`) and the fake AI provider. The suite passes on the source and on the built archive (1,069 checks).
- Archive: 675 KB (+17 KB, of which 7 KB are translations).

## 1.12.0 — 2026-11-04

GEO and editorial release: see which AIs read your site and why, and plan your articles in a calendar where the AI suggests ideas and writes drafts, with the cost of every call shown in tokens and dollars. Nothing is ever published without you. Public pages stay identical to the byte. Sites on 1.11.x update in one click from Administration › Updates, after a backup.

### Added

- **Editorial calendar (Administration › Planner).** Your next articles, day by day, in a month calendar (published, scheduled, drafts and ideas) and an "Upcoming" list. Add ideas by hand (title, planned date, keyword, category, angle), or let the AI suggest 4 to 12 at a time from what the site already holds (business, categories, articles written, pages AI bots read most, an optional theme), placed on the next free days at the pace you choose. One click writes an idea as a complete draft, with the same engine as "Create your page with AI"; it never publishes. Every AI call shows its cost in tokens and dollars, on the idea and in a planner total, and counts towards the AI assistant's monthly budget; buttons show the estimated cost before you click. **Automatic writing** (administrators, off by default) writes the due ideas 1 to 14 days before their date, one at a time, within the budget, when an administrator opens the admin or through a secret scheduled-task address. "Schedule without review" is available but not recommended (Google penalises unreviewed bulk content), and an article that still contains "To complete" markers always waits for a human.
- **AI bots (Administration › AI bots).** See which AIs read your site, which pages and why, over 7, 30 or 90 days: on-demand lookups (ChatGPT-User, Claude-User, Perplexity-User… someone asked an AI and it came to read your page, a potential citation), answer-engine indexing (OAI-SearchBot, Claude-SearchBot, PerplexityBot…) and model training (GPTBot, ClaudeBot, CCBot, Bytespider…). Visits per day, per bot and per page, robots.txt reads, published pages no AI has read yet (with what to do about them), and a warning when training bots come despite a robots.txt that asks them not to. A summary card sits on the dashboard. Recorded by `index.php` before anything else, so cached pages are counted too: one regular expression on the user agent, then one line (time, bot, address without query string) appended to `data/ai/YYYY-MM.php`. No IP address, no human visitor, no cookie; each month's log is capped at 4 MB and deleted after 13 months. On by default, switchable in Settings › AI & GEO. Identity is as declared by each bot.

### Changed

- **"Create your page with AI"** and the planner now share the same writing engine (`AdminAi::composeItem`): same prompt, same safeguards, same draft. Behaviour is unchanged.
- The admin menu gains **Planner** and **AI bots**, between Redirects and the Administration group.

### Still guaranteed

- **Your content does not move**, and your public pages stay identical to the byte: AI bot tracking adds no tag, script or cookie, and runs before the page is served.
- **The AI never publishes on its own** unless an administrator explicitly turns on "Schedule without review", and even then not while "To complete" markers remain.
- **Automated checks** on the source and on the archive.
- The 1.11.x → 1.12.0 update is signed (Ed25519) and verified before anything is touched, with automatic rollback.

### For maintainers

- New files: `app/lib/AiVisits.php`, `app/lib/Plan.php`, `app/lib/AdminPlan.php`, `app/admin/views/ai-visits.php`, `app/admin/views/plan.php`. New data: `data/ai/YYYY-MM.php` (AI bot log, guarded PHP file, 4 MB a month, 13 months), `data/plan.php` (ideas and automatic-writing settings).
- `index.php` fast path: when `cache/runtime.php` carries `ai` and `ai_re` (written by `Cache::runtime()` while `geo.ai_log` is on), one regular expression on the user agent, then one appended line. New setting `geo.ai_log` (default `true`).
- New routes: `GET /plan`, `POST /plan`, `POST /api/plan/ideas`, `POST /api/plan/write`, `POST /api/plan/tick` (admin), `GET /ai-visits`, and the public secret address `/_eclair/plan/<key>` (scheduled task, rate-limited). `AiChat::ask()` now also returns `usage` (tokens). New helpers `month_names()` and `fmt_month()`; new icons `calendar` and `chevron-left`.
- 52 new checks: `tests/ai-visits.php` (22) and `tests/plan.php` (30, with the fake AI provider). The suite passes on the source and on the built archive (1,034 checks).
- Archive: 659 KB (+32 KB, of which about half is translations), 1.28 MB once unzipped. README banners and installation visuals show the new size in all 7 languages.

## 1.11.0 — 2026-11-04

Usability release for the page builder and the admin: undo and redo everywhere, deletions you can take back, the admin's own confirmation dialogs, a redrawn Visual mode toolbar, an orange that is no longer brown, and optional micro-animations. Existing public sites stay identical to the byte unless their owner turns the micro-animations on. Sites on 1.10.x update in one click from Administration › Updates, after a backup.

### Added

- **Undo and redo everywhere in the editor.** Ctrl/Cmd+Z, Ctrl/Cmd+Shift+Z (or Ctrl+Y) and two new ↶ ↷ buttons in the editor bar undo and redo every change, in Blocks mode as in Visual mode: blocks added, moved, duplicated or deleted, items removed from a FAQ, features, team or variants, settings, AI rewrites, restored versions. Typing in one field is one step until a 3-second pause; inside the field you are typing in, Ctrl/Cmd+Z stays the browser's own, letter by letter. Up to 60 steps.
- **Deletions can be undone from a notification.** Deleting a block or an item (FAQ question, feature, team member, variant, menu item) shows "Deleted · Undo"; the notification stays while the pointer or keyboard is on it.
- **Visual mode: Delete key.** After Esc, Delete or Backspace removes the selected block (and Ctrl/Cmd+Z brings it back).
- **Micro-animations.** In the admin: dialogs, menus and notifications appear and leave smoothly, and a block that is added, moved, duplicated or restored flashes briefly in the lightning yellow, so you see at once what changed. They can be turned off per browser in My profile › Interface, and the system's "reduce motion" setting always turns them off. On the public site, a new **Appearance › Micro-animations** switch makes cards, features, FAQ answers and images slide in on scroll and adds a few hover effects: CSS only (under 1 KB, no script), transforms only (no layout shift, and text is never faded, so its contrast holds at every instant), nothing animated on page load, off for visitors who prefer less motion. Measured with Lighthouse 12 on the demo site: 100 in all four categories, on mobile and desktop, with the animations on or off. On for new sites; existing sites keep their exact pages until the switch is turned on.

### Changed

- **Confirmations use the admin's own dialog** instead of the browser's `confirm()` and `prompt()`: red button for risky actions, focus on "Cancel" so Enter never deletes by mistake, Esc to close. Deleting a page or post now asks once, with the optional redirect of its address in the same dialog (and a hint to avoid a 404). Adding a custom menu link asks for its text and address in one dialog. Restoring a version no longer asks for confirmation: it can be undone.
- **Visual mode toolbar redrawn.** Drawn icons replace the text characters (↑ ↓ ⧉ ✕ +↑ +↓), which some fonts showed as small squares. Tools are grouped (move · add above, add below, duplicate · delete), the delete cross sits apart at the end and turns red on hover, and buttons are larger (32 px).
- **Blocks mode toolbar.** The delete button is now a cross, set apart from the other tools and red on hover, labelled "Delete block"; after a deletion, the keyboard focus goes to the previous block.
- **"Needs attention" colour no longer brown.** Average SEO/GEO scores, warnings and meters use a clear orange (text 4.7:1 on its background, fills 3:1) instead of a brown that showed above the fold of the dashboard and editor.
- **Content filters stay on one line** (All, Pages, Posts, Products, Drafts, Scheduled) and scroll sideways when space runs out, on phones included.
- **"Imagine my art direction" with AI** no longer defaults to brown, beige or sand palettes: they are kept for businesses literally made of them (wood, leather, coffee, clay, bread), in one direction at most.

### Fixed

- Dark mode: the "Recommended" tag of the installer and settings choices was a dark brown pill; it now uses the lightning yellow.

### Still guaranteed

- **Your content does not move**, and your public site stays identical to the byte as long as the micro-animations stay off (they are only on for sites installed with 1.11.0).
- **Automated checks** on the source and on the archive.
- The 1.10.x → 1.11.0 update is signed (Ed25519) and verified before anything is touched, with automatic rollback.

### For maintainers

- New file `app/theme/motion.css` (appended to the inline style only when `appearance.motion` is on, before the custom CSS). New setting `appearance.motion` (default `false`; set to `true` by the installer).
- 18 new checks: `tests/ux.php` (16), plus one in `tests/run.php` (new sites start with micro-animations) and one in `tests/design.php` (art-direction prompt); `tests/design.php` also posts the new `motion` field with the Appearance form. The suite passes on the source and on the built archive (982 checks).
- Archive: 627 KB (+6 KB), 1.22 MB once unzipped. README banners and installation visuals show the new size in all 7 languages.

## 1.10.4 — 2026-11-04

Security release. A second, deeper audit of 1.10.3 confirmed the 1.10.2 fixes held and surfaced a new, smaller set of issues, all closed here: one high-severity issue in the release tooling (not in any installed site), one medium-severity issue (Google Drive), four low-severity issues, and six host-dependent hardening items. One further lead on reserved redirects is fixed as a precaution. Sites on 1.10.0–1.10.3 update in one click from Administration › Updates, after a backup.

### Security fixes

High severity (release tooling, not installed sites):

- **`tools/build.php --resign=<version>` now signs only an archive already signed for that version** (its SHA-256 and size must match a key-verified announcement in the Git history of `dist/latest.json`), or one whose SHA-256 the maintainer supplies in `ECLAIR_EXPECT_SHA256`. An archive swapped into `dist/` by someone without the signing key can no longer be re-announced as authentic.

Medium severity:

- **A Google Drive connection made before 1.10.3 (or redone by 1.10.2) is now removed until an administrator reconnects it.** Éclair could not tell which administrator had established it, so an admin deleted or demoted before the update could keep receiving full site backups. The connection is now revoked and the Security page asks an administrator to reconnect Google Drive.

Low severity:

- **Restoring a backup no longer revives sessions or MCP connections cut by "Log out everyone", "Log out my other devices", a new password, a password reset (by an admin or via FTP) or account deletion:** they are now deleted, not just refused.
- **Unpublishing or deleting content now clears the page cache even when a later write fails** (full disk…), so the retracted page is no longer served to visitors.
- **Rolling back to the previous version no longer overwrites a `.htaccess` or folder-protection file changed since the update** (a host's rule…): it is kept, its previous version is saved in `cache/update`, and the page says so.
- **Rebuilding the content index can no longer republish content deleted before 1.10.3 whose file was left behind:** a file the index does not list comes back as a draft for review.
- **Editors can no longer change the target of an administrator's `/.well-known/` redirect** by redirecting the page it points to (redirect chain collapse).

Hardening (host- or configuration-dependent):

- Data files (accounts, keys, vault) and backup archives are created private from the start, never briefly readable by other accounts, and the data folder is tightened to `0700` where the host allows it.
- If `data/site.php` is lost, the installer now stays locked until `data/install-window.php` is deleted via FTP; the window is kept, closed, after installing. Reinstalling still keeps accounts, keys and content.
- Images rejected by the provider's safety filter (FLUX, Ideogram) or lost after submission now count toward the monthly AI budget.
- The share image of a very large photo is no longer decoded beyond the host's memory limit: the page renders and is cached with the original as the share image.
- The MCP connector's anonymous registration and token counters are grouped into at most 4,096 per kind, so invented IPv6 networks can no longer create one counter file each.
- Backup copies of never-saved content left by 1.10.2 (with no site or account) are erased instead of being given to the only account of the site; copies of existing content are still kept.

### Still guaranteed

- **Your content does not move**, and your public site stays identical to the byte.
- **Automated checks** on the source and on the archive, including one regression test per fix.
- The 1.10.0–1.10.3 → 1.10.4 update is signed (Ed25519) and verified before anything is touched, with automatic rollback.

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
