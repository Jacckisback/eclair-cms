<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Import WordPress (fonction livrée « wordpress ») : lecture d'un export WXR sans DOCTYPE ni réseau, conversion du HTML en
 * blocs, import par lots avec adresses exactes, alias ?p= et images copiées depuis un faux ancien site. Inclus par
 * tests/run.php.
 */
declare(strict_types=1);

require_once ECLAIR_ROOT . '/app/bundled/wordpress/lib/Wxr.php';

use Eclair\Store;
use EclairExt\Wordpress\Wxr;

(static function (array $argv): void {
    $wxr = static fn (string $site, string $items): string => '<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>Le Fournil d’Antan</title><link>' . $site . '</link>
  <wp:wxr_version>1.2</wp:wxr_version><wp:base_site_url>' . $site . '</wp:base_site_url><wp:base_blog_url>' . $site . '</wp:base_blog_url>
  <wp:category><wp:term_id>3</wp:term_id><wp:category_nicename>recettes</wp:category_nicename><wp:category_parent></wp:category_parent><wp:cat_name><![CDATA[Recettes]]></wp:cat_name></wp:category>
' . $items . '
</channel></rss>';
    $item = static fn (int $id, string $type, string $status, string $title, string $link, string $slug, string $html, string $extra = ''): string => '
  <item><title>' . htmlspecialchars($title) . '</title><link>' . $link . '</link>
    <content:encoded><![CDATA[' . $html . ']]></content:encoded><excerpt:encoded><![CDATA[]]></excerpt:encoded>
    <wp:post_id>' . $id . '</wp:post_id><wp:post_date>2024-03-12 09:30:00</wp:post_date><wp:post_date_gmt>2024-03-12 08:30:00</wp:post_date_gmt>
    <wp:post_name>' . $slug . '</wp:post_name><wp:status>' . $status . '</wp:status><wp:post_type>' . $type . '</wp:post_type>' . $extra . '</item>';

    section('Import WordPress : lecture de l’export (WXR) et conversion en blocs');
    check('fichier avec DOCTYPE ou entités : refusé avant lecture', is_string(Wxr::parse('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "b">]><rss/>'))
        && is_string(Wxr::parse('<html><body>pas un export</body></html>')) && is_string(Wxr::parse('<rss><channel><title>x</title></channel></rss>')));
    $site = 'https://ancien.example';
    $sample = $wxr($site, $item(12, 'post', 'publish', 'Pain au levain', $site . '/2024/03/pain-au-levain/', 'pain-au-levain', '<p>Texte</p>',
            '<category domain="category" nicename="recettes"><![CDATA[Recettes]]></category><wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>99</wp:meta_value></wp:postmeta>')
        . $item(13, 'page', 'publish', 'Café crème', $site . '/caf%c3%a9-cr%c3%a8me/', 'caf%c3%a9-cr%c3%a8me', 'Ligne un.' . "\n\n" . 'Ligne deux.')
        . $item(14, 'post', 'publish', 'Liens simples', $site . '/?p=14', 'liens-simples', '<p>x</p>')
        . $item(15, 'post', 'inherit', 'Révision', $site . '/?p=15', '', '<p>r</p>')
        . $item(16, 'post', 'private', 'Privé', $site . '/prive/', 'prive', '<p>p</p>')
        . $item(99, 'attachment', 'inherit', 'levain', $site . '/levain/', 'levain', '', '<wp:attachment_url>' . $site . '/wp-content/uploads/2024/03/levain.png</wp:attachment_url>'));
    $export = Wxr::parse($sample);
    $items = is_array($export) ? array_column($export['items'], null, 'wp') : [];
    check('export lu : articles et pages, révisions écartées, privé en brouillon, pièces jointes et catégories', is_array($export) && count($items) === 4
        && ($items[16]['status'] ?? '') === 'draft' && ($export['attachments'][99] ?? '') === $site . '/wp-content/uploads/2024/03/levain.png'
        && ($export['categories']['recettes'] ?? '') === 'Recettes' && ($items[12]['cats'] ?? []) === ['recettes' => 'Recettes'] && ($items[12]['thumb'] ?? 0) === 99,
        is_string($export) ? $export : '');
    check('adresses exactes : /2024/03/pain-au-levain/, accents décodés ; lien ?p= sans adresse (alias)', ($items[12]['route'] ?? '') === '/2024/03/pain-au-levain/'
        && ($items[13]['route'] ?? '') === '/café-crème/' && ($items[14]['route'] ?? 'x') === '' && Wxr::route('https://autre.example/x/', $site) === ''
        && Wxr::route('https://www.ancien.example/x/', $site) === '/x/');
    [$blocks, $stats] = Wxr::blocks('<!-- wp:heading --><h2>Le levain</h2><!-- /wp:heading --><p>Un <strong>bon</strong> pain [gallery ids="1,2"]</p>'
        . '<ul><li>Farine</li><li>Eau<ul><li>tiède</li></ul></li></ul><figure><img src="https://ancien.example/a.png" alt="Miche"><figcaption>La miche</figcaption></figure>'
        . '<blockquote><p>Le pain, c’est la vie.</p><cite>Un boulanger</cite></blockquote><script>alert(1)</script><pre>code &lt;b&gt;</pre><hr>'
        . '<table><tr><td>Farine</td><td>500 g</td></tr></table><p><img src="https://cdn.example/b.png" alt="Ailleurs"></p>',
        static fn (string $url): ?string => str_contains($url, 'ancien.example') ? 'media1' : null);
    check('conversion : titre, paragraphe, liste (sous-liste à la suite de son élément), image légendée, citation, code, séparateur, tableau', array_column($blocks, 'type')
        === ['heading', 'paragraph', 'list', 'image', 'quote', 'code', 'divider', 'table', 'paragraph']
        && $blocks[2]['items'] === ['Farine', 'Eau', 'tiède'] && $blocks[3]['media'] === 'media1' && $blocks[3]['caption'] === 'La miche' && $blocks[4]['cite'] === 'Un boulanger'
        && $blocks[5]['code'] === 'code <b>' && $blocks[7]['rows'] === [['Farine', '500 g']], json_encode($blocks, JSON_UNESCAPED_UNICODE));
    [$more] = Wxr::blocks('<details><summary>Prix ?</summary><p>Sur devis.</p></details><details><summary>Délai ?</summary><p>Une semaine.</p></details>'
        . '<figure class="wp-block-gallery"><img src="https://ancien.example/a.png" alt="A"><img src="https://ancien.example/b.png" alt="B"></figure>'
        . '<video src="https://ancien.example/clip.mp4"></video>', static fn (string $url): ?string => 'm' . substr(md5($url), 0, 6));
    check('conversion : questions-réponses en un bloc FAQ, galerie en images, vidéo en lien', array_column($more, 'type') === ['faq', 'image', 'image', 'paragraph']
        && count($more[0]['items']) === 2 && $more[0]['items'][1]['q'] === 'Délai ?' && str_contains($more[3]['text'], 'clip.mp4'), json_encode($more, JSON_UNESCAPED_UNICODE));
    check('bilan : image d’un autre site laissée en lien, script écarté, code court compté', $stats === ['images' => 1, 'missed' => 1, 'shortcodes' => 1, 'dropped' => 1]
        && str_contains($blocks[8]['text'], 'href="https://cdn.example/b.png"'), json_encode($stats));
    [$classic] = Wxr::blocks("Première ligne\nsuite.\n\nDeuxième paragraphe.", static fn (): ?string => null);
    check('éditeur classique (sans balises) : paragraphes retrouvés', count($classic) === 2 && str_contains($classic[0]['text'], 'suite.'));
    // Article classique qui mêle texte libre et balises : mêmes paragraphes que WordPress à l'affichage (wpautop).
    [$mixed, $mixedStats] = Wxr::blocks("Intro en <strong>gras</strong> et <a href=\"https://ancien.example/x/\">lien</a>.\nMême paragraphe.\n\n"
        . '[caption id="attachment_7" align="aligncenter" width="300"]<a href="https://ancien.example/a.png"><img src="https://ancien.example/a-300x200.png" alt="Miche" /></a> La <em>miche</em>[/caption]'
        . "\n\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n\n[embed]https://vimeo.com/123456[/embed]\n<h2>Titre</h2>\nTexte après le titre.\n"
        . "<ul>\n<li>Un<ul><li>Sous un</li></ul></li>\n<li>Deux</li>\n</ul>\n<pre>a\n\nb</pre>\n[formulaire id=\"5\"]\n\nFin.",
        static fn (string $url): ?string => str_contains($url, 'ancien.example') ? 'media1' : null);
    check('éditeur classique avec balises : paragraphes, légende [caption], vidéos (adresse seule, [embed]), sous-liste, <pre> intact',
        array_column($mixed, 'type') === ['paragraph', 'image', 'video', 'video', 'heading', 'paragraph', 'list', 'code', 'paragraph', 'paragraph']
        && str_contains($mixed[0]['text'], '<strong>gras</strong>') && str_contains($mixed[0]['text'], 'Même paragraphe.')
        && $mixed[1]['caption'] === 'La <em>miche</em>' && ($mixed[1]['link'] ?? '') === 'https://ancien.example/a.png'
        && $mixed[2]['url'] === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' && $mixed[3]['url'] === 'https://vimeo.com/123456'
        && $mixed[5]['text'] === 'Texte après le titre.' && $mixed[6]['items'] === ['Un', 'Sous un', 'Deux'] && $mixed[7]['code'] === "a\n\nb"
        && $mixedStats['shortcodes'] === 1 && $mixed[9]['text'] === 'Fin.', json_encode($mixed, JSON_UNESCAPED_UNICODE));
    [$embed] = Wxr::blocks("<!-- wp:paragraph -->\n<p>Deux\nlignes.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:embed -->\n<figure class=\"wp-block-embed\"><div class=\"wp-block-embed__wrapper\">\nhttps://youtu.be/abc123XYZ_0\n</div></figure>\n<!-- /wp:embed -->"
        . '<p><img src="data:image/gif;base64,R0lGOD" data-lazy-src="https://ancien.example/b.png" alt="B"></p>', static fn (string $url): ?string => str_contains($url, 'ancien.example') ? 'media2' : null);
    check('éditeur de blocs : paragraphes laissés tels quels, bloc Embed en vidéo, image à chargement différé retrouvée', array_column($embed, 'type') === ['paragraph', 'video', 'image']
        && !str_contains($embed[0]['text'], '<br') && $embed[2]['media'] === 'media2', json_encode($embed, JSON_UNESCAPED_UNICODE));
    $meta = static fn (array $pairs): string => implode('', array_map(static fn (string $k, string $v): string => '<wp:postmeta><wp:meta_key>' . $k . '</wp:meta_key><wp:meta_value><![CDATA[' . $v . ']]></wp:meta_value></wp:postmeta>', array_keys($pairs), $pairs));
    $seoExport = Wxr::parse($wxr($site,
        $item(31, 'post', 'publish', 'Pain au levain', $site . '/pain/', 'pain', '<p>x</p>', $meta(['_yoast_wpseo_title' => 'Recette du pain au levain maison %%sep%% %%sitename%%',
            '_yoast_wpseo_metadesc' => 'La recette complète, pas à pas.', '_yoast_wpseo_focuskw' => 'pain au levain', '_yoast_wpseo_meta-robots-noindex' => '1',
            '_yoast_wpseo_canonical' => $site . '/pain/']))
        . $item(32, 'post', 'publish', 'Brioche', $site . '/brioche/', 'brioche', '<p>x</p>', $meta(['rank_math_title' => '%title% %sep% %sitename%', 'rank_math_description' => '%excerpt%',
            'rank_math_focus_keyword' => 'brioche,brioche maison', 'rank_math_robots' => 'a:1:{i:0;s:5:"index";}', 'rank_math_canonical_url' => 'https://autre.example/brioche/']))
        . $item(33, 'page', 'publish', 'Contact', $site . '/contact/', 'contact', '<p>x</p>', $meta(['_seopress_titles_title' => 'Nous écrire', '_seopress_robots_index' => 'yes']))));
    $seo = is_array($seoExport) ? array_column($seoExport['items'], 'seo', 'wp') : [];
    check('référencement repris des extensions (Yoast, Rank Math, SEOPress) : titre et description personnalisés, mot-clé, noindex',
        ($seo[31] ?? []) === ['title' => 'Recette du pain au levain maison · Le Fournil d’Antan', 'description' => 'La recette complète, pas à pas.', 'keyword' => 'pain au levain', 'canonical' => '', 'noindex' => true]
        && ($seo[33]['title'] ?? '') === 'Nous écrire' && ($seo[33]['noindex'] ?? false) === true, json_encode($seo, JSON_UNESCAPED_UNICODE));
    check('référencement : simple modèle de l’extension et variable inconnue non repris, canonical vers un autre site gardée',
        ($seo[32] ?? []) === ['title' => '', 'description' => '', 'keyword' => 'brioche', 'canonical' => 'https://autre.example/brioche/', 'noindex' => false], json_encode($seo[32] ?? null, JSON_UNESCAPED_UNICODE));

    // Lecture en flux d'un fichier : mêmes contenus que la lecture d'un texte, sans garder l'export en mémoire.
    $many = '';
    for ($i = 1; $i <= 130; $i++) {
        $many .= $item(100 + $i, 'post', 'publish', 'Article ' . $i, $site . '/2024/03/article-' . $i . '/', 'article-' . $i, '<p>Texte ' . $i . '</p>');
    }
    $stream = sys_get_temp_dir() . '/eclair-wxr-flux-' . getmypid() . '.xml';
    file_put_contents($stream, $wxr($site, $many . $item(999, 'attachment', 'inherit', 'a', $site . '/a/', 'a', '', '<wp:attachment_url>' . $site . '/wp-content/uploads/a.png</wp:attachment_url>')));
    $got = [];
    $head = Wxr::parseFile($stream, static function (array $it) use (&$got): void {
        $got[] = $it;
    });
    $whole = Wxr::parse((string) file_get_contents($stream));
    check('fichier lu en flux : mêmes contenus, catégories et fichiers joints que la lecture en mémoire', is_array($head) && is_array($whole) && $got === $whole['items']
        && $head['count'] === 130 && $head['site'] === $whole['site'] && $head['categories'] === $whole['categories'] && $head['attachments'] === $whole['attachments']
        && ($got[129]['route'] ?? '') === '/2024/03/article-130/' && Wxr::MAX === Eclair\Content::MAX_ITEMS, is_string($head) ? $head : (string) count($got));
    // Déclaration DOCTYPE à cheval sur deux morceaux lus (1 Mio) : refusée quand même, avant toute lecture XML.
    file_put_contents($stream, '<?xml version="1.0"?><!--' . str_repeat('x', 1048576 - 30) . '--><!DOC' . 'TYPE x [<!ENTITY a "b">]><rss/>');
    $none = static function (array $it): void {
    };
    check('fichier en flux : DOCTYPE refusé même à cheval sur deux morceaux, entité seule aussi', is_string(Wxr::parseFile($stream, $none))
        && str_contains((string) Wxr::parseFile($stream, $none), 'DOCTYPE'));
    file_put_contents($stream, substr($wxr($site, $many), 0, -900));
    $cut = Wxr::parseFile($stream, $none);
    check('fichier tronqué : signalé, jamais importé à moitié sans le dire', is_string($cut) && str_contains($cut, 'incomplet'), is_string($cut) ? $cut : 'accepté');
    file_put_contents($stream, '<html><body>pas un export</body></html>');
    check('fichier qui n’est pas un export : refusé', is_string(Wxr::parseFile($stream, $none)) && is_string(Wxr::parseFile($stream . '.absent', $none)));
    @unlink($stream);

    if (in_array('--unit', $argv, true)) {
        return;
    }
    section('Import WordPress : parcours complet (faux ancien site, images, adresses, alias, réimport)');
    [$copy, $port, $server] = serve('wordpress');
    $wpDir = sys_get_temp_dir() . '/eclair-fake-wp-' . getmypid();
    @mkdir($wpDir);
    [$wpPort, $wpServer] = start_server([__DIR__ . '/fake-wp.php'], ['FAKE_WP_DIR' => $wpDir], 60001, 64000);
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['wp' => 'http://127.0.0.1:$wpPort']);\n");
    $old = 'http://127.0.0.1:' . $wpPort;
    $file = sys_get_temp_dir() . '/eclair-wxr-' . getmypid() . '.xml';
    file_put_contents($file, $wxr($old, $item(12, 'post', 'publish', 'Pain au levain', $old . '/2024/03/pain-au-levain/', 'pain-au-levain',
            '<h2>Le levain</h2><p>Un <strong>bon</strong> pain.</p><figure><img src="' . $old . '/wp-content/uploads/2024/03/levain-300x200.png" alt="Levain"></figure>',
            '<category domain="category" nicename="recettes"><![CDATA[Recettes]]></category><wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>99</wp:meta_value></wp:postmeta>'
            . $meta(['_yoast_wpseo_title' => 'Le vrai pain au levain', '_yoast_wpseo_metadesc' => 'Notre méthode, du levain à la cuisson.']))
        . $item(13, 'page', 'publish', 'Café crème', $old . '/caf%c3%a9-cr%c3%a8me/', 'caf%c3%a9-cr%c3%a8me', "Notre café.\n\nNotre crème.")
        . $item(14, 'post', 'publish', 'Liens simples', $old . '/?p=14', 'liens-simples', '<p>Ancien lien à paramètre.</p>')
        . $item(99, 'attachment', 'inherit', 'levain', $old . '/levain/', 'levain', '', '<wp:attachment_url>' . $old . '/wp-content/uploads/2024/03/levain.png</wp:attachment_url>')));
    $upload = static function (object $c, array $fields) use ($file): array {
        $ch = curl_init('http://127.0.0.1:' . $c->port . '/gestion/x/wordpress');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_PROXY => '',
            CURLOPT_POSTFIELDS => $fields + ['wxr' => new CURLFile($file, 'text/xml', 'export.xml')], CURLOPT_TIMEOUT => 60]);
        $body = (string) curl_exec($ch);
        return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body];
    };
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Fournil Neuf', 'site_tagline' => 'Pain au levain', 'site_lang' => 'fr', 'site_type' => 'blog',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $c->req('POST', '/gestion/extensions', $c->token($c->req('GET', '/gestion/extensions')['body']) + ['action' => 'add', 'id' => 'wordpress']);
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('page Import WordPress : envoi de l’export, sans attribut style (CSP)', $page['status'] === 200 && str_contains($page['body'], 'name="wxr"')
            && !preg_match('~<[^>]+\sstyle="~', $page['body']));
        $upload($c, $c->token($page['body']) + ['action' => 'upload', 'keep' => '1', 'images' => '1']);
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('export lu : import prêt, rien encore créé', str_contains($page['body'], 'data-wp-run') && str_contains($page['body'], '0 contenus sur 3'));
        preg_match('/"csrf":"([a-f0-9]+)"/', $page['body'], $m);
        for ($i = 0, $done = false; $i < 10 && !$done; $i++) {
            $r = json_decode($c->req('POST', '/gestion/x/wordpress/step', '{}', ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])['body'], true);
            $done = (bool) ($r['done'] ?? false);
        }
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('import terminé : bilan (2 articles, 1 page, 2 adresses exactes, 3 alias, images copiées)', $done && str_contains($page['body'], '2 articles et 1 pages importés')
            && str_contains($page['body'], '2 adresses reprises à l’identique, 3 anciennes adresses') && str_contains($page['body'], 'images copiées dans la médiathèque, 0 laissées'),
            strip_tags((string) preg_replace('~.*<section class="card" aria-labelledby="wp-done">(.*?)</section>.*~s', '$1', $page['body'])));
        $v = client($port);
        $post = $v->req('GET', '/2024/03/pain-au-levain/');
        check('article servi à son adresse WordPress exacte, contenu converti, image locale', $post['status'] === 200 && str_contains($post['body'], '<h2')
            && str_contains($post['body'], 'Un <strong>bon</strong> pain.') && str_contains($post['body'], '/media/') && !str_contains($post['body'], ':' . $wpPort . '/wp-content'));
        check('titre et description saisis dans Yoast SEO repris sur la page', str_contains($post['body'], '<title>Le vrai pain au levain</title>')
            && str_contains($post['body'], '<meta name="description" content="Notre méthode, du levain à la cuisson.">'));
        $log = (string) @file_get_contents($wpDir . '/log.jsonl');
        check('image d’origine téléchargée (pas la vignette -300x200), une seule fois pour l’article et sa une', str_contains($log, '/2024/03/levain.png')
            && !str_contains($log, 'levain-300x200') && substr_count($log, 'levain.png') === 1, $log);
        check('page accentuée servie', $v->req('GET', '/caf%C3%A9-cr%C3%A8me/')['status'] === 200);
        $alias = $v->req('GET', '/?p=14');
        check('/?p=14 (permaliens simples) : 301 vers la nouvelle adresse', $alias['status'] === 301 && str_contains($alias['headers']['location'] ?? '', '/blog/liens-simples'),
            (string) ($alias['headers']['location'] ?? ''));
        check('/?p=12 : 301 vers l’adresse exacte', str_ends_with($v->req('GET', '/?p=12')['headers']['location'] ?? '', '/2024/03/pain-au-levain/'));
        check('catégorie de l’export créée', str_contains($v->req('GET', '/blog/recettes')['body'], 'Pain au levain'));
        // Réimport du même export : aucun doublon.
        $before = count(glob($copy . '/data/content/*.php') ?: []);
        $page = $c->req('GET', '/gestion/x/wordpress');
        $upload($c, $c->token($page['body']) + ['action' => 'upload', 'keep' => '1', 'images' => '1']);
        $c->req('POST', '/gestion/x/wordpress', $c->token($c->req('GET', '/gestion/x/wordpress')['body']) + ['action' => 'step']);
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('réimport du même export : aucun doublon, contenus signalés déjà présents', count(glob($copy . '/data/content/*.php') ?: []) === $before
            && str_contains($page['body'], '(3 déjà présents)'));
        check('import terminé : file d’attente et fichiers joints retirés du disque, fichier de travail réduit au bilan', !is_dir($copy . '/data/ext/wordpress/queue')
            && !is_file($copy . '/data/ext/wordpress/attachments.php') && filesize($copy . '/data/ext/wordpress/job.php') < 2000);

        // Gros export, en ligne de commande : lu en flux, rangé par paquets de 50, importé sans limite d'envoi ni de durée.
        $big = '';
        for ($i = 1; $i <= 120; $i++) {
            $big .= $item(500 + $i, 'post', 'publish', 'Série ' . $i, $old . '/serie/numero-' . $i . '/', 'numero-' . $i, "Premier paragraphe $i.\n\nSecond paragraphe $i.",
                $i === 7 ? $meta(['rank_math_description' => 'Description de la série sept.']) : '');
        }
        file_put_contents($file, $wxr($old, $big));
        $cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($copy . '/app/bundled/wordpress/lib/cli.php') . ' import ';
        exec($cli . escapeshellarg($file) . ' --no-images --json 2>&1', $lines, $code);
        $bilan = json_decode(implode("\n", $lines), true);
        check('ligne de commande : export .xml importé (120 articles, adresses gardées)', $code === 0 && ($bilan['report']['posts'] ?? 0) === 120 && ($bilan['report']['routes'] ?? 0) === 120
            && ($bilan['report']['errors'] ?? null) === [], implode(' ', array_slice($lines, 0, 3)));
        $serie = $v->req('GET', '/serie/numero-77/');
        $seven = $v->req('GET', '/serie/numero-7/');
        check('…contenus servis à leur adresse, paragraphes de l’éditeur classique, description SEO reprise', $serie['status'] === 200
            && str_contains($serie['body'], '<p>Premier paragraphe 77.</p>') && str_contains($serie['body'], '<p>Second paragraphe 77.</p>')
            && str_contains($seven['body'], '<meta name="description" content="Description de la série sept.">'));
        $found = $v->req('GET', '/recherche?q=' . rawurlencode('série 118'));
        check('…et trouvés par la recherche (index de recherche écrit par lots)', str_contains($found['body'], '/serie/numero-118/'));
        exec($cli . escapeshellarg($file) . ' --no-images --json 2>&1', $again, $code2);
        check('ligne de commande : réimport sans doublon', $code2 === 0 && (json_decode(implode("\n", $again), true)['report']['skipped'] ?? 0) === 120);
        exec($cli . escapeshellarg($file . '.absent.xml') . ' 2>&1', $missing, $code3);
        check('ligne de commande : fichier introuvable, erreur claire', $code3 === 1 && str_contains(implode(' ', $missing), 'introuvable'));

        // Import commencé par une version précédente (tout l'export dans le fichier de travail) : repris tel quel.
        $legacy = Wxr::parse($wxr($old, $item(701, 'post', 'publish', 'Ancien format un', $old . '/ancien-format-un/', 'ancien-format-un', '<p>Un.</p>')
            . $item(702, 'page', 'publish', 'Ancien format deux', $old . '/ancien-format-deux/', 'ancien-format-deux', '<p>Deux.</p>')));
        file_put_contents($copy . '/data/ext/wordpress/job.php', Store::GUARD . json_encode(['site' => $old, 'title' => 'x', 'categories' => [], 'attachments' => [], 'items' => $legacy['items'],
            'total' => 2, 'next' => 0, 'keep' => true, 'images' => false, 'started' => time(), 'done' => false,
            'report' => ['posts' => 0, 'pages' => 0, 'skipped' => 0, 'routes' => 0, 'aliases' => 0, 'images' => 0, 'missed' => 0, 'shortcodes' => 0, 'dropped' => 0, 'errors' => []]]));
        $c->req('POST', '/gestion/x/wordpress', $c->token($c->req('GET', '/gestion/x/wordpress')['body']) + ['action' => 'step']);
        check('import commencé avant la mise à jour : terminé sans rien perdre', $v->req('GET', '/ancien-format-un/')['status'] === 200 && $v->req('GET', '/ancien-format-deux/')['status'] === 200);
    } finally {
        proc_terminate($server);
        proc_terminate($wpServer);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($wpDir) . ' ' . escapeshellarg($file));
    }
})($argv);
