<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Contrôle d'intégrité : compare les fichiers d'Éclair à leur empreinte d'origine
 * (app/manifest.php) et cherche des fichiers suspects, notamment dans les médias.
 * Indispensable sur un mutualisé, où les infections passent souvent inaperçues.
 */
final class Integrity
{
    public static function check(): array
    {
        $manifestFile = ECLAIR_ROOT . '/app/manifest.php';
        $raw = @file_get_contents($manifestFile);
        $manifest = $raw === false ? null : json_decode(Store::strip($raw), true);
        $result = ['date' => time(), 'manifest' => is_array($manifest), 'modified' => [], 'missing' => [], 'unknown' => [], 'suspicious' => []];
        if (is_array($manifest)) {
            foreach ($manifest['files'] ?? [] as $path => $hash) {
                $file = ECLAIR_ROOT . '/' . $path;
                if (!is_file($file)) {
                    $result['missing'][] = $path;
                } elseif (strlen((string) $hash) < 32 || !hash_equals((string) $hash, substr((string) hash_file('sha256', $file), 0, strlen((string) $hash)))) {
                    // Empreinte SHA-256 complète, ou ses 128 premiers bits (manifeste compact des archives depuis la 1.7).
                    $result['modified'][] = $path;
                }
            }
            foreach (self::scan(['app', 'assets']) as $path) {
                // Ancienne vue .php restée lors d'une mise à jour : jamais lue, sa version compressée (.gz) l'emporte.
                $stale = isset($manifest['files'][$path . '.gz']);
                if (!isset($manifest['files'][$path]) && $path !== 'app/manifest.php' && !$stale) {
                    $result['unknown'][] = $path;
                }
            }
            foreach (glob(ECLAIR_ROOT . '/*') ?: [] as $file) {
                $name = basename($file);
                if (is_file($file) && !isset($manifest['files'][$name]) && !in_array($name, ['eclair-config.php'], true)) {
                    $result['unknown'][] = $name;
                }
            }
        }
        foreach (self::scan(['media']) as $path) {
            if (!preg_match('~\.(jpe?g|png|gif|webp|avif)$~i', $path) && !preg_match('~^media/fonts/[a-z0-9-]+\.woff2$~', $path) && !in_array(basename($path), ['index.html', '.htaccess'], true)) {
                $result['suspicious'][] = $path;
            }
        }
        // Version gardée par la dernière mise à jour : chaque fichier doit correspondre à son propre manifeste.
        $kept = 'cache/update/previous/';
        $raw = @file_get_contents(ECLAIR_ROOT . '/' . $kept . 'app/manifest.php');
        $previous = $raw === false ? [] : (array) (json_decode(Store::strip($raw), true)['files'] ?? []);
        foreach (self::scan(['data', 'cache']) as $path) {
            if (str_starts_with($path, $kept)) {
                $rel = substr($path, strlen($kept));
                $hash = (string) ($previous[$rel] ?? '');
                $known = $rel === 'app/manifest.php' || in_array($rel, ['index.php.txt', 'htaccess.txt'], true) || isset($previous[$rel . '.gz'])
                    || (strlen($hash) >= 32 && hash_equals($hash, substr((string) hash_file('sha256', ECLAIR_ROOT . '/' . $path), 0, strlen($hash))));
                if (!$known) {
                    $result['unknown'][] = $path;
                }
                continue;
            }
            if (preg_match('~\.(phtml|phar|php\d|sh|cgi|pl|py|html?)$~i', $path) && basename($path) !== 'index.html') {
                $result['suspicious'][] = $path;
            } elseif (str_ends_with($path, '.php') && !self::guarded($path)) {
                // Tout fichier .php des données doit commencer par la ligne de protection : sinon, c'est un intrus.
                $result['suspicious'][] = $path;
            }
        }
        return $result;
    }

    private static function guarded(string $path): bool
    {
        $file = str_starts_with($path, 'data/') ? ECLAIR_DATA . substr($path, 4) : ECLAIR_ROOT . '/' . $path;
        $head = (string) @file_get_contents($file, false, null, 0, strlen(Store::GUARD));
        return $head === Store::GUARD || ($path === 'data/reset.php' && str_starts_with($head, '<?php'));
    }

    /** Liste récursive (chemins relatifs à la racine d'Éclair). */
    public static function scan(array $dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $root = $dir === 'data' ? ECLAIR_DATA : ECLAIR_ROOT . '/' . $dir;
            if (!is_dir($root)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $path => $info) {
                if ($info->isFile()) {
                    $out[] = $dir . '/' . str_replace('\\', '/', substr((string) $path, strlen($root) + 1));
                }
            }
        }
        sort($out);
        return $out;
    }
}
