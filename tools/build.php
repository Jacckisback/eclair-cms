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
 *   php tools/build.php
 *   ECLAIR_SIGNING_KEY=… php tools/build.php      (version publiée : annonce signée pour la mise à jour en un clic)
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/eclair';
$dist = dirname(__DIR__) . '/dist';
preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/index.php'), $m);
$version = $m[1] ?? 'dev';
require __DIR__ . '/minify.php';

// Clé de signature des annonces (dist/latest.json) : vérifiée avant toute écriture, pour ne jamais laisser une archive et
// une annonce qui ne se correspondent pas.
$secret = base64_decode((string) getenv('ECLAIR_SIGNING_KEY'), true);
$public = null;
if ((string) getenv('ECLAIR_SIGNING_KEY') !== '') {
    preg_match("~private const KEY = '([^']+)'~", (string) file_get_contents($root . '/app/lib/Update.php'), $k);
    $public = base64_decode($k[1] ?? '', true);
    if (!function_exists('sodium_crypto_sign_detached') || !is_string($secret) || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
        || !is_string($public) || !hash_equals($public, sodium_crypto_sign_publickey_from_secretkey($secret))) {
        fwrite(STDERR, "✗ ECLAIR_SIGNING_KEY ne correspond pas à la clé publique d'app/lib/Update.php : rien n'a été construit.\n");
        exit(1);
    }
}

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

// Traductions de l'administration : les fichiers livrés sont recompilés depuis i18n/.
require __DIR__ . '/i18n.php';
echo '✓ Traductions : ' . count(eclair_i18n_build()) . " langues compilées (eclair/app/lang)\n";

$files = distributable($root);
$hashes = [];
foreach ($files as $rel) {
    $hashes[$rel] = substr((string) hash_file('sha256', $root . '/' . $rel), 0, 32);
}
$manifest = ['version' => $version, 'generated' => gmdate('c'), 'files' => $hashes];
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

/*
 * Annonce de la mise à jour en un clic (dist/latest.json), signée Ed25519 avec la clé privée du mainteneur.
 * Cette clé n'est jamais dans le dépôt : variable d'environnement ECLAIR_SIGNING_KEY (base64, 64 octets).
 * Les sites ne croient que la clé publique inscrite dans app/lib/Update.php : sans la clé privée, pas d'annonce.
 */
$latest = $dist . '/latest.json';
if ($public === null) {
    $announced = json_decode((string) (json_decode((string) @file_get_contents($latest), true)['payload'] ?? ''), true);
    echo is_array($announced) && ($announced['version'] ?? '') === $version && ($announced['sha256'] ?? '') !== hash_file('sha256', $zipFile)
        ? "⚠ dist/latest.json annonce une AUTRE archive {$version} : reconstruis avec ECLAIR_SIGNING_KEY avant de publier (sinon les sites refuseront le téléchargement).\n"
        : "⚠ ECLAIR_SIGNING_KEY absente : dist/latest.json inchangé (les sites ne se verront pas proposer cette archive).\n";
} else {
    $payload = json_encode([
        'version' => $version,
        'date' => gmdate('Y-m-d'),
        'url' => 'https://raw.githubusercontent.com/Jacckisback/eclair-cms/main/dist/eclair-cms-' . $version . '.zip',
        'sha256' => hash_file('sha256', $zipFile),
        'size' => filesize($zipFile),
        'php' => '8.1',
        'notes' => 'https://github.com/Jacckisback/eclair-cms/releases/tag/' . $version,
    ], JSON_UNESCAPED_SLASHES);
    $signature = sodium_crypto_sign_detached($payload, $secret);
    sodium_memzero($secret);
    if (!sodium_crypto_sign_verify_detached($signature, $payload, $public)) {
        fwrite(STDERR, "✗ Signature invalide : annonce refusée.\n");
        exit(1);
    }
    file_put_contents($latest, json_encode(['payload' => $payload, 'signature' => base64_encode($signature)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "✓ Annonce signée : dist/latest.json (version {$version})\n";
}
