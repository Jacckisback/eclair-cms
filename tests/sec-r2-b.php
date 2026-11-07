<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité de la 1.10.4, lot B (inclus par tests/run.php) : images refusées par le filtre du fournisseur (ou perdues
 * après l'envoi) comptées dans le budget du mois, compteurs anonymes du MCP bornés, image de partage d'un original trop grand
 * pour la mémoire, redirection réservée jamais réécrite par la fusion des chaînes, ré-annonce d'une version liée à une
 * archive déjà signée. Les connexions Google Drive d'avant la 1.10.3 sont testées dans tests/drive.php, les brouillons de
 * secours de la 1.10.2 dans tests/sec-C2.php.
 */
declare(strict_types=1);

use Eclair\Store;

/* ───────────── MCP : compteurs anonymes de l'enregistrement et des jetons regroupés ───────────── */
section('1.10.4 (lot B) : compteurs anonymes du MCP (enregistrement, jetons) bornés à 4 096 par famille');
$r2bNet = method_exists(Eclair\Mcp::class, 'network') ? new ReflectionMethod(Eclair\Mcp::class, 'network') : null;
$r2bNet?->setAccessible(true);
$r2bAddr = $_SERVER['REMOTE_ADDR'] ?? null;
$r2bKeys = ['mcp-reg' => [], 'mcp-token' => []];
for ($i = 0; $r2bNet !== null && $i < 6000; $i++) {
    // 6 000 réseaux /64 différents (un seul abonné IPv6 en possède des milliards).
    $_SERVER['REMOTE_ADDR'] = sprintf('2001:db8:7:%x::1', $i);
    foreach (array_keys($r2bKeys) as $family) {
        $r2bKeys[$family][$r2bNet->invoke(null, $family)] = true;
    }
}
$r2bShape = static fn (string $family): bool => $r2bKeys[$family] !== []
    && !array_filter(array_keys($r2bKeys[$family]), static fn (string $k): bool => preg_match('~^' . $family . ':[0-9a-f]{3}$~', $k) !== 1);
check('6 000 réseaux simulés : au plus 4 096 compteurs par famille, groupes signés sans adresse IP',
    count($r2bKeys['mcp-reg']) <= 4096 && count($r2bKeys['mcp-token']) <= 4096 && $r2bShape('mcp-reg') && $r2bShape('mcp-token'),
    count($r2bKeys['mcp-reg']) . ' / ' . count($r2bKeys['mcp-token']) . ' ' . json_encode(array_slice(array_keys($r2bKeys['mcp-reg']), 0, 2)));
check('réseaux toujours répartis entre de nombreux compteurs (aucun plafond commun qui bloquerait tout le monde)',
    count($r2bKeys['mcp-reg']) > 2000 && count($r2bKeys['mcp-token']) > 2000);
$_SERVER['REMOTE_ADDR'] = '2001:db8:7:1::9';
$r2bSame = $r2bNet?->invoke(null, 'mcp-reg');
$_SERVER['REMOTE_ADDR'] = '2001:db8:7:1:ffff::2';
check('un même réseau /64 garde toujours le même compteur', $r2bNet !== null && $r2bSame === $r2bNet->invoke(null, 'mcp-reg'));
if ($r2bAddr === null) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR'] = $r2bAddr;
}

if (!in_array('--unit', $argv, true)) {

    /* ───────────── Site servi (mémoire limitée à 128 Mo, comme un hébergement) ───────────── */
    section('1.10.4 (lot B) : compteurs du MCP, image de partage trop grande pour la mémoire, redirection réservée (site servi)');
    $r2bCopy = sys_get_temp_dir() . '/eclair-it-r2b-' . getmypid();
    exec('rm -rf ' . escapeshellarg($r2bCopy) . ' && cp -r ' . escapeshellarg(ECLAIR_ROOT) . ' ' . escapeshellarg($r2bCopy));
    foreach (['data/*.php', 'data/*/', 'cache/*', 'media/20*', 'media/site'] as $glob) {
        exec('rm -rf ' . $r2bCopy . '/' . $glob);
    }
    [$r2bPort, $r2bSrv] = start_server(['-d', 'memory_limit=128M', '-t', $r2bCopy, dirname(__DIR__) . '/tools/router.php'], ['PHP_CLI_SERVER_WORKERS' => '4']);
    try {
        $c = client($r2bPort);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + ['ui' => 'fr', 'site_name' => 'Atelier', 'site_tagline' => 'Céramique', 'site_lang' => 'fr',
            'site_type' => 'business', 'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1',
            'ai' => 'open', 'admin_path' => 'gestion'], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $read = static fn (string $name): array => json_decode(Store::strip((string) @file_get_contents($r2bCopy . '/data/' . $name . '.php')), true) ?: [];

        // MCP : l'enregistrement et les jetons comptent par groupe signé du réseau, jamais par adresse.
        settings($r2bCopy, ['ai' => ['mcp' => true]]);
        $reg = client($r2bPort)->req('POST', '/_eclair/oauth/register', '{}', ['Content-Type: application/json']);
        $tok = client($r2bPort)->req('POST', '/_eclair/oauth/token', ['grant_type' => 'authorization_code']);
        $limits = [];
        foreach (glob($r2bCopy . '/data/limits/*.php') ?: [] as $file) {
            $k = (string) (json_decode(Store::strip((string) file_get_contents($file)), true)['k'] ?? '');
            if (str_starts_with($k, 'mcp-')) {
                $limits[] = $k;
            }
        }
        sort($limits);
        check('MCP : /_eclair/oauth/register et /_eclair/oauth/token comptent par groupe signé du réseau (aucune adresse IP dans la clé)',
            $reg['status'] === 400 && $tok['status'] === 401 && count($limits) === 2 && preg_match('~^mcp-reg:[0-9a-f]{3}$~', $limits[0]) === 1
            && preg_match('~^mcp-token:[0-9a-f]{3}$~', $limits[1]) === 1, $reg['status'] . ' ' . $tok['status'] . ' ' . json_encode($limits));

        // Image de partage : un original dont le décodage ne tiendrait pas dans la mémoire n'est jamais décodé ; la page est
        // rendue et mise en cache avec l'original, et l'échec est retenu (aucun nouvel essai à chaque page).
        @mkdir($r2bCopy . '/media/2026/11', 0755, true);
        foreach (['geante', 'normale'] as $name) {
            $img = imagecreatetruecolor(800, 450);
            imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 120, 60));
            imagejpeg($img, $r2bCopy . '/media/2026/11/' . $name . '.jpg', 80);
        }
        $media = $read('media');
        // Fiche d'une très grande photo (9 000 × 7 000 pixels, plus de 400 Mo à décoder) ; le fichier, lui, est petit : s'il
        // était décodé, son image de partage serait créée.
        $media['r2bgeante1'] = ['id' => 'r2bgeante1', 'file' => '2026/11/geante', 'ext' => 'jpg', 'w' => 9000, 'h' => 7000, 'webp' => [], 'alt' => '',
            'name' => 'geante.jpg', 'created' => time(), 'user' => ''];
        $media['r2bnormal1'] = ['id' => 'r2bnormal1', 'file' => '2026/11/normale', 'ext' => 'jpg', 'w' => 800, 'h' => 450, 'webp' => [], 'alt' => '',
            'name' => 'normale.jpg', 'created' => time(), 'user' => ''];
        file_put_contents($r2bCopy . '/data/media.php', Store::GUARD . json_encode($media));
        settings($r2bCopy, ['seo' => ['image' => 'r2bgeante1']]);
        exec('rm -rf ' . escapeshellarg($r2bCopy . '/cache/pages'));
        $og = static fn (string $html): string => preg_match('~<meta property="og:image" content="([^"]+)"~', $html, $m) ? $m[1] : '';
        // Première visite après le changement de réglage : elle republie la génération du cache ; la suivante entre en cache.
        $first = client($r2bPort)->req('GET', '/a-propos');
        $second = client($r2bPort)->req('GET', '/a-propos');
        $cached = array_filter(glob($r2bCopy . '/cache/pages/*') ?: [], static fn (string $f): bool => str_contains((string) file_get_contents($f), '/media/2026/11/geante.jpg"'));
        check('image de partage trop grande pour la mémoire : pas de décodage, page rendue et mise en cache avec l’original',
            $first['status'] === 200 && str_ends_with($og($first['body']), '/media/2026/11/geante.jpg') && !is_file($r2bCopy . '/media/2026/11/geante-og.jpg')
            && $cached !== [] && $second['status'] === 200 && str_ends_with($og($second['body']), '/media/2026/11/geante.jpg'),
            $first['status'] . ' ' . $og($first['body']) . ' ' . count($cached));
        check('échec retenu : aucun nouvel essai à chaque page (marque à côté de l’original)', is_file($r2bCopy . '/media/2026/11/geante-og.none'));
        settings($r2bCopy, ['seo' => ['image' => 'r2bnormal1']]);
        exec('rm -rf ' . escapeshellarg($r2bCopy . '/cache/pages'));
        $normal = client($r2bPort)->req('GET', '/a-propos');
        check('image de taille ordinaire : image de partage 1200 × 630 toujours créée', $normal['status'] === 200
            && str_ends_with($og($normal['body']), '/media/2026/11/normale-og.jpg') && is_file($r2bCopy . '/media/2026/11/normale-og.jpg'), $og($normal['body']));
        settings($r2bCopy, ['seo' => ['image' => '']]);

        // Redirection réservée posée par un administrateur : un rédacteur ne change jamais sa cible, ni par l'écran
        // Redirections, ni en supprimant la page vers laquelle elle mène (fusion des chaînes A→B puis B→C).
        $target = static function (string $from) use ($read): ?string {
            foreach ($read('redirects') as $r) {
                if (is_array($r) && ($r['from'] ?? '') === $from) {
                    return (string) ($r['to'] ?? '');
                }
            }
            return null;
        };
        $redirect = static function (object $who, array $fields): void {
            $page = $who->req('GET', '/gestion/redirects');
            $who->req('POST', '/gestion/redirects', $who->token($page['body']) + $fields + ['code' => '301']);
        };
        $redirect($c, ['from' => '/.well-known/change-password', 'to' => '/a-propos']);
        $users = $read('users');
        $users['r2bed'] = ['id' => 'r2bed', 'name' => 'Rédac R2B', 'email' => 'redac-r2b@example.com', 'role' => 'editor', 'pass' => password_hash('Mot-de-passe-redac-r2b', PASSWORD_DEFAULT),
            'ver' => 1, 'created' => time(), 'slug' => 'redac-r2b', 'lang' => 'fr', 'must_change' => false, 'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []]];
        file_put_contents($r2bCopy . '/data/users.php', Store::GUARD . json_encode($users));
        $e = client($r2bPort);
        $e->login('redac-r2b@example.com', 'Mot-de-passe-redac-r2b');
        $redirect($e, ['from' => '/a-propos', 'to' => 'https://x.example.invalid/']);
        $wk = client($r2bPort)->req('GET', '/.well-known/change-password');
        check('écran Redirections : un rédacteur qui redirige la cible d’une adresse réservée ne réécrit pas cette adresse',
            $target('/.well-known/change-password') === '/a-propos' && $target('/a-propos') === 'https://x.example.invalid/'
            && $wk['status'] === 301 && str_ends_with($wk['headers']['location'] ?? '', '/a-propos'),
            json_encode([$target('/.well-known/change-password'), $target('/a-propos'), $wk['status'], $wk['headers']['location'] ?? '']));
        $redirect($e, ['action' => 'delete', 'from' => '/a-propos']);
        $about = (string) array_search('a-propos', array_column($read('index'), 'slug', 'id'), true);
        preg_match('~"csrf":"([a-f0-9]+)"~', $e->req('GET', '/gestion')['body'], $m);
        $del = $e->req('POST', '/gestion/api/delete', (string) json_encode(['id' => $about, 'redirect' => 'https://x.example.invalid/']),
            ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')]);
        check('suppression de page avec redirection par un rédacteur : l’adresse réservée garde sa cible, la redirection de la page est posée',
            $about !== '' && $del['status'] === 200 && $target('/.well-known/change-password') === '/a-propos' && $target('/a-propos') === 'https://x.example.invalid/',
            $del['status'] . ' ' . json_encode([$target('/.well-known/change-password'), $target('/a-propos')]));
        $redirect($e, ['from' => '/ancienne-offre', 'to' => '/offre']);
        $redirect($e, ['from' => '/offre', 'to' => '/nouvelle-offre']);
        check('fusion ordinaire des chaînes toujours faite (A→B puis B→C donne A→C)', $target('/ancienne-offre') === '/nouvelle-offre' && $target('/offre') === '/nouvelle-offre',
            json_encode([$target('/ancienne-offre'), $target('/offre')]));
    } finally {
        proc_terminate($r2bSrv);
        exec('rm -rf ' . escapeshellarg($r2bCopy));
    }

    /* ───────────── Images : dépense comptée même quand le fournisseur refuse ou perd l'image ───────────── */
    section('1.10.4 (lot B) : images refusées par le filtre du fournisseur ou perdues après l’envoi, comptées dans le budget du mois');
    $r2bAi = sys_get_temp_dir() . '/eclair-r2b-ai-' . getmypid();
    exec('rm -rf ' . escapeshellarg($r2bAi));
    foreach (['fake', 'root', 'data'] as $dir) {
        @mkdir($r2bAi . '/' . $dir, 0755, true);
    }
    [$r2bFakePort, $r2bFake] = start_server([__DIR__ . '/fake-ai.php'], ['FAKE_AI_DIR' => $r2bAi . '/fake', 'PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
    try {
        $res = review_child(<<<'PHP'
            define('ECLAIR_AI_ENDPOINTS', ['bfl' => $args['fake'], 'ideogram' => $args['fake']]);
            // Budget du mois de 0,20 $ : prix de liste 0,06 $ (Ideogram) et 0,05 $ (FLUX, qui annonce 0,048 $).
            Eclair\Settings::save(['ai' => ['image' => 'ideogram', 'budget' => 0.2]]);
            Eclair\Vault::set('image.ideogram', 'ideo-test-key-0123456789');
            $try = static function (string $prompt): array {
                $error = '';
                try {
                    Eclair\AiImage::generate($prompt, 'landscape', '', '', ['id' => 'u1']);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
                $u = Eclair\Ai::usage();
                return ['error' => $error, 'cost' => round((float) $u['cost'], 6), 'images' => (int) $u['images']];
            };
            $out = ['ideoModerated' => $try('Photo MODERE d’un atelier de céramique, lumière douce'),
                'ideoLost' => $try('Photo PERDUE d’un atelier de céramique, lumière douce')];
            Eclair\Settings::save(['ai' => ['image' => 'bfl']]);
            Eclair\Vault::set('image.bfl', 'cle-refusee-0123456789');
            $out['bflRefused'] = $try('Photo d’un atelier de céramique, lumière douce');
            Eclair\Vault::set('image.bfl', 'bfl-test-key-0123456789');
            $out['bflModerated'] = $try('Photo MODERE d’un atelier de céramique, lumière douce');
            $out['bflOver'] = $try('Photo MODERE d’un atelier de céramique, lumière douce');
            echo json_encode($out);
            PHP, ['root' => $r2bAi . '/root', 'data' => $r2bAi . '/data', 'fake' => 'http://127.0.0.1:' . $r2bFakePort]);
        $posts = count(array_filter(explode("\n", (string) @file_get_contents($r2bAi . '/fake/log.jsonl')),
            static fn (string $l): bool => ($j = json_decode($l, true)) && ($j['p'] ?? '') === '/v1/flux-3-image'));
        $at = static fn (string $k, string $f): mixed => $res[$k][$f] ?? null;
        check('Ideogram : image refusée par son filtre (is_image_safe=false), prix de liste compté dans la dépense du mois',
            $at('ideoModerated', 'error') !== '' && $at('ideoModerated', 'cost') === 0.06 && $at('ideoModerated', 'images') === 0, json_encode($res['ideoModerated'] ?? $res));
        check('Ideogram : image créée puis introuvable au téléchargement (échec après l’envoi), comptée aussi', $at('ideoLost', 'cost') === 0.12, json_encode($res['ideoLost'] ?? null));
        check('clé refusée avant toute création : rien n’est compté', $at('bflRefused', 'error') !== '' && $at('bflRefused', 'cost') === 0.12, json_encode($res['bflRefused'] ?? null));
        check('FLUX : travail accepté puis « Content Moderated », coût annoncé par le fournisseur compté (0,048 $)',
            $at('bflModerated', 'error') !== '' && $at('bflModerated', 'cost') === 0.168, json_encode($res['bflModerated'] ?? null));
        check('budget du mois atteint par ces images : demande suivante refusée avant d’être envoyée au fournisseur',
            stripos((string) $at('bflOver', 'error'), 'budget') !== false && $at('bflOver', 'cost') === 0.168 && $posts === 2,
            json_encode($res['bflOver'] ?? null) . ' ' . $posts . ' envoi(s)');
    } finally {
        proc_terminate($r2bFake);
        exec('rm -rf ' . escapeshellarg($r2bAi));
    }

    /* ───────────── Ré-annonce d'une version : seulement l'archive déjà signée ───────────── */
    $r2bGit = trim((string) shell_exec('command -v git 2>/dev/null'));
    if ($r2bGit !== '' && function_exists('sodium_crypto_sign_keypair') && class_exists('ZipArchive')) {
        section('1.10.4 (lot B) : --resign=<version> ne signe que l’archive déjà signée pour cette version (historique Git de dist/latest.json)');
        $repo = sys_get_temp_dir() . '/eclair-r2b-build-' . getmypid();
        exec('rm -rf ' . escapeshellarg($repo));
        @mkdir($repo . '/dist', 0755, true);
        // Copie de travail : code, outils et traductions de l'arbre de travail, avec une clé jetable.
        exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && git ls-files -z --cached --others --exclude-standard -- eclair tools i18n'
            . ' | xargs -0 -I{} sh -c ' . escapeshellarg('test -e "$1" && cp --parents "$1" "$2"') . ' _ {} ' . escapeshellarg($repo));
        $pair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($pair);
        $upd = $repo . '/eclair/app/lib/Update.php';
        file_put_contents($upd, (string) preg_replace("~private const KEY = '[^']+'~", "private const KEY = '" . base64_encode(sodium_crypto_sign_publickey($pair)) . "'", (string) file_get_contents($upd)));
        $git = static fn (string $args): int => (int) (exec('cd ' . escapeshellarg($repo) . ' && git ' . $args . ' >/dev/null 2>&1; echo $?') ?: 1);
        $git('init -q && git config user.email t@example.com && git config user.name Test && git add -A && git commit -qm base');
        $outs = [];
        $build = static function (array $args = [], array $env = []) use ($repo, $secret, &$outs): array {
            $p = proc_open(array_merge([PHP_BINARY, $repo . '/tools/build.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $repo,
                $env + ['PATH' => (string) getenv('PATH'), 'ECLAIR_SIGNING_KEY' => base64_encode($secret)]);
            $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            $outs[] = $out;
            return ['rc' => proc_close($p), 'out' => $out];
        };
        $announced = static fn (): array => json_decode((string) (json_decode((string) @file_get_contents($repo . '/dist/latest.json'), true)['v2']['payload'] ?? ''), true) ?: [];
        $made = $build(['--sign']);
        $zip = (glob($repo . '/dist/eclair-cms-*.zip') ?: [''])[0];
        $version = preg_match('~eclair-cms-(\d+\.\d+\.\d+)\.zip$~', $zip, $v) ? $v[1] : '';
        $git('add dist/latest.json && git commit -qm publication');
        $original = (string) @file_get_contents($zip);
        $first = $announced();
        // Archive remplacée dans dist/ par quelqu'un qui n'a pas la clé : même numéro de version dans son manifeste.
        $altered = $repo . '/altered.zip';
        copy($zip, $altered);
        $za = new ZipArchive();
        if ($za->open($altered) === true) {
            $za->addFromString('eclair/app/lib/Porte.php', "<?php\n// ajouté sans la clé\n");
            $za->close();
        }
        copy($altered, $zip);
        $before = (string) @file_get_contents($repo . '/dist/latest.json');
        $refused = $build(['--resign=' . $version]);
        check('archive altérée gardant son numéro de version : refusée, annonce inchangée', $made['rc'] === 0 && $version !== '' && $refused['rc'] !== 0
            && str_contains($refused['out'], 'aucune archive déjà signée') && (string) @file_get_contents($repo . '/dist/latest.json') === $before, $made['out'] . $refused['out']);
        $wrong = $build(['--resign=' . $version], ['ECLAIR_EXPECT_SHA256' => hash('sha256', $original)]);
        check('empreinte attendue (ECLAIR_EXPECT_SHA256) différente de celle de l’archive : refusée', $wrong['rc'] !== 0
            && (string) @file_get_contents($repo . '/dist/latest.json') === $before, $wrong['out']);
        file_put_contents($zip, $original);
        $signed = $build(['--resign=' . $version]);
        $now = $announced();
        check('archive d’origine (empreinte déjà signée pour cette version) : ré-annoncée avec un numéro plus grand', $signed['rc'] === 0
            && ($now['sha256'] ?? '') === hash('sha256', $original) && ($now['size'] ?? 0) === strlen($original) && ($now['version'] ?? '') === $version
            && (int) ($now['serial'] ?? 0) > (int) ($first['serial'] ?? PHP_INT_MAX), $signed['out']);
        copy($altered, $zip);
        $vouched = $build(['--resign=' . $version], ['ECLAIR_EXPECT_SHA256' => hash_file('sha256', $altered)]);
        check('archive absente de l’historique signé mais empreinte donnée par le mainteneur (ECLAIR_EXPECT_SHA256) : signée',
            $vouched['rc'] === 0 && ($announced()['sha256'] ?? '') === hash_file('sha256', $altered), $vouched['out']);
        check('clé jamais affichée', !str_contains(implode("\n", $outs), base64_encode($secret)));
        exec('rm -rf ' . escapeshellarg($repo));
    }
}
