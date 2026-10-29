<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Markdown ↔ blocs, pour l'assistant IA : le texte rédigé en Markdown devient des blocs Éclair
 * (puis passe par Blocks::normalize et la liste blanche de Sanitize), et un contenu existant
 * est présenté au modèle en Markdown lisible.
 */
final class Markdown
{
    private const ALERTS = ['NOTE' => 'note', 'TIP' => 'tip', 'IMPORTANT' => 'info', 'INFO' => 'info', 'WARNING' => 'warning', 'CAUTION' => 'warning', 'SUCCESS' => 'success'];

    /** Markdown → blocs validés. Les images ne viennent que de la médiathèque (« media:ID »). */
    public static function toBlocks(string $md): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $md));
        $blocks = [];
        $para = [];
        $flush = static function () use (&$para, &$blocks): void {
            if ($para) {
                $html = '';
                foreach ($para as $i => $line) {
                    // Retour à la ligne forcé : deux espaces ou une barre oblique inverse en fin de ligne.
                    $hard = (bool) preg_match('/(?:[ \t]{2,}|\\\\)$/', $line);
                    $html .= self::inline(trim((string) preg_replace('/\\\\$/', '', $line))) . ($i < count($para) - 1 ? ($hard ? '<br>' : ' ') : '');
                }
                $blocks[] = ['type' => 'paragraph', 'text' => $html];
                $para = [];
            }
        };
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            $line = $lines[$i];
            $trim = trim($line);
            if ($trim === '') {
                $flush();
                continue;
            }
            // Bloc de code.
            if (preg_match('/^(```|~~~)\s*([\w+#-]*)/', $trim, $m)) {
                $flush();
                $code = [];
                for ($i++; $i < $n && !str_starts_with(trim($lines[$i]), $m[1]); $i++) {
                    $code[] = $lines[$i];
                }
                $blocks[] = ['type' => 'code', 'code' => implode("\n", $code), 'lang' => strtolower($m[2])];
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $trim, $m)) {
                $flush();
                $blocks[] = ['type' => 'heading', 'level' => min(4, max(2, strlen($m[1]))), 'text' => self::inline($m[2])];
                continue;
            }
            if (preg_match('/^([-*_])(\s*\1){2,}$/', $trim)) {
                $flush();
                $blocks[] = ['type' => 'divider'];
                continue;
            }
            if (preg_match('/^!\[([^\]]*)\]\(\s*([^)\s]+)(?:\s+"([^"]*)")?\s*\)$/', $trim, $m)) {
                $flush();
                $media = preg_match('/^media:([a-z0-9]{1,32})$/', $m[2], $id) && Media::get($id[1]) ? $id[1] : '';
                if ($media !== '') {
                    $blocks[] = ['type' => 'image', 'media' => $media, 'alt' => $m[1], 'caption' => self::inline($m[3] ?? ''), 'size' => 'normal'];
                }
                continue;
            }
            // Citation, ou encadré au format des alertes GitHub (« > [!TIP] Titre »).
            if (str_starts_with($trim, '>')) {
                $flush();
                $quote = [];
                for (; $i < $n && str_starts_with(trim($lines[$i]), '>'); $i++) {
                    $quote[] = (string) preg_replace('/^>\s?/', '', trim($lines[$i]));
                }
                $i--;
                if (preg_match('/^\[!(\w+)\]\s*(.*)$/', $quote[0], $m) && isset(self::ALERTS[strtoupper($m[1])])) {
                    array_shift($quote);
                    $blocks[] = ['type' => 'callout', 'variant' => self::ALERTS[strtoupper($m[1])], 'title' => trim(str_replace(['**', '__'], '', $m[2])),
                        'text' => self::lines($quote)];
                    continue;
                }
                $cite = '';
                $last = trim((string) end($quote));
                if (count($quote) > 1 && preg_match('/^(?:—|–|--|-)\s*(.+)$/u', $last, $m)) {
                    array_pop($quote);
                    $cite = trim(str_replace(['*', '_'], '', $m[1]));
                }
                $blocks[] = ['type' => 'quote', 'text' => self::lines($quote), 'cite' => $cite];
                continue;
            }
            if (preg_match('/^\s*([-*+]|\d{1,3}[.)])\s+/', $line, $m)) {
                $flush();
                $style = ctype_digit($m[1][0]) ? 'ol' : 'ul';
                $items = [];
                for (; $i < $n; $i++) {
                    $l = $lines[$i];
                    if (preg_match('/^\s*(?:[-*+]|\d{1,3}[.)])\s+(.*)$/', $l, $mm)) {
                        $items[] = $mm[1];
                    } elseif (trim($l) !== '' && $items && preg_match('/^\s{2,}\S/', $l)) {
                        $items[count($items) - 1] .= ' ' . trim($l);
                    } else {
                        break;
                    }
                }
                $i--;
                $blocks[] = ['type' => 'list', 'style' => $style, 'items' => array_map([self::class, 'inline'], $items)];
                continue;
            }
            if (str_starts_with($trim, '|') && $i + 1 < $n && preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', trim($lines[$i + 1]))) {
                $flush();
                $rows = [self::cells($trim)];
                for ($i += 2; $i < $n && str_starts_with(trim($lines[$i]), '|'); $i++) {
                    $rows[] = self::cells(trim($lines[$i]));
                }
                $i--;
                $blocks[] = ['type' => 'table', 'head' => true, 'caption' => '', 'rows' => $rows];
                continue;
            }
            $para[] = ltrim($line);
        }
        $flush();
        return Blocks::normalize($blocks);
    }

    /**
     * Markdown en ligne → HTML en ligne assaini (gras, italique, code, liens, barré, surligné, retours à la ligne).
     * Les entités (« &lt; ») sont lues comme en Markdown ; un lien vers le site lui-même redevient un chemin (« /contact »).
     */
    public static function inline(string $md): string
    {
        $keep = [];
        $hold = static function (string $html) use (&$keep): string {
            $keep[] = $html;
            return "\x01" . (count($keep) - 1) . "\x02";
        };
        $s = str_replace(["\x01", "\x02", "\r"], '', $md);
        $s = (string) preg_replace_callback('/`([^`\n]+)`/', static fn (array $m): string => $hold('<code>' . e($m[1]) . '</code>'), $s);
        // Adresse : parenthèses équilibrées acceptées (liens Wikipédia…), « javascript:alert(1) » compris en entier pour être rejeté.
        $site = rtrim((string) Settings::get('url'), '/');
        $s = (string) preg_replace_callback('/\[([^\]\n]+)\]\(\s*((?:[^()\s]|\([^()\s]*\))+)(?:\s+"[^"]*")?\s*\)/', static function (array $m) use ($hold, $site): string {
            $url = Sanitize::url($m[2]);
            if ($site !== '' && ($url === $site || str_starts_with($url, $site . '/'))) {
                $url = substr($url, strlen($site)) ?: '/';
            }
            return $url === '' ? $m[1] : $hold('<a href="' . e($url) . '">') . $m[1] . $hold('</a>');
        }, $s);
        // Paragraphes séparés et retours à la ligne forcés ; un simple retour à la ligne vaut une espace.
        $s = (string) preg_replace_callback('/[ \t]*\n[ \t]*\n\s*|(?:[ \t]{2,}|\\\\)\n[ \t]*/', static fn (array $m): string => $hold(substr_count($m[0], "\n") > 1 ? '<br><br>' : '<br>'), trim($s));
        $s = e(html_entity_decode(str_replace("\n", ' ', $s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $s = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*|__(?=\S)(.+?)(?<=\S)__/u', '<strong>$1$2</strong>', $s);
        $s = (string) preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])|(?<![_\w])_(?=\S)(.+?)(?<=\S)_(?![_\w])/u', '<em>$1$2</em>', $s);
        $s = (string) preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/u', '<s>$1</s>', $s);
        $s = (string) preg_replace('/==(?=\S)(.+?)(?<=\S)==/u', '<mark>$1</mark>', $s);
        $s = (string) preg_replace_callback("/\x01(\\d+)\x02/", static fn (array $m): string => $keep[(int) $m[1]], $s);
        return Sanitize::inline($s);
    }

    private static function lines(array $lines): string
    {
        return implode('<br>', array_filter(array_map(fn ($l) => self::inline(trim($l)), $lines), fn ($l) => $l !== ''));
    }

    private static function cells(string $row): array
    {
        $row = trim(trim($row), '|');
        $cells = preg_split('/(?<!\\\\)\|/', $row) ?: [];
        return array_map(fn ($c) => self::inline(str_replace('\|', '|', trim($c))), $cells);
    }

    /* ─────────────────────── Blocs → Markdown (lecture par le modèle) ─────────────────────── */

    /** Contenu présenté au modèle : texte en Markdown, images « media:ID », blocs spéciaux signalés entre crochets. */
    public static function fromBlocks(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $b) {
            $out[] = match ($b['type']) {
                'paragraph' => Blocks::md($b['text']),
                'heading' => str_repeat('#', $b['level']) . ' ' . Blocks::md($b['text']),
                'list' => implode("\n", array_map(fn ($li, $k) => ($b['style'] === 'ol' ? ($k + 1) . '. ' : '- ') . Blocks::md($li), $b['items'], array_keys($b['items']))),
                'quote' => '> ' . Blocks::md($b['text']) . ($b['cite'] !== '' ? "\n> — " . $b['cite'] : ''),
                'image' => '![' . $b['alt'] . '](media:' . $b['media'] . ')',
                'callout' => '> [!' . strtoupper(array_search($b['variant'], self::ALERTS, true) ?: 'NOTE') . '] ' . $b['title'] . "\n> " . Blocks::md($b['text']),
                'faq' => '[FAQ' . ($b['title'] !== '' ? ' : ' . $b['title'] : '') . "]\n" . implode("\n", array_map(fn ($q) => 'Q: ' . $q['q'] . "\nR: " . Blocks::md($q['a']), $b['items'])),
                'table' => implode("\n", array_map(fn ($r) => '| ' . implode(' | ', array_map([Blocks::class, 'md'], $r)) . ' |', $b['rows'])),
                'button' => '[' . t('Bouton') . ' : ' . $b['text'] . '](' . $b['url'] . ')',
                'video' => '[' . t('Vidéo') . '](' . $b['url'] . ')',
                'code' => "```" . $b['lang'] . "\n" . $b['code'] . "\n```",
                'divider' => '---',
                'hero' => '[' . t('Bannière') . '] # ' . $b['title'] . ($b['text'] !== '' ? "\n" . Blocks::md($b['text']) : '')
                    . ($b['button'] !== '' ? "\n[" . $b['button'] . '](' . $b['url'] . ')' : ''),
                'features' => '[' . t('Atouts') . ($b['title'] !== '' ? ' : ' . $b['title'] : '') . "]\n"
                    . implode("\n", array_map(fn ($f) => '- ' . trim($f['icon'] . ' ' . $f['title']) . ($f['text'] !== '' ? ' — ' . Blocks::md($f['text']) : ''), $b['items'])),
                'team' => '[' . t('Équipe') . ($b['title'] !== '' ? ' : ' . $b['title'] : '') . "]\n"
                    . implode("\n", array_map(fn ($p) => '- ' . $p['name'] . ($p['role'] !== '' ? ' — ' . $p['role'] : '') . ($p['bio'] !== '' ? ' : ' . Blocks::md($p['bio']) : '')
                        . ($p['media'] !== '' ? ' (photo media:' . $p['media'] . ')' : ''), $b['items'])),
                'posts' => '[' . t('Derniers articles') . ']',
                'contact' => '[' . t('Formulaire de contact') . ']',
                default => '',
            };
        }
        return trim(implode("\n\n", array_filter($out, fn ($s) => $s !== '')));
    }
}
