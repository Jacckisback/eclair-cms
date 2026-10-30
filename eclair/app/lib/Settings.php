<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Réglages du site (data/site.php), fusionnés avec des valeurs par défaut sûres. */
final class Settings
{
    public const DEFAULTS = [
        'name' => 'Mon site',
        'tagline' => '',
        'description' => '',
        'url' => '',
        'lang' => 'fr',
        'timezone' => 'Europe/Paris',
        'admin_path' => 'admin',
        'home' => '',
        'blog' => [
            'enabled' => true,
            'base' => 'blog',
            'title' => 'Blog',
            'description' => '',
            'per_page' => 9,
        ],
        'search' => true,
        'appearance' => [
            'theme' => 'eclair',
            'header' => 'left',
            'cards' => 'grid',
            'accent' => '#5b4cf0',
            'font_head' => 'system',
            'font_body' => 'system',
            'logo' => '',
            'favicon' => '',
            'dark' => 'auto',
            'footer' => '',
            'credit' => true,
        ],
        'seo' => [
            'title_format' => '{title} · {site}',
            'index' => true,
            'image' => '',
            'twitter' => '',
            'google' => '',
            'bing' => '',
            'entity' => 'Organization',
            'entity_name' => '',
            'phone' => '',
            'email' => '',
            'street' => '',
            'city' => '',
            'zip' => '',
            'region' => '',
            'country' => '',
            'hours' => '',
            'price_range' => '',
        ],
        'social' => [
            'linkedin' => '', 'x' => '', 'facebook' => '', 'instagram' => '',
            'youtube' => '', 'tiktok' => '', 'github' => '', 'other' => '',
        ],
        'geo' => [
            'ai' => 'open',
            'llms' => true,
            'llms_full' => true,
            'markdown' => true,
            'signal' => false,
            'about' => '',
        ],
        'indexnow' => false,
        'contact' => [
            'email' => '',
            'store' => true,
            'thanks' => '',
        ],
        'security' => [
            'https' => false,
            'canonical' => true,
            'hsts' => false,
            'idle' => 240,
            'proxy' => '',
            'verified' => '',
        ],
        'cache' => true,
        'shop' => [
            'enabled' => false,
            'base' => '',
            'title' => '',
            'description' => '',
            'currency' => 'EUR',
            'button' => '',
            'per_page' => 12,
            'shipping' => ['price' => '', 'free_from' => '', 'countries' => '', 'days_min' => 2, 'days_max' => 5],
            'returns' => ['days' => 14, 'free' => false],
            'terms' => '',
            'legal' => '',
            'delivery' => '',
            'thanks' => '',
        ],
        'ai' => [
            'provider' => '',
            'model' => '',
            'models' => ['anthropic' => '', 'openai' => ''],
            'image' => '',
            'budget' => 10,
            'daily' => 60,
            'editors' => true,
            'mcp' => false,
            'profile' => ['about' => '', 'audience' => '', 'tone' => '', 'values' => '', 'rules' => '', 'visual' => ''],
        ],
        'onboarding' => ['done' => false],
        'installed' => 0,
        'version' => '',
    ];

    private static ?array $data = null;

    public static function installed(): bool
    {
        return Store::exists('site');
    }

    public static function all(): array
    {
        if (self::$data === null) {
            $stored = Store::read('site', []);
            self::$data = array_replace_recursive(self::DEFAULTS, is_array($stored) ? $stored : []);
        }
        return self::$data;
    }

    /** Lecture avec notation pointée : Settings::get('seo.index'). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all();
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Fusionne des changements, enregistre et vide le cache du site. */
    public static function save(array $patch): void
    {
        Store::locked(function () use ($patch): void {
            $stored = Store::read('site', []);
            $merged = self::merge(is_array($stored) ? $stored : [], $patch);
            Store::write('site', $merged);
        });
        self::$data = null;
        Cache::purge();
    }

    /**
     * L'adresse enregistrée mène-t-elle vraiment à ce site ? Tant que ce n'est pas prouvé,
     * aucun visiteur n'est redirigé vers elle (une adresse erronée ou piégée ne détourne rien).
     */
    public static function verified(): bool
    {
        $url = (string) self::get('url');
        return $url !== '' && (string) self::get('security.verified') === $url;
    }

    /** Vérifie l'adresse du site par une requête du serveur vers lui-même, et mémorise le résultat. */
    public static function verifyUrl(): bool
    {
        $url = (string) self::get('url');
        $ok = Loopback::check($url);
        if ($ok !== self::verified()) {
            self::save(['security' => ['verified' => $ok ? $url : '']]);
        }
        return $ok;
    }

    /** Fusion récursive où les listes sont remplacées (et non fusionnées index par index). */
    private static function merge(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            if (is_array($v) && !array_is_list($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = self::merge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    public static function forget(): void
    {
        self::$data = null;
    }

    /** Chemins réservés par le routeur (non utilisables comme slug racine). */
    public static function reserved(): array
    {
        $words = Theme::words();
        return array_values(array_filter([
            'app', 'data', 'cache', 'assets', 'media', 'index', 'admin', '_eclair', '__eclair',
            'sitemap', 'robots', 'feed', 'llms', 'llms-full', 'favicon', 'wp-admin', 'wp-login',
            (string) self::get('admin_path'),
            (string) self::get('blog.base'),
            $words['search'],
            // L'adresse de la boutique n'est réservée qu'une fois la boutique configurée : une page existante garde la sienne.
            Shop::enabled() || (string) self::get('shop.base') !== '' ? ltrim(Shop::path(), '/') : '',
        ]));
    }
}
