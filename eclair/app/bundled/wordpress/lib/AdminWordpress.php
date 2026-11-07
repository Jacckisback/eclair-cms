<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Wordpress;

use Eclair\Admin;
use Eclair\Http;
use Eclair\Req;

defined('ECLAIR') || exit;

/** Import WordPress dans l'administration : envoi de l'export, import par lots, bilan (droit Réglages). */
final class AdminWordpress
{
    public static function page(array $user): never
    {
        Admin::extensionPage('wordpress', __DIR__ . '/page.php', ['job' => Importer::job(), 'limit' => self::limit()], t('Import WordPress'));
    }

    public static function action(array $user): never
    {
        $action = Req::input('action');
        if ($action === 'cancel') {
            Importer::cancel();
            Admin::back('x/wordpress', t('Import abandonné. Les contenus déjà importés sont gardés.'));
        }
        if ($action === 'step') {
            $job = Importer::step($user);
            Admin::back('x/wordpress', !empty($job['done']) ? t('Import terminé.') : t('{n} contenus sur {total} importés : continue.', ['n' => (int) ($job['next'] ?? 0), 'total' => (int) ($job['total'] ?? 0)]));
        }
        if ($action === 'upload') {
            $job = Importer::job();
            if ($job !== null && !$job['done']) {
                Admin::back('x/wordpress', t('Un import est déjà en cours : termine-le ou abandonne-le.'), 'bad');
            }
            $tmp = (string) ($_FILES['wxr']['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                Admin::back('x/wordpress', (int) ($_FILES['wxr']['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                    ? t('Fichier plus lourd que ce que ton hébergeur accepte ({n}) : exporte par type ou par période.', ['n' => self::limit()])
                    : t('Choisis d’abord le fichier .xml exporté depuis WordPress.'), 'bad');
            }
            $export = Wxr::parse((string) file_get_contents($tmp));
            if (is_string($export)) {
                Admin::back('x/wordpress', $export, 'bad');
            }
            if (!$export['items']) {
                Admin::back('x/wordpress', t('Aucun article ni page dans cet export.'), 'bad');
            }
            Importer::start($export, Req::bool('keep'), Req::bool('images'));
            Admin::back('x/wordpress', t('Export lu : {n} contenus prêts à importer.', ['n' => count($export['items'])]));
        }
        Admin::back('x/wordpress');
    }

    /** Lot suivant (appelé en boucle par la page) : avancement en JSON. */
    public static function step(array $user): never
    {
        session_write_close();
        extend_time(60);
        $job = Importer::step($user);
        Http::json(['ok' => $job !== [], 'done' => (bool) ($job['done'] ?? true), 'next' => (int) ($job['next'] ?? 0), 'total' => (int) ($job['total'] ?? 0)]);
    }

    private static function limit(): string
    {
        $bytes = static function (string $v): int {
            $n = (int) $v;
            return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        $max = min(array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size')), Wxr::MAX_BYTES]));
        return human_size($max);
    }
}
