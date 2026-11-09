<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Wordpress;

use Eclair\Ai;
use Eclair\Aliases;
use Eclair\Categories;
use Eclair\Content;
use Eclair\Redirects;
use Eclair\Settings;
use Eclair\Shop;
use Eclair\Store;

defined('ECLAIR') || exit;

/**
 * Rapport de migration remis au client, après l'import d'un paquet Eclair Export : une page HTML autonome aux couleurs
 * d'Éclair (à télécharger, ou à imprimer en PDF), en français, espagnol ou anglais, quelle que soit la langue de
 * l'administration. Contenus importés, adresses (chaque ancienne adresse publique vérifiée : servie, redirigée ou
 * introuvable), redirections, médias, éléments non migrés et leur raison, liste de contrôle SEO, vitesse avant/après
 * mesurée par Google PageSpeed Insights.
 */
final class Report
{
    private const BRANDING = 'ext/wordpress/branding';
    private const SPEED = 'ext/wordpress/speed';
    private const PSI = 'https://www.googleapis.com/pagespeedonline/v5';
    public const LANGS = ['fr' => 'Français', 'es' => 'Español', 'en' => 'English'];
    private const ROWS = 300;

    public static function branding(): array
    {
        $b = Store::read(self::BRANDING, []);
        return (is_array($b) ? $b : []) + ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'website' => '', 'note' => ''];
    }

    public static function saveBranding(array $in): void
    {
        $clean = [];
        foreach (['name' => 80, 'company' => 80, 'email' => 120, 'phone' => 40, 'website' => 200, 'note' => 600] as $k => $max) {
            $clean[$k] = mb_substr(trim(strip_tags((string) ($in[$k] ?? ''))), 0, $max);
        }
        $clean['email'] = filter_var($clean['email'], FILTER_VALIDATE_EMAIL) ? $clean['email'] : '';
        $clean['website'] = preg_match('~^https?://[^\s<>"]+$~i', $clean['website']) ? $clean['website'] : '';
        Store::write(self::BRANDING, $clean);
    }

    /* ─────────────────────────── Vitesse (Google PageSpeed Insights) ─────────────────────────── */

    public static function speed(): array
    {
        $s = Store::read(self::SPEED, []);
        return is_array($s) ? $s : [];
    }

    /**
     * Mesure une adresse avec PageSpeed Insights (mobile, performance) et garde le résultat sous $which (« old » ou
     * « new »). Seul le service de Google est appelé : il charge lui-même la page. Renvoie un message d'erreur, ou null.
     */
    public static function measure(string $which, string $url): ?string
    {
        if (!in_array($which, ['old', 'new'], true) || !preg_match('~^https?://[a-z0-9.-]+\.[a-z]{2,}(?::\d+)?(/[^\s]*)?$~i', $url)) {
            return t('Adresse à mesurer invalide : elle doit être publique (https://…).');
        }
        $endpoint = Ai::endpoint('pagespeed') ?: self::PSI;
        $res = Ai::request('GET', $endpoint . '/runPagespeed?' . http_build_query(['url' => $url, 'strategy' => 'mobile', 'category' => 'performance']), ['Accept: application/json'], null, 100);
        $json = json_decode((string) $res['body'], true);
        $lh = is_array($json) ? ($json['lighthouseResult'] ?? null) : null;
        if ($res['status'] !== 200 || !is_array($lh)) {
            return $res['status'] === 429 ? t('Google PageSpeed est saturé pour le moment : réessaie dans quelques minutes.')
                : t('Mesure impossible : Google PageSpeed n’a pas pu charger cette adresse (site hors ligne ou bloqué ?).');
        }
        $audit = static fn (string $id): ?float => isset($lh['audits'][$id]['numericValue']) ? (float) $lh['audits'][$id]['numericValue'] : null;
        $speed = self::speed();
        $speed[$which] = [
            'url' => $url,
            'at' => time(),
            'score' => isset($lh['categories']['performance']['score']) ? (int) round((float) $lh['categories']['performance']['score'] * 100) : null,
            'fcp' => $audit('first-contentful-paint'),
            'lcp' => $audit('largest-contentful-paint'),
            'tbt' => $audit('total-blocking-time'),
            'cls' => $audit('cumulative-layout-shift'),
            'si' => $audit('speed-index'),
            'bytes' => $audit('total-byte-weight'),
            'requests' => isset($lh['audits']['network-requests']['details']['items']) ? count((array) $lh['audits']['network-requests']['details']['items']) : null,
        ];
        Store::write(self::SPEED, $speed);
        return null;
    }

    /* ─────────────────────────── Anciennes adresses ─────────────────────────── */

    /** Ce que donne une ancienne adresse sur Éclair : 'ok' (page servie), 'redirect' (301 vers une page), 'missing'. */
    public static function check(string $url): string
    {
        $p = parse_url($url);
        if (!is_array($p)) {
            return 'missing';
        }
        $path = rawurldecode((string) ($p['path'] ?? '/'));
        $base = rtrim((string) parse_url((string) (PackageImporter::job()['source'] ?? ''), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        parse_str((string) ($p['query'] ?? ''), $query);
        if ($query !== [] && ($key = Aliases::key($query)) !== '') {
            return Aliases::resolve($key) !== null ? 'redirect' : 'missing';
        }
        if ($path === '' || $path === '/') {
            return 'ok';
        }
        $clean = Redirects::normalize($path);
        if (Content::byRoute($path) || Content::byRoute($clean . '/') || Content::findByPath($clean)) {
            return 'ok';
        }
        if (Redirects::lookup($clean)) {
            return 'redirect';
        }
        $blog = Content::blogPath();
        if (in_array($clean, [$blog, '/feed.xml', '/sitemap.xml', Shop::path()], true)) {
            return 'ok';
        }
        foreach (Categories::all() as $cat) {
            if (Categories::path($cat) === $clean) {
                return 'ok';
            }
        }
        return 'missing';
    }

    /** Toutes les anciennes adresses publiques du journal : [ok, redirect, missing, [adresses introuvables…]]. */
    public static function urls(array $ledger): array
    {
        $out = ['ok' => 0, 'redirect' => 0, 'missing' => 0, 'list' => []];
        foreach ($ledger['urls'] as [$url]) {
            $state = self::check((string) $url);
            $out[$state]++;
            if ($state === 'missing' && count($out['list']) < 200) {
                $out['list'][] = (string) $url;
            }
        }
        return $out;
    }

    /* ─────────────────────────── Liste de contrôle SEO ─────────────────────────── */

    /** Points vérifiés automatiquement : [clé du libellé, réussi]. */
    public static function checks(array $urls): array
    {
        $url = (string) Settings::get('url');
        $noindex = count(array_filter(Content::published(), static fn (array $e): bool => !empty($e['noindex'])));
        return [
            ['sitemap', (bool) Settings::get('seo.index')],
            ['robots', (bool) Settings::get('seo.index')],
            ['domain', $url !== '' && Settings::verified()],
            ['https', str_starts_with($url, 'https://')],
            ['canonical', $url !== ''],
            ['console', (string) Settings::get('seo.google') !== ''],
            ['bing', (string) Settings::get('seo.bing') !== ''],
            ['noindex', $noindex === 0],
            ['404', $urls['missing'] === 0 && ($urls['ok'] + $urls['redirect']) > 0],
        ];
    }

    /* ─────────────────────────── Page ─────────────────────────── */

    public static function filename(): string
    {
        $host = (string) parse_url((string) (PackageImporter::job()['source'] ?? ''), PHP_URL_HOST);
        return 'rapport-migration-' . (slugify((string) preg_replace('~^www\.~', '', $host)) ?: 'site') . '-' . date('Y-m-d') . '.html';
    }

    public static function html(string $lang): string
    {
        $lang = isset(self::LANGS[$lang]) ? $lang : 'fr';
        $T = static fn (string $key, array $vars = []): string => strtr(self::TEXT[$key][$lang] ?? self::TEXT[$key]['fr'] ?? $key, array_combine(array_map(static fn ($k) => '{' . $k . '}', array_keys($vars)), array_map('strval', $vars)) ?: []);
        $n = static fn (int|float $v): string => number_format((float) $v, 0, ',', $lang === 'en' ? ',' : "\u{202F}");
        $job = PackageImporter::job() ?? ['report' => [], 'source' => '', 'title' => '', 'dry' => false, 'finished' => time()];
        $r = (array) $job['report'] + ['posts' => 0, 'pages' => 0, 'products' => 0, 'custom' => 0, 'images' => 0, 'documents' => 0, 'media_skipped' => 0, 'media_missing' => 0,
            'routes' => 0, 'moved' => 0, 'redirects' => 0, 'aliases' => 0, 'links' => 0, 'seo' => 0, 'seo_partial' => 0, 'categories' => 0, 'menu_items' => 0, 'not_migrated' => []];
        $ledger = PackageImporter::ledger();
        $urls = self::urls($ledger);
        $checks = self::checks($urls);
        $speed = self::speed();
        $b = self::branding();
        $site = (string) Settings::get('name');
        $new = rtrim((string) Settings::get('url'), '/');
        $contents = $r['posts'] + $r['pages'] + $r['products'] + $r['custom'];
        $total = $urls['ok'] + $urls['redirect'] + $urls['missing'];
        $date = (int) ($job['finished'] ?? time());
        $months = ['fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']];
        $day = $lang === 'en' ? date('F j, Y', $date) : date('j', $date) . ($lang === 'es' ? ' de ' : ' ') . $months[$lang][(int) date('n', $date) - 1] . ($lang === 'es' ? ' de ' : ' ') . date('Y', $date);
        $states = ['kept' => 'state_kept', 'moved' => 'state_moved', 'home' => 'state_home', 'draft' => 'state_draft'];
        $types = ['post' => 'type_post', 'page' => 'type_page', 'product' => 'type_product'];
        $ms = static fn (?float $v): string => $v === null ? '—' : ($v >= 1000 ? number_format($v / 1000, 1, $lang === 'en' ? '.' : ',', '') . ' s' : (int) round($v) . ' ms');
        $kb = static fn (?float $v): string => $v === null ? '—' : ($v >= 1048576 ? number_format($v / 1048576, 1, $lang === 'en' ? '.' : ',', '') . ' Mo' : (int) round($v / 1024) . ' Ko');
        $metrics = [
            ['score', 'm_score', static fn ($v) => $v === null ? '—' : (string) $v, true],
            ['lcp', 'm_lcp', $ms, false],
            ['fcp', 'm_fcp', $ms, false],
            ['tbt', 'm_tbt', $ms, false],
            ['cls', 'm_cls', static fn ($v) => $v === null ? '—' : number_format((float) $v, 3, $lang === 'en' ? '.' : ',', ''), false],
            ['si', 'm_si', $ms, false],
            ['bytes', 'm_bytes', $kb, false],
            ['requests', 'm_requests', static fn ($v) => $v === null ? '—' : (string) $v, false],
        ];
        $pct = $total > 0 ? (int) floor(100 * ($urls['ok'] + $urls['redirect']) / $total) : 0;
        ob_start();
        ?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($T('title', ['site' => $site])) ?></title>
<style>
:root{--brand:#6443cc;--brand-strong:#4f2fb8;--brand-soft:#efeafd;--ink:#1d1b2a;--muted:#5d5a70;--line:#e5e3ee;--bg:#f7f6fb;--panel:#fff;--ok:#1d7f55;--ok-soft:#e5f6ee;--warn:#a85208;--warn-soft:#fdf1e2;--bad:#be3427;--bad-soft:#fce9e7}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{max-width:940px;margin:0 auto;padding:32px 20px 64px}
.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}
.brand{display:flex;align-items:center;gap:10px;font-weight:700}
.brand svg{width:34px;height:34px}
.tag{font-size:12px;color:var(--muted);text-align:right}
.hero{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:28px}
.hero h1{margin:0 0 8px;font-size:27px;line-height:1.2;letter-spacing:-.02em}
.move{display:flex;flex-wrap:wrap;align-items:center;gap:10px;font-weight:600;word-break:break-all}
.move .old{color:var(--muted);text-decoration:line-through;text-decoration-thickness:1px}
.move .new{color:var(--brand-strong)}
.verdict{margin:14px 0 0;padding:12px 14px;border-radius:12px;background:var(--ok-soft);color:var(--ok);font-weight:600}
.verdict.warn{background:var(--warn-soft);color:var(--warn)}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0 4px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.card b{display:block;font-size:24px;letter-spacing:-.01em}
.card span{color:var(--muted);font-size:13px}
h2{font-size:18px;margin:34px 0 12px}
h2 small{font-weight:400;color:var(--muted);font-size:13px;margin-left:6px}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden}
.pad{padding:16px 18px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:9px 14px;border-bottom:1px solid var(--line);vertical-align:top}
th{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);background:#fbfaff;font-weight:600}
tr:last-child td{border-bottom:0}
td.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
td.url{word-break:break-all;font-size:13px}
.pill{display:inline-block;font-size:12px;font-weight:600;padding:2px 9px;border-radius:999px;white-space:nowrap}
.pill.ok{background:var(--ok-soft);color:var(--ok)}.pill.warn{background:var(--warn-soft);color:var(--warn)}.pill.bad{background:var(--bad-soft);color:var(--bad)}.pill.info{background:var(--brand-soft);color:var(--brand-strong)}
.bar{display:flex;height:14px;border-radius:999px;overflow:hidden;background:var(--line);margin:10px 0 8px}
.bar i{display:block;height:100%}
.bar .a{background:var(--ok)}.bar .b{background:var(--brand)}.bar .c{background:var(--bad)}
.legend{display:flex;flex-wrap:wrap;gap:14px;font-size:13px;color:var(--muted)}
.legend b{color:var(--ink)}
.better{color:var(--ok);font-weight:600}.worse{color:var(--bad);font-weight:600}
.check{display:grid;grid-template-columns:28px 1fr;gap:10px;padding:11px 16px;border-bottom:1px solid var(--line)}
.check:last-child{border-bottom:0}
.check p{margin:2px 0 0;color:var(--muted);font-size:13px}
.box{width:22px;height:22px;border-radius:6px;display:grid;place-items:center;font-weight:700;font-size:13px}
.box.yes{background:var(--ok-soft);color:var(--ok)}.box.no{background:var(--warn-soft);color:var(--warn)}.box.todo{border:2px solid var(--line)}
.more{color:var(--muted);font-size:13px;padding:10px 14px}
.cta{margin-top:34px;background:var(--brand);color:#fff;border-radius:18px;padding:24px 28px;display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center}
.cta h2{margin:0 0 6px;color:#fff}
.cta p{margin:0;opacity:.93}
.cta .who{text-align:right;font-size:14px;line-height:1.6}
.cta a{color:#fff}
.print{margin:0 0 16px;font-size:13px;color:var(--muted)}
footer{margin-top:26px;color:var(--muted);font-size:12px;text-align:center}
@media (max-width:720px){.cards{grid-template-columns:repeat(2,1fr)}.cta{grid-template-columns:1fr}.cta .who{text-align:left}th,td{padding:8px 10px}}
@media print{body{background:#fff}.page{padding:0}.print{display:none}h2{break-after:avoid}.panel,.card,.check{break-inside:avoid}@page{margin:14mm}}
</style>
</head>
<body>
<div class="page">
<p class="print"><?= e($T('print')) ?></p>
<header class="top">
  <div class="brand"><svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#6443cc"/><path d="M18 4 6 18h8.3L13 28l12-15.4h-8.4z" fill="#fff"/></svg><?= e($T('heading')) ?></div>
  <div class="tag"><?= e($T('date', ['date' => $day])) ?></div>
</header>

<section class="hero">
  <h1><?= e($site) ?></h1>
  <div class="move"><span class="old"><?= e((string) $job['source']) ?></span><span aria-hidden="true">→</span><span class="new"><?= e($new !== '' ? $new : $T('new_site')) ?></span></div>
  <?php if (!empty($job['dry'])): ?><p class="verdict warn"><?= e($T('dry')) ?></p>
  <?php elseif ($urls['missing'] === 0 && $total > 0): ?><p class="verdict"><?= e($T('verdict_ok', ['n' => $n($total)])) ?></p>
  <?php else: ?><p class="verdict warn"><?= e($T('verdict_warn', ['n' => $n($urls['missing'])])) ?></p><?php endif ?>
</section>

<div class="cards">
  <div class="card"><b><?= e($n($contents)) ?></b><span><?= e($T('c_contents')) ?></span></div>
  <div class="card"><b><?= e($n($r['routes'])) ?></b><span><?= e($T('c_kept')) ?></span></div>
  <div class="card"><b><?= e($n($r['redirects'] + $r['aliases'])) ?></b><span><?= e($T('c_redirects')) ?></span></div>
  <div class="card"><b><?= e($n($r['images'] + $r['documents'])) ?></b><span><?= e($T('c_media')) ?></span></div>
</div>

<h2><?= e($T('h_urls')) ?> <small><?= e($T('h_urls_small', ['n' => $n($total)])) ?></small></h2>
<div class="panel pad">
  <?php if ($total > 0): ?>
  <div class="bar" role="img" aria-label="<?= e($T('urls_legend', ['ok' => $urls['ok'], 'redirect' => $urls['redirect'], 'missing' => $urls['missing']])) ?>">
    <i class="a" style="width:<?= round(100 * $urls['ok'] / $total, 2) ?>%"></i><i class="b" style="width:<?= round(100 * $urls['redirect'] / $total, 2) ?>%"></i><i class="c" style="width:<?= round(100 * $urls['missing'] / $total, 2) ?>%"></i>
  </div>
  <div class="legend"><span><b><?= e($n($urls['ok'])) ?></b> <?= e($T('u_ok')) ?></span><span><b><?= e($n($urls['redirect'])) ?></b> <?= e($T('u_redirect')) ?></span><span><b><?= e($n($urls['missing'])) ?></b> <?= e($T('u_missing')) ?></span><span><b><?= e((string) $pct) ?> %</b> <?= e($T('u_reached')) ?></span></div>
  <?php if ($urls['list']): ?><p><strong><?= e($T('u_list')) ?></strong></p><ul><?php foreach ($urls['list'] as $u): ?><li class="url"><?= e($u) ?></li><?php endforeach ?></ul><?php endif ?>
  <?php else: ?><p><?= e($T('u_none')) ?></p><?php endif ?>
</div>

<?php if (isset($speed['old']) || isset($speed['new'])): ?>
<h2><?= e($T('h_speed')) ?> <small><?= e($T('h_speed_small')) ?></small></h2>
<div class="panel"><table>
  <thead><tr><th><?= e($T('m_metric')) ?></th><th class="n"><?= e($T('m_before')) ?></th><th class="n"><?= e($T('m_after')) ?></th><th class="n"><?= e($T('m_change')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($metrics as [$key, $label, $fmt, $higher]):
      $a = $speed['old'][$key] ?? null;
      $z = $speed['new'][$key] ?? null;
      $delta = '';
      if ($a !== null && $z !== null && (float) $a > 0) {
          $change = $higher ? (float) $z - (float) $a : 100 * ((float) $z - (float) $a) / (float) $a;
          $good = $higher ? $change > 0 : $change < 0;
          $delta = '<span class="' . ($change == 0 ? '' : ($good ? 'better' : 'worse')) . '">' . ($change > 0 ? '+' : '') . e(number_format($change, 0, ',', ' ')) . ($higher ? ' pts' : ' %') . '</span>';
      }
      ?>
    <tr><td><?= e($T($label)) ?></td><td class="n"><?= e($fmt($a)) ?></td><td class="n"><strong><?= e($fmt($z)) ?></strong></td><td class="n"><?= $delta ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table>
<p class="more"><?= e($T('speed_note', ['old' => (string) ($speed['old']['url'] ?? '—'), 'new' => (string) ($speed['new']['url'] ?? '—')])) ?></p></div>
<?php endif ?>

<h2><?= e($T('h_contents')) ?> <small><?= e($T('h_contents_small', ['posts' => $r['posts'], 'pages' => $r['pages'], 'products' => $r['products'], 'other' => $r['custom']])) ?></small></h2>
<div class="panel"><table>
  <thead><tr><th><?= e($T('t_title')) ?></th><th><?= e($T('t_old')) ?></th><th><?= e($T('t_new')) ?></th><th><?= e($T('t_state')) ?></th></tr></thead>
  <tbody>
  <?php foreach (array_slice($ledger['items'], 0, self::ROWS) as [$type, $title, $old, $newPath, $state]): ?>
    <tr><td><?= e($title) ?><br><small><?= e($T($types[$type] ?? 'type_other')) ?></small></td><td class="url"><?= e((string) (parse_url((string) $old, PHP_URL_PATH) ?? '') . ((string) parse_url((string) $old, PHP_URL_QUERY) !== '' ? '?' . parse_url((string) $old, PHP_URL_QUERY) : '')) ?></td><td class="url"><?= e($newPath) ?></td>
      <td><span class="pill <?= $state === 'kept' || $state === 'home' ? 'ok' : ($state === 'moved' ? 'info' : 'warn') ?>"><?= e($T($states[$state] ?? 'state_kept')) ?></span></td></tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php if (count($ledger['items']) > self::ROWS): ?><p class="more"><?= e($T('and_more', ['n' => $n(count($ledger['items']) - self::ROWS)])) ?></p><?php endif ?></div>

<?php if ($ledger['redirects']): ?>
<h2><?= e($T('h_redirects')) ?> <small><?= e($T('h_redirects_small', ['n' => $n(count($ledger['redirects'])), 'aliases' => $n($r['aliases'])])) ?></small></h2>
<div class="panel"><table>
  <thead><tr><th><?= e($T('r_from')) ?></th><th><?= e($T('r_to')) ?></th><th><?= e($T('r_origin')) ?></th></tr></thead>
  <tbody>
  <?php foreach (array_slice($ledger['redirects'], 0, self::ROWS) as [$from, $to, $origin]): ?>
    <tr><td class="url"><?= e($from) ?></td><td class="url"><?= e($to) ?></td><td><span class="pill <?= $origin === 'site' ? 'ok' : 'info' ?>"><?= e($T($origin === 'site' ? 'r_site' : 'r_migration')) ?></span></td></tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php if (count($ledger['redirects']) > self::ROWS): ?><p class="more"><?= e($T('and_more', ['n' => $n(count($ledger['redirects']) - self::ROWS)])) ?></p><?php endif ?></div>
<?php endif ?>

<h2><?= e($T('h_media')) ?></h2>
<div class="panel"><table><tbody>
  <tr><td><?= e($T('md_images')) ?></td><td class="n"><?= e($n($r['images'])) ?></td></tr>
  <tr><td><?= e($T('md_docs')) ?></td><td class="n"><?= e($n($r['documents'])) ?></td></tr>
  <tr><td><?= e($T('md_skipped')) ?></td><td class="n"><?= e($n($r['media_skipped'])) ?></td></tr>
  <tr><td><?= e($T('md_missing')) ?></td><td class="n"><?= e($n($r['media_missing'])) ?></td></tr>
  <tr><td><?= e($T('md_links')) ?></td><td class="n"><?= e($n($r['links'])) ?></td></tr>
  <tr><td><?= e($T('md_seo')) ?></td><td class="n"><?= e($n($r['seo'])) ?></td></tr>
</tbody></table></div>

<h2><?= e($T('h_left')) ?> <small><?= e($n(count($r['not_migrated']))) ?></small></h2>
<div class="panel">
  <?php if (!$r['not_migrated']): ?><p class="pad"><?= e($T('left_none')) ?></p>
  <?php else: ?><table><thead><tr><th><?= e($T('l_item')) ?></th><th><?= e($T('l_reason')) ?></th></tr></thead><tbody>
    <?php foreach ($r['not_migrated'] as [$what, $name, $why]): ?><tr><td><?= e($name) ?><br><small><?= e($T('k_' . $what)) ?></small></td><td><?= e($why) ?></td></tr><?php endforeach ?>
  </tbody></table><?php endif ?>
</div>

<h2><?= e($T('h_checklist')) ?></h2>
<div class="panel">
  <?php foreach ($checks as [$key, $ok]): ?>
    <div class="check"><span class="box <?= $ok ? 'yes' : 'no' ?>"><?= $ok ? '✓' : '!' ?></span><div><strong><?= e($T('ck_' . $key)) ?></strong><p><?= e($T('ck_' . $key . ($ok ? '_ok' : '_no'))) ?></p></div></div>
  <?php endforeach ?>
  <?php foreach (['submit', 'address', 'analytics', 'forms', 'watch', 'old'] as $key): ?>
    <div class="check"><span class="box todo"></span><div><strong><?= e($T('todo_' . $key)) ?></strong><p><?= e($T('todo_' . $key . '_p')) ?></p></div></div>
  <?php endforeach ?>
</div>

<section class="cta">
  <div><h2><?= e($T('h_next')) ?></h2><p><?= e($b['note'] !== '' ? $b['note'] : $T('next_default')) ?></p></div>
  <?php if ($b['name'] . $b['company'] . $b['email'] . $b['phone'] . $b['website'] !== ''): ?>
  <div class="who"><?= e($T('prepared')) ?><br><strong><?= e((string) preg_replace('~^[ ·]+|[ ·]+$~u', '', $b['name'] . ($b['company'] !== '' ? ' · ' . $b['company'] : ''))) ?></strong><br>
    <?php if ($b['email'] !== ''): ?><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a><br><?php endif ?>
    <?php if ($b['phone'] !== ''): ?><?= e($b['phone']) ?><br><?php endif ?>
    <?php if ($b['website'] !== ''): ?><a href="<?= e($b['website']) ?>"><?= e((string) preg_replace('~^https?://~', '', rtrim($b['website'], '/'))) ?></a><?php endif ?></div>
  <?php endif ?>
</section>
<footer><?= e($T('footer')) ?></footer>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    /** Textes du rapport (fr, es, en) : la langue du client, indépendante de celle de l'administration. */
    private const TEXT = [
        'title' => ['fr' => 'Rapport de migration – {site}', 'es' => 'Informe de migración – {site}', 'en' => 'Migration report – {site}'],
        'heading' => ['fr' => 'Rapport de migration vers Éclair', 'es' => 'Informe de migración a Éclair', 'en' => 'Migration report to Éclair'],
        'date' => ['fr' => 'Migration du {date}', 'es' => 'Migración del {date}', 'en' => 'Migration of {date}'],
        'print' => ['fr' => 'Pour un PDF : Fichier › Imprimer › Enregistrer au format PDF.', 'es' => 'Para un PDF: Archivo › Imprimir › Guardar como PDF.', 'en' => 'For a PDF: File › Print › Save as PDF.'],
        'new_site' => ['fr' => 'nouveau site Éclair', 'es' => 'nuevo sitio Éclair', 'en' => 'new Éclair site'],
        'dry' => ['fr' => 'Simulation : rien n’a encore été écrit. Ce rapport montre ce que l’import fera.', 'es' => 'Simulación: todavía no se ha escrito nada. Este informe muestra lo que hará la importación.', 'en' => 'Simulation: nothing has been written yet. This report shows what the import will do.'],
        'verdict_ok' => ['fr' => 'Migration réussie : les {n} anciennes adresses publiques mènent toutes à une page, aucune ne finit en erreur 404.', 'es' => 'Migración completada: las {n} direcciones públicas antiguas llevan todas a una página, ninguna termina en error 404.', 'en' => 'Successful migration: all {n} old public addresses lead to a page, none ends on a 404 error.'],
        'verdict_warn' => ['fr' => 'Migration faite, avec {n} anciennes adresses encore sans destination : elles sont listées plus bas pour être redirigées.', 'es' => 'Migración hecha, con {n} direcciones antiguas aún sin destino: se listan más abajo para redirigirlas.', 'en' => 'Migration done, with {n} old addresses still without a destination: they are listed below to be redirected.'],
        'c_contents' => ['fr' => 'contenus importés', 'es' => 'contenidos importados', 'en' => 'contents imported'],
        'c_kept' => ['fr' => 'adresses gardées à l’identique', 'es' => 'direcciones conservadas tal cual', 'en' => 'addresses kept exactly'],
        'c_redirects' => ['fr' => 'redirections 301 en place', 'es' => 'redirecciones 301 activas', 'en' => '301 redirections in place'],
        'c_media' => ['fr' => 'images et documents', 'es' => 'imágenes y documentos', 'en' => 'images and documents'],
        'h_urls' => ['fr' => 'Les anciennes adresses', 'es' => 'Las direcciones antiguas', 'en' => 'The old addresses'],
        'h_urls_small' => ['fr' => '{n} adresses publiques de l’ancien site vérifiées une par une', 'es' => '{n} direcciones públicas del sitio antiguo comprobadas una a una', 'en' => '{n} public addresses of the old site checked one by one'],
        'urls_legend' => ['fr' => '{ok} servies, {redirect} redirigées, {missing} introuvables', 'es' => '{ok} servidas, {redirect} redirigidas, {missing} no encontradas', 'en' => '{ok} served, {redirect} redirected, {missing} not found'],
        'u_ok' => ['fr' => 'servies à la même adresse', 'es' => 'servidas en la misma dirección', 'en' => 'served at the same address'],
        'u_redirect' => ['fr' => 'redirigées en 301 vers la bonne page', 'es' => 'redirigidas con un 301 a la página correcta', 'en' => 'redirected with a 301 to the right page'],
        'u_missing' => ['fr' => 'introuvables (404)', 'es' => 'no encontradas (404)', 'en' => 'not found (404)'],
        'u_reached' => ['fr' => 'des anciennes adresses mènent à une page', 'es' => 'de las direcciones antiguas llevan a una página', 'en' => 'of the old addresses lead to a page'],
        'u_list' => ['fr' => 'Adresses à rediriger :', 'es' => 'Direcciones por redirigir:', 'en' => 'Addresses to redirect:'],
        'u_none' => ['fr' => 'Aucune ancienne adresse publique dans le paquet.', 'es' => 'Ninguna dirección pública antigua en el paquete.', 'en' => 'No old public address in the package.'],
        'h_speed' => ['fr' => 'Vitesse avant / après', 'es' => 'Velocidad antes / después', 'en' => 'Speed before / after'],
        'h_speed_small' => ['fr' => 'Google PageSpeed Insights, page d’accueil sur mobile', 'es' => 'Google PageSpeed Insights, página de inicio en móvil', 'en' => 'Google PageSpeed Insights, home page on mobile'],
        'm_metric' => ['fr' => 'Mesure', 'es' => 'Medida', 'en' => 'Metric'],
        'm_before' => ['fr' => 'Avant (WordPress)', 'es' => 'Antes (WordPress)', 'en' => 'Before (WordPress)'],
        'm_after' => ['fr' => 'Après (Éclair)', 'es' => 'Después (Éclair)', 'en' => 'After (Éclair)'],
        'm_change' => ['fr' => 'Évolution', 'es' => 'Evolución', 'en' => 'Change'],
        'm_score' => ['fr' => 'Score de performance (sur 100)', 'es' => 'Puntuación de rendimiento (sobre 100)', 'en' => 'Performance score (out of 100)'],
        'm_lcp' => ['fr' => 'Affichage du contenu principal (LCP)', 'es' => 'Carga del contenido principal (LCP)', 'en' => 'Largest Contentful Paint (LCP)'],
        'm_fcp' => ['fr' => 'Premier affichage (FCP)', 'es' => 'Primera visualización (FCP)', 'en' => 'First Contentful Paint (FCP)'],
        'm_tbt' => ['fr' => 'Temps de blocage (TBT)', 'es' => 'Tiempo de bloqueo (TBT)', 'en' => 'Total Blocking Time (TBT)'],
        'm_cls' => ['fr' => 'Stabilité visuelle (CLS)', 'es' => 'Estabilidad visual (CLS)', 'en' => 'Cumulative Layout Shift (CLS)'],
        'm_si' => ['fr' => 'Indice de vitesse', 'es' => 'Índice de velocidad', 'en' => 'Speed Index'],
        'm_bytes' => ['fr' => 'Poids de la page', 'es' => 'Peso de la página', 'en' => 'Page weight'],
        'm_requests' => ['fr' => 'Fichiers chargés', 'es' => 'Archivos cargados', 'en' => 'Files loaded'],
        'speed_note' => ['fr' => 'Mesuré sur {old} et {new}. Une mesure varie de quelques points d’un essai à l’autre.', 'es' => 'Medido en {old} y {new}. Una medida varía unos puntos de un intento a otro.', 'en' => 'Measured on {old} and {new}. A measurement varies by a few points from one run to the next.'],
        'h_contents' => ['fr' => 'Contenus importés', 'es' => 'Contenidos importados', 'en' => 'Contents imported'],
        'h_contents_small' => ['fr' => '{posts} articles, {pages} pages, {products} produits, {other} autres', 'es' => '{posts} artículos, {pages} páginas, {products} productos, {other} otros', 'en' => '{posts} posts, {pages} pages, {products} products, {other} others'],
        't_title' => ['fr' => 'Titre', 'es' => 'Título', 'en' => 'Title'],
        't_old' => ['fr' => 'Ancienne adresse', 'es' => 'Dirección antigua', 'en' => 'Old address'],
        't_new' => ['fr' => 'Nouvelle adresse', 'es' => 'Nueva dirección', 'en' => 'New address'],
        't_state' => ['fr' => 'État', 'es' => 'Estado', 'en' => 'Status'],
        'type_post' => ['fr' => 'Article', 'es' => 'Artículo', 'en' => 'Post'],
        'type_page' => ['fr' => 'Page', 'es' => 'Página', 'en' => 'Page'],
        'type_product' => ['fr' => 'Produit', 'es' => 'Producto', 'en' => 'Product'],
        'type_other' => ['fr' => 'Autre type de contenu', 'es' => 'Otro tipo de contenido', 'en' => 'Other content type'],
        'state_kept' => ['fr' => 'Adresse identique', 'es' => 'Misma dirección', 'en' => 'Same address'],
        'state_moved' => ['fr' => 'Redirigée (301)', 'es' => 'Redirigida (301)', 'en' => 'Redirected (301)'],
        'state_home' => ['fr' => 'Page d’accueil', 'es' => 'Página de inicio', 'en' => 'Home page'],
        'state_draft' => ['fr' => 'Brouillon', 'es' => 'Borrador', 'en' => 'Draft'],
        'and_more' => ['fr' => '… et {n} autres.', 'es' => '… y {n} más.', 'en' => '… and {n} more.'],
        'h_redirects' => ['fr' => 'Redirections créées', 'es' => 'Redirecciones creadas', 'en' => 'Redirections created'],
        'h_redirects_small' => ['fr' => '{n} redirections, plus {aliases} anciens liens courts (?p=)', 'es' => '{n} redirecciones, más {aliases} enlaces cortos antiguos (?p=)', 'en' => '{n} redirections, plus {aliases} old short links (?p=)'],
        'r_from' => ['fr' => 'Ancienne adresse', 'es' => 'Dirección antigua', 'en' => 'Old address'],
        'r_to' => ['fr' => 'Mène à', 'es' => 'Lleva a', 'en' => 'Leads to'],
        'r_origin' => ['fr' => 'Origine', 'es' => 'Origen', 'en' => 'Origin'],
        'r_site' => ['fr' => 'Reprise de l’ancien site', 'es' => 'Del sitio antiguo', 'en' => 'From the old site'],
        'r_migration' => ['fr' => 'Créée pour la migration', 'es' => 'Creada para la migración', 'en' => 'Created for the migration'],
        'h_media' => ['fr' => 'Médias, liens et SEO', 'es' => 'Medios, enlaces y SEO', 'en' => 'Media, links and SEO'],
        'md_images' => ['fr' => 'Images optimisées (WebP)', 'es' => 'Imágenes optimizadas (WebP)', 'en' => 'Optimised images (WebP)'],
        'md_docs' => ['fr' => 'Documents (PDF, bureautique)', 'es' => 'Documentos (PDF, ofimática)', 'en' => 'Documents (PDF, office files)'],
        'md_skipped' => ['fr' => 'Fichiers non repris (vidéos, sons…)', 'es' => 'Archivos no recuperados (vídeos, audio…)', 'en' => 'Files not taken over (videos, audio…)'],
        'md_missing' => ['fr' => 'Fichiers introuvables', 'es' => 'Archivos no encontrados', 'en' => 'Files not found'],
        'md_links' => ['fr' => 'Liens internes réécrits vers les nouvelles adresses', 'es' => 'Enlaces internos reescritos a las nuevas direcciones', 'en' => 'Internal links rewritten to the new addresses'],
        'md_seo' => ['fr' => 'Pages avec titre et description SEO repris', 'es' => 'Páginas con título y descripción SEO recuperados', 'en' => 'Pages with SEO title and description taken over'],
        'h_left' => ['fr' => 'Éléments non migrés', 'es' => 'Elementos no migrados', 'en' => 'Items not migrated'],
        'left_none' => ['fr' => 'Tout a été repris.', 'es' => 'Todo se ha recuperado.', 'en' => 'Everything was taken over.'],
        'l_item' => ['fr' => 'Élément', 'es' => 'Elemento', 'en' => 'Item'],
        'l_reason' => ['fr' => 'Raison', 'es' => 'Motivo', 'en' => 'Reason'],
        'k_shortcode' => ['fr' => 'Code court d’extension', 'es' => 'Shortcode de plugin', 'en' => 'Plugin shortcode'],
        'k_widget' => ['fr' => 'Élément de mise en page', 'es' => 'Elemento de diseño', 'en' => 'Layout element'],
        'k_media' => ['fr' => 'Fichier', 'es' => 'Archivo', 'en' => 'File'],
        'k_redirect' => ['fr' => 'Redirection', 'es' => 'Redirección', 'en' => 'Redirection'],
        'k_menu' => ['fr' => 'Lien de menu', 'es' => 'Enlace de menú', 'en' => 'Menu link'],
        'k_type' => ['fr' => 'Type de contenu', 'es' => 'Tipo de contenido', 'en' => 'Content type'],
        'k_content' => ['fr' => 'Contenu', 'es' => 'Contenido', 'en' => 'Content'],
        'k_category' => ['fr' => 'Catégorie', 'es' => 'Categoría', 'en' => 'Category'],
        'h_checklist' => ['fr' => 'Liste de contrôle SEO', 'es' => 'Lista de control SEO', 'en' => 'SEO checklist'],
        'ck_sitemap' => ['fr' => 'Plan du site (sitemap.xml)', 'es' => 'Mapa del sitio (sitemap.xml)', 'en' => 'Sitemap (sitemap.xml)'],
        'ck_sitemap_ok' => ['fr' => 'Généré automatiquement et tenu à jour à chaque publication.', 'es' => 'Generado automáticamente y actualizado en cada publicación.', 'en' => 'Generated automatically and kept up to date on every publication.'],
        'ck_sitemap_no' => ['fr' => 'Le site demande encore aux moteurs de ne pas l’indexer : à ouvrir dans Réglages › SEO avant la mise en ligne.', 'es' => 'El sitio aún pide a los buscadores que no lo indexen: abrirlo en Ajustes › SEO antes de publicarlo.', 'en' => 'The site still asks search engines not to index it: open it in Settings › SEO before going live.'],
        'ck_robots' => ['fr' => 'robots.txt', 'es' => 'robots.txt', 'en' => 'robots.txt'],
        'ck_robots_ok' => ['fr' => 'Autorise l’indexation et indique le plan du site.', 'es' => 'Permite la indexación e indica el mapa del sitio.', 'en' => 'Allows indexing and points to the sitemap.'],
        'ck_robots_no' => ['fr' => 'Bloque l’indexation pour le moment.', 'es' => 'Bloquea la indexación por ahora.', 'en' => 'Blocks indexing for now.'],
        'ck_domain' => ['fr' => 'Domaine officiel vérifié', 'es' => 'Dominio oficial verificado', 'en' => 'Official domain verified'],
        'ck_domain_ok' => ['fr' => 'Toutes les variantes (www, http) mènent à une seule adresse.', 'es' => 'Todas las variantes (www, http) llevan a una sola dirección.', 'en' => 'Every variant (www, http) leads to a single address.'],
        'ck_domain_no' => ['fr' => 'À vérifier dans Réglages une fois le domaine pointé vers Éclair.', 'es' => 'Por verificar en Ajustes una vez el dominio apunte a Éclair.', 'en' => 'To verify in Settings once the domain points to Éclair.'],
        'ck_https' => ['fr' => 'HTTPS', 'es' => 'HTTPS', 'en' => 'HTTPS'],
        'ck_https_ok' => ['fr' => 'Le site est servi en HTTPS.', 'es' => 'El sitio se sirve en HTTPS.', 'en' => 'The site is served over HTTPS.'],
        'ck_https_no' => ['fr' => 'Activer le certificat HTTPS chez l’hébergeur.', 'es' => 'Activar el certificado HTTPS en el alojamiento.', 'en' => 'Turn on the HTTPS certificate at the host.'],
        'ck_canonical' => ['fr' => 'Adresses canoniques', 'es' => 'Direcciones canónicas', 'en' => 'Canonical addresses'],
        'ck_canonical_ok' => ['fr' => 'Chaque page déclare son adresse officielle.', 'es' => 'Cada página declara su dirección oficial.', 'en' => 'Every page declares its official address.'],
        'ck_canonical_no' => ['fr' => 'Renseigner l’adresse du site dans Réglages.', 'es' => 'Indicar la dirección del sitio en Ajustes.', 'en' => 'Fill in the site address in Settings.'],
        'ck_console' => ['fr' => 'Google Search Console', 'es' => 'Google Search Console', 'en' => 'Google Search Console'],
        'ck_console_ok' => ['fr' => 'Le site est vérifié auprès de Google.', 'es' => 'El sitio está verificado ante Google.', 'en' => 'The site is verified with Google.'],
        'ck_console_no' => ['fr' => 'Coller la balise de vérification dans Réglages › SEO.', 'es' => 'Pegar la etiqueta de verificación en Ajustes › SEO.', 'en' => 'Paste the verification tag in Settings › SEO.'],
        'ck_bing' => ['fr' => 'Bing Webmaster Tools', 'es' => 'Bing Webmaster Tools', 'en' => 'Bing Webmaster Tools'],
        'ck_bing_ok' => ['fr' => 'Le site est vérifié auprès de Bing (et Bing est prévenu à chaque publication).', 'es' => 'El sitio está verificado ante Bing (y Bing recibe aviso en cada publicación).', 'en' => 'The site is verified with Bing (and Bing is notified on every publication).'],
        'ck_bing_no' => ['fr' => 'Facultatif : coller la balise de Bing dans Réglages › SEO.', 'es' => 'Opcional: pegar la etiqueta de Bing en Ajustes › SEO.', 'en' => 'Optional: paste the Bing tag in Settings › SEO.'],
        'ck_noindex' => ['fr' => 'Pages exclues de Google', 'es' => 'Páginas excluidas de Google', 'en' => 'Pages excluded from Google'],
        'ck_noindex_ok' => ['fr' => 'Aucune page publiée n’est exclue de l’indexation.', 'es' => 'Ninguna página publicada está excluida de la indexación.', 'en' => 'No published page is excluded from indexing.'],
        'ck_noindex_no' => ['fr' => 'Certaines pages étaient en « noindex » sur l’ancien site et le restent : à confirmer.', 'es' => 'Algunas páginas estaban en «noindex» en el sitio antiguo y lo siguen estando: por confirmar.', 'en' => 'Some pages were "noindex" on the old site and remain so: to confirm.'],
        'ck_404' => ['fr' => 'Zéro erreur 404 sur les anciennes adresses', 'es' => 'Cero errores 404 en las direcciones antiguas', 'en' => 'Zero 404 errors on the old addresses'],
        'ck_404_ok' => ['fr' => 'Chaque ancienne adresse publique mène à une page.', 'es' => 'Cada dirección pública antigua lleva a una página.', 'en' => 'Every old public address leads to a page.'],
        'ck_404_no' => ['fr' => 'Des anciennes adresses restent à rediriger (liste plus haut).', 'es' => 'Quedan direcciones antiguas por redirigir (lista más arriba).', 'en' => 'Some old addresses still need a redirection (list above).'],
        'todo_submit' => ['fr' => 'Envoyer le plan du site à Google', 'es' => 'Enviar el mapa del sitio a Google', 'en' => 'Submit the sitemap to Google'],
        'todo_submit_p' => ['fr' => 'Search Console › Sitemaps : ajouter /sitemap.xml le jour de la mise en ligne.', 'es' => 'Search Console › Sitemaps: añadir /sitemap.xml el día de la publicación.', 'en' => 'Search Console › Sitemaps: add /sitemap.xml on launch day.'],
        'todo_address' => ['fr' => 'Changement de domaine ?', 'es' => '¿Cambio de dominio?', 'en' => 'Domain change?'],
        'todo_address_p' => ['fr' => 'Si l’adresse du site change, utiliser l’outil « Changement d’adresse » de Search Console.', 'es' => 'Si la dirección del sitio cambia, usar la herramienta «Cambio de dirección» de Search Console.', 'en' => 'If the site address changes, use the Search Console "Change of address" tool.'],
        'todo_analytics' => ['fr' => 'Mesure d’audience', 'es' => 'Medición de audiencia', 'en' => 'Analytics'],
        'todo_analytics_p' => ['fr' => 'Vérifier que l’outil de statistiques reçoit bien les visites du nouveau site.', 'es' => 'Comprobar que la herramienta de estadísticas recibe las visitas del nuevo sitio.', 'en' => 'Check that the analytics tool receives the visits of the new site.'],
        'todo_forms' => ['fr' => 'Formulaires et boutons d’achat', 'es' => 'Formularios y botones de compra', 'en' => 'Forms and buy buttons'],
        'todo_forms_p' => ['fr' => 'Envoyer un message de test et cliquer sur chaque lien de paiement.', 'es' => 'Enviar un mensaje de prueba y hacer clic en cada enlace de pago.', 'en' => 'Send a test message and click every payment link.'],
        'todo_watch' => ['fr' => 'Suivi des 4 premières semaines', 'es' => 'Seguimiento de las 4 primeras semanas', 'en' => 'Follow-up over the first 4 weeks'],
        'todo_watch_p' => ['fr' => 'Search Console › Pages : surveiller les erreurs et le nombre de pages indexées chaque semaine.', 'es' => 'Search Console › Páginas: vigilar los errores y el número de páginas indexadas cada semana.', 'en' => 'Search Console › Pages: watch errors and the number of indexed pages every week.'],
        'todo_old' => ['fr' => 'Ancien hébergement', 'es' => 'Alojamiento antiguo', 'en' => 'Old hosting'],
        'todo_old_p' => ['fr' => 'Garder une sauvegarde de l’ancien WordPress au moins 3 mois avant de le supprimer.', 'es' => 'Conservar una copia del WordPress antiguo al menos 3 meses antes de borrarlo.', 'en' => 'Keep a backup of the old WordPress for at least 3 months before deleting it.'],
        'h_next' => ['fr' => 'Et maintenant ?', 'es' => '¿Y ahora?', 'en' => 'What now?'],
        'next_default' => ['fr' => 'Votre site est plus rapide, plus sûr et plus simple à faire vivre. Les points de la liste de contrôle restent à cocher le jour de la mise en ligne.', 'es' => 'Su sitio es más rápido, más seguro y más fácil de mantener. Los puntos de la lista de control quedan por marcar el día de la publicación.', 'en' => 'Your site is faster, safer and simpler to run. The checklist items remain to be ticked on launch day.'],
        'prepared' => ['fr' => 'Préparé par', 'es' => 'Preparado por', 'en' => 'Prepared by'],
        'footer' => ['fr' => 'Rapport généré par Éclair CMS à partir de l’import du paquet Eclair Export.', 'es' => 'Informe generado por Éclair CMS a partir de la importación del paquete Eclair Export.', 'en' => 'Report generated by Éclair CMS from the import of the Eclair Export package.'],
    ];
}
