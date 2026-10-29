<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Journaux (erreurs et événements de sécurité), au format JSON ligne par ligne.
 * Taille bornée : chaque champ est tronqué, et au-delà de 1 Mo seuls les 256 derniers Ko sont gardés.
 */
final class Log
{
    private const MAX_BYTES = 1048576;
    private const KEEP_BYTES = 262144;

    /** Enregistre une erreur et renvoie une référence courte à communiquer. */
    public static function error(\Throwable $e): string
    {
        $id = substr(bin2hex(random_bytes(4)), 0, 8);
        self::append('errors', [
            'id' => $id,
            'msg' => $e->getMessage(),
            'at' => basename($e->getFile()) . ':' . $e->getLine(),
            'path' => Req::path(),
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

    /** Dernières entrées, de la plus récente à la plus ancienne (seule la fin du fichier est lue). */
    public static function read(string $name, int $limit = 200): array
    {
        $h = @fopen(self::file($name), 'rb');
        if ($h === false) {
            return [];
        }
        $size = (int) fstat($h)['size'];
        $start = max(0, $size - self::KEEP_BYTES);
        fseek($h, $start);
        $raw = (string) stream_get_contents($h);
        fclose($h);
        $lines = explode("\n", trim($start === 0 ? Store::strip($raw) : substr($raw, (int) strpos($raw, "\n") + 1)));
        $out = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
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
            foreach ($entry as $k => $value) {
                $entry[$k] = is_scalar($value) || $value === null ? mb_substr((string) $value, 0, 300) : '';
            }
            // HEX_TAG/HEX_AMP : aucune séquence « <?php » ne peut apparaître dans le journal.
            $line = json_encode(['t' => time()] + $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP) . "\n";
            if (!is_dir(ECLAIR_DATA) || !is_writable(ECLAIR_DATA)) {
                error_log('[eclair] ' . $line);
                return;
            }
            Store::ensureDir(ECLAIR_DATA . '/logs');
            $file = self::file($name);
            $head = (string) @file_get_contents($file, false, null, 0, strlen(Store::GUARD));
            if ($head !== Store::GUARD) {
                // Fichier absent ou sans ligne de protection : on repart d'un journal propre.
                Store::atomic($file, Store::GUARD . $line);
                return;
            }
            clearstatcache(true, $file);
            if ((int) filesize($file) > self::MAX_BYTES) {
                $h = fopen($file, 'rb');
                fseek($h, -self::KEEP_BYTES, SEEK_END);
                $tail = (string) stream_get_contents($h);
                fclose($h);
                Store::atomic($file, Store::GUARD . substr($tail, (int) strpos($tail, "\n") + 1) . $line);
                return;
            }
            file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Un journal ne doit jamais faire tomber le site.
        }
    }
}
