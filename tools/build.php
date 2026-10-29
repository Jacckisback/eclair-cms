<?php
/**
 * Prépare une version distribuable d'Éclair :
 *   1. calcule l'empreinte SHA-256 de chaque fichier (app/manifest.php, pour le contrôle d'intégrité) ;
 *   2. crée dist/eclair-cms-<version>.zip, prêt à téléverser sur un hébergement.
 *
 *   php tools/build.php
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/eclair';
$dist = dirname(__DIR__) . '/dist';
preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/index.php'), $m);
$version = $m[1] ?? 'dev';

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

$files = distributable($root);
$hashes = [];
foreach ($files as $rel) {
    $hashes[$rel] = hash_file('sha256', $root . '/' . $rel);
}
$manifest = ['version' => $version, 'generated' => gmdate('c'), 'files' => $hashes];
file_put_contents($root . '/app/manifest.php', "<?php exit; ?>\n" . json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
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
foreach (array_merge($files, ['app/manifest.php']) as $rel) {
    $zip->addFile($root . '/' . $rel, 'eclair/' . $rel);
}
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
printf("✓ Archive : %s (%s Ko)\n", $zipFile, round(filesize($zipFile) / 1024));
