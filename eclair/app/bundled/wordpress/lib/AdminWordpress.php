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
        Admin::extensionPage('wordpress', __DIR__ . '/page.php', ['job' => Importer::job(), 'limit' => self::limit(), 'pjob' => PackageImporter::job(),
            'parts' => array_map('filesize', Package::parts()), 'branding' => Report::branding(), 'speed' => Report::speed()], t('Import WordPress'));
    }

    public static function action(array $user): never
    {
        $action = Req::input('action');
        if ($action === 'branding') {
            Report::saveBranding(['name' => Req::input('name'), 'company' => Req::input('company'), 'email' => Req::input('email'),
                'phone' => Req::input('phone'), 'website' => Req::input('website'), 'note' => Req::input('note')]);
            Admin::back('x/wordpress', t('Enregistré.'));
        }
        if ($action === 'speed') {
            session_write_close();
            extend_time(150);
            $which = Req::input('which') === 'old' ? 'old' : 'new';
            $error = Report::measure($which, trim(Req::input('url')));
            Admin::back('x/wordpress', $error ?? t('Vitesse mesurée.'), $error === null ? 'ok' : 'bad');
        }
        if (in_array($action, ['package', 'simulate', 'import', 'pstep', 'pcancel', 'pclear'], true)) {
            self::package($user, $action);
        }
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
            // Lu en flux, contenu par contenu : un gros export ne dépasse ni la mémoire ni le temps d'une requête.
            session_write_close();
            extend_time(300);
            $job = Importer::startFile($tmp, Req::bool('keep'), Req::bool('images'));
            if (is_string($job)) {
                Admin::back('x/wordpress', $job, 'bad');
            }
            Admin::back('x/wordpress', t('Export lu : {n} contenus prêts à importer.', ['n' => (int) $job['total']]));
        }
        Admin::back('x/wordpress');
    }

    /** Paquet Eclair Export : envoi des parties, simulation, import, abandon. */
    private static function package(array $user, string $action): never
    {
        $pjob = PackageImporter::job();
        $running = $pjob !== null && !$pjob['done'];
        if ($action === 'pcancel') {
            PackageImporter::cancel();
            Admin::back('x/wordpress', t('Import abandonné. Les contenus déjà importés sont gardés.'));
        }
        if ($action === 'pclear') {
            PackageImporter::cancel();
            Package::clear(true);
            Admin::back('x/wordpress', t('Paquet retiré du serveur.'));
        }
        if ($action === 'pstep') {
            $job = PackageImporter::step($user);
            [$n, $total] = PackageImporter::progress($job);
            Admin::back('x/wordpress', !empty($job['done']) ? t('Import terminé.') : t('{n} contenus sur {total} importés : continue.', ['n' => $n, 'total' => $total]));
        }
        if ($running || (($job = Importer::job()) !== null && !$job['done'])) {
            Admin::back('x/wordpress', t('Un import est déjà en cours : termine-le ou abandonne-le.'), 'bad');
        }
        if ($action === 'package') {
            $n = Package::receive((array) ($_FILES['parts'] ?? []));
            Admin::back('x/wordpress', is_string($n) ? $n : t('{n} archive(s) reçue(s).', ['n' => $n]), is_string($n) || $n === 0 ? 'bad' : 'ok');
        }
        extend_time(120);
        $manifest = Package::open();
        if (is_string($manifest)) {
            Admin::back('x/wordpress', $manifest, 'bad');
        }
        if ($manifest['missing']) {
            Admin::back('x/wordpress', t('Parties manquantes : {list}. Envoie-les aussi (ou dépose-les dans data/import/).', ['list' => implode(', ', $manifest['missing'])]), 'bad');
        }
        PackageImporter::start($manifest, ['dry' => $action === 'simulate', 'keep' => Req::bool('keep'), 'site' => Req::bool('site'), 'menus' => Req::bool('menus'), 'remote' => Req::bool('remote')]);
        Admin::back('x/wordpress', $action === 'simulate' ? t('Simulation prête : lance-la, rien ne sera écrit.') : t('Paquet vérifié : import prêt.'));
    }

    /** Rapport de migration (page autonome, à imprimer en PDF ; ?download=1 : fichier HTML). */
    public static function report(array $user): never
    {
        $lang = Req::query('lang', 'fr');
        $html = Report::html($lang);
        $headers = ['Content-Type: text/html; charset=utf-8', 'X-Content-Type-Options: nosniff', 'Cache-Control: no-store',
            "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"];
        if (Req::query('download') === '1') {
            $headers[] = 'Content-Disposition: attachment; filename="' . Report::filename() . '"';
        }
        Http::send($html, 200, $headers);
    }

    /** Lot suivant (appelé en boucle par la page) : avancement en JSON. */
    public static function step(array $user): never
    {
        session_write_close();
        extend_time(60);
        $pjob = PackageImporter::job();
        if ($pjob !== null && !$pjob['done']) {
            $pjob = PackageImporter::step($user);
            [$n, $total] = PackageImporter::progress($pjob);
            Http::json(['ok' => $pjob !== [], 'done' => (bool) ($pjob['done'] ?? true), 'next' => $n, 'total' => $total]);
        }
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
