<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Cache de pages statiques.
 *
 * Chaque page publique rendue est enregistrée dans cache/pages/<md5 du chemin> :
 * une première ligne JSON (en-têtes + ETag + génération), puis le contenu. index.php la sert
 * ensuite sans charger l'application, tant que sa génération est celle de cache/runtime.php.
 */
final class Cache
{
    /** Génération du cache lue au début de la requête (App::run), avant toute lecture de réglages ou de contenus. */
    private static ?string $generation = null;

    public static function dir(): string
    {
        return ECLAIR_ROOT . '/cache/pages';
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('cache', true) && !ECLAIR_DEBUG;
    }

    /**
     * Génération du cache au début de la requête, lue une seule fois. Chaque purge en tire une nouvelle avant de vider le
     * cache : une page rendue à partir de données lues avant la purge (un contenu dépublié pendant son rendu) garde l'ancienne,
     * et index.php ne la sert jamais. Elle n'est jamais relue après une purge faite par la requête elle-même.
     */
    public static function generation(): string
    {
        if (self::$generation === null) {
            $raw = @file_get_contents(ECLAIR_ROOT . '/cache/runtime.php');
            $rt = $raw === false ? null : json_decode(Store::strip($raw), true);
            self::$generation = is_array($rt) && is_string($rt['g'] ?? null) ? $rt['g'] : '';
        }
        return self::$generation;
    }

    public static function put(string $path, string $body, array $headers): void
    {
        // Sans génération connue au début de la requête, la page ne serait jamais servie : inutile de l'écrire.
        if (!self::enabled() || $body === '' || self::generation() === '') {
            return;
        }
        try {
            Store::ensureDir(self::dir());
            $meta = ['h' => array_values($headers), 'e' => substr(md5($body), 0, 16), 'g' => self::generation()];
            Store::atomic(self::dir() . '/' . md5($path), json_encode($meta, JSON_UNESCAPED_SLASHES) . "\n" . $body);
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /**
     * Vide le cache et régénère les paramètres d'exécution du chemin rapide. Toujours appelée après l'écriture des données :
     * la nouvelle génération est publiée d'abord, si bien qu'une page rendue avant (même écrite après ce vidage) n'est plus servie.
     */
    public static function purge(): void
    {
        self::runtime();
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
    }

    /**
     * Fichier lu par index.php à chaque requête : il dit si le cache est actif, quand il expire (prochain article
     * programmé), quel hôte/protocole exiger, et la génération des pages qu'il peut servir (nouvelle à chaque appel).
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
            'v' => ECLAIR_VERSION,
            'g' => bin2hex(random_bytes(8)),
            'on' => self::enabled(),
            'expires' => Content::nextScheduled(),
            'host' => $host,
            'https' => Security::httpsOnly(),
        ];
        try {
            Store::ensureDir(ECLAIR_ROOT . '/cache');
            Store::atomic(ECLAIR_ROOT . '/cache/runtime.php', Store::GUARD . json_encode($data));
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /**
     * Article programmé qui doit paraître, ou Éclair mis à jour (pages rendues par l'ancien code) : cache vidé avant de servir.
     * La requête garde la génération lue à son début : la page qu'elle rend n'est pas servie, la suivante la régénère.
     */
    public static function checkExpiry(): void
    {
        $raw = @file_get_contents(ECLAIR_ROOT . '/cache/runtime.php');
        $rt = $raw === false ? null : json_decode(Store::strip($raw), true);
        if (!is_array($rt) || ($rt['v'] ?? '') !== ECLAIR_VERSION || (!empty($rt['expires']) && time() >= (int) $rt['expires'])) {
            self::purge();
        }
    }

    public static function count(): int
    {
        return count(glob(self::dir() . '/' . str_repeat('[a-f0-9]', 32)) ?: []);
    }
}
