<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Générateur de QR code autonome (ISO/IEC 18004) : mode octet, correction M,
 * versions 1 à 40, rendu SVG. Sert au QR code d'activation TOTP de l'admin.
 */
final class Qr
{
    /** Codewords de correction par bloc, niveau M, versions 1 à 40. */
    private const ECC = [
        10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26,
        26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28,
    ];

    /** Nombre de blocs, niveau M, versions 1 à 40. */
    private const BLOCKS = [
        1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
        17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49,
    ];

    /** SVG autonome : fond blanc, modules sombres en un seul chemin de segments horizontaux. */
    public static function svg(string $text, int $scale = 6, int $margin = 4): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $margin = max(0, $margin);
        $d = '';
        foreach ($m as $y => $row) {
            for ($x = 0; $x < $n; $x++) {
                if (!$row[$x]) {
                    continue;
                }
                $w = 1;
                while ($x + $w < $n && $row[$x + $w]) {
                    $w++;
                }
                $d .= sprintf('M%d,%dh%dv1h-%dz', $x + $margin, $y + $margin, $w, $w);
                $x += $w;
            }
        }
        $s = $n + 2 * $margin;
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d"'
            . ' role="img" aria-label="QR code" shape-rendering="crispEdges">'
            . '<rect width="%1$d" height="%1$d" fill="#fff"/><path fill="#000" d="%3$s"/></svg>',
            $s,
            $s * max(1, $scale),
            $d
        );
    }

    /**
     * Modules (true = sombre), sans zone de silence.
     *
     * @return list<list<bool>>
     * @throws \InvalidArgumentException si le texte ne tient pas en version 40
     */
    public static function matrix(string $text): array
    {
        [$ver, $words] = self::encode($text);
        $n = 17 + 4 * $ver;
        $m = array_fill(0, $n, array_fill(0, $n, null));

        // Repères et séparateurs
        foreach ([[3, 3], [3, $n - 4], [$n - 4, 3]] as [$cy, $cx]) {
            for ($y = max(0, $cy - 4); $y <= min($n - 1, $cy + 4); $y++) {
                for ($x = max(0, $cx - 4); $x <= min($n - 1, $cx + 4); $x++) {
                    $d = max(abs($y - $cy), abs($x - $cx));
                    $m[$y][$x] = (int) ($d !== 2 && $d !== 4);
                }
            }
        }
        // Alignement (hors repères), posé avant la synchro qu'il recoupe sans la contredire
        if ($ver > 1) {
            $a = intdiv($ver, 7) + 2;
            $step = intdiv($ver * 8 + $a * 3 + 5, $a * 4 - 4) * 2;
            $pos = [6];
            for ($i = 0; $i < $a - 1; $i++) {
                $pos[] = $n - 7 - $i * $step;
            }
            foreach ($pos as $cy) {
                foreach ($pos as $cx) {
                    if ($m[$cy][$cx] !== null) {
                        continue;
                    }
                    for ($y = -2; $y <= 2; $y++) {
                        for ($x = -2; $x <= 2; $x++) {
                            $m[$cy + $y][$cx + $x] = (int) (max(abs($y), abs($x)) !== 1);
                        }
                    }
                }
            }
        }
        // Synchro, module sombre, format (réservé ici, écrit par masque), version
        for ($i = 8; $i < $n - 8; $i++) {
            $m[6][$i] = $m[$i][6] = 1 - $i % 2;
        }
        $m[$n - 8][8] = 1;
        self::format($m, 0);
        if ($ver >= 7) {
            $r = $ver;
            for ($i = 0; $i < 12; $i++) {
                $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
            }
            $v = $ver << 12 | $r;
            for ($i = 0; $i < 18; $i++) {
                $m[intdiv($i, 3)][$n - 11 + $i % 3] = $m[$n - 11 + $i % 3][intdiv($i, 3)] = $v >> $i & 1;
            }
        }

        // Zigzag : colonnes doubles de droite à gauche (sauf la 6), montée et descente alternées
        $cells = $bits = [];
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($j = 0; $j < $n; $j++) {
                $y = ($right + 1) & 2 ? $j : $n - 1 - $j;
                foreach ([$right, $right - 1] as $x) {
                    if ($m[$y][$x] === null) {
                        $i = count($cells);
                        $cells[] = [$y, $x];
                        $bits[] = isset($words[$i >> 3]) ? $words[$i >> 3] >> (7 - ($i & 7)) & 1 : 0;
                    }
                }
            }
        }

        // Les 8 masques sont évalués, le moins pénalisé l'emporte
        $best = [];
        $min = PHP_INT_MAX;
        for ($k = 0; $k < 8; $k++) {
            $t = $m;
            foreach ($cells as $i => [$y, $x]) {
                $t[$y][$x] = $bits[$i] ^ (int) self::mask($k, $y, $x);
            }
            self::format($t, $k);
            $p = self::penalty($t);
            if ($p < $min) {
                [$min, $best] = [$p, $t];
            }
        }
        return array_map(static fn(array $row): array => array_map('boolval', $row), $best);
    }

    /** Plus petite version qui convient, et ses codewords (données + correction) entrelacés. */
    private static function encode(string $text): array
    {
        $len = strlen($text);
        for ($ver = 1; ; $ver++) {
            if ($ver > 40) {
                throw new \InvalidArgumentException('Texte trop long pour un QR code.');
            }
            // Codewords bruts : n² moins repères (3×64), synchro, alignements, format (2×15+1), version (2×18)
            $n = 17 + 4 * $ver;
            $a = $ver > 1 ? intdiv($ver, 7) + 2 : 0;
            $raw = $n * $n - 192 - 2 * ($n - 16) - ($a ? 25 * ($a * $a - 3) - 10 * ($a - 2) : 0) - 31;
            $raw = ($raw - ($ver >= 7 ? 36 : 0)) >> 3;
            $ecc = self::ECC[$ver - 1];
            $blocks = self::BLOCKS[$ver - 1];
            $cap = 8 * ($raw - $ecc * $blocks);
            $cc = $ver < 10 ? 8 : 16;
            if (4 + $cc + 8 * $len <= $cap) {
                break;
            }
        }

        // Mode octet, longueur, données, terminateur, alignement sur l'octet, octets de bourrage
        $bits = '0100' . sprintf("%0{$cc}b", $len);
        foreach (unpack('C*', $text) as $byte) {
            $bits .= sprintf('%08b', $byte);
        }
        $bits .= str_repeat('0', min(4, $cap - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        for ($pad = 0xEC; strlen($bits) < $cap; $pad = $pad === 0xEC ? 0x11 : 0xEC) {
            $bits .= sprintf('%08b', $pad);
        }
        $data = array_map('bindec', str_split($bits, 8));

        // Blocs courts puis longs (un octet de plus), correction par bloc, puis entrelacement
        $short = intdiv($raw, $blocks) - $ecc;
        $shorts = $blocks - $raw % $blocks;
        $dat = $cor = [];
        for ($i = 0, $o = 0; $i < $blocks; $i++) {
            $size = $short + ($i < $shorts ? 0 : 1);
            $dat[] = array_slice($data, $o, $size);
            $cor[] = self::rs($dat[$i], $ecc);
            $o += $size;
        }
        $out = [];
        foreach ([$dat, $cor] as $set) {
            for ($i = 0, $max = count(end($set)); $i < $max; $i++) {
                foreach ($set as $b) {
                    if (isset($b[$i])) {
                        $out[] = $b[$i];
                    }
                }
            }
        }
        return [$ver, $out];
    }

    /** Correction Reed-Solomon sur GF(256), polynôme primitif 0x11D. */
    private static function rs(array $data, int $n): array
    {
        static $exp = [], $log = [];
        if (!$exp) {
            for ($i = 0, $x = 1; $i < 255; $i++) {
                $exp[$i] = $exp[$i + 255] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
        }
        // Générateur ∏(x − αⁱ) pour i < n, coefficients par degré décroissant
        $g = [1];
        for ($i = 0; $i < $n; $i++) {
            $g[] = 0;
            for ($j = $i + 1; $j > 0; $j--) {
                if ($g[$j - 1]) {
                    $g[$j] ^= $exp[$log[$g[$j - 1]] + $i];
                }
            }
        }
        // Reste de data·xⁿ divisé par le générateur
        $r = array_fill(0, $n, 0);
        foreach ($data as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            if ($f) {
                for ($j = 0; $j < $n; $j++) {
                    if ($g[$j + 1]) {
                        $r[$j] ^= $exp[$log[$g[$j + 1]] + $log[$f]];
                    }
                }
            }
        }
        return $r;
    }

    /** Le masque $k inverse-t-il le module (ligne $y, colonne $x) ? */
    private static function mask(int $k, int $y, int $x): bool
    {
        return match ($k) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => $y * $x % 2 + $y * $x % 3 === 0,
            6 => ($y * $x % 2 + $y * $x % 3) % 2 === 0,
            default => (($y + $x) % 2 + $y * $x % 3) % 2 === 0,
        };
    }

    /** Format (niveau M = 00, masque) protégé par BCH(15,5), écrit en deux exemplaires. */
    private static function format(array &$m, int $mask): void
    {
        $n = count($m);
        $r = $mask;
        for ($i = 0; $i < 10; $i++) {
            $r = ($r << 1) ^ (($r >> 9) * 0x537);
        }
        $f = ($mask << 10 | $r) ^ 0x5412;
        for ($i = 0; $i < 15; $i++) {
            $b = $f >> $i & 1;
            if ($i < 8) {
                $m[$i < 6 ? $i : $i + 1][8] = $m[8][$n - 1 - $i] = $b;
            } else {
                $m[8][$i === 8 ? 7 : 14 - $i] = $m[$n - 15 + $i][8] = $b;
            }
        }
    }

    /** Pénalité d'un symbole masqué, règles N1 à N4. */
    private static function penalty(array $m): int
    {
        $n = count($m);
        $p = 0;
        foreach (array_merge($m, array_map(null, ...$m)) as $line) { // lignes puis colonnes
            $s = implode('', $line);
            preg_match_all('/0{5,}|1{5,}/', $s, $runs);
            foreach ($runs[0] as $run) {
                $p += strlen($run) - 2; // N1 : 3 + (longueur − 5)
            }
            $p += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101')); // N3
        }
        for ($y = 1; $y < $n; $y++) {
            for ($x = 1; $x < $n; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y - 1][$x] && $c === $m[$y][$x - 1] && $c === $m[$y - 1][$x - 1]) {
                    $p += 3; // N2 : carré 2×2 uni
                }
            }
        }
        $dark = array_sum(array_map('array_sum', $m));
        return $p + 10 * intdiv(abs(20 * $dark - 10 * $n * $n), $n * $n); // N4 : écart à 50 %, par pas de 5 %
    }
}
