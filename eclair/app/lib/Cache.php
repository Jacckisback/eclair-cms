<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Cache de pages statiques.
 *
 * Chaque page publique rendue est enregistrée dans cache/pages/<md5 du chemin> :
 * une première ligne JSON (en-têtes + ETag), puis le contenu. index.php la sert
 * ensuite sans charger l'application.
 */
final class Cache
{
    public static function dir(): string
    {
        return ECLAIR_ROOT . '/cache/pages';
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('cache', true) && !ECLAIR_DEBUG;
    }

    public static function put(string $path, string $body, array $headers): void
    {
        if (!self::enabled() || $body === '') {
            return;
        }
        try {
            Store::ensureDir(self::dir());
            $meta = ['h' => array_values($headers), 'e' => substr(md5($body), 0, 16)];
            Store::atomic(self::dir() . '/' . md5($path), json_encode($meta, JSON_UNESCAPED_SLASHES) . "\n" . $body);
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /** Vide le cache et régénère les paramètres d'exécution du chemin rapide. */
    public static function purge(): void
    {
        foreach (glob(self::dir() . '/*') ?: [] as $file) {
            if (preg_match('~/[a-f0-9]{32}(\.[a-f0-9]{8}\.tmp(\.php)?)?$~', $file)) {
                @unlink($file);
            }
        }
        // Sauvegardes ZIP interrompues (le téléchargement les supprime normalement).
        foreach (glob(ECLAIR_ROOT . '/cache/backup-*.zip') ?: [] as $file) {
            if (filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }
        self::runtime();
    }

    /**
     * Fichier lu par index.php à chaque requête : il dit si le cache est actif,
     * quand il expire (prochain article programmé ou feuille de style du site modifiée)
     * et quel hôte/protocole exiger.
     */
    public static function runtime(): void
    {
        if (!Settings::installed()) {
            return;
        }
        $host = '';
        $url = (string) Settings::get('url');
        if (Settings::get('security.canonical') && Settings::verified()) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $port = parse_url($url, PHP_URL_PORT);
            if ($port) {
                $host .= ':' . $port;
            }
        }
        $data = [
            'on' => self::enabled(),
            'expires' => Content::nextScheduled(),
            'host' => $host,
            'https' => Security::httpsOnly(),
            'css' => [Theme::customFile(), Theme::customStamp()],
        ];
        try {
            Store::ensureDir(ECLAIR_ROOT . '/cache');
            Store::atomic(ECLAIR_ROOT . '/cache/runtime.php', Store::GUARD . json_encode($data));
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /** Si un article programmé doit paraître, ou si la feuille de style du site a changé, on vide le cache avant de servir. */
    public static function checkExpiry(): void
    {
        $raw = @file_get_contents(ECLAIR_ROOT . '/cache/runtime.php');
        $rt = $raw === false ? null : json_decode(Store::strip($raw), true);
        if (!is_array($rt) || (!empty($rt['expires']) && time() >= (int) $rt['expires']) || ($rt['css'][1] ?? '') !== Theme::customStamp()) {
            self::purge();
        }
    }

    public static function count(): int
    {
        return count(glob(self::dir() . '/' . str_repeat('[a-f0-9]', 32)) ?: []);
    }
}
