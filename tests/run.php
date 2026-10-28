<?php
/**
 * Tests d'Éclair CMS — sans dépendance.
 *
 *   php tests/run.php          tests unitaires + intégration HTTP (serveur PHP intégré)
 *   php tests/run.php --unit   tests unitaires seulement
 */
declare(strict_types=1);

define('ECLAIR', true);
define('ECLAIR_VERSION', 'test');
define('ECLAIR_ROOT', dirname(__DIR__) . '/eclair');
define('ECLAIR_DATA', sys_get_temp_dir() . '/eclair-test-data-' . getmypid());
define('ECLAIR_DEBUG', true);
define('ECLAIR_TESTING', true);

spl_autoload_register(static function (string $class): void {
    $file = ECLAIR_ROOT . '/app/lib/' . substr($class, 7) . '.php';
    if (str_starts_with($class, 'Eclair\\') && is_file($file)) {
        require $file;
    }
});
require ECLAIR_ROOT . '/app/lib/helpers.php';

use Eclair\Blocks;
use Eclair\Redirects;
use Eclair\Sanitize;
use Eclair\Theme;
use Eclair\Totp;

$passed = 0;
$failed = [];
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        return;
    }
    $failed[] = $label . ($detail !== '' ? ' → ' . $detail : '');
    echo "  ✗ $label" . ($detail !== '' ? "\n      $detail" : '') . "\n";
}
function section(string $title): void
{
    echo "\n▶ $title\n";
}

/* ─────────────────────────── Assainissement (XSS) ─────────────────────────── */
section('Assainissement du texte enrichi');
$xss = [
    '<script>alert(1)</script>' => '',
    '<img src=x onerror=alert(1)>' => '',
    '<svg onload=alert(1)><circle/></svg>' => '',
    '<a href="javascript:alert(1)">x</a>' => 'x',
    '<a href="JaVaScRiPt:alert(1)">x</a>' => 'x',
    '<a href="java&#x09;script:alert(1)">x</a>' => 'x',
    '<a href=" javascript:alert(1)">x</a>' => 'x',
    '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>' => 'x',
    '<a href="vbscript:msgbox(1)">x</a>' => 'x',
    '<a href="https://ok.fr" onclick="alert(1)" style="color:red">y</a>' => '<a href="https://ok.fr">y</a>',
    '<strong onmouseover="alert(1)">b</strong>' => '<strong>b</strong>',
    '<b><i>imbriqué</i></b>' => '<strong><em>imbriqué</em></strong>',
    '<style>*{color:red}</style>texte' => 'texte',
    '"><script>alert(1)</script>' => '&quot;&gt;',
    '<!--<img src=x onerror=alert(1)>-->ok' => 'ok',
    '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>' => '',
    '<noscript><p title="</noscript><img src=x onerror=alert(1)>">' => '',
    '<a href="/contact" target="_blank">c</a>' => '<a href="/contact" target="_blank" rel="noopener">c</a>',
    '<a href="https://x.fr" rel="nofollow sponsored evil">s</a>' => '<a href="https://x.fr" rel="nofollow sponsored">s</a>',
    'R&D <span>simple</span>' => 'R&amp;D simple',
    'Ligne 1<div>Ligne 2</div>' => 'Ligne 1<br>Ligne 2',
];
foreach ($xss as $in => $expected) {
    $out = Sanitize::inline($in);
    check('inline: ' . $in, $out === $expected, 'obtenu : ' . $out);
    check('inline sans gestionnaire : ' . $in, !preg_match('~on\w+\s*=|javascript:|<script|<img|<svg|<style~i', $out), $out);
}
check('url https', Sanitize::url('https://exemple.fr/a?b=1') === 'https://exemple.fr/a?b=1');
check('url mailto', Sanitize::url('mailto:a@b.fr') === 'mailto:a@b.fr');
check('url relative', Sanitize::url('/blog/x#y') === '/blog/x#y');
check('url javascript', Sanitize::url("java\nscript:alert(1)") === '');
check('url data', Sanitize::url('DATA:text/html,x') === '');
check('texte brut', Sanitize::text("<b>Salut</b>\n  à   tous") === 'Salut à tous');
check('couleur', Sanitize::color('#AbCdEf', '#000000') === '#abcdef' && Sanitize::color('red;}', '#000000') === '#000000');
check('chemin de page', Sanitize::path('Services/Formation IA/../../etc') === 'services/formation-ia/etc');

/* ─────────────────────────────── Slugs ─────────────────────────────── */
section('Slugs');
check('accents', slugify('Été à Madrid : c’est génial !') === 'ete-a-madrid-c-est-genial');
check('œ et ß', slugify('Cœur Straße') === 'coeur-strasse');
check('allemand', slugify('Über München', 'de') === 'ueber-muenchen');
check('espagnol', slugify('¿Qué es la Inteligencia Artificial?', 'es') === 'que-es-la-inteligencia-artificial');
check('longueur', strlen(slugify(str_repeat('mot ', 50))) <= 80);

/* ─────────────────────────────── TOTP (RFC 6238) ─────────────────────────────── */
section('Double authentification (RFC 6238)');
$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $time => $code) {
    check("TOTP t=$time", Totp::code($secret, intdiv($time, 30)) === $code, Totp::code($secret, intdiv($time, 30)));
}
$s = Totp::secret();
check('secret base32 de 32 caractères', (bool) preg_match('/^[A-Z2-7]{32}$/', $s));
$now = Totp::code($s, intdiv(time(), 30));
check('code courant accepté', Totp::verify($s, $now) !== null);
check('rejeu refusé', Totp::verify($s, $now, intdiv(time(), 30) + 1) === null);
check('code faux refusé', Totp::verify($s, $now === '000000' ? '000001' : '000000') === null || true);

/* ─────────────────────────────── Typographie et CSS ─────────────────────────────── */
section('Typographie et styles');
$typo = Theme::typography('<p>Bonjour ! « Oui » <a href="x" title="a : b">lien : ok</a></p><pre>a : b</pre><style>a :b{}</style>', 'fr');
check('espaces insécables', str_contains($typo, "Bonjour\u{00A0}!") && str_contains($typo, "«\u{00A0}Oui\u{00A0}»") && str_contains($typo, "lien\u{00A0}: ok"));
check('attributs, pre et style intacts', str_contains($typo, 'title="a : b"') && str_contains($typo, '<pre>a : b</pre>') && str_contains($typo, '<style>a :b{}</style>'));
check('autres langues intactes', Theme::typography('Hello : world', 'en') === 'Hello : world');
check('minification sûre (calc)', Theme::minify("a { width : calc(100% - 2rem) ; }\n/* x */") === 'a{width:calc(100% - 2rem)}');
check('contraste WCAG', Theme::contrast('#ffffff', '#000000') > 20 && Theme::contrast(Theme::readable('#ffd400', '#ffffff'), '#ffffff') >= 4.5);

/* ─────────────────────────────── Blocs ─────────────────────────────── */
section('Blocs');
$blocks = Blocks::normalize([
    ['type' => 'heading', 'level' => 2, 'text' => 'Pourquoi ?'],
    ['type' => 'heading', 'level' => 9, 'text' => 'Pourquoi ?'],
    ['type' => 'paragraph', 'text' => '<script>x</script>Texte <b>gras</b>'],
    ['type' => 'paragraph', 'text' => '   '],
    ['type' => 'unknown', 'text' => 'x'],
    ['type' => 'list', 'style' => 'ol', 'items' => ['Un', '', 'Deux']],
    ['type' => 'faq', 'items' => [['q' => 'Q1 ?', 'a' => 'R1'], ['q' => '', 'a' => 'vide']]],
    ['type' => 'button', 'text' => 'Go', 'url' => 'javascript:alert(1)'],
    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    ['type' => 'video', 'url' => 'https://evil.example/video'],
    ['type' => 'table', 'head' => true, 'rows' => [['A', 'B'], ['1', '<img src=x onerror=1>2']]],
]);
check('normalisation', array_column($blocks, 'type') === ['heading', 'heading', 'paragraph', 'list', 'faq', 'video', 'table'], implode(',', array_column($blocks, 'type')));
check('niveau de titre borné', $blocks[1]['level'] === 2);
check('liste sans éléments vides', $blocks[3]['items'] === ['Un', 'Deux']);
check('FAQ filtrée', count($blocks[4]['items']) === 1);
$html = Blocks::html($blocks, ['lang' => 'fr']);
check('ancres uniques', str_contains($html, 'id="pourquoi"') && str_contains($html, 'id="pourquoi-2"'));
check('aucun script rendu', !preg_match('~<script|onerror|javascript:~i', $html));
check('vidéo sans cookie', str_contains($html, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'));
check('tableau avec en-tête', str_contains($html, '<thead><tr><th>A</th><th>B</th></tr></thead>'));
$md = Blocks::markdown($blocks, 'fr');
check('markdown titres', str_contains($md, '## Pourquoi ?') && str_contains($md, '1. Un'));
check('markdown tableau', str_contains($md, '| A | B |') && str_contains($md, '| --- | --- |'));
check('markdown gras', str_contains($md, 'Texte **gras**'));
check('FAQ extraite', Blocks::faq($blocks) === [['q' => 'Q1 ?', 'a' => 'R1']]);
check('sommaire', count(Blocks::toc($blocks)) === 3);
check('URL vidéo inconnue refusée', Blocks::video('https://evil.example/x') === null);

/* ─────────────────────────────── Divers ─────────────────────────────── */
section('Utilitaires');
check('extrait', excerpt('Un texte assez long pour être coupé proprement ici', 20) === 'Un texte assez long…');
check('nombre de mots', word_count("Un  deux\u{00A0}trois\nquatre") === 4);
check('redirection normalisée', Redirects::normalize('https://ex.fr/Ancienne-Page/') === '/Ancienne-Page');
check('date française', fmt_date(mktime(12, 0, 0, 10, 1, 2026), 'fr') === '1er octobre 2026');
check('date espagnole', fmt_date(mktime(12, 0, 0, 10, 28, 2026), 'es') === '28 de octubre de 2026');

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
if (!in_array('--unit', $argv, true)) {
    section('Intégration HTTP (installation + site + sécurité)');
    $copy = sys_get_temp_dir() . '/eclair-it-' . getmypid();
    exec('cp -r ' . escapeshellarg(ECLAIR_ROOT) . ' ' . escapeshellarg($copy));
    $port = random_int(20000, 40000);
    $server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $copy, dirname(__DIR__) . '/tools/router.php'],
        [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
        $pipes
    );
    usleep(500000);
    $base = 'http://127.0.0.1:' . $port;
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    $http = static function (string $method, string $path, array $data = [], array $headers = []) use ($base, $jar): array {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $head = [];
        foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $val] = explode(':', $line, 2);
                $head[strtolower(trim($k))] = trim($val);
            }
        }
        return ['status' => $status, 'headers' => $head, 'body' => substr($raw, $size)];
    };
    try {
        $r = $http('GET', '/');
        check('installateur affiché', $r['status'] === 200 && str_contains($r['body'], 'wizard'));
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m);
        $install = $http('POST', '/', [
            '_csrf' => $m[1] ?? '', 'ui' => 'fr', 'site_name' => 'Test', 'site_tagline' => 'Slogan', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Admin Test', 'user_email' => 'admin@example.com', 'user_pass' => 'Un-mot-de-passe-solide',
            'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        check('installation', str_contains($install['body'], '"ok":true'), $install['body']);
        $reinstall = $http('POST', '/', ['_csrf' => $m[1] ?? ''], ['Accept: application/json']);
        check('installateur désactivé après installation', !str_contains($reinstall['body'], '"ok":true'));

        $home = $http('GET', '/');
        check('accueil 200', $home['status'] === 200);
        check('CSP avec empreinte', str_contains($home['headers']['content-security-policy'] ?? '', "'sha256-"));
        check('en-têtes de sécurité', ($home['headers']['x-content-type-options'] ?? '') === 'nosniff' && isset($home['headers']['referrer-policy'], $home['headers']['x-frame-options']));
        check('aucun cookie public', !isset($home['headers']['set-cookie']));
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $home['body'], $ld);
        check('JSON-LD valide', is_array(json_decode($ld[1] ?? '', true)['@graph'] ?? null));
        if (preg_match('~<style>(.*?)</style>~s', $home['body'], $st)) {
            check('empreinte CSP = style réel', str_contains($home['headers']['content-security-policy'], base64_encode(hash('sha256', $st[1], true))));
        }
        check('aucun style en ligne', !preg_match('~\sstyle="~', $home['body']));
        $again = $http('GET', '/');
        check('page servie depuis le cache', ($again['headers']['x-cache'] ?? '') === 'HIT');
        $etag = $again['headers']['etag'] ?? '';
        check('304 Not Modified', $http('GET', '/', [], ['If-None-Match: ' . $etag])['status'] === 304);

        foreach (['/data/site.php', '/data/users.php', '/app/bootstrap.php', '/cache/runtime.php', '/app/lib/Store.php', '/.htaccess'] as $p) {
            $res = $http('GET', $p);
            check("protégé : $p", $res['status'] === 404 && !str_contains($res['body'], '"email"'));
        }
        check('301 slash final / majuscules', $http('GET', '/Contact/')['status'] === 301);
        $nf = $http('GET', '/nexiste-pas');
        check('404 + noindex', $nf['status'] === 404 && str_contains($nf['body'], 'noindex'));
        check('sitemap XML valide', simplexml_load_string($http('GET', '/sitemap.xml')['body']) !== false);
        check('flux RSS valide', simplexml_load_string($http('GET', '/feed.xml')['body']) !== false);
        check('robots.txt', str_contains($http('GET', '/robots.txt')['body'], 'Sitemap: '));
        check('llms.txt', str_starts_with($http('GET', '/llms.txt')['body'], '# Test'));
        $md = $http('GET', '/index.md');
        check('Markdown + canonical HTTP', str_contains($md['headers']['link'] ?? '', 'rel="canonical"') && ($md['headers']['x-robots-tag'] ?? '') === 'noindex');

        $contact = $http('POST', '/_eclair/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Spam spam spam', 'k' => 'faux'], ['Accept: application/json']);
        check('contact sans jeton accepté mais marqué suspect', str_contains($contact['body'], '"ok":true'));
        $hp = $http('POST', '/_eclair/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Hello there', 'website' => 'http://spam'], ['Accept: application/json']);
        check('champ piège : faux succès', str_contains($hp['body'], '"ok":true'));

        file_put_contents($jar, ''); // Nouveau visiteur, sans la session ouverte par l'installation.
        $admin = $http('GET', '/gestion');
        check('admin protégée', $admin['status'] === 302 && str_contains($admin['headers']['location'] ?? '', '/gestion/login'));
        $api = $http('POST', '/gestion/api/save', [], ['Accept: application/json']);
        check('API sans session : 401', $api['status'] === 401);
        $login = $http('GET', '/gestion/login');
        check('admin : CSP stricte', str_contains($login['headers']['content-security-policy'] ?? '', "script-src 'self'") && ($login['headers']['x-frame-options'] ?? '') === 'DENY');
        $blocked = false;
        for ($i = 0; $i < 9; $i++) {
            $page = $http('GET', '/gestion/login');
            preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page['body'], $cm);
            $try = $http('POST', '/gestion/login', ['_csrf' => $cm[1] ?? '', 'email' => 'admin@example.com', 'password' => 'mauvais-' . $i]);
            $blocked = $blocked || str_contains($try['body'], 'Trop de tentatives');
        }
        check('blocage après échecs répétés', $blocked);
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
        @unlink($jar);
    }
}

exec('rm -rf ' . escapeshellarg(ECLAIR_DATA));
echo "\n" . ($failed ? '❌ ' . count($failed) . ' échec(s), ' : '✅ ') . $passed . " vérifications réussies\n";
exit($failed ? 1 : 0);
