<p align="center">
  <a href="GUIDE.md">Français</a> · <b>English</b> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Éclair CMS guide

Everything you need to know to install, use, secure and maintain Éclair. For the overview, go back to the [README](../README.en.md).

## Contents

1. [Installation](#installation)
2. [Features in detail](#features-in-detail)
3. [Everyday use](#everyday-use)
4. [Themes](#themes)
5. [Shop](#shop)
6. [AI assistant](#ai-assistant)
7. [MCP connector (Claude and ChatGPT)](#mcp-connector-claude-and-chatgpt)
8. [Security](#security)
9. [Updating, backing up, moving](#updating-backing-up-moving)
10. [Troubleshooting](#troubleshooting) (including [Nginx](#nginx))
11. [For developers](#for-developers)

---

## Installation

### Requirements

- **PHP 8.1 or later** (8.3 recommended), with the `mbstring`, `json`, `dom` and `session` extensions (enabled by default almost everywhere).
- Recommended: `gd` (WebP images), `zip` (one-click backups), `curl` (IndexNow).
- An **Apache or LiteSpeed** server (virtually every shared host: Hostinger, IONOS, OVHcloud…). For Nginx, see [below](#nginx).
- An HTTPS certificate (free with Let's Encrypt at almost every host).

The wizard checks all of this for you and explains what to fix.

### Steps

1. Download the [`eclair-cms-1.5.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.5.0.zip) archive (433 KB) and unzip it.
2. Upload **the contents of the `eclair` folder** to your hosting's public folder (`public_html`, `www`…) with your FTP client (FileZilla…) or your host's file manager.
   > ⚠️ `.htaccess` files are hidden: turn on “Show hidden files” to make sure they get uploaded.
3. Open your site's URL: **the setup wizard starts** (in 7 languages).
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
- **CSS inlined in the page** (17 to 23 KB depending on the theme and whether the shop is open, 5 to 6 KB compressed), system fonts: no render-blocking requests, no layout shift.
- **Images optimized on upload**: resized (2000 px max), available as **responsive WebP** (480, 800, 1200, 1600 px), explicit dimensions, lazy loading, priority given to the main image.
- ETag and `304 Not Modified` responses, Brotli/Gzip compression and one-year browser caching for static files (via `.htaccess`).

### 🔎 SEO

- Clean URLs, **automatic 301 redirect** when an address changes, redirect manager (handy for taking over an old WordPress or Wix site).
- Title, meta description and **live Google preview** in the editor, with counters.
- **Real-time SEO analysis** with a score: title and description length, keyphrase (title, URL, introduction, subheadings, density), word count, structure, alt text, internal linking, featured image…
- Complete **JSON-LD structured data**: WebSite (+ search), Organization / Person / LocalBusiness (address, opening hours, social profiles, return policy), WebPage / AboutPage / ContactPage, BlogPosting (author, dates, section), BreadcrumbList, FAQPage, ProfilePage, and for the shop, Product / ProductGroup / Offer (price, stock, shipping).
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

- 18 blocks: text, subheading (H2-H4), list, quote, image, callout, FAQ, table, button, YouTube/Vimeo video (no cookies until clicked), code, separator, hero, features, team (portraits, roles, bios), latest posts, products (shop showcase), contact form.
- Type **`/`** on an empty line to pick a block, drag and drop, formatting toolbar (bold, italic, link, code, highlight).
- **Assisted internal links**: as you type a link, the editor suggests your pages and posts (internal linking in 2 seconds).
- **Smart paste** from Word, Google Docs or a web page: headings, lists, tables and bold text become clean blocks.
- `Ctrl+S` to save, local backup draft (nothing is lost if the connection drops), real preview before publishing, **history of the last 20 versions**, scheduled publishing.

### 🧩 Everything else

Media library (drag and drop, alt text, detection of images in use), drag-and-drop menus (header and footer), “pillar” categories for a topic cluster, site search, spam-proof contact form **without a captcha** (honeypot field, token, limits per network and per day) with an inbox, administrator and editor accounts, 6 themes with live preview, automatic dark mode, 6 system font stacks, main color with guaranteed WCAG contrast, admin in 7 languages (English, French, Spanish, German, Italian, Portuguese, Chinese), public site in 6 languages (fr, es, en, de, it, pt).

---

## Everyday use

| I want to… | Where |
|---|---|
| Write a post | Dashboard › **Write a post** |
| Ask the AI for a post, a page, an image | **“What do you want to create today?”** field on the dashboard, or **✨ AI assistant** (`Ctrl+J`) |
| Edit a page from the site | **✏️ Edit** button (visible only when you're logged in) |
| Change the theme, logo, color, fonts | Settings › Appearance |
| Add my own CSS | Settings › Appearance › Custom CSS |
| Open the shop | Settings › Shop |
| Add a product | Content › **+ Product** |
| Choose the home page | Settings › General |
| Allow or block AI | Settings › AI & GEO |
| Turn on the AI assistant | Settings › AI assistant |
| Connect Google Search Console | Settings › SEO |
| Add a link to the menu | Menus |
| Redirect an old URL | Redirects |
| Turn on two-factor authentication | My profile › Two-factor authentication |
| Download a backup | Security › Backup |

Editor shortcuts: `/` insert a block · `Ctrl+S` save · `Ctrl+K` link · `Ctrl+B` bold · `Ctrl+I` italic · `Enter` new paragraph · `Shift+Enter` line break · `Ctrl+J` AI assistant.

---

## Themes

Éclair comes with **6 themes**. Pick one in **Settings › Appearance**: the **live preview** shows your real site (home page, blog, a post, the shop, a product) on desktop or mobile, and nothing is published until you save. You can click around in the preview: links stay inside the preview.

| Theme | Look and feel | Best for |
|---|---|---|
| **Éclair** | Clean, modern and versatile: the original theme | Any kind of site |
| **Editorial** | Magazine style: serif typography, fine rules, drop cap, featured post | Blogs, media outlets, authors |
| **Atelier** | Warm and handcrafted: cream and terracotta tones, soft shapes | Craftspeople, restaurants, wellness |
| **Studio** | Minimalist portfolio: black and white, big headlines, frameless images | Photographers, designers, agencies |
| **Shop** | Built to sell: square photos, sticky header, bold buttons | Shops, makers |
| **Contrast** | Maximum readability: large text, strong contrast, underlined links | Public services, nonprofits, seniors |

Choosing a theme applies its recommended color and fonts: you can change them later. Two layout settings round out the theme: the **header** (logo on the left and menu on the right, or everything centered) and the **post and product lists** (card grid, or a list with the image on the left).

**Why it stays light**: an Éclair theme is just a 1.5 to 3 KB stylesheet, added to the base one. The HTML doesn't change from one theme to another: speed, accessibility, structured data and SEO stay exactly the same. Only the active theme is inlined in the page, and the automatic dark mode works with each of them.

**Custom CSS**: to go further than the theme (a button color, spacing, a block style), paste your rules into **Settings › Appearance › Custom CSS**. They are added after the theme on every page, so they take precedence, and they are stored with your data (`data/`): **Éclair updates keep them**. The `/*dark*/ … /*/dark*/` markers work too: their rules disappear when dark mode is set to “Always light”. For safety, the `<` character and `@import` rules are removed; 64 KB max.

---

## Shop

The **lightweight shop mode** lets you sell a few dozen products without the overhead of a full e-commerce platform: no cart, no customer accounts, no database, no payment details on your server. Each product gets its own page, optimized for Google and AI, and its **Buy now** button leads to a **Stripe payment link** (Payment Links): Stripe collects payments, sends receipts and handles refunds.

### Open the shop

1. **Publish your legal notice** (the installation created an outline for you to complete) **and your terms of sale**. The shop won't open without them. Have them reviewed: as the seller, you're legally responsible for them.
2. In **Settings › Shop**: select these two pages, then set the currency, the shipping cost and delivery time, the shipping countries and the return window (in the EU, distance selling comes with a withdrawal period of at least 14 days, with some exceptions such as personalized products).
3. Click **Create the thank-you page**: Stripe will send your customers back there after payment.
4. Turn on **Shop open**, save, then add the shop to the menu (Menus › Shop).

The catalog is at `/shop` (`/boutique`, `/tienda`, `/negozio` or `/loja` depending on the site's language), and you can change it; if you do, old URLs are redirected.

### Create a product page

Content › **+ Product**. The product page fields are at the top of the editor:

- **Price** and **compare-at price** (optional: the discount is shown as a percentage), **availability** (in stock, sold out, pre-order).
- **Payment link**: the product's Stripe link (`https://buy.stripe.com/…`).
- **Photos**: the first one is the main one (Google, social media); aim for at least 3 views.
- **Variants** (size, color…): each one can have its own price and link. On the site, choosing a variant works without JavaScript.
- **Google Shopping details**: brand, SKU, barcode (GTIN/EAN), condition (new, used, refurbished), digital product or service (no shipping).

Below that, describe the product with the usual blocks, and file it in a **product category** (Categories › “It contains: Products (shop)”). The SEO analysis also checks the price, the payment link, the number of photos and the identifiers. To feature products on a page, add the **Products** block.

With the AI assistant: attach a photo and ask “Create a product page from the attached photo. Price: €29.90.” It writes a complete draft, but **never makes up a price, a discount or a payment link**: you fill in whatever is missing.

### Connect Stripe (Payment Links)

1. In Stripe, open **Payment Links** and create a link: product, price, and shipping address collection if needed.
2. In the **After the payment** tab, choose to redirect customers to your thank-you page (its URL is shown in Settings › Shop).
3. In Stripe's settings (**Customer emails**), turn on receipts for successful payments.
4. Copy the link and paste it into the product page. Éclair adds the product reference to it (`client_reference_id`): each payment shows what was bought.

You receive each order in Stripe (and by email, if you've turned that on): you pack and ship, and Stripe handles refunds if needed. Another payment provider works too, as long as its payment link starts with `https://`.

### What's built in for Google and AI

- Product and Offer **structured data** on every product page (ProductGroup for variants): price, currency, stock, condition, brand, shipping cost and delivery time, and a **return policy** set for your whole business. This is the information Google shows in product results.
- **Product feed** `/products.xml` in Google Merchant Center format: add it as a product source in Merchant Center to appear for free in the Shopping tab.
- Product categories as pillar pages, a sitemap with every photo, breadcrumbs.
- For AI: each product's price and availability in `llms.txt`, and in the Markdown version of each product page (add `.md` to its URL).

### How much does it cost?

Nothing on Éclair's side. Stripe charges no subscription or setup fee, and Payment Links are included: you only pay a fee per payment, for example **1.5% + €0.25** for a standard card from the European Economic Area (2.8% + €0.25 for a premium card, 3.15% + €0.25 for a card from outside the EEA, plus 2% if a currency conversion is needed). These are Stripe's prices in France, taken from [stripe.com/fr/pricing](https://stripe.com/fr/pricing) on October 30, 2026; they may change.

### What the shop doesn't do

No multi-product cart, discount codes, customer accounts or automatic inventory management: the idea is to keep things simple. If you sell hundreds of products or need those features, a full e-commerce platform (Shopify, WooCommerce, PrestaShop) will be a better fit. The prices shown are the ones you enter: display them with taxes included if you sell to consumers.

---

## AI assistant

The assistant writes drafts, improves your texts and creates images **in your site's voice**. You talk to it in plain language, as you would to a colleague: “Write an article about choosing brand colors, with an image.” It first checks whether the topic has already been covered, creates the image, writes the complete draft (title, Key takeaways, subheadings, lists or tables, FAQ, meta description, internal links), then shows it to you with its SEO and GEO scores. **Nothing is published without your click.**

It works with **Claude** (Anthropic) or **ChatGPT** (OpenAI), using your own API key: no Éclair subscription, you pay the provider directly, for what you actually use. Would you rather use your Claude or ChatGPT subscription? Go through the [MCP connector](#mcp-connector-claude-and-chatgpt).

### Turn on the assistant (5 minutes)

1. Create an API key with the provider of your choice: [console.anthropic.com](https://console.anthropic.com/settings/keys) for Claude, [platform.openai.com](https://platform.openai.com/api-keys) for ChatGPT, and add a few dollars of credit.
2. In **Settings › AI assistant**, choose the provider, paste the key, choose the model, then click **Test the connection** (free: no text is generated).
3. For images, choose a generator: **GPT Image 2.5** (same key as ChatGPT), **FLUX 3** ([dashboard.bfl.ai](https://dashboard.bfl.ai)) or **Ideogram 3** ([ideogram.ai](https://ideogram.ai/manage-api), ideal for visuals that include text). Without a generator, the assistant uses your media library and the photos you attach.
4. Click **Draft my profile from my site**, then review it, adjust it and save.

The assistant then appears in the menu (**✨ AI assistant**, `Ctrl+J`), in the editor and on the dashboard. Editors can use it too, if you allow it.

**Claude or ChatGPT, your choice**: save both keys and a selector appears at the top of the assistant so you can choose the AI for each conversation. Your choice is remembered for your account, editor touch-ups included; the provider chosen in the settings remains the default.

### The editorial profile

This is what makes the AI write **like you**: who you are and what you offer, who you're writing for, tone and style (formal or casual, sentence length, humor), values, rules to follow (“always link to the contact page”, “never mention prices”) and image style. One click drafts it from your pages and posts; you have the final say. With every request, the assistant also follows Éclair's analysis rules: its drafts aim for a high SEO and GEO score right from the start.

### What you can ask

- “Write an article about servicing an e-bike in spring, with an image.”
- “Add Léa Martin, art director, to the team page with the attached photo.” (attach the photo with 📎, by drag and drop or by pasting it)
- “Create an ‘Our services’ page that presents our three packages.”
- “Give me 5 article ideas to attract new customers.”
- “Review my home page and suggest improvements.”
- In the editor: “Add a FAQ to this article”, “Make the introduction punchier”.

Each action produces a **card** below the reply:

| Card | What happened | Your buttons |
|---|---|---|
| 📝 Draft | New post or new page, never published | Open · Preview · Publish now or schedule |
| ✏️ Suggested change | For **published** content: nothing changes until you approve | Preview · Apply · Dismiss, then Cancel if needed |
| ✅ Updated | A **draft** changed directly | Open · Preview · Cancel |
| ✅ Changed in the editor | The content you have open is changed in the editor | Cancel (then you save) |
| 🖼️ Image | Image created, optimized (WebP) and added to the media library | Featured image (in the editor) |

“Cancel” works as long as nobody has edited the content since; otherwise, its earlier versions are still in the editor's **Version history**. The assistant's history keeps your last 30 conversations. The assistant never makes up facts about your business (prices, opening hours, figures, testimonials): when information is missing, it leaves a visible placeholder such as **[price to confirm]** and points it out to you.

### Touch-ups in the editor

- **Select a passage**, then click ✨ in the formatting toolbar: improve, fix mistakes, shorten, expand, simplify, warmer, more professional, translate. A click on “Cancel” restores the text.
- **Key takeaways**: “Write” button.
- **SEO tab**: SEO title and description, focus keyphrase, title ideas.
- **Analysis tab**: add a FAQ, missing alt texts, “Improve with the assistant”.
- **Featured image**: “Create with AI” (posts).
- **Media library**: “Describe with AI” writes an image's alt text by looking at it.

### How much does it cost?

You pay the provider for what you actually use. Éclair calculates each expense from the billed tokens and shows it in Settings › AI assistant. Estimate for a full post (about 25,000 input tokens and 7,000 output tokens, searches and checks included):

| Model | Price per million tokens (input / output) | Full post |
|---|---|---|
| Claude Opus 5.5 (the most capable) | $4 / $20 | ≈ $0.24 |
| Claude Sonnet 5.5 | $2 / $10 | ≈ $0.12 |
| Claude Haiku 5.5 | $0.10 / $0.50 | < $0.01 |
| GPT-6 Astra | $10 / $50 | ≈ $0.60 |
| GPT-6.1 Sol | $2 / $10 | ≈ $0.12 |
| GPT-6.1 Terra | $1 / $5 | ≈ $0.06 |
| GPT-6 Luna | $0.10 / $0.50 | < $0.01 |

An image costs about $0.05 (FLUX 3 at 1 megapixel: $0.048). Public prices taken from the official pages of [Anthropic](https://platform.claude.com/docs/en/about-claude/pricing), [OpenAI](https://developers.openai.com/api/docs/pricing) and [Black Forest Labs](https://docs.bfl.ml/release-notes) on October 29, 2026; they may change. The assistant's fixed instructions are cached by the provider, which lowers the cost of follow-up requests.

**Safeguards**: a maximum monthly budget ($10 by default), checked before each step of a request: once it's reached, the assistant stops until the following month. There's also a limit on requests per person per day (60 by default), and each person can run at most two requests at a time. The budget and quota are set in Settings › AI assistant.

### Security and privacy

- **The AI prepares, you decide**: it can search and read your content, create drafts, edit a draft (undoable), suggest a change to published content and create images. It **cannot publish, delete, or touch the settings, menus or accounts**. Publishing always takes a human click.
- **Encrypted keys** on your server (libsodium, or AES-256-GCM), never sent to the browser, never displayed (only a hint like “sk-ant…ab12” is shown), **excluded from backups**, as is the conversation history. For stronger protection, add `define('ECLAIR_SECRET', 'a-long-secret-phrase');` to `eclair-config.php` (do this before saving your keys).
- **What goes to the provider**: your request, the images you attach, the editorial profile and the relevant context (the list of your pages and posts, the content open in the editor). Don't include sensitive data. According to their terms (checked on October 29, 2026), [Anthropic](https://www.anthropic.com/legal/commercial-terms) doesn't train its models on data sent through the API, and neither does [OpenAI](https://help.openai.com/en/articles/5722486-api-data-usage-policies) by default.
- **Sanitized content**: everything the AI writes goes through the same allowlist as the editor, and its edits only touch the visible text (never a link's URL). Generated images are downloaded over HTTPS from a verified public address, then re-encoded just like an upload.
- **Prompt injection**: content the AI reads is treated as data, never as instructions; and even if a booby-trapped page tried to hijack it, it could only prepare a draft or a suggestion, which you would see before anything takes effect.

### If the assistant doesn't respond

- **“API key rejected”**: the key was copied incompletely or has been revoked; paste it again and test the connection.
- **“Out of credit”**: top up your account with the provider.
- **“This month's AI budget is used up”**: raise the budget in Settings › AI assistant, or wait until next month.
- **The reply arrives all at once instead of streaming in**: your host's buffering is holding back the stream. Everything works, just without the “live” effect. On Nginx, add `fastcgi_buffering off;` to the PHP block.
- **“Can't reach…”**: your host blocks outgoing connections. Ask them to allow `api.anthropic.com` or `api.openai.com` (and the image generator you chose).
- **The assistant doesn't appear**: it needs the PHP cURL extension and encryption (sodium or openssl), available at almost every host; the setup wizard checks for them.

---

## MCP connector (Claude and ChatGPT)

Would you rather work in **Claude** or **ChatGPT**, with the subscription you already have? The MCP connector (*Model Context Protocol*, the open standard that connects AI assistants to tools) opens your site to them: they read your content, write drafts in your voice and prepare changes. **No API key, no cost for the site**: your subscription does the work. Just like with the built-in assistant, **nothing is published or deleted** without you.

### Turn on the connector (3 minutes)

1. Your site must use **HTTPS**: Claude and ChatGPT refuse any other URL.
2. In **Settings › AI assistant**, at the bottom of the page, turn on **Allow MCP connections**, save, then copy the **Connector URL** (`https://example.com/_eclair/mcp`).
3. Add it to your AI:
   - **Claude** (web and desktop app): *Customize › Connectors › Add › Add custom connector*, give it a name (“My site”), paste the URL, click **Continue**, keep the detected authentication settings, then click **Add**. On Team and Enterprise plans, an organization owner adds it; members then click **Connect**.
   - **ChatGPT** (on the web): *Plugins › + › Add custom MCP server*, give it a name, paste the URL under **Connection**, choose **OAuth** authentication, accept the warning, then click **Create as a plugin**.
4. A page on your site opens: log in if needed (two-factor authentication included), check the app and its return URL (`claude.ai` or `chatgpt.com`), then click **Allow**.

You're all set. Ask, for example: “Look at my site and give me 5 article ideas”, “Write a draft article about servicing an e-bike” or “Review my home page and suggest a more direct tagline”.

**Compatible plans** (checked on October 30, 2026):

| Plan | Read the site | Drafts and suggestions |
|---|---|---|
| **Claude** Free, Pro, Max, Team, Enterprise | ✅ | ✅ (Free: only one custom connector) |
| **ChatGPT** Business, Enterprise, Edu | ✅ | ✅ |
| **ChatGPT** Pro | ✅ | ❌ (read-only) |
| **ChatGPT**, other plans | not listed in OpenAI's help | |

Sources: [Claude help on custom connectors](https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp), [OpenAI help on custom MCP servers](https://help.openai.com/en/articles/12584461-connecting-custom-mcp-servers-in-chatgpt) and [OpenAI documentation](https://developers.openai.com/api/docs/guides/custom-mcp-server). These plans change quickly: check in your app.

### What the AI can do

It starts with `get_site`, then uses the built-in assistant's tools, except image generation (paid with the site's key):

| Tool | What it does |
|---|---|
| `get_site` | site inventory (pages, posts, products, categories and their IDs), editorial profile, SEO and GEO rules |
| `search_content` · `get_content` | search for content, read it in Markdown |
| `find_media` | search for an image in the media library |
| `create_draft` | create a post, a page or a product page **as a draft** |
| `update_content` | edit a draft; for published content, prepare a **suggestion** |

Each draft comes back with a link to **open it in Éclair**; each suggestion, with a link that **applies it in the editor** without saving it: you review it, then you save (or you close it, and nothing changes). Claude and ChatGPT also ask for your approval before each write action, depending on their own settings.

### Connector security

- **Disabled by default**: as long as it is, none of its URLs respond.
- **OAuth 2.1**: PKCE required, automatic app registration without storing anything, return URL checked for an exact match.
- **Your explicit approval**, after you log in to your account (two-factor authentication included), on a page that never reveals your admin URL.
- **One-hour tokens**, renewed automatically and replaced at each renewal; after 60 days without use, you have to give your approval again. Only their hashes are stored, excluded from backups.
- **Revoke access**: one button per app in Settings › AI assistant. Turning off the connector cuts off all apps; so does changing your password or logging out all your devices.
- **Same permissions as the built-in assistant**: editors only have access if “Editors can use the assistant” is checked; at most 60 changes per hour per app, to prevent loops.
- **What goes to Anthropic or OpenAI**: the content the AI reads to answer you, under the terms of your subscription.

### If the connection fails

- **The AI can't reach the site, or authorization fails**: check that the site uses HTTPS and that the URL ends with `/_eclair/mcp`. On Apache, keep Éclair's `.htaccess` up to date: it passes along the authorization header, which some hosts strip.
- **Éclair is installed in a subfolder**: some apps look for authorization at the root of the domain. Install Éclair at the root of a domain or subdomain instead.
- **Firewall or bot protection** (Cloudflare…): let `/_eclair/` and `/.well-known/` through.
- **A suggestion no longer applies**: the content has changed since; ask the AI for the change again.

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
- **Bounded AI assistant**: drafts and suggestions only, API keys encrypted and excluded from backups, monthly budget and daily quota (details in [AI assistant](#security-and-privacy)).
- **Bounded logs** (truncated fields, 1 MB maximum): they can't be inflated.
- **Integrity check**: compares every file with its original fingerprint and flags suspicious files, including a `.php` file without the protection line in `data/` or `cache/`.
- **Live protection test**: the Security page checks from your browser that internal files really are inaccessible, and that the site address is verified.

---

## Updating, backing up, moving

**Back up**: Security › Download a backup (ZIP archive of `data/` and `media/`). Without the zip extension, copy these two folders via FTP.

**Update Éclair**:
1. Make a backup.
2. Via FTP, **delete** the `app/` and `assets/` folders, then upload the ones from the new version along with `index.php` and `.htaccess` (deleting first avoids leaving old files behind, which the integrity check would flag).
3. **Don't touch** `data/` (content, settings, accounts, custom CSS) or `media/` (images).
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
├── app/theme/themes/    the 5 optional themes (one CSS file each, added to theme.css)
├── app/lang/            admin translations (6 languages, compressed JSON) — the French text is the key
├── assets/admin/        admin CSS and JavaScript (no dependencies)
├── assets/site/site.js  the only public script (2.4 KB, 1.2 KB compressed)
├── data/                content, settings, accounts (created at installation, never public)
├── cache/               generated pages
└── media/               uploaded images
i18n/                    human-readable translation sources (compiled by tools/i18n.php)
tests/run.php            unit tests + HTTP integration (including the attacks from the security review)
tests/fake-ai.php        fake AI providers (Claude, ChatGPT, images): the assistant is tested with no key and no spending
tests/shop.php           themes and shop (product pages, Stripe, JSON-LD, Merchant Center feed)
tests/mcp.php            MCP connector (OAuth, PKCE, protocol, revocation) and choosing the AI
tools/router.php         router for PHP's built-in server (development)
tools/build.php          integrity fingerprints + dist/eclair-cms-<version>.zip archive (compacted PHP)
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # development server
php tests/run.php                                  # 456 unit and HTTP checks
php tools/build.php                                # ready-to-ship archive
```

`PHP_CLI_SERVER_WORKERS` lets the site call itself (to verify its address): with a single process, the check simply fails and redirects to the official address stay disabled.

For a style change, prefer **Custom CSS** (Settings › Appearance): it survives updates. To go further, customize the theme by editing `app/theme/*.php`, `app/theme/theme.css` and `app/theme/themes/*.css` (keep a backup: the integrity check will flag these files as modified, which is expected). In the archive, the PHP is compacted (`php -w`, comments removed) to stay under 1 MB: the commented sources are on GitHub.

---

Éclair is released under the [MIT license](../LICENSE). Some admin icons are derived from [Lucide](https://lucide.dev) (ISC license): see [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
