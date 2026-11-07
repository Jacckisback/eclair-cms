<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Routeur de mesure (outil de développement, jamais livré) : tools/router.php, plus une ligne JSON par requête dans le
 * fichier ECLAIR_MEASURE_LOG (mémoire de pointe, fichiers PHP inclus, durée). Les fichiers statiques ne sont pas notés.
 */
$__start = microtime(true);
// Le serveur intégré traite plusieurs requêtes par processus : pointe mémoire remise à zéro à chaque requête (PHP 8.2+).
if (function_exists('memory_reset_peak_usage')) {
    memory_reset_peak_usage();
}
$__log = (string) getenv('ECLAIR_MEASURE_LOG');
register_shutdown_function(static function () use ($__start, &$__log): void {
    if ($__log === '') {
        return;
    }
    $files = array_values(array_filter(get_included_files(), static fn (string $f): bool => !str_ends_with($f, 'measure-router.php') && !str_ends_with($f, '/router.php')));
    @file_put_contents($__log, json_encode(['uri' => $_SERVER['REQUEST_URI'] ?? '', 'mem' => memory_get_peak_usage(), 'files' => count($files),
        'ms' => round((microtime(true) - $__start) * 1000, 2)]) . "\n", FILE_APPEND | LOCK_EX);
});
$__result = require __DIR__ . '/router.php';
if ($__result === false) {
    $__log = '';
    return false;
}
