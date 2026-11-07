<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Blocs conservés (fonction absente), registre des capacités optionnelles et leur cycle de vie (inclus par tests/run.php).
 */
declare(strict_types=1);

use Eclair\Blocks;
use Eclair\Extensions;
use Eclair\Store;
use Eclair\Themes;

(static function (array $argv): void {
    section('Blocs d’une fonction absente : conservés, bornés, jamais affichés');
    $foreign = ['type' => 'boutique/variante', 'sku' => 'A-12', 'options' => [['taille' => 'M', 'prix' => 12.5]], 'html' => '<script>alert(1)</script>'];
    $norm = Blocks::normalize([['type' => 'paragraph', 'text' => 'Avant'], $foreign, ['type' => 'paragraph', 'text' => '']]);
    check('bloc inconnu conservé à sa place, données intactes', count($norm) === 2 && $norm[1]['type'] === 'boutique/variante' && !empty($norm[1]['preserved'])
        && ($norm[1]['data']['options'][0]['taille'] ?? '') === 'M' && ($norm[1]['data']['html'] ?? '') === '<script>alert(1)</script>');
    check('bloc du noyau invalide toujours retiré (paragraphe vide)', count(array_filter($norm, static fn (array $b): bool => $b['type'] === 'paragraph')) === 1);
    check('enregistrement répété : identique (idempotent)', Blocks::normalize($norm) === $norm && Blocks::normalize(Blocks::normalize($norm)) === $norm);
    check('bloc conservé jamais affiché ni exécuté', !str_contains(Blocks::html($norm, ['lang' => 'fr']), 'script') && str_contains(Blocks::html($norm, ['lang' => 'fr']), 'Avant'));
    check('données d’origine rendues à la fonction qui revient', Blocks::original($norm[1]) === ['type' => 'boutique/variante'] + array_diff_key($foreign, ['type' => 1]));
    check('bloc conservé borné (32 Ko)', Blocks::normalize([['type' => 'x/gros', 'v' => str_repeat('a', 40000)]]) === []);
    check('type de bloc suspect jamais conservé', Blocks::normalize([['type' => '../../x'], ['type' => 'A<b>'], ['type' => str_repeat('a', 90)]]) === []);

    section('Capacités optionnelles : manifeste, validation, cycle de vie (unitaire)');
    $example = dirname(__DIR__) . '/examples/extensions/bonjour';
    check('exemple « bonjour » conforme au contrat', Extensions::validate($example) === [], implode(' | ', Extensions::validate($example)));
    if (!getenv('ECLAIR_TEST_ROOT')) {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/validate.php') . ' ' . escapeshellarg($example) . ' --json', $vOut, $vCode);
        $vJson = json_decode(implode("\n", $vOut), true);
        check('validateur en ligne de commande : fonction conforme (JSON pour les skills)', $vCode === 0 && ($vJson['valid'] ?? false) === true && ($vJson['kind'] ?? '') === 'extension');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/validate.php') . ' ' . escapeshellarg(sys_get_temp_dir()), $vOut2, $vCode2);
        check('validateur : dossier sans manifeste refusé', $vCode2 === 1);
    }
    $tmp = sys_get_temp_dir() . '/eclair-ext-src-' . getmypid();
    $make = static function (string $id, array $patch, array $files = []) use ($tmp, $example): string {
        $dir = $tmp . '/' . $id . '-' . bin2hex(random_bytes(2));
        mkdir($dir, 0755, true);
        exec('cp -r ' . escapeshellarg($example) . '/. ' . escapeshellarg($dir));
        $m = array_replace(json_decode((string) file_get_contents($example . '/extension.json'), true), ['id' => $id], $patch);
        file_put_contents($dir . '/extension.json', json_encode($m));
        foreach ($files as $rel => $content) {
            @mkdir(dirname($dir . '/' . $rel), 0755, true);
            file_put_contents($dir . '/' . $rel, $content);
        }
        return $dir;
    };
    $refus = [
        'route publique interne' => $make('interne', ['routes' => ['public' => [['path' => '/app/x', 'handler' => 'page']], 'admin' => []]]),
        'droit inconnu' => $make('droit', ['routes' => ['public' => [], 'admin' => [['path' => '', 'handler' => 'adminPage', 'right' => 'root']]]]),
        'bloc hors de son espace' => $make('espace', ['blocks' => [['type' => 'paragraph', 'render' => 'salut']]]),
        'script non déclaré' => $make('script', [], ['outil.php' => '<?php echo 1;']),
        'empreinte différente' => $make('empreinte', ['files' => ['extension.php' => str_repeat('0', 64)]]),
        'contrat inconnu' => $make('contrat', ['schema' => 'eclair-extension/9']),
        'PHP trop ancien' => $make('php', ['requires' => ['php' => '>=99']]),
    ];
    foreach ($refus as $label => $dir) {
        check("validation : $label refusé", Extensions::validate($dir) !== []);
    }
    $r = Extensions::install($example);
    check('installation sans activation, sans scan à chaque requête', $r['ok'] && is_file(Extensions::dir() . '/bonjour/extension.php') && Extensions::active() === []);
    $r = Extensions::activate('bonjour');
    Store::forget();
    Extensions::forget();
    check('activation : migration exécutée une fois et journalisée', $r['ok'] && (Store::read('ext/bonjour/compteur')['visites'] ?? null) === 0
        && (Extensions::state()['items']['bonjour']['migrations'] ?? []) === ['001-compteur'], (string) ($r['error'] ?? ''));
    Store::write('ext/bonjour/compteur', ['visites' => 7]);
    Extensions::activate('bonjour');
    check('migration jamais rejouée (idempotente)', (Store::read('ext/bonjour/compteur')['visites'] ?? null) === 7);
    $b = Blocks::normalize([['type' => 'bonjour/salut', 'name' => '<b>Léa</b>', 'style' => 'pirate', 'extra' => 'x']]);
    check('bloc d’une capacité active : champs validés par le noyau', ($b[0] ?? []) === ['type' => 'bonjour/salut', 'name' => 'Léa', 'style' => 'simple'], json_encode($b));
    check('bloc d’une capacité active : rendu par la capacité', str_contains(Blocks::html($b, ['lang' => 'fr']), 'Salut Léa !'));
    check('découverte : versions, contrats, blocs, sans secret', ($d = Extensions::describe())['contracts']['theme'] === Themes::SUPPORTED
        && in_array('bonjour/salut', $d['blocks']['extensions'], true) && !str_contains((string) json_encode($d), (string) (Extensions::state()['token'] ?? 'x')));
    $dep = $make('dependante', ['depends' => ['bonjour' => '^1.0'], 'routes' => ['public' => [], 'admin' => []], 'blocks' => [], 'nav' => [], 'dashboard' => [], 'migrations' => []]);
    $copy = Extensions::dir() . '/dependante';
    exec('cp -r ' . escapeshellarg($dep) . ' ' . escapeshellarg($copy));
    check('dépendance active : activation acceptée', Extensions::activate('dependante')['ok']);
    $d = Extensions::deactivate('bonjour', true);
    check('désactivation refusée tant qu’une capacité en dépend', !$d['ok'] && str_contains($d['error'], 'dependante'));
    Extensions::deactivate('dependante', true);
    $d = Extensions::deactivate('bonjour');
    check('désactivation : confirmation demandée (adresse publique)', !$d['ok'] && !empty($d['confirm']));
    $d = Extensions::deactivate('bonjour', true);
    Extensions::forget();
    $kept = Blocks::normalize($b);
    check('désactivée : bloc conservé, plus affiché, données gardées', $d['ok'] && !empty($kept[0]['preserved']) && Blocks::html($kept, ['lang' => 'fr']) === ''
        && (Store::read('ext/bonjour/compteur')['visites'] ?? null) === 7);
    Extensions::activate('dependante');
    Extensions::forget();
    check('réactivation : dépendance réactivée automatiquement, bloc de nouveau affiché', Extensions::enabled('bonjour')
        && str_contains(Blocks::html(Blocks::normalize($kept), ['lang' => 'fr']), 'Salut Léa !'));
    Extensions::deactivate('dependante', true);
    Extensions::deactivate('bonjour', true);
    check('suppression du code : données gardées', Extensions::remove('bonjour')['ok'] && !is_dir(Extensions::dir() . '/bonjour') && Store::read('ext/bonjour/compteur') !== null);
    check('effacement des données : opération distincte', Extensions::purge('bonjour')['ok'] && Store::read('ext/bonjour/compteur') === null);
    Extensions::remove('dependante');
    Extensions::purge('dependante');
    exec('rm -rf ' . escapeshellarg($tmp));

    if (in_array('--unit', $argv, true)) {
        return;
    }

    section('Capacités optionnelles : route publique, administration, tableau de bord, éditeur, désactivation sans perte (HTTP)');
    [$copy, $port, $server] = serve('extensions');
    try {
        $c = client($port);
        // Typographie française du site : espace insécable avant « ! », ignorée par ces vérifications.
        $text = static fn (array $r): string => str_replace("\u{00A0}", ' ', (string) $r['body']);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier Fonctions', 'site_tagline' => 'Test', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $none = $c->req('GET', '/bonjour');
        check('sans la capacité : /bonjour introuvable, aucun code chargé', $none['status'] === 404);
        $page = $c->req('GET', '/gestion/extensions');
        check('page Fonctions : rien d’installé, import proposé', $page['status'] === 200 && str_contains($page['body'], 'Aucune fonction ajoutée') && str_contains($page['body'], 'name="package"'));
        exec('mkdir -p ' . escapeshellarg($copy . '/site/extensions') . ' && cp -r ' . escapeshellarg($example) . ' ' . escapeshellarg($copy . '/site/extensions/bonjour'));
        $page = $c->req('GET', '/gestion/extensions');
        $c->req('POST', '/gestion/extensions', $c->token($page['body']) + ['action' => 'activate', 'id' => 'bonjour']);
        $pub = $c->req('GET', '/bonjour');
        check('route publique : page dans le thème, en-têtes SEO et sécurité du noyau', $pub['status'] === 200 && str_contains($text($pub), 'Bonjour et bienvenue !')
            && substr_count($pub['body'], 'rel="canonical"') === 1 && str_contains($pub['body'], '<title>') && isset($pub['headers']['content-security-policy']));
        check('route publique mise en cache comme les pages du site', ($c->req('GET', '/bonjour')['headers']['x-cache'] ?? '') === 'HIT');
        $dash = $c->req('GET', '/gestion');
        check('tableau de bord : carte de la capacité ; menu : son entrée', str_contains($dash['body'], 'data-ext="bonjour"') && str_contains($dash['body'], 'href="/gestion/x/bonjour"'));
        $admin = $c->req('GET', '/gestion/x/bonjour');
        check('route d’administration : protections centrales', $admin['status'] === 200 && str_contains($admin['body'], 'Message actuel') && ($admin['headers']['x-frame-options'] ?? '') === 'DENY');
        $set = $c->req('GET', '/gestion/x/bonjour/settings');
        $c->req('POST', '/gestion/x/bonjour/settings', ['message' => '<em>Salut</em> la compagnie']);
        check('réglage sans jeton CSRF : refusé', !str_contains($c->req('GET', '/gestion/x/bonjour')['body'], 'la compagnie'));
        $c->req('POST', '/gestion/x/bonjour/settings', $c->token($set['body']) + ['message' => '<em>Salut</em> la compagnie']);
        check('réglage déclaratif validé par le noyau, page publique à jour', str_contains($c->req('GET', '/bonjour')['body'], 'Salut la compagnie'));
        preg_match('/"csrf":"([a-f0-9]+)"/', $dash['body'], $m);
        $api = static fn (string $path, array $data): array => ($res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data),
            ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])) + ['json' => json_decode($res['body'], true)];
        $saved = $api('api/save', ['type' => 'page', 'title' => 'Page avec salut', 'status' => 'published',
            'blocks' => [['type' => 'paragraph', 'text' => 'Intro'], ['type' => 'bonjour/salut', 'name' => 'Léa', 'style' => 'encadre']]]);
        $url = (string) ($saved['json']['url'] ?? '/x');
        check('contenu avec un bloc de la capacité : affiché', str_contains($text($c->req('GET', $url)), 'Salut Léa !'), $saved['body']);
        $editor = $c->req('GET', '/gestion/edit/' . ($saved['json']['item']['id'] ?? ''));
        check('éditeur : blocs de la capacité proposés', str_contains($editor['body'], 'bonjour\/salut'));
        $describe = $c->req('GET', '/gestion/api/capabilities', [], ['Accept: application/json']);
        $json = json_decode($describe['body'], true);
        check('découverte structurée (droit Réglages) : capacité, blocs, contrats, sans secret', ($json['ok'] ?? false) && in_array('bonjour/salut', $json['blocks']['extensions'] ?? [], true)
            && !str_contains($describe['body'], 'password') && !str_contains($describe['body'], (string) json_decode(Store::strip((string) file_get_contents($copy . '/data/extensions.php')), true)['token']));

        // Désactivation : la confirmation est demandée, puis rien n'est perdu.
        $page = $c->req('GET', '/gestion/extensions');
        $c->req('POST', '/gestion/extensions', $c->token($page['body']) + ['action' => 'deactivate', 'id' => 'bonjour']);
        check('désactivation sans confirmation : refusée (page publique et contenu publié)', $c->req('GET', '/bonjour')['status'] === 200);
        $page = $c->req('GET', '/gestion/extensions');
        $c->req('POST', '/gestion/extensions', $c->token($page['body']) + ['action' => 'deactivate', 'id' => 'bonjour', 'confirm' => '1']);
        check('désactivée : route, carte et menu retirés', $c->req('GET', '/bonjour')['status'] === 404 && !str_contains($c->req('GET', '/gestion')['body'], 'data-ext="bonjour"')
            && $c->req('GET', '/gestion/x/bonjour')['status'] === 404);
        check('désactivée : le contenu reste en ligne, sans le bloc', ($p = $c->req('GET', $url))['status'] === 200 && str_contains($p['body'], 'Intro') && !str_contains($p['body'], 'Salut Léa'));
        $item = $saved['json']['item'];
        $resave = $api('api/save', $item);
        $stored = json_decode(Store::strip((string) file_get_contents($copy . '/data/content/' . $item['id'] . '.php')), true);
        $kept = array_values(array_filter($stored['blocks'] ?? [], static fn (array $bl): bool => $bl['type'] === 'bonjour/salut'));
        check('enregistré pendant la désactivation : bloc conservé avec ses données', ($kept[0]['data']['name'] ?? '') === 'Léa' && !empty($kept[0]['preserved']), $resave['body']);
        $editor = $c->req('GET', '/gestion/edit/' . $item['id']);
        check('éditeur : le bloc conservé est transmis tel quel (affiché comme bloc conservé)', str_contains($editor['body'], '"preserved":true'));
        $page = $c->req('GET', '/gestion/extensions');
        $c->req('POST', '/gestion/extensions', $c->token($page['body']) + ['action' => 'activate', 'id' => 'bonjour']);
        check('réactivée : bloc et page retrouvés, réglage gardé', str_contains($text($c->req('GET', $url)), 'Salut Léa !') && str_contains($c->req('GET', '/bonjour')['body'], 'Salut la compagnie'));
        // Mise à jour du noyau : les capacités ne sont jamais réinstallées ni écrasées (site/ hors de l'archive du noyau).
        check('capacités hors du périmètre du noyau (site/extensions)', !str_contains((string) file_get_contents(ECLAIR_ROOT . '/app/lib/Update.php'), "'site/'")
            && str_contains((string) file_get_contents(ECLAIR_ROOT . '/app/lib/Update.php'), '(site|data|media)/'));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
})($argv);
