<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Analyse SEO (moteurs classiques) et GEO (moteurs génératifs : ChatGPT, Perplexity,
 * Google AI Overviews…). Chaque contrôle donne un statut et un conseil actionnable.
 */
final class Analyzer
{
    public static function run(array $item): array
    {
        $blocks = $item['blocks'] ?? [];
        $text = Blocks::text($blocks);
        $words = word_count($text);
        $isPost = ($item['type'] ?? 'post') === 'post';
        $landing = (bool) array_filter($blocks, fn ($b) => in_array($b['type'], ['hero', 'features'], true));
        $title = (string) ($item['title'] ?? '');
        $seo = $item['seo'] ?? [];
        $metaTitle = Seo::title($item);
        $desc = (string) ($seo['description'] ?? '');
        $kw = mb_strtolower(trim((string) ($seo['keyword'] ?? '')));
        $intro = Blocks::intro($blocks);
        $headings = array_map(fn ($b) => plain($b['text']), array_filter($blocks, fn ($b) => $b['type'] === 'heading'));
        $images = array_filter($blocks, fn ($b) => $b['type'] === 'image');
        $links = Blocks::links($blocks);
        $host = (string) parse_url((string) Settings::get('url'), PHP_URL_HOST);
        $internal = array_filter($links, fn ($u) => str_starts_with($u, '/') && !str_starts_with($u, '//') || ($host !== '' && parse_url($u, PHP_URL_HOST) === $host));
        $external = array_filter($links, fn ($u) => preg_match('~^https?://~', $u) && parse_url($u, PHP_URL_HOST) !== $host);
        $minWords = $isPost ? 600 : ($landing ? 150 : 250);
        $checks = [];
        $add = static function (string $group, string $status, string $label, int $weight = 1) use (&$checks): void {
            $checks[] = ['group' => $group, 'status' => $status, 'label' => $label, 'weight' => $weight];
        };

        /* ── SEO ── */
        $len = mb_strlen($metaTitle);
        $add('seo', $len >= 30 && $len <= 60 ? 'good' : ($len > 60 && $len <= 70 || $len >= 20 && $len < 30 ? 'ok' : 'bad'),
            $len > 60 ? t('Titre SEO trop long ({n} caractères) : Google le coupera vers 60.', ['n' => $len])
                : ($len < 30 ? t('Titre SEO court ({n} caractères) : vise 30 à 60.', ['n' => $len]) : t('Titre SEO de bonne longueur ({n} caractères).', ['n' => $len])), 2);

        $dl = mb_strlen($desc);
        $add('seo', $dl >= 110 && $dl <= 160 ? 'good' : ($dl === 0 ? 'bad' : 'ok'),
            $dl === 0 ? t('Ajoute une méta-description (110 à 160 caractères) : elle donne envie de cliquer.')
                : ($dl > 160 ? t('Méta-description trop longue ({n}) : elle sera tronquée.', ['n' => $dl])
                    : ($dl < 110 ? t('Méta-description un peu courte ({n}) : vise 110 à 160.', ['n' => $dl]) : t('Méta-description parfaite ({n} caractères).', ['n' => $dl]))), 2);

        if ($kw === '') {
            $add('seo', 'ok', t('Définis une expression-clé principale pour guider l’analyse.'));
        } else {
            $kwSlug = slugify($kw);
            $density = $words > 0 ? substr_count(mb_strtolower($text), $kw) * max(1, word_count($kw)) / $words * 100 : 0;
            $add('seo', str_contains(mb_strtolower($metaTitle . ' ' . $title), $kw) ? 'good' : 'bad', t('Expression-clé dans le titre.'), 2);
            $add('seo', str_contains(mb_strtolower($desc), $kw) ? 'good' : 'ok', t('Expression-clé dans la méta-description.'));
            $add('seo', $kwSlug !== '' && str_contains((string) ($item['slug'] ?? ''), $kwSlug) ? 'good' : 'ok', t('Expression-clé dans l’URL.'));
            $add('seo', str_contains(mb_strtolower($intro), $kw) ? 'good' : 'ok', t('Expression-clé dès l’introduction.'));
            $add('seo', array_filter($headings, fn ($h) => str_contains(mb_strtolower($h), $kw)) ? 'good' : 'ok', t('Expression-clé dans au moins un intertitre.'));
            $add('seo', $density > 3 ? 'bad' : ($density >= 0.4 ? 'good' : 'ok'),
                $density > 3 ? t('Expression-clé trop répétée ({n} %) : écris naturellement.', ['n' => round($density, 1)])
                    : t('Densité de l’expression-clé : {n} %.', ['n' => round($density, 1)]));
        }

        $add('seo', $words >= $minWords ? 'good' : ($words >= $minWords / 2 ? 'ok' : 'bad'),
            t('{n} mots (recommandé : {min} et plus).', ['n' => $words, 'min' => $minWords]), 2);

        if ($words > 300 || $isPost) {
            $add('seo', count($headings) >= 2 ? 'good' : (count($headings) === 1 ? 'ok' : 'bad'), t('Structure avec des intertitres (H2, H3).'));
        }
        if ($images) {
            $missing = count(array_filter($images, fn ($b) => $b['alt'] === '' && (Media::get($b['media'])['alt'] ?? '') === ''));
            $add('seo', $missing === 0 ? 'good' : 'bad', $missing === 0 ? t('Toutes les images ont un texte alternatif.')
                : t('{n} image(s) sans texte alternatif.', ['n' => $missing]));
        }
        if ($isPost || $words > 300) {
            $add('seo', count($internal) >= 2 ? 'good' : (count($internal) === 1 ? 'ok' : 'bad'),
                t('{n} lien(s) interne(s) : relie tes pages entre elles (maillage).', ['n' => count($internal)]));
        }
        if ($isPost) {
            $add('seo', ($item['image'] ?? '') !== '' ? 'good' : 'ok', t('Image à la une pour les partages et Google Discover.'));
        }
        if (strlen((string) ($item['slug'] ?? '')) > 75) {
            $add('seo', 'ok', t('URL longue : raccourcis-la à l’essentiel.'));
        }
        if (!empty($seo['noindex'])) {
            $add('seo', 'bad', t('Cette page est exclue de Google (noindex).'), 3);
        }

        /* ── GEO (moteurs de réponse IA) ── */
        $summary = (string) ($item['summary'] ?? '');
        $sw = word_count($summary);
        $add('geo', $sw >= 25 && $sw <= 90 ? 'good' : ($sw > 0 ? 'ok' : 'bad'),
            $sw === 0 ? t('Ajoute « L’essentiel » : un résumé de 2-3 phrases que les IA peuvent citer tel quel.')
                : t('Résumé « L’essentiel » : {n} mots (idéal : 25 à 90).', ['n' => $sw]), 3);

        $introWords = word_count($intro);
        $add('geo', $introWords > 0 && $introWords <= 70 ? 'good' : 'ok',
            t('Introduction directe : réponds à la question dès les premières lignes (moins de 70 mots).'), 2);

        $faq = Blocks::faq($blocks);
        $questions = array_filter($headings, fn ($h) => str_ends_with(trim($h), '?'));
        if ($isPost || $words > 400) {
            $add('geo', $faq ? 'good' : ($questions ? 'ok' : 'bad'), $faq ? t('Bloc FAQ présent (balisé FAQPage).')
                : t('Ajoute un bloc FAQ : les questions-réponses sont très reprises par les IA.'), 2);
            $add('geo', count($questions) >= 1 || $faq ? 'good' : 'ok', t('Intertitres formulés en questions (« Comment… ? »).'));
        }
        $structured = array_filter($blocks, fn ($b) => in_array($b['type'], ['list', 'table'], true));
        $add('geo', $structured ? 'good' : 'ok', t('Listes ou tableaux : des données faciles à extraire.'));
        $add('geo', preg_match('/\d+(?:[.,]\d+)?\s?(?:%|€|\$|ans|mois|jours|heures|km|kg|m²|x\b)/u', $text) || preg_match_all('/\b\d{2,}\b/', $text) >= 2 ? 'good' : 'ok',
            t('Chiffres concrets (statistiques, prix, durées) : ils rendent le contenu citable.'));
        if ($isPost || $words > 400) {
            $add('geo', count($external) >= 1 ? 'good' : 'ok', t('Cite au moins une source externe fiable.'));
        }
        $author = Users::find((string) ($item['author'] ?? ''));
        $add('geo', $author && mb_strlen((string) ($author['bio'] ?? '')) >= 40 ? 'good' : 'ok',
            t('Auteur identifié avec une biographie (E-E-A-T) : complète ton profil.'));
        $paragraphs = array_filter($blocks, fn ($b) => $b['type'] === 'paragraph');
        $long = count(array_filter($paragraphs, fn ($b) => word_count(plain($b['text'])) > 120));
        $add('geo', $long === 0 ? 'good' : 'ok', $long === 0 ? t('Paragraphes courts et faciles à citer.')
            : t('{n} paragraphe(s) de plus de 120 mots : découpe-les.', ['n' => $long]));
        if (!empty($item['updated']) && !empty($item['date']) && $item['status'] === 'published') {
            $age = time() - (int) $item['date'];
            $add('geo', $age < 365 * 86400 ? 'good' : 'ok', t('Contenu récent ou mis à jour (les IA privilégient la fraîcheur).'));
        }

        return [
            'checks' => $checks,
            'seo' => self::score($checks, 'seo'),
            'geo' => self::score($checks, 'geo'),
            'words' => $words,
            'reading' => Content::readingTime($words),
        ];
    }

    private static function score(array $checks, string $group): int
    {
        $total = 0;
        $got = 0;
        foreach ($checks as $c) {
            if ($c['group'] !== $group) {
                continue;
            }
            $total += $c['weight'];
            $got += $c['weight'] * ['good' => 1, 'ok' => 0.5, 'bad' => 0][$c['status']];
        }
        return $total > 0 ? (int) round($got / $total * 100) : 0;
    }
}
