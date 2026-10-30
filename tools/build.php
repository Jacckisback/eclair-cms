<?php
/**
 * Prépare une version distribuable d'Éclair :
 *   1. calcule l'empreinte SHA-256 de chaque fichier (app/manifest.php, pour le contrôle d'intégrité) ;
 *   2. crée dist/eclair-cms-<version>.zip, prêt à téléverser sur un hébergement.
 * Dans l'archive, le PHP est compacté (php -w : ni commentaires ni espaces superflus) : même code, un quart plus léger.
 * Son manifeste porte les empreintes de ces fichiers compactés ; celui du dépôt, les empreintes des sources.
 *
 *   php tools/build.php
 *
 * Inclus par un autre script (les tests), ce fichier fournit seulement ses fonctions.
 */
declare(strict_types=1);

/** Fichiers livrés : le code, sans les données d'une installation locale ni un thème personnalisé (theme/). */
function eclair_distributable(string $root): array
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
        if ($keep && !str_starts_with($rel, 'theme/') && $rel !== 'app/manifest.php' && $rel !== 'eclair-config.php' && !str_ends_with($rel, '.DS_Store')) {
            $files[] = $rel;
        }
    }
    sort($files);
    return $files;
}

/** Écrit app/manifest.php (empreinte de chaque fichier livré, en source) et renvoie ce manifeste. */
function eclair_manifest(string $root, string $version): array
{
    $hashes = [];
    foreach (eclair_distributable($root) as $rel) {
        $hashes[$rel] = hash_file('sha256', $root . '/' . $rel);
    }
    $manifest = ['version' => $version, 'generated' => gmdate('c'), 'files' => $hashes];
    file_put_contents($root . '/app/manifest.php', "<?php exit; ?>\n" . json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $manifest;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}

$root = dirname(__DIR__) . '/eclair';
$dist = dirname(__DIR__) . '/dist';
preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/index.php'), $m);
$version = $m[1] ?? 'dev';

// Traductions de l'administration : les fichiers livrés sont recompilés depuis i18n/.
require __DIR__ . '/i18n.php';
echo '✓ Traductions : ' . count(eclair_i18n_build()) . " langues compilées (eclair/app/lang)\n";

$manifest = eclair_manifest($root, $version);
$files = array_keys($manifest['files']);
echo "✓ Empreintes : " . count($files) . " fichiers (app/manifest.php)\n";

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
    // Le modèle de configuration reste lisible : ses commentaires sont son mode d'emploi.
    $body = str_ends_with($rel, '.php') && $rel !== 'eclair-config.exemple.php'
        ? php_strip_whitespace($root . '/' . $rel) : (string) file_get_contents($root . '/' . $rel);
    $shipped[$rel] = hash('sha256', $body);
    $size += strlen($body);
    $zip->addFromString('eclair/' . $rel, $body);
}
$zipManifest = "<?php exit; ?>\n" . json_encode(array_replace($manifest, ['files' => $shipped]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
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

Mise à jour d'un site existant : supprime app/ et assets/, envoie ceux de cette version avec
index.php et .htaccess, puis vide le cache. Ne touche ni à data/, ni à media/, ni à ton thème (theme/).

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
