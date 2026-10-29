<?php
/**
 * Tests de l'assistant IA, inclus par tests/run.php. Aucune clé réelle : les fournisseurs
 * (Claude, ChatGPT, GPT Image, FLUX, Ideogram) sont simulés par tests/fake-ai.php.
 */
declare(strict_types=1);

use Eclair\Ai;
use Eclair\AiTools;
use Eclair\Blocks;
use Eclair\Markdown;
use Eclair\Store;
use Eclair\Vault;

/* ─────────────────────────── Markdown → blocs ─────────────────────────── */
section('Assistant IA : Markdown → blocs');
Store::write('media', ['abc123' => ['id' => 'abc123', 'file' => '2026/10/x', 'ext' => 'jpg', 'w' => 800, 'h' => 600, 'webp' => [], 'alt' => 'Un bol', 'name' => 'x.jpg', 'created' => time()]]);
$b = Markdown::toBlocks("# Titre\n\nUn **gras**, un *italique*, du `code` et [un lien](/contact).\nSuite du paragraphe.\n\n## Section ?\n\n- un\n- deux\n  suite\n\n1. a\n2. b\n\n| A | B |\n| --- | --- |\n| 1 | 2 \\| 3 |\n\n> [!WARNING] Attention\n> Texte d'alerte.\n\n> Belle phrase.\n> — Quelqu'un\n\n![Bol](media:abc123 \"Légende\")\n\n![Externe](https://evil.example/x.png)\n\n---\n\n```js\nlet a = 1 < 2;\n```\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1)) et [y](JaVaScRiPt:alert(2))");
$types = array_column($b, 'type');
check('Markdown : types de blocs', $types === ['heading', 'paragraph', 'heading', 'list', 'list', 'table', 'callout', 'quote', 'image', 'divider', 'code', 'paragraph', 'paragraph'], implode(',', $types));
check('Markdown : H1 converti en H2', $b[0]['level'] === 2);
check('Markdown : mise en forme en ligne', $b[1]['text'] === 'Un <strong>gras</strong>, un <em>italique</em>, du <code>code</code> et <a href="/contact">un lien</a>. Suite du paragraphe.', $b[1]['text']);
check('Markdown : listes (suite de ligne, numérotée)', $b[3]['items'] === ['un', 'deux suite'] && $b[4]['style'] === 'ol');
check('Markdown : tableau avec en-tête et barre échappée', $b[5]['head'] && $b[5]['rows'][1] === ['1', '2 | 3']);
check('Markdown : encadré (alerte GitHub)', $b[6]['variant'] === 'warning' && $b[6]['title'] === 'Attention');
check('Markdown : citation et auteur', $b[7]['cite'] === 'Quelqu\'un' && $b[7]['text'] === 'Belle phrase.');
check('Markdown : image de la médiathèque seulement', $b[8]['media'] === 'abc123' && $b[8]['caption'] === 'Légende' && !in_array('evil', array_column($b, 'media'), true));
check('Markdown : code conservé', $b[10]['code'] === 'let a = 1 < 2;' && $b[10]['lang'] === 'js');
check('Markdown : aucun script', !str_contains(json_encode($b), 'script>') && $b[11]['text'] === '&lt;script&gt;alert(1)&lt;/script&gt;', $b[11]['text']);
check('Markdown : liens javascript retirés', $b[12]['text'] === 'x et y', $b[12]['text']);
check('Markdown : retour fidèle pour le modèle', str_contains(Markdown::fromBlocks($b), '![Bol](media:abc123)') && str_contains(Markdown::fromBlocks($b), '> [!WARNING] Attention'));
check('Markdown : bloc équipe validé', Blocks::normalize([['type' => 'team', 'title' => 'Nous', 'items' => [['name' => 'Léa', 'role' => 'CEO', 'bio' => '<b>Hi</b><img src=x onerror=alert(1)>', 'media' => 'abc123', 'link' => 'javascript:alert(1)'], ['name' => '']]]])
    === [['type' => 'team', 'title' => 'Nous', 'items' => [['name' => 'Léa', 'role' => 'CEO', 'bio' => '<strong>Hi</strong>', 'media' => 'abc123', 'link' => '']]]]);
$teamHtml = Blocks::html(Blocks::normalize([['type' => 'team', 'title' => '', 'items' => [['name' => 'Jean Dupont', 'role' => 'Gérant', 'bio' => 'Fondateur.', 'link' => 'https://www.linkedin.com/in/jd']]]]));
check('bloc équipe : initiales sans photo, lien de profil', str_contains($teamHtml, '>JD</span>') && str_contains($teamHtml, 'linkedin.com ↗'), $teamHtml);

/* ─────────────────────────── Coffre des clés ─────────────────────────── */
section('Assistant IA : coffre des clés');
Vault::set('ai.test', 'sk-ant-secret-1234567890abcd');
$rawVault = (string) file_get_contents(ECLAIR_DATA . '/vault.php');
check('clé chiffrée sur le disque', Vault::get('ai.test') === 'sk-ant-secret-1234567890abcd' && !str_contains($rawVault, 'secret-1234567890'));
check('indice seulement (début et fin)', Vault::hint('ai.test') === 'sk-ant…abcd');
Vault::set('ai.test', '');
check('clé supprimée', !Vault::has('ai.test') && Vault::hint('ai.test') === '');
$sealed = json_decode(Store::strip((string) file_get_contents(ECLAIR_DATA . '/vault.php')), true);
check('aucune trace après suppression', !isset($sealed['ai.test']));
check('coût calculé (Opus 5.5)', abs(Ai::cost('anthropic', 'claude-opus-5-5', ['in' => 1_000_000, 'out' => 100_000, 'cache_read' => 1_000_000, 'cache_write' => 0]) - 6.2) < 1e-9);
check('coût calculé (GPT-6 Astra)', abs(Ai::cost('openai', 'gpt-6-astra', ['in' => 100_000, 'out' => 10_000]) - 1.5) < 1e-9);

/* ─────────────────────────── Modifications par l'IA ─────────────────────────── */
section('Assistant IA : modifications ciblées');
$item = ['id' => 'x1', 'type' => 'page', 'status' => 'published', 'title' => 'Équipe', 'summary' => '', 'image' => '', 'date' => 1700000000, 'updated' => 1,
    'seo' => ['title' => '', 'description' => '', 'keyword' => '', 'canonical' => '', 'noindex' => false], 'options' => [],
    'blocks' => Blocks::normalize([
        ['type' => 'paragraph', 'text' => 'Nous sommes <strong>une équipe</strong> soudée.'],
        ['type' => 'heading', 'level' => 2, 'text' => 'Notre histoire'],
        ['type' => 'paragraph', 'text' => 'Depuis 2012.'],
        ['type' => 'heading', 'level' => 2, 'text' => 'Nos valeurs'],
        ['type' => 'paragraph', 'text' => 'Le fait main.'],
        ['type' => 'contact', 'title' => '', 'button' => ''],
    ])];
[$changed, $labels, $missing] = AiTools::apply($item, [
    'title' => 'Notre équipe', 'replace' => [['find' => 'une équipe soudée', 'replace' => 'une **petite** équipe soudée'], ['find' => 'introuvable', 'replace' => 'x']],
    'append' => "## Nos engagements\n\nDes matériaux locaux.", 'after_heading' => 'Notre histoire',
    'team_member' => ['name' => 'Léa Martin', 'role' => 'Céramiste', 'photo' => 'abc123', 'bio' => 'Depuis **2019**.'],
    'faq' => [['question' => 'Où ?', 'answer' => 'À Lyon.']],
]);
$t = array_column($changed['blocks'], 'type');
check('modif : titre et passage chevauchant une mise en forme', $changed['title'] === 'Notre équipe' && $changed['blocks'][0]['text'] === 'Nous sommes une <strong>petite</strong> équipe soudée.', $changed['blocks'][0]['text']);
check('modif : passage introuvable signalé', $missing === ['introuvable']);
check('modif : section insérée après « Notre histoire »', Blocks::text([$changed['blocks'][3]]) === 'Nos engagements' && plain($changed['blocks'][5]['text']) === 'Nos valeurs', implode(',', $t));
check('modif : équipe et FAQ avant le formulaire', array_slice($t, -3) === ['faq', 'team', 'contact'] || array_slice($t, -3) === ['team', 'faq', 'contact'], implode(',', $t));
$team = $changed['blocks'][array_search('team', $t, true)];
check('modif : membre avec photo et présentation', $team['items'][0]['media'] === 'abc123' && $team['items'][0]['bio'] === 'Depuis <strong>2019</strong>.');
check('modif : date d’origine conservée', $changed['date'] === '' && count($labels) === 5, implode(' | ', $labels));
[$again] = AiTools::apply($changed, ['team_member' => ['name' => 'léa martin', 'role' => 'Directrice artistique']]);
$team2 = $again['blocks'][array_search('team', array_column($again['blocks'], 'type'), true)];
check('modif : membre existant mis à jour (sans doublon)', count($team2['items']) === 1 && $team2['items'][0]['role'] === 'Directrice artistique' && $team2['items'][0]['media'] === 'abc123');
Store::delete('media');

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Assistant IA : parcours complet (faux fournisseurs)');
[$copy, $port, $server] = serve('ai');
$fakeDir = sys_get_temp_dir() . '/eclair-fake-ai-' . getmypid();
@mkdir($fakeDir);
$fakePort = random_int(40001, 50000);
$fake = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $fakePort, __DIR__ . '/fake-ai.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
    $fakePipes, null, ['FAKE_AI_DIR' => $fakeDir, 'PATH' => (string) getenv('PATH'), 'PHP_CLI_SERVER_WORKERS' => '4']);
$fakeBase = 'http://127.0.0.1:' . $fakePort;
file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['anthropic' => '$fakeBase', 'openai' => '$fakeBase', 'bfl' => '$fakeBase', 'ideogram' => '$fakeBase']);\n");
usleep(400000);
$fakeLog = static function () use ($fakeDir): array {
    return array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) @file_get_contents($fakeDir . '/log.jsonl'))));
};
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Atelier Test', 'site_tagline' => 'Céramique à Lyon', 'site_lang' => 'fr', 'site_type' => 'business',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $dash = $c->req('GET', '/gestion');
    check('IA désactivée : invitation à l’activer, aucun script', str_contains($dash['body'], 'settings?tab=ai') && !str_contains($dash['body'], 'assistant.js'));
    preg_match('/"csrf":"([a-f0-9]+)"/', $dash['body'], $m);
    $csrf = $m[1] ?? '';
    $api = static function (string $method, string $path, ?array $data = null, array $headers = []) use ($c, &$csrf): array {
        $res = $c->req($method, '/gestion/' . $path, $data === null ? [] : (string) json_encode($data),
            array_merge(['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $csrf], $headers));
        return $res + ['json' => json_decode($res['body'], true)];
    };
    $chat = static function (string $message, array $extra = []) use ($api): array {
        $res = $api('POST', 'api/ai/chat', ['message' => $message] + $extra);
        $events = array_values(array_filter(array_map(fn ($l) => json_decode($l, true), explode("\n", $res['body']))));
        return ['status' => $res['status'], 'events' => $events, 'type' => $res['headers']['content-type'] ?? '',
            'text' => implode('', array_column(array_filter($events, fn ($e) => $e['t'] === 'text'), 'd')),
            'cards' => array_column(array_filter($events, fn ($e) => $e['t'] === 'card'), 'card'),
            'tools' => array_column(array_filter($events, fn ($e) => $e['t'] === 'tool'), 'name'),
            'errors' => array_column(array_filter($events, fn ($e) => $e['t'] === 'error'), 'error')];
    };
    $settingsAi = static function (array $fields) use ($c): array {
        $page = $c->req('GET', '/gestion/settings?tab=ai');
        return $c->req('POST', '/gestion/settings', $c->token($page['body']) + $fields + ['tab' => 'ai', 'budget' => '10', 'daily' => '60', 'editors' => '1']);
    };

    // Réglages : clés chiffrées, jamais renvoyées au navigateur.
    $settingsAi(['provider' => 'anthropic', 'key_ai_anthropic' => 'sk-ant-test-0123456789', 'model_anthropic' => 'claude-opus-5-5', 'image' => 'openai',
        'key_image_openai' => 'sk-test-openai-0123456789', 'profile_tone' => 'Chaleureux, tutoiement, phrases courtes.', 'profile_visual' => 'Lumière douce, tons chauds.']);
    $page = $c->req('GET', '/gestion/settings?tab=ai');
    check('réglages IA : indice affiché, clé jamais renvoyée', str_contains($page['body'], 'sk-ant…6789') && !str_contains($page['body'], 'sk-ant-test-0123456789') && !str_contains($page['body'], 'sk-test-openai-0123456789'));
    check('réglages IA : clé chiffrée sur le disque', !str_contains((string) file_get_contents($copy . '/data/vault.php'), 'test-0123456789'));
    $dash = $c->req('GET', '/gestion');
    check('assistant chargé + champ du tableau de bord', str_contains($dash['body'], 'assistant.js') && str_contains($dash['body'], 'data-ai-prompt') && str_contains($dash['body'], '"model":"Claude Opus 5.5"'));
    check('test de connexion (clé enregistrée)', ($api('POST', 'api/ai/test', ['provider' => 'anthropic'])['json']['ok'] ?? false) === true);
    $bad = $api('POST', 'api/ai/test', ['provider' => 'anthropic', 'key' => 'sk-ant-mauvaise-cle']);
    check('test de connexion : clé refusée expliquée', ($bad['json']['ok'] ?? true) === false && str_contains((string) ($bad['json']['error'] ?? ''), 'Clé'), $bad['body']);
    check('requête sans jeton CSRF refusée', $c->req('POST', '/gestion/api/ai/chat', '{"message":"x"}', ['Accept: application/json', 'Content-Type: application/json'])['status'] === 419);

    // Article : image générée puis brouillon, en flux.
    $r = $chat('Écris un ARTICLE sur la céramique pour débutants');
    check('chat : réponse en flux NDJSON', $r['status'] === 200 && str_contains($r['type'], 'application/x-ndjson') && ($r['events'][0]['t'] ?? '') === 'start', $r['status'] . ' ' . substr(json_encode($r['events']), 0, 300));
    check('chat : outils image puis brouillon', $r['tools'] === ['generate_image', 'create_draft'] && !$r['errors'], json_encode([$r['tools'], $r['errors']]));
    check('chat : texte avant et après les outils', str_contains($r['text'], 'Je prépare ton article.') && str_contains($r['text'], 'Relis le brouillon'), $r['text']);
    $draftCard = array_values(array_filter($r['cards'], fn ($x) => $x['kind'] === 'draft'))[0] ?? [];
    $imageCard = array_values(array_filter($r['cards'], fn ($x) => $x['kind'] === 'image'))[0] ?? [];
    check('carte image : alt et miniature', ($imageCard['media']['alt'] ?? '') === 'Bols en céramique dans un atelier lumineux' && str_contains($imageCard['media']['thumb'] ?? '', '/media/'));
    $draftId = (string) ($draftCard['id'] ?? '');
    $draft = json_decode(substr((string) @file_get_contents($copy . '/data/content/' . $draftId . '.php'), strlen(Store::GUARD)), true) ?: [];
    $dt = array_column($draft['blocks'] ?? [], 'type');
    check('brouillon créé (jamais publié)', ($draft['status'] ?? '') === 'draft' && $draft['title'] === 'Comment débuter la céramique en 5 étapes' && $draft['image'] === ($imageCard['media']['id'] ?? '-'));
    check('brouillon : H1 répété retiré, structure complète', ($dt[0] ?? '') === 'paragraph' && !array_diff(['heading', 'list', 'table', 'callout', 'quote', 'image', 'faq'], $dt), implode(',', $dt));
    check('brouillon : aucun script ni lien javascript', !preg_match('~<script|javascript:~i', json_encode($draft)));
    check('brouillon : bien noté par l’analyse', ($draftCard['seo'] ?? 0) >= 60 && ($draftCard['geo'] ?? 0) >= 60, json_encode($draftCard));
    $log = $fakeLog();
    $messagesCalls = array_values(array_filter($log, fn ($l) => $l['p'] === '/v1/messages'));
    check('requêtes Anthropic : 3 appels, outils déclarés, profil dans les consignes', count($messagesCalls) === 3
        && count($messagesCalls[0]['b']['tools']) === 6 && str_contains($messagesCalls[0]['b']['system'][0]['text'], 'Chaleureux, tutoiement'));
    check('consignes : contexte variable hors cache', !isset($messagesCalls[0]['b']['system'][1]['cache_control']) && str_contains($messagesCalls[0]['b']['system'][1]['text'], 'Atelier Test'));
    $usage = json_decode(substr((string) file_get_contents($copy . '/data/ai/usage.php'), strlen(Store::GUARD)), true);
    $month = $usage['months'][date('Y-m')] ?? [];
    check('dépenses comptées (texte + image)', ($month['cost'] ?? 0) > 0.01 && ($month['images'] ?? 0) === 1, json_encode($month));

    // Équipe : photo jointe, proposition sur une page publiée, aperçu, application, annulation.
    $team = $api('POST', 'api/save', ['type' => 'page', 'title' => 'Notre équipe', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Une équipe passionnée.']]]);
    $teamId = (string) ($team['json']['item']['id'] ?? '');
    $photo = (string) ($imageCard['media']['id'] ?? '');
    $r = $chat('Ajoute à la page EQUIPE : Léa Martin, céramiste, avec la photo jointe.', ['media' => [$photo]]);
    $prop = array_values(array_filter($r['cards'], fn ($x) => $x['kind'] === 'proposal'))[0] ?? [];
    check('équipe : proposition (contenu publié non modifié)', ($prop['id'] ?? '') === $teamId && !str_contains((string) file_get_contents($copy . '/data/content/' . $teamId . '.php'), 'Léa'), json_encode($r['cards']) . json_encode($r['errors']));
    $last = array_values(array_filter($fakeLog(), fn ($l) => $l['p'] === '/v1/messages'));
    $sent = json_encode($last[count($last) - 2]['b']['messages'] ?? []);
    check('photo jointe envoyée au modèle (image base64)', str_contains($sent, '"type":"image"') && str_contains($sent, '"media_type":"image\/webp"') && str_contains($sent, 'media:' . $photo));
    $preview = $c->req('GET', '/gestion/ai/preview/' . ($prop['pid'] ?? 'x'));
    check('aperçu de la proposition', $preview['status'] === 200 && str_contains($preview['body'], 'Léa Martin') && str_contains($preview['body'], 'Céramiste'));
    $applied = $api('POST', 'api/ai/apply', ['pid' => $prop['pid'] ?? '']);
    $page = (string) file_get_contents($copy . '/data/content/' . $teamId . '.php');
    check('proposition appliquée : membre, photo et lien', ($applied['json']['ok'] ?? false) && str_contains($page, '"name": "Léa Martin"') && str_contains($page, '"media": "' . $photo . '"') && str_contains($page, 'linkedin.com/in/lea-martin'));
    $public = client($port)->req('GET', '/notre-equipe');
    check('page publique : bloc équipe rendu', $public['status'] === 200 && str_contains($public['body'], 'class="team"') && str_contains($public['body'], 'Léa Martin'));
    check('proposition à usage unique', ($api('POST', 'api/ai/apply', ['pid' => $prop['pid'] ?? ''])['json']['ok'] ?? true) === false);
    $undone = $api('POST', 'api/ai/undo', ['token' => $applied['json']['undo'] ?? '']);
    check('annulation : version d’avant rétablie', ($undone['json']['ok'] ?? false) && !str_contains((string) file_get_contents($copy . '/data/content/' . $teamId . '.php'), 'Léa'));

    // Retouche d'un brouillon : appliquée directement, annulable.
    $r = $chat('MODIFIE ' . $draftId . ' pour améliorer l’introduction');
    $upd = array_values(array_filter($r['cards'], fn ($x) => $x['kind'] === 'updated'))[0] ?? [];
    $after = json_decode(substr((string) file_get_contents($copy . '/data/content/' . $draftId . '.php'), strlen(Store::GUARD)), true);
    $headings = array_map(fn ($b) => plain($b['text']), array_values(array_filter($after['blocks'], fn ($b) => $b['type'] === 'heading')));
    check('retouche : brouillon modifié directement', ($upd['id'] ?? '') === $draftId && $after['title'] === 'Débuter la céramique : 5 étapes simples' && str_contains($after['blocks'][0]['text'], 'avant tout de la <em>patience</em>'));
    check('retouche : section insérée au bon endroit', array_slice($headings, 0, 3) === ['Pourquoi se lancer ?', 'Où trouver un atelier ?', 'Quel matériel choisir ?'], implode(' | ', $headings));
    check('retouche : passage introuvable signalé au modèle', str_contains($r['text'], 'C’est fait') && str_contains(json_encode($fakeLog()), 'not_found'));
    $api('POST', 'api/ai/undo', ['token' => $upd['undo'] ?? '']);
    $restored = json_decode(substr((string) file_get_contents($copy . '/data/content/' . $draftId . '.php'), strlen(Store::GUARD)), true);
    check('retouche annulée', $restored['title'] === 'Comment débuter la céramique en 5 étapes' && $restored['status'] === 'draft');

    // Contenu ouvert dans l'éditeur : rien n'est enregistré côté serveur.
    $before = count(glob($copy . '/data/content/*.php'));
    $r = $chat('EDITEUR : ajoute une FAQ', ['context' => ['item' => ['id' => '', 'type' => 'post', 'title' => 'Brouillon local', 'status' => 'draft',
        'blocks' => [['type' => 'paragraph', 'text' => 'Texte non enregistré.']], 'seo' => ['title' => '', 'description' => '', 'keyword' => ''], 'options' => []]]]);
    $ed = array_values(array_filter($r['cards'], fn ($x) => $x['kind'] === 'editor'))[0] ?? [];
    check('éditeur : modification renvoyée à l’éditeur', in_array('faq', array_column($ed['patch']['blocks'] ?? [], 'type'), true) && str_starts_with($ed['patch']['summary'] ?? '', 'Un résumé neuf'));
    check('éditeur : aucun contenu enregistré', count(glob($copy . '/data/content/*.php')) === $before);
    $sys = array_values(array_filter($fakeLog(), fn ($l) => $l['p'] === '/v1/messages'));
    check('éditeur : texte non enregistré transmis au modèle', str_contains(end($sys)['b']['system'][1]['text'] ?? '', 'Texte non enregistré.'));

    // Refus de sécurité et erreurs.
    $r = $chat('REFUS');
    check('refus du fournisseur expliqué', $r['errors'] && str_contains($r['errors'][0], 'refusé'), json_encode($r['errors']));

    // Retouches ciblées.
    $doc = ['id' => $draftId, 'type' => 'post', 'title' => 'Céramique', 'blocks' => [['type' => 'paragraph', 'text' => 'Débuter la céramique est simple.']], 'seo' => ['keyword' => '']];
    $s1 = $api('POST', 'api/ai/assist', ['task' => 'summary', 'item' => $doc])['json'];
    $s2 = $api('POST', 'api/ai/assist', ['task' => 'titles', 'item' => $doc])['json'];
    $s3 = $api('POST', 'api/ai/assist', ['task' => 'faq', 'item' => $doc])['json'];
    $s4 = $api('POST', 'api/ai/assist', ['task' => 'rewrite', 'mode' => 'improve', 'html' => 'Un <em>texte</em> à revoir.', 'item' => $doc])['json'];
    $s5 = $api('POST', 'api/ai/assist', ['task' => 'alt', 'media' => $photo])['json'];
    $s6 = $api('POST', 'api/ai/assist', ['task' => 'meta', 'item' => $doc])['json'];
    check('retouches : résumé, titres, FAQ, méta', ($s1['summary'] ?? '') === 'Exemple summary' && count($s2['titles'] ?? []) === 2 && ($s3['items'][0]['a'] ?? '') !== '' && ($s6['seo_title'] ?? '') === 'Exemple seo_title', json_encode([$s1, $s2, $s3]));
    check('retouche de texte assainie (script et contenu retirés)', ($s4['html'] ?? '') === '<strong>Réécrit</strong> : Un texte à revoir.', $s4['html'] ?? '');
    check('texte alternatif décrit et enregistré', ($s5['media']['alt'] ?? '') === 'Atelier lumineux avec des bols en céramique');
    check('retouche sans contenu : explication', str_contains((string) ($api('POST', 'api/ai/assist', ['task' => 'summary', 'item' => ['type' => 'post', 'blocks' => []]])['json']['error'] ?? ''), 'contenu'));
    $p = $api('POST', 'api/ai/profile', [])['json'];
    check('profil éditorial rédigé à partir du site', ($p['profile']['tone'] ?? '') === 'Exemple tone' && count($p['profile'] ?? []) === 6);

    // Publication depuis la carte : toujours un clic humain.
    $pub = $api('POST', 'api/ai/publish', ['id' => $draftId])['json'];
    check('publication depuis la carte', ($pub['ok'] ?? false) && $pub['visible'] === true && ($pub['card']['state'] ?? '') === 'published');
    check('article publié visible', client($port)->req('GET', '/blog/' . ($restored['slug'] ?? 'x'))['status'] === 200);

    // Historique des conversations.
    $list = $api('GET', 'api/ai/chats')['json']['chats'] ?? [];
    $one = $api('GET', 'api/ai/chat/' . ($list[0]['id'] ?? 'x'))['json'];
    check('historique : conversations et messages', count($list) >= 5 && ($one['chat']['messages'][0]['r'] ?? '') === 'u');
    $api('POST', 'api/ai/forget', ['id' => $list[0]['id'] ?? '']);
    check('historique : conversation supprimée', count($api('GET', 'api/ai/chats')['json']['chats'] ?? []) === count($list) - 1);

    // Autres fournisseurs : ChatGPT (Responses API), FLUX, Ideogram.
    $settingsAi(['provider' => 'openai', 'key_ai_openai' => 'sk-test-openai-0123456789', 'model_openai' => 'gpt-6.1-sol', 'image' => 'bfl', 'key_image_bfl' => 'bfl-test-key-0123456789']);
    $r = $chat('Écris un ARTICLE avec GPT');
    $gpt = array_values(array_filter($r['cards'], fn ($x) => $x['kind'] === 'draft'))[0] ?? [];
    check('ChatGPT : brouillon créé via la Responses API', ($gpt['title'] ?? '') === 'Article écrit par GPT' && str_contains($r['text'], 'Brouillon prêt') && !$r['errors'], json_encode($r['errors']));
    $flux = $api('POST', 'api/ai/assist', ['task' => 'cover', 'item' => $doc])['json'];
    check('FLUX : image suivie puis téléchargée sans la clé', ($flux['ok'] ?? false) && in_array('/delivery/job1.jpg', array_column($fakeLog(), 'p'), true)
        && in_array('/v1/get_result', array_column($fakeLog(), 'p'), true), json_encode($flux));
    $settingsAi(['provider' => 'openai', 'image' => 'ideogram', 'key_image_ideogram' => 'ideo-test-key-0123456789']);
    $ideo = $api('POST', 'api/ai/assist', ['task' => 'cover', 'item' => $doc])['json'];
    check('Ideogram : image créée (formulaire multipart)', ($ideo['ok'] ?? false) && ($ideo['media']['w'] ?? 0) === 1344, json_encode($ideo));

    // Budget et quotas.
    $settingsAi(['provider' => 'anthropic', 'image' => '']);
    settings($copy, ['ai' => ['budget' => 0.001]]);
    $over = $api('POST', 'api/ai/chat', ['message' => 'Bonjour']);
    check('budget du mois atteint : demande refusée', $over['status'] === 429 && str_contains((string) ($over['json']['error'] ?? ''), 'Budget'), $over['body']);
    settings($copy, ['ai' => ['budget' => 10, 'daily' => 1]]);
    $quota = $api('POST', 'api/ai/chat', ['message' => 'Bonjour']);
    check('quota quotidien atteint : demande refusée', $quota['status'] === 429 && str_contains((string) ($quota['json']['error'] ?? ''), 'limite'), $quota['body']);
    settings($copy, ['ai' => ['daily' => 60]]);

    // Rédacteur : accès selon le réglage, jamais aux réglages ni aux tests de clé.
    $users = json_decode(substr((string) file_get_contents($copy . '/data/users.php'), strlen(Store::GUARD)), true);
    $users['ed1'] = ['id' => 'ed1', 'name' => 'Rédac IA', 'email' => 'redac-ia@example.com', 'role' => 'editor', 'pass' => password_hash('Mot-de-passe-redac-1', PASSWORD_DEFAULT),
        'ver' => 1, 'created' => time(), 'slug' => 'redac-ia', 'lang' => 'fr', 'must_change' => false, 'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []]];
    file_put_contents($copy . '/data/users.php', Store::GUARD . json_encode($users));
    $e = client($port);
    $e->login('redac-ia@example.com', 'Mot-de-passe-redac-1');
    preg_match('/"csrf":"([a-f0-9]+)"/', $e->req('GET', '/gestion')['body'], $em);
    $eh = ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($em[1] ?? '')];
    check('rédacteur : assistant disponible', $e->req('GET', '/gestion/api/ai/chats', [], $eh)['status'] === 200);
    check('rédacteur : test de clé interdit', $e->req('POST', '/gestion/api/ai/test', '{"provider":"anthropic"}', $eh)['status'] === 403);
    check('rédacteur : proposition d’un autre introuvable', str_contains($e->req('POST', '/gestion/api/ai/undo', '{"token":"abc"}', $eh)['body'], '"ok":false'));
    settings($copy, ['ai' => ['editors' => false]]);
    check('rédacteur : assistant coupé par l’administrateur', $e->req('GET', '/gestion/api/ai/chats', [], $eh)['status'] === 403);

    // Sauvegarde : ni les clés ni les conversations.
    if (class_exists('ZipArchive')) {
        $sec = $c->req('GET', '/gestion/security');
        $zip = $c->req('POST', '/gestion/backup', $c->token($sec['body']) + ['confirm_password' => 'Un-mot-de-passe-solide']);
        $file = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($file, $zip['body']);
        $z = new ZipArchive();
        $names = [];
        if ($z->open($file) === true) {
            for ($i = 0; $i < $z->numFiles; $i++) {
                $names[] = $z->getNameIndex($i);
            }
            $z->close();
        }
        @unlink($file);
        check('sauvegarde sans clés ni conversations IA', in_array('data/site.php', $names, true) && !preg_grep('~^data/(vault\.php|ai/)~', $names), implode(',', array_slice($names, 0, 12)));
    }
} finally {
    proc_terminate($server);
    proc_terminate($fake);
    exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($fakeDir));
}
