<?php
/**
 * Fonctions utilitaires globales (utilisées partout, y compris dans les gabarits).
 */
declare(strict_types=1);

defined('ECLAIR') || exit;

use Eclair\I18n;
use Eclair\Req;
use Eclair\Settings;

/** Échappe une valeur pour l'insérer dans du HTML (texte ou attribut). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Traduction de l'interface d'administration : le texte français sert de clé. */
function t(string $text, array $vars = []): string
{
    return I18n::t($text, $vars);
}

/** Chemin public relatif au domaine (tient compte d'une installation en sous-dossier). */
function path_to(string $path = '/'): string
{
    return Req::base() . ($path === '' ? '/' : $path);
}

/** URL absolue officielle du site (canonical, sitemap, Open Graph…). */
function absolute_url(string $path = '/'): string
{
    return rtrim((string) Settings::get('url'), '/') . ($path === '' ? '/' : $path);
}

/** URL de l'administration. */
function admin_url(string $path = ''): string
{
    return Req::base() . '/' . Settings::get('admin_path', 'admin') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/** URL d'un fichier statique avec empreinte anti-cache. */
function asset(string $file): string
{
    $mtime = @filemtime(ECLAIR_ROOT . '/assets/' . $file) ?: 0;
    return Req::base() . '/assets/' . $file . '?v=' . substr(md5(ECLAIR_VERSION . $mtime), 0, 8);
}

/** Identifiant aléatoire court (10 caractères hexadécimaux). */
function random_id(): string
{
    return bin2hex(random_bytes(5));
}

/** Transforme un texte en slug d'URL propre : « Été à Madrid ! » → « ete-a-madrid ». */
function slugify(string $text, string $lang = 'fr', int $max = 80): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = $lang === 'de' ? ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue'] : [];
    $map += [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
        'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ł' => 'l',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'œ' => 'oe', 'ř' => 'r', 'ś' => 's', 'š' => 's', 'ș' => 's',
        'ş' => 's', 'ß' => 'ss', 'ť' => 't', 'ț' => 't', 'ţ' => 't', 'þ' => 'th',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
    ];
    $text = strtr($text, $map);
    if (class_exists('Normalizer')) {
        $text = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
    }
    $text = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    if (strlen($text) > $max) {
        $cut = substr($text, 0, $max + 1);
        $pos = strrpos($cut, '-');
        $text = rtrim($pos !== false && $pos > $max / 2 ? substr($cut, 0, $pos) : substr($text, 0, $max), '-');
    }
    return $text;
}

/** Texte brut à partir de HTML (espaces normalisés). */
function plain(string $html): string
{
    $html = (string) preg_replace('~<br\s*/?>|</(?:p|li|h\d|div|tr|td|th|blockquote|figcaption|summary|dd|dt)>~i', '$0 ', $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

/** Coupe proprement un texte à une longueur maximale, sur une frontière de mot. */
function excerpt(string $text, int $max = 160): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max + 1);
    $pos = mb_strrpos($cut, ' ');
    $cut = mb_substr($cut, 0, $pos !== false && $pos > $max * 0.6 ? $pos : $max);
    return rtrim($cut, " ,;:.!?-–—") . '…';
}

/** Nombre de mots d'un texte brut (gère les accents et les espaces insécables). */
function word_count(string $text): int
{
    $text = trim($text);
    return $text === '' ? 0 : count(preg_split('/[\s\x{00A0}\x{202F}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

/** Date lisible dans la langue demandée : « 28 octobre 2026 ». */
function fmt_date(int $ts, ?string $lang = null, bool $time = false): string
{
    $lang = $lang ?: (string) Settings::get('lang', 'fr');
    static $months = [
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
        'pt' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
    ];
    [$d, $m, $y] = array_map('intval', explode(' ', date('j n Y', $ts)));
    $month = ($months[$lang] ?? $months['en'])[$m - 1];
    $out = match ($lang) {
        'fr' => ($d === 1 ? '1er' : $d) . " $month $y",
        'es', 'pt' => "$d de $month de $y",
        'en' => "$month $d, $y",
        'de' => "$d. $month $y",
        default => "$d $month $y",
    };
    return $time ? $out . ' · ' . date('H:i', $ts) : $out;
}

/** Durée relative courte pour l'administration : « il y a 5 min ». */
function ago(int $ts): string
{
    $diff = time() - $ts;
    return match (true) {
        $diff < 60 => t("à l'instant"),
        $diff < 3600 => t('il y a {n} min', ['n' => intdiv($diff, 60)]),
        $diff < 86400 => t('il y a {n} h', ['n' => intdiv($diff, 3600)]),
        $diff < 86400 * 30 => t('il y a {n} j', ['n' => intdiv($diff, 86400)]),
        default => fmt_date($ts, I18n::lang()),
    };
}

/** Taille de fichier lisible. */
function human_size(int $bytes): string
{
    return match (true) {
        $bytes >= 1048576 => round($bytes / 1048576, 1) . ' Mo',
        $bytes >= 1024 => round($bytes / 1024) . ' Ko',
        default => $bytes . ' o',
    };
}

/** Encode des données pour un bloc <script type="application/json"> (sans risque de fermeture de balise). */
function json_script(mixed $data): string
{
    return (string) json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
