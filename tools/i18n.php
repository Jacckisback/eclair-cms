<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
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

/**
 * Lit une source i18n/<langue>.php comme des données, sans jamais l'exécuter : seuls la ligne de protection et un tableau
 * de textes littéraux (« 'texte français' => 'traduction', ») sont acceptés. Une contribution de traduction ne peut donc
 * faire tourner aucun code, en particulier pendant la construction signée d'une version (tools/build.php).
 */
function eclair_i18n_load(string $source): array
{
    $tokens = token_get_all((string) file_get_contents($source));
    $where = static fn (mixed $t): string => basename($source) . (is_array($t) ? ', ligne ' . $t[2] : '');
    if (!is_array($tokens[0] ?? null) || $tokens[0][0] !== T_OPEN_TAG) {
        throw new RuntimeException('Traductions : ' . basename($source) . ' doit commencer par <?php.');
    }
    $code = array_values(array_filter(array_slice($tokens, 1), static fn (mixed $t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    // En-tête attendu, à l'identique : defined('ECLAIR') || exit; return [
    $head = [[T_STRING, 'defined'], '(', [T_CONSTANT_ENCAPSED_STRING, "'ECLAIR'"], ')', [T_BOOLEAN_OR, '||'], [T_EXIT, 'exit'], ';', [T_RETURN, 'return'], '['];
    foreach ($head as $i => $want) {
        $t = $code[$i] ?? null;
        $ok = is_array($want) ? is_array($t) && $t[0] === $want[0] && strtolower($t[1]) === strtolower($want[1]) : $t === $want;
        if (!$ok) {
            throw new RuntimeException('Traductions : en-tête inattendu dans ' . $where($t) . ' (attendu : defined(\'ECLAIR\') || exit; return [).');
        }
    }
    $dict = [];
    $n = count($code);
    for ($i = count($head); $i < $n;) {
        if ($code[$i] === ']') {
            if (($code[$i + 1] ?? null) !== ';' || $i + 2 !== $n) {
                throw new RuntimeException('Traductions : rien n’est permis après le tableau dans ' . $where($code[$i + 1] ?? null) . '.');
            }
            return $dict;
        }
        $key = $code[$i];
        $arrow = $code[$i + 1] ?? null;
        $value = $code[$i + 2] ?? null;
        $next = $code[$i + 3] ?? null;
        if (!is_array($key) || $key[0] !== T_CONSTANT_ENCAPSED_STRING || !is_array($arrow) || $arrow[0] !== T_DOUBLE_ARROW
            || !is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING || ($next !== ',' && $next !== ']')) {
            throw new RuntimeException('Traductions : seul « \'texte\' => \'traduction\', » est permis (' . $where(is_array($key) ? $key : $value) . ').');
        }
        $dict[eclair_i18n_literal($key[1], $where($key))] = eclair_i18n_literal($value[1], $where($value));
        $i += $next === ',' ? 4 : 3;
    }
    throw new RuntimeException('Traductions : tableau non refermé dans ' . basename($source) . '.');
}

/** Valeur d'un texte littéral PHP (entre apostrophes, ou entre guillemets sans variable), décodé comme PHP le fait. */
function eclair_i18n_literal(string $literal, string $where): string
{
    $body = substr($literal, 1, -1);
    if ($literal[0] === "'") {
        return (string) preg_replace_callback("/\\\\([\\\\'])/", static fn (array $m): string => $m[1], $body);
    }
    // Entre guillemets : le jeton T_CONSTANT_ENCAPSED_STRING garantit l'absence de variable ; seuls les échappements sont décodés.
    if ($literal[0] !== '"') {
        throw new RuntimeException('Traductions : texte illisible (' . $where . ').');
    }
    $simple = ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'];
    return (string) preg_replace_callback('/\\\\(?:([ntrvef\\\\$"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u\{([0-9A-Fa-f]+)\})/', static function (array $m) use ($simple, $where): string {
        if (($m[1] ?? '') !== '') {
            return $simple[$m[1]];
        }
        if (($m[2] ?? '') !== '') {
            return chr(octdec($m[2]) & 255);
        }
        if (($m[3] ?? '') !== '') {
            return chr((int) hexdec($m[3]));
        }
        $code = (int) hexdec($m[4]);
        $char = function_exists('mb_chr') ? mb_chr($code, 'UTF-8') : false;
        if (!is_string($char)) {
            throw new RuntimeException('Traductions : caractère \\u{' . $m[4] . '} invalide (' . $where . ').');
        }
        return $char;
    }, $body);
}

/**
 * Contenu du fichier livré pour une source i18n/<langue>.php (identique d'une compilation à l'autre) : $scope « core »
 * (app/lang/, sans les textes propres à une fonction livrée) ou l'identifiant d'une fonction (ses seuls textes).
 */
function eclair_i18n_compile(string $source, string $scope = 'core', ?array $scopes = null): string
{
    $dict = eclair_i18n_load($source);
    $scopes ??= eclair_i18n_scopes();
    $map = [];
    $seen = [];
    foreach ($dict as $fr => $text) {
        if ((eclair_i18n_owner((string) $fr, $scopes) ?? 'core') !== $scope) {
            continue;
        }
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
    return array_keys(eclair_i18n_scopes());
}

/**
 * Où sert chaque texte : texte => [portée => true], la portée étant « core » ou l'identifiant d'une fonction livrée
 * (app/bundled/<id>/). Le nom et les entrées de menu d'une fonction, affichés par le noyau, sont du noyau.
 */
function eclair_i18n_scopes(): array
{
    $root = dirname(__DIR__) . '/eclair/app';
    $decode = static fn (string $lit): string => $lit[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($lit, 1, -1)) : stripcslashes(substr($lit, 1, -1));
    $texts = [];
    foreach (glob($root . '/bundled/*/extension.json') ?: [] as $manifest) {
        $m = json_decode((string) file_get_contents($manifest), true) ?: [];
        foreach (array_merge([(string) ($m['name'] ?? '')], array_column((array) ($m['nav'] ?? []), 'label')) as $label) {
            $texts[(string) $label]['core'] = true;
        }
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!str_ends_with((string) $file, '.php')) {
            continue;
        }
        $scope = preg_match('~/bundled/([a-z0-9-]+)/~', str_replace('\\', '/', (string) $file), $sm) ? $sm[1] : 'core';
        $tokens = array_values(array_filter(token_get_all((string) file_get_contents((string) $file)), fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $js = str_ends_with((string) $file, 'AdminStrings.php');
        foreach ($tokens as $i => $t) {
            if ($js && is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i - 1] ?? null) !== '(') {
                $texts[$decode($t[1])][$scope] = true;
            } elseif (is_array($t) && $t[0] === T_STRING && $t[1] === 't' && ($tokens[$i + 1] ?? null) === '('
                && !(is_array($tokens[$i - 1] ?? null) && in_array($tokens[$i - 1][0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_FUNCTION], true))
                && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING) {
                $texts[$decode($tokens[$i + 2][1])][$scope] = true;
            }
        }
    }
    unset($texts['']);
    return $texts;
}

/** Fonction livrée à laquelle un texte appartient seule, ou null (texte du noyau, partagé, ou non trouvé dans le code). */
function eclair_i18n_owner(string $text, array $scopes): ?string
{
    $s = array_keys($scopes[$text] ?? []);
    return count($s) === 1 && $s[0] !== 'core' ? $s[0] : null;
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
    $scopes = eclair_i18n_scopes();
    $owners = array_values(array_unique(array_filter(array_map(static fn (string $t): ?string => eclair_i18n_owner($t, $scopes), array_keys($scopes)))));
    foreach (glob($root . '/i18n/*.php') ?: [] as $source) {
        $lang = basename($source, '.php');
        if (!$check && is_file("$dir/$lang.php")) {
            unlink("$dir/$lang.php"); // ancien format (Éclair 1.1)
        }
        $targets = ["$dir/$lang.json.gz" => 'core'];
        foreach (glob($root . '/eclair/app/bundled/*', GLOB_ONLYDIR) ?: [] as $b) {
            $id = basename($b);
            $file = "$b/lang/$lang.json.gz";
            if (in_array($id, $owners, true)) {
                $targets[$file] = $id;
            } elseif (is_file($file)) {
                $check ? $status[$lang] = 'périmé' : unlink($file); // fonction sans texte propre : fichier retiré
            }
        }
        foreach ($targets as $target => $scope) {
            $code = eclair_i18n_compile($source, $scope, $scopes);
            if (is_file($target) && file_get_contents($target) === $code) {
                $status[$lang] ??= 'à jour';
            } elseif ($check) {
                $status[$lang] = 'périmé';
            } else {
                is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
                file_put_contents($target, $code);
                $status[$lang] = 'compilé';
            }
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
