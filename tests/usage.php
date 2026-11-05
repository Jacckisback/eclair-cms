<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Usages du site : trois nouveaux types à l'installation (média / Google Discover, affiliation, ancien site à relancer),
 * réglage « Usage du site », tableau de bord qui met en premier les outils de l'usage et étape propre dans « Premiers pas ».
 * Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Starter;
use Eclair\Store;
use Eclair\Usage;

section('Usages du site (unitaire)');
$usageKeys = array_keys(Usage::all());
sort($usageKeys);
$typeKeys = Starter::TYPES;
sort($typeKeys);
check('7 usages proposés, ceux de l’installation : vitrine, blog, freelance, page unique, média, affiliation, ancien site', $usageKeys === $typeKeys
    && count($usageKeys) === 7 && array_key_first(Usage::all()) === 'business' && array_intersect(['media', 'affiliate', 'revival'], Starter::TYPES) === ['media', 'affiliate', 'revival']);

$htDir = sys_get_temp_dir() . '/eclair-ht-' . getmypid();
@mkdir($htDir);
check('installation : .htaccess absent sur Apache ou LiteSpeed signalé, jamais sur nginx ni quand il est là', \Eclair\Installer::htaccessMissing($htDir, 'Apache/2.4.62 (Unix)')
    && \Eclair\Installer::htaccessMissing($htDir, 'LiteSpeed') && !\Eclair\Installer::htaccessMissing($htDir, 'nginx/1.27') && !\Eclair\Installer::htaccessMissing(ECLAIR_ROOT, 'Apache'));
@rmdir($htDir);

/* ─────────────────────────── Parcours HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Usages du site : installation, tableau de bord adapté, réglage');
[$copy, $port, $server] = serve('usage');
$read = static fn (string $file): array => json_decode(substr((string) @file_get_contents($copy . '/data/' . $file . '.php'), strlen(Store::GUARD)), true) ?: [];
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    check('installation : 7 types de site, dont média, affiliation et ancien site', substr_count($form['body'], 'name="site_type"') === 7
        && str_contains($form['body'], 'value="media"') && str_contains($form['body'], 'Média / Google Discover')
        && str_contains($form['body'], 'value="affiliate"') && str_contains($form['body'], 'value="revival"') && str_contains($form['body'], 'Relancer un ancien site'));
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Le Fil du Pain', 'site_tagline' => 'Le magazine du pain au levain', 'site_lang' => 'fr', 'site_type' => 'media',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $site = $read('site');
    $menu = $read('menus')['main'] ?? [];
    check('type « média » : usage enregistré, blog en accueil, menu À propos et Contact comme un blog', ($site['usage'] ?? '') === 'media'
        && ($site['blog']['enabled'] ?? false) === true && ($site['home'] ?? 'x') === '' && count($menu) === 2, json_encode($menu));
    check('site public d’un média : blog et article de départ en ligne', $c->req('GET', '/')['status'] === 200 && str_contains($c->req('GET', '/feed.xml')['body'], '<item>'));
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $dash = $c->req('GET', '/gestion/');
    $tiles = preg_match_all('~<a class="usage-tile" href="([^"]+)">.*?<span class="usage-name">([^<]+)</span>~s', $dash['body'], $m) ? array_combine($m[2], $m[1]) : [];
    check('tableau de bord d’un média : carte « Ton média » avec Discover, tendances, planning, publicité', str_contains($dash['body'], 'Ton média')
        && array_keys($tiles) === ['Prêt pour Discover', 'Tendances du jour', 'Planning', 'Publicité'] && $tiles['Tendances du jour'] === '/gestion/discover#trends', json_encode($tiles, JSON_UNESCAPED_UNICODE));
    preg_match_all('~class="todo-label"[^>]*>([^<]+)<~', $dash['body'], $steps);
    check('« Premiers pas » d’un média : préparer un article pour Discover, juste après l’installation', ($steps[1][1] ?? '') === 'Préparer un article pour Google Discover'
        && count($steps[1]) === 9, json_encode($steps[1], JSON_UNESCAPED_UNICODE));
    check('tableau de bord sans attribut style (CSP)', !preg_match('~<[^>]+\sstyle="~', $dash['body']));

    settings($copy, ['usage' => 'affiliate']);
    $dash = $c->req('GET', '/gestion/');
    $tiles = preg_match_all('~<a class="usage-tile" href="([^"]+)">.*?<span class="usage-name">([^<]+)</span>\s*<span class="usage-state[^"]*">([^<]+)</span>~s', $dash['body'], $m)
        ? array_combine($m[2], $m[3]) : [];
    check('affiliation : liens, article par l’IA, planning, Discover ; aucun lien encore', str_contains($dash['body'], 'Ton site d’affiliation')
        && array_keys($tiles) === ['Liens d’affiliation', 'Article par l’IA', 'Planning', 'Prêt pour Discover'] && $tiles['Liens d’affiliation'] === 'Aucun lien'
        && $tiles['Article par l’IA'] === 'Active l’assistant IA', json_encode($tiles, JSON_UNESCAPED_UNICODE));
    check('affiliation : « Ajouter ton premier lien d’affiliation » dans les premiers pas', str_contains($dash['body'], 'Ajouter ton premier lien d’affiliation'));

    settings($copy, ['usage' => 'revival']);
    $dash = $c->req('GET', '/gestion/');
    check('ancien site : recherche dans la Wayback Machine « À lancer », redirections, brouillons, Discover', str_contains($dash['body'], 'Ton ancien site')
        && str_contains($dash['body'], 'href="/gestion/wayback"') && str_contains($dash['body'], 'À lancer') && str_contains($dash['body'], 'Retrouver les anciennes adresses de ton domaine')
        && str_contains($dash['body'], 'href="/gestion/redirects"'));

    settings($copy, ['usage' => 'business']);
    $dash = $c->req('GET', '/gestion/');
    check('site vitrine : messages, page d’accueil ; pas d’étape en plus', str_contains($dash['body'], 'Ton site vitrine') && str_contains($dash['body'], 'Aucun en attente')
        && !str_contains($dash['body'], 'Préparer un article pour Google Discover') && preg_match_all('~class="todo-label"~', $dash['body']) === 8);

    settings($copy, ['usage' => '']);
    $dash = $c->req('GET', '/gestion/');
    check('usage non précisé (site d’avant la 1.14) : tableau de bord de toujours, sans carte d’usage', $dash['status'] === 200 && !str_contains($dash['body'], 'usage-tile')
        && preg_match_all('~class="todo-label"~', $dash['body']) === 8);

    $general = $c->req('GET', '/gestion/settings?tab=general');
    check('Réglages › Général : choix de l’usage parmi les 7, aucun sélectionné', str_contains($general['body'], 'name="usage"') && str_contains($general['body'], '<option value="">Non précisé</option>')
        && substr_count($general['body'], '<option value="revival"') === 1 && !preg_match('~<option value="(business|blog|freelance|landing|media|affiliate|revival)" selected>~', $general['body']));
    $fields = ['tab' => 'general', 'name' => 'Le Fil du Pain', 'lang' => 'fr', 'timezone' => 'Europe/Paris', 'url' => 'http://127.0.0.1:' . $port,
        'blog_enabled' => '1', 'blog_base' => 'blog', 'blog_title' => 'Blog', 'per_page' => '9', 'search' => '1'];
    $c->req('POST', '/gestion/settings', $c->token($general['body']) + $fields + ['usage' => 'blog']);
    check('usage enregistré depuis les réglages', ($read('site')['usage'] ?? '') === 'blog' && str_contains($c->req('GET', '/gestion/')['body'], 'Ton blog'));
    $c->req('POST', '/gestion/settings', $c->token($c->req('GET', '/gestion/settings?tab=general')['body']) + $fields + ['usage' => '<script>']);
    check('usage inconnu : « Non précisé »', ($read('site')['usage'] ?? 'x') === '');
} finally {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($copy));
}
