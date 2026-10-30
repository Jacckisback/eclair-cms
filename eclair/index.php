<?php
/**
 * Éclair CMS — contrôleur frontal.
 *
 * Les pages publiques déjà générées sont servies directement depuis le cache
 * (aucune lecture de contenu, aucun rendu) : c'est ce qui rend Éclair si rapide.
 */
declare(strict_types=1);

define('ECLAIR', true);
define('ECLAIR_VERSION', '1.3.0');
define('ECLAIR_ROOT', __DIR__);

(static function (): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }
    $raw = @file_get_contents(__DIR__ . '/cache/runtime.php');
    if ($raw === false) {
        return;
    }
    $rt = json_decode(substr($raw, (int) strpos($raw, "\n") + 1), true);
    if (!is_array($rt) || empty($rt['on']) || (!empty($rt['expires']) && time() >= $rt['expires'])) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
    if ((!empty($rt['https']) && !$https)
        || (!empty($rt['host']) && strcasecmp($_SERVER['HTTP_HOST'] ?? '', $rt['host']) !== 0)) {
        return;
    }
    $path = rawurldecode((string) parse_url('http://x' . ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/.');
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base));
    }
    if ($path === '') {
        $path = '/';
    }
    $file = __DIR__ . '/cache/pages/' . md5($path);
    $h = @fopen($file, 'rb');
    if ($h === false) {
        return;
    }
    $meta = json_decode((string) fgets($h), true);
    if (!is_array($meta)) {
        fclose($h);
        return;
    }
    header_remove('X-Powered-By');
    foreach ($meta['h'] ?? [] as $line) {
        header($line);
    }
    $etag = '"' . ($meta['e'] ?? '') . '"';
    header('ETag: ' . $etag);
    header('X-Cache: HIT');
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . (filesize($file) - ftell($h)));
    if ($method === 'GET') {
        fpassthru($h);
    }
    exit;
})();

require __DIR__ . '/app/bootstrap.php';
