<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Mesure d'une requête publique dans un processus PHP neuf (outil de développement, jamais livré) : mémoire de pointe et
 * fichiers PHP inclus, sans l'état d'un serveur déjà en marche. Affiche une ligne JSON.
 *   php tools/measure-cli.php <dossier d'Éclair> <adresse> <hôte:port>
 */
[$__self, $__root, $__uri, $__host] = $argv + [null, '', '/', '127.0.0.1'];
$_SERVER = array_merge($_SERVER, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $__uri, 'QUERY_STRING' => (string) parse_url($__uri, PHP_URL_QUERY),
    'HTTP_HOST' => $__host, 'SERVER_NAME' => explode(':', $__host)[0], 'SERVER_PORT' => (string) (explode(':', $__host)[1] ?? '80'),
    'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $__root . '/index.php', 'DOCUMENT_ROOT' => $__root, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'mesure']);
parse_str((string) $_SERVER['QUERY_STRING'], $_GET);
register_shutdown_function(static function (): void {
    $files = array_values(array_filter(get_included_files(), static fn (string $f): bool => !str_ends_with($f, 'measure-cli.php')));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDERR, json_encode(['mem' => memory_get_peak_usage(), 'files' => count($files)]) . "\n");
});
ob_start();
chdir($__root);
require $__root . '/index.php';
