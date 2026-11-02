<?php
/**
 * Faux Google (OAuth « appareil » + Drive v3) pour les tests des sauvegardes : aucun compte réel n'est utilisé.
 *   FAKE_GOOGLE_DIR=/tmp/x php -S 127.0.0.1:9999 tests/fake-google.php
 * Fichiers de contrôle dans FAKE_GOOGLE_DIR : approved / denied (réponse de l'utilisateur), fail-chunk (prochain morceau
 * refusé une fois), revoked (accès retiré). Journal des requêtes : log.txt ; état : state.json ; octets reçus : <session>.bin.
 */
declare(strict_types=1);

$dir = (string) getenv('FAKE_GOOGLE_DIR');
$method = $_SERVER['REQUEST_METHOD'];
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = (string) file_get_contents('php://input');
parse_str($body, $form);
file_put_contents($dir . '/log.txt', $method . ' ' . $path . ' ' . ($form['grant_type'] ?? '') . "\n", FILE_APPEND | LOCK_EX);

$lock = fopen($dir . '/lock', 'c');
flock($lock, LOCK_EX);
$state = json_decode((string) @file_get_contents($dir . '/state.json'), true) ?: ['folders' => [], 'files' => [], 'sessions' => [], 'seq' => 0, 'device' => []];
$reply = static function (int $status, array $data = [], array $headers = []) use (&$state, $dir, $lock): never {
    file_put_contents($dir . '/state.json', json_encode($state));
    flock($lock, LOCK_UN);
    foreach ($headers as $h) {
        header($h);
    }
    http_response_code($status); // après « Location: », que PHP transformerait sinon en redirection 302
    header('Content-Type: application/json');
    echo $data ? json_encode($data) : '';
    exit;
};
$flag = static fn (string $name): bool => is_file($dir . '/' . $name);
$bearer = static fn (): string => preg_match('/^Bearer (.+)$/', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m) ? $m[1] : '';
$authed = static function () use ($bearer, $flag, $reply): void {
    if (!str_starts_with($bearer(), 'at-') || $flag('revoked')) {
        $reply(401, ['error' => ['code' => 401, 'message' => 'Invalid Credentials']]);
    }
};

// OAuth « appareil »
if ($method === 'POST' && $path === '/device/code') {
    $state['device'] = ['client_id' => $form['client_id'] ?? '', 'scope' => $form['scope'] ?? ''];
    $reply(200, ['device_code' => 'dev-123', 'user_code' => 'ABCD-EFGH', 'verification_url' => 'https://www.google.com/device', 'expires_in' => 1800, 'interval' => 5]);
}
if ($method === 'POST' && $path === '/token') {
    if (($form['client_id'] ?? '') !== '123-test.apps.googleusercontent.com' || ($form['client_secret'] ?? '') !== 'secret-de-test') {
        $reply(401, ['error' => 'invalid_client']);
    }
    if (($form['grant_type'] ?? '') === 'refresh_token') {
        return $flag('revoked') || ($form['refresh_token'] ?? '') !== 'rt-1'
            ? $reply(400, ['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'])
            : $reply(200, ['access_token' => 'at-' . (++$state['seq']), 'expires_in' => 3599, 'token_type' => 'Bearer']);
    }
    if (($form['device_code'] ?? '') !== 'dev-123') {
        $reply(400, ['error' => 'invalid_grant']);
    }
    if ($flag('denied')) {
        $reply(403, ['error' => 'access_denied']);
    }
    if (!$flag('approved')) {
        $reply(428, ['error' => 'authorization_pending']);
    }
    @unlink($dir . '/revoked');
    $reply(200, ['access_token' => 'at-0', 'refresh_token' => 'rt-1', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/drive.file', 'token_type' => 'Bearer']);
}
if ($method === 'POST' && $path === '/revoke') {
    touch($dir . '/revoked');
    $reply(200);
}

// Drive v3
if ($method === 'GET' && $path === '/drive/v3/about') {
    $authed();
    $reply(200, ['user' => ['emailAddress' => 'camille@gmail.com']]);
}
if ($method === 'POST' && $path === '/drive/v3/files') {
    $authed();
    $meta = json_decode($body, true);
    $id = 'folder' . (++$state['seq']);
    $state['folders'][$id] = ['name' => $meta['name'] ?? '', 'mimeType' => $meta['mimeType'] ?? '', 'trashed' => false];
    $reply(200, ['id' => $id]);
}
if ($method === 'GET' && $path === '/drive/v3/files') {
    $authed();
    preg_match("/'([^']+)' in parents/", (string) ($_GET['q'] ?? ''), $m);
    $files = array_values(array_filter($state['files'], static fn (array $f): bool => $f['parent'] === ($m[1] ?? '') && !$f['trashed']));
    usort($files, static fn (array $a, array $b): int => $b['createdTime'] <=> $a['createdTime']);
    $reply(200, ['files' => array_map(static fn (array $f): array => ['id' => $f['id'], 'name' => $f['name'], 'createdTime' => $f['createdTime']], $files)]);
}
if (preg_match('~^/drive/v3/files/([A-Za-z0-9_-]+)$~', $path, $m)) {
    $authed();
    if ($method === 'GET') {
        isset($state['folders'][$m[1]]) ? $reply(200, ['id' => $m[1], 'trashed' => $state['folders'][$m[1]]['trashed']]) : $reply(404, ['error' => ['code' => 404, 'message' => 'File not found']]);
    }
    if ($method === 'PATCH') {
        foreach ($state['files'] as $i => $f) {
            if ($f['id'] === $m[1]) {
                $state['files'][$i]['trashed'] = (bool) (json_decode($body, true)['trashed'] ?? false);
                $reply(200, ['id' => $m[1]]);
            }
        }
        $reply(404, ['error' => ['code' => 404]]);
    }
}
if ($method === 'POST' && $path === '/upload/drive/v3/files' && ($_GET['uploadType'] ?? '') === 'resumable') {
    $authed();
    $meta = json_decode($body, true);
    $sid = 'up' . (++$state['seq']);
    $state['sessions'][$sid] = ['name' => $meta['name'] ?? '', 'parent' => $meta['parents'][0] ?? '', 'total' => (int) ($_SERVER['HTTP_X_UPLOAD_CONTENT_LENGTH'] ?? 0), 'done' => ''];
    file_put_contents($dir . '/' . $sid . '.bin', '');
    $reply(200, [], ['Location: http://' . $_SERVER['HTTP_HOST'] . '/upload/session/' . $sid]);
}
if ($method === 'PUT' && preg_match('~^/upload/session/(up\d+)$~', $path, $m) && isset($state['sessions'][$m[1]])) {
    $sid = $m[1];
    $s = $state['sessions'][$sid];
    $file = $dir . '/' . $sid . '.bin';
    clearstatcache();
    $have = (int) filesize($file);
    $done = static fn () => $reply(201, ['id' => $s['done']]);
    $partial = static fn (int $n) => $reply(308, [], $n > 0 ? ['Range: bytes=0-' . ($n - 1)] : []);
    if ($s['done'] !== '') {
        $done();
    }
    $range = (string) ($_SERVER['HTTP_CONTENT_RANGE'] ?? '');
    if (preg_match('~^bytes \*/(\d+)$~', $range)) {
        $partial($have);
    }
    if (!preg_match('~^bytes (\d+)-(\d+)/(\d+)$~', $range, $r) || (int) $r[1] !== $have || strlen($body) !== (int) $r[2] - (int) $r[1] + 1) {
        $reply(400, ['error' => ['code' => 400, 'message' => 'Bad range ' . $range . ' (have ' . $have . ')']]);
    }
    if ($have > 0 && $flag('fail-chunk')) {
        unlink($dir . '/fail-chunk');
        // Une partie du morceau arrive quand même : la reprise doit repartir de ce que Google a vraiment reçu.
        file_put_contents($file, substr($body, 0, 262144), FILE_APPEND);
        $reply(503, ['error' => ['code' => 503, 'message' => 'Backend Error']]);
    }
    file_put_contents($file, $body, FILE_APPEND);
    $have += strlen($body);
    if ($have < $s['total']) {
        $partial($have);
    }
    $id = 'file' . (++$state['seq']);
    $state['sessions'][$sid]['done'] = $id;
    $state['files'][] = ['id' => $id, 'name' => $s['name'], 'parent' => $s['parent'], 'createdTime' => sprintf('%s.%03dZ', gmdate('Y-m-d\TH:i:s'), $state['seq']), 'size' => $have, 'trashed' => false, 'bin' => $sid . '.bin'];
    $reply(201, ['id' => $id]);
}
$reply(404, ['error' => ['code' => 404, 'message' => 'Unknown ' . $method . ' ' . $path]]);
