<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Centre de sécurité : journal, blocages, intégrité des fichiers, sauvegarde. */
final class AdminSecurity
{
    public static function page(array $user): never
    {
        $integrity = null;
        if (!empty($_SESSION['integrity'])) {
            $integrity = $_SESSION['integrity'];
            unset($_SESSION['integrity']);
        }
        $admins = Users::admins();
        $checks = [
            ['ok' => Security::httpsOnly(), 'label' => t('Connexion chiffrée (HTTPS) imposée')],
            ['ok' => Settings::verified(), 'label' => t('Adresse du site vérifiée ({url})', ['url' => (string) Settings::get('url')])],
            ['ok' => !array_filter($admins, fn ($u) => empty($u['totp']['on'])), 'label' => t('Double authentification pour tous les administrateurs')],
            ['ok' => Settings::get('admin_path') !== 'admin', 'label' => t('Adresse d’administration personnalisée (moins de robots)')],
            ['ok' => PHP_VERSION_ID >= 80200, 'label' => t('Version de PHP maintenue')],
            ['ok' => defined('PASSWORD_ARGON2ID'), 'label' => t('Mots de passe chiffrés avec Argon2id')],
            ['ok' => (bool) Settings::get('security.hsts') && Security::httpsOnly(), 'label' => t('HSTS (navigateurs forcés en HTTPS)')],
        ];
        Admin::view('security', [
            'checks' => $checks,
            'score' => (int) round(count(array_filter($checks, fn ($c) => $c['ok'])) / count($checks) * 100),
            'blocked' => self::blocked(),
            'log' => self::events(),
            'errors' => Log::read('errors', 20),
            'integrity' => $integrity,
            'zip' => class_exists('ZipArchive'),
            'gd' => Media::gd(),
            'verified' => Settings::verified(),
            'drive' => Drive::status(),
        ], t('Sécurité'));
    }

    public static function action(array $user): never
    {
        switch (Req::input('action')) {
            case 'unblock':
                RateLimit::clear(Req::input('key'));
                Admin::back('security', t('Adresse débloquée.'));
            case 'revoke-all':
                foreach (array_keys(Users::all()) as $id) {
                    $saved = Auth::revokeAll((string) $id);
                    // Identifiants comparés en texte (une clé « 1234567890 » devient un entier en PHP). Aucun mot de passe
                    // vérifié ici : la session garde son âge et sa confirmation (Auth::rebind, pas login).
                    if ($saved && (string) $id === (string) $user['id']) {
                        Auth::rebind($saved);
                    }
                }
                Log::security('sessions_revoked');
                Admin::back('security', t('Toutes les sessions ont été fermées (sauf la tienne).'));
            case 'integrity':
                $_SESSION['integrity'] = Integrity::check();
                Http::redirect(admin_url('security#integrite'));
            case 'verify-url':
                Settings::verifyUrl()
                    ? Admin::back('security', t('Adresse vérifiée : les visiteurs sont redirigés vers elle.'))
                    : Admin::back('security', t('Vérification impossible : ton serveur ne parvient pas à joindre {url}. Le site fonctionne, mais sans redirection vers l’adresse officielle.', ['url' => (string) Settings::get('url')]), 'warn');
            case 'reindex':
                Content::reindex();
                Cache::purge();
                Admin::back('security', t('Index reconstruit et cache vidé.'));
        }
        Admin::back('security');
    }

    /** Clés bloquées ; le compteur d'un compte (groupe signé de l'e-mail) affiche les comptes concernés. */
    private static function blocked(): array
    {
        $accounts = [];
        foreach (Users::all() as $u) {
            $accounts[Auth::accountKey((string) $u['email'])][] = (string) $u['email'];
        }
        $out = [];
        foreach (RateLimit::active() as $b) {
            $out[] = $b + ['label' => isset($accounts[$b['key']]) ? $b['key'] . ' (' . implode(', ', $accounts[$b['key']]) . ')' : $b['key']];
        }
        return $out;
    }

    /**
     * Journal de la page : les vrais événements, plus les derniers échecs de connexion anonymes, gardés à part
     * pour qu'aucun afflux ne puisse chasser les premiers.
     */
    private static function events(): array
    {
        $log = array_merge(Log::read('security', 80), Log::read(Log::FAILURES, 20));
        usort($log, static fn (array $a, array $b): int => (int) ($b['t'] ?? 0) <=> (int) ($a['t'] ?? 0));
        return $log;
    }

    public static function clearCache(array $user): never
    {
        Cache::purge();
        Admin::back(ltrim(Req::input('back'), '/'), t('Cache vidé : toutes les pages seront régénérées.'));
    }

    /** Sauvegarde complète (contenus, réglages, médias) dans un fichier ZIP. */
    public static function backup(array $user): never
    {
        $back = Req::input('back') === 'update' ? 'update' : 'security';
        if (!Auth::confirmed($user)) {
            Admin::back($back, t('Mot de passe incorrect.'), 'bad');
        }
        extend_time(300);
        $file = ECLAIR_ROOT . '/cache/backup-' . bin2hex(random_bytes(8)) . '.zip';
        try {
            Backup::create($file);
        } catch (\Throwable $e) {
            Admin::back($back, $e->getMessage(), 'bad');
        }
        Log::security('backup_downloaded');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . Backup::name() . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        @unlink($file);
        exit;
    }

    /** Restaure une sauvegarde envoyée (mot de passe confirmé) : voir Backup::restore() pour les garanties. */
    public static function restore(array $user): never
    {
        if (!Auth::confirmed($user)) {
            Admin::back('security', t('Mot de passe incorrect.'), 'bad');
        }
        $tmp = (string) ($_FILES['backup']['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && !(defined('ECLAIR_TESTING') && is_file($tmp)))) {
            Admin::back('security', t('Choisis d’abord le fichier de sauvegarde (.zip).'), 'bad');
        }
        extend_time(300);
        $r = Backup::restore($tmp);
        Admin::back('security', $r['ok'] ? t('Sauvegarde restaurée : {n} fichiers remis en place.', ['n' => $r['files']]) : (string) $r['error'], $r['ok'] ? 'ok' : 'bad');
    }
}
