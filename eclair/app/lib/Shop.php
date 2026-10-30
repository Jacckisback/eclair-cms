<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Mode boutique léger : fiches produit, catalogue et paiement par lien (Stripe Payment Links ou autre).
 * Pas de panier, de commandes ni de base de données : le prestataire de paiement encaisse, envoie le reçu
 * et gère les remboursements ; Éclair publie des fiches produit rapides, balisées pour Google et les IA.
 */
final class Shop
{
    public const CURRENCIES = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF', 'CAD' => 'CA$'];
    public const AVAILABILITY = ['in_stock' => 'InStock', 'out_of_stock' => 'OutOfStock', 'preorder' => 'PreOrder'];
    public const CONDITIONS = ['new' => 'NewCondition', 'used' => 'UsedCondition', 'refurbished' => 'RefurbishedCondition'];
    private const MAX_VARIANTS = 20;
    private const MAX_GALLERY = 8;
    /** Pays de livraison par défaut selon la langue du site. */
    private const COUNTRY = ['fr' => 'FR', 'es' => 'ES', 'en' => 'GB', 'de' => 'DE', 'it' => 'IT', 'pt' => 'PT'];

    public static function enabled(): bool
    {
        return (bool) Settings::get('shop.enabled');
    }

    /** Adresse du catalogue (« /boutique » par défaut, selon la langue du site). */
    public static function path(): string
    {
        $base = (string) Settings::get('shop.base');
        return '/' . ($base !== '' ? $base : Theme::words()['shop']);
    }

    public static function currency(): string
    {
        $c = (string) Settings::get('shop.currency');
        return isset(self::CURRENCIES[$c]) ? $c : 'EUR';
    }

    public static function title(?string $lang = null): string
    {
        $title = trim((string) Settings::get('shop.title'));
        return $title !== '' ? $title : Theme::t('shop', $lang);
    }

    public static function button(?string $lang = null): string
    {
        $label = trim((string) Settings::get('shop.button'));
        return $label !== '' ? $label : Theme::t('buy', $lang);
    }

    /* ─────────────────────────────── Prix ─────────────────────────────── */

    /** Prix saisi (« 19,90 », « 19.9 € ») → « 19.90 », ou « » s'il est vide ou invalide. */
    public static function amount(mixed $value): string
    {
        $v = str_replace([' ', "\u{00A0}", "\u{202F}", '€', '$', '£', 'CHF'], '', trim((string) (is_scalar($value) ? $value : '')));
        $v = str_replace(',', '.', $v);
        return preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v) ? number_format((float) $v, 2, '.', '') : '';
    }

    /** Prix affiché selon la langue : « 19,90 € », « 1 290 € », « €19.90 ». */
    public static function money(string $amount, ?string $lang = null): string
    {
        if ($amount === '') {
            return '';
        }
        $lang = $lang ?: (string) Settings::get('lang');
        $currency = self::currency();
        $symbol = self::CURRENCIES[$currency];
        $n = (float) $amount;
        $decimals = abs($n - round($n)) < 0.001 ? 0 : 2;
        if ($lang === 'en') {
            return ($currency === 'CHF' ? 'CHF ' : $symbol) . number_format($n, $decimals, '.', ',');
        }
        return number_format($n, $decimals, ',', $lang === 'fr' ? "\u{202F}" : '.') . "\u{00A0}" . $symbol;
    }

    /* ─────────────────────────── Données produit ─────────────────────────── */

    /** Fiche produit nettoyée (prix, disponibilité, lien de paiement, galerie, variantes). */
    public static function normalize(mixed $data): array
    {
        $d = is_array($data) ? $data : [];
        $availability = static fn (mixed $a): string => is_string($a) && isset(self::AVAILABILITY[$a]) ? $a : 'in_stock';
        $variants = [];
        foreach (array_slice(is_array($d['variants'] ?? null) ? $d['variants'] : [], 0, self::MAX_VARIANTS) as $v) {
            $label = is_array($v) ? Sanitize::text($v['label'] ?? '', 80) : '';
            if ($label !== '') {
                $variants[] = ['label' => $label, 'price' => self::amount($v['price'] ?? ''), 'link' => self::link($v['link'] ?? ''), 'availability' => $availability($v['availability'] ?? '')];
            }
        }
        $gallery = [];
        foreach (is_array($d['gallery'] ?? null) ? $d['gallery'] : [] as $id) {
            $id = preg_replace('/[^a-z0-9]/', '', (string) (is_scalar($id) ? $id : '')) ?? '';
            if ($id !== '' && !in_array($id, $gallery, true) && count($gallery) < self::MAX_GALLERY && Media::get($id)) {
                $gallery[] = $id;
            }
        }
        $gtin = preg_replace('/\D/', '', (string) (is_scalar($d['gtin'] ?? '') ? $d['gtin'] ?? '' : '')) ?? '';
        return [
            'price' => self::amount($d['price'] ?? ''),
            'compare' => self::amount($d['compare'] ?? ''),
            'availability' => $availability($d['availability'] ?? ''),
            'link' => self::link($d['link'] ?? ''),
            'condition' => is_string($d['condition'] ?? null) && isset(self::CONDITIONS[$d['condition']]) ? $d['condition'] : 'new',
            'digital' => !empty($d['digital']),
            'sku' => (string) preg_replace('/\s+/', '', Sanitize::text($d['sku'] ?? '', 64)),
            'gtin' => in_array(strlen($gtin), [8, 12, 13, 14], true) ? $gtin : '',
            'brand' => Sanitize::text($d['brand'] ?? '', 80),
            'gallery' => $gallery,
            'variants' => $variants,
        ];
    }

    /** Lien de paiement : une adresse https seulement (Stripe Payment Links, ou un autre prestataire). */
    public static function link(mixed $url): string
    {
        $url = Sanitize::url((string) (is_scalar($url) ? $url : ''));
        return preg_match('~^https://[a-z0-9.-]+\.[a-z]{2,}(?:[/?#]|$)~i', $url) ? $url : '';
    }

    /** Offres d'un produit : une par variante (prix et lien hérités du produit s'ils sont vides), ou une seule. */
    public static function offers(array $item): array
    {
        $p = $item['product'];
        if (!$p['variants']) {
            return [['label' => '', 'price' => $p['price'], 'link' => $p['link'], 'availability' => $p['availability']]];
        }
        // Un lien Stripe encaisse un montant fixé dans Stripe : une variante à un autre prix n'hérite pas du lien du produit.
        return array_map(fn (array $v): array => [
            'label' => $v['label'],
            'price' => $v['price'] !== '' ? $v['price'] : $p['price'],
            'link' => $v['link'] !== '' ? $v['link'] : ($v['price'] === '' || $v['price'] === $p['price'] ? $p['link'] : ''),
            'availability' => $p['availability'] === 'out_of_stock' ? 'out_of_stock' : $v['availability'],
        ], $p['variants']);
    }

    /** Médias du produit : image principale puis galerie. */
    public static function images(array $item): array
    {
        $out = [];
        foreach (array_unique(array_merge([(string) $item['image']], $item['product']['gallery'] ?? [])) as $id) {
            if ($m = Media::get((string) $id)) {
                $out[] = $m;
            }
        }
        return $out;
    }

    /**
     * Adresse du bouton « Acheter ». Pour un lien Stripe, la référence du produit est ajoutée
     * (client_reference_id) : elle apparaît avec le paiement dans Stripe et aide à préparer la commande.
     */
    public static function buyUrl(string $link, string $reference): string
    {
        if ($link === '' || !preg_match('~^https://buy\.stripe\.com/~i', $link) || str_contains($link, 'client_reference_id=')) {
            return $link;
        }
        $ref = substr((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference), 0, 200);
        return $link . (str_contains($link, '?') ? '&' : '?') . 'client_reference_id=' . $ref;
    }

    /** Pays de livraison (codes ISO à deux lettres), sinon celui de la langue du site. */
    public static function countries(): array
    {
        return self::parseCountries((string) Settings::get('shop.shipping.countries')) ?: [self::COUNTRY[(string) Settings::get('lang')] ?? 'FR'];
    }

    /** « fr, be ; CH » → ['FR', 'BE', 'CH'] (50 pays au plus). */
    public static function parseCountries(string $raw): array
    {
        // Tout ce qui n'est pas une lettre sépare les codes (virgules, espaces, « ， » ou « 、 » d'un clavier chinois…).
        $codes = array_filter(preg_split('/[^A-Z0-9]+/', strtoupper($raw)) ?: [], fn ($c) => preg_match('/^[A-Z]{2}$/', $c));
        return array_slice(array_values(array_unique($codes)), 0, 50);
    }

    /** Frais de livraison pour un prix donné (« 0.00 » au-delà du seuil de gratuité), ou « » s'ils ne sont pas renseignés. */
    public static function shippingFor(string $price): string
    {
        $s = (array) Settings::get('shop.shipping');
        $cost = self::amount($s['price'] ?? '');
        $free = self::amount($s['free_from'] ?? '');
        if ($cost !== '' && $free !== '' && $price !== '' && (float) $price >= (float) $free) {
            return '0.00';
        }
        return $cost;
    }

    /** Pages obligatoires avant d'ouvrir la boutique, encore manquantes (libellés). */
    public static function missing(?string $legal = null, ?string $terms = null): array
    {
        $out = [];
        $ids = ['legal' => $legal ?? (string) Settings::get('shop.legal'), 'terms' => $terms ?? (string) Settings::get('shop.terms')];
        foreach (['legal' => t('Mentions légales'), 'terms' => t('Conditions générales de vente')] as $key => $label) {
            $entry = Content::index()[$ids[$key]] ?? null;
            if (!$entry || $entry['type'] !== 'page' || !Content::visible($entry)) {
                $out[] = $label;
            }
        }
        return $out;
    }

    /** Lien vers une page de la boutique (CGV, livraison et retours…), si elle est publiée. */
    public static function pageLink(string $key): ?array
    {
        $entry = Content::index()[(string) Settings::get('shop.' . $key)] ?? null;
        return $entry && $entry['type'] === 'page' && Content::visible($entry) ? $entry : null;
    }

    /* ─────────────────────────── Données structurées ─────────────────────────── */

    /**
     * Offres réellement achetables : un prix et un lien de paiement (les autres ne vont ni dans le JSON-LD ni dans le flux).
     * Les clés restent celles des variantes : leurs identifiants ne changent pas quand une autre variante change.
     */
    public static function buyable(array $item): array
    {
        return array_filter(self::offers($item), fn ($o) => $o['price'] !== '' && (float) $o['price'] > 0 && $o['link'] !== '');
    }

    /** JSON-LD d'une fiche : Product + Offer, ou ProductGroup avec une fiche par variante (recommandation de Google). */
    public static function schema(array $item, string $url, array $images, string $description, ?array $category): array
    {
        $p = $item['product'];
        $offers = self::buyable($item);
        if (!$offers) {
            return [];
        }
        $base = array_filter([
            'name' => $item['title'],
            'description' => $description,
            'image' => $images,
            'sku' => $p['sku'],
            'gtin' => $p['gtin'],
            'brand' => $p['brand'] !== '' ? ['@type' => 'Brand', 'name' => $p['brand']] : null,
            'category' => $category['name'] ?? '',
        ]);
        if (!$p['variants']) {
            return ['@type' => 'Product', '@id' => $url . '#product'] + $base + ['offers' => self::offer($offers[0], $p, $url)];
        }
        $variants = [];
        foreach ($offers as $i => $o) {
            $variants[] = array_filter([
                '@type' => 'Product',
                'name' => $item['title'] . ' — ' . $o['label'],
                'sku' => $p['sku'] !== '' ? $p['sku'] . '-' . ($i + 1) : '',
                'image' => $images[0] ?? '',
            ]) + ['offers' => self::offer($o, $p, $url)];
        }
        return ['@type' => 'ProductGroup', '@id' => $url . '#product', 'productGroupID' => $item['id']] + $base + ['hasVariant' => $variants];
    }

    private static function offer(array $o, array $p, string $url): array
    {
        $offer = [
            '@type' => 'Offer',
            'url' => $url,
            'price' => $o['price'],
            'priceCurrency' => self::currency(),
            'availability' => 'https://schema.org/' . self::AVAILABILITY[$o['availability']],
            'itemCondition' => 'https://schema.org/' . self::CONDITIONS[$p['condition']],
            'seller' => ['@id' => Seo::entity()['@id']],
        ];
        $shipping = $p['digital'] ? '' : self::shippingFor($o['price']);
        if ($shipping !== '') {
            $s = (array) Settings::get('shop.shipping');
            $min = max(0, (int) ($s['days_min'] ?? 2));
            $offer['shippingDetails'] = [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => $shipping, 'currency' => self::currency()],
                'shippingDestination' => array_map(fn (string $c): array => ['@type' => 'DefinedRegion', 'addressCountry' => $c], self::countries()),
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                    'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => $min, 'maxValue' => max($min, (int) ($s['days_max'] ?? 5)), 'unitCode' => 'DAY'],
                ],
            ];
        }
        return $offer;
    }

    /** Politique de retour de l'entreprise (balisage recommandé par Google, au niveau de l'organisation). */
    public static function returnPolicy(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $days = max(0, min(365, (int) Settings::get('shop.returns.days')));
        $policy = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => self::countries(),
            'returnPolicyCategory' => 'https://schema.org/' . ($days > 0 ? 'MerchantReturnFiniteReturnWindow' : 'MerchantReturnNotPermitted'),
        ];
        if ($days > 0) {
            $policy['merchantReturnDays'] = $days;
            $policy['returnMethod'] = 'https://schema.org/ReturnByMail';
            $policy['returnFees'] = 'https://schema.org/' . (Settings::get('shop.returns.free') ? 'FreeReturn' : 'ReturnFeesCustomerResponsibility');
        }
        if ($page = self::pageLink('delivery')) {
            $policy['merchantReturnLink'] = absolute_url(Content::path($page));
        }
        return $policy;
    }

    /* ─────────────────────────────── Flux produits ─────────────────────────────── */

    /** Flux produits au format RSS de Google Merchant Center (une ligne par variante). */
    public static function feed(): string
    {
        $items = '';
        $x = static fn (string $s): string => Seo::xml($s);
        foreach (Content::published('product') as $entry) {
            $item = $entry['noindex'] ? null : Content::get($entry['id']);
            if (!$item) {
                continue;
            }
            $p = $item['product'];
            $url = absolute_url(Content::path($entry));
            $images = array_map(fn (array $m): string => absolute_url(Media::url($m)), self::images($item));
            $description = excerpt($item['seo']['description'] ?: ($item['summary'] ?: Blocks::text($item['blocks'])), 4900);
            $offers = self::buyable($item);
            foreach ($offers as $i => $o) {
                $sale = $p['compare'] !== '' && (float) $p['compare'] > (float) $o['price'];
                $xml = '<item><g:id>' . $x($item['id'] . ($p['variants'] ? '-' . ($i + 1) : '')) . '</g:id>'
                    . ($p['variants'] ? '<g:item_group_id>' . $x($item['id']) . '</g:item_group_id>' : '')
                    . '<title>' . $x($item['title'] . ($o['label'] !== '' ? ' — ' . $o['label'] : '')) . '</title>'
                    . '<link>' . $x($url) . '</link><description>' . $x($description) . '</description>'
                    . ($images ? '<g:image_link>' . $x($images[0]) . '</g:image_link>' : '');
                foreach (array_slice($images, 1, 10) as $img) {
                    $xml .= '<g:additional_image_link>' . $x($img) . '</g:additional_image_link>';
                }
                $xml .= '<g:availability>' . $x($o['availability']) . '</g:availability>'
                    . '<g:price>' . $x(($sale ? $p['compare'] : $o['price']) . ' ' . self::currency()) . '</g:price>'
                    . ($sale ? '<g:sale_price>' . $x($o['price'] . ' ' . self::currency()) . '</g:sale_price>' : '')
                    . '<g:condition>' . $x($p['condition']) . '</g:condition>'
                    . ($p['brand'] !== '' ? '<g:brand>' . $x($p['brand']) . '</g:brand>' : '')
                    . ($p['gtin'] !== '' ? '<g:gtin>' . $x($p['gtin']) . '</g:gtin>' : '')
                    . ($p['sku'] !== '' ? '<g:mpn>' . $x($p['sku']) . '</g:mpn>' : '')
                    . ($p['gtin'] === '' && ($p['brand'] === '' || $p['sku'] === '') ? '<g:identifier_exists>no</g:identifier_exists>' : '');
                $shipping = $p['digital'] ? '' : self::shippingFor($o['price']);
                if ($shipping !== '') {
                    foreach (self::countries() as $country) {
                        $xml .= '<g:shipping><g:country>' . $x($country) . '</g:country><g:price>' . $x($shipping . ' ' . self::currency()) . '</g:price></g:shipping>';
                    }
                }
                $items .= $xml . "</item>\n";
            }
        }
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n"
            . '<channel><title>' . $x((string) Settings::get('name')) . '</title><link>' . $x(absolute_url(self::path())) . '</link>'
            . '<description>' . $x(self::title()) . "</description>\n" . $items . '</channel></rss>' . "\n";
    }

    /* ─────────────────────────────── Divers ─────────────────────────────── */

    /** Ligne de prix pour les IA (llms.txt, Markdown) : « 19,90 € · En stock ». */
    public static function summary(array $entry, string $lang): string
    {
        $price = (string) ($entry['price'] ?? '');
        return trim(($price !== '' ? self::money($price, $lang) . ' · ' : '') . Theme::t((string) ($entry['availability'] ?? 'in_stock'), $lang));
    }
}
