<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Redirections 301/302, créées automatiquement quand une URL change (aucun lien cassé). */
final class Redirects
{
    public static function all(): array
    {
        $list = Store::read('redirects', []);
        return is_array($list) ? $list : [];
    }

    public static function normalize(string $path): string
    {
        $path = trim($path);
        if (preg_match('~^https?://~i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }
        $path = '/' . ltrim(rawurldecode($path), '/');
        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    public static function find(string $path): ?array
    {
        foreach (self::all() as $r) {
            if ($r['from'] === $path) {
                return $r;
            }
        }
        return null;
    }

    public static function add(string $from, string $to, bool $auto, int $code = 301): void
    {
        $from = self::normalize($from);
        $to = trim($to);
        $to = preg_match('~^https?://~i', $to) ? Sanitize::url($to) : self::normalize($to);
        if ($from === '/' || $to === '' || $from === $to) {
            return;
        }
        Store::locked(function () use ($from, $to, $auto, $code): void {
            $list = [];
            foreach (self::all() as $r) {
                if ($r['from'] === $from) {
                    continue;
                }
                // Évite les chaînes : A→B puis B→C devient A→C.
                if ($r['to'] === $from) {
                    $r['to'] = $to;
                }
                if ($r['from'] !== $r['to']) {
                    $list[] = $r;
                }
            }
            $list[] = ['from' => $from, 'to' => $to, 'code' => $code === 302 ? 302 : 301, 'auto' => $auto, 'hits' => 0, 'created' => time()];
            Store::write('redirects', $list);
        });
    }

    public static function remove(string $from): void
    {
        $from = self::normalize($from);
        if (self::find($from) === null) {
            return;
        }
        Store::locked(function () use ($from): void {
            Store::write('redirects', array_values(array_filter(self::all(), fn ($r) => $r['from'] !== $from)));
        });
    }

    public static function hit(string $from): void
    {
        Store::locked(function () use ($from): void {
            $list = self::all();
            foreach ($list as $i => $r) {
                if ($r['from'] === $from) {
                    $list[$i]['hits'] = (int) $r['hits'] + 1;
                    $list[$i]['last'] = time();
                }
            }
            Store::write('redirects', $list);
        });
    }
}
