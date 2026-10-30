<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <b>Deutsch</b> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Handbuch zu Éclair CMS

Alles, was du wissen musst, um Éclair zu installieren, zu nutzen, abzusichern und zu pflegen. Zur Übersicht geht es zurück zum [README](../README.de.md).

## Inhalt

1. [Installation](#installation)
2. [Funktionen im Detail](#funktionen-im-detail)
3. [Éclair im Alltag](#éclair-im-alltag)
4. [Themes](#themes)
5. [Shop](#shop)
6. [KI-Assistent](#ki-assistent)
7. [MCP-Connector (Claude und ChatGPT)](#mcp-connector-claude-und-chatgpt)
8. [Sicherheit](#sicherheit)
9. [Aktualisieren, sichern, umziehen](#aktualisieren-sichern-umziehen)
10. [Fehlerbehebung](#fehlerbehebung) (inklusive [Nginx](#nginx))
11. [Für Entwickler](#für-entwickler)

---

## Installation

### Voraussetzungen

- **PHP 8.1 oder neuer** (8.3 empfohlen) mit den Erweiterungen `mbstring`, `json`, `dom` und `session` (fast überall standardmäßig aktiv).
- Empfohlen: `gd` (WebP-Bilder), `zip` (Backups mit einem Klick), `curl` (IndexNow).
- Ein **Apache- oder LiteSpeed-Server** (praktisch jedes Shared Hosting: Hostinger, IONOS, OVHcloud …). Für Nginx siehe [weiter unten](#nginx).
- Ein HTTPS-Zertifikat (bei fast allen Hostern kostenlos über Let's Encrypt).

Der Assistent prüft all das für dich und erklärt, was zu tun ist.

### Schritte

1. Lade das Archiv [`eclair-cms-1.4.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.4.0.zip) (432 KB) herunter und entpacke es.
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
- **CSS direkt in der Seite** (17 bis 23 KB je nach Theme und Shop, 5 bis 6 KB komprimiert), Systemschriften: keine blockierenden Anfragen, keine Layoutverschiebungen.
- **Bilder beim Hochladen optimiert**: verkleinert (maximal 2000 px), als **responsives WebP** bereitgestellt (480, 800, 1200, 1600 px), feste Abmessungen, Lazy Loading, Priorität für das Hauptbild.
- ETag und `304 Not Modified`-Antworten, Brotli-/Gzip-Komprimierung und ein Jahr Browser-Cache für statische Dateien (per `.htaccess`).

### 🔎 SEO

- Saubere URLs, **automatische 301-Weiterleitung**, wenn sich eine Adresse ändert, Weiterleitungsmanager (praktisch, um eine alte WordPress- oder Wix-Website zu übernehmen).
- Titel, Meta-Beschreibung und **Live-Vorschau in Google** im Editor, mit Zeichenzählern.
- **SEO-Analyse in Echtzeit** mit Score: Länge von Titel und Beschreibung, Fokus-Keyphrase (Titel, URL, Einleitung, Zwischenüberschriften, Dichte), Wortanzahl, Struktur, Alternativtexte, interne Verlinkung, Beitragsbild …
- Vollständige **strukturierte Daten in JSON-LD**: WebSite (+ Suche), Organization / Person / LocalBusiness (Adresse, Öffnungszeiten, Social-Media-Profile, Rückgabebedingungen), WebPage / AboutPage / ContactPage, BlogPosting (Autor, Daten, Rubrik), BreadcrumbList, FAQPage, ProfilePage sowie für den Shop Product / ProductGroup / Offer (Preis, Lagerbestand, Versand).
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

- 18 Blöcke: Text, Zwischenüberschrift (H2–H4), Liste, Zitat, Bild, Hinweisbox, FAQ, Tabelle, Button, YouTube-/Vimeo-Video (ohne Cookies vor dem Klick), Code, Trennlinie, Hero, Vorteile, Team (Porträts, Funktionen, Kurzvorstellungen), neueste Artikel, Produkte (Schaufenster des Shops), Kontaktformular.
- Tippe **`/`** in eine leere Zeile, um einen Block auszuwählen, Drag-and-drop, Formatierungsleiste (fett, kursiv, Link, Code, Hervorhebung).
- **Unterstützte interne Links**: Während du einen Link eingibst, schlägt der Editor deine Seiten und Artikel vor (interne Verlinkung in 2 Sekunden).
- **Intelligentes Einfügen** aus Word, Google Docs oder einer Webseite: Überschriften, Listen, Tabellen und Fettdruck werden zu sauberen Blöcken.
- Speichern mit `Strg+S`, lokaler Sicherungsentwurf (nichts geht verloren, wenn die Verbindung abbricht), echte Vorschau vor dem Veröffentlichen, **Verlauf der letzten 20 Versionen**, geplante Veröffentlichung.

### 🧩 Alles Weitere

Mediathek (Drag-and-drop, Alternativtexte, Erkennung verwendeter Bilder), Menüs per Drag-and-drop (Kopf- und Fußzeile), „Säulen“-Kategorien für ein Themencluster, Suche auf der Website, Kontaktformular gegen Spam **ohne Captcha** (Honeypot-Feld, Token, Limits pro Netzwerk und pro Tag) mit Posteingang, Konten für Administratoren und Redakteure, 6 Themes mit Live-Vorschau, automatischer Dark Mode, 6 Systemschriften, Hauptfarbe mit garantiertem WCAG-Kontrast, Verwaltung in 7 Sprachen (Deutsch, Englisch, Französisch, Spanisch, Italienisch, Portugiesisch, Chinesisch), öffentliche Website in 6 Sprachen (fr, es, en, de, it, pt).

---

## Éclair im Alltag

| Ich möchte … | Wo |
|---|---|
| Einen Beitrag schreiben | Dashboard › **Beitrag schreiben** |
| Die KI um einen Beitrag, eine Seite oder ein Bild bitten | Feld **„Was möchtest du heute erstellen?“** im Dashboard oder **✨ KI-Assistent** (`Strg+J`) |
| Eine Seite direkt auf der Website bearbeiten | Schaltfläche **✏️ Bearbeiten** (nur sichtbar, wenn du angemeldet bist) |
| Theme, Logo, Farbe, Schriften ändern | Einstellungen › Design |
| Den Shop öffnen | Einstellungen › Shop |
| Ein Produkt hinzufügen | Inhalte › **+ Produkt** |
| Die Startseite festlegen | Einstellungen › Allgemein |
| KI zulassen oder sperren | Einstellungen › KI & GEO |
| Den KI-Assistenten aktivieren | Einstellungen › KI-Assistent |
| Google Search Console verbinden | Einstellungen › SEO |
| Einen Link zum Menü hinzufügen | Menüs |
| Eine alte URL weiterleiten | Weiterleitungen |
| Zwei-Faktor-Authentifizierung aktivieren | Mein Profil › Zwei-Faktor-Authentifizierung |
| Ein Backup herunterladen | Sicherheit › Backup |

Tastenkürzel im Editor: `/` Block einfügen · `Strg+S` speichern · `Strg+K` Link · `Strg+B` fett · `Strg+I` kursiv · `Enter` neuer Absatz · `Umschalt+Enter` Zeilenumbruch · `Strg+J` KI-Assistent.

---

## Themes

Éclair enthält **6 Themes**. Wähle eines unter **Einstellungen › Design** aus: Die **Live-Vorschau** zeigt deine echte Website (Startseite, Blog, einen Beitrag, den Shop, ein Produkt) in der Desktop- oder Mobilansicht, und nichts wird veröffentlicht, bevor du speicherst. Du kannst in der Vorschau navigieren: Links bleiben in der Vorschau.

| Theme | Stil | Für wen |
|---|---|---|
| **Éclair** | Klar, modern und vielseitig: das Original-Theme | Alle Websites |
| **Editorial** | Magazin-Look: Serifenschrift, feine Linien, Initiale, hervorgehobener Beitrag | Blogs, Medien, Autoren |
| **Atelier** | Warm und handgemacht: Creme- und Terrakottatöne, weiche Formen | Handwerksbetriebe, Restaurants, Wellness |
| **Studio** | Minimalistisches Portfolio: Schwarz-Weiß, große Überschriften, Bilder ohne Rahmen | Fotografen, Designer, Agenturen |
| **Shop** | Zum Verkaufen gemacht: quadratische Fotos, fixierter Header, markante Buttons | Shops, Kreative |
| **Kontrast** | Maximale Lesbarkeit: große Schrift, starke Kontraste, unterstrichene Links | Öffentliche Einrichtungen, Vereine, Senioren |

Wenn du ein Theme auswählst, werden seine Farbe und seine empfohlenen Schriftarten übernommen: Du kannst sie danach ändern. Zwei Layout-Einstellungen ergänzen das Theme: der **Header** (Logo links und Menü rechts oder alles zentriert) und die **Beitrags- und Produktlisten** (Kartenraster oder Liste mit dem Bild links).

**Warum das leicht bleibt**: Ein Éclair-Theme ist nur ein Stylesheet von 1,5 bis 3 KB, das zum Basis-Stylesheet hinzukommt. Das HTML ändert sich von Theme zu Theme nicht: Geschwindigkeit, Barrierefreiheit, strukturierte Daten und SEO bleiben gleich. Nur das aktive Theme wird in die Seite eingefügt, und der automatische Dark Mode funktioniert mit jedem Theme.

---

## Shop

Der **schlanke Shop-Modus** ist dafür gedacht, ein paar Dutzend Produkte ohne den Ballast eines klassischen Onlineshops zu verkaufen: kein Warenkorb, keine Kundenkonten, keine Datenbank, keine Bankdaten auf deinem Server. Jedes Produkt hat seine eigene Seite, optimiert für Google und KI, und sein **Kauf-Button** führt zu einem **Stripe-Zahlungslink** (Payment Links): Stripe wickelt die Zahlung ab, verschickt die Belege und kümmert sich um Rückerstattungen.

### Shop öffnen

1. **Veröffentliche dein Impressum** (die Installation hat dafür ein Gerüst angelegt, das du ergänzen musst) **und deine Allgemeinen Geschäftsbedingungen (AGB)**. Ohne sie lässt sich der Shop nicht öffnen: Lass sie prüfen, denn als Verkäufer haftest du für ihren Inhalt.
2. Unter **Einstellungen › Shop**: Wähle diese beiden Seiten aus und lege die Währung, die Versandkosten und Lieferzeiten, die Lieferländer und die Rückgabefrist fest (beim Fernabsatz in der EU gilt ein Widerrufsrecht von mindestens 14 Tagen, mit Ausnahmen wie personalisierten Produkten).
3. Klicke auf **Danke-Seite erstellen**: Dorthin leitet Stripe deine Kunden nach der Zahlung weiter.
4. Aktiviere **Shop geöffnet**, speichere und füge den Shop dann zum Menü hinzu (Menüs › Shop).

Der Katalog liegt unter der Adresse `/shop` (`/boutique`, `/tienda`, `/negozio` oder `/loja` je nach Sprache der Website) und lässt sich ändern; wenn du sie änderst, werden die alten Adressen weitergeleitet.

### Eine Produktseite erstellen

Inhalte › **+ Produkt**. Ganz oben im Editor stehen die Angaben der Produktseite:

- **Preis** und **Streichpreis** (optional: Der Rabatt wird in Prozent angezeigt), **Verfügbarkeit** (auf Lager, ausverkauft, Vorbestellung).
- **Zahlungslink**: der Stripe-Link des Produkts (`https://buy.stripe.com/…`).
- **Fotos**: Das erste ist das Hauptfoto (Google, soziale Netzwerke); Ziel sind mindestens 3 Ansichten.
- **Varianten** (Größe, Farbe …): Jede Variante kann einen eigenen Preis und Link haben. Auf der Website funktioniert die Auswahl ohne JavaScript.
- **Details für Google Shopping**: Marke, Artikelnummer, Barcode (GTIN/EAN), Zustand (neu, gebraucht, generalüberholt), digitales Produkt oder Dienstleistung (kein Versand).

Darunter beschreibst du das Produkt mit den gewohnten Blöcken und ordnest es einer **Produktkategorie** zu (Kategorien › „Sie enthält: Produkte (Shop)“). Die SEO-Analyse prüft zusätzlich Preis, Zahlungslink, Anzahl der Fotos und Produktkennungen. Um Produkte auf einer Seite hervorzuheben, füge den Block **Produkte** hinzu.

Mit dem KI-Assistenten: Hänge ein Foto an und schreibe „Erstelle eine Produktseite zum angehängten Foto. Preis: 29,90 €“. Er schreibt einen vollständigen Entwurf, **erfindet aber nie einen Preis, einen Rabatt oder einen Zahlungslink**: Was fehlt, ergänzt du selbst.

### Stripe verbinden (Payment Links)

1. Öffne in Stripe **Payment Links** und erstelle einen Link: Produkt, Preis und bei Bedarf die Erfassung der Lieferadresse.
2. Lege auf dem Tab **After the payment** fest, dass Kunden auf deine Danke-Seite weitergeleitet werden (ihre Adresse wird unter Einstellungen › Shop angezeigt).
3. Aktiviere in den Stripe-Einstellungen (**Customer emails**) die Belege für erfolgreiche Zahlungen.
4. Kopiere den Link und füge ihn in die Produktseite ein. Éclair hängt die Produktreferenz an (`client_reference_id`): Bei jeder Zahlung siehst du, was gekauft wurde.

Jede Bestellung kommt in Stripe an (und per E-Mail, wenn du das aktiviert hast): Du verpackst und versendest, Stripe kümmert sich bei Bedarf um die Rückerstattung. Ein anderer Zahlungsanbieter funktioniert ebenfalls, solange sein Zahlungslink mit `https://` beginnt.

### Was Éclair für Google und KI tut

- **Strukturierte Daten** Product und Offer auf jeder Produktseite (ProductGroup für Varianten): Preis, Währung, Lagerbestand, Zustand, Marke, Versandkosten und Lieferzeiten sowie die **Rückgabebedingungen** auf Ebene deines Unternehmens. Das sind die Informationen, die Google in seinen Produktergebnissen anzeigt.
- **Produktfeed** `/products.xml` im Format von Google Merchant Center: Füge ihn im Merchant Center als Produktquelle hinzu, um kostenlos auf dem Tab „Shopping“ zu erscheinen.
- Produktkategorien als „Säulen“-Seiten, Sitemap mit allen Fotos, Breadcrumbs.
- Für KI: Preis und Verfügbarkeit jedes Produkts in `llms.txt` und in der Markdown-Version jeder Produktseite (hänge `.md` an ihre Adresse an).

### Was kostet das?

Éclair selbst kostet nichts. Stripe verlangt weder ein Abo noch eine Einrichtungsgebühr, und Zahlungslinks sind inklusive: Du zahlst nur eine Gebühr pro Zahlung, zum Beispiel **1,5 % + 0,25 €** für eine Standardkarte aus dem Europäischen Wirtschaftsraum (2,8 % + 0,25 € für eine Premiumkarte, 3,15 % + 0,25 € für eine Karte von außerhalb des EWR, plus 2 % bei einer Währungsumrechnung). Das sind die Preise von Stripe in Frankreich laut [stripe.com/fr/pricing](https://stripe.com/fr/pricing), Stand: 30. Oktober 2026; sie können sich ändern.

### Was der Shop nicht kann

Kein Warenkorb für mehrere Produkte, keine Rabattcodes, keine Kundenkonten und keine automatische Lagerverwaltung: Die Idee ist, einfach zu bleiben. Wenn du Hunderte Artikel verkaufst oder diese Funktionen brauchst, ist ein vollwertiger Onlineshop (Shopify, WooCommerce, PrestaShop) besser geeignet. Angezeigt werden die Preise, die du eingibst: Gib sie inklusive Mehrwertsteuer an, wenn du an Privatkunden verkaufst.

---

## KI-Assistent

Der Assistent schreibt Entwürfe, verbessert deine Texte und erstellt Bilder **im Ton deiner Website**. Du sprichst mit ihm in natürlicher Sprache, wie mit einem Kollegen: „Schreib einen Artikel über die Wahl der Markenfarben, mit einem Bild.“ Er prüft zuerst, ob es zum Thema schon einen Artikel gibt, erstellt das Bild, schreibt den vollständigen Entwurf (Titel, „Das Wichtigste“, Zwischenüberschriften, Listen oder Tabellen, FAQ, Meta-Beschreibung, interne Links) und legt ihn dir dann mit seinen SEO- und GEO-Scores vor. **Nichts wird ohne deinen Klick veröffentlicht.**

Er funktioniert mit **Claude** (Anthropic) oder **ChatGPT** (OpenAI) und deinem eigenen API-Schlüssel: kein Éclair-Abo, du bezahlst direkt beim Anbieter, und zwar nur deinen tatsächlichen Verbrauch. Du arbeitest lieber mit deinem Claude- oder ChatGPT-Abo? Dann nutze den [MCP-Connector](#mcp-connector-claude-und-chatgpt).

### Den Assistenten aktivieren (5 Minuten)

1. Erstelle beim gewählten Anbieter einen API-Schlüssel: [console.anthropic.com](https://console.anthropic.com/settings/keys) für Claude, [platform.openai.com](https://platform.openai.com/api-keys) für ChatGPT, und lade ein paar Dollar Guthaben auf.
2. Wähle unter **Einstellungen › KI-Assistent** den Anbieter, füge den Schlüssel ein, wähle das Modell und klicke dann auf **Verbindung testen** (kostenlos: Es wird kein Text erzeugt).
3. Wähle für Bilder einen Bildgenerator: **GPT Image 2.5** (derselbe Schlüssel wie für ChatGPT), **FLUX 3** ([dashboard.bfl.ai](https://dashboard.bfl.ai)) oder **Ideogram 3** ([ideogram.ai](https://ideogram.ai/manage-api), ideal für Grafiken mit Text). Ohne Bildgenerator nutzt der Assistent deine Mediathek und die Fotos, die du ihm anhängst.
4. Klicke auf **Mein Profil aus meiner Website entwerfen**, lies den Entwurf durch, passe ihn an und speichere.

Danach erscheint der Assistent im Menü (**✨ KI-Assistent**, `Strg+J`), im Editor und im Dashboard. Redakteure können ihn nutzen, wenn du es erlaubst.

**Claude oder ChatGPT, nach Wahl**: Speichere beide Schlüssel, dann erscheint oben im Assistenten eine Auswahl, mit der du die KI für jede Unterhaltung festlegst. Éclair merkt sich deine Entscheidung für dein Konto, Feinschliff im Editor inklusive; der in den Einstellungen gewählte Anbieter bleibt voreingestellt.

### Das Redaktionsprofil

Damit schreibt die KI **wie du**: wer du bist und was du anbietest, für wen du schreibst, Ton und Stil (duzen oder siezen, Satzlänge, Humor), Werte, Regeln, an die sie sich halten soll („immer ein Link zur Kontaktseite“, „nie Preise nennen“), und der Bildstil. Ein Klick entwirft das Profil aus deinen Seiten und Artikeln; das letzte Wort hast du. Bei jeder Anfrage folgt der Assistent außerdem den Analyseregeln von Éclair: Seine Entwürfe zielen von Anfang an auf hohe SEO- und GEO-Scores.

### Worum du ihn bitten kannst

- „Schreib einen Artikel über die Wartung eines E-Bikes im Frühling, mit einem Bild.“
- „Füge Léa Martin, Art Director, mit dem angehängten Foto zur Teamseite hinzu.“ (das Foto hängst du mit 📎, per Drag-and-drop oder durch Einfügen an)
- „Erstelle eine Seite ‚Unsere Leistungen‘, die unsere drei Pakete vorstellt.“
- „Schlag mir 5 Artikelideen vor, mit denen ich neue Kunden gewinne.“
- „Lies meine Startseite durch und schlag Verbesserungen vor.“
- Im Editor: „Füge diesem Artikel eine FAQ hinzu“, „Mach die Einleitung packender“.

Jede Aktion erzeugt unter der Antwort eine **Karte**:

| Karte | Was passiert ist | Deine Schaltflächen |
|---|---|---|
| 📝 Entwurf | Neuer Beitrag oder neue Seite, nicht veröffentlicht | Öffnen · Vorschau · Jetzt veröffentlichen oder planen |
| ✏️ Vorgeschlagene Änderung | Bei einem **veröffentlichten** Inhalt: Nichts ändert sich ohne deine Zustimmung | Vorschau · Übernehmen · Verwerfen, danach bei Bedarf Abbrechen |
| ✅ Aktualisiert | Ein **Entwurf** wurde direkt geändert | Öffnen · Vorschau · Abbrechen |
| ✅ Im Editor geändert | Der geöffnete Inhalt wurde im Editor geändert | Abbrechen (danach speicherst du) |
| 🖼️ Bild | Bild erstellt, optimiert (WebP) und in der Mediathek abgelegt | Beitragsbild (im Editor) |

„Abbrechen“ macht eine Änderung rückgängig, solange niemand den Inhalt seitdem bearbeitet hat; andernfalls findest du frühere Versionen im **Versionsverlauf** des Editors. Der Assistent bewahrt deine letzten 30 Unterhaltungen auf. Er erfindet nie Fakten über dein Unternehmen (Preise, Öffnungszeiten, Zahlen, Kundenstimmen): Fehlt eine Information, setzt er einen sichtbaren Platzhalter wie **[Preis zu bestätigen]** und weist dich darauf hin.

### Feinschliff im Editor

- **Markiere eine Textstelle** und klicke dann in der Formatierungsleiste auf ✨: Verbessern, Fehler korrigieren, Kürzen, Ausbauen, Vereinfachen, Herzlicher, Professioneller, Übersetzen. Ein Klick auf „Abbrechen“ stellt den vorherigen Text wieder her.
- **Das Wichtigste**: Schaltfläche „Schreiben“.
- **Tab „SEO“**: SEO-Titel und Beschreibung, Fokus-Keyphrase, Titelideen.
- **Tab „Analyse“**: FAQ hinzufügen, fehlende Alternativtexte, „Mit dem Assistenten verbessern“.
- **Beitragsbild**: „Mit KI erstellen“ (bei Beiträgen).
- **Mediathek**: „Mit KI beschreiben“ sieht sich ein Bild an und schreibt seinen Alternativtext.

### Was kostet das?

Du bezahlst den Anbieter nach tatsächlicher Nutzung. Éclair berechnet jede Ausgabe anhand der abgerechneten Tokens und zeigt sie unter Einstellungen › KI-Assistent an. Schätzung für einen vollständigen Artikel (etwa 25.000 gelesene und 7.000 geschriebene Tokens, Recherchen und Prüfungen inklusive):

| Modell | Preis pro 1 Million Tokens (gelesen / geschrieben) | Vollständiger Artikel |
|---|---|---|
| Claude Opus 5.5 (das leistungsstärkste) | 4 $ / 20 $ | ≈ 0,24 $ |
| Claude Sonnet 5.5 | 2 $ / 10 $ | ≈ 0,12 $ |
| Claude Haiku 5.5 | 0,10 $ / 0,50 $ | < 0,01 $ |
| GPT-6 Astra | 10 $ / 50 $ | ≈ 0,60 $ |
| GPT-6.1 Sol | 2 $ / 10 $ | ≈ 0,12 $ |
| GPT-6.1 Terra | 1 $ / 5 $ | ≈ 0,06 $ |
| GPT-6 Luna | 0,10 $ / 0,50 $ | < 0,01 $ |

Ein Bild kostet etwa 0,05 $ (FLUX 3 mit 1 Megapixel: 0,048 $). Öffentliche Preise laut den offiziellen Seiten von [Anthropic](https://platform.claude.com/docs/en/about-claude/pricing), [OpenAI](https://developers.openai.com/api/docs/pricing) und [Black Forest Labs](https://docs.bfl.ml/release-notes), Stand: 29. Oktober 2026; sie können sich ändern. Die festen Anweisungen des Assistenten werden beim Anbieter zwischengespeichert, was die Kosten der folgenden Anfragen senkt.

**Leitplanken**: ein maximales Monatsbudget (standardmäßig 10 $), das vor jedem Schritt einer Anfrage geprüft wird: Ist es erreicht, pausiert der Assistent bis zum nächsten Monat. Dazu kommen ein Limit für Anfragen pro Person und Tag (standardmäßig 60) und höchstens zwei gleichzeitige Anfragen pro Person. Budget und Tageslimit stellst du unter Einstellungen › KI-Assistent ein.

### Sicherheit und Datenschutz

- **Die KI bereitet vor, du entscheidest**: Sie kann deine Inhalte durchsuchen und lesen, Entwürfe erstellen, einen Entwurf ändern (lässt sich rückgängig machen), eine Änderung an einem veröffentlichten Inhalt vorschlagen und Bilder erstellen. Sie kann **weder veröffentlichen noch löschen noch Einstellungen, Menüs oder Konten ändern**. Veröffentlichen bleibt der Klick eines Menschen.
- **Verschlüsselte API-Schlüssel** auf deinem Server (libsodium oder AES-256-GCM), nie an den Browser gesendet, nie angezeigt (nur ein Hinweis wie „sk-ant…ab12“), und wie der Unterhaltungsverlauf **von Backups ausgeschlossen**. Für stärkeren Schutz füge `define('ECLAIR_SECRET', 'ein-langer-geheimer-satz');` in `eclair-config.php` ein (und zwar bevor du deine Schlüssel speicherst).
- **Was an den Anbieter geht**: deine Anfrage, die Bilder, die du anhängst, das Redaktionsprofil und der nötige Kontext (Liste deiner Seiten und Artikel, der im Editor geöffnete Inhalt). Gib deshalb keine sensiblen Daten ein. Laut ihren Bedingungen (geprüft am 29. Oktober 2026) trainiert [Anthropic](https://www.anthropic.com/legal/commercial-terms) seine Modelle nicht mit Daten, die per API gesendet werden, und [OpenAI](https://help.openai.com/en/articles/5722486-api-data-usage-policies) standardmäßig ebenfalls nicht.
- **Bereinigte Inhalte**: Alles, was die KI schreibt, durchläuft dieselbe Allowlist wie der Editor, und ihre Überarbeitungen betreffen nur den sichtbaren Text (nie die Adresse eines Links). Erzeugte Bilder werden per HTTPS von einer geprüften öffentlichen Adresse heruntergeladen und dann wie ein Upload neu kodiert.
- **Prompt-Injections**: Inhalte, die die KI liest, werden als Daten behandelt, nie als Befehle; und selbst wenn eine präparierte Seite versuchen würde, sie umzulenken, könnte sie nur einen Entwurf oder einen Vorschlag vorbereiten, den du siehst, bevor irgendetwas passiert.

### Wenn der Assistent nicht antwortet

- **„API-Schlüssel von … abgelehnt“**: Der Schlüssel wurde unvollständig kopiert oder widerrufen. Füge ihn erneut ein und teste die Verbindung.
- **„Guthaben bei … aufgebraucht“**: Lade dein Konto beim Anbieter auf.
- **„KI-Budget für diesen Monat aufgebraucht“**: Erhöhe das Budget unter Einstellungen › KI-Assistent oder warte bis zum nächsten Monat.
- **Die Antwort erscheint auf einmal statt nach und nach**: Ein Puffer deines Hosters hält den Datenstrom zurück. Alles funktioniert, nur ohne den „Live“-Effekt. Füge unter Nginx `fastcgi_buffering off;` in den PHP-Block ein.
- **„… ist nicht erreichbar“**: Dein Hoster blockiert ausgehende Verbindungen. Bitte ihn, `api.anthropic.com` bzw. `api.openai.com` (und den gewählten Bildgenerator) freizugeben.
- **Der Assistent wird nicht angezeigt**: Er braucht die PHP-Erweiterung cURL und Verschlüsselung (sodium oder openssl), die fast alle Hoster bereitstellen; der Installationsassistent prüft das.

---

## MCP-Connector (Claude und ChatGPT)

Du arbeitest lieber in **Claude** oder **ChatGPT**, mit dem Abo, das du schon hast? Der MCP-Connector (*Model Context Protocol*, der offene Standard, der KI mit Werkzeugen verbindet) öffnet ihnen deine Website: Sie lesen deine Inhalte, schreiben Entwürfe in deinem Ton und bereiten Änderungen vor. **Kein API-Schlüssel, keine Kosten für die Website**: Die Arbeit erledigt dein Abo. Wie beim integrierten Assistenten wird ohne dich **nichts veröffentlicht oder gelöscht**.

### Den Connector aktivieren (3 Minuten)

1. Deine Website muss über **HTTPS** laufen: Claude und ChatGPT lehnen andere Adressen ab.
2. Aktiviere unter **Einstellungen › KI-Assistent** unten auf der Seite **MCP-Verbindungen erlauben**, speichere und kopiere dann die **Adresse des Connectors** (`https://beispiel.de/_eclair/mcp`).
3. Füge sie in deiner KI hinzu:
   - **Claude** (Web und Desktop-App): *Anpassen › Connectors › Hinzufügen › Benutzerdefinierten Connector hinzufügen*, gib einen Namen ein („Meine Website“), füge die Adresse ein, klicke auf **Weiter**, übernimm die erkannten Authentifizierungseinstellungen und klicke dann auf **Hinzufügen**. Bei den Tarifen Team und Enterprise fügt ihn ein Inhaber der Organisation hinzu; die Mitglieder klicken danach auf **Verbinden**.
   - **ChatGPT** (im Web): *Plugins › + › Benutzerdefinierten MCP-Server hinzufügen*, gib einen Namen ein, füge die Adresse unter **Verbindung** ein, wähle die Authentifizierung **OAuth**, akzeptiere den Warnhinweis und erstelle dann das Plugin.
4. Eine Seite deiner Website öffnet sich: Melde dich bei Bedarf an (Zwei-Faktor-Authentifizierung inklusive), prüfe die App und ihre Rücksprungadresse (`claude.ai` oder `chatgpt.com`) und klicke dann auf **Erlauben**.

Fertig. Frag zum Beispiel: „Sieh dir meine Website an und schlag mir 5 Artikelideen vor“, „Schreib als Entwurf einen Artikel über die Wartung eines E-Bikes“ oder „Lies meine Startseite durch und schlag einen direkteren Aufhänger vor“.

**Kompatible Tarife** (geprüft am 30. Oktober 2026):

| Tarif | Website lesen | Entwürfe und Vorschläge |
|---|---|---|
| **Claude** Free, Pro, Max, Team, Enterprise | ✅ | ✅ (Free: nur ein benutzerdefinierter Connector) |
| **ChatGPT** Business, Enterprise, Edu | ✅ | ✅ |
| **ChatGPT** Pro | ✅ | ❌ (nur Lesezugriff) |
| **ChatGPT**, andere Tarife | in der Hilfe von OpenAI nicht vorgesehen | |

Quellen: [Claude-Hilfe zu benutzerdefinierten Connectors](https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp), [OpenAI-Hilfe zu benutzerdefinierten MCP-Servern](https://help.openai.com/en/articles/12584461-connecting-custom-mcp-servers-in-chatgpt) und [OpenAI-Dokumentation](https://developers.openai.com/api/docs/guides/custom-mcp-server). Diese Tarife ändern sich schnell: Prüfe das in deiner App.

### Was die KI tun kann

Sie beginnt mit `get_site` und nutzt dann die Werkzeuge des integrierten Assistenten, außer der Bilderstellung (bezahlt mit dem Schlüssel der Website):

| Werkzeug | Aufgabe |
|---|---|
| `get_site` | Inventar der Website (Seiten, Beiträge, Produkte, Kategorien und ihre IDs), Redaktionsprofil, SEO- und GEO-Regeln |
| `search_content` · `get_content` | einen Inhalt suchen und als Markdown lesen |
| `find_media` | ein Bild in der Mediathek suchen |
| `create_draft` | einen Beitrag, eine Seite oder eine Produktseite **als Entwurf** erstellen |
| `update_content` | einen Entwurf ändern; bei einem veröffentlichten Inhalt einen **Vorschlag** vorbereiten |

Jeder Entwurf kommt mit einem Link zurück, um ihn **in Éclair zu öffnen**; jeder Vorschlag mit einem Link, der ihn **im Editor übernimmt**, ohne ihn zu speichern: Du prüfst und speicherst dann (oder du schließt den Editor, und nichts ändert sich). Claude und ChatGPT fragen außerdem vor jeder Schreibaktion nach deiner Zustimmung, je nach ihren eigenen Einstellungen.

### Sicherheit des Connectors

- **Standardmäßig deaktiviert**: Solange er es ist, antwortet keine seiner Adressen.
- **OAuth 2.1**: PKCE verpflichtend, automatische Registrierung der App, ohne etwas zu speichern, Rücksprungadresse auf exakte Übereinstimmung geprüft.
- **Deine ausdrückliche Zustimmung**, nachdem du dich bei deinem Konto angemeldet hast (Zwei-Faktor-Authentifizierung inklusive), auf einer Seite, die die Adresse deiner Verwaltung nie verrät.
- **Tokens mit einer Stunde Gültigkeit**, automatisch erneuert und bei jeder Erneuerung ersetzt; nach 60 Tagen ohne Nutzung musst du erneut zustimmen. Gespeichert werden nur ihre Hashes, von Backups ausgeschlossen.
- **Zugriff entziehen**: eine Schaltfläche pro App unter Einstellungen › KI-Assistent. Wenn du den Connector deaktivierst, verlieren alle Apps den Zugriff; ebenso, wenn du dein Passwort änderst oder alle deine Geräte abmeldest.
- **Dieselben Rechte wie der integrierte Assistent**: Redakteure haben nur Zugriff, wenn „Redakteure dürfen den Assistenten nutzen“ aktiviert ist; höchstens 60 Änderungen pro Stunde und App, gegen Endlosschleifen.
- **Was an Anthropic oder OpenAI geht**: die Inhalte, die die KI liest, um dir zu antworten, gemäß den Bedingungen deines Abos.

### Wenn die Verbindung scheitert

- **Die KI erreicht die Website nicht, oder die Autorisierung scheitert**: Prüfe, ob die Website über HTTPS läuft und die Adresse auf `/_eclair/mcp` endet. Halte unter Apache die `.htaccess` von Éclair aktuell: Sie leitet den Autorisierungs-Header weiter, den manche Hoster entfernen.
- **Éclair ist in einem Unterordner installiert**: Manche Apps suchen die Autorisierung im Stammverzeichnis der Domain. Installiere Éclair besser im Stammverzeichnis einer Domain oder Subdomain.
- **Firewall oder Bot-Schutz** (Cloudflare …): Lass `/_eclair/` und `/.well-known/` durch.
- **Ein Vorschlag lässt sich nicht mehr übernehmen**: Der Inhalt hat sich inzwischen geändert; bitte die KI erneut um die Änderung.

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
- **KI-Assistent mit festen Grenzen**: nur Entwürfe und Vorschläge, verschlüsselte API-Schlüssel, die von Backups ausgeschlossen sind, Monatsbudget und Tageslimit (Details im Abschnitt [KI-Assistent](#sicherheit-und-datenschutz)).
- **Begrenzte Protokolle** (gekürzte Felder, maximal 1 MB): Sie lassen sich nicht aufblähen.
- **Integritätsprüfung**: Vergleicht jede Datei mit ihrem ursprünglichen Fingerabdruck und erkennt verdächtige Dateien, auch eine `.php`-Datei ohne Schutzzeile in `data/` oder `cache/`.
- **Live-Schutztest**: Die Seite „Sicherheit“ prüft aus deinem Browser heraus, ob interne Dateien wirklich unzugänglich sind und ob die Adresse der Website verifiziert ist.

---

## Aktualisieren, sichern, umziehen

**Sichern**: Sicherheit › Backup herunterladen (ZIP-Archiv von `data/` und `media/`). Ohne die Erweiterung zip kopierst du diese beiden Ordner per FTP.

**Éclair aktualisieren**:
1. Erstelle ein Backup.
2. **Lösche** per FTP die Ordner `app/` und `assets/` und lade dann die der neuen Version zusammen mit `index.php` und `.htaccess` hoch (durch das vorherige Löschen bleiben keine alten Dateien zurück, die die Integritätsprüfung melden würde).
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
├── app/theme/themes/    die 5 optionalen Themes (je eine CSS-Datei, die theme.css ergänzt)
├── app/lang/            Übersetzungen der Verwaltung (6 Sprachen, komprimiertes JSON); der französische Text dient als Schlüssel
├── assets/admin/        CSS und JavaScript der Verwaltung (keine Abhängigkeiten)
├── assets/site/site.js  das einzige öffentliche Skript (2,4 KB, 1,2 KB komprimiert)
├── data/                Inhalte, Einstellungen, Konten (bei der Installation angelegt, nie öffentlich)
├── cache/               erzeugte Seiten
└── media/               hochgeladene Bilder
i18n/                    lesbare Quellen der Übersetzungen (kompiliert von tools/i18n.php)
tests/run.php            Unit-Tests + HTTP-Integration (einschließlich der Angriffe aus dem Sicherheitsaudit)
tests/fake-ai.php        simulierte KI-Anbieter (Claude, ChatGPT, Bilder): Der Assistent wird ohne Schlüssel und ohne Kosten getestet
tests/shop.php           Themes und Shop (Produktseiten, Stripe, JSON-LD, Merchant-Center-Feed)
tests/mcp.php            MCP-Connector (OAuth, PKCE, Protokoll, Widerruf) und Wahl der KI
tools/router.php         Router für den eingebauten PHP-Server (Entwicklung)
tools/build.php          Integritäts-Fingerabdrücke + Archiv dist/eclair-cms-<Version>.zip (PHP kompaktiert)
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # Entwicklungsserver
php tests/run.php                                  # 445 Unit- und HTTP-Prüfungen
php tools/build.php                                # Archiv zum Verteilen
```

`PHP_CLI_SERVER_WORKERS` erlaubt der Website, sich selbst aufzurufen (zur Prüfung ihrer Adresse): Mit nur einem Prozess schlägt die Prüfung einfach fehl, und Weiterleitungen auf die offizielle Adresse bleiben deaktiviert.

Das Theme passt du an, indem du `app/theme/*.php`, `app/theme/theme.css` und `app/theme/themes/*.css` bearbeitest (mach vorher eine Sicherung: Die Integritätsprüfung meldet diese Dateien als geändert, das ist normal). Im Archiv ist der PHP-Code kompaktiert (`php -w`, ohne Kommentare), damit es unter 1 MB bleibt: Die kommentierten Quellen liegen auf GitHub.

---

Éclair steht unter der [MIT-Lizenz](../LICENSE). Ein Teil der Icons der Verwaltung basiert auf [Lucide](https://lucide.dev) (ISC-Lizenz): siehe [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
