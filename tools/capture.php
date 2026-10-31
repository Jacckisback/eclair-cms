<?php
/**
 * Capture de référence du rendu public (outil de développement, jamais livré).
 *
 *   php tools/capture.php        installe les 4 sites de départ (+ une page avec chaque type de bloc), garde leurs données
 *                                dans tests/fixtures/sites/<type>/ et écrit tests/fixtures/public-<version>.json :
 *                                empreintes de chaque page (visiteur et session admin), HTML de chaque bloc, CSS/JS publics,
 *                                poids gzip de l'administration, taille de l'archive.
 *
 *   php tools/capture.php --lighthouse
 *                                Lighthouse mobile (médiane de 3 passages) sur l'accueil et un article du site « business » et sur
 *                                la landing, avec le code courant → tests/fixtures/lighthouse-<version>.json.
 *                                Variables : LIGHTHOUSE (commande, défaut « lighthouse »), CHROME_PATH (navigateur).
 *
 * tests/run.php s'en sert pour vérifier qu'une nouvelle version ne change pas un octet du site public.
 * Seules l'empreinte de version des URL d'assets (« ?v=… ») et l'année du pied de page sont neutralisées.
 */
declare(strict_types=1);

const CAPTURE_ADMIN = 'gestion';
const CAPTURE_EMAIL = 'camille@example.com';
const CAPTURE_PASS = 'Un-mot-de-passe-solide';

/** Neutralise ce qui change par conception d'une version ou d'une année à l'autre. */
function capture_normalize(string $html): string
{
    return (string) preg_replace(['~(/assets/[\w./-]+)\?v=[0-9a-f]{8}~', '~© \d{4} ~u'], ['$1?v=VERSION', '© YEAR '], $html);
}

/** Copie d'Éclair servie par le serveur intégré de PHP, avec les données d'un site de référence (ou vierge). */
function capture_serve(string $root, ?string $fixture, string $name): array
{
    $copy = sys_get_temp_dir() . '/eclair-capture-' . $name . '-' . getmypid();
    exec('rm -rf ' . escapeshellarg($copy) . ' && cp -r ' . escapeshellarg($root . '/eclair') . ' ' . escapeshellarg($copy));
    foreach (['data/*.php', 'data/*/', 'cache/*', 'media/20*', 'media/site'] as $glob) {
        exec('rm -rf ' . $copy . '/' . $glob);
    }
    if ($fixture !== null) {
        exec('cp -r ' . escapeshellarg($fixture) . '/data/. ' . escapeshellarg($copy . '/data/'));
        if (is_dir($fixture . '/media')) {
            exec('cp -r ' . escapeshellarg($fixture) . '/media/. ' . escapeshellarg($copy . '/media/'));
        }
    }
    $port = random_int(41000, 49000);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $copy, $root . '/tools/router.php'],
        [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, null,
        ['PHP_CLI_SERVER_WORKERS' => '4', 'PATH' => (string) getenv('PATH')]);
    usleep(600000);
    return [$copy, $port, $server];
}

function capture_req(int $port, string $method, string $path, array|string $data = [], array $headers = [], ?string $jar = null): array
{
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 60] + ($jar !== null ? [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar] : []));
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) && !array_filter($data, fn ($v) => $v instanceof CURLFile) ? http_build_query($data) : $data);
    }
    $body = (string) curl_exec($ch);
    return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE), 'body' => $body];
}

function capture_token(string $body): array
{
    return preg_match('/name="(_ft|_csrf)" value="([a-f0-9]+)"/', $body, $m) ? [$m[1] => $m[2]] : [];
}

function capture_login(int $port, string $jar): void
{
    $page = capture_req($port, 'GET', '/' . CAPTURE_ADMIN . '/login', [], [], $jar);
    capture_req($port, 'POST', '/' . CAPTURE_ADMIN . '/login', capture_token($page['body']) + ['email' => CAPTURE_EMAIL, 'password' => CAPTURE_PASS], [], $jar);
}

/** Pages publiques d'un site : parcours des liens internes depuis l'accueil (+ une page introuvable). */
function capture_urls(int $port, array $seeds = ['/']): array
{
    $todo = $seeds;
    $seen = [];
    while ($todo && count($seen) < 80) {
        $path = array_shift($todo);
        if (isset($seen[$path])) {
            continue;
        }
        $res = capture_req($port, 'GET', $path);
        $seen[$path] = true;
        if (!str_starts_with($res['type'], 'text/html')) {
            continue;
        }
        preg_match_all('~href="(/[^"#?]*)"~', $res['body'], $m);
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!preg_match('~^/(assets|media|_eclair|' . CAPTURE_ADMIN . ')(/|$)|\.(xml|txt|md|ico|png|jpe?g|webp|svg)$~', $href) && !isset($seen[$href])) {
                $todo[] = $href;
            }
        }
    }
    $urls = array_keys(array_filter($seen));
    $urls[] = '/page-introuvable-capture';
    sort($urls);
    return $urls;
}

/** Empreinte de chaque page, pour un visiteur puis avec une session d'administration. */
function capture_pages(int $port, array $urls, ?string $jar = null): array
{
    $out = [];
    foreach ($urls as $path) {
        $res = capture_req($port, 'GET', $path, [], [], $jar);
        $html = capture_normalize($res['body']);
        $out[$path] = [$res['status'], strlen($html), hash('sha256', $html)];
    }
    return $out;
}

/** Fichiers publics (CSS et JS servis aux visiteurs) et leur empreinte. */
function capture_public_files(string $root): array
{
    $files = array_merge(glob($root . '/eclair/assets/site/*') ?: [], [$root . '/eclair/app/theme/theme.css'], glob($root . '/eclair/app/theme/themes/*.css') ?: []);
    $out = [];
    foreach ($files as $f) {
        if (is_file($f) && basename($f) !== 'index.html') {
            $out[substr($f, strlen($root) + 1)] = hash_file('sha256', $f);
        }
    }
    ksort($out);
    return $out;
}

/** Poids gzip -9 des fichiers de l'administration. */
function capture_admin_weights(string $root): array
{
    $out = [];
    foreach (glob($root . '/eclair/assets/admin/*.{js,css}', GLOB_BRACE) ?: [] as $f) {
        $raw = (string) file_get_contents($f);
        $out[basename($f)] = ['raw' => strlen($raw), 'gzip' => strlen((string) gzencode($raw, 9))];
    }
    ksort($out);
    return $out;
}

/** Une page avec chaque type de bloc, pour figer leur HTML. */
function capture_all_blocks(string $media): array
{
    return [
        ['type' => 'hero', 'title' => 'Bandeau de démonstration', 'text' => 'Un <strong>texte</strong> d’accroche avec <a href="/contact">un lien</a>.',
            'button' => 'Nous écrire', 'url' => '/contact', 'button2' => 'En savoir plus', 'url2' => '/a-propos', 'media' => $media],
        ['type' => 'paragraph', 'text' => 'Un paragraphe avec du <em>style</em>, <code>du code</code> et <a href="https://example.com">un lien externe</a>.'],
        ['type' => 'heading', 'level' => 2, 'text' => 'Intertitre de niveau deux', 'anchor' => ''],
        ['type' => 'heading', 'level' => 3, 'text' => 'Intertitre de niveau trois', 'anchor' => 'ancre-perso'],
        ['type' => 'list', 'style' => 'ul', 'items' => ['Premier point', 'Deuxième <strong>point</strong>']],
        ['type' => 'list', 'style' => 'ol', 'items' => ['Étape une', 'Étape deux']],
        ['type' => 'quote', 'text' => 'Une citation marquante.', 'cite' => 'Quelqu’un'],
        ['type' => 'image', 'media' => $media, 'alt' => 'Texte alternatif', 'caption' => 'Une <em>légende</em>', 'link' => '', 'size' => 'wide'],
        ['type' => 'callout', 'variant' => 'tip', 'title' => '', 'text' => 'Une astuce utile.'],
        ['type' => 'callout', 'variant' => 'warning', 'title' => 'Attention', 'text' => 'Un avertissement.'],
        ['type' => 'faq', 'title' => '', 'items' => [['q' => 'Première question ?', 'a' => 'Première réponse.'], ['q' => 'Deuxième question ?', 'a' => 'Deuxième <strong>réponse</strong>.']]],
        ['type' => 'table', 'head' => true, 'caption' => 'Un tableau', 'rows' => [['Offre', 'Prix'], ['Essentiel', '[À confirmer]']]],
        ['type' => 'button', 'text' => 'Réserver', 'url' => '/contact', 'style' => 'secondary', 'align' => 'center'],
        ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Une vidéo'],
        ['type' => 'code', 'code' => "echo '<b>bonjour</b>';", 'lang' => 'php'],
        ['type' => 'divider'],
        ['type' => 'features', 'title' => 'Nos atouts', 'items' => [['icon' => '🚀', 'title' => 'Rapide', 'text' => 'Très <em>rapide</em>.'], ['icon' => '', 'title' => 'Simple', 'text' => '']]],
        ['type' => 'team', 'title' => '', 'items' => [['name' => 'Léa Martin', 'role' => 'Fondatrice', 'bio' => 'Une <strong>bio</strong>.', 'media' => $media, 'link' => 'https://www.linkedin.com/in/lea'],
            ['name' => 'Jean Dupont', 'role' => '', 'bio' => '', 'media' => '', 'link' => '']]],
        ['type' => 'posts', 'title' => '', 'count' => 3, 'category' => ''],
        ['type' => 'products', 'title' => 'Boutique', 'count' => 4, 'category' => ''],
        ['type' => 'contact', 'title' => 'Écrivez-nous', 'button' => ''],
    ];
}

/** HTML de chaque bloc (rendu seul), dans un processus qui lit les données d'un site de référence. */
function capture_blocks(string $root, string $fixture): array
{
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --render-blocks ' . escapeshellarg($root) . ' ' . escapeshellarg($fixture), $lines, $code);
    $data = json_decode(implode("\n", $lines), true);
    if ($code !== 0 || !is_array($data)) {
        throw new RuntimeException('Rendu des blocs impossible : ' . implode("\n", $lines));
    }
    foreach ($data as $i => [$type, $html]) {
        $out[$i . '-' . $type] = $html;
    }
    return $out;
}

/** Sous-processus : rend chaque bloc de la page « tous-les-blocs » avec le code courant. */
function capture_render_blocks(string $root, string $fixture): void
{
    $data = sys_get_temp_dir() . '/eclair-capture-blocks-' . getmypid();
    exec('rm -rf ' . escapeshellarg($data) . ' && cp -r ' . escapeshellarg($fixture . '/data') . ' ' . escapeshellarg($data));
    define('ECLAIR', true);
    define('ECLAIR_VERSION', 'capture');
    define('ECLAIR_ROOT', $root . '/eclair');
    define('ECLAIR_DATA', $data);
    define('ECLAIR_DEBUG', true);
    $_SERVER['HTTP_HOST'] = '127.0.0.1';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    spl_autoload_register(static function (string $class): void {
        $file = ECLAIR_ROOT . '/app/lib/' . substr($class, 7) . '.php';
        if (str_starts_with($class, 'Eclair\\') && is_file($file)) {
            require $file;
        }
    });
    require ECLAIR_ROOT . '/app/lib/helpers.php';
    $page = Eclair\Content::findBySlugAny(['tous-les-blocs']);
    $item = $page ? Eclair\Content::get($page['id']) : null;
    $out = [];
    foreach ($item['blocks'] ?? [] as $b) {
        $out[] = [$b['type'], Eclair\Blocks::html([$b], ['lang' => 'fr', 'page' => $item['id']])];
    }
    exec('rm -rf ' . escapeshellarg($data));
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Installe un site de départ et renvoie le dossier de ses données de référence. */
function capture_install(string $root, string $type, string $fixtures): void
{
    [$copy, $port, $server] = capture_serve($root, null, 'install-' . $type);
    $jar = (string) tempnam(sys_get_temp_dir(), 'jar');
    try {
        $form = capture_req($port, 'GET', '/', [], [], $jar);
        capture_req($port, 'POST', '/', capture_token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Site ' . $type, 'site_tagline' => 'Référence ' . $type, 'site_lang' => 'fr', 'site_type' => $type,
            'user_name' => 'Camille Martin', 'user_email' => CAPTURE_EMAIL, 'user_pass' => CAPTURE_PASS, 'index' => '1', 'ai' => 'open', 'admin_path' => CAPTURE_ADMIN,
        ], ['Accept: application/json'], $jar);
        capture_login($port, $jar);
        if ($type === 'business') {
            // Une page qui contient chaque type de bloc, avec une image de la médiathèque.
            preg_match('/"csrf":"([a-f0-9]+)"/', capture_req($port, 'GET', '/' . CAPTURE_ADMIN, [], [], $jar)['body'], $m);
            $img = imagecreatetruecolor(1200, 800);
            imagefilledrectangle($img, 0, 0, 1199, 799, (int) imagecolorallocate($img, 91, 76, 240));
            $file = sys_get_temp_dir() . '/capture-' . getmypid() . '.jpg';
            imagejpeg($img, $file, 80);
            $up = json_decode(capture_req($port, 'POST', '/' . CAPTURE_ADMIN . '/api/media/upload', ['file' => new CURLFile($file, 'image/jpeg', 'capture.jpg'), 'alt' => 'Image de démonstration'],
                ['Accept: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')], $jar)['body'], true);
            @unlink($file);
            $headers = ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')];
            $save = static fn (array $item): array => (array) json_decode(capture_req($port, 'POST', '/' . CAPTURE_ADMIN . '/api/save', (string) json_encode($item), $headers, $jar)['body'], true);
            $media = (string) ($up['media']['id'] ?? '');
            // Boutique ouverte (mentions légales + CGV) avec deux produits, pour figer aussi le bloc Produits et les pages de la boutique.
            $raw = (string) file_get_contents($copy . '/data/index.php');
            $legal = (string) array_search('mentions-legales', array_column(json_decode(substr($raw, (int) strpos($raw, '{')), true), 'slug', 'id'), true);
            $terms = $save(['type' => 'page', 'title' => 'Conditions générales de vente', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Prix TTC.']]]);
            $form = capture_req($port, 'GET', '/' . CAPTURE_ADMIN . '/settings?tab=shop', [], [], $jar);
            capture_req($port, 'POST', '/' . CAPTURE_ADMIN . '/settings', capture_token($form['body']) + ['tab' => 'shop', 'shop_enabled' => '1', 'shop_base' => 'boutique',
                'shop_title' => '', 'shop_description' => 'Pièces uniques faites main.', 'currency' => 'EUR', 'button' => '', 'shop_per_page' => '12', 'shipping_price' => '4,90',
                'free_from' => '60', 'days_min' => '2', 'days_max' => '4', 'countries' => 'fr, be', 'returns_days' => '14', 'legal' => $legal,
                'terms' => (string) ($terms['item']['id'] ?? ''), 'delivery' => '', 'thanks' => ''], [], $jar);
            $save(['type' => 'product', 'title' => 'Bol en grès émaillé', 'status' => 'published', 'image' => $media, 'summary' => 'Un bol tourné à la main, émaillé bleu.',
                'blocks' => [['type' => 'paragraph', 'text' => 'Bol de 14 cm.']], 'product' => ['price' => '29,90', 'compare' => '39', 'availability' => 'in_stock',
                'link' => 'https://buy.stripe.com/test_bol', 'brand' => 'Atelier', 'sku' => 'BOL-01', 'gallery' => [], 'variants' => [], 'condition' => 'new', 'digital' => false]]);
            $save(['type' => 'product', 'title' => 'Tasse à café', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Tasse de 12 cl.']],
                'product' => ['price' => '18', 'link' => 'https://www.paypal.com/ncp/payment/TASSE', 'variants' => [['label' => 'Blanche'], ['label' => 'Bleue', 'availability' => 'out_of_stock']]]]);
            $save(['type' => 'page', 'title' => 'Tous les blocs', 'slug' => 'tous-les-blocs', 'status' => 'published',
                'summary' => 'Une page qui contient chaque type de bloc d’Éclair.', 'blocks' => capture_all_blocks($media)]);
        }
        $dest = $fixtures . '/sites/' . $type;
        exec('rm -rf ' . escapeshellarg($dest) . ' && mkdir -p ' . escapeshellarg($dest . '/data'));
        foreach (glob($copy . '/data/*') ?: [] as $f) {
            if (!preg_match('~/(sessions|limits|logs|index\.html)$~', $f)) {
                exec('cp -r ' . escapeshellarg($f) . ' ' . escapeshellarg($dest . '/data/'));
            }
        }
        // Adresse figée (l'installateur a enregistré un port aléatoire) : rendu reproductible, aucune redirection vers l'hôte officiel.
        $file = $dest . '/data/site.php';
        $raw = (string) file_get_contents($file);
        $guard = substr($raw, 0, (int) strpos($raw, '{'));
        $site = json_decode(substr($raw, strlen($guard)), true);
        $site['url'] = 'https://eclair.test';
        $site['security']['verified'] = '';
        file_put_contents($file, $guard . json_encode($site, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach (glob($copy . '/media/{20*,site}', GLOB_BRACE) ?: [] as $dir) {
            exec('mkdir -p ' . escapeshellarg($dest . '/media') . ' && cp -r ' . escapeshellarg($dir) . ' ' . escapeshellarg($dest . '/media/'));
        }
    } finally {
        proc_terminate($server);
        @unlink($jar);
        exec('rm -rf ' . escapeshellarg($copy));
    }
}

/** Capture complète du rendu public des sites de référence avec le code courant. */
function capture_all(string $root, string $fixtures): array
{
    $sites = [];
    foreach (['blog', 'business', 'freelance', 'landing'] as $type) {
        [$copy, $port, $server] = capture_serve($root, $fixtures . '/sites/' . $type, 'site-' . $type);
        $jar = (string) tempnam(sys_get_temp_dir(), 'jar');
        try {
            $urls = capture_urls($port, $type === 'business' ? ['/', '/tous-les-blocs'] : ['/']);
            $visitor = capture_pages($port, $urls);
            capture_login($port, $jar);
            $admin = capture_pages($port, $urls, $jar);
            $sites[$type] = ['visitor' => $visitor, 'admin_same' => $visitor === $admin];
        } finally {
            proc_terminate($server);
            @unlink($jar);
            exec('rm -rf ' . escapeshellarg($copy));
        }
    }
    return $sites;
}

/** Lighthouse mobile sur des pages des sites de référence : scores des 4 catégories, LCP, CLS, TBT (médiane de 3 passages). */
function capture_lighthouse(string $root, string $fixtures): array
{
    $bin = (string) (getenv('LIGHTHOUSE') ?: 'lighthouse');
    $out = [];
    foreach (['business' => ['/', '/blog/premiers-pas-avec-eclair'], 'landing' => ['/']] as $type => $paths) {
        [$copy, $port, $server] = capture_serve($root, $fixtures . '/sites/' . $type, 'lh-' . $type);
        try {
            foreach ($paths as $path) {
                $url = 'http://127.0.0.1:' . $port . $path;
                capture_req($port, 'GET', $path);
                $runs = [];
                for ($i = 0; $i < 3; $i++) {
                    $json = sys_get_temp_dir() . '/eclair-lh-' . getmypid() . '.json';
                    exec(escapeshellarg($bin) . ' ' . escapeshellarg($url) . ' --quiet --output=json --output-path=' . escapeshellarg($json)
                        . ' --only-categories=performance,accessibility,best-practices,seo --chrome-flags="--headless=new --no-sandbox --no-proxy-server" 2>/dev/null');
                    $r = json_decode((string) @file_get_contents($json), true);
                    @unlink($json);
                    if (is_array($r['categories'] ?? null)) {
                        $runs[] = ['scores' => array_map(fn ($c) => (int) round(($c['score'] ?? 0) * 100), $r['categories']),
                            'lcp_ms' => (int) round($r['audits']['largest-contentful-paint']['numericValue']),
                            'cls' => round((float) $r['audits']['cumulative-layout-shift']['numericValue'], 3),
                            'tbt_ms' => (int) round($r['audits']['total-blocking-time']['numericValue'])];
                    }
                }
                usort($runs, fn ($a, $b) => $a['lcp_ms'] <=> $b['lcp_ms']);
                $out[$type . ' ' . $path] = $runs[intdiv(count($runs), 2)] ?? null;
            }
        } finally {
            proc_terminate($server);
            exec('rm -rf ' . escapeshellarg($copy));
        }
    }
    return $out;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    if (($argv[1] ?? '') === '--render-blocks') {
        capture_render_blocks((string) $argv[2], (string) $argv[3]);
        exit(0);
    }
    preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/eclair/index.php'), $v);
    $version = $v[1] ?? 'dev';
    $fixtures = $root . '/tests/fixtures';
    if (($argv[1] ?? '') === '--lighthouse') {
        $lh = capture_lighthouse($root, $fixtures);
        file_put_contents($fixtures . '/lighthouse-' . $version . '.json', json_encode($lh, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        foreach ($lh as $page => $r) {
            echo $page, ' : ', $r ? implode(' / ', $r['scores']) . ' · LCP ' . $r['lcp_ms'] . ' ms · CLS ' . $r['cls'] . ' · TBT ' . $r['tbt_ms'] . ' ms' : 'échec', "\n";
        }
        exit(0);
    }
    foreach (['blog', 'business', 'freelance', 'landing'] as $type) {
        capture_install($root, $type, $fixtures);
        echo "✓ Site de départ « $type » installé\n";
    }
    $zip = $root . '/dist/eclair-cms-' . $version . '.zip';
    $unpacked = 0;
    if (is_file($zip) && class_exists('ZipArchive')) {
        $z = new ZipArchive();
        $z->open($zip);
        for ($i = 0; $i < $z->numFiles; $i++) {
            $unpacked += (int) $z->statIndex($i)['size'];
        }
    }
    $capture = [
        'version' => $version,
        'normalized' => ['?v= des URL d’assets', 'année du pied de page'],
        'sites' => capture_all($root, $fixtures),
        'blocks' => capture_blocks($root, $fixtures . '/sites/business'),
        'public_files' => capture_public_files($root),
        'admin_weights' => capture_admin_weights($root),
        'archive' => ['zip' => is_file($zip) ? filesize($zip) : 0, 'unpacked' => $unpacked],
    ];
    file_put_contents($fixtures . '/public-' . $version . '.json', json_encode($capture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    foreach ($capture['sites'] as $type => $s) {
        echo "✓ $type : " . count($s['visitor']) . ' pages, ' . ($s['admin_same'] ? 'identiques avec une session admin' : '⚠ DIFFÉRENTES avec une session admin') . "\n";
    }
    echo '✓ ' . count($capture['blocks']) . ' blocs, ' . count($capture['public_files']) . " fichiers publics → tests/fixtures/public-$version.json\n";
}
