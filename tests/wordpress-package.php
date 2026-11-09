<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Import d'un paquet Eclair Export (fonction « wordpress ») : paquet en deux parties fabriqué ici au format
 * eclair-export 1.0, simulation sans écriture, import réel (adresses exactes, 301 des adresses changées, images et
 * documents, liens internes réécrits, SEO, menus, redirections, accueil), réimport sans doublon, ligne de commande.
 * Inclus par tests/run.php.
 */
declare(strict_types=1);

(static function (array $argv): void {
    if (in_array('--unit', $argv, true)) {
        return;
    }
    section('Import WordPress : paquet Eclair Export (simulation, import, adresses, médias, SEO, redirections, ligne de commande)');
    $old = 'https://www.fournil-ancien.example';
    $tmp = sys_get_temp_dir() . '/eclair-pkg-' . getmypid();
    @mkdir($tmp);
    // Image PNG et PDF minimaux.
    $img = imagecreatetruecolor(640, 400);
    imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 120, 40));
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    $uploads = $old . '/wp-content/uploads/2024/03/';
    $content = static fn (array $over): array => $over + ['status' => 'publish', 'protected' => false, 'parent' => 0, 'menu_order' => 0, 'template' => '',
        'date_gmt' => '2024-03-12T08:30:00+00:00', 'modified_gmt' => '2024-03-12T08:30:00+00:00', 'author' => 1, 'lang' => 'fr', 'translation_group' => '',
        'excerpt' => '', 'source_format' => 'gutenberg', 'featured_media' => 0, 'terms' => [], 'primary_category' => '', 'sticky' => false, 'comments' => 0,
        'meta' => [], 'shortcodes_left' => []];
    $posts = [
        $content(['wp_id' => 12, 'type' => 'post', 'title' => 'Pain au levain', 'slug' => 'pain-au-levain', 'url' => $old . '/2024/03/pain-au-levain/', 'path' => '/2024/03/pain-au-levain/',
            'featured_media' => 50, 'terms' => ['category' => ['recettes'], 'post_tag' => ['levain']],
            'html' => '<h2>Le levain</h2><p>Voir <a href="' . $old . '/notre-histoire/">notre histoire</a> et <a href="' . $uploads . 'tarifs.pdf">nos tarifs</a>.</p>'
                . '<figure><img src="' . $uploads . 'miche-300x188.png" alt="Miche"><figcaption>La miche</figcaption></figure>'
                . '<details><summary>Combien de temps ?</summary><p>Une nuit.</p></details><table><tr><th>Farine</th><th>Eau</th></tr><tr><td>500 g</td><td>350 g</td></tr></table>'
                . '<p>[contact-form-7 id="1"]</p>', 'shortcodes_left' => ['contact-form-7']]),
        $content(['wp_id' => 14, 'type' => 'post', 'title' => 'Brouillon caché', 'slug' => 'brouillon', 'status' => 'draft', 'url' => $old . '/?p=14', 'path' => '/?p=14', 'html' => '<p>Pas prêt.</p>']),
    ];
    $pages = [
        $content(['wp_id' => 20, 'type' => 'page', 'title' => 'Accueil', 'slug' => 'accueil', 'url' => $old . '/', 'path' => '/', 'html' => '<p>Bienvenue au fournil.</p>']),
        $content(['wp_id' => 21, 'type' => 'page', 'title' => 'Notre histoire', 'slug' => 'notre-histoire', 'url' => $old . '/notre-histoire/', 'path' => '/notre-histoire/',
            'html' => '<p>Depuis 1950. <a href="' . $old . '/2024/03/pain-au-levain/">Notre levain</a>.</p>']),
        $content(['wp_id' => 22, 'type' => 'page', 'title' => 'Contact', 'slug' => 'contact', 'url' => $old . '/contact(1)/', 'path' => '/contact(1)/', 'html' => '<p>Écrivez-nous.</p>']),
        $content(['wp_id' => 23, 'type' => 'page', 'title' => 'Actualités', 'slug' => 'actualites', 'url' => $old . '/actualites/', 'path' => '/actualites/', 'html' => '']),
    ];
    $products = [$content(['wp_id' => 30, 'type' => 'product', 'title' => 'Miche de campagne', 'slug' => 'miche-de-campagne', 'url' => $old . '/produit/miche-de-campagne/',
        'path' => '/produit/miche-de-campagne/', 'html' => '<p>Une grosse miche.</p>', 'featured_media' => 50,
        'product' => ['kind' => 'simple', 'price' => '4.50', 'regular_price' => '5.00', 'sale_price' => '4.50', 'currency' => 'EUR', 'sku' => 'MICHE-1', 'gtin' => '',
            'stock_status' => 'instock', 'virtual' => false, 'downloadable' => false, 'gallery' => [], 'brand' => '', 'external_url' => '', 'variations' => []]])];
    $data = [
        'content/posts.json' => json_encode($posts),
        'content/products.json' => json_encode($products),
        'content/pages.json' => json_encode($pages),
        'content/taxonomies.json' => json_encode([
            ['wp_id' => 3, 'taxonomy' => 'category', 'slug' => 'recettes', 'name' => 'Recettes', 'description' => '', 'parent' => 0, 'count' => 1, 'url' => $old . '/category/recettes/', 'path' => '/category/recettes/'],
            ['wp_id' => 4, 'taxonomy' => 'post_tag', 'slug' => 'levain', 'name' => 'Levain', 'description' => '', 'parent' => 0, 'count' => 1, 'url' => $old . '/tag/levain/', 'path' => '/tag/levain/'],
        ]),
        'content/authors.json' => json_encode([['wp_id' => 1, 'slug' => 'admin', 'display_name' => 'Admin', 'email' => '']]),
        'content/menus.json' => json_encode([['wp_id' => 9, 'name' => 'Principal', 'slug' => 'principal', 'locations' => ['primary'], 'items' => [
            ['wp_id' => 90, 'parent' => 0, 'order' => 1, 'title' => 'Notre histoire', 'type' => 'post_type', 'object' => 'page', 'object_id' => 21, 'url' => $old . '/notre-histoire/', 'new_tab' => false],
            ['wp_id' => 91, 'parent' => 90, 'order' => 2, 'title' => 'Recettes', 'type' => 'taxonomy', 'object' => 'category', 'object_id' => 3, 'url' => $old . '/category/recettes/', 'new_tab' => false],
        ]]]),
        'content/settings.json' => json_encode(['woocommerce' => ['shop_page' => 0, 'currency' => 'EUR'], 'name' => 'Le Fournil', 'description' => 'Pain au levain', 'show_on_front' => 'page', 'page_on_front' => 20, 'page_for_posts' => 23]),
        'media/index.json' => json_encode([
            ['wp_id' => 50, 'url' => $uploads . 'miche.png', 'variants' => [$uploads . 'miche-300x188.png'], 'file' => 'media/uploads/2024/03/miche.png', 'mime' => 'image/png',
                'alt' => 'Une miche', 'parent' => 12, 'date_gmt' => '2024-03-12T08:30:00+00:00', 'missing' => false, 'included' => true, 'sha256' => hash('sha256', $png)],
            ['wp_id' => 51, 'url' => $uploads . 'tarifs.pdf', 'variants' => [], 'file' => 'media/uploads/2024/03/tarifs.pdf', 'mime' => 'application/pdf',
                'alt' => '', 'parent' => 0, 'date_gmt' => '2024-03-12T08:30:00+00:00', 'missing' => false, 'included' => true, 'sha256' => hash('sha256', $pdf)],
            ['wp_id' => 52, 'url' => $uploads . 'clip.mp4', 'variants' => [], 'file' => 'media/uploads/2024/03/clip.mp4', 'mime' => 'video/mp4', 'parent' => 0, 'included' => false, 'sha256' => ''],
        ]),
        'seo.json' => json_encode(['global' => ['source' => 'yoast', 'verification' => ['google' => 'GoogleJeton_42-ab', 'bing' => '<meta content="x">BING9']], 'posts' => [
            '12' => ['title' => 'Le vrai pain au levain', 'description' => 'Notre recette de pain au levain.', 'keyword' => 'levain', 'canonical' => '', 'noindex' => false, 'nofollow' => false,
                'og' => ['title' => 'Partage', 'description' => '', 'image' => ''], 'twitter' => [], 'schema' => ''],
            '22' => ['title' => '', 'description' => '', 'keyword' => '', 'canonical' => '', 'noindex' => true, 'nofollow' => false, 'og' => [], 'twitter' => [], 'schema' => ''],
        ], 'terms' => []]),
        'redirects.csv' => "source,target,code,origin,regex\n/ancienne-boutique/,/notre-histoire/,301,redirection,0\n^/promo-(.*)$,/,301,redirection,1\n/supprime/,,410,redirection,0\n"
            . "/category/recettes/,eclair:term:category:recettes,301,migration,0\n/tag/levain/,eclair:term:post_tag:levain,301,migration,0\n/author/admin/,eclair:author:admin,301,migration,0\n/feed/,eclair:feed,301,migration,0\n",
        'urls.csv' => "url,kind,ref\n$old/2024/03/pain-au-levain/,content,post:12\n",
        'report.json' => json_encode(['shortcodes' => ['contact-form-7' => ['count' => 1, 'contents' => 1, 'examples' => []]], 'widgets' => []]),
    ];
    $files = [];
    foreach ($data as $name => $bytes) {
        $files[$name] = ['bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }
    $data['manifest.json'] = json_encode(['format' => 'eclair-export', 'format_version' => '1.0', 'package' => 'eclair-export-fournil-test',
        'source' => ['url' => $old, 'name' => 'Le Fournil'], 'parts' => [['n' => 1, 'file' => 'eclair-export-fournil-test.zip'], ['n' => 2, 'file' => 'eclair-export-fournil-test-part2.zip']],
        'files' => $files]);
    $zip = new ZipArchive();
    $zip->open($tmp . '/eclair-export-fournil-test.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($data as $name => $bytes) {
        $zip->addFromString($name, $bytes);
    }
    $zip->addFromString('media/uploads/2024/03/tarifs.pdf', $pdf);
    $zip->close();
    $zip->open($tmp . '/eclair-export-fournil-test-part2.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('part.json', '{"part":2}');
    $zip->addFromString('media/uploads/2024/03/miche.png', $png);
    $zip->addFromString('../../evil.php', '<?php echo "x";');
    $zip->close();

    [$copy, $port, $server] = serve('wp-package');
    [$psiPort, $psiServer] = start_server([__DIR__ . '/fake-psi.php'], [], 64001, 65000);
    file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['pagespeed' => 'http://127.0.0.1:$psiPort/pagespeedonline/v5']);\n");
    $upload = static function (object $c, array $fields, array $parts): array {
        $post = $fields;
        foreach ($parts as $i => $file) {
            $post['parts[' . $i . ']'] = new CURLFile($file, 'application/zip', basename($file));
        }
        $ch = curl_init('http://127.0.0.1:' . $c->port . '/gestion/x/wordpress');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_PROXY => '',
            CURLOPT_POSTFIELDS => $post, CURLOPT_TIMEOUT => 60]);
        $body = (string) curl_exec($ch);
        return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body];
    };
    $run = static function (object $c): bool {
        $page = $c->req('GET', '/gestion/x/wordpress');
        preg_match('/"csrf":"([a-f0-9]+)"/', $page['body'], $m);
        for ($i = 0; $i < 20; $i++) {
            $r = json_decode($c->req('POST', '/gestion/x/wordpress/step', '{}', ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])['body'], true);
            if (!empty($r['done'])) {
                return true;
            }
        }
        return false;
    };
    try {
        $c = client($port);
        $form = $c->req('GET', '/');
        $c->req('POST', '/', $c->token($form['body']) + [
            'ui' => 'fr', 'site_name' => 'Mon site', 'site_tagline' => '', 'site_lang' => 'fr', 'site_type' => 'blog',
            'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
        ], ['Accept: application/json']);
        $c->login('camille@example.com', 'Un-mot-de-passe-solide');
        $c->req('POST', '/gestion/extensions', $c->token($c->req('GET', '/gestion/extensions')['body']) + ['action' => 'add', 'id' => 'wordpress']);
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('page Import WordPress : envoi d’un paquet en plusieurs parties', $page['status'] === 200 && str_contains($page['body'], 'name="parts[]"') && str_contains($page['body'], 'multiple'));
        $upload($c, $c->token($page['body']) + ['action' => 'package'], [$tmp . '/eclair-export-fournil-test.zip']);
        $page = $c->req('GET', '/gestion/x/wordpress');
        $c->req('POST', '/gestion/x/wordpress', $c->token($page['body']) + ['action' => 'simulate', 'keep' => '1', 'site' => '1', 'menus' => '1']);
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('partie manquante signalée, rien n’est lancé', str_contains($page['body'], 'Parties manquantes : eclair-export-fournil-test-part2.zip') && !str_contains($page['body'], 'data-wp-run'));
        $upload($c, $c->token($page['body']) + ['action' => 'package'], [$tmp . '/eclair-export-fournil-test-part2.zip']);
        $before = count(glob($copy . '/data/content/*.php') ?: []);

        // Simulation : rien n'est écrit.
        $page = $c->req('GET', '/gestion/x/wordpress');
        $c->req('POST', '/gestion/x/wordpress', $c->token($page['body']) + ['action' => 'simulate', 'keep' => '1', 'site' => '1', 'menus' => '1']);
        check('simulation menée à son terme', $run($c));
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('simulation : bilan annoncé, aucun contenu, média ni redirection créés', str_contains($page['body'], 'Simulation : voici ce que l’import ferait')
            && str_contains($page['body'], '2 articles, 3 pages, 1 produits') && count(glob($copy . '/data/content/*.php') ?: []) === $before
            && !is_dir($copy . '/media/files') && !glob($copy . '/media/20*') && !str_contains((string) @file_get_contents($copy . '/data/redirects.php'), 'ancienne-boutique'),
            strip_tags((string) preg_replace('~.*<section class="card" aria-labelledby="wp-pdone">(.*?)</section>.*~s', '$1', $page['body'])));
        check('simulation : bouton pour importer pour de vrai', str_contains($page['body'], 'Importer pour de vrai'));

        // Import réel.
        $c->req('POST', '/gestion/x/wordpress', $c->token($page['body']) + ['action' => 'import', 'keep' => '1', 'site' => '1', 'menus' => '1']);
        check('import mené à son terme', $run($c));
        $page = $c->req('GET', '/gestion/x/wordpress');
        $report = strip_tags((string) preg_replace('~.*<section class="card" aria-labelledby="wp-pdone">(.*?)</section>.*~s', '$1', $page['body']));
        check('bilan : contenus, adresses, médias, SEO, éléments non migrés listés', str_contains($report, '2 articles, 3 pages, 1 produits') && str_contains($report, '1 images et 1 documents')
            && str_contains($report, 'clip.mp4') && str_contains($report, '[contact-form-7]') && str_contains($report, 'expression régulière'), $report);
        $v = client($port);
        $post = $v->req('GET', '/2024/03/pain-au-levain/');
        check('article servi à son adresse d’origine exacte', $post['status'] === 200 && str_contains($post['body'], 'Le levain'));
        $dates = array_map(static fn (string $f): int => (int) (json_decode(substr((string) file_get_contents($f), 15), true)['date'] ?? 0), glob($copy . '/data/content/*.php') ?: []);
        check('dates d’origine gardées, y compris après la réécriture des liens', in_array(strtotime('2024-03-12T08:30:00+00:00'), $dates, true) && max($dates) < time() + 86400, (string) max($dates));
        check('SEO repris : titre et description', str_contains($post['body'], '<title>Le vrai pain au levain') && str_contains($post['body'], 'Notre recette de pain au levain.'));
        check('image locale (la vignette -300x188 mène à l’original importé), légende gardée', str_contains($post['body'], '/media/') && str_contains($post['body'], 'La miche')
            && !str_contains($post['body'], 'fournil-ancien.example/wp-content'));
        check('lien interne réécrit vers la page importée, PDF servi depuis le site', str_contains($post['body'], 'href="/notre-histoire/"')
            && (bool) preg_match('~href="(/media/files/2024/03/tarifs\.pdf)"~', $post['body'], $pm));
        check('PDF copié tel quel dans media/files', is_file($copy . '/media/files/2024/03/tarifs.pdf') && file_get_contents($copy . '/media/files/2024/03/tarifs.pdf') === $pdf);
        check('questions-réponses et tableau convertis en blocs', str_contains($post['body'], 'Combien de temps ?') && str_contains($post['body'], '<table') && str_contains($post['body'], '350 g'));
        check('code court inconnu resté en texte', str_contains($post['body'], '[contact-form-7 id=&quot;1&quot;]'));
        $prod = $v->req('GET', '/produit/miche-de-campagne/');
        check('produit WooCommerce : boutique ouverte, servi à son adresse, prix et prix barré', $prod['status'] === 200 && str_contains($prod['body'], 'Miche de campagne')
            && str_contains($prod['body'], '4,50') && str_contains($prod['body'], 'Prix habituel'), (string) $prod['status']);
        check('page d’accueil reprise', str_contains($v->req('GET', '/')['body'], 'Bienvenue au fournil.'));
        check('nom du site repris', str_contains($v->req('GET', '/')['body'], 'Le Fournil'));
        $moved = $v->req('GET', '/contact(1)/');
        $to = (string) parse_url((string) ($moved['headers']['location'] ?? ''), PHP_URL_PATH);
        check('adresse que l’on ne peut pas garder : une seule 301 vers la nouvelle', $moved['status'] === 301 && (bool) preg_match('~^/contact(-\d+)?$~', $to), $to . ' ' . $moved['status']);
        check('noindex repris', $to !== '' && str_contains($v->req('GET', $to)['body'], 'noindex, follow'));
        $r = $v->req('GET', '/category/recettes/');
        check('archive de catégorie : 301 vers la catégorie Éclair', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/blog/recettes'), (string) ($r['headers']['location'] ?? $r['status']));
        $r = $v->req('GET', '/tag/levain/');
        check('étiquette : 301 vers le blog', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/blog'));
        $r = $v->req('GET', '/ancienne-boutique/');
        check('redirection existante reprise', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/notre-histoire/'));
        $r = $v->req('GET', '/actualites/');
        check('page « Articles » de WordPress : 301 vers le blog', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/blog'));
        check('flux : 301 vers le flux d’Éclair', str_ends_with($v->req('GET', '/feed/')['headers']['location'] ?? '', '/feed.xml'));
        $r = $v->req('GET', '/wp-content/uploads/2024/03/miche.png');
        check('ancienne adresse d’une image : 301 vers l’image reprise', $r['status'] === 301 && (bool) preg_match('~/media/.+\.png$~', $r['headers']['location'] ?? ''), (string) ($r['headers']['location'] ?? $r['status']));
        $r2 = $v->req('GET', '/wp-content/uploads/2024/03/miche-300x188.png');
        check('ancienne adresse d’une miniature : 301 vers la même image (correspondance exacte, une seule redirection)', $r2['status'] === 301
            && ($r2['headers']['location'] ?? '') === ($r['headers']['location'] ?? '-'), (string) ($r2['headers']['location'] ?? $r2['status']));
        $r = $v->req('GET', '/wp-content/uploads/2024/03/tarifs.pdf');
        check('ancienne adresse d’un PDF : 301 vers le PDF repris', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/media/files/2024/03/tarifs.pdf'), (string) ($r['headers']['location'] ?? $r['status']));
        check('fichier non repris (vidéo) : pas de redirection inventée', $v->req('GET', '/wp-content/uploads/2024/03/clip.mp4')['status'] === 404);
        $home = $v->req('GET', '/')['body'];
        check('balises de vérification Google et Bing reprises (nettoyées)', str_contains($home, 'name="google-site-verification" content="GoogleJeton_42-ab"')
            && str_contains($home, 'name="msvalidate.01" content="metacontentxBING9"'));
        check('/?p=12 : 301 vers l’adresse exacte', str_ends_with($v->req('GET', '/?p=12')['headers']['location'] ?? '', '/2024/03/pain-au-levain/'));
        check('brouillon importé en brouillon (non publié)', $v->req('GET', '/blog/brouillon')['status'] === 404);
        check('menu repris (sous-menu mis à plat)', str_contains($v->req('GET', '/notre-histoire/')['body'], '>Recettes<'));
        check('aucun fichier de l’archive extrait hors des entrées attendues', !is_file($copy . '/evil.php') && !is_file(dirname($copy) . '/evil.php'));

        // Rapport de migration pour le client.
        $rep = $c->req('GET', '/gestion/x/wordpress/report?lang=fr');
        check('rapport : page autonome, politique de sécurité propre, sans script', $rep['status'] === 200 && str_contains($rep['body'], 'Rapport de migration vers Éclair')
            && str_contains($rep['headers']['content-security-policy'] ?? '', "default-src 'none'") && !str_contains($rep['body'], '<script'));
        check('rapport : contenus, ancienne et nouvelle adresse, adresses vérifiées sans 404', str_contains($rep['body'], 'Pain au levain') && str_contains($rep['body'], '/2024/03/pain-au-levain/')
            && str_contains($rep['body'], 'Migration réussie') && str_contains($rep['body'], 'Redirections créées') && str_contains($rep['body'], '/ancienne-boutique'));
        check('rapport : éléments non migrés avec leur raison, liste de contrôle SEO', str_contains($rep['body'], 'clip.mp4') && str_contains($rep['body'], 'expression régulière')
            && str_contains($rep['body'], 'Liste de contrôle SEO') && str_contains($rep['body'], 'Zéro erreur 404'));
        check('rapport en espagnol et en anglais', str_contains($c->req('GET', '/gestion/x/wordpress/report?lang=es')['body'], 'Informe de migración a Éclair')
            && str_contains($c->req('GET', '/gestion/x/wordpress/report?lang=en')['body'], 'Migration report to Éclair'));
        $dl = $c->req('GET', '/gestion/x/wordpress/report?lang=fr&download=1');
        check('rapport : téléchargement en fichier HTML', str_contains($dl['headers']['content-disposition'] ?? '', 'attachment; filename="rapport-migration-fournil-ancien-example-'));
        $page = $c->req('GET', '/gestion/x/wordpress');
        check('écran : liens vers le rapport, mesures de vitesse, bloc « Préparé par », sans attribut style', str_contains($page['body'], 'x/wordpress/report?lang=es')
            && str_contains($page['body'], 'name="which"') && str_contains($page['body'], 'name="company"') && !preg_match('~<[^>]+\sstyle="~', $page['body']));
        $c->req('POST', '/gestion/x/wordpress', $c->token($page['body']) + ['action' => 'branding', 'name' => 'Thierry Test', 'company' => 'Agence <b>Test</b>', 'email' => 'pas-un-email', 'website' => 'javascript:alert(1)']);
        $c->req('POST', '/gestion/x/wordpress', $c->token($c->req('GET', '/gestion/x/wordpress')['body']) + ['action' => 'speed', 'which' => 'old', 'url' => 'https://www.fournil-ancien.example/']);
        $c->req('POST', '/gestion/x/wordpress', $c->token($c->req('GET', '/gestion/x/wordpress')['body']) + ['action' => 'speed', 'which' => 'new', 'url' => 'https://www.fournil-neuf.example/']);
        $bad = $c->req('POST', '/gestion/x/wordpress', $c->token($c->req('GET', '/gestion/x/wordpress')['body']) + ['action' => 'speed', 'which' => 'new', 'url' => 'file:///etc/passwd']);
        $rep = $c->req('GET', '/gestion/x/wordpress/report?lang=fr')['body'];
        check('vitesse avant/après : scores et évolution', str_contains($rep, 'Vitesse avant / après') && str_contains($rep, '<td class="n">42</td>') && str_contains($rep, '<strong>98</strong>')
            && str_contains($rep, '+56 pts') && str_contains($rep, '6,2 s') && str_contains($rep, '1,1 s'));
        check('vitesse : adresse non web refusée', !str_contains((string) json_encode(Eclair\Store::read('x', null)), 'passwd') && !str_contains($rep, 'passwd'));
        check('bloc « Préparé par » : texte nettoyé, e-mail et site invalides écartés', str_contains($rep, 'Thierry Test · Agence Test') && !str_contains($rep, 'javascript:') && !str_contains($rep, 'pas-un-email'));

        // Réimport : aucun doublon.
        $count = count(glob($copy . '/data/content/*.php') ?: []);
        $page = $c->req('GET', '/gestion/x/wordpress');
        $c->req('POST', '/gestion/x/wordpress', $c->token($page['body']) + ['action' => 'import', 'keep' => '1', 'site' => '1', 'menus' => '1']);
        $run($c);
        check('réimport du même paquet : aucun doublon', count(glob($copy . '/data/content/*.php') ?: []) === $count
            && str_contains($c->req('GET', '/gestion/x/wordpress')['body'], '6 déjà présents'));

        // Ligne de commande : simulation en JSON.
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($copy . '/app/bundled/wordpress/lib/cli.php') . ' import ' . escapeshellarg($tmp . '/eclair-export-fournil-test.zip')
            . ' ' . escapeshellarg($tmp . '/eclair-export-fournil-test-part2.zip') . ' --dry-run --json 2>&1', $out, $code);
        $json = json_decode(implode("\n", $out), true);
        check('ligne de commande : simulation, bilan JSON', $code === 0 && ($json['dry'] ?? false) === true && ($json['report']['skipped'] ?? 0) === 6, implode("\n", array_slice($out, -5)));
        $web = $v->req('GET', '/app/bundled/wordpress/lib/cli.php');
        check('ligne de commande : jamais exécutée depuis le web', $web['status'] === 404 || $web['status'] === 403);
    } finally {
        proc_terminate($server);
        proc_terminate($psiServer);
        exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($tmp));
    }
})($argv);
