<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Google Discover : publicité AdSense (identifiant, consentement exigé, blocs placés entre les blocs du texte avec
 * l'étiquette de Google, politique de sécurité à nonce sur les seules pages avec publicité, ads.txt), « prêt pour
 * Discover » (titres, image d'origine dans les données structurées), tendances du jour (faux Google Trends) et idées tirées
 * par l'IA (faux fournisseur). Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Ads;
use Eclair\Discover;
use Eclair\Security;
use Eclair\Settings;
use Eclair\Store;
use Eclair\Trends;

section('Google Discover : publicité, titres, tendances (unitaire)');
check('identifiant d’éditeur : « ca-pub- » ou « pub- » et 16 chiffres, sinon refusé', Ads::client('ca-pub-1234567890123456') === 'pub-1234567890123456'
    && Ads::client(' PUB-1234567890123456 ') === 'pub-1234567890123456' && Ads::client('pub-123') === '' && Ads::client('pub-1234567890123456"><script>') === '');
check('bloc d’annonces : des chiffres seulement', Ads::slot('1234567890') === '1234567890' && Ads::slot('12ab34') === '' && Ads::slot('123') === '');
check('ads.txt : lignes ajoutées nettoyées (texte brut, 50 lignes au plus)', Ads::cleanTxt("a.com, 1, DIRECT\r\n\r\n<script>x</script>\x01b.com, 2, RESELLER") === "a.com, 1, DIRECT\nscriptx/scriptb.com, 2, RESELLER"
    && substr_count(Ads::cleanTxt(str_repeat("l.com, 1, DIRECT\n", 80)), "\n") === 49);
check('titres : majuscules, « !! » ou « ?! », trop long ; sinon rien à redire', Discover::title('VOUS NE CROIREZ JAMAIS CE QUI SUIT') === 'caps'
    && Discover::title('Le levain, enfin expliqué !!') === 'punct' && Discover::title('Vraiment ?!') === 'punct' && Discover::title(str_repeat('Levain ', 20)) === 'long'
    && Discover::title('Réussir son levain en 5 étapes') === '' && Discover::title('PAIN') === '' && Discover::title('Le pain, c’est bon !') === '');
$adsCsp = implode("\n", Security::headers('site', 'abc=', 'N0nce-Test_123'));
$siteCsp = implode("\n", Security::headers('site', 'abc='));
check('page avec publicité : politique stricte documentée par Google (nonce, strict-dynamic)', str_contains($adsCsp,
    "script-src 'nonce-N0nce-Test_123' 'unsafe-inline' 'unsafe-eval' 'strict-dynamic' https: http:;") && str_contains($adsCsp, "object-src 'none'")
    && str_contains($adsCsp, "base-uri 'none'") && str_contains($adsCsp, "frame-ancestors 'self'") && str_contains($adsCsp, 'Cross-Origin-Opener-Policy: same-origin-allow-popups'), $adsCsp);
check('page sans publicité : politique d’Éclair inchangée', str_contains($siteCsp, "default-src 'self'; script-src 'self'; style-src 'self' 'sha256-abc='")
    && str_contains($siteCsp, 'Cross-Origin-Opener-Policy: same-origin') && !str_contains($siteCsp, 'nonce'), $siteCsp);
check('nonce : aléatoire, sans caractère à échapper', Ads::nonce() !== Ads::nonce() && (bool) preg_match('/^[A-Za-z0-9_-]{24}$/', Ads::nonce()));
$trItems = Trends::parse((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('$_SERVER["REQUEST_URI"]="/trending/rss?geo=FR"; putenv("FAKE_TR_DIR=' . sys_get_temp_dir() . '"); include "' . __DIR__ . '/fake-trends.php";')));
check('flux Google Trends lu : recherches, volume, presse ; entités décodées, balises et adresses piégées écartées', is_array($trItems) && count($trItems) === 3
    && $trItems[0]['title'] === 'pain au levain maison' && $trItems[0]['traffic'] === '2000+' && $trItems[0]['date'] > 0 && count($trItems[0]['news']) === 1
    && $trItems[0]['news'][0] === ['title' => 'Le levain fait son grand retour \'chez soi\'', 'url' => 'https://journal.example/levain-retour', 'source' => 'Journal Test']
    && $trItems[1]['title'] === 'Match & résultats' && $trItems[1]['news'] === [] && $trItems[2]['news'][0]['title'] === 'Une célébrité fait parler d’elle', json_encode($trItems, JSON_UNESCAPED_UNICODE));
check('flux illisible ou autre chose qu’un flux : refusé', Trends::parse('<html><body>Pas un flux</body></html>') === null && Trends::parse('<rss><channel>') === null && Trends::parse('') === null);
$siteFile = ECLAIR_DATA . '/site.php';
$siteBackup = is_file($siteFile) ? (string) file_get_contents($siteFile) : null;
try {
    Settings::save(['ads' => ['client' => 'pub-1234567890123456', 'enabled' => true, 'consent' => true, 'auto' => false, 'slot' => '1234567890', 'positions' => ['intro', 'middle', 'end']]]);
    $p = static fn (int $n, array $extra = []): array => array_merge(array_map(fn ($i) => ['type' => 'paragraph', 'text' => 'p' . $i], range(1, $n)), $extra);
    check('blocs : rien sous 3 paragraphes ; après le 2e, au milieu d’un article long, à la fin ; 3 paragraphes au moins entre deux', Ads::places($p(2)) === []
        && Ads::places($p(4)) === [1] && Ads::places($p(5)) === [1, 4] && Ads::places($p(9)) === [1, 4, 8]
        && Ads::places(array_merge([['type' => 'heading', 'text' => 'T', 'level' => 2]], $p(9), [['type' => 'faq', 'items' => []]])) === [2, 5, 10]);
    Settings::save(['ads' => ['positions' => ['end']]]);
    check('emplacement choisi seul : fin de l’article', Ads::places($p(5)) === [4] && Ads::places($p(2)) === []);
    Settings::save(['ads' => ['consent' => false]]);
    check('consentement non confirmé : publicité jamais en service', !Ads::active() && !Ads::wanted(['type' => 'post', 'options' => []], false));
    Settings::save(['ads' => ['consent' => true]]);
    check('en service : articles seulement, jamais en aperçu ni sur un article « Sans publicité »', Ads::active() && Ads::wanted(['type' => 'post', 'options' => []], false)
        && !Ads::wanted(['type' => 'page', 'options' => []], false) && !Ads::wanted(['type' => 'post', 'options' => []], true)
        && !Ads::wanted(['type' => 'post', 'options' => ['noads' => true]], false));
    $unit = Ads::unit('es', 'abc');
    check('bloc : étiquette permise par Google dans la langue de la page, appel de Google avec le nonce', str_contains($unit, '<p class="ad-label">Anuncios</p>')
        && str_contains($unit, 'data-ad-client="ca-pub-1234567890123456" data-ad-slot="1234567890"') && str_contains($unit, '<script nonce="abc">(adsbygoogle = window.adsbygoogle || []).push({});</script>'), $unit);
    check('ads.txt : ligne de Google, puis les lignes ajoutées', Ads::txt() === "google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\n"
        && (Settings::save(['ads' => ['txt' => 'autre.example, 42, RESELLER']]) || true) && Ads::txt() === "google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\nautre.example, 42, RESELLER\n");
} finally {
    $siteBackup === null ? @unlink($siteFile) : file_put_contents($siteFile, $siteBackup);
    Settings::forget();
}

/* ─────────────────────────── Parcours HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Google Discover : page, publicité sur les articles, ads.txt, tendances du jour, idées de l’IA');
[$copy, $port, $server] = serve('discover');
$trDir = sys_get_temp_dir() . '/eclair-fake-tr-' . getmypid();
$aiDir = sys_get_temp_dir() . '/eclair-fake-dc-' . getmypid();
@mkdir($trDir);
@mkdir($aiDir);
[$trPort, $trServer] = start_server([__DIR__ . '/fake-trends.php'], ['FAKE_TR_DIR' => $trDir], 50001, 60000);
[$aiPort, $aiServer] = start_server([__DIR__ . '/fake-ai.php'], ['FAKE_AI_DIR' => $aiDir, 'PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['anthropic' => 'http://127.0.0.1:$aiPort', 'openai' => 'http://127.0.0.1:$aiPort', 'trends' => 'http://127.0.0.1:$trPort']);\n");
usleep(400000);
$read = static fn (string $file): array => json_decode(substr((string) @file_get_contents($copy . '/data/' . $file . '.php'), strlen(Store::GUARD)), true) ?: [];
// Code AdSense ou bloc d'annonces dans la page (la feuille de style commune nomme aussi la classe des blocs).
$hasAds = static fn (string $body): bool => str_contains($body, 'googlesyndication.com') || str_contains($body, '<ins class="adsbygoogle"');
$nonceOf = static fn (array $res): array => [preg_match("~'nonce-([A-Za-z0-9_-]+)'~", (string) ($res['headers']['content-security-policy'] ?? ''), $h) ? $h[1] : '',
    preg_match_all('~nonce="([A-Za-z0-9_-]+)"~', $res['body'], $b) ? array_values(array_unique($b[1])) : []];
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Fournil Test', 'site_tagline' => 'Pain au levain', 'site_lang' => 'fr', 'site_type' => 'blog',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $page = $c->req('GET', '/gestion/discover');
    check('page Discover : menu, contrôles, tendances à charger, publicité inactive, sans attribut style (CSP)', $page['status'] === 200
        && str_contains($page['body'], 'href="/gestion/discover"') && str_contains($page['body'], 'Prêt pour Discover') && str_contains($page['body'], 'data-dc-trends')
        && str_contains($page['body'], 'name="action" value="ads"') && str_contains($page['body'], 'Inactive') && !preg_match('~<[^>]+\sstyle="~', $page['body']));
    preg_match('/"csrf":"([a-f0-9]+)"/', $page['body'], $m);
    $api = static fn (string $path, array $data): array => ($res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data),
        ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])) + ['json' => json_decode($res['body'], true)];
    $paras = array_map(fn ($i) => ['type' => 'paragraph', 'text' => 'Paragraphe ' . $i . ' : le levain se nourrit de farine et d’eau, chaque jour, à heure fixe.'], range(1, 9));
    $long = $api('api/save', ['type' => 'post', 'title' => 'Réussir son levain', 'status' => 'published', 'blocks' => $paras]);
    $short = $api('api/save', ['type' => 'post', 'title' => 'Billet court', 'status' => 'published', 'blocks' => array_slice($paras, 0, 2)]);
    $quiet = $api('api/save', ['type' => 'post', 'title' => 'Billet sans publicité', 'status' => 'published', 'blocks' => $paras, 'options' => ['noads' => true]]);
    $longUrl = (string) ($long['json']['url'] ?? '/x');
    $visitor = client($port);
    $before = $visitor->req('GET', $longUrl);
    check('articles enregistrés, « Sans publicité » gardé', $before['status'] === 200 && ($quiet['json']['item']['options']['noads'] ?? false) === true
        && ($long['json']['item']['options']['noads'] ?? true) === false);
    check('publicité jamais configurée : ni ads.txt, ni code AdSense, politique d’Éclair', $visitor->req('GET', '/ads.txt')['status'] === 404
        && !$hasAds($before['body']) && !str_contains($before['body'], 'google-adsense-account')
        && str_contains((string) ($before['headers']['content-security-policy'] ?? ''), "script-src 'self'"));

    $post = static fn (array $fields): array => $c->req('POST', '/gestion/discover', $c->token($c->req('GET', '/gestion/discover')['body']) + ['action' => 'ads'] + $fields);
    $post(['client' => 'pub-123', 'enabled' => '1', 'consent' => '1']);
    check('identifiant invalide : refusé, rien d’enregistré', ($read('site')['ads']['client'] ?? '') === '' && !($read('site')['ads']['enabled'] ?? false));
    $post(['client' => 'ca-pub-1234567890123456', 'enabled' => '1', 'auto' => '1']);
    check('activation sans message de consentement confirmé : refusée', ($read('site')['ads']['enabled'] ?? false) === false && ($read('site')['ads']['client'] ?? '') === '');
    $post(['client' => 'ca-pub-1234567890123456', 'enabled' => '1', 'consent' => '1']);
    check('activation sans annonces automatiques ni bloc : refusée', ($read('site')['ads']['enabled'] ?? false) === false);
    $post(['client' => 'ca-pub-1234567890123456', 'txt' => "autre.example, 42, RESELLER\n<b>x</b>"]);
    $txt = $visitor->req('GET', '/ads.txt');
    check('identifiant seul : ads.txt servi (ligne de Google, lignes ajoutées nettoyées)', $txt['status'] === 200 && str_starts_with((string) ($txt['headers']['content-type'] ?? ''), 'text/plain')
        && $txt['body'] === "google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\nautre.example, 42, RESELLER\nbx/b\n", $txt['body']);
    $verify = $visitor->req('GET', $longUrl);
    check('identifiant seul : balise de validation de Google sur les pages, aucun code AdSense ni autre changement', str_contains($verify['body'], '<meta name="google-adsense-account" content="ca-pub-1234567890123456">')
        && !$hasAds($verify['body']) && str_replace("\n" . '<meta name="google-adsense-account" content="ca-pub-1234567890123456">', '', $verify['body']) === $before['body']);

    $post(['client' => 'pub-1234567890123456', 'enabled' => '1', 'consent' => '1', 'slot' => '1234567890', 'positions' => ['intro', 'middle', 'end']]);
    $ad = $visitor->req('GET', $longUrl);
    [$hNonce, $bNonces] = $nonceOf($ad);
    check('article : code AdSense et 3 blocs étiquetés « Publicités », entre les paragraphes', substr_count($ad['body'], '<aside class="ad" aria-label="Publicités"><p class="ad-label">Publicités</p>') === 3
        && str_contains($ad['body'], '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456" crossorigin="anonymous" nonce="')
        && (bool) preg_match('~Paragraphe 2[^<]*</p>\n<aside class="ad"~u', $ad['body']) && (bool) preg_match('~Paragraphe 5[^<]*</p>\n<aside class="ad"~u', $ad['body'])
        && (bool) preg_match('~Paragraphe 9[^<]*</p>\n<aside class="ad"~u', $ad['body']), $ad['body']);
    check('nonce : le même dans la politique de sécurité et sur chaque script (code AdSense, blocs, site.js)', $hNonce !== '' && $bNonces === [$hNonce]
        && substr_count($ad['body'], 'nonce="' . $hNonce . '"') === 5 && (bool) preg_match('~/assets/site/site\.js\?v=[a-f0-9]+" defer nonce="~', $ad['body']));
    check('styles des blocs ajoutés, place réservée', str_contains($ad['body'], '.ad{margin:2.5rem 0;') && str_contains($ad['body'], 'min-height:19rem'));
    $again = $visitor->req('GET', $longUrl);
    check('page en cache : même nonce dans l’en-tête et dans la page', $nonceOf($again) === [$hNonce, [$hNonce]] && $again['body'] === $ad['body']);
    $c->req('POST', '/gestion/discover', $c->token($c->req('GET', '/gestion/discover')['body']) + ['action' => 'geo', 'geo' => 'FR']);
    $fresh = $visitor->req('GET', $longUrl);
    check('cache vidé (réglage changé) : nouveau rendu, nouveau nonce', $nonceOf($fresh)[0] !== '' && $nonceOf($fresh)[0] !== $hNonce && $nonceOf($fresh)[1] === [$nonceOf($fresh)[0]]);
    $shortRes = $visitor->req('GET', (string) ($short['json']['url'] ?? '/x'));
    check('article de 2 paragraphes, sans annonces automatiques : aucun code AdSense', $shortRes['status'] === 200 && !$hasAds($shortRes['body'])
        && !str_contains((string) ($shortRes['headers']['content-security-policy'] ?? ''), 'nonce'));
    $quietRes = $visitor->req('GET', (string) ($quiet['json']['url'] ?? '/x'));
    check('article « Sans publicité » : aucun code AdSense', $quietRes['status'] === 200 && !$hasAds($quietRes['body']));
    $about = $visitor->req('GET', '/a-propos');
    check('page (pas un article) : aucune publicité, politique d’Éclair', $about['status'] === 200 && !$hasAds($about['body'])
        && str_contains((string) ($about['headers']['content-security-policy'] ?? ''), "script-src 'self'"));
    $preview = $c->req('GET', '/gestion/preview/' . ($long['json']['item']['id'] ?? 'x'));
    check('aperçu dans l’administration : jamais de publicité', $preview['status'] === 200 && !$hasAds($preview['body']));
    check('flux RSS et Markdown : jamais de publicité', !$hasAds($visitor->req('GET', '/feed.xml')['body'])
        && ($md = $visitor->req('GET', $longUrl . '.md'))['status'] === 200 && !$hasAds($md['body']));
    $edit = $c->req('GET', '/gestion/edit/' . ($long['json']['item']['id'] ?? 'x'));
    check('éditeur : case « Sans publicité sur cet article » quand la publicité est en service', str_contains($edit['body'], 'id="f-noads"'));
    $page = $c->req('GET', '/gestion/discover');
    check('page Discover : publicité active, lien ads.txt et test du message de consentement', str_contains($page['body'], 'Active sur les articles')
        && str_contains($page['body'], '/ads.txt') && str_contains($page['body'], '?fc=alwaysshow&amp;fctype=gdpr'));

    $post(['client' => 'pub-1234567890123456', 'consent' => '1', 'slot' => '1234567890']);
    $off = $visitor->req('GET', $longUrl);
    check('publicité coupée : plus aucun bloc ni code AdSense, styles retirés', !$hasAds($off['body']) && !str_contains($off['body'], '.ad{margin')
        && str_contains((string) ($off['headers']['content-security-policy'] ?? ''), "script-src 'self'"));

    // Tendances du jour (faux Google Trends).
    $res = $api('api/discover/trends', []);
    $page = $c->req('GET', '/gestion/discover');
    check('tendances du jour chargées et affichées (presse liée, adresse piégée écartée)', ($res['json']['ok'] ?? false) === true && str_contains($page['body'], 'pain au levain maison')
        && str_contains($page['body'], 'href="https://journal.example/levain-retour"') && !str_contains($page['body'], 'javascript:alert') && str_contains($page['body'], 'Source : Google Trends'), $res['body']);
    $calls = count(file($trDir . '/log.jsonl') ?: []);
    $api('api/discover/trends', []);
    check('au plus une lecture par heure et par pays', count(file($trDir . '/log.jsonl') ?: []) === $calls && str_contains((string) file_get_contents($trDir . '/log.jsonl'), 'geo=FR'));
    $c->req('POST', '/gestion/discover', $c->token($page['body']) + ['action' => 'trend', 'n' => '0']);
    $ideas = array_values(array_filter($read('plan')['items'] ?? [], fn ($i) => $i['title'] === 'pain au levain maison'));
    check('« Ajouter au planning » : idée datée d’aujourd’hui, avec la presse pour contexte', count($ideas) === 1 && $ideas[0]['date'] === date('Y-m-d')
        && str_contains($ideas[0]['brief'], 'https://journal.example/levain-retour') && str_contains($ideas[0]['brief'], 'sans rapporter l’actualité'));
    $c->req('POST', '/gestion/discover', $c->token($page['body']) + ['action' => 'geo', 'geo' => 'ES']);
    $err = $api('api/discover/trends', []);
    check('Google Trends en erreur : message clair, rien d’écrit', ($err['json']['ok'] ?? true) === false && str_contains((string) ($err['json']['error'] ?? ''), 'Google Trends ne répond pas')
        && ($read('site')['discover']['geo'] ?? '') === 'ES');
    $c->req('POST', '/gestion/discover', $c->token($page['body']) + ['action' => 'geo', 'geo' => 'XX']);
    check('pays inconnu : celui de la langue du site', ($read('site')['discover']['geo'] ?? 'x') === '' && Trends::GEOS[0] === 'FR');

    // Idées de l'IA à partir des tendances (faux fournisseur).
    $set = $c->req('GET', '/gestion/settings?tab=ai');
    $c->req('POST', '/gestion/settings', $c->token($set['body']) + ['tab' => 'ai', 'provider' => 'anthropic', 'key_ai_anthropic' => 'sk-ant-test-0123456789',
        'model_anthropic' => 'claude-sonnet-5-5', 'budget' => '10', 'daily' => '60', 'editors' => '1']);
    $page = $c->req('GET', '/gestion/discover');
    check('assistant IA actif : bouton « Proposer des idées pour mon site » avec coût indicatif', (bool) preg_match('#data-dc-ideas[^>]*>.*Proposer des idées pour mon site <small>· ~[\d,]+.\$#u', $page['body']));
    $before = count($read('plan')['items'] ?? []);
    $gen = $api('api/discover/ideas', []);
    $lines = array_values(array_filter(explode("\n", (string) @file_get_contents($aiDir . '/log.jsonl'))));
    $prompt = (string) json_encode(json_decode((string) end($lines), true)['b'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $today = array_filter($read('plan')['items'] ?? [], fn ($i) => $i['source'] === 'ai' && $i['date'] === date('Y-m-d'));
    check('idées de l’IA : ajoutées au planning à la date d’aujourd’hui, coût compté', ($gen['json']['ok'] ?? false) === true && ($gen['json']['added'] ?? 0) === 2
        && count($read('plan')['items'] ?? []) === $before + 2 && count($today) === 2 && (float) ($read('ai/usage')['months'][date('Y-m')]['cost'] ?? 0) > 0, $gen['body']);
    check('IA : tendances et presse fournies, jamais d’article d’actualité ni de fait sur l’actualité', str_contains($prompt, 'pain au levain maison (2000+ searches)')
        && str_contains($prompt, 'Never propose a news report and never state facts about the news itself') && str_contains($prompt, 'Proposing no idea at all is a correct answer'));
} finally {
    proc_terminate($server);
    proc_terminate($trServer);
    proc_terminate($aiServer);
    exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($trDir) . ' ' . escapeshellarg($aiDir));
}
