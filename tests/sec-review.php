<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité de la 1.10.3, corrections issues de la relecture finale (inclus par tests/run.php) :
 * connexion Google Drive (fenêtre comptée depuis son lancement, administrateur lié à la connexion), canal de mise à jour
 * d'un fork avec paramètres, version signée sans lien symbolique et manifeste qui signale un arbre modifié.
 */
declare(strict_types=1);

/** Petit programme PHP dans un processus séparé (constantes propres), sans mandataire réseau : renvoie son JSON. */
function review_child(string $code, array $args): array
{
    $script = sys_get_temp_dir() . '/eclair-review-child-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($script, "<?php\ndeclare(strict_types=1);\n\$lib = \$argv[1];\n\$args = json_decode(\$argv[2], true);\n"
        . "foreach (['http_proxy', 'HTTP_PROXY', 'https_proxy', 'HTTPS_PROXY', 'all_proxy', 'ALL_PROXY'] as \$v) { putenv(\$v); }\n"
        . "define('ECLAIR', true);\ndefine('ECLAIR_VERSION', 'test');\ndefine('ECLAIR_TESTING', true);\ndefine('ECLAIR_DEBUG', false);\n"
        . "define('ECLAIR_ROOT', \$args['root']);\ndefine('ECLAIR_DATA', \$args['data']);\n"
        . "spl_autoload_register(static function (string \$class) use (\$lib): void {\n"
        . "    \$file = \$lib . '/' . substr(\$class, 7) . '.php';\n"
        . "    if (str_starts_with(\$class, 'Eclair\\\\') && is_file(\$file)) { require \$file; }\n"
        . "});\nrequire \$lib . '/helpers.php';\n" . $code);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(ECLAIR_ROOT . '/app/lib') . ' ' . escapeshellarg((string) json_encode($args)) . ' 2>&1', $out, $rc);
    @unlink($script);
    $json = json_decode(implode("\n", $out), true);
    return is_array($json) ? $json : ['error' => $rc . ' ' . implode("\n", $out)];
}

/* ───────────── Google Drive : fenêtre de la connexion, administrateur lié à la connexion ───────────── */
section('Relecture 1.10.3 : connexion Google Drive (fenêtre depuis son lancement, administrateur lié à la connexion)');
// 2.0 : Google Drive est une fonction livrée (app/bundled/drive).
require_once ECLAIR_ROOT . '/app/bundled/drive/lib/Drive.php';
$rvLive = method_exists(EclairExt\Drive\Drive::class, 'live') ? new ReflectionMethod(EclairExt\Drive\Drive::class, 'live') : null;
$rvBinder = method_exists(EclairExt\Drive\Drive::class, 'binder') ? new ReflectionMethod(EclairExt\Drive\Drive::class, 'binder') : null;
$rvLive?->setAccessible(true);
$rvBinder?->setAccessible(true);
check('connexion lancée il y a 1 minute (mot de passe saisi 14 minutes plus tôt) : toujours en cours, code affiché',
    $rvLive !== null && $rvLive->invoke(null, ['by' => ['at' => time() - 60]]) === true);
check('connexion lancée il y a plus de 15 minutes, ou sans heure de lancement (1.10.2) : échue',
    $rvLive !== null && $rvLive->invoke(null, ['by' => ['at' => time() - 900]]) === false && $rvLive->invoke(null, ['by' => []]) === false);
check('administrateur noté avec la date de la connexion en place : reconnu',
    $rvBinder !== null && $rvBinder->invoke(null, ['since' => 1700000000, 'by' => ['uid' => 'abc', 'since' => 1700000000]]) === 'abc');
check('connexion refaite ailleurs (date différente) ou faite avant la 1.10.3 : administrateur inconnu, jamais attribuée',
    $rvBinder !== null && $rvBinder->invoke(null, ['since' => 1700000500, 'by' => ['uid' => 'abc', 'since' => 1700000000]]) === ''
    && $rvBinder->invoke(null, ['since' => 1700000500, 'by' => ['uid' => 'abc', 'email' => 'a@example.com']]) === ''
    && $rvBinder->invoke(null, ['since' => 1700000500]) === '');

/* ───────────── Page Sécurité : comptes bloqués avant les réseaux, CSS, cache et redirections ───────────── */
section('Relecture 1.10.3 : comptes bloqués listés en premier, CSS personnalisé, cache et redirections d’avant la 1.10.3');
$rvKeys = [];
for ($i = 0; $i < 150; $i++) {
    $rvKeys[] = $k = 'login-ip:198.51.100.' . $i . '-rv';
    Eclair\RateLimit::attempt($k, 1, 600, 600);
    Eclair\RateLimit::attempt($k, 1, 600, 600);
}
$rvKeys[] = $rvAcct = 'login-acct:rv1';
Eclair\RateLimit::attempt($rvAcct, 1, 600, 600);
Eclair\RateLimit::attempt($rvAcct, 1, 600, 600);
$rvActive = Eclair\RateLimit::active();
check('attaque venue de 150 réseaux : le compte bloqué reste listé, en premier, et la liste reste bornée à 100',
    ($rvActive[0]['key'] ?? '') === $rvAcct && count($rvActive) === 100, json_encode(array_slice($rvActive, 0, 2)));
foreach ($rvKeys as $k) {
    Eclair\RateLimit::clear($k);
}
$rvCss = 'a[href^="mailto:"]::after { content: " @\2709"; } .c{color:blue}';
check('CSS personnalisé : une chaîne contenant « @\ » reste intacte', Eclair\Theme::cleanCss($rvCss) === $rvCss, Eclair\Theme::cleanCss($rvCss));
check('CSS personnalisé : @import déguisé par un échappement toujours retiré', !str_contains(Eclair\Theme::cleanCss("x{a:1}\n@\\69mport url(x.css);\nb{x:1}"), 'url(x.css)'));

$rvTmp = sys_get_temp_dir() . '/eclair-review-unit-' . getmypid();
exec('rm -rf ' . escapeshellarg($rvTmp));
@mkdir($rvTmp . '/root/cache/pages', 0755, true);
@mkdir($rvTmp . '/data', 0755, true);
// cache/runtime.php à la bonne version mais sans génération (écrit par l'ancien code pendant une mise à jour par FTP).
$rvRes = review_child(
    "Eclair\\Store::write('site', ['name' => 'Test', 'installed' => time(), 'version' => ECLAIR_VERSION]);\n"
    . "file_put_contents(ECLAIR_ROOT . '/cache/runtime.php', Eclair\\Store::GUARD . json_encode(['v' => ECLAIR_VERSION, 'on' => true, 'expires' => 0]));\n"
    . "Eclair\\Cache::checkExpiry();\n"
    . "\$rt = json_decode(Eclair\\Store::strip((string) file_get_contents(ECLAIR_ROOT . '/cache/runtime.php')), true);\n"
    . "echo json_encode(['g' => \$rt['g'] ?? null]);\n",
    ['root' => $rvTmp . '/root', 'data' => $rvTmp . '/data']
);
check('cache sans génération (mise à jour par FTP) : une purge en publie une, les pages se remettent en cache', is_string($rvRes['g'] ?? null) && $rvRes['g'] !== '', json_encode($rvRes));
// Liste de redirections d'avant la 1.10.3, au-delà du maximum : jamais coupée en masse, une redirection modifiable.
$rvRes = review_child(
    "\$rows = [];\nfor (\$i = 0; \$i < 5100; \$i++) { \$rows[] = ['from' => '/ancienne-' . \$i, 'to' => '/x', 'code' => 301, 'auto' => \$i >= 100, 'hits' => 0, 'created' => 1000 + \$i]; }\n"
    . "Eclair\\Store::write('redirects', \$rows);\n"
    . "\$auto = Eclair\\Redirects::add('/renommee', '/nouvelle', true);\n"
    . "\$count = count(Eclair\\Redirects::all());\n"
    . "\$manual = Eclair\\Redirects::add('/ancienne-7', '/autre', false);\n"
    . "\$seven = Eclair\\Redirects::find('/ancienne-7');\n"
    . "echo json_encode(['auto' => \$auto, 'count' => \$count, 'oldest' => Eclair\\Redirects::find('/ancienne-100') !== null, 'next' => Eclair\\Redirects::find('/ancienne-101') !== null,"
    . " 'manual' => \$manual, 'to' => \$seven['to'] ?? '', 'after' => count(Eclair\\Redirects::all())]);\n",
    ['root' => $rvTmp . '/root', 'data' => $rvTmp . '/data']
);
check('liste de 5 100 redirections (1.10.2) : une redirection automatique remplace la plus ancienne, une pour une',
    ($rvRes['auto'] ?? null) === true && ($rvRes['count'] ?? 0) === 5100 && ($rvRes['oldest'] ?? true) === false && ($rvRes['next'] ?? false) === true, json_encode($rvRes));
check('liste au-delà du maximum : une redirection existante reste modifiable', ($rvRes['manual'] ?? null) === true && ($rvRes['to'] ?? '') === '/autre' && ($rvRes['after'] ?? 0) === 5100, json_encode($rvRes));
exec('rm -rf ' . escapeshellarg($rvTmp));

if (!in_array('--unit', $argv, true)) {

    /* ───────────── Installation relancée : comptes et contenus déjà là jamais effacés ───────────── */
    section('Relecture 1.10.3 : installation relancée (data/site.php perdu), comptes et contenus gardés');
    [$rvCopy, $rvPort2, $rvSrv2] = serve('review-install');
    try {
        $c = client($rvPort2);
        $install = static function (object $c, string $email): array {
            $form = $c->req('GET', '/');
            return $c->req('POST', '/', $c->token($form['body']) + ['ui' => 'fr', 'site_name' => 'Atelier', 'site_tagline' => 'Céramique', 'site_lang' => 'fr',
                'site_type' => 'business', 'user_name' => 'Camille Test', 'user_email' => $email, 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1',
                'ai' => 'open', 'admin_path' => 'gestion'], ['Accept: application/json']);
        };
        $install($c, 'camille@example.com');
        $rvContent = count(glob($rvCopy . '/data/content/*.php') ?: []);
        @unlink($rvCopy . '/data/site.php');
        @unlink($rvCopy . '/cache/runtime.php');
        // Fenêtre d'installation gardée, refermée (1.10.4) : la réinstallation demande de la supprimer par FTP.
        @unlink($rvCopy . '/data/install-window.php');
        $again = $install(client($rvPort2), 'camille@example.com');
        $errors = (array) (json_decode($again['body'], true)['errors'] ?? []);
        check('installation relancée avec l’e-mail d’un compte existant : refusée avant toute écriture, rien d’effacé',
            $again['status'] === 422 && isset($errors['user_email']) && is_file($rvCopy . '/data/users.php') && count(glob($rvCopy . '/data/content/*.php') ?: []) === $rvContent,
            $again['status'] . ' ' . substr($again['body'], 0, 200));
        $other = $install(client($rvPort2), 'dominique@example.com');
        $users = json_decode(Eclair\Store::strip((string) file_get_contents($rvCopy . '/data/users.php')), true) ?: [];
        check('installation relancée avec un autre e-mail : réussie, comptes et contenus d’avant gardés',
            $other['status'] === 200 && count($users) === 2 && count(glob($rvCopy . '/data/content/*.php') ?: []) >= $rvContent, $other['status'] . ' ' . count($users));
        // Verrou des données inutilisable : le message clair aussi pour le formulaire de contact (pas une erreur interne).
        $lock = $rvCopy . '/data/.lock';
        @unlink($lock);
        @mkdir($lock . '/plein', 0755, true);
        @touch($lock . '/plein/x');
        $contactPage = client($rvPort2)->req('GET', '/contact');
        preg_match('/data-k="([^"]+)"/', $contactPage['body'], $k);
        preg_match('/name="page" value="([a-z0-9]*)"/', $contactPage['body'], $pid);
        $post = client($rvPort2)->req('POST', '/_eclair/contact', ['name' => 'Camille', 'email' => 'camille@example.org', 'message' => 'Bonjour, un message.',
            'website' => '', 'k' => $k[1] ?? '', 'page' => $pid[1] ?? ''], ['Accept: application/json']);
        check('verrou des données inutilisable : le formulaire de contact répond avec le message clair (503), pas une erreur interne',
            $post['status'] === 503 && str_contains((string) (json_decode($post['body'], true)['error'] ?? ''), 'data/.lock'), $post['status'] . ' ' . substr($post['body'], 0, 200));
        exec('rm -rf ' . escapeshellarg($lock));
    } finally {
        proc_terminate($rvSrv2);
    }
    /* ───────────── Canal de mise à jour d'un fork : adresse avec paramètres ───────────── */
    section('Relecture 1.10.3 : canal de mise à jour d’un fork avec paramètres, toujours lu');
    $rvChan = sys_get_temp_dir() . '/eclair-review-chan-' . getmypid();
    exec('rm -rf ' . escapeshellarg($rvChan));
    @mkdir($rvChan . '/eclair', 0755, true);
    file_put_contents($rvChan . '/latest.json', '{"canal":"fork"}');
    [$rvPort, $rvServer] = start_server(['-t', $rvChan], ['PHP_CLI_SERVER_WORKERS' => '1'], 50001, 60000);
    try {
        $rvRes = review_child(
            "define('ECLAIR_UPDATE_URL', \$args['url']);\n"
            . "\$get = new ReflectionMethod(Eclair\\Update::class, 'get');\n\$get->setAccessible(true);\n"
            . "echo json_encode(['channel' => \$get->invoke(null, ECLAIR_UPDATE_URL, 100000), 'other' => \$get->invoke(null, \$args['other'], 100000)]);\n",
            ['root' => $rvChan . '/eclair', 'data' => $rvChan . '/data', 'url' => 'http://127.0.0.1:' . $rvPort . '/latest.json?channel=stable',
                'other' => 'http://127.0.0.1:' . $rvPort . '/latest.json?x=1']
        );
        check('canal déclaré avec des paramètres (?channel=stable) : lu comme en 1.10.2', ($rvRes['channel'] ?? null) === '{"canal":"fork"}', json_encode($rvRes));
        check('adresse annoncée avec des paramètres : toujours refusée', array_key_exists('other', $rvRes) && $rvRes['other'] === null, json_encode($rvRes));
    } finally {
        proc_terminate($rvServer);
        exec('rm -rf ' . escapeshellarg($rvChan));
    }

    /* ───────────── Version signée : liens symboliques refusés, arbre modifié signalé au manifeste ───────────── */
    $rvGit = trim((string) shell_exec('command -v git 2>/dev/null'));
    if ($rvGit !== '' && function_exists('sodium_crypto_sign_keypair') && class_exists('ZipArchive')) {
        section('Relecture 1.10.3 : version signée sans lien symbolique, manifeste qui signale un arbre modifié');
        $rvRepo = sys_get_temp_dir() . '/eclair-review-build-' . getmypid();
        $rvOutside = sys_get_temp_dir() . '/eclair-review-outside-' . getmypid() . '.txt';
        exec('rm -rf ' . escapeshellarg($rvRepo));
        @mkdir($rvRepo . '/dist', 0755, true);
        // Copie de travail : code, outils et traductions de l'arbre de travail (fichiers suivis, et nouveaux fichiers non
        // ignorés), avec une clé jetable.
        exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && git ls-files -z --cached --others --exclude-standard -- eclair tools i18n'
            . ' | xargs -0 -I{} sh -c ' . escapeshellarg('test -e "$1" && cp --parents "$1" "$2"') . ' _ {} ' . escapeshellarg($rvRepo));
        $rvPair = sodium_crypto_sign_keypair();
        $rvSecret = sodium_crypto_sign_secretkey($rvPair);
        $rvUpdate = $rvRepo . '/eclair/app/lib/Update.php';
        file_put_contents($rvUpdate, (string) preg_replace("~private const KEY = '[^']+'~", "private const KEY = '" . base64_encode(sodium_crypto_sign_publickey($rvPair)) . "'", (string) file_get_contents($rvUpdate)));
        file_put_contents($rvOutside, 'SECRET-HORS-DU-DEPOT');
        $rvGitRun = static fn (string $args): int => (int) (exec('cd ' . escapeshellarg($rvRepo) . ' && git ' . $args . ' >/dev/null 2>&1; echo $?') ?: 1);
        $rvGitRun('init -q && git config user.email t@example.com && git config user.name Test');
        symlink($rvOutside, $rvRepo . '/eclair/assets/notes.txt');
        $rvGitRun('add -A && git commit -qm base');
        $rvBuild = static function () use ($rvRepo, $rvSecret): array {
            $p = proc_open([PHP_BINARY, $rvRepo . '/tools/build.php', '--sign'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $rvRepo,
                ['PATH' => (string) getenv('PATH'), 'ECLAIR_SIGNING_KEY' => base64_encode($rvSecret)]);
            $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            return ['rc' => proc_close($p), 'out' => $out];
        };
        $rvZip = static fn (): array => glob($rvRepo . '/dist/*.zip') ?: [];
        $res = $rvBuild();
        check('lien symbolique suivi par Git : version signée refusée, rien n’est construit', $res['rc'] !== 0 && str_contains($res['out'], 'Liens symboliques') && $rvZip() === [], $res['out']);
        unlink($rvRepo . '/eclair/assets/notes.txt');
        $rvGitRun('add -A && git commit -qm sans-lien');
        $res = $rvBuild();
        $rvManifest = static function () use ($rvRepo): array {
            $raw = (string) @file_get_contents($rvRepo . '/eclair/app/manifest.php');
            $m = json_decode(substr($raw, strlen("<?php exit; ?>\n")), true);
            return is_array($m) ? $m : [];
        };
        $m = $rvManifest();
        check('arbre propre : version signée construite, commit noté, sans mention de modification', $res['rc'] === 0 && preg_match('/^[a-f0-9]{40}$/', (string) ($m['commit'] ?? '')) === 1 && !isset($m['dirty']), $res['out']);
        file_put_contents($rvRepo . '/eclair/app/lib/Cache.php', "\n// modification non enregistrée\n", FILE_APPEND);
        $res = $rvBuild();
        $m = $rvManifest();
        check('fichier modifié sans commit : le manifeste le signale (dirty)', $res['rc'] === 0 && ($m['dirty'] ?? false) === true, $res['out']);
        $rvLeak = str_contains($res['out'], base64_encode($rvSecret));
        check('clé jamais affichée', !$rvLeak);
        exec('rm -rf ' . escapeshellarg($rvRepo));
        @unlink($rvOutside);
    }
}
