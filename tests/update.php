<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de la mise à jour en un clic (1.10), inclus par tests/run.php : annonce signée (Ed25519), archive vérifiée
 * (empreinte, chemins, manifeste, syntaxe PHP), échange du code, contrôle de santé et retour automatique, retour manuel.
 * Un faux canal (serveur PHP intégré, fichiers statiques) et une paire de clés de test remplacent GitHub et la clé du mainteneur.
 */
declare(strict_types=1);

use Eclair\Loopback;
use Eclair\Store;

section('Mise à jour en un clic : protocole');
$answer = Loopback::answer(str_repeat('a', 32));
check('ping : la version est prouvée (signée) sans être dévoilée', strlen((string) ($answer['v'] ?? '')) === 32 && $answer['v'] !== $answer['p']
    && !str_contains((string) json_encode($answer), ECLAIR_VERSION));
$source = (string) file_get_contents(ECLAIR_ROOT . '/app/lib/Update.php');
check('clé publique du mainteneur inscrite dans le code', (bool) preg_match("~private const KEY = '([A-Za-z0-9+/]{43}=)'~", $source, $key)
    && strlen((string) base64_decode($key[1], true)) === 32);

/** Code livrable d'une copie d'Éclair (sans données), comme tools/build.php le met dans l'archive. */
function eclair_code(string $root): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $path => $info) {
        $rel = str_replace('\\', '/', substr((string) $path, strlen($root) + 1));
        if ($info->isFile() && (!preg_match('~^(data|cache|media)/~', $rel) || preg_match('~^(data|cache|cache/pages|media)/(\.htaccess|index\.html)$~', $rel))
            && $rel !== 'app/manifest.php' && $rel !== 'eclair-config.php') {
            $files[$rel] = (string) file_get_contents((string) $path);
        }
    }
    ksort($files);
    return $files;
}

function eclair_manifest(string $version, array $files): string
{
    return Store::GUARD . json_encode(['version' => $version, 'generated' => gmdate('c'), 'files' => array_map(static fn (string $c): string => substr(hash('sha256', $c), 0, 32), $files)], JSON_UNESCAPED_SLASHES) . "\n";
}

/** Archive d'une version d'Éclair publiée sur le faux canal ($change modifie le code ; $extra : entrées ajoutées telles quelles). */
function eclair_release(string $channel, string $version, ?callable $change = null, array $extra = []): array
{
    $files = eclair_code(ECLAIR_ROOT);
    $files['index.php'] = (string) preg_replace("~define\('ECLAIR_VERSION', '[^']+'\)~", "define('ECLAIR_VERSION', '$version')", $files['index.php']);
    $files = $change ? $change($files) : $files;
    $name = 'eclair-cms-' . $version . '-' . bin2hex(random_bytes(3)) . '.zip';
    $zip = new ZipArchive();
    $zip->open($channel . '/' . $name, ZipArchive::CREATE);
    foreach ($files + ['app/manifest.php' => eclair_manifest($version, $files)] as $rel => $content) {
        $zip->addFromString('eclair/' . $rel, $content);
    }
    foreach ($extra + ['LISEZ-MOI.txt' => 'Archive de test'] as $entry => $content) {
        $zip->addFromString($entry, $content);
    }
    $zip->close();
    return ['version' => $version, 'file' => $name, 'sha256' => hash_file('sha256', $channel . '/' . $name), 'size' => filesize($channel . '/' . $name), 'files' => $files];
}

/** Annonce signée (latest.json) pour cette archive. */
function eclair_announce(string $channel, int $port, array $release, string $secret, array $override = []): void
{
    $payload = json_encode($override + [
        'version' => $release['version'], 'date' => '2026-11-02', 'url' => 'http://127.0.0.1:' . $port . '/' . $release['file'],
        'sha256' => $release['sha256'], 'size' => $release['size'], 'php' => '8.1', 'notes' => 'https://github.com/Jacckisback/eclair-cms/releases/tag/' . $release['version'],
    ], JSON_UNESCAPED_SLASHES);
    file_put_contents($channel . '/latest.json', json_encode(['payload' => $payload, 'signature' => base64_encode(sodium_crypto_sign_detached($payload, $secret))]));
}

if (!in_array('--unit', $argv, true) && function_exists('sodium_crypto_sign_keypair') && class_exists('ZipArchive')) {
    section('Mise à jour en un clic : annonce signée, archive vérifiée, retour automatique et manuel (faux canal)');
    [$copy, $port, $server] = serve('update');
    $channel = sys_get_temp_dir() . '/eclair-channel-' . getmypid();
    @mkdir($channel);
    // Faux canal : fichiers statiques ; on attend qu'il réponde (autre port si celui-ci est pris).
    file_put_contents($channel . '/index.html', 'canal');
    for ($try = 0; $try < 5; $try++) {
        $chanPort = random_int(50001, 60000);
        $chan = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $chanPort, '-t', $channel], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $chanPipes, null, ['PATH' => (string) getenv('PATH'), 'PHP_CLI_SERVER_WORKERS' => '2']);
        for ($wait = 0; $wait < 30 && @file_get_contents('http://127.0.0.1:' . $chanPort . '/index.html') !== 'canal'; $wait++) {
            usleep(100000);
        }
        if (@file_get_contents('http://127.0.0.1:' . $chanPort . '/index.html') === 'canal') {
            break;
        }
        proc_terminate($chan);
    }
    $pair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    $intruder = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_UPDATE_URL', 'http://127.0.0.1:$chanPort/latest.json');\n"
        . "define('ECLAIR_UPDATE_KEY', '" . base64_encode(sodium_crypto_sign_publickey($pair)) . "');\n");
    // Fichier de cette version que la suivante n'a plus ; manifeste de la copie à jour de son code (le dépôt peut avoir changé
    // depuis la dernière construction).
    file_put_contents($copy . '/app/lib/Ancien.php', "<?php\ndefined('ECLAIR') || exit;\n");
    preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($copy . '/index.php'), $vm);
    $current = $vm[1] ?? '';
    file_put_contents($copy . '/app/manifest.php', eclair_manifest($current, eclair_code($copy)));
    usleep(400000);
    $version = static function () use ($copy): string {
        preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($copy . '/index.php'), $m);
        return $m[1] ?? '';
    };
    $state = static fn (): array => json_decode(Store::strip((string) @file_get_contents($copy . '/data/update.php')), true) ?: [];
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Test', 'site_tagline' => 'Céramique à Nantes', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $post = static function (array $fields) use ($c): string {
            $page = $c->req('GET', '/gestion/update');
            $c->req('POST', '/gestion/update', $c->token($page['body']) + $fields);
            return $c->req('GET', '/gestion/update')['body'];
        };
        // Relit l'annonce publiée sur le faux canal, puis lance l'installation.
        $install = static function (array $more = []) use ($post): string {
            $post(['action' => 'check']);
            return $post(['action' => 'install', 'saved' => '1'] + $more);
        };
        $intact = static fn (): bool => $version() === $current && !is_dir($copy . '/cache/update/stage') && !is_dir($copy . '/cache/update/previous')
            && $c->req('GET', '/')['status'] === 200;

        // Pas d'annonce joignable : la page le dit, rien n'est proposé.
        $page = $c->req('GET', '/gestion/update');
        check('page Mises à jour : version installée, canal injoignable signalé', $page['status'] === 200 && str_contains($page['body'], 'Version installée : ' . $current)
            && str_contains($page['body'], 'GitHub injoignable') && !str_contains($page['body'], 'name="action" value="install"'), (string) $page['status']);
        check('menu : entrée Mises à jour pour les administrateurs', str_contains($page['body'], '<a href="/gestion/update"'));

        // Annonce signée par une autre clé : refusée et journalisée.
        $good = eclair_release($channel, '9.9.9', static function (array $f): array {
            $f['assets/admin/admin.css'] .= "\n/* version-9-9-9 */\n";
            $f['app/lib/Nouveau.php'] = "<?php\ndefined('ECLAIR') || exit;\n";
            return $f;
        });
        eclair_announce($channel, $chanPort, $good, $intruder);
        $body = $post(['action' => 'check']);
        check('annonce signée par une autre clé : refusée', str_contains($body, 'signature ne correspond pas') && !str_contains($body, 'Éclair 9.9.9 est disponible'));
        check('annonce refusée : inscrite au journal de sécurité', str_contains($c->req('GET', '/gestion/security')['body'], 'Annonce de mise à jour refusée'));

        // Annonce valide, mais archive différente de son empreinte : rien ne bouge.
        eclair_announce($channel, $chanPort, $good, $secret, ['sha256' => str_repeat('f', 64)]);
        $body = $post(['action' => 'check']);
        check('nouvelle version annoncée : proposée, avec le rappel de sauvegarde', str_contains($body, 'Éclair 9.9.9 est disponible')
            && str_contains($body, 'Avant toute mise à jour, fais une sauvegarde') && str_contains($body, 'name="saved"') && str_contains($body, 'Télécharger une sauvegarde'));
        $dash = $c->req('GET', '/gestion')['body'];
        check('tableau de bord : bandeau et pastille dans le menu', str_contains($dash, 'data-update-banner') && str_contains($dash, 'Éclair 9.9.9 est disponible (tu as la ' . $current . ')')
            && (bool) preg_match('~<a href="/gestion/update"[^>]*>(?:(?!</a>).)*<span class="count"~s', $dash));
        $body = $post(['action' => 'install']);
        check('sans « J’ai une sauvegarde récente » : refusé', str_contains($body, 'Coche d’abord') && $intact());
        $body = $install();
        check('archive altérée (empreinte) : refusée, rien ne bouge', str_contains($body, 'incomplète ou altérée') && $intact(), $version());

        // Archive piégée : chemin qui sort du dossier.
        $slip = eclair_release($channel, '9.9.9', null, ['eclair/../evil.php' => '<?php echo 1;']);
        eclair_announce($channel, $chanPort, $slip, $secret);
        $body = $install();
        check('archive piégée (../) : refusée, aucun fichier écrit', str_contains($body, 'chemin suspect') && $intact() && !is_file(dirname($copy) . '/evil.php') && !is_file($copy . '/evil.php'));

        // Fichier en trop, absent du manifeste.
        $stray = eclair_release($channel, '9.9.9', null, ['eclair/app/lib/Intrus.php' => "<?php\ndefined('ECLAIR') || exit;\n"]);
        eclair_announce($channel, $chanPort, $stray, $secret);
        $body = $install();
        check('fichier hors manifeste : archive refusée', str_contains($body, 'fichier inattendu') && $intact());

        // Syntaxe PHP illisible par le PHP du serveur : refusée avant tout changement.
        $syntax = eclair_release($channel, '9.9.9', static function (array $f): array {
            $f['app/lib/Casse.php'] = "<?php\ndefined('ECLAIR') || exit;\nfunction (\n";
            return $f;
        });
        eclair_announce($channel, $chanPort, $syntax, $secret);
        $body = $install();
        check('erreur de syntaxe PHP : refusée, rien ne bouge', str_contains($body, 'ne fonctionne pas avec PHP') && $intact());

        // Version qui casse le site public : installée puis annulée d'elle-même par le contrôle de santé.
        $broken = eclair_release($channel, '9.9.9', static function (array $f): array {
            $f['app/lib/Site.php'] = (string) preg_replace('~(public static function handle\(string \$path\): never\s*\{)~', '$1 throw new \RuntimeException(\'Version cassée\');', $f['app/lib/Site.php'], 1);
            return $f;
        });
        check('version cassée de test : bien modifiée', str_contains($broken['files']['app/lib/Site.php'], 'Version cassée'));
        eclair_announce($channel, $chanPort, $broken, $secret);
        $body = $install();
        check('site cassé par la nouvelle version : retour automatique, rien n’est perdu', str_contains($body, 'est revenu tout seul') && $intact()
            && str_contains($c->req('GET', '/')['body'], 'Atelier Test'), $version());
        check('retour automatique : signalé sur la page et au journal', str_contains($body, 'ne fonctionnait pas correctement sur ton serveur')
            && ($state()['failed']['version'] ?? '') === '9.9.9' && str_contains($c->req('GET', '/gestion/security')['body'], 'Retour à la version précédente'));

        // Fichier du thème retouché à la main : signalé avant l'installation (il serait remplacé).
        $themeCss = $copy . '/app/theme/theme.css';
        $originalCss = (string) file_get_contents($themeCss);
        file_put_contents($themeCss, $originalCss . "\n/* retouche à la main */\n");
        $page = $c->req('GET', '/gestion/update')['body'];
        check('fichiers retouchés à la main : listés avant la mise à jour', str_contains($page, 'Fichiers d’Éclair modifiés à la main') && str_contains($page, 'app/theme/theme.css'));
        file_put_contents($themeCss, $originalCss);

        // Version saine : mot de passe redemandé (session de plus de 15 min), .htaccess personnalisé gardé, ancien fichier écarté.
        file_put_contents($copy . '/.htaccess', "\n# Réglage ajouté par l'hébergeur\n", FILE_APPEND);
        $contents = count(glob($copy . '/data/content/*.php') ?: []);
        $visitor = client($port);
        $visitor->req('GET', '/');
        $cached = $visitor->req('GET', '/');
        // Page en cache marquée : elle ne doit plus jamais être servie après la mise à jour.
        $marked = @file_put_contents($copy . '/cache/pages/' . md5('/'), '<!-- page de l’ancienne version -->', FILE_APPEND) > 0
            && str_contains($visitor->req('GET', '/')['body'], 'page de l’ancienne version');
        eclair_announce($channel, $chanPort, $good, $secret);
        foreach (glob($copy . '/data/sessions/*.php') ?: [] as $file) {
            file_put_contents($file, (string) preg_replace('~sudo\|i:\d+;~', 'sudo|i:0;', (string) file_get_contents($file)));
        }
        $body = $install();
        check('mot de passe redemandé (session ancienne) : refusé sans lui', str_contains($body, 'Mot de passe incorrect') && $intact());
        $body = $install(['confirm_password' => 'Un-mot-de-passe-solide']);
        check('mise à jour réussie : nouvelle version en place', str_contains($body, 'Éclair est passé à la version 9.9.9') && $version() === '9.9.9'
            && str_ends_with(rtrim((string) file_get_contents($copy . '/assets/admin/admin.css')), '/* version-9-9-9 */') && is_file($copy . '/app/lib/Nouveau.php'), $version() . ' ' . substr(strip_tags($body), 0, 300));
        check('fichier retiré par la nouvelle version : plus dans le code, gardé avec l’ancienne', !is_file($copy . '/app/lib/Ancien.php') && is_file($copy . '/cache/update/previous/app/lib/Ancien.php'));
        check('.htaccess retouché : conservé, nouveau modèle mis à disposition', str_contains((string) file_get_contents($copy . '/.htaccess'), 'Réglage ajouté par l')
            && (string) @file_get_contents($copy . '/cache/update/htaccess-9.9.9.txt') === $good['files']['.htaccess'] && str_contains($body, 'conservés tels quels : .htaccess'));
        check('contrôle de santé fait (aucun avertissement), contenus et réglages intacts', !str_contains($body, 'empêche Éclair de contrôler')
            && count(glob($copy . '/data/content/*.php') ?: []) === $contents && $contents > 0 && str_contains($c->req('GET', '/')['body'], 'Atelier Test'));
        check('ancienne version gardée sans point d’entrée exécutable', is_file($copy . '/cache/update/previous/index.php.txt') && !is_file($copy . '/cache/update/previous/index.php')
            && !is_dir($copy . '/cache/update/stage') && !glob($copy . '/cache/update/*.zip'));
        // (Le contrôle de santé a déjà rendu l'accueil avec le nouveau code : c'est cette nouvelle page qui est en cache.)
        $home = $visitor->req('GET', '/');
        $rt = json_decode(Store::strip((string) @file_get_contents($copy . '/cache/runtime.php')), true);
        check('cache vidé puis régénéré par la nouvelle version', ($cached['headers']['x-cache'] ?? '') === 'HIT' && $marked && $home['status'] === 200
            && !str_contains($home['body'], 'page de l’ancienne version') && ($rt['v'] ?? '') === '9.9.9', ($cached['headers']['x-cache'] ?? '-') . ' ' . json_encode($rt));
        $body = $c->req('GET', '/gestion/update')['body'];
        check('après la mise à jour : à jour, retour possible vers ' . $current, str_contains($body, 'Tu as la dernière version') && str_contains($body, 'Revenir à la version ' . $current)
            && str_contains($c->req('GET', '/gestion/security')['body'], 'Mise à jour d’Éclair installée'));
        $sec = $c->req('GET', '/gestion/security');
        $scan = $c->req('POST', '/gestion/security', $c->token($sec['body']) + ['action' => 'integrity']);
        $scan = $c->req('GET', '/gestion/security');
        check('contrôle d’intégrité : seul le .htaccess retouché est signalé', str_contains($scan['body'], 'Fichiers modifiés (1)') && !str_contains($scan['body'], 'Fichiers inconnus')
            && !str_contains($scan['body'], 'Fichiers suspects'), substr(strip_tags((string) strstr($scan['body'], 'Intégrité des fichiers')), 0, 400));

        // Retour manuel à la version précédente.
        $body = $post(['action' => 'rollback']);
        check('retour manuel : version précédente remise en place', str_contains($body, 'Retour à la version ' . $current . ' effectué') && $version() === $current
            && is_file($copy . '/app/lib/Ancien.php') && !is_file($copy . '/app/lib/Nouveau.php') && !str_contains((string) file_get_contents($copy . '/assets/admin/admin.css'), 'version-9-9-9'));
        check('retour manuel : site en ligne, nouvelle version de nouveau proposée', $c->req('GET', '/')['status'] === 200 && !is_dir($copy . '/cache/update/previous')
            && str_contains($body, 'Éclair 9.9.9 est disponible') && str_contains((string) file_get_contents($copy . '/.htaccess'), 'Réglage ajouté par l'));

        // Version plus ancienne annoncée : jamais proposée (pas de retour en arrière par le canal).
        $old = eclair_release($channel, '1.0.0');
        eclair_announce($channel, $chanPort, $old, $secret);
        $body = $post(['action' => 'check']);
        check('version plus ancienne annoncée : non proposée', str_contains($body, 'Tu as la dernière version') && !str_contains($body, 'name="action" value="install"'));

        // Vérification discrète depuis l'administration : seulement si elle est due et activée.
        eclair_announce($channel, $chanPort, $good, $secret);
        Store::forget();
        file_put_contents($copy . '/data/update.php', Store::GUARD . json_encode(['next' => 0] + $state()));
        $dash = $c->req('GET', '/gestion');
        preg_match('~"csrf":"([a-f0-9]+)"~', $dash['body'], $csrf);
        $api = $c->req('POST', '/gestion/api/update/check', '{}', ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($csrf[1] ?? '')]);
        $json = json_decode($api['body'], true);
        check('vérification en arrière-plan : signalée au JavaScript, nouvelle version trouvée', str_contains($dash['body'], '"updateCheck":true')
            && ($json['available'] ?? null) === true && ($json['version'] ?? '') === '9.9.9', $api['body']);
        settings($copy, ['updates' => ['check' => false]]);
        file_put_contents($copy . '/data/update.php', Store::GUARD . json_encode(['next' => 0] + $state()));
        eclair_announce($channel, $chanPort, eclair_release($channel, '9.9.10'), $secret);
        $dash = $c->req('GET', '/gestion');
        $api = $c->req('POST', '/gestion/api/update/check', '{}', ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($csrf[1] ?? '')]);
        check('vérification automatique désactivée : aucun appel au canal', !str_contains($dash['body'], '"updateCheck":true') && (json_decode($api['body'], true)['version'] ?? '') === '9.9.9', $api['body']);

        // Rédacteur : aucune entrée, page refusée.
        $users = $c->req('GET', '/gestion/users');
        $c->req('POST', '/gestion/users', $c->token($users['body']) + ['name' => 'Rédac Test', 'email' => 'redac@example.com', 'role' => 'editor']);
        preg_match('/([A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4})/', $c->req('GET', '/gestion/users')['body'], $tmp);
        $editor = client($port);
        $editor->login('redac@example.com', $tmp[1] ?? 'x');
        $profile = $editor->req('GET', '/gestion/profile');
        $editor->req('POST', '/gestion/profile', $editor->token($profile['body']) + ['action' => 'password', 'current' => $tmp[1] ?? 'x', 'password' => 'Plume-Bleue-Solide-2026']);
        $denied = $editor->req('GET', '/gestion/update');
        $menu = $editor->req('GET', '/gestion/content');
        check('rédacteur : page Mises à jour refusée, absente du menu', $denied['status'] === 403 && $menu['status'] === 200 && !str_contains($menu['body'], '<a href="/gestion/update"'),
            $denied['status'] . ' ' . $menu['status']);
    } finally {
        proc_terminate($server);
        proc_terminate($chan);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($channel));
    }
}
