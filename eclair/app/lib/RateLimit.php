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
     * Réserve une tentative AVANT de la vérifier : des requêtes simultanées ne peuvent pas dépasser
     * la limite. Au-delà de $max tentatives dans la fenêtre, la clé est bloquée $block secondes.
     * Renvoie false si la tentative est refusée ; $fresh indique que ce refus vient de poser le blocage.
     */
    public static function attempt(string $key, int $max, int $window, int $block = 900, ?bool &$fresh = null): bool
    {
        if (random_int(1, 200) === 1) {
            self::gc();
        }
        $fresh = false;
        return (bool) Store::locked(function () use ($key, $max, $window, $block, &$fresh): bool {
            $now = time();
            $row = Store::read(self::name($key));
            if (is_array($row) && (int) ($row['b'] ?? 0) > $now) {
                return false;
            }
            if (!is_array($row) || $now - (int) ($row['t'] ?? 0) > $window) {
                $row = ['k' => $key, 'n' => 0, 't' => $now, 'b' => 0];
            }
            if ($row['n'] >= $max) {
                $row = ['k' => $key, 'n' => 0, 't' => $now, 'b' => $now + $block];
                Store::write(self::name($key), $row);
                $fresh = true;
                return false;
            }
            $row['n']++;
            Store::write(self::name($key), $row);
            return true;
        });
    }

    /** Rend une tentative réservée qui a réussi (elle ne compte pas comme un échec). */
    public static function release(string $key): void
    {
        Store::locked(function () use ($key): void {
            $row = Store::read(self::name($key));
            if (is_array($row) && (int) ($row['n'] ?? 0) > 0) {
                $row['n']--;
                Store::write(self::name($key), $row);
            }
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
            if (is_array($row) && (int) ($row['b'] ?? 0) > time()) {
                $out[] = ['key' => (string) $row['k'], 'until' => (int) $row['b']];
            }
        }
        self::gc();
        return $out;
    }

    /** Supprime les compteurs inactifs depuis plus d'un jour. */
    private static function gc(): void
    {
        foreach (glob(ECLAIR_DATA . '/limits/*.php') ?: [] as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }
}
