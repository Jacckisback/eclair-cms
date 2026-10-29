<p align="center">
  <a href="README.md">Français</a> · <b>English</b> · <a href="README.es.md">Español</a> · <a href="README.de.md">Deutsch</a> · <a href="README.it.md">Italiano</a> · <a href="README.pt-BR.md">Português</a> · <a href="README.zh-CN.md">简体中文</a>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/en/banner-dark.webp">
    <img src="docs/images/en/banner-light.webp" alt="Éclair CMS: the ultra-light, ultra-fast CMS built for Google and AI" width="100%">
  </picture>
</p>

<p align="center">
  <a href="https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.0.0.zip"><img src="https://img.shields.io/badge/download-v1.0.0-5b4cf0?style=for-the-badge" alt="Download Éclair 1.0.0"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1 or later">
  <img src="https://img.shields.io/badge/Lighthouse-100-0cce6b?style=for-the-badge&logo=lighthouse&logoColor=white" alt="Lighthouse 100">
  <img src="https://img.shields.io/badge/database-none-14122b?style=for-the-badge" alt="No database">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-22c55e?style=for-the-badge" alt="MIT license"></a>
</p>

<h3 align="center">A professional website in 3 minutes, on any shared hosting.<br>No database. No plugins. No maintenance.</h3>

<p align="center">
  <a href="#install-in-3-minutes"><b>Install</b></a> ·
  <a href="#why-éclair"><b>Why Éclair</b></a> ·
  <a href="#preview"><b>Preview</b></a> ·
  <a href="#security"><b>Security</b></a> ·
  <a href="docs/GUIDE.md"><b>Full guide (French)</b></a>
</p>

<br>

<p align="center">
  <img src="docs/images/en/showcase.webp" alt="The Éclair editor with SEO and GEO analysis scoring 100 out of 100, and the published article on a phone" width="100%">
</p>

## Why Éclair

WordPress can do everything, but it needs a database, plugins for SEO, security and caching… and endless updates. Éclair packs the essentials into a CMS **under 1 MB**, designed for freelancers, craftspeople and small businesses.

<table>
  <tr>
    <td width="33%" valign="top">
      <h3>⚡ Ultra-fast</h3>
      Every page is served from a static cache, without loading the application: <b>100/100</b> on Lighthouse and a <b>0.7 ms</b> server response time (measured locally).
    </td>
    <td width="33%" valign="top">
      <h3>🔎 SEO built in</h3>
      Structured data, sitemap, redirects, Google preview and a <b>live SEO score</b> in the editor, without a single plugin.
    </td>
    <td width="33%" valign="top">
      <h3>🤖 Ready for AI</h3>
      <code>llms.txt</code>, a Markdown version of every page, rules for 30 AI bots and a <b>GEO score</b> to get cited by ChatGPT, Perplexity or Gemini.
    </td>
  </tr>
  <tr>
    <td width="33%" valign="top">
      <h3>🛡️ Secure by default</h3>
      Two-factor authentication, brute-force protection, strict headers, sanitized content. <b>An offensive security review</b>, every vulnerability fixed.
    </td>
    <td width="33%" valign="top">
      <h3>🪶 Ultra-light</h3>
      No database: your content lives in protected files. A <b>257 KB</b> download, zero dependencies, zero cookies on the public site.
    </td>
    <td width="33%" valign="top">
      <h3>😊 Dead simple</h3>
      Guided setup in 4 questions, block editor with <code>/</code>, getting-started checklist. Admin in <b>English, French and Spanish</b>, site in 6 languages.
    </td>
  </tr>
</table>

## Éclair vs. WordPress

| | Classic WordPress | ⚡ Éclair |
|---|---|---|
| **Installation** | Create a MySQL database, then run the wizard | ✅ Upload the folder, answer 4 questions |
| **Database** | MySQL required | ✅ None (protected files) |
| **Download size** | ≈ 35 MB[^wp] | ✅ 257 KB |
| **SEO, sitemap, structured data** | A plugin to install and maintain | ✅ Built in |
| **GEO: llms.txt, Markdown, AI bots** | Plugins, rarely complete | ✅ Built in |
| **Public pages** | Generated on every visit, unless you add a cache plugin | ✅ Served from a static cache |
| **Cookies on the public site** | Often many | ✅ None |
| **Two-factor authentication, brute-force protection** | Plugins | ✅ Built in |
| **Plugin updates** | Frequent, the #1 source of vulnerabilities | ✅ No plugins to update |

## Performance

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/en/performance-dark.webp">
  <img src="docs/images/en/performance-light.webp" alt="Lighthouse: 100 in performance, accessibility, best practices and SEO. 0.7 ms response time, 7 to 10 KB of compressed HTML, 1.2 KB of JavaScript, 0 cookies" width="100%">
</picture>

Measured locally on the demo site (PHP 8.3, illustrated home page and a 600-word article), with Lighthouse 12 on mobile and desktop; a score can vary by one point from one run to the next. How it works:

- **Automatic static cache**: each page is generated once, then served directly by `index.php`. The cache clears itself whenever you change something.
- **CSS inlined in the page**, system fonts: no render-blocking requests, no layout shift.
- **Images optimized on upload**: resized, converted to **responsive WebP** (480 to 1600 px), explicit dimensions and lazy loading.
- **A single public script**, deferred, 1.2 KB compressed. Brotli or Gzip compression and one-year browser caching via `.htaccess`.

On real shared hosting, add your host's network latency and PHP startup time.

## SEO and GEO: found on Google, cited by AI

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/en/geo-dark.webp">
  <img src="docs/images/en/geo-light.webp" alt="A published article becomes an HTML page with JSON-LD, a sitemap with IndexNow, an RSS feed, llms.txt, a Markdown version and a robots.txt, read by Google, Bing, ChatGPT, Perplexity, Gemini, Claude and Copilot" width="100%">
</picture>

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>🔎 SEO</h3>
      <ul>
        <li><b>Real-time analysis</b> with a score: title, meta description, keyphrase, structure, internal linking, alt text…</li>
        <li><b>JSON-LD structured data</b>: Organization or LocalBusiness, BlogPosting, FAQPage, BreadcrumbList, author…</li>
        <li>Sitemap with images and <code>hreflang</code>, full RSS feed, Open Graph with a <b>generated share image</b>.</li>
        <li><b>Automatic 301 redirects</b> when a URL changes, plus a redirect manager to migrate an old site.</li>
        <li><b>IndexNow</b>: Bing and other compatible engines are notified every time you publish.</li>
      </ul>
    </td>
    <td width="50%" valign="top">
      <h3>🤖 GEO (AI answer engines)</h3>
      <ul>
        <li><code>/llms.txt</code> and <code>/llms-full.txt</code> generated automatically (<a href="https://llmstxt.org/">llmstxt.org</a> proposal).</li>
        <li><b>A Markdown version of every page</b>: add <code>.md</code> to any URL.</li>
        <li><b>One-click AI bot policy</b>: open, citations only or closed, for 30 bots (GPTBot, ClaudeBot, PerplexityBot…).</li>
        <li>A <b>“Key takeaways”</b> block at the top of each article: the summary AI likes to quote.</li>
        <li><b>GEO score</b>: FAQ, question-style subheadings, concrete figures, sources, identified author (E-E-A-T)…</li>
      </ul>
    </td>
  </tr>
</table>

## Preview

<table>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/en/frame-dashboard.webp" alt="Éclair dashboard: statistics, pages to improve and site health">
      <p align="center"><b>Dashboard</b><br><sub>What to improve and your site's health, at a glance.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/en/frame-geo.webp" alt="AI and GEO settings: choosing the policy for artificial intelligence bots">
      <p align="center"><b>AI visibility</b><br><sub>Allow, limit or block AI bots in one click.</sub></p>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/en/frame-media.webp" alt="Éclair media library with uploaded images">
      <p align="center"><b>Media library</b><br><sub>Images resized and converted to WebP automatically.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/frame-dark.webp" alt="Public site article displayed in dark mode">
      <p align="center"><b>Automatic dark mode</b><br><sub>The site adapts to each visitor's theme.</sub></p>
    </td>
  </tr>
</table>

<sub>Screenshots from “Atelier Nova”, a fictional demo site built with Éclair (original illustrations). The admin is shown in English; the demo content is in French.</sub>

## Security

Éclair is built for a hostile environment: shared hosting, bots trying passwords around the clock.

- **No database**, so no SQL injection. Data files are doubly protected (`.htaccess` and a `<?php exit; ?>` line), private and written atomically.
- **Hardened login**: Argon2id password hashing, limits per network and per account (your usual device is never locked out by an attacker), **two-factor authentication** with recovery codes.
- **Password re-prompt** for sensitive actions, temporary passwords that must be changed, short and revocable sessions.
- **Locked-down public site**: strict Content-Security-Policy, allowlist-based content sanitizing, no open redirects, re-encoded images.
- **Protected installation**: a 2-hour window, and the site URL verified by the server itself before any redirect.
- **Security page**: file integrity check, folder protection test, security log, one-click backup.

> [!NOTE]
> **Offensive security review.** Race conditions, brute force, redirects, injections, booby-trapped files: 17 vulnerabilities were found (2 high, 7 medium, 8 low) and all of them fixed. The 13 attack scenarios were then replayed against the fixed version: all neutralized. The automated tests (`php tests/run.php`) cover a large part of them.

Full details in the [guide, Security section](docs/GUIDE.md#sécurité) (in French).

## Install in 3 minutes

<p align="center">
  <img src="docs/images/en/install.webp" alt="Installation in three steps: upload the folder, answer four questions, the site is live" width="100%">
</p>

1. **Download** the [`eclair-cms-1.0.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.0.0.zip) archive (257 KB) and unzip it.
2. **Upload the contents of the `eclair` folder** to your hosting's public folder (`public_html`, `www`…), hidden `.htaccess` files included.
3. **Open your site's URL**: the setup wizard starts. Four questions, some confetti, and your site is live with its first pages.

**Requirements**: PHP 8.1 or later (8.3 recommended), Apache or LiteSpeed (virtually every shared host: Hostinger, IONOS, OVHcloud…), an HTTPS certificate. The wizard checks everything and explains what to fix. Nginx works too ([configuration](docs/GUIDE.md#nginx)).

> [!TIP]
> For security, the installation must be completed within **2 hours** of the first visit. After that, delete `data/install-window.php` via FTP and reload the page.

## Documentation

The [full guide](docs/GUIDE.md) is currently available in French. It covers:

- [Installation](docs/GUIDE.md#installation): requirements, site types, first steps
- [Features in detail](docs/GUIDE.md#fonctionnalités-en-détail): speed, SEO, GEO, block editor
- [Everyday use](docs/GUIDE.md#utiliser-éclair-au-quotidien)
- [Updating, backing up, moving](docs/GUIDE.md#mettre-à-jour-sauvegarder-déménager)
- [Troubleshooting](docs/GUIDE.md#dépannage): forgotten password, 404 or 500 errors, Nginx…

## For developers

PHP 8.1+ with no framework and no Composer: plain classes in `eclair/app/lib/`, PHP views and vanilla JavaScript.

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # development server
php tests/run.php                                                           # 174 unit and HTTP tests
php tools/build.php                                                         # ready-to-ship archive
```

Project structure and theme customization: see [For developers](docs/GUIDE.md#pour-les-développeurs) (in French).

## License

Éclair is released under the **MIT license** ([`LICENSE`](LICENSE)): use it, modify it and redistribute it freely, including for commercial use, as long as you keep the copyright notice. Some admin icons are derived from [Lucide](https://lucide.dev) (ISC license, see [`THIRD-PARTY-NOTICES.txt`](THIRD-PARTY-NOTICES.txt)).

<br>

<p align="center"><sub>⚡ Éclair, for freelancers and small businesses who want a fast, well-ranked, hassle-free website.</sub></p>

[^wp]: Official WordPress 7.1.4 archive: 37,239,942 bytes, i.e. 35.5 MB ([download link](https://downloads.wordpress.org/release/wordpress-7.1.4.zip), checked on October 29, 2026).
