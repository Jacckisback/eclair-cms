<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Journaux (erreurs et événements de sécurité), au format JSON ligne par ligne.
 * Taille bornée : chaque champ est tronqué ; un journal plein devient le segment précédent (<nom>-1), qui remplace
 * l'ancien. Les échecs de connexion anonymes ont leur propre petit journal : ils ne chassent jamais les vrais événements.
 */
final class Log
{
    /** Au-delà de 1 Mo, le journal passe en segment précédent : au plus 2 Mo par journal sur le disque. */
    private const MAX_BYTES = 1048576;
    /** Échecs de connexion anonymes : un attaquant les choisit, ils restent donc dans un fichier à part, petit. */
    private const FAILURES_MAX = 262144;
    /** Lecture : seule la fin de chaque segment est lue (la page Sécurité n'affiche que les dernières lignes). */
    private const KEEP_BYTES = 262144;
    /** E-mail saisi et navigateur : une adresse ou un navigateur réels tiennent largement dans 100 caractères. */
    private const FIELD_MAX = 100;
    /** Même problème signalé au plus une fois par heure (verrou inutilisable, à chaque requête sinon). */
    private const ALERT_EVERY = 3600;
    /** Journal des échecs de connexion anonymes (affichés aussi sur la page Sécurité). */
    public const FAILURES = 'login-failures';
    private const ANONYMOUS = ['login_failed', 'login_locked'];

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

    /** Signale un problème qui se répéterait à chaque requête : une ligne par heure au plus dans le journal des erreurs. */
    public static function alert(string $key, string $message): void
    {
        try {
            Store::ensureDir(ECLAIR_DATA . '/logs');
            $mark = ECLAIR_DATA . '/logs/' . preg_replace('/[^a-z0-9-]/', '', $key) . '.alert';
            clearstatcache(true, $mark);
            $last = @filemtime($mark);
            if ($last !== false && time() - $last < self::ALERT_EVERY) {
                return;
            }
            @touch($mark);
        } catch (\Throwable) {
            // dossier des journaux inaccessible : la ligne part dans le journal du serveur (append)
        }
        self::append('errors', ['id' => $key, 'msg' => $message, 'at' => '', 'path' => Req::path()]);
    }

    public static function security(string $event, array $context = []): void
    {
        $user = Auth::user();
        $email = mb_substr((string) ($user['email'] ?? ($context['email'] ?? '')), 0, self::FIELD_MAX);
        if (isset($context['email']) && mb_substr((string) $context['email'], 0, self::FIELD_MAX) === $email) {
            unset($context['email']); // déjà dans « user » : jamais deux fois
        }
        $anonymous = $user === null && in_array($event, self::ANONYMOUS, true);
        self::append($anonymous ? self::FAILURES : 'security', [
            'event' => $event,
            'ip' => Req::ip(),
            'user' => $email,
            'ua' => mb_substr(Req::userAgent(), 0, self::FIELD_MAX),
        ] + $context);
    }

    /** Dernières entrées, de la plus récente à la plus ancienne (seule la fin de chaque segment est lue). */
    public static function read(string $name, int $limit = 200): array
    {
        $out = [];
        foreach ([$name, $name . '-1'] as $segment) {
            foreach (array_reverse(self::tail(self::file($segment))) as $line) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $out[] = $row;
                    if (count($out) >= $limit) {
                        return $out;
                    }
                }
            }
        }
        return $out;
    }

    /** @return list<string> lignes de la fin d'un journal (au plus KEEP_BYTES lus) */
    private static function tail(string $file): array
    {
        $h = @fopen($file, 'rb');
        if ($h === false) {
            return [];
        }
        $size = (int) fstat($h)['size'];
        $start = max(0, $size - self::KEEP_BYTES);
        fseek($h, $start);
        $raw = (string) stream_get_contents($h);
        fclose($h);
        $raw = $start === 0 ? Store::strip($raw) : substr($raw, (int) strpos($raw, "\n") + 1);
        return explode("\n", trim($raw));
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
            $h = @fopen($file, 'a+b');
            if ($h === false) {
                error_log('[eclair] ' . $line);
                return;
            }
            try {
                // Verrou du journal lui-même : vérification de l'en-tête, rotation et ajout sans perdre la ligne d'un autre.
                flock($h, LOCK_EX);
                fseek($h, 0);
                $head = (string) fread($h, strlen(Store::GUARD));
                $full = (int) fstat($h)['size'] > ($name === self::FAILURES ? self::FAILURES_MAX : self::MAX_BYTES);
                if ($head !== Store::GUARD || $full) {
                    if ($head === Store::GUARD) {
                        // Journal plein : il devient le segment précédent (rien n'est jeté d'un coup), puis repart à zéro.
                        fseek($h, 0);
                        Store::atomic(self::file($name . '-1'), (string) stream_get_contents($h));
                    }
                    // Fichier nouveau, plein, ou sans ligne de protection : on repart d'un journal propre (0600).
                    ftruncate($h, 0);
                    fwrite($h, Store::GUARD);
                    @chmod($file, 0600);
                }
                fwrite($h, $line);
            } finally {
                flock($h, LOCK_UN);
                fclose($h);
            }
        } catch (\Throwable) {
            // Un journal ne doit jamais faire tomber le site.
        }
    }
}
