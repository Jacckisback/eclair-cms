<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Alias à paramètres d'un ancien site (WordPress : /?p=123, /?page_id=45, /?attachment_id=67, aussi derrière /index.php),
 * résolus AVANT le cache et avant la canonicalisation : une seule redirection 301 vers l'adresse actuelle, jamais de chaîne,
 * et jamais la page d'accueil pour un alias inconnu (404). Les paramètres marketing ne créent aucune variante de cache
 * (index.php les ignore) ; tout autre paramètre fait passer la requête par l'application.
 *
 * La table (data/aliases.php) est remplie par l'import (préparation de la migration WordPress) ou par Aliases::add().
 * Elle est bornée (MAX) : au-delà, l'ajout est refusé avec un message, jamais tronqué en silence.
 */
final class Aliases
{
    /** Paramètres reconnus => nature de l'identifiant. */
    public const KINDS = ['p' => 'post', 'page_id' => 'page', 'attachment_id' => 'attachment'];
    /** Paramètres marketing : jamais une autre page, jamais une autre entrée du cache (liste reprise dans index.php). */
    public const MARKETING = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid', 'gbraid', 'wbraid', 'dclid',
        'fbclid', 'msclkid', 'twclid', 'ttclid', 'li_fat_id', 'igshid', 'yclid', 'mc_cid', 'mc_eid', '_ga', '_gl', 'ref'];
    public const MAX = 50000;

    /** Table : clé « p:123 » => chemin interne (« /blog/mon-article »). */
    public static function all(): array
    {
        $d = Store::read('aliases', []);
        return is_array($d) ? $d : [];
    }

    /**
     * Alias présent dans la requête ($query : paramètres déjà décodés), sous la forme « p:123 », ou '' s'il n'y en a pas.
     * Un identifiant non numérique n'est pas un alias (WordPress l'ignore aussi).
     */
    public static function key(array $query): string
    {
        foreach (self::KINDS as $param => $kind) {
            $v = $query[$param] ?? null;
            if (is_string($v) && $v !== '') {
                return preg_match('~^[1-9][0-9]{0,18}$~', $v) ? $param . ':' . $v : $param . ':?';
            }
        }
        return '';
    }

    /** Chemin interne d'un alias, ou null. */
    public static function resolve(string $key): ?string
    {
        $path = self::all()[$key] ?? null;
        return is_string($path) && self::internal($path) ? $path : null;
    }

    /** Ajoute (ou remplace) des alias : [« p:123 » => « /chemin »]. Renvoie le nombre enregistré. */
    public static function add(array $map): int
    {
        return (int) Store::locked(static function () use ($map): int {
            $all = self::all();
            $n = 0;
            foreach ($map as $key => $path) {
                if (!is_string($key) || !preg_match('~^(p|page_id|attachment_id):[1-9][0-9]{0,18}$~', $key) || !is_string($path) || !self::internal($path)) {
                    continue;
                }
                if (!isset($all[$key]) && count($all) >= self::MAX) {
                    throw new StorageException(t('Trop d’anciennes adresses à paramètres ({n} au plus) : rien n’a été ajouté au-delà.', ['n' => self::MAX]));
                }
                $all[$key] = $path;
                $n++;
            }
            Store::write('aliases', $all);
            return $n;
        });
    }

    /** Chemin interne sûr : commence par un seul « / », sans schéma, hôte, contrôle ni antislash (aucune redirection ouverte). */
    public static function internal(string $path): bool
    {
        return $path !== '' && strlen($path) <= 500 && $path[0] === '/' && !str_starts_with($path, '//') && !preg_match('~[\\\\\x00-\x20\x7f]~', $path);
    }

    /**
     * Requête de l'accueil (ou de /index.php) portant un alias : redirection 301 vers sa page, ou 404 s'il est inconnu.
     * Ne fait rien sans alias.
     */
    public static function handle(string $path): void
    {
        if ($path !== '/' && $path !== '/index.php') {
            return;
        }
        $key = self::key($_GET);
        if ($key === '') {
            return;
        }
        $target = str_ends_with($key, ':?') ? null : self::resolve($key);
        if ($target === null) {
            Site::notFound();
        }
        Http::redirect(Req::base() . $target, 301);
    }
}
