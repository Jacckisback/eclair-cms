<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Limitation des tentatives (connexion, double authentification, formulaire de contact). */
final class RateLimit
{
    /** Clés bloquées listées sur la page Sécurité : au-delà de 100, la liste n'aiderait plus personne. */
    private const SHOWN = 100;
    /** Compteur inactif depuis plus d'un jour : supprimé (aucune fenêtre ne dure plus de 24 h). */
    private const IDLE = 86400;

    private static function name(string $key): string
    {
        return 'limits/' . substr(hash('sha256', $key), 0, 40);
    }

    /** Secondes de blocage restantes (0 = libre). */
    public static function blocked(string $key): int
    {
        return max(0, (int) (self::peek(Store::file(self::name($key)))['b'] ?? 0) - time());
    }

    /**
     * Réserve une tentative AVANT de la vérifier : des requêtes simultanées ne peuvent pas dépasser
     * la limite. Au-delà de $max tentatives dans la fenêtre, la clé est bloquée $block secondes, et au moins
     * jusqu'à la fin de cette fenêtre : jamais plus de $max tentatives acceptées par fenêtre.
     * Renvoie false si la tentative est refusée ; $fresh indique que ce refus vient de poser le blocage.
     */
    public static function attempt(string $key, int $max, int $window, int $block = 900, ?bool &$fresh = null): bool
    {
        if (random_int(1, 200) === 1) {
            self::gc();
        }
        $fresh = false;
        // Clé déjà bloquée : refus sans prendre le verrou des données (une adresse bloquée ne l'occupe pas).
        if (self::blocked($key) > 0) {
            return false;
        }
        return (bool) Store::locked(function () use ($key, $max, $window, $block, &$fresh): bool {
            $now = time();
            $row = Store::read(self::name($key));
            if (is_array($row) && (int) ($row['b'] ?? 0) > $now) {
                return false;
            }
            if (!is_array($row) || $now - (int) ($row['t'] ?? 0) > $window) {
                $row = ['k' => $key, 'n' => 0, 't' => $now, 'b' => 0];
            }
            $row['n'] = (int) ($row['n'] ?? 0);
            if ($row['n'] >= $max) {
                // La fenêtre épuisée est gardée : le quota ne se recharge pas avant sa fin, même après un blocage plus court.
                $row['b'] = max($now + $block, (int) ($row['t'] ?? $now) + $window + 1);
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
            $name = self::name($key);
            $row = Store::read($name);
            if (!is_array($row) || (int) ($row['n'] ?? 0) <= 0) {
                return;
            }
            $row['n'] = (int) $row['n'] - 1;
            // Plus rien à retenir (ni tentative, ni blocage) : aucun fichier ne reste après une connexion réussie ou refusée faute de place.
            if ($row['n'] === 0 && (int) ($row['b'] ?? 0) <= time()) {
                Store::delete($name);
                return;
            }
            Store::write($name, $row);
        });
    }

    public static function clear(string $key): void
    {
        Store::delete(self::name($key));
    }

    /**
     * Clés actuellement bloquées (pour la page Sécurité). Le dossier est lu un fichier à la fois, sans garder les
     * compteurs en mémoire : la page reste légère même avec des centaines de milliers de compteurs.
     */
    public static function active(): array
    {
        $out = [];
        $now = time();
        foreach (self::files() as $file) {
            if (!preg_match('~/[a-f0-9]{40}\.php$~', $file)) {
                continue;
            }
            $row = self::peek($file);
            if ((int) ($row['b'] ?? 0) > $now) {
                $out[] = ['key' => (string) ($row['k'] ?? ''), 'until' => (int) $row['b']];
                if (count($out) >= self::SHOWN) {
                    break;
                }
            }
        }
        self::gc();
        return $out;
    }

    /** Supprime les compteurs inactifs depuis plus d'un jour (dossier lu au fil de l'eau : mémoire constante). */
    private static function gc(): void
    {
        $cut = time() - self::IDLE;
        foreach (self::files() as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cut) {
                @unlink($file);
            }
        }
    }

    /** @return \Generator<int, string> fichiers .php de data/limits, un à la fois (jamais la liste entière en mémoire) */
    private static function files(): \Generator
    {
        $dir = @opendir(ECLAIR_DATA . '/limits');
        if ($dir === false) {
            return;
        }
        try {
            while (($entry = readdir($dir)) !== false) {
                if (str_ends_with($entry, '.php')) {
                    yield ECLAIR_DATA . '/limits/' . $entry;
                }
            }
        } finally {
            closedir($dir);
        }
    }

    /** Compteur lu directement, sans passer par la mémoire de Store (lecture de contrôle, page Sécurité). */
    private static function peek(string $file): array
    {
        $raw = @file_get_contents($file, false, null, 0, 4096);
        $row = $raw === false ? null : json_decode(Store::strip($raw), true);
        return is_array($row) ? $row : [];
    }
}
