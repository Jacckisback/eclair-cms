<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Planning éditorial (1.12) : idées datées, calendrier, idées et articles rédigés par l'IA (faux fournisseur), coût en
 * jetons et en dollars, rédaction automatique (administration ouverte ou tâche planifiée). Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Plan;
use Eclair\Store;

section('1.12 : planning éditorial, dates et créneaux');
check('date prévue : AAAA-MM-JJ valide seulement', Plan::day('2026-12-24') === '2026-12-24' && Plan::day('2026-02-30') === '' && Plan::day('24/12/2026') === ''
    && Plan::day("2026-12-24\n") === '2026-12-24');
$planFile = ECLAIR_DATA . '/plan.php';
$planBackup = is_file($planFile) ? (string) file_get_contents($planFile) : null;
try {
    @unlink($planFile);
    $from = (int) strtotime('2031-03-02 10:00'); // lundi, loin de tout article existant
    $slots = Plan::slots(4, 2, $from);
    check('créneaux : à partir du lendemain, deux par semaine, jamais le même jour', $slots[0] === '2031-03-03' && count(array_unique($slots)) === 4
        && (strtotime($slots[3]) - strtotime($slots[0])) / 86400 >= 9 && (strtotime($slots[3]) - strtotime($slots[0])) / 86400 <= 12, implode(' ', $slots));
    $a = Plan::put(['title' => '  Idée <b>test</b> ', 'date' => '2031-03-03', 'brief' => "ligne 1\nligne 2", 'category' => 'inexistante']);
    check('idée enregistrée : texte nettoyé, catégorie inconnue écartée', is_array($a) && $a['title'] === 'Idée test' && $a['category'] === '' && $a['source'] === 'manual'
        && Plan::state($a) === 'idea');
    check('idée sans titre refusée', is_string(Plan::put(['title' => ' '])));
    $next = Plan::slots(1, 2, $from);
    check('créneaux suivants : après la dernière date prévue', $next[0] > '2031-03-03', $next[0] ?? '');
    check('calendrier du mois : idée à sa date', ($month = Plan::month('2031-03')) && ($month['2031-03-03'][0]['kind'] ?? '') === 'idea');
    check('mode automatique coupé par défaut, sans publication', Plan::auto()['on'] === false && Plan::auto()['publish'] === false && Plan::due() === null);
    $removed = Plan::remove($a['id']);
    check('idée retirée puis remise', $removed !== null && Plan::get($a['id']) === null && (Plan::restore($removed) || true) && Plan::get($a['id']) !== null);
} finally {
    $planBackup === null ? @unlink($planFile) : file_put_contents($planFile, $planBackup);
}

/* ─────────────────────────── Parcours HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('1.12 : planning éditorial, IA (faux fournisseur) et rédaction automatique');
[$copy, $port, $server] = serve('plan');
$fakeDir = sys_get_temp_dir() . '/eclair-fake-plan-' . getmypid();
@mkdir($fakeDir);
[$fakePort, $fake] = start_server([__DIR__ . '/fake-ai.php'], ['FAKE_AI_DIR' => $fakeDir, 'PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
$fakeBase = 'http://127.0.0.1:' . $fakePort;
file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['anthropic' => '$fakeBase', 'openai' => '$fakeBase']);\n");
usleep(400000);
$stored = static function () use ($copy): array {
    $d = json_decode(substr((string) @file_get_contents($copy . '/data/plan.php'), strlen(Store::GUARD)), true);
    return is_array($d) ? $d : ['items' => [], 'auto' => []];
};
$content = static function (string $id) use ($copy): array {
    return json_decode(substr((string) @file_get_contents($copy . '/data/content/' . $id . '.php'), strlen(Store::GUARD)), true) ?: [];
};
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Fournil Test', 'site_tagline' => 'Pain au levain à Lyon', 'site_lang' => 'fr', 'site_type' => 'blog',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $page = $c->req('GET', '/gestion/plan');
    check('page Planning : calendrier du mois, formulaire, invitation à activer l’IA', $page['status'] === 200 && str_contains($page['body'], 'class="plan-grid"')
        && str_contains($page['body'], 'name="action" value="add"') && str_contains($page['body'], 'settings?tab=ai') && !str_contains($page['body'], 'data-plan-ideas'));
    check('page Planning : sans attribut style (CSP de l’administration)', !preg_match('~<[^>]+\sstyle="~', $page['body']));
    check('menu : entrée Planning', str_contains($page['body'], 'href="/gestion/plan"'));
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $r = $c->req('POST', '/gestion/plan', $c->token($page['body']) + ['action' => 'add', 'title' => 'Bien choisir sa farine', 'date' => $tomorrow, 'keyword' => 'farine pain', 'brief' => 'Pour débutants.']);
    $items = $stored()['items'];
    $manual = array_values($items)[0] ?? [];
    check('idée ajoutée à la main (sans JavaScript)', $r['status'] === 302 && count($items) === 1 && $manual['date'] === $tomorrow && $manual['keyword'] === 'farine pain');
    $page = $c->req('GET', '/gestion/plan?m=' . substr($tomorrow, 0, 7));
    check('idée visible dans le calendrier et dans « À venir »', str_contains($page['body'], 'data-kind="idea"') && str_contains($page['body'], 'id="idea-' . $manual['id'] . '"'));

    // Assistant IA activé (faux fournisseur).
    $settings = $c->req('GET', '/gestion/settings?tab=ai');
    $c->req('POST', '/gestion/settings', $c->token($settings['body']) + ['tab' => 'ai', 'provider' => 'anthropic', 'key_ai_anthropic' => 'sk-ant-test-0123456789',
        'model_anthropic' => 'claude-sonnet-5-5', 'budget' => '10', 'daily' => '60', 'editors' => '1']);
    $page = $c->req('GET', '/gestion/plan');
    preg_match('/"csrf":"([a-f0-9]+)"/', $page['body'], $m);
    $api = static fn (string $path, array $data): array => ($res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data),
        ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])) + ['json' => json_decode($res['body'], true)];
    check('IA active : propositions et rédaction proposées, coût indicatif affiché', str_contains($page['body'], 'data-plan-ideas') && str_contains($page['body'], 'data-plan-write="' . $manual['id'] . '"')
        && (bool) preg_match('#Proposer <small>· ~[\d,]+.\$#u', $page['body']));

    $ideas = $api('api/plan/ideas', ['count' => 4, 'week' => 2, 'theme' => 'hiver']);
    $items = $stored()['items'];
    $ai = array_values(array_filter($items, fn ($i) => $i['source'] === 'ai'));
    check('idées proposées par l’IA, datées sur les prochains créneaux libres', ($ideas['json']['ok'] ?? false) === true && count($ai) === 2
        && $ai[0]['date'] > $tomorrow && $ai[0]['date'] !== $ai[1]['date'] && $ai[0]['tokens'] > 0 && $ai[0]['cost'] > 0, $ideas['body']);
    $log = array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) @file_get_contents($fakeDir . '/log.jsonl'))));
    $prompt = json_encode(end($log)['b'] ?? [], JSON_UNESCAPED_UNICODE);
    check('demande d’idées : bâtie sur le site (nom, idées déjà prévues, thème), réponse structurée', str_contains($prompt, 'Fournil Test') && str_contains($prompt, 'Bien choisir sa farine')
        && str_contains($prompt, 'hiver') && str_contains($prompt, 'output_config'));
    $flash = $c->req('GET', '/gestion/plan')['body'];
    check('coût affiché après coup : jetons et dollars', (bool) preg_match('~2 idées ajoutées au planning \([\d\x{202F}]+ jetons · [\d,]+.\$\)~u', $flash));

    $write = $api('api/plan/write', ['id' => $manual['id']]);
    $after = $stored()['items'][$manual['id']] ?? [];
    $draft = $content((string) ($after['content'] ?? ''));
    check('idée rédigée par l’IA : brouillon lié, jamais publié, ouvert dans l’éditeur', ($write['json']['ok'] ?? false) === true && ($draft['status'] ?? '') === 'draft'
        && str_contains((string) ($write['json']['edit'] ?? ''), '/gestion/edit/' . $after['content']));
    check('coût de la rédaction noté sur l’idée et compté dans le budget du mois', $after['tokens'] > 0 && $after['cost'] > 0
        && (float) (json_decode(substr((string) file_get_contents($copy . '/data/ai/usage.php'), strlen(Store::GUARD)), true)['months'][date('Y-m')]['cost'] ?? 0) > 0);
    check('idée déjà rédigée : pas de second appel payant', ($api('api/plan/write', ['id' => $manual['id']])['json']['ok'] ?? true) === false);
    $page = $c->req('GET', '/gestion/plan');
    check('brouillon : « Relire et programmer » à la place de « Rédiger »', str_contains($page['body'], 'Relire et programmer') && !str_contains($page['body'], 'data-plan-write="' . $manual['id'] . '"'));

    // Rédaction automatique : l'idée due est rédigée quand un administrateur ouvre l'administration.
    $c->req('POST', '/gestion/plan', $c->token($page['body']) + ['action' => 'auto', 'on' => '1', 'lead' => '14', 'publish' => '1']);
    $auto = $stored()['auto'];
    check('rédaction automatique activée au nom de l’administrateur', $auto['on'] === true && $auto['lead'] === 14 && $auto['publish'] === true && $auto['by'] !== '');
    $dash = $c->req('GET', '/gestion/');
    check('administration ouverte : rédaction due signalée au navigateur', str_contains($dash['body'], '"planDue":true'));
    $tick = $api('api/plan/tick', []);
    $done = array_values(array_filter($stored()['items'], fn ($i) => $i['source'] === 'ai' && $i['content'] !== ''));
    $autoDraft = $content((string) ($done[0]['content'] ?? ''));
    check('idée due rédigée toute seule, une à la fois', ($tick['json']['status'] ?? '') === 'done' && count($done) === 1, $tick['body']);
    check('programmer sans relecture : refusé tant qu’il reste « À compléter »', ($autoDraft['status'] ?? '') === 'draft');
    $cron = $c->req('GET', '/_eclair/plan/' . str_repeat('0', 32));
    check('tâche planifiée : adresse inconnue refusée', $cron['status'] === 404);
    preg_match('~/_eclair/plan/([a-f0-9]{32})~', $c->req('GET', '/gestion/plan')['body'], $k);
    $cron = $c->req('GET', '/_eclair/plan/' . ($k[1] ?? 'x'));
    check('tâche planifiée : rédige l’idée due suivante', $cron['status'] === 200 && (json_decode($cron['body'], true)['status'] ?? '') === 'done', $cron['body']);
    check('plus rien de dû : rien n’est dépensé', (json_decode($c->req('GET', '/_eclair/plan/' . ($k[1] ?? 'x'))['body'], true)['status'] ?? '') === 'idle');

    // Retrait d'une idée : le brouillon rédigé est gardé.
    $page = $c->req('GET', '/gestion/plan');
    $c->req('POST', '/gestion/plan', $c->token($page['body']) + ['action' => 'delete', 'id' => $manual['id']]);
    check('idée retirée du planning, brouillon gardé', !isset($stored()['items'][$manual['id']]) && ($content((string) $after['content'])['status'] ?? '') === 'draft');
    check('« Crée ta page avec l’IA » fonctionne toujours (rédaction commune)', ($api('api/ai/compose', ['type' => 'page', 'brief' => 'Une page pour présenter nos cours.'])['json']['ok'] ?? false) === true);
} finally {
    proc_terminate($server);
    proc_terminate($fake);
    exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($fakeDir));
}
