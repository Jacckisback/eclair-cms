<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Visites des robots d'IA : qui lit le site (ChatGPT, Claude, Perplexity, Gemini…), quoi, et pourquoi.
 *
 * index.php note chaque visite d'un robot connu, dès le chemin rapide (pages en cache comprises) : une seule expression
 * sur l'agent déclaré, puis une ligne « heure, robot, adresse » ajoutée au journal du mois (data/ai/AAAA-MM.php,
 * 4 Mo au plus). Rien n'est noté pour un visiteur humain, aucune adresse IP n'est gardée. L'identité est celle que le
 * robot déclare : un faux robot peut se faire passer pour un vrai (la page le dit).
 */
final class AiVisits
{
    /** Taille maximale du journal d'un mois (index.php cesse d'y écrire au-delà). */
    public const MAX = 4194304;

    /**
     * Robots connus : agent (en minuscules) => [nom, éditeur, type]. Types : « user » consultation demandée par une
     * personne (quelqu'un a interrogé l'IA, qui est allée lire la page : une citation en puissance), « search »
     * indexation pour un moteur de réponse, « train » collecte pour entraîner des modèles.
     */
    public const BOTS = [
        'chatgpt-user' => ['ChatGPT-User', 'OpenAI', 'user'],
        'claude-user' => ['Claude-User', 'Anthropic', 'user'],
        'perplexity-user' => ['Perplexity-User', 'Perplexity', 'user'],
        'mistralai-user' => ['MistralAI-User', 'Mistral AI', 'user'],
        'meta-externalfetcher' => ['Meta-ExternalFetcher', 'Meta', 'user'],
        'duckassistbot' => ['DuckAssistBot', 'DuckDuckGo', 'user'],
        'oai-searchbot' => ['OAI-SearchBot', 'OpenAI', 'search'],
        'claude-searchbot' => ['Claude-SearchBot', 'Anthropic', 'search'],
        'perplexitybot' => ['PerplexityBot', 'Perplexity', 'search'],
        'youbot' => ['YouBot', 'You.com', 'search'],
        'gptbot' => ['GPTBot', 'OpenAI', 'train'],
        'claudebot' => ['ClaudeBot', 'Anthropic', 'train'],
        'anthropic-ai' => ['anthropic-ai', 'Anthropic', 'train'],
        'ccbot' => ['CCBot', 'Common Crawl', 'train'],
        'bytespider' => ['Bytespider', 'ByteDance', 'train'],
        'meta-externalagent' => ['Meta-ExternalAgent', 'Meta', 'train'],
        'facebookbot' => ['FacebookBot', 'Meta', 'train'],
        'applebot-extended' => ['Applebot-Extended', 'Apple', 'train'],
        'amazonbot' => ['Amazonbot', 'Amazon', 'train'],
        'cohere-training-data-crawler' => ['cohere-training-data-crawler', 'Cohere', 'train'],
        'cohere-ai' => ['cohere-ai', 'Cohere', 'train'],
        'diffbot' => ['Diffbot', 'Diffbot', 'train'],
        'ai2bot' => ['AI2Bot', 'Allen Institute', 'train'],
        'omgilibot' => ['Omgilibot', 'Webz.io', 'train'],
        'omgili' => ['omgili', 'Webz.io', 'train'],
        'pangubot' => ['PanguBot', 'Huawei', 'train'],
        'timpibot' => ['Timpibot', 'Timpi', 'train'],
        'imagesiftbot' => ['ImagesiftBot', 'Hive', 'train'],
        'googleother' => ['GoogleOther', 'Google', 'train'],
    ];

    /** Expression lue par index.php (cache/runtime.php) : les agents les plus longs d'abord, insensible à la casse. */
    public static function pattern(): string
    {
        $names = array_keys(self::BOTS);
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        return '~(?:' . implode('|', array_map(fn ($n) => preg_quote($n, '~'), $names)) . ')~i';
    }

    public static function dir(): string
    {
        return ECLAIR_DATA . '/ai';
    }

    /** Fiche d'un robot noté (nom canonique, éditeur, type), ou null s'il n'est plus dans la liste. */
    public static function bot(string $raw): ?array
    {
        $b = self::BOTS[strtolower($raw)] ?? null;
        return $b ? ['name' => $b[0], 'owner' => $b[1], 'kind' => $b[2]] : null;
    }

    /**
     * Visites des $days derniers jours, agrégées : totaux par type, par robot, par page, par jour.
     * Les journaux de plus de 13 mois sont effacés au passage.
     */
    public static function report(int $days, ?int $now = null): array
    {
        $now ??= time();
        $from = $now - $days * 86400;
        $out = ['total' => 0, 'kinds' => ['user' => 0, 'search' => 0, 'train' => 0], 'bots' => [], 'pages' => [], 'days' => [], 'robots_txt' => 0];
        for ($d = 0; $d < $days; $d++) {
            $out['days'][gmdate('Y-m-d', $now - ($days - 1 - $d) * 86400)] = ['user' => 0, 'search' => 0, 'train' => 0];
        }
        $months = [];
        for ($t = $from; $t <= $now + 86400 * 31; $t += 86400 * 27) {
            $months[gmdate('Y-m', min($t, $now))] = true;
        }
        $keep = gmdate('Y-m', $now - 400 * 86400);
        foreach (glob(self::dir() . '/[0-9][0-9][0-9][0-9]-[0-9][0-9].php') ?: [] as $file) {
            if (basename($file, '.php') < $keep) {
                @unlink($file);
            }
        }
        foreach (array_keys($months) as $month) {
            $h = @fopen(self::dir() . '/' . $month . '.php', 'rb');
            if (!$h) {
                continue;
            }
            while (($line = fgets($h, 1024)) !== false) {
                $p = explode("\t", rtrim($line, "\n"), 3);
                if (count($p) !== 3 || !ctype_digit($p[0]) || (int) $p[0] < $from || (int) $p[0] > $now || !($bot = self::bot($p[1]))) {
                    continue;
                }
                $time = (int) $p[0];
                $kind = $bot['kind'];
                $path = self::path($p[2]);
                $out['total']++;
                $out['kinds'][$kind]++;
                $b = &$out['bots'][$bot['name']];
                $b ??= $bot + ['count' => 0, 'last' => 0];
                $b['count']++;
                $b['last'] = max($b['last'], $time);
                unset($b);
                if ($path === '/robots.txt') {
                    $out['robots_txt']++;
                } else {
                    $pg = &$out['pages'][$path];
                    $pg ??= ['count' => 0, 'user' => 0, 'search' => 0, 'train' => 0, 'last' => 0];
                    $pg['count']++;
                    $pg[$kind]++;
                    $pg['last'] = max($pg['last'], $time);
                    unset($pg);
                }
                $day = gmdate('Y-m-d', $time);
                if (isset($out['days'][$day])) {
                    $out['days'][$day][$kind]++;
                }
            }
            fclose($h);
        }
        uasort($out['bots'], fn ($a, $b) => $b['count'] <=> $a['count']);
        uasort($out['pages'], fn ($a, $b) => [$b['user'], $b['count']] <=> [$a['user'], $a['count']]);
        return $out;
    }

    /** Adresse notée → chemin lisible du site (sans le dossier d'installation, décodé, borné). */
    public static function path(string $raw): string
    {
        $path = rawurldecode($raw);
        $base = rtrim(Req::base(), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        $path = (string) preg_replace('~[\x00-\x1f\x7f]~u', '', mb_substr($path, 0, 200));
        return $path === '' || $path[0] !== '/' ? '/' : $path;
    }

    /** Contenus publiés jamais lus par un robot d'IA dans le rapport (pour savoir quoi améliorer ou relier). */
    public static function unread(array $report, int $max = 12): array
    {
        $out = [];
        foreach (Content::published() as $e) {
            $path = Content::path($e);
            if (!isset($report['pages'][$path]) && empty($e['noindex'])) {
                $out[] = ['title' => $e['title'], 'path' => $path, 'id' => $e['id']];
            }
            if (count($out) >= $max) {
                break;
            }
        }
        return $out;
    }
}
