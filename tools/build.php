<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Prépare une version distribuable d'Éclair :
 *   1. calcule l'empreinte SHA-256 de chaque fichier (app/manifest.php, pour le contrôle d'intégrité) ;
 *   2. crée dist/eclair-cms-<version>.zip, prêt à téléverser sur un hébergement ;
 *   3. signe l'annonce de mise à jour dist/latest.json (clé privée du mainteneur : variable ECLAIR_SIGNING_KEY).
 * Dans l'archive, le PHP est compacté (php -w : ni commentaires ni espaces superflus), le HTML des vues de l'administration
 * perd son indentation puis ces vues sont compressées (app/admin/views/*.php.gz, lues par zlib), et le JavaScript et le CSS
 * de l'administration sont allégés (tools/minify.php) : même code, plus léger.
 * Les fichiers publics du site (site.js, thèmes, gabarits) restent à l'octet près : le rendu public ne change jamais.
 * Son manifeste porte les empreintes de ces fichiers allégés ; celui du dépôt, les empreintes des sources
 * (128 premiers bits du SHA-256, en JSON compact).
 *
 * L'annonce porte deux signatures de la même clé. La première, d'origine (champs payload et signature), couvre version,
 * date, adresse, empreinte, taille, PHP minimal et notes : les sites en 1.10.0 à 1.10.2 ne lisent qu'elle, et elle garde
 * exactement sa forme. La seconde (champ v2, lue depuis la 1.10.3) couvre les mêmes champs plus un numéro de série
 * strictement croissant et une date d'expiration : les sites refusent une annonce plus ancienne que la dernière reçue et
 * signalent une annonce périmée. Le canal doit donc être re-signé avant son échéance, même sans nouvelle version : une
 * commande suffit (--resign). Un retrait de version est une nouvelle annonce, au numéro plus grand (--resign=<version>).
 * La clé n'est jamais affichée, et la construction signée n'exécute aucune source de traduction (lues comme des données).
 *
 *   php tools/build.php
 *   ECLAIR_SIGNING_KEY=… php tools/build.php                   (version publiée : archive et annonce signée)
 *   ECLAIR_SIGNING_KEY=… php tools/build.php --resign          (annonce actuelle re-signée : nouveau numéro, nouvelle échéance)
 *   ECLAIR_SIGNING_KEY=… php tools/build.php --resign=1.10.3   (retrait : annonce de nouveau la 1.10.3, archive déjà dans dist/)
 */
declare(strict_types=1);

/**
 * Validité d'une annonce : 180 jours. Le mainteneur publie toutes les quelques semaines, chaque version repousse donc
 * l'échéance bien avant qu'elle n'arrive, et une longue pause (jusqu'à six mois) ne déclenche aucune alerte sur les sites.
 * Plus court, chaque pause alerterait tous les sites pour rien (et une alerte qui sonne souvent n'est plus lue) ; plus long,
 * un canal figé par un dépôt piraté resterait silencieux plus longtemps. Avant l'échéance, « --resign » suffit.
 */
const ECLAIR_ANNOUNCE_DAYS = 180;

$root = dirname(__DIR__) . '/eclair';
$dist = dirname(__DIR__) . '/dist';
preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/index.php'), $m);
$version = $m[1] ?? 'dev';

// --resign : annonce actuelle re-signée, sans rien construire ; --resign=<version> : cette version, déjà dans dist/, annoncée de nouveau.
$resign = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--resign') {
        $resign = '';
    } elseif (preg_match('/^--resign=(\d{1,3}\.\d{1,3}\.\d{1,3})$/', $arg, $r)) {
        $resign = $r[1];
    } else {
        // L'argument n'est pas recopié : ce pourrait être la clé, passée là par erreur.
        fwrite(STDERR, "✗ Option inconnue : seules --resign et --resign=<version> sont acceptées.\n");
        exit(1);
    }
}

// Clé de signature des annonces (dist/latest.json) : vérifiée avant toute écriture, pour ne jamais laisser une archive et
// une annonce qui ne se correspondent pas. Elle quitte aussitôt l'environnement (aucun programme lancé ne la reçoit).
$secret = base64_decode((string) getenv('ECLAIR_SIGNING_KEY'), true);
$signing = (string) getenv('ECLAIR_SIGNING_KEY') !== '';
putenv('ECLAIR_SIGNING_KEY');
unset($_ENV['ECLAIR_SIGNING_KEY'], $_SERVER['ECLAIR_SIGNING_KEY']);
$updateCode = (string) file_get_contents($root . '/app/lib/Update.php');
preg_match("~private const KEY = '([^']+)'~", $updateCode, $k);
preg_match("~private const CONTEXT = '([^']+)'~", $updateCode, $c);
$public = base64_decode($k[1] ?? '', true);
$context = (string) ($c[1] ?? '');
if (!is_string($public) || strlen($public) !== 32 || $context === '') {
    fwrite(STDERR, "✗ Clé publique ou contexte de signature introuvable dans app/lib/Update.php.\n");
    exit(1);
}
if ($resign !== null && !$signing) {
    fwrite(STDERR, "✗ --resign demande la clé du mainteneur (variable ECLAIR_SIGNING_KEY).\n");
    exit(1);
}
if ($signing && (!function_exists('sodium_crypto_sign_detached') || !is_string($secret) || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
    || !hash_equals($public, sodium_crypto_sign_publickey_from_secretkey($secret)))) {
    fwrite(STDERR, "✗ ECLAIR_SIGNING_KEY ne correspond pas à la clé publique d'app/lib/Update.php : rien n'a été construit.\n");
    exit(1);
}
if (!$signing) {
    $secret = null;
}

require __DIR__ . '/minify.php';
require __DIR__ . '/announce.php';

/** Vues de l'administration et de l'installation : l'indentation du HTML en début de ligne est retirée (jamais dans le PHP). */
function eclair_compact_view(string $code): string
{
    $out = '';
    foreach (token_get_all($code) as $t) {
        $text = is_array($t) ? $t[1] : $t;
        if (is_array($t) && $t[0] === T_INLINE_HTML) {
            $text = (string) preg_replace(str_ends_with($out, "\n") ? '/(^|(?<=\n))[ \t]+/' : '/(?<=\n)[ \t]+/', '', $text);
        }
        $out .= $text;
    }
    return $out;
}

/**
 * Application Google d'Éclair (sauvegardes Drive, client « TV et appareils à saisie limitée ») : ses identifiants sont posés
 * dans l'archive depuis l'environnement (ECLAIR_GOOGLE_CLIENT_ID, ECLAIR_GOOGLE_CLIENT_SECRET), jamais écrits dans le dépôt.
 */
function eclair_google(string $code): string
{
    $id = (string) getenv('ECLAIR_GOOGLE_CLIENT_ID');
    $secret = (string) getenv('ECLAIR_GOOGLE_CLIENT_SECRET');
    if (!preg_match('~^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$~', $id) || !preg_match('~^[A-Za-z0-9_-]{20,80}$~', $secret)) {
        return $code;
    }
    $out = str_replace(["const CLIENT_ID = ''", "const CLIENT_SECRET = ''"], ["const CLIENT_ID = '$id'", "const CLIENT_SECRET = '$secret'"], $code, $count);
    if ($count !== 2) {
        fwrite(STDERR, "✗ Identifiants Google : emplacement introuvable dans app/lib/Drive.php.\n");
        exit(1);
    }
    return $out;
}

/** Contenu livré d'un fichier de l'application. */
function shipped(string $root, string $rel): string
{
    $path = $root . '/' . $rel;
    $admin = str_starts_with($rel, 'assets/admin/');
    return match (true) {
        // Le modèle de configuration reste lisible : ses commentaires sont son mode d'emploi.
        $rel === 'eclair-config.exemple.php' => (string) file_get_contents($path),
        str_starts_with($rel, 'app/admin/views/') || $rel === 'app/install/view.php' => eclair_compact_view(php_strip_whitespace($path)),
        $rel === 'app/lib/Drive.php' => eclair_google(php_strip_whitespace($path)),
        str_ends_with($rel, '.php') => php_strip_whitespace($path),
        $admin && str_ends_with($rel, '.js') => eclair_minify_js((string) file_get_contents($path)),
        $admin && str_ends_with($rel, '.css') => eclair_minify_css((string) file_get_contents($path)),
        // Morceaux des directions artistiques : chacun allégé, leurs repères gardés (Design::css les découpe).
        $rel === 'app/theme/directions.css' => implode('', array_map(static fn (string $part): string => str_starts_with($part, '/*@') ? $part : eclair_minify_css($part),
            preg_split('~(/\*@(?:[a-z]+(?::[a-z]+)?)?\*/)~', (string) file_get_contents($path), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY))),
        default => (string) file_get_contents($path),
    };
}

/** Fichiers livrés : le code, sans les données d'une installation locale. */
function distributable(string $root): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $path => $info) {
        if (!$info->isFile()) {
            continue;
        }
        $rel = str_replace('\\', '/', substr((string) $path, strlen($root) + 1));
        $inStorage = preg_match('~^(data|cache|media)/~', $rel);
        $keep = !$inStorage || preg_match('~^(data|cache|media)/(\.htaccess|index\.html)$~', $rel) || $rel === 'cache/pages/index.html';
        if ($keep && $rel !== 'app/manifest.php' && $rel !== 'eclair-config.php' && !str_ends_with($rel, '.DS_Store')) {
            $files[] = $rel;
        }
    }
    sort($files);
    return $files;
}

/**
 * Commande Git dans le dépôt (sortie, ou null si Git manque ou échoue). Lancée sans la clé dans son environnement.
 */
function eclair_git(string $repo, array $args): ?string
{
    $env = getenv();
    unset($env['ECLAIR_SIGNING_KEY'], $env['ECLAIR_GOOGLE_CLIENT_SECRET']);
    $p = @proc_open(array_merge(['git', '-C', $repo], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($p)) {
        return null;
    }
    $out = (string) stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($p) === 0 ? $out : null;
}

/** Fichiers suivis par Git sous eclair/ (chemins relatifs à eclair/), ou null sans dépôt Git. */
function eclair_tracked(string $repo): ?array
{
    $out = eclair_git($repo, ['ls-files', '-z', '--', 'eclair']);
    if ($out === null) {
        return null;
    }
    $files = [];
    foreach (explode("\0", $out) as $path) {
        if (str_starts_with($path, 'eclair/')) {
            $files[] = substr($path, 7);
        }
    }
    return $files;
}

$latest = $dist . '/latest.json';
$current = is_file($latest) ? eclair_announce_read((string) file_get_contents($latest), $public, $context) : 'absente';

if ($resign === null) {
    $files = distributable($root);
    // Version publiée : seuls des fichiers suivis par Git entrent dans l'archive signée (jamais un fichier oublié dans le
    // dossier, comme une copie de eclair-config.php ou un script de débogage) ; le commit source est noté au manifeste.
    $commit = '';
    if ($signing) {
        $tracked = eclair_tracked(dirname(__DIR__));
        if ($tracked === null) {
            fwrite(STDERR, "✗ Version signée : dépôt Git introuvable (elle se construit depuis le dépôt). Rien n'a été construit.\n");
            exit(1);
        }
        $untracked = array_values(array_diff($files, $tracked));
        if ($untracked) {
            fwrite(STDERR, "✗ Fichiers non suivis par Git, refusés dans une version signée : " . implode(', ', array_slice($untracked, 0, 10))
                . (count($untracked) > 10 ? '…' : '') . "\n  Ajoute-les avec git add, ou retire-les. Rien n'a été construit.\n");
            exit(1);
        }
        $commit = trim((string) eclair_git(dirname(__DIR__), ['rev-parse', 'HEAD']));
    }

    // Traductions de l'administration : les fichiers livrés sont recompilés depuis i18n/ (lus comme des données, jamais exécutés).
    require __DIR__ . '/i18n.php';
    echo '✓ Traductions : ' . count(eclair_i18n_build()) . " langues compilées (eclair/app/lang)\n";

    $files = distributable($root);
    $hashes = [];
    foreach ($files as $rel) {
        $hashes[$rel] = substr((string) hash_file('sha256', $root . '/' . $rel), 0, 32);
    }
    $manifest = ['version' => $version, 'generated' => gmdate('c')] + (preg_match('/^[a-f0-9]{40,64}$/', $commit) ? ['commit' => $commit] : []) + ['files' => $hashes];
    file_put_contents($root . '/app/manifest.php', "<?php exit; ?>\n" . json_encode($manifest, JSON_UNESCAPED_SLASHES) . "\n");
    echo "✓ Empreintes : " . count($hashes) . " fichiers (app/manifest.php)\n";

    if (!class_exists('ZipArchive')) {
        exit("⚠ Extension zip absente : archive non créée.\n");
    }
    if (!is_dir($dist)) {
        mkdir($dist, 0755, true);
    }
    $zipFile = $dist . '/eclair-cms-' . $version . '.zip';
    @unlink($zipFile);
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE);
    $shipped = [];
    $size = 0;
    foreach ($files as $rel) {
        $body = shipped($root, $rel);
        // Vues de l'administration et de l'installation compressées (gzip, lues par zlib) : un tiers de leur poids. Le site public n'en a pas.
        if (preg_match('~^app/(admin/views/.+|install/view)\.php$~', $rel)) {
            $body = (string) gzencode($body, 9);
            $rel .= '.gz';
        }
        $shipped[$rel] = substr(hash('sha256', $body), 0, 32);
        $size += strlen($body);
        $zip->addFromString('eclair/' . $rel, $body);
    }
    $zipManifest = "<?php exit; ?>\n" . json_encode(array_replace($manifest, ['files' => $shipped]), JSON_UNESCAPED_SLASHES) . "\n";
    $zip->addFromString('eclair/app/manifest.php', $zipManifest);
    $size += strlen($zipManifest);
    foreach (['data', 'cache/pages', 'media'] as $dir) {
        $zip->addEmptyDir('eclair/' . $dir);
    }
    $zip->addFromString('LISEZ-MOI.txt', <<<TXT
⚡ Éclair CMS {$version}

1. Envoie le CONTENU du dossier « eclair » sur ton hébergement (dans public_html, www…),
   y compris les fichiers cachés .htaccess (active « afficher les fichiers cachés » dans ton logiciel FTP).
2. Ouvre l'adresse de ton site dans le navigateur : l'assistant d'installation démarre.
   Par sécurité, termine-le dans les 2 heures qui suivent la première visite
   (sinon : supprime data/install-window.php par FTP et recharge la page).
3. C'est tout. Documentation complète : https://github.com/Jacckisback/eclair-cms

Licence : GNU AGPL v3 seulement (LICENSE.txt). Historique de licence et licence commerciale : NOTICE.txt.

Mot de passe oublié et aucun autre administrateur ? Crée par FTP le fichier data/reset.php
avec ces trois lignes, puis connecte-toi dans les 24 h avec cet e-mail et ce nouveau mot de passe :
<?php exit; ?>
ton-adresse@email.fr
TonNouveauMotDePasse2026

TXT);
    // Licence (AGPL-3.0-only), avis de licence et mentions des tiers : ils accompagnent chaque copie distribuée.
    foreach (['LICENSE' => 'LICENSE.txt', 'NOTICE' => 'NOTICE.txt', 'THIRD-PARTY-NOTICES.txt' => 'THIRD-PARTY-NOTICES.txt'] as $source => $target) {
        if (is_file(dirname(__DIR__) . '/' . $source)) {
            $zip->addFile(dirname(__DIR__) . '/' . $source, $target);
        }
    }
    $zip->close();
    printf("✓ Archive : %s (%s Ko, %s octets une fois décompressée)\n", $zipFile, round(filesize($zipFile) / 1024), number_format($size, 0, ',', ' '));
    echo str_contains(shipped($root, 'app/lib/Drive.php'), "CLIENT_ID = ''")
        ? "ℹ ECLAIR_GOOGLE_CLIENT_ID / ECLAIR_GOOGLE_CLIENT_SECRET absents : pas d'application Google intégrée, chaque site crée la sienne (guide dans Sécurité › Google Drive).\n"
        : "✓ Application Google intégrée : Google Drive se connecte en un clic, sans créer d'application\n";
}

/*
 * Annonce de la mise à jour en un clic (dist/latest.json), signée Ed25519 avec la clé privée du mainteneur.
 * Cette clé n'est jamais dans le dépôt : variable d'environnement ECLAIR_SIGNING_KEY (base64, 64 octets).
 * Les sites ne croient que la clé publique inscrite dans app/lib/Update.php : sans la clé privée, pas d'annonce.
 */
if (!$signing) {
    $announced = json_decode((string) (json_decode((string) @file_get_contents($latest), true)['payload'] ?? ''), true);
    echo is_array($announced) && ($announced['version'] ?? '') === $version && ($announced['sha256'] ?? '') !== hash_file('sha256', $zipFile)
        ? "⚠ dist/latest.json annonce une AUTRE archive {$version} : reconstruis avec ECLAIR_SIGNING_KEY avant de publier (sinon les sites refuseront le téléchargement).\n"
        : "⚠ ECLAIR_SIGNING_KEY absente : dist/latest.json inchangé (les sites ne se verront pas proposer cette archive).\n";
    if (is_array($current) && $current['expires'] > 0 && $current['expires'] < time() + 30 * 86400) {
        echo '⚠ L’annonce de dist/latest.json ' . ($current['expires'] <= time() ? 'est périmée depuis le ' : 'expire le ') . gmdate('Y-m-d', $current['expires'])
            . " : re-signe-la (ECLAIR_SIGNING_KEY=… php tools/build.php --resign), sinon les sites signalent un canal périmé.\n";
    }
    exit(0);
}

// Numéro de série : strictement plus grand que celui de l'annonce actuelle (s'il est bien signé), et au moins l'heure.
if (is_string($current) && $current !== 'absente') {
    echo "⚠ dist/latest.json actuel refusé ({$current}) : son numéro de série n'est pas repris.\n";
}
$serial = max(time(), (is_array($current) ? $current['serial'] : 0) + 1);
$expires = time() + ECLAIR_ANNOUNCE_DAYS * 86400;
if ($resign === '') {
    // Re-signature : même version, même archive ; seule l'annonce d'origine déjà signée est gardée telle quelle.
    if (!is_array($current)) {
        fwrite(STDERR, "✗ --resign : dist/latest.json {$current}, rien à re-signer. Rien n'a été signé.\n");
        exit(1);
    }
    $fields = $current['fields'];
    $local = $dist . '/' . basename((string) parse_url((string) ($fields['url'] ?? ''), PHP_URL_PATH));
    if (is_file($local) && (hash_file('sha256', $local) !== ($fields['sha256'] ?? '') || filesize($local) !== ($fields['size'] ?? -1))) {
        fwrite(STDERR, "✗ --resign : " . basename($local) . " ne correspond plus à l'annonce (empreinte ou taille). Rien n'a été signé.\n");
        exit(1);
    }
    if (!is_file($local)) {
        echo "⚠ " . basename($local) . " absente de dist/ : vérifie qu'elle est bien en ligne à l'adresse annoncée.\n";
    }
    $channel = eclair_announce_sign($fields, $serial, $expires, (string) $secret, $context, $current['channel']);
} else {
    // Nouvelle version construite, ou version déjà dans dist/ annoncée de nouveau (retrait de la suivante).
    $announce = $resign ?? $version;
    $zipFile = $dist . '/eclair-cms-' . $announce . '.zip';
    $date = gmdate('Y-m-d');
    if ($resign !== null) {
        $check = new ZipArchive();
        $inside = $check->open($zipFile) === true ? json_decode(substr((string) $check->getFromName('eclair/app/manifest.php'), 15), true) : null;
        if (!is_array($inside) || ($inside['version'] ?? '') !== $announce) {
            fwrite(STDERR, "✗ --resign={$announce} : dist/eclair-cms-{$announce}.zip absente ou d'une autre version. Rien n'a été signé.\n");
            exit(1);
        }
        $check->close();
        // Date de publication d'origine : celle de la construction de l'archive.
        $date = preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($inside['generated'] ?? ''), $d) ? $d[0] : $date;
    }
    $channel = eclair_announce_sign([
        'version' => $announce,
        'date' => $date,
        'url' => 'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/eclair-cms-' . $announce . '.zip',
        'sha256' => hash_file('sha256', $zipFile),
        'size' => filesize($zipFile),
        'php' => '8.1',
        'notes' => 'https://github.com/Jacckisback/eclair-cms/releases/tag/' . $announce,
    ], $serial, $expires, (string) $secret, $context);
}
sodium_memzero($secret);
$json = eclair_announce_json($channel);
$verified = eclair_announce_read($json, $public, $context);
if (!is_array($verified) || $verified['serial'] !== $serial) {
    fwrite(STDERR, "✗ Signature invalide : annonce refusée.\n");
    exit(1);
}
file_put_contents($latest, $json);
printf("✓ Annonce signée : dist/latest.json (version %s, numéro %d, valable jusqu'au %s ; avant cette date : ECLAIR_SIGNING_KEY=… php tools/build.php --resign)\n",
    $verified['fields']['version'], $serial, gmdate('Y-m-d', $expires));
