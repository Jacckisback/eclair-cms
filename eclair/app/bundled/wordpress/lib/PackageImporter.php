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
use Eclair\Media;
use Eclair\Menus;
use Eclair\Redirects;
use Eclair\Settings;
use Eclair\Shop;
use Eclair\Store;
use Eclair\Users;

defined('ECLAIR') || exit;

/**
 * Import d'un paquet Eclair Export, par lots de quelques secondes, repris là où il s'est arrêté (même principe que
 * l'import WXR). Étapes : préparation (file d'attente) → catégories et auteurs → médias → contenus → liens internes →
 * menus → redirections → fin. En simulation, chaque étape calcule ce qu'elle ferait sans rien écrire hors de ses propres
 * fichiers de travail.
 *
 * Adresses : chaque contenu garde son chemin d'origine (route exacte) ; si Éclair ne peut pas le servir (adresse réservée
 * ou déjà prise), l'ancien chemin redirige en 301 vers le nouveau. Les liens et images des contenus qui visaient l'ancien
 * site sont réécrits vers les adresses d'Éclair. Réimporter le même paquet ne crée aucun doublon.
 *
 * Données : data/ext/wordpress/pjob (travail et bilan), pmap (correspondances), pqueue/<n> et pmedia/<n> (files d'attente).
 */
final class PackageImporter
{
    private const JOB = 'ext/wordpress/pjob';
    private const MAP = 'ext/wordpress/pmap';
    /** Correspondances d'une simulation : à part, jamais mêlées à celles d'un vrai import. */
    private const DRY = 'ext/wordpress/pdry';
    private const CHUNK = 40;
    private const SLICE = 12;
    public const STAGES = ['prepare', 'terms', 'media', 'content', 'links', 'menus', 'redirects', 'done'];
    /** Images que la médiathèque accepte, et documents copiés tels quels dans media/files/. */
    private const IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const DOCS = ['pdf' => '%PDF-', 'docx' => "PK\x03\x04", 'xlsx' => "PK\x03\x04", 'pptx' => "PK\x03\x04", 'odt' => "PK\x03\x04", 'ods' => "PK\x03\x04", 'odp' => "PK\x03\x04"];
    private const MAX_LIST = 500;
    /** Journal pour le rapport de migration : contenus, redirections, anciennes adresses publiques (bornés). */
    public const LEDGER = 'ext/wordpress/pledger';
    private static ?array $ledger = null;

    /** Journal du dernier import (vide s'il n'y en a pas). */
    public static function ledger(): array
    {
        $l = Store::read(self::LEDGER, []);
        return (is_array($l) ? $l : []) + ['items' => [], 'redirects' => [], 'urls' => []];
    }

    private static function log(string $list, array $row, int $max): void
    {
        self::$ledger ??= self::ledger();
        if (count(self::$ledger[$list]) < $max) {
            self::$ledger[$list][] = $row;
        }
    }

    public static function job(): ?array
    {
        $job = Store::read(self::JOB, null);
        return is_array($job) ? $job : null;
    }

    /** Prépare l'import (ou la simulation) d'un paquet ouvert par Package::open. Rien n'est encore créé. */
    public static function start(array $manifest, array $options): array
    {
        self::cleanup();
        $job = [
            'dry' => !empty($options['dry']),
            'keep' => !empty($options['keep']),
            'site' => !empty($options['site']),
            'menus' => !empty($options['menus']),
            'remote' => !empty($options['remote']),
            'source' => (string) ($manifest['source']['url'] ?? ''),
            'title' => (string) ($manifest['source']['name'] ?? ''),
            'package' => (string) ($manifest['package'] ?? ''),
            'files' => array_values((array) ($manifest['content_files'] ?? [])),
            'stage' => 'prepare', 'chunk' => 0, 'chunks' => 0, 'mchunks' => 0, 'pos' => 0, 'total' => 0, 'media_total' => 0, 'next' => 0,
            'started' => time(), 'done' => false,
            'report' => self::blankReport(),
        ];
        Store::write(self::JOB, $job);
        Store::write(self::LEDGER, ['items' => [], 'redirects' => [], 'urls' => []]);
        if ($job['dry']) {
            Store::write(self::DRY, self::map());
        } else {
            $map = self::map();
            Store::write(self::MAP, ['paths' => [], 'moves' => []] + $map);
        }
        return $job;
    }

    private static function blankReport(): array
    {
        return [
            'posts' => 0, 'pages' => 0, 'products' => 0, 'custom' => 0, 'skipped' => 0, 'drafts' => 0, 'protected' => 0,
            'categories' => 0, 'authors' => 0, 'authors_mine' => 0,
            'images' => 0, 'documents' => 0, 'media_skipped' => 0, 'media_missing' => 0,
            'routes' => 0, 'moved' => 0, 'aliases' => 0, 'links' => 0, 'links_left' => 0,
            'menu_items' => 0, 'menu_flattened' => 0, 'redirects' => 0, 'redirects_skipped' => 0, 'media_redirects' => 0, 'media_redirects_skipped' => 0,
            'verification' => [],
            'seo' => 0, 'seo_partial' => 0, 'shortcodes' => 0, 'dropped' => 0, 'home' => false,
            'not_migrated' => [], 'errors' => [],
        ];
    }

    /** Correspondances gardées d'un import à l'autre : wp => contenu, médias, catégories, auteurs, anciens chemins. */
    private static function map(bool $dry = false): array
    {
        $map = Store::read($dry ? self::DRY : self::MAP, []);
        return (is_array($map) ? $map : []) + ['items' => [], 'media' => [], 'urls' => [], 'cats' => [], 'authors' => [], 'paths' => [], 'moves' => []];
    }

    /** Abandonne l'import en cours : les contenus déjà créés restent. */
    public static function cancel(): void
    {
        self::cleanup();
        Store::delete(self::JOB);
    }

    private static function cleanup(): void
    {
        foreach (array_merge(Store::names('ext/wordpress/pqueue'), Store::names('ext/wordpress/pmedia')) as $name) {
            Store::delete($name);
        }
        Store::delete('ext/wordpress/pseo');
        Store::delete(self::DRY);
    }

    /** Avance l'import au nom de $user, pendant quelques secondes. */
    public static function step(array $user): array
    {
        $job = self::job();
        if ($job === null || $job['done']) {
            return $job ?? [];
        }
        $until = microtime(true) + self::SLICE;
        $map = self::map($job['dry']);
        try {
            while (!$job['done'] && microtime(true) < $until) {
                $stage = $job['stage'];
                match ($stage) {
                    'prepare' => self::prepare($job),
                    'terms' => self::terms($job, $map, $user),
                    'media' => self::media($job, $map, $user, $until),
                    'content' => self::content($job, $map, $user, $until),
                    'links' => self::links($job, $map, $user, $until),
                    'menus' => self::menus($job, $map),
                    'redirects' => self::redirects($job, $map),
                    default => null,
                };
                if ($job['stage'] === 'done') {
                    self::finish($job, $map, $user);
                }
            }
        } finally {
            if (self::$ledger !== null) {
                Store::write(self::LEDGER, self::$ledger);
                self::$ledger = null;
            }
            Store::write($job['dry'] ? self::DRY : self::MAP, $map);
            Store::write(self::JOB, $job);
        }
        return $job;
    }

    private static function next(array &$job, string $stage): void
    {
        $job['stage'] = $stage;
        $job['chunk'] = 0;
        $job['pos'] = 0;
    }

    private static function note(array &$job, string $what, string $name, string $reason): void
    {
        if (count($job['report']['not_migrated']) < self::MAX_LIST) {
            $job['report']['not_migrated'][] = [$what, mb_substr($name, 0, 200), $reason];
        }
    }

    private static function error(array &$job, string $message): void
    {
        $job['report']['errors'][] = mb_substr($message, 0, 300);
        $job['report']['errors'] = array_slice($job['report']['errors'], -30);
    }

    /* ─────────────────────────── Étapes ─────────────────────────── */

    /** Files d'attente : contenus et médias par paquets de CHUNK, SEO des contenus à part. */
    private static function prepare(array &$job): void
    {
        $n = 0;
        $chunk = [];
        $settings = (array) Package::data('content/settings.json');
        $skip = (int) ($settings['page_for_posts'] ?? 0);
        foreach ($job['files'] as $file) {
            foreach ((array) Package::data($file) as $item) {
                if (!is_array($item) || (int) ($item['wp_id'] ?? 0) < 1) {
                    continue;
                }
                $item['_front'] = ($settings['show_on_front'] ?? '') === 'page' && (int) $item['wp_id'] === (int) ($settings['page_on_front'] ?? 0);
                if ($skip && (int) $item['wp_id'] === $skip) {
                    // Page « Articles » de WordPress : le blog d'Éclair la remplace ; son adresse y mène.
                    $job['posts_page'] = (string) ($item['path'] ?? '');
                    continue;
                }
                $chunk[] = $item;
                $job['total']++;
                if (count($chunk) === self::CHUNK) {
                    Store::write('ext/wordpress/pqueue/' . $n++, $chunk);
                    $chunk = [];
                }
            }
        }
        if ($chunk) {
            Store::write('ext/wordpress/pqueue/' . $n++, $chunk);
        }
        $job['chunks'] = $n;
        $m = 0;
        foreach (array_chunk(array_values(array_filter((array) Package::data('media/index.json'), 'is_array')), self::CHUNK) as $part) {
            Store::write('ext/wordpress/pmedia/' . $m++, $part);
            $job['media_total'] += count($part);
        }
        $job['mchunks'] = $m;
        foreach (array_slice(Package::csv('urls.csv'), 1, 20000) as $row) {
            if (isset($row[0]) && preg_match('~^https?://~i', (string) $row[0])) {
                self::log('urls', [(string) $row[0], (string) ($row[1] ?? '')], 20000);
            }
        }
        $seo = (array) Package::data('seo.json');
        Store::write('ext/wordpress/pseo', ['posts' => (array) ($seo['posts'] ?? []), 'terms' => (array) ($seo['terms'] ?? [])]);
        $report = (array) Package::data('report.json');
        foreach ((array) ($report['shortcodes'] ?? []) as $tag => $row) {
            $job['report']['shortcodes'] += (int) ($row['count'] ?? 0);
            self::note($job, 'shortcode', '[' . $tag . ']', t('code court d’une extension WordPress, resté en texte dans {n} contenus', ['n' => (int) ($row['contents'] ?? 0)]));
        }
        foreach ((array) ($report['widgets'] ?? []) as $name => $row) {
            self::note($job, 'widget', (string) $name, t('élément de mise en page non converti, dans {n} contenus', ['n' => (int) ($row['contents'] ?? 0)]));
        }
        self::next($job, 'terms');
    }

    /** Catégories (articles et produits) et auteurs. Étiquettes et autres taxonomies : adresses redirigées plus loin. */
    private static function terms(array &$job, array &$map, array $user): void
    {
        $seo = (array) (Store::read('ext/wordpress/pseo', [])['terms'] ?? []);
        foreach ((array) Package::data('content/taxonomies.json') as $term) {
            $tax = (string) ($term['taxonomy'] ?? '');
            $slug = slugify((string) ($term['slug'] ?? ''));
            if (!in_array($tax, ['category', 'product_cat'], true) || $slug === '' || ($tax === 'category' && $slug === 'uncategorized' && (int) ($term['count'] ?? 0) === 0)) {
                continue;
            }
            $kind = $tax === 'product_cat' ? 'product' : 'post';
            $key = $tax . ':' . $term['slug'];
            if (isset($map['cats'][$key]) && Categories::get((string) $map['cats'][$key])) {
                continue;
            }
            $existing = Categories::bySlug($slug, $kind);
            if ($existing) {
                $map['cats'][$key] = $existing['id'];
                continue;
            }
            $job['report']['categories']++;
            if ($job['dry']) {
                $map['cats'][$key] = 'dry';
                continue;
            }
            $s = (array) ($seo[$tax . ':' . (int) ($term['wp_id'] ?? 0)] ?? []);
            try {
                $map['cats'][$key] = Categories::save(['name' => (string) ($term['name'] ?? $slug), 'slug' => $slug, 'kind' => $kind,
                    'description' => (string) ($s['description'] ?? '') !== '' ? (string) $s['description'] : strip_tags((string) ($term['description'] ?? '')),
                    'seo_title' => (string) ($s['title'] ?? '')])['id'];
            } catch (\Throwable $e) {
                $job['report']['categories']--;
                self::note($job, 'category', (string) ($term['name'] ?? $slug), $e->getMessage());
            }
        }
        // Auteurs : un compte Éclair existant (même e-mail, ou même identifiant d'adresse), sinon le compte qui importe.
        // Aucun compte n'est créé : un compte demande un mot de passe choisi par la personne.
        foreach ((array) Package::data('content/authors.json') as $a) {
            $found = ((string) ($a['email'] ?? '') !== '' ? Users::findByEmail((string) $a['email']) : null) ?? Users::findBySlug((string) ($a['slug'] ?? ''));
            $map['authors'][(int) ($a['wp_id'] ?? 0)] = $found['id'] ?? $user['id'];
            $found ? $job['report']['authors']++ : $job['report']['authors_mine']++;
        }
        self::next($job, 'media');
    }

    /** Médias : images dans la médiathèque (WebP), documents dans media/files/, le reste signalé. */
    private static function media(array &$job, array &$map, array $user, float $until): void
    {
        while ($job['chunk'] < $job['mchunks'] && microtime(true) < $until) {
            $records = (array) Store::read('ext/wordpress/pmedia/' . $job['chunk'], []);
            for (; $job['pos'] < count($records) && microtime(true) < $until; $job['pos']++) {
                self::oneMedia((array) $records[$job['pos']], $job, $map, $user);
                $job['next']++;
            }
            if ($job['pos'] >= count($records)) {
                Store::delete('ext/wordpress/pmedia/' . $job['chunk']);
                $job['chunk']++;
                $job['pos'] = 0;
            }
        }
        if ($job['chunk'] >= $job['mchunks']) {
            self::next($job, 'content');
        }
    }

    private static function oneMedia(array $m, array &$job, array &$map, array $user): void
    {
        $id = (int) ($m['wp_id'] ?? 0);
        $mime = strtolower((string) ($m['mime'] ?? ''));
        $url = (string) ($m['url'] ?? '');
        $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'media';
        if (isset($map['media'][$id])) {
            return;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $image = in_array($mime, self::IMAGES, true);
        $doc = !$image && isset(self::DOCS[$ext]);
        if (!$image && !$doc) {
            $job['report']['media_skipped']++;
            self::note($job, 'media', $name, t('type de fichier non repris ({type}) : le lien vise encore l’ancien site', ['type' => $mime !== '' ? $mime : $ext]));
            return;
        }
        if ($job['dry']) {
            $ok = !empty($m['included']) || ($job['remote'] && $url !== '');
            $ok ? $job['report'][$image ? 'images' : 'documents']++ : $job['report']['media_missing']++;
            return;
        }
        $bytes = !empty($m['included']) ? Package::media((string) ($m['file'] ?? ''), (string) ($m['sha256'] ?? '')) : null;
        if ($bytes === null && $job['remote'] && $url !== '') {
            foreach (array_unique([(string) preg_replace('~^http://~i', 'https://', $url), $url]) as $try) {
                try {
                    $bytes = AiImage::download($try);
                    break;
                } catch (\Throwable) {
                    continue;
                }
            }
        }
        if ($bytes === null) {
            $job['report']['media_missing']++;
            self::note($job, 'media', $name, !empty($m['missing']) ? t('fichier absent de l’ancien site') : t('fichier introuvable dans le paquet ni sur l’ancien site'));
            return;
        }
        try {
            if ($image) {
                $media = Media::import($bytes, $name, $user['id']);
                if ((string) ($m['alt'] ?? '') !== '') {
                    Media::update($media['id'], ['alt' => (string) $m['alt']]);
                }
                $target = 'm:' . $media['id'];
                $job['report']['images']++;
            } else {
                $target = 'f:' . self::document($bytes, $name, $ext, (string) ($m['date_gmt'] ?? ''));
                $job['report']['documents']++;
            }
        } catch (\Throwable $e) {
            self::note($job, 'media', $name, $e->getMessage());
            return;
        }
        $map['media'][$id] = $target;
        foreach (array_merge([$url], (array) ($m['variants'] ?? [])) as $u) {
            $key = self::key((string) $u);
            if ($key !== '') {
                $map['urls'][$key] = $target;
            }
        }
    }

    /**
     * Document (PDF, bureautique) copié dans media/files/AAAA/MM/ : en-tête vérifié, nom propre et unique, jamais un script.
     * Renvoie son chemin public.
     */
    private static function document(string $bytes, string $name, string $ext, string $date): string
    {
        if (!str_starts_with($bytes, self::DOCS[$ext]) || strlen($bytes) > Package::MAX_MEDIA) {
            throw new \RuntimeException(t('document refusé (contenu différent de son extension)'));
        }
        $ts = strtotime($date) ?: time();
        $dir = '/media/files/' . date('Y', $ts) . '/' . date('m', $ts);
        $base = slugify(pathinfo($name, PATHINFO_FILENAME)) ?: 'document';
        Store::ensureDir(ECLAIR_ROOT . $dir);
        foreach ([ECLAIR_ROOT . '/media/files', ECLAIR_ROOT . '/media/files/' . date('Y', $ts), ECLAIR_ROOT . $dir] as $d) {
            if (!is_file($d . '/index.html')) {
                @file_put_contents($d . '/index.html', '');
            }
        }
        $file = $base . '.' . $ext;
        for ($n = 2; is_file(ECLAIR_ROOT . $dir . '/' . $file); $n++) {
            $file = $base . '-' . $n . '.' . $ext;
        }
        if (@file_put_contents(ECLAIR_ROOT . $dir . '/' . $file, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException(t('Impossible d’enregistrer l’archive : vérifie l’espace disque.'));
        }
        @chmod(ECLAIR_ROOT . $dir . '/' . $file, 0644);
        return $dir . '/' . $file;
    }

    /** Contenus : un par un, par paquets de CHUNK. */
    private static function content(array &$job, array &$map, array $user, float $until): void
    {
        while ($job['chunk'] < $job['chunks'] && microtime(true) < $until) {
            $items = (array) Store::read('ext/wordpress/pqueue/' . $job['chunk'], []);
            for (; $job['pos'] < count($items) && microtime(true) < $until; $job['pos']++) {
                self::oneContent((array) $items[$job['pos']], $job, $map, $user);
                $job['next']++;
            }
            if ($job['pos'] >= count($items)) {
                $job['chunk']++;
                $job['pos'] = 0;
            }
        }
        if ($job['chunk'] >= $job['chunks']) {
            self::next($job, 'links');
        }
    }

    private static function oneContent(array $wp, array &$job, array &$map, array $user): void
    {
        $wpId = (int) $wp['wp_id'];
        $r = &$job['report'];
        if (isset($map['items'][$wpId]) && Content::get((string) $map['items'][$wpId])) {
            $r['skipped']++;
            return;
        }
        $wpType = (string) ($wp['type'] ?? 'post');
        $type = in_array($wpType, ['post', 'page', 'product'], true) ? $wpType : 'page';
        $oldPath = (string) ($wp['path'] ?? '');
        $front = !empty($wp['_front']);
        // Chemin d'origine gardé à l'identique, sauf l'accueil et les adresses à paramètres (?p=, brouillons) : un alias les reprend.
        $route = $front || $oldPath === '' || $oldPath === '/' || str_contains($oldPath, '?') ? '' : Content::cleanRoute($oldPath);
        $index = Content::index();
        if ($route !== '' && (Content::routeTaken($route, '', $index) || isset($map['paths'][$route]))) {
            $route = '';
        }
        $status = in_array($wp['status'] ?? '', ['publish', 'future'], true) && empty($wp['protected']) && $job['keep'] ? 'published' : 'draft';
        $r['protected'] += empty($wp['protected']) ? 0 : 1;
        $r['drafts'] += $status === 'draft' ? 1 : 0;
        $media = static function (string $url) use (&$map): ?string {
            $target = $map['urls'][self::key($url)] ?? '';
            return str_starts_with($target, 'm:') ? substr($target, 2) : null;
        };
        [$blocks, $stats] = Wxr::blocks((string) ($wp['html'] ?? ''), $media);
        $r['dropped'] += (int) $stats['dropped'];
        $seo = (array) ((Store::read('ext/wordpress/pseo', [])['posts'] ?? [])[$wpId] ?? []);
        $canonical = (string) ($seo['canonical'] ?? '');
        if ($canonical !== '' && self::key($canonical) !== '' ) {
            $canonical = ''; // canonical vers l'ancien site : Éclair calcule la sienne
        }
        if ((string) ($seo['title'] ?? '') . (string) ($seo['description'] ?? '') !== '') {
            $r['seo']++;
        }
        if (!empty($seo['nofollow']) || array_filter((array) ($seo['og'] ?? [])) || array_filter((array) ($seo['twitter'] ?? []))) {
            $r['seo_partial']++;
        }
        $terms = (array) ($wp['terms'] ?? []);
        $catTax = $type === 'product' ? 'product_cat' : 'category';
        $category = '';
        foreach (array_merge(array_filter([(string) ($wp['primary_category'] ?? '')]), (array) ($terms[$catTax] ?? [])) as $slug) {
            if (isset($map['cats'][$catTax . ':' . $slug])) {
                $category = (string) $map['cats'][$catTax . ':' . $slug];
                break;
            }
        }
        $thumb = (string) ($map['media'][(int) ($wp['featured_media'] ?? 0)] ?? '');
        $lang = (string) ($wp['lang'] ?? '');
        $data = [
            'type' => $type,
            'title' => (string) ($wp['title'] ?? ''),
            'slug' => (string) ($wp['slug'] ?? ''),
            'route' => $route,
            'status' => $status,
            'date' => (string) ($wp['date_gmt'] ?? ''),
            'blocks' => $blocks ?: [['type' => 'paragraph', 'text' => '']],
            'summary' => (string) ($wp['excerpt'] ?? ''),
            'category' => $category === 'dry' ? '' : $category,
            'image' => str_starts_with($thumb, 'm:') ? substr($thumb, 2) : '',
            'author' => (string) ($map['authors'][(int) ($wp['author'] ?? 0)] ?? $user['id']),
            'lang' => isset(Content::LANGS[$lang]) ? $lang : (string) Settings::get('lang'),
            'tgroup' => (string) ($wp['translation_group'] ?? '') !== '' ? substr(md5((string) $wp['translation_group']), 0, 10) : '',
            'seo' => ['title' => (string) ($seo['title'] ?? ''), 'description' => (string) ($seo['description'] ?? ''),
                'keyword' => (string) ($seo['keyword'] ?? ''), 'canonical' => $canonical, 'noindex' => !empty($seo['noindex'])],
        ];
        if ($type === 'product') {
            $data['product'] = self::product((array) ($wp['product'] ?? []), $map);
        }
        $r[match ($wpType) { 'post' => 'posts', 'page' => 'pages', 'product' => 'products', default => 'custom' }]++;
        if ($wpType !== $type) {
            self::note($job, 'type', (string) $data['title'], t('type « {type} » importé comme page, à la même adresse', ['type' => $wpType]));
        }
        if ($job['dry']) {
            $route !== '' ? $r['routes']++ : ($oldPath !== '' && !str_contains($oldPath, '?') && !$front && $status === 'published' ? $r['moved']++ : null);
            if ($route !== '') {
                $map['paths'][$route] = 'dry';
            }
            return;
        }
        try {
            $item = Content::save($data, $user);
        } catch (\Throwable $e) {
            self::error($job, $data['title'] . ' : ' . $e->getMessage());
            self::note($job, 'content', (string) $data['title'], $e->getMessage());
            return;
        }
        $map['items'][$wpId] = $item['id'];
        $newPath = $front ? '/' : Content::path(Content::entry($item));
        if ($front && $job['site']) {
            Settings::save(['home' => $item['id']]);
            $r['home'] = true;
        }
        $r['routes'] += $item['route'] !== '' ? 1 : 0;
        $state = $status === 'draft' ? 'draft' : ($front ? 'home' : ($oldPath !== '' && !str_contains($oldPath, '?') && Redirects::normalize($oldPath) !== Redirects::normalize($newPath) ? 'moved' : 'kept'));
        self::log('items', [$wpType, (string) $item['title'], (string) ($wp['url'] ?? ''), $newPath, $state], 5000);
        if ($oldPath !== '' && !str_contains($oldPath, '?')) {
            $map['paths'][self::key($oldPath)] = $newPath;
            if (Redirects::normalize($oldPath) !== Redirects::normalize($newPath) && $status === 'published') {
                $map['moves'][$oldPath] = $newPath;
                $r['moved']++;
            }
        }
        try {
            $r['aliases'] += Aliases::add([($wpType === 'page' ? 'page_id:' : 'p:') . $wpId => $newPath]);
        } catch (\Throwable $e) {
            self::error($job, $e->getMessage());
        }
    }

    /** Produit WooCommerce → produit Éclair (catalogue : prix, prix barré, stock, références, galerie, variantes). */
    private static function product(array $p, array $map): array
    {
        $stock = static fn (string $s): string => match ($s) { 'outofstock' => 'out_of_stock', 'onbackorder' => 'preorder', default => 'in_stock' };
        $sale = (string) ($p['sale_price'] ?? '') !== '';
        $variants = [];
        foreach ((array) ($p['variations'] ?? []) as $v) {
            $variants[] = ['label' => (string) ($v['label'] ?? ''), 'price' => (string) ($v['price'] ?? ''), 'availability' => $stock((string) ($v['stock_status'] ?? ''))];
        }
        $gallery = [];
        foreach ((array) ($p['gallery'] ?? []) as $id) {
            $t = (string) ($map['media'][(int) $id] ?? '');
            if (str_starts_with($t, 'm:')) {
                $gallery[] = substr($t, 2);
            }
        }
        return [
            'price' => (string) ($p['price'] ?? ''),
            'compare' => $sale ? (string) ($p['regular_price'] ?? '') : '',
            'availability' => $stock((string) ($p['stock_status'] ?? '')),
            'link' => (string) ($p['external_url'] ?? ''),
            'digital' => !empty($p['virtual']) || !empty($p['downloadable']),
            'sku' => (string) ($p['sku'] ?? ''),
            'gtin' => (string) ($p['gtin'] ?? ''),
            'brand' => (string) ($p['brand'] ?? ''),
            'gallery' => $gallery,
            'variants' => $variants,
        ];
    }

    /** Liens internes et adresses de fichiers des contenus importés : réécrits vers les adresses d'Éclair. */
    private static function links(array &$job, array &$map, array $user, float $until): void
    {
        if ($job['dry']) {
            self::next($job, 'menus');
            return;
        }
        $ids = array_values($map['items']);
        for (; $job['pos'] < count($ids) && microtime(true) < $until; $job['pos']++) {
            $item = Content::get((string) $ids[$job['pos']]);
            if ($item === null) {
                continue;
            }
            $count = 0;
            $left = 0;
            $blocks = self::rewriteValue($item['blocks'], $map, $count, $left);
            $job['report']['links_left'] += $left;
            if ($count > 0) {
                try {
                    // La date est renvoyée en texte : Content::build lit une date, pas un horodatage.
                    Content::save(['blocks' => $blocks, 'date' => gmdate('c', (int) $item['date'])] + $item, $user);
                    $job['report']['links'] += $count;
                } catch (\Throwable $e) {
                    self::error($job, $item['title'] . ' : ' . $e->getMessage());
                }
            }
        }
        if ($job['pos'] >= count($ids)) {
            self::next($job, 'menus');
        }
    }

    private static function rewriteValue(mixed $value, array $map, int &$count, int &$left): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = in_array($k, ['link', 'url'], true) && is_string($v) ? self::rewriteUrl($v, $map, $count, $left) : self::rewriteValue($v, $map, $count, $left);
            }
            return $value;
        }
        if (!is_string($value) || !str_contains($value, 'href=')) {
            return $value;
        }
        return (string) preg_replace_callback('~(href|src)="([^"]*)"~', static function (array $m) use ($map, &$count, &$left): string {
            return $m[1] . '="' . e(self::rewriteUrl(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $map, $count, $left)) . '"';
        }, $value);
    }

    /** Adresse de l'ancien site → adresse d'Éclair (contenu, média, document) ; un autre site ne change pas. */
    public static function rewriteUrl(string $url, array $map, int &$count, int &$left): string
    {
        $key = self::key($url, $query, $fragment);
        if ($key === '' || ($key === '/' && $query === '')) {
            return $url;
        }
        $target = null;
        if ($query !== '' && preg_match('~(?:^|&)(p|page_id|attachment_id)=(\d+)~', $query, $q)) {
            $target = Aliases::resolve($q[1] . ':' . $q[2]);
        }
        $target ??= $map['paths'][$key] ?? $map['paths'][rtrim($key, '/')] ?? $map['paths'][rtrim($key, '/') . '/'] ?? null;
        if ($target === null && isset($map['urls'][$key])) {
            $t = (string) $map['urls'][$key];
            $media = str_starts_with($t, 'm:') ? Media::get(substr($t, 2)) : null;
            $target = $media ? Media::url($media) : (str_starts_with($t, 'f:') ? substr($t, 2) : null);
        }
        if ($target === null) {
            // Page de l'ancien site non importée (archive, page d'extension…) : son chemin, redirigé ou servi par Éclair.
            if (preg_match('~^https?://~i', $url)) {
                $count++;
                $left++;
                return $key . ($query !== '' ? '?' . $query : '') . $fragment;
            }
            return $url;
        }
        $count++;
        return $target . $fragment;
    }

    /**
     * Chemin d'une adresse de l'ancien site (hôte de l'ancien site, avec ou sans www, ou chemin relatif), décodé ; '' pour
     * une autre adresse. $query et $fragment reçoivent le reste.
     */
    public static function key(string $url, ?string &$query = null, ?string &$fragment = null): string
    {
        $query = '';
        $fragment = '';
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '//')) {
            return '';
        }
        $p = parse_url($url);
        if (!is_array($p)) {
            return '';
        }
        $source = (string) (self::job()['source'] ?? '');
        if (isset($p['scheme']) || isset($p['host'])) {
            $source = strtolower((string) parse_url($source, PHP_URL_HOST));
            $host = strtolower((string) ($p['host'] ?? ''));
            if (!in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true) || $source === ''
                || preg_replace('~^www\.~', '', $host) !== preg_replace('~^www\.~', '', $source)) {
                return '';
            }
        } elseif (!str_starts_with($url, '/')) {
            return '';
        }
        $query = (string) ($p['query'] ?? '');
        $fragment = isset($p['fragment']) ? '#' . $p['fragment'] : '';
        $path = rawurldecode((string) ($p['path'] ?? '/'));
        // Ancien site installé dans un sous-dossier : le chemin est repris sans lui (comme l'export).
        $base = rtrim((string) parse_url((string) (self::job()['source'] ?? ''), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        return $path === '' ? '/' : $path;
    }

    /** Menus principal et pied de page (un niveau, 30 liens) : contenus, catégories, liens réécrits. */
    private static function menus(array &$job, array &$map): void
    {
        $menus = (array) Package::data('content/menus.json');
        if (!$job['menus'] || !$menus) {
            self::next($job, 'redirects');
            return;
        }
        $pick = static function (array $menus, array $words): ?array {
            foreach ($menus as $m) {
                foreach (array_merge((array) ($m['locations'] ?? []), [(string) ($m['name'] ?? ''), (string) ($m['slug'] ?? '')]) as $where) {
                    foreach ($words as $w) {
                        if (str_contains(strtolower((string) $where), $w)) {
                            return $m;
                        }
                    }
                }
            }
            return null;
        };
        $footer = $pick($menus, ['footer', 'pied', 'pie']);
        $main = $pick(array_filter($menus, static fn ($m) => $m !== $footer), ['primary', 'main', 'header', 'menu-1', 'top', 'principal'])
            ?? (array_values(array_filter($menus, static fn ($m) => $m !== $footer))[0] ?? null);
        foreach (['main' => $main, 'footer' => $footer] as $name => $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $items = [];
            foreach ((array) ($menu['items'] ?? []) as $it) {
                $entry = self::menuItem((array) $it, $map);
                if ($entry === null) {
                    self::note($job, 'menu', (string) ($it['title'] ?? ''), t('lien de menu sans équivalent sur Éclair'));
                    continue;
                }
                if ((int) ($it['parent'] ?? 0) > 0) {
                    $job['report']['menu_flattened']++;
                }
                if (count($items) >= 30) {
                    self::note($job, 'menu', (string) ($it['title'] ?? ''), t('au-delà des 30 liens d’un menu Éclair'));
                    continue;
                }
                $items[] = $entry;
            }
            $job['report']['menu_items'] += count($items);
            if (!$job['dry'] && $items) {
                Menus::save($name, $items);
            }
        }
        self::next($job, 'redirects');
    }

    private static function menuItem(array $it, array $map): ?array
    {
        $label = (string) ($it['title'] ?? '');
        $type = (string) ($it['type'] ?? '');
        $object = (string) ($it['object'] ?? '');
        if ($type === 'post_type' && isset($map['items'][(int) ($it['object_id'] ?? 0)])) {
            return ['type' => 'content', 'id' => (string) $map['items'][(int) $it['object_id']], 'label' => $label];
        }
        if ($type === 'taxonomy') {
            foreach ($map['cats'] as $key => $id) {
                if (str_starts_with($key, $object . ':') && $id !== 'dry' && ($c = Categories::get((string) $id)) && str_ends_with((string) ($it['url'] ?? ''), '/' . $c['slug'] . '/')) {
                    return ['type' => 'category', 'id' => (string) $id, 'label' => $label];
                }
            }
        }
        if ($type === 'post_type_archive') {
            return ['type' => $object === 'product' ? 'shop' : 'blog', 'label' => $label];
        }
        $url = (string) ($it['url'] ?? '');
        if ($url === '' || $url === '#') {
            return null;
        }
        $count = 0;
        $left = 0;
        $url = self::rewriteUrl($url, $map, $count, $left);
        $home = self::key((string) ($it['url'] ?? ''));
        return ['type' => 'link', 'url' => $home === '/' ? '/' : $url, 'label' => $label];
    }

    /** Redirections du paquet (existantes et générées), anciennes adresses déplacées, alias des fichiers joints. */
    private static function redirects(array &$job, array &$map): void
    {
        $rows = Package::csv('redirects.csv');
        array_shift($rows);
        $mediaIndex = array_column(array_filter((array) Package::data('media/index.json'), 'is_array'), null, 'wp_id');
        $pairs = ['manual' => [301 => [], 302 => []], 'auto' => [301 => [], 302 => []]];
        $blog = Content::blogPath();
        $live = static fn (string $path): bool => Content::findByPath(Redirects::normalize($path)) !== null || Content::byRoute($path) !== null;
        foreach ($rows as $row) {
            [$source, $target, $code, $origin, $regex] = array_pad($row, 5, '');
            if ($regex === '1') {
                $job['report']['redirects_skipped']++;
                self::note($job, 'redirect', $source, t('redirection à motif (expression régulière) : à réécrire à la main'));
                continue;
            }
            $code = (int) $code;
            if (!in_array($code, [301, 302, 307, 308], true)) {
                $job['report']['redirects_skipped']++;
                self::note($job, 'redirect', $source, t('code {code} : Éclair ne fait que des redirections 301 et 302', ['code' => $code]));
                continue;
            }
            $to = self::resolve($target, $map, $blog, $mediaIndex);
            if ($to === null || $to === '' || $live($source) && $origin === 'migration') {
                if ($to === null) {
                    $job['report']['redirects_skipped']++;
                    self::note($job, 'redirect', $source, t('cible introuvable sur Éclair'));
                }
                continue;
            }
            $pairs[$origin === 'migration' ? 'auto' : 'manual'][in_array($code, [302, 307], true) ? 302 : 301][] = [$source, $to];
        }
        foreach ($map['moves'] as $from => $to) {
            $pairs['manual'][301][] = [(string) $from, (string) $to];
        }
        if (($job['posts_page'] ?? '') !== '' && Redirects::normalize($job['posts_page']) !== Redirects::normalize($blog)) {
            $pairs['manual'][301][] = [$job['posts_page'], $blog];
        }
        // Pages des fichiers joints (/?attachment_id=…) : vers le contenu parent, sinon le fichier.
        $aliases = [];
        foreach ($mediaIndex as $m) {
            $to = self::resolve('eclair:media:' . (int) ($m['wp_id'] ?? 0), $map, $blog, $mediaIndex);
            if ($to !== null && $to !== '' && str_starts_with($to, '/')) {
                $aliases['attachment_id:' . (int) $m['wp_id']] = $to;
            }
        }
        foreach ($pairs as $kind => $byCode) {
            foreach ($byCode as $code => $list) {
                foreach ($list as [$from, $to]) {
                    self::log('redirects', [Redirects::normalize((string) $from), (string) $to, $kind === 'auto' ? 'migration' : 'site', (int) $code], 5000);
                }
                if ($job['dry']) {
                    $job['report']['redirects'] += count($list);
                } elseif ($list) {
                    $job['report']['redirects'] += Redirects::addMany($list, $kind === 'auto', $code, false);
                }
            }
        }
        self::mediaRedirects($job, $map, $mediaIndex, $live);
        if (!$job['dry'] && $aliases) {
            try {
                $job['report']['aliases'] += Aliases::add($aliases);
            } catch (\Throwable $e) {
                self::error($job, $e->getMessage());
            }
        }
        self::next($job, 'done');
    }

    /** Cible d'une redirection du paquet : référence « eclair:… », chemin de l'ancien site, ou adresse externe. */
    private static function resolve(string $target, array $map, string $blog, array $mediaIndex): ?string
    {
        if (str_starts_with($target, 'eclair:')) {
            $parts = explode(':', $target, 4);
            switch ($parts[1] ?? '') {
                case 'term':
                    $tax = (string) ($parts[2] ?? '');
                    $slug = (string) ($parts[3] ?? '');
                    $id = $map['cats'][$tax . ':' . $slug] ?? null;
                    $cat = $id && $id !== 'dry' ? Categories::get((string) $id) : null;
                    if ($id === 'dry') {
                        return $blog . '/' . slugify($slug);
                    }
                    // Étiquette : la catégorie du même nom s'il y en a une, sinon le blog (ou la boutique).
                    $cat ??= Categories::bySlug(slugify($slug), str_starts_with($tax, 'product_') ? 'product' : 'post');
                    return $cat ? Categories::path($cat) : (str_starts_with($tax, 'product_') ? Shop::path() : $blog);
                case 'author':
                case 'archive':
                case 'blog':
                    return $blog;
                case 'shop':
                    return Shop::path();
                case 'feed':
                    return '/feed.xml';
                case 'media':
                    $m = $mediaIndex[(int) ($parts[2] ?? 0)] ?? null;
                    if (!is_array($m)) {
                        return null;
                    }
                    $parent = (int) ($m['parent'] ?? 0);
                    if ($parent && isset($map['items'][$parent]) && ($e = Content::index()[$map['items'][$parent]] ?? null)) {
                        return Content::path($e);
                    }
                    $t = (string) ($map['media'][(int) $m['wp_id']] ?? '');
                    $media = str_starts_with($t, 'm:') ? Media::get(substr($t, 2)) : null;
                    return $media ? Media::url($media) : (str_starts_with($t, 'f:') ? substr($t, 2) : null);
            }
            return null;
        }
        if ($target === '') {
            return null;
        }
        $key = self::key($target);
        if ($key !== '') {
            return $map['paths'][$key] ?? $map['moves'][$key] ?? $key;
        }
        return preg_match('~^https?://~i', $target) ? $target : null;
    }

    /**
     * Anciennes adresses des fichiers (/wp-content/uploads/…, miniatures comprises) : une 301 vers le fichier repris, grâce à la
     * correspondance exacte établie pendant l'import des médias (aucune recherche par nom). Les liens venus d'autres sites, de
     * Google Images ou d'anciens e-mails (PDF de tarifs, photos) ne tombent pas en 404. Ajoutées en dernier et comme redirections
     * « du site » : elles ne remplacent jamais une redirection de contenu ; si la liste se remplit, les miniatures passent après
     * les originaux et ce qui ne tient pas est compté.
     */
    private static function mediaRedirects(array &$job, array $map, array $mediaIndex, callable $live): void
    {
        $originals = [];
        $variants = [];
        foreach ($mediaIndex as $m) {
            foreach (array_merge([(string) ($m['url'] ?? '')], (array) ($m['variants'] ?? [])) as $i => $u) {
                $key = self::key((string) $u);
                if ($key === '' || $key === '/') {
                    continue;
                }
                if ($job['dry']) {
                    // Simulation : les médias ne sont pas importés, on compte ce qui le serait.
                    $ok = !empty($m['included']) || ($job['remote'] && (string) ($m['url'] ?? '') !== '');
                    $ok && $job['report']['media_redirects']++;
                    continue;
                }
                $to = self::mediaPath((string) ($map['urls'][$key] ?? ''));
                if ($to === '' || Redirects::normalize($key) === Redirects::normalize($to) || $live($key)) {
                    continue;
                }
                if ($i === 0) {
                    $originals[$key] = $to;
                } else {
                    $variants[$key] = $to;
                }
            }
        }
        if ($job['dry']) {
            return;
        }
        $pairs = [];
        foreach ($originals + $variants as $from => $to) {
            $pairs[] = [(string) $from, $to];
        }
        foreach ($pairs as [$from, $to]) {
            self::log('redirects', [Redirects::normalize($from), $to, 'migration', 301], 5000);
        }
        // Une marge reste libre pour les redirections ajoutées à la main.
        $room = max(0, Redirects::MAX - 250 - count(Redirects::all()));
        $kept = array_slice($pairs, 0, $room);
        $added = $kept ? Redirects::addMany($kept, false, 301, false) : 0;
        $job['report']['media_redirects'] += $added;
        $job['report']['media_redirects_skipped'] += count($pairs) - count($kept);
    }

    /** Adresse publique d'un média repris : « m:<id> » (médiathèque) ou « f:/media/files/… » (document). */
    private static function mediaPath(string $target): string
    {
        if (str_starts_with($target, 'f:')) {
            return substr($target, 2);
        }
        $media = str_starts_with($target, 'm:') ? Media::get(substr($target, 2)) : null;
        return $media ? Media::url($media) : '';
    }

    private static function finish(array &$job, array &$map, array $user): void
    {
        if (!$job['dry'] && $job['site']) {
            $settings = (array) Package::data('content/settings.json');
            $patch = [];
            if (Settings::get('name') === 'Mon site' && (string) ($settings['name'] ?? '') !== '') {
                $patch['name'] = (string) $settings['name'];
                $patch['tagline'] = (string) ($settings['description'] ?? '');
            }
            // Balises de vérification Google Search Console et Bing (Yoast, Rank Math, AIOSEO) : sans elles, la propriété
            // Search Console vérifiée par balise serait perdue à la bascule. Une balise déjà réglée dans Éclair n'est jamais remplacée.
            $global = (array) (((array) Package::data('seo.json'))['global'] ?? []);
            foreach (['google', 'bing'] as $engine) {
                $token = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($global['verification'][$engine] ?? ''));
                if ($token !== '' && (string) Settings::get('seo.' . $engine) === '') {
                    $patch['seo'][$engine] = $token;
                    $job['report']['verification'][] = $engine;
                }
            }
            if ($patch) {
                Settings::save($patch);
            }
        }
        // Produits importés : la boutique d'Éclair (fermée par défaut) est ouverte, sinon leurs pages répondraient 404.
        if (!$job['dry'] && $job['report']['products'] > 0 && !Shop::enabled()) {
            $currency = strtoupper((string) (((array) Package::data('content/settings.json'))['woocommerce']['currency'] ?? ''));
            Settings::save(['shop' => ['enabled' => true] + (preg_match('~^[A-Z]{3}$~', $currency) ? ['currency' => $currency] : [])]);
            $job['report']['shop'] = true;
        }
        $job['done'] = true;
        $job['finished'] = time();
        self::cleanup();
        if (!$job['dry']) {
            Log::security('wordpress_package_import', ['site' => $job['source'], 'posts' => $job['report']['posts'], 'pages' => $job['report']['pages']]);
        }
    }

    /** Avancement : [fait, total, étape]. */
    public static function progress(array $job): array
    {
        $total = (int) $job['total'] + (int) $job['media_total'];
        return [min((int) $job['next'], $total), $total, (string) $job['stage']];
    }
}
