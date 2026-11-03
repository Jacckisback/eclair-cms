<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité de la 1.10.3, groupe C1 (canal de mise à jour, outils de publication, sauvegardes, Google Drive,
 * secrets, installation, règles du serveur), inclus par tests/run.php. Les parties HTTP ne tournent pas avec --unit.
 */
declare(strict_types=1);

use Eclair\Store;

/**
 * Lance un petit programme PHP dans un processus séparé, avec ses propres constantes (ECLAIR_ROOT, ECLAIR_DATA…) :
 * $code reçoit $lib (classes d'Éclair) et $args ; il écrit son résultat en JSON.
 */
function c1_child(string $code, array $args): array
{
    $script = sys_get_temp_dir() . '/eclair-c1-child-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($script, "<?php\ndeclare(strict_types=1);\n\$lib = \$argv[1];\n\$args = json_decode(\$argv[2], true);\n"
        . "define('ECLAIR', true);\ndefine('ECLAIR_VERSION', 'test');\ndefine('ECLAIR_TESTING', true);\n"
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

/* ───────────── Sauvegarde : ECLAIR_DATA écrit avec une barre finale (processus séparé) ───────────── */
section('Sauvegarde : secrets exclus quelle que soit l’écriture du dossier des données');
$c1Root = sys_get_temp_dir() . '/eclair-c1-root-' . getmypid();
$c1Data = sys_get_temp_dir() . '/eclair-c1-data-' . getmypid();
exec('rm -rf ' . escapeshellarg($c1Root) . ' ' . escapeshellarg($c1Data));
foreach (['/media/2026/11', '/media/fonts'] as $dir) {
    @mkdir($c1Root . $dir, 0755, true);
}
foreach (['sessions', 'limits', 'ai/chats', 'content', 'logs'] as $dir) {
    @mkdir($c1Data . '/' . $dir, 0700, true);
}
file_put_contents($c1Root . '/media/2026/11/photo.jpg', "\xFF\xD8\xFF\xE0image");
foreach (['keys', 'users', 'site', 'vault', 'drive', 'update', 'reset', 'sessions/' . str_repeat('a', 64), 'limits/abc', 'ai/mcp', 'ai/chats/c1', 'content/page1', 'logs/security'] as $name) {
    file_put_contents($c1Data . '/' . $name . '.php', Store::GUARD . '{"secret":"' . $name . '"}');
}
// Lien symbolique vers le coffre, rangé parmi les contenus : jamais archivé (exclusion par l'emplacement réel).
$c1Link = function_exists('symlink') && @symlink($c1Data . '/vault.php', $c1Data . '/content/copie.php');
$c1Listing = static fn (string $data): array => c1_child(<<<'PHP'
    $out = ['backup' => array_keys(Eclair\Backup::files()), 'scan' => Eclair\Integrity::scan(['data'])];
    $zip = sys_get_temp_dir() . '/eclair-c1-backup-' . getmypid() . '.zip';
    Eclair\Backup::create($zip);
    clearstatcache();
    $out['mode'] = fileperms($zip) & 0777;
    $z = new ZipArchive();
    $out['zip'] = [];
    if ($z->open($zip) === true) {
        for ($i = 0; $i < $z->numFiles; $i++) {
            $out['zip'][] = (string) $z->getNameIndex($i);
        }
        $z->close();
    }
    @unlink($zip);
    echo json_encode($out);
    PHP, ['root' => $c1Root, 'data' => $data]);
$secretNames = static fn (array $names): array => array_values(array_filter($names, static fn (string $n): bool
    => (bool) preg_match('~^data/(vault|drive|update|reset)\.php$|^data/(sessions|limits|ai)/~', $n) || $n === 'data/content/copie.php'));
$expected = ['data/content/page1.php', 'data/keys.php', 'data/logs/security.php', 'data/site.php', 'data/users.php', 'media/2026/11/photo.jpg'];
foreach (['sans barre finale' => $c1Data, 'avec une barre finale' => $c1Data . '/', 'avec deux barres finales' => $c1Data . '//'] as $label => $data) {
    $res = $c1Listing($data);
    $backup = (array) ($res['backup'] ?? []);
    check("ECLAIR_DATA $label : aucun secret ni état de service dans la liste de sauvegarde", isset($res['backup']) && $secretNames($backup) === [], json_encode($res));
    check("ECLAIR_DATA $label : chemins exacts (contenus, comptes, clés, réglages, images)", $backup === $expected, json_encode($backup));
    check("ECLAIR_DATA $label : archive créée sans secret, lisible par PHP seulement (0600)", isset($res['zip']) && $secretNames((array) $res['zip']) === []
        && in_array('data/keys.php', (array) $res['zip'], true) && in_array('LISEZ-MOI.txt', (array) $res['zip'], true) && ($res['mode'] ?? 0) === 0600,
        json_encode(['mode' => decoct((int) ($res['mode'] ?? 0)), 'zip' => $res['zip'] ?? null, 'error' => $res['error'] ?? '']));
    check("ECLAIR_DATA $label : contrôle d’intégrité avec les vrais noms de fichiers", in_array('data/vault.php', (array) ($res['scan'] ?? []), true)
        && in_array('data/sessions/' . str_repeat('a', 64) . '.php', (array) ($res['scan'] ?? []), true) && !in_array('data/ault.php', (array) ($res['scan'] ?? []), true),
        json_encode($res['scan'] ?? $res));
}
check('lien symbolique vers le coffre parmi les contenus : exclu (test effectif)', $c1Link, 'symlink indisponible');
exec('rm -rf ' . escapeshellarg($c1Root) . ' ' . escapeshellarg($c1Data));

/* ───────────── Canal de mise à jour : adresses permises, outils de publication (unitaire) ───────────── */
section('Mise à jour : adresses permises, annonce publiée');
$c1Trusted = new ReflectionMethod(Eclair\Update::class, 'trusted');
$c1Urls = [
    'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/eclair-cms-1.10.3.zip' => true,
    'https://github.com/Jacckisback/eclair-cms/releases/tag/1.10.3' => true,
    'https://raw.githubusercontent.com/quelquun/eclair-cms/main/dist/eclair-cms-1.10.3.zip' => false,
    'https://github.com/quelquun/autre/releases/download/1.10.3/eclair-cms-1.10.3.zip' => false,
    'https://raw.githubusercontent.com/Jacckisback/eclair-cms-pirate/main/dist/x.zip' => false,
    'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/../../quelquun/x/main/x.zip' => false,
    'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/x.zip?y=1' => false,
    'http://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/x.zip' => false,
    'https://moi@raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/x.zip' => false,
];
$c1Wrong = array_keys(array_filter($c1Urls, static fn (bool $ok, string $url): bool => $c1Trusted->invoke(null, $url) !== $ok, ARRAY_FILTER_USE_BOTH));
check('archive et notes : seulement dans le dépôt du projet (jamais celui d’un autre, ni « .. », ni paramètres)', $c1Wrong === [], implode(' | ', $c1Wrong));

require_once dirname(__DIR__) . '/tools/announce.php';
$c1UpdateCode = (string) file_get_contents(ECLAIR_ROOT . '/app/lib/Update.php');
preg_match("~private const KEY = '([^']+)'~", $c1UpdateCode, $c1Key);
preg_match("~private const CONTEXT = '([^']+)'~", $c1UpdateCode, $c1Context);
$c1Latest = dirname(__DIR__) . '/dist/latest.json';
if (is_file($c1Latest)) {
    $c1Announced = eclair_announce_read((string) file_get_contents($c1Latest), (string) base64_decode($c1Key[1] ?? '', true), $c1Context[1] ?? '');
    $c1Zip = is_array($c1Announced) ? dirname(__DIR__) . '/dist/' . basename((string) parse_url((string) $c1Announced['fields']['url'], PHP_URL_PATH)) : '';
    check('dist/latest.json : signé par la clé du mainteneur (et format 2 cohérent s’il est là)', is_array($c1Announced), is_string($c1Announced) ? $c1Announced : '');
    check('dist/latest.json : l’archive annoncée dans dist/ correspond à son empreinte et à sa taille', $c1Zip !== '' && is_file($c1Zip)
        && hash_file('sha256', $c1Zip) === $c1Announced['fields']['sha256'] && filesize($c1Zip) === $c1Announced['fields']['size'], basename($c1Zip));
}

/* ───────────── tools/build.php : annonce au nouveau format, re-signature, clé jamais affichée (copie de travail) ───────────── */
if (function_exists('sodium_crypto_sign_keypair') && class_exists('ZipArchive')) {
    section('Publication : annonce signée deux fois, re-signature en une commande, retrait de version, clé jamais affichée');
    $c1Build = sys_get_temp_dir() . '/eclair-c1-build-' . getmypid();
    exec('rm -rf ' . escapeshellarg($c1Build));
    @mkdir($c1Build . '/tools', 0755, true);
    @mkdir($c1Build . '/eclair/app/lib', 0755, true);
    @mkdir($c1Build . '/dist', 0755, true);
    foreach (['build.php', 'announce.php', 'minify.php', 'i18n.php'] as $tool) {
        copy(dirname(__DIR__) . '/tools/' . $tool, $c1Build . '/tools/' . $tool);
    }
    // Clé jetable à la place de celle du mainteneur, dans la copie seulement.
    $c1Pair = sodium_crypto_sign_keypair();
    $c1Secret = sodium_crypto_sign_secretkey($c1Pair);
    $c1Public = sodium_crypto_sign_publickey($c1Pair);
    file_put_contents($c1Build . '/eclair/app/lib/Update.php', (string) preg_replace("~private const KEY = '[^']+'~", "private const KEY = '" . base64_encode($c1Public) . "'", $c1UpdateCode));
    file_put_contents($c1Build . '/eclair/index.php', "<?php\ndefine('ECLAIR_VERSION', '7.7.8');\n");
    $c1Archive = static function (string $version) use ($c1Build): string {
        $file = $c1Build . '/dist/eclair-cms-' . $version . '.zip';
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('eclair/app/manifest.php', "<?php exit; ?>\n" . json_encode(['version' => $version, 'generated' => '2026-10-01T10:00:00+00:00', 'files' => []]) . "\n");
        $zip->addFromString('eclair/index.php', str_repeat('x', 3000));
        $zip->close();
        return $file;
    };
    $c1Run = static function (array $args, bool $key = true) use ($c1Build, $c1Secret): array {
        $env = ['PATH' => (string) getenv('PATH')] + ($key ? ['ECLAIR_SIGNING_KEY' => base64_encode($c1Secret)] : []);
        $p = proc_open(array_merge([PHP_BINARY, $c1Build . '/tools/build.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $c1Build, $env);
        $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        return ['rc' => proc_close($p), 'out' => $out];
    };
    $c1Read = static fn (): array|string => eclair_announce_read((string) @file_get_contents($c1Build . '/dist/latest.json'), $c1Public, $c1Context[1] ?? '');
    $c1Leak = static fn (string $out): bool => str_contains($out, base64_encode($c1Secret)) || str_contains($out, bin2hex($c1Secret)) || str_contains($out, substr(base64_encode($c1Secret), 0, 20));
    // Annonce d'origine seule (comme celle de la 1.10.2), signée par la clé jetable.
    $c1Old = $c1Archive('7.7.7');
    $c1Payload = json_encode(['version' => '7.7.7', 'date' => '2026-10-01', 'url' => 'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/eclair-cms-7.7.7.zip',
        'sha256' => hash_file('sha256', $c1Old), 'size' => filesize($c1Old), 'php' => '8.1', 'notes' => 'https://github.com/Jacckisback/eclair-cms/releases/tag/7.7.7'], JSON_UNESCAPED_SLASHES);
    $c1Legacy = ['payload' => $c1Payload, 'signature' => base64_encode(sodium_crypto_sign_detached($c1Payload, $c1Secret))];
    file_put_contents($c1Build . '/dist/latest.json', json_encode($c1Legacy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $before = time();
    $res = $c1Run(['--resign']);
    $first = $c1Read();
    check('--resign sur une annonce d’origine : format 2 ajouté (numéro, échéance à 180 jours), annonce d’origine gardée à l’octet', $res['rc'] === 0 && is_array($first)
        && $first['serial'] >= $before && abs($first['expires'] - ($before + 180 * 86400)) < 120 && $first['channel']['payload'] === $c1Legacy['payload']
        && $first['channel']['signature'] === $c1Legacy['signature'] && $first['fields']['sha256'] === hash_file('sha256', $c1Old), $res['out'] . (is_string($first) ? $first : ''));
    // Les sites en 1.10.0 à 1.10.2 vérifient toujours l'annonce d'origine (clés inconnues ignorées).
    $c1Outer = json_decode((string) file_get_contents($c1Build . '/dist/latest.json'), true);
    check('annonce re-signée : toujours lisible par la 1.10.2 (payload et signature d’origine valides, champ v2 en plus)', is_array($c1Outer)
        && sodium_crypto_sign_verify_detached((string) base64_decode((string) $c1Outer['signature']), (string) $c1Outer['payload'], $c1Public)
        && array_keys($c1Outer) === ['payload', 'signature', 'v2'] && strlen((string) file_get_contents($c1Build . '/dist/latest.json')) < 65536);
    $res2 = $c1Run(['--resign']);
    $second = $c1Read();
    check('--resign de nouveau : numéro strictement plus grand, même archive, une seule commande', $res2['rc'] === 0 && is_array($second) && is_array($first)
        && $second['serial'] > $first['serial'] && $second['fields']['sha256'] === $first['fields']['sha256'] && $second['fields']['version'] === '7.7.7', $res2['out']);
    $c1Bare = is_array($second) ? (string) base64_decode((string) ($second['channel']['v2']['signature'] ?? '')) : '';
    check('format 2 signé avec son contexte : la signature seule de l’annonce ne vaut pas', strlen($c1Bare) === SODIUM_CRYPTO_SIGN_BYTES
        && !sodium_crypto_sign_verify_detached($c1Bare, (string) $second['channel']['v2']['payload'], $c1Public));
    // Retrait : la 7.7.8, publiée puis retirée ; la 7.7.7 est annoncée de nouveau, avec un numéro plus grand.
    $c1Archive('7.7.8');
    $res3 = $c1Run(['--resign=7.7.8']);
    $third = $c1Read();
    $res4 = $c1Run(['--resign=7.7.7']);
    $fourth = $c1Read();
    check('--resign=<version> : retrait par une nouvelle annonce (numéro plus grand), annonce d’origine refaite pour les anciens sites', $res3['rc'] === 0 && $res4['rc'] === 0
        && is_array($third) && is_array($fourth) && $third['fields']['version'] === '7.7.8' && $fourth['fields']['version'] === '7.7.7' && $fourth['serial'] > $third['serial']
        && json_decode((string) $fourth['channel']['payload'], true)['version'] === '7.7.7' && $fourth['fields']['date'] === '2026-10-01', $res3['out'] . $res4['out']);
    // Archive de dist/ modifiée depuis l'annonce : rien n'est signé.
    file_put_contents($c1Old, 'archive remplacée', FILE_APPEND);
    $kept = (string) file_get_contents($c1Build . '/dist/latest.json');
    $res5 = $c1Run(['--resign']);
    check('--resign : archive de dist/ différente de l’annonce, refusé, rien n’est signé', $res5['rc'] !== 0 && (string) file_get_contents($c1Build . '/dist/latest.json') === $kept, $res5['out']);
    // Annonce trafiquée dans le dépôt (sans la clé) : jamais re-signée.
    $c1Tampered = json_decode($kept, true);
    $c1Tampered['v2']['payload'] = str_replace('"version":"7.7.7"', '"version":"7.7.9"', (string) $c1Tampered['v2']['payload']);
    file_put_contents($c1Build . '/dist/latest.json', json_encode($c1Tampered));
    $res6 = $c1Run(['--resign']);
    check('--resign : annonce trafiquée dans le dépôt, refusée (jamais re-signée)', $res6['rc'] !== 0 && !str_contains((string) file_get_contents($c1Build . '/dist/latest.json'), '"v2": {'), $res6['out']);
    $res7 = $c1Run(['--resign'], false);
    $res8 = $c1Run(['--inconnue=' . base64_encode($c1Secret)]);
    check('--resign sans la clé : refusé ; argument inconnu jamais recopié', $res7['rc'] !== 0 && str_contains($res7['out'], 'ECLAIR_SIGNING_KEY') && $res8['rc'] !== 0 && !$c1Leak($res8['out']), $res7['out'] . $res8['out']);
    check('la clé n’apparaît dans aucune sortie de tools/build.php', !$c1Leak($res['out'] . $res2['out'] . $res3['out'] . $res4['out'] . $res5['out'] . $res6['out'] . $res7['out']));
    exec('rm -rf ' . escapeshellarg($c1Build));
}

/* ───────────── Canal de mise à jour : numéro de série, échéance, rejeu (faux canal, HTTP) ───────────── */
if (!in_array('--unit', $argv, true) && function_exists('sodium_crypto_sign_keypair') && class_exists('ZipArchive')) {
    section('Mise à jour : annonce rejouée refusée, retrait par une annonce plus récente, annonce périmée signalée');
    [$copy, $port, $server] = serve('sec-c1-update');
    $channel = sys_get_temp_dir() . '/eclair-c1-channel-' . getmypid();
    exec('rm -rf ' . escapeshellarg($channel));
    @mkdir($channel);
    file_put_contents($channel . '/index.html', 'canal');
    [$chanPort, $chan] = start_server(['-t', $channel], ['PHP_CLI_SERVER_WORKERS' => '2'], 50001, 60000);
    $pair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    $intruder = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_UPDATE_URL', 'http://127.0.0.1:$chanPort/latest.json');\n"
        . "define('ECLAIR_UPDATE_KEY', '" . base64_encode(sodium_crypto_sign_publickey($pair)) . "');\n");
    preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($copy . '/index.php'), $vm);
    $current = $vm[1] ?? '';
    file_put_contents($copy . '/app/manifest.php', eclair_manifest($current, eclair_code($copy)));
    $state = static fn (): array => json_decode(Store::strip((string) @file_get_contents($copy . '/data/update.php')), true) ?: [];
    $version = static function () use ($copy): string {
        preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($copy . '/index.php'), $m);
        return $m[1] ?? '';
    };
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Canal', 'site_tagline' => 'Poterie', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $check = static function () use ($c): string {
            $page = $c->req('GET', '/gestion/update');
            $c->req('POST', '/gestion/update', $c->token($page['body']) + ['action' => 'check']);
            return $c->req('GET', '/gestion/update')['body'];
        };
        $saved = static fn (): string => (string) file_get_contents($channel . '/latest.json');
        $offers = static fn (string $body, string $v): bool => str_contains($body, 'Éclair ' . $v . ' est disponible') && str_contains($body, 'name="action" value="install"');
        $upToDate = static fn (string $body): bool => str_contains($body, 'Tu as la dernière version');

        // Le mainteneur : 9.9.9 annoncée, puis retirée (la version installée annoncée de nouveau, numéro plus grand), puis 9.9.10.
        $r999 = eclair_release($channel, '9.9.9');
        eclair_announce($channel, $chanPort, $r999, $secret);
        $a1 = $saved();
        $body = $check();
        check('canal : 9.9.9 annoncée et proposée', $offers($body, '9.9.9') && is_int($state()['serial'] ?? null), (string) json_encode($state()['release'] ?? null));
        eclair_announce($channel, $chanPort, eclair_release($channel, $current), $secret);
        $a2 = $saved();
        $body = $check();
        check('retrait signé (annonce plus récente de la version installée) : 9.9.9 n’est plus proposée, à jour', !$offers($body, '9.9.9') && $upToDate($body));
        $r9910 = eclair_release($channel, '9.9.10');
        eclair_announce($channel, $chanPort, $r9910, $secret);
        $body = $check();
        $serial = (int) ($state()['serial'] ?? 0);
        check('canal : 9.9.10 annoncée et proposée', $offers($body, '9.9.10'));

        // Sans la clé, le canal rejoue des annonces publiées plus tôt (octet pour octet).
        file_put_contents($channel . '/latest.json', $a2);
        $body = $check();
        check('annonce plus ancienne rejouée (gel) : refusée, 9.9.10 toujours proposée, jamais « dernière version »', $offers($body, '9.9.10') && !$upToDate($body)
            && str_contains($body, 'annonce plus ancienne que celle déjà reçue') && ($state()['error'] ?? '') === 'replay' && (int) ($state()['serial'] ?? 0) === $serial,
            (string) json_encode(['error' => $state()['error'] ?? '', 'serial' => $state()['serial'] ?? 0]));
        file_put_contents($channel . '/latest.json', $a1);
        $body = $check();
        check('annonce retirée rejouée : 9.9.9 jamais proposée de nouveau', !$offers($body, '9.9.9') && $offers($body, '9.9.10') && ($state()['error'] ?? '') === 'replay');
        $dash = $c->req('GET', '/gestion')['body'];
        check('tableau de bord : la version la plus récente reste signalée', str_contains($dash, 'Éclair 9.9.10 est disponible') && !str_contains($dash, 'Éclair 9.9.9 est disponible'));
        check('annonce rejouée : inscrite au journal de sécurité', str_contains($c->req('GET', '/gestion/security')['body'], 'Annonce de mise à jour refusée (plus ancienne que celle déjà reçue)'));
        // Annonce d'avant la 1.10.3 (sans format 2), bien signée : une ancienne annonce, refusée.
        $payload = json_encode(['version' => '9.9.11', 'date' => '2026-11-02', 'url' => 'http://127.0.0.1:' . $chanPort . '/' . $r9910['file'], 'sha256' => $r9910['sha256'],
            'size' => $r9910['size'], 'php' => '8.1', 'notes' => ''], JSON_UNESCAPED_SLASHES);
        file_put_contents($channel . '/latest.json', json_encode(['payload' => $payload, 'signature' => base64_encode(sodium_crypto_sign_detached($payload, $secret))]));
        $body = $check();
        check('annonce sans numéro de série (ancien format) : refusée, jamais proposée', !$offers($body, '9.9.11') && $offers($body, '9.9.10') && ($state()['error'] ?? '') === 'replay');
        // Format 2 signé par une autre clé, ou signé sans son contexte : refusé.
        eclair_announce($channel, $chanPort, eclair_release($channel, '9.9.12'), $intruder);
        $body = $check();
        check('format 2 signé par une autre clé : refusé', !$offers($body, '9.9.12') && str_contains($body, 'signature ne correspond pas') && ($state()['error'] ?? '') === 'signature');
        $bare = json_decode($a1, true);
        $bare['v2']['payload'] = str_replace('"serial":', '"serial":9', (string) $bare['v2']['payload']);
        $bare['v2']['signature'] = base64_encode(sodium_crypto_sign_detached((string) $bare['v2']['payload'], $secret));
        file_put_contents($channel . '/latest.json', json_encode($bare));
        $body = $check();
        check('format 2 signé sans son contexte (la clé du mainteneur, mais pour autre chose) : refusé', !$offers($body, '9.9.9') && ($state()['error'] ?? '') === 'signature');
        // Archive annoncée hors du dépôt du projet (ou du serveur du canal) : refusée même bien signée.
        eclair_announce($channel, $chanPort, eclair_release($channel, '9.9.13'), $secret, ['url' => 'https://raw.githubusercontent.com/quelquun/eclair-cms/main/dist/eclair-cms-9.9.13.zip']);
        $body = $check();
        check('archive annoncée dans le dépôt d’un autre : refusée', !$offers($body, '9.9.13') && ($state()['error'] ?? '') === 'format' && $offers($body, '9.9.10'));

        // Annonce périmée : gardée (rien n'est bloqué), mais signalée ; puis le mainteneur re-signe.
        $r9914 = eclair_release($channel, '9.9.14');
        eclair_announce($channel, $chanPort, $r9914, $secret, ['expires' => time() - 3600]);
        $body = $check();
        check('annonce périmée d’une nouvelle version : proposée quand même, avec l’alerte', $offers($body, '9.9.14') && str_contains($body, 'Annonce de mise à jour périmée depuis le')
            && ($state()['error'] ?? '') === 'expired');
        eclair_announce($channel, $chanPort, eclair_release($channel, $current), $secret, ['expires' => time() - 60]);
        $body = $check();
        check('annonce périmée de la version installée : jamais « dernière version », alerte affichée', !$upToDate($body) && str_contains($body, 'Annonce de mise à jour périmée depuis le'));
        eclair_announce($channel, $chanPort, eclair_release($channel, $current), $secret);
        $body = $check();
        check('canal re-signé : de nouveau à jour, sans alerte', $upToDate($body) && !str_contains($body, 'Annonce de mise à jour périmée') && ($state()['error'] ?? '') === '');
        // Canal injoignable : jamais « dernière version ».
        @unlink($channel . '/latest.json');
        $body = $check();
        check('vérification impossible (canal injoignable) : jamais « dernière version »', !$upToDate($body) && str_contains($body, 'Dernière vérification impossible'));

        // État enregistré par la 1.10.2 (annonce sans numéro de série) : jamais proposé, relu au plus vite.
        $r9915 = eclair_release($channel, '9.9.15');
        file_put_contents($copy . '/data/update.php', Store::GUARD . json_encode(['checked' => time(), 'next' => time() + 43200, 'error' => '',
            'release' => ['version' => '9.9.15', 'url' => 'http://127.0.0.1:' . $chanPort . '/' . $r9915['file'], 'sha256' => $r9915['sha256'], 'size' => $r9915['size'],
                'php' => '8.1', 'from' => '', 'date' => '2026-11-02', 'notes' => '']]));
        $dash = $c->req('GET', '/gestion')['body'];
        check('état de la 1.10.2 (sans numéro de série) : rien de proposé avant une nouvelle lecture, relecture demandée', !str_contains($dash, 'Éclair 9.9.15 est disponible')
            && str_contains($dash, '"updateCheck":true'));
        $body = $c->req('GET', '/gestion/update')['body'];
        check('état de la 1.10.2, canal injoignable : relu, rien proposé, jamais « dernière version »', !$offers($body, '9.9.15') && !$upToDate($body) && !isset($state()['release']));
        eclair_announce($channel, $chanPort, $r9915, $secret);
        $body = $check();
        check('après la relecture : la version annoncée au format 2 est proposée', $offers($body, '9.9.15') && ($state()['release']['serial'] ?? 0) > $serial);

        // data/update.php réécrit (sans la clé) pour désigner une autre archive : l'installation la refuse sans rien télécharger.
        $evil = eclair_release($channel, '9.9.15', static function (array $f): array {
            $f['assets/admin/admin.css'] .= "\n/* archive-piegee */\n";
            return $f;
        });
        $s = $state();
        $s['release'] = ['url' => 'http://127.0.0.1:' . $chanPort . '/' . $evil['file'], 'sha256' => $evil['sha256'], 'size' => $evil['size']] + (array) ($s['release'] ?? []);
        $p = json_decode((string) ($s['signed']['payload'] ?? ''), true);
        $s['signed'] = ['payload' => json_encode(['url' => $s['release']['url'], 'sha256' => $evil['sha256'], 'size' => $evil['size']] + (is_array($p) ? $p : []), JSON_UNESCAPED_SLASHES),
            'signature' => (string) ($s['signed']['signature'] ?? '')];
        file_put_contents($copy . '/data/update.php', Store::GUARD . json_encode($s));
        $page = $c->req('GET', '/gestion/update');
        $c->req('POST', '/gestion/update', $c->token($page['body']) + ['action' => 'install', 'saved' => '1', 'confirm_password' => 'Un-mot-de-passe-solide']);
        $body = $c->req('GET', '/gestion/update')['body'];
        check('annonce enregistrée réécrite sans la clé : installation refusée, rien n’a changé', str_contains($body, 'signature ne correspond pas') && $version() === $current
            && !str_contains((string) file_get_contents($copy . '/assets/admin/admin.css'), 'archive-piegee') && !glob($copy . '/cache/update/*.zip'), $version());
    } finally {
        proc_terminate($server);
        proc_terminate($chan);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($channel));
    }
}
