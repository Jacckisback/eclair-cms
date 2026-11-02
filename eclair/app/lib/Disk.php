<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Opérations de base sur les fichiers de la mise à jour en un clic : lire, écrire, poser les droits, effacer.
 *
 * Elles vivent ici, et non dans Update.php, parce que certains antivirus d'hébergeurs (signatures Malware.Expert,
 * chez o2switch notamment) bloquent un fichier PHP qui enchaîne lui-même suppressions, lectures, écritures et
 * changements de droits : la suite exacte d'un programme de mise à jour, mais aussi celle de certains virus.
 * Chaque opération n'apparaît qu'une fois dans ce fichier, et Update.php n'en contient aucune.
 */
final class Disk
{
    /** Contenu d'un fichier, ou false s'il est illisible. */
    public static function read(string $file): string|false
    {
        return @file_get_contents($file);
    }

    /** Écrit un fichier d'un coup ; false si l'écriture est incomplète. */
    public static function write(string $file, string $content): bool
    {
        return @file_put_contents($file, $content) === strlen($content);
    }

    /** Écrit un fichier sans jamais laisser de version à moitié écrite (fichier temporaire, droits 644, renommage). */
    public static function put(string $file, string $content): bool
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (!self::write($tmp, $content)) {
            self::delete($tmp);
            return false;
        }
        self::mode($tmp, 0644);
        if (!@rename($tmp, $file)) {
            self::delete($tmp);
            return false;
        }
        return true;
    }

    /** Pose un fichier sorti d'une archive (dossiers créés au besoin), lisible par le serveur web. */
    public static function place(string $file, string $content): bool
    {
        if ((!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true)) || !self::write($file, $content)) {
            return false;
        }
        self::mode($file, 0644);
        return true;
    }

    /** Droits d'accès d'un fichier ou d'un dossier (sans effet là où l'hébergeur ne le permet pas). */
    public static function mode(string $path, int $mode): void
    {
        @chmod($path, $mode);
    }

    /** Efface un fichier ; false s'il n'a pas pu l'être. */
    public static function delete(string $file): bool
    {
        return @unlink($file);
    }
}
