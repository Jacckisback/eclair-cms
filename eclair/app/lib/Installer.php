<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Assistant d'installation : vérifie le serveur, crée le site, le compte et les premiers contenus. */
final class Installer
{
    private const TIMEZONES = ['fr' => 'Europe/Paris', 'es' => 'Europe/Madrid', 'en' => 'Europe/London', 'de' => 'Europe/Berlin', 'it' => 'Europe/Rome', 'pt' => 'Europe/Lisbon'];
    private const WINDOW = 7200; // l'installation doit être faite dans les 2 h qui suivent la première visite

    public static function handle(): never
    {
        $ui = Req::query('lang') ?: Req::input('ui');
        I18n::setLang($ui !== '' ? $ui : I18n::detect());

        if (Req::path() !== '/') {
            Http::redirect(path_to('/') . (Req::query('lang') !== '' ? '?lang=' . rawurlencode(Req::query('lang')) : ''));
        }
        // Un site déposé mais jamais installé ne reste pas indéfiniment ouvert au premier venu.
        if (!self::window()) {
            $html = Theme::partial('../install/locked', ['lang' => I18n::lang()]);
            Http::send($html, 403, array_merge(['Content-Type: text/html; charset=utf-8'], Security::headers('admin')));
        }
        if (Req::isPost()) {
            if (!Security::checkFormToken()) {
                Http::json(['ok' => false, 'errors' => ['_' => t('Session expirée : recharge la page.')]], 400);
            }
            $errors = self::validate();
            if ($errors) {
                Http::json(['ok' => false, 'errors' => $errors], 422);
            }
            try {
                self::install();
            } catch (\Throwable $e) {
                self::rollback();
                Log::error($e);
                Http::json(['ok' => false, 'errors' => ['_' => t("L'installation a échoué : {msg}", ['msg' => $e->getMessage()])]], 500);
            }
            Http::json(['ok' => true, 'admin' => admin_url(), 'site' => path_to('/')]);
        }
        $checks = self::checks();
        $html = Theme::partial('../install/view', [
            'checks' => $checks,
            'blocking' => (bool) array_filter($checks, fn ($c) => $c['level'] === 'error'),
            'lang' => I18n::lang(),
            'url' => Req::origin(),
        ]);
        Http::send($html, 200, array_merge(['Content-Type: text/html; charset=utf-8'], Security::headers('admin')));
    }

    /**
     * Fenêtre d'installation : elle s'ouvre à la première visite (data/install-window.php) et se ferme
     * 2 h plus tard. Pour la rouvrir, il suffit de supprimer ce fichier par FTP (preuve d'accès au serveur).
     */
    private static function window(): bool
    {
        $file = ECLAIR_DATA . '/install-window.php';
        if (!is_file($file)) {
            try {
                Store::ensureDir(ECLAIR_DATA);
                Store::atomic($file, Store::GUARD . time());
            } catch (\Throwable) {
                return true; // dossier data/ non accessible : le diagnostic l'affichera, rien ne peut s'installer
            }
            return true;
        }
        return time() - (int) Store::strip((string) @file_get_contents($file)) < self::WINDOW;
    }

    /** Diagnostic du serveur, présenté de façon rassurante. */
    public static function checks(): array
    {
        $checks = [];
        $add = static function (string $label, string $level, string $detail = '') use (&$checks): void {
            $checks[] = ['label' => $label, 'level' => $level, 'detail' => $detail];
        };
        $add(t('PHP {v}', ['v' => PHP_VERSION]), PHP_VERSION_ID >= 80200 ? 'ok' : 'warn', PHP_VERSION_ID >= 80200 ? '' : t('Fonctionne, mais passe à PHP 8.3 ou plus si ton hébergeur le propose.'));
        foreach (['mbstring' => 'mbstring', 'json' => 'json', 'dom' => 'dom', 'session' => 'session'] as $ext => $name) {
            $add(t('Extension {n}', ['n' => $name]), extension_loaded($ext) ? 'ok' : 'error', extension_loaded($ext) ? '' : t('Indispensable : active-la dans le panneau PHP de ton hébergeur.'));
        }
        $gd = Media::gd();
        $add(t('Traitement des images (GD)'), $gd ? 'ok' : 'warn', $gd ? (function_exists('imagewebp') ? t('WebP disponible : images ultra légères.') : t('Sans WebP : les images resteront en JPG/PNG.')) : t('Sans GD, les images ne seront ni redimensionnées ni converties.'));
        foreach (['data' => ECLAIR_DATA, 'cache' => ECLAIR_ROOT . '/cache', 'media' => ECLAIR_ROOT . '/media'] as $name => $dir) {
            $ok = self::writable($dir);
            $add(t('Dossier « {d} » accessible en écriture', ['d' => $name]), $ok ? 'ok' : 'error', $ok ? '' : t('Donne les droits d’écriture (755) à ce dossier via ton FTP.'));
        }
        $add(t('Connexion sécurisée (HTTPS)'), Req::isHttps() ? 'ok' : 'warn', Req::isHttps() ? '' : t('Active le certificat SSL gratuit (Let’s Encrypt) de ton hébergeur, puis recharge en https://.'));
        $add(t('Chiffrement des mots de passe'), defined('PASSWORD_ARGON2ID') ? 'ok' : 'ok', defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'Bcrypt');
        $add(t('Sauvegardes en un clic (ZIP)'), class_exists('ZipArchive') ? 'ok' : 'warn', class_exists('ZipArchive') ? '' : t('Extension zip absente : la sauvegarde se fera par FTP.'));
        $add(t('Administration en plusieurs langues (zlib)'), function_exists('gzdecode') ? 'ok' : 'warn', function_exists('gzdecode') ? '' : t('Extension zlib absente : l’administration restera en français.'));
        $ai = function_exists('curl_init') && Vault::available();
        $add(t('Assistant IA (cURL et chiffrement)'), $ai ? 'ok' : 'warn', $ai ? '' : t('Extension curl, sodium ou openssl absente : l’assistant IA ne pourra pas être activé.'));
        return $checks;
    }

    private static function writable(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $probe = $dir . '/.write-test-' . bin2hex(random_bytes(3));
        if (@file_put_contents($probe, 'ok') === false) {
            return false;
        }
        @unlink($probe);
        return true;
    }

    private static function validate(): array
    {
        $e = [];
        if (Sanitize::text(Req::input('site_name'), 100) === '') {
            $e['site_name'] = t('Donne un nom à ton site.');
        }
        if (!isset(Content::LANGS[Req::input('site_lang')])) {
            $e['site_lang'] = t('Choisis une langue.');
        }
        if (!in_array(Req::input('site_type'), Starter::TYPES, true)) {
            $e['site_type'] = t('Choisis un type de site.');
        }
        if (Sanitize::text(Req::input('user_name'), 80) === '') {
            $e['user_name'] = t('Indique ton nom.');
        }
        if (Sanitize::email(Req::input('user_email')) === '') {
            $e['user_email'] = t('Adresse e-mail invalide.');
        }
        $issues = Security::passwordIssues(Req::input('user_pass'), [Req::input('user_name'), explode('@', Req::input('user_email'))[0], Req::input('site_name')]);
        if ($issues) {
            $e['user_pass'] = implode(' ', $issues);
        }
        $admin = Req::input('admin_path');
        if (!preg_match('/^[a-z0-9-]{3,40}$/', $admin) || in_array($admin, ['app', 'data', 'cache', 'assets', 'media', 'blog', 'feed', 'sitemap'], true)) {
            $e['admin_path'] = t('3 à 40 caractères : lettres minuscules, chiffres et tirets.');
        }
        return $e;
    }

    private static function install(): void
    {
        @set_time_limit(120);
        foreach (['content', 'revisions', 'sessions', 'limits', 'logs', 'messages'] as $dir) {
            Store::ensureDir(ECLAIR_DATA . '/' . $dir);
        }
        Store::ensureDir(ECLAIR_ROOT . '/cache/pages');
        Store::ensureDir(ECLAIR_ROOT . '/media/site');
        Keys::generate();

        $lang = Req::input('site_lang');
        $type = Req::input('site_type');
        $name = Sanitize::text(Req::input('site_name'), 100);
        $tagline = Sanitize::text(Req::input('site_tagline'), 160);
        $https = Req::isHttps();
        Store::write('site', [
            'name' => $name,
            'tagline' => $tagline,
            'description' => $tagline,
            'url' => Req::origin(),
            'lang' => $lang,
            'timezone' => self::TIMEZONES[$lang] ?? 'UTC',
            'admin_path' => Req::input('admin_path'),
            'seo' => ['index' => Req::bool('index'), 'email' => ''],
            'geo' => ['ai' => in_array(Req::input('ai'), ['open', 'search', 'closed'], true) ? Req::input('ai') : 'open'],
            'security' => ['https' => $https, 'hsts' => false],
            'indexnow' => Req::bool('index') && $https,
            'installed' => time(),
            'version' => ECLAIR_VERSION,
        ]);
        Settings::forget();
        date_default_timezone_set((string) Settings::get('timezone'));

        $user = Users::create([
            'name' => Sanitize::text(Req::input('user_name'), 80),
            'email' => Sanitize::email(Req::input('user_email')),
            'pass' => Security::hashPassword(Req::input('user_pass')),
            'role' => 'admin',
            'lang' => I18n::lang(),
        ]);
        Starter::create($type, $lang, $user, $name);
        Media::icons(null, (string) Settings::get('appearance.accent'));
        @unlink(ECLAIR_DATA . '/install-window.php');
        // Les visiteurs ne seront redirigés vers cette adresse que si elle mène vraiment à ce site.
        Settings::verifyUrl();
        Cache::purge();
        Auth::login($user);
        Log::security('install', ['email' => $user['email']]);
    }

    /** En cas d'échec, on efface ce qui a été créé pour pouvoir recommencer proprement. */
    private static function rollback(): void
    {
        foreach (['site', 'users', 'keys', 'index', 'categories', 'menus', 'search', 'redirects'] as $name) {
            Store::delete($name);
        }
        foreach (Store::names('content') as $name) {
            Store::delete($name);
        }
    }
}
