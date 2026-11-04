<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité de la 1.10.4, lot A (inclus par tests/run.php, après tests/sec-review.php) : reconstruction de l'index
 * sans republier un contenu supprimé, accès retirés effacés (une sauvegarde restaurée ne les réveille plus), cache vidé
 * même quand une écriture échoue après une dépublication, installateur verrouillé après la perte de data/site.php,
 * fichiers temporaires et archives créés privés, retour arrière qui garde un .htaccess modifié depuis la mise à jour.
 */
declare(strict_types=1);

use Eclair\Store;

$r2aTmp = sys_get_temp_dir() . '/eclair-r2a-' . getmypid();
exec('rm -rf ' . escapeshellarg($r2aTmp));
@mkdir($r2aTmp, 0755, true);

/* ───────────── Reconstruction de l'index : un fichier que l'index ne connaît pas revient en brouillon ───────────── */
section('1.10.4 (lot A) : reconstruction de l’index, contenu supprimé avant la 1.10.3 jamais republié');
@mkdir($r2aTmp . '/v01/root/cache/pages', 0755, true);
@mkdir($r2aTmp . '/v01/data', 0755, true);
$r2aRes = review_child(<<<'PHP'
    Eclair\Store::write('site', ['name' => 'Test', 'installed' => time(), 'version' => ECLAIR_VERSION]);
    $u = ['id' => 'r2aauteur', 'role' => 'admin', 'name' => 'Camille R2A', 'email' => 'camille@example.com'];
    $page = static fn (string $title): array => Eclair\Content::save(['type' => 'page', 'status' => 'published', 'title' => $title,
        'blocks' => [['type' => 'paragraph', 'text' => 'Texte de ' . $title . '.']]], $u);
    $x = $page('Page retirée');
    $y = $page('Page gardée');
    $path = static fn (array $item): string => Eclair\Content::path(Eclair\Content::entry($item));
    // Suppression ratée en 1.10.0 à 1.10.2 : l'index ne la liste plus, son fichier est resté avec le statut publié.
    $index = Eclair\Store::read('index');
    unset($index[$x['id']]);
    Eclair\Store::write('index', $index);
    Eclair\Content::forget();
    $rebuilt = Eclair\Content::reindex();
    $out = ['index' => $rebuilt[$x['id']]['status'] ?? null, 'file' => Eclair\Content::get($x['id'])['status'] ?? null,
        'found' => Eclair\Content::findByPath($path($x)) !== null, 'other' => $rebuilt[$y['id']]['status'] ?? null];
    // Index perdu (illisible) : reconstruction comme avant, chaque contenu avec son statut enregistré.
    unlink(ECLAIR_DATA . '/index.php');
    Eclair\Store::forget();
    Eclair\Content::forget();
    $out['auto'] = Eclair\Content::index()[$y['id']]['status'] ?? null;
    $out['autoFound'] = Eclair\Content::findByPath($path($y)) !== null;
    echo json_encode($out);
    PHP, ['root' => $r2aTmp . '/v01/root', 'data' => $r2aTmp . '/v01/data']);
check('fichier absent de l’index lisible (suppression ratée avant la 1.10.3) : réintégré en brouillon, jamais republié',
    ($r2aRes['index'] ?? '') === 'draft' && ($r2aRes['file'] ?? '') === 'draft' && ($r2aRes['found'] ?? true) === false && ($r2aRes['other'] ?? '') === 'published',
    json_encode($r2aRes));
check('sans index lisible : reconstruction inchangée, contenus gardés avec leur statut', ($r2aRes['auto'] ?? '') === 'published' && ($r2aRes['autoFound'] ?? false) === true,
    json_encode($r2aRes));

/* ───────────── Fichiers temporaires et archives de sauvegarde : privés dès leur création ───────────── */
section('1.10.4 (lot A) : fichier temporaire et archive de sauvegarde privés dès leur création, dossier des données resserré');
// Droits de chaque fichier juste avant que l'application ne pose les siens (fin de Store::atomic, fin de Backup::create).
file_put_contents($r2aTmp . '/droits.php', <<<'PHP'
    <?php
    namespace Eclair;

    function chmod(string $file, int $mode): bool
    {
        clearstatcache(true, $file);
        if (is_file($file)) {
            $GLOBALS['r2aModes'][basename($file)] = fileperms($file) & 0777;
        }
        return \chmod($file, $mode);
    }
    PHP);
@mkdir($r2aTmp . '/v10/root', 0755, true);
$r2aRes = review_child(<<<'PHP'
    require $args['probe'];
    umask(0022);
    mkdir(ECLAIR_DATA, 0755); // dossier des données livré avec l'archive, envoyé par FTP
    Eclair\Store::write('users', ['u1' => ['id' => 'u1', 'email' => 'camille@example.com', 'totp' => ['on' => true, 'secret' => 'JBSWY3DPEHPK3PXP']]]);
    $zip = class_exists('ZipArchive');
    if ($zip) {
        Eclair\Backup::create(ECLAIR_ROOT . '/cache/backup-r2a.zip');
    }
    clearstatcache();
    echo json_encode(['modes' => $GLOBALS['r2aModes'] ?? [], 'data' => fileperms(ECLAIR_DATA) & 0777, 'umask' => umask(), 'zip' => $zip]);
    PHP, ['root' => $r2aTmp . '/v10/root', 'data' => $r2aTmp . '/v10/data', 'probe' => $r2aTmp . '/droits.php']);
$r2aTemp = array_values(array_filter((array) ($r2aRes['modes'] ?? []), static fn (string $name): bool => (bool) preg_match('~^users\.php\.[a-f0-9]{8}\.tmp\.php$~', $name),
    ARRAY_FILTER_USE_KEY));
check('users.php : fichier temporaire déjà privé (0600) quand il reçoit son contenu, sous un masque 0022', $r2aTemp === [0600], json_encode($r2aRes));
check('archive de sauvegarde : déjà privée (0600) dès sa création', ($r2aRes['zip'] ?? false) === false || (($r2aRes['modes']['backup-r2a.zip'] ?? null) === 0600), json_encode($r2aRes));
check('dossier des données envoyé en 755 : resserré à 0700, masque du processus rendu tel quel',
    ($r2aRes['data'] ?? 0) === 0700 && ($r2aRes['umask'] ?? 0) === 0022, json_encode($r2aRes));

/** Copie d'Éclair servie comme serve(), avec un fichier chargé avant chaque requête (pannes simulées). */
function r2a_serve(string $name, string $prepend): array
{
    $copy = sys_get_temp_dir() . '/eclair-it-' . $name . '-' . getmypid();
    exec('rm -rf ' . escapeshellarg($copy) . ' && cp -r ' . escapeshellarg(ECLAIR_ROOT) . ' ' . escapeshellarg($copy));
    foreach (['data/*.php', 'data/*/', 'cache/*', 'media/20*', 'media/site'] as $glob) {
        exec('rm -rf ' . $copy . '/' . $glob);
    }
    // Le serveur intégré n'applique pas auto_prepend_file au routeur : un routeur qui charge le fichier, puis celui des tests.
    $router = dirname($prepend) . '/router-' . $name . '.php';
    file_put_contents($router, '<?php require ' . var_export($prepend, true) . ";\nreturn require " . var_export(dirname(__DIR__) . '/tools/router.php', true) . ";\n");
    [$port, $server] = start_server(['-t', $copy, $router], ['PHP_CLI_SERVER_WORKERS' => '4']);
    return [$copy, $port, $server];
}

if (!in_array('--unit', $argv, true)) {
    // Disque plein simulé : réécriture de data/search.php ou de cache/runtime.php impossible tant que le témoin existe.
    file_put_contents($r2aTmp . '/pannes.php', <<<'PHP'
        <?php
        namespace Eclair;

        function file_put_contents(string $file, mixed $data, int $flags = 0): int|false
        {
            foreach (['search.php.' => 'panne-search', 'runtime.php.' => 'panne-runtime'] as $prefix => $flag) {
                if (str_starts_with(basename($file), $prefix) && is_file(__DIR__ . '/' . $flag)) {
                    return false;
                }
            }
            return \file_put_contents($file, $data, $flags);
        }
        PHP);
    [$aCopy, $aPort, $aServer] = r2a_serve('r2a', $r2aTmp . '/pannes.php');
    try {
        $pass = 'Un-mot-de-passe-solide';
        $install = static function (object $c, string $email): array {
            $form = $c->req('GET', '/');
            return $c->req('POST', '/', $c->token($form['body']) + ['ui' => 'fr', 'site_name' => 'Atelier R2A', 'site_tagline' => 'Céramique', 'site_lang' => 'fr',
                'site_type' => 'blog', 'user_name' => 'Camille R2A', 'user_email' => $email, 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1',
                'ai' => 'open', 'admin_path' => 'gestion'], ['Accept: application/json']);
        };
        $install(client($aPort), 'camille@example.com');
        $admin = static function (string $email = 'camille@example.com', string $password = 'Un-mot-de-passe-solide') use ($aPort): object {
            $a = client($aPort);
            $a->login($email, $password);
            return $a;
        };
        $form = static function (object $a, string $page, array $fields): array {
            $p = $a->req('GET', '/gestion/' . $page);
            return $a->req('POST', '/gestion/' . $page, $a->token($p['body']) + $fields);
        };
        $c = $admin();
        preg_match('/"csrf":"([a-f0-9]+)"/', $c->req('GET', '/gestion')['body'], $m);
        $csrf = $m[1] ?? '';
        $api = static function (string $path, array $data) use ($c, $csrf): array {
            $res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data), ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
            return $res + ['json' => (array) json_decode($res['body'], true)];
        };
        $visitor = client($aPort);
        $hit = static fn (array $res): bool => ($res['headers']['x-cache'] ?? '') === 'HIT';
        $publish = static function (string $marker) use ($api, $visitor): array {
            $item = (array) ($api('api/save', ['type' => 'post', 'title' => 'Billet ' . $marker, 'status' => 'published',
                'blocks' => [['type' => 'paragraph', 'text' => 'Texte ' . $marker . '.']]])['json']['item'] ?? []);
            $url = '/blog/' . ($item['slug'] ?? 'x');
            $visitor->req('GET', $url);
            return [$item, $url, $visitor->req('GET', $url)];
        };
        $index = static fn (): array => (array) json_decode(Store::strip((string) @file_get_contents($aCopy . '/data/index.php')), true);

        /* ───────────── Écriture interrompue après une dépublication ou une suppression : cache vidé quand même ───────────── */
        section('1.10.4 (lot A) : écriture de search.php ou de cache/runtime.php ratée, page retirée plus servie par le cache');
        [$post, $url, $warm] = $publish('RETRAITRDA');
        touch($r2aTmp . '/panne-search');
        $off = $api('api/save', ['status' => 'draft'] + $post);
        @unlink($r2aTmp . '/panne-search');
        $after = $visitor->req('GET', $url);
        check('dépublication, puis échec de la réécriture de search.php : la page n’est plus servie par le cache',
            $hit($warm) && $off['status'] >= 500 && ($index()[$post['id'] ?? '']['status'] ?? '') === 'draft' && !$hit($after) && !str_contains($after['body'], 'RETRAITRDA'),
            json_encode([$off['status'], $after['status'], $after['headers']['x-cache'] ?? '-']));
        [$post, $url, $warm] = $publish('SUPPRRDA');
        touch($r2aTmp . '/panne-search');
        $del = $api('api/delete', ['id' => $post['id'] ?? '']);
        @unlink($r2aTmp . '/panne-search');
        $after = $visitor->req('GET', $url);
        check('suppression, puis échec de la réécriture de search.php : la page n’est plus servie par le cache',
            $hit($warm) && $del['status'] >= 500 && !isset($index()[$post['id'] ?? '']) && !$hit($after) && !str_contains($after['body'], 'SUPPRRDA'),
            json_encode([$del['status'], $after['status'], $after['headers']['x-cache'] ?? '-']));
        // Nouvelle génération impossible à publier : cache/runtime.php retiré, une page rendue avant la purge (et écrite
        // après elle, avec l'ancienne génération) n'est jamais servie.
        [$post, $url, $warm] = $publish('GENRDA');
        $late = (string) @file_get_contents($aCopy . '/cache/pages/' . md5($url));
        touch($r2aTmp . '/panne-runtime');
        $off = $api('api/save', ['status' => 'draft'] + $post);
        clearstatcache(); // fichiers changés par le serveur : jamais l'état gardé en mémoire par ce processus
        $closed = !is_file($aCopy . '/cache/runtime.php');
        @unlink($r2aTmp . '/panne-runtime');
        file_put_contents($aCopy . '/cache/pages/' . md5($url), $late);
        $after = $visitor->req('GET', $url);
        check('cache/runtime.php impossible à réécrire : retiré, le chemin rapide se ferme (page retirée jamais servie)',
            $hit($warm) && $late !== '' && ($off['json']['ok'] ?? false) === true && $closed && !$hit($after) && !str_contains($after['body'], 'GENRDA'),
            json_encode([$off['status'], $closed, $after['status'], $after['headers']['x-cache'] ?? '-']));
        $visitor->req('GET', '/');
        check('cache/runtime.php réécrit à la purge suivante : pages de nouveau servies depuis le cache', $hit($visitor->req('GET', '/')));

        /* ───────────── Accès retirés : effacés, une sauvegarde restaurée de data/users.php ne les réveille plus ───────────── */
        section('1.10.4 (lot A) : accès retirés effacés, la restauration d’une sauvegarde ne réveille ni session ni connexion MCP');
        settings($aCopy, ['ai' => ['mcp' => true]]);
        $usersFile = $aCopy . '/data/users.php';
        $account = static function (string $email) use ($usersFile): array {
            foreach ((array) json_decode(Store::strip((string) file_get_contents($usersFile)), true) as $u) {
                if (is_array($u) && ($u['email'] ?? '') === $email) {
                    return $u;
                }
            }
            return [];
        };
        // Connexion MCP de ce compte telle que l'enregistre l'échange OAuth (jetons gardés par leur empreinte).
        $grant = static function (array $u) use ($aCopy): string {
            $file = $aCopy . '/data/ai/mcp.php';
            $token = bin2hex(random_bytes(32));
            $d = (array) json_decode(Store::strip((string) @file_get_contents($file)), true) + ['grants' => [], 'codes' => []];
            $d['grants']['g' . bin2hex(random_bytes(5))] = ['a' => hash('sha256', $token), 'ae' => time() + 3600, 'r' => hash('sha256', 'r' . $token),
                're' => time() + 86400, 'p' => '', 'used' => time(), 'user' => (string) $u['id'], 'ver' => (int) ($u['ver'] ?? 1), 'client' => 'Claude',
                'host' => 'claude.ai', 'cid' => substr(hash('sha256', 'r2a'), 0, 32), 'created' => time()];
            @mkdir(dirname($file), 0700, true);
            file_put_contents($file, Store::GUARD . json_encode($d));
            return $token;
        };
        $alive = static function (object $device, string $token) use ($aPort): array {
            $rpc = client($aPort)->req('POST', '/_eclair/mcp', '{"jsonrpc":"2.0","id":1,"method":"ping"}', ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
            return [$device->req('GET', '/gestion')['status'] === 200, $rpc['status'] === 200];
        };
        // Une session sur un autre appareil et une connexion MCP, une sauvegarde de data/users.php, la révocation, puis la
        // sauvegarde remise par FTP (ancienne version du compte) : ni l'une ni l'autre ne doit revenir.
        $scenario = static function (string $label, string $email, string $password, callable $revoke) use ($admin, $account, $grant, $alive, $usersFile): void {
            $device = $admin($email, $password);
            $u = $account($email);
            $token = $grant($u);
            $live = $alive($device, $token);
            $backup = (string) file_get_contents($usersFile);
            $revoke();
            $now = $account($email);
            $revoked = $now === [] || (int) ($now['ver'] ?? 1) > (int) ($u['ver'] ?? 1);
            file_put_contents($usersFile, $backup);
            $after = $alive($device, $token);
            check($label, $live === [true, true] && $revoked && $after === [false, false], json_encode(['avant' => $live, 'révoqué' => $revoked, 'après' => $after]));
        };
        $scenario('« Déconnecter tout le monde », puis sauvegarde restaurée : session et connexion MCP toujours refusées', 'camille@example.com', $pass,
            static fn () => $form($admin(), 'security', ['action' => 'revoke-all']));
        $scenario('« Déconnecter mes autres appareils », puis sauvegarde restaurée : toujours refusées', 'camille@example.com', $pass,
            static fn () => $form($admin(), 'profile', ['action' => 'revoke']));
        $scenario('nouveau mot de passe (Mon profil), puis sauvegarde restaurée : toujours refusées', 'camille@example.com', $pass,
            static fn () => $form($admin(), 'profile', ['action' => 'password', 'current' => $pass, 'password' => 'Nouveau-Mot-De-Passe-R2A']));
        $scenario('secours par FTP (data/reset.php), puis sauvegarde restaurée : toujours refusées', 'camille@example.com', $pass,
            static function () use ($aCopy, $admin): void {
                file_put_contents($aCopy . '/data/reset.php', "<?php exit; ?>\ncamille@example.com\nSecours-Ftp-R2A-Solide\n");
                $admin('camille@example.com', 'Secours-Ftp-R2A-Solide');
            });
        // Second administrateur, réinitialisé puis supprimé par Camille.
        $a = $admin();
        $form($a, 'users', ['name' => 'Dominique R2A', 'email' => 'dominique@example.com', 'role' => 'admin']);
        preg_match('/([A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4})/', $a->req('GET', '/gestion/users')['body'], $tmp);
        $form($admin('dominique@example.com', $tmp[1] ?? 'x'), 'profile', ['action' => 'password', 'current' => $tmp[1] ?? 'x', 'password' => 'Plume-Bleue-Solide-R2A']);
        $did = (string) ($account('dominique@example.com')['id'] ?? 'x');
        $scenario('mot de passe réinitialisé par un administrateur, puis sauvegarde restaurée : toujours refusées', 'dominique@example.com', 'Plume-Bleue-Solide-R2A',
            static fn () => $form($admin(), 'users/' . $did, ['action' => 'reset']));
        $scenario('compte supprimé, puis sauvegarde restaurée : session et connexion MCP toujours refusées', 'dominique@example.com', 'Plume-Bleue-Solide-R2A',
            static fn () => $form($admin(), 'users/' . $did, ['action' => 'delete']));
        $other = $admin();
        $device = $admin();
        $form($other, 'profile', ['action' => 'revoke']);
        check('la session qui fait la révocation reste ouverte, les autres du compte sont fermées',
            $other->req('GET', '/gestion')['status'] === 200 && $device->req('GET', '/gestion')['status'] !== 200);

        /* ───────────── Installateur : fenêtre gardée après l'installation, réinstallation avec l'accès FTP seulement ───────────── */
        section('1.10.4 (lot A) : data/site.php perdu, installateur verrouillé tant que la fenêtre n’est pas supprimée par FTP');
        $window = $aCopy . '/data/install-window.php';
        $closedWindow = static fn (): bool => clearstatcache() === null && is_file($window) && time() - (int) Store::strip((string) file_get_contents($window)) >= 7200;
        @unlink($window); // site installé avant la 1.10.4 : la fenêtre a été supprimée à la fin de l'installation
        $admin()->req('GET', '/gestion');
        check('site installé avant la 1.10.4 : fenêtre refermée à la première visite de l’administration', $closedWindow());
        $users = static fn (): int => count((array) json_decode(Store::strip((string) file_get_contents($usersFile)), true));
        $keys = static fn (): string => (string) ((array) json_decode(Store::strip((string) file_get_contents($aCopy . '/data/keys.php')), true))['secret'];
        [$countBefore, $keyBefore] = [$users(), $keys()];
        $contents = count(glob($aCopy . '/data/content/*.php') ?: []);
        @unlink($aCopy . '/data/site.php');
        @unlink($aCopy . '/cache/runtime.php');
        // Requête directe d'un inconnu : jeton du formulaire choisi par lui-même (cookie et champ identiques), sans Origin.
        $ft = bin2hex(random_bytes(16));
        $intrusion = client($aPort)->req('POST', '/', ['_ft' => $ft, 'ui' => 'fr', 'site_name' => 'Pirate', 'site_lang' => 'fr', 'site_type' => 'blog',
            'user_name' => 'Pirate', 'user_email' => 'pirate@example.com', 'user_pass' => 'Un-mot-de-passe-pirate', 'index' => '1', 'ai' => 'open', 'admin_path' => 'pirate'],
            ['Cookie: eclair_ft=' . $ft, 'Accept: application/json']);
        $page = client($aPort)->req('GET', '/');
        clearstatcache();
        check('data/site.php perdu, fenêtre gardée : installateur verrouillé (403), aucun compte ni réglage créé',
            $intrusion['status'] === 403 && $page['status'] === 403 && str_contains($page['body'], 'install-window.php') && !is_file($aCopy . '/data/site.php')
            && $users() === $countBefore, $intrusion['status'] . ' ' . substr($intrusion['body'], 0, 120));
        @unlink($window); // preuve d'accès au serveur : supprimée par FTP
        $taken = $install(client($aPort), 'camille@example.com');
        $again = $install(client($aPort), 'claude.r2a@example.com');
        check('fenêtre supprimée par FTP : réinstallation comme en 1.10.3 (e-mail existant refusé ; comptes, clés et contenus gardés), fenêtre refermée',
            $taken['status'] === 422 && $again['status'] === 200 && $users() === $countBefore + 1 && $keys() === $keyBefore
            && count(glob($aCopy . '/data/content/*.php') ?: []) >= $contents && $closedWindow(), $taken['status'] . ' ' . $again['status'] . ' ' . $users());
    } finally {
        proc_terminate($aServer);
        exec('rm -rf ' . escapeshellarg($aCopy));
    }
}

/* ───────────── Retour arrière : .htaccess et protections modifiés depuis la mise à jour gardés et signalés ───────────── */
if (!in_array('--unit', $argv, true) && function_exists('sodium_crypto_sign_keypair') && class_exists('ZipArchive')) {
    section('1.10.4 (lot A) : retour à la version précédente, .htaccess et protections modifiés depuis la mise à jour gardés');
    [$uCopy, $uPort, $uServer] = serve('r2a-update');
    $uChannel = $r2aTmp . '/canal';
    @mkdir($uChannel, 0755, true);
    [$uChanPort, $uChan] = start_server(['-t', $uChannel], ['PHP_CLI_SERVER_WORKERS' => '2'], 50001, 60000);
    try {
        $pair = sodium_crypto_sign_keypair();
        file_put_contents($uCopy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_UPDATE_URL', 'http://127.0.0.1:$uChanPort/latest.json');\n"
            . "define('ECLAIR_UPDATE_KEY', '" . base64_encode(sodium_crypto_sign_publickey($pair)) . "');\n");
        preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($uCopy . '/index.php'), $vm);
        $uCurrent = $vm[1] ?? '';
        file_put_contents($uCopy . '/app/manifest.php', eclair_manifest($uCurrent, eclair_code($uCopy)));
        $c = client($uPort);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + ['ui' => 'fr', 'site_name' => 'Atelier R2A', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille R2A', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion'],
            ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $post = static function (array $fields) use ($c): string {
            $page = $c->req('GET', '/gestion/update');
            $c->req('POST', '/gestion/update', $c->token($page['body']) + $fields);
            return $c->req('GET', '/gestion/update')['body'];
        };
        $read = static fn (string $path): string => (string) @file_get_contents($uCopy . '/' . $path);
        $originals = ['.htaccess' => $read('.htaccess'), 'data/.htaccess' => $read('data/.htaccess'), 'eclair-config.exemple.php' => $read('eclair-config.exemple.php')];
        $release = eclair_release($uChannel, '9.9.9', static function (array $f): array {
            $f['data/.htaccess'] .= "# protection 9.9.9\n";
            $f['eclair-config.exemple.php'] .= "// modèle 9.9.9\n";
            $f['cache/pages/.htaccess'] = "Require all denied\n";
            $f['cache/pages/index.html'] = '';
            return $f;
        });
        eclair_announce($uChannel, $uChanPort, $release, sodium_crypto_sign_secretkey($pair));
        $post(['action' => 'check']);
        $body = $post(['action' => 'install', 'saved' => '1']);
        $updated = str_contains($body, 'Éclair est passé à la version 9.9.9') && is_file($uCopy . '/cache/pages/index.html');
        // Après la mise à jour : l'hébergeur complète le .htaccess, le propriétaire retouche deux protections de dossier.
        file_put_contents($uCopy . '/.htaccess', "\n# Règle ajoutée par l'hébergeur après la mise à jour\n", FILE_APPEND);
        file_put_contents($uCopy . '/data/.htaccess', "# Règle locale R2A\n", FILE_APPEND);
        file_put_contents($uCopy . '/cache/pages/.htaccess', "# Règle locale R2A\n", FILE_APPEND);
        $body = $post(['action' => 'rollback']);
        clearstatcache();
        preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", $read('index.php'), $vm);
        check('retour arrière : .htaccess, data/.htaccess et protection ajoutée, modifiés depuis la mise à jour, gardés tels quels',
            $updated && ($vm[1] ?? '') === $uCurrent && str_contains($read('.htaccess'), "Règle ajoutée par l'hébergeur après la mise à jour")
            && str_ends_with($read('data/.htaccess'), "# protection 9.9.9\n# Règle locale R2A\n") && str_ends_with($read('cache/pages/.htaccess'), "# Règle locale R2A\n"),
            json_encode(['maj' => $updated, 'version' => $vm[1] ?? '', 'data' => substr($read('data/.htaccess'), -40)], JSON_UNESCAPED_UNICODE));
        check('retour arrière : fichiers gardés signalés, leur version d’avant rangée dans cache/update',
            str_contains($body, 'Fichiers modifiés depuis la mise à jour, conservés tels quels : .htaccess, data/.htaccess, cache/pages/.htaccess. Leur version')
            && str_contains($body, 'Retour à la version ' . $uCurrent . ' effectué')
            && $read('cache/update/htaccess-' . $uCurrent . '.txt') === $originals['.htaccess'] && $read('cache/update/data~.htaccess-' . $uCurrent . '.txt') === $originals['data/.htaccess'],
            substr(strip_tags($body), 0, 300));
        check('retour arrière : fichiers hors code non retouchés remis comme avant, protection ajoutée non retouchée retirée',
            $read('eclair-config.exemple.php') === $originals['eclair-config.exemple.php'] && !is_file($uCopy . '/cache/pages/index.html') && !is_dir($uCopy . '/cache/update/previous'),
            json_encode(['modèle' => $read('eclair-config.exemple.php') === $originals['eclair-config.exemple.php'], 'ajout' => is_file($uCopy . '/cache/pages/index.html'),
                'gardée' => is_dir($uCopy . '/cache/update/previous'), 'cache' => array_map('basename', glob($uCopy . '/cache/update/*') ?: [])], JSON_UNESCAPED_UNICODE));
    } finally {
        proc_terminate($uServer);
        proc_terminate($uChan);
        exec('rm -rf ' . escapeshellarg($uCopy));
    }
}
exec('rm -rf ' . escapeshellarg($r2aTmp));
