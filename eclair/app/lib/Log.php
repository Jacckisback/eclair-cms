<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Journaux (erreurs et événements de sécurité), au format JSON ligne par ligne. */
final class Log
{
    private const MAX_BYTES = 1048576;

    /** Enregistre une erreur et renvoie une référence courte à communiquer. */
    public static function error(\Throwable $e): string
    {
        $id = substr(bin2hex(random_bytes(4)), 0, 8);
        self::append('errors', [
            'id' => $id,
            'msg' => mb_substr($e->getMessage(), 0, 500),
            'at' => basename($e->getFile()) . ':' . $e->getLine(),
            'path' => mb_substr(Req::path(), 0, 200),
        ]);
        return $id;
    }

    public static function security(string $event, array $context = []): void
    {
        $user = Auth::user();
        self::append('security', [
            'event' => $event,
            'ip' => Req::ip(),
            'user' => $user['email'] ?? ($context['email'] ?? ''),
            'ua' => mb_substr(Req::userAgent(), 0, 120),
        ] + $context);
    }

    /** Dernières entrées, de la plus récente à la plus ancienne. */
    public static function read(string $name, int $limit = 200): array
    {
        $raw = @file_get_contents(self::file($name));
        if ($raw === false) {
            return [];
        }
        $lines = array_slice(explode("\n", trim(Store::strip($raw))), -$limit);
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private static function file(string $name): string
    {
        return ECLAIR_DATA . '/logs/' . $name . '.php';
    }

    private static function append(string $name, array $entry): void
    {
        try {
            if (!is_dir(ECLAIR_DATA) || !is_writable(ECLAIR_DATA)) {
                error_log('[eclair] ' . json_encode($entry));
                return;
            }
            Store::ensureDir(ECLAIR_DATA . '/logs');
            $file = self::file($name);
            $line = json_encode(['t' => time()] + $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
            if (!is_file($file)) {
                file_put_contents($file, Store::GUARD . $line, LOCK_EX);
                return;
            }
            if (filesize($file) > self::MAX_BYTES) {
                $lines = explode("\n", trim(Store::strip((string) file_get_contents($file))));
                Store::atomic($file, Store::GUARD . implode("\n", array_slice($lines, -2000)) . "\n" . $line);
                return;
            }
            file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Un journal ne doit jamais faire tomber le site.
        }
    }
}
