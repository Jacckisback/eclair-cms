<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Lecture prudente de la requête HTTP courante. */
final class Req
{
    private static ?string $path = null;
    private static ?string $base = null;
    private static ?array $json = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Dossier d'installation (« » à la racine, « /site » dans un sous-dossier). */
    public static function base(): string
    {
        return self::$base ??= rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/.');
    }

    /** Chemin demandé, décodé, sans le dossier d'installation : « / », « /blog/mon-article »… */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }
        $path = rawurldecode((string) parse_url('http://x' . ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $base = self::base();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base));
        }
        return self::$path = ($path === '' ? '/' : $path);
    }

    public static function queryString(): string
    {
        return (string) ($_SERVER['QUERY_STRING'] ?? '');
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
    }

    public static function host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        return preg_match('/^[a-z0-9.\-]+(?::\d{1,5})?$|^\[[0-9a-f:.]+\](?::\d{1,5})?$/', $host) ? $host : 'localhost';
    }

    /** Origine courante : schéma + hôte + dossier (« https://exemple.fr/site »). */
    public static function origin(): string
    {
        return (self::isHttps() ? 'https://' : 'http://') . self::host() . self::base();
    }

    /**
     * Adresse IP du visiteur. L'en-tête d'un proxy n'est lu que s'il a été choisi dans les réglages,
     * et seulement sa dernière adresse : celle ajoutée par le proxy (les précédentes viennent du client).
     */
    public static function ip(): string
    {
        $proxy = Settings::installed() ? (string) Settings::get('security.proxy') : '';
        if ($proxy !== '' && !empty($_SERVER[$proxy]) && is_string($_SERVER[$proxy])) {
            $hops = explode(',', $_SERVER[$proxy]);
            $ip = trim((string) end($hops));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /** Réseau du visiteur pour les limites : l'adresse IPv4, ou le bloc /64 en IPv6 (un abonné en possède des milliards). */
    public static function ipPrefix(): string
    {
        $ip = self::ip();
        $bin = str_contains($ip, ':') ? @inet_pton($ip) : false;
        if ($bin === false || strlen($bin) !== 16) {
            return $ip;
        }
        if (str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($bin, 12));
        }
        return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
    }

    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($_SERVER[$key] ?? '');
    }

    /** Champ de formulaire texte (les tableaux sont ignorés). */
    public static function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? str_replace("\0", '', $value) : $default;
    }

    public static function bool(string $key): bool
    {
        return in_array(self::input($key), ['1', 'on', 'true', 'yes'], true);
    }

    /** Le formulaire envoyé contient-il ce champ texte, même vide ? */
    public static function has(string $key): bool
    {
        return is_string($_POST[$key] ?? null);
    }

    public static function inputArray(string $key): array
    {
        $value = $_POST[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /** Corps JSON (2 Mo maximum). */
    public static function json(): array
    {
        if (self::$json === null) {
            $raw = (string) file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024 + 1);
            if (strlen($raw) > 2 * 1024 * 1024) {
                Http::json(['ok' => false, 'error' => t('Contenu trop volumineux.')], 413);
            }
            $data = json_decode($raw, true);
            self::$json = is_array($data) ? $data : [];
        }
        return self::$json;
    }

    public static function wantsJson(): bool
    {
        return str_contains(self::header('Accept'), 'application/json')
            || str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
    }
}
