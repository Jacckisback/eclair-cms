<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité 1.10.3, connexion et sessions (inclus par tests/run.php) : limites de tentatives, places de
 * vérification du mot de passe, verrou des données, sessions, appareils de confiance, journal de sécurité.
 */
declare(strict_types=1);

use Eclair\Auth;
use Eclair\Keys;
use Eclair\Log;
use Eclair\RateLimit;
use Eclair\Security;
use Eclair\Session;
use Eclair\Store;
use Eclair\Totp;
use Eclair\Users;

// Portée propre : run.php et les autres fichiers de tests partagent leurs variables globales.
(static function (array $argv): void {
    /** Fichier du compteur d'une clé (même nom que RateLimit). */
    $limitFile = static fn (string $key, string $data = ECLAIR_DATA): string => $data . '/limits/' . substr(hash('sha256', $key), 0, 40) . '.php';
    $limitRow = static function (string $key, string $data = ECLAIR_DATA) use ($limitFile): ?array {
        $raw = @file_get_contents($limitFile($key, $data));
        $row = $raw === false ? null : json_decode(Store::strip($raw), true);
        return is_array($row) ? $row : null;
    };

    /* ─────────────────── Limites : au plus $max tentatives par fenêtre ─────────────────── */
    section('Sécurité 1.10.3 : limites de tentatives');
    // Le temps passe : on recule la fenêtre et le blocage du compteur d'autant (seuls les écarts comptent).
    $age = static function (string $key, int $seconds) use ($limitFile, $limitRow): void {
        $row = $limitRow($key);
        $row['t'] = (int) $row['t'] - $seconds;
        $row['b'] = (int) $row['b'] > 0 ? (int) $row['b'] - $seconds : 0;
        file_put_contents($limitFile($key), Store::GUARD . json_encode($row));
        Store::forget();
    };
    // Blocage plus court que la fenêtre (comme le quota du site pour le formulaire de contact) : 5 par 600 s, blocage 60 s.
    RateLimit::clear('unit-short');
    $taken = 0;
    for ($i = 0; $i < 6; $i++) {
        $taken += RateLimit::attempt('unit-short', 5, 600, 60, $fresh) ? 1 : 0;
    }
    check('quota épuisé : la 6e tentative pose le blocage', $taken === 5 && $fresh);
    $row = $limitRow('unit-short');
    check('blocage : la fenêtre épuisée est gardée, bloquée jusqu’à sa fin', is_array($row) && (int) $row['n'] === 5 && (int) $row['b'] >= (int) $row['t'] + 601, json_encode($row));
    $age('unit-short', 61);
    $again = 0;
    for ($i = 0; $i < 5; $i++) {
        $again += RateLimit::attempt('unit-short', 5, 600, 60) ? 1 : 0;
    }
    check('blocage court terminé, fenêtre en cours : aucun nouveau quota', $again === 0, $again . ' acceptées');
    $age('unit-short', 541);
    check('fenêtre terminée : nouveau quota', RateLimit::attempt('unit-short', 5, 600, 60));
    // Blocage égal à la fenêtre (connexion : 10 par 900 s) : rien n'est accepté à la seconde où le blocage finit.
    $second = static function () use ($limitFile, $limitRow): array {
        for ($try = 0; $try < 3; $try++) {
            RateLimit::clear('unit-login');
            for ($i = 0; $i < 11; $i++) {
                RateLimit::attempt('unit-login', 10, 900, 900);
            }
            $now = time();
            $row = $limitRow('unit-login');
            $shift = (int) $row['t'] - ($now - 900); // maintenant = début de la fenêtre + 900 s
            $row['t'] = (int) $row['t'] - $shift;
            $row['b'] = (int) $row['b'] - $shift;
            file_put_contents($limitFile('unit-login'), Store::GUARD . json_encode($row));
            Store::forget();
            $accepted = 0;
            for ($i = 0; $i < 10; $i++) {
                $accepted += RateLimit::attempt('unit-login', 10, 900, 900) ? 1 : 0;
            }
            if (time() === $now) {
                return [$accepted, $row];
            }
        }
        return [-1, []];
    };
    [$accepted, $row] = $second();
    check('blocage égal à la fenêtre : pas de second quota dans sa dernière seconde', $accepted === 0, $accepted . ' acceptées ' . json_encode($row));
    RateLimit::clear('unit-login');

    RateLimit::clear('unit-release');
    RateLimit::attempt('unit-release', 5, 600);
    RateLimit::release('unit-release');
    check('tentative rendue : aucun fichier ne reste (connexion réussie ou « busy »)', !is_file($limitFile('unit-release')));
    for ($i = 0; $i < 3; $i++) {
        RateLimit::attempt('unit-release', 2, 600, 600);
    }
    RateLimit::release('unit-release');
    check('clé bloquée : rendre une tentative ne lève pas le blocage', RateLimit::blocked('unit-release') > 0 && is_file($limitFile('unit-release')));
    RateLimit::clear('unit-release');

    // Compteur par compte : des groupes signés, jamais un fichier par e-mail saisi.
    $limits = new ReflectionMethod(Auth::class, 'limits');
    unset($_COOKIE['eclair_dev']);
    $groups = [];
    for ($i = 0; $i < 5000; $i++) {
        foreach (array_keys($limits->invoke(null, 'inconnu-' . $i . '-' . bin2hex(random_bytes(3)) . '@example.invalid', null)) as $key) {
            if (str_starts_with($key, 'login-acct:')) {
                $groups[$key] = true;
            }
        }
    }
    check('5 000 e-mails inventés : au plus 4 096 compteurs de compte', count($groups) <= 4096 && count($groups) > 1000, (string) count($groups));
    $real = Users::create(['name' => 'Compte Groupe', 'email' => 'groupe@example.com', 'pass' => Security::hashPassword('Mot-de-passe-du-groupe'), 'role' => 'editor']);
    $known = array_keys($limits->invoke(null, 'groupe@example.com', $real));
    $unknown = array_keys($limits->invoke(null, 'personne@example.com', null));
    check('compte existant ou non : mêmes compteurs (aucune énumération)', count($known) === 2 && count($unknown) === 2
        && (bool) preg_match('/^login-acct:[a-f0-9]{3}$/', $known[1]) && (bool) preg_match('/^login-acct:[a-f0-9]{3}$/', $unknown[1])
        && $known[1] === Auth::accountKey(' Groupe@Example.com '), json_encode([$known, $unknown]));
    Users::delete($real['id']);

    // Page Sécurité : dossier lu au fil de l'eau, rien gardé en mémoire, liste bornée.
    Store::ensureDir(ECLAIR_DATA . '/limits');
    $made = [];
    for ($i = 0; $i < 3000; $i++) {
        $key = 'login-acct:bruit-' . $i . '-' . str_repeat('x', 200);
        file_put_contents($made[] = $limitFile($key), Store::GUARD . json_encode(['k' => $key, 'n' => 1, 't' => time(), 'b' => 0]));
    }
    for ($i = 0; $i < 150; $i++) {
        $key = 'login-ip:192.0.2.' . $i;
        file_put_contents($made[] = $limitFile($key), Store::GUARD . json_encode(['k' => $key, 'n' => 10, 't' => time(), 'b' => time() + 900]));
    }
    Store::forget();
    $memo = new ReflectionProperty(Store::class, 'memo');
    $before = memory_get_usage();
    $active = RateLimit::active();
    $kept = memory_get_usage() - $before;
    check('page Sécurité : clés bloquées listées, 100 au plus', count($active) === 100
        && count(array_filter($active, static fn (array $b): bool => str_starts_with($b['key'], 'login-ip:192.0.2.'))) >= 99, (string) count($active));
    check('page Sécurité : aucun compteur gardé en mémoire', !array_filter(array_keys((array) $memo->getValue()), static fn ($n): bool => str_starts_with((string) $n, 'limits/'))
        && $kept < 200000, $kept . ' octets');
    foreach ($made as $file) {
        @unlink($file);
    }

    /* ─────────────────── Places de vérification du mot de passe ─────────────────── */
    section('Sécurité 1.10.3 : places de vérification du mot de passe');
    $slot = new ReflectionMethod(Auth::class, 'slot');
    $hold = static function (array $ids): array {
        $held = [];
        foreach ($ids as $i) {
            $h = fopen(ECLAIR_DATA . '/.login-' . $i . '.lock', 'c');
            flock($h, LOCK_EX);
            $held[] = $h;
        }
        return $held;
    };
    $free = static function (array $held): void {
        foreach ($held as $h) {
            flock($h, LOCK_UN);
            fclose($h);
        }
    };
    $held = $hold([0, 1]);
    $started = microtime(true);
    $anon = $slot->invoke(null, false, 0.3);
    check('deux places occupées : un inconnu attend puis reçoit « busy »', $anon === null && microtime(true) - $started >= 0.25);
    $trusted = $slot->invoke(null, true, 0.3);
    check('deux places occupées : un appareil de confiance a sa place réservée', is_resource($trusted));
    $third = $slot->invoke(null, true, 0.2);
    check('place réservée prise elle aussi : refus', $third === null);
    if (is_resource($trusted)) {
        flock($trusted, LOCK_UN);
        fclose($trusted);
    }
    $free($held);
    // Fichier de place impossible à ouvrir (ici un dossier non vide à sa place) : jamais de place sans verrou.
    $broken = static function (array $ids, bool $empty): void {
        foreach ($ids as $i) {
            $f = ECLAIR_DATA . '/.login-' . $i . '.lock';
            @unlink($f);
            @mkdir($f);
            if (!$empty) {
                touch($f . '/x');
            }
        }
    };
    $restore = static function (array $ids): void {
        foreach ($ids as $i) {
            $f = ECLAIR_DATA . '/.login-' . $i . '.lock';
            @unlink($f . '/x');
            is_dir($f) ? @rmdir($f) : null;
        }
    };
    $broken([0, 1], false);
    $started = microtime(true);
    $none = $slot->invoke(null, false, 2.0);
    check('places impossibles à ouvrir : « busy » aussitôt, jamais de place sans verrou', $none === null && microtime(true) - $started < 1.0);
    $slot->invoke(null, false, 2.0);
    $alerts = array_filter(Log::read('errors', 50), static fn (array $r): bool => ($r['id'] ?? '') === 'login-slots');
    check('places impossibles à ouvrir : signalé une seule fois dans le journal', count($alerts) === 1, (string) count($alerts));
    $restore([0, 1]);
    $broken([0], true);
    $broken([1], false);
    $one = $slot->invoke(null, false, 0.3);
    check('place 0 réparable (dossier vide) : recréée et utilisée', is_resource($one) && is_file(ECLAIR_DATA . '/.login-0.lock'));
    $two = $slot->invoke(null, false, 0.3);
    check('place 1 irréparable : la place 0 étant prise, « busy »', $two === null);
    if (is_resource($one)) {
        flock($one, LOCK_UN);
        fclose($one);
    }
    $restore([0, 1]);

    /* ─────────────────── Verrou des données ─────────────────── */
    section('Sécurité 1.10.3 : verrou des données');
    Store::locked(static fn (): bool => true);
    $probe = Users::create(['name' => 'Verrou Test', 'email' => 'verrou@example.com', 'pass' => 'x', 'role' => 'editor']);
    $lock = ECLAIR_DATA . '/.lock';
    @unlink($lock);
    mkdir($lock);
    touch($lock . '/x');
    $ran = false;
    $error = '';
    try {
        Store::locked(static function () use (&$ran): void {
            $ran = true;
        });
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
    check('verrou inutilisable : enregistrement refusé, rien n’est exécuté sans verrou', !$ran && str_contains($error, 'data/.lock'), $error);
    $called = false;
    try {
        Users::update($probe['id'], static function (array $u) use (&$called): array {
            $called = true;
            return $u;
        });
    } catch (RuntimeException) {
    }
    $limited = null;
    try {
        $limited = RateLimit::attempt('unit-lock', 3, 600);
    } catch (RuntimeException) {
    }
    check('verrou inutilisable : comptes et limites non modifiés', !$called && $limited === null);
    Store::forget();
    check('verrou inutilisable : la lecture (site public) fonctionne toujours', Users::find($probe['id']) !== null);
    unlink($lock . '/x');
    rmdir($lock);
    mkdir($lock);
    $ran = false;
    Store::locked(static function () use (&$ran): void {
        $ran = true;
    });
    check('verrou remplacé par un dossier vide : recréé, l’enregistrement passe', $ran && is_file($lock));
    Users::delete($probe['id']);

    /* ─────────────────── Sessions ─────────────────── */
    section('Sécurité 1.10.3 : sessions');
    $handler = new Session();
    $sessionPath = static fn (string $id): string => ECLAIR_DATA . '/sessions/' . hash('sha256', $id) . '.php';
    Store::ensureDir(ECLAIR_DATA . '/sessions');
    $sid = bin2hex(random_bytes(16));
    file_put_contents($sessionPath($sid), Store::GUARD . 'uid|s:3:"abc";');
    $handler->read($sid);
    unlink($sessionPath($sid)); // déconnexion par une requête concurrente
    $handler->write($sid, 'uid|s:3:"abc";last|i:1;');
    check('session fermée pendant une requête : jamais recréée par son écriture', !is_file($sessionPath($sid)));
    $handler->updateTimestamp($sid, '');
    check('session fermée : la mise à jour de date ne la recrée pas', !is_file($sessionPath($sid)));
    $new = bin2hex(random_bytes(16));
    $handler->read($new);
    $handler->write($new, 'uid|s:3:"abc";');
    check('nouvelle session (connexion) : bien enregistrée', is_file($sessionPath($new)) && str_contains((string) file_get_contents($sessionPath($new)), 'uid|s:3:"abc";'));
    $handler->write($new, 'uid|s:3:"abc";last|i:2;');
    check('session existante : écriture ordinaire', str_contains((string) file_get_contents($sessionPath($new)), 'last|i:2;'));
    $handler->destroy($new);
    $empty = bin2hex(random_bytes(16));
    $handler->read($empty);
    $handler->write($empty, '');
    check('session détruite et visiteur anonyme : aucun fichier', !is_file($sessionPath($new)) && !is_file($sessionPath($empty)));

    /* ─────────────────── Appareils de confiance ─────────────────── */
    section('Sécurité 1.10.3 : appareils de confiance');
    $owner = Users::create(['name' => 'Appareil Test', 'email' => 'appareil@example.com', 'pass' => Security::hashPassword('Mot-de-passe-appareil-1'), 'role' => 'admin']);
    $device = bin2hex(random_bytes(16));
    $cookie = static fn (array $u, string $id): string => $id . '.' . substr(Keys::sign('device|' . $u['id'] . '|' . (int) ($u['ver'] ?? 1) . '|' . hash('sha256', (string) $u['pass']) . '|' . $id), 0, 32);
    $trustedKey = static function (array $u, string $value) use ($limits): bool {
        $_COOKIE['eclair_dev'] = $value;
        $keys = array_keys($limits->invoke(null, (string) $u['email'], $u));
        unset($_COOKIE['eclair_dev']);
        return count($keys) === 1 && str_starts_with($keys[0], 'login-dev:');
    };
    check('cookie d’appareil valide : son propre compteur', $trustedKey($owner, $cookie($owner, $device)));
    $legacy = $device . '.' . substr(Keys::sign('device|' . $owner['id'] . '|' . $device), 0, 32);
    check('cookie d’appareil de la 1.10.2 : plus reconnu (l’appareil n’est simplement plus de confiance)', !$trustedKey($owner, $legacy));
    $revoked = Auth::revokeAll((string) $owner['id']);
    check('« Déconnecter mes autres appareils » : appareils oubliés', !$trustedKey($revoked, $cookie($owner, $device)) && $trustedKey($revoked, $cookie($revoked, $device)));
    $changed = Users::update((string) $owner['id'], static fn (array $u): array => ['pass' => Security::hashPassword('Mot-de-passe-appareil-2')] + $u);
    check('nouveau mot de passe : appareils oubliés', !$trustedKey($changed, $cookie($revoked, $device)));
    // Connexion lente pendant l'activation de la 2FA : refusée (elle repassera par le code).
    $stale = $changed;
    Users::update((string) $owner['id'], static fn (array $u): array => ['totp' => ['on' => true, 'secret' => Totp::secret(), 'last' => 0, 'recovery' => []]] + $u);
    check('2FA activée pendant une connexion : la connexion sans code est refusée', Auth::login($stale) === false);
    Users::delete((string) $owner['id']);

    /* ─────────────────── Comptes revérifiés sous verrou ─────────────────── */
    section('Sécurité 1.10.3 : comptes revérifiés sous verrou');
    $one = Users::create(['name' => 'Admin Un', 'email' => 'admin-un@example.com', 'pass' => 'x', 'role' => 'admin']);
    $two = Users::create(['name' => 'Admin Deux', 'email' => 'admin-deux@example.com', 'pass' => 'x', 'role' => 'admin']);
    check('création : e-mail déjà pris (casse et espaces compris) refusé sous verrou', Users::create(['name' => 'Doublon', 'email' => ' Admin-Un@Example.com', 'pass' => 'x']) === null
        && count(array_filter(Users::all(), static fn (array $u): bool => mb_strtolower(trim((string) $u['email'])) === 'admin-un@example.com')) === 1);
    check('suppressions croisées : la première passe', Users::delete((string) $two['id'], (string) $one['id']) === true);
    check('suppressions croisées : la seconde est refusée, le site garde un administrateur', Users::delete((string) $one['id'], (string) $two['id']) === false
        && Users::find((string) $one['id']) !== null);
    check('suppression de son propre compte : refusée sous verrou', Users::delete((string) $one['id'], (string) $one['id']) === false);
    Users::delete((string) $one['id']);

    /* ─────────────────── Secours FTP ─────────────────── */
    section('Sécurité 1.10.3 : secours par FTP');
    $reset = new ReflectionMethod(Auth::class, 'resetFromFile');
    $victim = Users::create(['name' => 'Secours Test', 'email' => 'secours@example.com', 'pass' => Security::hashPassword('Ancien-mot-de-passe-77'), 'role' => 'admin']);
    $resetFile = ECLAIR_DATA . '/reset.php';
    file_put_contents($resetFile, "<?php exit; ?>\nsecours@example.com\nNouveau-Secours-Valide-2026\n");
    touch($resetFile, time() + 3 * 86400);
    $reset->invoke(null, 'secours@example.com', 'Nouveau-Secours-Valide-2026');
    clearstatcache();
    check('fichier de secours daté dans le futur : refusé et supprimé', !is_file($resetFile)
        && password_verify('Ancien-mot-de-passe-77', (string) Users::find($victim['id'])['pass']));
    file_put_contents($resetFile, "<?php exit; ?>\nsecours@example.com\nYourNewPassword2026\n");
    $reset->invoke(null, 'secours@example.com', 'YourNewPassword2026');
    check('mot de passe d’exemple des guides : refusé', !is_file($resetFile) && password_verify('Ancien-mot-de-passe-77', (string) Users::find($victim['id'])['pass']));
    file_put_contents($resetFile, "<?php exit; ?>\nsecours@example.com\nNouveau-Secours-Valide-2026\n");
    $reset->invoke(null, 'secours@example.com', 'Nouveau-Secours-Valide-2026');
    check('secours valide : toujours appliqué', !is_file($resetFile) && password_verify('Nouveau-Secours-Valide-2026', (string) Users::find($victim['id'])['pass']));

    // Mot de passe d'une personne connectée (confirmation, profil) : limité comme une connexion.
    $checks = [];
    for ($i = 0; $i < 11; $i++) {
        $checks[] = Auth::checkPassword(Users::find($victim['id']), 'pas-le-bon-' . $i);
    }
    check('mot de passe d’une personne connectée : 10 essais faux, puis refus', count(array_keys($checks, 'invalid', true)) === 10 && $checks[10] === 'locked', implode(',', $checks));
    RateLimit::clear('sudo:' . $victim['id']);
    check('mot de passe d’une personne connectée : le bon est accepté', Auth::checkPassword(Users::find($victim['id']), 'Nouveau-Secours-Valide-2026') === 'ok');
    Users::delete($victim['id']);

    /* ─────────────────── Journal de sécurité ─────────────────── */
    section('Sécurité 1.10.3 : journal de sécurité');
    $logs = ECLAIR_DATA . '/logs';
    foreach (glob($logs . '/*') ?: [] as $file) {
        @unlink($file);
    }
    $_SERVER['REMOTE_ADDR'] = '192.0.2.200';
    Log::security('login', ['email' => 'proprietaire@example.com']);
    $_SERVER['HTTP_USER_AGENT'] = str_repeat('<', 300);
    for ($i = 1; $i <= 400; $i++) {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.' . ($i % 200 + 1);
        Log::security($i % 11 === 0 ? 'login_locked' : 'login_failed', ['email' => str_repeat('<', 246) . sprintf('%08d', $i)]);
    }
    unset($_SERVER['HTTP_USER_AGENT']);
    $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
    $events = Log::read('security', 80);
    check('400 échecs anonymes : la connexion du propriétaire reste dans le journal', (bool) array_filter($events, static fn (array $r): bool => $r['event'] === 'login' && $r['user'] === 'proprietaire@example.com'));
    check('échecs anonymes : à part, le journal de sécurité ne grossit pas', filesize($logs . '/security.php') < 4096, (string) filesize($logs . '/security.php'));
    $failures = Log::read(Log::FAILURES, 20);
    $last = $failures[0] ?? [];
    check('échecs anonymes : les plus récents restent lisibles', count($failures) === 20 && in_array($last['event'] ?? '', ['login_failed', 'login_locked'], true));
    check('échecs anonymes : e-mail (une seule fois) et navigateur tronqués', mb_strlen((string) ($last['user'] ?? '')) <= 100 && !isset($last['email'])
        && mb_strlen((string) ($last['ua'] ?? '')) <= 100, json_encode(array_map('strlen', array_map('strval', $last))));
    clearstatcache();
    check('échecs anonymes : fichier borné, segment précédent gardé', filesize($logs . '/login-failures.php') < 270000 && is_file($logs . '/login-failures-1.php')
        && str_starts_with((string) file_get_contents($logs . '/login-failures-1.php'), Store::GUARD), (string) filesize($logs . '/login-failures.php'));
    // Rotation du journal de sécurité lui-même : l'ancien contenu passe dans le segment précédent, rien n'est jeté.
    Log::security('install', ['email' => 'premier-evenement@example.com']);
    for ($i = 0; $i < 3000; $i++) {
        Log::security('settings_saved', ['tab' => str_repeat('r', 290)]);
    }
    clearstatcache();
    check('journal plein : le premier événement est gardé dans le segment précédent', is_file($logs . '/security-1.php')
        && str_contains((string) file_get_contents($logs . '/security-1.php'), 'premier-evenement@example.com') && filesize($logs . '/security.php') < 1048576 + 2048);
    Log::security('test', ['email' => 'apres-rotation@example.com']);
    check('journal après rotation : ligne de protection, ajouts suivants', str_starts_with((string) file_get_contents($logs . '/security.php'), Store::GUARD)
        && (Log::read('security', 1)[0]['user'] ?? '') === 'apres-rotation@example.com');

    /* ─────────────────────────── Intégration HTTP ─────────────────────────── */
    if (in_array('--unit', $argv, true)) {
        return;
    }
    section('Sécurité 1.10.3 : connexion, sessions et appareils (HTTP)');
    [$copy, $port, $server] = serve('sec-a');
    $servers = [$server];
    try {
        $pass = 'Un-mot-de-passe-solide';
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Sécurité', 'site_tagline' => 'Tests', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => $pass, 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $jarValue = static fn (object $cl, string $name): string => preg_match('/\t' . $name . '\t(\S+)/', (string) file_get_contents($cl->jar), $m) ? $m[1] : '';
        $sessionFile = static fn (object $cl): string => $copy . '/data/sessions/' . hash('sha256', $jarValue($cl, 'eclair_sid')) . '.php';
        $stamp = static fn (object $cl, string $key): int => preg_match('/' . $key . '\|i:(\d+);/', (string) @file_get_contents($sessionFile($cl)), $m) ? (int) $m[1] : -1;
        $setStamps = static function (object $cl, array $stamps) use ($sessionFile): void {
            $raw = (string) file_get_contents($sessionFile($cl));
            foreach ($stamps as $key => $value) {
                $raw = (string) preg_replace('/' . $key . '\|i:\d+;/', $key . '|i:' . $value . ';', $raw);
            }
            file_put_contents($sessionFile($cl), $raw);
        };
        $post = static function (object $cl, string $page, string $action, array $fields = []): array {
            return $cl->req('POST', '/gestion/' . $action, $cl->token($cl->req('GET', '/gestion/' . $page)['body']) + $fields);
        };
        $users = static fn (): array => json_decode(Store::strip((string) file_get_contents($copy . '/data/users.php')), true) ?: [];
        $copyRow = static fn (string $key): ?array => $limitRow($key, $copy . '/data');
        $signedIn = static fn (object $cl): bool => $cl->req('GET', '/gestion')['status'] === 200;

        // Déconnecter les autres appareils : ni la confirmation du mot de passe ni la durée de session ne repartent à zéro.
        $a = client($port);
        $b = client($port);
        $a->login('camille@example.com', $pass);
        $b->login('camille@example.com', $pass);
        $deviceA = explode('.', $jarValue($a, 'eclair_dev'))[0];
        $deviceB = explode('.', $jarValue($b, 'eclair_dev'))[0];
        $now = time();
        $setStamps($a, ['sudo' => $now - 1000, 'born' => $now - 100000]);
        $revoke = $post($a, 'profile', 'profile', ['action' => 'revoke']);
        check('« Déconnecter mes autres appareils » : autres sessions fermées, la sienne gardée', $revoke['status'] === 302 && !$signedIn($b) && $signedIn($a));
        check('« Déconnecter mes autres appareils » : confirmation du mot de passe et âge de la session inchangés',
            abs($stamp($a, 'sudo') - ($now - 1000)) <= 2 && abs($stamp($a, 'born') - ($now - 100000)) <= 2, $stamp($a, 'sudo') - $now . ' / ' . ($stamp($a, 'born') - $now));
        $page = $a->req('GET', '/gestion/users');
        $a->req('POST', '/gestion/users', $a->token($page['body']) + ['name' => 'Sonde Admin', 'email' => 'sonde@example.com', 'role' => 'admin']);
        check('après « Déconnecter mes autres appareils » : action sensible toujours refusée sans le mot de passe', str_contains($page['body'], 'name="confirm_password"')
            && !str_contains(json_encode($users()), 'sonde@example.com'));
        $b->login('camille@example.com', $pass);
        $deviceB = explode('.', $jarValue($b, 'eclair_dev'))[0];
        $revokeAll = $post($a, 'security', 'security', ['action' => 'revoke-all']);
        $backup = $post($a, 'security', 'backup');
        check('« Déconnecter tout le monde » : autres sessions fermées, confirmation et âge inchangés', $revokeAll['status'] === 302 && !$signedIn($b) && $signedIn($a)
            && abs($stamp($a, 'sudo') - ($now - 1000)) <= 2 && abs($stamp($a, 'born') - ($now - 100000)) <= 2);
        check('après « Déconnecter tout le monde » : sauvegarde refusée sans le mot de passe', !str_contains($backup['headers']['content-type'] ?? '', 'zip'));

        // Appareils de confiance : oubliés avec les sessions ; le navigateur qui agit garde le sien.
        $ipRow = static fn (): int => (int) ($copyRow('login-ip:127.0.0.1')['n'] ?? 0);
        $before = $ipRow();
        $b->login('camille@example.com', 'pas-le-bon-1');
        check('après « Déconnecter mes autres appareils » : l’appareil de l’autre navigateur n’est plus de confiance',
            $copyRow('login-dev:' . $deviceB) === null && $ipRow() === $before + 1, json_encode([$before, $ipRow(), $copyRow('login-dev:' . $deviceB)]));
        $a->req('POST', '/gestion/logout', $a->token($a->req('GET', '/gestion')['body']));
        $a->login('camille@example.com', 'pas-le-bon-2');
        check('le navigateur qui a déconnecté les autres reste un appareil de confiance', (int) ($copyRow('login-dev:' . $deviceA)['n'] ?? 0) === 1
            && $ipRow() === $before + 1 && explode('.', $jarValue($a, 'eclair_dev'))[0] === $deviceA);
        $a->login('camille@example.com', $pass);
        $b->login('camille@example.com', $pass);
        $deviceB = explode('.', $jarValue($b, 'eclair_dev'))[0];
        $newPass = 'Un-nouveau-mot-de-passe-solide';
        $post($a, 'profile', 'profile', ['action' => 'password', 'current' => $pass, 'password' => $newPass]);
        $before = $ipRow();
        $b->login('camille@example.com', 'pas-le-bon-3');
        check('nouveau mot de passe : l’appareil de l’autre navigateur n’est plus de confiance', $copyRow('login-dev:' . $deviceB) === null && $ipRow() === $before + 1);
        $a->req('POST', '/gestion/logout', $a->token($a->req('GET', '/gestion')['body']));
        $a->login('camille@example.com', 'pas-le-bon-4');
        check('nouveau mot de passe : le navigateur qui l’a changé reste un appareil de confiance', (int) ($copyRow('login-dev:' . $deviceA)['n'] ?? 0) === 2 && $ipRow() === $before + 1);
        $pass = $newPass;

        // Afflux de tentatives : les deux places partagées sont prises, l'appareil de confiance passe quand même.
        $held = [];
        foreach ([0, 1] as $i) {
            $held[$i] = fopen($copy . '/data/.login-' . $i . '.lock', 'c');
            flock($held[$i], LOCK_EX);
        }
        $start = microtime(true);
        $in = $a->login('camille@example.com', $pass);
        check('places partagées occupées : l’appareil de confiance se connecte', $in['status'] === 302 && microtime(true) - $start < 4, $in['status'] . ' ' . round(microtime(true) - $start, 1) . ' s');
        $stranger = client($port)->login('camille@example.com', $pass);
        check('places partagées occupées : un appareil inconnu reçoit « serveur très sollicité »', $stranger['status'] === 401 && str_contains($stranger['body'], 'très sollicité'));
        foreach ($held as $h) {
            flock($h, LOCK_UN);
            fclose($h);
        }

        // Page Sécurité : vrais événements et échecs récents ; compteur d'un compte nommé ; légère avec beaucoup de compteurs.
        $secret = (string) (json_decode(Store::strip((string) file_get_contents($copy . '/data/keys.php')), true)['secret'] ?? '');
        $acctKey = 'login-acct:' . substr(hash_hmac('sha256', 'login-acct|camille@example.com', $secret), 0, 3);
        file_put_contents($limitFile($acctKey, $copy . '/data'), Store::GUARD . json_encode(['k' => $acctKey, 'n' => 30, 't' => time(), 'b' => time() + 3600]));
        for ($i = 0; $i < 12000; $i++) {
            $key = 'login-ip:2001:db8:' . dechex($i) . '::/64';
            file_put_contents($limitFile($key, $copy . '/data'), Store::GUARD . '{"k":"' . $key . '","n":1,"t":' . time() . ',"b":0}');
        }
        [$port8, $server8] = start_server(['-d', 'memory_limit=8M', '-t', $copy, dirname(__DIR__) . '/tools/router.php']);
        $servers[] = $server8;
        $a8 = client($port8);
        $own = $a8->jar;
        $a8->jar = $a->jar; // même navigateur (les cookies ne dépendent pas du port)
        $security = $a8->req('GET', '/gestion/security');
        $a8->jar = $own;
        check('page Sécurité avec 12 000 compteurs (mémoire limitée à 8 Mo) : affichée', $security['status'] === 200 && str_contains($security['body'], 'Journal de sécurité'), (string) $security['status']);
        check('page Sécurité : compteur d’un compte bloqué nommé par son e-mail', str_contains($security['body'], e($acctKey . ' (camille@example.com)')));
        check('page Sécurité : connexions réussies et échecs anonymes affichés ensemble', str_contains($security['body'], 'Connexion réussie') && str_contains($security['body'], 'Échec de connexion'));
        exec('rm -rf ' . escapeshellarg($copy . '/data/limits'));

        // Verrou des données inutilisable : la connexion explique la cause, le site public reste en ligne ; réparé tout seul sinon.
        rename($copy . '/data/.lock', $copy . '/data/.lock-saved');
        mkdir($copy . '/data/.lock');
        touch($copy . '/data/.lock/x');
        $refused = client($port)->login('camille@example.com', $pass);
        check('verrou des données inutilisable : connexion refusée avec un message clair', $refused['status'] === 401 && str_contains($refused['body'], 'data/.lock'));
        check('verrou des données inutilisable : site public en ligne', client($port)->req('GET', '/')['status'] === 200);
        unlink($copy . '/data/.lock/x');
        $repaired = client($port)->login('camille@example.com', $pass);
        check('verrou remplacé par un dossier vide : recréé, connexion normale', $repaired['status'] === 302 && is_file($copy . '/data/.lock'));
        @unlink($copy . '/data/.lock-saved');

        // Double authentification : première activation inchangée sur une connexion récente ; jamais remplacée tant
        // qu'elle est active ; sa propre 2FA ne se désactive que depuis le profil (mot de passe actuel).
        $a->req('POST', '/gestion/logout', $a->token($a->req('GET', '/gestion')['body']));
        $a->login('camille@example.com', $pass);
        $code = static fn (string $secret, int $shift = 0): string => Totp::code($secret, intdiv(time(), 30) + $shift);
        $setup = static fn (): string => preg_match('/totp_setup\|s:\d+:"([A-Z2-7]+)";/', (string) file_get_contents($sessionFile($a)), $m) ? $m[1] : '';
        $me = static fn (): array => array_values(array_filter($users(), static fn (array $u): bool => $u['email'] === 'camille@example.com'))[0] ?? [];
        $post($a, 'profile', 'profile', ['action' => '2fa-start']);
        preg_match('~<code>([A-Z2-7 ]+)</code>~', $a->req('GET', '/gestion/profile')['body'], $m);
        $totpSecret = str_replace(' ', '', $m[1] ?? '');
        $post($a, 'profile', 'profile', ['action' => '2fa-confirm', 'code' => $code($totpSecret)]);
        check('première activation de la 2FA (connexion récente) : sans mot de passe, comme avant', $totpSecret !== ''
            && ($me()['totp']['secret'] ?? '') === $totpSecret && !empty($me()['totp']['on']));
        $post($a, 'profile', 'profile', ['action' => '2fa-start', 'confirm_password' => $pass]);
        $noSetup = $setup() === '';
        $other = Totp::secret();
        $raw = (string) file_get_contents($sessionFile($a));
        file_put_contents($sessionFile($a), preg_replace('/totp_setup\|s:\d+:"[A-Z2-7]*";/', '', $raw) . 'totp_setup|s:' . strlen($other) . ':"' . $other . '";');
        $post($a, 'profile', 'profile', ['action' => '2fa-confirm', 'code' => $code($other), 'confirm_password' => $pass]);
        check('2FA active : jamais remplacée, même avec le mot de passe (secret et codes de secours intacts)', $noSetup && ($me()['totp']['secret'] ?? '') === $totpSecret);
        $own = (string) ($me()['id'] ?? '');
        $post($a, 'users/' . $own, 'users/' . $own, ['action' => 'disable2fa', 'confirm_password' => $pass]);
        check('sa propre 2FA : jamais désactivée depuis la gestion des comptes, bouton absent', !empty($me()['totp']['on'])
            && !str_contains($a->req('GET', '/gestion/users/' . $own)['body'], 'value="disable2fa"'));
        $post($a, 'profile', 'profile', ['action' => '2fa-disable', 'current' => $pass]);
        check('2FA désactivée depuis le profil avec le mot de passe actuel', empty($me()['totp']['on']));
        // Connexion de plus de 15 minutes : l'activation redemande le mot de passe ; les codes faux sont limités.
        $setStamps($a, ['sudo' => time() - 1000]);
        $post($a, 'profile', 'profile', ['action' => '2fa-start']);
        $secret2 = $setup();
        $post($a, 'profile', 'profile', ['action' => '2fa-confirm', 'code' => $code($secret2)]);
        check('connexion ancienne : activation refusée sans le mot de passe, champ affiché', $secret2 !== '' && empty($me()['totp']['on'])
            && str_contains($a->req('GET', '/gestion/profile')['body'], 'name="confirm_password"'));
        $valid = [$code($secret2, -1), $code($secret2), $code($secret2, 1)];
        $bad = '000000';
        for ($n = 0; in_array($bad, $valid, true); $n++) {
            $bad = sprintf('%06d', $n + 1);
        }
        $wrong = [];
        for ($i = 0; $i < 11; $i++) {
            $post($a, 'profile', 'profile', ['action' => '2fa-confirm', 'code' => $bad, 'confirm_password' => $pass]);
            $wrong[] = $a->req('GET', '/gestion/profile')['body'];
        }
        check('activation de la 2FA : codes faux limités à 10 par quart d’heure', str_contains($wrong[0], 'Code incorrect') && str_contains($wrong[10], 'Trop de tentatives')
            && empty($me()['totp']['on']));
        @unlink($limitFile('totp-setup:' . $own, $copy . '/data'));
        $post($a, 'profile', 'profile', ['action' => '2fa-confirm', 'code' => $code($secret2)]);
        check('connexion ancienne : activation après le mot de passe', ($me()['totp']['secret'] ?? '') === $secret2 && !empty($me()['totp']['on']));

        // Mot de passe actuel du profil : au plus 10 essais faux par quart d'heure.
        $tries = [];
        for ($i = 0; $i < 11; $i++) {
            $post($a, 'profile', 'profile', ['action' => 'password', 'current' => 'pas-le-bon-' . $i, 'password' => 'Encore-un-autre-mot-de-passe']);
            $tries[] = $a->req('GET', '/gestion/profile')['body'];
        }
        check('profil : mot de passe actuel limité à 10 essais faux', str_contains($tries[0], 'Mot de passe actuel incorrect') && str_contains($tries[10], 'Trop de tentatives'));
    } finally {
        foreach ($servers as $s) {
            proc_terminate($s);
        }
        exec('rm -rf ' . escapeshellarg($copy));
    }
})($argv);
