<p align="center">
  <a href="GUIDE.md">Français</a> · <b>English</b> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Éclair CMS guide

Everything you need to know to install, use, secure and maintain Éclair. For the overview, go back to the [README](../README.en.md).

## Contents

1. [Installation](#installation)
2. [Features in detail](#features-in-detail)
3. [Everyday use](#everyday-use)
4. [Security](#security)
5. [Updating, backing up, moving](#updating-backing-up-moving)
6. [Troubleshooting](#troubleshooting) (including [Nginx](#nginx))
7. [For developers](#for-developers)

---

## Installation

### Requirements

- **PHP 8.1 or later** (8.3 recommended), with the `mbstring`, `json`, `dom` and `session` extensions (enabled by default almost everywhere).
- Recommended: `gd` (WebP images), `zip` (one-click backups), `curl` (IndexNow).
- An **Apache or LiteSpeed** server (virtually every shared host: Hostinger, IONOS, OVHcloud…). For Nginx, see [below](#nginx).
- An HTTPS certificate (free with Let's Encrypt at almost every host).

The wizard checks all of this for you and explains what to fix.

### Steps

1. Download the [`eclair-cms-1.0.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.0.0.zip) archive (257 KB) and unzip it.
2. Upload **the contents of the `eclair` folder** to your hosting's public folder (`public_html`, `www`…) with your FTP client (FileZilla…) or your host's file manager.
   > ⚠️ `.htaccess` files are hidden: turn on “Show hidden files” to make sure they get uploaded.
3. Open your site's URL: **the setup wizard starts** (in English, French or Spanish).
4. Answer 4 questions (your site, your account, your visibility, your admin URL). Confetti 🎉: your site is live, with pages and a sample post.

> 🔒 For security, the installation must be completed **within 2 hours** of the first visit (otherwise anyone could take over a site that was uploaded and then forgotten). After that, delete the `data/install-window.php` file via FTP and reload the page.

Installing in a subfolder (`example.com/site/`): just upload the files to that subfolder, everything is detected automatically.

### The 4 site types

| Type | What gets created |
|---|---|
| 🏢 Business website | Home (hero, features, latest posts), Services (with FAQ), About, Contact, Blog, legal notice, privacy policy |
| ✍️ Blog | Home = latest posts, About, Contact, a “Guides” category, legal pages |
| 🧑‍💻 Freelancer / portfolio | Home in your name, skills, About, Blog, Contact, legal pages |
| 🎯 Landing page | A sales page (hero, features, FAQ, form) + legal pages, no blog |

Starter content is available in English, French and Spanish; a site in German, Italian or Portuguese starts with the English content. The legal pages contain an outline to complete (French LCEN, Spanish LSSI, GDPR).

### Right after installation

The dashboard shows a “Getting started” checklist: logo and color, home page, first post, author profile, two-factor authentication, legal notice, Google Search Console.

---

## Features in detail

### ⚡ Speed

- **Automatic static cache**: each page is generated once, then served without loading the application. The cache clears itself on every change (and at the scheduled time for a scheduled post).
- **CSS inlined in the page** (≈ 12 KB minified), system fonts: no render-blocking requests, no layout shift.
- **Images optimized on upload**: resized (2000 px max), available as **responsive WebP** (480, 800, 1200, 1600 px), explicit dimensions, lazy loading, priority given to the main image.
- ETag and `304 Not Modified` responses, Brotli/Gzip compression and one-year browser caching for static files (via `.htaccess`).

### 🔎 SEO

- Clean URLs, **automatic 301 redirect** when an address changes, redirect manager (handy for taking over an old WordPress or Wix site).
- Title, meta description and **live Google preview** in the editor, with counters.
- **Real-time SEO analysis** with a score: title and description length, keyphrase (title, URL, introduction, subheadings, density), word count, structure, alt text, internal linking, featured image…
- Complete **JSON-LD structured data**: WebSite (+ search), Organization / Person / LocalBusiness (address, opening hours, social profiles), WebPage / AboutPage / ContactPage, BlogPosting (author, dates, section), BreadcrumbList, FAQPage, ProfilePage.
- XML sitemap with images and `hreflang`, robots.txt, full RSS feed, Open Graph and Twitter Cards (**1200×630 share image generated automatically**), breadcrumbs, canonical URLs.
- One address per piece of content: redirects for HTTPS, www/non-www, trailing slash, uppercase, `index.php`.
- **IndexNow**: Bing, Yandex, Seznam… are notified every time you publish.
- Google Search Console and Bing Webmaster Tools verification (paste the `<meta>` tag, Éclair extracts the code).
- Multilingual: language per piece of content, linked translations and automatic `hreflang` tags.
- Automatic French typography for French content (non-breaking spaces before `: ; ! ?` and inside « » quotes).

### 🤖 GEO (AI answer engines)

- **`/llms.txt`** (llmstxt.org standard) and **`/llms-full.txt`** generated automatically.
- **A Markdown version of every page**: add `.md` to any URL (with a metadata header, `noindex` and a canonical URL so it never creates duplicate content).
- **One-click AI bot policy**: open (recommended to get cited), citations only (answer engines allowed, training refused) or closed. Covers GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Meta-ExternalAgent… Optional `Content-Signal` directive.
- **“Key takeaways”** block: the summary AI likes to quote, shown at the top of the article.
- **GEO score** in the editor: summary, direct introduction, FAQ, question-style subheadings, lists and tables, concrete figures, external sources, identified author (E-E-A-T), short paragraphs, freshness.
- Author pages with a bio and profiles (`sameAs`), visible publication and update dates.

### ✍️ Block editor

- 16 blocks: text, subheading (H2-H4), list, quote, image, callout, FAQ, table, button, YouTube/Vimeo video (no cookies until clicked), code, separator, hero, features, latest posts, contact form.
- Type **`/`** on an empty line to pick a block, drag and drop, formatting toolbar (bold, italic, link, code, highlight).
- **Assisted internal links**: as you type a link, the editor suggests your pages and posts (internal linking in 2 seconds).
- **Smart paste** from Word, Google Docs or a web page: headings, lists, tables and bold text become clean blocks.
- `Ctrl+S` to save, local backup draft (nothing is lost if the connection drops), real preview before publishing, **history of the last 20 versions**, scheduled publishing.

### 🧩 Everything else

Media library (drag and drop, alt text, detection of images in use), drag-and-drop menus (header and footer), “pillar” categories for a topic cluster, site search, spam-proof contact form **without a captcha** (honeypot field, token, limits per network and per day) with an inbox, administrator and editor accounts, automatic dark mode, 6 system font stacks, main color with guaranteed WCAG contrast, admin in English, French and Spanish, public site in 6 languages (fr, es, en, de, it, pt).

---

## Everyday use

| I want to… | Where |
|---|---|
| Write a post | Dashboard › **Write a post** |
| Edit a page from the site | **✏️ Edit** button (visible only when you're logged in) |
| Change the logo, color, fonts | Settings › Appearance |
| Choose the home page | Settings › General |
| Allow or block AI | Settings › AI & GEO |
| Connect Google Search Console | Settings › SEO |
| Add a link to the menu | Menus |
| Redirect an old URL | Redirects |
| Turn on two-factor authentication | My profile › Two-factor authentication |
| Download a backup | Security › Backup |

Editor shortcuts: `/` insert a block · `Ctrl+S` save · `Ctrl+K` link · `Ctrl+B` bold · `Ctrl+I` italic · `Enter` new paragraph · `Shift+Enter` line break.

---

## Security

Éclair is built for a hostile environment (shared hosting, bots trying passwords around the clock). It went through an offensive security review (race conditions, brute force, redirects, injections, booby-trapped files): every vulnerability found was fixed, then the attack was replayed to prove it (`tests/run.php`).

**Data and server**
- **No database**: SQL injection is impossible.
- **Doubly protected data**: the `data/`, `app/` and `cache/` folders are blocked by `.htaccess`; every data file starts with `<?php exit; ?>` (even if a server ignored `.htaccess` files, nothing would be displayed) and no `<?php` sequence can ever be written into it. Private files (0600 permissions), atomic writes.
- **Protected installation**: it is only possible within 2 hours of the first visit, and the site address is only used to redirect visitors once it has been **verified by the server itself** (a wrong or tampered address hijacks nothing).

**Login**
- **Passwords** hashed with Argon2id (19 MiB, 2 passes: secure and bearable on shared hosting; bcrypt as a fallback), 10 characters minimum, common passwords refused.
- **Brute-force protection**: each attempt is reserved before it is checked (the limit cannot be exceeded with parallel requests); limits per network (IPv4 address or /64 block in IPv6) and per targeted account. **Your usual device is never locked out** by an attacker who fails on purpose. At most two password checks at a time: a flood of requests cannot exhaust the memory.
- **TOTP two-factor authentication** (Google Authenticator, 1Password, Bitwarden…) with a QR code and 8 recovery codes shown only once; each code works only once, even when sent in parallel; after 10 wrong codes, the account's 2FA is locked for 15 minutes and its owner is notified by email.
- **Accounts updated atomically**: a login in progress can never undo a password change, a global logout, enabling 2FA, a demotion or a deletion.
- **Password re-prompt** for sensitive actions (backups, account management, advanced settings, email change) more than 15 minutes after logging in. Temporary passwords must be replaced at first login.
- **Hardened sessions**: no session before login (bots can't fill the disk), `HttpOnly`, `Secure`, `SameSite=Strict` cookies, regenerated at login, logout after inactivity, bound to the browser, “Log out my other devices”. The admin is forced onto HTTPS as soon as the site uses it.
- **CSRF tokens** on every action and origin checks on requests.

**Public site**
- **Strict headers**: Content-Security-Policy (no inline scripts, SHA-256 hash for the style), anti-clickjacking, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, optional HSTS. They depend on the settings, never on the request: the cache cannot be poisoned.
- **No open redirects**: crafted addresses (`/\example.com`, control characters…) return a 404.
- **Content sanitized** with an allowlist (the HTML is parsed and then entirely rewritten); `javascript:` and `data:` links are impossible, even disguised (`javascript&colon;`). The Markdown, `llms.txt` and RSS versions stay inert.
- **Contact form** without a captcha: honeypot field, token, 5 messages per network every 10 minutes and 100 per day for the whole site; the inbox is capped at 500 messages (spam goes first) and paginated.
- **Images re-encoded on upload** (removes hidden payloads and EXIF/GPS data); animated GIFs rewritten block by block; any image containing code is refused; 40 megapixels maximum; SVGs refused, no scripts or file listing in `/media`.

**Admin**
- **Customizable admin URL** (fewer bots), administrator / editor roles; the site's “Edit” button doesn't reveal that URL.
- **Bounded logs** (truncated fields, 1 MB maximum): they can't be inflated.
- **Integrity check**: compares every file with its original fingerprint and flags suspicious files, including a `.php` file without the protection line in `data/` or `cache/`.
- **Live protection test**: the Security page checks from your browser that internal files really are inaccessible, and that the site address is verified.

---

## Updating, backing up, moving

**Back up**: Security › Download a backup (ZIP archive of `data/` and `media/`). Without the zip extension, copy these two folders via FTP.

**Update Éclair**:
1. Make a backup.
2. Via FTP, replace `index.php`, `.htaccess` and the `app/` and `assets/` folders with those from the new version.
3. **Don't touch** `data/` (content, settings, accounts) or `media/` (images).
4. In the admin: Settings › Advanced › Clear cache.

**Move to another host or domain**: copy the whole folder, then change the address in Settings › General. Internal links adapt automatically, including in a subfolder.

---

## Troubleshooting

**Forgot your password (or lost your 2FA phone) and no other administrator?**
Via FTP, create a `data/reset.php` file containing exactly these three lines:

```
<?php exit; ?>
your-address@example.com
YourNewPassword2026
```

Within 24 hours, **log in with that email and that new password**: the password is replaced, two-factor authentication for that account is turned off, your other sessions are closed and the file is deleted automatically. Nobody else can trigger it (they would need to know its contents); a file without the first line is refused and deleted.

**“Installation locked”**: the 2-hour window after the first visit has passed. Delete `data/install-window.php` via FTP and reload the page.

**Site address not verified (Security page)**: your server can't call itself on the site's address (host firewall, DNS not propagated yet…). The site works normally, but without redirects to the official address (www / non-www). Click “Verify the site address” once the domain is in place.

**404 error on every page except the home page**: the `.htaccess` file wasn't uploaded (hidden file) or URL rewriting is disabled. If your host requires it, uncomment the `RewriteBase /` line in `.htaccess`.

**500 error right after uploading**: your host rejects a `.htaccess` directive (very rare: only directives accepted everywhere are used). Remove the block in question or contact your host.

**Images aren't converted to WebP**: the GD extension (or its WebP support) is missing. Enable it in your host's PHP panel; in the meantime, images stay in JPG/PNG.

**I don't receive emails from the contact form**: messages are also saved in the admin (Messages). Check the address in Settings › Contact; some hosts require the sender to belong to the domain.

**Debug mode**: create an `eclair-config.php` file at the root (see `eclair-config.exemple.php`) with `define('ECLAIR_DEBUG', true);`, then delete it once the problem is solved.

### Nginx

Éclair doesn't need `.htaccess` on Nginx, but these rules must be reproduced:

```nginx
location ~ ^/(app|data|cache)(/|$) { return 404; }
location ~ /\.(?!well-known/) { return 404; }
location ^~ /media/ {
    location ~* \.(php\d?|phtml|phar|s?html?|svgz?|xml|js)$ { return 404; }
    add_header X-Content-Type-Options nosniff;
    expires 1y;
}
location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)$ { expires 1y; add_header Cache-Control "public, immutable"; }
location / { try_files $uri /index.php$is_args$args; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { return 404; }
```

---

## For developers

```
eclair/                  ← upload this to your hosting
├── index.php            front controller (serves the cache without loading the application)
├── .htaccess            rewriting, protections, compression, browser caching
├── app/lib/             code (Eclair\… classes): Content, Blocks, Seo, Analyzer, Media, Auth…
├── app/admin/views/     admin screens
├── app/theme/           public site templates + theme.css (inlined in every page)
├── app/lang/            admin translations (en, es); the French text is the key
├── assets/admin/        admin CSS and JavaScript (no dependencies)
├── assets/site/site.js  the only public script (2.4 KB, 1.2 KB compressed)
├── data/                content, settings, accounts (created at installation, never public)
├── cache/               generated pages
└── media/               uploaded images
tests/run.php            unit tests + HTTP integration (including the attacks from the security review)
tools/router.php         router for PHP's built-in server (development)
tools/build.php          integrity fingerprints + dist/eclair-cms-<version>.zip archive
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # development server
php tests/run.php                                  # tests (unit + HTTP)
php tools/build.php                                # ready-to-ship archive
```

`PHP_CLI_SERVER_WORKERS` lets the site call itself (to verify its address): with a single process, the check simply fails and redirects to the official address stay disabled.

Customize the theme by editing `app/theme/*.php` and `app/theme/theme.css` (keep a backup: the integrity check will flag these files as modified, which is expected).

---

Éclair is released under the [MIT license](../LICENSE). Some admin icons are derived from [Lucide](https://lucide.dev) (ISC license): see [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
