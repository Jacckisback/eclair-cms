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
    /** Repère laissé là où une information manque (« [À compléter : prix] »), dans la langue du contenu. */
    public const TODO = ['fr' => 'À compléter', 'es' => 'A completar', 'en' => 'To complete', 'de' => 'Zu ergänzen', 'it' => 'Da completare', 'pt' => 'A completar'];
    /** Formules creuses du « design générique » (relevées par Impeccable), par langue : à remplacer par du concret. */
    private const HOLLOW = [
        'fr' => 'révolutionnaire|innovant(?:e|s|es)?|de pointe|à la pointe|leader du marché|incontournable|clé en main|synergie|disruptif|disruptive|sans compromis|expérience unique|n[’\']attendez plus|inégalé(?:e|s|es)?|boostez|propulsez',
        'en' => 'seamless(?:ly)?|cutting-edge|revolutionary|game-?chang(?:er|ing)|unlock|elevate|empower|leverage|synergy|best-in-class|world-class|next-generation|innovative|supercharge|effortless(?:ly)?|delve|unleash',
        'es' => 'revolucionari[oa]s?|innovador(?:a|es|as)?|de vanguardia|líder del sector|sin igual|llave en mano|sinergia|disruptiv[oa]s?|experiencia única|de primer nivel|potencia tu|impulsa tu',
        'de' => 'revolutionär(?:e|en|er)?|innovativ(?:e|en|er)?|bahnbrechend(?:e|en|er)?|nahtlos(?:e|en|er)?|erstklassig(?:e|en|er)?|synergie|ganzheitlich(?:e|en|er)?|spitzenklasse|einzigartiges erlebnis|gamechanger',
        'it' => 'rivoluzionari[oa]|innovativ[oaie]|all[’\']avanguardia|leader di settore|senza pari|chiavi in mano|sinergia|esperienza unica|di prim[’\']ordine|potenzia',
        'pt' => 'revolucionári[oa]s?|inovador(?:a|es|as)?|de ponta|líder de mercado|sem igual|chave na mão|sinergia|experiência única|de primeira linha|potencialize|alavanque',
    ];

    public static function run(array $item): array
    {
        $blocks = $item['blocks'] ?? [];
        $text = Blocks::text($blocks);
        $words = word_count($text);
        $isPost = ($item['type'] ?? 'post') === 'post';
        $isProduct = ($item['type'] ?? '') === 'product' && isset($item['product']);
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
        $minWords = $isPost ? 600 : ($isProduct || $landing ? 150 : 250);
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
        if ($isProduct) {
            $offers = Shop::offers($item);
            $add('seo', array_filter($offers, fn ($o) => $o['price'] !== '' && (float) $o['price'] > 0) ? 'good' : 'bad',
                t('Prix indiqué : sans prix, Google n’affiche pas la fiche dans ses résultats produits.'), 3);
            $add('seo', array_filter(array_column($offers, 'link')) ? 'good' : 'bad',
                t('Lien de paiement (Stripe, PayPal ou Revolut Business) : sans lui, le bouton « Acheter » reste désactivé.'), 2);
            $orphans = count(array_filter($item['product']['variants'], fn ($v) => $v['link'] === '' && $v['price'] !== '' && $v['price'] !== $item['product']['price']));
            if ($orphans) {
                $add('seo', 'bad', t('{n} variante(s) à un autre prix sans lien de paiement : chacune a besoin de son propre lien.', ['n' => $orphans]), 2);
            }
            $photos = Shop::images($item);
            $add('seo', count($photos) >= 3 ? 'good' : ($photos ? 'ok' : 'bad'),
                t('{n} photo(s) du produit : vise au moins 3 vues.', ['n' => count($photos)]), 2);
            $noAlt = count(array_filter($photos, fn ($m) => $m['alt'] === ''));
            if ($noAlt) {
                $add('seo', 'ok', t('{n} image(s) sans texte alternatif.', ['n' => $noAlt]));
            }
            $p = $item['product'];
            $add('seo', $p['gtin'] !== '' || ($p['brand'] !== '' && $p['sku'] !== '') ? 'good' : 'ok',
                t('Marque et code produit (GTIN/EAN ou référence) : ils aident Google Shopping à identifier le produit.'));
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
        // Passages laissés « [À compléter : …] » par l'IA : rien ne doit être publié en l'état.
        $todo = preg_match_all('/\[(?:' . implode('|', array_map('preg_quote', array_unique(self::TODO))) . ')[\s\x{a0}\x{202f}]*:/iu',
            $title . ' ' . ($item['summary'] ?? '') . ' ' . $desc . ' ' . $text);
        if ($todo) {
            $add('seo', 'bad', t('{n} passage(s) « [À compléter : …] » à remplir avant de publier.', ['n' => $todo]), 3);
        }
        if (!empty($item['updated']) && !empty($item['date']) && $item['status'] === 'published') {
            $age = time() - (int) $item['date'];
            $add('geo', $age < 365 * 86400 ? 'good' : 'ok', t('Contenu récent ou mis à jour (les IA privilégient la fraîcheur).'));
        }

        self::design($item, $blocks, $text, $words, $add);

        return [
            'checks' => $checks,
            'seo' => self::score($checks, 'seo'),
            'geo' => self::score($checks, 'geo'),
            'design' => self::score($checks, 'design'),
            'words' => $words,
            'reading' => Content::readingTime($words),
        ];
    }

    /**
     * Score Design : la page tient-elle la route visuellement ? Vérifications déterministes adaptées des règles d'Impeccable
     * (Paul Bakaus, Apache 2.0) aux blocs d'Éclair : hiérarchie, bannière, appels à l'action, rythme, tics du « design générique ».
     */
    private static function design(array $item, array $blocks, string $text, int $words, \Closure $add): void
    {
        $d = Theme::direction();
        $plain = static fn (mixed $v): string => trim(plain(is_string($v) ? $v : ''));
        $headings = array_values(array_filter($blocks, fn ($b) => $b['type'] === 'heading'));
        $hero = array_values(array_filter($blocks, fn ($b) => $b['type'] === 'hero'))[0] ?? null;
        $features = array_filter($blocks, fn ($b) => $b['type'] === 'features');

        // Hiérarchie des titres : pas de saut de niveau (le titre de la page est le H1).
        $skips = 0;
        $prev = 1;
        foreach ($headings as $h) {
            $skips += (int) $h['level'] > $prev + 1 ? 1 : 0;
            $prev = (int) $h['level'];
        }
        if ($headings) {
            $add('design', $skips === 0 ? 'good' : 'bad', $skips === 0 ? t('Hiérarchie des intertitres sans saut (H2, puis H3, puis H4).')
                : t('{n} saut(s) de niveau dans les intertitres (ex. : H2 puis H4) : garde l’ordre H2 → H3 → H4.', ['n' => $skips]), 2);
            $long = count(array_filter($headings, fn ($h) => word_count($plain($h['text'])) > 12));
            $add('design', $long === 0 ? 'good' : 'ok', $long === 0 ? t('Intertitres courts, lisibles d’un coup d’œil.')
                : t('{n} intertitre(s) de plus de 12 mots : raccourcis-les.', ['n' => $long]));
            $deco = count(array_filter($headings, fn ($h) => preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $plain($h['text']))));
            if ($deco) {
                $add('design', 'ok', t('{n} intertitre(s) avec un émoji décoratif : le texte seul est plus net.', ['n' => $deco]));
            }
        }

        // Bannière : un titre court, un bouton d'action, et l'image que demande la composition choisie.
        if ($hero) {
            $hw = word_count($plain($hero['title']));
            $max = $d && $d['case'] === 'upper' ? 6 : 8;
            $add('design', $hw <= $max ? 'good' : ($hw <= $max + 6 ? 'ok' : 'bad'), $hw <= $max ? t('Titre de bannière court et percutant.')
                : t('Titre de bannière de {n} mots : vise {max} mots au plus pour qu’il frappe.', ['n' => $hw, 'max' => $max]));
            $add('design', $hero['button'] !== '' ? 'good' : 'ok', $hero['button'] !== '' ? t('Bannière avec un bouton d’action.')
                : t('Ajoute un bouton à la bannière : dis au visiteur quoi faire ensuite.'));
            if ($d && in_array($d['hero'], ['fullbleed', 'editorial'], true)) {
                $add('design', $hero['media'] !== '' ? 'good' : 'bad', $hero['media'] !== '' ? t('Bannière illustrée, comme le veut ta direction artistique.')
                    : t('Ajoute une image à la bannière : la composition de ta direction artistique en a besoin.'), 2);
            }
        }

        // Appels à l'action : un chemin principal clair plutôt qu'une forêt de boutons.
        $buttons = count(array_filter($blocks, fn ($b) => $b['type'] === 'button')) + ($hero ? ($hero['button'] !== '' ? 1 : 0) + ($hero['button2'] !== '' ? 1 : 0) : 0);
        $add('design', $buttons <= 3 ? 'good' : ($buttons <= 5 ? 'ok' : 'bad'), $buttons <= 3 ? t('Appels à l’action mesurés (3 boutons au plus).')
            : t('{n} boutons sur la page : garde un appel à l’action principal, les autres diluent l’attention.', ['n' => $buttons]));

        // Bloc « Atouts » : 3 à 6 éléments décrits, des icônes cohérentes.
        foreach ($features as $f) {
            $n = count($f['items']);
            $bare = count(array_filter($f['items'], fn ($i) => trim(plain($i['text'])) === ''));
            $add('design', $n >= 3 && $n <= 6 && $bare === 0 ? 'good' : ($n >= 2 && $n <= 8 ? 'ok' : 'bad'), $n < 3 || $n > 6
                ? t('Bloc « Atouts » de {n} élément(s) : 3 à 6 se lisent le mieux.', ['n' => $n])
                : ($bare ? t('{n} atout(s) sans description : une phrase concrète chacun.', ['n' => $bare]) : t('Bloc « Atouts » équilibré et décrit.')));
            $emoji = array_filter(array_column($f['items'], 'icon'), fn ($i) => $i !== '');
            if (!$emoji) {
                continue;
            }
            $icons = $d['icons'] ?? 'emoji';
            if ($icons === 'line') {
                $missing = count(array_filter($emoji, fn ($i) => Design::icon($i) === ''));
                $add('design', $missing === 0 ? 'good' : 'ok', $missing === 0 ? t('Icônes dessinées pour chaque atout.')
                    : t('{n} émoji(s) sans icône dessinée correspondante (rien ne s’affiche) : essaie ✓ 🚀 ⏱ 🛡 ⭐ ❤ 💡 📞 📍 🚚.', ['n' => $missing]));
            } elseif (!$d) {
                $add('design', 'ok', t('Émojis en guise d’icônes : une direction artistique les remplace par des icônes dessinées.'));
            }
        }

        // Tics du texte qui trahissent le « contenu générique ».
        preg_match_all('/(?:(?<!\p{L})\p{Lu}{2,}(?!\p{L})[\s,’\'-]+){3,}\p{Lu}{2,}(?!\p{L})/u', $text, $caps);
        $add('design', !$caps[0] ? 'good' : 'ok', !$caps[0] ? t('Pas de phrases en MAJUSCULES dans le texte.')
            : t('{n} passage(s) en MAJUSCULES : écris en casse normale, la mise en forme s’occupe du reste.', ['n' => count($caps[0])]));
        $lang = (string) ($item['lang'] ?? Settings::get('lang'));
        if (isset(self::HOLLOW[$lang])) {
            preg_match_all('/(?<![\p{L}\p{N}])(?:' . self::HOLLOW[$lang] . ')(?![\p{L}\p{N}])/iu', ($item['title'] ?? '') . ' ' . $text, $m);
            $found = array_values(array_unique(array_map('mb_strtolower', $m[0])));
            $add('design', !$found ? 'good' : (count($m[0]) >= 3 ? 'bad' : 'ok'), !$found ? t('Pas de formules creuses : du concret.')
                : t('Formules creuses à remplacer par du concret : {list}.', ['list' => '« ' . implode(' », « ', array_slice($found, 0, 4)) . ' »']));
        }
        $dashes = substr_count($text, '—');
        if ($words >= 150 && $dashes > max(2, intdiv($words, 150))) {
            $add('design', 'ok', t('{n} tirets cadratins (—) : un tic d’écriture des IA, remplace-les par des virgules ou des points.', ['n' => $dashes]));
        }
        $seen = [];
        $repeats = 0;
        foreach ($blocks as $b) {
            foreach (array_merge([$b['text'] ?? ''], array_column(($b['type'] ?? '') === 'features' ? $b['items'] : [], 'text')) as $t) {
                $t = mb_strtolower($plain($t));
                if (word_count($t) >= 6) {
                    $repeats += isset($seen[$t]) ? 1 : 0;
                    $seen[$t] = true;
                }
            }
        }
        $add('design', $repeats === 0 ? 'good' : 'bad', $repeats === 0 ? t('Aucun texte répété à l’identique.')
            : t('{n} texte(s) répété(s) à l’identique : chaque passage doit apporter du neuf.', ['n' => $repeats]));

        // Rythme : un long mur de paragraphes fatigue l'œil.
        if ($words > 300) {
            $run = 0;
            $wall = 0;
            foreach ($blocks as $b) {
                $run = $b['type'] === 'paragraph' ? $run + word_count($plain($b['text'])) : 0;
                $wall = max($wall, $run);
            }
            $add('design', $wall <= 350 ? 'good' : ($wall <= 600 ? 'ok' : 'bad'), $wall <= 350 ? t('Rythme varié : le texte respire.')
                : t('Mur de texte de {n} mots : coupe-le avec un intertitre, une image ou une liste.', ['n' => $wall]));
        }

        // Le site lui-même : une direction artistique plutôt qu'un thème standard.
        $add('design', $d ? 'good' : 'ok', $d ? t('Direction artistique : {name}.', ['name' => $d['name'] !== '' ? $d['name'] : $d['id']])
            : t('Choisis une direction artistique (Réglages › Apparence) : ton site ne ressemblera à aucun autre.'));
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
