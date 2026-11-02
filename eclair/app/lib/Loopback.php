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
        $data = self::probe($url, $nonce);
        // En https, PHP doit aussi voir la connexion chiffrée : sinon forcer le HTTPS ferait tourner en boucle.
        return $data !== null && (!str_starts_with($url, 'https://') || ($data['s'] ?? false) === true);
    }

    /**
     * Cette adresse est-elle servie par cette installation, avec cette version d'Éclair ? (contrôle après une mise à jour,
     * sans dévoiler la version : elle n'apparaît que signée). Protocole à ne jamais changer : la version installée s'en sert
     * pour contrôler la suivante.
     */
    public static function runs(string $url, string $version): bool
    {
        $data = self::probe($url, $nonce);
        return $data !== null && hash_equals(self::proof($nonce, $version), (string) ($data['v'] ?? ''));
    }

    /** Réponse de /__eclair/ping?n=… */
    public static function answer(string $nonce): array
    {
        return ['p' => self::proof($nonce), 's' => Req::isHttps(), 'v' => self::proof($nonce, ECLAIR_VERSION)];
    }

    /** Réponse signée de l'adresse (preuve d'identité vérifiée), ou null. */
    private static function probe(string $url, ?string &$nonce): ?array
    {
        $nonce = bin2hex(random_bytes(16));
        if (!preg_match('~^https?://[^/?#]+~', $url)) {
            return null;
        }
        $body = self::fetch(rtrim($url, '/') . '/__eclair/ping?n=' . $nonce);
        $data = $body !== null ? json_decode($body, true) : null;
        return is_array($data) && hash_equals(self::proof($nonce), (string) ($data['p'] ?? '')) ? $data : null;
    }

    private static function proof(string $nonce, string $version = ''): string
    {
        return substr(Keys::sign($version === '' ? 'ping|' . $nonce : 'version|' . $version . '|' . $nonce), 0, 32);
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
