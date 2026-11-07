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

    public static function job(): ?array
    {
        $job = Store::read(self::JOB, null);
        return is_array($job) ? $job : null;
    }

    /** Prépare l'import d'un export lu par Wxr::parse ; rien n'est encore créé. */
    public static function start(array $export, bool $keepStatus, bool $images): array
    {
        $job = [
            'site' => $export['site'], 'title' => $export['title'], 'categories' => $export['categories'], 'attachments' => $export['attachments'],
            'items' => $export['items'], 'total' => count($export['items']), 'next' => 0, 'keep' => $keepStatus, 'images' => $images, 'started' => time(), 'done' => false,
            'report' => ['posts' => 0, 'pages' => 0, 'skipped' => 0, 'routes' => 0, 'aliases' => 0, 'images' => 0, 'missed' => 0, 'shortcodes' => 0, 'dropped' => 0, 'errors' => []],
        ];
        Store::write(self::JOB, $job);
        return $job;
    }

    public static function cancel(): void
    {
        Store::delete(self::JOB);
    }

    /** Importe le lot suivant au nom de $user. Renvoie le travail mis à jour (next, done, report). */
    public static function step(array $user): array
    {
        $job = self::job();
        if ($job === null || $job['done']) {
            return $job ?? [];
        }
        $map = Store::read(self::MAP, []);
        $map = (is_array($map) ? $map : []) + ['items' => [], 'images' => [], 'cats' => []];
        $until = microtime(true) + self::SLICE;
        $cats = self::categories($job['categories'], $map);
        $n = count($job['items']);
        try {
            while ($job['next'] < $n && microtime(true) < $until) {
                $wp = $job['items'][$job['next']];
                $job['next']++;
                self::one($wp, $job, $map, $cats, $user);
            }
        } finally {
            Store::write(self::MAP, $map);
            $job['done'] = $job['next'] >= $n;
            if ($job['done']) {
                // Bilan seul : le contenu de l'export n'est plus gardé.
                $job['items'] = $job['attachments'] = $job['categories'] = [];
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

    private static function one(array $wp, array &$job, array &$map, array $cats, array $user): void
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
        [$blocks, $stats] = Wxr::blocks((string) $wp['html'], $fetch);
        foreach (['images', 'missed', 'shortcodes', 'dropped'] as $k) {
            $job['report'][$k] += (int) $stats[$k];
        }
        $thumb = $wp['thumb'] && isset($job['attachments'][$wp['thumb']]) && $job['images']
            ? self::image((string) $job['attachments'][$wp['thumb']], (string) $job['site'], $map, $user) : null;
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
