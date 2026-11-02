<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Archive de sauvegarde du site (contenus, réglages, comptes, médias) : téléchargée, ou envoyée sur Google Drive. */
final class Backup
{
    /** Jamais dans une sauvegarde : sessions, compteurs, conversations IA, secrets (coffre des clés), état des services. */
    private const SKIP = '~^data/(sessions/|limits/|ai/|reset\.php$|vault\.php$|drive\.php$|update\.php$)~';

    /** Fichiers à sauvegarder : chemin dans l'archive => chemin sur le disque. */
    public static function files(): array
    {
        $out = [];
        foreach (['data' => ECLAIR_DATA, 'media' => ECLAIR_ROOT . '/media'] as $prefix => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $path => $info) {
                $rel = $prefix . '/' . str_replace('\\', '/', substr((string) $path, strlen($dir) + 1));
                if ($info->isFile() && !preg_match(self::SKIP, $rel) && !str_ends_with($rel, '.lock') && !str_contains($rel, '.tmp')) {
                    $out[$rel] = (string) $path;
                }
            }
        }
        ksort($out);
        return $out;
    }

    /** Crée l'archive $file. Les images, déjà compressées, y sont rangées telles quelles (rapide, peu de calcul). */
    public static function create(string $file): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException(t('L’extension zip n’est pas disponible : copie les dossiers data et media par FTP.'));
        }
        $files = self::files();
        $size = array_sum(array_map(static fn (string $path): int => (int) @filesize($path), $files));
        $free = function_exists('disk_free_space') ? @disk_free_space(dirname($file)) : false;
        if (is_float($free) && $free < $size * 1.1 + 20000000) {
            throw new \RuntimeException(t('Espace disque insuffisant pour préparer la sauvegarde ({n} Mo nécessaires).', ['n' => (int) ceil($size * 1.1 / 1048576) + 20]));
        }
        Store::ensureDir(dirname($file));
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(t('Impossible de créer la sauvegarde.'));
        }
        foreach ($files as $rel => $path) {
            $zip->addFile($path, $rel);
            if (str_starts_with($rel, 'media/') && preg_match('~\.(jpe?g|png|gif|webp|avif|woff2)$~i', $rel)) {
                $zip->setCompressionName($rel, \ZipArchive::CM_STORE);
            }
        }
        $zip->addFromString('LISEZ-MOI.txt', "Sauvegarde Éclair du " . date('Y-m-d H:i') . " (" . Settings::get('name') . ")\n\n"
            . "Pour restaurer : remplace les dossiers data/ et media/ de ton installation par ceux-ci (par FTP), puis vide le cache depuis l'administration.\n"
            . "Les clés d'API (assistant IA) et la connexion Google Drive ne sont jamais sauvegardées : saisis-les de nouveau après une restauration.\n");
        if (!$zip->close() || !is_file($file)) {
            @unlink($file);
            throw new \RuntimeException(t('Impossible de créer la sauvegarde.'));
        }
    }

    /** Nom du fichier de sauvegarde : sauvegarde-<site>-<date>.zip */
    public static function name(bool $time = false): string
    {
        return 'sauvegarde-' . (slugify((string) Settings::get('name')) ?: 'site') . '-' . date($time ? 'Y-m-d-His' : 'Y-m-d') . '.zip';
    }
}
