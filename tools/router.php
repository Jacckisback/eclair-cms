<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Routeur pour le serveur de développement intégré de PHP (reproduit les règles du .htaccess) :
 *   php -S localhost:8000 -t eclair tools/router.php
 */
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
// Sans tenir compte de la casse : sur un disque qui l'ignore (macOS, Windows), /DATA/ mène au dossier data/.
// Médias : les mêmes extensions refusées que media/.htaccess (et le guide Nginx).
if (preg_match('#^/(app|data|cache)(/|$)#i', $path) || preg_match('#(^|/)\.(?!well-known/)#', $path)
    || (preg_match('#\.(php\d?|phtml|phar|inc)$#i', $path) && $path !== '/index.php')
    || (stripos($path, '/media/') === 0 && (preg_match('#\.(php\d?|phtml|phar|inc|pl|py|cgi|sh|asp|aspx|jsp|s?html?|xht(ml)?|svgz?|xml|m?js)$#i', $path) || is_dir($root . $path)))) {
    http_response_code(404);
    exit('Not found');
}
if ($path !== '/' && is_file($root . $path)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
