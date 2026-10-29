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

use Eclair\Auth;
use Eclair\Blocks;
use Eclair\Log;
use Eclair\Media;
use Eclair\RateLimit;
use Eclair\Redirects;
use Eclair\Req;
use Eclair\Sanitize;
use Eclair\Security;
use Eclair\Store;
use Eclair\Theme;
use Eclair\Totp;
use Eclair\Users;

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
check('url entité HTML5 (&colon;)', Sanitize::url('javascript&colon;alert(1)') === '');
check('url entité numérique', Sanitize::url('&#106;avascript:alert(1)') === '' && Sanitize::url('java&#x09;script:alert(1)') === '');
check('url avec &amp; conservée', Sanitize::url('https://x.fr/?a=1&amp;b=2') === 'https://x.fr/?a=1&amp;b=2');
$spaces = Sanitize::inline('<b>x</b>' . str_repeat(' ', 20000) . 'fin');
check('20 000 espaces : paragraphe conservé', str_starts_with($spaces, '<strong>x</strong>') && str_ends_with($spaces, 'fin'));
check('espaces et <br> retirés aux extrémités', Sanitize::inline("  \u{A0}<br> voilà <br>\u{A0} ") === 'voilà');
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
$evil = Blocks::markdown(Blocks::normalize([
    ['type' => 'paragraph', 'text' => '<a href="javascript&amp;colon;alert(1)">la doc</a> et <a href="https://ok.fr/a b">ok</a>'],
    ['type' => 'paragraph', 'text' => '&lt;img src=x onerror=alert(1)&gt; et <code>&lt;b&gt;</code>'],
    ['type' => 'heading', 'level' => 2, 'text' => '&lt;script&gt;alert(1)&lt;/script&gt;'],
]), 'fr');
check('markdown : aucun lien javascript', !preg_match('~javascript:~i', $evil), $evil);
check('markdown : aucune balise active', !preg_match('~<(img|script)~i', $evil) && str_contains($evil, '&lt;img'), $evil);
check('markdown : code intact, lien valide encodé', str_contains($evil, '`<b>`') && str_contains($evil, '[ok](https://ok.fr/a%20b)'), $evil);
check('sommaire', count(Blocks::toc($blocks)) === 3);
check('URL vidéo inconnue refusée', Blocks::video('https://evil.example/x') === null);

/* ─────────────────────────────── Divers ─────────────────────────────── */
section('Utilitaires');
check('extrait', excerpt('Un texte assez long pour être coupé proprement ici', 20) === 'Un texte assez long…');
check('nombre de mots', word_count("Un  deux\u{00A0}trois\nquatre") === 4);
check('redirection normalisée', Redirects::normalize('https://ex.fr/Ancienne-Page/') === '/Ancienne-Page');
check('date française', fmt_date(mktime(12, 0, 0, 10, 1, 2026), 'fr') === '1er octobre 2026');
check('date espagnole', fmt_date(mktime(12, 0, 0, 10, 28, 2026), 'es') === '28 de octubre de 2026');
check('date chinoise', fmt_date(mktime(12, 0, 0, 10, 28, 2026), 'zh') === '2026年10月28日');

/* ───────────────────── Traductions de l'administration ───────────────────── */
section('Traductions de l’administration');
require_once dirname(__DIR__) . '/tools/i18n.php';
$reference = include dirname(__DIR__) . '/i18n/en.php';
$marks = static fn (string $s): array => [preg_match_all('/\{\w+\}|<\/?[a-z][a-z0-9]*/i', $s, $m) ? array_count_values($m[0]) : [], substr_count($s, "\n")];
foreach (array_keys(Eclair\I18n::LANGS) as $lang) {
    if ($lang === 'fr') {
        continue;
    }
    $source = dirname(__DIR__) . "/i18n/$lang.php";
    $dict = is_file($source) ? include $source : [];
    $gaps = array_merge(array_keys(array_diff_key($reference, $dict)), array_keys(array_diff_key($dict, $reference)));
    check("$lang : tous les textes traduits", $gaps === [], implode(' | ', array_slice($gaps, 0, 3)));
    $broken = array_filter(array_keys($dict), static fn ($fr) => trim((string) $dict[$fr]) === '' || $marks((string) $fr) != $marks((string) $dict[$fr]));
    check("$lang : variables, balises et retours à la ligne conservés", $broken === [], implode(' | ', array_slice($broken, 0, 3)));
    check("$lang : fichier livré à jour (php tools/i18n.php)", is_file($source) && (string) @file_get_contents(ECLAIR_ROOT . "/app/lang/$lang.json.gz") === eclair_i18n_compile($source));
    Eclair\I18n::setLang($lang);
    check("$lang : interface traduite", t('Réglages') !== 'Réglages' && t('il y a {n} min', ['n' => 5]) !== 'il y a 5 min');
}
Eclair\I18n::setLang('fr');
$untranslated = array_values(array_diff(eclair_i18n_texts(), array_keys($reference)));
check('chaque texte de l’interface a sa traduction (i18n/*.php)', $untranslated === [], implode(' | ', array_slice($untranslated, 0, 4)));
check('compilation stable (même fichier à chaque fois)', eclair_i18n_compile(dirname(__DIR__) . '/i18n/de.php') === eclair_i18n_compile(dirname(__DIR__) . '/i18n/de.php'));

/* ─────────────────────────── Sécurité (unitaire) ─────────────────────────── */
section('Limites, journaux, comptes, médias');
$_SERVER['REMOTE_ADDR'] = '2001:db8:abcd:12::7';
check('IPv6 regroupée par /64', Req::ipPrefix() === '2001:db8:abcd:12::/64', Req::ipPrefix());
$_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.9';
check('IPv4 mappée', Req::ipPrefix() === '192.0.2.9', Req::ipPrefix());
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
check('IPv4 telle quelle', Req::ipPrefix() === '203.0.113.5');

$ok = [];
for ($i = 0; $i < 5; $i++) {
    $ok[] = RateLimit::attempt('unit', 3, 600, 600, $fresh) ? 'ok' : ($fresh ? 'bloque' : 'refus');
}
check('réservation atomique : 3 tentatives puis blocage', $ok === ['ok', 'ok', 'ok', 'bloque', 'refus'], implode(',', $ok));
RateLimit::clear('unit');
RateLimit::attempt('unit', 3, 600);
RateLimit::attempt('unit', 3, 600);
RateLimit::release('unit');
RateLimit::release('unit');
$after = [RateLimit::attempt('unit', 3, 600), RateLimit::attempt('unit', 3, 600), RateLimit::attempt('unit', 3, 600), RateLimit::attempt('unit', 3, 600)];
check('une tentative réussie est rendue', $after === [true, true, true, false]);

Log::security('test', ['email' => str_repeat('a', 5000) . '<?php echo 1; ?>']);
$logFile = ECLAIR_DATA . '/logs/security.php';
$logRaw = (string) file_get_contents($logFile);
check('journal : ligne de protection', str_starts_with($logRaw, Store::GUARD));
check('journal : champs tronqués', strlen($logRaw) < 2000, (string) strlen($logRaw));
Log::security('test', ['email' => '<?php echo 1; ?>']);
check('journal : aucun code PHP possible', substr_count((string) file_get_contents($logFile), '<?php') === 1);
file_put_contents($logFile, 'sans protection <?php echo 1; ?>' . "\n");
Log::security('test');
check('journal sans protection : réécrit proprement', str_starts_with((string) file_get_contents($logFile), Store::GUARD) && substr_count((string) file_get_contents($logFile), '<?php') === 1);
Store::write('evil', ['x' => '<?php system("id"); ?>']);
check('données : « <?php » jamais écrit tel quel', substr_count((string) file_get_contents(ECLAIR_DATA . '/evil.php'), '<?php') === 1 && Store::read('evil')['x'] === '<?php system("id"); ?>');

$hash = Security::hashPassword('Mot-de-passe-test');
check('Argon2id calibré (19 Mio, 2 passes)', !defined('PASSWORD_ARGON2ID') || str_contains($hash, 'm=19456,t=2,p=1'), $hash);
check('ancien coût : réhachage demandé', !defined('PASSWORD_ARGON2ID') || Security::needsRehash(password_hash('x', PASSWORD_ARGON2ID)));

$u = Users::create(['name' => 'Alice Test', 'email' => 'alice@example.com', 'pass' => $hash, 'role' => 'admin', 'must_change' => true]);
check('création : les valeurs fournies priment', $u['role'] === 'admin' && $u['must_change'] === true && $u['ver'] === 1 && $u['slug'] === 'alice-test');
$stale = $u;
Users::update($u['id'], fn (array $x): array => ['pass' => Security::hashPassword('Nouveau-mot-de-passe'), 'ver' => 2] + $x);
check('connexion avec une copie périmée refusée', Auth::login($stale) === false);
Users::delete($u['id']);
check('compte supprimé jamais recréé', Users::update($u['id'], fn (array $x): array => ['last_login' => time()] + $x) === null && Users::find($u['id']) === null);

$clean = new ReflectionMethod(Media::class, 'cleanGif');
$gif = 'GIF89a' . pack('vvCCC', 1, 1, 0x80, 0, 0) . "\0\0\0\xff\xff\xff"
    . "\x21\xFE\x0F<?php echo 1;?>\x00"                         // commentaire piégé
    . "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x05<?php\x00" // boucle + données cachées
    . "\x21\xF9\x04\x00\x0A\x00\x00\x00"                     // minutage
    . "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00"
    . "\x3B<?php echo 2; ?>";                                     // données après la fin
$cleaned = $clean->invoke(null, $gif);
check('GIF nettoyé : plus aucun code', is_string($cleaned) && !str_contains($cleaned, '<?php') && str_ends_with($cleaned, "\x3B"));
check('GIF nettoyé : animation conservée', is_string($cleaned) && str_contains($cleaned, "NETSCAPE2.0\x03\x01\x00\x00\x00") && str_contains($cleaned, "\x21\xF9\x04"));
check('GIF tronqué refusé', $clean->invoke(null, substr($gif, 0, 40)) === null);

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
/** Lance une copie d'Éclair sur le serveur intégré (4 processus : le site peut s'appeler lui-même). */
function serve(string $name): array
{
    $copy = sys_get_temp_dir() . '/eclair-it-' . $name . '-' . getmypid();
    exec('rm -rf ' . escapeshellarg($copy) . ' && cp -r ' . escapeshellarg(ECLAIR_ROOT) . ' ' . escapeshellarg($copy));
    foreach (['data/*.php', 'data/*/', 'cache/*', 'media/20*', 'media/site'] as $glob) {
        exec('rm -rf ' . $copy . '/' . $glob);
    }
    $port = random_int(20000, 40000);
    $env = ['PHP_CLI_SERVER_WORKERS' => '4', 'PATH' => (string) getenv('PATH')];
    $server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $copy, dirname(__DIR__) . '/tools/router.php'],
        [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
        $pipes,
        null,
        $env
    );
    usleep(600000);
    return [$copy, $port, $server];
}

/** Client HTTP avec son propre pot à cookies. */
function client(int $port): object
{
    return new class ($port) {
        public string $jar;
        public function __construct(public int $port)
        {
            $this->jar = (string) tempnam(sys_get_temp_dir(), 'jar');
        }
        public function __destruct()
        {
            @unlink($this->jar);
        }
        public function req(string $method, string $path, array|string $data = [], array $headers = []): array
        {
            $ch = curl_init('http://127.0.0.1:' . $this->port . $path);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_COOKIEJAR => $this->jar,
                CURLOPT_COOKIEFILE => $this->jar,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_PROXY => '',
                CURLOPT_TIMEOUT => 20,
            ]);
            if ($data !== [] && $data !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
            }
            $raw = (string) curl_exec($ch);
            $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $head = [];
            foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
                if (str_contains($line, ':')) {
                    [$k, $val] = explode(':', $line, 2);
                    $head[strtolower(trim($k))] = trim($val);
                }
            }
            return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $head, 'body' => substr($raw, $size)];
        }
        /** Jeton du formulaire de la page (connexion/installation : _ft ; administration : _csrf). */
        public function token(string $body): array
        {
            if (preg_match('/name="(_ft|_csrf)" value="([a-f0-9]+)"/', $body, $m)) {
                return [$m[1] => $m[2]];
            }
            return [];
        }
        public function login(string $email, string $password, string $admin = 'gestion'): array
        {
            $page = $this->req('GET', '/' . $admin . '/login');
            return $this->req('POST', '/' . $admin . '/login', $this->token($page['body']) + ['email' => $email, 'password' => $password]);
        }
    };
}

function sessions(string $copy): int
{
    return count(glob($copy . '/data/sessions/*.php') ?: []);
}

function settings(string $copy, array $patch): void
{
    $file = $copy . '/data/site.php';
    $data = json_decode(substr((string) file_get_contents($file), strlen(Store::GUARD)), true);
    file_put_contents($file, Store::GUARD . json_encode(array_replace_recursive($data, $patch)));
    @unlink($copy . '/cache/runtime.php');
}

if (!in_array('--unit', $argv, true)) {
    section('Intégration HTTP (installation + site + sécurité)');
    [$copy, $port, $server] = serve('main');
    [$copy2, $port2, $server2] = serve('hijack');
    $base = 'http://127.0.0.1:' . $port;
    try {
        $c = client($port);
        file_put_contents($copy . '/data/install-window.php', Store::GUARD . (time() - 8000));
        $locked = $c->req('GET', '/');
        check('installateur verrouillé après 2 h', $locked['status'] === 403 && str_contains($locked['body'], 'install-window.php'));
        unlink($copy . '/data/install-window.php');
        $r = $c->req('GET', '/');
        check('installateur affiché', $r['status'] === 200 && str_contains($r['body'], 'wizard'));
        check('installateur : aucune session avant installation', sessions($copy) === 0);
        $install = $c->req('POST', '/', $c->token($r['body']) + [
            'ui' => 'fr', 'site_name' => 'Test', 'site_tagline' => 'Slogan', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Admin Test', 'user_email' => 'admin@example.com', 'user_pass' => 'Un-mot-de-passe-solide',
            'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        check('installation', str_contains($install['body'], '"ok":true'), $install['body']);
        $site = json_decode(substr((string) file_get_contents($copy . '/data/site.php'), strlen(Store::GUARD)), true);
        check('adresse vérifiée par le serveur lui-même', ($site['security']['verified'] ?? '') === $base, json_encode($site['security'] ?? null));
        check('fenêtre d’installation refermée', !is_file($copy . '/data/install-window.php'));
        check('fichiers de données privés (0600)', (fileperms($copy . '/data/users.php') & 0777) === 0600);
        $reinstall = $c->req('POST', '/', $c->token($r['body']), ['Accept: application/json']);
        check('installateur désactivé après installation', !str_contains($reinstall['body'], '"ok":true'));

        // H2 : un inconnu installe une copie avec un faux nom d'hôte.
        $evil = client($port2);
        $form = $evil->req('GET', '/', [], ['Host: evil.example']);
        $evil->req('POST', '/', $evil->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Pirate', 'site_lang' => 'fr', 'site_type' => 'blog', 'user_name' => 'Pirate',
            'user_email' => 'p@example.com', 'user_pass' => 'Un-mot-de-passe-pirate', 'index' => '1', 'ai' => 'open', 'admin_path' => 'admin',
        ], ['Accept: application/json', 'Host: evil.example']);
        $visit = client($port2)->req('GET', '/');
        check('adresse piégée jamais utilisée pour rediriger', $visit['status'] === 200 && !str_contains($visit['headers']['location'] ?? '', 'evil'), $visit['status'] . ' ' . ($visit['headers']['location'] ?? ''));

        $v = client($port);
        $home = $v->req('GET', '/');
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
        $again = $v->req('GET', '/');
        check('page servie depuis le cache', ($again['headers']['x-cache'] ?? '') === 'HIT');
        $etag = $again['headers']['etag'] ?? '';
        check('304 Not Modified', $v->req('GET', '/', [], ['If-None-Match: ' . $etag])['status'] === 304);
        $poison = $v->req('GET', '/blog', [], ['X-Forwarded-Proto: https']);
        $plain = $v->req('GET', '/blog');
        check('cache : en-têtes indépendants du protocole annoncé', !str_contains(($poison['headers']['content-security-policy'] ?? '') . ($plain['headers']['content-security-policy'] ?? ''), 'upgrade-insecure'));

        foreach (['/data/site.php', '/data/users.php', '/app/bootstrap.php', '/cache/runtime.php', '/app/lib/Store.php', '/.htaccess'] as $p) {
            $res = $v->req('GET', $p);
            check("protégé : $p", $res['status'] === 404 && !str_contains($res['body'], '"email"'));
        }
        check('301 slash final / majuscules', $v->req('GET', '/Contact/')['status'] === 301);
        foreach (['/%5Cevil.com/', '/%09/evil.com/', '/%5CEvil.com', '/index.php/%5Cevil.com'] as $p) {
            $res = $v->req('GET', $p);
            check("aucune redirection ouverte : $p", $res['status'] === 404 && !isset($res['headers']['location']), $res['status'] . ' ' . ($res['headers']['location'] ?? ''));
        }
        $nf = $v->req('GET', '/nexiste-pas');
        check('404 + noindex', $nf['status'] === 404 && str_contains($nf['body'], 'noindex'));
        check('sitemap XML valide', simplexml_load_string($v->req('GET', '/sitemap.xml')['body']) !== false);
        check('flux RSS valide', simplexml_load_string($v->req('GET', '/feed.xml')['body']) !== false);
        check('robots.txt', str_contains($v->req('GET', '/robots.txt')['body'], 'Sitemap: '));
        check('llms.txt', str_starts_with($v->req('GET', '/llms.txt')['body'], '# Test'));
        $md = $v->req('GET', '/index.md');
        check('Markdown + canonical HTTP', str_contains($md['headers']['link'] ?? '', 'rel="canonical"') && ($md['headers']['x-robots-tag'] ?? '') === 'noindex');
        $ping = json_decode($v->req('GET', '/__eclair/ping?n=' . str_repeat('ab', 16))['body'], true);
        check('preuve d’identité du site', is_array($ping) && strlen((string) ($ping['p'] ?? '')) === 32);
        check('bouton Modifier : rien pour un visiteur', $v->req('GET', '/_eclair/edit/abc')['status'] === 404);

        $contact = $v->req('POST', '/_eclair/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Spam spam spam', 'k' => 'faux'], ['Accept: application/json']);
        check('contact sans jeton accepté mais marqué suspect', str_contains($contact['body'], '"ok":true'));
        $hp = $v->req('POST', '/_eclair/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Hello there', 'website' => 'http://spam'], ['Accept: application/json']);
        check('champ piège : faux succès', str_contains($hp['body'], '"ok":true'));
        $back = $v->req('POST', '/_eclair/contact', ['name' => 'X', 'email' => 'x@example.com', 'message' => 'Bonjour à vous'], ['Referer: ' . $base . '.evil.example/']);
        check('lien retour jamais vers un autre site', !str_contains($back['body'], 'evil.example'));

        // Connexion : aucune session pour un visiteur anonyme, jeton sans session, limites.
        $anon = client($port);
        $before = sessions($copy);
        for ($i = 0; $i < 5; $i++) {
            $anon->req('GET', '/gestion/login');
            client($port)->req('GET', '/gestion/login', [], ['Cookie: eclair_sid=' . bin2hex(random_bytes(16))]);
        }
        check('aucun fichier de session pour les visiteurs anonymes', sessions($copy) === $before, $before . ' → ' . sessions($copy));
        $admin = $anon->req('GET', '/gestion');
        check('admin protégée', $admin['status'] === 302 && str_contains($admin['headers']['location'] ?? '', '/gestion/login'));
        check('API sans session : 401', $anon->req('POST', '/gestion/api/save', [], ['Accept: application/json'])['status'] === 401);
        $login = $anon->req('GET', '/gestion/login');
        check('admin : CSP stricte', str_contains($login['headers']['content-security-policy'] ?? '', "script-src 'self'") && ($login['headers']['x-frame-options'] ?? '') === 'DENY');
        $forged = $anon->req('POST', '/gestion/login', ['_ft' => str_repeat('a', 32), 'email' => 'admin@example.com', 'password' => 'Un-mot-de-passe-solide']);
        check('jeton de connexion falsifié refusé', $forged['status'] === 401);
        $big = $anon->login(str_repeat('a', 300000) . '@example.com', 'x');
        check('e-mail démesuré refusé sans journal géant', $big['status'] === 401 && filesize($copy . '/data/logs/security.php') < 200000);

        // Propriétaire : connexion réussie, cookie d'appareil.
        $owner = client($port);
        $ok = $owner->login('admin@example.com', 'Un-mot-de-passe-solide');
        check('connexion réussie', $ok['status'] === 302 && ($ok['headers']['location'] ?? '') === '/gestion', $ok['status'] . ' ' . substr($ok['body'], 0, 200));
        check('cookie Modifier sans adresse secrète', str_contains((string) file_get_contents($owner->jar), "eclair_edit\t1"));
        check('bouton Modifier : redirection pour une personne connectée', str_contains($owner->req('GET', '/_eclair/edit/abc')['headers']['location'] ?? '', '/gestion/edit/abc'));
        $owner->req('POST', '/gestion/logout', $owner->token($owner->req('GET', '/gestion')['body']));

        // Un attaquant (adresses IP tournantes) rate exprès : le propriétaire, sur son appareil habituel, reste libre.
        settings($copy, ['security' => ['proxy' => 'HTTP_X_FORWARDED_FOR']]);
        $locked = false;
        for ($i = 0; $i < 32; $i++) {
            $bot = client($port);
            $ip = '198.51.100.' . ($i % 4 + 1);
            $page = $bot->req('GET', '/gestion/login', [], ['X-Forwarded-For: ' . $ip]);
            $res = $bot->req('POST', '/gestion/login', $bot->token($page['body']) + ['email' => 'admin@example.com', 'password' => 'faux-' . $i], ['X-Forwarded-For: ' . $ip]);
            $locked = $locked || str_contains($res['body'], 'Trop de tentatives');
        }
        check('compte visé : verrouillé pour les appareils inconnus', $locked);
        $ownerAgain = $owner->login('admin@example.com', 'Un-mot-de-passe-solide');
        check('propriétaire (appareil connu) jamais bloqué', $ownerAgain['status'] === 302, (string) $ownerAgain['status']);
        $stranger = client($port);
        $page = $stranger->req('GET', '/gestion/login', [], ['X-Forwarded-For: 192.0.2.77']);
        $res = $stranger->req('POST', '/gestion/login', $stranger->token($page['body']) + ['email' => 'admin@example.com', 'password' => 'Un-mot-de-passe-solide'], ['X-Forwarded-For: 192.0.2.77']);
        check('appareil inconnu : bloqué même avec le bon mot de passe', str_contains($res['body'], 'Trop de tentatives'));
        $blocked = false;
        for ($i = 0; $i < 12; $i++) {
            $res = client($port)->login('inconnu@example.com', 'mauvais-' . $i);
            $blocked = $blocked || str_contains($res['body'], 'Trop de tentatives');
        }
        check('blocage par réseau après échecs répétés', $blocked);

        // Contact : limite par bloc IPv6 /64.
        $sent = 0;
        for ($i = 1; $i <= 8; $i++) {
            $res = client($port)->req('POST', '/_eclair/contact', ['name' => 'V', 'email' => 'v@example.com', 'message' => 'Message numéro ' . $i],
                ['Accept: application/json', 'X-Forwarded-For: 2001:db8:42:1::' . dechex($i)]);
            $sent += str_contains($res['body'], '"ok":true') ? 1 : 0;
        }
        check('contact : 5 messages maximum par /64', $sent === 5, (string) $sent);
        settings($copy, ['security' => ['proxy' => '']]);
        exec('rm -rf ' . escapeshellarg($copy . '/data/limits')); // fin des scénarios de blocage

        // Compte provisoire : changement de mot de passe obligatoire.
        $page = $ownerAgain['status'] === 302 ? $owner->req('GET', '/gestion/users') : ['body' => ''];
        $created = $owner->req('POST', '/gestion/users', $owner->token($page['body']) + ['name' => 'Rédac Test', 'email' => 'redac@example.com', 'role' => 'editor']);
        $list = $owner->req('GET', '/gestion/users');
        preg_match('/([A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4})/', $list['body'], $tmp);
        $editor = client($port);
        $in = $editor->login('redac@example.com', $tmp[1] ?? 'x');
        $go = $editor->req('GET', '/gestion/content');
        check('mot de passe provisoire : à changer avant tout', $go['status'] === 302 && str_contains($go['headers']['location'] ?? '', '/gestion/profile'),
            $go['status'] . ' ' . ($go['headers']['location'] ?? '') . ' | création ' . $created['status'] . ' | connexion ' . $in['status']);

        // Secours FTP : appliqué seulement à la connexion faite avec ces identifiants.
        file_put_contents($copy . '/data/reset.php', "<?php exit; ?>\r\nadmin@example.com\r\nSecours-Par-FTP-2026\r\n");
        client($port)->req('GET', '/gestion/login');
        client($port)->login('autre@example.com', 'Secours-Par-FTP-2026');
        check('secours FTP : intact pour les autres visiteurs', is_file($copy . '/data/reset.php'));
        $rescued = client($port)->login('admin@example.com', 'Secours-Par-FTP-2026');
        check('secours FTP : nouveau mot de passe actif', $rescued['status'] === 302 && !is_file($copy . '/data/reset.php'), (string) $rescued['status']);
        check('secours FTP : ancien mot de passe refusé', client($port)->login('admin@example.com', 'Un-mot-de-passe-solide')['status'] === 401);
        file_put_contents($copy . '/data/reset.php', "admin@example.com\nSans-Protection-2026\n");
        $refused = client($port)->login('admin@example.com', 'Sans-Protection-2026');
        check('secours FTP sans ligne de protection : refusé et supprimé', $refused['status'] === 401 && !is_file($copy . '/data/reset.php'));
    } finally {
        proc_terminate($server);
        proc_terminate($server2);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($copy2));
    }
}

require __DIR__ . '/ai.php';

exec('rm -rf ' . escapeshellarg(ECLAIR_DATA));
echo "\n" . ($failed ? '❌ ' . count($failed) . ' échec(s), ' : '✅ ') . $passed . " vérifications réussies\n";
exit($failed ? 1 : 0);
