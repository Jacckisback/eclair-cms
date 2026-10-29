<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <b>Deutsch</b> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Handbuch zu Éclair CMS

Alles, was du wissen musst, um Éclair zu installieren, zu nutzen, abzusichern und zu pflegen. Zur Übersicht geht es zurück zum [README](../README.de.md).

## Inhalt

1. [Installation](#installation)
2. [Funktionen im Detail](#funktionen-im-detail)
3. [Éclair im Alltag](#éclair-im-alltag)
4. [Sicherheit](#sicherheit)
5. [Aktualisieren, sichern, umziehen](#aktualisieren-sichern-umziehen)
6. [Fehlerbehebung](#fehlerbehebung) (inklusive [Nginx](#nginx))
7. [Für Entwickler](#für-entwickler)

---

## Installation

### Voraussetzungen

- **PHP 8.1 oder neuer** (8.3 empfohlen) mit den Erweiterungen `mbstring`, `json`, `dom` und `session` (fast überall standardmäßig aktiv).
- Empfohlen: `gd` (WebP-Bilder), `zip` (Backups mit einem Klick), `curl` (IndexNow).
- Ein **Apache- oder LiteSpeed-Server** (praktisch jedes Shared Hosting: Hostinger, IONOS, OVHcloud …). Für Nginx siehe [weiter unten](#nginx).
- Ein HTTPS-Zertifikat (bei fast allen Hostern kostenlos über Let's Encrypt).

Der Assistent prüft all das für dich und erklärt, was zu tun ist.

### Schritte

1. Lade das Archiv [`eclair-cms-1.1.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip) (315 KB) herunter und entpacke es.
2. Lade **den Inhalt des Ordners `eclair`** in den öffentlichen Ordner deines Webspace hoch (`public_html`, `www` …), mit deinem FTP-Programm (FileZilla …) oder dem Dateimanager deines Hosters.
   > ⚠️ `.htaccess`-Dateien sind versteckt: Aktiviere „Versteckte Dateien anzeigen“, damit sie sicher mit hochgeladen werden.
3. Öffne die Adresse deiner Website: **Der Installationsassistent startet** (in 7 Sprachen, darunter Deutsch).
4. Beantworte 4 Fragen (deine Website, dein Konto, deine Sichtbarkeit, die Adresse deiner Verwaltung). Konfetti 🎉: Deine Website ist online, mit ersten Seiten und einem Beispielartikel.

> 🔒 Aus Sicherheitsgründen muss die Installation **innerhalb von 2 Stunden** nach dem ersten Aufruf abgeschlossen werden (sonst könnte jeder eine hochgeladene und dann vergessene Website übernehmen). Danach lösche die Datei `data/install-window.php` per FTP und lade die Seite neu.

Installation in einem Unterordner (`beispiel.de/website/`): Lade die Dateien einfach in diesen Unterordner hoch, alles wird automatisch erkannt.

### Die 4 Website-Typen

| Typ (im Assistenten) | Was angelegt wird |
|---|---|
| 🏢 Unternehmenswebsite | Startseite (Hero, Vorteile, neueste Artikel), Leistungen (mit FAQ), Über uns, Kontakt, Blog, Impressum, Datenschutzerklärung |
| ✍️ Blog | Startseite = neueste Artikel, Über uns, Kontakt, Kategorie „Guides“, rechtliche Seiten |
| 🧑‍💻 Freelancer / Portfolio | Startseite mit deinem Namen, Kompetenzen, Über mich, Blog, Kontakt, rechtliche Seiten |
| 🎯 Landingpage | Eine Verkaufsseite (Hero, Vorteile, FAQ, Formular) + rechtliche Seiten, ohne Blog |

Die Startinhalte gibt es auf Französisch, Spanisch und Englisch: Eine Website auf Deutsch startet mit den englischen Texten, die du dann übersetzt. Die rechtlichen Seiten enthalten ein Gerüst nach französischem (LCEN) und spanischem Recht (LSSI) sowie der DSGVO; die Pflichtangaben deines Landes (zum Beispiel das Impressum) musst du selbst ergänzen.

### Direkt nach der Installation

Das Dashboard zeigt eine Checkliste „Erste Schritte“: Logo und Farbe, Startseite, erster Artikel, Autorenprofil, Zwei-Faktor-Authentifizierung, rechtliche Angaben, Google Search Console.

---

## Funktionen im Detail

### ⚡ Geschwindigkeit

- **Automatischer statischer Cache**: Jede Seite wird einmal erzeugt und dann ausgeliefert, ohne die Anwendung zu laden. Bei jeder Änderung leert sich der Cache von selbst (und zum geplanten Zeitpunkt eines geplanten Artikels).
- **CSS direkt in der Seite** (≈ 12 KB minifiziert), Systemschriften: keine blockierenden Anfragen, keine Layoutverschiebungen.
- **Bilder beim Hochladen optimiert**: verkleinert (maximal 2000 px), als **responsives WebP** bereitgestellt (480, 800, 1200, 1600 px), feste Abmessungen, Lazy Loading, Priorität für das Hauptbild.
- ETag und `304 Not Modified`-Antworten, Brotli-/Gzip-Komprimierung und ein Jahr Browser-Cache für statische Dateien (per `.htaccess`).

### 🔎 SEO

- Saubere URLs, **automatische 301-Weiterleitung**, wenn sich eine Adresse ändert, Weiterleitungsmanager (praktisch, um eine alte WordPress- oder Wix-Website zu übernehmen).
- Titel, Meta-Beschreibung und **Live-Vorschau in Google** im Editor, mit Zeichenzählern.
- **SEO-Analyse in Echtzeit** mit Score: Länge von Titel und Beschreibung, Fokus-Keyphrase (Titel, URL, Einleitung, Zwischenüberschriften, Dichte), Wortanzahl, Struktur, Alternativtexte, interne Verlinkung, Beitragsbild …
- Vollständige **strukturierte Daten in JSON-LD**: WebSite (+ Suche), Organization / Person / LocalBusiness (Adresse, Öffnungszeiten, Social-Media-Profile), WebPage / AboutPage / ContactPage, BlogPosting (Autor, Daten, Rubrik), BreadcrumbList, FAQPage, ProfilePage.
- XML-Sitemap mit Bildern und `hreflang`, robots.txt, vollständiger RSS-Feed, Open Graph und Twitter Cards (**Vorschaubild 1200×630, automatisch erzeugt**), Breadcrumbs, kanonische URLs.
- Eine einzige Adresse pro Inhalt: Weiterleitungen für HTTPS, mit/ohne www, abschließenden Schrägstrich, Großbuchstaben, `index.php`.
- **IndexNow**: Bing, Yandex, Seznam … werden bei jeder Veröffentlichung benachrichtigt.
- Verifizierung für Google Search Console und Bing Webmaster Tools (füge das `<meta>`-Tag ein, Éclair liest den Code aus).
- Mehrsprachig: Sprache pro Inhalt, verknüpfte Übersetzungen und automatische `hreflang`-Tags.
- Automatische französische Typografie für französische Inhalte (geschützte Leerzeichen vor `: ; ! ?` und in « »-Anführungszeichen).

### 🤖 GEO (KI-Antwortmaschinen)

- **`/llms.txt`** (Standard llmstxt.org) und **`/llms-full.txt`** werden automatisch erzeugt.
- **Markdown-Version jeder Seite**: Hänge `.md` an eine beliebige URL an (mit Metadaten-Kopf, `noindex` und kanonischer URL, damit kein Duplicate Content entsteht).
- **KI-Bot-Regeln mit einem Klick**: offen (empfohlen, um zitiert zu werden), nur Zitate (Antwortmaschinen erlaubt, Training untersagt) oder gesperrt. Berücksichtigt GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Meta-ExternalAgent … Optional mit `Content-Signal`-Direktive.
- Block **„Das Wichtigste“**: die Zusammenfassung, die KI gern zitiert, am Anfang des Artikels.
- **GEO-Score** im Editor: Zusammenfassung, direkte Einleitung, FAQ, Zwischenüberschriften als Fragen, Listen und Tabellen, konkrete Zahlen, externe Quellen, erkennbarer Autor (E-E-A-T), kurze Absätze, Aktualität.
- Autorenseiten mit Biografie und Profilen (`sameAs`), sichtbare Veröffentlichungs- und Aktualisierungsdaten.

### ✍️ Block-Editor

- 16 Blöcke: Text, Zwischenüberschrift (H2–H4), Liste, Zitat, Bild, Hinweisbox, FAQ, Tabelle, Button, YouTube-/Vimeo-Video (ohne Cookies vor dem Klick), Code, Trennlinie, Hero, Vorteile, neueste Artikel, Kontaktformular.
- Tippe **`/`** in eine leere Zeile, um einen Block auszuwählen, Drag-and-drop, Formatierungsleiste (fett, kursiv, Link, Code, Hervorhebung).
- **Unterstützte interne Links**: Während du einen Link eingibst, schlägt der Editor deine Seiten und Artikel vor (interne Verlinkung in 2 Sekunden).
- **Intelligentes Einfügen** aus Word, Google Docs oder einer Webseite: Überschriften, Listen, Tabellen und Fettdruck werden zu sauberen Blöcken.
- Speichern mit `Strg+S`, lokaler Sicherungsentwurf (nichts geht verloren, wenn die Verbindung abbricht), echte Vorschau vor dem Veröffentlichen, **Verlauf der letzten 20 Versionen**, geplante Veröffentlichung.

### 🧩 Alles Weitere

Mediathek (Drag-and-drop, Alternativtexte, Erkennung verwendeter Bilder), Menüs per Drag-and-drop (Kopf- und Fußzeile), „Säulen“-Kategorien für ein Themencluster, Suche auf der Website, Kontaktformular gegen Spam **ohne Captcha** (Honeypot-Feld, Token, Limits pro Netzwerk und pro Tag) mit Posteingang, Konten für Administratoren und Redakteure, automatischer Dark Mode, 6 Systemschriften, Hauptfarbe mit garantiertem WCAG-Kontrast, Verwaltung in 7 Sprachen (Deutsch, Englisch, Französisch, Spanisch, Italienisch, Portugiesisch, Chinesisch), öffentliche Website in 6 Sprachen (fr, es, en, de, it, pt).

---

## Éclair im Alltag

| Ich möchte … | Wo |
|---|---|
| Einen Beitrag schreiben | Dashboard › **Beitrag schreiben** |
| Eine Seite direkt auf der Website bearbeiten | Schaltfläche **✏️ Bearbeiten** (nur sichtbar, wenn du angemeldet bist) |
| Logo, Farbe, Schriften ändern | Einstellungen › Design |
| Die Startseite festlegen | Einstellungen › Allgemein |
| KI zulassen oder sperren | Einstellungen › KI & GEO |
| Google Search Console verbinden | Einstellungen › SEO |
| Einen Link zum Menü hinzufügen | Menüs |
| Eine alte URL weiterleiten | Weiterleitungen |
| Zwei-Faktor-Authentifizierung aktivieren | Mein Profil › Zwei-Faktor-Authentifizierung |
| Ein Backup herunterladen | Sicherheit › Backup |

Tastenkürzel im Editor: `/` Block einfügen · `Strg+S` speichern · `Strg+K` Link · `Strg+B` fett · `Strg+I` kursiv · `Enter` neuer Absatz · `Umschalt+Enter` Zeilenumbruch.

---

## Sicherheit

Éclair ist für eine feindliche Umgebung gebaut (Shared Hosting, Bots, die rund um die Uhr Passwörter ausprobieren). Es hat ein offensives Sicherheitsaudit durchlaufen (Race Conditions, Brute Force, Weiterleitungen, Injections, präparierte Dateien): Jede gefundene Lücke wurde geschlossen und der Angriff anschließend wiederholt, um das zu beweisen (`tests/run.php`).

**Daten und Server**
- **Keine Datenbank**: SQL-Injection ist unmöglich.
- **Doppelt geschützte Daten**: Die Ordner `data/`, `app/` und `cache/` sind per `.htaccess` gesperrt; jede Datendatei beginnt mit `<?php exit; ?>` (selbst wenn ein Server `.htaccess`-Dateien ignorieren würde, wird nichts angezeigt), und keine `<?php`-Sequenz kann hineingeschrieben werden. Private Dateien (Rechte 0600), atomare Schreibvorgänge.
- **Geschützte Installation**: Sie ist nur innerhalb von 2 Stunden nach dem ersten Aufruf möglich, und die Adresse der Website wird erst dann für Weiterleitungen genutzt, wenn sie **vom Server selbst geprüft** wurde (eine falsche oder manipulierte Adresse leitet nichts um).

**Anmeldung**
- **Passwörter** mit Argon2id gehasht (19 MiB, 2 Durchläufe: sicher und auf Shared Hosting gut tragbar; bcrypt als Ausweichlösung), mindestens 10 Zeichen, gängige Passwörter werden abgelehnt.
- **Brute-Force-Schutz**: Jeder Versuch wird reserviert, bevor er geprüft wird (das Limit lässt sich auch mit parallelen Anfragen nicht überschreiten); Limits pro Netzwerk (IPv4-Adresse bzw. /64-Block bei IPv6) und pro angegriffenem Konto. **Dein gewohntes Gerät wird nie ausgesperrt**, auch nicht von einem Angreifer, der absichtlich falsche Passwörter eingibt. Höchstens zwei Passwortprüfungen gleichzeitig: Eine Flut von Anfragen kann den Speicher nicht erschöpfen.
- **Zwei-Faktor-Authentifizierung per TOTP** (Google Authenticator, 1Password, Bitwarden …) mit QR-Code und 8 Wiederherstellungscodes, die nur einmal angezeigt werden; jeder Code funktioniert nur einmal, auch bei parallelen Anfragen; nach 10 falschen Codes wird die 2FA des Kontos 15 Minuten lang gesperrt und der Inhaber per E-Mail benachrichtigt.
- **Konten werden atomar geändert**: Eine laufende Anmeldung kann niemals eine Passwortänderung, eine allgemeine Abmeldung, die Aktivierung der 2FA, eine Herabstufung oder eine Löschung rückgängig machen.
- **Erneute Passwortabfrage** bei sensiblen Aktionen (Backup, Kontoverwaltung, erweiterte Einstellungen, Änderung der E-Mail-Adresse), wenn die Anmeldung mehr als 15 Minuten zurückliegt. Vorläufige Passwörter müssen bei der ersten Anmeldung ersetzt werden.
- **Gehärtete Sitzungen**: keine Sitzung vor der Anmeldung (Bots können die Festplatte nicht füllen), Cookies mit `HttpOnly`, `Secure`, `SameSite=Strict`, Neuerzeugung bei der Anmeldung, Abmeldung bei Inaktivität, an den Browser gebunden, „Meine anderen Geräte abmelden“. Die Verwaltung wird auf HTTPS gezwungen, sobald die Website es nutzt.
- **CSRF-Token** bei allen Aktionen und Prüfung der Herkunft der Anfragen.

**Öffentliche Website**
- **Strenge Header**: Content-Security-Policy (keine Inline-Skripte, SHA-256-Hash für das Stylesheet), Clickjacking-Schutz, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS optional. Sie hängen von den Einstellungen ab, nie von der Anfrage: Der Cache kann nicht vergiftet werden.
- **Keine offenen Weiterleitungen**: Präparierte Adressen (`/\beispiel.de`, Steuerzeichen …) ergeben einen 404-Fehler.
- **Inhalte per Allowlist bereinigt** (das HTML wird analysiert und dann vollständig neu geschrieben); `javascript:`- und `data:`-Links sind unmöglich, auch getarnt (`javascript&colon;`). Die Markdown-, `llms.txt`- und RSS-Versionen bleiben harmlos.
- **Kontaktformular** ohne Captcha: Honeypot-Feld, Token, 5 Nachrichten pro Netzwerk alle 10 Minuten und 100 pro Tag für die ganze Website; der Posteingang ist auf 500 Nachrichten begrenzt (Spam wird zuerst entfernt) und seitenweise aufgeteilt.
- **Bilder werden beim Hochladen neu kodiert** (entfernt versteckte Nutzlasten sowie EXIF-/GPS-Daten); animierte GIFs werden Block für Block neu geschrieben; jedes Bild mit Code wird abgelehnt; maximal 40 Megapixel; SVGs werden abgelehnt, keine Skripte und keine Dateilisten in `/media`.

**Verwaltung**
- **Anpassbare Adresse der Verwaltung** (weniger Bots), Rollen Administrator / Redakteur; die Schaltfläche „Bearbeiten“ der Website verrät diese Adresse nicht.
- **Begrenzte Protokolle** (gekürzte Felder, maximal 1 MB): Sie lassen sich nicht aufblähen.
- **Integritätsprüfung**: Vergleicht jede Datei mit ihrem ursprünglichen Fingerabdruck und erkennt verdächtige Dateien, auch eine `.php`-Datei ohne Schutzzeile in `data/` oder `cache/`.
- **Live-Schutztest**: Die Seite „Sicherheit“ prüft aus deinem Browser heraus, ob interne Dateien wirklich unzugänglich sind und ob die Adresse der Website verifiziert ist.

---

## Aktualisieren, sichern, umziehen

**Sichern**: Sicherheit › Backup herunterladen (ZIP-Archiv von `data/` und `media/`). Ohne die Erweiterung zip kopierst du diese beiden Ordner per FTP.

**Éclair aktualisieren**:
1. Erstelle ein Backup.
2. Ersetze per FTP `index.php`, `.htaccess` sowie die Ordner `app/` und `assets/` durch die der neuen Version.
3. **Lass** `data/` (Inhalte, Einstellungen, Konten) und `media/` (Bilder) **unverändert**.
4. In der Verwaltung: Einstellungen › Erweitert › Cache leeren.

**Zu einem anderen Hoster oder einer anderen Domain umziehen**: Kopiere den ganzen Ordner und ändere dann die Adresse unter Einstellungen › Allgemein. Interne Links passen sich automatisch an, auch in einem Unterordner.

---

## Fehlerbehebung

**Passwort vergessen (oder 2FA-Smartphone verloren) und kein anderer Administrator?**
Lege per FTP eine Datei `data/reset.php` mit genau diesen drei Zeilen an:

```
<?php exit; ?>
deine-adresse@beispiel.de
DeinNeuesPasswort2026
```

Melde dich innerhalb von 24 Stunden **mit dieser E-Mail-Adresse und diesem neuen Passwort an**: Das Passwort wird ersetzt, die Zwei-Faktor-Authentifizierung dieses Kontos deaktiviert, deine anderen Sitzungen werden beendet und die Datei wird automatisch gelöscht. Niemand sonst kann das auslösen (man müsste ihren Inhalt kennen); eine Datei ohne die erste Zeile wird abgelehnt und gelöscht.

**„Installation gesperrt“**: Das Zeitfenster von 2 Stunden nach dem ersten Aufruf ist abgelaufen. Lösche `data/install-window.php` per FTP und lade die Seite neu.

**Adresse der Website nicht verifiziert (Seite „Sicherheit“)**: Dein Server kann sich unter der Adresse der Website nicht selbst aufrufen (Firewall des Hosters, DNS noch nicht verbreitet …). Die Website funktioniert normal, aber ohne Weiterleitung auf die offizielle Adresse (mit/ohne www). Klicke auf „Adresse der Website verifizieren“, sobald die Domain eingerichtet ist.

**Fehler 404 auf allen Seiten außer der Startseite**: Die Datei `.htaccess` wurde nicht hochgeladen (versteckte Datei) oder die URL-Umschreibung ist deaktiviert. Falls dein Hoster es verlangt, entferne das Kommentarzeichen vor der Zeile `RewriteBase /` in der `.htaccess`.

**Fehler 500 direkt nach dem Hochladen**: Dein Hoster lehnt eine Direktive der `.htaccess` ab (sehr selten: Es werden nur Direktiven verwendet, die überall zulässig sind). Entferne den betreffenden Block oder wende dich an deinen Hoster.

**Bilder werden nicht in WebP umgewandelt**: Die Erweiterung GD (oder ihre WebP-Unterstützung) fehlt. Aktiviere sie im PHP-Bereich deines Hosters; bis dahin bleiben die Bilder im JPG-/PNG-Format.

**Ich erhalte die E-Mails des Kontaktformulars nicht**: Die Nachrichten werden auch in der Verwaltung gespeichert (Nachrichten). Prüfe die Adresse unter Einstellungen › Kontakt; manche Hoster verlangen, dass der Absender zur Domain gehört.

**Debug-Modus**: Lege im Stammverzeichnis eine Datei `eclair-config.php` an (siehe `eclair-config.exemple.php`) mit `define('ECLAIR_DEBUG', true);` und lösche sie wieder, sobald das Problem behoben ist.

### Nginx

Unter Nginx braucht Éclair keine `.htaccess`, diese Regeln müssen aber nachgebildet werden:

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

## Für Entwickler

```
eclair/                  ← das kommt auf den Webspace
├── index.php            Front Controller (liefert den Cache aus, ohne die Anwendung zu laden)
├── .htaccess            Umschreibung, Schutzregeln, Komprimierung, Browser-Cache
├── app/lib/             Code (Klassen Eclair\…): Content, Blocks, Seo, Analyzer, Media, Auth …
├── app/admin/views/     Bildschirme der Verwaltung
├── app/theme/           Vorlagen der öffentlichen Website + theme.css (in jede Seite eingefügt)
├── app/lang/            Übersetzungen der Verwaltung (en, es); der französische Text dient als Schlüssel
├── assets/admin/        CSS und JavaScript der Verwaltung (keine Abhängigkeiten)
├── assets/site/site.js  das einzige öffentliche Skript (2,4 KB, 1,2 KB komprimiert)
├── data/                Inhalte, Einstellungen, Konten (bei der Installation angelegt, nie öffentlich)
├── cache/               erzeugte Seiten
└── media/               hochgeladene Bilder
tests/run.php            Unit-Tests + HTTP-Integration (einschließlich der Angriffe aus dem Sicherheitsaudit)
tools/router.php         Router für den eingebauten PHP-Server (Entwicklung)
tools/build.php          Integritäts-Fingerabdrücke + Archiv dist/eclair-cms-<Version>.zip
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # Entwicklungsserver
php tests/run.php                                  # Tests (Unit + HTTP)
php tools/build.php                                # Archiv zum Verteilen
```

`PHP_CLI_SERVER_WORKERS` erlaubt der Website, sich selbst aufzurufen (zur Prüfung ihrer Adresse): Mit nur einem Prozess schlägt die Prüfung einfach fehl, und Weiterleitungen auf die offizielle Adresse bleiben deaktiviert.

Das Theme passt du an, indem du `app/theme/*.php` und `app/theme/theme.css` bearbeitest (mach vorher eine Sicherung: Die Integritätsprüfung meldet diese Dateien als geändert, das ist normal).

---

Éclair steht unter der [MIT-Lizenz](../LICENSE). Ein Teil der Icons der Verwaltung basiert auf [Lucide](https://lucide.dev) (ISC-Lizenz): siehe [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
