<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Réglages du site, par onglets. */
final class AdminSettings
{
    public const TABS = ['general', 'appearance', 'shop', 'seo', 'geo', 'ai', 'contact', 'advanced'];

    public static function page(array $user): never
    {
        $tab = in_array(Req::query('tab'), self::TABS, true) ? Req::query('tab') : 'general';
        $pages = array_filter(Content::index(), fn ($e) => $e['type'] === 'page');
        $media = [];
        foreach (['appearance.logo', 'appearance.favicon', 'seo.image'] as $key) {
            $m = Media::get((string) Settings::get($key));
            if ($m) {
                $media[$m['id']] = AdminMedia::export($m);
            }
        }
        Admin::view('settings', [
            'tab' => $tab,
            's' => Settings::all(),
            'pages' => $pages,
            'media' => $media,
            'indexnow' => IndexNow::key(),
            'ai' => $tab === 'ai' ? self::aiState() : [],
            'previews' => $tab === 'appearance' ? self::previewPages() : [],
        ], t('Réglages'));
    }

    /** Pages proposées dans l'aperçu en direct de l'apparence. */
    private static function previewPages(): array
    {
        $pages = ['/' => t('Accueil')];
        $posts = array_values(Content::published('post'));
        if (Settings::get('blog.enabled') && Settings::get('home') !== '') {
            $pages[Content::blogPath()] = t('Blog');
        }
        if ($posts) {
            $pages[Content::path($posts[0])] = t('Un article');
        }
        if (Shop::enabled()) {
            $pages[Shop::path()] = t('La boutique');
            $products = array_values(Content::published('product'));
            if ($products) {
                $pages[Content::path($products[0])] = t('Un produit');
            }
        }
        return $pages;
    }

    /**
     * Aperçu en direct de l'apparence : une page du site rendue avec l'apparence choisie dans le formulaire,
     * sans rien enregistrer ni mettre en cache. Seules des valeurs connues sont acceptées.
     */
    public static function preview(array $user): never
    {
        $q = static fn (string $k): string => (string) Req::query($k);
        $look = [];
        if (isset(Theme::THEMES[$q('theme')])) {
            $look['theme'] = $q('theme');
        }
        foreach (['header' => ['left', 'center'], 'cards' => ['grid', 'list'], 'dark' => ['auto', 'light']] as $k => $allowed) {
            if (in_array($q($k), $allowed, true)) {
                $look[$k] = $q($k);
            }
        }
        if (preg_match('/^#[0-9a-f]{6}$/i', $q('accent'))) {
            $look['accent'] = strtolower($q('accent'));
        }
        foreach (['font_head', 'font_body'] as $k) {
            if (isset(Theme::FONTS[$q($k)])) {
                $look[$k] = $q($k);
            }
        }
        Theme::preview($look);
        $path = $q('path');
        Site::preview(preg_match('~^/[a-z0-9/_-]*$~', $path) ? $path : '/');
    }

    /** État de l'assistant pour l'onglet IA : indices des clés (jamais les clés), dépenses du mois. */
    private static function aiState(): array
    {
        $hints = [];
        foreach (['ai.anthropic', 'ai.openai', 'image.openai', 'image.bfl', 'image.ideogram'] as $name) {
            $hints[$name] = Vault::hint($name);
        }
        return ['hints' => $hints, 'usage' => Ai::usage(), 'crypto' => Vault::available(), 'mcp' => Mcp::grants(), 'mcp_url' => Mcp::url()];
    }

    public static function save(array $user): never
    {
        $tab = in_array(Req::input('tab'), self::TABS, true) ? Req::input('tab') : 'general';
        $s = Settings::all();
        $text = static fn (string $k, int $max = 200): string => Sanitize::text(Req::input($k), $max);
        $mediaId = static fn (string $k): string => Media::get(Req::input($k)) ? Req::input($k) : '';
        $patch = [];
        $redirect = 'settings?tab=' . $tab;

        switch ($tab) {
            case 'general':
                $url = rtrim(Sanitize::url(Req::input('url')), '/');
                if (!preg_match('~^https?://[^/\s]+~', $url)) {
                    Admin::back($redirect, t('L’adresse du site doit commencer par https:// (ou http://).'), 'bad');
                }
                $base = slugify(Req::input('blog_base')) ?: 'blog';
                $home = Req::input('home');
                $patch = [
                    'name' => $text('name', 100) ?: $s['name'],
                    'tagline' => $text('tagline', 160),
                    'description' => $text('description', 320),
                    'url' => $url,
                    'lang' => isset(Content::LANGS[Req::input('lang')]) ? Req::input('lang') : $s['lang'],
                    'timezone' => in_array(Req::input('timezone'), \DateTimeZone::listIdentifiers(), true) ? Req::input('timezone') : $s['timezone'],
                    'home' => $home === '' || (Content::index()[$home]['type'] ?? '') === 'page' ? $home : '',
                    'search' => Req::bool('search'),
                    'blog' => [
                        'enabled' => Req::bool('blog_enabled'),
                        'base' => in_array($base, [$s['admin_path'], 'media', 'assets', 'app', 'data', 'cache', 'products', ltrim(Shop::path(), '/')], true) ? 'blog' : $base,
                        'title' => $text('blog_title', 80) ?: 'Blog',
                        'description' => Sanitize::multiline(Req::input('blog_description'), 500),
                        'per_page' => max(1, min(48, (int) Req::input('per_page'))),
                    ],
                ];
                if ($patch['blog']['base'] !== $s['blog']['base'] && $s['blog']['enabled']) {
                    foreach (Content::published('post') as $post) {
                        Redirects::add('/' . $s['blog']['base'] . '/' . $post['slug'], '/' . $patch['blog']['base'] . '/' . $post['slug'], true);
                    }
                }
                break;

            case 'appearance':
                $patch = ['appearance' => [
                    'theme' => isset(Theme::THEMES[Req::input('theme')]) ? Req::input('theme') : 'eclair',
                    'header' => Req::input('header') === 'center' ? 'center' : 'left',
                    'cards' => Req::input('cards') === 'list' ? 'list' : 'grid',
                    'logo' => $mediaId('logo'),
                    'favicon' => $mediaId('favicon'),
                    'accent' => Sanitize::color(Req::input('accent'), $s['appearance']['accent']),
                    'font_head' => isset(Theme::FONTS[Req::input('font_head')]) ? Req::input('font_head') : 'system',
                    'font_body' => isset(Theme::FONTS[Req::input('font_body')]) ? Req::input('font_body') : 'system',
                    'dark' => Req::input('dark') === 'light' ? 'light' : 'auto',
                    'footer' => Sanitize::multiline(Req::input('footer'), 500),
                    'credit' => Req::bool('credit'),
                ]];
                $icon = Media::get($patch['appearance']['favicon']);
                if ($patch['appearance']['favicon'] !== $s['appearance']['favicon'] || (!$icon && $patch['appearance']['accent'] !== $s['appearance']['accent'])) {
                    Media::icons($icon, $patch['appearance']['accent']);
                }
                $custom = Req::input('custom_css');
                if (strlen($custom) > Theme::CUSTOM_MAX) {
                    Admin::back($redirect, t('CSS personnalisé trop long : {n} Ko maximum.', ['n' => intdiv(Theme::CUSTOM_MAX, 1024)]), 'bad');
                }
                Theme::saveCustom($custom);
                break;

            case 'seo':
                $social = [];
                foreach (array_keys(Settings::DEFAULTS['social']) as $k) {
                    $u = Sanitize::url(Req::input('social_' . $k));
                    $social[$k] = preg_match('~^https?://~', $u) ? $u : '';
                }
                $patch = [
                    'seo' => [
                        'title_format' => str_contains(Req::input('title_format'), '{title}') ? $text('title_format', 80) : '{title} · {site}',
                        'index' => Req::bool('index'),
                        'image' => $mediaId('image'),
                        'twitter' => ltrim($text('twitter', 40), '@'),
                        'google' => self::verification(Req::input('google')),
                        'bing' => self::verification(Req::input('bing')),
                        'entity' => in_array(Req::input('entity'), ['Organization', 'Person', 'LocalBusiness'], true) ? Req::input('entity') : 'Organization',
                        'entity_name' => $text('entity_name', 120),
                        'email' => Sanitize::email(Req::input('email')),
                        'phone' => $text('phone', 40),
                        'street' => $text('street', 150),
                        'city' => $text('city', 80),
                        'zip' => $text('zip', 20),
                        'region' => $text('region', 80),
                        'country' => $text('country', 60),
                        'hours' => $text('hours', 150),
                        'price_range' => $text('price_range', 20),
                    ],
                    'social' => $social,
                    'indexnow' => Req::bool('indexnow'),
                ];
                break;

            case 'geo':
                $patch = ['geo' => [
                    'ai' => in_array(Req::input('ai'), ['open', 'search', 'closed'], true) ? Req::input('ai') : 'open',
                    'llms' => Req::bool('llms'),
                    'llms_full' => Req::bool('llms_full'),
                    'markdown' => Req::bool('markdown'),
                    'signal' => Req::bool('signal'),
                    'about' => Sanitize::multiline(Req::input('about'), 3000),
                ]];
                break;

            case 'ai':
                $provider = isset(Ai::PROVIDERS[Req::input('provider')]) ? Req::input('provider') : '';
                try {
                    foreach (['ai.anthropic', 'ai.openai', 'image.openai', 'image.bfl', 'image.ideogram'] as $name) {
                        $field = str_replace('.', '_', $name);
                        $key = (string) preg_replace('/\s+/', '', Req::input('key_' . $field));
                        if (Req::bool('remove_' . $field)) {
                            Vault::set($name, '');
                        } elseif ($key !== '') {
                            Vault::set($name, mb_substr($key, 0, 500));
                        }
                    }
                } catch (\RuntimeException $e) {
                    Admin::back($redirect, $e->getMessage(), 'bad');
                }
                $models = [];
                foreach (array_keys(Ai::PROVIDERS) as $p) {
                    $models[$p] = isset(Ai::MODELS[$p][Req::input('model_' . $p)]) ? Req::input('model_' . $p) : '';
                }
                $profile = [];
                foreach (array_keys(Settings::DEFAULTS['ai']['profile']) as $k) {
                    $profile[$k] = Sanitize::multiline(Req::input('profile_' . $k), 2000);
                }
                $patch = ['ai' => [
                    'provider' => $provider,
                    'model' => $models[$provider] ?? '',
                    'models' => $models,
                    'image' => isset(Ai::IMAGES[Req::input('image')]) ? Req::input('image') : '',
                    'budget' => max(0, min(100000, round((float) str_replace(',', '.', Req::input('budget')), 2))),
                    'daily' => max(0, min(100000, (int) Req::input('daily'))),
                    'editors' => Req::bool('editors'),
                    'mcp' => Req::bool('mcp'),
                    'profile' => $profile,
                ]];
                // Connecteur MCP : couper une application, ou toutes quand il est désactivé.
                $revoke = preg_replace('/[^a-z0-9]/', '', Req::input('mcp_revoke')) ?? '';
                if ($s['ai']['mcp'] && !$patch['ai']['mcp']) {
                    Mcp::revoke();
                } elseif ($revoke !== '') {
                    Mcp::revoke($revoke);
                }
                if ($provider !== '' && !Vault::has('ai.' . $provider)) {
                    Session::flash('warn', t('Ajoute la clé d’API de {name} pour activer l’assistant.', ['name' => Ai::PROVIDERS[$provider]]));
                }
                if ($patch['ai']['image'] !== '' && Ai::imageKey($patch['ai']['image']) === '') {
                    Session::flash('warn', t('Ajoute la clé d’API de {name} pour créer des images.', ['name' => Ai::IMAGES[$patch['ai']['image']][0]]));
                }
                break;

            case 'shop':
                $patch = self::shop($user, $s, $redirect);
                break;

            case 'contact':
                $patch = ['contact' => [
                    'email' => Sanitize::email(Req::input('email')),
                    'store' => Req::bool('store'),
                    'thanks' => $text('thanks', 300),
                ]];
                break;

            case 'advanced':
                if (!Auth::confirmed($user)) {
                    Admin::back($redirect, t('Mot de passe incorrect.'), 'bad');
                }
                $admin = strtolower(trim(Req::input('admin_path')));
                if (!preg_match('/^[a-z0-9-]{3,40}$/', $admin) || in_array($admin, ['app', 'data', 'cache', 'assets', 'media', $s['blog']['base']], true)) {
                    Admin::back($redirect, t('Adresse d’administration invalide : 3 à 40 caractères (lettres, chiffres, tirets).'), 'bad');
                }
                $patch = [
                    'admin_path' => $admin,
                    'cache' => Req::bool('cache'),
                    'security' => [
                        'https' => Req::bool('https'),
                        'canonical' => Req::bool('canonical'),
                        'hsts' => Req::bool('hsts'),
                        'idle' => max(15, min(1440, (int) Req::input('idle'))),
                        'proxy' => in_array(Req::input('proxy'), ['', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'], true) ? Req::input('proxy') : '',
                    ],
                ];
                if ($patch['security']['https'] && !Req::isHttps()) {
                    Admin::back($redirect, t('Active d’abord HTTPS chez ton hébergeur et connecte-toi en https:// avant de forcer HTTPS.'), 'bad');
                }
                // Forcer HTTPS fait aussi passer l'adresse officielle en https://.
                if ($patch['security']['https'] && str_starts_with((string) $s['url'], 'http://')) {
                    $patch['url'] = 'https://' . substr((string) $s['url'], 7);
                }
                break;
        }
        Settings::save($patch);
        Log::security('settings_saved', ['tab' => $tab]);
        if (isset($patch['url']) && $patch['url'] !== $s['url'] && !Settings::verifyUrl()) {
            Session::flash('warn', t('Je n’ai pas pu vérifier que {url} mène bien à ce site : en attendant, personne n’est redirigé vers cette adresse. Vérifie-la depuis la page Sécurité.', ['url' => $patch['url']]));
        }
        if ($tab === 'advanced' && $patch['admin_path'] !== $s['admin_path']) {
            Session::flash('ok', t('Nouvelle adresse de l’administration enregistrée : pense à mettre à jour tes favoris.'));
            Http::redirect(Req::base() . '/' . $patch['admin_path'] . '/settings?tab=advanced');
        }
        Admin::back($redirect, t('Réglages enregistrés. Le site est déjà à jour ⚡'));
    }

    /**
     * Onglet Boutique. Elle ne s'ouvre qu'avec des mentions légales et des CGV publiées ; si son adresse change,
     * le catalogue, les fiches et les catégories de produits sont redirigés (301).
     */
    private static function shop(array $user, array $s, string $redirect): array
    {
        $page = static fn (string $k): string => (Content::index()[Req::input($k)]['type'] ?? '') === 'page' ? Req::input($k) : '';
        $text = static fn (string $k, int $max = 200): string => Sanitize::text(Req::input($k), $max);
        $old = ltrim(Shop::path(), '/');
        $base = str_replace('/', '-', slugify(Req::input('shop_base'))) ?: Theme::words()['shop'];
        $taken = [$s['admin_path'], 'media', 'assets', 'app', 'data', 'cache', 'feed', $s['blog']['base'], Theme::words()['search']];
        foreach (Content::index() as $e) {
            if ($e['type'] === 'page') {
                $taken[] = $e['slug'];
            }
        }
        $enabled = Req::bool('shop_enabled');
        // Vérifiée aussi à l'ouverture : l'adresse par défaut (« boutique ») peut être celle d'une page existante.
        if (($base !== $old || $enabled) && in_array($base, $taken, true)) {
            Admin::back($redirect, t('Cette adresse est déjà utilisée : choisis-en une autre.'), 'bad');
        }
        $legal = $page('legal');
        $terms = $page('terms');
        if ($enabled && ($missing = Shop::missing($legal, $terms))) {
            $enabled = false;
            Session::flash('warn', t('La boutique reste fermée : publie d’abord ces pages, puis choisis-les ci-dessous : {pages}.', ['pages' => implode(', ', $missing)]));
        }
        $min = max(0, min(60, (int) Req::input('days_min')));
        $patch = ['shop' => [
            'enabled' => $enabled,
            'base' => $base,
            'title' => $text('shop_title', 80),
            'description' => Sanitize::multiline(Req::input('shop_description'), 500),
            'currency' => isset(Shop::CURRENCIES[Req::input('currency')]) ? Req::input('currency') : 'EUR',
            'button' => $text('button', 40),
            'per_page' => max(1, min(48, (int) Req::input('shop_per_page'))),
            'shipping' => [
                'price' => Shop::amount(Req::input('shipping_price')),
                'free_from' => Shop::amount(Req::input('free_from')),
                'countries' => implode(', ', Shop::parseCountries(Req::input('countries'))),
                'days_min' => $min,
                'days_max' => max($min, min(90, (int) Req::input('days_max'))),
            ],
            'returns' => ['days' => max(0, min(365, (int) Req::input('returns_days'))), 'free' => Req::bool('returns_free')],
            'legal' => $legal,
            'terms' => $terms,
            'delivery' => $page('delivery'),
            'thanks' => $page('thanks'),
        ]];
        if ($base !== $old && $s['shop']['enabled']) {
            foreach (array_merge([['slug' => '']], Content::published('product'), Categories::of('product')) as $x) {
                Redirects::add(rtrim('/' . $old . '/' . $x['slug'], '/'), rtrim('/' . $base . '/' . $x['slug'], '/'), true);
            }
        }
        // Page de remerciement (après le paiement, Stripe peut y rediriger le client) : créée à la demande, hors index.
        if (Req::input('action') === 'thanks' && $patch['shop']['thanks'] === '') {
            $lang = (string) $s['lang'];
            $thanks = Content::save([
                'type' => 'page',
                'status' => 'published',
                'title' => Theme::t('thanks_title', $lang),
                'lang' => $lang,
                'blocks' => [
                    ['type' => 'paragraph', 'text' => Theme::t('thanks_text', $lang)],
                    ['type' => 'button', 'text' => Theme::t('all_products', $lang), 'url' => '/' . $base, 'style' => 'secondary', 'align' => 'left'],
                ],
                'seo' => ['noindex' => true],
            ], $user);
            $patch['shop']['thanks'] = $thanks['id'];
        }
        return $patch;
    }

    /** Accepte le code seul ou la balise <meta> complète copiée depuis Google / Bing. */
    private static function verification(string $value): string
    {
        if (preg_match('~content=["\']([^"\']+)["\']~i', $value, $m)) {
            $value = $m[1];
        }
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $value) ?? '';
    }
}
