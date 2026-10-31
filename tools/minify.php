<?php
/**
 * Allègement du JavaScript et du CSS de l'administration dans l'archive (tools/build.php), sans dépendance.
 * Prudent par construction : chaînes et expressions régulières recopiées telles quelles, retours à la ligne gardés
 * (l'insertion automatique des « ; » ne change jamais), et refus net de ce qui n'est pas géré (gabarits `${…}`).
 * Les fichiers publics du site (site.js, thèmes) ne passent jamais par ici : leur octet près est garanti.
 */
declare(strict_types=1);

/** JavaScript sans commentaires ni espaces inutiles. */
function eclair_minify_js(string $s): string
{
    $out = '';
    $n = strlen($s);
    $i = 0;
    $last = '';       // dernier caractère écrit
    $lastWord = '';   // dernier mot écrit (s'il est le dernier élément)
    $pending = '';    // séparation en attente : « », « » ou retour à la ligne
    $isWord = static fn (string $c): bool => $c !== '' && (ctype_alnum($c) || $c === '_' || $c === '$' || $c === '\\' || ord($c) >= 0x80);
    $emit = static function (string $tok) use (&$out, &$pending, &$last, &$lastWord, $isWord): void {
        if ($pending !== '' && $out !== '') {
            $c = $tok[0];
            if ($pending === "\n") {
                $out .= "\n";
            } elseif (($isWord($last) && $isWord($c)) || ($last === $c && ($c === '+' || $c === '-')) || ($last === '/' && ($c === '/' || $c === '*'))) {
                $out .= ' ';
            }
        }
        $pending = '';
        $out .= $tok;
        $last = substr($tok, -1);
        $lastWord = $isWord($tok[0]) ? $tok : '';
    };
    // Après ces caractères ou mots-clés, « / » ouvre une expression régulière ; sinon c'est une division.
    $regexAfter = '(,=:[!&|?{};+-*%<>~^';
    $keywords = ['return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await'];
    while ($i < $n) {
        $c = $s[$i];
        $next = $s[$i + 1] ?? '';
        if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
            $nl = $pending === "\n";
            while ($i < $n && strpbrk($s[$i], " \t\n\r") !== false) {
                $nl = $nl || $s[$i] === "\n";
                $i++;
            }
            $pending = $nl ? "\n" : ' ';
            continue;
        }
        if ($c === '/' && $next === '/') {
            $end = strpos($s, "\n", $i);
            $i = $end === false ? $n : $end;
            continue;
        }
        if ($c === '/' && $next === '*') {
            $end = strpos($s, '*/', $i + 2);
            if ($end === false) {
                throw new RuntimeException('Commentaire non fermé');
            }
            $pending = str_contains(substr($s, $i, $end - $i), "\n") || $pending === "\n" ? "\n" : ' ';
            $i = $end + 2;
            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $j = $i + 1;
            while ($j < $n && $s[$j] !== $c) {
                if ($s[$j] === '\\') {
                    $j++;
                } elseif ($c !== '`' && $s[$j] === "\n") {
                    throw new RuntimeException('Chaîne non fermée');
                } elseif ($c === '`' && $s[$j] === '$' && ($s[$j + 1] ?? '') === '{') {
                    throw new RuntimeException('Gabarit avec ${…} : non géré par le minifieur');
                }
                $j++;
            }
            $emit(substr($s, $i, $j + 1 - $i));
            $i = $j + 1;
            continue;
        }
        if ($c === '/' && ($out === '' || strpbrk($last, $regexAfter) !== false || in_array($lastWord, $keywords, true))) {
            $j = $i + 1;
            $class = false;
            while ($j < $n) {
                $d = $s[$j];
                if ($d === '\\') {
                    $j += 2;
                    continue;
                }
                if ($d === "\n") {
                    throw new RuntimeException('Expression régulière non fermée');
                }
                if ($class) {
                    $class = $d !== ']';
                } elseif ($d === '[') {
                    $class = true;
                } elseif ($d === '/') {
                    break;
                }
                $j++;
            }
            $j++;
            while ($j < $n && ctype_alpha($s[$j])) {
                $j++;
            }
            $emit(substr($s, $i, $j - $i));
            $i = $j;
            continue;
        }
        if ($isWord($c)) {
            $j = $i;
            while ($j < $n && $isWord($s[$j])) {
                $j++;
            }
            $emit(substr($s, $i, $j - $i));
            $i = $j;
            continue;
        }
        $emit($c);
        $i++;
    }
    return $out . "\n";
}

/** CSS sans commentaires ni espaces inutiles (les espaces entre valeurs et avant « : » restent : sélecteurs intacts). */
function eclair_minify_css(string $s): string
{
    $s = (string) preg_replace('~/\*.*?\*/~s', '', $s);
    // Les chaînes (polices, content) sont mises de côté pendant l'opération, puis remises telles quelles.
    $keep = [];
    $s = (string) preg_replace_callback('/"[^"\n]*"|\'[^\'\n]*\'/', static function (array $m) use (&$keep): string {
        $keep[] = $m[0];
        return "\x01" . (count($keep) - 1) . "\x02";
    }, $s);
    $s = (string) preg_replace('/\s+/', ' ', $s);
    $s = (string) preg_replace('/\s*([{};,>])\s*/', '$1', $s);
    $s = (string) preg_replace('/:\s+/', ':', $s);
    $s = trim(str_replace(';}', '}', $s));
    return (string) preg_replace_callback("/\x01(\\d+)\x02/", static fn (array $m): string => $keep[(int) $m[1]], $s) . "\n";
}
