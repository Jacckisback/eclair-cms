<?php
/**
 * Routeur pour le serveur de développement intégré de PHP (reproduit les règles du .htaccess) :
 *   php -S localhost:8000 -t eclair tools/router.php
 */
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(app|data|cache)(/|$)#', $path) || preg_match('#(^|/)\.(?!well-known/)#', $path)
    || (preg_match('#\.(php\d?|phtml|phar|inc)$#i', $path) && $path !== '/index.php')
    || (str_starts_with($path, '/media/') && (preg_match('#\.(s?html?|svgz?|xml|js|php\d?)$#i', $path) || is_dir($root . $path)))) {
    http_response_code(404);
    exit('Not found');
}
if ($path !== '/' && is_file($root . $path)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
