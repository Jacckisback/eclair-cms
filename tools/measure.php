<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Mesures reproductibles d'Éclair, avant/après : archive, taille installée, traductions et actifs, fichiers PHP chargés,
 * mémoire et durée par parcours (cache froid et chaud), poids HTML/CSS/JS de pages représentatives.
 *
 *   php tools/measure.php                    tableau Markdown (référence : dist/eclair-cms-1.14.1.zip, comparée aux sources)
 *   php tools/measure.php --write            écrit aussi docs/MEASURES.md
 *   php tools/measure.php --check            vérifie les budgets de tools/budgets.json (code de sortie 1 si dépassés)
 *   php tools/measure.php --pages=200        ajoute un site de 200 contenus (performances selon la taille du site)
 *
 * L'archive des sources est construite dans un dossier temporaire, SANS clé de signature (jamais annoncée). Chaque
 * installation tourne sur le serveur intégré de PHP, avec tools/measure-router.php qui note, pour chaque requête, la
 * mémoire de pointe, le nombre de fichiers PHP inclus et la durée. Les durées dépendent de la machine : les budgets ne
 * portent que sur des grandeurs stables (octets, fichiers, mémoire).
 */
declare(strict_types=1);

$repo = dirname(__DIR__);
$args = array_slice($argv, 1);
$write = in_array('--write', $args, true);
$checkBudgets = in_array('--check', $args, true);
$pages = 0;
foreach ($args as $a) {
    if (preg_match('/^--pages=(\d{1,5})$/', $a, $m)) {
        $pages = (int) $m[1];
    }
}
$tmp = sys_get_temp_dir() . '/eclair-measure-' . getmypid();
@mkdir($tmp, 0700, true);

function run(array $cmd, ?string $cwd = null, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env + ['PATH' => (string) getenv('PATH')]);
    $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    return [proc_close($p), $out];
}

/** Archive des sources construite dans une copie du dépôt, sans clé (l'environnement ne contient que PATH). */
function build_current(string $repo, string $tmp): string
{
    $copy = $tmp . '/repo';
    run(['rm', '-rf', $copy]);
    run(['cp', '-r', $repo, $copy]);
    [$rc, $out] = run([PHP_BINARY, $copy . '/tools/build.php'], $copy);
    preg_match('~Archive : (\S+\.zip)~', $out, $m);
    if ($rc !== 0 || !isset($m[1]) || !is_file($m[1])) {
        fwrite(STDERR, "✗ Construction impossible :\n$out\n");
        exit(1);
    }
    return $m[1];
}

function dir_size(string $dir, ?string $pattern = null): array
{
    $bytes = 0;
    $files = 0;
    if (!is_dir($dir)) {
        return [0, 0];
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $path => $info) {
        if ($info->isFile() && ($pattern === null || preg_match($pattern, (string) $path))) {
            $bytes += $info->getSize();
            $files++;
        }
    }
    return [$bytes, $files];
}

/** Petit client HTTP avec cookies. */
function http(int $port, string $method, string $path, array $data = [], array $headers = [], string $jar = ''): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_PROXY => '',
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'head' => substr($raw, 0, $size), 'body' => substr($raw, $size),
        'time' => (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME)];
}

function token(string $body): array
{
    return preg_match('/name="(_ft|_csrf)" value="([a-f0-9]+)"/', $body, $m) ? [$m[1] => $m[2]] : [];
}

/** Installe une archive, la sert, et mesure les parcours représentatifs. */
function measure(string $zipFile, string $label, string $tmp, int $pages): array
{
    $root = $tmp . '/' . $label;
    run(['rm', '-rf', $root]);
    $zip = new ZipArchive();
    $zip->open($zipFile);
    $zip->extractTo($root . '-zip');
    $zip->close();
    rename($root . '-zip/eclair', $root);
    run(['rm', '-rf', $root . '-zip']);
    $r = ['label' => $label, 'zip' => filesize($zipFile)];
    [$r['installed'], $r['files']] = dir_size($root);
    [$r['lang'], $r['lang_files']] = dir_size($root . '/app/lang');
    $r['lang_each'] = (int) round($r['lang'] / max(1, $r['lang_files']));
    [$r['lib'], $r['classes']] = dir_size($root . '/app/lib', '~\.php$~');
    [$r['admin_assets']] = dir_size($root . '/assets/admin');
    [$r['site_assets']] = dir_size($root . '/assets/site');
    [$r['theme']] = dir_size($root . '/app/theme');

    $log = $tmp . '/' . $label . '.log';
    @unlink($log);
    $port = random_int(30001, 39999);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root, dirname(__DIR__) . '/tools/measure-router.php'],
        [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, null,
        ['PATH' => (string) getenv('PATH'), 'PHP_CLI_SERVER_WORKERS' => '4', 'ECLAIR_MEASURE_LOG' => $log]);
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
        usleep(100000);
    }
    $jar = $tmp . '/' . $label . '.jar';
    @unlink($jar);
    try {
        $form = http($port, 'GET', '/', [], [], $jar);
        http($port, 'POST', '/', token($form['body']) + ['ui' => 'fr', 'site_name' => 'Atelier Mesure', 'site_tagline' => 'Céramique', 'site_lang' => 'fr',
            'site_type' => 'blog', 'user_name' => 'Camille Mesure', 'user_email' => 'mesure@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1',
            'ai' => 'open', 'admin_path' => 'gestion'], ['Accept: application/json'], $jar);
        $login = http($port, 'GET', '/gestion/login', [], [], $jar);
        http($port, 'POST', '/gestion/login', token($login['body']) + ['email' => 'mesure@example.com', 'password' => 'Un-mot-de-passe-solide'], [], $jar);
        if ($pages > 0) {
            seed($root, $pages);
        }
        http($port, 'GET', '/gestion', [], [], $jar); // migrations éventuelles hors mesure
        $purge = static function () use ($root): void {
            foreach (glob($root . '/cache/pages/*') ?: [] as $f) {
                if (preg_match('~/[a-f0-9]{32}$~', $f)) {
                    @unlink($f);
                }
            }
        };
        $routes = [];
        $purge();
        file_put_contents($log, '');
        $home = http($port, 'GET', '/');
        $routes['home_cold'] = $home;
        $routes['home_warm'] = http($port, 'GET', '/');
        $article = '';
        if (preg_match_all('~href="(/[a-z0-9-]+/[a-z0-9-]+)"~', $home['body'], $mm)) {
            $article = $mm[1][0];
        }
        if ($article !== '') {
            $routes['post_cold'] = http($port, 'GET', $article);
            $routes['post_warm'] = http($port, 'GET', $article);
        }
        $routes['404'] = http($port, 'GET', '/introuvable-' . bin2hex(random_bytes(3)));
        $routes['sitemap'] = http($port, 'GET', '/sitemap.xml');
        $routes['login'] = http($port, 'GET', '/gestion/login');
        $routes['admin'] = http($port, 'GET', '/gestion', [], [], $jar);
        $routes['editor'] = http($port, 'GET', '/gestion/new/post', [], [], $jar);
        usleep(200000);
        $lines = array_values(array_filter(array_map(static fn (string $l): ?array => json_decode($l, true), file($log) ?: [])));
        // Chaque parcours est apparié à la première ligne non utilisée de la même adresse (les appels internes du site à
        // lui-même, comme la vérification de son adresse, ajoutent des lignes).
        $paths = ['home_cold' => '/', 'home_warm' => '/', 'post_cold' => $article, 'post_warm' => $article, '404' => null, 'sitemap' => '/sitemap.xml',
            'login' => '/gestion/login', 'admin' => '/gestion', 'editor' => '/gestion/new/post'];
        $used = [];
        foreach (array_keys($routes) as $k) {
            $m = [];
            foreach ($lines as $i => $line) {
                $uri = (string) ($line['uri'] ?? '');
                if (!isset($used[$i]) && ($paths[$k] === null ? str_starts_with($uri, '/introuvable-') : $uri === $paths[$k])) {
                    $used[$i] = true;
                    $m = $line;
                    break;
                }
            }
            $r['route'][$k] = ['status' => $routes[$k]['status'], 'mem' => (int) ($m['mem'] ?? 0), 'files' => (int) ($m['files'] ?? 0),
                'ms' => round((float) ($m['ms'] ?? $routes[$k]['time'] * 1000), 1), 'bytes' => strlen($routes[$k]['body'])];
        }
        // Poids du HTML public : CSS en ligne, JavaScript du site.
        $html = $home['body'];
        preg_match_all('~<style[^>]*>(.*?)</style>~s', $html, $styles);
        $r['home_html'] = strlen($html);
        $r['home_css'] = array_sum(array_map('strlen', $styles[1] ?? []));
        preg_match_all('~<script[^>]+src="([^"?]+)~', $html, $scripts);
        $js = 0;
        foreach ($scripts[1] ?? [] as $src) {
            $js += (int) @filesize($root . $src);
        }
        $r['home_js'] = $js;
        $r['home_fonts'] = substr_count($html, '.woff2');
    } finally {
        proc_terminate($server);
    }
    return $r;
}

/** Ajoute $n articles publiés (données au format d'Éclair) puis reconstruit l'index au prochain passage. */
function seed(string $root, int $n): void
{
    $dir = $root . '/data/content';
    for ($i = 1; $i <= $n; $i++) {
        $id = sprintf('m%06d', $i);
        $item = ['id' => $id, 'type' => 'post', 'title' => 'Article de mesure ' . $i, 'slug' => 'article-mesure-' . $i, 'status' => 'published',
            'created' => time() - $i * 3600, 'updated' => time() - $i * 3600, 'published' => time() - $i * 3600, 'author' => '', 'lang' => 'fr',
            'blocks' => [['type' => 'paragraph', 'text' => str_repeat('Texte de mesure représentatif. ', 40)]]];
        file_put_contents($dir . '/' . $id . '.php', "<?php exit; ?>\n" . json_encode($item));
    }
    @unlink($root . '/data/index.php');
}

$reference = $repo . '/dist/eclair-cms-1.14.1.zip';
$current = build_current($repo, $tmp);
$before = measure($reference, 'v1.14.1', $tmp, $pages);
$after = measure($current, 'sources', $tmp, $pages);

$ko = static fn (int $b): string => number_format($b / 1024, 1, ',', ' ') . ' Ko';
$delta = static fn (int|float $a, int|float $b): string => $a == 0 ? '' : sprintf('%+.1f %%', ($b - $a) / $a * 100);
$rows = [
    ['Archive ZIP du noyau', $before['zip'], $after['zip'], 'ko'],
    ['Taille installée', $before['installed'], $after['installed'], 'ko'],
    ['Fichiers installés', $before['files'], $after['files'], 'n'],
    ['Classes PHP (app/lib)', $before['classes'], $after['classes'], 'n'],
    ['Code PHP (app/lib)', $before['lib'], $after['lib'], 'ko'],
    ['Traductions de l’administration (toutes)', $before['lang'], $after['lang'], 'ko'],
    ['Traductions : une langue', $before['lang_each'], $after['lang_each'], 'ko'],
    ['Actifs de l’administration', $before['admin_assets'], $after['admin_assets'], 'ko'],
    ['Thème de base (app/theme)', $before['theme'], $after['theme'], 'ko'],
    ['Accueil : HTML', $before['home_html'], $after['home_html'], 'ko'],
    ['Accueil : CSS en ligne', $before['home_css'], $after['home_css'], 'ko'],
    ['Accueil : JavaScript', $before['home_js'], $after['home_js'], 'ko'],
    ['Accueil : polices', $before['home_fonts'], $after['home_fonts'], 'n'],
];
$md = "| Mesure | 1.14.1 | Sources | Écart |\n|---|---:|---:|---:|\n";
foreach ($rows as [$name, $a, $b, $unit]) {
    $md .= '| ' . $name . ' | ' . ($unit === 'ko' ? $ko($a) : $a) . ' | ' . ($unit === 'ko' ? $ko($b) : $b) . ' | ' . $delta($a, $b) . " |\n";
}
$md .= "\n| Parcours | 1.14.1 : fichiers PHP / mémoire / durée | Sources : fichiers PHP / mémoire / durée |\n|---|---|---|\n";
foreach (array_keys($after['route']) as $k) {
    $f = static fn (array $r): string => isset($r['route'][$k]) ? sprintf('%d / %s / %s ms (%d)', $r['route'][$k]['files'], $ko($r['route'][$k]['mem']), $r['route'][$k]['ms'], $r['route'][$k]['status']) : '—';
    $md .= '| ' . $k . ' | ' . $f($before) . ' | ' . $f($after) . " |\n";
}
$intro = "Mesures produites par `php tools/measure.php" . ($pages ? " --pages=$pages" : '') . "` le " . gmdate('Y-m-d H:i') . " UTC, PHP " . PHP_VERSION
    . ", serveur intégré de PHP" . ($pages ? ", site de $pages articles ajoutés" : ', site neuf (type blog)') . ".\n"
    . "Durées indicatives (machine de mesure) ; mémoire = pointe PHP de la requête ; fichiers = fichiers PHP inclus (`get_included_files`).\n"
    . "« _cold » : cache de pages vidé ; « _warm » : page servie par le chemin rapide d'index.php.\n\n";
echo $intro . $md;
if ($write) {
    file_put_contents($repo . '/docs/MEASURES.md', "# Mesures d’Éclair\n\n" . $intro . $md
        . "\nBudgets vérifiés automatiquement : `tools/budgets.json` (`php tools/measure.php --check`, lancé par la CI).\n");
}

$code = 0;
if ($checkBudgets) {
    $budgets = json_decode((string) file_get_contents(__DIR__ . '/budgets.json'), true);
    $values = ['zip' => $after['zip'], 'installed' => $after['installed'], 'lang_each' => $after['lang_each'], 'home_html' => $after['home_html'],
        'home_css' => $after['home_css'], 'home_js' => $after['home_js'], 'home_fonts' => $after['home_fonts'],
        'home_warm_files' => $after['route']['home_warm']['files'] ?? 99, 'home_cold_files' => $after['route']['home_cold']['files'] ?? 999,
        'home_cold_mem' => $after['route']['home_cold']['mem'] ?? PHP_INT_MAX];
    foreach ((array) ($budgets['budgets'] ?? []) as $key => $max) {
        $ok = isset($values[$key]) && $values[$key] <= $max;
        echo ($ok ? '✓' : '✗') . " budget $key : " . ($values[$key] ?? '?') . " ≤ $max\n";
        $code = $ok ? $code : 1;
    }
}
run(['rm', '-rf', $tmp]);
exit($code);
