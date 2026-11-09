<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Wordpress;

use Eclair\Store;

defined('ECLAIR') || exit;

/**
 * Paquet de l'extension WordPress « Eclair Export » (format eclair-export 1.x, voir son docs/FORMAT.md) : une ou plusieurs
 * archives ZIP, envoyées depuis l'administration ou déposées par FTP dans data/import/.
 *
 * Lu comme des données : seules les entrées attendues sont lues (jamais d'extraction d'un chemin choisi par l'archive),
 * taille décompressée bornée avant lecture, empreintes SHA-256 du manifeste vérifiées. Les fichiers de données de la
 * partie 1 sont copiés dans data/ext/wordpress/package/ ; les médias restent dans leurs archives et sont lus un par un.
 */
final class Package
{
    /** Fichiers de données lus dans la partie 1 (les contenus content/cpt-<type>.json en plus). */
    public const DATA = ['manifest.json', 'content/taxonomies.json', 'content/authors.json', 'content/menus.json', 'content/settings.json',
        'media/index.json', 'seo.json', 'redirects.csv', 'urls.csv', 'report.json'];
    /** Taille décompressée maximale d'un fichier de données, et d'un média. */
    public const MAX_DATA = 200 * 1048576;
    public const MAX_MEDIA = 25 * 1048576;
    /** Parties d'un même paquet au plus. */
    public const MAX_PARTS = 200;

    /** Dossier privé du paquet en cours (dans les données, jamais servi). */
    public static function dir(): string
    {
        $dir = ECLAIR_DATA . '/ext/wordpress/package';
        Store::ensureDir($dir);
        return $dir;
    }

    /** Dossier de dépôt par FTP (data/import/), pour les paquets plus lourds que la limite d'envoi de l'hébergeur. */
    public static function inbox(): string
    {
        return ECLAIR_DATA . '/import';
    }

    /** Archives disponibles : envoyées (package/parts/) puis déposées (data/import/), nom => chemin. */
    public static function parts(): array
    {
        $out = [];
        foreach ([self::dir() . '/parts', self::inbox()] as $dir) {
            foreach (glob($dir . '/*.zip') ?: [] as $file) {
                $name = basename($file);
                if (preg_match('~^[A-Za-z0-9._-]{1,180}\.zip$~', $name) && is_file($file) && !is_link($file)) {
                    $out[$name] ??= $file;
                }
            }
        }
        ksort($out);
        return array_slice($out, 0, self::MAX_PARTS, true);
    }

    /**
     * Archives envoyées par le formulaire ($_FILES['parts'] à plusieurs fichiers) : rangées dans package/parts/.
     * Renvoie le nombre reçu, ou un message d'erreur.
     */
    public static function receive(array $files): int|string
    {
        $names = (array) ($files['name'] ?? []);
        $n = 0;
        foreach ($names as $i => $name) {
            $tmp = (string) ($files['tmp_name'][$i] ?? '');
            $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                return t('Partie plus lourde que ce que ton hébergeur accepte : refais l’export avec des parties plus petites, ou dépose les fichiers par FTP dans le dossier data/import/.');
            }
            if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            $clean = (string) preg_replace('~[^A-Za-z0-9._-]~', '-', basename((string) $name));
            if (!preg_match('~\.zip$~i', $clean) || !self::isZip($tmp)) {
                return t('« {name} » n’est pas une archive ZIP d’Eclair Export.', ['name' => basename((string) $name)]);
            }
            Store::ensureDir(self::dir() . '/parts');
            if (!move_uploaded_file($tmp, self::dir() . '/parts/' . substr($clean, 0, 180))) {
                return t('Impossible d’enregistrer l’archive : vérifie l’espace disque.');
            }
            $n++;
        }
        return $n;
    }

    private static function isZip(string $file): bool
    {
        $h = @fopen($file, 'rb');
        $magic = $h ? (string) fread($h, 4) : '';
        $h && fclose($h);
        return $magic === "PK\x03\x04";
    }

    /**
     * Ouvre le paquet : trouve la partie qui porte le manifeste, vérifie le format, les empreintes et la présence des autres
     * parties, copie les données. Renvoie le manifeste (avec 'files' => fichiers de contenus présents), ou un message.
     */
    public static function open(): array|string
    {
        if (!class_exists(\ZipArchive::class)) {
            return t('L’import d’un paquet demande l’extension PHP zip, absente de ton hébergement.');
        }
        $parts = self::parts();
        $main = null;
        foreach ($parts as $file) {
            $zip = new \ZipArchive();
            if ($zip->open($file, \ZipArchive::RDONLY) === true) {
                $found = $zip->locateName('manifest.json') !== false;
                $zip->close();
                if ($found) {
                    $main = $file;
                    break;
                }
            }
        }
        if ($main === null) {
            return $parts ? t('Aucune archive ne contient le manifeste : envoie aussi la première partie (le fichier sans « -part » dans son nom).')
                : t('Aucune archive reçue.');
        }
        $zip = new \ZipArchive();
        $zip->open($main, \ZipArchive::RDONLY);
        try {
            $manifest = json_decode((string) self::entry($zip, 'manifest.json'), true);
            if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'eclair-export' || !preg_match('~^1\.\d+$~', (string) ($manifest['format_version'] ?? ''))) {
                return t('Ce paquet n’est pas au format Eclair Export 1.x : mets l’extension WordPress à jour, puis refais l’export.');
            }
            $out = self::dir() . '/data';
            self::wipe($out);
            Store::ensureDir($out . '/content');
            Store::ensureDir($out . '/media');
            $files = [];
            foreach ((array) ($manifest['files'] ?? []) as $name => $sum) {
                $name = (string) $name;
                if (!in_array($name, self::DATA, true) && !preg_match('~^content/(posts|pages|products|cpt-[a-z0-9_-]{1,40})\.json$~', $name)) {
                    continue;
                }
                $bytes = self::entry($zip, $name);
                if ($bytes === null) {
                    return t('Fichier « {name} » absent du paquet : l’archive est incomplète, refais le téléchargement.', ['name' => $name]);
                }
                if (!is_array($sum) || !hash_equals((string) ($sum['sha256'] ?? ''), hash('sha256', $bytes))) {
                    return t('Fichier « {name} » abîmé (empreinte différente) : refais le téléchargement de l’archive.', ['name' => $name]);
                }
                file_put_contents($out . '/' . $name, $bytes, LOCK_EX);
                @chmod($out . '/' . $name, 0600);
                if (str_starts_with($name, 'content/') && !in_array($name, self::DATA, true)) {
                    $files[] = $name;
                }
            }
            file_put_contents($out . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        } finally {
            $zip->close();
        }
        // Autres parties : toutes doivent être là, du même paquet.
        $missing = [];
        foreach ((array) ($manifest['parts'] ?? []) as $part) {
            $name = basename((string) ($part['file'] ?? ''));
            if ($name !== '' && !isset($parts[$name])) {
                $missing[] = $name;
            }
        }
        $manifest['missing'] = $missing;
        $manifest['content_files'] = $files;
        return $manifest;
    }

    /** Contenu d'une entrée, si sa taille décompressée est raisonnable. */
    private static function entry(\ZipArchive $zip, string $name, int $max = self::MAX_DATA): ?string
    {
        $stat = $zip->statName($name);
        if (!is_array($stat) || (int) $stat['size'] > $max) {
            return null;
        }
        $bytes = $zip->getFromName($name);
        return is_string($bytes) && strlen($bytes) === (int) $stat['size'] ? $bytes : null;
    }

    /** Données copiées par open() : JSON décodé (ou texte brut pour un .csv), null si absent. */
    public static function data(string $name): mixed
    {
        if (!in_array($name, self::DATA, true) && !preg_match('~^content/[a-z0-9_-]{1,50}\.json$~', $name)) {
            return null;
        }
        $raw = @file_get_contents(self::dir() . '/data/' . $name);
        if ($raw === false) {
            return null;
        }
        return str_ends_with($name, '.csv') ? $raw : json_decode($raw, true);
    }

    /** Lignes d'un fichier CSV du paquet (en-tête comprise). */
    public static function csv(string $name): array
    {
        $rows = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, (string) self::data($name));
        rewind($h);
        while (($row = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }
        fclose($h);
        return $rows;
    }

    /**
     * Octets d'un média du paquet (entrée « media/uploads/… » d'une des parties), vérifiés par leur empreinte ; null s'il
     * n'est dans aucune partie ou s'il est abîmé.
     */
    public static function media(string $file, string $sha256): ?string
    {
        if (!preg_match('~^media/uploads/[^\x00-\x1f\\\\]{1,400}$~u', $file) || str_contains($file, '..')) {
            return null;
        }
        static $open = [];
        foreach (self::parts() as $name => $path) {
            if (!isset($open[$name])) {
                $zip = new \ZipArchive();
                $open[$name] = $zip->open($path, \ZipArchive::RDONLY) === true ? $zip : false;
            }
            if ($open[$name] instanceof \ZipArchive && $open[$name]->locateName($file) !== false) {
                $bytes = self::entry($open[$name], $file, self::MAX_MEDIA);
                return $bytes !== null && ($sha256 === '' || hash_equals($sha256, hash('sha256', $bytes))) ? $bytes : null;
            }
        }
        return null;
    }

    /** Supprime le paquet reçu (archives envoyées et données copiées ; les dépôts FTP de data/import/ aussi si $inbox). */
    public static function clear(bool $inbox = false): void
    {
        self::wipe(self::dir());
        if ($inbox) {
            foreach (glob(self::inbox() . '/*.zip') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    private static function wipe(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
    }
}
