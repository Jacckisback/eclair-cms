<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Wordpress;

use Eclair\Http;
use Eclair\Log;
use Eclair\RateLimit;
use Eclair\Req;
use Eclair\Security;
use Eclair\Settings;
use Eclair\Store;

defined('ECLAIR') || exit;

/**
 * Réception directe d'un paquet Eclair Export : l'extension WordPress envoie ses archives ici, par morceaux, au lieu de
 * les faire télécharger puis renvoyer à la main (/_eclair/import).
 *
 * L'adresse n'existe que pendant qu'une clé de liaison est active (créée dans l'administration, valable deux heures, dont
 * seule l'empreinte est gardée). La clé ne donne qu'un droit : déposer des archives ZIP nommées eclair-export-*.zip dans le
 * dossier privé du paquet. Elle ne lit rien, n'importe rien, ne touche à aucun contenu : l'import reste lancé par une
 * personne connectée, après vérification du manifeste et des empreintes (Package::open).
 */
final class Receiver
{
    private const LINK = 'ext/wordpress/link';
    private const TTL = 7200;
    /** Morceau le plus lourd accepté (borné aussi par post_max_size de l'hébergeur). */
    private const CHUNK = 8 * 1048576;
    /** Taille maximale d'une archive, et place gardée libre sur le disque. */
    private const MAX_PART = 4 * 1073741824;
    private const MARGIN = 64 * 1048576;

    /** Clé de liaison active (empreinte, échéance, personne), ou null. */
    public static function link(): ?array
    {
        $l = Store::read(self::LINK);
        return is_array($l) && is_string($l['hash'] ?? null) && (int) ($l['expires'] ?? 0) > time() ? $l : null;
    }

    /** Crée la clé (remplace la précédente) et la renvoie : elle n'est montrée qu'à ce moment-là. */
    public static function issue(array $user): string
    {
        $code = bin2hex(random_bytes(20));
        foreach (glob(Package::dir() . '/parts/*.part') ?: [] as $file) {
            @unlink($file);
        }
        Store::write(self::LINK, ['hash' => hash('sha256', $code), 'expires' => time() + self::TTL, 'user' => (string) ($user['id'] ?? '')]);
        Log::security('wordpress_link_created', ['user' => (string) ($user['id'] ?? '')]);
        return 'eclair1.' . rtrim(strtr(base64_encode(rtrim((string) Settings::get('url'), '/')), '+/', '-_'), '=') . '.' . $code;
    }

    public static function revoke(): void
    {
        Store::delete(self::LINK);
    }

    /** La clé montrée à la personne correspond-elle encore à la clé active ? */
    public static function matches(string $key): bool
    {
        $link = self::link();
        return $link !== null && hash_equals($link['hash'], hash('sha256', (string) substr((string) strrchr('.' . $key, '.'), 1)));
    }

    /** Sans clé active, l'adresse n'existe pas : le site répond sa page 404. */
    public static function handle(): void
    {
        $link = self::link();
        if ($link === null) {
            return;
        }
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        if (Req::method() !== 'POST') {
            self::fail(405, 'method');
        }
        if (Security::httpsOnly() && !Req::isHttps()) {
            self::fail(400, 'https');
        }
        $ip = 'wplink:' . Req::ipPrefix();
        if (RateLimit::blocked($ip) > 0) {
            self::fail(429, 'blocked');
        }
        if (!hash_equals($link['hash'], hash('sha256', Req::header('X-Eclair-Code')))) {
            // Dix clés fausses en dix minutes depuis une même adresse : un quart d'heure de blocage.
            RateLimit::attempt($ip, 10, 600);
            self::fail(403, 'code');
        }
        if ($link['expires'] - time() < 1800) {
            // Gros site sur un hébergement lent : un envoi en cours n'est pas coupé par l'échéance.
            Store::write(self::LINK, ['expires' => time() + 1800] + $link);
        }
        session_write_close();
        match (Req::query('op')) {
            'hello' => self::hello(),
            'chunk' => self::chunk(),
            'finish' => self::finish(),
            'done' => self::done($link),
            default => self::fail(400, 'op'),
        };
    }

    private static function fail(int $status, string $error, array $more = []): never
    {
        Http::json(['ok' => false, 'error' => $error] + $more, $status);
    }

    private static function parts(): string
    {
        $dir = Package::dir() . '/parts';
        Store::ensureDir($dir);
        return $dir;
    }

    /** Nom d'archive accepté : celui que fabrique l'extension, sans dossier. */
    private static function name(): string
    {
        $name = Req::query('name');
        if (!preg_match('~^eclair-export-[A-Za-z0-9._-]{1,150}\.zip$~D', $name) || str_contains($name, '..')) {
            self::fail(400, 'name');
        }
        return $name;
    }

    private static function busy(): bool
    {
        $p = PackageImporter::job();
        $w = Importer::job();
        return ($p !== null && !$p['done']) || ($w !== null && !$w['done']);
    }

    /** Morceau le plus lourd que cet hébergement accepte en une requête. */
    public static function limit(): int
    {
        $v = trim((string) ini_get('post_max_size'));
        $n = (int) $v;
        $post = match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        return max(262144, min(self::CHUNK, $post > 0 ? $post - 65536 : self::CHUNK));
    }

    /** Ce que le site accepte et ce qu'il a déjà reçu : l'envoi reprend là où il s'était arrêté. */
    private static function hello(): never
    {
        $received = [];
        foreach (glob(self::parts() . '/eclair-export-*') ?: [] as $file) {
            $base = basename($file);
            if (str_ends_with($base, '.zip')) {
                $received[$base] = ['bytes' => (int) filesize($file), 'complete' => true];
            } elseif (str_ends_with($base, '.zip.part')) {
                $received[substr($base, 0, -5)] ??= ['bytes' => (int) filesize($file), 'complete' => false];
            }
        }
        Http::json(['ok' => true, 'site' => (string) Settings::get('name'), 'eclair' => ECLAIR_VERSION, 'chunk' => self::limit(),
            'busy' => self::busy(), 'received' => $received]);
    }

    /** Un morceau, écrit à la suite de ce qui est déjà là (?name=…&offset=…, corps brut). */
    private static function chunk(): never
    {
        $name = self::name();
        if (self::busy()) {
            self::fail(409, 'busy');
        }
        $dir = self::parts();
        if (is_file($dir . '/' . $name)) {
            Http::json(['ok' => true, 'size' => (int) filesize($dir . '/' . $name), 'complete' => true]);
        }
        $file = $dir . '/' . $name . '.part';
        if (!is_file($file) && count(glob($dir . '/*') ?: []) >= Package::MAX_PARTS) {
            self::fail(400, 'parts');
        }
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length < 1 || $length > self::limit()) {
            self::fail(413, 'chunk', ['chunk' => self::limit()]);
        }
        $h = @fopen($file, 'c+b');
        if (!$h || !flock($h, LOCK_EX | LOCK_NB)) {
            self::fail(409, 'locked');
        }
        $size = (int) (fstat($h)['size'] ?? 0);
        if ((int) Req::query('offset', '-1') !== $size) {
            // Morceau déjà reçu ou perdu en route : l'extension reprend à la taille annoncée.
            self::fail(409, 'offset', ['size' => $size]);
        }
        $free = @disk_free_space($dir);
        if ($size + $length > self::MAX_PART || ($free !== false && $free < $length + self::MARGIN)) {
            self::fail(507, 'space');
        }
        $in = fopen('php://input', 'rb');
        fseek($h, 0, SEEK_END);
        $written = $in ? (int) stream_copy_to_stream($in, $h, $length + 1) : 0;
        $ok = $written === $length;
        if ($ok && $size === 0) {
            fseek($h, 0);
            $ok = fread($h, 4) === "PK\x03\x04";
        }
        if (!$ok) {
            ftruncate($h, $size);
            fclose($h);
            if ($size === 0) {
                @unlink($file);
            }
            self::fail(400, $written === $length ? 'zip' : 'body');
        }
        fflush($h);
        fclose($h);
        Http::json(['ok' => true, 'size' => $size + $length]);
    }

    /** Archive entière reçue : empreinte vérifiée, puis rangée avec les autres parties du paquet (?name=…&sha256=…). */
    private static function finish(): never
    {
        $name = self::name();
        $dir = self::parts();
        if (is_file($dir . '/' . $name)) {
            Http::json(['ok' => true, 'complete' => true]);
        }
        $file = $dir . '/' . $name . '.part';
        if (!is_file($file) || is_link($file)) {
            self::fail(404, 'missing');
        }
        extend_time(300);
        $sha = strtolower(Req::query('sha256'));
        if (!preg_match('~^[a-f0-9]{64}$~D', $sha) || !hash_equals($sha, (string) hash_file('sha256', $file))) {
            @unlink($file);
            self::fail(422, 'sha256');
        }
        if (!@rename($file, $dir . '/' . $name)) {
            self::fail(500, 'rename');
        }
        Http::json(['ok' => true, 'complete' => true]);
    }

    /** Tout est arrivé : la clé a servi, elle disparaît. L'import se lance dans l'administration. */
    private static function done(array $link): never
    {
        $parts = glob(self::parts() . '/eclair-export-*.zip') ?: [];
        if (!$parts) {
            self::fail(400, 'empty');
        }
        self::revoke();
        Log::security('wordpress_package_received', ['parts' => count($parts), 'bytes' => array_sum(array_map('filesize', $parts)), 'user' => (string) $link['user']]);
        Http::json(['ok' => true, 'parts' => count($parts)]);
    }
}
