<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Redirections 301/302, créées automatiquement quand une URL change (aucun lien cassé).
 *
 * La liste (data/redirects.php) reste la seule source, celle de l'administration. Les visiteurs passent par un index
 * qui en est tiré, une petite fiche par adresse (data/redirects-index/), et chaque utilisation est comptée dans un
 * fichier propre à la redirection (data/redirect-hits/), sous son propre verrou : une adresse inconnue ou redirigée
 * ne relit, ne réécrit ni ne verrouille jamais toute la liste. Si la liste change sans passer par ici (mise à jour,
 * sauvegarde restaurée, modification à la main), son empreinte ne correspond plus et l'index est reconstruit.
 */
final class Redirects
{
    /** Au plus 5 000 redirections : bien au-delà des besoins d'un vrai site, et chaque modification de la liste reste rapide. */
    public const MAX = 5000;
    /** Longueur maximale d'une ancienne adresse, ou d'une nouvelle adresse sur le site : les vraies font moins de 200 caractères. */
    public const MAX_LENGTH = 1000;
    private const INDEX = 'redirects-index';
    private const HITS = 'redirect-hits';

    public static function all(): array
    {
        $list = Store::read('redirects', []);
        return is_array($list) ? $list : [];
    }

    public static function normalize(string $path): string
    {
        $path = trim($path);
        if (preg_match('~^https?://~i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }
        $path = '/' . ltrim(rawurldecode($path), '/');
        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    /**
     * Adresses que seuls l'hébergement et Éclair servent, jamais une redirection enregistrée : /.well-known/*
     * (RFC 8615 : validations de domaine ACME et pki-validation, découverte OAuth), /_eclair/* et /__eclair/*.
     * Les anciennes adresses d'autres outils (« /_files/… ») restent redirigeables.
     */
    public static function reserved(string $path): bool
    {
        $first = strtolower(explode('/', ltrim($path, '/'), 2)[0]);
        return str_starts_with($first, '.') || in_array($first, ['_eclair', '__eclair'], true);
    }

    /**
     * Une redirection peut-elle partir de cette adresse ? Hors des adresses réservées, oui. Un administrateur
     * (droit « settings ») peut seul rediriger une adresse /.well-known/ (ex. change-password), jamais une
     * validation de domaine (acme-challenge, pki-validation) ni une adresse d'Éclair.
     */
    public static function allowed(string $from, bool $admin): bool
    {
        return !self::reserved($from) || ($admin && self::wellKnown($from));
    }

    /** /.well-known/… hors validation de domaine : la seule adresse réservée qu'un administrateur peut rediriger. */
    private static function wellKnown(string $path): bool
    {
        $parts = explode('/', strtolower(ltrim($path, '/')), 3);
        return $parts[0] === '.well-known' && !in_array($parts[1] ?? '', ['acme-challenge', 'pki-validation'], true);
    }

    /** Recherche dans toute la liste (administration). Les visiteurs passent par lookup(). */
    public static function find(string $path): ?array
    {
        foreach (self::all() as $r) {
            if ($r['from'] === $path) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Redirection à appliquer à une adresse publique : une seule petite fiche lue, sans verrou ni lecture de la liste.
     * Une adresse réservée n'est jamais redirigée, sauf /.well-known/… enregistrée par un administrateur.
     */
    public static function lookup(string $path): ?array
    {
        if (self::reserved($path) && !self::wellKnown($path)) {
            return null;
        }
        $card = self::entry($path);
        return $card === null || (self::reserved($path) && empty($card['admin'])) ? null : $card;
    }

    public static function full(): bool
    {
        return count(self::all()) >= self::MAX;
    }

    /** Liste de l'administration : utilisations comptées ajoutées, redirection inopérante (adresse réservée) signalée. */
    public static function listing(): array
    {
        $out = [];
        foreach (self::all() as $r) {
            $count = Store::read(self::HITS . '/' . self::key((string) $r['from']));
            $r['hits'] = (int) ($r['hits'] ?? 0) + (is_array($count) ? (int) ($count['n'] ?? 0) : 0);
            $r['active'] = !self::reserved($r['from']) || (self::wellKnown($r['from']) && !empty($r['admin']));
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Ajoute une redirection (ou remplace celle qui part de la même adresse). Renvoie false si rien n'est enregistré :
     * adresse réservée, trop longue, vide ou identique à la cible, ou liste pleine. Une redirection automatique ne fait
     * jamais échouer un enregistrement : elle prend la place des plus anciennes redirections automatiques, ou elle est omise.
     */
    public static function add(string $from, string $to, bool $auto, int $code = 301, bool $admin = false): bool
    {
        return self::addMany([[$from, $to]], $auto, $code, $admin) === 1;
    }

    /** Plusieurs redirections en une seule écriture (adresse du blog ou de la boutique changée) ; renvoie le nombre enregistré. */
    public static function addMany(array $pairs, bool $auto, int $code = 301, bool $admin = false): int
    {
        $rows = [];
        foreach ($pairs as $pair) {
            $row = is_array($pair) ? self::row((string) ($pair[0] ?? ''), (string) ($pair[1] ?? ''), $auto, $code, $admin) : null;
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        if (!$rows) {
            return 0;
        }
        return (int) Store::locked(function () use ($rows, $auto): int {
            $list = self::all();
            $touched = [];
            $reset = [];
            $added = 0;
            foreach ($rows as $row) {
                $next = [];
                $gone = [];
                $moved = [];
                foreach ($list as $r) {
                    if ($r['from'] === $row['from']) {
                        $gone[] = $r['from'];
                        continue;
                    }
                    // Évite les chaînes : A→B puis B→C devient A→C.
                    if ($r['to'] === $row['from']) {
                        $r['to'] = $row['to'];
                        $moved[] = $r['from'];
                    }
                    if ($r['from'] !== $r['to']) {
                        $next[] = $r;
                    } else {
                        $gone[] = $r['from'];
                    }
                }
                // Liste pleine (ou au-delà : liste d'avant la 1.10.3, jamais coupée en masse). Remplacer une redirection
                // existante (même ancienne adresse) est toujours permis ; une nouvelle redirection ajoutée à la main est
                // refusée (l'écran le dit avant) ; une nouvelle redirection automatique prend la place de la plus ancienne
                // redirection automatique, une pour une, sinon elle est omise.
                if (count($next) >= self::MAX && !in_array($row['from'], $gone, true)) {
                    if (!$auto) {
                        continue;
                    }
                    [$next, $pruned] = self::prune($next, 1);
                    if ($pruned === []) {
                        continue;
                    }
                    $gone = array_merge($gone, $pruned);
                }
                $next[] = $row;
                $list = $next;
                foreach (array_merge($gone, $moved, [$row['from']]) as $from) {
                    $touched[$from] = true;
                }
                foreach (array_merge($gone, [$row['from']]) as $from) {
                    $reset[$from] = true;
                }
                $added++;
            }
            if ($added > 0) {
                self::save($list, array_keys($touched), array_keys($reset));
            }
            return $added;
        });
    }

    /** Supprime une redirection ; une entrée ancienne enregistrée sous une forme non normalisée se supprime aussi. */
    public static function remove(string $from): void
    {
        $raw = trim($from);
        $from = self::normalize($from);
        if (self::entry($from) === null && ($raw === $from || self::find($raw) === null)) {
            return;
        }
        Store::locked(function () use ($from, $raw): void {
            $list = [];
            $gone = [];
            foreach (self::all() as $r) {
                if ($r['from'] === $from || $r['from'] === $raw) {
                    $gone[] = $r['from'];
                    continue;
                }
                $list[] = $r;
            }
            if ($gone) {
                self::save($list, $gone, $gone);
            }
        });
    }

    /**
     * Compte une utilisation dans le fichier de cette redirection, sous son propre petit verrou : jamais le verrou
     * des données, jamais la liste. Un compteur illisible repart de zéro ; il ne bloque jamais la redirection.
     */
    public static function hit(string $from): void
    {
        try {
            $file = Store::file(self::HITS . '/' . self::key($from));
            if (!is_file($file)) {
                // Créé complet (ligne de protection, 0600) : jamais un fichier .php vide dans les données.
                Store::ensureDir(dirname($file));
                Store::atomic($file, Store::GUARD . '{"n":0,"t":0}');
            }
            $h = @fopen($file, 'r+');
            if ($h === false) {
                return;
            }
            try {
                if (flock($h, LOCK_EX)) {
                    $count = json_decode(Store::strip((string) stream_get_contents($h)), true);
                    $data = Store::GUARD . json_encode(['n' => (is_array($count) ? (int) ($count['n'] ?? 0) : 0) + 1, 't' => time()]);
                    // Réécrit sur place sans vider d'abord : le fichier commence toujours par la ligne de protection.
                    rewind($h);
                    fwrite($h, $data);
                    ftruncate($h, strlen($data));
                    fflush($h);
                    flock($h, LOCK_UN);
                }
            } finally {
                fclose($h);
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /** Redirection validée, ou null : adresse réservée, vide, trop longue, ou qui mène à elle-même. */
    private static function row(string $from, string $to, bool $auto, int $code, bool $admin): ?array
    {
        $from = self::normalize($from);
        $to = trim($to);
        $to = preg_match('~^https?://~i', $to) ? Sanitize::url($to) : self::normalize($to);
        if ($from === '/' || $to === '' || $from === $to || !self::allowed($from, $admin)
            || mb_strlen($from) > self::MAX_LENGTH || (str_starts_with($to, '/') && mb_strlen($to) > self::MAX_LENGTH)) {
            return null;
        }
        $row = ['from' => $from, 'to' => $to, 'code' => $code === 302 ? 302 : 301, 'auto' => $auto, 'hits' => 0, 'created' => time()];
        // Marque des adresses /.well-known/ enregistrées par un administrateur : les anciennes entrées restent inopérantes.
        return self::reserved($from) ? $row + ['admin' => true] : $row;
    }

    /** Retire les $n redirections automatiques les plus anciennes ; renvoie la liste restante et les adresses retirées. */
    private static function prune(array $list, int $n): array
    {
        $auto = array_filter($list, fn ($r) => !empty($r['auto']));
        uasort($auto, fn ($a, $b) => (int) ($a['created'] ?? 0) <=> (int) ($b['created'] ?? 0));
        $drop = array_slice(array_keys($auto), 0, $n);
        $gone = [];
        foreach ($drop as $i) {
            $gone[] = $list[$i]['from'];
            unset($list[$i]);
        }
        return [array_values($list), $gone];
    }

    /** Enregistre la liste, puis met à jour l'index : seulement les adresses touchées s'il était à jour, sinon en entier. */
    private static function save(array $list, array $touched, array $reset): void
    {
        $fresh = self::fresh(self::stamp());
        Store::write('redirects', $list);
        // L'index n'est qu'un raccourci : s'il ne peut pas être mis à jour, l'empreinte périmée le fera reconstruire.
        try {
            if (!$fresh) {
                self::rebuild($list);
            } else {
                $rows = array_column($list, null, 'from');
                foreach (array_unique($touched) as $from) {
                    $name = self::INDEX . '/' . self::key((string) $from);
                    isset($rows[$from]) ? Store::write($name, self::card($rows[$from])) : Store::delete($name);
                }
                Store::write(self::INDEX . '/stamp', ['table' => self::stamp()]);
            }
            foreach (array_unique($reset) as $from) {
                Store::delete(self::HITS . '/' . self::key((string) $from));
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /** Fiche d'index d'une adresse, lue par lookup(), ou null. Index périmé : reconstruit ; illisible : liste complète. */
    private static function entry(string $from): ?array
    {
        $stamp = self::stamp();
        if ($stamp === '') {
            return null;
        }
        try {
            if (!self::fresh($stamp)) {
                Store::locked(function (): void {
                    if (!self::fresh(self::stamp())) {
                        self::rebuild(self::all());
                    }
                });
            }
            $card = Store::read(self::INDEX . '/' . self::key($from));
        } catch (\Throwable $e) {
            Log::error($e);
            $row = self::find($from);
            return $row === null ? null : self::card($row);
        }
        return is_array($card) && ($card['from'] ?? null) === $from && is_string($card['to'] ?? null) ? $card : null;
    }

    /** Reconstruit tout l'index depuis la liste ; les compteurs d'adresses disparues sont supprimés. */
    private static function rebuild(array $list): void
    {
        $keep = [];
        foreach ($list as $r) {
            if (is_array($r) && is_string($r['from'] ?? null) && is_string($r['to'] ?? null)) {
                $key = self::key($r['from']);
                Store::write(self::INDEX . '/' . $key, self::card($r));
                $keep[$key] = true;
            }
        }
        $keep['stamp'] = true;
        foreach ([self::INDEX, self::HITS] as $dir) {
            foreach (Store::names($dir) as $name) {
                if (!isset($keep[substr($name, strlen($dir) + 1)])) {
                    Store::delete($name);
                }
            }
        }
        Store::write(self::INDEX . '/stamp', ['table' => self::stamp()]);
    }

    private static function card(array $r): array
    {
        $card = ['from' => (string) $r['from'], 'to' => (string) $r['to'], 'code' => (int) ($r['code'] ?? 301) === 302 ? 302 : 301];
        return !empty($r['admin']) ? $card + ['admin' => true] : $card;
    }

    /** L'index correspond-il à cette empreinte de la liste ? */
    private static function fresh(string $stamp): bool
    {
        $saved = Store::read(self::INDEX . '/stamp');
        return $stamp !== '' && is_array($saved) && ($saved['table'] ?? null) === $stamp;
    }

    /** Empreinte de la liste (inode, taille, date) : elle change à chaque écriture, même faite par un autre outil. */
    private static function stamp(): string
    {
        $file = Store::file('redirects');
        clearstatcache(true, $file);
        $st = @stat($file);
        return $st === false ? '' : $st['ino'] . '-' . $st['size'] . '-' . $st['mtime'];
    }

    private static function key(string $from): string
    {
        return substr(hash('sha256', $from), 0, 32);
    }
}
