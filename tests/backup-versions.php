<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Sauvegarde d'un site dont le thème a été mis à jour : les versions gardées (site/themes/.versions/) entrent dans
 * l'archive et la restauration sur une installation neuve les accepte (bogue de 2.4.0 : « entrée inattendue
 * (site/themes/.versions/…) »). Les autres entrées cachées restent refusées, avec un message qui dit quoi faire.
 * Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Store;
use Eclair\Themes;

(static function (array $argv): void {
    if (in_array('--unit', $argv, true) || !class_exists('ZipArchive')) {
        return;
    }
    section('Sauvegarde d’un site au thème mis à jour deux fois, restaurée sur une installation neuve');
    $example = dirname(__DIR__) . '/examples/themes/atelier-lumiere';
    $pass = 'Un-mot-de-passe-solide';
    $install = static function (object $c, string $name, string $email) use ($pass): void {
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => $name, 'site_tagline' => 'Test', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => $email, 'user_pass' => $pass, 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login($email, $pass);
    };
    $upload = static function (object $c, string $path, string $field, string $file, array $fields): array {
        $ch = curl_init('http://127.0.0.1:' . $c->port . $path);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_PROXY => '',
            CURLOPT_POSTFIELDS => $fields + [$field => new CURLFile($file, 'application/zip', 'paquet.zip')], CURLOPT_TIMEOUT => 60]);
        $body = (string) curl_exec($ch);
        return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body];
    };
    // Message affiché après une action (bandeau de la page Sécurité).
    $flash = static function (object $c): string {
        $page = $c->req('GET', '/gestion/security')['body'];
        return preg_match('~data-flash>.*?<p>(.*?)</p>~s', $page, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
    };
    // Paquet du thème d'exemple à une version donnée (feuille de style marquée : chaque version a sa propre empreinte).
    $package = static function (string $version) use ($example): string {
        $file = sys_get_temp_dir() . '/eclair-theme-' . $version . '-' . getmypid() . '.zip';
        $z = new ZipArchive();
        $z->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (array_keys(Themes::files($example)) as $rel) {
            $content = (string) file_get_contents($example . '/' . $rel);
            if ($rel === 'theme.json') {
                $m = json_decode($content, true);
                $m['version'] = $version;
                $content = (string) json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } elseif ($rel === 'styles/site.css') {
                $content .= "\n/* version $version */\n";
            }
            $z->addFromString('atelier-lumiere/' . $rel, $content);
        }
        $z->close();
        return $file;
    };
    $zipNames = static function (string $file): array {
        $z = new ZipArchive();
        if ($z->open($file) !== true) {
            return [];
        }
        $out = array_map(static fn (int $i): string => (string) $z->getNameIndex($i), $z->numFiles > 0 ? range(0, $z->numFiles - 1) : []);
        $z->close();
        return $out;
    };
    $versionsOf = static function (string $root): array {
        $out = [];
        foreach (glob($root . '/site/themes/.versions/atelier-lumiere/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $out[basename($dir)] = Themes::fingerprint($dir);
        }
        ksort($out);
        return $out;
    };
    $state = static function (string $root): array {
        $raw = (string) @file_get_contents($root . '/data/theme.php');
        $data = json_decode(substr($raw, strlen(Store::GUARD)), true);
        return is_array($data) ? $data : [];
    };

    [$a, $portA, $serverA] = serve('bkv-a');
    [$b, $portB, $serverB] = serve('bkv-b');
    $tmpFiles = [];
    try {
        $ca = client($portA);
        $install($ca, 'Atelier Versions', 'source@example.com');
        // 1.0.0 installé et activé, puis deux mises à jour (1.1.0, 1.2.0) : deux versions gardées dans .versions/.
        foreach (['1.0.0', '1.1.0', '1.2.0'] as $n => $version) {
            $tmpFiles[] = $pkg = $package($version);
            $page = $ca->req('GET', '/gestion/themes');
            $upload($ca, '/gestion/themes', 'package', $pkg, $ca->token($page['body']) + ['action' => 'upload', 'confirm_password' => $pass]);
            if ($n === 0) {
                $page = $ca->req('GET', '/gestion/themes');
                $ca->req('POST', '/gestion/themes', $ca->token($page['body']) + ['action' => 'activate', 'id' => 'atelier-lumiere']);
            }
            if ($n === 1) {
                sleep(1); // dossiers des versions gardées horodatés à la seconde
            }
        }
        $versionsA = $versionsOf($a);
        $stateA = $state($a);
        check('source : thème mis à jour deux fois, deux versions gardées', count($versionsA) === 2
            && (string) (json_decode((string) file_get_contents($a . '/site/themes/atelier-lumiere/theme.json'), true)['version'] ?? '') === '1.2.0', implode(', ', array_keys($versionsA)));
        check('source : thème actif servi', str_contains($ca->req('GET', '/')['body'], '.atelier-404') && ($stateA['active']['id'] ?? '') === 'atelier-lumiere');
        $printA = Themes::fingerprint($a . '/site/themes/atelier-lumiere');

        $sec = $ca->req('GET', '/gestion/security');
        $backup = $ca->req('POST', '/gestion/backup', $ca->token($sec['body']) + ['confirm_password' => $pass]);
        $tmpFiles[] = $zipFile = sys_get_temp_dir() . '/eclair-bkv-' . getmypid() . '.zip';
        file_put_contents($zipFile, $backup['body']);
        $listed = $zipNames($zipFile);
        check('sauvegarde : versions gardées du thème incluses', array_filter($listed, static fn (string $f): bool => str_starts_with($f, 'site/themes/.versions/atelier-lumiere/')) !== [], (string) count($listed));

        // Installation neuve : restauration complète.
        $cb = client($portB);
        $install($cb, 'Atelier Neuf', 'cible@example.com');
        $sec = $cb->req('GET', '/gestion/security');
        $upload($cb, '/gestion/restore', 'backup', $zipFile, $cb->token($sec['body']) + ['confirm_password' => $pass]);
        // Les comptes de la source remplacent ceux de la cible : la session de la cible prend fin, le site dit le résultat.
        check('restauration : acceptée (plus d’« entrée inattendue »)', str_contains($cb->req('GET', '/')['body'], 'Atelier Versions'));
        $stateB = $state($b);
        check('restauration : thème actif identique (id, version, empreinte)', ($stateB['active']['id'] ?? '') === 'atelier-lumiere'
            && ($stateB['active']['version'] ?? '') === ($stateA['active']['version'] ?? '-')
            && ($stateB['active']['hash'] ?? '') === ($stateA['active']['hash'] ?? '-')
            && Themes::fingerprint($b . '/site/themes/atelier-lumiere') === $printA, json_encode($stateB['active'] ?? null) . ' / ' . json_encode($stateA['active'] ?? null));
        check('restauration : thème servi sur le site public', str_contains($cb->req('GET', '/')['body'], '.atelier-404'));
        check('restauration : versions gardées remises, identiques (retour en arrière possible)', $versionsOf($b) === $versionsA, json_encode($versionsOf($b)));
        $c2 = client($portB);
        $c2->login('source@example.com', $pass);

        // Archive d'une version antérieure (2.4.0 et avant) : même format, avec .versions/ ; le cas réel signalé
        // (actif d'une version gardée) est accepté tel quel.
        $tmpFiles[] = $old = sys_get_temp_dir() . '/eclair-bkv-old-' . getmypid() . '.zip';
        copy($zipFile, $old);
        $z = new ZipArchive();
        $z->open($old);
        $z->addFromString('site/themes/.versions/weekinfo/1.0.0-20261117192731/assets/boot.js', 'console.log(1);');
        $z->addFromString('site/themes/.versions/weekinfo/1.0.0-20261117192731/theme.json', '{}');
        $z->close();
        $sec = $c2->req('GET', '/gestion/security');
        $upload($c2, '/gestion/restore', 'backup', $old, $c2->token($sec['body']) + ['confirm_password' => $pass]);
        $message = $flash($c2);
        check('archive d’une version antérieure (avec .versions/) : restaurée', str_contains($message, 'Sauvegarde restaurée') && is_file($b . '/site/themes/.versions/weekinfo/1.0.0-20261117192731/assets/boot.js'), $message);

        // Les autres entrées cachées restent refusées en entier, avec un message qui dit quoi faire.
        $c3 = client($portB);
        $c3->login('source@example.com', $pass);
        foreach ([
            'dossier caché inconnu de site/' => 'site/.cache/x.js',
            'segment caché sous une version gardée' => 'site/themes/.versions/atelier-lumiere/1.0.0-20261117192731/.git/config',
            'identifiant de thème invalide' => 'site/themes/.versions/Mauvais_ID/1.0.0-20261117192731/theme.json',
            'version gardée sans dossier de version' => 'site/themes/.versions/atelier-lumiere/theme.json',
            'remontée sous .versions' => 'site/themes/.versions/atelier-lumiere/../../../data/x.php',
            '.versions hors de site/themes' => 'data/.versions/x/1.0.0/y.php',
        ] as $label => $entry) {
            $tmpFiles[] = $bad = sys_get_temp_dir() . '/eclair-bkv-bad-' . md5($entry) . '-' . getmypid() . '.zip';
            $z = new ZipArchive();
            $z->open($bad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $z->addFromString('data/site.php', Store::GUARD . '{}');
            $z->addFromString($entry, 'x');
            $z->close();
            $sec = $c3->req('GET', '/gestion/security');
            $upload($c3, '/gestion/restore', 'backup', $bad, $c3->token($sec['body']) + ['confirm_password' => $pass]);
            $message = $flash($c3);
            check("sauvegarde refusée : $label", str_contains($message, 'inattendue') && str_contains($c3->req('GET', '/')['body'], 'Atelier Versions'), $message);
            if ($label === 'dossier caché inconnu de site/') {
                check('refus d’une entrée cachée : le message dit de la retirer de l’archive', str_contains($message, 'retire-le de l’archive ZIP'), $message);
            }
        }
    } finally {
        proc_terminate($serverA);
        proc_terminate($serverB);
        exec('rm -rf ' . escapeshellarg($a) . ' ' . escapeshellarg($b));
        foreach ($tmpFiles as $f) {
            @unlink($f);
        }
    }
})($argv);
