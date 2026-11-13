<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Durcissements issus de l'audit de sécurité light (novembre 2026) : une sauvegarde sans données valides est refusée au
 * lieu de créer un brouillon vide, et HSTS est actif d'emblée pour un site installé en HTTPS seulement. Inclus par
 * tests/run.php.
 */
declare(strict_types=1);

use Eclair\Store;

(static function (array $argv): void {
    if (in_array('--unit', $argv, true)) {
        return;
    }
    section('Audit light : sauvegarde sans données valides refusée, HSTS selon le protocole d’installation');
    $fields = static fn (string $name): array => ['ui' => 'fr', 'site_name' => $name, 'site_tagline' => 'Test', 'site_lang' => 'fr', 'site_type' => 'blog',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion'];
    $site = static fn (string $copy): array => json_decode(substr((string) @file_get_contents($copy . '/data/site.php'), strlen(Store::GUARD)), true) ?: [];

    // Installation en HTTP : HSTS reste coupé ; sauvegardes invalides refusées.
    [$copy, $port, $server] = serve('hardening-http');
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + $fields('Atelier HTTP'), ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        preg_match('/"csrf":"([a-f0-9]+)"/', $c->req('GET', '/gestion/')['body'], $m);
        $post = static fn (string $body) => $c->req('POST', '/gestion/api/save', $body, ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')]);
        $count = static fn (): int => count(glob($copy . '/data/content/*.php') ?: []);
        $before = $count();
        $results = ['JSON invalide' => $post('{{{'), 'corps vide' => $post(''), 'objet vide' => $post('{}'), 'liste JSON' => $post('[1,2]')];
        $codes = array_map(static fn (array $r): int => $r['status'], $results);
        check('sauvegarde sans données valides (JSON invalide, corps vide, objet vide, liste) : refusée en 400', array_values(array_unique($codes)) === [400], json_encode($codes));
        check('…et aucun brouillon vide « Sans titre » n’est créé', $count() === $before, $before . ' → ' . $count());
        $ok = $post((string) json_encode(['type' => 'page', 'title' => 'Page valide', 'status' => 'draft', 'blocks' => [['type' => 'paragraph', 'text' => 'Texte.']]]));
        check('une sauvegarde normale fonctionne toujours', $ok['status'] === 200 && $count() === $before + 1 && str_contains($ok['body'], '"title":"Page valide"'), (string) $ok['status']);
        check('installation en HTTP : HSTS coupé (réglage et en-tête)', ($site($copy)['security']['hsts'] ?? null) === false
            && !isset($c->req('GET', '/')['headers']['strict-transport-security']));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }

    // Installation derrière un relais HTTPS : HSTS actif d'emblée.
    [$copy, $port, $server] = serve('hardening-https');
    try {
        $https = ['X-Forwarded-Proto: https'];
        $c = client($port);
        $form = $c->req('GET', '/', [], $https);
        $c->req('POST', '/', $c->token($form['body']) + $fields('Atelier HTTPS'), array_merge(['Accept: application/json'], $https));
        check('installation en HTTPS : HSTS actif d’emblée (réglage)', ($site($copy)['security']['https'] ?? null) === true && ($site($copy)['security']['hsts'] ?? null) === true,
            json_encode($site($copy)['security'] ?? null));
        $home = client($port)->req('GET', '/', [], $https);
        check('…et en-tête Strict-Transport-Security envoyé sur les pages en HTTPS', str_contains($home['headers']['strict-transport-security'] ?? '', 'max-age=31536000'),
            (string) $home['status'] . ' ' . json_encode($home['headers']));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
})($argv);
