<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Médiathèque (page et API d'envoi). */
final class AdminMedia
{
    /** Représentation d'un média pour le JavaScript. */
    public static function export(array $m): array
    {
        return [
            'id' => $m['id'],
            'thumb' => path_to(Media::thumb($m)),
            'url' => path_to(Media::url($m)),
            'abs' => absolute_url(Media::url($m)),
            'w' => (int) $m['w'],
            'h' => (int) $m['h'],
            'alt' => (string) $m['alt'],
            'name' => (string) ($m['name'] ?? basename($m['file'])),
            'bytes' => (int) ($m['bytes'] ?? 0),
            'size' => human_size((int) ($m['bytes'] ?? 0)),
            'webp' => count($m['webp'] ?? []),
            'date' => fmt_date((int) $m['created'], I18n::lang()),
        ];
    }

    public static function page(array $user): never
    {
        Admin::view('media', ['js' => ['media' => array_map([self::class, 'export'], array_values(Media::all()))]], t('Médias'));
    }

    public static function list(array $user): never
    {
        Http::json(['ok' => true, 'media' => array_map([self::class, 'export'], array_values(Media::all()))]);
    }

    public static function upload(array $user): never
    {
        if (empty($_FILES['file'])) {
            $max = ini_get('post_max_size');
            Http::json(['ok' => false, 'error' => t('Fichier trop lourd pour ton hébergement (max {max}).', ['max' => $max])], 413);
        }
        try {
            $m = Media::upload($_FILES['file'], $user['id']);
            $alt = Sanitize::text(Req::input('alt'), 250);
            if ($alt !== '') {
                $m = Media::update($m['id'], ['alt' => $alt]) ?? $m;
            }
            Http::json(['ok' => true, 'media' => self::export($m)]);
        } catch (\RuntimeException $e) {
            Http::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public static function update(array $user): never
    {
        $data = Req::json();
        $m = Media::update((string) ($data['id'] ?? ''), ['alt' => (string) ($data['alt'] ?? '')]);
        Http::json($m ? ['ok' => true, 'media' => self::export($m)] : ['ok' => false], $m ? 200 : 404);
    }

    public static function delete(array $user): never
    {
        $data = Req::json();
        $id = (string) ($data['id'] ?? '');
        $usage = Media::usage($id);
        if ($usage && empty($data['force'])) {
            Http::json(['ok' => false, 'used' => $usage]);
        }
        Media::delete($id);
        Http::json(['ok' => true]);
    }
}
