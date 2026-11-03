<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité du groupe B2 (1.10.3), inclus par tests/run.php : redirections enregistrées (adresses réservées,
 * plafonds, index et compteurs sans le verrou des données), exports Markdown sans balise active ni lien non vérifié,
 * plafonds de la médiathèque et des catégories, durcissements (adresses, CSS personnalisé, couleurs, images IA).
 */
declare(strict_types=1);

use Eclair\Blocks;
use Eclair\Categories;
use Eclair\Design;
use Eclair\Markdown;
use Eclair\Media;
use Eclair\Redirects;
use Eclair\Sanitize;
use Eclair\Store;
use Eclair\Theme;

/**
 * Constructions actives qu'un lecteur CommonMark verrait dans un export : HTML brut ou lien automatique, lien ou image
 * dont l'adresse n'est pas http(s), mailto, tel ou relative, définition de référence, attributs « {…} ». Lecture de
 * gauche à droite comme CommonMark : blocs de code clôturés, échappements, puis code en ligne, qui rendent le texte inerte.
 */
$b2Active = static function (string $md): array {
    $kept = [];
    $fence = '';
    foreach (explode("\n", $md) as $line) {
        if ($fence === '' && preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $m) && !($m[1][0] === '`' && str_contains($m[2], '`'))) {
            $fence = $m[1];
            continue;
        }
        if ($fence !== '') {
            if (preg_match('/^ {0,3}' . preg_quote($fence[0], '/') . '{' . strlen($fence) . ',}\s*$/', $line)) {
                $fence = '';
            }
            continue;
        }
        $kept[] = $line;
    }
    $s = implode("\n", $kept);
    $plain = '';
    for ($i = 0, $n = strlen($s); $i < $n;) {
        if ($s[$i] === '\\' && $i + 1 < $n && preg_match('/[!-\/:-@\[-`{-~]/', $s[$i + 1])) {
            $plain .= '_';
            $i += 2;
            continue;
        }
        if ($s[$i] === '`') {
            $run = strspn($s, '`', $i);
            for ($j = $i + $run; $j < $n; $j++) {
                if ($s[$j] === '`') {
                    $other = strspn($s, '`', $j);
                    if ($other === $run) {
                        break;
                    }
                    $j += $other - 1;
                }
            }
            if ($j < $n) {
                $plain .= ' ';
                $i = $j + $run;
            } else {
                $plain .= str_repeat('_', $run);
                $i += $run;
            }
            continue;
        }
        $plain .= $s[$i++];
    }
    $found = [];
    if (preg_match_all('~<[a-zA-Z/!?][^\n]{0,40}~', $plain, $m)) {
        $found = array_merge($found, $m[0]);
    }
    if (preg_match_all('~\]\(\s*<?([^)\s>]*)~', $plain, $m)) {
        foreach ($m[1] as $dest) {
            $probe = (string) preg_replace('/[\x00-\x20]+/', '', html_entity_decode(rawurldecode($dest), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (preg_match('~^([a-z][a-z0-9+.\-]*):~i', $probe, $s2) && !in_array(strtolower($s2[1]), ['http', 'https', 'mailto', 'tel'], true)) {
                $found[] = '](' . $dest;
            }
        }
    }
    if (preg_match_all('~^ {0,3}\[[^\]\n]+\]:.*$~m', $plain, $m)) {
        $found = array_merge($found, $m[0]);
    }
    if (preg_match_all('~!\[[^\]\n]*\]\((?![^)]*/media/)[^)\n]*\)~', $plain, $m)) {
        $found = array_merge($found, $m[0]);
    }
    if (preg_match_all('~\{[^}\n]{0,40}~', $plain, $m)) {
        $found = array_merge($found, $m[0]);
    }
    return $found;
};

/* ───────────── Redirections : adresses réservées à l'hébergement et à Éclair ───────────── */
section('Sécurité B2 : redirections enregistrées (adresses réservées, plafonds, index sans verrou)');
Store::write('redirects', []);
Redirects::add('/.well-known/acme-challenge/x', 'https://evil.example/x', false);
Redirects::add('https://ex.fr/.well-known/pki-validation/y.txt', 'https://evil.example/y', false);
Redirects::add('/.well-known/security.txt', 'https://evil.example/s', false, 302);
Redirects::add('/_eclair/mcp', 'https://evil.example/mcp', false);
Redirects::add('/__eclair/status', 'https://evil.example/d', false);
Redirects::add('/_eclair/edit/abc', 'https://evil.example/login', true);
Redirects::add('/ancienne-page', '/nouvelle-page', false);
Redirects::add('/_files/ugd/ancien.pdf', '/nouveau', false);
check('redirection : /.well-known/, /_eclair/ et /__eclair/ jamais enregistrés', Redirects::find('/.well-known/acme-challenge/x') === null
    && Redirects::find('/.well-known/pki-validation/y.txt') === null && Redirects::find('/.well-known/security.txt') === null
    && Redirects::find('/_eclair/mcp') === null && Redirects::find('/__eclair/status') === null && Redirects::find('/_eclair/edit/abc') === null);
check('redirection ordinaire conservée (y compris /_autre)', (Redirects::find('/ancienne-page')['to'] ?? '') === '/nouvelle-page'
    && (Redirects::find('/_files/ugd/ancien.pdf')['to'] ?? '') === '/nouveau');
$b2Allowed = method_exists(Redirects::class, 'allowed');
check('redirection : un administrateur peut rediriger /.well-known/change-password, jamais une validation de domaine',
    $b2Allowed && Redirects::allowed('/.well-known/change-password', true) && !Redirects::allowed('/.well-known/change-password', false)
    && !Redirects::allowed('/.well-known/acme-challenge/t', true) && !Redirects::allowed('/.well-known/PKI-Validation/t', true)
    && !Redirects::allowed('/_eclair/mcp', true) && !Redirects::allowed('/__eclair/ping', true) && Redirects::allowed('/_files/x', false));
// Entrées enregistrées avant la correction : inopérantes pour les visiteurs, mais listées et supprimables.
Store::write('redirects', array_merge(Redirects::all(), [
    ['from' => '/.well-known/pki-validation/legacy.txt', 'to' => 'https://evil.example/pki', 'code' => 301, 'auto' => false, 'hits' => 0, 'created' => time()],
    ['from' => '/.well-known/security.txt', 'to' => 'https://evil.example/s', 'code' => 302, 'auto' => false, 'hits' => 0, 'created' => time()],
    ['from' => '/_eclair/edit/abc', 'to' => 'https://evil.example/login', 'code' => 301, 'auto' => false, 'hits' => 0, 'created' => time()],
]));
$b2Lookup = method_exists(Redirects::class, 'lookup');
check('redirection : anciennes entrées réservées inopérantes pour les visiteurs', $b2Lookup && Redirects::lookup('/.well-known/pki-validation/legacy.txt') === null
    && Redirects::lookup('/.well-known/security.txt') === null && Redirects::lookup('/_eclair/edit/abc') === null
    && (Redirects::lookup('/ancienne-page')['to'] ?? '') === '/nouvelle-page');
check('redirection : anciennes entrées réservées signalées dans la liste', method_exists(Redirects::class, 'listing')
    && array_column(array_filter(Redirects::listing(), fn ($r) => !$r['active']), 'from') === ['/.well-known/pki-validation/legacy.txt', '/.well-known/security.txt', '/_eclair/edit/abc']);
Redirects::remove('/.well-known/pki-validation/legacy.txt');
Redirects::remove('/_eclair/edit/abc');
Redirects::remove('/.well-known/security.txt');
check('redirection : anciennes entrées réservées supprimables', Redirects::find('/.well-known/pki-validation/legacy.txt') === null
    && Redirects::find('/_eclair/edit/abc') === null && Redirects::find('/.well-known/security.txt') === null && count(Redirects::all()) === 2);
if ($b2Allowed) {
    Redirects::add('/.well-known/change-password', '/gestion/profile', false, 301, true);
}
check('redirection : /.well-known/change-password d’un administrateur appliquée', $b2Lookup && (Redirects::lookup('/.well-known/change-password')['to'] ?? '') === '/gestion/profile');

// Longueurs et nombre de redirections bornés ; une redirection automatique ne fait jamais échouer un enregistrement.
$b2Long = '/' . str_repeat('a', 1000);
Redirects::add($b2Long, '/ok', false);
Redirects::add('/court', '/' . str_repeat('b', 1000), false);
check('redirection : ancienne adresse et adresse interne trop longues refusées', Redirects::find($b2Long) === null && Redirects::find('/court') === null
    && defined(Redirects::class . '::MAX_LENGTH') && Redirects::MAX_LENGTH === 1000);
Redirects::add('/' . str_repeat('c', 999), '/ok', false);
check('redirection : adresse de 1 000 caractères acceptée', Redirects::find('/' . str_repeat('c', 999)) !== null);
$b2Max = defined(Redirects::class . '::MAX') ? Redirects::MAX : 5000;
$b2Rows = [];
for ($i = 0; $i < $b2Max; $i++) {
    $b2Rows[] = ['from' => '/plein-' . $i, 'to' => '/cible', 'code' => 301, 'auto' => $i % 2 === 0, 'hits' => 0, 'created' => 1000 + $i];
}
Store::write('redirects', $b2Rows);
$b2Manual = Redirects::add('/manuelle-en-trop', '/cible', false);
check('redirection : liste pleine, ajout à la main refusé', $b2Manual === false && Redirects::find('/manuelle-en-trop') === null && count(Redirects::all()) === $b2Max);
$b2Auto = Redirects::add('/auto-en-trop', '/cible', true);
check('redirection : liste pleine, la plus ancienne redirection automatique fait place', $b2Auto === true && count(Redirects::all()) === $b2Max
    && Redirects::find('/auto-en-trop') !== null && Redirects::find('/plein-0') === null && Redirects::find('/plein-1') !== null && Redirects::find('/plein-2') !== null);
check('redirection : l’index suit la liste (place faite, nouvelle entrée)', $b2Lookup && Redirects::lookup('/plein-0') === null
    && (Redirects::lookup('/auto-en-trop')['to'] ?? '') === '/cible' && (Redirects::lookup('/plein-4999')['to'] ?? '') === '/cible');
Store::write('redirects', array_map(fn ($r) => ['auto' => false] + $r, $b2Rows));
$b2Skip = null;
try {
    $b2Skip = Redirects::add('/auto-sans-place', '/cible', true);
} catch (Throwable $e) {
    $b2Skip = $e->getMessage();
}
check('redirection : liste pleine de redirections manuelles, l’automatique est omise sans erreur', $b2Skip === false && Redirects::find('/auto-sans-place') === null);
unset($b2Rows);

// Visiteurs : recherche par empreinte (la liste n'est pas relue) ; utilisations comptées sans réécrire la liste.
Store::write('redirects', []);
Redirects::add('/ancienne-offre', '/contact', false);
Redirects::add('/tarifs-2025', '/tarifs', true);
$b2Table = Store::file('redirects');
if ($b2Lookup) {
    Redirects::lookup('/ancienne-offre');
}
clearstatcache();
$b2Before = [fileinode($b2Table), filemtime($b2Table), filesize($b2Table)];
// La cible est changée dans la liste sans en changer ni la taille, ni l'inode, ni la date : la visite lit l'index, pas la liste.
$b2Raw = (string) file_get_contents($b2Table);
$b2Fp = fopen($b2Table, 'r+');
fwrite($b2Fp, str_replace('"/contact"', '"/xxxxxxx"', $b2Raw));
fclose($b2Fp);
touch($b2Table, $b2Before[1]);
Store::forget();
check('redirection : une visite lit sa fiche d’index, pas toute la liste', $b2Lookup && (Redirects::lookup('/ancienne-offre')['to'] ?? '') === '/contact'
    && Redirects::lookup('/adresse-inconnue') === null);
file_put_contents($b2Table, $b2Raw);
Store::forget();
check('redirection : liste modifiée par un autre outil, index reconstruit', $b2Lookup && (Redirects::lookup('/ancienne-offre')['to'] ?? '') === '/contact'
    && (Redirects::lookup('/tarifs-2025')['to'] ?? '') === '/tarifs');
clearstatcache();
$b2Before = [fileinode($b2Table), filemtime($b2Table), filesize($b2Table)];
Redirects::hit('/ancienne-offre');
Redirects::hit('/ancienne-offre');
clearstatcache();
check('redirection : une utilisation ne réécrit pas la liste', [fileinode($b2Table), filemtime($b2Table), filesize($b2Table)] === $b2Before
    && str_contains((string) file_get_contents($b2Table), '"hits": 0'));
$b2Hits = method_exists(Redirects::class, 'listing') ? array_column(Redirects::listing(), 'hits', 'from') : [];
check('redirection : utilisations comptées et affichées', ($b2Hits['/ancienne-offre'] ?? 0) === 2 && ($b2Hits['/tarifs-2025'] ?? -1) === 0);
$b2Files = array_merge(glob(ECLAIR_DATA . '/redirect-hits/*') ?: [], glob(ECLAIR_DATA . '/redirects-index/*') ?: []);
check('redirection : index et compteurs privés et protégés (0600, ligne de protection)', count($b2Files) >= 4
    && array_filter($b2Files, fn ($f) => !str_starts_with((string) file_get_contents($f), Store::GUARD) || (fileperms($f) & 0777) !== 0600) === []);
// Le verrou des données (connexion, contact, MCP, enregistrements) tenu ailleurs ne bloque ni la recherche ni le comptage.
$b2Lock = fopen(ECLAIR_DATA . '/.lock', 'c');
flock($b2Lock, LOCK_EX);
$b2Child = sys_get_temp_dir() . '/eclair-b2-child-' . getmypid() . '.php';
file_put_contents($b2Child, '<?php
define("ECLAIR", true); define("ECLAIR_VERSION", "test"); define("ECLAIR_ROOT", getenv("B2_ROOT")); define("ECLAIR_DATA", getenv("B2_DATA"));
define("ECLAIR_DEBUG", true); define("ECLAIR_TESTING", true);
spl_autoload_register(static function (string $c): void { $f = ECLAIR_ROOT . "/app/lib/" . substr($c, 7) . ".php"; if (is_file($f)) { require $f; } });
require ECLAIR_ROOT . "/app/lib/helpers.php";
$r = method_exists(Eclair\Redirects::class, "lookup") ? Eclair\Redirects::lookup("/ancienne-offre") : Eclair\Redirects::find("/ancienne-offre");
Eclair\Redirects::hit("/ancienne-offre");
echo $r["to"] ?? "-";
');
$b2Proc = proc_open([PHP_BINARY, $b2Child], [['pipe', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $b2Pipes, null,
    ['B2_ROOT' => ECLAIR_ROOT, 'B2_DATA' => ECLAIR_DATA, 'PATH' => (string) getenv('PATH')]);
$b2Until = microtime(true) + 8;
while (proc_get_status($b2Proc)['running'] && microtime(true) < $b2Until) {
    usleep(20000);
}
$b2Blocked = proc_get_status($b2Proc)['running'];
if ($b2Blocked) {
    proc_terminate($b2Proc);
}
$b2Out = (string) stream_get_contents($b2Pipes[1]);
proc_close($b2Proc);
flock($b2Lock, LOCK_UN);
fclose($b2Lock);
@unlink($b2Child);
check('redirection : visite et comptage sans attendre le verrou des données', !$b2Blocked && $b2Out === '/contact', ($b2Blocked ? 'bloqué' : 'sortie : ') . $b2Out);

/* ───────────── Export Markdown : aucun texte ne devient balise, lien ou code actif ───────────── */
section('Sécurité B2 : exports Markdown (.md, llms.txt, llms-full.txt) sans balise active ni lien non vérifié');
$b2Md = Blocks::markdown(Blocks::normalize([
    ['type' => 'code', 'code' => "x = 1\n```\n\n<img src=x onerror=alert(51)>\n\n<script>alert(52)</script>\n\n```", 'lang' => 'txt'],
    ['type' => 'paragraph', 'text' => '\\`&lt;img src=x onerror=alert(53)&gt;`'],
    ['type' => 'paragraph', 'text' => '``a`b`` &lt;img src=x onerror=alert(54)&gt; `'],
    ['type' => 'paragraph', 'text' => '`a<br>b` &lt;img src=x onerror=alert(55)&gt; `'],
    ['type' => 'paragraph', 'text' => 'Voir [la doc](javascript:alert(56)) ici.'],
    ['type' => 'paragraph', 'text' => '[v05]: javascript:alert(57)'],
    ['type' => 'paragraph', 'text' => 'Lien par reference : [v05]'],
    ['type' => 'paragraph', 'text' => 'CONTROLE <a href="javascript:alert(58)">lien reel</a> et &lt;img src=x onerror=alert(59)&gt; hors code'],
]), 'fr');
check('markdown : charges de l’audit (51 à 59) sans construction active', $b2Active($b2Md) === [], implode(' | ', $b2Active($b2Md)) . "\n" . $b2Md);
check('markdown : clôture du code plus longue que ses accents graves', str_contains($b2Md, "````txt\nx = 1\n```\n\n<img src=x onerror=alert(51)>\n\n<script>alert(52)</script>\n\n```\n````"), $b2Md);
check('markdown : échappement, code multiple et coupé, lien et définition tapés restent du texte',
    str_contains($b2Md, '\\\\\\`&lt;img src=x onerror=alert(53)>\\`') && str_contains($b2Md, '\\`\\`a\\`b\\`\\` &lt;img src=x onerror=alert(54)> \\`')
    && str_contains($b2Md, "\\`a  \nb\\` &lt;img src=x onerror=alert(55)> \\`") && str_contains($b2Md, 'Voir \\[la doc\\](javascript:alert(56)) ici.')
    && str_contains($b2Md, '\\[v05\\]: javascript:alert(57)') && str_contains($b2Md, 'Lien par reference : \\[v05\\]'), $b2Md);
check('markdown : texte lisible (ponctuation ordinaire intacte)', Blocks::mdText('Prix : 12,50 € (TTC) ! *Promo* #1 — 50 % _vrai_ ? « oui »')
    === 'Prix : 12,50 € (TTC) ! *Promo* #1 — 50 % _vrai_ ? « oui »');
$b2Title = 'Verif v05 [titre](javascript:alert(60))';
$b2Summary = 'Resume [clic](javascript:alert(61))';
$b2Intro = Blocks::intro(Blocks::normalize([['type' => 'paragraph', 'text' => '\\`&lt;img src=x onerror=alert(62)&gt;` intro B']]));
$b2Head = "# " . Blocks::mdText($b2Title) . "\n\n> **L’essentiel** — " . Blocks::mdText($b2Summary) . "\n\n- [Page B](https://example.test/b.md): " . Blocks::mdText($b2Intro) . "\n";
check('markdown : titre, résumé et note de llms.txt (60 à 62) sans construction active', $b2Active($b2Head) === [], implode(' | ', $b2Active($b2Head)) . "\n" . $b2Head);
$b2Meta = method_exists(Blocks::class, 'mdMeta') ? "---\ntitle: " . Blocks::mdMeta($b2Title) . "\n---\n" : "---\ntitle: " . json_encode($b2Title, JSON_HEX_TAG) . "\n---\n";
check('markdown : en-tête de métadonnées sans lien, même lu comme du texte', $b2Active($b2Meta) === [] && !str_contains($b2Meta, '[')
    && json_decode(substr($b2Meta, 11, -5)) === $b2Title, $b2Meta);
$b2More = Blocks::markdown(Blocks::normalize([
    ['type' => 'paragraph', 'text' => '<a href="javascript\\:alert(70)">echappe</a> et <a href="https://ok.example/a b">ok</a>'],
    ['type' => 'paragraph', 'text' => 'Regarde!<a href="https://img.example/x.png">ici</a>'],
    ['type' => 'paragraph', 'text' => '<a href="https://ok.example">x](javascript:alert(71)) y</a>'],
    ['type' => 'paragraph', 'text' => '<code>&lt;img src=x onerror=alert(72)&gt;</code>{=html} et <code>a`b</code> et <code>`x</code>'],
    ['type' => 'paragraph', 'text' => '<a href="https://ok.example">lien</a>{onclick=alert(73)}'],
    ['type' => 'paragraph', 'text' => 'Ligne<br>~~~<br>suite'],
    ['type' => 'code', 'code' => "~~~\n<script>alert(74)</script>\n~~~", 'lang' => ''],
    ['type' => 'paragraph', 'text' => '<s>~x</s> et <code>a<br>&lt;script&gt;alert(75)&lt;/script&gt;</code>'],
    ['type' => 'paragraph', 'text' => '&lt;javascript:alert(76)&gt;'],
    ['type' => 'heading', 'level' => 2, 'text' => 'Titre {onclick=alert(78)}'],
    ['type' => 'table', 'head' => true, 'caption' => 'Legende [t](javascript:alert(79))', 'rows' => [['A', 'B'], ['<code>a|b</code>', '[c](javascript:alert(80))']]],
    ['type' => 'quote', 'text' => 'Citation', 'cite' => 'Auteur [a](javascript:alert(83))'],
    ['type' => 'paragraph', 'text' => '::: {onclick=alert(87)}'],
]), 'fr');
check('markdown : vrais liens, code en ligne, barré, légende, attributs, clôtures tapées (70 à 87) sans construction active',
    $b2Active($b2More) === [], implode(' | ', $b2Active($b2More)) . "\n" . $b2More);
check('markdown : adresse revalidée et encodée, « ! » avant un lien, code en ligne bien délimité',
    str_contains($b2More, '[echappe](javascript%5C:alert%2870%29)') && str_contains($b2More, '[ok](https://ok.example/a%20b)')
    && str_contains($b2More, 'Regarde\\![ici](https://img.example/x.png)') && str_contains($b2More, '[x\\](javascript:alert(71)) y](https://ok.example)')
    && str_contains($b2More, '``a`b``') && str_contains($b2More, '`` `x ``') && str_contains($b2More, '`a <script>alert(75)</script>`'), $b2More);
check('markdown : contrôle 1.10.2 conservé (vrai lien javascript retiré, code intact)', str_contains($b2Md, 'CONTROLE lien reel et &lt;img src=x onerror=alert(59)> hors code')
    && !preg_match('~(?<!\\\\)\]\(javascript~', $b2Md) && str_contains($b2More, '`<img src=x onerror=alert(72)>`'), $b2Md);

// Le Markdown lu par le modèle (assistant, MCP) garde le texte tel quel, mais ses blocs de code restent entiers.
$b2Code = ['type' => 'code', 'code' => "avant\n```\napres", 'lang' => 'md'];
$b2Model = Markdown::fromBlocks(Blocks::normalize([['type' => 'paragraph', 'text' => 'Prix [promo] ~10 € <code>x</code>'], $b2Code]));
$b2Back = Markdown::toBlocks($b2Model);
check('Markdown du modèle : texte non échappé, bloc de code entier à l’aller et au retour', str_contains($b2Model, 'Prix [promo] ~10 € `x`')
    && str_contains($b2Model, "````md\navant\n```\napres\n````") && ($b2Back[1]['code'] ?? '') === "avant\n```\napres" && count($b2Back) === 2, $b2Model);

/* ───────────── Médiathèque et catégories plafonnées ; durcissements ───────────── */
section('Sécurité B2 : médiathèque et catégories plafonnées, durcissements');
$b2Store = Store::read('media', []);
$b2MaxMedia = defined(Media::class . '::MAX_ITEMS') ? Media::MAX_ITEMS : 10000;
$b2Media = [];
for ($i = 0; $i < $b2MaxMedia; $i++) {
    $b2Media['m' . $i] = ['id' => 'm' . $i, 'file' => '2026/01/m' . $i, 'ext' => 'png', 'w' => 1, 'h' => 1, 'webp' => [], 'alt' => '', 'name' => 'm.png', 'created' => $i, 'user' => 'u'];
}
Store::write('media', $b2Media);
$b2Tmp = (string) tempnam(sys_get_temp_dir(), 'b2img');
file_put_contents($b2Tmp, 'pas une image');
$b2Error = '';
try {
    Media::upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $b2Tmp, 'name' => 'photo.png'], 'u');
} catch (RuntimeException $e) {
    $b2Error = $e->getMessage();
}
check('médiathèque pleine : envoi refusé avec un message clair', $b2Error === t('Limite de {n} images atteinte : supprime celles qui ne servent plus.', ['n' => $b2MaxMedia]), $b2Error);
Store::write('media', is_array($b2Store) ? $b2Store : []);
unset($b2Media);
if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
    // JPEG dont un segment de commentaire contient 101 marqueurs de passe : refusé avant tout décodage ou écriture.
    $b2Img = imagecreatetruecolor(4, 4);
    ob_start();
    imagejpeg($b2Img);
    $b2Jpeg = (string) ob_get_clean();
    $b2Com = str_repeat("\xFF\xDA", 101);
    file_put_contents($b2Tmp, substr($b2Jpeg, 0, 2) . "\xFF\xFE" . pack('n', strlen($b2Com) + 2) . $b2Com . substr($b2Jpeg, 2));
    $b2Error = '';
    try {
        Media::upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $b2Tmp, 'name' => 'passes.jpg'], 'u');
    } catch (RuntimeException $e) {
        $b2Error = $e->getMessage();
    }
    check('JPEG à plus de 100 passes refusé avant décodage', $b2Error === t('Image illisible ou corrompue.'), $b2Error);
}
@unlink($b2Tmp);
$b2Cats = Store::read('categories', []);
$b2MaxCats = defined(Categories::class . '::MAX') ? Categories::MAX : 1000;
$b2List = [];
for ($i = 0; $i < $b2MaxCats; $i++) {
    $b2List[] = ['id' => 'c' . $i, 'kind' => 'post', 'name' => 'C' . $i, 'slug' => 'c' . $i, 'description' => '', 'seo_title' => ''];
}
Store::write('categories', $b2List);
$b2Error = '';
try {
    Categories::save(['name' => 'Une de trop']);
} catch (RuntimeException $e) {
    $b2Error = $e->getMessage();
}
$b2Edited = Categories::save(['id' => 'c3', 'name' => 'C3 renommée']);
check('catégories : la 1 001e refusée avec un message clair, les existantes restent modifiables',
    $b2Error === t('Limite de {n} catégories atteinte : supprime celles qui ne servent plus.', ['n' => $b2MaxCats])
    && count(Categories::all()) === $b2MaxCats && $b2Edited['name'] === 'C3 renommée', $b2Error);
Store::write('categories', is_array($b2Cats) ? $b2Cats : []);
unset($b2List);

check('adresse « /\hôte » ou « \\\\hôte » refusée (lue « //hôte » par les navigateurs)', Sanitize::url('/\\evil.example') === '' && Sanitize::url('\\\\evil.example') === ''
    && Sanitize::url('\\/evil.example') === '' && Sanitize::url('//ok.example/x') === '//ok.example/x' && Sanitize::url('/a\\b') === '/a\\b');
check('CSS personnalisé : @import caché ou échappé retiré, échappements de contenu conservés',
    Theme::cleanCss('@im@import ;port url(https://evil.example/x.css); a{color:red}') === 'a{color:red}'
    && Theme::cleanCss('@\\69mport url(https://evil.example/x.css); a{color:red}') === 'a{color:red}'
    && Theme::cleanCss('a::after{content:"\\2014 mail@site\\201C"}') === 'a::after{content:"\\2014 mail@site\\201C"}');
$b2Dir = ['id' => 'x', 'name' => 'X', 'idea' => '', 'ground' => "#ffffff\n", 'surface' => '#f2f2f0', 'ink' => '#111111', 'muted' => '#5c5c5c', 'accent' => '#d7261e'];
check('direction artistique : couleur suivie d’un retour à la ligne refusée', Design::normalize($b2Dir) === null && Design::normalize(['ground' => '#ffffff'] + $b2Dir) !== null);
$b2Pin = new ReflectionMethod(Eclair\AiImage::class, 'pin');
$b2Pin->setAccessible(true);
check('image IA : hôte encodé ou international refusé (cURL le résoudrait autrement)', $b2Pin->invoke(null, 'https://pinned%2etest/x.png') === null
    && $b2Pin->invoke(null, 'https://pïnned.test/x.png') === null);
check('image IA : adresses non publiques refusées (CGNAT, tests, multidiffusion)', $b2Pin->invoke(null, 'https://100.100.100.200/x.png') === null
    && $b2Pin->invoke(null, 'https://100.64.0.1/x.png') === null && $b2Pin->invoke(null, 'https://198.18.0.1/x.png') === null
    && $b2Pin->invoke(null, 'https://192.0.2.10/x.png') === null && $b2Pin->invoke(null, 'https://224.0.0.1/x.png') === null
    && ($b2Pin->invoke(null, 'https://8.8.8.8/x.png')[CURLOPT_RESOLVE][0] ?? '') === '8.8.8.8:443:8.8.8.8');

/* ───────────── Parcours HTTP : éditeur, visiteurs, exports, médias ───────────── */
if (!in_array('--unit', $argv, true)) {
    section('Sécurité B2 : parcours HTTP (redirections réservées, compteurs, exports Markdown, médias, adresse du site)');
    [$copy, $port, $server] = serve('sec-b2');
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Atelier B2', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        preg_match('/"csrf":"([a-f0-9]+)"/', $c->req('GET', '/gestion')['body'], $m);
        $csrf = $m[1] ?? '';
        $api = static function (object $who, string $token, string $path, array $data): array {
            $res = $who->req('POST', '/gestion/' . $path, (string) json_encode($data), ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $token]);
            return $res + ['json' => json_decode($res['body'], true)];
        };
        $redirect = static function (object $who, array $fields): array {
            $page = $who->req('GET', '/gestion/redirects');
            $who->req('POST', '/gestion/redirects', $who->token($page['body']) + $fields);
            preg_match('~data-flash>.*?<p>(.*?)</p>~s', $who->req('GET', '/gestion/redirects')['body'], $m);
            return ['flash' => html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')];
        };
        $v = client($port);

        // Rédacteur ajouté directement (comme les autres tests) : droits « redirections », pas « réglages ».
        $users = json_decode(substr((string) file_get_contents($copy . '/data/users.php'), strlen(Store::GUARD)), true);
        $users['edb2'] = ['id' => 'edb2', 'name' => 'Rédac B2', 'email' => 'redac-b2@example.com', 'role' => 'editor', 'pass' => password_hash('Mot-de-passe-redac-1', PASSWORD_DEFAULT),
            'ver' => 1, 'created' => time(), 'slug' => 'redac-b2', 'lang' => 'fr', 'must_change' => false, 'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []]];
        file_put_contents($copy . '/data/users.php', Store::GUARD . json_encode($users));
        $e = client($port);
        $e->login('redac-b2@example.com', 'Mot-de-passe-redac-1');
        check('rédacteur : réglages interdits, redirections permises', $e->req('GET', '/gestion/settings')['status'] === 403 && $e->req('GET', '/gestion/redirects')['status'] === 200);
        $taken = 'Cette adresse est déjà utilisée : choisis-en une autre.';
        $flashes = [];
        foreach (['/.well-known/acme-challenge/tok-b2', 'http://127.0.0.1:' . $port . '/.well-known/pki-validation/b2.txt', '/.well-known/security.txt', '/_eclair/mcp', '/_eclair/edit/abc', '/__eclair/status'] as $from) {
            $flashes[$from] = $redirect($e, ['from' => $from, 'to' => 'https://attacker.example/x', 'code' => '301'])['flash'];
        }
        check('rédacteur : adresses réservées refusées avec un message clair', array_unique(array_values($flashes)) === [$taken], json_encode($flashes, JSON_UNESCAPED_UNICODE));
        $anon = [];
        foreach (['/.well-known/acme-challenge/tok-b2', '/.well-known/pki-validation/b2.txt', '/.well-known/security.txt', '/_eclair/mcp', '/_eclair/edit/abc', '/__eclair/status'] as $p) {
            $res = $v->req('GET', $p);
            $anon[$p] = $res['status'] . ' ' . ($res['headers']['location'] ?? '');
        }
        check('visiteur : aucune redirection sur les adresses réservées', array_unique(array_values($anon)) === ['404 '], json_encode($anon));
        $ok = $redirect($e, ['from' => '/_files/ugd/ancienne-brochure.pdf', 'to' => '/contact', 'code' => '301'])['flash'];
        $res = $v->req('HEAD', '/_files/ugd/ancienne-brochure.pdf');
        check('rédacteur : ancienne adresse d’un autre outil toujours redirigeable', $ok === 'Redirection ajoutée.' && $res['status'] === 301 && str_ends_with($res['headers']['location'] ?? '', '/contact'), $ok);
        $adm = $redirect($c, ['from' => '/.well-known/acme-challenge/tok-b2', 'to' => 'https://attacker.example/x', 'code' => '301'])['flash'];
        $cp = $redirect($c, ['from' => '/.well-known/change-password', 'to' => '/gestion/profile', 'code' => '302'])['flash'];
        $res = $v->req('GET', '/.well-known/change-password');
        check('administrateur : validation de domaine refusée, /.well-known/change-password permis', $adm === $taken && $cp === 'Redirection ajoutée.'
            && $res['status'] === 302 && str_ends_with($res['headers']['location'] ?? '', '/gestion/profile'), $adm . ' | ' . $cp);
        $long = $redirect($e, ['from' => '/' . str_repeat('x', 1000), 'to' => '/contact', 'code' => '301'])['flash'];
        check('rédacteur : ancienne adresse trop longue refusée avec un message clair', $long === 'Adresse trop longue : 1000 caractères au maximum.', $long);

        // Anciennes entrées réservées (enregistrées avant la mise à jour) : inopérantes, signalées, supprimables.
        $table = $copy . '/data/redirects.php';
        $list = json_decode(substr((string) file_get_contents($table), strlen(Store::GUARD)), true);
        $list[] = ['from' => '/.well-known/pki-validation/legacy.txt', 'to' => 'https://attacker.example/pki', 'code' => 301, 'auto' => false, 'hits' => 3, 'created' => time()];
        file_put_contents($table, Store::GUARD . json_encode($list));
        $legacy = $v->req('GET', '/.well-known/pki-validation/legacy.txt');
        $screen = $c->req('GET', '/gestion/redirects')['body'];
        check('ancienne entrée réservée : 404 pour les visiteurs, signalée « Désactivée »', $legacy['status'] === 404
            && (bool) preg_match('~legacy\.txt</td>.*?Désactivée~s', $screen), (string) $legacy['status']);
        $redirect($c, ['action' => 'delete', 'from' => '/.well-known/pki-validation/legacy.txt']);
        check('ancienne entrée réservée : supprimable depuis l’écran', !str_contains((string) file_get_contents($table), 'legacy.txt'));

        // Une visite redirigée ne réécrit pas la liste ; l'écran compte les utilisations.
        clearstatcache();
        $stat = [fileinode($table), filemtime($table), filesize($table)];
        sleep(1);
        $v->req('HEAD', '/_files/ugd/ancienne-brochure.pdf');
        $v->req('GET', '/_files/ugd/ancienne-brochure.pdf');
        clearstatcache();
        $screen = $c->req('GET', '/gestion/redirects')['body'];
        check('visite redirigée : liste des redirections jamais réécrite', [fileinode($table), filemtime($table), filesize($table)] === $stat);
        check('écran des redirections : utilisations comptées', (bool) preg_match('~ancienne-brochure\.pdf</td>.*?<td class="small">3</td>~s', $screen));

        // Exports Markdown d'une page publiée par le rédacteur (charges de l'audit).
        preg_match('/"csrf":"([a-f0-9]+)"/', $e->req('GET', '/gestion')['body'], $em);
        $page = $api($e, $em[1] ?? '', 'api/save', ['type' => 'page', 'title' => 'Verif b2 [titre](javascript:alert(60))', 'slug' => 'verif-b2', 'status' => 'published',
            'summary' => 'Resume [clic](javascript:alert(61))', 'blocks' => [
                ['type' => 'paragraph', 'text' => 'Page de vérification (export Markdown).'],
                ['type' => 'code', 'code' => "x = 1\n```\n\n<img src=x onerror=alert(51)>\n\n<script>alert(52)</script>\n\n```", 'lang' => 'txt'],
                ['type' => 'paragraph', 'text' => '\\`&lt;img src=x onerror=alert(53)&gt;`'],
                ['type' => 'paragraph', 'text' => '``a`b`` &lt;img src=x onerror=alert(54)&gt; `'],
                ['type' => 'paragraph', 'text' => '`a<br>b` &lt;img src=x onerror=alert(55)&gt; `'],
                ['type' => 'paragraph', 'text' => 'Voir [la doc](javascript:alert(56)) ici.'],
                ['type' => 'paragraph', 'text' => '[v05]: javascript:alert(57)'],
                ['type' => 'paragraph', 'text' => 'Lien par reference : [v05]'],
            ]]);
        $api($e, $em[1] ?? '', 'api/save', ['type' => 'page', 'title' => 'Verif b2 bis', 'slug' => 'verif-b2-bis', 'status' => 'published',
            'blocks' => [['type' => 'paragraph', 'text' => '\\`&lt;img src=x onerror=alert(62)&gt;` intro B']]]);
        $exports = [];
        foreach (['/verif-b2.md', '/llms-full.txt', '/llms.txt'] as $p) {
            $res = $v->req('GET', $p);
            $exports[$p] = $res['status'] === 200 ? $res['body'] : '';
        }
        $found = array_map($b2Active, $exports);
        check('exports .md, llms-full.txt et llms.txt : aucune construction active (charges 51 à 62)', ($page['json']['ok'] ?? false) === true
            && $exports['/verif-b2.md'] !== '' && str_contains($exports['/llms-full.txt'], 'alert(62)') && str_contains($exports['/llms.txt'], 'alert(61)')
            && array_merge(...array_values($found)) === [], json_encode($found));
        check('export .md : en-tête de métadonnées sans crochet brut', str_contains($exports['/verif-b2.md'], 'title: "Verif b2 \u005btitre\u005d(javascript:alert(60))"'));
        $html = $v->req('GET', '/verif-b2')['body'];
        check('page publique : inchangée, sans balise active', str_contains($html, '[la doc](javascript:alert(56))') && !preg_match('~<img src=x|<script>alert~', $html));

        // Médias : un nom « x-og » ne prend jamais l'image de partage de « x » ; aucun fichier vide laissé.
        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            $upload = static function (string $name) use ($port, $c, $csrf): array {
                $img = imagecreatetruecolor(8, 8);
                $tmp = (string) tempnam(sys_get_temp_dir(), 'b2up');
                imagepng($img, $tmp);
                $ch = curl_init('http://127.0.0.1:' . $port . '/gestion/api/media/upload');
                curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_PROXY => '',
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $csrf], CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, 'image/png', $name)]]);
                $json = json_decode((string) curl_exec($ch), true);
                @unlink($tmp);
                return is_array($json) ? $json : [];
            };
            $first = $upload('photo-b2.png');
            $second = $upload('photo-b2-og.png');
            $empty = array_filter(glob($copy . '/media/*/*/*') ?: [], fn ($f) => is_file($f) && filesize($f) === 0 && !str_ends_with($f, 'index.html'));
            check('médias : « photo-b2-og » ne devient pas l’image de partage de « photo-b2 », aucun fichier vide',
                ($first['ok'] ?? false) && ($second['ok'] ?? false) && str_ends_with((string) ($second['media']['url'] ?? ''), '/photo-b2-og-2.png') && $empty === [],
                json_encode([$first['media']['url'] ?? $first, $second['media']['url'] ?? $second, array_values($empty)]));
        }

        // Adresse du site : ni identifiants, ni paramètres, ni ancre (la vérification par /__eclair/ping reste intacte).
        $general = $c->req('GET', '/gestion/settings?tab=general');
        $c->req('POST', '/gestion/settings', $c->token($general['body']) + ['tab' => 'general', 'name' => 'Atelier B2', 'lang' => 'fr', 'timezone' => 'Europe/Paris',
            'url' => 'http://moi:secret@127.0.0.1:' . $port . '/?x=1#ancre', 'blog_enabled' => '1', 'blog_base' => 'blog', 'blog_title' => 'Blog', 'per_page' => '10', 'search' => '1']);
        $site = json_decode(substr((string) file_get_contents($copy . '/data/site.php'), strlen(Store::GUARD)), true);
        check('adresse du site : seulement protocole, hôte, port et dossier', ($site['url'] ?? '') === 'http://127.0.0.1:' . $port, (string) ($site['url'] ?? ''));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
}
