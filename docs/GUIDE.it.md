<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <b>Italiano</b> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Guida di Éclair CMS

Tutto quello che c’è da sapere per installare, usare, proteggere e mantenere Éclair. Per la presentazione, torna al [README](../README.it.md).

## Indice

1. [Installazione](#installazione)
2. [Funzionalità in dettaglio](#funzionalità-in-dettaglio)
3. [Uso quotidiano](#uso-quotidiano)
4. [Sicurezza](#sicurezza)
5. [Aggiornare, fare backup, traslocare](#aggiornare-fare-backup-traslocare)
6. [Risoluzione dei problemi](#risoluzione-dei-problemi) (compreso [Nginx](#nginx))
7. [Per sviluppatori](#per-sviluppatori)

---

## Installazione

### Requisiti

- **PHP 8.1 o successivo** (consigliato 8.3), con le estensioni `mbstring`, `json`, `dom` e `session` (attive di default quasi ovunque).
- Consigliate: `gd` (immagini WebP), `zip` (backup in un clic), `curl` (IndexNow).
- Un server **Apache o LiteSpeed** (praticamente tutti gli hosting condivisi: Hostinger, IONOS, OVHcloud…). Per Nginx, vedi [più sotto](#nginx).
- Un certificato HTTPS (gratuito con Let's Encrypt presso quasi tutti i provider).

La procedura guidata controlla tutto questo per te e spiega cosa correggere.

### Passaggi

1. Scarica l’archivio [`eclair-cms-1.1.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip) (315 KB) ed estrailo.
2. Carica **il contenuto della cartella `eclair`** nella cartella pubblica del tuo hosting (`public_html`, `www`…) con il tuo programma FTP (FileZilla…) o con il file manager del tuo provider.
   > ⚠️ I file `.htaccess` sono nascosti: attiva «Mostra file nascosti» per essere sicuro di caricarli.
3. Apri l’indirizzo del tuo sito: **parte la procedura guidata di installazione** (in 7 lingue, italiano compreso).
4. Rispondi a 4 domande (il tuo sito, il tuo account, la tua visibilità, l’indirizzo del pannello). Coriandoli 🎉: il tuo sito è online, con alcune pagine e un articolo di esempio.

> 🔒 Per sicurezza, l’installazione va completata **entro 2 ore** dalla prima visita (altrimenti chiunque potrebbe impadronirsi di un sito caricato e poi dimenticato). Scaduto il tempo, elimina il file `data/install-window.php` via FTP e ricarica la pagina.

Installazione in una sottocartella (`esempio.it/sito/`): basta caricare i file in quella sottocartella, tutto viene rilevato automaticamente.

### I 4 tipi di sito

| Tipo (nella procedura guidata) | Cosa viene creato |
|---|---|
| 🏢 Sito vetrina | Home (banner, punti di forza, ultimi articoli), Servizi (con FAQ), Chi siamo, Contatti, Blog, note legali, privacy policy |
| ✍️ Blog | Home = ultimi articoli, Chi siamo, Contatti, categoria «Guides», pagine legali |
| 🧑‍💻 Freelance / portfolio | Home a tuo nome, competenze, Chi sono, Blog, Contatti, pagine legali |
| 🎯 Pagina unica | Una pagina di vendita (banner, punti di forza, FAQ, modulo) + pagine legali, senza blog |

I contenuti iniziali esistono in francese, spagnolo e inglese: un sito in italiano parte dai testi inglesi, da tradurre. Le pagine legali contengono uno schema basato sul diritto francese (LCEN), spagnolo (LSSI) e sul GDPR; gli obblighi specifici del tuo Paese vanno aggiunti da te.

### Subito dopo l’installazione

La bacheca mostra una checklist «Primi passi»: logo e colore, home page, primo articolo, profilo dell’autore, autenticazione a due fattori, note legali, Google Search Console.

---

## Funzionalità in dettaglio

### ⚡ Velocità

- **Cache statica automatica**: ogni pagina viene generata una volta, poi servita senza caricare l’applicazione. La cache si svuota da sola a ogni modifica (e all’ora prevista per un articolo programmato).
- **CSS incorporato nella pagina** (≈ 12 KB minificato), font di sistema: nessuna richiesta bloccante, nessuno spostamento del layout.
- **Immagini ottimizzate al caricamento**: ridimensionate (massimo 2000 px), disponibili in **WebP responsive** (480, 800, 1200, 1600 px), dimensioni esplicite, caricamento differito, priorità all’immagine principale.
- ETag e risposte `304 Not Modified`, compressione Brotli/Gzip e cache del browser di un anno per i file statici (tramite `.htaccess`).

### 🔎 SEO

- URL pulite, **reindirizzamento 301 automatico** quando un indirizzo cambia, gestore dei reindirizzamenti (comodo per migrare un vecchio sito WordPress o Wix).
- Titolo, meta description e **anteprima Google in tempo reale** nell’editor, con contatori.
- **Analisi SEO in tempo reale** con punteggio: lunghezza di titolo e descrizione, frase chiave (titolo, URL, introduzione, sottotitoli, densità), numero di parole, struttura, testi alternativi, link interni, immagine in evidenza…
- **Dati strutturati JSON-LD** completi: WebSite (+ ricerca), Organization / Person / LocalBusiness (indirizzo, orari, profili social), WebPage / AboutPage / ContactPage, BlogPosting (autore, date, sezione), BreadcrumbList, FAQPage, ProfilePage.
- Sitemap XML con immagini e `hreflang`, robots.txt, feed RSS completo, Open Graph e Twitter Cards (**immagine di condivisione 1200×630 generata automaticamente**), breadcrumb, URL canonici.
- Un solo indirizzo per contenuto: reindirizzamenti per HTTPS, con/senza www, slash finale, maiuscole, `index.php`.
- **IndexNow**: Bing, Yandex, Seznam… vengono avvisati a ogni pubblicazione.
- Verifica di Google Search Console e Bing Webmaster Tools (incolla il tag `<meta>`, Éclair estrae il codice).
- Multilingue: lingua per contenuto, collegamento tra le traduzioni e tag `hreflang` automatici.
- Tipografia francese automatica per i contenuti in francese (spazi indivisibili prima di `: ; ! ?` e dentro le virgolette « »).

### 🤖 GEO (motori di risposta IA)

- **`/llms.txt`** (standard llmstxt.org) e **`/llms-full.txt`** generati automaticamente.
- **Versione Markdown di ogni pagina**: aggiungi `.md` a qualsiasi URL (con intestazione di metadati, `noindex` e URL canonico per non creare contenuti duplicati).
- **Regole per i bot IA in un clic**: aperto (consigliato per essere citati), solo citazioni (motori di risposta ammessi, addestramento vietato) o chiuso. Gestisce GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Meta-ExternalAgent… Direttiva `Content-Signal` facoltativa.
- Blocco **«In breve»**: il riassunto che le IA citano volentieri, mostrato in apertura dell’articolo.
- **Punteggio GEO** nell’editor: riassunto, introduzione diretta, FAQ, sottotitoli in forma di domanda, elenchi e tabelle, dati concreti, fonti esterne, autore identificato (E-E-A-T), paragrafi brevi, aggiornamento.
- Pagine autore con biografia e profili (`sameAs`), date di pubblicazione e di aggiornamento visibili.

### ✍️ Editor a blocchi

- 16 blocchi: testo, sottotitolo (H2-H4), elenco, citazione, immagine, riquadro, FAQ, tabella, pulsante, video YouTube/Vimeo (senza cookie prima del clic), codice, separatore, banner, punti di forza, ultimi articoli, modulo di contatto.
- Digita **`/`** su una riga vuota per scegliere un blocco, trascina e rilascia, barra di formattazione (grassetto, corsivo, link, codice, evidenziatore).
- **Link interni assistiti**: mentre scrivi un link, l’editor ti propone le tue pagine e i tuoi articoli (link interni in 2 secondi).
- **Incolla intelligente** da Word, Google Docs o una pagina web: titoli, elenchi, tabelle e grassetti diventano blocchi puliti.
- Salvataggio con `Ctrl+S`, bozza locale di riserva (non si perde nulla se cade la connessione), anteprima reale prima della pubblicazione, **cronologia delle ultime 20 versioni**, pubblicazione programmata.

### 🧩 Tutto il resto

Libreria media (trascina e rilascia, testi alternativi, rilevamento delle immagini in uso), menu con trascina e rilascia (intestazione e piè di pagina), categorie «pilastro» per un cluster tematico, ricerca nel sito, modulo di contatto antispam **senza captcha** (campo trappola, token, limiti per rete e per giorno) con casella di posta, account amministratore e redattore, modalità scura automatica, 6 font di sistema, colore principale con contrasto WCAG garantito, pannello in 7 lingue (italiano, inglese, francese, spagnolo, tedesco, portoghese, cinese), sito pubblico in 6 lingue (fr, es, en, de, it, pt).

---

## Uso quotidiano

| Voglio… | Dove |
|---|---|
| Scrivere un articolo | Bacheca › **Scrivi un articolo** |
| Modificare una pagina dal sito | Pulsante **✏️ Modifica** (visibile solo quando hai effettuato l’accesso) |
| Cambiare logo, colore, font | Impostazioni › Aspetto |
| Scegliere la home page | Impostazioni › Generali |
| Consentire o bloccare le IA | Impostazioni › IA & GEO |
| Collegare Google Search Console | Impostazioni › SEO |
| Aggiungere un link al menu | Menu |
| Reindirizzare un vecchio URL | Reindirizzamenti |
| Attivare l’autenticazione a due fattori | Il mio profilo › Autenticazione a due fattori |
| Scaricare un backup | Sicurezza › Backup |

Scorciatoie dell’editor: `/` inserisci un blocco · `Ctrl+S` salva · `Ctrl+K` link · `Ctrl+B` grassetto · `Ctrl+I` corsivo · `Invio` nuovo paragrafo · `Maiusc+Invio` a capo.

---

## Sicurezza

Éclair è pensato per un ambiente ostile (hosting condiviso, bot che provano password giorno e notte). È stato sottoposto a un audit di sicurezza offensivo (race condition, brute force, reindirizzamenti, injection, file trappola): ogni vulnerabilità trovata è stata corretta, poi l’attacco è stato ripetuto per dimostrarlo (`tests/run.php`).

**Dati e server**
- **Nessun database**: nessuna SQL injection possibile.
- **Dati con doppia protezione**: le cartelle `data/`, `app/` e `cache/` sono bloccate da `.htaccess`; ogni file di dati inizia con `<?php exit; ?>` (anche se un server ignorasse i `.htaccess`, non verrebbe mostrato nulla) e nessuna sequenza `<?php` può esservi scritta. File privati (permessi 0600), scritture atomiche.
- **Installazione protetta**: è possibile solo entro 2 ore dalla prima visita, e l’indirizzo del sito viene usato per reindirizzare i visitatori solo dopo essere stato **verificato dal server stesso** (un indirizzo errato o manomesso non dirotta nulla).

**Accesso**
- **Password** cifrate con Argon2id (19 MiB, 2 passaggi: sicuro e sostenibile su un hosting condiviso; bcrypt come ripiego), almeno 10 caratteri, password comuni rifiutate.
- **Protezione anti brute force**: ogni tentativo viene prenotato prima di essere verificato (impossibile superare il limite con richieste in parallelo); limiti per rete (indirizzo IPv4 o blocco /64 in IPv6) e per account preso di mira. **Il tuo dispositivo abituale non viene mai bloccato** da un attaccante che sbaglia apposta. Al massimo due verifiche di password alla volta: un’ondata di richieste non esaurisce la memoria.
- **Autenticazione a due fattori TOTP** (Google Authenticator, 1Password, Bitwarden…) con codice QR e 8 codici di recupero mostrati una sola volta; ogni codice vale una sola volta, anche se inviato in parallelo; dopo 10 codici sbagliati, la 2FA dell’account viene bloccata per 15 minuti e il titolare viene avvisato via e-mail.
- **Account modificati in modo atomico**: un accesso in corso non può mai annullare un cambio di password, una disconnessione generale, l’attivazione della 2FA, un declassamento o un’eliminazione.
- **Password richiesta di nuovo** per le azioni sensibili (backup, gestione degli account, impostazioni avanzate, cambio di e-mail) oltre 15 minuti dopo l’accesso. Le password provvisorie vanno sostituite al primo accesso.
- **Sessioni rafforzate**: nessuna sessione prima dell’accesso (i bot non riempiono il disco), cookie `HttpOnly`, `Secure`, `SameSite=Strict`, rigenerazione all’accesso, disconnessione dopo inattività, legate al browser, «Disconnetti i miei altri dispositivi». Il pannello viene forzato su HTTPS non appena il sito lo usa.
- **Token CSRF** su tutte le azioni e verifica dell’origine delle richieste.

**Sito pubblico**
- **Header rigorosi**: Content-Security-Policy (nessuno script in linea, impronta SHA-256 per lo stile), protezione dal clickjacking, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS facoltativo. Dipendono dalle impostazioni, mai dalla richiesta: la cache non può essere avvelenata.
- **Nessun reindirizzamento aperto**: gli indirizzi manomessi (`/\esempio.it`, caratteri di controllo…) restituiscono un 404.
- **Contenuti sanificati** tramite lista bianca (l’HTML viene analizzato e poi riscritto per intero); i link `javascript:` e `data:` sono impossibili, anche camuffati (`javascript&colon;`). Le versioni Markdown, `llms.txt` e RSS restano inerti.
- **Modulo di contatto** senza captcha: campo trappola, token, 5 messaggi per rete ogni 10 minuti e 100 al giorno per tutto il sito; casella limitata a 500 messaggi (lo spam viene eliminato per primo), con paginazione.
- **Immagini ricodificate al caricamento** (elimina i contenuti nascosti e i dati EXIF/GPS); GIF animate riscritte blocco per blocco; qualsiasi immagine che contenga codice viene rifiutata; massimo 40 megapixel; SVG rifiutati, nessuno script né elenco di file in `/media`.

**Pannello**
- **Indirizzo del pannello personalizzabile** (meno bot), ruoli amministratore / redattore; il pulsante «Modifica» del sito non rivela questo indirizzo.
- **Registri limitati** (campi troncati, massimo 1 MB): impossibile gonfiarli.
- **Controllo di integrità**: confronta ogni file con la sua impronta originale e individua i file sospetti, compreso un `.php` senza riga di protezione in `data/` o `cache/`.
- **Test di protezione in tempo reale**: la pagina Sicurezza verifica dal tuo browser che i file interni siano davvero inaccessibili e che l’indirizzo del sito sia verificato.

---

## Aggiornare, fare backup, traslocare

**Fare un backup**: Sicurezza › Scarica un backup (archivio ZIP di `data/` e `media/`). Senza l’estensione zip, copia queste due cartelle via FTP.

**Aggiornare Éclair**:
1. Fai un backup.
2. Sostituisci via FTP `index.php`, `.htaccess` e le cartelle `app/` e `assets/` con quelli della nuova versione.
3. **Non toccare** `data/` (contenuti, impostazioni, account) né `media/` (immagini).
4. Nel pannello: Impostazioni › Avanzate › Svuota la cache.

**Traslocare su un altro hosting o dominio**: copia l’intera cartella, poi cambia l’indirizzo in Impostazioni › Generali. I link interni si adattano automaticamente, anche in una sottocartella.

---

## Risoluzione dei problemi

**Password dimenticata (o telefono della 2FA perso) e nessun altro amministratore?**
Crea via FTP un file `data/reset.php` che contenga esattamente queste tre righe:

```
<?php exit; ?>
tuo-indirizzo@esempio.it
LaTuaNuovaPassword2026
```

Entro 24 ore, **accedi con questa e-mail e questa nuova password**: la password viene sostituita, l’autenticazione a due fattori di quell’account viene disattivata, le tue altre sessioni vengono chiuse e il file viene eliminato automaticamente. Nessun altro può attivarlo (bisogna conoscerne il contenuto); un file senza la prima riga viene rifiutato ed eliminato.

**«Installazione bloccata»**: la finestra di 2 ore dalla prima visita è scaduta. Elimina `data/install-window.php` via FTP e ricarica la pagina.

**Indirizzo del sito non verificato (pagina Sicurezza)**: il tuo server non riesce a chiamare se stesso sull’indirizzo del sito (firewall del provider, DNS non ancora propagato…). Il sito funziona normalmente, ma senza reindirizzamento all’indirizzo ufficiale (con/senza www). Clicca su «Verifica l’indirizzo del sito» quando il dominio è pronto.

**Errore 404 su tutte le pagine tranne la home**: il file `.htaccess` non è stato caricato (file nascosto) oppure la riscrittura degli URL è disattivata. Se il tuo provider lo richiede, togli il commento alla riga `RewriteBase /` del `.htaccess`.

**Errore 500 subito dopo il caricamento**: il tuo provider rifiuta una direttiva del `.htaccess` (molto raro: vengono usate solo direttive accettate ovunque). Elimina il blocco in questione o contatta il tuo provider.

**Le immagini non vengono convertite in WebP**: manca l’estensione GD (o il suo supporto WebP). Attivala nel pannello PHP del tuo provider; nel frattempo le immagini restano in JPG/PNG.

**Non ricevo le e-mail del modulo**: i messaggi vengono salvati anche nel pannello (Messaggi). Controlla l’indirizzo in Impostazioni › Contatti; alcuni provider richiedono che il mittente appartenga al dominio.

**Modalità debug**: crea un file `eclair-config.php` nella radice (vedi `eclair-config.exemple.php`) con `define('ECLAIR_DEBUG', true);`, poi eliminalo una volta risolto il problema.

### Nginx

Con Nginx, Éclair non ha bisogno di `.htaccess`, ma bisogna riprodurre queste regole:

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

## Per sviluppatori

```
eclair/                  ← da caricare sull’hosting
├── index.php            front controller (serve la cache senza caricare l’applicazione)
├── .htaccess            riscrittura, protezioni, compressione, cache del browser
├── app/lib/             codice (classi Eclair\…): Content, Blocks, Seo, Analyzer, Media, Auth…
├── app/admin/views/     schermate del pannello
├── app/theme/           template del sito pubblico + theme.css (inserito in ogni pagina)
├── app/lang/            traduzioni del pannello (en, es); il testo francese fa da chiave
├── assets/admin/        CSS e JavaScript del pannello (nessuna dipendenza)
├── assets/site/site.js  l’unico script pubblico (2,4 KB, 1,2 KB compresso)
├── data/                contenuti, impostazioni, account (creato all’installazione, mai pubblico)
├── cache/               pagine generate
└── media/               immagini caricate
tests/run.php            test unitari + integrazione HTTP (compresi gli attacchi dell’audit di sicurezza)
tools/router.php         router per il server integrato di PHP (sviluppo)
tools/build.php          impronte di integrità + archivio dist/eclair-cms-<versione>.zip
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # server di sviluppo
php tests/run.php                                  # test (unitari + HTTP)
php tools/build.php                                # archivio pronto da distribuire
```

`PHP_CLI_SERVER_WORKERS` permette al sito di chiamare se stesso (verifica del suo indirizzo): con un solo processo, la verifica semplicemente fallisce e i reindirizzamenti all’indirizzo ufficiale restano disattivati.

Il tema si personalizza modificando `app/theme/*.php` e `app/theme/theme.css` (tieni una copia: il controllo di integrità segnalerà questi file come modificati, è normale).

---

Éclair è distribuito con [licenza MIT](../LICENSE). Una parte delle icone del pannello deriva da [Lucide](https://lucide.dev) (licenza ISC): vedi [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
