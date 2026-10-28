<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Administration : routes, contrôle d'accès et actions. */
final class Admin
{
    private static string $sub = '/';

    public static function handle(string $sub): never
    {
        self::$sub = rtrim($sub, '/') ?: '/';
        foreach (Security::headers('admin') as $h) {
            header($h);
        }
        Session::start();
        $method = Req::method();
        $route = self::$sub;

        if ($route === '/login') {
            Auth::user() ? Http::redirect(admin_url()) : self::login();
        }
        if ($route === '/login/2fa') {
            self::loginTotp();
        }
        $user = Auth::user();
        if (!$user) {
            if (str_starts_with($route, '/api/') || Req::wantsJson()) {
                Http::json(['ok' => false, 'error' => t('Session expirée : reconnecte-toi dans un autre onglet, puis réessaie.'), 'login' => true], 401);
            }
            Http::redirect(admin_url('login') . ($route !== '/' ? '?next=' . rawurlencode($route) : ''));
        }
        if ($method === 'POST' && !Security::checkCsrf()) {
            if (str_starts_with($route, '/api/') || Req::wantsJson()) {
                Http::json(['ok' => false, 'error' => t('Jeton de sécurité expiré : recharge la page.')], 419);
            }
            Session::flash('bad', t('Jeton de sécurité expiré : réessaie.'));
            Http::redirect(admin_url(ltrim($route, '/')));
        }

        $routes = [
            'GET /' => [self::class, 'dashboard', 'content'],
            'POST /logout' => [self::class, 'logout', ''],
            'POST /onboarding' => [self::class, 'onboardingDone', 'content'],
            'GET /content' => [AdminContent::class, 'list', 'content'],
            'GET /new/(page|post)' => [AdminContent::class, 'create', 'content'],
            'GET /edit/([a-z0-9]+)' => [AdminContent::class, 'edit', 'content'],
            'GET /preview/([a-z0-9]+)' => [AdminContent::class, 'previewSaved', 'content'],
            'POST /preview' => [AdminContent::class, 'preview', 'content'],
            'POST /api/save' => [AdminContent::class, 'save', 'content'],
            'POST /api/analyze' => [AdminContent::class, 'analyze', 'content'],
            'POST /api/delete' => [AdminContent::class, 'delete', 'content'],
            'POST /api/duplicate' => [AdminContent::class, 'duplicate', 'content'],
            'GET /api/revisions/([a-z0-9]+)' => [AdminContent::class, 'revisions', 'content'],
            'GET /api/revision/([a-z0-9]+)/([a-z0-9-]+)' => [AdminContent::class, 'revision', 'content'],
            'GET /api/links' => [AdminContent::class, 'links', 'content'],
            'POST /api/ping' => [AdminContent::class, 'ping', 'content'],
            'GET /media' => [AdminMedia::class, 'page', 'media'],
            'GET /api/media' => [AdminMedia::class, 'list', 'media'],
            'POST /api/media/upload' => [AdminMedia::class, 'upload', 'media'],
            'POST /api/media/update' => [AdminMedia::class, 'update', 'media'],
            'POST /api/media/delete' => [AdminMedia::class, 'delete', 'media'],
            'GET /menus' => [AdminSite::class, 'menus', 'menus'],
            'POST /menus' => [AdminSite::class, 'saveMenus', 'menus'],
            'GET /categories' => [AdminSite::class, 'categories', 'categories'],
            'POST /categories' => [AdminSite::class, 'saveCategory', 'categories'],
            'GET /messages' => [AdminSite::class, 'messages', 'messages'],
            'POST /messages' => [AdminSite::class, 'messageAction', 'messages'],
            'GET /redirects' => [AdminSite::class, 'redirects', 'redirects'],
            'POST /redirects' => [AdminSite::class, 'saveRedirect', 'redirects'],
            'GET /settings' => [AdminSettings::class, 'page', 'settings'],
            'POST /settings' => [AdminSettings::class, 'save', 'settings'],
            'GET /users' => [AdminUsers::class, 'list', 'users'],
            'POST /users' => [AdminUsers::class, 'create', 'users'],
            'GET /users/([a-z0-9]+)' => [AdminUsers::class, 'edit', 'users'],
            'POST /users/([a-z0-9]+)' => [AdminUsers::class, 'update', 'users'],
            'GET /profile' => [AdminUsers::class, 'profile', ''],
            'POST /profile' => [AdminUsers::class, 'saveProfile', ''],
            'GET /security' => [AdminSecurity::class, 'page', 'security'],
            'POST /security' => [AdminSecurity::class, 'action', 'security'],
            'POST /backup' => [AdminSecurity::class, 'backup', 'security'],
            'POST /cache' => [AdminSecurity::class, 'clearCache', 'content'],
        ];
        foreach ($routes as $pattern => [$class, $fn, $area]) {
            [$m, $regex] = explode(' ', $pattern, 2);
            if (($m === $method || ($m === 'GET' && $method === 'HEAD')) && preg_match('~^' . $regex . '$~', $route, $args)) {
                if ($area !== '' && !Auth::can($area)) {
                    self::forbidden();
                }
                array_shift($args);
                $class::$fn($user, ...$args);
                exit;
            }
        }
        self::view('error', ['title' => t('Page introuvable'), 'text' => t('Cette page de l’administration n’existe pas.')], t('Page introuvable'), 404);
    }

    /* ─────────────────────────────── Rendu ─────────────────────────────── */

    public static function view(string $name, array $v = [], string $title = '', int $status = 200): never
    {
        $v['title'] ??= $title;
        $v['user'] = Auth::user();
        $v['flash'] = Session::pullFlash();
        $v['section'] = self::section();
        $content = self::partial($name, $v);
        $html = self::partial($v['layout'] ?? 'layout', $v + ['content' => $content, 'page_title' => $title]);
        Http::send($html, $status, ['Content-Type: text/html; charset=utf-8']);
    }

    public static function partial(string $name, array $v): string
    {
        ob_start();
        try {
            (static function (string $__file, array $v): void {
                include $__file;
            })(ECLAIR_ROOT . '/app/admin/views/' . $name . '.php', $v);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Section active du menu latéral. */
    private static function section(): string
    {
        $first = explode('/', trim(self::$sub, '/'))[0];
        return match ($first) {
            '' => 'dashboard',
            'new', 'edit', 'content' => 'content',
            'users' => 'users',
            default => $first,
        };
    }

    public static function back(string $path, string $message = '', string $type = 'ok'): never
    {
        if ($message !== '') {
            Session::flash($type, $message);
        }
        Http::redirect(admin_url($path));
    }

    public static function forbidden(): never
    {
        if (str_starts_with(self::$sub, '/api/') || Req::wantsJson()) {
            Http::json(['ok' => false, 'error' => t('Action réservée aux administrateurs.')], 403);
        }
        self::view('error', ['title' => t('Accès réservé'), 'text' => t('Cette section est réservée aux administrateurs du site.')], t('Accès réservé'), 403);
    }

    /** Données passées au JavaScript (sans script en ligne : bloc JSON non exécutable). */
    public static function jsData(array $extra = []): string
    {
        return '<script type="application/json" id="eclair-data">' . json_script($extra + [
            'csrf' => Security::csrfToken(),
            'admin' => admin_url(),
            'base' => Req::base(),
            'i18n' => AdminStrings::js(),
        ]) . '</script>';
    }

    /* ───────────────────────────── Connexion ───────────────────────────── */

    private static function login(): never
    {
        self::ftpReset();
        $error = '';
        $email = '';
        if (Req::isPost()) {
            $email = Req::input('email');
            if (!Security::checkCsrf()) {
                $error = t('Session expirée : réessaie.');
            } else {
                $result = Auth::attempt($email, Req::input('password'));
                if ($result === 'ok') {
                    self::redirectNext();
                }
                if ($result === 'totp') {
                    $_SESSION['next'] = Req::input('next');
                    Http::redirect(admin_url('login/2fa'));
                }
                $error = $result === 'locked'
                    ? t('Trop de tentatives. Par sécurité, réessaie dans quelques minutes.')
                    : t('E-mail ou mot de passe incorrect.');
            }
        }
        self::view('login', ['layout' => 'auth', 'error' => $error, 'email' => $email, 'next' => Req::query('next') ?: Req::input('next')], t('Connexion'), $error !== '' ? 401 : 200);
    }

    private static function loginTotp(): never
    {
        if (empty($_SESSION['pending'])) {
            Http::redirect(admin_url('login'));
        }
        $error = '';
        if (Req::isPost()) {
            if (!Security::checkCsrf()) {
                $error = t('Session expirée : réessaie.');
            } else {
                $result = Auth::attemptTotp(Req::input('code'));
                if ($result === 'ok') {
                    $next = (string) ($_SESSION['next'] ?? '');
                    unset($_SESSION['next']);
                    self::redirectNext($next);
                }
                if ($result === 'expired') {
                    Session::flash('bad', t('Délai dépassé : recommence la connexion.'));
                    Http::redirect(admin_url('login'));
                }
                $error = t('Code incorrect. Vérifie l’heure de ton téléphone ou utilise un code de secours.');
            }
        }
        self::view('login-2fa', ['layout' => 'auth', 'error' => $error], t('Double authentification'));
    }

    /**
     * Secours par FTP (preuve d'accès au serveur) : un fichier data/reset.php contenant
     * « <?php exit; ?> », puis l'e-mail et le nouveau mot de passe sur deux lignes, remplace
     * le mot de passe, désactive la double authentification du compte, puis il est supprimé.
     */
    private static function ftpReset(): void
    {
        $file = ECLAIR_DATA . '/reset.php';
        if (!is_file($file)) {
            return;
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', Store::strip((string) file_get_contents($file))) ?: [])));
        @unlink($file);
        $user = Users::findByEmail($lines[0] ?? '');
        $password = $lines[1] ?? '';
        if (!$user || Security::passwordIssues($password)) {
            Session::flash('bad', t('Réinitialisation par FTP refusée : e-mail inconnu ou mot de passe trop faible (10 caractères minimum).'));
            return;
        }
        $user['pass'] = Security::hashPassword($password);
        $user['totp'] = ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []];
        Auth::revokeAll($user);
        RateLimit::clear('login-ip:' . Req::ip());
        RateLimit::clear('login-user:' . mb_strtolower((string) $user['email']));
        Log::security('ftp_reset', ['email' => $user['email']]);
        Session::flash('ok', t('Mot de passe réinitialisé par FTP. Connecte-toi, puis réactive la double authentification.'));
    }

    private static function redirectNext(string $next = ''): never
    {
        $next = $next !== '' ? $next : Req::input('next');
        Http::redirect(preg_match('~^/[a-z0-9/_-]*$~', $next) ? admin_url(ltrim($next, '/')) : admin_url());
    }

    public static function logout(array $user): never
    {
        Auth::logout();
        Session::start();
        Session::flash('ok', t('À bientôt ! Tu es bien déconnecté.'));
        Http::redirect(admin_url('login'));
    }

    /* ─────────────────────────── Tableau de bord ─────────────────────────── */

    public static function dashboard(array $user): never
    {
        $index = Content::index();
        $installed = (int) Settings::get('installed');
        $published = array_filter($index, fn ($e) => $e['status'] === 'published');
        $scores = array_column(array_column($published, 'score'), 'seo');
        $geo = array_column(array_column($published, 'score'), 'geo');
        $home = $index[(string) Settings::get('home')] ?? null;
        $legal = null;
        foreach ($index as $e) {
            if (in_array($e['slug'], ['mentions-legales', 'aviso-legal', 'legal-notice'], true)) {
                $legal = $e;
            }
        }
        $todo = [
            ['done' => true, 'label' => t('Installer Éclair'), 'url' => ''],
            ['done' => Settings::get('appearance.logo') !== '' || Settings::get('appearance.accent') !== Settings::DEFAULTS['appearance']['accent'],
                'label' => t('Ajouter ton logo et ta couleur'), 'url' => admin_url('settings?tab=appearance')],
            ['done' => !$home || (int) $home['updated'] > $installed + 120, 'label' => t('Personnaliser la page d’accueil'),
                'url' => $home ? admin_url('edit/' . $home['id']) : admin_url('settings')],
            ['done' => (bool) array_filter($index, fn ($e) => $e['type'] === 'post' && (int) $e['created'] > $installed + 120),
                'label' => t('Écrire ton premier article'), 'url' => admin_url('new/post')],
            ['done' => mb_strlen((string) ($user['bio'] ?? '')) >= 40, 'label' => t('Compléter ton profil d’auteur (bio, photo)'), 'url' => admin_url('profile')],
            ['done' => !empty($user['totp']['on']), 'label' => t('Activer la double authentification'), 'url' => admin_url('profile#securite')],
            ['done' => !$legal || (int) $legal['updated'] > $installed + 120, 'label' => t('Compléter les mentions légales'),
                'url' => $legal ? admin_url('edit/' . $legal['id']) : ''],
            ['done' => Settings::get('seo.google') !== '', 'label' => t('Relier ton site à Google Search Console'), 'url' => admin_url('settings?tab=seo')],
        ];
        $recent = $index;
        uasort($recent, fn ($a, $b) => $b['updated'] <=> $a['updated']);
        $improve = array_filter($published, fn ($e) => !$e['noindex']);
        uasort($improve, fn ($a, $b) => ($a['score']['seo'] + $a['score']['geo']) <=> ($b['score']['seo'] + $b['score']['geo']));
        self::view('dashboard', [
            'todo' => $todo,
            'onboarding' => !Settings::get('onboarding.done'),
            'stats' => [
                'posts' => count(array_filter($published, fn ($e) => $e['type'] === 'post')),
                'pages' => count(array_filter($published, fn ($e) => $e['type'] === 'page')),
                'drafts' => count(array_filter($index, fn ($e) => $e['status'] === 'draft')),
                'messages' => Messages::unread(),
                'seo' => $scores ? (int) round(array_sum($scores) / count($scores)) : 0,
                'geo' => $geo ? (int) round(array_sum($geo) / count($geo)) : 0,
            ],
            'recent' => array_slice($recent, 0, 6, true),
            'improve' => array_slice(array_filter($improve, fn ($e) => $e['score']['seo'] < 80 || $e['score']['geo'] < 80), 0, 5, true),
            'health' => self::health($user),
        ], t('Tableau de bord'));
    }

    /** Points de contrôle rapides de la santé du site. */
    public static function health(array $user): array
    {
        $https = str_starts_with((string) Settings::get('url'), 'https://');
        $admins = Users::admins();
        $no2fa = count(array_filter($admins, fn ($u) => empty($u['totp']['on'])));
        $last = Store::read('indexnow');
        return [
            ['ok' => $https, 'label' => $https ? t('Site servi en HTTPS') : t('Passe ton site en HTTPS (certificat gratuit chez ton hébergeur)')],
            ['ok' => (bool) Settings::get('seo.index'), 'label' => Settings::get('seo.index') ? t('Visible par les moteurs de recherche') : t('Site masqué aux moteurs (mode construction)')],
            ['ok' => $no2fa === 0, 'label' => $no2fa === 0 ? t('Double authentification active pour tous les admins') : t('{n} administrateur(s) sans double authentification', ['n' => $no2fa])],
            ['ok' => PHP_VERSION_ID >= 80200, 'label' => t('PHP {v}', ['v' => PHP_VERSION]) . (PHP_VERSION_ID >= 80200 ? '' : ' — ' . t('pense à passer à PHP 8.3'))],
            ['ok' => Cache::enabled(), 'label' => Cache::enabled() ? t('Cache statique actif ({n} pages prêtes)', ['n' => Cache::count()]) : t('Cache désactivé : le site est plus lent')],
            ['ok' => IndexNow::enabled(), 'label' => IndexNow::enabled()
                ? t('IndexNow actif') . (is_array($last) ? ' — ' . t('dernier envoi {when}', ['when' => ago((int) $last['t'])]) : '')
                : t('IndexNow désactivé (indexation Bing/Copilot moins rapide)')],
        ];
    }

    public static function onboardingDone(array $user): never
    {
        Settings::save(['onboarding' => ['done' => true]]);
        self::back('', t('Bravo, ton site est prêt à briller ⚡'));
    }
}
