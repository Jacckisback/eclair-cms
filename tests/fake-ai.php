<?php
/**
 * Faux fournisseurs d'IA pour les tests (serveur intégré de PHP) : Messages API d'Anthropic,
 * Responses API d'OpenAI, images OpenAI, FLUX (Black Forest Labs) et Ideogram.
 * Chaque requête est vérifiée (en-têtes, forme du corps, renvoi fidèle des blocs du tour précédent)
 * puis journalisée dans $FAKE_AI_DIR/log.jsonl ; une requête mal formée reçoit une erreur 400 explicite.
 *
 *   FAKE_AI_DIR=/tmp/x php -S 127.0.0.1:9999 tests/fake-ai.php
 *
 * Scénarios (mot-clé dans le dernier message de la personne) : ARTICLE (image puis brouillon), EQUIPE (recherche
 * puis ajout d'un membre), MODIFIE <id> (retouche d'un brouillon), EDITEUR (modification du contenu ouvert),
 * PIEGE (texte malveillant), REFUS (refus de sécurité) ; sinon une simple réponse texte.
 */
declare(strict_types=1);

$dir = (string) getenv('FAKE_AI_DIR') ?: sys_get_temp_dir();
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$raw = (string) file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$header = static fn (string $name): string => (string) ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '');
$base = 'http://' . $_SERVER['HTTP_HOST'];
file_put_contents($dir . '/log.jsonl', json_encode(['m' => $method, 'p' => $path, 'b' => $body ?: $_POST, 'h' => [
    'key' => $header('x-api-key') ?: $header('authorization') ?: $header('x-key') ?: $header('api-key'),
    'version' => $header('anthropic-version'), 'beta' => $header('anthropic-beta'),
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

function fail(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => $message]]);
    exit;
}

function sse(array $events): never
{
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    foreach ($events as $e) {
        echo 'event: ' . $e['type'] . "\n" . 'data: ' . json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        flush();
    }
    exit;
}

/** Image de test (JPEG ou PNG) aux dimensions demandées. */
function picture(int $w, int $h, string $type = 'png'): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, (int) imagecolorallocate($img, 91, 76, 240));
    imagefilledellipse($img, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), (int) imagecolorallocate($img, 255, 201, 40));
    ob_start();
    $type === 'jpeg' ? imagejpeg($img, null, 80) : imagepng($img);
    return (string) ob_get_clean();
}

/** Valeur d'exemple conforme à un schéma JSON (sorties structurées). */
function sample(array $schema, string $key = 'value', string $input = ''): mixed
{
    return match ($schema['type'] ?? 'string') {
        'object' => (object) array_combine(array_keys($schema['properties']), array_map(fn ($k) => sample($schema['properties'][$k], (string) $k, $input), array_keys($schema['properties']))),
        'array' => [sample($schema['items'], $key . ' 1', $input), sample($schema['items'], $key . ' 2', $input)],
        'integer', 'number' => 1,
        'boolean' => true,
        default => match ($key) {
            'html' => '<strong>Réécrit</strong> : ' . strip_tags($input) . ' <script>alert(1)</script>',
            'alt' => 'Atelier lumineux avec des bols en céramique',
            'prompt' => 'A bright ceramic studio with handmade bowls on a wooden table, soft morning light, warm tones, natural editorial photograph, no text.',
            'answer' => 'Une réponse claire et utile, en **deux** phrases. Voir [le contact](/contact).',
            default => 'Exemple ' . $key,
        },
    };
}

/* ───────────────────────────── Anthropic ───────────────────────────── */

if (preg_match('~^/v1/models/([\w.-]+)$~', $path, $m) && $method === 'GET') {
    $key = $header('x-api-key') ?: str_replace('Bearer ', '', $header('authorization'));
    if (!in_array($key, ['sk-ant-test-0123456789', 'sk-test-openai-0123456789'], true)) {
        fail('invalid x-api-key', 401);
    }
    header('Content-Type: application/json');
    echo json_encode(['id' => $m[1], 'type' => 'model', 'display_name' => $m[1]]);
    exit;
}

if ($path === '/v1/messages' && $method === 'POST') {
    if ($header('x-api-key') !== 'sk-ant-test-0123456789') {
        fail('invalid x-api-key', 401);
    }
    $errors = [];
    if ($header('anthropic-version') !== '2026-10-21') {
        $errors[] = 'anthropic-version must be 2026-10-21';
    }
    foreach (['temperature', 'top_p', 'top_k', 'thinking'] as $k) {
        if (isset($body[$k])) {
            $errors[] = "$k must not be sent";
        }
    }
    if (($body['stream'] ?? false) !== true || !is_array($body['system'] ?? null) || ($body['system'][0]['cache_control']['type'] ?? '') !== 'ephemeral') {
        $errors[] = 'stream, system blocks and cache_control expected';
    }
    if ($body['model'] === 'claude-opus-5-5' && (($body['fallbacks'] ?? '') !== 'default' || !str_contains($header('anthropic-beta'), 'server-side-fallback-2026-07-01'))) {
        $errors[] = 'fallbacks "default" + beta header expected';
    }
    if (isset($body['tool_choice'])) {
        $errors[] = 'tool_choice must not be forced';
    }
    $messages = $body['messages'] ?? [];
    if (!$messages || $messages[0]['role'] !== 'user' || end($messages)['role'] !== 'user') {
        $errors[] = 'messages must start and end with a user turn';
    }
    // Le tour précédent doit revenir intact : bloc de réflexion signé, puis un résultat par outil demandé.
    $calls = [];
    foreach ($messages as $i => $msg) {
        foreach (is_array($msg['content']) ? $msg['content'] : [] as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $calls[$block['id']] = $block;
                if (!is_array($block['input']) && !is_object($block['input'])) {
                    $errors[] = 'tool_use input must be an object';
                }
            }
            if (($block['type'] ?? '') === 'thinking' && ($block['signature'] ?? '') !== 'sig-' . $i) {
                $errors[] = 'thinking block not echoed unchanged at message ' . $i;
            }
            if (($block['type'] ?? '') === 'tool_result') {
                unset($calls[$block['tool_use_id']]);
            }
        }
    }
    if ($calls) {
        $errors[] = 'missing tool_result for ' . implode(', ', array_keys($calls));
    }
    if ($errors) {
        fail(implode('; ', $errors));
    }
    $structured = $body['output_config']['format']['schema'] ?? null;
    $last = end($messages)['content'];
    $results = array_values(array_filter(is_array($last) ? $last : [], fn ($b) => ($b['type'] ?? '') === 'tool_result'));
    $userText = '';
    foreach (array_reverse($messages) as $msg) {
        $texts = array_filter(is_array($msg['content']) ? $msg['content'] : [], fn ($b) => ($b['type'] ?? '') === 'text');
        if ($msg['role'] === 'user' && $texts) {
            $userText = implode(' ', array_column($texts, 'text'));
            break;
        }
    }
    $index = count($messages);
    $usage = ['input_tokens' => 1000, 'cache_creation_input_tokens' => 2000, 'cache_read_input_tokens' => 500, 'output_tokens' => 1];
    $events = [['type' => 'message_start', 'message' => ['id' => 'msg_' . $index, 'type' => 'message', 'role' => 'assistant', 'model' => $body['model'], 'content' => [], 'usage' => $usage]]];
    $block = 0;
    $text = static function (string $t) use (&$events, &$block): void {
        $events[] = ['type' => 'content_block_start', 'index' => $block, 'content_block' => ['type' => 'text', 'text' => '']];
        foreach (mb_str_split($t, 12) as $piece) {
            $events[] = ['type' => 'content_block_delta', 'index' => $block, 'delta' => ['type' => 'text_delta', 'text' => $piece]];
        }
        $events[] = ['type' => 'content_block_stop', 'index' => $block++];
    };
    $tool = static function (string $name, array $input) use (&$events, &$block, $index): void {
        $json = json_encode($input, JSON_UNESCAPED_UNICODE);
        $events[] = ['type' => 'content_block_start', 'index' => $block, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_' . $index . '_' . $block, 'name' => $name, 'input' => new stdClass()]];
        foreach (mb_str_split($json, 40) as $piece) {
            $events[] = ['type' => 'content_block_delta', 'index' => $block, 'delta' => ['type' => 'input_json_delta', 'partial_json' => $piece]];
        }
        $events[] = ['type' => 'content_block_stop', 'index' => $block++];
    };
    // Réflexion (texte omis, seule la signature arrive) : elle devra être renvoyée telle quelle.
    $events[] = ['type' => 'content_block_start', 'index' => $block, 'content_block' => ['type' => 'thinking', 'thinking' => '', 'signature' => '']];
    $events[] = ['type' => 'content_block_delta', 'index' => $block, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig-' . $index]];
    $events[] = ['type' => 'content_block_stop', 'index' => $block++];
    $events[] = ['type' => 'ping'];

    $stop = 'end_turn';
    $first = $results ? json_decode((string) $results[0]['content'], true) : null;
    if ($structured) {
        $text(json_encode(sample($structured, 'value', $userText), JSON_UNESCAPED_UNICODE));
    } elseif (str_contains($userText, 'REFUS')) {
        $stop = 'refusal';
    } elseif (str_contains($userText, 'ARTICLE') && !$results) {
        $text('Je prépare ton article.');
        $tool('generate_image', ['prompt' => 'A bright ceramic studio with handmade bowls, soft morning light, warm tones, natural editorial photograph, no text.', 'alt' => 'Bols en céramique dans un atelier lumineux', 'format' => 'landscape', 'name' => 'atelier ceramique']);
        $stop = 'tool_use';
    } elseif (str_contains($userText, 'ARTICLE') && isset($first['media_id'])) {
        $tool('create_draft', [
            'type' => 'post', 'title' => 'Comment débuter la céramique en 5 étapes', 'keyword' => 'débuter la céramique',
            'summary' => 'Pour débuter la céramique, commence par un cours d’initiation, choisis une argile facile et pratique régulièrement. En cinq étapes simples, tu crées tes premières pièces en quelques semaines.',
            'seo_title' => 'Débuter la céramique : le guide en 5 étapes', 'seo_description' => 'Comment débuter la céramique ? Matériel, argile, premiers gestes et erreurs à éviter : notre guide en 5 étapes pour créer tes premières pièces.',
            'image' => $first['media_id'],
            'body' => "# Comment débuter la céramique en 5 étapes\n\nDébuter la céramique demande surtout de la **patience** et un bon accompagnement.\n\n## Pourquoi se lancer ?\n\nLa céramique apaise et développe la créativité. Voir [nos cours](/cours).\n\n## Quel matériel choisir ?\n\n- Une argile *facile* à travailler\n- Des outils de base\n- Un tablier\n\n| Étape | Durée |\n| --- | --- |\n| Modelage | 2 h |\n| Cuisson | 8 h |\n\n> [!TIP] Bon à savoir\n> Commence par des bols simples.\n\n> La terre ne ment jamais.\n> — Une potière\n\n![Bols en céramique](media:" . $first['media_id'] . ")\n\n<script>alert('xss')</script>\n\n[Lien piégé](javascript:alert(1))",
            'faq' => [['question' => 'Combien de temps pour apprendre ?', 'answer' => 'Quelques **semaines** de pratique régulière suffisent pour les bases.']],
        ]);
        $stop = 'tool_use';
    } elseif (str_contains($userText, 'EQUIPE') && !$results) {
        $tool('search_content', ['query' => 'équipe', 'type' => 'page']);
        $stop = 'tool_use';
    } elseif (str_contains($userText, 'EQUIPE') && isset($first['results'])) {
        preg_match('/media:([a-z0-9]+)/', $userText, $photo);
        $tool('update_content', ['id' => $first['results'][0]['id'] ?? 'inconnu', 'note' => 'Ajout de Léa Martin à l’équipe',
            'team_member' => ['name' => 'Léa Martin', 'role' => 'Céramiste', 'bio' => 'Léa anime les **cours du soir** depuis 2019.', 'photo' => $photo[1] ?? '', 'link' => 'https://www.linkedin.com/in/lea-martin']]);
        $stop = 'tool_use';
    } elseif (preg_match('/MODIFIE ([a-z0-9]+)/', $userText, $id) && !$results) {
        $tool('update_content', ['id' => $id[1], 'note' => 'Introduction plus claire et FAQ enrichie', 'title' => 'Débuter la céramique : 5 étapes simples',
            'replace' => [['find' => 'surtout de la patience', 'replace' => 'avant tout de la *patience*'], ['find' => 'texte absent', 'replace' => 'x']],
            'append' => "## Où trouver un atelier ?\n\nCherche un atelier près de chez toi.", 'after_heading' => 'Pourquoi se lancer ?',
            'faq' => [['question' => 'Faut-il un four ?', 'answer' => 'Non, l’atelier cuit tes pièces.']]]);
        $stop = 'tool_use';
    } elseif (str_contains($userText, 'EDITEUR') && !$results) {
        $tool('update_content', ['id' => 'current', 'note' => 'FAQ ajoutée', 'faq' => [['question' => 'Est-ce difficile ?', 'answer' => 'Non, avec un bon accompagnement.']], 'summary' => 'Un résumé neuf et clair pour ce contenu en cours de rédaction.']);
        $stop = 'tool_use';
    } elseif ($results) {
        $text('C’est fait ! ' . (isset($first['error']) ? 'Erreur outil : ' . $first['error'] : 'Relis le brouillon avant de le publier.'));
    } else {
        $text('Bonjour ! Je peux **écrire un article**, créer une page ou retoucher tes textes.');
    }
    $events[] = ['type' => 'message_delta', 'delta' => ['stop_reason' => $stop, 'stop_sequence' => null], 'usage' => ['output_tokens' => 400]];
    $events[] = ['type' => 'message_stop'];
    sse($events);
}

/* ───────────────────────────── OpenAI ───────────────────────────── */

if ($path === '/v1/responses' && $method === 'POST') {
    if ($header('authorization') !== 'Bearer sk-test-openai-0123456789') {
        fail('Incorrect API key provided: sk-test…6789', 401);
    }
    $errors = [];
    if (($body['store'] ?? true) !== false || ($body['stream'] ?? false) !== true || !is_string($body['instructions'] ?? null)) {
        $errors[] = 'store=false, stream=true and instructions expected';
    }
    foreach (['temperature', 'top_p'] as $k) {
        if (isset($body[$k])) {
            $errors[] = "$k must not be sent";
        }
    }
    $calls = [];
    $reasoning = 0;
    foreach ($body['input'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'function_call') {
            $calls[$item['call_id']] = true;
        }
        if (($item['type'] ?? '') === 'function_call_output') {
            unset($calls[$item['call_id']]);
        }
        if (($item['type'] ?? '') === 'reasoning') {
            $reasoning += ($item['encrypted_content'] ?? '') === 'enc-1' ? 1 : 100;
        }
    }
    if ($calls) {
        $errors[] = 'missing function_call_output for ' . implode(', ', array_keys($calls));
    }
    if ($reasoning >= 100) {
        $errors[] = 'reasoning item not echoed unchanged';
    }
    if ($errors) {
        fail(implode('; ', $errors));
    }
    $outputs = array_values(array_filter($body['input'], fn ($i) => ($i['type'] ?? '') === 'function_call_output'));
    $userText = '';
    foreach (array_reverse($body['input']) as $item) {
        if (($item['role'] ?? '') === 'user') {
            foreach ((array) $item['content'] as $part) {
                $userText .= is_array($part) ? (string) ($part['text'] ?? '') : (string) $part;
            }
            break;
        }
    }
    $out = [['type' => 'reasoning', 'id' => 'rs_1', 'summary' => [], 'encrypted_content' => 'enc-1']];
    $events = [['type' => 'response.created', 'response' => ['id' => 'resp_1', 'status' => 'in_progress']]];
    $structured = $body['text']['format']['schema'] ?? null;
    if ($structured) {
        $answer = json_encode(sample($structured, 'value', $userText), JSON_UNESCAPED_UNICODE);
    } elseif (str_contains($userText, 'ARTICLE') && count($outputs) === 0) {
        $out[] = ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_1', 'name' => 'create_draft', 'status' => 'completed', 'arguments' => json_encode([
            'type' => 'post', 'title' => 'Article écrit par GPT', 'body' => "Introduction.\n\n## Partie\n\nTexte.", 'summary' => 'Résumé.', 'keyword' => 'test',
        ], JSON_UNESCAPED_UNICODE)];
    } else {
        $answer = $outputs ? 'Brouillon prêt, relis-le.' : 'Bonjour depuis GPT.';
    }
    foreach ($out as $i => $item) {
        $events[] = ['type' => 'response.output_item.added', 'output_index' => $i, 'item' => ['type' => $item['type'], 'id' => $item['id']] + ($item['type'] === 'function_call' ? ['call_id' => $item['call_id'], 'name' => $item['name'], 'arguments' => ''] : [])];
        $events[] = ['type' => 'response.output_item.done', 'output_index' => $i, 'item' => $item];
    }
    if (isset($answer)) {
        $i = count($out);
        $events[] = ['type' => 'response.output_item.added', 'output_index' => $i, 'item' => ['type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'content' => []]];
        foreach (mb_str_split($answer, 10) as $piece) {
            $events[] = ['type' => 'response.output_text.delta', 'item_id' => 'msg_1', 'output_index' => $i, 'content_index' => 0, 'delta' => $piece];
        }
        $out[] = ['type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'status' => 'completed', 'phase' => 'final_answer', 'content' => [['type' => 'output_text', 'text' => $answer, 'annotations' => []]]];
        $events[] = ['type' => 'response.output_item.done', 'output_index' => $i, 'item' => end($out)];
    }
    $events[] = ['type' => 'response.completed', 'response' => ['id' => 'resp_1', 'status' => 'completed', 'output' => $out,
        'usage' => ['input_tokens' => 3000, 'input_tokens_details' => ['cached_tokens' => 1000, 'cache_write_tokens' => 500], 'output_tokens' => 600]]];
    sse($events);
}

/* ───────────────────────────── Images ───────────────────────────── */

if ($path === '/v1/images/generations' && $method === 'POST') {
    if ($header('authorization') !== 'Bearer sk-test-openai-0123456789') {
        fail('Incorrect API key provided', 401);
    }
    if (($body['model'] ?? '') !== 'gpt-image-2.5-flare' || ($body['size'] ?? '') !== '1536x1024' || ($body['output_format'] ?? '') !== 'webp' || isset($body['response_format'])) {
        fail('unexpected image request: ' . json_encode($body));
    }
    header('Content-Type: application/json');
    echo json_encode(['created' => time(), 'data' => [['b64_json' => base64_encode(picture(1536, 1024))]], 'usage' => ['input_tokens' => 60, 'output_tokens' => 1500]]);
    exit;
}

if ($path === '/v1/flux-3-image' && $method === 'POST') {
    if ($header('x-key') !== 'bfl-test-key-0123456789') {
        fail('Not authenticated', 401);
    }
    if (($body['aspect_ratio'] ?? '') !== '16:9' || ($body['resolution'] ?? '') !== '1k' || isset($body['output_format']) || isset($body['seed'])) {
        fail('unexpected FLUX request', 422);
    }
    header('Content-Type: application/json');
    echo json_encode(['id' => 'job1', 'polling_url' => $base . '/v1/get_result?id=job1', 'cost' => 4.8]);
    exit;
}

if ($path === '/v1/get_result') {
    if ($header('x-key') !== 'bfl-test-key-0123456789') {
        fail('Not authenticated', 401);
    }
    $count = (int) @file_get_contents($dir . '/poll') + 1;
    file_put_contents($dir . '/poll', (string) $count);
    header('Content-Type: application/json');
    echo json_encode($count < 2 ? ['id' => 'job1', 'status' => 'Pending'] : ['id' => 'job1', 'status' => 'Ready', 'result' => ['sample' => $base . '/delivery/job1.jpg']]);
    exit;
}

if (preg_match('~^/v2/image/generate/ideogram-3$~', $path) && $method === 'POST') {
    if ($header('api-key') !== 'ideo-test-key-0123456789') {
        fail('Unauthorized', 401);
    }
    if (($_POST['aspect_ratio'] ?? '') !== '16x9' || ($_POST['rendering_speed'] ?? '') !== 'default' || ($_POST['prompt'] ?? '') === '') {
        fail('unexpected Ideogram request: ' . json_encode($_POST), 422);
    }
    header('Content-Type: application/json');
    echo json_encode(['generation_id' => 'g1', 'data' => [['prompt' => $_POST['prompt'], 'resolution' => '1312x736', 'is_image_safe' => true, 'url' => $base . '/delivery/ideo.png']]]);
    exit;
}

if (str_starts_with($path, '/delivery/')) {
    if ($header('x-key') !== '' || $header('api-key') !== '') {
        fail('provider key must never be sent to delivery URLs', 400);
    }
    $jpeg = str_ends_with($path, '.jpg');
    header('Content-Type: ' . ($jpeg ? 'image/jpeg' : 'image/png'));
    echo picture(1344, 768, $jpeg ? 'jpeg' : 'png');
    exit;
}

fail('unknown endpoint ' . $method . ' ' . $path, 404);
