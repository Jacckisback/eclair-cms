<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Stockage à plat, sans base de données.
 *
 * Chaque donnée est un fichier .php qui commence par une instruction de sortie :
 * même si un serveur ignorait les .htaccess, l'appeler depuis le web n'affiche rien.
 * Les écritures sont atomiques (fichier temporaire puis renommage).
 */
final class Store
{
    public const GUARD = "<?php exit; ?>\n";

    /** @var array<string, mixed> */
    private static array $memo = [];
    private static int $depth = 0;

    public static function file(string $name): string
    {
        if (!preg_match('~^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$~', $name)) {
            throw new \InvalidArgumentException('Nom de donnée invalide : ' . $name);
        }
        return ECLAIR_DATA . '/' . $name . '.php';
    }

    public static function exists(string $name): bool
    {
        return array_key_exists($name, self::$memo) || is_file(self::file($name));
    }

    public static function read(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, self::$memo)) {
            return self::$memo[$name];
        }
        $raw = @file_get_contents(self::file($name));
        if ($raw === false) {
            return $default;
        }
        $data = json_decode(self::strip($raw), true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::error(new \RuntimeException('Fichier de données illisible : ' . $name));
            return $default;
        }
        return self::$memo[$name] = $data;
    }

    public static function write(string $name, mixed $data): void
    {
        $file = self::file($name);
        self::ensureDir(dirname($file));
        // HEX_TAG/HEX_AMP : aucune séquence « <?php » ne peut apparaître dans un fichier de données.
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
        self::atomic($file, self::GUARD . $json);
        self::$memo[$name] = $data;
    }

    public static function delete(string $name): void
    {
        unset(self::$memo[$name]);
        $file = self::file($name);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Noms des données d'un sous-dossier (ex. « content » → ["content/ab12…", …]). */
    public static function names(string $dir): array
    {
        $out = [];
        foreach (glob(ECLAIR_DATA . '/' . $dir . '/*.php') ?: [] as $file) {
            $base = basename($file, '.php');
            if (preg_match('~^[a-z0-9_-]+$~', $base)) {
                $out[] = $dir . '/' . $base;
            }
        }
        return $out;
    }

    /** Exécute une opération lire-modifier-écrire sous verrou exclusif (réentrant). */
    public static function locked(callable $fn): mixed
    {
        if (self::$depth > 0) {
            return $fn();
        }
        self::ensureDir(ECLAIR_DATA);
        $h = @fopen(ECLAIR_DATA . '/.lock', 'c');
        if ($h === false) {
            return $fn();
        }
        flock($h, LOCK_EX);
        self::$depth++;
        try {
            self::$memo = [];
            return $fn();
        } finally {
            self::$depth--;
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    /** Écrit un fichier de façon atomique. */
    /**
     * Écrit un fichier de façon atomique, lisible uniquement par PHP (0600).
     * Le fichier temporaire ne porte l'extension .php que s'il commence par la ligne de protection.
     */
    public static function atomic(string $file, string $content): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . (str_starts_with($content, self::GUARD) ? '.tmp.php' : '.tmp');
        if (@file_put_contents($tmp, $content, LOCK_EX) !== strlen($content)) {
            @unlink($tmp);
            throw new \RuntimeException("Impossible d'écrire « " . basename($file) . ' » : vérifie les droits du dossier.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException("Impossible d'enregistrer « " . basename($file) . ' ».');
        }
    }

    /** Crée un dossier : privé (0700) pour les données et le cache, public (0755) ailleurs (médias). */
    public static function ensureDir(string $dir): void
    {
        $private = str_starts_with($dir, ECLAIR_DATA) || str_starts_with($dir, ECLAIR_ROOT . '/cache');
        if (!is_dir($dir) && !@mkdir($dir, $private ? 0700 : 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier ' . basename($dir) . '.');
        }
    }

    /** Retire la ligne de protection en tête de fichier. */
    public static function strip(string $raw): string
    {
        return str_starts_with($raw, '<?php') ? substr($raw, (int) strpos($raw, "\n") + 1) : $raw;
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
