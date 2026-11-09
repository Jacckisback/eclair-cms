<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Stockage à plat, sans base de données.
 *
 * Chaque donnée est un fichier .php qui commence par une instruction de sortie :
 * même si un serveur ignorait les .htaccess, l'appeler depuis le web n'affiche rien.
 * Les écritures sont atomiques (fichier temporaire puis renommage).
 */
final class Store
{
    public const GUARD = "<?php exit; ?>\n";
    /**
     * Fichiers dérivés des contenus, relus en entier à chaque page générée (index) ou à chaque recherche : écrits sans
     * indentation. Sur un site de 3 000 contenus, l'index pèse 27 % de moins et se décode un tiers plus vite ; les autres
     * fichiers (réglages, contenus…) restent indentés, lisibles et comparables à la main.
     */
    private const COMPACT = ['index' => true, 'index-lite' => true, 'search' => true];
    /** Réparation d'un fichier de verrou laissée en plan au-delà de ce délai (secondes) : abandonnée, on peut reprendre. */
    private const REPAIR_STALE = 30;

    /** @var array<string, mixed> */
    private static array $memo = [];
    private static int $depth = 0;

    public static function file(string $name): string
    {
        if (!preg_match('~^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$~', $name)) {
            throw new \InvalidArgumentException('Nom de donnée invalide : ' . $name);
        }
        return ECLAIR_DATA . '/' . $name . '.php';
    }

    public static function exists(string $name): bool
    {
        return array_key_exists($name, self::$memo) || is_file(self::file($name));
    }

    public static function read(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, self::$memo)) {
            return self::$memo[$name];
        }
        $raw = @file_get_contents(self::file($name));
        if ($raw === false) {
            return $default;
        }
        $data = json_decode(self::strip($raw), true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::error(new \RuntimeException('Fichier de données illisible : ' . $name));
            return $default;
        }
        return self::$memo[$name] = $data;
    }

    public static function write(string $name, mixed $data): void
    {
        $file = self::file($name);
        self::ensureDir(dirname($file));
        // HEX_TAG/HEX_AMP : aucune séquence « <?php » ne peut apparaître dans un fichier de données.
        $json = json_encode(
            $data,
            (isset(self::COMPACT[$name]) ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
        self::atomic($file, self::GUARD . $json);
        self::$memo[$name] = $data;
    }

    /**
     * Oublie une donnée gardée en mémoire pour la requête (le fichier reste). Pour les parcours de tous les contenus
     * (plan du site, reconstruction de l'index) : sans cela, chaque fichier lu restait en mémoire jusqu'à la fin de la requête
     * (50 Mo pour 3 000 contenus, au-delà de la limite de PHP pour 10 000).
     */
    public static function drop(string $name): void
    {
        unset(self::$memo[$name]);
    }

    public static function delete(string $name): void
    {
        unset(self::$memo[$name]);
        $file = self::file($name);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Noms des données d'un sous-dossier (ex. « content » → ["content/ab12…", …]). */
    public static function names(string $dir): array
    {
        $out = [];
        foreach (glob(ECLAIR_DATA . '/' . $dir . '/*.php') ?: [] as $file) {
            $base = basename($file, '.php');
            if (preg_match('~^[a-z0-9_-]+$~', $base)) {
                $out[] = $dir . '/' . $base;
            }
        }
        return $out;
    }

    /**
     * Exécute une opération lire-modifier-écrire sous verrou exclusif (réentrant).
     * Jamais sans verrou : si data/.lock ne peut être ni ouvert, ni recréé, ni verrouillé, l'enregistrement est refusé
     * (une exception au message clair) plutôt que lancé sans protection sur des données lues plus tôt.
     */
    public static function locked(callable $fn): mixed
    {
        if (self::$depth > 0) {
            return $fn();
        }
        self::ensureDir(ECLAIR_DATA);
        $h = self::lockFile(ECLAIR_DATA . '/.lock');
        // Un appel interrompu (signal) est retenté une fois ; un échec persistant (stockage sans verrous) refuse.
        if ($h === false || (!flock($h, LOCK_EX) && !flock($h, LOCK_EX))) {
            if ($h !== false) {
                fclose($h);
            }
            throw new StorageException(t('Enregistrement impossible : le verrou des données (data/.lock) ne fonctionne pas. Supprime ce fichier par FTP (Éclair le recrée), ou demande à ton hébergeur si le verrouillage des fichiers (flock) est disponible.'));
        }
        self::$depth++;
        try {
            self::$memo = [];
            return $fn();
        } finally {
            self::$depth--;
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    /**
     * Ouvre un fichier de verrou (data/.lock, places de connexion). S'il existe mais que PHP ne peut plus l'ouvrir
     * (copié ou restauré par un autre compte, dossier à sa place), il est recréé quand le dossier le permet.
     * Un seul processus répare à la fois (mkdir est atomique) : jamais deux fichiers de verrou en service en même temps.
     *
     * @return resource|false
     */
    public static function lockFile(string $file): mixed
    {
        $h = @fopen($file, 'c');
        if ($h !== false) {
            return $h;
        }
        clearstatcache(true, $file);
        if (!file_exists($file) && !is_link($file)) {
            return false; // absent et impossible à créer : le dossier lui-même est en lecture seule
        }
        $mutex = $file . '.repair';
        if (!@mkdir($mutex, 0700)) {
            clearstatcache(true, $mutex);
            // Réparation en cours ailleurs (ou à l'instant) : un nouvel essai suffit. Un reste d'une réparation
            // interrompue (plus de 30 s) est retiré pour ne jamais bloquer la suivante.
            if (time() - (int) @filemtime($mutex) < self::REPAIR_STALE || !@rmdir($mutex) || !@mkdir($mutex, 0700)) {
                return @fopen($file, 'c');
            }
        }
        try {
            clearstatcache(true, $file);
            if (is_dir($file) && !is_link($file)) {
                @rmdir($file); // un dossier vide à la place du fichier
            } elseif ((file_exists($file) || is_link($file)) && !is_writable($file)) {
                @unlink($file); // appartient à un autre compte : le dossier, lui, nous appartient
            }
            $h = @fopen($file, 'c');
            if ($h !== false) {
                Log::alert('lock-repaired', 'Fichier de verrou recréé (il ne pouvait plus être ouvert) : ' . basename($file));
            }
            return $h;
        } finally {
            @rmdir($mutex);
        }
    }

    /** Écrit un fichier de façon atomique. */
    /**
     * Écrit un fichier de façon atomique, lisible uniquement par PHP (0600).
     * Le fichier temporaire ne porte l'extension .php que s'il commence par la ligne de protection. Il est créé privé
     * (masque 0077 le temps de l'écriture) : jamais lisible par un autre compte, même un instant, avant ses droits définitifs.
     */
    public static function atomic(string $file, string $content): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . (str_starts_with($content, self::GUARD) ? '.tmp.php' : '.tmp');
        $mask = umask(0077);
        try {
            $written = @file_put_contents($tmp, $content, LOCK_EX);
        } finally {
            umask($mask);
        }
        if ($written !== strlen($content)) {
            @unlink($tmp);
            throw new \RuntimeException("Impossible d'écrire « " . basename($file) . ' » : vérifie les droits du dossier.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException("Impossible d'enregistrer « " . basename($file) . ' ».');
        }
    }

    /** Crée un dossier : privé (0700) pour les données et le cache, public (0755) ailleurs (médias). */
    public static function ensureDir(string $dir): void
    {
        $private = str_starts_with($dir, ECLAIR_DATA) || str_starts_with($dir, ECLAIR_ROOT . '/cache');
        if (!is_dir($dir) && !@mkdir($dir, $private ? 0700 : 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier ' . basename($dir) . '.');
        }
        // Dossier des données livré avec l'archive (il garde les droits de l'envoi par FTP, souvent 755 ou 777) : resserré
        // au mieux à 0700 dès qu'Éclair s'en sert (installation comprise), sans échec là où l'hébergeur le refuse.
        $data = rtrim(ECLAIR_DATA, '/\\');
        if (rtrim($dir, '/\\') === $data && $data !== '' && (@fileperms($dir) & 0077) !== 0) {
            @chmod($dir, 0700);
        }
    }

    /** Retire la ligne de protection en tête de fichier. */
    public static function strip(string $raw): string
    {
        return str_starts_with($raw, '<?php') ? substr($raw, (int) strpos($raw, "\n") + 1) : $raw;
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
