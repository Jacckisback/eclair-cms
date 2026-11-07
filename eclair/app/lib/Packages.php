<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Paquets du site (thèmes, capacités) livrés en archive ZIP : décompression vérifiée, puis installation par leur registre. */
final class Packages
{
    /**
     * Installe un thème ou une capacité depuis une archive ZIP (paquet produit par Éclair Design, un skill ou un intégrateur). Chaque entrée
     * est vérifiée avant toute écriture : chemin sûr (ni « .. », ni chemin absolu, ni fichier caché), aucun lien symbolique,
     * tailles bornées (archive « bombe » refusée), puis le dossier décompressé passe la validation complète du contrat.
     * Le manifeste (theme.json, extension.json) est attendu à la racine de l'archive ou dans un unique dossier de premier niveau.
     */
    public static function installZip(string $file, string $kind): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => t('Extension PHP {n} absente : active-la dans le panneau PHP de ton hébergeur.', ['n' => 'zip'])];
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return ['ok' => false, 'error' => t('Archive illisible : rien n’a été modifié.')];
        }
        Themes::ensure();
        $manifest = $kind === 'extension' ? 'extension.json' : 'theme.json';
        $root = $kind === 'extension' ? Extensions::dir() : Themes::dir();
        $stage = $root . '/.upload-' . bin2hex(random_bytes(4));
        try {
            $total = 0;
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                $zip->getExternalAttributesIndex($i, $os, $attr);
                if ($os === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                    return ['ok' => false, 'error' => t('Lien symbolique refusé : {f}.', ['f' => $name])];
                }
                if (!Themes::safePath($name) || str_starts_with(basename($name), '.')) {
                    return ['ok' => false, 'error' => t('Archive refusée : chemin suspect.')];
                }
                $total += (int) ($stat['size'] ?? 0);
                if (count($entries) >= Themes::MAX_FILES || (int) ($stat['size'] ?? 0) > Themes::MAX_FILE || $total > Themes::MAX_TOTAL) {
                    return ['ok' => false, 'error' => t('Paquet trop volumineux ({n} fichiers, 2 Mo par fichier, 20 Mo en tout au plus).', ['n' => Themes::MAX_FILES])];
                }
                $entries[$i] = $name;
            }
            $roots = array_unique(array_map(static fn (string $n): string => str_contains($n, '/') ? explode('/', $n)[0] : '', $entries));
            $prefix = in_array($manifest, $entries, true) ? '' : (count($roots) === 1 && $roots[0] !== '' && in_array($roots[0] . '/' . $manifest, $entries, true) ? $roots[0] . '/' : null);
            if ($prefix === null) {
                return ['ok' => false, 'error' => $kind === 'extension' ? t('Fichier extension.json absent ou illisible.') : t('Fichier theme.json absent ou illisible.')];
            }
            foreach ($entries as $i => $name) {
                $data = $zip->getFromIndex($i);
                if (!is_string($data) || !Disk::place($stage . '/' . substr($name, strlen($prefix)), $data)) {
                    return ['ok' => false, 'error' => t('Décompression impossible : espace disque ou droits insuffisants. Rien n’a été modifié.')];
                }
            }
            return $kind === 'extension' ? Extensions::install($stage) : Themes::install($stage);
        } finally {
            $zip->close();
            Themes::removeTree($stage);
        }
    }
}
