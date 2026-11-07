<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests des sauvegardes Google Drive (1.10), inclus par tests/run.php : connexion par code (OAuth « appareil »), envoi par
 * morceaux avec reprise, conservation des N dernières, tâche planifiée, accès retiré, déconnexion. Un faux Google
 * (tests/fake-google.php) remplace oauth2.googleapis.com et l'API Drive : aucun compte réel n'est utilisé.
 */
declare(strict_types=1);

use Eclair\Store;

if (!in_array('--unit', $argv, true) && class_exists('ZipArchive')) {
    section('Sauvegardes Google Drive : connexion par code, envoi par morceaux, reprise, tâche planifiée (faux Google)');
    [$copy, $port, $server] = serve('drive');
    $gdir = sys_get_temp_dir() . '/eclair-fake-google-' . getmypid();
    exec('rm -rf ' . escapeshellarg($gdir));
    @mkdir($gdir);
    file_put_contents($gdir . '/index.html', 'google');
    // Faux Google : on attend qu'il réponde (autre port si celui-ci est pris).
    $answers = static function (int $port): bool {
        $ch = curl_init('http://127.0.0.1:' . $port . '/ping');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_PROXY => '']);
        curl_exec($ch);
        return curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 404;
    };
    for ($try = 0; $try < 5; $try++) {
        $gport = random_int(60001, 64000);
        $google = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $gport, __DIR__ . '/fake-google.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $gpipes, null, ['FAKE_GOOGLE_DIR' => $gdir, 'PATH' => (string) getenv('PATH'), 'PHP_CLI_SERVER_WORKERS' => '2']);
        for ($wait = 0; $wait < 30 && !$answers($gport); $wait++) {
            usleep(100000);
        }
        if ($answers($gport)) {
            break;
        }
        proc_terminate($google);
    }
    $g = 'http://127.0.0.1:' . $gport;
    // Aucun identifiant Google dans la configuration ni dans l'archive : le site enregistre sa propre application.
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\n"
        . "define('ECLAIR_GOOGLE_ENDPOINTS', ['device' => '$g/device/code', 'token' => '$g/token', 'revoke' => '$g/revoke', 'api' => '$g/drive/v3', 'upload' => '$g/upload/drive/v3']);\n");
    $gstate = static fn (): array => json_decode((string) @file_get_contents($gdir . '/state.json'), true) ?: [];
    $live = static fn (): array => array_values(array_filter($gstate()['files'] ?? [], static fn (array $f): bool => !$f['trashed']));
    $drive = static fn (): array => json_decode(Store::strip((string) @file_get_contents($copy . '/data/drive.php')), true) ?: [];
    $patch = static function (array $p) use ($copy, $drive): void {
        file_put_contents($copy . '/data/drive.php', Store::GUARD . json_encode(array_replace_recursive($drive(), $p)));
    };
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Drive', 'site_tagline' => 'Reliure à Lille', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        // 2.0 : Sauvegardes Google Drive est une fonction livrée, ajoutée ici (Fonctions › Ajouter).
        $extPage = $c->req('GET', '/gestion/extensions');
        $c->req('POST', '/gestion/extensions', $c->token($extPage['body']) + ['action' => 'add', 'id' => 'drive']);
        // Une grande image (5 Mo) : l'archive dépasse un morceau de 4 Mo.
        @mkdir($copy . '/media/2026/11', 0755, true);
        file_put_contents($copy . '/media/2026/11/grande-photo.jpg', "\xFF\xD8\xFF\xE0" . random_bytes(5 * 1048576));
        $post = static function (array $fields) use ($c): string {
            $page = $c->req('GET', '/gestion/x/drive');
            $c->req('POST', '/gestion/x/drive', $c->token($page['body']) + $fields);
            return $c->req('GET', '/gestion/x/drive')['body'];
        };
        $csrf = static function () use ($c): string {
            preg_match('~"csrf":"([a-f0-9]+)"~', $c->req('GET', '/gestion')['body'], $m);
            return $m[1] ?? '';
        };
        $api = static fn (string $path, array $body = []): array => json_decode($c->req('POST', '/gestion/x/' . $path, (string) json_encode($body ?: new stdClass()),
            ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $csrf()])['body'], true) ?: [];

        // Sans application Google : guide pas à pas et formulaire, pas encore de connexion possible.
        $page = $c->req('GET', '/gestion/x/drive')['body'];
        check('sans application Google : guide et formulaire proposés', str_contains($page, 'Sauvegardes sur Google Drive') && str_contains($page, 'Enregistrer l’application Google')
            && str_contains($page, 'TVs and Limited Input devices') && str_contains($page, 'https://console.cloud.google.com/auth/clients') && !str_contains($page, 'Connecter Google Drive'));
        $body = $post(['action' => 'app', 'client_id' => 'pas-un-identifiant', 'client_secret' => 'secret-de-test', 'confirm_password' => 'Un-mot-de-passe-solide']);
        check('application Google : ID client invalide refusé', str_contains($body, 'Cet ID client ne ressemble pas') && !str_contains($body, 'Connecter Google Drive'));
        $body = $post(['action' => 'app', 'client_id' => ' 123-test.apps.googleusercontent.com ', 'client_secret' => 'secret-de-test', 'confirm_password' => 'Un-mot-de-passe-solide']);
        check('application Google enregistrée : connexion proposée, ID affiché', str_contains($body, 'Application Google enregistrée') && str_contains($body, 'Connecter Google Drive')
            && str_contains($body, 'ID client : 123-test.apps.googleusercontent.com') && !str_contains($body, 'secret-de-test'));
        check('code secret de l’application chiffré dans le coffre, jamais en clair', is_file($copy . '/data/vault.php')
            && !str_contains((string) file_get_contents($copy . '/data/vault.php'), 'secret-de-test') && !str_contains((string) file_get_contents($copy . '/data/drive.php'), 'secret-de-test'));
        $page = $c->req('GET', '/gestion/x/drive')['body'];
        check('Sécurité : carte Google Drive, connexion proposée', str_contains($page, 'Sauvegardes sur Google Drive') && str_contains($page, 'Connecter Google Drive'));

        // Connexion : code affiché, autorisation attendue, intervalle respecté, puis accordée.
        $body = $post(['action' => 'connect']);
        check('connexion : code et page Google affichés', str_contains($body, 'ABCD-EFGH') && str_contains($body, 'www.google.com/device') && str_contains($body, 'data-drive-poll="5"'));
        check('connexion : seul l’accès aux fichiers créés par Éclair est demandé', ($gstate()['device']['scope'] ?? '') === 'https://www.googleapis.com/auth/drive.file'
            && ($gstate()['device']['client_id'] ?? '') === '123-test.apps.googleusercontent.com');
        $first = $api('drive/poll');
        $asked = substr_count((string) file_get_contents($gdir . '/log.txt'), 'device_code');
        $second = $api('drive/poll');
        check('en attente : Google interrogé au plus toutes les 5 secondes', ($first['state'] ?? '') === 'pending' && ($second['state'] ?? '') === 'pending'
            && $asked === 1 && substr_count((string) file_get_contents($gdir . '/log.txt'), 'device_code') === 1);
        touch($gdir . '/approved');
        $patch(['pending' => ['next' => 0]]);
        $done = $api('drive/poll');
        $body = $c->req('GET', '/gestion/x/drive')['body'];
        check('autorisation accordée : compte et dossier affichés', ($done['state'] ?? '') === 'connected' && str_contains($body, 'Google Drive connecté')
            && str_contains($body, 'camille@gmail.com') && str_contains($body, 'Éclair – Atelier Drive'), (string) json_encode($done));
        check('jeton chiffré dans le coffre, jamais en clair', !str_contains((string) file_get_contents($copy . '/data/vault.php'), 'rt-1')
            && !str_contains((string) file_get_contents($copy . '/data/drive.php'), 'rt-1'));
        check('première connexion : sauvegarde hebdomadaire proposée', str_contains($body, '<option value="weekly" selected'));

        // Sauvegarde à la demande : archive complète envoyée en deux morceaux, sans secret, puis effacée du serveur.
        $body = $post(['action' => 'now']);
        $files = $live();
        $zipCopy = sys_get_temp_dir() . '/eclair-drive-check-' . getmypid() . '.zip';
        @copy($gdir . '/' . ($files[0]['bin'] ?? 'x'), $zipCopy);
        $zip = new ZipArchive();
        $opened = $zip->open($zipCopy) === true;
        $names = [];
        for ($i = 0; $opened && $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        check('sauvegarde envoyée sur le Drive', str_contains($body, 'Sauvegarde envoyée sur Google Drive') && count($files) === 1
            && str_starts_with($files[0]['name'] ?? '', 'sauvegarde-atelier-drive-') && substr_count((string) file_get_contents($gdir . '/log.txt'), 'PUT /upload/session/') >= 2,
            substr(strip_tags($body), 0, 200));
        check('archive complète et lisible (contenus, réglages, images)', $opened && in_array('data/site.php', $names, true) && in_array('media/2026/11/grande-photo.jpg', $names, true)
            && $zip->getFromName('media/2026/11/grande-photo.jpg') === file_get_contents($copy . '/media/2026/11/grande-photo.jpg'));
        check('aucun secret ni session dans la sauvegarde', !array_filter($names, static fn (string $n): bool => (bool) preg_match('~^data/(vault|drive|update)\.php$|^data/sessions/~', $n)));
        if ($opened) {
            $zip->close();
        }
        @unlink($zipCopy);
        check('archive locale effacée après l’envoi', !glob($copy . '/cache/backups/*.zip') && str_contains($body, 'Dernière sauvegarde'));

        // Envoi interrompu au milieu : reprise là où Google s'est arrêté.
        touch($gdir . '/fail-chunk');
        $body = $post(['action' => 'now']);
        $job = $drive()['job'] ?? null;
        check('envoi interrompu : travail gardé pour reprendre', is_array($job) && (int) $job['offset'] > 0 && is_file($copy . '/cache/backups/' . $job['file']) && str_contains($body, 'data-drive-run'),
            (string) json_encode($job));
        $resumed = $api('drive/tick', ['now' => true]);
        $files = $live();
        $last = end($files) ?: [];
        $whole = new ZipArchive();
        $readable = $whole->open($gdir . '/' . ($last['bin'] ?? 'x')) === true && $whole->locateName('media/2026/11/grande-photo.jpg') !== false
            && $whole->getFromName('media/2026/11/grande-photo.jpg') === file_get_contents($copy . '/media/2026/11/grande-photo.jpg');
        check('reprise : sauvegarde complète et intacte, reprise au bon octet', ($resumed['status'] ?? '') === 'done' && count($files) === 2 && $readable
            && (int) ($last['size'] ?? 0) === (int) ($job['size'] ?? -1) && !isset($drive()['job']), (string) json_encode($resumed));

        // Conservation : seules les N dernières restent, les autres vont à la corbeille.
        $post(['action' => 'save', 'schedule' => 'daily', 'keep' => '2']);
        $body = $post(['action' => 'now']);
        $all = $gstate()['files'] ?? [];
        check('conservation : 2 dernières gardées, la plus ancienne à la corbeille', count($live()) === 2 && count(array_filter($all, static fn (array $f): bool => $f['trashed'])) === 1
            && ($all[0]['trashed'] ?? false) === true && str_contains($body, '<option value="daily" selected'));

        // Tâche planifiée : adresse secrète, ne fait rien tant que rien n'est dû.
        preg_match('~/_eclair/backup/([a-f0-9]{32})~', $body, $key);
        $bad = $c->req('GET', '/_eclair/backup/' . str_repeat('0', 32));
        $idle = $c->req('GET', '/_eclair/backup/' . ($key[1] ?? 'x'));
        check('tâche planifiée : mauvaise clé refusée, rien à faire si rien n’est dû', $bad['status'] === 404 && $idle['status'] === 200 && trim($idle['body']) === 'idle', trim($idle['body']));
        $patch(['ok' => time() - 2 * 86400, 'attempt' => time() - 2 * 86400]);
        $cron = $c->req('GET', '/_eclair/backup/' . ($key[1] ?? 'x'));
        check('tâche planifiée : sauvegarde due envoyée', trim($cron['body']) === 'done' && count($live()) === 2 && count($gstate()['files'] ?? []) === 4, trim($cron['body']));

        // Connexion dont l'administrateur n'est pas connu (faite avant la 1.10.3 sans « by », ou refaite par la 1.10.2 : date
        // différente) : la tâche planifiée n'envoie rien, la connexion est retirée (jeton révoqué) avec un message clair.
        $unbound = static function (?array $by) use ($copy, $drive, $c, $key, $gstate, $gdir): array {
            $s = $drive();
            unset($s['by']);
            $s = ($by !== null ? ['by' => $by] : []) + ['ok' => time() - 2 * 86400, 'attempt' => time() - 2 * 86400] + $s;
            file_put_contents($copy . '/data/drive.php', Store::GUARD . json_encode($s));
            @unlink($gdir . '/revoked');
            $files = count($gstate()['files'] ?? []);
            $cron = trim($c->req('GET', '/_eclair/backup/' . ($key[1] ?? 'x'))['body']);
            $after = $drive();
            return ['cron' => $cron, 'sent' => count($gstate()['files'] ?? []) - $files + count(glob($copy . '/cache/backups/*.zip') ?: []),
                'revoked' => is_file($gdir . '/revoked'), 'left' => array_values(array_intersect(['email', 'since', 'by'], array_keys($after))),
                'error' => (string) ($after['error'] ?? '')];
        };
        $reconnect = static function () use ($post, $patch, $api, $drive): bool {
            $post(['action' => 'connect']);
            $patch(['pending' => ['next' => 0]]);
            $api('drive/poll');
            $s = $drive();
            return isset($s['since'], $s['by']['since']) && (int) $s['by']['since'] === (int) $s['since'];
        };
        $old = $unbound(null);
        $body = $c->req('GET', '/gestion/x/drive')['body'];
        check('connexion d’avant la 1.10.3 (administrateur non noté) : tâche planifiée en erreur, rien envoyé, jeton révoqué, connexion retirée',
            $old['cron'] === 'error' && $old['sent'] === 0 && $old['revoked'] && $old['left'] === [] && str_contains($old['error'], 'date d’avant la 1.10.3'), json_encode($old));
        check('connexion d’avant la 1.10.3 : un administrateur est invité à reconnecter Google Drive', str_contains($body, 'Un administrateur doit le reconnecter')
            && str_contains($body, 'Connecter Google Drive'));
        $rebound = $reconnect();
        $s = $drive();
        $redone = $unbound(['uid' => (string) ($s['by']['uid'] ?? ''), 'email' => 'camille@example.com', 'since' => (int) ($s['since'] ?? 0) - 60]);
        check('connexion refaite par la 1.10.2 (date différente de celle de l’administrateur noté) : rien envoyé, jeton révoqué, connexion retirée',
            $rebound && $redone['cron'] === 'error' && $redone['sent'] === 0 && $redone['revoked'] && $redone['left'] === [], json_encode($redone));
        $rebound = $reconnect();
        $patch(['ok' => time() - 2 * 86400, 'attempt' => time() - 2 * 86400]);
        $cron = $c->req('GET', '/_eclair/backup/' . ($key[1] ?? 'x'));
        check('reconnexion par un administrateur (compte noté) : la tâche planifiée envoie de nouveau', $rebound && trim($cron['body']) === 'done', trim($cron['body']));

        // Ouverture de l'administration : sauvegarde due signalée au JavaScript, qui la fait avancer en arrière-plan.
        $patch(['ok' => time() - 2 * 86400, 'attempt' => time() - 2 * 86400]);
        $dash = $c->req('GET', '/gestion');
        $tick = $api('drive/tick');
        check('administration ouverte : sauvegarde due lancée en arrière-plan', str_contains($dash['body'], '"driveDue":true') && ($tick['status'] ?? '') === 'done'
            && !str_contains($c->req('GET', '/gestion')['body'], '"driveDue":true'), (string) json_encode($tick));

        // Accès retiré depuis le compte Google : Éclair le voit et propose de se reconnecter.
        touch($gdir . '/revoked');
        $body = $post(['action' => 'now']);
        check('accès retiré chez Google : signalé, reconnexion proposée', str_contains($body, 'Accès à Google Drive retiré') && str_contains($body, 'Connecter Google Drive'));

        // Reconnexion (même compte : même dossier), puis déconnexion : jeton révoqué, sauvegardes laissées sur le Drive.
        $folders = count($gstate()['folders'] ?? []);
        $post(['action' => 'connect']);
        $patch(['pending' => ['next' => 0]]);
        $api('drive/poll');
        check('reconnexion : même dossier réutilisé', count($gstate()['folders'] ?? []) === $folders && str_contains($c->req('GET', '/gestion/x/drive')['body'], 'camille@gmail.com'));
        $body = $post(['action' => 'disconnect']);
        check('déconnexion : jeton révoqué chez Google, sauvegardes conservées', str_contains($body, 'Google Drive déconnecté') && is_file($gdir . '/revoked')
            && str_contains($body, 'Connecter Google Drive') && count($live()) === 2);
        // Application retirée : retour au guide (une reconnexion demanderait une nouvelle application).
        $body = $post(['action' => 'app-remove', 'confirm_password' => 'Un-mot-de-passe-solide']);
        check('application Google retirée : guide de nouveau proposé', str_contains($body, 'Application Google retirée de ce site') && str_contains($body, 'Enregistrer l’application Google')
            && !str_contains($body, 'Connecter Google Drive') && !str_contains((string) file_get_contents($copy . '/data/vault.php'), 'google_app'));

        // Téléchargement : même archive, sans secrets.
        $sec = $c->req('GET', '/gestion/x/drive');
        $dl = $c->req('POST', '/gestion/backup', $c->token($sec['body']));
        $tmpZip = sys_get_temp_dir() . '/eclair-dl-' . getmypid() . '.zip';
        file_put_contents($tmpZip, $dl['body']);
        $zip = new ZipArchive();
        $ok = $zip->open($tmpZip) === true && $zip->locateName('data/site.php') !== false && $zip->locateName('data/vault.php') === false && $zip->locateName('media/2026/11/grande-photo.jpg') !== false;
        $zip->close();
        @unlink($tmpZip);
        check('sauvegarde téléchargée : contenus et images, sans le coffre', $dl['status'] === 200 && str_contains($dl['headers']['content-disposition'] ?? '', 'sauvegarde-atelier-drive-') && $ok);
    } finally {
        proc_terminate($server);
        proc_terminate($google);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($gdir));
    }
}
