<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Routage compatible avec une migration (inclus par tests/run.php) : alias WordPress à paramètres résolus avant le cache,
 * paramètres marketing sans variante de cache, aucune redirection ouverte ni chaîne.
 */
declare(strict_types=1);

use Eclair\Aliases;
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
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
})($argv);
