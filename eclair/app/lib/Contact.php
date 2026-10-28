<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Formulaire de contact sans captcha : champ piège, jeton posé par JavaScript,
 * limitation par adresse IP et filtrage des liens. Les messages sont aussi archivés.
 */
final class Contact
{
    public static function token(): string
    {
        return substr(Keys::sign('contact-form'), 0, 24);
    }

    public static function handle(): never
    {
        $lang = (string) Settings::get('lang');
        if (!Req::isPost()) {
            Http::send('Method Not Allowed', 405, ['Allow: POST', 'Content-Type: text/plain; charset=utf-8']);
        }
        if (!Security::sameOrigin()) {
            self::reply(false, 403, $lang);
        }
        $ip = Req::ip();
        if (RateLimit::blocked('contact:' . $ip)) {
            self::reply(false, 429, $lang);
        }
        // Un robot remplit le champ piège : on fait semblant d'accepter, sans rien garder.
        if (Req::input('website') !== '') {
            self::reply(true, 200, $lang);
        }
        $name = Sanitize::text(Req::input('name'), 100);
        $email = Sanitize::email(Req::input('email'));
        $message = Sanitize::multiline(Req::input('message'), 5000);
        if ($name === '' || $email === '' || mb_strlen($message) < 5) {
            self::reply(false, 422, $lang);
        }
        RateLimit::hit('contact:' . $ip, 5, 600, 1800);
        $page = Content::index()[preg_replace('/[^a-z0-9]/', '', Req::input('page')) ?? ''] ?? null;
        $suspect = !hash_equals(self::token(), Req::input('k')) || preg_match_all('~https?://|www\.~i', $message) > 3;
        $record = [
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'page' => $page['title'] ?? '',
            'ip' => $ip,
            'suspect' => $suspect,
        ];
        if (Settings::get('contact.store') || $suspect) {
            Messages::add($record);
        }
        if (!$suspect) {
            self::mail($record);
        }
        self::reply(true, 200, $lang);
    }

    private static function reply(bool $ok, int $status, string $lang): never
    {
        $custom = trim((string) Settings::get('contact.thanks'));
        $text = $ok ? ($custom !== '' ? $custom : Theme::t('form_thanks', $lang)) : Theme::t('form_error', $lang);
        if (Req::wantsJson()) {
            Http::json(['ok' => $ok, 'message' => $text], $status);
        }
        Site::message($ok ? '✓' : '⚠', $text, $status);
    }

    private static function mail(array $m): bool
    {
        if (!function_exists('mail')) {
            return false;
        }
        $to = Sanitize::email(Settings::get('contact.email'));
        if ($to === '') {
            $admins = Users::admins();
            $to = (string) (reset($admins)['email'] ?? '');
        }
        if ($to === '') {
            return false;
        }
        $site = (string) Settings::get('name');
        $host = preg_replace('/^www\./', '', (string) parse_url((string) Settings::get('url'), PHP_URL_HOST)) ?: 'localhost';
        $encode = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $subject = $encode('[' . $site . '] ' . t('Nouveau message de {name}', ['name' => $m['name']]));
        $body = t('Nom') . ' : ' . $m['name'] . "\n" . t('E-mail') . ' : ' . $m['email'] . "\n"
            . ($m['page'] !== '' ? t('Page') . ' : ' . $m['page'] . "\n" : '')
            . "\n" . $m['message'] . "\n\n— " . t('Message envoyé depuis le formulaire de contact de {site}.', ['site' => $site]) . "\n";
        $headers = [
            'From: ' . $encode($site) . ' <no-reply@' . $host . '>',
            'Reply-To: ' . $m['email'],
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
