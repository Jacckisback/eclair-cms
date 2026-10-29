<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Coffre des clés d'API (assistant IA). Chaque clé est chiffrée (libsodium, ou AES-256-GCM à défaut)
 * avec une clé dérivée du secret du site et, s'il est défini dans eclair-config.php, de ECLAIR_SECRET.
 * Une clé n'est jamais renvoyée au navigateur (seul un indice « sk-…a1b2 » l'est) ni incluse dans les sauvegardes.
 */
final class Vault
{
    public static function get(string $name): string
    {
        $all = Store::read('vault', []);
        $sealed = is_array($all) ? (string) ($all[$name]['v'] ?? '') : '';
        return $sealed !== '' ? (self::open($sealed) ?? '') : '';
    }

    public static function has(string $name): bool
    {
        return self::get($name) !== '';
    }

    /** Indice affichable : début et fin de la clé, jamais la clé entière. */
    public static function hint(string $name): string
    {
        $all = Store::read('vault', []);
        return is_array($all) && self::has($name) ? (string) ($all[$name]['h'] ?? '') : '';
    }

    /** Enregistre une clé ; une valeur vide la supprime. */
    public static function set(string $name, string $value): void
    {
        $value = trim($value);
        if ($value !== '' && !self::available()) {
            throw new \RuntimeException(t('Chiffrement indisponible sur ce serveur (extensions sodium et openssl absentes) : impossible d’enregistrer la clé.'));
        }
        Store::locked(function () use ($name, $value): void {
            $all = Store::read('vault', []);
            $all = is_array($all) ? $all : [];
            if ($value === '') {
                unset($all[$name]);
            } else {
                $all[$name] = [
                    'v' => self::seal($value),
                    'h' => mb_strlen($value) > 12 ? mb_substr($value, 0, 6) . '…' . mb_substr($value, -4) : '…',
                    't' => time(),
                ];
            }
            Store::write('vault', $all);
        });
    }

    public static function available(): bool
    {
        return function_exists('sodium_crypto_secretbox')
            || (function_exists('openssl_get_cipher_methods') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true));
    }

    private static function key(): string
    {
        $extra = defined('ECLAIR_SECRET') ? (string) constant('ECLAIR_SECRET') : '';
        return hash_hmac('sha256', 'eclair-vault-v1', Keys::get('secret') . $extra, true);
    }

    private static function seal(string $plain): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
        }
        $iv = random_bytes(12);
        $cipher = (string) openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'o' . base64_encode($iv . $tag . $cipher);
    }

    private static function open(string $sealed): ?string
    {
        $raw = base64_decode(substr($sealed, 1), true);
        if ($raw === false) {
            return null;
        }
        if ($sealed[0] === 's' && function_exists('sodium_crypto_secretbox_open')) {
            $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $plain = strlen($raw) > $n ? sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), self::key()) : false;
            return is_string($plain) ? $plain : null;
        }
        if ($sealed[0] === 'o' && strlen($raw) > 28) {
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return is_string($plain) ? $plain : null;
        }
        return null;
    }
}
