<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Assistant IA : réglages, catalogue des modèles, budget et appels aux fournisseurs (Claude d'Anthropic,
 * ChatGPT d'OpenAI). Appels HTTPS directs avec cURL, en flux (streaming) : aucune dépendance à installer.
 * Les clés d'API restent sur le serveur (coffre chiffré, voir Vault).
 */
final class Ai
{
    public const PROVIDERS = ['anthropic' => 'Claude (Anthropic)', 'openai' => 'ChatGPT (OpenAI)'];

    /** Modèles de texte : libellé, puis prix en dollars par million de jetons (entrée, sortie, entrée en cache, écriture du cache). */
    public const MODELS = [
        'anthropic' => [
            'claude-opus-5-5' => ['Claude Opus 5.5', 4.0, 20.0, 0.20, 5.0],
            'claude-sonnet-5-5' => ['Claude Sonnet 5.5', 2.0, 10.0, 0.20, 2.5],
            'claude-haiku-5-5' => ['Claude Haiku 5.5', 0.10, 0.50, 0.01, 0.125],
        ],
        'openai' => [
            'gpt-6-astra' => ['GPT-6 Astra', 10.0, 50.0, 1.0, 12.5],
            'gpt-6.1-sol' => ['GPT-6.1 Sol', 2.0, 10.0, 0.10, 2.5],
            'gpt-6.1-terra' => ['GPT-6.1 Terra', 1.0, 5.0, 0.05, 1.25],
            'gpt-6-luna' => ['GPT-6 Luna', 0.10, 0.50, 0.01, 0.125],
        ],
    ];

    /** Générateurs d'images : modèle par défaut et prix indicatif par image (dollars). */
    public const IMAGES = [
        'openai' => ['GPT Image 2.5 (OpenAI)', 'gpt-image-2.5-flare', 0.05],
        'bfl' => ['FLUX 3 (Black Forest Labs)', 'flux-3-image', 0.05],
        'ideogram' => ['Ideogram 3', 'ideogram-3', 0.06],
    ];

    private const ANTHROPIC_VERSION = '2026-10-21';
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';
    private const ENDPOINTS = [
        'anthropic' => 'https://api.anthropic.com',
        'openai' => 'https://api.openai.com',
        'bfl' => 'https://api.bfl.ai',
        'ideogram' => 'https://api.ideogram.ai',
    ];

    /* ───────────────────────────── Réglages ───────────────────────────── */

    public static function config(): array
    {
        $c = Settings::get('ai', []);
        return (is_array($c) ? $c : []) + Settings::DEFAULTS['ai'];
    }

    /** Fournisseur de texte actif (« » si l'assistant est désactivé ou sans clé). */
    public static function provider(): string
    {
        $p = (string) self::config()['provider'];
        return isset(self::PROVIDERS[$p]) && Vault::has('ai.' . $p) ? $p : '';
    }

    public static function enabled(): bool
    {
        return self::provider() !== '';
    }

    public static function allowed(?array $user): bool
    {
        return $user !== null && self::enabled() && ($user['role'] === 'admin' || (bool) self::config()['editors']);
    }

    public static function model(?string $provider = null): string
    {
        $provider ??= self::provider();
        $model = (string) self::config()['model'];
        $models = self::MODELS[$provider] ?? [];
        return isset($models[$model]) ? $model : (string) array_key_first($models);
    }

    public static function label(): string
    {
        $p = self::provider();
        return $p === '' ? '' : (string) (self::MODELS[$p][self::model($p)][0] ?? self::model($p));
    }

    /** Générateur d'images actif (« » si aucun). La clé OpenAI du texte sert aussi aux images OpenAI. */
    public static function imager(): string
    {
        $i = (string) self::config()['image'];
        return isset(self::IMAGES[$i]) && self::imageKey($i) !== '' ? $i : '';
    }

    public static function imageKey(string $provider): string
    {
        return Vault::get('image.' . $provider) ?: ($provider === 'openai' ? Vault::get('ai.openai') : '');
    }

    /** Adresse d'un service ; ECLAIR_AI_ENDPOINTS (eclair-config.php) peut la remplacer (passerelle, tests). */
    public static function endpoint(string $service): string
    {
        $custom = defined('ECLAIR_AI_ENDPOINTS') ? (array) constant('ECLAIR_AI_ENDPOINTS') : [];
        return rtrim((string) ($custom[$service] ?? self::ENDPOINTS[$service] ?? ''), '/');
    }

    /** Vérifie une clé sans rien dépenser : lecture de la fiche du modèle. Renvoie null si tout va bien, sinon l'erreur. */
    public static function verify(string $provider, string $key, string $model): ?string
    {
        $res = $provider === 'openai'
            ? self::request('GET', self::endpoint('openai') . '/v1/models/' . rawurlencode($model), ['Authorization: Bearer ' . $key], null, 20)
            : self::request('GET', self::endpoint('anthropic') . '/v1/models/' . rawurlencode($model), ['x-api-key: ' . $key, 'anthropic-version: ' . self::ANTHROPIC_VERSION], null, 20);
        return $res['status'] === 200 ? null : self::failure($provider, $res);
    }

    /* ─────────────────────────── Budget et quotas ─────────────────────────── */

    public static function usage(): array
    {
        $u = Store::read('ai/usage', []);
        $u = is_array($u) ? $u : [];
        $month = date('Y-m');
        return ($u['months'][$month] ?? []) + ['cost' => 0.0, 'calls' => 0, 'images' => 0, 'users' => []];
    }

    /**
     * Réserve une demande : refuse (message) si le budget du mois est atteint ou si la personne
     * a épuisé son quota du jour. Renvoie null quand la demande peut partir.
     */
    public static function reserve(array $user): ?string
    {
        $c = self::config();
        $budget = (float) $c['budget'];
        if ($budget > 0 && self::usage()['cost'] >= $budget) {
            return t('Budget IA du mois atteint ({spent} sur {budget}). Un administrateur peut l’augmenter dans Réglages › IA.', [
                'spent' => self::money(self::usage()['cost']), 'budget' => self::money($budget),
            ]);
        }
        $daily = (int) $c['daily'];
        return Store::locked(function () use ($user, $daily): ?string {
            $u = Store::read('ai/usage', []);
            $u = is_array($u) ? $u : [];
            $day = date('Y-m-d');
            $row = $u['days'][$user['id']] ?? [];
            $n = ($row['d'] ?? '') === $day ? (int) ($row['n'] ?? 0) : 0;
            if ($daily > 0 && $n >= $daily) {
                return t('Tu as atteint ta limite de {n} demandes à l’assistant pour aujourd’hui.', ['n' => $daily]);
            }
            $u['days'][$user['id']] = ['d' => $day, 'n' => $n + 1];
            Store::write('ai/usage', $u);
            return null;
        });
    }

    /** Ajoute une dépense au compteur du mois (les 12 derniers mois sont gardés). */
    public static function charge(string $userId, float $cost, int $images = 0): void
    {
        if ($cost <= 0 && $images === 0) {
            return;
        }
        Store::locked(function () use ($userId, $cost, $images): void {
            $u = Store::read('ai/usage', []);
            $u = is_array($u) ? $u : [];
            $month = date('Y-m');
            $m = ($u['months'][$month] ?? []) + ['cost' => 0.0, 'calls' => 0, 'images' => 0, 'users' => []];
            $m['cost'] = round((float) $m['cost'] + $cost, 6);
            $m['calls'] += $images > 0 ? 0 : 1;
            $m['images'] += $images;
            $m['users'][$userId] = round((float) ($m['users'][$userId] ?? 0) + $cost, 6);
            $u['months'][$month] = $m;
            krsort($u['months']);
            $u['months'] = array_slice($u['months'], 0, 12, true);
            Store::write('ai/usage', $u);
        });
    }

    /** Coût d'un appel à partir des jetons consommés. */
    public static function cost(string $provider, string $model, array $usage): float
    {
        [, $in, $out, $cached, $write] = self::MODELS[$provider][$model] ?? array_values(self::MODELS[$provider])[0] ?? ['', 5, 25, 0.5, 6.25];
        return ((int) ($usage['in'] ?? 0) * $in + (int) ($usage['out'] ?? 0) * $out
            + (int) ($usage['cache_read'] ?? 0) * $cached + (int) ($usage['cache_write'] ?? 0) * $write) / 1_000_000;
    }

    public static function money(float $amount): string
    {
        $digits = $amount > 0 && $amount < 0.1 ? 3 : 2;
        return I18n::lang() === 'en' || I18n::lang() === 'zh'
            ? '$' . number_format($amount, $digits, '.', ',')
            : number_format($amount, $digits, ',', "\u{202F}") . "\u{00A0}\$";
    }

    /* ─────────────────────────── Appel d'un modèle ─────────────────────────── */

    /**
     * Un appel au modèle de texte, en flux. $req : system (texte stable, mis en cache), context (texte variable),
     * messages (format du fournisseur), tools (définitions neutres), max, effort, format (schéma JSON).
     * $onText reçoit le texte au fil de l'eau, $onTick est appelé régulièrement (maintien de la connexion).
     * Renvoie ok, text, calls [id, name, input], native (tour de l'assistant à renvoyer), stop, usage, cost — ou ok=false + error.
     */
    public static function call(array $req, ?callable $onText = null, ?callable $onTick = null): array
    {
        $provider = (string) ($req['provider'] ?? self::provider());
        $model = (string) ($req['model'] ?? self::model($provider));
        $key = Vault::get('ai.' . $provider);
        if ($key === '') {
            return ['ok' => false, 'error' => t('Assistant IA non configuré : ajoute une clé d’API dans Réglages › IA.'), 'cost' => 0.0];
        }
        $result = $provider === 'openai'
            ? self::openai($key, $model, $req, $onText, $onTick)
            : self::anthropic($key, $model, $req, $onText, $onTick);
        $result['cost'] = isset($result['usage']) ? self::cost($provider, $model, $result['usage']) : 0.0;
        $result['model'] = $model;
        return $result;
    }

    /** Messages API d'Anthropic. */
    private static function anthropic(string $key, string $model, array $req, ?callable $onText, ?callable $onTick): array
    {
        $system = [['type' => 'text', 'text' => (string) $req['system'], 'cache_control' => ['type' => 'ephemeral']]];
        if (trim((string) ($req['context'] ?? '')) !== '') {
            $system[] = ['type' => 'text', 'text' => (string) $req['context']];
        }
        $messages = $req['messages'];
        // Point de cache sur le dernier bloc : les appels suivants du même tour relisent tout le préfixe.
        $last = count($messages) - 1;
        if ($last >= 0 && is_array($messages[$last]['content'] ?? null) && $messages[$last]['content']) {
            $i = count($messages[$last]['content']) - 1;
            $messages[$last]['content'][$i]['cache_control'] = ['type' => 'ephemeral'];
        }
        $body = [
            'model' => $model,
            'max_tokens' => (int) ($req['max'] ?? 16000),
            'system' => $system,
            'messages' => $messages,
            'output_config' => ['effort' => (string) ($req['effort'] ?? 'medium')],
            'stream' => true,
        ];
        if (!empty($req['tools'])) {
            $body['tools'] = array_map(fn (array $t): array => ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['schema']], $req['tools']);
        }
        if (!empty($req['format'])) {
            $body['output_config']['format'] = ['type' => 'json_schema', 'schema' => $req['format']];
        }
        $headers = ['x-api-key: ' . $key, 'anthropic-version: ' . self::ANTHROPIC_VERSION, 'content-type: application/json'];
        // Un refus des filtres de sécurité est rejoué côté serveur sur le modèle recommandé (sauf Haiku, non concerné).
        if ($model !== 'claude-haiku-5-5') {
            $body['fallbacks'] = 'default';
            $headers[] = 'anthropic-beta: ' . self::FALLBACK_BETA;
        }

        $blocks = [];
        $usage = ['in' => 0, 'out' => 0, 'cache_read' => 0, 'cache_write' => 0];
        $stop = '';
        $error = '';
        $readUsage = static function (array $u) use (&$usage): void {
            if (isset($u['iterations']) && is_array($u['iterations'])) {
                $usage = ['in' => 0, 'out' => 0, 'cache_read' => 0, 'cache_write' => 0];
                foreach ($u['iterations'] as $it) {
                    $usage['in'] += (int) ($it['input_tokens'] ?? 0);
                    $usage['out'] += (int) ($it['output_tokens'] ?? 0);
                    $usage['cache_read'] += (int) ($it['cache_read_input_tokens'] ?? 0);
                    $usage['cache_write'] += (int) ($it['cache_creation_input_tokens'] ?? 0);
                }
                return;
            }
            foreach (['input_tokens' => 'in', 'output_tokens' => 'out', 'cache_read_input_tokens' => 'cache_read', 'cache_creation_input_tokens' => 'cache_write'] as $k => $n) {
                if (isset($u[$k])) {
                    $usage[$n] = (int) $u[$k];
                }
            }
        };
        $onData = static function (array $e) use (&$blocks, &$stop, &$error, $readUsage, $onText): void {
            switch ($e['type'] ?? '') {
                case 'message_start':
                    $readUsage((array) ($e['message']['usage'] ?? []));
                    break;
                case 'content_block_start':
                    $blocks[(int) $e['index']] = (array) $e['content_block'] + ['_json' => ''];
                    break;
                case 'content_block_delta':
                    $i = (int) $e['index'];
                    $d = (array) $e['delta'];
                    match ($d['type'] ?? '') {
                        'text_delta' => $blocks[$i]['text'] = ($blocks[$i]['text'] ?? '') . $d['text'],
                        'thinking_delta' => $blocks[$i]['thinking'] = ($blocks[$i]['thinking'] ?? '') . $d['thinking'],
                        'signature_delta' => $blocks[$i]['signature'] = ($blocks[$i]['signature'] ?? '') . $d['signature'],
                        'input_json_delta' => $blocks[$i]['_json'] .= $d['partial_json'],
                        default => null,
                    };
                    if (($d['type'] ?? '') === 'text_delta' && $onText) {
                        $onText((string) $d['text']);
                    }
                    break;
                case 'message_delta':
                    $stop = (string) ($e['delta']['stop_reason'] ?? $stop);
                    $readUsage((array) ($e['usage'] ?? []));
                    break;
                case 'error':
                    $error = (string) ($e['error']['message'] ?? 'error');
                    break;
            }
        };
        $http = self::stream(self::endpoint('anthropic') . '/v1/messages', $headers, $body, $onData, $onTick);
        if ($http['error'] !== '' || $http['status'] >= 400 || $error !== '') {
            return ['ok' => false, 'error' => self::failure('anthropic', $http, $error), 'usage' => $usage];
        }
        ksort($blocks);
        // Après une bascule en cours de réponse, seuls les textes d'avant la dernière bascule sont renvoyés au modèle.
        $cut = -1;
        foreach ($blocks as $i => $b) {
            if (($b['type'] ?? '') === 'fallback') {
                $cut = $i;
            }
        }
        $content = [];
        $calls = [];
        $text = '';
        foreach ($blocks as $i => $b) {
            $type = (string) ($b['type'] ?? '');
            $json = $b['_json'];
            unset($b['_json']);
            if ($type === 'fallback' || ($i < $cut && $type !== 'text')) {
                continue;
            }
            if ($type === 'tool_use') {
                $input = $json !== '' ? json_decode($json, true) : ($b['input'] ?? []);
                $b['input'] = is_array($input) && $input !== [] ? $input : new \stdClass();
                $calls[] = ['id' => (string) $b['id'], 'name' => (string) $b['name'], 'input' => is_array($input) ? $input : []];
            }
            if ($type === 'text') {
                $text .= (string) ($b['text'] ?? '');
                if (($b['text'] ?? '') === '') {
                    continue;
                }
            }
            $content[] = $b;
        }
        return [
            'ok' => true,
            'text' => $text,
            'calls' => $calls,
            'native' => $content ? [['role' => 'assistant', 'content' => $content]] : [],
            'stop' => match ($stop) {
                'end_turn', 'stop_sequence' => 'end',
                'tool_use' => 'tool',
                'max_tokens' => 'max',
                'refusal' => 'refusal',
                default => $stop ?: 'end',
            },
            'usage' => $usage,
        ];
    }

    /** Responses API d'OpenAI (sans état côté OpenAI : store=false, raisonnement chiffré renvoyé à chaque tour). */
    private static function openai(string $key, string $model, array $req, ?callable $onText, ?callable $onTick): array
    {
        $body = [
            'model' => $model,
            'instructions' => trim((string) $req['system'] . "\n\n" . (string) ($req['context'] ?? '')),
            'input' => $req['messages'],
            'max_output_tokens' => (int) ($req['max'] ?? 16000),
            'reasoning' => ['effort' => (string) ($req['effort'] ?? 'medium')],
            'store' => false,
            'stream' => true,
            'stream_options' => ['include_obfuscation' => false],
        ];
        if (!empty($req['tools'])) {
            $body['tools'] = array_map(fn (array $t): array => [
                'type' => 'function', 'name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['schema'], 'strict' => false,
            ], $req['tools']);
        }
        if (!empty($req['format'])) {
            $body['text'] = ['format' => ['type' => 'json_schema', 'name' => 'result', 'schema' => $req['format'], 'strict' => true]];
        }
        $headers = ['Authorization: Bearer ' . $key, 'Content-Type: application/json'];
        $final = null;
        $items = [];
        $error = '';
        $refusal = false;
        $onData = static function (array $e) use (&$final, &$items, &$error, &$refusal, $onText): void {
            switch ($e['type'] ?? '') {
                case 'response.output_item.done':
                    $items[(int) ($e['output_index'] ?? count($items))] = (array) ($e['item'] ?? []);
                    break;
                case 'response.output_text.delta':
                    if ($onText) {
                        $onText((string) ($e['delta'] ?? ''));
                    }
                    break;
                case 'response.refusal.delta':
                    $refusal = true;
                    break;
                case 'response.completed':
                case 'response.incomplete':
                    $final = (array) ($e['response'] ?? []);
                    break;
                case 'response.failed':
                    $error = (string) ($e['response']['error']['message'] ?? 'failed');
                    break;
                case 'error':
                    $error = (string) ($e['message'] ?? $e['error']['message'] ?? 'error');
                    break;
            }
        };
        $http = self::stream(self::endpoint('openai') . '/v1/responses', $headers, $body, $onData, $onTick);
        $u = (array) ($final['usage'] ?? []);
        $cached = (int) ($u['input_tokens_details']['cached_tokens'] ?? 0);
        $written = (int) ($u['input_tokens_details']['cache_write_tokens'] ?? 0);
        $usage = ['in' => max(0, (int) ($u['input_tokens'] ?? 0) - $cached - $written), 'out' => (int) ($u['output_tokens'] ?? 0),
            'cache_read' => $cached, 'cache_write' => $written];
        if ($http['error'] !== '' || $http['status'] >= 400 || $error !== '' || $final === null) {
            return ['ok' => false, 'error' => self::failure('openai', $http, $error), 'usage' => $usage];
        }
        $text = '';
        $calls = [];
        $native = [];
        // Les éléments complets (raisonnement chiffré compris) arrivent avec « output_item.done ».
        ksort($items);
        foreach ($items ?: (array) ($final['output'] ?? []) as $item) {
            $item = (array) $item;
            $native[] = $item;
            if (($item['type'] ?? '') === 'function_call') {
                $input = json_decode((string) ($item['arguments'] ?? ''), true);
                $calls[] = ['id' => (string) $item['call_id'], 'name' => (string) $item['name'], 'input' => is_array($input) ? $input : []];
            } elseif (($item['type'] ?? '') === 'message') {
                foreach ((array) ($item['content'] ?? []) as $part) {
                    if (($part['type'] ?? '') === 'output_text') {
                        $text .= (string) $part['text'];
                    } elseif (($part['type'] ?? '') === 'refusal') {
                        $refusal = true;
                    }
                }
            }
        }
        $incomplete = (string) ($final['incomplete_details']['reason'] ?? '');
        return [
            'ok' => true,
            'text' => $text,
            'calls' => $calls,
            'native' => $native,
            'stop' => $refusal || $incomplete === 'content_filter' ? 'refusal' : ($calls ? 'tool' : ($incomplete === 'max_output_tokens' ? 'max' : 'end')),
            'usage' => $usage,
        ];
    }

    /* ───────────────────────── Messages selon le fournisseur ───────────────────────── */

    /** Message de la personne : texte et images (fichiers de la médiathèque, envoyés en base64). */
    public static function userMessage(string $provider, string $text, array $images = []): array
    {
        $parts = [];
        foreach ($images as [$mime, $data]) {
            $parts[] = $provider === 'openai'
                ? ['type' => 'input_image', 'image_url' => 'data:' . $mime . ';base64,' . base64_encode($data)]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($data)]];
        }
        $parts[] = $provider === 'openai' ? ['type' => 'input_text', 'text' => $text] : ['type' => 'text', 'text' => $text];
        return ['role' => 'user', 'content' => $parts];
    }

    /** Réponse passée de l'assistant (historique, sans le raisonnement). */
    public static function assistantMessage(string $provider, string $text): array
    {
        return $provider === 'openai'
            ? ['role' => 'assistant', 'content' => $text]
            : ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /** Résultats des outils : [[id, contenu, erreur?], …] → messages à ajouter. */
    public static function toolResults(string $provider, array $results): array
    {
        if ($provider === 'openai') {
            return array_map(fn (array $r): array => ['type' => 'function_call_output', 'call_id' => $r[0], 'output' => $r[1]], $results);
        }
        return [['role' => 'user', 'content' => array_map(fn (array $r): array => [
            'type' => 'tool_result', 'tool_use_id' => $r[0], 'content' => $r[1],
        ] + (!empty($r[2]) ? ['is_error' => true] : []), $results)]];
    }

    /* ───────────────────────────── Transport HTTP ───────────────────────────── */

    /**
     * Requête POST JSON lue en flux (Server-Sent Events) : chaque « data: {…} » est décodé et passé à $onData.
     * Si la personne ferme la fenêtre, le transfert s'arrête (plus rien n'est facturé au-delà).
     */
    private static function stream(string $url, array $headers, array $body, callable $onData, ?callable $onTick): array
    {
        $status = 0;
        $buffer = '';
        $raw = '';
        $lastTick = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: text/event-stream']),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_USERAGENT => 'Eclair/' . ECLAIR_VERSION,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$status): int {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
                    $status = (int) $m[1];
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$buffer, &$raw, &$status, &$lastTick, $onData, $onTick): int {
                if ($status >= 400) {
                    $raw .= substr($chunk, 0, 20000 - strlen($raw));
                    return strlen($chunk);
                }
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $pos), "\r");
                    $buffer = substr($buffer, $pos + 1);
                    if (str_starts_with($line, 'data:')) {
                        $data = json_decode(ltrim(substr($line, 5)), true);
                        if (is_array($data)) {
                            $onData($data);
                        }
                    }
                }
                if ($onTick && microtime(true) - $lastTick > 5) {
                    $lastTick = microtime(true);
                    $onTick();
                }
                return connection_aborted() ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno !== 0 ? (connection_aborted() ? 'aborted' : curl_error($ch)) : '';
        $status = $status ?: (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return ['status' => $status, 'error' => $error, 'body' => $raw];
    }

    /** Requête HTTP simple (corps JSON, ou formulaire multipart si $form), pour les images et les tests de connexion. */
    public static function request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 60, bool $form = false): array
    {
        $maxBytes = 25_000_000;
        $out = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_USERAGENT => 'Eclair/' . ECLAIR_VERSION,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$out, $maxBytes): int {
                if (strlen($out) + strlen($chunk) > $maxBytes) {
                    return 0;
                }
                $out .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        curl_exec($ch);
        $errno = curl_errno($ch);
        return [
            'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'body' => $out,
            'error' => $errno !== 0 ? curl_error($ch) : '',
        ];
    }

    /** Message d'erreur compréhensible (le détail technique part dans le journal, sans la clé). */
    public static function failure(string $provider, array $http, string $streamError = ''): string
    {
        $name = self::PROVIDERS[$provider] ?? self::IMAGES[$provider][0] ?? $provider;
        if (($http['error'] ?? '') === 'aborted') {
            return t('Demande interrompue.');
        }
        $detail = '';
        $json = json_decode((string) ($http['body'] ?? ''), true);
        if (is_array($json)) {
            $detail = (string) ($json['error']['message'] ?? $json['message'] ?? $json['detail'] ?? '');
        }
        $detail = $detail !== '' ? $detail : ($streamError !== '' ? $streamError : (string) ($http['error'] ?? ''));
        $detail = (string) preg_replace('~(sk-|key-|bfl_)[A-Za-z0-9_\-]{6,}~', '$1…', mb_substr($detail, 0, 300));
        Log::error(new \RuntimeException('IA ' . $provider . ' (' . (int) ($http['status'] ?? 0) . ') : ' . $detail));
        $status = (int) ($http['status'] ?? 0);
        $low = strtolower($detail);
        return match (true) {
            ($http['error'] ?? '') !== '' && $status === 0 => t('Impossible de joindre {name} : vérifie la connexion internet du serveur, puis réessaie.', ['name' => $name]),
            $status === 401 || $status === 403 && str_contains($low, 'key') => t('Clé d’API refusée par {name} : vérifie-la dans Réglages › IA.', ['name' => $name]),
            str_contains($low, 'credit') || str_contains($low, 'quota') || str_contains($low, 'billing') || $status === 402
                => t('Crédit épuisé chez {name} : recharge ton compte chez le fournisseur, puis réessaie.', ['name' => $name]),
            $status === 404 || str_contains($low, 'model') && $status === 400 => t('Modèle indisponible avec cette clé : choisis-en un autre dans Réglages › IA.'),
            $status === 429 => t('Trop de demandes envoyées à {name} : réessaie dans une minute.', ['name' => $name]),
            $status >= 500 || str_contains($low, 'overloaded') => t('{name} est momentanément surchargé : réessaie dans un instant.', ['name' => $name]),
            default => t('{name} a renvoyé une erreur : {detail}', ['name' => $name, 'detail' => $detail !== '' ? $detail : 'HTTP ' . $status]),
        };
    }
}
