<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Sessions stockées dans data/sessions, sous un nom haché :
 * même en listant le dossier, impossible de retrouver un identifiant de session valide.
 * Les sessions ne sont démarrées que pour l'administration (le site public reste sans cookie).
 *
 * Concurrence : contrairement au gestionnaire de fichiers de PHP, une requête n'en bloque pas une autre de la même
 * session pendant toute sa durée (une longue sauvegarde ne fige pas les autres onglets). Écriture, mise à jour et
 * destruction passent en revanche sous le verrou des données : une session fermée (déconnexion, nouvel identifiant)
 * n'est jamais recréée par une requête plus lente qui l'avait lue avant. La dernière écriture l'emporte.
 */
final class Session implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public const NAME = 'eclair_sid';

    /** @var array<string, bool> Sessions dont le fichier existait quand cette requête les a lues. */
    private array $seen = [];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = 86400 * 2;
        ini_set('session.use_strict_mode', '1');
        if (PHP_VERSION_ID < 80400) {
            // Valeurs imposées depuis PHP 8.4 (et dépréciées) : identifiant uniquement par cookie.
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
        }
        ini_set('session.cache_limiter', '');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
        session_name(self::NAME);
        session_set_save_handler(new self(), true);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Req::base() . '/',
            'secure' => Req::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        // Session enregistrée avant le premier octet de la réponse (et non après) : la requête suivante, souvent
        // immédiate (clic, autre onglet), lit toujours la session à jour et ne réécrit pas une copie périmée (jeton, message).
        header_register_callback(static function (): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
        });
    }

    /** Reprend une session existante, sans jamais en créer pour un visiteur anonyme. */
    public static function resume(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (empty($_COOKIE[self::NAME]) || !is_string($_COOKIE[self::NAME])) {
            return false;
        }
        self::start();
        return true;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function end(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        setcookie(self::NAME, '', [
            'expires' => time() - 3600,
            'path' => Req::base() . '/',
            'secure' => Req::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Ferme les sessions d'un compte, sauf celle de cette requête : leurs fichiers sont effacés (Auth::revokeAccess). Le
     * compte se reconnaît à son identifiant tel que PHP l'enregistre, dans $_SESSION['uid'] ou dans une connexion qui attend
     * le code de double authentification, quel que soit le format des sessions du serveur (php, php_serialize, php_binary).
     */
    public static function revokeUser(string $uid): void
    {
        $current = session_status() === PHP_SESSION_ACTIVE ? hash('sha256', (string) session_id()) . '.php' : '';
        $value = 's:' . strlen($uid) . ':"' . $uid . '";';
        $marks = ['uid|' . $value, '"uid";' . $value, "\x03uid" . $value];
        Store::locked(static function () use ($current, $marks): void {
            foreach (glob(ECLAIR_DATA . '/sessions/*.php') ?: [] as $file) {
                $raw = basename($file) === $current ? '' : (string) @file_get_contents($file);
                foreach ($marks as $mark) {
                    if ($raw !== '' && str_contains($raw, $mark)) {
                        @unlink($file);
                        break;
                    }
                }
            }
        });
    }

    /** Message affiché une seule fois après une redirection. */
    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][] = [$type, $message];
    }

    public static function pullFlash(): array
    {
        if (!self::resume()) {
            return [];
        }
        $out = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($out) ? $out : [];
    }

    private function path(string $id): string
    {
        return ECLAIR_DATA . '/sessions/' . hash('sha256', $id) . '.php';
    }

    public function open(string $path, string $name): bool
    {
        Store::ensureDir(ECLAIR_DATA . '/sessions');
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $raw = @file_get_contents($this->path($id));
        $this->seen[$id] = $raw !== false;
        return $raw === false ? '' : Store::strip($raw);
    }

    /**
     * Une session qui existait à la lecture et a disparu depuis (fermée par une requête concurrente) n'est jamais
     * recréée : le contrôle et l'écriture se font sous le même verrou que destroy().
     */
    public function write(string $id, string $data): bool
    {
        $file = $this->path($id);
        try {
            return (bool) Store::locked(function () use ($id, $data, $file): bool {
                clearstatcache(true, $file);
                if (!is_file($file) && ($data === '' || !empty($this->seen[$id]))) {
                    return true;
                }
                Store::atomic($file, Store::GUARD . $data);
                return true;
            });
        } catch (\Throwable $e) {
            Log::error($e); // verrou des données ou dossier inutilisable : la cause reste dans le journal des erreurs
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        $file = $this->path($id);
        $end = static function () use ($file): bool {
            clearstatcache(true, $file);
            return !is_file($file) || @unlink($file);
        };
        try {
            return (bool) Store::locked($end);
        } catch (\Throwable) {
            return $end(); // verrou des données inutilisable : la session est fermée quand même
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        $count = 0;
        foreach (glob(ECLAIR_DATA . '/sessions/*.php') ?: [] as $file) {
            if (filemtime($file) + $max_lifetime < time() && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public function validateId(string $id): bool
    {
        return is_file($this->path($id));
    }

    /**
     * Ne crée jamais de fichier : une session vide (identifiant inconnu, visiteur anonyme) ne laisse aucune trace,
     * et une session détruite au même moment n'est pas recréée vide (même verrou que destroy()).
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        $file = $this->path($id);
        try {
            return (bool) Store::locked(static function () use ($file): bool {
                clearstatcache(true, $file);
                return !is_file($file) || @touch($file);
            });
        } catch (\Throwable) {
            return false;
        }
    }
}
