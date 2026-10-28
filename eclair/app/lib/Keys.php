<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Clés secrètes générées à l'installation (jamais affichées, jamais exportées dans les réglages). */
final class Keys
{
    public static function get(string $name): string
    {
        $keys = Store::read('keys', []);
        if (!is_array($keys) || empty($keys[$name])) {
            $keys = self::generate(is_array($keys) ? $keys : []);
        }
        return (string) $keys[$name];
    }

    public static function generate(array $keys = []): array
    {
        $keys += [
            'secret' => bin2hex(random_bytes(32)),
            'indexnow' => bin2hex(random_bytes(16)),
            'dummy' => Security::hashPassword(bin2hex(random_bytes(12))),
        ];
        Store::write('keys', $keys);
        return $keys;
    }

    /** Signature HMAC avec la clé du site. */
    public static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::get('secret'));
    }
}
