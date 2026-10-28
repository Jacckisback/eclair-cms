<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Sessions stockées dans data/sessions, sous un nom haché :
 * même en listant le dossier, impossible de retrouver un identifiant de session valide.
 * Les sessions ne sont démarrées que pour l'administration (le site public reste sans cookie).
 */
final class Session implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public const NAME = 'eclair_sid';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = 86400 * 2;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cache_limiter', '');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
        session_name(self::NAME);
        session_set_save_handler(new self(), true);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Req::base() . '/',
            'secure' => Req::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function end(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        setcookie(self::NAME, '', [
            'expires' => time() - 3600,
            'path' => Req::base() . '/',
            'secure' => Req::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /** Message affiché une seule fois après une redirection. */
    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][] = [$type, $message];
    }

    public static function pullFlash(): array
    {
        self::start();
        $out = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($out) ? $out : [];
    }

    private function path(string $id): string
    {
        return ECLAIR_DATA . '/sessions/' . hash('sha256', $id) . '.php';
    }

    public function open(string $path, string $name): bool
    {
        Store::ensureDir(ECLAIR_DATA . '/sessions');
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $raw = @file_get_contents($this->path($id));
        return $raw === false ? '' : Store::strip($raw);
    }

    public function write(string $id, string $data): bool
    {
        try {
            Store::atomic($this->path($id), Store::GUARD . $data);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        $file = $this->path($id);
        return !is_file($file) || @unlink($file);
    }

    public function gc(int $max_lifetime): int|false
    {
        $count = 0;
        foreach (glob(ECLAIR_DATA . '/sessions/*.php') ?: [] as $file) {
            if (filemtime($file) + $max_lifetime < time() && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public function validateId(string $id): bool
    {
        return is_file($this->path($id));
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return @touch($this->path($id));
    }
}
