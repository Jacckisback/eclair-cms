<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Envoi des réponses HTTP. */
final class Http
{
    public static function send(string $body, int $status = 200, array $headers = []): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            foreach ($headers as $line) {
                header($line);
            }
            if ($status !== 304 && $status !== 204) {
                header('Content-Length: ' . strlen($body));
            }
        }
        if (Req::method() !== 'HEAD') {
            echo $body;
        }
        exit;
    }

    public static function redirect(string $to, int $code = 302): never
    {
        // Jamais de redirection vers un autre domaine à partir d'une valeur douteuse : « //hôte », « /\hôte »,
        // antislash ou caractère de contrôle (que les navigateurs suppriment ou transforment en « / »).
        if (preg_match('~^[/\\\\]{2}|[\\\\\x00-\x1f\x7f]~', $to)) {
            $to = path_to('/');
        }
        if (!headers_sent()) {
            header('Location: ' . $to, true, $code);
            header('Cache-Control: no-store');
        }
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        self::send(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $status,
            [
                'Content-Type: application/json; charset=utf-8',
                'Cache-Control: no-store',
                'X-Content-Type-Options: nosniff',
                'X-Robots-Tag: noindex, nofollow',
            ]
        );
    }

    /** Termine la réponse côté visiteur pour continuer une tâche (ping IndexNow…). */
    public static function finish(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
    }
}
