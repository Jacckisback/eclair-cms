<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Routage compatible avec une migration (inclus par tests/run.php) : alias WordPress à paramètres résolus avant le cache,
 * paramètres marketing sans variante de cache, aucune redirection ouverte ni chaîne.
 */
declare(strict_types=1);

use Eclair\Aliases;
use Eclair\Content;
use Eclair\Site;
use Eclair\Store;

(static function (array $argv): void {
    section('Alias d’un ancien site (/?p=123) et paramètres marketing');
    check('alias reconnus : ?p=, ?page_id=, ?attachment_id= (numériques)', Aliases::key(['p' => '123']) === 'p:123' && Aliases::key(['page_id' => '45']) === 'page_id:45'
        && Aliases::key(['attachment_id' => '7']) === 'attachment_id:7' && Aliases::key(['utm_source' => 'x']) === '' && Aliases::key(['p' => 'abc']) === 'p:?');
    check('alias : jamais une adresse externe ni protocole-relative', !Aliases::internal('//evil.example/x') && !Aliases::internal('https://evil.example')
        && !Aliases::internal("/a\\b") && !Aliases::internal("/a\nb") && Aliases::internal('/blog/mon-article'));
    $n = Aliases::add(['p:123' => '/a-propos', 'p:9' => '//evil.example', 'x:1' => '/a', 'page_id:45' => '/contact']);
    check('ajout : seules les entrées valides sont gardées', $n === 2 && Aliases::resolve('p:123') === '/a-propos' && Aliases::resolve('p:9') === null);
    Store::delete('aliases');

    section('Adresses exactes d’un ancien site (/2024/03/article/, .html, accents)');
    check('adresses exactes acceptées : dates, « / » final, .html, accents', Content::cleanRoute('/2024/03/mon-article/') === '/2024/03/mon-article/'
        && Content::cleanRoute('/cafe-creme.html') === '/cafe-creme.html' && Content::cleanRoute('/café/crème') === '/café/crème' && Content::cleanRoute(' /a ') === '/a');
    check('adresses exactes refusées : réservées, scripts, remontée, doubles « / », contrôle, trop longues', Content::cleanRoute('/media/x') === ''
        && Content::cleanRoute('/admin/x') === '' && Content::cleanRoute('/sitemap.xml') === '' && Content::cleanRoute('/a.php') === '' && Content::cleanRoute('/../etc') === ''
        && Content::cleanRoute('/a//b') === '' && Content::cleanRoute("/a
b") === '' && Content::cleanRoute('/a?b=1') === '' && Content::cleanRoute('a/b') === ''
        && Content::cleanRoute('/' . str_repeat('a/', 9) . 'b') === '' && Content::cleanRoute('/') === '' && Content::cleanRoute('/_eclair/x') === '' && Content::cleanRoute('/wp-admin/x') === '');
    check('url_escape : ASCII inchangé, accents encodés', url_escape('/blog/mon-article?x=1') === '/blog/mon-article?x=1' && url_escape('/café/crème') === '/caf%C3%A9/cr%C3%A8me');

    // Garde de la recherche d'adresse exacte (Site::handle) : en 2.0.0, son motif ne compilait pas (« range out of order »),
    // un avertissement était journalisé à chaque page publique non mise en cache et la garde ne s'appliquait jamais.
    $exactUser = ['id' => 'routeauteur', 'role' => 'admin', 'name' => 'Camille Route', 'email' => 'camille@example.com'];
    $exactItem = Content::save(['type' => 'page', 'status' => 'published', 'title' => 'Route exacte', 'route' => '/2024/03/route-exacte/',
        'blocks' => [['type' => 'paragraph', 'text' => 'Texte.']]], $exactUser);
    $warnings = [];
    set_error_handler(static function (int $no, string $msg) use (&$warnings): bool {
        $warnings[] = $msg;
        return true;
    });
    try {
        $found = Site::exactRoute('/2024/03/route-exacte/');
        $backslash = Site::exactRoute('/2024/03/route-exacte/\\');
        $control = Site::exactRoute("/2024/03/route-exacte/\x01");
        $del = Site::exactRoute("/2024/03/route-exacte/\x7f");
        $home = Site::exactRoute('/');
    } finally {
        restore_error_handler();
    }
    check('adresse exacte : chemin normal trouvé', ($found['id'] ?? '') === ($exactItem['id'] ?? 'x'), json_encode($found['route'] ?? null));
    check('adresse exacte : jamais cherchée avec antislash, caractère de contrôle ou pour l’accueil', $backslash === null && $control === null && $del === null && $home === null);
    check('adresse exacte : motif de la garde valide, aucun avertissement', $warnings === [] && preg_last_error() === PREG_NO_ERROR, implode(' | ', $warnings));
    Content::delete((string) ($exactItem['id'] ?? ''));

    if (in_array('--unit', $argv, true)) {
        return;
    }
    [$copy, $port, $server] = serve('routing');
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Routes', 'site_tagline' => 'Test', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $v = client($port);
        $v->req('GET', '/');
        $home = $v->req('GET', '/');
        check('accueil servi par le chemin rapide', ($home['headers']['x-cache'] ?? '') === 'HIT');
        $utm = $v->req('GET', '/?utm_source=lettre&utm_campaign=novembre&fbclid=abc');
        check('paramètres marketing : même page, servie par le cache (aucune variante)', ($utm['headers']['x-cache'] ?? '') === 'HIT' && $utm['body'] === $home['body']);
        $p = $v->req('GET', '/?p=123');
        check('/?p=123 inconnu : 404, jamais l’accueil en cache', $p['status'] === 404 && ($p['headers']['x-cache'] ?? '') !== 'HIT');
        check('/?p=abc : 404', $v->req('GET', '/?p=abc')['status'] === 404);
        file_put_contents($copy . '/data/aliases.php', Store::GUARD . json_encode(['p:123' => '/a-propos', 'page_id:45' => '/contact']));
        $p = $v->req('GET', '/?p=123');
        check('/?p=123 connu : 301 directe vers sa page', $p['status'] === 301 && str_ends_with($p['headers']['location'] ?? '', '/a-propos'), (string) ($p['headers']['location'] ?? ''));
        $p = $v->req('GET', '/index.php?page_id=45');
        check('/index.php?page_id=45 : une seule redirection, sans passer par /?page_id=45', $p['status'] === 301 && str_ends_with($p['headers']['location'] ?? '', '/contact'),
            (string) ($p['headers']['location'] ?? ''));
        $p = $v->req('GET', '/?p=123&utm_source=x');
        check('alias avec paramètre marketing : résolu quand même', $p['status'] === 301);
        $other = $v->req('GET', '/?inconnu=1');
        check('autre paramètre : page rendue par l’application, contenu identique', $other['status'] === 200 && ($other['headers']['x-cache'] ?? '') !== 'HIT');
        check('page intérieure avec paramètre marketing : servie par le cache', ($v->req('GET', '/a-propos') + [])['status'] === 200
            && ($v->req('GET', '/a-propos?utm_medium=email')['headers']['x-cache'] ?? '') === 'HIT');

        // Adresses exactes (contenus repris d'un ancien site).
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $page = $c->req('GET', '/gestion/');
        preg_match('/"csrf":"([a-f0-9]+)"/', $page['body'], $m);
        $api = static fn (array $data): array => json_decode($c->req('POST', '/gestion/api/save', (string) json_encode($data),
            ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])['body'], true) ?: [];
        $para = [['type' => 'paragraph', 'text' => 'Texte repris de l’ancien site.']];
        $wp = $api(['type' => 'page', 'title' => 'Article daté', 'status' => 'published', 'route' => '/2024/03/mon-article/', 'blocks' => $para]);
        $html = $api(['type' => 'page', 'title' => 'Page html', 'status' => 'published', 'route' => '/cafe-creme.html', 'blocks' => $para]);
        $uni = $api(['type' => 'page', 'title' => 'Page accentuée', 'status' => 'published', 'route' => '/café/crème', 'blocks' => $para]);
        $dup = $api(['type' => 'page', 'title' => 'Doublon', 'status' => 'published', 'route' => '/2024/03/mon-article', 'blocks' => $para]);
        $bad = $api(['type' => 'page', 'title' => 'Piège', 'status' => 'published', 'route' => '/gestion/piege', 'blocks' => $para]);
        check('adresses exactes enregistrées ; doublon (même adresse sans « / ») et adresse réservée abandonnés',
            ($wp['item']['route'] ?? '') === '/2024/03/mon-article/' && ($html['item']['route'] ?? '') === '/cafe-creme.html' && ($uni['item']['route'] ?? '') === '/café/crème'
            && ($dup['item']['route'] ?? 'x') === '' && ($bad['item']['route'] ?? 'x') === '', json_encode([$wp['item']['route'] ?? null, $dup['item']['route'] ?? null]));
        $r = $v->req('GET', '/2024/03/mon-article/');
        check('/2024/03/mon-article/ servie telle quelle (« / » final gardé), canonical identique', $r['status'] === 200 && str_contains($r['body'], 'Article daté')
            && str_contains($r['body'], 'rel="canonical" href="http://127.0.0.1:' . $port . '/2024/03/mon-article/"'), (string) $r['status']);
        check('deuxième visite : servie par le chemin rapide', ($v->req('GET', '/2024/03/mon-article/')['headers']['x-cache'] ?? '') === 'HIT');
        $r = $v->req('GET', '/2024/03/mon-article');
        check('sans « / » final : une seule 301 vers l’adresse exacte', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/2024/03/mon-article/'));
        $r = $v->req('GET', '/2024/03/MON-ARTICLE/');
        check('majuscules : une seule 301 vers l’adresse exacte', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/2024/03/mon-article/'),
            (string) ($r['headers']['location'] ?? ''));
        check('/cafe-creme.html servie', $v->req('GET', '/cafe-creme.html')['status'] === 200);
        $r = $v->req('GET', '/caf%C3%A9/cr%C3%A8me');
        check('adresse accentuée servie (encodée par le navigateur)', $r['status'] === 200 && str_contains($r['body'], 'Page accentuée')
            && str_contains($r['body'], '/caf%C3%A9/cr%C3%A8me"'), (string) $r['status']);
        $sitemap = $v->req('GET', '/sitemap.xml')['body'];
        check('plan du site : adresses exactes, accents encodés', str_contains($sitemap, '/2024/03/mon-article/</loc>') && str_contains($sitemap, '/caf%C3%A9/cr%C3%A8me</loc>')
            && !str_contains($sitemap, 'café'));
        check('adresse habituelle d’un contenu à adresse exacte : 404 (une seule adresse par contenu)', $v->req('GET', '/article-date')['status'] === 404);
        check('antislash ou caractère de contrôle après une adresse exacte : 404', $v->req('GET', '/2024/03/mon-article/%5C')['status'] === 404
            && $v->req('GET', '/2024/03/mon-article%01')['status'] === 404);
        $errors = (string) @file_get_contents($copy . '/data/logs/errors.php');
        check('aucune erreur preg_match journalisée par les pages publiques', !str_contains($errors, 'preg_match'), mb_substr($errors, 0, 300));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
})($argv);
