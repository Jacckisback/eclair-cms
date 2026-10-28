<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * IndexNow : prévient instantanément Bing, Yandex, Seznam, Naver… (et donc Copilot
 * et la recherche de ChatGPT, qui s'appuie sur l'index de Bing) à chaque publication.
 */
final class IndexNow
{
    public static function enabled(): bool
    {
        return (bool) Settings::get('indexnow') && (bool) Settings::get('seo.index') && str_starts_with((string) Settings::get('url'), 'http');
    }

    public static function key(): string
    {
        return Keys::get('indexnow');
    }

    /** @param string[] $paths chemins publics à signaler */
    public static function ping(array $paths): bool
    {
        if (!self::enabled() || !$paths) {
            return false;
        }
        $host = (string) parse_url((string) Settings::get('url'), PHP_URL_HOST);
        $body = json_encode([
            'host' => $host,
            'key' => self::key(),
            'keyLocation' => absolute_url('/' . self::key() . '.txt'),
            'urlList' => array_values(array_unique(array_map('absolute_url', $paths))),
        ], JSON_UNESCAPED_SLASHES);
        $ok = false;
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init('https://api.indexnow.org/indexnow');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                ]);
                curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $ok = $code >= 200 && $code < 300;
            } elseif (ini_get('allow_url_fopen')) {
                $ctx = stream_context_create(['http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json; charset=utf-8\r\n",
                    'content' => $body,
                    'timeout' => 5,
                    'ignore_errors' => true,
                ]]);
                @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
                $status = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers()[0] ?? '') : ($http_response_header[0] ?? '');
                $ok = preg_match('~\s2\d\d\s~', $status . ' ') === 1;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        Store::write('indexnow', ['t' => time(), 'ok' => $ok, 'n' => count($paths)]);
        return $ok;
    }
}
