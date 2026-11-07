<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Éclair CMS — contrôleur frontal.
 *
 * Les pages publiques déjà générées sont servies directement depuis le cache
 * (aucune lecture de contenu, aucun rendu) : c'est ce qui rend Éclair si rapide.
 */
declare(strict_types=1);

define('ECLAIR', true);
define('ECLAIR_VERSION', '2.0.0');
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
    // Visites des robots d'IA (Réglages › IA & GEO, voir app/lib/AiVisits.php) : seulement l'heure, le robot et l'adresse,
    // jamais d'IP ni de visiteur humain. Une seule expression sur l'agent ; journal du mois borné à 4 Mo.
    if (is_array($rt) && is_string($rt['ai'] ?? null) && is_string($rt['ai_re'] ?? null)
        && preg_match($rt['ai_re'], (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $bot)) {
        $log = $rt['ai'] . '/' . gmdate('Y-m') . '.php';
        $size = @filesize($log);
        if ($size === false || $size < 4194304) {
            $uri = substr((string) preg_replace('~[\x00-\x20\x7f]+~', '', explode('?', (string) ($_SERVER['REQUEST_URI'] ?? '/'), 2)[0]), 0, 300);
            @file_put_contents($log, ($size === false ? "<?php exit; ?>\n" : '') . time() . "\t" . $bot[0] . "\t" . $uri . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    // Cache d'une autre version (mise à jour par FTP) : l'application le vide et le régénère.
    if (!is_array($rt) || empty($rt['on']) || ($rt['v'] ?? '') !== ECLAIR_VERSION || (!empty($rt['expires']) && time() >= $rt['expires'])) {
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
    // Page d'une autre génération (rendue à partir de données lues avant la dernière purge) : jamais servie, l'application la refait.
    $generation = (string) ($rt['g'] ?? '');
    if (!is_array($meta) || $generation === '' || ($meta['g'] ?? null) !== $generation) {
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
