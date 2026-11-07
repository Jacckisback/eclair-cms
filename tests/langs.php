<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de la 1.15 : site multilingue (langues ajoutées, adresses préfixées, blog et catégories par langue, menus traduits,
 * sélecteur de langue, hreflang), menu mobile plein écran et écran de démarrage (manifeste). Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Langs;
use Eclair\Menus;
use Eclair\Settings;
use Eclair\Store;

section('1.15 : réglages par défaut (un site existant ne change pas)');
check('aucune langue ajoutée, menu mobile et écran de démarrage coupés', Settings::DEFAULTS['languages'] === [] && Settings::DEFAULTS['i18n'] === []
    && Settings::DEFAULTS['appearance']['mobile_menu'] === false && Settings::DEFAULTS['appearance']['splash'] === false);
check('site à une langue : ni préfixe ni sélecteur', !Langs::enabled() && Langs::prefix('en') === '' && Langs::split('/en/blog') === [Langs::main(), '/en/blog']);
$clean = Langs::clean('fr', ['en', 'fr', 'xx', 'en', 'es'], ['en' => ['name' => '<b>Site</b>', 'home' => 'inexistant'], 'xx' => ['name' => 'x']]);
check('langues ajoutées nettoyées : connues, sans doublon ni langue du site', $clean['languages'] === ['en', 'es'] && !isset($clean['i18n']['xx'])
    && $clean['i18n']['en']['home'] === '' && !str_contains($clean['i18n']['en']['name'], '<'));
\Eclair\Theme::preview(['direction' => \Eclair\Design::find('nocturne')]);
$cards = \Eclair\Blocks::html(\Eclair\Blocks::normalize([['type' => 'features', 'title' => '', 'items' => [['icon' => '4', 'title' => 'Pack', 'text' => '88 €'], ['icon' => '🎯', 'title' => 'But', 'text' => 'x']]]]));
\Eclair\Theme::preview([]);
check('atouts en icônes dessinées : un repère court (« 4 ») reste affiché et lisible, un émoji devient une icône', str_contains($cards, '<span class="feature-icon feature-mark">4</span>')
    && str_contains($cards, '<span class="feature-icon" aria-hidden="true"><svg'));
check('encadré « L’essentiel » : affiché par défaut (un site existant ne change pas)', Settings::DEFAULTS['appearance']['key_box'] === true
    && str_contains((string) file_get_contents(ECLAIR_ROOT . '/app/theme/single.php'), "appearance.key_box"));
check('police Fraunces : proposée aux directions artistiques', isset(\Eclair\Design::FONTS['fraunces']) && \Eclair\Design::FONTS['fraunces'][1] === 600);
check('bandes de couleur : une bande qui en suit une autre ne recouvre pas ses dernières cartes (clip-path sans débord vers le haut)',
    str_contains((string) file_get_contents(ECLAIR_ROOT . '/app/theme/directions.css'), 'clip-path:inset(0 -100vmax calc(-1 * var(--band)))'));
check('icônes de menu : toutes dessinées', array_filter(Menus::ICONS, fn (string $n): bool => !\Eclair\Icons::has($n)) === []);

if (!in_array('--unit', $argv, true)) {
    require_once dirname(__DIR__) . '/tools/capture.php';
    section('1.15 : site multilingue (fr + en), menu mobile et écran de démarrage');
    [$copy, $port, $server] = capture_serve(dirname(__DIR__), __DIR__ . '/fixtures/sites/business', 'langs');
    try {
        $read = static function (string $name) use ($copy): mixed {
            return json_decode(substr((string) file_get_contents($copy . '/data/' . $name . '.php'), strlen(Store::GUARD)), true);
        };
        $write = static function (string $name, mixed $data) use ($copy): void {
            file_put_contents($copy . '/data/' . $name . '.php', Store::GUARD . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        };
        // Traductions anglaises de l'accueil, de « À propos » et du premier article, reliées à leur original.
        $copies = ['bc9d0bbf76' => ['en0home01', 'en', 'Home'], 'dd462d991e' => ['en0about1', 'en/about', 'About us'],
            '9b388ef72a' => ['en0post01', 'getting-started', 'Getting started with Eclair']];
        foreach ($copies as $id => [$newId, $slug, $title]) {
            $item = $read('content/' . $id);
            $item['tgroup'] = 'g' . $id;
            $write('content/' . $id, $item);
            $write('content/' . $newId, ['id' => $newId, 'slug' => $slug, 'title' => $title, 'lang' => 'en', 'tgroup' => 'g' . $id] + $item);
        }
        $cats = $read('categories');
        $en = $read('content/en0post01');
        $cats[] = ['id' => 'encat00001', 'kind' => 'post', 'name' => 'Guides EN', 'slug' => 'guides-en', 'description' => '', 'seo_title' => '', 'lang' => 'en'];
        $write('categories', $cats);
        $write('content/en0post01', ['category' => 'encat00001'] + $en);
        $site = $read('site');
        $site['languages'] = ['en'];
        $site['i18n'] = ['en' => ['home' => 'en0home01', 'name' => 'Business site', 'tagline' => 'Reference', 'blog_title' => 'Journal']];
        $site['appearance']['mobile_menu'] = true;
        $site['appearance']['splash'] = true;
        $site['appearance']['key_box'] = false;
        $write('site', $site);
        @unlink($copy . '/data/index.php');
        exec('rm -rf ' . escapeshellarg($copy) . '/cache/*');

        $get = static fn (string $path): array => capture_req($port, 'GET', $path);
        $home = $get('/en');
        check('/en : accueil anglais, lang="en", nom traduit, hreflang vers /', $home['status'] === 200 && str_contains($home['body'], '<html lang="en">')
            && str_contains($home['body'], 'hreflang="fr" href="https://eclair.test/"') && str_contains($home['body'], 'Business site'));
        $fr = $get('/');
        check('/ : sélecteur de langue vers /en, menu mobile et écran de démarrage', $fr['status'] === 200 && str_contains($fr['body'], 'class="lang-switch"')
            && str_contains($fr['body'], 'href="/en" hreflang="en"') && str_contains($fr['body'], 'class="nav-toggle"') && str_contains($fr['body'], 'id="mnav"')
            && str_contains($fr['body'], 'class="splash"') && str_contains($fr['body'], 'rel="manifest"') && str_contains($fr['body'], '/assets/site/menu.js'));
        check('menu mobile : une icône dessinée par élément, aucun style en ligne (CSP)', substr_count($fr['body'], 'class="mnav-ic"') >= 3 && !preg_match('~\sstyle="~', $fr['body']));
        check('« L’essentiel » masqué par le réglage, résumé gardé dans la méta-description', !str_contains($fr['body'], 'class="key"') && str_contains($fr['body'], 'name="description"'));
        $about = $get('/en/about');
        check('/en/about : traduction de « À propos », fil d’Ariane depuis /en, sans doublon', $about['status'] === 200
            && str_contains($about['body'], 'hreflang="fr" href="https://eclair.test/a-propos"') && substr_count($about['body'], '"item":"https://eclair.test/en"') === 1);
        $post = $get('/en/blog/getting-started');
        check('article anglais sous /en/blog, absent de /blog', $post['status'] === 200 && $get('/blog/getting-started')['status'] === 404);
        $blogEn = $get('/en/blog');
        $blogFr = $get('/blog');
        check('blog par langue : /en/blog liste l’article anglais, /blog seulement les français', $blogEn['status'] === 200 && str_contains($blogEn['body'], 'Getting started with Eclair')
            && str_contains($blogEn['body'], 'Journal') && !str_contains($blogFr['body'], 'Getting started with Eclair') && str_contains($blogFr['body'], 'hreflang="en" href="https://eclair.test/en/blog"'),
            json_encode([$blogEn['status'], $blogFr['status']]));
        check('catégorie anglaise sous /en/blog, absente de /blog', $get('/en/blog/guides-en')['status'] === 200 && $get('/blog/guides-en')['status'] === 404);
        check('recherche par langue (/en/search)', $get('/en/search?q=Eclair')['status'] === 200 && str_contains($get('/en/search?q=Getting')['body'], 'Getting started'));
        check('menu de la langue du site traduit sur une page anglaise (À propos → About us)', str_contains($about['body'], 'href="/en/about"'));
        check('sitemap : adresses anglaises et alternates', str_contains($get('/sitemap.xml')['body'], 'https://eclair.test/en/blog/getting-started'));
        $manifest = json_decode($get('/manifest.webmanifest')['body'], true);
        check('manifeste : nom, affichage autonome, couleurs', is_array($manifest) && $manifest['name'] === 'Site business' && $manifest['display'] === 'standalone'
            && preg_match('/^#[0-9a-f]{3,8}$/i', (string) $manifest['background_color']));
    } finally {
        proc_terminate($server);
        exec('rm -rf ' . escapeshellarg($copy));
    }
}
