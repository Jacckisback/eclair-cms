<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Thèmes du site (site/themes/), contrat de rendu, cycle de vie, import de paquets, sauvegarde et restauration sur une
 * autre installation (inclus par tests/run.php).
 */
declare(strict_types=1);

use Eclair\Backup;
use Eclair\Store;
use Eclair\Theme;
use Eclair\Themes;

(static function (array $argv): void {
    section('Thèmes du site : contrat, validation, activation, repli, retour');
    $example = dirname(__DIR__) . '/examples/themes/atelier-lumiere';
    $tmp = sys_get_temp_dir() . '/eclair-theme-src-' . getmypid();
    $make = static function (string $name, array $manifest, array $files = []) use ($tmp): string {
        $dir = $tmp . '/' . $name;
        exec('rm -rf ' . escapeshellarg($dir));
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/theme.json', json_encode($manifest + ['schema' => 'eclair-theme/1', 'id' => $name, 'version' => '1.0.0', 'contract' => 1, 'parent' => 'eclair']));
        foreach ($files as $rel => $content) {
            @mkdir(dirname($dir . '/' . $rel), 0755, true);
            file_put_contents($dir . '/' . $rel, $content);
        }
        return $dir;
    };

    check('exemple minimal : conforme au contrat 1', Themes::validate($example) === [], implode(' | ', Themes::validate($example)));
    $refus = [
        'contrat non pris en charge' => $make('contrat', ['contract' => 2]),
        'identifiant invalide' => $make('Mauvais_ID', []),
        'chemin qui remonte' => $make('remonte', ['styles' => ['../x.css']]),
        'gabarit hors contrat' => $make('horscontrat', ['templates' => ['admin']], ['templates/admin.php' => '<?php ']),
        'script non déclaré' => $make('script', [], ['outil.php' => '<?php echo 1;']),
        'gabarit déclaré absent' => $make('absent', ['templates' => ['single']]),
        'syntaxe PHP illisible' => $make('syntaxe', ['templates' => ['404']], ['templates/404.php' => "<?php\nfunction (\n"]),
        'parent inconnu' => $make('parent', ['parent' => 'autre@3']),
        'actif d’un type refusé' => $make('actif', ['assets' => ['page.html']], ['page.html' => '<p>x</p>']),
        'noyau trop ancien' => $make('noyau', ['requires' => ['php' => '>=99.0']]),
    ];
    foreach ($refus as $label => $dir) {
        check("validation : $label refusé", Themes::validate($dir) !== []);
    }
    $link = $make('lien', ['styles' => ['styles/a.css']]);
    @mkdir($link . '/styles');
    @symlink('/etc/hostname', $link . '/styles/a.css');
    check('validation : lien symbolique refusé', is_link($link . '/styles/a.css') ? Themes::validate($link) !== [] : true);
    check('contraintes de version', Themes::satisfies('2.1.0', '>=2.0.0 <3.0.0') && !Themes::satisfies('3.0.0', '^2.0') && Themes::satisfies('2.4.1', '^2.0')
        && !Themes::satisfies('1.9.9', '>=2.0.0') && !Themes::satisfies('2.0.0', '>=2.0.0; rm'));

    // Cycle de vie : installation, activation, rendu, actifs.
    $r = Themes::install($example);
    check('installation dans site/themes (sans activation)', $r['ok'] && is_file(Themes::dir() . '/atelier-lumiere/theme.json') && Themes::active() === null, (string) ($r['error'] ?? ''));
    check('dossiers privés du site protégés (.htaccess)', str_contains((string) @file_get_contents(Themes::dir() . '/.htaccess'), 'Require all denied')
        && str_contains((string) @file_get_contents(Themes::assetsDir() . '/.htaccess'), 'FilesMatch'));
    $css0 = Theme::css();
    $r = Themes::activate('atelier-lumiere');
    Store::forget();
    Themes::forget();
    $reflection = new ReflectionProperty(Theme::class, 'css');
    $reflection->setValue(null, null);
    check('activation après validation', $r['ok'] && (Themes::active()['id'] ?? '') === 'atelier-lumiere', (string) ($r['error'] ?? ''));
    check('feuille du thème ajoutée après le thème de base', str_contains(Theme::css(), '.atelier-404') && strlen(Theme::css()) > strlen($css0));
    check('gabarit remplacé seulement s’il est déclaré', Themes::template('404') !== null && Themes::template('layout') === null);
    $html = Theme::partial('404', ['lang' => 'fr']);
    check('gabarit du thème rendu', str_contains($html, 'atelier-404') && str_contains($html, 'Page introuvable'));
    $asset = Themes::asset('assets/img/motif.svg');
    check('actif déclaré publié dans site/assets/<id>/', $asset !== '' && is_file(Themes::assetsDir() . '/atelier-lumiere/assets/img/motif.svg') && Themes::asset('styles/site.css') === '');

    // Nouvelle version : l'ancienne est gardée, le retour la rétablit.
    $v2 = $make('atelier-lumiere', json_decode((string) file_get_contents($example . '/theme.json'), true) + [], []);
    exec('cp -r ' . escapeshellarg($example) . '/. ' . escapeshellarg($v2));
    $m = json_decode((string) file_get_contents($v2 . '/theme.json'), true);
    $m['version'] = '1.1.0';
    file_put_contents($v2 . '/theme.json', json_encode($m));
    file_put_contents($v2 . '/styles/site.css', '.atelier-v2{color:red}');
    $r = Themes::install($v2);
    Themes::activate('atelier-lumiere');
    Themes::forget();
    check('nouvelle version installée, ancienne gardée', $r['ok'] && (Themes::active()['version'] ?? '') === '1.1.0' && in_array('1.0.0', Themes::versions('atelier-lumiere'), true));
    Store::forget();
    $back = Themes::revert();
    Themes::forget();
    check('retour à la version précédente du thème', $back['ok'] && (Themes::active()['version'] ?? '') === '1.0.0' && !str_contains(Themes::css(), 'atelier-v2'), (string) ($back['error'] ?? ''));

    // Erreur dans un gabarit du site : le noyau prend le relais, le thème est signalé.
    $broken = $make('casse', ['templates' => ['404']], ['templates/404.php' => "<?php\ndefined('ECLAIR') || exit;\necho '<p>début</p>';\nthrow new RuntimeException('gabarit cassé');\n"]);
    Themes::install($broken);
    Themes::activate('casse');
    Themes::forget();
    $html = Theme::partial('404', ['lang' => 'fr']);
    Store::forget();
    check('gabarit du site en erreur : page rendue avec le gabarit du noyau', str_contains($html, 'notfound') && !str_contains($html, 'début'), substr($html, 0, 120));
    check('thème en panne signalé à l’administration', (Themes::state()['broken']['id'] ?? '') === 'casse');
    check('accès de secours : ECLAIR_SAFE_THEME documenté', str_contains((string) file_get_contents(ECLAIR_ROOT . '/app/lib/Themes.php'), 'ECLAIR_SAFE_THEME'));
    $d = Themes::deactivate();
    Themes::forget();
    check('retour au thème de base, fichiers gardés', $d['ok'] && Themes::active() === null && is_dir(Themes::dir() . '/casse'));

    // Import d'un paquet ZIP.
    $zipOf = static function (array $entries, array $links = []) use ($tmp): string {
        $file = $tmp . '/p-' . bin2hex(random_bytes(3)) . '.zip';
        $z = new ZipArchive();
        $z->open($file, ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $z->addFromString($name, $content);
        }
        foreach ($links as $name => $target) {
            $z->addFromString($name, $target);
            $z->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0120777 << 16));
        }
        $z->close();
        return $file;
    };
    $good = ['mon-theme/theme.json' => json_encode(['schema' => 'eclair-theme/1', 'id' => 'mon-theme', 'version' => '1.0.0', 'contract' => 1, 'parent' => 'eclair', 'styles' => ['s.css']]), 'mon-theme/s.css' => '.x{}'];
    check('import ZIP : paquet conforme installé', Themes::installZip($zipOf($good))['ok'] && is_file(Themes::dir() . '/mon-theme/s.css'));
    check('import ZIP : chemin qui remonte refusé', !Themes::installZip($zipOf(['theme.json' => '{}', '../../evil.php' => '<?php']))['ok'] && !is_file(dirname(Themes::dir()) . '/evil.php'));
    check('import ZIP : lien symbolique refusé', !Themes::installZip($zipOf($good, ['mon-theme/lien.css' => '/etc/passwd']))['ok']);
    check('import ZIP : sans theme.json refusé', !Themes::installZip($zipOf(['a/b.css' => '.x{}']))['ok']);
    check('aucun dossier de travail laissé', glob(Themes::dir() . '/.{stage,upload}-*', GLOB_BRACE) === []);

    // Sauvegarde : le dossier site/ est inclus (thèmes, actifs, versions gardées), jamais les dossiers de travail.
    $files = Backup::files();
    check('sauvegarde : thèmes, actifs publiés et versions gardées inclus', isset($files['site/themes/atelier-lumiere/templates/404.php'], $files['site/assets/atelier-lumiere/assets/img/motif.svg'])
        && array_filter(array_keys($files), static fn (string $f): bool => str_starts_with($f, 'site/themes/.versions/')) !== []);
    exec('rm -rf ' . escapeshellarg($tmp));

    if (in_array('--unit', $argv, true) || !class_exists('ZipArchive')) {
        return;
    }

    section('Sauvegarde puis restauration sur une installation distincte (thème, contenus, empreintes)');
    $install = static function (object $c, string $name, string $email): void {
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => $name, 'site_tagline' => 'Test', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => $email, 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login($email, 'Un-mot-de-passe-solide');
    };
    [$a, $portA, $serverA] = serve('backup-a');
    [$b, $portB, $serverB] = serve('backup-b');
    $upload = static function (object $c, string $path, string $field, string $file, array $fields): array {
        $ch = curl_init('http://127.0.0.1:' . $c->port . $path);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_PROXY => '',
            CURLOPT_POSTFIELDS => $fields + [$field => new CURLFile($file, 'application/zip', 'paquet.zip')], CURLOPT_TIMEOUT => 60]);
        $body = (string) curl_exec($ch);
        return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body];
    };
    try {
        $ca = client($portA);
        $install($ca, 'Atelier Source', 'source@example.com');
        // Thème importé par l'administration (droit « security », mot de passe confirmé), essayé, puis activé.
        $pkg = sys_get_temp_dir() . '/eclair-theme-pkg-' . getmypid() . '.zip';
        $z = new ZipArchive();
        $z->open($pkg, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (array_keys(Themes::files($example)) as $rel) {
            $z->addFile($example . '/' . $rel, 'atelier-lumiere/' . $rel);
        }
        $z->close();
        $page = $ca->req('GET', '/gestion/themes');
        check('page Thèmes : thème de base actif, import proposé', $page['status'] === 200 && str_contains($page['body'], 'Thème de base d’Éclair') && str_contains($page['body'], 'name="package"'));
        $up = $upload($ca, '/gestion/themes', 'package', $pkg, $ca->token($page['body']) + ['action' => 'upload', 'confirm_password' => 'Un-mot-de-passe-solide']);
        if (getenv('ECLAIR_TEST_VERBOSE')) {
            echo $up['status'], ' ', preg_replace('~\s+~', ' ', strip_tags((string) strstr($ca->req('GET', '/gestion/themes')['body'], '<main'))), "\n";
        }
        check('import par l’administration : installé, pas encore actif', is_file($a . '/site/themes/atelier-lumiere/theme.json')
            && !str_contains($ca->req('GET', '/')['body'], '.atelier-404'));
        $preview = $ca->req('GET', '/gestion/appearance/preview?site_theme=atelier-lumiere&path=/');
        check('aperçu avec les vrais contenus, jamais en cache', $preview['status'] === 200 && str_contains($preview['body'], '.atelier-404') && str_contains($preview['headers']['cache-control'] ?? '', 'no-store'));
        check('aperçu : la page publique reste inchangée', !str_contains($ca->req('GET', '/')['body'], '.atelier-404'));
        $page = $ca->req('GET', '/gestion/themes');
        $ca->req('POST', '/gestion/themes', $ca->token($page['body']) + ['action' => 'activate', 'id' => 'atelier-lumiere']);
        $home = $ca->req('GET', '/');
        check('thème activé : servi sur le site public', str_contains($home['body'], '.atelier-404'));
        $nf = $ca->req('GET', '/page-qui-n-existe-pas');
        check('gabarit 404 du thème servi avec le statut 404', $nf['status'] === 404 && str_contains($nf['body'], 'atelier-404'));
        check('actif publié servi', $ca->req('GET', '/site/assets/atelier-lumiere/assets/img/motif.svg')['status'] === 200);
        $printA = Themes::fingerprint($a . '/site/themes/atelier-lumiere');
        $sec = $ca->req('GET', '/gestion/security');
        $backup = $ca->req('POST', '/gestion/backup', $ca->token($sec['body']) + ['confirm_password' => 'Un-mot-de-passe-solide']);
        $zipFile = sys_get_temp_dir() . '/eclair-backup-' . getmypid() . '.zip';
        file_put_contents($zipFile, $backup['body']);
        $z = new ZipArchive();
        $listed = $z->open($zipFile) === true ? array_map(static fn (int $i): string => (string) $z->getNameIndex($i), range(0, $z->numFiles - 1)) : [];
        check('sauvegarde : thème et état du thème inclus, aucun secret', in_array('site/themes/atelier-lumiere/templates/404.php', $listed, true) && in_array('data/theme.php', $listed, true)
            && !array_filter($listed, static fn (string $f): bool => (bool) preg_match('~^data/(sessions/|vault\.php|limits/)~', $f)), (string) count($listed));

        // Installation distincte : restauration.
        $cb = client($portB);
        $install($cb, 'Atelier Cible', 'cible@example.com');
        $vaultB = (string) @file_get_contents($b . '/data/vault.php');
        $sec = $cb->req('GET', '/gestion/security');
        check('page Sécurité : restauration proposée', str_contains($sec['body'], 'name="backup"'));
        $up = $upload($cb, '/gestion/restore', 'backup', $zipFile, $cb->token($sec['body']) + ['confirm_password' => 'Un-mot-de-passe-solide']);
        if (getenv('ECLAIR_TEST_VERBOSE')) {
            $flash = $cb->req('GET', '/gestion/security')['body'];
            preg_match('~<div class="flash[^"]*"[^>]*>(.*?)</div>~s', $flash, $fm);
            echo 'restauration : ', $up['status'], ' zip ', filesize($zipFile), ' ', substr(preg_replace('~\s+~', ' ', strip_tags((string) strstr($flash, '<main'))), 0, 500), "\n";
        }
        $homeB = $cb->req('GET', '/');
        check('restauration : site, thème et contenus de la source', str_contains($homeB['body'], 'Atelier Source') && str_contains($homeB['body'], '.atelier-404'));
        check('restauration : fichiers du thème identiques (empreinte)', is_dir($b . '/site/themes/atelier-lumiere') && Themes::fingerprint($b . '/site/themes/atelier-lumiere') === $printA);
        check('restauration : coffre des clés de la cible gardé', (string) @file_get_contents($b . '/data/vault.php') === $vaultB);
        $c2 = client($portB);
        $login = $c2->login('source@example.com', 'Un-mot-de-passe-solide');
        check('restauration : comptes de la source utilisables', $c2->req('GET', '/gestion')['status'] === 200);

        // Archive piégée : refusée en entier.
        $evil = sys_get_temp_dir() . '/eclair-backup-evil-' . getmypid() . '.zip';
        $z = new ZipArchive();
        $z->open($evil, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('data/site.php', Store::GUARD . '{}');
        $z->addFromString('app/lib/Auth.php', '<?php echo "pirate";');
        $z->close();
        $sec = $c2->req('GET', '/gestion/security');
        $upload($c2, '/gestion/restore', 'backup', $evil, $c2->token($sec['body']) + ['confirm_password' => 'Un-mot-de-passe-solide']);
        check('sauvegarde piégée (hors data/, media/, site/) : refusée, rien ne change', !str_contains((string) file_get_contents($b . '/app/lib/Auth.php'), 'pirate')
            && str_contains($c2->req('GET', '/')['body'], 'Atelier Source'));
        @unlink($zipFile);
        @unlink($evil);
        @unlink($pkg);
    } finally {
        proc_terminate($serverA);
        proc_terminate($serverB);
        exec('rm -rf ' . escapeshellarg($a) . ' ' . escapeshellarg($b));
    }
})($argv);
