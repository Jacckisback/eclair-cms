<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
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
        // Mot de passe et cookie de session ne circulent jamais en clair quand le site est en HTTPS.
        if (Security::httpsOnly() && !Req::isHttps()) {
            Http::redirect('https://' . Req::host() . admin_url(ltrim(self::$sub, '/')));
        }
        // Aucune session n'est créée avant la connexion (pas de fichier pour les robots qui sondent l'adresse).
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
        Installer::seal();
        if ($method === 'POST' && !Security::checkCsrf()) {
            if (str_starts_with($route, '/api/') || Req::wantsJson()) {
                Http::json(['ok' => false, 'error' => t('Jeton de sécurité expiré : recharge la page.')], 419);
            }
            Session::flash('bad', t('Jeton de sécurité expiré : réessaie.'));
            Http::redirect(admin_url(ltrim($route, '/')));
        }
        // Mot de passe provisoire (créé ou réinitialisé par un administrateur) : à remplacer avant tout le reste.
        if (!empty($user['must_change']) && !in_array($route, ['/profile', '/logout'], true)) {
            if (str_starts_with($route, '/api/') || Req::wantsJson()) {
                Http::json(['ok' => false, 'error' => t('Choisis d’abord ton mot de passe personnel (page Mon profil).')], 403);
            }
            Http::redirect(admin_url('profile#mot-de-passe'));
        }

        $routes = [
            'GET /' => [self::class, 'dashboard', 'content'],
            'POST /logout' => [self::class, 'logout', ''],
            'POST /onboarding' => [self::class, 'onboardingDone', 'content'],
            'GET /content' => [AdminContent::class, 'list', 'content'],
            'GET /new/(page|post|product)' => [AdminContent::class, 'create', 'content'],
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
            'GET /ai-visits' => [AdminSite::class, 'aiVisits', 'content'],
            'POST /redirects' => [AdminSite::class, 'saveRedirect', 'redirects'],
            'GET /settings' => [AdminSettings::class, 'page', 'settings'],
            'POST /settings' => [AdminSettings::class, 'save', 'settings'],
            'GET /appearance/preview' => [AdminSettings::class, 'preview', 'settings'],
            'GET /users' => [AdminUsers::class, 'list', 'users'],
            'POST /users' => [AdminUsers::class, 'create', 'users'],
            'GET /users/([a-z0-9]+)' => [AdminUsers::class, 'edit', 'users'],
            'POST /users/([a-z0-9]+)' => [AdminUsers::class, 'update', 'users'],
            'GET /profile' => [AdminUsers::class, 'profile', ''],
            'POST /profile' => [AdminUsers::class, 'saveProfile', ''],
            'GET /security' => [AdminSecurity::class, 'page', 'security'],
            'POST /security' => [AdminSecurity::class, 'action', 'security'],
            'POST /backup' => [AdminSecurity::class, 'backup', 'security'],
            'GET /update' => [AdminUpdate::class, 'page', 'security'],
            'POST /update' => [AdminUpdate::class, 'action', 'security'],
            'POST /api/update/check' => [AdminUpdate::class, 'check', 'security'],
            'POST /drive' => [AdminDrive::class, 'action', 'security'],
            'POST /api/drive/poll' => [AdminDrive::class, 'poll', 'security'],
            'POST /api/drive/tick' => [AdminDrive::class, 'tick', 'security'],
            'POST /api/ai/chat' => [AdminAi::class, 'chat', 'content'],
            'GET /api/ai/chats' => [AdminAi::class, 'chats', 'content'],
            'GET /api/ai/chat/([a-z0-9]+)' => [AdminAi::class, 'load', 'content'],
            'POST /api/ai/forget' => [AdminAi::class, 'forget', 'content'],
            'POST /api/ai/assist' => [AdminAi::class, 'assist', 'content'],
            'POST /api/ai/compose' => [AdminAi::class, 'compose', 'content'],
            'POST /api/ai/apply' => [AdminAi::class, 'apply', 'content'],
            'GET /ai/preview/([a-z0-9]+)' => [AdminAi::class, 'preview', 'content'],
            'POST /api/ai/dismiss' => [AdminAi::class, 'dismiss', 'content'],
            'POST /api/ai/undo' => [AdminAi::class, 'undo', 'content'],
            'POST /api/ai/publish' => [AdminAi::class, 'publish', 'content'],
            'POST /api/ai/test' => [AdminAi::class, 'test', 'settings'],
            'POST /api/ai/profile' => [AdminAi::class, 'profile', 'settings'],
            'POST /api/ai/directions' => [AdminAi::class, 'directions', 'settings'],
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
        // Archive : vues compressées (gzip, lues par zlib) depuis la 1.8 ; une ancienne vue .php restée d'une mise à jour est ignorée.
        $file = ECLAIR_ROOT . '/app/admin/views/' . $name . '.php';
        if (is_file($file . '.gz')) {
            if (!extension_loaded('zlib')) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
                exit("L'extension PHP zlib est indispensable à l'administration d'Éclair : active-la dans le panneau PHP de ton hébergeur.\n");
            }
            $file = 'compress.zlib://' . $file . '.gz';
        }
        ob_start();
        try {
            (static function (string $__file, array $v): void {
                include $__file;
            })($file, $v);
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

    /**
     * Données passées au JavaScript (sans script en ligne : bloc JSON non exécutable).
     * « me » : compte connecté (vide sans session), auquel l'éditeur lie ses brouillons de secours dans le navigateur.
     */
    public static function jsData(array $extra = []): string
    {
        $user = Auth::user();
        return '<script type="application/json" id="eclair-data">' . json_script($extra + [
            'csrf' => $user ? Security::csrfToken() : '',
            'me' => $user ? (string) $user['id'] : '',
            // Site à un seul compte : les brouillons de secours d'avant la 1.10.3 (sans compte) d'un contenu existant lui reviennent.
            'solo' => $user !== null && count(Users::all()) === 1,
            'admin' => admin_url(),
            'base' => Req::base(),
            'i18n' => AdminStrings::js(),
            'ai' => $user ? AdminAi::client($user) : null,
        ]) . '</script>';
    }

    /* ───────────────────────────── Connexion ───────────────────────────── */

    private static function login(): never
    {
        $error = '';
        $email = '';
        if (Req::isPost()) {
            $email = mb_substr(Req::input('email'), 0, 254);
            if (!Security::checkFormToken()) {
                $error = t('Session expirée : réessaie.');
            } else {
                try {
                    $result = Auth::attempt(Req::input('email'), Req::input('password'));
                } catch (\RuntimeException $e) {
                    // Données impossibles à enregistrer (droits du dossier, verrou) : la cause s'affiche, pas une page muette.
                    Log::error($e);
                    [$result, $error] = ['error', $e->getMessage()];
                }
                if ($result === 'ok') {
                    self::redirectNext();
                }
                if ($result === 'totp') {
                    $_SESSION['next'] = Req::input('next');
                    Http::redirect(admin_url('login/2fa'));
                }
                $error = match ($result) {
                    'locked' => t('Trop de tentatives. Par sécurité, réessaie dans quelques minutes.'),
                    'busy' => t('Le serveur est très sollicité : réessaie dans un instant.'),
                    'error' => $error,
                    default => t('E-mail ou mot de passe incorrect.'),
                };
            }
        }
        self::view('login', [
            'layout' => 'auth',
            'error' => $error,
            'email' => $email,
            'next' => Req::query('next') ?: Req::input('next'),
            'bye' => Req::query('bye') === '1' && !Req::isPost(),
        ], t('Connexion'), $error !== '' ? 401 : 200);
    }

    private static function loginTotp(): never
    {
        if (!Session::resume() || empty($_SESSION['pending'])) {
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
                if ($result === 'expired' || $result === 'locked') {
                    Session::flash('bad', $result === 'locked'
                        ? t('Trop de codes incorrects : la double authentification de ce compte est bloquée 15 minutes.')
                        : t('Délai dépassé : recommence la connexion.'));
                    Http::redirect(admin_url('login'));
                }
                $error = t('Code incorrect. Vérifie l’heure de ton téléphone ou utilise un code de secours.');
            }
        }
        self::view('login-2fa', ['layout' => 'auth', 'error' => $error], t('Double authentification'));
    }

    private static function redirectNext(string $next = ''): never
    {
        $next = $next !== '' ? $next : Req::input('next');
        Http::redirect(preg_match('~^/[a-z0-9/_-]*$~', $next) ? admin_url(ltrim($next, '/')) : admin_url());
    }

    public static function logout(array $user): never
    {
        Auth::logout();
        Http::redirect(admin_url('login?bye=1'));
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
                'label' => t('Ajouter ton logo et ta couleur'), 'hint' => t('Ton site te ressemble dès la première visite.'), 'url' => admin_url('settings?tab=appearance')],
            ['done' => !$home || (int) $home['updated'] > $installed + 120, 'label' => t('Personnaliser la page d’accueil'), 'hint' => t('C’est la première page que voient tes visiteurs.'),
                'url' => $home ? admin_url('edit/' . $home['id']) : admin_url('settings')],
            ['done' => (bool) array_filter($index, fn ($e) => $e['type'] === 'post' && (int) $e['created'] > $installed + 120),
                'label' => t('Écrire ton premier article'), 'hint' => t('Un article utile attire des visiteurs depuis Google et les IA.'), 'url' => admin_url('new/post')],
            ['done' => mb_strlen((string) ($user['bio'] ?? '')) >= 40, 'label' => t('Compléter ton profil d’auteur (bio, photo)'), 'hint' => t('Un auteur présenté inspire confiance à tes lecteurs.'), 'url' => admin_url('profile')],
            ['done' => !empty($user['totp']['on']), 'label' => t('Activer la double authentification'), 'hint' => t('Ton compte reste protégé, même si ton mot de passe fuite.'), 'url' => admin_url('profile#securite')],
            ['done' => !$legal || (int) $legal['updated'] > $installed + 120, 'label' => t('Compléter les mentions légales'), 'hint' => t('Indique qui édite ton site et qui l’héberge.'),
                'url' => $legal ? admin_url('edit/' . $legal['id']) : ''],
            ['done' => Settings::get('seo.google') !== '', 'label' => t('Relier ton site à Google Search Console'), 'hint' => t('Suis tes clics et tes positions dans Google.'), 'url' => admin_url('settings?tab=seo')],
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
            // Réservé aux administrateurs : version de PHP, administrateurs sans double authentification… (rien qu'un rédacteur puisse régler).
            'health' => $user['role'] === 'admin' ? self::health($user) : [],
            // Robots IA des 30 derniers jours (rien si la mesure est coupée).
            'ai' => Settings::get('geo.ai_log') ? AiVisits::report(30) : null,
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
        self::back('', t('Bravo, ton site est prêt à briller !'));
    }
}
