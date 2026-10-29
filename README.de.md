<p align="center">
  <a href="README.md">Français</a> · <a href="README.en.md">English</a> · <a href="README.es.md">Español</a> · <b>Deutsch</b> · <a href="README.it.md">Italiano</a> · <a href="README.pt-BR.md">Português</a> · <a href="README.zh-CN.md">简体中文</a>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/de/banner-dark.webp">
    <img src="docs/images/de/banner-light.webp" alt="Éclair CMS: das ultraleichte, ultraschnelle CMS, gemacht für Google und KI" width="100%">
  </picture>
</p>

<p align="center">
  <a href="https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip"><img src="https://img.shields.io/badge/download-v1.1.0-5b4cf0?style=for-the-badge" alt="Éclair 1.1.0 herunterladen"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1 oder neuer">
  <img src="https://img.shields.io/badge/Lighthouse-100-0cce6b?style=for-the-badge&logo=lighthouse&logoColor=white" alt="Lighthouse 100">
  <img src="https://img.shields.io/badge/Datenbank-keine-14122b?style=for-the-badge" alt="Keine Datenbank">
  <a href="LICENSE"><img src="https://img.shields.io/badge/Lizenz-MIT-22c55e?style=for-the-badge" alt="MIT-Lizenz"></a>
</p>

<h3 align="center">Eine professionelle Website in 3 Minuten, auf jedem Shared Hosting.<br>Ohne Datenbank. Ohne Plugins. Ohne Wartung.</h3>

<p align="center">
  <a href="#installation-in-3-minuten"><b>Installieren</b></a> ·
  <a href="#warum-éclair"><b>Warum Éclair</b></a> ·
  <a href="#vorschau"><b>Vorschau</b></a> ·
  <a href="#sicherheit"><b>Sicherheit</b></a> ·
  <a href="docs/GUIDE.de.md"><b>Vollständiges Handbuch</b></a>
</p>

<br>

<p align="center">
  <img src="docs/images/de/showcase.webp" alt="Der Éclair-Editor mit SEO- und GEO-Analyse bei 100 von 100 und der veröffentlichte Artikel auf einem Smartphone" width="100%">
</p>

## Warum Éclair

WordPress kann alles, braucht dafür aber eine Datenbank, Plugins für SEO, Sicherheit und Caching … und endlose Updates. Éclair vereint das Wesentliche in einem CMS mit **unter 1 MB**, gemacht für Selbstständige, Handwerksbetriebe und kleine Unternehmen.

<table>
  <tr>
    <td width="33%" valign="top">
      <h3>⚡ Ultraschnell</h3>
      Jede Seite kommt aus einem statischen Cache, ohne die Anwendung zu laden: <b>100/100</b> bei Lighthouse und <b>0,7 ms</b> Server-Antwortzeit (lokal gemessen).
    </td>
    <td width="33%" valign="top">
      <h3>🔎 SEO integriert</h3>
      Strukturierte Daten, Sitemap, Weiterleitungen, Google-Vorschau und ein <b>Live-SEO-Score</b> im Editor, ganz ohne Plugin.
    </td>
    <td width="33%" valign="top">
      <h3>🤖 Bereit für KI</h3>
      <code>llms.txt</code>, eine Markdown-Version jeder Seite, Regeln für 30 KI-Bots und ein <b>GEO-Score</b>, damit ChatGPT, Perplexity oder Gemini dich zitieren.
    </td>
  </tr>
  <tr>
    <td width="33%" valign="top">
      <h3>🛡️ Sicher ab Werk</h3>
      Zwei-Faktor-Authentifizierung, Brute-Force-Schutz, strenge Header, bereinigte Inhalte. <b>Ein offensives Sicherheitsaudit</b>, alle Lücken geschlossen.
    </td>
    <td width="33%" valign="top">
      <h3>🪶 Ultraleicht</h3>
      Keine Datenbank: Deine Inhalte liegen in geschützten Dateien. Ein Download von <b>315 KB</b>, null Abhängigkeiten, null Cookies auf der öffentlichen Website.
    </td>
    <td width="33%" valign="top">
      <h3>😊 Kinderleicht</h3>
      Geführte Installation mit 4 Fragen, Block-Editor mit <code>/</code>, Checkliste für den Start. Verwaltung in <b>7 Sprachen</b>, Website in 6 Sprachen (jeweils auch auf Deutsch).
    </td>
  </tr>
</table>

## Éclair im Vergleich zu WordPress

| | Klassisches WordPress | ⚡ Éclair |
|---|---|---|
| **Installation** | MySQL-Datenbank anlegen, dann den Assistenten starten | ✅ Ordner hochladen, 4 Fragen beantworten |
| **Datenbank** | MySQL erforderlich | ✅ Keine (geschützte Dateien) |
| **Download-Größe** | ≈ 35 MB[^wp] | ✅ 315 KB |
| **SEO, Sitemap, strukturierte Daten** | Ein Plugin, das installiert und gepflegt werden muss | ✅ Integriert |
| **GEO: llms.txt, Markdown, KI-Bots** | Plugins, selten vollständig | ✅ Integriert |
| **Öffentliche Seiten** | Bei jedem Aufruf neu erzeugt, außer mit Cache-Plugin | ✅ Aus einem statischen Cache ausgeliefert |
| **Cookies auf der öffentlichen Website** | Oft viele | ✅ Keine |
| **Zwei-Faktor-Authentifizierung, Brute-Force-Schutz** | Plugins | ✅ Integriert |
| **Plugin-Updates** | Ständig, die häufigste Ursache für Sicherheitslücken | ✅ Keine Plugins zu aktualisieren |

## Performance

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/de/performance-dark.webp">
  <img src="docs/images/de/performance-light.webp" alt="Lighthouse: 100 in Leistung, Barrierefreiheit, Best Practices und SEO. 0,7 ms Antwortzeit, 7 bis 10 KB komprimiertes HTML, 1,2 KB JavaScript, 0 Cookies" width="100%">
</picture>

Lokal auf der Demo-Website gemessen (PHP 8.3, bebilderte Startseite und ein Artikel mit 600 Wörtern), mit Lighthouse 12 auf Mobilgeräten und Desktop; ein Wert kann von Durchlauf zu Durchlauf um einen Punkt schwanken. So funktioniert es:

- **Automatischer statischer Cache**: Jede Seite wird einmal erzeugt und dann direkt von `index.php` ausgeliefert. Bei jeder Änderung leert sich der Cache von selbst.
- **CSS direkt in der Seite**, Systemschriften: keine blockierenden Anfragen, keine Layoutverschiebungen.
- **Bilder beim Hochladen optimiert**: verkleinert, in **responsives WebP** umgewandelt (480 bis 1600 px), feste Abmessungen und Lazy Loading.
- **Ein einziges öffentliches Skript**, verzögert geladen, 1,2 KB komprimiert. Brotli- oder Gzip-Komprimierung und ein Jahr Browser-Cache per `.htaccess`.

Auf echtem Shared Hosting kommen die Netzwerklatenz und der PHP-Start deines Hosters hinzu.

## SEO und GEO: gefunden bei Google, zitiert von KI

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/de/geo-dark.webp">
  <img src="docs/images/de/geo-light.webp" alt="Aus einem veröffentlichten Artikel werden eine HTML-Seite mit JSON-LD, eine Sitemap mit IndexNow, ein RSS-Feed, llms.txt, eine Markdown-Version und eine robots.txt, gelesen von Google, Bing, ChatGPT, Perplexity, Gemini, Claude und Copilot" width="100%">
</picture>

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>🔎 SEO</h3>
      <ul>
        <li><b>Echtzeit-Analyse</b> mit Score: Titel, Meta-Beschreibung, Fokus-Keyphrase, Struktur, interne Verlinkung, Alternativtexte …</li>
        <li><b>Strukturierte Daten in JSON-LD</b>: Organization oder LocalBusiness, BlogPosting, FAQPage, BreadcrumbList, Autor …</li>
        <li>Sitemap mit Bildern und <code>hreflang</code>, vollständiger RSS-Feed, Open Graph mit <b>automatisch erzeugtem Vorschaubild</b>.</li>
        <li><b>Automatische 301-Weiterleitungen</b>, wenn sich eine Adresse ändert, und ein Weiterleitungsmanager für den Umzug einer alten Website.</li>
        <li><b>IndexNow</b>: Bing und andere kompatible Suchmaschinen werden bei jeder Veröffentlichung benachrichtigt.</li>
      </ul>
    </td>
    <td width="50%" valign="top">
      <h3>🤖 GEO (KI-Antwortmaschinen)</h3>
      <ul>
        <li><code>/llms.txt</code> und <code>/llms-full.txt</code> werden automatisch erzeugt (Vorschlag <a href="https://llmstxt.org/">llmstxt.org</a>).</li>
        <li><b>Markdown-Version jeder Seite</b>: Hänge <code>.md</code> an eine beliebige Adresse an.</li>
        <li><b>KI-Bot-Regeln mit einem Klick</b>: offen, nur Zitate oder gesperrt, für 30 Bots (GPTBot, ClaudeBot, PerplexityBot …).</li>
        <li>Ein Block <b>„Das Wichtigste“</b> am Anfang jedes Artikels: die Zusammenfassung, die KI gern zitiert.</li>
        <li><b>GEO-Score</b>: FAQ, Zwischenüberschriften als Fragen, konkrete Zahlen, Quellen, erkennbarer Autor (E-E-A-T) …</li>
      </ul>
    </td>
  </tr>
</table>

## Vorschau

<table>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/de/frame-dashboard.webp" alt="Éclair-Dashboard: Statistiken, Seiten mit Verbesserungsbedarf und Zustand der Website">
      <p align="center"><b>Dashboard</b><br><sub>Was zu verbessern ist und wie es der Website geht, auf einen Blick.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/de/frame-geo.webp" alt="KI- und GEO-Einstellungen: Auswahl der Regeln für Bots künstlicher Intelligenz">
      <p align="center"><b>Sichtbarkeit in KI</b><br><sub>KI-Bots mit einem Klick zulassen, begrenzen oder sperren.</sub></p>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/de/frame-media.webp" alt="Mediathek von Éclair mit hochgeladenen Bildern">
      <p align="center"><b>Mediathek</b><br><sub>Bilder werden automatisch verkleinert und in WebP umgewandelt.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/frame-dark.webp" alt="Artikel der öffentlichen Website im dunklen Modus">
      <p align="center"><b>Automatischer Dark Mode</b><br><sub>Die Website passt sich dem Farbschema jedes Besuchers an.</sub></p>
    </td>
  </tr>
</table>

<sub>Screenshots von „Atelier Nova“, einer fiktiven, mit Éclair erstellten Demo-Website (eigene Illustrationen). Die Verwaltung ist auf Deutsch zu sehen, die Demo-Inhalte sind auf Französisch.</sub>

## Sicherheit

Éclair ist für eine feindliche Umgebung gebaut: Shared Hosting und Bots, die rund um die Uhr Passwörter ausprobieren.

- **Keine Datenbank**, also keine SQL-Injection. Die Datendateien sind doppelt geschützt (`.htaccess` und eine Zeile `<?php exit; ?>`), privat und werden atomar geschrieben.
- **Abgesicherte Anmeldung**: Passwörter mit Argon2id, Limits pro Netzwerk und pro Konto (dein gewohntes Gerät wird nie von einem Angreifer ausgesperrt), **Zwei-Faktor-Authentifizierung** mit Wiederherstellungscodes.
- **Erneute Passwortabfrage** bei sensiblen Aktionen, vorläufige Passwörter, die geändert werden müssen, kurze und widerrufbare Sitzungen.
- **Abgeriegelte öffentliche Website**: strenge Content-Security-Policy, Inhaltsbereinigung per Allowlist, keine offenen Weiterleitungen, neu kodierte Bilder.
- **Geschützte Installation**: ein Zeitfenster von 2 Stunden, und die Adresse der Website wird vom Server selbst geprüft, bevor weitergeleitet wird.
- **Seite „Sicherheit“**: Integritätsprüfung der Dateien, Test des Ordnerschutzes, Sicherheitsprotokoll, Backup mit einem Klick.

> [!NOTE]
> **Offensives Sicherheitsaudit.** Race Conditions, Brute Force, Weiterleitungen, Injections, präparierte Dateien: 17 Schwachstellen wurden gefunden (2 schwere, 7 mittlere, 8 geringe) und alle behoben. Anschließend wurden die 13 Angriffsszenarien gegen die korrigierte Version wiederholt: alle abgewehrt. Die automatischen Tests (`php tests/run.php`) decken einen großen Teil davon ab.

Alle Details im [Handbuch, Abschnitt Sicherheit](docs/GUIDE.de.md#sicherheit).

## Installation in 3 Minuten

<p align="center">
  <img src="docs/images/de/install.webp" alt="Installation in drei Schritten: Ordner hochladen, vier Fragen beantworten, die Website ist online" width="100%">
</p>

1. **Lade** das Archiv [`eclair-cms-1.1.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip) (315 KB) **herunter** und entpacke es.
2. **Lade den Inhalt des Ordners `eclair`** in den öffentlichen Ordner deines Webspace hoch (`public_html`, `www` …), einschließlich der versteckten `.htaccess`-Dateien.
3. **Öffne die Adresse deiner Website**: Der Assistent startet. Vier Fragen, ein bisschen Konfetti, und deine Website ist mit ihren ersten Seiten online.

**Voraussetzungen**: PHP 8.1 oder neuer (8.3 empfohlen), Apache oder LiteSpeed (praktisch jedes Shared Hosting: Hostinger, IONOS, OVHcloud …) und ein HTTPS-Zertifikat. Der Assistent prüft alles und erklärt, was zu tun ist. Nginx funktioniert ebenfalls ([Konfiguration](docs/GUIDE.de.md#nginx)).

> [!TIP]
> Aus Sicherheitsgründen muss die Installation innerhalb von **2 Stunden** nach dem ersten Aufruf abgeschlossen werden. Danach lösche `data/install-window.php` per FTP und lade die Seite neu.

## Dokumentation

Das [vollständige Handbuch](docs/GUIDE.de.md) erklärt alles Weitere:

- [Installation](docs/GUIDE.de.md#installation): Voraussetzungen, Website-Typen, erste Schritte
- [Funktionen im Detail](docs/GUIDE.de.md#funktionen-im-detail): Geschwindigkeit, SEO, GEO, Block-Editor
- [Éclair im Alltag](docs/GUIDE.de.md#éclair-im-alltag)
- [Aktualisieren, sichern, umziehen](docs/GUIDE.de.md#aktualisieren-sichern-umziehen)
- [Fehlerbehebung](docs/GUIDE.de.md#fehlerbehebung): Passwort vergessen, Fehler 404 oder 500, Nginx …

## Für Entwickler

PHP 8.1+ ohne Framework und ohne Composer: einfache Klassen in `eclair/app/lib/`, PHP-Views und natives JavaScript.

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # Entwicklungsserver
php tests/run.php                                                           # 199 Unit- und HTTP-Tests
php tools/build.php                                                         # Archiv zum Verteilen
```

Projektstruktur und Anpassung des Themes: siehe [Für Entwickler](docs/GUIDE.de.md#für-entwickler).

## Lizenz

Éclair steht unter der **MIT-Lizenz** ([`LICENSE`](LICENSE)): Du darfst es frei nutzen, verändern und weitergeben, auch kommerziell, solange der Copyright-Hinweis erhalten bleibt. Ein Teil der Icons der Verwaltung basiert auf [Lucide](https://lucide.dev) (ISC-Lizenz, siehe [`THIRD-PARTY-NOTICES.txt`](THIRD-PARTY-NOTICES.txt)).

<br>

<p align="center"><sub>⚡ Éclair, für Selbstständige und kleine Unternehmen, die eine schnelle, gut auffindbare und sorgenfreie Website wollen.</sub></p>

[^wp]: Offizielles Archiv von WordPress 7.1.4: 37.239.942 Bytes, also 35,5 MB ([Download-Link](https://downloads.wordpress.org/release/wordpress-7.1.4.zip), geprüft am 29. Oktober 2026).
