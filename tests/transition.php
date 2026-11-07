<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Transition 1.x → architecture « site/ » (inclus par tests/run.php, après tests/update.php dont il reprend les outils).
 *
 * Part de la VRAIE archive 1.14.1 (dist/eclair-cms-1.14.1.zip) : installation, personnalisation directe des gabarits, du
 * CSS du thème, d'un actif et du .htaccess, puis mise à jour par l'updater 1.14.1 lui-même vers une archive construite
 * depuis les sources. Vérifie que les personnalisations sont rangées dans site/themes/legacy et toujours affichées, que les
 * mises à jour suivantes (nouvel updater) et le retour arrière gardent site/ à l'identique (empreintes), et que le retour
 * vers la 1.14.1 rend ses gabarits personnalisés à l'ancien code.
 */
declare(strict_types=1);

use Eclair\Store;
use Eclair\Themes;

(static function (array $argv): void {
    $legacyZip = dirname(__DIR__) . '/dist/eclair-cms-1.14.1.zip';
    if (in_array('--unit', $argv, true) || !function_exists('sodium_crypto_sign_keypair') || !class_exists('ZipArchive') || !is_file($legacyZip)) {
        return;
    }
    section('Transition depuis une vraie installation 1.14.1 personnalisée (updater 1.14.1, puis nouvel updater)');
    $copy = sys_get_temp_dir() . '/eclair-transition-' . getmypid();
    $unzip = $copy . '-zip';
    exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($unzip));
    $zip = new ZipArchive();
    $zip->open($legacyZip);
    $zip->extractTo($unzip);
    $zip->close();
    rename($unzip . '/eclair', $copy);
    exec('rm -rf ' . escapeshellarg($unzip));
    check('archive 1.14.1 officielle décompressée', is_file($copy . '/app/manifest.php') && str_contains((string) file_get_contents($copy . '/index.php'), "'1.14.1'"));

    // Faux canal de mise à jour et clés de test (jamais la clé du mainteneur).
    $channel = sys_get_temp_dir() . '/eclair-transition-channel-' . getmypid();
    @mkdir($channel);
    file_put_contents($channel . '/index.html', 'canal');
    [$chanPort, $chan] = start_server(['-t', $channel], ['PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
    $pair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_UPDATE_URL', 'http://127.0.0.1:$chanPort/latest.json');\n"
        . "define('ECLAIR_UPDATE_KEY', '" . base64_encode(sodium_crypto_sign_publickey($pair)) . "');\n");
    // Sans OPcache : les fichiers modifiés par le test (gabarits, code envoyé « par FTP ») sont relus aussitôt.
    [$port, $server] = start_server(['-t', $copy, dirname(__DIR__) . '/tools/router.php'], ['PHP_CLI_SERVER_WORKERS' => '4'], 20000, 30000, ['-d', 'opcache.enable=0']);
    $version = static function () use ($copy): string {
        preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($copy . '/index.php'), $m);
        return $m[1] ?? '';
    };
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Transition', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $home = $c->req('GET', '/');
        if (PHP_VERSION_ID < 80200) {
            // Défaut de la 1.14.1 publiée : sous PHP 8.1, ses pages échouent (type de retour « null » seul dans Affiliate.php)
            // et son updater ne peut pas télécharger (CURLOPT_XFERINFOFUNCTION). Constaté ici ; la transition par son updater
            // n'est rejouable qu'en PHP 8.2+, celle par FTP (plus bas) l'est aussi en 8.1.
            check('PHP 8.1 : la 1.14.1 publiée y est cassée (défaut connu, corrigé en 2.0)', $home['status'] === 500, (string) $home['status']);
            throw new \RuntimeException('php81');
        }
        check('1.14.1 installée et en ligne', $home['status'] === 200 && $version() === '1.14.1', (string) $home['status']);

        // Personnalisations directes, comme un intégrateur les faisait en 1.x (FTP).
        $layout = (string) file_get_contents($copy . '/app/theme/layout.php');
        file_put_contents($copy . '/app/theme/layout.php', str_replace('<a class="skip"', '<!-- PERSO-LAYOUT --><a class="skip"', $layout));
        file_put_contents($copy . '/app/theme/theme.css', (string) file_get_contents($copy . '/app/theme/theme.css') . "\n.perso-marque{outline:3px solid #c00}\n");
        file_put_contents($copy . '/app/theme/themes/maison.css', ".maison{color:#123456}\n");
        file_put_contents($copy . '/assets/site/perso.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4"><rect width="4" height="4"/></svg>');
        file_put_contents($copy . '/.htaccess', (string) file_get_contents($copy . '/.htaccess') . "\n# Règle de l'hébergeur (perso)\n");
        $htaccess = (string) file_get_contents($copy . '/.htaccess');
        // Le site utilisait « Ancien site » (Wayback) en 1.14.1 : ses données doivent survivre et la fonction rester disponible.
        $wbData = Store::GUARD . json_encode(['domain' => 'ancien.test', 'date' => '2019-06-30', 'scanned' => time() - 3600, 'found' => 1,
            'urls' => ['/notre-histoire' => ['type' => 'page', 'slug' => 'notre-histoire', 'ts' => '20190101000000', 'raw' => 'http://ancien.test/notre-histoire',
                'exact' => true, 'selected' => true, 'content' => '', 'error' => '', 'cost' => 0, 'tokens' => 0]]]);
        file_put_contents($copy . '/data/wayback.php', $wbData);
        // Google Drive commencé en 1.14.1 (état propre à l'installation, jamais sauvegardé) : la fonction doit rester disponible.
        $driveData = Store::GUARD . json_encode(['schedule' => 'weekly', 'keep' => 5]);
        file_put_contents($copy . '/data/drive.php', $driveData);
        // Site vitrine qui avait consulté les tendances du jour (Discover) en 1.14.1 : la fonction doit lui rester.
        $trData = Store::GUARD . json_encode(['geo' => 'FR', 'fetched' => time() - 7200, 'items' => []]);
        file_put_contents($copy . '/data/trends.php', $trData);
        // Liens d'affiliation enregistrés en 1.14.1 : la fonction de gestion doit leur rester.
        $affData = Store::GUARD . json_encode(['links' => ['a1' => ['id' => 'a1', 'name' => 'Tour Shimpo', 'url' => 'https://www.amazon.fr/dp/B0T?tag=atelier-21',
            'program' => 'amazon', 'notes' => '', 'created' => time() - 86400]], 'disclosure' => '']);
        file_put_contents($copy . '/data/affiliate.php', $affData);
        // Idée planifiée en 1.14.1 (site vitrine) : le planning doit lui rester, avec son adresse de tâche planifiée.
        $planData = Store::GUARD . json_encode(['items' => ['p1' => ['id' => 'p1', 'title' => 'Émailler sans four', 'date' => '2031-01-15', 'keyword' => '',
            'brief' => '', 'category' => '', 'content' => '', 'source' => 'manual', 'cost' => 0, 'tokens' => 0, 'error' => '', 'tried' => 0, 'created' => time() - 3600]],
            'auto' => ['on' => false, 'lead' => 3, 'publish' => false, 'by' => '']]);
        file_put_contents($copy . '/data/plan.php', $planData);
        // Robots IA (mesure active par défaut en 1.x) : une visite notée avant la mise à jour doit rester, la mesure continuer.
        $gpt = ['User-Agent: Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)'];
        client($port)->req('GET', '/', [], $gpt);
        $aiLog = $copy . '/data/ai/' . gmdate('Y-m') . '.php';
        $aiBefore = substr_count((string) @file_get_contents($aiLog), "\tGPTBot\t");
        array_map('unlink', glob($copy . '/cache/pages/*[0-9a-f]') ?: []);
        $home = $c->req('GET', '/')['body'];
        check('1.14.1 personnalisée : gabarit et CSS modifiés affichés', str_contains($home, 'PERSO-LAYOUT') && str_contains($home, 'perso-marque'));
        $pages = [];
        foreach (['/', '/gestion/login'] as $p) {
            $pages[$p] = $c->req('GET', $p)['status'];
        }

        $post = static function (array $fields) use ($c): string {
            $page = $c->req('GET', '/gestion/update');
            $c->req('POST', '/gestion/update', $c->token($page['body']) + $fields);
            return $c->req('GET', '/gestion/update')['body'];
        };
        $install = static function () use ($post): string {
            $post(['action' => 'check']);
            return $post(['action' => 'install', 'saved' => '1']);
        };

        // 1. Mise à jour par l'updater 1.14.1 vers l'archive des sources (version 2.0.0 de test, signée par la clé de test).
        $next = eclair_release($channel, '2.0.0');
        check('archive de transition : uniquement des chemins acceptés par l’updater 1.14.1', array_filter(array_keys($next['files']),
            static fn (string $f): bool => !preg_match('~^(app/|assets/|index\.php$|\.htaccess$|eclair-config\.exemple\.php$|(data|cache|cache/pages|media)/(\.htaccess|index\.html)$)~', $f)) === []);
        eclair_announce($channel, $chanPort, $next, $secret);
        $body = $install();
        check('updater 1.14.1 : passage à la 2.0.0 réussi', $version() === '2.0.0' && str_contains($body, 'est passé à la version 2.0.0'), strip_tags(substr($body, 0, 0)) . $version());
        $c->req('GET', '/gestion'); // passage de l'application (migrations), s'il n'a pas déjà eu lieu pendant le contrôle de santé
        Store::forget();
        $mig = json_decode(Store::strip((string) @file_get_contents($copy . '/data/migrations.php')), true) ?: [];
        check('migrations identifiées et journalisées', isset($mig['done']['2026-11-site-dir'], $mig['done']['2026-11-legacy-theme']) && ($mig['code'] ?? '') === '2.0.0',
            (string) json_encode($mig['failed'] ?? $mig));
        $legacy = $copy . '/site/themes/legacy';
        check('gabarit personnalisé rangé dans site/themes/legacy', is_file($legacy . '/templates/layout.php')
            && str_contains((string) file_get_contents($legacy . '/templates/layout.php'), 'PERSO-LAYOUT') && !is_file($legacy . '/templates/single.php'));
        check('CSS du thème modifié : remplacement déclaré (overrides)', str_contains((string) @file_get_contents($legacy . '/styles/theme.css'), 'perso-marque')
            && str_contains((string) @file_get_contents($legacy . '/theme.json'), '"theme.css"'));
        check('copie brute des personnalisations gardée avant toute transformation', count(glob($copy . '/site/themes/.legacy-originals/*/app/theme/themes/maison.css') ?: []) === 1);
        $state = json_decode(Store::strip((string) @file_get_contents($copy . '/data/theme.php')), true) ?: [];
        check('thème « legacy » activé', ($state['active']['id'] ?? '') === 'legacy');
        $home = $c->req('GET', '/');
        check('après la transition : même apparence (gabarit et CSS personnalisés servis)', $home['status'] === 200 && str_contains($home['body'], 'PERSO-LAYOUT')
            && str_contains($home['body'], 'perso-marque'));
        $svg = $c->req('GET', '/assets/site/perso.svg');
        check('actif personnalisé servi à son ancienne adresse', $svg['status'] === 200 && str_contains($svg['body'], '<svg') && str_contains($svg['headers']['content-type'] ?? '', 'image/svg+xml'),
            $svg['status'] . ' ' . ($svg['headers']['content-type'] ?? ''));
        check('.htaccess personnalisé gardé', (string) file_get_contents($copy . '/.htaccess') === $htaccess);
        check('« Ancien site » utilisé en 1.14.1 : fonction installée, active, données intactes', is_file($copy . '/site/extensions/wayback/extension.json')
            && $c->req('GET', '/gestion/x/wayback')['status'] === 200 && str_contains($c->req('GET', '/gestion/x/wayback')['body'], 'notre-histoire')
            && (string) file_get_contents($copy . '/data/wayback.php') === $wbData);
        check('Google Drive configuré en 1.14.1 : fonction installée, active, réglage gardé', is_file($copy . '/site/extensions/drive/extension.json')
            && $c->req('GET', '/gestion/x/drive')['status'] === 200 && str_contains((string) file_get_contents($copy . '/data/drive.php'), '"weekly"'));
        client($port)->req('GET', '/', [], $gpt);
        check('Robots IA mesurés en 1.14.1 : fonction installée, journal gardé, mesure poursuivie', $aiBefore === 1 && is_file($copy . '/site/extensions/ai-visits/extension.json')
            && $c->req('GET', '/gestion/x/ai-visits')['status'] === 200 && substr_count((string) @file_get_contents($aiLog), "\tGPTBot\t") === 2, (string) $aiBefore);
        check('Discover utilisé en 1.14.1 : fonction installée, données gardées ; réglages AdSense sur la page Publicité du noyau', is_file($copy . '/site/extensions/discover/extension.json')
            && $c->req('GET', '/gestion/x/discover')['status'] === 200 && (string) file_get_contents($copy . '/data/trends.php') === $trData
            && str_contains($c->req('GET', '/gestion/ads')['body'], 'name="action" value="ads"'));
        check('liens d’affiliation de la 1.14.1 : fonction installée, liens intacts', is_file($copy . '/site/extensions/affiliate/extension.json')
            && str_contains($c->req('GET', '/gestion/x/affiliate')['body'], 'Tour Shimpo') && (string) file_get_contents($copy . '/data/affiliate.php') === $affData);
        check('planning de la 1.14.1 : fonction installée, idée gardée et affichée', is_file($copy . '/site/extensions/planning/extension.json')
            && str_contains($c->req('GET', '/gestion/x/planning?m=2031-01')['body'], 'Émailler sans four') && (string) file_get_contents($copy . '/data/plan.php') === $planData);
        foreach ($pages as $p => $status) {
            check("page $p : même statut qu’avant ($status)", $c->req('GET', $p)['status'] === $status);
        }
        check('modèles du site et capacités jamais servis par le web', $c->req('GET', '/site/themes/legacy/theme.json')['status'] === 404
            && $c->req('GET', '/site/themes/legacy/templates/layout.php')['status'] === 404);

        // 2. Mise à jour suivante par le NOUVEL updater : site/ intact à l'octet près.
        $print = Themes::fingerprint($copy . '/site');
        $next2 = eclair_release($channel, '2.0.1', static function (array $f): array {
            $f['app/theme/layout.php'] .= "<?php /* noyau 2.0.1 */ ?>\n";
            return $f;
        });
        eclair_announce($channel, $chanPort, $next2, $secret);
        $body = $install();
        check('nouvel updater : passage à la 2.0.1', $version() === '2.0.1', $version());
        check('site/ intact après la mise à jour du noyau (empreinte)', Themes::fingerprint($copy . '/site') === $print);
        check('apparence personnalisée toujours servie après la mise à jour', str_contains($c->req('GET', '/')['body'], 'PERSO-LAYOUT'));

        // 3. Archive du noyau qui tenterait d'écrire dans site/ : refusée en entier.
        $evil = eclair_release($channel, '2.0.2', null, ['eclair/site/themes/legacy/templates/layout.php' => '<?php echo "écrasé";']);
        eclair_announce($channel, $chanPort, $evil, $secret);
        $body = $install();
        check('archive du noyau qui écrit dans site/ : refusée, rien ne bouge', str_contains($body, 'ne peut pas écrire dans') && $version() === '2.0.1'
            && Themes::fingerprint($copy . '/site') === $print);

        // 4. Retour à la version précédente (2.0.0) : site/ intact.
        $body = $post(['action' => 'rollback', 'confirm_password' => 'Un-mot-de-passe-solide']);
        check('retour arrière 2.0.1 → 2.0.0 : site/ intact, apparence gardée', $version() === '2.0.0' && Themes::fingerprint($copy . '/site') === $print
            && str_contains($c->req('GET', '/')['body'], 'PERSO-LAYOUT'), $version());
    } catch (\RuntimeException $e) {
        if ($e->getMessage() !== 'php81') {
            throw $e;
        }
    } finally {
        proc_terminate($server);
        proc_terminate($chan);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($channel));
    }

    // 5. Retour de la 2.x vers la 1.14.1 (PHP 8.2+ : la 1.14.1 publiée ne fonctionne pas en 8.1).
    if (PHP_VERSION_ID >= 80200) {
        // 5. Retour de la 2.x vers la 1.14.1 avec l'updater 2.x : l'ancien code retrouve ses gabarits personnalisés.
        section('Transition : retour de la nouvelle architecture vers la 1.14.1');
        $copy = sys_get_temp_dir() . '/eclair-transition-back-' . getmypid();
        $zip = new ZipArchive();
        $zip->open($legacyZip);
        $zip->extractTo($copy . '-zip');
        $zip->close();
        rename($copy . '-zip/eclair', $copy);
        exec('rm -rf ' . escapeshellarg($copy . '-zip'));
        @mkdir($channel);
        file_put_contents($channel . '/index.html', 'canal');
        [$chanPort, $chan] = start_server(['-t', $channel], ['PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
        file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_UPDATE_URL', 'http://127.0.0.1:$chanPort/latest.json');\n"
            . "define('ECLAIR_UPDATE_KEY', '" . base64_encode(sodium_crypto_sign_publickey($pair)) . "');\n");
        // Sans OPcache : les fichiers modifiés par le test (gabarits, code envoyé « par FTP ») sont relus aussitôt.
        [$port, $server] = start_server(['-t', $copy, dirname(__DIR__) . '/tools/router.php'], ['PHP_CLI_SERVER_WORKERS' => '4'], 20000, 30000, ['-d', 'opcache.enable=0']);
        try {
            $c = client($port);
            $form = $c->req('GET', '/');
            $c->req('POST', '/', $c->token($form['body']) + [
                'ui' => 'fr', 'site_name' => 'Atelier Retour', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'business',
                'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
            ], ['Accept: application/json']);
            $c->login('camille@example.com', 'Un-mot-de-passe-solide');
            file_put_contents($copy . '/app/theme/layout.php', str_replace('<a class="skip"', '<!-- PERSO-RETOUR --><a class="skip"', (string) file_get_contents($copy . '/app/theme/layout.php')));
            $post = static function (array $fields) use ($c): string {
                $page = $c->req('GET', '/gestion/update');
                $c->req('POST', '/gestion/update', $c->token($page['body']) + $fields);
                return $c->req('GET', '/gestion/update')['body'];
            };
            eclair_announce($channel, $chanPort, eclair_release($channel, '2.0.0'), $secret);
            $post(['action' => 'check']);
            $post(['action' => 'install', 'saved' => '1']);
            $c->req('GET', '/gestion');
            check('installation personnalisée passée en 2.0.0, apparence gardée', str_contains($c->req('GET', '/')['body'], 'PERSO-RETOUR'));
            $post(['action' => 'rollback', 'confirm_password' => 'Un-mot-de-passe-solide']);
            preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($copy . '/index.php'), $m);
            array_map('unlink', glob($copy . '/cache/pages/*[0-9a-f]') ?: []);
            check('retour à la 1.14.1 : son gabarit personnalisé revient avec son code', ($m[1] ?? '') === '1.14.1'
                && str_contains((string) file_get_contents($copy . '/app/theme/layout.php'), 'PERSO-RETOUR') && str_contains($c->req('GET', '/')['body'], 'PERSO-RETOUR'), $m[1] ?? '');
            check('retour à la 1.14.1 : site/ conservé (une nouvelle mise à jour le retrouvera)', is_file($copy . '/site/themes/legacy/templates/layout.php'));
        } finally {
            proc_terminate($server);
            proc_terminate($chan);
            exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($channel));
        }
    }

    // 6. Mise à jour par FTP (docs/UPGRADE-2.md §2) : app/ et assets/ déplacés dans cache/legacy-app et cache/legacy-assets,
    // puis le code 2.0 envoyé par-dessus. Les migrations retrouvent les personnalisations dans ces dossiers.
    section('Transition par FTP : personnalisations rangées dans cache/legacy-app avant l’envoi du nouveau code');
    $copy = sys_get_temp_dir() . '/eclair-transition-ftp-' . getmypid();
    $zip = new ZipArchive();
    $zip->open($legacyZip);
    $zip->extractTo($copy . '-zip');
    $zip->close();
    rename($copy . '-zip/eclair', $copy);
    exec('rm -rf ' . escapeshellarg($copy . '-zip'));
    // Sans OPcache : les fichiers modifiés par le test (gabarits, code envoyé « par FTP ») sont relus aussitôt.
    [$port, $server] = start_server(['-t', $copy, dirname(__DIR__) . '/tools/router.php'], ['PHP_CLI_SERVER_WORKERS' => '4'], 20000, 30000, ['-d', 'opcache.enable=0']);
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier FTP', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        file_put_contents($copy . '/app/theme/layout.php', str_replace('<a class="skip"', '<!-- PERSO-FTP --><a class="skip"', (string) file_get_contents($copy . '/app/theme/layout.php')));
        file_put_contents($copy . '/assets/site/ftp.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2"><rect width="2" height="2"/></svg>');
        // Ce que la personne fait avec son logiciel FTP : mettre de côté, puis envoyer le contenu de l'archive 2.0.
        rename($copy . '/app', $copy . '/cache/legacy-app');
        rename($copy . '/assets', $copy . '/cache/legacy-assets');
        foreach (eclair_code(ECLAIR_ROOT) as $rel => $content) {
            if (preg_match('~^(app/|assets/|index\.php$)~', $rel)) {
                @mkdir(dirname($copy . '/' . $rel), 0755, true);
                file_put_contents($copy . '/' . $rel, $content);
            }
        }
        file_put_contents($copy . '/app/manifest.php', eclair_manifest('2.0.0', eclair_code(ECLAIR_ROOT)));
        $home = $c->req('GET', '/');
        check('FTP : site en ligne avec le nouveau code, gabarit personnalisé retrouvé', $home['status'] === 200 && str_contains($home['body'], 'PERSO-FTP'), (string) $home['status']);
        check('FTP : thème « legacy » créé et activé depuis cache/legacy-app', is_file($copy . '/site/themes/legacy/templates/layout.php')
            && str_contains((string) @file_get_contents($copy . '/data/theme.php'), '"legacy"'));
        check('FTP : actif ajouté servi à son ancienne adresse', $c->req('GET', '/assets/site/ftp.svg')['status'] === 200);
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
})($argv);
