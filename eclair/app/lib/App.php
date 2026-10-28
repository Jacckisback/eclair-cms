<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Point d'entrée : gestion des erreurs puis aiguillage vers l'installateur, l'admin ou le site. */
final class App
{
    public static function run(): never
    {
        error_reporting(E_ALL);
        ini_set('display_errors', ECLAIR_DEBUG ? '1' : '0');
        set_error_handler([self::class, 'onError']);
        set_exception_handler([self::class, 'onException']);
        header_remove('X-Powered-By');
        mb_internal_encoding('UTF-8');

        $path = Req::path();

        // Vérification de la réécriture d'URL (utilisée par l'installateur).
        if ($path === '/__eclair/ping') {
            Http::send('ok', 200, ['Content-Type: text/plain; charset=utf-8', 'Cache-Control: no-store']);
        }

        if (!Settings::installed()) {
            Installer::handle();
        }

        date_default_timezone_set((string) Settings::get('timezone') ?: 'UTC');
        I18n::setLang((string) Settings::get('lang', 'fr'));

        $admin = '/' . Settings::get('admin_path', 'admin');
        if ($path === $admin || str_starts_with($path, $admin . '/')) {
            Admin::handle(substr($path, strlen($admin)) ?: '/');
        }
        if ($path === '/_eclair/contact') {
            Contact::handle();
        }
        Site::handle($path);
    }

    public static function onError(int $no, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $no)) {
            return false;
        }
        if (ECLAIR_DEBUG && !($no & (E_DEPRECATED | E_USER_DEPRECATED))) {
            throw new \ErrorException($message, 0, $no, $file, $line);
        }
        Log::error(new \ErrorException($message, 0, $no, $file, $line));
        return true;
    }

    public static function onException(\Throwable $e): void
    {
        $id = Log::error($e);
        if (headers_sent()) {
            return;
        }
        http_response_code(500);
        header('Cache-Control: no-store');
        if (Req::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => ECLAIR_DEBUG ? $e->getMessage() : 'Erreur interne (' . $id . ')']);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        $detail = ECLAIR_DEBUG ? '<pre style="white-space:pre-wrap;font-size:13px">' . e((string) $e) . '</pre>' : '';
        echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            . '<meta name="robots" content="noindex"><title>Oups…</title>'
            . '<body style="font:17px/1.6 system-ui,sans-serif;max-width:42rem;margin:12vh auto;padding:0 1.25rem;color:#1e1b4b">'
            . '<p style="font-size:3rem;margin:0">⚡</p><h1 style="margin:.2em 0">Oups, un court-circuit.</h1>'
            . '<p>Une erreur inattendue est survenue. Réessaie dans un instant.</p>'
            . '<p style="color:#64748b;font-size:.9rem">Référence : ' . e($id) . '</p>' . $detail . '</body></html>';
    }
}
