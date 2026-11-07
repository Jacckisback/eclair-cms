<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Faux ancien site WordPress pour les tests (serveur intégré de PHP) : sert une image PNG pour toute adresse
 * /wp-content/uploads/….png ; journalise chaque requête dans $FAKE_WP_DIR/log.jsonl.
 */
declare(strict_types=1);

$dir = (string) getenv('FAKE_WP_DIR') ?: sys_get_temp_dir();
$uri = (string) $_SERVER['REQUEST_URI'];
file_put_contents($dir . '/log.jsonl', json_encode(['u' => $uri], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
if (!preg_match('~^/wp-content/uploads/.+\.png$~', (string) parse_url($uri, PHP_URL_PATH))) {
    http_response_code(404);
    exit('not found');
}
$img = imagecreatetruecolor(640, 420);
imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 150, 90));
header('Content-Type: image/png');
imagepng($img);
