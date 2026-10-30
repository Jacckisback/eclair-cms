<?php
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
        // Fichiers internes que le navigateur tente d'ouvrir, dont un gabarit du thème personnalisé s'il est dans le dossier public.
        $exposure = ['/data/site.php', '/data/users.php', '/app/bootstrap.php', '/cache/runtime.php', '/data/.htaccess'];
        $templates = str_starts_with(Theme::dir(), ECLAIR_ROOT . '/') ? glob(Theme::dir() . '/*.php') : [];
        if ($templates) {
            $exposure[] = substr($templates[0], strlen(ECLAIR_ROOT));
        }
        Admin::view('security', [
            'checks' => $checks,
            'score' => (int) round(count(array_filter($checks, fn ($c) => $c['ok'])) / count($checks) * 100),
            'blocked' => RateLimit::active(),
            'log' => Log::read('security', 80),
            'errors' => Log::read('errors', 20),
            'integrity' => $integrity,
            'exposure' => $exposure,
            'zip' => class_exists('ZipArchive'),
            'gd' => Media::gd(),
            'verified' => Settings::verified(),
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
                    if ($saved && $id === $user['id']) {
                        Auth::login($saved);
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

    public static function clearCache(array $user): never
    {
        Cache::purge();
        Admin::back(ltrim(Req::input('back'), '/'), t('Cache vidé : toutes les pages seront régénérées.'));
    }

    /** Sauvegarde complète (contenus, réglages, médias, thème personnalisé) dans un fichier ZIP. */
    public static function backup(array $user): never
    {
        if (!Auth::confirmed($user)) {
            Admin::back('security', t('Mot de passe incorrect.'), 'bad');
        }
        if (!class_exists('ZipArchive')) {
            Admin::back('security', t('L’extension zip n’est pas disponible : copie les dossiers data et media par FTP.'), 'bad');
        }
        @set_time_limit(300);
        $file = ECLAIR_ROOT . '/cache/backup-' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Admin::back('security', t('Impossible de créer la sauvegarde.'), 'bad');
        }
        foreach (['data' => ECLAIR_DATA, 'media' => ECLAIR_ROOT . '/media', 'theme' => Theme::dir()] as $prefix => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $path => $info) {
                $rel = $prefix . '/' . str_replace('\\', '/', substr((string) $path, strlen($dir) + 1));
                if ($info->isFile() && !preg_match('~^data/(sessions/|limits/|ai/|reset\.php$|vault\.php$)~', $rel) && !str_ends_with($rel, '.lock')) {
                    $zip->addFile((string) $path, $rel);
                }
            }
        }
        $zip->addFromString('LISEZ-MOI.txt', "Sauvegarde Éclair du " . date('Y-m-d H:i') . "\n\nPour restaurer : remplace les dossiers data/, media/ (et theme/ s'il y est) de ton installation par ceux-ci (par FTP), puis vide le cache depuis l'administration.\n");
        $zip->close();
        Log::security('backup_downloaded');
        $name = slugify((string) Settings::get('name')) ?: 'site';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="sauvegarde-' . $name . '-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        @unlink($file);
        exit;
    }
}
