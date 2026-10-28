<?php
/**
 * Éclair CMS — démarrage de l'application (requêtes non servies par le cache).
 * Ce fichier reste compatible avec les vieux PHP pour pouvoir afficher un message clair.
 */
defined('ECLAIR') || exit;

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Éclair</title>'
        . '<p style="font:17px/1.6 system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem">'
        . '⚡ <strong>Éclair a besoin de PHP 8.1 ou plus récent.</strong><br>Version actuelle : ' . PHP_VERSION
        . '.<br>Change la version de PHP dans l\'espace client de ton hébergeur (souvent en un clic), puis recharge cette page.</p>';
    exit;
}

// Réglages avancés facultatifs (emplacement des données, mode débogage…).
if (is_file(ECLAIR_ROOT . '/eclair-config.php')) {
    require ECLAIR_ROOT . '/eclair-config.php';
}
defined('ECLAIR_DATA') || define('ECLAIR_DATA', ECLAIR_ROOT . '/data');
defined('ECLAIR_DEBUG') || define('ECLAIR_DEBUG', false);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Eclair\\', 7) === 0) {
        $file = ECLAIR_ROOT . '/app/lib/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require ECLAIR_ROOT . '/app/lib/helpers.php';

Eclair\App::run();
