<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** E-mails texte (UTF-8) envoyés par le serveur, depuis no-reply@domaine-du-site. */
final class Mail
{
    public static function send(string $to, string $subject, string $body, string $replyTo = ''): bool
    {
        $to = Sanitize::email($to);
        if ($to === '' || !function_exists('mail')) {
            return false;
        }
        $site = (string) Settings::get('name');
        $host = preg_replace('/^www\./', '', (string) parse_url((string) Settings::get('url'), PHP_URL_HOST)) ?: 'localhost';
        $encode = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $headers = ['From: ' . $encode($site) . ' <no-reply@' . $host . '>'];
        $replyTo = Sanitize::email($replyTo);
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        array_push($headers, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit');
        return @mail($to, $encode('[' . $site . '] ' . $subject), $body, implode("\r\n", $headers));
    }
}
