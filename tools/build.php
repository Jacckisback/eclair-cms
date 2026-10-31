<?php
/**
 * Prépare une version distribuable d'Éclair :
 *   1. calcule l'empreinte SHA-256 de chaque fichier (app/manifest.php, pour le contrôle d'intégrité) ;
 *   2. crée dist/eclair-cms-<version>.zip, prêt à téléverser sur un hébergement.
 * Dans l'archive, le PHP est compacté (php -w : ni commentaires ni espaces superflus), le HTML des vues de l'administration
 * perd son indentation et le JavaScript et le CSS de l'administration sont allégés (tools/minify.php) : même code, plus léger.
 * Les fichiers publics du site (site.js, thèmes, gabarits) restent à l'octet près : le rendu public ne change jamais.
 * Son manifeste porte les empreintes de ces fichiers allégés ; celui du dépôt, les empreintes des sources
 * (128 premiers bits du SHA-256, en JSON compact).
 *
 *   php tools/build.php
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/eclair';
$dist = dirname(__DIR__) . '/dist';
preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/index.php'), $m);
$version = $m[1] ?? 'dev';
require __DIR__ . '/minify.php';

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

/** Contenu livré d'un fichier de l'application. */
function shipped(string $root, string $rel): string
{
    $path = $root . '/' . $rel;
    $admin = str_starts_with($rel, 'assets/admin/');
    return match (true) {
        // Le modèle de configuration reste lisible : ses commentaires sont son mode d'emploi.
        $rel === 'eclair-config.exemple.php' => (string) file_get_contents($path),
        str_starts_with($rel, 'app/admin/views/') || $rel === 'app/install/view.php' => eclair_compact_view(php_strip_whitespace($path)),
        str_ends_with($rel, '.php') => php_strip_whitespace($path),
        $admin && str_ends_with($rel, '.js') => eclair_minify_js((string) file_get_contents($path)),
        $admin && str_ends_with($rel, '.css') => eclair_minify_css((string) file_get_contents($path)),
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

Mot de passe oublié et aucun autre administrateur ? Crée par FTP le fichier data/reset.php
avec ces trois lignes, puis connecte-toi dans les 24 h avec cet e-mail et ce nouveau mot de passe :
<?php exit; ?>
ton-adresse@email.fr
TonNouveauMotDePasse2026

TXT);
// Licence MIT et mentions des tiers : elles accompagnent chaque copie distribuée.
foreach (['LICENSE' => 'LICENSE.txt', 'THIRD-PARTY-NOTICES.txt' => 'THIRD-PARTY-NOTICES.txt'] as $source => $target) {
    if (is_file(dirname(__DIR__) . '/' . $source)) {
        $zip->addFile(dirname(__DIR__) . '/' . $source, $target);
    }
}
$zip->close();
printf("✓ Archive : %s (%s Ko, %s octets une fois décompressée)\n", $zipFile, round(filesize($zipFile) / 1024), number_format($size, 0, ',', ' '));
