<?php
/**
 * Compile les traductions de l'administration.
 *
 *   i18n/<langue>.php            source lisible : texte français => traduction
 *   eclair/app/lang/<langue>.php fichier livré : empreinte CRC32 du texte français => traduction
 *
 * Le texte français n'est plus recopié dans chaque langue : les fichiers livrés sont
 * deux fois plus légers, ce qui garde Éclair sous 1 Mo avec ses sept langues.
 *
 *   php tools/i18n.php          compile toutes les langues
 *   php tools/i18n.php --check  vérifie seulement que les fichiers livrés sont à jour
 */
declare(strict_types=1);

defined('ECLAIR') || define('ECLAIR', true);

/** Contenu du fichier livré pour une source i18n/<langue>.php. */
function eclair_i18n_compile(string $source): string
{
    $dict = include $source;
    $lang = basename($source, '.php');
    $out = "<?php\n/** Généré par tools/i18n.php depuis i18n/$lang.php : ne pas modifier à la main. */\n"
        . "defined('ECLAIR') || exit;\n\nreturn [\n";
    $seen = [];
    foreach ($dict as $fr => $text) {
        $key = hash('crc32b', (string) $fr);
        if (isset($seen[$key])) {
            throw new RuntimeException("Empreinte identique pour « {$seen[$key]} » et « $fr » : renomme l'un des deux textes.");
        }
        $seen[$key] = $fr;
        $out .= "'$key' => " . var_export((string) $text, true) . ",\n";
    }
    return $out . "];\n";
}

/** Compile (ou vérifie) toutes les langues ; renvoie l'état de chacune. */
function eclair_i18n_build(bool $check = false): array
{
    $root = dirname(__DIR__);
    $dir = $root . '/eclair/app/lang';
    if (!$check && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $status = [];
    foreach (glob($root . '/i18n/*.php') ?: [] as $source) {
        $lang = basename($source, '.php');
        $target = "$dir/$lang.php";
        $code = eclair_i18n_compile($source);
        if (is_file($target) && file_get_contents($target) === $code) {
            $status[$lang] = 'à jour';
        } elseif ($check) {
            $status[$lang] = 'périmé';
        } else {
            file_put_contents($target, $code);
            $status[$lang] = 'compilé';
        }
    }
    return $status;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $stale = false;
    foreach (eclair_i18n_build(in_array('--check', $argv, true)) as $lang => $state) {
        echo ($state === 'périmé' ? '✗' : '✓') . " $lang : $state\n";
        $stale = $stale || $state === 'périmé';
    }
    exit($stale ? 1 : 0);
}
