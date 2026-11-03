<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité 1.10.3, groupe C2 (inclus par tests/run.php) :
 *   - e-mails : adresse de réponse et en-têtes toujours sur une seule ligne (formulaire de contact) ;
 *   - connecteur MCP : mot de passe toujours demandé pour une application inconnue, « Autoriser » protégé du double
 *     clickjacking (consent.js), adresses de retour strictes ;
 *   - éditeur : brouillons de secours du navigateur liés au compte connecté et effacés à la déconnexion ;
 *   - durcissements : santé du site réservée aux administrateurs, liens de l'assistant.
 * Le comportement du JavaScript est exécuté par Node.js quand il est installé (sinon : vérifications de présence seulement).
 */
declare(strict_types=1);

use Eclair\Sanitize;
use Eclair\Store;

/* ─────────────────────────── E-mails : une seule ligne par en-tête ─────────────────────────── */
section('Sécurité 1.10.3 : adresses e-mail et en-têtes sur une seule ligne');
$escapedLf = "\"a\\\nX-Probe:1\"@example.com"; // partie locale entre guillemets : a, antislash, saut de ligne, X-Probe:1
$escapedCr = "\"a\\\rX-Probe:1\"@example.com";
check('e-mail : saut de ligne échappé entre guillemets refusé', Sanitize::email($escapedLf) === '', json_encode(Sanitize::email($escapedLf)));
check('e-mail : retour chariot échappé entre guillemets refusé', Sanitize::email($escapedCr) === '');
check('e-mail : guillemets, antislash, espace, caractère de contrôle refusés', Sanitize::email('"camille"@example.com') === '' && Sanitize::email('a\\b@example.com') === ''
    && Sanitize::email('a b@example.com') === '' && Sanitize::email("a\tb@example.com") === '' && Sanitize::email("a\x7Fb@example.com") === '');
check('e-mail : adresses ordinaires acceptées', Sanitize::email('camille@example.com') === 'camille@example.com'
    && Sanitize::email('Camille.Test+contact@example.org') === 'Camille.Test+contact@example.org' && Sanitize::email("  o'brien@example.ie\n") === "o'brien@example.ie");

/** Faux sendmail : chaque e-mail transmis par mail() devient un fichier du dossier de capture. */
function c2_sendmail(string $dir): string
{
    exec('rm -rf ' . escapeshellarg($dir));
    mkdir($dir, 0700, true);
    file_put_contents($dir . '/sendmail.sh', "#!/bin/sh\nf=\$(mktemp \"\$1/mail.XXXXXX\") && cat > \"\$f\"\n");
    return 'sh ' . $dir . '/sendmail.sh ' . $dir;
}

// Mail::send dans un processus PHP dont sendmail est le faux ci-dessus.
$mailDir = sys_get_temp_dir() . '/eclair-c2-mail-unit-' . getmypid();
$sendmail = c2_sendmail($mailDir);
$mailCode = '
    define("ECLAIR", true); define("ECLAIR_VERSION", "test"); define("ECLAIR_ROOT", ' . var_export(ECLAIR_ROOT, true) . ');
    define("ECLAIR_DATA", ' . var_export($mailDir . '/data', true) . '); define("ECLAIR_TESTING", true);
    spl_autoload_register(static function (string $c): void { $f = ECLAIR_ROOT . "/app/lib/" . substr($c, 7) . ".php"; if (str_starts_with($c, "Eclair\\\\") && is_file($f)) { require $f; } });
    require ECLAIR_ROOT . "/app/lib/helpers.php";
    $dir = ' . var_export($mailDir, true) . ';
    $count = static fn (): int => count(glob($dir . "/mail.*") ?: []);
    $out = [];
    foreach (json_decode((string) stream_get_contents(STDIN), true) as $name => [$to, $subject, $reply]) {
        $before = $count();
        try { $ok = Eclair\Mail::send($to, $subject, "Corps du message.\n", $reply); } catch (Throwable $e) { $ok = "exception"; }
        $out[$name] = [$ok, $count() - $before];
    }
    echo json_encode($out);
';
$vectors = [
    'ordinaire' => ['camille@example.com', 'Nouveau message', 'Camille.Test+contact@example.org'],
    'réponse LF' => ['camille@example.com', 'Nouveau message', $escapedLf],
    'réponse CR' => ['camille@example.com', 'Nouveau message', $escapedCr],
    'sujet CRLF' => ['camille@example.com', "Nouveau message\r\nBcc: x@evil.example", ''],
    'sujet NUL' => ['camille@example.com', "Nouveau message\0", ''],
    'destinataire LF' => ["camille@example.com\n", 'Nouveau message', ''],
];
$proc = proc_open([PHP_BINARY, '-d', 'sendmail_path=' . $sendmail, '-r', $mailCode], [['pipe', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
fwrite($pipes[0], (string) json_encode($vectors));
fclose($pipes[0]);
$mailOut = (array) json_decode((string) stream_get_contents($pipes[1]), true);
fclose($pipes[1]);
proc_close($proc);
$sentFiles = glob($mailDir . '/mail.*') ?: [];
$sentMail = $sentFiles ? (string) file_get_contents($sentFiles[0]) : '';
check('Mail::send : adresse ordinaire envoyée, adresse de réponse sur une ligne', ($mailOut['ordinaire'] ?? null) === [true, 1]
    && preg_match('/^Reply-To: Camille\.Test\+contact@example\.org\r?$/m', $sentMail) === 1, json_encode($mailOut));
check('Mail::send : adresse de réponse avec saut de ligne ou retour chariot, rien ne part', ($mailOut['réponse LF'] ?? null) === [false, 0] && ($mailOut['réponse CR'] ?? null) === [false, 0], json_encode($mailOut));
check('Mail::send : sujet ou destinataire avec retour à la ligne ou octet nul, rien ne part', ($mailOut['sujet CRLF'] ?? null) === [false, 0] && ($mailOut['sujet NUL'] ?? null) === [false, 0]
    && ($mailOut['destinataire LF'] ?? null) === [false, 0], json_encode($mailOut));
check('Mail::send : un seul e-mail parti en tout', count($sentFiles) === 1, (string) count($sentFiles));
exec('rm -rf ' . escapeshellarg($mailDir));

/* ─────────────────────────── JavaScript de l'administration ─────────────────────────── */
section('Sécurité 1.10.3 : brouillons de secours par compte, bouton « Autoriser » protégé, liens de l’assistant');
$adminJs = (string) file_get_contents(ECLAIR_ROOT . '/assets/admin/admin.js');
$editorJs = (string) file_get_contents(ECLAIR_ROOT . '/assets/admin/editor.js');
$oauthView = (string) file_get_contents(ECLAIR_ROOT . '/app/admin/views/oauth.php');
preg_match_all('~localStorage\.\w+\(([^)]*)\)~', $editorJs, $direct);
check('éditeur : brouillons lus, écrits et effacés seulement par le magasin lié au compte (drafts)', str_contains($editorJs, 'drafts.key(item)')
    && str_contains($editorJs, 'drafts.get(storeKey)') && str_contains($editorJs, 'drafts.set(storeKey') && !str_contains($editorJs, "'eclair-draft-")
    && array_filter($direct[1], static fn (string $a): bool => !str_contains($a, 'eclair-mode')) === [], implode(' | ', $direct[0]));
check('admin.js : brouillons effacés à la déconnexion et sur la page qui la suit', str_contains($adminJs, "form[data-logout]") && str_contains($adminJs, 'bye=1')
    && str_contains($adminJs, "SITE + D.me + '-'"));
check('consentement : « Autoriser » désactivé dans le HTML, consent.js chargé', (bool) preg_match('~value="allow"[^>]*\sdisabled\sdata-gate~', $oauthView)
    && str_contains($oauthView, "asset('admin/consent.js')") && is_file(ECLAIR_ROOT . '/assets/admin/consent.js'));

// Exécution réelle des scripts (Node.js) : page minimale et stockage en mémoire.
$harness = <<<'JS'
'use strict';
const vm = require('vm');
const fs = require('fs');
const [adminFile, consentFile, assistantFile] = process.argv.slice(2);
const out = {};
const safe = (name, fn) => { try { out[name] = fn(); } catch (e) { out[name] = 'erreur : ' + e.message; } };
const storage = (init) => {
  const m = new Map(Object.entries(init || {}));
  return {
    get length() { return m.size; },
    key: (i) => [...m.keys()][i] ?? null,
    getItem: (k) => (m.has(k) ? m.get(k) : null),
    setItem: (k, v) => { m.set(String(k), String(v)); },
    removeItem: (k) => { m.delete(k); },
    keys: () => [...m.keys()].sort(),
  };
};
// admin.js sur une page de l'administration réduite à son bloc de données (et au formulaire de déconnexion).
const page = (me, search, store, withLogout = false, solo = false) => {
  const form = { dataset: {}, handlers: [], addEventListener(type, fn) { if (type === 'submit') this.handlers.push(fn); } };
  const forms = withLogout ? [form] : [];
  const data = { textContent: JSON.stringify({ i18n: {}, csrf: '0', me, solo, admin: '/gestion', base: '' }) };
  const ctx = {
    document: {
      getElementById: (id) => (id === 'eclair-data' ? data : null),
      querySelector: () => null,
      querySelectorAll: (sel) => (sel === 'form' || sel === 'form[data-logout]' ? forms : []),
      addEventListener: () => {},
    },
    location: { search, origin: 'http://127.0.0.1', href: 'http://127.0.0.1/gestion' + search },
    localStorage: store, sessionStorage: storage(), URLSearchParams, setTimeout, clearTimeout,
    requestAnimationFrame: () => 0, navigator: {}, confirm: () => true, console,
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(adminFile, 'utf8'), ctx);
  return { E: ctx.Eclair, logout: () => form.handlers.forEach((fn) => fn({ submitter: null, preventDefault() {} })) };
};
const A = 'aaaaaaaaaa';
const B = 'bbbbbbbbbb';
// Brouillons de ce site (dossier « / ») ; ceux d'un autre site Éclair à la même adresse (sous-dossier /test) ne sont jamais touchés.
const SITE = 'eclair-draft-%2F|';
const OTHER = 'eclair-draft-%2Ftest|' + A + '-dd462d991e';
const start = () => ({
  'eclair-draft-dd462d991e': JSON.stringify({ t: 1, data: { title: 'ancien format' } }),
  'eclair-draft-new-page': JSON.stringify({ t: 1, data: { title: 'ancien format, nouvelle page' } }),
  [SITE + B + '-dd462d991e']: JSON.stringify({ t: 1, u: B, data: { title: 'de B' } }),
  [SITE + A + '-dd462d991e']: JSON.stringify({ t: 1, u: A, data: { title: 'de A' } }),
  [OTHER]: JSON.stringify({ t: 1, u: A, data: { title: 'autre site' } }),
  'eclair-mode': 'visual',
  'cle-du-site': 'gardee',
});
safe('signedIn', () => { const s = storage(start()); page(A, '', s); return s.keys(); });
safe('afterLogout', () => { const s = storage(start()); page('', '?bye=1', s); return s.keys(); });
safe('expired', () => { const s = storage(start()); page('', '?next=%2Fedit%2Fdd462d991e', s); return s.keys(); });
safe('logout', () => { const s = storage(start()); page(A, '', s, true).logout(); return s.keys(); });
safe('solo', () => {
  const s = storage(start());
  page(A, '', s, false, true);
  const adopted = JSON.parse(s.getItem(SITE + A + '-new-page') || 'null');
  return [s.keys(), adopted && adopted.u, adopted && adopted.data.title, JSON.parse(s.getItem(SITE + A + '-dd462d991e') || 'null').data.title];
});
safe('keys', () => {
  const a = page(A, '', storage()).E.drafts;
  const anon = page('', '', storage()).E.drafts;
  return [a.key({ id: 'dd462d991e', type: 'page' }), a.key({ id: '', type: 'page' }), anon.key({ id: 'dd462d991e', type: 'page' })];
});
safe('author', () => {
  const s = storage();
  const a = page(A, '', s).E.drafts;
  const k = a.key({ id: 'dd462d991e', type: 'page' });
  a.set(k, { title: 'texte de A' });
  const mine = a.get(k);
  s.setItem(k, JSON.stringify({ t: Date.now(), u: B, data: { title: 'écrit par B' } }));
  const forged = a.get(k);
  s.setItem(k, JSON.stringify({ t: Date.now(), data: { title: 'sans compte' } }));
  return [mine && mine.data.title, forged, a.get(k)];
});
safe('otherAccount', () => {
  const s = storage();
  const a = page(A, '', s).E.drafts;
  a.set(a.key({ id: 'dd462d991e', type: 'page' }), { title: 'texte de A' });
  a.set(a.key({ id: '', type: 'page' }), { title: 'nouvelle page de A' });
  const b = page(B, '', s).E.drafts; // B se connecte ensuite dans le même navigateur
  return [b.get(b.key({ id: 'dd462d991e', type: 'page' })), b.get(b.key({ id: '', type: 'page' })), s.keys()];
});

// consent.js : horloge, focus et visibilité simulés ; un même événement traverse la fenêtre puis le formulaire.
const gate = () => {
  let now = 0;
  let visible = 'visible';
  let focused = true;
  let submits = 0;
  const L = { win: {}, doc: {}, allow: {}, form: {} };
  const on = (m) => (type, fn) => { (m[type] = m[type] || []).push(fn); };
  const dispatch = (maps, type, extra) => {
    const e = Object.assign({ type, detail: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, stopImmediatePropagation() {} }, extra);
    maps.forEach((m) => (m[type] || []).forEach((fn) => fn(e)));
    return e;
  };
  const form = { addEventListener: on(L.form) };
  const allow = { disabled: false, form, addEventListener: on(L.allow) };
  allow.click = () => { if (allow.disabled) return; if (!dispatch([L.allow], 'click', { detail: 0 }).defaultPrevented) submits++; };
  const ctx = {
    document: { querySelector: (s) => (s === '[data-gate]' ? allow : null), get visibilityState() { return visible; }, hasFocus: () => focused, addEventListener: on(L.doc) },
    performance: { now: () => now },
  };
  ctx.window = { addEventListener: on(L.win) };
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(consentFile, 'utf8'), ctx);
  const g = {
    allow,
    last: null,
    at: (t) => { now = t; return g; },
    move: () => { dispatch([L.win], 'pointermove', {}); return g; },
    press: () => { dispatch([L.win], 'pointerdown', {}); return g; },
    key: (key, target = { matches: () => false }) => { g.last = dispatch([L.win, L.form], 'keydown', { key, target, isComposing: false }); return g; },
    click: (detail = 1) => { if (allow.disabled) return false; const e = dispatch([L.allow], 'click', { detail }); if (!e.defaultPrevented) submits++; return !e.defaultPrevented; },
    blur: () => { focused = false; dispatch([L.win], 'blur', {}); return g; },
    focus: () => { focused = true; dispatch([L.win], 'focus', {}); return g; },
    hide: () => { visible = 'hidden'; dispatch([L.doc], 'visibilitychange', {}); return g; },
    submits: () => submits,
  };
  return g;
};
safe('gateLoad', () => gate().allow.disabled);
safe('gateDelay', () => { const g = gate(); g.at(300).move(); const early = g.allow.disabled; g.at(900).move(); return [early, g.allow.disabled]; });
safe('gateClick', () => { const g = gate(); g.at(900).move(); g.at(1300).press(); return g.click(); });
safe('gateDoubleClick', () => {
  // Fenêtre préparée derrière une autre (sans focus), puis amenée au premier plan au premier clic d'un double-clic.
  const g = gate();
  g.at(10).blur().at(4000).move();
  const behind = g.allow.disabled;
  g.at(5000).focus().at(5060).move().at(5120).press();
  return [behind, g.allow.disabled, g.click()];
});
safe('gateFirstTap', () => {
  // Premier appui (doigt) sur le bouton après le délai : il active le bouton, mais son propre clic n'autorise rien.
  const g = gate();
  g.at(1000).press();
  const first = g.click();
  g.at(1600).press();
  return [first, g.click()];
});
safe('gateKeyboard', () => { const g = gate(); g.at(1000).key('Tab'); return [g.allow.disabled, g.click(0)]; });
safe('gateBackground', () => {
  const g = gate();
  g.at(900).move();
  const open = !g.allow.disabled;
  g.at(1000).blur();
  const blurred = g.allow.disabled;
  g.at(1100).focus().at(1200).move();
  const tooSoon = g.allow.disabled;
  g.at(2000).move();
  const again = !g.allow.disabled;
  g.at(2100).hide();
  return [open, blurred, tooSoon, again, g.allow.disabled];
});
safe('gateEnter', () => {
  const input = { matches: (s) => s === 'input' };
  const g = gate();
  g.at(1000).key('a', input).at(1100).key('Enter', input);
  const typed = [g.submits(), g.last.defaultPrevented];
  const h = gate();
  h.at(1000).key('Enter', input); // Entrée seule, premier geste : rien ne part, pas même « Refuser » (envoi implicite bloqué)
  return [typed, [h.submits(), h.last.defaultPrevented]];
});
safe('links', () => {
  const m = fs.readFileSync(assistantFile, 'utf8').match(/const safeHref = (\(url\) => .*?);\n/);
  const fn = vm.runInNewContext(m[1]);
  return ['/contact', '#faq', 'https://exemple.fr/a', '//evil.example/x', '/\\evil.example/x', 'javascript:alert(1)'].map((u) => fn(u) !== null);
});
process.stdout.write(JSON.stringify(out));
JS;
$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node === '') {
    echo "  (Node.js absent : comportement du JavaScript non exécuté, vérifications de présence seulement)\n";
} else {
    $harnessFile = sys_get_temp_dir() . '/eclair-c2-harness-' . getmypid() . '.js';
    file_put_contents($harnessFile, $harness);
    $js = (array) json_decode((string) shell_exec(implode(' ', array_map('escapeshellarg', [$node, $harnessFile, ECLAIR_ROOT . '/assets/admin/admin.js',
        ECLAIR_ROOT . '/assets/admin/consent.js', ECLAIR_ROOT . '/assets/admin/assistant.js'])) . ' 2>&1'), true);
    @unlink($harnessFile);
    $site = 'eclair-draft-%2F|';
    $mine = $site . 'aaaaaaaaaa-dd462d991e';
    $other = 'eclair-draft-%2Ftest|aaaaaaaaaa-dd462d991e';
    $show = static fn (string $k): string => json_encode($js[$k] ?? null, JSON_UNESCAPED_UNICODE);
    check('brouillons (JS) : compte connecté, ceux des autres comptes et ceux sans compte (1.10.2) effacés, « eclair-mode » gardé',
        ($js['signedIn'] ?? null) === ['cle-du-site', $other, $mine, 'eclair-mode'], $show('signedIn'));
    check('brouillons (JS) : page de connexion après déconnexion, plus aucun brouillon de ce site, préférences et autre site gardés', ($js['afterLogout'] ?? null) === ['cle-du-site', $other, 'eclair-mode'], $show('afterLogout'));
    check('brouillons (JS) : session expirée, rien d’effacé (le compte peut revenir les retrouver)', count((array) ($js['expired'] ?? [])) === 7, $show('expired'));
    check('brouillons (JS) : déconnexion, tous les brouillons de ce site effacés, ceux d’un autre site Éclair à la même adresse gardés', ($js['logout'] ?? null) === ['cle-du-site', $other, 'eclair-mode'], $show('logout'));
    check('brouillons (JS) : site à un seul compte, brouillons de la 1.10.2 repris par ce compte (rien de perdu), ceux d’un autre compte effacés',
        ($js['solo'] ?? null) === [['cle-du-site', $other, $mine, $site . 'aaaaaaaaaa-new-page', 'eclair-mode'], 'aaaaaaaaaa', 'ancien format, nouvelle page', 'de A'], $show('solo'));
    check('brouillons (JS) : clé propre au site et au compte, aucune sans compte connecté', ($js['keys'] ?? null) === [$mine, $site . 'aaaaaaaaaa-new-page', ''], $show('keys'));
    check('brouillons (JS) : seule une copie écrite par ce compte est proposée', ($js['author'] ?? null) === ['texte de A', null, null], $show('author'));
    check('brouillons (JS) : un autre compte du même navigateur ne se voit rien proposer', ($js['otherAccount'] ?? null) === [null, null, []], $show('otherAccount'));
    check('« Autoriser » (JS) : désactivé au chargement', ($js['gateLoad'] ?? null) === true, $show('gateLoad'));
    check('« Autoriser » (JS) : actif seulement après un court délai puis un geste', ($js['gateDelay'] ?? null) === [true, false] && ($js['gateClick'] ?? null) === true,
        $show('gateDelay') . ' ' . $show('gateClick'));
    check('« Autoriser » (JS) : fenêtre amenée sous un double-clic, le second clic n’autorise rien', ($js['gateDoubleClick'] ?? null) === [true, true, false], $show('gateDoubleClick'));
    check('« Autoriser » (JS) : le geste qui active le bouton n’autorise pas à lui seul', ($js['gateFirstTap'] ?? null) === [false, true], $show('gateFirstTap'));
    check('« Autoriser » (JS) : clavier permis, désactivé en arrière-plan puis délai à nouveau', ($js['gateKeyboard'] ?? null) === [false, true]
        && ($js['gateBackground'] ?? null) === [true, true, true, true, true], $show('gateKeyboard') . ' ' . $show('gateBackground'));
    check('« Autoriser » (JS) : Entrée dans le mot de passe autorise, jamais « Refuser » par défaut', ($js['gateEnter'] ?? null) === [[1, true], [0, true]], $show('gateEnter'));
    check('assistant (JS) : « /\\hôte » n’est pas un lien du site', ($js['links'] ?? null) === [true, true, true, false, false, false], $show('links'));
}

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}

/** Copie d'Éclair sur le serveur intégré, avec des réglages PHP en plus (faux sendmail). */
function c2_serve(string $name, array $ini): array
{
    $copy = sys_get_temp_dir() . '/eclair-it-' . $name . '-' . getmypid();
    exec('rm -rf ' . escapeshellarg($copy) . ' && cp -r ' . escapeshellarg(ECLAIR_ROOT) . ' ' . escapeshellarg($copy));
    foreach (['data/*.php', 'data/*/', 'cache/*', 'media/20*', 'media/site'] as $glob) {
        exec('rm -rf ' . $copy . '/' . $glob);
    }
    [$port, $server] = start_server(array_merge($ini, ['-t', $copy, dirname(__DIR__) . '/tools/router.php']), ['PHP_CLI_SERVER_WORKERS' => '4']);
    return [$copy, $port, $server];
}

section('Sécurité 1.10.3 (HTTP) : formulaire de contact, consentement MCP, brouillons, tableau de bord');
$mailDir = sys_get_temp_dir() . '/eclair-c2-mail-http-' . getmypid();
[$copy, $port, $server] = c2_serve('c2', ['-d', 'sendmail_path=' . c2_sendmail($mailDir)]);
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Atelier C2', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'business',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $mails = static fn (): array => glob($mailDir . '/mail.*') ?: [];

    // Formulaire de contact : jeton public de la page Contact, comme n'importe quel visiteur.
    $contactPage = client($port)->req('GET', '/contact');
    preg_match('/data-k="([^"]+)"/', $contactPage['body'], $k);
    preg_match('/name="page" value="([a-z0-9]*)"/', $contactPage['body'], $pid);
    $send = static fn (string $email): array => client($port)->req('POST', '/_eclair/contact', ['name' => 'Camille Test', 'email' => $email,
        'message' => 'Bonjour, ceci est un message de test.', 'website' => '', 'k' => $k[1] ?? '', 'page' => $pid[1] ?? ''], ['Accept: application/json']);
    $evil = $send($escapedLf);
    $stored = implode('', array_map('file_get_contents', glob($copy . '/data/messages/*.php') ?: []));
    check('contact : adresse avec saut de ligne échappé refusée (422), ni e-mail ni message gardé', $evil['status'] === 422 && $mails() === [] && !str_contains($stored, 'X-Probe'),
        $evil['status'] . ' ' . count($mails()) . ' ' . substr($evil['body'], 0, 120));
    $good = $send('Camille.Test+contact@example.org');
    $sent = $mails() ? (string) file_get_contents($mails()[0]) : '';
    // En-têtes tels qu'un serveur de messagerie les lit : une ligne par saut de ligne (LF).
    $head = array_map(static fn (string $l): string => rtrim($l, "\r"), explode("\n", (string) preg_split('/\r?\n\r?\n/', $sent, 2)[0]));
    check('contact : adresse ordinaire, un e-mail avec une seule ligne Reply-To', $good['status'] === 200 && count($mails()) === 1
        && in_array('Reply-To: Camille.Test+contact@example.org', $head, true) && !preg_grep('/^X-Probe:/', $head), json_encode($head));

    // Connecteur MCP : activé par l'administratrice, connexion récente (moins de 15 minutes).
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $page = $c->req('GET', '/gestion/settings?tab=ai');
    $c->req('POST', '/gestion/settings', $c->token($page['body']) + ['tab' => 'ai', 'provider' => '', 'budget' => '10', 'daily' => '60', 'editors' => '1', 'mcp' => '1']);
    $reg = static fn (array $uris): array => client($port)->req('POST', '/_eclair/oauth/register', (string) json_encode(['client_name' => 'Outil', 'redirect_uris' => $uris]), ['Content-Type: application/json']);
    $cid = static fn (array $res): string => (string) (json_decode($res['body'], true)['client_id'] ?? '');
    $unknown = $cid($reg(['https://client.example/cb']));
    $claude = $cid($reg(['https://claude.ai/api/mcp/auth_callback']));
    $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $auth = static fn (string $id, string $uri): string => '/_eclair/oauth/authorize?' . http_build_query(['response_type' => 'code', 'client_id' => $id,
        'redirect_uri' => $uri, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'etat-c2']);
    $u = $auth($unknown, 'https://client.example/cb');
    $consent = $c->req('GET', $u);
    check('application inconnue, connexion récente : mot de passe demandé quand même', $consent['status'] === 200 && str_contains($consent['body'], 'Connecter client.example à ton site')
        && str_contains($consent['body'], 'name="confirm_password"'), substr(strip_tags($consent['body']), 0, 300));
    $oneClick = $c->req('POST', $u, $c->token($consent['body']) + ['step' => 'allow']);
    $wrong = $c->req('POST', $u, $c->token($consent['body']) + ['step' => 'allow', 'confirm_password' => 'pas-le-bon']);
    check('application inconnue : un clic sans le bon mot de passe n’envoie aucun code', $oneClick['status'] === 401 && !isset($oneClick['headers']['location'])
        && $wrong['status'] === 401 && !isset($wrong['headers']['location']), $oneClick['status'] . ' ' . ($oneClick['headers']['location'] ?? ''));
    $right = $c->req('POST', $u, $c->token($consent['body']) + ['step' => 'allow', 'confirm_password' => 'Un-mot-de-passe-solide']);
    check('application inconnue : accord avec le mot de passe, code envoyé', $right['status'] === 303 && str_starts_with($right['headers']['location'] ?? '', 'https://client.example/cb?code='));
    parse_str((string) parse_url($right['headers']['location'] ?? '', PHP_URL_QUERY), $back);
    $tok = (array) json_decode(client($port)->req('POST', '/_eclair/oauth/token', ['grant_type' => 'authorization_code', 'code' => $back['code'] ?? '', 'client_id' => $unknown,
        'redirect_uri' => 'https://client.example/cb', 'code_verifier' => $verifier])['body'], true);
    $search = static fn (string $q): array => (array) (json_decode(client($port)->req('POST', '/_eclair/mcp', (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'search_content', 'arguments' => ['query' => $q]]]), ['Content-Type: application/json', 'Authorization: Bearer ' . ($tok['access_token'] ?? '')])['body'], true)['result'] ?? []);
    $long = $search(str_repeat('aa ', 67) . 'aa');
    $short = $search('céramique atelier');
    check('MCP : recherche démesurée refusée avec un message clair, recherche ordinaire servie', ($long['isError'] ?? null) === true
        && str_contains((string) ($long['content'][0]['text'] ?? ''), 'at most 200 characters') && ($short['isError'] ?? null) === false, json_encode([$long, $short]));
    $deny = $c->req('POST', $u, $c->token($consent['body']) + ['step' => 'deny']);
    check('application inconnue : refus sans mot de passe à saisir', str_contains($deny['headers']['location'] ?? '', 'error=access_denied')
        && (bool) preg_match('~value="deny"\s+formnovalidate~', $consent['body']));
    $csp = $consent['headers']['content-security-policy'] ?? '';
    check('consentement : « Autoriser » désactivé au chargement, consent.js du site, CSP inchangée', (bool) preg_match('~<button[^>]*value="allow"[^>]*\sdisabled[^>]*\sdata-gate~', $consent['body'])
        && (bool) preg_match('~<script src="/assets/admin/consent\.js\?v=\w+" defer></script>~', $consent['body']) && str_contains($csp, "script-src 'self';")
        && !str_contains($csp, 'unsafe') && str_contains($csp, "frame-ancestors 'none'") && client($port)->req('GET', '/assets/admin/consent.js')['status'] === 200, $csp);
    $kc = $c->req('GET', $auth($claude, 'https://claude.ai/api/mcp/auth_callback'));
    check('Claude, connexion récente : pas de mot de passe (inchangé), mais avertissement « lancée par toi »', $kc['status'] === 200 && !str_contains($kc['body'], 'name="confirm_password"')
        && str_contains($kc['body'], 'N’accepte que si tu viens de lancer cette connexion toi-même depuis Claude.') && !str_contains($kc['body'], 'Application inconnue'));
    $ka = $c->req('POST', $auth($claude, 'https://claude.ai/api/mcp/auth_callback'), $c->token($kc['body']) + ['step' => 'allow']);
    check('Claude, connexion récente : accord en un clic (après le geste exigé par la page)', $ka['status'] === 303 && str_starts_with($ka['headers']['location'] ?? '', 'https://claude.ai/api/mcp/auth_callback?code='));
    check('enregistrement : adresse de retour terminée par un saut de ligne refusée', $reg(["https://claude.ai/api/mcp/auth_callback\n"])['status'] === 400
        && $reg(["https://client.example/cb\n"])['status'] === 400);
    $loop = $cid($reg(['http://127.0.0.1:4567/cb']));
    check('boucle locale : le port peut changer, dans la limite d’un vrai port', $c->req('GET', $auth($loop, 'http://127.0.0.1:5678/cb'))['status'] === 200
        && $c->req('GET', $auth($loop, 'http://127.0.0.1:99999999/cb'))['status'] === 400);

    // Brouillons de secours : le JavaScript connaît le compte connecté ; la déconnexion est repérable sans rien envoyer de plus.
    $users = json_decode(substr((string) file_get_contents($copy . '/data/users.php'), strlen(Store::GUARD)), true);
    $adminId = (string) array_search('camille@example.com', array_column($users, 'email', 'id'), true);
    $jsData = static fn (string $html): array => preg_match('~<script type="application/json" id="eclair-data">(.*?)</script>~s', $html, $m) ? (array) json_decode($m[1], true) : [];
    $dash = $c->req('GET', '/gestion');
    check('administration : compte connecté transmis au JavaScript (clé des brouillons)', $adminId !== '' && ($jsData($dash['body'])['me'] ?? null) === $adminId);
    check('déconnexion : formulaire repérable par le JavaScript', str_contains($dash['body'], '<form method="post" action="/gestion/logout" data-logout>'));
    check('tableau de bord d’un administrateur : santé du site', str_contains($dash['body'], 'class="health"') && str_contains($dash['body'], 'PHP ' . PHP_VERSION));
    $out = $c->req('POST', '/gestion/logout', $c->token($dash['body']));
    $bye = $c->req('GET', '/gestion/login?bye=1');
    check('déconnexion : sans Clear-Site-Data (préférences et stockage du site gardés), page suivante avec admin.js et sans compte',
        $out['status'] === 302 && !isset($out['headers']['clear-site-data']) && str_ends_with($out['headers']['location'] ?? '', '/gestion/login?bye=1')
        && str_contains($bye['body'], '/assets/admin/admin.js') && ($jsData($bye['body'])['me'] ?? null) === '');

    // Santé du site : version de PHP et administrateurs sans double authentification, pour les administrateurs seulement.
    $users['c2ed'] = ['id' => 'c2ed', 'name' => 'Rédac C2', 'email' => 'redac-c2@example.com', 'role' => 'editor', 'pass' => password_hash('Mot-de-passe-redac-c2', PASSWORD_DEFAULT),
        'ver' => 1, 'created' => time(), 'slug' => 'redac-c2', 'lang' => 'fr', 'must_change' => false, 'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []]];
    file_put_contents($copy . '/data/users.php', Store::GUARD . json_encode($users));
    $e = client($port);
    $e->login('redac-c2@example.com', 'Mot-de-passe-redac-c2');
    $ed = $e->req('GET', '/gestion');
    check('tableau de bord d’un rédacteur : ni version de PHP ni administrateurs sans double authentification', $ed['status'] === 200 && str_contains($ed['body'], 'Santé du site')
        && !str_contains($ed['body'], 'class="health"') && !str_contains($ed['body'], 'PHP ' . PHP_VERSION)
        && !str_contains($ed['body'], 'sans double authentification') && !str_contains($ed['body'], 'Double authentification active pour tous les admins')
        && ($jsData($ed['body'])['me'] ?? null) === 'c2ed', (string) $ed['status']);
} finally {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($mailDir));
}
