<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Limitation des tentatives (connexion, double authentification, formulaire de contact). */
final class RateLimit
{
    private static function name(string $key): string
    {
        return 'limits/' . substr(hash('sha256', $key), 0, 40);
    }

    /** Secondes de blocage restantes (0 = libre). */
    public static function blocked(string $key): int
    {
        $row = Store::read(self::name($key));
        return is_array($row) ? max(0, (int) ($row['b'] ?? 0) - time()) : 0;
    }

    /**
     * Compte une tentative. Au-delà de $max dans la fenêtre, la clé est bloquée
     * pendant $block secondes. Renvoie true si la clé est désormais bloquée.
     */
    public static function hit(string $key, int $max, int $window, int $block = 900): bool
    {
        return (bool) Store::locked(function () use ($key, $max, $window, $block): bool {
            $now = time();
            $row = Store::read(self::name($key));
            if (!is_array($row) || $now - (int) ($row['t'] ?? 0) > $window) {
                $row = ['k' => $key, 'n' => 0, 't' => $now, 'b' => 0];
            }
            $row['n']++;
            if ($row['n'] >= $max) {
                $row['b'] = $now + $block;
                $row['n'] = 0;
                $row['t'] = $now;
            }
            Store::write(self::name($key), $row);
            return $row['b'] > $now;
        });
    }

    public static function clear(string $key): void
    {
        Store::delete(self::name($key));
    }

    /** Clés actuellement bloquées (pour la page Sécurité). */
    public static function active(): array
    {
        $out = [];
        foreach (Store::names('limits') as $name) {
            $row = Store::read($name);
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['b'] ?? 0) > time()) {
                $out[] = ['key' => (string) $row['k'], 'until' => (int) $row['b']];
            } elseif (time() - (int) ($row['t'] ?? 0) > 86400) {
                Store::delete($name);
            }
        }
        return $out;
    }
}
