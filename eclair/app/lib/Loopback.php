<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Le serveur s'appelle lui-même pour prouver qu'une adresse mène bien à cette installation :
 * il demande /__eclair/ping avec un nombre aléatoire, et seule cette installation sait le signer.
 */
final class Loopback
{
    public static function check(string $url): bool
    {
        if (!preg_match('~^https?://[^/?#]+~', $url)) {
            return false;
        }
        $nonce = bin2hex(random_bytes(16));
        $body = self::fetch(rtrim($url, '/') . '/__eclair/ping?n=' . $nonce);
        $data = $body !== null ? json_decode($body, true) : null;
        return is_array($data)
            && hash_equals(self::proof($nonce), (string) ($data['p'] ?? ''))
            // En https, PHP doit aussi voir la connexion chiffrée : sinon forcer le HTTPS ferait tourner en boucle.
            && (!str_starts_with($url, 'https://') || ($data['s'] ?? false) === true);
    }

    /** Réponse de /__eclair/ping?n=… */
    public static function answer(string $nonce): array
    {
        return ['p' => self::proof($nonce), 's' => Req::isHttps()];
    }

    private static function proof(string $nonce): string
    {
        return substr(Keys::sign('ping|' . $nonce), 0, 32);
    }

    private static function fetch(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'Eclair/' . ECLAIR_VERSION . ' (verification)',
            ]);
            $body = curl_exec($ch);
            return is_string($body) ? $body : null;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout' => 4,
                'follow_location' => 0,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            return is_string($body) ? $body : null;
        }
        return null;
    }
}
