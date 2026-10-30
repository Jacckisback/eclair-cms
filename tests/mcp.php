<?php
/**
 * Tests du connecteur MCP (Claude, ChatGPT) et du choix de l'IA, inclus par tests/run.php :
 * découverte OAuth, enregistrement, autorisation (connexion, consentement), jetons, outils, révocation.
 */
declare(strict_types=1);

use Eclair\Ai;
use Eclair\Settings;
use Eclair\Store;
use Eclair\Vault;

/* ─────────────────────────── Choix de l'IA ─────────────────────────── */
section('Assistant IA : Claude ou ChatGPT au choix');
Settings::save(['ai' => ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'models' => ['anthropic' => '', 'openai' => 'gpt-6-luna']]]);
Vault::set('ai.anthropic', 'sk-ant-unit-0123456789');
check('une seule clé : pas de choix', array_keys(Ai::providers()) === ['anthropic'] && Ai::model('anthropic') === 'claude-haiku-5-5');
Vault::set('ai.openai', 'sk-unit-openai-0123456789');
check('deux clés : Claude et ChatGPT, chacun avec son modèle', Ai::providers() === ['anthropic' => 'Claude Haiku 5.5', 'openai' => 'GPT-6 Luna']);
Ai::choose('openai');
check('ChatGPT choisi pour la demande', Ai::provider() === 'openai' && Ai::label() === 'GPT-6 Luna');
Ai::choose('mistral');
check('fournisseur inconnu : celui des réglages', Ai::provider() === 'anthropic');
Vault::set('ai.openai', '');
Ai::choose('openai');
check('clé retirée : choix ignoré', Ai::provider() === 'anthropic');
Ai::choose('');
Vault::set('ai.anthropic', '');
Settings::save(['ai' => ['provider' => '', 'model' => '', 'models' => ['anthropic' => '', 'openai' => '']]]);

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Connecteur MCP : parcours complet');
[$copy, $port, $server] = serve('mcp');
try {
    $base = 'http://127.0.0.1:' . $port;
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Atelier Test', 'site_tagline' => 'Céramique à Lyon', 'site_lang' => 'fr', 'site_type' => 'business',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $settingsAi = static function (array $fields) use ($c): array {
        $page = $c->req('GET', '/gestion/settings?tab=ai');
        return $c->req('POST', '/gestion/settings', $c->token($page['body']) + $fields + ['tab' => 'ai', 'provider' => '', 'budget' => '10', 'daily' => '60', 'editors' => '1']);
    };
    $json = static fn (array $res): array => (array) json_decode($res['body'], true);
    $v = client($port);

    $off = [$v->req('GET', '/.well-known/oauth-authorization-server'), $v->req('POST', '/_eclair/mcp', '{}'), $v->req('GET', '/_eclair/oauth/authorize')];
    check('MCP désactivé : rien ne répond (comme toute adresse inconnue)', array_column($off, 'status') === [404, 405, 404]);
    $settingsAi(['mcp' => '1']);
    $page = $c->req('GET', '/gestion/settings?tab=ai');
    check('réglages : adresse du connecteur à copier', str_contains($page['body'], 'data-copy="' . $base . '/_eclair/mcp"'));

    // Découverte (RFC 9728, RFC 8414) : sans jamais révéler l'adresse de l'administration.
    $prm = $json($v->req('GET', '/.well-known/oauth-protected-resource'));
    $as = $v->req('GET', '/.well-known/oauth-authorization-server');
    $meta = $json($as);
    check('découverte : ressource et serveur d’autorisation', ($prm['resource'] ?? '') === $base . '/_eclair/mcp' && ($prm['authorization_servers'] ?? []) === [$base]
        && ($meta['issuer'] ?? '') === $base && ($meta['code_challenge_methods_supported'] ?? []) === ['S256']
        && ($meta['registration_endpoint'] ?? '') === $base . '/_eclair/oauth/register', $as['body']);
    check('découverte : adresse de l’administration jamais publiée', !str_contains($as['body'] . json_encode($prm), 'gestion'));
    check('découverte : variante OpenID et chemin de la ressource', $json($v->req('GET', '/.well-known/openid-configuration')) === $meta
        && $json($v->req('GET', '/.well-known/oauth-protected-resource/_eclair/mcp')) === $prm);
    $anon = $v->req('POST', '/_eclair/mcp', '{"jsonrpc":"2.0","id":1,"method":"initialize"}', ['Content-Type: application/json']);
    check('sans jeton : 401 qui pointe vers la découverte', $anon['status'] === 401
        && ($anon['headers']['www-authenticate'] ?? '') === 'Bearer resource_metadata="' . $base . '/.well-known/oauth-protected-resource"', json_encode($anon['headers']));
    $pre = $v->req('OPTIONS', '/_eclair/mcp', [], ['Origin: https://inspector.example', 'Access-Control-Request-Method: POST']);
    check('CORS : préflight accepté, sans cookie', $pre['status'] === 204 && ($pre['headers']['access-control-allow-origin'] ?? '') === '*');
    check('GET sur le point MCP : 405 (pas de flux SSE)', $v->req('GET', '/_eclair/mcp')['status'] === 405);

    // Enregistrement dynamique (RFC 7591).
    $reg = static fn (array $data) => $v->req('POST', '/_eclair/oauth/register', (string) json_encode($data), ['Content-Type: application/json']);
    $r = $reg(['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'], 'token_endpoint_auth_method' => 'none']);
    $client = $json($r);
    $cid = (string) ($client['client_id'] ?? '');
    check('enregistrement : identifiant signé, client public', $r['status'] === 201 && $cid !== '' && ($client['token_endpoint_auth_method'] ?? '') === 'none' && !isset($client['client_secret']));
    check('enregistrement : adresses de retour dangereuses refusées', $reg(['redirect_uris' => ['http://evil.example/cb']])['status'] === 400
        && $reg(['redirect_uris' => ['javascript:alert(1)']])['status'] === 400 && $reg(['redirect_uris' => []])['status'] === 400
        && $reg(['redirect_uris' => ['https://user:pass@evil.example/cb']])['status'] === 400
        && $reg(['redirect_uris' => ["https://x;sandbox allow-scripts;base-uri *;b/cb"]])['status'] === 400
        && $reg(['redirect_uris' => ['https://evil.example/cb#frag']])['status'] === 400);
    $conf = $json($reg(['redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'], 'token_endpoint_auth_method' => 'client_secret_basic']));
    check('enregistrement : toujours un client public (PKCE), jamais de secret', ($conf['token_endpoint_auth_method'] ?? '') === 'none' && !isset($conf['client_secret']));

    // Autorisation : PKCE S256, consentement explicite.
    $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $auth = static fn (array $over = []): string => '/_eclair/oauth/authorize?' . http_build_query($over + ['response_type' => 'code', 'client_id' => $cid,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        'state' => 'etat-123', 'scope' => 'eclair', 'resource' => $base . '/_eclair/mcp']);
    $relay = $c->req('GET', $auth(), [], ['Sec-Fetch-Site: cross-site']);
    check('arrivée depuis Claude : page relais (cookie Strict)', $relay['status'] === 200 && str_contains($relay['body'], 'http-equiv="refresh"'));
    check('page d’autorisation : retour vers l’application permis par la CSP, sans COOP', str_contains($relay['headers']['content-security-policy'] ?? '', "form-action 'self' https://claude.ai")
        && !isset($relay['headers']['cross-origin-opener-policy']) && ($relay['headers']['x-frame-options'] ?? '') === 'DENY');
    $forged = $c->req('GET', $auth(['redirect_uri' => 'https://evil.example/cb']));
    check('adresse de retour non enregistrée : aucune redirection', $forged['status'] === 400 && !isset($forged['headers']['location']));
    $tampered = $c->req('GET', $auth(['client_id' => substr($cid, 0, -2) . 'xx']));
    check('identifiant d’application falsifié : refusé', $tampered['status'] === 400);
    $nopkce = $c->req('GET', $auth(['code_challenge_method' => 'plain']));
    check('PKCE S256 obligatoire', $nopkce['status'] === 303 && str_starts_with($nopkce['headers']['location'] ?? '', 'https://claude.ai/api/mcp/auth_callback?error=invalid_request')
        && str_contains($nopkce['headers']['location'] ?? '', 'state=etat-123'));
    $spoof = (string) ($json($reg(['client_name' => 'Claude', 'redirect_uris' => ['https://evil.example/cb']]))['client_id'] ?? '');
    $fake = $c->req('GET', $auth(['client_id' => $spoof, 'redirect_uri' => 'https://evil.example/cb']));
    check('application inconnue : nommée par son domaine, avec un avertissement', str_contains($fake['body'], 'Connecter evil.example à ton site')
        && !str_contains($fake['body'], 'Connecter Claude') && str_contains($fake['body'], 'Application inconnue'));
    $open = $c->req('GET', $auth(['client_id' => $spoof, 'redirect_uri' => 'https://evil.example/cb', 'response_type' => 'token']));
    check('application inconnue : erreur affichée, aucune redirection ouverte', $open['status'] === 400 && !isset($open['headers']['location']));
    $consent = $c->req('GET', $auth());
    check('connectée : consentement affiché', $consent['status'] === 200 && str_contains($consent['body'], 'Connecter Claude à ton site')
        && str_contains($consent['body'], 'claude.ai') && str_contains($consent['body'], 'value="allow"'));
    $deny = $c->req('POST', $auth(), $c->token($consent['body']) + ['step' => 'deny']);
    check('refus : erreur access_denied renvoyée à l’application', str_contains($deny['headers']['location'] ?? '', 'error=access_denied'));
    $allow = $c->req('POST', $auth(), $c->token($consent['body']) + ['step' => 'allow']);
    parse_str((string) parse_url($allow['headers']['location'] ?? '', PHP_URL_QUERY), $back);
    check('accord : code, état et émetteur renvoyés', $allow['status'] === 303 && strlen($back['code'] ?? '') === 64 && ($back['state'] ?? '') === 'etat-123' && ($back['iss'] ?? '') === $base);
    $csrf = $c->req('POST', $auth(), ['step' => 'allow', '_csrf' => str_repeat('0', 64)]);
    check('accord sans jeton CSRF valide : refusé', $csrf['status'] === 200 && !isset($csrf['headers']['location']));
    // Connexion vieille de plus de 15 minutes : le mot de passe est redemandé pour ouvrir l'accès.
    preg_match('/eclair_sid\s+(\S+)/', (string) file_get_contents($c->jar), $sid);
    $sessionFile = $copy . '/data/sessions/' . hash('sha256', $sid[1] ?? '') . '.php';
    file_put_contents($sessionFile, (string) preg_replace('/sudo\|i:\d+;/', 'sudo|i:0;', (string) file_get_contents($sessionFile)));
    $stale = $c->req('GET', $auth());
    $nopass = $c->req('POST', $auth(), $c->token($stale['body']) + ['step' => 'allow']);
    $wrongpass = $c->req('POST', $auth(), $c->token($stale['body']) + ['step' => 'allow', 'confirm_password' => 'pas-le-bon']);
    check('connexion ancienne : mot de passe redemandé avant l’accord', str_contains($stale['body'], 'name="confirm_password"') && $nopass['status'] === 401
        && !isset($nopass['headers']['location']) && $wrongpass['status'] === 401);
    $repass = $c->req('POST', $auth(), $c->token($stale['body']) + ['step' => 'allow', 'confirm_password' => 'Un-mot-de-passe-solide']);
    check('connexion ancienne : accord après le mot de passe', $repass['status'] === 303 && str_contains($repass['headers']['location'] ?? '', 'code='));

    // Connexion depuis la page d'autorisation, sans jamais montrer l'adresse de l'administration.
    $w = client($port);
    $login = $w->req('GET', $auth());
    check('non connectée : connexion sur place, adresse de l’administration absente', $login['status'] === 200 && str_contains($login['body'], 'name="password"') && !str_contains($login['body'], 'gestion'));
    $wrong = $w->req('POST', $auth(), $w->token($login['body']) + ['step' => 'login', 'email' => 'camille@example.com', 'password' => 'mauvais']);
    check('mauvais mot de passe : refusé', $wrong['status'] === 401 && !str_contains($wrong['body'], 'value="allow"'));
    $in = $w->req('POST', $auth(), $w->token($login['body']) + ['step' => 'login', 'email' => 'camille@example.com', 'password' => 'Un-mot-de-passe-solide']);
    check('connexion réussie : retour au consentement', $in['status'] === 303 && str_contains($w->req('GET', $auth())['body'], 'value="allow"'));

    // Jetons : échange du code (usage unique, PKCE vérifié), renouvellement avec rotation.
    $token = static fn (array $data) => $v->req('POST', '/_eclair/oauth/token', $data);
    $bad = $token(['grant_type' => 'authorization_code', 'code' => $back['code'] ?? '', 'client_id' => $cid, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_verifier' => str_repeat('a', 43)]);
    check('mauvais vérificateur PKCE : refusé (et code consommé)', $bad['status'] === 400 && ($json($bad)['error'] ?? '') === 'invalid_grant');
    $allow = $c->req('POST', $auth(), $c->token($consent['body']) + ['step' => 'allow']);
    parse_str((string) parse_url($allow['headers']['location'] ?? '', PHP_URL_QUERY), $back);
    $exchange = ['grant_type' => 'authorization_code', 'code' => $back['code'] ?? '', 'client_id' => $cid,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_verifier' => $verifier];
    $t = $token($exchange);
    $tok = $json($t);
    check('code échangé contre des jetons', $t['status'] === 200 && strlen($tok['access_token'] ?? '') === 64 && strlen($tok['refresh_token'] ?? '') === 64
        && ($tok['token_type'] ?? '') === 'Bearer' && ($tok['expires_in'] ?? 0) === 3600 && ($t['headers']['cache-control'] ?? '') === 'no-store', $t['body']);
    check('code à usage unique', ($json($token($exchange))['error'] ?? '') === 'invalid_grant');
    $mcpData = (string) file_get_contents($copy . '/data/ai/mcp.php');
    check('jetons jamais stockés en clair', !str_contains($mcpData, (string) $tok['access_token']) && !str_contains($mcpData, (string) $tok['refresh_token']));
    check('autre application : code refusé', ($json($token(['client_id' => 'x.y'] + $exchange))['error'] ?? '') === 'invalid_client');

    // Protocole MCP (JSON-RPC sur HTTP).
    $rpc = static function (string $method, array $params = [], ?string $bearer = null, mixed $id = 1) use ($v, &$tok): array {
        $msg = ['jsonrpc' => '2.0', 'method' => $method] + ($id === null ? [] : ['id' => $id]) + ($params ? ['params' => $params] : []);
        return $v->req('POST', '/_eclair/mcp', (string) json_encode($msg), ['Content-Type: application/json', 'Accept: application/json, text/event-stream',
            'Authorization: Bearer ' . ($bearer ?? $tok['access_token'])]);
    };
    $init = $json($rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]));
    check('initialize : version négociée, consignes', ($init['result']['protocolVersion'] ?? '') === '2025-06-18' && ($init['result']['serverInfo']['name'] ?? '') === 'eclair'
        && str_contains($init['result']['instructions'] ?? '', 'get_site') && isset($init['result']['capabilities']['tools']));
    check('version inconnue : la plus récente proposée', ($json($rpc('initialize', ['protocolVersion' => '1999-01-01']))['result']['protocolVersion'] ?? '') === '2025-11-25');
    $note = $rpc('notifications/initialized', [], null, null);
    check('notification : 202 sans corps', $note['status'] === 202 && $note['body'] === '');
    check('ping', $rpc('ping')['body'] === '{"jsonrpc":"2.0","id":1,"result":{}}');
    $tools = array_column($json($rpc('tools/list'))['result']['tools'] ?? [], null, 'name');
    check('outils : ceux de l’assistant, lecture signalée', array_keys($tools) === ['get_site', 'search_content', 'get_content', 'create_draft', 'update_content', 'find_media']
        && $tools['get_site']['annotations']['readOnlyHint'] === true && $tools['create_draft']['annotations']['readOnlyHint'] === false
        && ($tools['get_site']['inputSchema']['properties'] ?? null) === [], json_encode(array_keys($tools)));
    $call = static function (string $name, array $args = []) use ($rpc, $json): array {
        $res = $json($rpc('tools/call', ['name' => $name, 'arguments' => $args]))['result'] ?? [];
        return ['error' => $res['isError'] ?? null, 'text' => (string) ($res['content'][0]['text'] ?? ''), 'data' => (array) json_decode((string) ($res['content'][0]['text'] ?? ''), true)];
    };
    $site = $call('get_site');
    check('get_site : inventaire, profil et règles d’écriture', $site['error'] === false && str_contains($site['text'], 'Atelier Test') && str_contains($site['text'], '# Writing rules'));
    $redirects = $c->req('GET', '/gestion/redirects');
    $c->req('POST', '/gestion/redirects', $c->token($redirects['body']) + ['from' => '/ancienne-offre', 'to' => '/contact', 'code' => '301']);
    $squat = $call('create_draft', ['type' => 'page', 'title' => 'Ancienne offre', 'slug' => 'ancienne-offre', 'body' => 'Texte en préparation.']);
    check('brouillon à une adresse redirigée : redirection conservée', $squat['error'] === false && $v->req('GET', '/ancienne-offre')['status'] === 301);
    $draft = $call('create_draft', ['type' => 'post', 'title' => 'Bien choisir son bol en grès', 'body' => "## Pourquoi le grès ?\n\nIl est solide et passe au lave-vaisselle."]);
    $did = (string) ($draft['data']['id'] ?? '');
    check('create_draft : brouillon créé, lien pour le relire', $draft['error'] === false && $did !== '' && str_starts_with((string) ($draft['data']['edit_url'] ?? ''), $base . '/gestion/edit/' . $did), $draft['text']);
    check('brouillon : invisible sur le site', $v->req('GET', '/blog/bien-choisir-son-bol-en-gres')['status'] === 404);
    $home = '';
    foreach ($call('search_content', ['query' => '', 'type' => 'page'])['data']['results'] ?? [] as $row) {
        $home = $row['path'] === '/' && $row['status'] === 'published' ? $row['id'] : $home;
    }
    $read = $call('get_content', ['id' => $home]);
    check('get_content : texte en Markdown', $read['error'] === false && ($read['data']['path'] ?? '') === '/' && ($read['data']['markdown'] ?? '') !== '', $read['text']);
    $prop = $call('update_content', ['id' => $home, 'note' => 'Accroche plus directe', 'title' => 'Accueil de notre atelier']);
    check('contenu publié : proposition seulement, lien de vérification', $prop['error'] === false && str_contains((string) ($prop['data']['review_url'] ?? ''), '/gestion/edit/' . $home . '?proposal=')
        && str_contains((string) ($prop['data']['done'] ?? ''), 'NOT applied'), $prop['text']);
    check('proposition : site public inchangé', !str_contains($v->req('GET', '/')['body'], 'Accueil de notre atelier'));
    $review = $c->req('GET', substr((string) ($prop['data']['review_url'] ?? ''), strlen($base)));
    check('proposition : ouverte dans l’éditeur pour être vérifiée', $review['status'] === 200 && str_contains($review['body'], '"proposal":{') && str_contains($review['body'], 'Accueil de notre atelier'));
    check('proposition : changements listés par le serveur, pas par l’IA', str_contains($review['body'], '"note":"Titre"') && !str_contains($review['body'], 'Accroche plus directe'));
    $other = client($port)->req('GET', substr((string) ($prop['data']['review_url'] ?? ''), strlen($base)));
    check('proposition : réservée aux personnes connectées', $other['status'] === 302);
    $unknown = $json($rpc('tools/call', ['name' => 'delete_everything']));
    check('outil inconnu : erreur JSON-RPC', ($unknown['error']['code'] ?? 0) === -32602);
    check('méthode inconnue : erreur JSON-RPC', ($json($rpc('resources/list'))['error']['code'] ?? 0) === -32601);
    $batch = $v->req('POST', '/_eclair/mcp', '[{"jsonrpc":"2.0","id":"a","method":"ping"},{"jsonrpc":"2.0","method":"notifications/initialized"},{"jsonrpc":"2.0","id":"b","method":"ping"}]',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $tok['access_token']]);
    check('lot de messages : une réponse par requête', array_column((array) json_decode($batch['body'], true), 'id') === ['a', 'b']);
    $big = $v->req('POST', '/_eclair/mcp', (string) json_encode(array_fill(0, 21, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])),
        ['Content-Type: application/json', 'Authorization: Bearer ' . $tok['access_token']]);
    check('lot de plus de 20 messages : refusé', $big['status'] === 400);
    check('JSON illisible : erreur de syntaxe', $v->req('POST', '/_eclair/mcp', '{oops', ['Authorization: Bearer ' . $tok['access_token']])['status'] === 400);

    // Renouvellement : le nouveau jeton remplace l'ancien.
    $old = $tok;
    $ref = $token(['grant_type' => 'refresh_token', 'refresh_token' => $old['refresh_token'], 'client_id' => $cid]);
    $tok = $json($ref);
    check('renouvellement : nouveaux jetons', $ref['status'] === 200 && ($tok['access_token'] ?? '') !== $old['access_token'] && ($tok['refresh_token'] ?? '') !== $old['refresh_token']);
    check('renouvellement : ancien jeton d’accès refusé, nouveau accepté', $rpc('ping', [], $old['access_token'])['status'] === 401 && $rpc('ping')['status'] === 200);
    check('renouvellement : ancien jeton réutilisé (volé), connexion coupée', ($json($token(['grant_type' => 'refresh_token', 'refresh_token' => $old['refresh_token'], 'client_id' => $cid]))['error'] ?? '') === 'invalid_grant'
        && $rpc('ping')['status'] === 401 && ($json($token(['grant_type' => 'refresh_token', 'refresh_token' => $tok['refresh_token'], 'client_id' => $cid]))['error'] ?? '') === 'invalid_grant');
    $allow = $c->req('POST', $auth(), $c->token($c->req('GET', $auth())['body']) + ['step' => 'allow']);
    parse_str((string) parse_url($allow['headers']['location'] ?? '', PHP_URL_QUERY), $back);
    $tok = $json($token(['code' => $back['code'] ?? ''] + $exchange));
    $expired = $rpc('ping', [], str_repeat('f', 64));
    check('jeton inconnu : 401 invalid_token', $expired['status'] === 401 && str_contains($expired['headers']['www-authenticate'] ?? '', 'error="invalid_token"'));

    settings($copy, ['security' => ['https' => true]]);
    $plain = $v->req('GET', '/_eclair/oauth/authorize?client_id=x');
    check('site en HTTPS : autorisation jamais servie en clair', $plain['status'] === 308 && str_starts_with($plain['headers']['location'] ?? '', 'https://'));
    settings($copy, ['security' => ['https' => false]]);
    for ($i = 0; $i < 6; $i++) {
        $allow = $c->req('POST', $auth(), $c->token($c->req('GET', $auth())['body']) + ['step' => 'allow']);
        parse_str((string) parse_url($allow['headers']['location'] ?? '', PHP_URL_QUERY), $back);
        $tok = $json($token(['code' => $back['code'] ?? ''] + $exchange));
    }
    $grants = json_decode(substr((string) file_get_contents($copy . '/data/ai/mcp.php'), strlen(Store::GUARD)), true)['grants'] ?? [];
    check('5 connexions au plus par personne, les plus anciennes s’effacent', count($grants) === 5 && $rpc('ping')['status'] === 200, (string) count($grants));

    // Coupure : depuis les réglages, par la déconnexion générale, ou en désactivant le MCP.
    $page = $c->req('GET', '/gestion/settings?tab=ai');
    preg_match('/name="mcp_revoke" value="([a-z0-9]+)"/', $page['body'], $g);
    check('réglages : application connectée listée', isset($g[1]) && str_contains($page['body'], 'claude.ai'));
    $settingsAi(['mcp' => '1', 'mcp_revoke' => $g[1] ?? '']);
    check('accès coupé : jeton refusé aussitôt', $rpc('ping')['status'] === 401);
    $allow = $c->req('POST', $auth(), $c->token($c->req('GET', $auth())['body']) + ['step' => 'allow']);
    parse_str((string) parse_url($allow['headers']['location'] ?? '', PHP_URL_QUERY), $back);
    $tok = $json($token(['code' => $back['code'] ?? ''] + $exchange));
    check('nouvelle autorisation : accès rétabli', $rpc('ping')['status'] === 200);
    $users = json_decode(substr((string) file_get_contents($copy . '/data/users.php'), strlen(Store::GUARD)), true);
    foreach ($users as &$u) {
        $u['ver'] = (int) ($u['ver'] ?? 1) + 1;
    }
    unset($u);
    file_put_contents($copy . '/data/users.php', Store::GUARD . json_encode($users));
    check('déconnexion générale (ou nouveau mot de passe) : connecteur coupé aussi', $rpc('ping')['status'] === 401);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $settingsAi([]);
    check('MCP désactivé : connexions effacées, adresses introuvables', $rpc('ping')['status'] === 405 && $v->req('GET', '/.well-known/oauth-protected-resource')['status'] === 404
        && !preg_match('/"g[a-f0-9]{10}"/', (string) file_get_contents($copy . '/data/ai/mcp.php')));
} finally {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($copy));
}
