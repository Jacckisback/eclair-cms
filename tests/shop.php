<?php
/**
 * Tests des thèmes et du mode boutique, inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\AiTools;
use Eclair\Content;
use Eclair\Settings;
use Eclair\Shop;
use Eclair\Store;
use Eclair\Theme;

/* ─────────────────────────── Thèmes ─────────────────────────── */
section('Thèmes : registre, styles, variantes');
$themes = array_keys(Theme::THEMES);
check('6 thèmes livrés, chacun avec son fichier', $themes === ['eclair', 'editorial', 'atelier', 'studio', 'boutique', 'contraste']
    && !array_filter(array_slice($themes, 1), fn ($id) => !is_file(ECLAIR_ROOT . '/app/theme/themes/' . $id . '.css')));
foreach (array_slice($themes, 1) as $id) {
    $css = (string) file_get_contents(ECLAIR_ROOT . '/app/theme/themes/' . $id . '.css');
    check("thème $id : CSS seul, sans ressource externe", !preg_match('~url\(|@import|expression\(|javascript:|<~i', $css) && substr_count($css, '{') === substr_count($css, '}'));
}
Theme::preview(['theme' => 'eclair']);
$base = Theme::css();
check('boutique fermée : ses styles ne sont pas insérés', !str_contains($base, '.product-main') && !str_contains($base, '/*shop*/'));
Theme::preview(['theme' => 'editorial', 'header' => 'center', 'cards' => 'list', 'accent' => '#b42318']);
$css = Theme::css();
check('aperçu : thème ajouté au style de base', str_contains($css, '--accent:#b42318') && str_starts_with(substr($css, strpos($css, '}') + 1), substr($base, strpos($base, '}') + 1, 200))
    && strlen($css) > strlen($base));
Theme::withShop();
check('fiche produit : styles de la boutique insérés', str_contains(Theme::css(), '.product-main'));
check('aperçu : classes de mise en page', Theme::bodyClass() === 'theme-editorial hdr-center cards-list');
Theme::preview(['theme' => '../../etc/passwd', 'header' => 'x', 'cards' => 'y']);
check('valeurs inconnues : thème et variantes par défaut', Theme::bodyClass() === 'theme-eclair');
Theme::preview(['dark' => 'light']);
check('mode sombre désactivé : bloc retiré', !str_contains(Theme::css(), 'prefers-color-scheme:dark'));
Theme::preview([]);

/* ─────────────────────────── Boutique : unités ─────────────────────────── */
section('Boutique : prix, liens, fiches produit');
check('prix saisis : virgule, symbole, espaces', Shop::amount('1 290,5 €') === '1290.50' && Shop::amount('19.9') === '19.90' && Shop::amount('19') === '19.00');
check('prix invalides refusés', Shop::amount('abc') === '' && Shop::amount('-5') === '' && Shop::amount('12.345') === '' && Shop::amount(['1']) === '');
check('prix affichés selon la langue', Shop::money('1290.00', 'fr') === "1\u{202F}290\u{00A0}€" && Shop::money('19.90', 'en') === '€19.90'
    && Shop::money('19.90', 'de') === "19,90\u{00A0}€" && Shop::money('', 'fr') === '');
check('lien de paiement : https seulement', Shop::link('javascript:alert(1)') === '' && Shop::link('http://buy.stripe.com/x') === ''
    && Shop::link('https://buy.stripe.com/test_abc') === 'https://buy.stripe.com/test_abc' && Shop::link('https://x') === '');
check('référence du produit ajoutée aux liens Stripe', Shop::buyUrl('https://buy.stripe.com/test_abc', 'bol <x> 2') === 'https://buy.stripe.com/test_abc?client_reference_id=bol-x-2'
    && Shop::buyUrl('https://pay.example.com/p', 'a') === 'https://pay.example.com/p'
    && Shop::buyUrl('https://buy.stripe.com/t?client_reference_id=z', 'a') === 'https://buy.stripe.com/t?client_reference_id=z');
Store::write('media', ['gal1' => ['id' => 'gal1', 'file' => '2026/10/a', 'ext' => 'jpg', 'w' => 800, 'h' => 800, 'webp' => [], 'alt' => '', 'name' => 'a.jpg', 'created' => time()]]);
$n = Shop::normalize(['price' => '29,90', 'compare' => '39', 'availability' => 'nope', 'link' => 'https://buy.stripe.com/x', 'gtin' => '978-2-1234-5680-3',
    'condition' => 'broken', 'sku' => 'BOL 01', 'gallery' => ['gal1', 'gal1', '../x', 'absent'],
    'variants' => [['label' => 'S', 'price' => '25'], ['label' => ''], ['label' => '<b>M</b>', 'link' => 'javascript:x', 'availability' => 'preorder']]]);
check('fiche produit nettoyée', $n['price'] === '29.90' && $n['compare'] === '39.00' && $n['availability'] === 'in_stock' && $n['condition'] === 'new'
    && $n['gtin'] === '9782123456803' && $n['sku'] === 'BOL01' && $n['gallery'] === ['gal1'] && count($n['variants']) === 2
    && $n['variants'][1]['label'] === 'M' && $n['variants'][1]['link'] === '', json_encode($n));
check('fiche vide : valeurs sûres', Shop::normalize('x') === Shop::normalize([]) && Shop::normalize([])['variants'] === []);
$offers = Shop::offers(['product' => $n]);
check('variantes : prix et lien hérités du produit', $offers[0]['price'] === '25.00' && $offers[1]['price'] === '29.90' && $offers[1]['link'] === 'https://buy.stripe.com/x');
$out = Shop::offers(['product' => ['availability' => 'out_of_stock'] + $n]);
check('produit épuisé : toutes les variantes épuisées', array_unique(array_column($out, 'availability')) === ['out_of_stock']);
$v2 = Shop::offers(['product' => Shop::normalize(['price' => '25', 'link' => 'https://buy.stripe.com/p25',
    'variants' => [['label' => 'M'], ['label' => 'L', 'price' => '25'], ['label' => 'XL', 'price' => '30']]])]);
check('variante à un autre prix : jamais le lien du produit (Stripe encaisserait son prix)', $v2[0]['link'] === 'https://buy.stripe.com/p25'
    && $v2[1]['link'] === 'https://buy.stripe.com/p25' && $v2[2]['link'] === '');
check('adresse de la boutique libre tant qu’elle n’est pas configurée', !in_array('boutique', Settings::reserved(), true) && !in_array('products', Settings::reserved(), true));
$page = ['type' => 'page', 'title' => 'Boutique', 'slug' => 'boutique', 'blocks' => []];
$kept = Content::build($page, ['id' => 'u1']);
Settings::save(['shop' => ['base' => 'boutique']]);
$again = Content::build($page, ['id' => 'u1'], $kept);
$fresh = Content::build($page, ['id' => 'u1']);
check('mise à jour depuis la 1.2 : une page « boutique » garde son adresse', $kept['slug'] === 'boutique' && $again['slug'] === 'boutique' && $fresh['slug'] === 'boutique-2',
    $kept['slug'] . ' ' . $again['slug'] . ' ' . $fresh['slug']);
Settings::save(['shop' => ['base' => '']]);
check('pays de livraison', Shop::parseCountries('fr, be;ch xx1 FR') === ['FR', 'BE', 'CH'] && Shop::parseCountries('CN，HK、TW') === ['CN', 'HK', 'TW']);
$defs = (string) json_encode(AiTools::definitions());
check('assistant : pas de fiche produit tant que la boutique est fermée', !str_contains($defs, '"enum":["post","page","product"]') && !str_contains($defs, '"product":{'));
Settings::save(['shop' => ['enabled' => true]]);
check('assistant : fiches produit quand la boutique est ouverte', str_contains((string) json_encode(AiTools::definitions()), '"enum":["post","page","product"]'));
Settings::save(['shop' => ['enabled' => false]]);

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Thèmes et boutique : parcours complet');
[$copy, $port, $server] = serve('shop');
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Atelier Test', 'site_tagline' => 'Céramique à Lyon', 'site_lang' => 'fr', 'site_type' => 'business',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    preg_match('/"csrf":"([a-f0-9]+)"/', $c->req('GET', '/gestion')['body'], $m);
    $csrf = $m[1] ?? '';
    $api = static function (string $path, array $data) use ($c, $csrf): array {
        $res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data), ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
        return $res + ['json' => json_decode($res['body'], true)];
    };
    $settingsForm = static function (string $tab, array $fields) use ($c): array {
        $page = $c->req('GET', '/gestion/settings?tab=' . $tab);
        return $c->req('POST', '/gestion/settings', $c->token($page['body']) + ['tab' => $tab] + $fields);
    };
    $site = static fn (): array => json_decode(substr((string) file_get_contents($copy . '/data/site.php'), strlen(Store::GUARD)), true);
    $ld = static function (string $html): array {
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $graph = json_decode($m[1] ?? '', true)['@graph'] ?? [];
        $out = [];
        foreach ($graph as $node) {
            $out[$node['@type']] = $node;
        }
        return $out;
    };
    $cspOk = static function (array $res): bool {
        preg_match('~<style>(.*?)</style>~s', $res['body'], $st);
        return isset($st[1]) && str_contains($res['headers']['content-security-policy'] ?? '', base64_encode(hash('sha256', $st[1], true)));
    };
    $v = client($port);

    // Aperçu en direct de l'apparence.
    $anon = client($port)->req('GET', '/gestion/appearance/preview?theme=studio');
    check('aperçu : réservé aux personnes connectées', $anon['status'] === 302);
    $p = $c->req('GET', '/gestion/appearance/preview?theme=editorial&header=center&cards=list&accent=%23B42318&path=/');
    check('aperçu : apparence essayée sans être enregistrée', $p['status'] === 200 && preg_match('~<body class="[^"]*theme-editorial hdr-center cards-list~', $p['body'])
        && str_contains($p['body'], '--accent:#b42318') && str_contains($p['body'], 'theme-preview.js'));
    check('aperçu : affichable dans l’administration seulement', ($p['headers']['x-frame-options'] ?? '') === 'SAMEORIGIN'
        && str_contains($p['headers']['content-security-policy'] ?? '', "frame-ancestors 'self'") && str_contains($p['headers']['x-robots-tag'] ?? '', 'noindex'));
    check('aperçu : style autorisé par la CSP, jamais mis en cache', $cspOk($p) && ($p['headers']['cache-control'] ?? '') === 'no-store');
    $home = $v->req('GET', '/');
    check('aperçu : le site public ne change pas', !str_contains($home['body'], 'theme-editorial') && preg_match('~<body class="[^"]*theme-eclair~', $home['body']));
    $bad = $c->req('GET', '/gestion/appearance/preview?theme=../x&accent=red;}body{x&font_head=%3Cx&path=//evil.example/');
    check('aperçu : valeurs inconnues ignorées', $bad['status'] === 200 && preg_match('~<body class="[^"]*theme-eclair~', $bad['body']) && !str_contains($bad['body'], 'evil.example') && !str_contains($bad['body'], 'red;}'));
    $settingsForm('appearance', ['theme' => 'studio', 'header' => 'center', 'cards' => 'grid', 'accent' => '#111111', 'font_head' => 'grotesque', 'font_body' => 'grotesque', 'dark' => 'auto', 'credit' => '1']);
    $home = $v->req('GET', '/');
    check('thème enregistré : appliqué au site', preg_match('~<body class="[^"]*theme-studio hdr-center~', $home['body']) && $cspOk($home));
    check('thème : couleur du navigateur = accent', str_contains($home['body'], '<meta name="theme-color" content="#111111">'));

    // CSS personnalisé (data/theme.css) : écrit depuis Réglages › Apparence ou déposé par FTP, il survit aux mises à jour.
    $look = ['theme' => 'studio', 'header' => 'center', 'cards' => 'grid', 'accent' => '#111111', 'font_head' => 'grotesque', 'font_body' => 'grotesque', 'dark' => 'auto', 'credit' => '1'];
    check('CSS personnalisé : champ dans Réglages › Apparence', str_contains($c->req('GET', '/gestion/settings?tab=appearance')['body'], 'name="custom_css"'));
    $settingsForm('appearance', $look + ['custom_css' => ".site-header{outline:3px solid red}\r\n</style><script>alert(1)</script>"]);
    $home = $v->req('GET', '/');
    preg_match('~<style>(.*?)</style>~s', $home['body'], $st);
    check('CSS personnalisé : enregistré dans data/theme.css, ajouté au style de la page', is_file($copy . '/data/theme.css')
        && str_contains($st[1] ?? '', '}.site-header{outline:3px solid red}') && $cspOk($home));
    check('CSS personnalisé : impossible de refermer la balise <style>', substr_count($home['body'], '</style>') === 1 && str_ends_with($st[1] ?? '', '<script>alert(1)<\/script>'));
    $settingsForm('appearance', ['accent' => '#222222', 'custom_css' => str_repeat('p{color:red}', 6000)] + $look);
    check('CSS personnalisé : plus de 64 Ko refusé, rien n’est enregistré', str_contains($c->req('GET', '/gestion/settings?tab=appearance')['body'], 'Le CSS personnalisé dépasse 64 Ko')
        && $site()['appearance']['accent'] === '#111111' && !str_contains((string) file_get_contents($copy . '/data/theme.css'), 'p{color:red}'));
    $v->req('GET', '/');
    check('CSS personnalisé : page servie depuis le cache', ($v->req('GET', '/')['headers']['x-cache'] ?? '') === 'HIT');
    file_put_contents($copy . '/data/theme.css', '.site-footer{outline:4px dashed blue}');
    $ftp = $v->req('GET', '/');
    $again = $v->req('GET', '/');
    check('CSS déposé par FTP : cache vidé, pages régénérées avec lui', ($ftp['headers']['x-cache'] ?? '') !== 'HIT' && str_contains($ftp['body'], '}.site-footer{outline:4px dashed blue}</style>')
        && !str_contains($ftp['body'], 'solid red') && $cspOk($ftp) && ($again['headers']['x-cache'] ?? '') === 'HIT' && $again['body'] === $ftp['body']);
    $p = $c->req('GET', '/gestion/appearance/preview?theme=editorial&path=/');
    $draft = $c->req('GET', '/gestion/appearance/preview?theme=editorial&custom=0&path=/');
    check('aperçu : CSS personnalisé inclus, ou laissé au formulaire (saisie en cours)', str_contains($p['body'], 'dashed blue') && $cspOk($p)
        && !str_contains($draft['body'], 'dashed blue') && str_contains($draft['body'], 'theme-editorial') && $cspOk($draft));
    $settingsForm('appearance', $look);
    check('formulaire sans le champ : feuille de style conservée', is_file($copy . '/data/theme.css'));
    $settingsForm('appearance', $look + ['custom_css' => '']);
    clearstatcache();
    check('CSS personnalisé vidé : fichier supprimé, style d’origine', !is_file($copy . '/data/theme.css') && !str_contains($v->req('GET', '/')['body'], 'dashed blue'));

    // Boutique fermée : rien n'est publié.
    check('boutique fermée : catalogue et flux introuvables', $v->req('GET', '/boutique')['status'] === 404 && $v->req('GET', '/products.xml')['status'] === 404);
    check('éditeur de produit', str_contains($c->req('GET', '/gestion/new/product')['body'], 'id="product-box"'));
    $shopFields = ['shop_enabled' => '1', 'shop_base' => 'boutique', 'shop_title' => '', 'shop_description' => 'Pièces uniques tournées à Lyon.', 'currency' => 'EUR',
        'button' => '', 'shop_per_page' => '12', 'shipping_price' => '4,90', 'free_from' => '60', 'days_min' => '2', 'days_max' => '4', 'countries' => 'fr, be',
        'returns_days' => '14', 'legal' => '', 'terms' => '', 'delivery' => '', 'thanks' => ''];
    // Une page existante à l'adresse de la boutique (site mis à jour depuis la 1.2) n'est ni renommée ni masquée.
    $squat = $api('api/save', ['type' => 'page', 'title' => 'Notre boutique', 'slug' => 'boutique', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Ancienne page.']]]);
    $settingsForm('shop', $shopFields);
    check('page existante à l’adresse de la boutique : ouverture refusée, page intacte', ($squat['json']['item']['slug'] ?? '') === 'boutique'
        && !$site()['shop']['enabled'] && str_contains($v->req('GET', '/boutique')['body'], 'Ancienne page.'));
    $api('api/delete', ['id' => (string) ($squat['json']['item']['id'] ?? ''), 'redirect' => '']);
    $settingsForm('shop', $shopFields);
    check('boutique : ouverture refusée sans mentions légales ni CGV', !$site()['shop']['enabled'] && $v->req('GET', '/boutique')['status'] === 404);
    $legal = $api('api/save', ['type' => 'page', 'title' => 'Mentions légales', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Éditeur : Atelier Test.']]]);
    $terms = $api('api/save', ['type' => 'page', 'title' => 'Conditions générales de vente', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Prix TTC.']]]);
    $shopFields['legal'] = (string) ($legal['json']['item']['id'] ?? '');
    $shopFields['terms'] = (string) ($terms['json']['item']['id'] ?? '');
    $settingsForm('shop', $shopFields + ['action' => 'thanks']);
    $s = $site()['shop'];
    check('boutique ouverte avec mentions légales et CGV publiées', $s['enabled'] === true && $s['shipping']['countries'] === 'FR, BE' && $s['shipping']['price'] === '4.90');
    $thanks = $v->req('GET', '/merci-pour-votre-commande');
    check('page de remerciement créée, hors index', $s['thanks'] !== '' && $thanks['status'] === 200 && str_contains($thanks['body'], 'noindex'));

    // Catégorie de produits, photos, fiche produit.
    $page = $c->req('GET', '/gestion/categories?kind=product');
    $c->req('POST', '/gestion/categories', $c->token($page['body']) + ['name' => 'Bols', 'kind' => 'product', 'slug' => '', 'description' => 'Bols en grès faits main.', 'seo_title' => '']);
    $c->req('POST', '/gestion/categories', $c->token($page['body']) + ['name' => 'Conseils', 'kind' => 'post', 'slug' => '', 'description' => '', 'seo_title' => '']);
    $cats = json_decode(substr((string) file_get_contents($copy . '/data/categories.php'), strlen(Store::GUARD)), true);
    $catId = (string) (array_values(array_filter($cats, fn ($x) => $x['name'] === 'Bols'))[0]['id'] ?? '');
    $postCat = (string) (array_values(array_filter($cats, fn ($x) => $x['name'] === 'Conseils'))[0]['id'] ?? '');
    check('catégorie de produits enregistrée', $catId !== '' && array_values(array_filter($cats, fn ($x) => $x['id'] === $catId))[0]['kind'] === 'product');
    $photo = static fn (string $id): array => ['id' => $id, 'file' => '2026/10/' . $id, 'ext' => 'jpg', 'w' => 1200, 'h' => 1200, 'webp' => [], 'alt' => 'Bol ' . $id, 'name' => $id . '.jpg', 'created' => time()];
    file_put_contents($copy . '/data/media.php', Store::GUARD . json_encode(['ph1' => $photo('ph1'), 'ph2' => $photo('ph2'), 'ph3' => $photo('ph3')]));
    $bol = $api('api/save', ['type' => 'product', 'title' => 'Bol en grès émaillé', 'status' => 'published', 'category' => $catId, 'image' => 'ph1',
        'summary' => 'Un bol tourné à la main dans un grès de Bourgogne, émaillé bleu.',
        'blocks' => [['type' => 'paragraph', 'text' => 'Bol de 14 cm, idéal pour le petit-déjeuner.']],
        'product' => ['price' => '29,90', 'compare' => '39', 'availability' => 'in_stock', 'link' => 'https://buy.stripe.com/test_bol', 'brand' => 'Atelier Test',
            'sku' => 'BOL-01', 'gallery' => ['ph2', 'ph3'], 'variants' => [], 'condition' => 'new', 'digital' => false]]);
    $slug = (string) ($bol['json']['item']['slug'] ?? '');
    check('produit enregistré, adresse dans la boutique', $slug === 'bol-en-gres-emaille' && ($bol['json']['path'] ?? '') === '/boutique/bol-en-gres-emaille', $bol['body']);
    $refused = $api('api/save', ['type' => 'product', 'title' => 'Classé ailleurs', 'status' => 'draft', 'category' => $postCat, 'product' => []]);
    check('produit : catégorie d’articles refusée', ($refused['json']['item']['category'] ?? 'x') === '');
    $res = $v->req('GET', '/boutique/' . $slug);
    $graph = $ld($res['body']);
    check('fiche produit en ligne', $res['status'] === 200 && str_contains($res['body'], 'Bol en grès émaillé') && str_contains($res['body'], 'is-product') && $cspOk($res));
    check('bouton d’achat : lien Stripe et référence du produit', str_contains($res['body'], 'href="https://buy.stripe.com/test_bol?client_reference_id=' . $slug . '"'));
    check('prix, promotion, livraison, retours', str_contains($res['body'], "29,90\u{00A0}€") && str_contains($res['body'], '−23 %')
        && str_contains($res['body'], "Livraison\u{00A0}: 4,90\u{00A0}€ · offerte dès 60\u{00A0}€") && str_contains($res['body'], 'Retours sous 14 jours')
        && str_contains($res['body'], 'Paiement sécurisé par Stripe'));
    check('liens vers les CGV', str_contains($res['body'], 'href="/conditions-generales-de-vente"'));
    $product = $graph['Product'] ?? [];
    $offer = $product['offers'] ?? [];
    check('JSON-LD Product + Offer', ($offer['price'] ?? '') === '29.90' && ($offer['priceCurrency'] ?? '') === 'EUR' && ($offer['availability'] ?? '') === 'https://schema.org/InStock'
        && ($product['brand']['name'] ?? '') === 'Atelier Test' && ($product['sku'] ?? '') === 'BOL-01' && count($product['image'] ?? []) === 3, json_encode($product));
    check('JSON-LD : livraison', ($offer['shippingDetails']['shippingRate']['value'] ?? '') === '4.90' && count($offer['shippingDetails']['shippingDestination'] ?? []) === 2
        && ($offer['shippingDetails']['deliveryTime']['transitTime']['maxValue'] ?? 0) === 4);
    $org = $graph['Organization'] ?? [];
    check('JSON-LD : politique de retour de l’entreprise', ($org['hasMerchantReturnPolicy']['merchantReturnDays'] ?? 0) === 14
        && ($org['hasMerchantReturnPolicy']['applicableCountry'] ?? []) === ['FR', 'BE'], json_encode($org));
    check('JSON-LD : page produit et fil d’Ariane', ($graph['ItemPage']['mainEntity']['@id'] ?? '') === ($product['@id'] ?? '-') && count($graph['BreadcrumbList']['itemListElement'] ?? []) === 4);
    check('Open Graph produit', str_contains($res['body'], '<meta property="og:type" content="product">') && str_contains($res['body'], '<meta property="product:price:amount" content="29.90">'));

    // Variantes, et lien piégé.
    $tasse = $api('api/save', ['type' => 'product', 'title' => 'Tasse à café', 'status' => 'published', 'category' => $catId, 'blocks' => [['type' => 'paragraph', 'text' => 'Tasse de 12 cl.']],
        'product' => ['price' => '18', 'link' => 'https://buy.stripe.com/test_tasse', 'variants' => [
            ['label' => 'Blanche'], ['label' => 'Noire', 'price' => '20', 'link' => 'https://buy.stripe.com/test_noire'], ['label' => 'Bleue', 'availability' => 'out_of_stock'],
            ['label' => 'Géante', 'price' => '25']]]]);
    $tslug = (string) ($tasse['json']['item']['slug'] ?? '');
    $res = $v->req('GET', '/boutique/' . $tslug);
    $group = $ld($res['body'])['ProductGroup'] ?? [];
    check('variantes : choix sans JavaScript', substr_count($res['body'], 'type="radio" name="variant"') === 4 && str_contains($res['body'], "À partir de 18\u{00A0}€")
        && str_contains($res['body'], 'test_tasse?client_reference_id=' . $tslug . '-1') && str_contains($res['body'], 'test_noire?client_reference_id=' . $tslug . '-2')
        && str_contains($res['body'], 'Bleue · Épuisé'));
    check('variante à un autre prix sans lien : bouton désactivé, signalée par l’analyse', str_contains($res['body'], 'Géante · Bientôt disponible')
        && substr_count($res['body'], 'test_tasse?') === 1 && str_contains((string) json_encode($tasse['json']['analysis'] ?? []), 'autre prix sans lien'));
    check('variantes : JSON-LD ProductGroup', count($group['hasVariant'] ?? []) === 3 && ($group['hasVariant'][1]['offers']['price'] ?? '') === '20.00'
        && ($group['hasVariant'][2]['offers']['availability'] ?? '') === 'https://schema.org/OutOfStock', json_encode($group));
    $pichet = $api('api/save', ['type' => 'product', 'title' => 'Pichet', 'status' => 'published', 'product' => ['price' => '35', 'link' => 'https://buy.stripe.com/test_pichet',
        'variants' => [['label' => 'Petit', 'availability' => 'out_of_stock'], ['label' => 'Grand', 'availability' => 'out_of_stock']]]]);
    $res = $v->req('GET', '/boutique/' . ($pichet['json']['item']['slug'] ?? 'x'));
    check('toutes les variantes épuisées : produit affiché épuisé', str_contains($res['body'], 'class="stock stock-out_of_stock"')
        && str_contains($v->req('GET', '/llms.txt')['body'], "35\u{00A0}€ · Épuisé"));
    $trap = $api('api/save', ['type' => 'product', 'title' => 'Vase', 'status' => 'published', 'product' => ['price' => '45', 'link' => 'javascript:alert(1)']]);
    $res = $v->req('GET', '/boutique/' . ($trap['json']['item']['slug'] ?? 'x'));
    check('lien de paiement piégé ignoré, bouton désactivé', $res['status'] === 200 && !str_contains($res['body'], 'javascript:') && str_contains($res['body'], 'Bientôt disponible')
        && !isset($ld($res['body'])['Product']));

    // Catalogue, catégorie, bloc Produits, recherche.
    $cat = $v->req('GET', '/boutique');
    check('catalogue : produits, prix, filtres', $cat['status'] === 200 && str_contains($cat['body'], 'Bol en grès émaillé') && str_contains($cat['body'], 'Tasse à café')
        && str_contains($cat['body'], 'href="/boutique/bols"') && str_contains($cat['body'], 'Pièces uniques tournées à Lyon.') && isset($ld($cat['body'])['CollectionPage']) && $cspOk($cat));
    $bols = $v->req('GET', '/boutique/bols');
    check('catégorie de produits : page pilier', $bols['status'] === 200 && str_contains($bols['body'], 'Bol en grès émaillé') && !str_contains($bols['body'], '>Vase<')
        && str_contains($bols['body'], 'aria-current="page">Bols'));
    check('catégorie d’articles : jamais sous la boutique', $v->req('GET', '/boutique/conseils')['status'] === 404);
    $vitrine = $api('api/save', ['type' => 'page', 'title' => 'Vitrine', 'status' => 'published', 'blocks' => [['type' => 'products', 'title' => 'Nouveautés', 'count' => 2, 'category' => $catId]]]);
    $res = $v->req('GET', '/vitrine');
    check('bloc Produits sur une page', str_contains($res['body'], 'Nouveautés') && substr_count($res['body'], 'class="card product-card"') === 2 && str_contains($res['body'], 'Voir toute la boutique'));
    $search = $v->req('GET', '/recherche?q=gr%C3%A8s');
    check('recherche : produits trouvés avec leur prix', str_contains($search['body'], 'Bol en grès émaillé') && str_contains($search['body'], "29,90\u{00A0}€"));

    // Fichiers pour Google et les IA.
    $feed = $v->req('GET', '/products.xml');
    $xml = simplexml_load_string($feed['body']);
    $items = $xml ? $xml->channel->item : [];
    $g = static fn ($it): object => $it->children('http://base.google.com/ns/1.0');
    $byTitle = [];
    foreach ($items as $it) {
        $byTitle[(string) $it->title] = $g($it);
    }
    check('flux Google Merchant : XML valide, offres achetables seulement', $xml !== false && count($byTitle) === 6 && !isset($byTitle['Vase']) && !isset($byTitle['Tasse à café — Géante']),
        implode(' | ', array_keys($byTitle)));
    $gb = $byTitle['Bol en grès émaillé'] ?? null;
    check('flux : prix barré, promo, livraison', $gb && (string) $gb->price === '39.00 EUR' && (string) $gb->sale_price === '29.90 EUR' && (string) $gb->availability === 'in_stock'
        && count($gb->shipping) === 2 && (string) $gb->shipping[0]->price === '4.90 EUR' && (string) $gb->additional_image_link[1] !== '');
    $gn = $byTitle['Tasse à café — Noire'] ?? null;
    check('flux : variantes regroupées', $gn && (string) $gn->item_group_id !== '' && str_ends_with((string) $gn->id, '-2') && (string) ($byTitle['Tasse à café — Bleue']->availability ?? '') === 'out_of_stock');
    $sitemap = $v->req('GET', '/sitemap.xml')['body'];
    check('sitemap : boutique, catégorie, fiches et photos', simplexml_load_string($sitemap) !== false && str_contains($sitemap, '/boutique</loc>')
        && str_contains($sitemap, '/boutique/bols</loc>') && str_contains($sitemap, '/boutique/' . $slug . '</loc>') && str_contains($sitemap, '/media/2026/10/ph3.jpg'));
    $llms = $v->req('GET', '/llms.txt')['body'];
    check('llms.txt : produits avec prix et disponibilité', str_contains($llms, '## Produits') && str_contains($llms, "Bol en grès émaillé](") && str_contains($llms, "29,90\u{00A0}€ · En stock")
        && str_contains($llms, '/products.xml'));
    $md = $v->req('GET', '/boutique/' . $slug . '.md')['body'];
    check('Markdown : prix, prix habituel, achat', str_contains($md, 'price: "29.90 EUR"') && str_contains($md, 'availability: "in_stock"') && str_contains($md, "Prix habituel : 39\u{00A0}€")
        && str_contains($md, 'Acheter : http'));
    $list = $c->req('GET', '/gestion/content?type=product');
    check('administration : liste des produits avec leur prix', str_contains($list['body'], 'Tasse à café') && str_contains($list['body'], "29,90\u{00A0}€") && !str_contains($list['body'], 'Mentions légales'));

    // Nouvelle adresse : redirections. Fermeture : tout disparaît.
    $settingsForm('shop', ['shop_base' => 'magasin'] + $shopFields);
    $moved = $v->req('GET', '/boutique/' . $slug);
    check('adresse de la boutique changée : redirections 301', $moved['status'] === 301 && str_ends_with($moved['headers']['location'] ?? '', '/magasin/' . $slug)
        && $v->req('GET', '/magasin/' . $slug)['status'] === 200 && str_ends_with($v->req('GET', '/boutique/bols')['headers']['location'] ?? '', '/magasin/bols'));
    $taken = $settingsForm('shop', ['shop_base' => 'vitrine'] + $shopFields);
    check('adresse déjà prise par une page : refusée', ltrim((string) ($site()['shop']['base'] ?? ''), '/') === 'magasin');
    $settingsForm('shop', ['shop_enabled' => '', 'shop_base' => 'magasin'] + $shopFields);
    check('boutique fermée : fiches, catalogue et flux hors ligne', $v->req('GET', '/magasin')['status'] === 404 && $v->req('GET', '/magasin/' . $slug)['status'] === 404
        && $v->req('GET', '/products.xml')['status'] === 404 && !str_contains($v->req('GET', '/sitemap.xml')['body'], '/magasin'));
    check('boutique fermée : bloc Produits vide', !str_contains($v->req('GET', '/vitrine')['body'], 'class="card product-card"'));
    $list = $c->req('GET', '/gestion/content?type=product')['body'];
    check('boutique fermée : produits marqués comme tels dans l’administration', str_contains($list, 'badge warn">Boutique fermée') && !str_contains($list, 'badge brand">Programmé'));
} finally {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($copy));
}
