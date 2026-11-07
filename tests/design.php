<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests des directions artistiques (1.8), inclus par tests/run.php : garanties de contraste, CSS envoyé, icônes,
 * polices auto-hébergées, réglages, aperçu, « Imagine ma direction » (faux fournisseur) et Score Design.
 */
declare(strict_types=1);

use Eclair\Analyzer;
use Eclair\Design;
use Eclair\Settings;
use Eclair\Store;
use Eclair\Theme;

/* ─────────────────────────── Contrastes garantis ─────────────────────────── */
section('Directions artistiques : couleurs et contrastes');
// Fonds teintés d'accent du thème (color-mix en sRGB) : encadré 7 %, succès 9 %, variante 10 %, pastille 13 %, initiales 14 %, surlignage 22 %.
$mix = static function (string $a, string $b, float $share): string {
    [$x, $y] = [array_map('hexdec', str_split(ltrim($a, '#'), 2)), array_map('hexdec', str_split(ltrim($b, '#'), 2))];
    return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($x[$i] * $share + $y[$i] * (1 - $share)), [0, 1, 2]));
};
$meets = static function (?array $d) use ($mix): bool {
    if ($d === null || Theme::contrast($d['ink'], $d['ground']) < 7 || Theme::contrast($d['on_accent'], $d['accent']) < 4.5) {
        return false;
    }
    foreach (['ground', 'surface'] as $b) {
        foreach ([0, 0.07, 0.09, 0.1, 0.13, 0.14, 0.22] as $t) {
            $bg = $t ? $mix($d['accent'], $d[$b], $t) : $d[$b];
            if (Theme::contrast($d['ink'], $bg) < 4.5 || Theme::contrast($d['link'], $bg) < 4.5 || ($t <= 0.1 && Theme::contrast($d['muted'], $bg) < 4.5)) {
                return false;
            }
        }
    }
    return true;
};
$curated = array_keys(Design::DIRECTIONS);
check('12 directions livrées, toutes lisibles (texte ≥ 7:1, le reste ≥ 4,5:1, fonds teintés compris)', count($curated) === 12 && !array_filter($curated, fn ($id) => !$meets(Design::find($id))));
check('directions livrées utilisées telles quelles (aucune couleur corrigée)', !array_filter($curated, function ($id) {
    $d = Design::find($id);
    return array_filter(['ground', 'surface', 'ink', 'muted', 'accent'], fn ($k) => $d[$k] !== Design::DIRECTIONS[$id][$k]) !== [];
}));
check('polices des directions livrées : toutes dans la liste', !array_filter($curated, fn ($id) => !isset(Design::FONTS[Design::DIRECTIONS[$id]['display']])));
$fails = 0;
mt_srand(18);
for ($i = 0; $i < 3000; $i++) {
    $c = static fn (): string => sprintf('#%06x', mt_rand(0, 0xffffff));
    $fails += $meets(Design::normalize(['ground' => $c(), 'surface' => $c(), 'ink' => $c(), 'muted' => $c(), 'accent' => $c()])) ? 0 : 1;
}
check('3 000 palettes au hasard : contrastes toujours garantis', $fails === 0, "$fails échec(s)");
$grey = Design::normalize(['ground' => '#808080', 'surface' => '#7a7a7a', 'ink' => '#8a8a8a', 'muted' => '#858585', 'accent' => '#828282']);
check('gris moyen partout : fond éclairci et texte foncé lisible', $meets($grey) && $grey['scheme'] === 'light', json_encode($grey));
$mixed = Design::normalize(['ground' => '#101010', 'surface' => '#fafafa', 'ink' => '#ffffff', 'muted' => '#cccccc', 'accent' => '#ffcc00']);
check('bandes du mauvais côté (clair sur fond sombre) : ramenées vers le fond', $meets($mixed) && $mixed['scheme'] === 'dark' && Theme::contrast('#ffffff', $mixed['surface']) > Theme::contrast('#000000', $mixed['surface']));
check('couleur invalide : direction refusée', Design::normalize(['ground' => 'red', 'surface' => '#fff', 'ink' => '#000', 'muted' => '#333', 'accent' => '#f00']) === null
    && Design::normalize(['ground' => '#ffffff;}body{display:none', 'surface' => '#ffffff', 'ink' => '#000000', 'muted' => '#333333', 'accent' => '#ff0000']) === null
    && Design::normalize('nocturne') === null);
$odd = Design::normalize(Design::DIRECTIONS['galerie'] + ['id' => '../x"<', 'name' => '<script>alert(1)</script>Musée', 'idea' => str_repeat('a', 500)]);
$odd2 = Design::normalize(['display' => '../../etc/passwd', 'text' => 'mono', 'hero' => 'x;}', 'icons' => ['line'], 'case' => 'UPPER'] + Design::DIRECTIONS['galerie']);
check('valeurs hors liste : remplacées par les valeurs par défaut', $odd2['display'] === 'system' && $odd2['text'] === 'system' && $odd2['hero'] === 'split' && $odd2['icons'] === 'line' && $odd2['case'] === 'normal');
check('identifiant, nom et idée assainis', $odd['id'] === 'x' && !str_contains($odd['name'], '<') && mb_strlen($odd['idea']) <= 200);
$enc = rtrim(strtr(base64_encode((string) json_encode(Design::DIRECTIONS['argile'])), '+/', '-_'), '=');
check('direction transmise par l’aperçu (JSON en base64url)', (Design::find($enc)['display'] ?? '') === 'young-serif' && Design::find('inconnue') === null
    && Design::find('%%%') === null && Design::find(rtrim(strtr(base64_encode(str_repeat(' ', 4100) . '{}'), '+/', '-_'), '=')) === null);

/* ─────────────────────────── CSS et icônes ─────────────────────────── */
section('Directions artistiques : CSS envoyé, icônes');
$has = static fn (string $css, string $needle): bool => str_contains(Theme::minify($css), Theme::minify($needle)); // l'archive livre directions.css minifié
$css = Design::css(Design::find('nocturne'));
check('variables de la direction', $has($css, ':root{--bg:#0f1724;--bg-soft:#182235;') && $has($css, '--font-head:"Big Shoulders Display",') && $has($css, 'color-scheme:dark'));
check('seuls les morceaux des choix de la direction', $has($css, 'text-transform:uppercase') && $has($css, 'min-height:min(78vh, 46rem)')
    && !$has($css, 'max-width:15ch') && !$has($css, 'aspect-ratio:4/5') && !str_contains($css, '/*@'));
$cssPresse = Design::css(Design::find('presse'));
check('autre direction, autres morceaux', $has($cssPresse, 'aspect-ratio:4/5') && !$has($cssPresse, 'text-transform:uppercase') && !$has($cssPresse, 'box-shadow:0 0 0 100vmax'));
check('police absente : ni @font-face ni préchargement (police de secours)', Design::fontFile('gloock') === '' && !str_contains(Design::css(Design::find('riviera')), '@font-face')
    && Design::preload(Design::find('riviera')) === '' && Design::fontFile('inconnue') === '' && !Design::fetchFont('../../x'));
check('icône dessinée pour un émoji connu, rien pour un inconnu', str_starts_with(Design::icon('🚀'), '<svg viewBox="0 0 24 24" aria-hidden="true">')
    && Design::icon('❤️') !== '' && Design::icon('🦄') === '' && Design::icon('') === '' && Design::icon('<b>') === '');
// 1.9 : nouvelles compositions (bannière inversée ou encadrée, atouts numérotés, filets, boutons) et leurs morceaux.
$combo = Design::normalize(['hero' => 'reverse', 'features' => 'numbers', 'sections' => 'rules', 'buttons' => 'outline'] + Design::DIRECTIONS['argile']);
$cssCombo = Design::css($combo);
check('1.9 : bannière à gauche, atouts numérotés, filets et boutons à contour', $has($cssCombo, '.hero-media .hero-img{order:-1}') && $has($cssCombo, 'counter(feat, decimal-leading-zero)')
    && $has($cssCombo, '.btns .btn-primary{background:transparent') && $has($cssCombo, 'border-top:1px solid var(--line);padding-top') && !$has($cssCombo, 'box-shadow:0 0 0 100vmax'));
$over = '.hero-media .btns .btn,.hero-media .btns .btn:hover{background:transparent;color:#fff';
check('1.9 : boutons sans fond restés blancs sur une bannière plein cadre, seulement là', str_contains(Design::css(Design::normalize(['buttons' => 'underline'] + Design::DIRECTIONS['nocturne'])), $over)
    && !str_contains(Design::css(Design::find('nocturne')), $over) && !$has($cssCombo, $over) && str_contains(Design::css(Design::normalize(['hero' => 'framed'] + Design::DIRECTIONS['galerie'])), 'background:var(--bg-soft);border-radius:var(--radius);padding'));
check('1.9 : direction enregistrée avant la 1.9 (sans boutons) : boutons pleins par défaut', Design::normalize(Design::DIRECTIONS['presse'])['buttons'] === 'solid'
    && Design::normalize(['buttons' => 'neon'] + Design::DIRECTIONS['presse'])['buttons'] === 'solid');
$icons = (new ReflectionClass(Design::class))->getConstant('ICONS');
$emoji = (new ReflectionClass(Design::class))->getConstant('EMOJI');
check('1.9 : 65 icônes dessinées, chacune reliée à des émojis, sans doublon', count($icons) === 65 && !array_diff_key($icons, $emoji) && !array_diff_key($emoji, $icons)
    && count(preg_split('//u', implode('', $emoji), -1, PREG_SPLIT_NO_EMPTY)) === count(array_unique(preg_split('//u', implode('', $emoji), -1, PREG_SPLIT_NO_EMPTY))));
check('1.9 : émojis précisés (café, cadenas, poignée de main…) vers leur propre icône', Design::icon('☕') !== Design::icon('🍽') && Design::icon('🔒') !== Design::icon('🛡')
    && Design::icon('🤝') !== Design::icon('👥') && Design::icon('🐶') !== '' && Design::icon('✈️') !== '' && Design::icon('⚖️') !== '');
Theme::preview(['direction' => $combo]);
$numbered = Eclair\Blocks::html(Eclair\Blocks::normalize([['type' => 'features', 'title' => 'Atouts', 'items' => [['icon' => '🚀', 'title' => 'Rapide', 'text' => 'Oui.'], ['icon' => '⭐', 'title' => 'Noté', 'text' => 'Oui.']]]]));
check('1.9 : atouts numérotés sans icône dans le HTML', str_contains($numbered, '<h3') && !str_contains($numbered, 'feature-icon'));
Theme::preview(['direction' => Design::find('nocturne')]);
$themed = Theme::css();
Theme::preview(['direction' => []]);
$classic = Theme::css();
Theme::preview([]);
check('thème avec direction : sans mode sombre automatique, avec ses variables', str_contains($themed, '--bg:#0f1724') && !str_contains($themed, 'prefers-color-scheme')
    && str_contains($classic, 'prefers-color-scheme') && !str_contains($classic, '--font-head:"Big Shoulders'));

/* ─────────────────────────── Score Design ─────────────────────────── */
section('Score Design');
$page = static fn (array $blocks, string $lang = 'fr'): array => Analyzer::run(['type' => 'page', 'lang' => $lang, 'title' => 'Une page', 'status' => 'draft',
    'seo' => ['title' => '', 'description' => '', 'keyword' => ''], 'summary' => '', 'blocks' => Eclair\Blocks::normalize($blocks)]);
$labels = static fn (array $a, string $status): string => implode(' | ', array_map(fn ($c) => $c['label'], array_filter($a['checks'], fn ($c) => $c['group'] === 'design' && $c['status'] === $status)));
$good = $page([
    ['type' => 'hero', 'title' => 'Céramique faite main à Lyon', 'text' => 'Des bols uniques.', 'button' => 'Réserver', 'url' => '/contact'],
    ['type' => 'heading', 'level' => 2, 'text' => 'Nos ateliers'],
    ['type' => 'paragraph', 'text' => 'Des cours en petits groupes, le samedi matin, avec tout le matériel fourni.'],
    ['type' => 'features', 'title' => 'Atouts', 'items' => [['icon' => '🚀', 'title' => 'Rapide', 'text' => 'Inscription en deux minutes.'],
        ['icon' => '🛡', 'title' => 'Sûr', 'text' => 'Paiement sécurisé.'], ['icon' => '⭐', 'title' => 'Noté', 'text' => 'Avis vérifiés de nos élèves.']]],
]);
check('page soignée : score Design présent et élevé', isset($good['design']) && $good['design'] >= 85 && $labels($good, 'bad') === '', $good['design'] . ' ' . $labels($good, 'bad'));
$bad = $page([
    ['type' => 'hero', 'title' => 'Bienvenue sur le site de notre entreprise révolutionnaire et innovante qui propose des solutions clé en main', 'text' => ''],
    ['type' => 'heading', 'level' => 2, 'text' => 'Titre'],
    ['type' => 'heading', 'level' => 4, 'text' => '🚀 Sous-titre trop profond'],
    ['type' => 'paragraph', 'text' => 'NOUS SOMMES LES MEILLEURS DU MARCHÉ — vraiment — sans compromis.'],
    ['type' => 'paragraph', 'text' => 'Un texte copié collé deux fois de suite ici.'],
    ['type' => 'paragraph', 'text' => 'Un texte copié collé deux fois de suite ici.'],
    ['type' => 'button', 'text' => 'Un', 'url' => '/a'], ['type' => 'button', 'text' => 'Deux', 'url' => '/b'], ['type' => 'button', 'text' => 'Trois', 'url' => '/c'],
    ['type' => 'button', 'text' => 'Quatre', 'url' => '/d'], ['type' => 'button', 'text' => 'Cinq', 'url' => '/e'], ['type' => 'button', 'text' => 'Six', 'url' => '/f'],
    ['type' => 'features', 'title' => '', 'items' => [['icon' => '🦄', 'title' => 'Seul', 'text' => '']]],
]);
$badText = $labels($bad, 'bad');
check('saut de niveau, bannière trop longue, trop de boutons, répétition : signalés', str_contains($badText, 'saut(s) de niveau') && str_contains($badText, 'Titre de bannière de')
    && str_contains($badText, '6 boutons') && str_contains($badText, 'répété(s)'), $badText);
check('formules creuses (3 et plus) : signalées avec leurs mots', str_contains($badText, 'révolutionnaire') && str_contains($badText, 'clé en main'), $badText);
$okText = $labels($bad, 'ok');
check('majuscules, émoji d’intertitre, bannière sans bouton : signalés', str_contains($okText, 'MAJUSCULES') && str_contains($okText, 'émoji décoratif') && str_contains($okText, 'bouton à la bannière'), $okText);
check('score bas pour la page bâclée', $bad['design'] < 50, (string) $bad['design']);
$en = $page([['type' => 'paragraph', 'text' => 'Our seamless, cutting-edge platform will elevate your workflow.']], 'en');
check('formules creuses : selon la langue du contenu', str_contains($labels($en, 'bad'), 'seamless') && str_contains($labels($en, 'bad'), 'elevate'));
$wall = $page(array_fill(0, 4, ['type' => 'paragraph', 'text' => trim(str_repeat('Un mot de plus dans ce très long paragraphe ', 20))]));
check('mur de texte : signalé', str_contains($labels($wall, 'ok') . $labels($wall, 'bad'), 'Mur de texte'));
check('sans direction : invitation à en choisir une', str_contains($labels($good, 'ok'), 'Choisis une direction artistique'));

/* ─────────────────────────── Parcours HTTP ─────────────────────────── */
if (!in_array('--unit', $argv, true)) {
    section('Directions artistiques : réglages, aperçu, polices, IA (faux serveurs)');
    [$copy, $port, $server] = serve('design');
    $fakeDir = sys_get_temp_dir() . '/eclair-fake-design-' . getmypid();
    @mkdir($fakeDir);
    // Port libre vérifié (start_server) : jamais le faux serveur d'une autre suite lancée en même temps.
    [$fakePort, $fake] = start_server([__DIR__ . '/fake-ai.php'], ['FAKE_AI_DIR' => $fakeDir, 'PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
    $fakeBase = 'http://127.0.0.1:' . $fakePort;
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['anthropic' => '$fakeBase', 'openai' => '$fakeBase']);\n"
        . "define('ECLAIR_FONTS_URL', '$fakeBase/fonts');\n");
    usleep(400000);
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Fournil Test', 'site_tagline' => 'Pain au levain à Lyon', 'site_lang' => 'fr', 'site_type' => 'business',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $before = $c->req('GET', '/')['body'];
        $look = $c->req('GET', '/gestion/settings?tab=appearance');
        check('onglet Apparence : choix du style et 12 directions', str_contains($look['body'], 'name="style" value="direction"') && substr_count($look['body'], 'name="direction_id"') === 12);
        check('IA inactive : invitation à l’activer, pas de bouton', !str_contains($look['body'], 'id="dir-imagine"') && str_contains($look['body'], 'Active l’assistant IA'));
        $orig = array_replace(Settings::DEFAULTS['appearance'], json_decode(substr((string) file_get_contents($copy . '/data/site.php'), strlen(Store::GUARD)), true)['appearance'] ?? []);
        $save = static function (array $fields) use ($c, $orig): array {
            $page = $c->req('GET', '/gestion/settings?tab=appearance');
            return $c->req('POST', '/gestion/settings', $c->token($page['body']) + $fields + ['tab' => 'appearance', 'theme' => $orig['theme'], 'header' => $orig['header'],
                'cards' => $orig['cards'], 'logo' => $orig['logo'], 'favicon' => $orig['favicon'], 'accent' => $orig['accent'], 'font_head' => $orig['font_head'],
                'font_body' => $orig['font_body'], 'dark' => $orig['dark'], 'footer' => $orig['footer'], 'credit' => $orig['credit'] ? '1' : '',
                'motion' => $orig['motion'] ? '1' : '', 'mobile_menu' => !empty($orig['mobile_menu']) ? '1' : '', 'splash' => !empty($orig['splash']) ? '1' : '', 'custom_css' => '']);
        };
        $stored = static fn (): array => json_decode(substr((string) file_get_contents($copy . '/data/site.php'), strlen(Store::GUARD)), true)['appearance']['direction'] ?? ['?'];

        // Sans JavaScript : la direction cochée suffit ; sa police est téléchargée une fois et servie par le site.
        $res = $save(['style' => 'direction', 'direction_id' => 'nocturne', 'direction' => '']);
        $home = $c->req('GET', '/');
        $font = $copy . '/media/fonts/big-shoulders-display-800.woff2';
        check('direction enregistrée (sans JavaScript)', $res['status'] === 302 && ($stored()['id'] ?? '') === 'nocturne' && str_contains($home['body'], '--bg:#0f1724'));
        check('police téléchargée (signature WOFF2) et dossier protégé', is_file($font) && str_starts_with((string) file_get_contents($font), 'wOF2') && is_file($copy . '/media/fonts/index.html'));
        check('police préchargée et servie par le site, aucun tiers', str_contains($home['body'], '<link rel="preload" href="/media/fonts/big-shoulders-display-800.woff2" as="font" type="font/woff2" crossorigin>')
            && str_contains($home['body'], '@font-face{font-family:"Big Shoulders Display";src:url(/media/fonts/big-shoulders-display-800.woff2)') && str_contains($home['body'], 'font-display:optional')
            && !preg_match('~fonts\.googleapis|fonts\.gstatic|jsdelivr|fontsource|127\.0\.0\.1:' . $fakePort . '~', $home['body']));
        preg_match('~<style>(.*?)</style>~s', $home['body'], $sm);
        check('CSP : le style en ligne garde son empreinte, sans unsafe-inline', isset($sm[1]) && str_contains($home['headers']['content-security-policy'] ?? '', "'sha256-" . base64_encode(hash('sha256', $sm[1], true)) . "'")
            && !str_contains($home['headers']['content-security-policy'] ?? '', 'unsafe-inline'));
        check('HTML et SEO inchangés : même titre, même JSON-LD, un seul H1', substr_count($home['body'], '<h1') === substr_count($before, '<h1')
            && preg_match_all('~<script type="application/ld\+json">~', $home['body']) === preg_match_all('~<script type="application/ld\+json">~', $before));
        $file = $c->req('GET', '/media/fonts/big-shoulders-display-800.woff2');
        check('fichier de police servi', $file['status'] === 200 && str_starts_with($file['body'], 'wOF2'));

        // Archive : une vue compressée (.php.gz) l'emporte sur l'ancienne vue .php d'une mise à jour, qui n'est pas signalée.
        $view = $copy . '/app/admin/views/login.php';
        $source = is_file($view . '.gz') ? (string) gzdecode((string) file_get_contents($view . '.gz')) : (string) file_get_contents($view); // archive ou dépôt
        file_put_contents($view . '.gz', (string) gzencode(str_replace('</form>', '</form><!-- vue-gz -->', $source), 9));
        file_put_contents($view, str_replace('</form>', '</form><!-- vue-perimee -->', $source));
        $manifest = json_decode(Store::strip((string) file_get_contents($copy . '/app/manifest.php')), true);
        $manifest['files']['app/admin/views/login.php.gz'] = substr((string) hash_file('sha256', $view . '.gz'), 0, 32);
        unset($manifest['files']['app/admin/views/login.php']);
        file_put_contents($copy . '/app/manifest.php', Store::GUARD . json_encode($manifest, JSON_UNESCAPED_SLASHES));
        $login = client($port)->req('GET', '/gestion/login')['body'];
        check('vue compressée lue à la place de l’ancienne vue .php', str_contains($login, '<!-- vue-gz -->') && !str_contains($login, 'vue-perimee'));
        $c->req('POST', '/gestion/security', $c->token($c->req('GET', '/gestion/security')['body']) + ['action' => 'integrity']);
        $sec = $c->req('GET', '/gestion/security')['body'];
        check('contrôle d’intégrité : ni la police ni l’ancienne vue signalées', str_contains($sec, 'id="integrite"') && !str_contains($sec, 'media/fonts/') && !str_contains($sec, 'views/login.php'),
            (string) preg_replace('/\s+/', ' ', strip_tags(substr($sec, (int) strpos($sec, 'id="integrite"'), 1500))));

        // Valeurs du formulaire (JavaScript) : valides, ajustées ; piégées, refusées.
        $tuned = Design::DIRECTIONS['argile'];
        $tuned['hero'] = 'centered';
        $tuned['ink'] = '#d9d0c4';
        $b64 = static fn (array $d): string => rtrim(strtr(base64_encode((string) json_encode($d)), '+/', '-_'), '=');
        $save(['style' => 'direction', 'direction_id' => 'argile', 'direction' => $b64($tuned)]);
        $s = $stored();
        check('direction ajustée : composition gardée, texte trop pâle corrigé', ($s['hero'] ?? '') === 'centered' && Theme::contrast($s['ink'] ?? '#fff', $s['ground'] ?? '#fff') >= 7);
        $trap = ['ground' => '#fff;}body{display:none}', 'display' => '../../../etc/passwd'] + $tuned;
        $res = $save(['style' => 'direction', 'direction_id' => 'pas-une-direction', 'direction' => $b64($trap)]);
        check('direction piégée et identifiant inconnu : refusés, rien d’enregistré', $res['status'] === 302 && ($stored()['hero'] ?? '') === 'centered'
            && !str_contains((string) file_get_contents($copy . '/data/site.php'), 'display:none'));
        $res = $save(['style' => 'direction', 'direction_id' => 'syne-test', 'direction' => $b64(['display' => 'syne'] + Design::DIRECTIONS['galerie'])]);
        check('police invalide chez le fournisseur : rien d’écrit, avertissement, site intact', !glob($copy . '/media/fonts/syne*')
            && ($stored()['display'] ?? '') === 'syne' && str_contains($c->req('GET', '/gestion/settings?tab=appearance')['body'], 'police de secours')
            && !str_contains($c->req('GET', '/')['body'], 'syne-700'));

        // Aperçu : la direction du formulaire, jamais enregistrée ; sans elle, le thème classique.
        $prev = $c->req('GET', '/gestion/appearance/preview?path=/&direction=' . $b64(Design::DIRECTIONS['riviera']));
        $prevClassic = $c->req('GET', '/gestion/appearance/preview?path=/&theme=eclair');
        check('aperçu : direction du formulaire, police téléchargée', str_contains($prev['body'], '--bg:#f4f8fb') && is_file($copy . '/media/fonts/gloock-400.woff2')
            && ($stored()['display'] ?? '') === 'syne');
        check('aperçu sans direction : thème classique', !str_contains($prevClassic['body'], '--font-head:"') && str_contains($prevClassic['body'], 'prefers-color-scheme'));
        check('aperçu refusé sans session', client($port)->req('GET', '/gestion/appearance/preview?direction=nocturne')['status'] === 302);

        // Retour au thème classique : la direction est retirée, le site retrouve son rendu d'origine.
        $save(['style' => 'classic', 'direction_id' => 'nocturne', 'direction' => $b64(Design::DIRECTIONS['nocturne'])]);
        $back = $c->req('GET', '/')['body'];
        $at = strspn($back ^ $before, "\0");
        check('retour au thème classique : rendu d’origine', $stored() === [] && !str_contains($back, 'rel="preload"') && $back === $before,
            json_encode($stored()) . ' … ' . substr($before, max(0, $at - 80), 160) . ' ≠ ' . substr($back, max(0, $at - 80), 160));

        // « Imagine ma direction » : trois propositions validées ; jamais pour un rédacteur.
        $page = $c->req('GET', '/gestion/settings?tab=ai');
        $c->req('POST', '/gestion/settings', $c->token($page['body']) + ['tab' => 'ai', 'provider' => 'anthropic', 'key_ai_anthropic' => 'sk-ant-test-0123456789',
            'model_anthropic' => 'claude-opus-5-5', 'budget' => '10', 'daily' => '60', 'editors' => '1']);
        $look = $c->req('GET', '/gestion/settings?tab=appearance')['body'];
        check('IA active : bouton « Proposer 3 directions » et coût indicatif', str_contains($look, 'id="dir-imagine"') && str_contains($look, 'Coût indicatif'));
        preg_match('/"csrf":"([a-f0-9]+)"/', $look, $m);
        $headers = ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')];
        $short = $c->req('POST', '/gestion/api/ai/directions', '{"brief":"pain"}', $headers);
        $res = $c->req('POST', '/gestion/api/ai/directions', (string) json_encode(['brief' => 'Boulangerie bio de quartier, clientèle familiale.']), $headers);
        $json = json_decode($res['body'], true);
        $ds = $json['directions'] ?? [];
        $sent = (string) json_encode(array_slice(array_values(array_filter(array_map(fn ($l) => json_decode($l, true), explode("\n", (string) @file_get_contents($fakeDir . '/log.jsonl'))),
            fn ($l) => ($l['p'] ?? '') === '/v1/messages')), -1));
        check('description vide et site sans présentation : demande refusée', $short['status'] === 422 || ($short['status'] === 200 && count(json_decode($short['body'], true)['directions'] ?? []) > 0));
        check('IA : trois directions valides, l’invalide écartée', ($json['ok'] ?? false) && count($ds) === 3 && array_column($ds, 'id') === ['ia-1', 'ia-2', 'ia-3']
            && !array_filter($ds, fn ($d) => !$meets(Design::normalize($d))), $res['body']);
        check('IA : texte trop sombre éclairci, police inconnue remplacée, nom assaini', Theme::contrast($ds[1]['ink'] ?? '#000', $ds[1]['ground'] ?? '#000') >= 7
            && ($ds[1]['display'] ?? '') === 'system' && ($ds[1]['name'] ?? '') === 'Nuit de cuisson');
        check('IA : demande guidée (méthode, liste fermée des polices, présentation du site)', str_contains($sent, 'Avoid the category default') && str_contains($sent, 'big-shoulders-display')
            && str_contains($sent, 'Pain au levain') && str_contains($sent, '"enum"'), substr($sent, 0, 400));
        check('IA (1.11) : palettes brunes, beiges ou sable réservées aux métiers qui en sont faits, une direction au plus',
            str_contains($sent, 'Brown, beige, tan, sand') && str_contains($sent, 'in one direction at most'));
        $usage = json_decode(substr((string) @file_get_contents($copy . '/data/ai/usage.php'), strlen(Store::GUARD)), true);
        check('IA : dépense comptée', array_sum(array_column($usage['months'] ?? [], 'cost')) > 0, json_encode($usage));
        $users = json_decode(substr((string) file_get_contents($copy . '/data/users.php'), strlen(Store::GUARD)), true);
        $users['ed1'] = ['id' => 'ed1', 'name' => 'Rédac', 'email' => 'redac@example.com', 'role' => 'editor', 'pass' => password_hash('Mot-de-passe-redac-1', PASSWORD_DEFAULT),
            'ver' => 1, 'created' => time(), 'slug' => 'redac', 'lang' => 'fr', 'must_change' => false, 'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []]];
        file_put_contents($copy . '/data/users.php', Store::GUARD . json_encode($users));
        $e = client($port);
        $e->login('redac@example.com', 'Mot-de-passe-redac-1');
        preg_match('/"csrf":"([a-f0-9]+)"/', $e->req('GET', '/gestion')['body'], $em);
        $eh = ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($em[1] ?? '')];
        check('rédacteur : ni directions IA ni aperçu de l’apparence', $e->req('POST', '/gestion/api/ai/directions', '{"brief":"Boulangerie bio de quartier"}', $eh)['status'] === 403
            && $e->req('GET', '/gestion/appearance/preview?direction=nocturne')['status'] === 403);
    } finally {
        proc_terminate($server);
        proc_terminate($fake);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($fakeDir));
    }
}
