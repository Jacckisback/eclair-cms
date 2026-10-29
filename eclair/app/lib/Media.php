<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Médiathèque : chaque image envoyée est vérifiée, ré-encodée (ce qui supprime toute
 * charge cachée et les données EXIF), redimensionnée et déclinée en WebP responsive.
 */
final class Media
{
    public const WIDTHS = [480, 800, 1200, 1600];
    private const MAX = 2000;
    private const MAX_BYTES = 20 * 1048576;
    private const MAX_PIXELS = 40_000_000;

    public static function dir(): string
    {
        return ECLAIR_ROOT . '/media';
    }

    public static function gd(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
    }

    public static function all(): array
    {
        $all = Store::read('media', []);
        $all = is_array($all) ? $all : [];
        uasort($all, fn ($a, $b) => $b['created'] <=> $a['created']);
        return $all;
    }

    public static function get(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        $all = Store::read('media', []);
        return is_array($all) && isset($all[$id]) ? $all[$id] : null;
    }

    /** Chemin public du fichier principal (sans le dossier d'installation). */
    public static function url(array $m): string
    {
        return '/media/' . $m['file'] . '.' . $m['ext'];
    }

    public static function webpUrl(array $m, int $width): string
    {
        return $m['ext'] === 'webp' && $width === (int) $m['w'] ? self::url($m) : '/media/' . $m['file'] . '-' . $width . '.webp';
    }

    /** Version la plus légère adaptée à une vignette (≈ 480 px). */
    public static function thumb(array $m): string
    {
        foreach ($m['webp'] ?? [] as $w) {
            if ($w >= 400) {
                return self::webpUrl($m, (int) $w);
            }
        }
        return self::url($m);
    }

    /** Balise <picture> responsive (WebP + repli), dimensions explicites (zéro décalage de mise en page). */
    public static function picture(array $m, string $alt, string $sizes = '100vw', bool $eager = false): string
    {
        $attrs = ' alt="' . e($alt) . '" width="' . (int) $m['w'] . '" height="' . (int) $m['h'] . '"'
            . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"';
        $webp = $m['webp'] ?? [];
        if (!$webp) {
            return '<img src="' . e(path_to(self::url($m))) . '"' . $attrs . '>';
        }
        $srcset = implode(', ', array_map(fn ($w) => path_to(self::webpUrl($m, (int) $w)) . ' ' . (int) $w . 'w', $webp));
        if ($m['ext'] === 'webp') {
            return '<img src="' . e(path_to(self::url($m))) . '" srcset="' . e($srcset) . '" sizes="' . e($sizes) . '"' . $attrs . '>';
        }
        return '<picture><source type="image/webp" srcset="' . e($srcset) . '" sizes="' . e($sizes) . '">'
            . '<img src="' . e(path_to(self::url($m))) . '"' . $attrs . '></picture>';
    }

    /* ─────────────────────────────── Envoi ─────────────────────────────── */

    public static function upload(array $file, string $userId): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('Fichier trop lourd pour ton hébergement (max {max}).', ['max' => ini_get('upload_max_filesize')]),
                UPLOAD_ERR_NO_FILE => t('Aucun fichier reçu.'),
                default => t("L'envoi a échoué, réessaie."),
            });
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) && !(defined('ECLAIR_TESTING') && is_file($tmp))) {
            throw new \RuntimeException(t('Envoi invalide.'));
        }
        return self::ingest($tmp, (string) ($file['name'] ?? 'image'), $userId);
    }

    /** Image reçue d'un service (génération par IA) : même contrôle et même ré-encodage qu'un envoi. */
    public static function import(string $bytes, string $name, string $userId): array
    {
        Store::ensureDir(ECLAIR_ROOT . '/cache');
        $tmp = ECLAIR_ROOT . '/cache/import-' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($tmp, $bytes) !== strlen($bytes)) {
            @unlink($tmp);
            throw new \RuntimeException(t("Impossible d'enregistrer l'image : vérifie les droits du dossier media."));
        }
        try {
            return self::ingest($tmp, $name, $userId);
        } finally {
            @unlink($tmp);
        }
    }

    private static function ingest(string $tmp, string $originalName, string $userId): array
    {
        if (filesize($tmp) > self::MAX_BYTES) {
            throw new \RuntimeException(t('Image trop lourde (20 Mo maximum).'));
        }
        $info = @getimagesize($tmp);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) {
            throw new \RuntimeException(t('Format non pris en charge : JPG, PNG, WebP ou GIF uniquement.'));
        }
        [$w, $h, $type] = $info;
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS) {
            throw new \RuntimeException(t('Dimensions d’image invalides ou trop grandes (40 mégapixels maximum).'));
        }
        $ext = $types[$type];
        $folder = date('Y/m');
        Store::ensureDir(self::dir() . '/' . $folder);
        // Pas de liste des fichiers si l'hébergeur affiche le contenu des dossiers.
        foreach ([dirname(self::dir() . '/' . $folder), self::dir() . '/' . $folder] as $dir) {
            if (!is_file($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '');
            }
        }
        $base = substr(slugify(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image', 0, 60);
        $name = $base;
        $prefix = self::dir() . '/' . $folder . '/';
        for ($n = 2; glob($prefix . $name . '.*') || glob($prefix . $name . '-*'); $n++) {
            $name = $base . '-' . $n;
        }
        $record = [
            'id' => random_id(),
            'file' => $folder . '/' . $name,
            'ext' => $ext,
            'w' => $w,
            'h' => $h,
            'webp' => [],
            'alt' => '',
            'name' => mb_substr(basename($originalName), 0, 120),
            'created' => time(),
            'user' => $userId,
        ];
        $target = self::dir() . '/' . $record['file'];
        $animated = $type === IMAGETYPE_GIF && self::animated($tmp);
        if (self::gd() && !$animated) {
            $record = self::process($tmp, $type, $target, $record);
        } elseif (@file_put_contents($target . '.' . $ext, self::raw($tmp, $type)) === false) {
            throw new \RuntimeException(t("Impossible d'enregistrer l'image : vérifie les droits du dossier media."));
        }
        $record['bytes'] = (int) @filesize($target . '.' . $record['ext']);
        Store::locked(function () use ($record): void {
            $all = Store::read('media', []);
            $all = is_array($all) ? $all : [];
            $all[$record['id']] = $record;
            Store::write('media', $all);
        });
        return $record;
    }

    private static function process(string $tmp, int $type, string $target, array $record): array
    {
        @set_time_limit(120);
        self::memory((int) $record['w'], (int) $record['h']);
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG => @imagecreatefrompng($tmp),
            IMAGETYPE_GIF => @imagecreatefromgif($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default => false,
        };
        if (!$src) {
            throw new \RuntimeException(t('Image illisible ou corrompue.'));
        }
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        if ($type === IMAGETYPE_JPEG) {
            $src = self::orient($src, $tmp);
        }
        if (imagesx($src) > self::MAX) {
            $src = self::resize($src, self::MAX);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $ext = match ($type) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => function_exists('imagewebp') ? 'webp' : 'png',
            default => 'png',
        };
        imagealphablending($src, false);
        imagesavealpha($src, true);
        if ($ext === 'jpg') {
            imageinterlace($src, true);
        }
        $ok = match ($ext) {
            'jpg' => imagejpeg($src, $target . '.jpg', 82),
            'webp' => imagewebp($src, $target . '.webp', 82),
            default => imagepng($src, $target . '.png', 9),
        };
        if (!$ok) {
            throw new \RuntimeException(t("Impossible d'enregistrer l'image : vérifie les droits du dossier media."));
        }
        $record['ext'] = $ext;
        $record['w'] = $w;
        $record['h'] = $h;
        if (function_exists('imagewebp')) {
            foreach (self::WIDTHS as $width) {
                if ($width < $w - 80) {
                    $variant = self::resize($src, $width);
                    imagewebp($variant, $target . '-' . $width . '.webp', 80);
                    $record['webp'][] = $width;
                }
            }
            if ($ext !== 'webp') {
                imagewebp($src, $target . '-' . $w . '.webp', 80);
            }
            $record['webp'][] = $w;
        }
        return $record;
    }

    private static function resize(\GdImage $src, int $width, ?int $height = null): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $height ??= max(1, (int) round($h * $width / $w));
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);
        return $dst;
    }

    /** Recadrage centré aux dimensions exactes (image de partage, icônes). */
    private static function cover(\GdImage $src, int $width, int $height): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = max($width / $w, $height / $h);
        $cw = (int) round($width / $scale);
        $ch = (int) round($height / $scale);
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), $width, $height, $cw, $ch);
        return $dst;
    }

    private static function orient(\GdImage $img, string $file): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($file);
        $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        if (in_array($o, [2, 4, 5, 7], true)) {
            imageflip($img, $o === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($o) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        return $rotated ?: $img;
    }

    /**
     * Image conservée telle quelle (GIF animé, ou serveur sans GD) : un GIF est réécrit sans ses
     * commentaires ni extensions, et tout fichier contenant du code (PHP, HTML, script) est refusé.
     */
    private static function raw(string $file, int $type): string
    {
        $data = (string) file_get_contents($file);
        if ($type === IMAGETYPE_GIF) {
            $data = self::cleanGif($data) ?? throw new \RuntimeException(t('Ce GIF est invalide ou endommagé.'));
        }
        if (preg_match('~<\?php|<\?=|<script|<html|<iframe|<svg~i', $data)) {
            throw new \RuntimeException(t('Image refusée : elle contient du code.'));
        }
        return $data;
    }

    /** Réécrit un GIF bloc par bloc : images, minutage et boucle d'animation seulement. */
    private static function cleanGif(string $d): ?string
    {
        $len = strlen($d);
        if ($len < 13 || !in_array(substr($d, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            return null;
        }
        $table = static fn (int $flags): int => ($flags & 0x80) ? 3 * (2 << ($flags & 7)) : 0;
        // Fin d'une suite de sous-blocs (taille, données… jusqu'au bloc de taille 0).
        $chain = static function (int $p) use ($d, $len): ?int {
            while ($p < $len) {
                $n = ord($d[$p]);
                $p += 1 + $n;
                if ($n === 0) {
                    return $p;
                }
            }
            return null;
        };
        $pos = 13 + $table(ord($d[10]));
        $out = substr($d, 0, $pos);
        while ($pos < $len) {
            $byte = ord($d[$pos]);
            if ($byte === 0x3B) {
                return strlen($out) === $pos ? null : $out . "\x3B";
            }
            if ($byte === 0x2C && $pos + 10 <= $len) {
                $end = $chain($pos + 10 + $table(ord($d[$pos + 9])) + 1);
                if ($end === null || $end > $len) {
                    return null;
                }
                $out .= substr($d, $pos, $end - $pos);
                $pos = $end;
                continue;
            }
            if ($byte === 0x21 && $pos + 2 < $len) {
                $label = ord($d[$pos + 1]);
                $end = $chain($pos + 2);
                if ($end === null || $end > $len) {
                    return null;
                }
                if ($label === 0xF9 && $end - $pos === 8) {
                    $out .= substr($d, $pos, 8); // minutage de l'animation (taille fixe)
                } elseif ($label === 0xFF && substr($d, $pos + 2, 12) === "\x0BNETSCAPE2.0" && substr($d, $pos + 14, 2) === "\x03\x01") {
                    $out .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01" . substr($d, $pos + 16, 2) . "\x00"; // boucle, reconstruite
                }
                $pos = $end;
                continue;
            }
            return null;
        }
        return null;
    }

    private static function animated(string $file): bool
    {
        $data = (string) @file_get_contents($file, false, null, 0, 1048576);
        return preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $data) > 1;
    }

    private static function memory(int $w, int $h): void
    {
        $need = (int) ($w * $h * 6) + 24 * 1048576;
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit > 0 && memory_get_usage() + $need > $limit) {
            // La mémoire n'est jamais montée au-delà de 320 Mo.
            $wanted = (int) ceil((memory_get_usage() + $need) / 1048576 + 16);
            if ($wanted > 320) {
                throw new \RuntimeException(t('Image trop grande pour la mémoire de ton hébergement : réduis-la sous 4000 pixels de large.'));
            }
            @ini_set('memory_limit', $wanted . 'M');
            $limit = self::bytes((string) ini_get('memory_limit'));
            if ($limit > 0 && memory_get_usage() + $need > $limit) {
                throw new \RuntimeException(t('Image trop grande pour la mémoire de ton hébergement : réduis-la sous 4000 pixels de large.'));
            }
        }
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $n = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /* ─────────────────────────── Dérivés à la demande ─────────────────────────── */

    /** Image de partage 1200×630 (Open Graph), générée une seule fois. */
    public static function og(array $m): array
    {
        $path = '/media/' . $m['file'] . '-og.jpg';
        if (is_file(ECLAIR_ROOT . $path)) {
            return ['url' => $path, 'w' => 1200, 'h' => 630];
        }
        if (!self::gd() || $m['w'] < 600 || $m['ext'] === 'gif') {
            return ['url' => self::url($m), 'w' => (int) $m['w'], 'h' => (int) $m['h']];
        }
        try {
            $src = self::load($m);
            $og = self::cover($src, 1200, 630);
            $flat = imagecreatetruecolor(1200, 630);
            imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $og, 0, 0, 0, 0, 1200, 630);
            imageinterlace($flat, true);
            imagejpeg($flat, ECLAIR_ROOT . $path, 85);
            return ['url' => $path, 'w' => 1200, 'h' => 630];
        } catch (\Throwable $e) {
            Log::error($e);
            return ['url' => self::url($m), 'w' => (int) $m['w'], 'h' => (int) $m['h']];
        }
    }

    /** Icônes du site (favicon, écran d'accueil mobile) depuis une image ou un éclair aux couleurs du site. */
    public static function icons(?array $m, string $accent): void
    {
        if (!self::gd() || !function_exists('imagepng')) {
            return;
        }
        Store::ensureDir(self::dir() . '/site');
        try {
            $src = $m ? self::load($m) : self::bolt($accent);
            foreach ([32, 180, 192, 512] as $size) {
                imagepng(self::cover($src, $size, $size), self::dir() . '/site/icon-' . $size . '.png', 9);
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    private static function bolt(string $accent): \GdImage
    {
        $img = imagecreatetruecolor(512, 512);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, (int) imagecolorallocatealpha($img, 0, 0, 0, 127));
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x') ?: [91, 76, 240];
        $bg = (int) imagecolorallocate($img, (int) $r, (int) $g, (int) $b);
        imagefilledellipse($img, 112, 112, 224, 224, $bg);
        imagefilledellipse($img, 400, 112, 224, 224, $bg);
        imagefilledellipse($img, 112, 400, 224, 224, $bg);
        imagefilledellipse($img, 400, 400, 224, 224, $bg);
        imagefilledrectangle($img, 112, 0, 400, 512, $bg);
        imagefilledrectangle($img, 0, 112, 512, 400, $bg);
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        $points = [292, 64, 132, 292, 244, 292, 212, 448, 380, 204, 268, 204, 316, 64];
        imagefilledpolygon($img, $points, $white);
        return $img;
    }

    private static function load(array $m): \GdImage
    {
        $file = ECLAIR_ROOT . self::url($m);
        $img = match ($m['ext']) {
            'jpg' => @imagecreatefromjpeg($file),
            'png' => @imagecreatefrompng($file),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            'gif' => @imagecreatefromgif($file),
            default => false,
        };
        if (!$img) {
            throw new \RuntimeException('Image illisible : ' . $m['file']);
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        return $img;
    }

    /* ────────────────────────────── Gestion ────────────────────────────── */

    public static function update(string $id, array $fields): ?array
    {
        $result = Store::locked(function () use ($id, $fields): ?array {
            $all = Store::read('media', []);
            if (!isset($all[$id])) {
                return null;
            }
            if (array_key_exists('alt', $fields)) {
                $all[$id]['alt'] = Sanitize::text($fields['alt'], 250);
            }
            Store::write('media', $all);
            return $all[$id];
        });
        Cache::purge();
        return $result;
    }

    public static function delete(string $id): void
    {
        $m = self::get($id);
        if (!$m) {
            return;
        }
        $files = [self::url($m), '/media/' . $m['file'] . '-og.jpg'];
        foreach ($m['webp'] ?? [] as $width) {
            $files[] = self::webpUrl($m, (int) $width);
        }
        foreach (array_map(fn ($f) => ECLAIR_ROOT . $f, array_unique($files)) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        Store::locked(function () use ($id): void {
            $all = Store::read('media', []);
            unset($all[$id]);
            Store::write('media', $all);
        });
        Cache::purge();
    }

    /** Contenus et réglages qui utilisent une image. */
    public static function usage(string $id): array
    {
        $out = [];
        foreach (Content::index() as $entry) {
            if ($entry['image'] === $id) {
                $out[] = $entry['title'];
                continue;
            }
            $item = Content::get($entry['id']);
            if ($item && in_array($id, Blocks::media($item['blocks']), true)) {
                $out[] = $entry['title'];
            }
        }
        foreach (['appearance.logo' => t('Logo'), 'appearance.favicon' => t('Icône du site'), 'seo.image' => t('Image de partage')] as $key => $label) {
            if (Settings::get($key) === $id) {
                $out[] = $label;
            }
        }
        foreach (Users::all() as $user) {
            if (($user['avatar'] ?? '') === $id) {
                $out[] = t('Photo de {name}', ['name' => $user['name']]);
            }
        }
        return $out;
    }
}
