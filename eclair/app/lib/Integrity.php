<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Contrôle d'intégrité : compare les fichiers d'Éclair à leur empreinte d'origine
 * (app/manifest.php) et cherche des fichiers suspects, notamment dans les médias et le thème personnalisé.
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
                } elseif (!hash_equals((string) $hash, hash_file('sha256', $file))) {
                    $result['modified'][] = $path;
                }
            }
            foreach (self::scan(['app', 'assets']) as $path) {
                if (!isset($manifest['files'][$path]) && $path !== 'app/manifest.php') {
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
            if (!preg_match('~\.(jpe?g|png|gif|webp|avif)$~i', $path) && !in_array(basename($path), ['index.html', '.htaccess'], true)) {
                $result['suspicious'][] = $path;
            }
        }
        foreach (self::scan(['data', 'cache']) as $path) {
            if (preg_match('~\.(phtml|phar|php\d|sh|cgi|pl|py|html?)$~i', $path) && basename($path) !== 'index.html') {
                $result['suspicious'][] = $path;
            } elseif (str_ends_with($path, '.php') && !self::guarded($path)) {
                // Tout fichier .php des données doit commencer par la ligne de protection : sinon, c'est un intrus.
                $result['suspicious'][] = $path;
            }
        }
        // Thème personnalisé : jamais « inconnu » (il n'a pas d'empreinte d'origine), mais surveillé de près :
        // aucun script exécutable, et chaque gabarit .php commence par sa ligne de protection.
        foreach (self::scan(['theme']) as $path) {
            if (preg_match('~\.(phtml|phar|php\d|inc|sh|cgi|pl|py|s?html?)$~i', $path) && basename($path) !== 'index.html') {
                $result['suspicious'][] = $path;
            } elseif (preg_match('~\.php$~i', $path) && !self::template(Theme::dir() . substr($path, 5))) {
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

    /**
     * Gabarit inerte s'il est appelé directement : sa première instruction (après d'éventuels commentaires)
     * est « defined('ECLAIR') || exit; », comme dans app/theme.
     */
    private static function template(string $file): bool
    {
        $head = (string) @file_get_contents($file, false, null, 0, 8192);
        return (bool) preg_match('~^(?:\xEF\xBB\xBF)?<\?php\s++(?:(?:/\*.*?\*/|(?://|#)[^\n]*+|declare\([^)]*+\)\s*+;)\s*+)*+'
            . 'defined\(\s*+([\'"])ECLAIR\1\s*+\)\s*+(?:\|\||or)\s*+(?:exit|die)\b~s', $head);
    }

    /** Liste récursive (chemins relatifs à la racine d'Éclair ; « data » et « theme » peuvent être ailleurs). */
    public static function scan(array $dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $root = match ($dir) {
                'data' => ECLAIR_DATA,
                'theme' => Theme::dir(),
                default => ECLAIR_ROOT . '/' . $dir,
            };
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
