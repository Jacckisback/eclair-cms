<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Wordpress;

use Eclair\AiImage;
use Eclair\Aliases;
use Eclair\Categories;
use Eclair\Content;
use Eclair\Log;
use Eclair\Store;

defined('ECLAIR') || exit;

/**
 * Import d'un export WordPress, par lots (quelques secondes par appel, repris là où il s'est arrêté). Chaque contenu garde
 * son adresse exacte (/2024/03/article/, /page.html…) quand elle appartient à l'ancien site, et son ancienne adresse à
 * paramètres (/?p=123, /?page_id=45) mène à lui par une seule redirection. Les images de l'ancien site sont téléchargées
 * dans la médiathèque (https, hôte public vérifié) ; une image d'un autre site reste un lien. Un même export réimporté
 * ne crée aucun doublon (correspondance des identifiants WordPress gardée).
 *
 * Données : data/ext/wordpress/job.php (import en cours et bilan), data/ext/wordpress/map.php (identifiant WordPress =>
 * contenu, adresse d'image => média).
 */
final class Importer
{
    private const JOB = 'ext/wordpress/job';
    private const MAP = 'ext/wordpress/map';
    /** Durée d'un lot, en secondes : bien en dessous des limites d'exécution des hébergements mutualisés. */
    private const SLICE = 12;

    /** Contenus de l'export par fichier de file d'attente (data/ext/wordpress/queue/<n>). */
    private const CHUNK = 50;
    private const QUEUE = 'ext/wordpress/queue';
    private const ATTACHMENTS = 'ext/wordpress/attachments';

    public static function job(): ?array
    {
        $job = Store::read(self::JOB, null);
        return is_array($job) ? $job : null;
    }

    /** Prépare l'import d'un export lu par Wxr::parse (déjà en mémoire : petits exports) ; rien n'est encore créé. */
    public static function start(array $export, bool $keepStatus, bool $images): array
    {
        self::clear();
        foreach (array_chunk($export['items'], self::CHUNK) as $n => $chunk) {
            self::queue($n, $chunk);
        }
        return self::open($export, count($export['items']), $keepStatus, $images);
    }

    /**
     * Prépare l'import d'un fichier d'export, lu en flux : ses contenus sont rangés par paquets de CHUNK dans une file
     * d'attente sur le disque, si bien que ni la lecture ni l'import ne gardent tout l'export en mémoire, et que le fichier
     * de travail relu à chaque lot reste petit (il contenait tout l'export). Renvoie le travail, ou un message d'erreur.
     */
    public static function startFile(string $file, bool $keepStatus, bool $images): array|string
    {
        self::clear();
        $chunk = [];
        $n = 0;
        $head = Wxr::parseFile($file, static function (array $item) use (&$chunk, &$n): void {
            $chunk[] = $item;
            if (count($chunk) === self::CHUNK) {
                self::queue($n++, $chunk);
                $chunk = [];
            }
        });
        if (is_string($head) || $head['count'] === 0) {
            self::clear();
            return is_string($head) ? $head : t('Aucun article ni page dans cet export.');
        }
        if ($chunk) {
            self::queue($n, $chunk);
        }
        return self::open($head, $head['count'], $keepStatus, $images);
    }

    /** Écrit un paquet de la file d'attente, sans le garder en mémoire (la file d'un gros export y tiendrait tout entière). */
    private static function queue(int $n, array $chunk): void
    {
        Store::write(self::QUEUE . '/' . $n, $chunk);
        Store::drop(self::QUEUE . '/' . $n);
    }

    private static function open(array $head, int $total, bool $keepStatus, bool $images): array
    {
        Store::write(self::ATTACHMENTS, $head['attachments']);
        $job = [
            'site' => $head['site'], 'title' => $head['title'], 'categories' => $head['categories'], 'chunk' => self::CHUNK,
            'total' => $total, 'next' => 0, 'keep' => $keepStatus, 'images' => $images, 'started' => time(), 'done' => false,
            'report' => ['posts' => 0, 'pages' => 0, 'skipped' => 0, 'routes' => 0, 'aliases' => 0, 'images' => 0, 'missed' => 0, 'shortcodes' => 0, 'dropped' => 0, 'errors' => []],
        ];
        Store::write(self::JOB, $job);
        return $job;
    }

    /** Retire la file d'attente et les fichiers joints d'un import (terminé, abandonné ou remplacé). */
    private static function clear(): void
    {
        foreach (Store::names(self::QUEUE) as $name) {
            Store::delete($name);
        }
        @rmdir(ECLAIR_DATA . '/' . self::QUEUE);
        Store::delete(self::ATTACHMENTS);
    }

    public static function cancel(): void
    {
        self::clear();
        Store::delete(self::JOB);
    }

    /** Importe le lot suivant au nom de $user. Renvoie le travail mis à jour (next, done, report). */
    public static function step(array $user): array
    {
        $job = self::job();
        if ($job === null || $job['done']) {
            return $job ?? [];
        }
        // Import commencé par une version précédente (tout l'export dans le fichier de travail) : passé en file d'attente.
        if (!isset($job['chunk'])) {
            foreach (array_chunk((array) ($job['items'] ?? []), self::CHUNK) as $n => $chunk) {
                self::queue($n, $chunk);
            }
            Store::write(self::ATTACHMENTS, (array) ($job['attachments'] ?? []));
            $job['chunk'] = self::CHUNK;
            unset($job['items'], $job['attachments']);
        }
        $map = Store::read(self::MAP, []);
        $map = (is_array($map) ? $map : []) + ['items' => [], 'images' => [], 'cats' => []];
        $until = microtime(true) + self::SLICE;
        $cats = self::categories($job['categories'], $map);
        $attachments = (array) Store::read(self::ATTACHMENTS, []);
        $n = (int) $job['total'];
        $size = (int) $job['chunk'];
        try {
            // Enregistrements groupés : l'index de recherche n'est réécrit qu'une fois par lot (Content::batch).
            Content::batch(static function () use (&$job, &$map, $cats, $user, $attachments, $n, $size, $until): void {
                $loaded = -1;
                $chunk = [];
                while ($job['next'] < $n && microtime(true) < $until) {
                    $at = intdiv($job['next'], $size);
                    // Un paquet de la file lu une fois pour ses contenus (chaque enregistrement vide la mémoire des données lues).
                    if ($at !== $loaded) {
                        $chunk = (array) Store::read(self::QUEUE . '/' . $at, []);
                        $loaded = $at;
                    }
                    $wp = $chunk[$job['next'] % $size] ?? null;
                    $job['next']++;
                    if (is_array($wp)) {
                        self::one($wp, $job, $map, $cats, $user, $attachments);
                    }
                    // Paquet terminé : retiré du disque (et de la mémoire de la requête).
                    if ($job['next'] % $size === 0 || $job['next'] >= $n) {
                        Store::delete(self::QUEUE . '/' . $at);
                    }
                }
            });
        } finally {
            Store::write(self::MAP, $map);
            $job['done'] = $job['next'] >= $n;
            if ($job['done']) {
                // Bilan seul : le contenu de l'export n'est plus gardé.
                $job['categories'] = [];
                self::clear();
            }
            Store::write(self::JOB, $job);
        }
        if ($job['done']) {
            Log::security('wordpress_import', ['site' => (string) $job['site'], 'posts' => $job['report']['posts'], 'pages' => $job['report']['pages']]);
        }
        return $job;
    }

    /** Catégories de l'export : retrouvées (même slug) ou créées, une seule fois. */
    private static function categories(array $wanted, array &$map): array
    {
        $out = [];
        foreach ($wanted as $slug => $name) {
            if (isset($map['cats'][$slug]) && Categories::get((string) $map['cats'][$slug])) {
                $out[$slug] = (string) $map['cats'][$slug];
                continue;
            }
            $cat = Categories::bySlug(slugify((string) $slug), 'post') ?? Categories::save(['name' => (string) $name, 'slug' => (string) $slug, 'kind' => 'post']);
            $out[$slug] = $map['cats'][$slug] = $cat['id'];
        }
        return $out;
    }

    private static function one(array $wp, array &$job, array &$map, array $cats, array $user, array $attachments): void
    {
        $key = $wp['type'] . ':' . $wp['wp'];
        $done = isset($map['items'][$key]) ? (Content::index()[$map['items'][$key]] ?? null) : null;
        if ($done !== null) {
            $job['report']['skipped']++;
            return;
        }
        $fetch = function (string $url, string $alt) use (&$job, &$map, $user): ?string {
            return $job['images'] ? self::image($url, (string) $job['site'], $map, $user) : null;
        };
        // [gallery ids="1,2"] : les images de la galerie, retrouvées par leur identifiant parmi les fichiers joints de l'export.
        $html = (string) preg_replace_callback('~\[gallery\b[^\]]*\bids=["\']([\d,\s]+)["\'][^\]]*\]~i', static function (array $m) use ($attachments): string {
            $imgs = '';
            foreach (array_slice(array_filter(array_map('intval', explode(',', $m[1]))), 0, 60) as $id) {
                $imgs .= isset($attachments[$id]) ? '<img src="' . e((string) $attachments[$id]) . '" alt="">' : '';
            }
            return $imgs !== '' ? "\n\n<figure>" . $imgs . "</figure>\n\n" : $m[0];
        }, (string) $wp['html']);
        [$blocks, $stats] = Wxr::blocks($html, $fetch);
        foreach (['images', 'missed', 'shortcodes', 'dropped'] as $k) {
            $job['report'][$k] += (int) $stats[$k];
        }
        $thumb = $wp['thumb'] && isset($attachments[$wp['thumb']]) && $job['images']
            ? self::image((string) $attachments[$wp['thumb']], (string) $job['site'], $map, $user) : null;
        $category = '';
        foreach (array_keys((array) $wp['cats']) as $slug) {
            if (isset($cats[$slug])) {
                $category = $cats[$slug];
                break;
            }
        }
        try {
            $item = Content::save([
                'type' => $wp['type'],
                'title' => $wp['title'],
                'slug' => $wp['slug'],
                'route' => $wp['route'],
                'status' => $job['keep'] ? $wp['status'] : 'draft',
                'date' => $wp['date'],
                'blocks' => $blocks ?: [['type' => 'paragraph', 'text' => '']],
                'summary' => $wp['excerpt'],
                'category' => $category,
                'image' => $thumb ?? '',
                // Titre, description, mot-clé, noindex et canonical saisis dans Yoast SEO, Rank Math ou SEOPress (Wxr::seo).
                'seo' => (array) ($wp['seo'] ?? []),
            ], $user);
        } catch (\Throwable $e) {
            $job['report']['errors'][] = mb_substr($wp['title'] . ' : ' . $e->getMessage(), 0, 300);
            $job['report']['errors'] = array_slice($job['report']['errors'], -20);
            return;
        }
        $map['items'][$key] = $item['id'];
        $job['report'][$wp['type'] === 'page' ? 'pages' : 'posts']++;
        $job['report']['routes'] += $item['route'] !== '' ? 1 : 0;
        try {
            $job['report']['aliases'] += Aliases::add([($wp['type'] === 'page' ? 'page_id:' : 'p:') . $wp['wp'] => Content::path(Content::entry($item))]);
        } catch (\Throwable $e) {
            $job['report']['errors'][] = mb_substr($e->getMessage(), 0, 300);
        }
    }

    /**
     * Image de l'ancien site → média (déjà importée : même média). Seulement depuis le domaine de l'ancien site (ou le CDN
     * d'images de WordPress.com), en https ; l'original est préféré à une vignette (« -300x200 »).
     */
    private static function image(string $url, string $site, array &$map, array $user): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Une vignette (« -300x200 ») et son original sont la même image : une seule copie dans la médiathèque.
        $key = (string) preg_replace('~-\d{2,5}x\d{2,5}(?=\.(jpe?g|png|gif|webp)(\?|$))~i', '', (string) preg_replace('~^http://~i', 'https://', $url));
        if (isset($map['images'][$key])) {
            return (string) $map['images'][$key] ?: null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = preg_replace('~^www\.~', '', strtolower((string) parse_url($site, PHP_URL_HOST)));
        if ($host === '' || !(preg_replace('~^www\.~', '', $host) === $own || preg_match('~^i[0-3]\.wp\.com$~', $host))) {
            return null;
        }
        $https = (string) preg_replace('~^http://~i', 'https://', $url);
        $original = (string) preg_replace('~-\d{2,5}x\d{2,5}(?=\.(jpe?g|png|gif|webp)(\?|$))~i', '', $https);
        $media = null;
        // https d'abord ; l'adresse telle quelle ensuite, que la protection du téléchargement refuse si elle n'est pas en https
        // (hors adresses de test configurées dans ECLAIR_AI_ENDPOINTS).
        $given = (string) preg_replace('~-\d{2,5}x\d{2,5}(?=\.(jpe?g|png|gif|webp)(\?|$))~i', '', $url);
        foreach (array_unique([$original, $https, $given, $url]) as $try) {
            try {
                $bytes = AiImage::download($try);
                $media = \Eclair\Media::import($bytes, basename((string) parse_url($try, PHP_URL_PATH)) ?: 'image', $user['id'])['id'] ?? null;
                break;
            } catch (\Throwable) {
                continue;
            }
        }
        $map['images'][$key] = $media ?? '';
        return $media;
    }
}
