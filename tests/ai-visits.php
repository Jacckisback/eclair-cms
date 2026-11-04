<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Robots IA (1.12) : visites des robots d'IA notées par index.php avant tout le reste, sans IP ni visiteur humain,
 * journal du mois borné, page de l'administration et carte du tableau de bord. Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\AiVisits;
use Eclair\Settings;

section('1.12 : Robots IA, reconnaissance et agrégation');
$re = AiVisits::pattern();
$ua = [
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)' => 'GPTBot',
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot' => 'ChatGPT-User',
    'Mozilla/5.0 (compatible; Claude-User/1.0; +Claude-User@anthropic.com)' => 'Claude-User',
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)' => 'ClaudeBot',
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)' => 'PerplexityBot',
    'Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)' => 'OAI-SearchBot',
];
$named = [];
foreach ($ua as $agent => $name) {
    $named[] = preg_match($re, $agent, $m) && (AiVisits::bot($m[0])['name'] ?? '') === $name;
}
check('robots d’IA reconnus à leur agent, nom canonique (le plus long l’emporte : ChatGPT-User, pas GPTBot)', !in_array(false, $named, true));
check('navigateurs et moteurs classiques ignorés (Chrome, Safari, Googlebot, Bingbot)', !preg_match($re, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36')
    && !preg_match($re, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
    && !preg_match($re, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
    && !preg_match($re, 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'));
check('trois usages : consultation à la demande, moteur de réponse, entraînement', array_values(array_unique(array_column(AiVisits::BOTS, 2))) === ['user', 'search', 'train']);
check('mesure active par défaut (réglable dans IA & GEO)', Settings::DEFAULTS['geo']['ai_log'] === true);

$aiDir = AiVisits::dir();
$aiBackup = is_dir($aiDir) ? $aiDir . '.bak-' . getmypid() : null;
if ($aiBackup) {
    rename($aiDir, $aiBackup);
}
try {
    @mkdir($aiDir, 0700, true);
    $now = gmmktime(12, 0, 0, 6, 15, 2026);
    $lines = [
        [$now - 3600, 'ChatGPT-User', '/pain-au-levain'],
        [$now - 7200, 'chatgpt-user', '/pain-au-levain?utm=x'],
        [$now - 86400, 'GPTBot', '/a-propos'],
        [$now - 86400 * 2, 'PerplexityBot', '/pain-au-levain'],
        [$now - 100, 'ClaudeBot', '/robots.txt'],
        [$now - 86400 * 40, 'GPTBot', '/trop-vieux'],
        [$now - 50, 'BotRetire', '/inconnu'],
        [$now - 60, 'Claude-User', '/caf%C3%A9'],
    ];
    $byMonth = [];
    foreach ($lines as [$t, $bot, $path]) {
        $byMonth[gmdate('Y-m', $t)][] = $t . "\t" . $bot . "\t" . explode('?', $path)[0];
    }
    foreach ($byMonth as $month => $rows) {
        file_put_contents($aiDir . '/' . $month . '.php', "<?php exit; ?>\n" . implode("\n", $rows) . "\nligne\tabimée\n");
    }
    file_put_contents($aiDir . '/2024-01.php', "<?php exit; ?>\n");
    $r = AiVisits::report(30, $now);
    check('agrégation sur 30 jours : visites trop anciennes, robots inconnus et lignes abîmées écartés', $r['total'] === 6
        && $r['kinds'] === ['user' => 3, 'search' => 1, 'train' => 2], json_encode($r['kinds']));
    check('par robot : nom canonique quelle que soit la casse, du plus actif au moins actif', array_key_first($r['bots']) === 'ChatGPT-User'
        && $r['bots']['ChatGPT-User']['count'] === 2 && $r['bots']['ChatGPT-User']['owner'] === 'OpenAI' && $r['bots']['ChatGPT-User']['last'] === $now - 3600);
    check('par page : les plus demandées par les assistants d’abord, adresse décodée, robots.txt compté à part', array_key_first($r['pages']) === '/pain-au-levain'
        && $r['pages']['/pain-au-levain']['count'] === 3 && $r['pages']['/pain-au-levain']['user'] === 2 && isset($r['pages']['/café'])
        && !isset($r['pages']['/robots.txt']) && $r['robots_txt'] === 1);
    check('par jour : 30 jours, du plus ancien au plus récent', count($r['days']) === 30 && array_key_last($r['days']) === '2026-06-15'
        && $r['days']['2026-06-15'] === ['user' => 3, 'search' => 0, 'train' => 1]);
    check('journaux de plus de 13 mois effacés', !is_file($aiDir . '/2024-01.php'));
    check('chemin noté : caractères de contrôle retirés, toujours une adresse du site', AiVisits::path("/a\x01b") === '/ab' && AiVisits::path('http://x') === '/');
} finally {
    exec('rm -rf ' . escapeshellarg($aiDir));
    if ($aiBackup) {
        rename($aiBackup, $aiDir);
    }
}

/* ─────────────────────────── Parcours HTTP ─────────────────────────── */
if (!in_array('--unit', $argv, true)) {
    section('1.12 : Robots IA, journal tenu par index.php et pages de l’administration');
    [$copy, $port, $server] = serve('ai-visits');
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Fournil Test', 'site_tagline' => 'Pain au levain à Lyon', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $visitor = client($port);
        $human = $visitor->req('GET', '/', [], ['User-Agent: Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/130.0 Safari/537.36']);
        $log = $copy . '/data/ai/' . gmdate('Y-m') . '.php';
        $bot = static fn (string $path, string $agent = 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)'): array
            => $visitor->req('GET', $path, [], ['User-Agent: ' . $agent, 'X-Forwarded-For: 203.0.113.9']);
        check('visiteur humain : rien de noté', $human['status'] === 200 && !is_file($log));
        $first = $bot('/');
        $bot('/?q=secret');
        $bot('/robots.txt', 'Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)');
        $bot('/a%09b%0Ac');
        $content = (string) @file_get_contents($log);
        $rows = array_slice(explode("\n", trim($content)), 1);
        check('robot d’IA : page servie normalement, visite notée (heure, robot, adresse)', $first['status'] === 200 && str_starts_with($content, "<?php exit; ?>\n")
            && count($rows) === 4 && (bool) preg_match('~^\d{10}\tGPTBot\t/$~', $rows[0]));
        check('ni IP, ni paramètres d’adresse, ni tabulation ou retour à la ligne glissés dans l’adresse', !str_contains($content, '203.0.113') && !str_contains($content, '127.0.0.1')
            && !str_contains($content, 'secret') && count(array_filter($rows, static fn ($l) => substr_count($l, "\t") === 2)) === 4);
        check('journal protégé : fichier PHP qui s’arrête aussitôt, illisible depuis le web', $visitor->req('GET', '/data/ai/' . gmdate('Y-m') . '.php')['status'] !== 200
            || !str_contains($visitor->req('GET', '/data/ai/' . gmdate('Y-m') . '.php')['body'], 'GPTBot'));
        file_put_contents($log, str_repeat('x', AiVisits::MAX));
        $bot('/');
        clearstatcache();
        check('journal du mois plein (4 Mo) : plus rien n’est ajouté, le site répond toujours', filesize($log) === AiVisits::MAX && $bot('/')['status'] === 200);
        file_put_contents($log, "<?php exit; ?>\n" . implode("\n", $rows) . "\n");

        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $page = $c->req('GET', '/gestion/ai-visits');
        check('page Robots IA : robots, usages et pages lues', $page['status'] === 200 && str_contains($page['body'], 'GPTBot') && str_contains($page['body'], 'ChatGPT-User')
            && str_contains($page['body'], 'Entraînement des modèles') && str_contains($page['body'], 'data-height='));
        check('page Robots IA : sans attribut style (CSP de l’administration)', !preg_match('~<[^>]+\sstyle="~', $page['body']));
        check('page Robots IA : période choisie (7, 30, 90 jours)', $c->req('GET', '/gestion/ai-visits?days=7')['status'] === 200
            && str_contains($c->req('GET', '/gestion/ai-visits?days=999')['body'], 'aria-current="page">30 jours'));
        $dash = $c->req('GET', '/gestion/');
        check('tableau de bord : carte Robots IA avec lien vers le détail', str_contains($dash['body'], 'id="ai-visits-title"') && str_contains($dash['body'], '/gestion/ai-visits'));
        check('menu de l’administration : entrée Robots IA', str_contains($dash['body'], 'href="/gestion/ai-visits"'));

        // Mesure coupée : index.php n'écrit plus rien, la page le dit.
        settings($copy, ['geo' => ['ai_log' => false]]);
        $c->req('GET', '/gestion/');
        $before = (string) file_get_contents($log);
        $bot('/');
        $bot('/');
        check('mesure coupée : plus aucune visite notée', (string) file_get_contents($log) === $before);
        check('mesure coupée : la page invite à la réactiver, la carte disparaît du tableau de bord', str_contains($c->req('GET', '/gestion/ai-visits')['body'], 'La mesure est coupée')
            && !str_contains($c->req('GET', '/gestion/')['body'], 'id="ai-visits-title"'));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
}
