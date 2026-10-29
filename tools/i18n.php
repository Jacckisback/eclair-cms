<?php
/**
 * Compile les traductions de l'administration.
 *
 *   i18n/<langue>.php                source lisible : texte français => traduction
 *   eclair/app/lang/<langue>.json.gz fichier livré : empreinte CRC32 du texte français => traduction, en JSON compressé
 *
 * Le texte français n'est pas recopié dans chaque langue et le fichier livré est compressé :
 * cinq fois plus léger que la source, ce qui garde Éclair sous 1 Mo avec ses sept langues.
 *
 *   php tools/i18n.php          compile toutes les langues
 *   php tools/i18n.php --check  vérifie seulement que les fichiers livrés sont à jour
 */
declare(strict_types=1);

defined('ECLAIR') || define('ECLAIR', true);

/** Contenu du fichier livré pour une source i18n/<langue>.php (identique d'une compilation à l'autre). */
function eclair_i18n_compile(string $source): string
{
    $dict = include $source;
    $map = [];
    $seen = [];
    foreach ($dict as $fr => $text) {
        $key = hash('crc32b', (string) $fr);
        if (isset($seen[$key])) {
            throw new RuntimeException("Empreinte identique pour « {$seen[$key]} » et « $fr » : renomme l'un des deux textes.");
        }
        $seen[$key] = $fr;
        $map[$key] = (string) $text;
    }
    return (string) gzencode((string) json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 9);
}

/**
 * Textes français de l'administration : appels t('…') du code et textes du JavaScript (AdminStrings).
 * Chacun doit figurer dans les sources i18n (vérifié par les tests).
 */
function eclair_i18n_texts(): array
{
    $root = dirname(__DIR__) . '/eclair/app';
    $decode = static fn (string $lit): string => $lit[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($lit, 1, -1)) : stripcslashes(substr($lit, 1, -1));
    $texts = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!str_ends_with((string) $file, '.php')) {
            continue;
        }
        $tokens = array_values(array_filter(token_get_all((string) file_get_contents((string) $file)), fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $js = str_ends_with((string) $file, 'AdminStrings.php');
        foreach ($tokens as $i => $t) {
            if ($js && is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i - 1] ?? null) !== '(') {
                $texts[$decode($t[1])] = true;
            } elseif (is_array($t) && $t[0] === T_STRING && $t[1] === 't' && ($tokens[$i + 1] ?? null) === '('
                && !(is_array($tokens[$i - 1] ?? null) && in_array($tokens[$i - 1][0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_FUNCTION], true))
                && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING) {
                $texts[$decode($tokens[$i + 2][1])] = true;
            }
        }
    }
    unset($texts['']);
    return array_keys($texts);
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
        $target = "$dir/$lang.json.gz";
        if (!$check && is_file("$dir/$lang.php")) {
            unlink("$dir/$lang.php"); // ancien format (Éclair 1.1)
        }
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
