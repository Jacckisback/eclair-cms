<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Réglages du site, par onglets. */
final class AdminSettings
{
    public const TABS = ['general', 'appearance', 'seo', 'geo', 'contact', 'advanced'];

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
        ], t('Réglages'));
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
                        'base' => in_array($base, [$s['admin_path'], 'media', 'assets', 'app', 'data', 'cache'], true) ? 'blog' : $base,
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

    /** Accepte le code seul ou la balise <meta> complète copiée depuis Google / Bing. */
    private static function verification(string $value): string
    {
        if (preg_match('~content=["\']([^"\']+)["\']~i', $value, $m)) {
            $value = $m[1];
        }
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $value) ?? '';
    }
}
