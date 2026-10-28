<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** En-têtes de sécurité, jetons CSRF et règles de mot de passe. */
final class Security
{
    private const COMMON = [
        '123456789', '1234567890', '12345678910', 'azertyuiop', 'qwertyuiop', 'motdepasse', 'motdepasse1',
        'password', 'password1', 'password123', 'passw0rd', 'iloveyou', 'admin123', 'administrator',
        'bonjour123', 'soleil123', 'contrasena', 'contraseña', '1q2w3e4r5t', 'qwerty123', 'azerty123',
        '0123456789', '9876543210', '1111111111', 'aaaaaaaaaa', 'abcdefghij', 'loveyou123', 'welcome123',
    ];

    /** En-têtes communs + politique adaptée au contexte (site public, admin, texte brut). */
    public static function headers(string $mode, string $styleHash = ''): array
    {
        $https = Req::isHttps();
        $h = [
            'X-Content-Type-Options: nosniff',
            'Referrer-Policy: strict-origin-when-cross-origin',
            'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
            'Cross-Origin-Opener-Policy: same-origin',
        ];
        if ($https && Settings::installed() && Settings::get('security.hsts')) {
            $h[] = 'Strict-Transport-Security: max-age=31536000';
        }
        if ($mode === 'admin') {
            $h[] = "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob: https:; "
                . "connect-src 'self'; font-src 'self'; frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com; "
                . "frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'";
            $h[] = 'X-Frame-Options: DENY';
            $h[] = 'X-Robots-Tag: noindex, nofollow';
            $h[] = 'Cache-Control: no-store, private';
        } elseif ($mode === 'site') {
            $style = $styleHash !== '' ? " 'sha256-" . $styleHash . "'" : '';
            $h[] = "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'" . $style . '; '
                . "img-src 'self' data: https:; media-src 'self' https:; "
                . 'frame-src https://www.youtube-nocookie.com https://player.vimeo.com; '
                . "connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
                . ($https ? '; upgrade-insecure-requests' : '');
            $h[] = 'X-Frame-Options: SAMEORIGIN';
        } else {
            $h[] = "Content-Security-Policy: default-src 'none'; frame-ancestors 'none'";
        }
        return $h;
    }

    public static function csrfToken(): string
    {
        Session::start();
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    /** Jeton valide ET requête provenant bien de ce site. */
    public static function checkCsrf(): bool
    {
        Session::start();
        $token = Req::input('_csrf');
        if ($token === '') {
            $token = Req::header('X-CSRF-Token');
        }
        $expected = $_SESSION['csrf'] ?? '';
        return is_string($expected) && $expected !== '' && hash_equals($expected, $token) && self::sameOrigin();
    }

    /** Défense en profondeur : refuse les requêtes envoyées depuis un autre site. */
    public static function sameOrigin(): bool
    {
        $site = Req::header('Sec-Fetch-Site');
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }
        $origin = Req::header('Origin');
        if ($origin === '') {
            return true;
        }
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $port = parse_url($origin, PHP_URL_PORT);
        return $host !== '' && ($port ? $host . ':' . $port : $host) === preg_replace('/:(80|443)$/', '', Req::host());
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, self::algo());
    }

    public static function algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** Liste des problèmes d'un mot de passe (vide = accepté). */
    public static function passwordIssues(string $password, array $context = []): array
    {
        $issues = [];
        if (mb_strlen($password) < 10) {
            $issues[] = t('Utilise au moins 10 caractères.');
        }
        if (in_array(mb_strtolower($password), self::COMMON, true)) {
            $issues[] = t('Ce mot de passe est beaucoup trop courant.');
        }
        if (count(array_unique(mb_str_split($password))) < 5) {
            $issues[] = t('Varie davantage les caractères.');
        }
        foreach ($context as $word) {
            $word = mb_strtolower(trim((string) $word));
            if (mb_strlen($word) >= 4 && str_contains(mb_strtolower($password), $word)) {
                $issues[] = t("Évite d'y mettre ton nom, ton e-mail ou le nom du site.");
                break;
            }
        }
        return $issues;
    }

    /** Mot de passe aléatoire lisible (comptes créés par un administrateur). */
    public static function randomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            if ($i % 4 === 3 && $i < 15) {
                $out .= '-';
            }
        }
        return $out;
    }
}
