<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Pages et articles. Chaque contenu est un fichier data/content/<id>.php ;
 * data/index.php garde un résumé de tous les contenus pour lister et router vite.
 */
final class Content
{
    public const LANGS = ['fr' => 'Français', 'es' => 'Español', 'en' => 'English', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português'];
    private const MAX_REVISIONS = 20;
    /**
     * Nombre maximal de contenus (pages, articles, produits, brouillons compris). L'index ne doit pas pouvoir grossir sans
     * fin (boucle d'une IA ou d'un connecteur, compte malveillant) jusqu'à bloquer le site. 10 000 depuis que les pages
     * publiques ne lisent plus que l'index léger : à ce plafond, une page générée coûte ce qu'elle coûtait à 3 000 contenus
     * avec l'index complet (environ 20 ms et 13 Mo), et les parcours de tout le site (plan du site, llms.txt, recherche,
     * administration), qui lisent encore l'index complet, restent sous 60 Mo, loin de la limite de mémoire courante de PHP
     * (128 Mo) ; à 20 000, ils la frôleraient (110 Mo).
     */
    public const MAX_ITEMS = 10_000;
    /**
     * Taille maximale de l'index de recherche (data/search.php : le texte des contenus publiés), relu en entier à chaque
     * recherche d'un visiteur. 16 Mo, c'est plus de 1 500 longs articles ; au-delà, la recherche épuiserait la mémoire de PHP.
     * Quand il est plein, un contenu n'y entre plus que par son début (SEARCH_SHORT), puis plus du tout : il reste publié, et
     * trouvable par son titre, son extrait et son résumé (voir searchTexts).
     */
    public const MAX_SEARCH_BYTES = 16_000_000;
    /** Texte cherchable gardé par contenu (caractères). */
    private const SEARCH_TEXT = 30000;
    /** Texte cherchable gardé par contenu quand l'index de recherche est plein (caractères). */
    private const SEARCH_SHORT = 1500;
    /**
     * data/search.php : « id » → texte du contenu, « ~id » → son extrait et son résumé, « ~ » → version du format. Tout y est
     * en minuscules, prêt à être comparé : une recherche ne lit ainsi ni l'index complet ni ne remet 16 Mo en minuscules.
     */
    private const SEARCH_MARK = '~';
    private const SEARCH_HEAD = '~';
    private const SEARCH_VERSION = 2;
    /** Enregistrements groupés en cours (batch) : index de recherche gardé en mémoire, écrit une fois à la fin. */
    private static int $batch = 0;
    /** [index de recherche à écrire, taille et date du fichier lu au début, taille estimée, clés touchées par le groupe] */
    private static ?array $pending = null;
    /** Termes d'une recherche pris en compte : chacun parcourt tout l'index de recherche. */
    private const MAX_TERMS = 8;

    private static ?array $index = null;
    /**
     * Vues de l'index calculées une fois par requête (listes publiées triées, table des adresses) : sur un site de 3 000
     * contenus, chaque page générée refaisait sinon cinq à huit filtrages et tris complets. Oubliées dès que l'index change
     * (hold) ; leur clé reprend ce dont elles dépendent hors de l'index (seconde courante, boutique, accueil, adresses).
     */
    private static array $views = [];
    /**
     * Index léger (data/index-lite.php) : une ligne par contenu, dans l'ordre de l'index, avec seulement ce qu'il faut pour
     * décider d'une adresse, d'une visibilité, d'un ordre ou d'un compte (voir les colonnes L_*). Les pages publiques ne
     * lisent que lui : trois à quatre fois plus petit que l'index complet (pas d'extrait, de résumé ni de noms de champs),
     * il se décode d'autant plus vite, et les quelques contenus réellement affichés sont relus dans leur propre fichier.
     * Toujours dérivé de l'index : écrit avec lui, et refait tout seul si l'index a changé sans lui (voir rows()).
     */
    private const LITE = 'index-lite';
    /**
     * Taille de data/index.php (octets) à partir de laquelle les pages publiques lisent l'index léger. En dessous (environ
     * 450 contenus), l'index complet est si petit que le lire directement reste le plus rapide : rien ne change pour ces
     * sites, ni les fichiers chargés ni la mémoire. Les tests forcent l'index léger avec la constante ECLAIR_LITE_FROM (0).
     */
    private const LITE_FROM = 300_000;
    private const L_ID = 0, L_TYPE = 1, L_STATUS = 2, L_SLUG = 3, L_ROUTE = 4, L_LANG = 5, L_TGROUP = 6, L_DATE = 7, L_CATEGORY = 8,
        L_AUTHOR = 9, L_NOINDEX = 10, L_TITLE = 11, L_UPDATED = 12;
    /** Version du format de l'index léger : un fichier d'une autre version est refait. */
    private const LITE_VERSION = 2;
    private static ?array $rows = null;
    /** Contenus relus dont le fichier reste en mémoire pour la requête (une page en affiche rarement plus). */
    private const KEEP = 48;
    /** Nombre de contenus relus depuis le début de la requête. */
    private static int $reread = 0;
    /** Résumés complets relus pour la requête (id → résumé, ou false si le fichier manque). */
    private static array $full = [];

    /* ─────────────────────────────── Lecture ─────────────────────────────── */

    /** Index complet : id → résumé. */
    public static function index(): array
    {
        if (self::$index === null) {
            $index = Store::read('index');
            is_array($index) ? self::hold($index) : self::reindex();
        }
        return self::$index;
    }

    /** Index gardé pour la requête (null : relu au prochain besoin) ; les vues calculées à partir de l'ancien sont oubliées. */
    private static function hold(?array $index): ?array
    {
        self::$views = [];
        self::$full = [];
        self::$rows = null;
        return self::$index = $index;
    }

    /** Vue de l'index gardée pour la requête, recalculée si ce dont elle dépend a changé. */
    private static function view(string $name, string $key, callable $make): array
    {
        if ((self::$views[$name][0] ?? null) !== $key) {
            self::$views[$name] = [$key, $make()];
        }
        return self::$views[$name][1];
    }

    /* ───────────────────────────── Index léger ───────────────────────────── */

    /**
     * Ce à quoi l'index léger se reconnaît à jour : la taille et la date de data/index.php, et son numéro de fichier (inode),
     * qui change à chaque écriture d'Éclair (fichier temporaire puis renommage), même dans la même seconde et à taille égale.
     * [] si l'index n'existe pas.
     */
    private static function stamp(): array
    {
        $file = Store::file('index');
        clearstatcache(true, $file);
        $stat = @stat($file);
        return $stat === false ? [] : ['of' => $stat['size'] . '.' . $stat['mtime'], 'ino' => (int) $stat['ino'], 'size' => (int) $stat['size']];
    }

    /** L'index léger lu correspond-il à l'index présent sur le disque ? Un numéro de fichier noté 0 n'est pas comparé. */
    private static function fresh(mixed $lite, array $stamp): bool
    {
        return is_array($lite) && $stamp !== [] && ($lite['v'] ?? 0) === self::LITE_VERSION && ($lite['of'] ?? null) === $stamp['of'] && is_array($lite['rows'] ?? null)
            && (($lite['ino'] ?? 0) === 0 || $lite['ino'] === $stamp['ino']);
    }

    private static function rowsOf(array $index): array
    {
        $rows = [];
        foreach ($index as $e) {
            $rows[] = [(string) $e['id'], (string) $e['type'], (string) $e['status'], (string) $e['slug'], (string) ($e['route'] ?? ''), (string) ($e['lang'] ?? ''),
                (string) ($e['tgroup'] ?? ''), $e['date'], (string) ($e['category'] ?? ''), (string) ($e['author'] ?? ''), empty($e['noindex']) ? 0 : 1, (string) $e['title'],
                $e['updated'] ?? $e['date']];
        }
        return $rows;
    }

    /** Écrit l'index, puis l'index léger qui en dérive. Toujours sous le verrou des données (appelants). */
    private static function writeIndex(array $index): void
    {
        Store::write('index', $index);
        self::writeLite(self::rowsOf($index));
    }

    /** Fichier dérivé : son écriture ne fait jamais échouer un enregistrement (il serait refait à la prochaine page générée). */
    private static function writeLite(array $rows, bool $inode = true): void
    {
        try {
            $stamp = self::stamp();
            if ($stamp !== []) {
                Store::write(self::LITE, ['v' => self::LITE_VERSION, 'of' => $stamp['of'], 'ino' => $inode ? $stamp['ino'] : 0, 'rows' => $rows]);
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    /**
     * Lignes de l'index léger. Si l'index complet est déjà en mémoire (administration) ou s'il est petit (LITE_FROM), elles
     * en sont tirées. Sinon le fichier léger est lu, pourvu qu'il corresponde à l'index présent sur le disque (taille et date) ; absent ou dépassé
     * (site mis à jour depuis une version sans index léger, sauvegarde restaurée, données envoyées par FTP), il est refait à
     * partir de l'index, sous verrou, une fois.
     */
    private static function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }
        if (self::$index !== null) {
            return self::$rows = self::rowsOf(self::$index);
        }
        $stamp = self::stamp();
        if ($stamp !== [] && $stamp['size'] < (defined('ECLAIR_LITE_FROM') ? (int) ECLAIR_LITE_FROM : self::LITE_FROM)) {
            return self::$rows = self::rowsOf(self::index());
        }
        if ($stamp !== []) {
            $lite = Store::read(self::LITE);
            if (self::fresh($lite, $stamp)) {
                return self::$rows = $lite['rows'];
            }
        }
        try {
            return self::$rows = Store::locked(static function (): array {
                self::hold(null);
                $index = self::index();
                $rows = self::rowsOf($index);
                $stamp = self::stamp();
                $lite = Store::read(self::LITE);
                if (!self::fresh($lite, $stamp)) {
                    // Même taille et même date mais un autre numéro de fichier : soit l'index a été remplacé dans la seconde,
                    // soit l'hébergement ne donne pas de numéros stables (certains disques réseau). Dans le doute, le numéro
                    // n'est plus comparé jusqu'au prochain enregistrement, pour ne jamais refaire l'index léger à chaque page.
                    $unstable = is_array($lite) && $stamp !== [] && ($lite['v'] ?? 0) === self::LITE_VERSION && ($lite['of'] ?? null) === $stamp['of'];
                    self::writeLite($rows, !$unstable);
                }
                return $rows;
            });
        } catch (StorageException) {
            // Verrou des données hors service : les pages restent servies à partir de l'index complet, sans rien écrire.
            return self::$rows = self::rowsOf(self::index());
        }
    }

    private static function rowVisible(array $r): bool
    {
        return $r[self::L_STATUS] === 'published' && (int) $r[self::L_DATE] <= time() && ($r[self::L_TYPE] !== 'product' || Shop::enabled());
    }

    /** Numéros des lignes visibles, du contenu le plus récent au plus ancien (même ordre que published()). */
    private static function order(): array
    {
        $rows = self::rows();
        return self::view('order', time() . (Settings::get('shop.enabled') ? '+' : '-'), static function () use ($rows): array {
            // Même règle que visible(), l'heure et la boutique lues une fois pour toutes les lignes.
            $now = time();
            $shop = (bool) Settings::get('shop.enabled');
            $dates = [];
            foreach ($rows as $i => $r) {
                if ($r[self::L_STATUS] === 'published' && (int) $r[self::L_DATE] <= $now && ($shop || $r[self::L_TYPE] !== 'product')) {
                    $dates[$i] = $r[self::L_DATE];
                }
            }
            // Tri stable (PHP 8) : à date égale, l'ordre de l'index est gardé, comme dans published().
            uasort($dates, static fn (mixed $a, mixed $b): int => $b <=> $a);
            return array_keys($dates);
        });
    }

    /** Numéro de ligne par identifiant. */
    private static function ids(): array
    {
        $rows = self::rows();
        return self::view('ids', '', static fn (): array => array_flip(array_column($rows, self::L_ID)));
    }

    /**
     * Résumé complet du contenu d'une ligne : celui de l'index s'il est en mémoire, sinon recalculé à partir du fichier du
     * contenu (le même calcul que l'index, entry()). Un contenu que l'index dit en ligne mais dont le fichier ne l'est pas
     * (fichier modifié hors d'Éclair) est traité comme absent : jamais un brouillon affiché.
     */
    private static function hydrate(array $r): ?array
    {
        $id = $r[self::L_ID];
        if (self::$index !== null) {
            return self::$index[$id] ?? null;
        }
        if (!array_key_exists($id, self::$full)) {
            $entry = false;
            $item = self::get($id);
            if ($item !== null && isset($item['blocks'])) {
                try {
                    $entry = self::entry($item);
                    if (self::rowVisible($r) && !self::visible($entry)) {
                        $entry = false;
                    }
                } catch (\Throwable) {
                    $entry = false;
                }
            }
            // Parcours de nombreux contenus (llms.txt, résultats, traductions du plan du site) : la mémoire de la requête reste
            // bornée. Au-delà de quelques dizaines, le fichier lu n'est plus gardé, et les résumés gardés repartent de zéro.
            if (++self::$reread > self::KEEP) {
                Store::drop('content/' . $id);
                if (count(self::$full) >= 8 * self::KEEP) {
                    self::$full = [];
                }
            }
            self::$full[$id] = $entry;
        }
        return self::$full[$id] ?: null;
    }

    /** Résumé complet d'un contenu par son identifiant (en ligne ou non), sans lire tout l'index. */
    public static function find(string $id): ?array
    {
        if (self::$index !== null) {
            return self::$index[$id] ?? null;
        }
        $i = self::ids()[$id] ?? null;
        return $i === null ? null : self::hydrate(self::rows()[$i]);
    }

    /**
     * L'essentiel d'un contenu par son identifiant, sans lire son fichier : id, type, status, title, slug, lang, tgroup,
     * date, updated, category, author, noindex (et route). Assez pour visible() et path() : menus, liens vers une page.
     */
    public static function brief(string $id): ?array
    {
        $i = self::ids()[$id] ?? null;
        return $i === null ? null : self::briefOf(self::rows()[$i]);
    }

    private static function briefOf(array $r): array
    {
        return ['id' => $r[self::L_ID], 'type' => $r[self::L_TYPE], 'status' => $r[self::L_STATUS], 'title' => $r[self::L_TITLE], 'slug' => $r[self::L_SLUG],
            'lang' => $r[self::L_LANG], 'tgroup' => $r[self::L_TGROUP], 'date' => $r[self::L_DATE], 'updated' => $r[self::L_UPDATED], 'category' => $r[self::L_CATEGORY],
            'author' => $r[self::L_AUTHOR], 'noindex' => (bool) $r[self::L_NOINDEX]] + ($r[self::L_ROUTE] !== '' ? ['route' => $r[self::L_ROUTE]] : []);
    }

    /**
     * Les contenus en ligne un par un, du plus récent au plus ancien (l'ordre de published()), sans lire l'index complet ni
     * rien garder en mémoire : l'essentiel de chacun (voir brief()). Pour les sorties qui parcourent tout le site (plan du
     * site, llms.txt) ; le détail d'un contenu se lit ensuite avec peek() ou find().
     *
     * @return \Generator<int, array>
     */
    public static function online(?string $type = null): \Generator
    {
        $rows = self::rows();
        foreach (self::order() as $i) {
            if ($type === null || $rows[$i][self::L_TYPE] === $type) {
                yield self::briefOf($rows[$i]);
            }
        }
    }

    /** Adresses des traductions en ligne d'un contenu (langue → chemin), sans lire leurs fichiers ; [] s'il n'en a pas d'autre. */
    public static function alternates(array $entry): array
    {
        if (($entry['tgroup'] ?? '') === '') {
            return [];
        }
        $rows = self::rows();
        $out = [];
        foreach (self::tgroups()[(string) $entry['tgroup']] ?? [] as $i) {
            $out[$rows[$i][self::L_LANG]] = self::path(self::briefOf($rows[$i]));
        }
        return count($out) > 1 ? $out : [];
    }

    /** Lignes en ligne de chaque groupe de traductions (hors noindex), dans l'ordre de published() : une passe par requête. */
    private static function tgroups(): array
    {
        $rows = self::rows();
        return self::view('tgroups', time() . (Settings::get('shop.enabled') ? '+' : '-'), static function () use ($rows): array {
            $out = [];
            foreach (self::order() as $i) {
                if ($rows[$i][self::L_TGROUP] !== '' && !$rows[$i][self::L_NOINDEX]) {
                    $out[$rows[$i][self::L_TGROUP]][] = $i;
                }
            }
            return $out;
        });
    }

    /**
     * Une page de contenus en ligne d'un type, du plus récent au plus ancien : [contenus, nombre total]. $where restreint
     * à une catégorie (['category' => id]), à un auteur (['author' => id]) ou aux contenus sans noindex (['indexable' => true]).
     * Avec $limit à 0, seul le total est calculé.
     */
    public static function page(string $type, array $where = [], int $offset = 0, int $limit = 0): array
    {
        $rows = self::rows();
        $category = isset($where['category']) ? (string) $where['category'] : null;
        $author = isset($where['author']) ? (string) $where['author'] : null;
        $indexable = !empty($where['indexable']);
        $found = [];
        foreach (self::order() as $i) {
            $r = $rows[$i];
            if ($r[self::L_TYPE] === $type && ($category === null || $r[self::L_CATEGORY] === $category) && ($author === null || $r[self::L_AUTHOR] === $author)
                && (!$indexable || !$r[self::L_NOINDEX])) {
                $found[] = $i;
            }
        }
        $out = [];
        foreach ($limit > 0 ? array_slice($found, max(0, $offset), $limit) : [] as $i) {
            $entry = self::hydrate($rows[$i]);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }
        return [$out, count($found)];
    }

    /** Nombre de contenus en ligne d'un type par catégorie (id de catégorie → nombre). */
    public static function tally(string $type): array
    {
        $rows = self::rows();
        $counts = [];
        foreach (self::order() as $i) {
            $r = $rows[$i];
            if ($r[self::L_TYPE] === $type && $r[self::L_CATEGORY] !== '') {
                $counts[$r[self::L_CATEGORY]] = ($counts[$r[self::L_CATEGORY]] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /** Oublie l'index gardé pour la requête : la prochaine lecture repart du disque (changements faits par d'autres requêtes). */
    public static function forget(): void
    {
        self::hold(null);
    }

    public static function get(string $id): ?array
    {
        if (!preg_match('/^[a-z0-9]{1,32}$/', $id)) {
            return null;
        }
        $item = Store::read('content/' . $id);
        return is_array($item) ? $item : null;
    }

    /** Lit un contenu sans le garder en mémoire pour la requête : pour les parcours de tous les contenus. */
    public static function peek(string $id): ?array
    {
        $item = self::get($id);
        Store::drop('content/' . $id);
        return $item;
    }

    public static function visible(array $entry): bool
    {
        // Les produits ne sont en ligne que si la boutique est ouverte.
        return $entry['status'] === 'published' && (int) $entry['date'] <= time() && ($entry['type'] !== 'product' || Shop::enabled());
    }

    /** Contenus visibles d'un type, du plus récent au plus ancien. */
    public static function published(?string $type = null): array
    {
        $key = time() . (Settings::get('shop.enabled') ? '+' : '-');
        $all = self::view('published', $key, static function (): array {
            $out = array_filter(self::index(), fn (array $e): bool => self::visible($e));
            uasort($out, fn (array $a, array $b): int => $b['date'] <=> $a['date']);
            return $out;
        });
        // Le tri est stable : filtrer la liste triée par type donne le même ordre que trier la liste filtrée.
        return $type === null ? $all : self::view('published:' . $type, $key, static fn (): array => array_filter($all, fn (array $e): bool => $e['type'] === $type));
    }

    public static function latest(int $count, string $category = '', string $exclude = ''): array
    {
        $rows = self::rows();
        $out = [];
        foreach ($count > 0 ? self::order() : [] as $i) {
            $r = $rows[$i];
            if ($r[self::L_TYPE] === 'post' && ($category === '' || $r[self::L_CATEGORY] === $category) && $r[self::L_ID] !== $exclude
                && ($entry = self::hydrate($r)) !== null) {
                $out[] = $entry;
                if (count($out) >= $count) {
                    break;
                }
            }
        }
        return $out;
    }

    /** Chemin public d'un contenu (sans le dossier d'installation). */
    public static function path(array $entry): string
    {
        if ($entry['type'] === 'page' && $entry['id'] === Settings::get('home')) {
            return '/';
        }
        if (($entry['route'] ?? '') !== '') {
            return (string) $entry['route'];
        }
        return match ($entry['type']) {
            'post' => self::blogPath() . '/' . $entry['slug'],
            'product' => Shop::path() . '/' . $entry['slug'],
            default => '/' . $entry['slug'],
        };
    }

    public static function blogPath(): string
    {
        return '/' . Settings::get('blog.base', 'blog');
    }

    /** Contenu publié correspondant à un chemin. */
    public static function findByPath(string $path): ?array
    {
        $rows = self::rows();
        // Table adresse → contenu (le plus récent l'emporte, comme le parcours de la liste) : une seule passe par requête.
        $key = time() . '|' . Settings::get('home') . '|' . self::blogPath() . '|' . (Settings::get('shop.enabled') ? Shop::path() : '');
        $paths = self::view('paths', $key, static function () use ($rows): array {
            // Même adresse que path(), les réglages lus une fois pour toutes les lignes.
            $home = (string) Settings::get('home');
            $blog = self::blogPath() . '/';
            $shop = Settings::get('shop.enabled') ? Shop::path() . '/' : '';
            $out = [];
            foreach (self::order() as $i) {
                $r = $rows[$i];
                $type = $r[self::L_TYPE];
                $path = $type === 'page' && $r[self::L_ID] === $home ? '/'
                    : ($r[self::L_ROUTE] !== '' ? $r[self::L_ROUTE] : ($type === 'post' ? $blog : ($type === 'product' ? $shop : '/')) . $r[self::L_SLUG]);
                $out[$path] ??= $i;
            }
            return $out;
        });
        return isset($paths[$path]) ? self::hydrate($rows[$paths[$path]]) : null;
    }

    /** Contenu en ligne dont l'adresse exacte (route) est $path, ou null. */
    public static function byRoute(string $path): ?array
    {
        $rows = self::rows();
        $home = (string) Settings::get('home');
        $routes = self::view('routes', time() . '|' . $home . (Settings::get('shop.enabled') ? '+' : '-'), static function () use ($rows, $home): array {
            $out = [];
            foreach ($rows as $i => $r) {
                // La plupart des contenus n'ont pas d'adresse exacte : la visibilité n'est vérifiée que pour ceux qui en ont une.
                if ($r[self::L_ROUTE] !== '' && $r[self::L_ID] !== $home && self::rowVisible($r)) {
                    $out[$r[self::L_ROUTE]] ??= $i;
                }
            }
            return $out;
        });
        return isset($routes[$path]) ? self::hydrate($rows[$routes[$path]]) : null;
    }

    /**
     * Adresse exacte valide d'un ancien site, ou '' : commence par « / », segments de lettres (accents compris), chiffres,
     * « - », « _ », « . » et « ~ », « / » final permis, au plus 8 segments et 300 octets ; jamais « . » ni « .. », jamais
     * une adresse réservée d'Éclair (administration, médias, actifs, plan du site…), jamais un script (.php).
     */
    public static function cleanRoute(string $raw): string
    {
        $path = trim($raw);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 300 || !mb_check_encoding($path, 'UTF-8') || str_contains($path, '//')) {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $path = (string) \Normalizer::normalize($path, \Normalizer::FORM_C);
        }
        $segments = explode('/', trim($path, '/'));
        if ($path === '/' || count($segments) > 8) {
            return '';
        }
        foreach ($segments as $seg) {
            if ($seg === '.' || $seg === '..' || !preg_match('#^[\p{L}\p{M}\p{N}._~-]{1,120}$#u', $seg)) {
                return '';
            }
        }
        $first = strtolower($segments[0]);
        if (in_array($first, Settings::reserved(), true) || in_array(pathinfo($first, PATHINFO_FILENAME), Settings::reserved(), true)
            || in_array($first, ['site', '.well-known'], true) || preg_match('~\.(php\d?|phtml|phar|inc|md|xml|txt)$~i', $path)) {
            return '';
        }
        return $path;
    }

    /** Adresse déjà servie par un autre contenu (adresse exacte ou adresse habituelle, avec ou sans « / » final). */
    public static function routeTaken(string $route, string $id, ?array $index = null): bool
    {
        $bare = rtrim($route, '/');
        foreach ($index ?? self::index() as $e) {
            if ($e['id'] !== $id && in_array(rtrim(self::path($e), '/'), [$bare], true)) {
                return true;
            }
        }
        return false;
    }

    /** Première page publiée trouvée parmi plusieurs slugs possibles. */
    public static function findBySlugAny(array $slugs): ?array
    {
        foreach (self::published('page') as $entry) {
            if (in_array($entry['slug'], $slugs, true)) {
                return $entry;
            }
        }
        return null;
    }

    /** Traductions publiées (même groupe, autre langue) → [lang => entrée]. */
    public static function translations(array $entry): array
    {
        if (($entry['tgroup'] ?? '') === '') {
            return [];
        }
        $rows = self::rows();
        $out = [];
        foreach (self::tgroups()[(string) $entry['tgroup']] ?? [] as $i) {
            if (($other = self::hydrate($rows[$i])) !== null) {
                $out[$other['lang']] = $other;
            }
        }
        return count($out) > 1 ? $out : [];
    }

    /** Date de parution du prochain article programmé (0 si aucun). */
    public static function nextScheduled(): int
    {
        $next = 0;
        foreach (self::rows() as $r) {
            if ($r[self::L_STATUS] === 'published' && (int) $r[self::L_DATE] > time() && ($next === 0 || (int) $r[self::L_DATE] < $next)) {
                $next = (int) $r[self::L_DATE];
            }
        }
        return $next;
    }

    /** Termes d'une recherche : en minuscules, au moins 2 caractères, sans doublon, MAX_TERMS au plus. */
    public static function terms(string $query): array
    {
        $terms = array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [], fn ($t) => mb_strlen($t) >= 2);
        return array_slice(array_values(array_unique($terms)), 0, self::MAX_TERMS);
    }

    /** Recherche plein texte simple, pondérée (titre > résumé > texte). */
    public static function search(string $query, int $limit = 30): array
    {
        $terms = self::terms($query);
        if (!$terms) {
            return [];
        }
        $texts = Store::read('search', []);
        $texts = is_array($texts) ? $texts : [];
        if (($texts[self::SEARCH_MARK] ?? null) !== self::SEARCH_VERSION) {
            return self::searchByIndex($terms, $texts, $limit);
        }
        // Index de recherche complet (voir searchTexts) : titre lu dans l'index léger, extrait, résumé et texte déjà en
        // minuscules dans data/search.php. L'index complet n'est pas lu ; seuls les résultats affichés sont relus.
        $rows = self::rows();
        $results = [];
        foreach (self::order() as $i) {
            $r = $rows[$i];
            if ($r[self::L_NOINDEX]) {
                continue;
            }
            $id = $r[self::L_ID];
            $title = mb_strtolower($r[self::L_TITLE]);
            $summary = (string) ($texts[self::SEARCH_HEAD . $id] ?? '');
            $body = (string) ($texts[$id] ?? '');
            $score = 0;
            foreach ($terms as $term) {
                $hit = (str_contains($title, $term) ? 6 : 0) + (str_contains($summary, $term) ? 3 : 0) + min(5, substr_count($body, $term));
                if ($hit === 0) {
                    continue 2;
                }
                $score += $hit;
            }
            $results[] = [$score, $r[self::L_DATE], $i];
        }
        usort($results, static fn (array $a, array $b): int => $b[0] <=> $a[0] ?: $b[1] <=> $a[1]);
        $out = [];
        foreach ($results as [, , $i]) {
            if (count($out) >= $limit) {
                break;
            }
            $entry = self::hydrate($rows[$i]);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /**
     * Plusieurs enregistrements à la suite (import d'un site) : l'index de recherche (jusqu'à 16 Mo), relu et réécrit en
     * entier à chaque contenu publié, ne l'est plus qu'une fois pour tout le groupe. L'index des contenus, lui, reste écrit à
     * chaque enregistrement : ce qui est en ligne ne dépend jamais de la fin du groupe. Si le groupe est interrompu, les
     * contenus déjà enregistrés le restent ; seul leur texte manque à la recherche (ils se trouvent par leur titre) jusqu'à
     * leur prochain enregistrement. Un enregistrement fait entre-temps par une autre requête est conservé (fusion à la fin).
     */
    public static function batch(callable $fn): mixed
    {
        self::$batch++;
        try {
            return $fn();
        } finally {
            if (--self::$batch === 0 && self::$pending !== null) {
                [$search, $stamp, , $touched] = self::$pending;
                self::$pending = null;
                if ($touched !== []) {
                    // L'index des contenus n'est plus utile au groupe : lâché avant d'écrire l'index de recherche (16 Mo au plus,
                    // et autant pour son texte), pour que la fin d'un lot d'import ne soit pas son pic de mémoire.
                    self::hold(null);
                    try {
                        Store::locked(static function () use ($search, $stamp, $touched): void {
                            $file = Store::file('search');
                            clearstatcache(true, $file);
                            if ((@filesize($file) ?: 0) . '.' . (@filemtime($file) ?: 0) !== $stamp) {
                                // Fichier changé par une autre requête pendant le groupe : on repart du sien, et seules les clés
                                // que le groupe a touchées (textes ajoutés, changés ou retirés) y sont reportées. L'index des
                                // contenus est déjà lâché : deux index de recherche tiennent alors en mémoire sans lui.
                                $now = Store::read('search', []);
                                $now = is_array($now) ? $now : [];
                                foreach ($touched as $key => $unused) {
                                    if (array_key_exists($key, $search)) {
                                        $now[$key] = $search[$key];
                                    } else {
                                        unset($now[$key]);
                                    }
                                }
                                $search = $now;
                            }
                            Store::write('search', $search);
                            // Pas gardé pour la suite de la requête (l'import en ligne de commande enchaîne les lots).
                            Store::drop('search');
                        });
                    } catch (\Throwable $e) {
                        Log::error($e);
                    }
                }
            }
        }
    }

    /** Index de recherche courant et sa taille : le fichier, ou celui du groupe d'enregistrements en cours. */
    private static function searchNow(): array
    {
        $file = Store::file('search');
        if (self::$batch === 0) {
            $current = Store::read('search', []);
            return [is_array($current) ? $current : [], (int) @filesize($file)];
        }
        if (self::$pending === null) {
            clearstatcache(true, $file);
            $stamp = (@filesize($file) ?: 0) . '.' . (@filemtime($file) ?: 0);
            $current = Store::read('search', []);
            // Gardé par le groupe seulement : pas une seconde fois dans la mémoire des données lues.
            Store::drop('search');
            self::$pending = [is_array($current) ? $current : [], $stamp, (int) @filesize($file), []];
        }
        return [self::$pending[0], self::$pending[2]];
    }

    /** Écrit l'index de recherche, ou le garde pour la fin du groupe d'enregistrements ($touched : clés ajoutées, changées ou retirées). */
    private static function putSearch(array $search, int $size, array $touched): void
    {
        if (self::$batch > 0 && self::$pending !== null) {
            self::$pending[0] = $search;
            self::$pending[2] = $size;
            self::$pending[3] += $touched;
            return;
        }
        Store::write('search', $search);
    }

    /**
     * Recherche à partir de l'index complet : tant que data/search.php vient d'une version sans extraits (avant le premier
     * enregistrement fait par cette version), mêmes résultats qu'avant.
     */
    private static function searchByIndex(array $terms, array $texts, int $limit): array
    {
        $results = [];
        foreach (self::published() as $id => $entry) {
            if ($entry['noindex'] ?? false) {
                continue;
            }
            $title = mb_strtolower($entry['title']);
            $summary = mb_strtolower($entry['excerpt'] . ' ' . ($entry['summary'] ?? ''));
            $body = mb_strtolower((string) ($texts[$id] ?? ''));
            $score = 0;
            foreach ($terms as $term) {
                $hit = (str_contains($title, $term) ? 6 : 0) + (str_contains($summary, $term) ? 3 : 0) + min(5, substr_count($body, $term));
                if ($hit === 0) {
                    continue 2;
                }
                $score += $hit;
            }
            $results[] = ['score' => $score, 'entry' => $entry];
        }
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['entry']['date'] <=> $a['entry']['date']);
        return array_map(fn ($r) => $r['entry'], array_slice($results, 0, $limit));
    }

    /* ────────────────────────────── Écriture ─────────────────────────────── */

    /**
     * Crée ou met à jour un contenu à partir des données de l'éditeur.
     * Gère : slug unique, redirection 301 si l'URL change, révisions, index, cache.
     * $expect (enregistrement conditionnel, outils de l'IA) reçoit la version enregistrée, relue sous verrou (null pour un
     * nouveau contenu) : s'il ne l'accepte pas, rien n'est écrit (UnexpectedValueException).
     * Un nouveau contenu au-delà de MAX_ITEMS est refusé (OverflowException).
     */
    public static function save(array $data, array $user, ?callable $expect = null): array
    {
        // Dès la première écriture, le cache est vidé même si une écriture suivante échoue (index de recherche sur un disque
        // plein…) : une page dépubliée n'y reste jamais servie.
        $written = false;
        try {
            $result = Store::locked(function () use ($data, $user, $expect, &$written): array {
                self::hold(null);
                $index = self::index();
                $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
                $old = $id !== '' && isset($index[$id]) ? self::get($id) : null;
                if ($expect !== null && !$expect($old)) {
                    throw new \UnexpectedValueException('Contenu modifié entre-temps.');
                }
                if ($old === null && count($index) >= self::MAX_ITEMS) {
                    throw new \OverflowException(t('Le site compte déjà {n} contenus, le maximum : supprime ceux qui ne servent plus avant d’en créer d’autres.', ['n' => self::MAX_ITEMS]));
                }
                $item = self::build($data, $user, $old, $index);
                $id = $item['id'];
                $entry = self::entry($item);
                $oldEntry = $index[$id] ?? null;
                $index[$id] = $entry;
                // Index de recherche préparé (et sa taille vérifiée) avant toute écriture : un refus ne laisse rien à moitié enregistré.
                $search = self::searchTexts($index, $id, $item['status'] === 'published' ? Blocks::text($item['blocks']) : null);
                $written = true;
                if ($old !== null) {
                    self::addRevision($old);
                    if ($oldEntry !== null && self::visible($oldEntry)) {
                        $from = self::path($oldEntry);
                        $to = self::path($entry);
                        if ($from !== $to && $from !== '/') {
                            Redirects::add($from, $to, true);
                        }
                    }
                }
                // L'index décide de ce qui est en ligne. Un contenu qui le quitte (brouillon, date future) y est inscrit avant d'être
                // écrit : une écriture interrompue (disque plein…) laisse toujours la version la moins visible, jamais un brouillon public.
                if ($old !== null && !self::visible($entry)) {
                    self::writeIndex($index);
                    Store::write('content/' . $id, $item);
                } else {
                    Store::write('content/' . $id, $item);
                    self::writeIndex($index);
                }
                self::hold($index);
                if ($search !== null) {
                    self::putSearch($search[0], $search[1], $search[2]);
                }
                if ($item['status'] !== 'draft') {
                    Redirects::remove(self::path($entry));
                }
                return $item;
            });
        } finally {
            if ($written) {
                Cache::purge();
            }
        }
        return $result;
    }

    /**
     * Construit un contenu propre et validé à partir des données de l'éditeur, sans l'enregistrer
     * (sert aussi à l'analyse SEO en direct et à l'aperçu).
     */
    public static function build(array $data, array $user, ?array $old = null, ?array $index = null): array
    {
        $index ??= self::index();
        $id = $old['id'] ?? random_id();
        $now = time();
        $type = $old['type'] ?? (in_array($data['type'] ?? '', ['page', 'product'], true) ? (string) $data['type'] : 'post');
        $lang = array_key_exists($data['lang'] ?? '', self::LANGS) ? (string) $data['lang'] : (string) Settings::get('lang');
        $title = Sanitize::text($data['title'] ?? '', 200);
        $title = $title !== '' ? $title : t('Sans titre');
        $status = ($data['status'] ?? '') === 'published' ? 'published' : 'draft';

        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $type === 'page' ? Sanitize::path($slug !== '' ? $slug : $title, $lang) : slugify($slug !== '' ? $slug : $title, $lang);
        // Un contenu existant garde son adresse tant qu'on ne la change pas, même si le mot est devenu réservé depuis.
        if ($old === null || $slug !== $old['slug']) {
            $slug = self::uniqueSlug($slug !== '' ? $slug : $id, $type, $id, $index);
        }

        $date = (int) ($old['date'] ?? 0);
        if (!empty($data['date'])) {
            $parsed = strtotime((string) $data['date']);
            $date = $parsed !== false ? $parsed : $date;
        } elseif ($status === 'published' && ($old['status'] ?? 'draft') === 'draft') {
            $date = $now;
        }
        if ($date === 0) {
            $date = $now;
        }

        // Adresse exacte (migration d'un ancien site : /2024/03/article/, /page.html, accents) : gardée tant qu'on ne la change
        // pas, abandonnée si elle n'est plus valide ou déjà prise par un autre contenu.
        $route = array_key_exists('route', $data) ? self::cleanRoute((string) $data['route']) : (string) ($old['route'] ?? '');
        if ($route !== '' && self::routeTaken($route, $id, $index)) {
            $route = '';
        }

        $seo = is_array($data['seo'] ?? null) ? $data['seo'] : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $category = preg_replace('/[^a-z0-9]/', '', (string) ($data['category'] ?? '')) ?? '';
        $author = preg_replace('/[^a-z0-9]/', '', (string) ($data['author'] ?? '')) ?? '';
        $toc = (string) ($options['toc'] ?? 'auto');

        $item = [
            'id' => $id,
            'type' => $type,
            'status' => $status,
            'title' => $title,
            'slug' => $slug,
            'route' => $route,
            'lang' => $lang,
            'tgroup' => preg_replace('/[^a-z0-9]/', '', (string) ($data['tgroup'] ?? $old['tgroup'] ?? '')) ?? '',
            'blocks' => Blocks::normalize($data['blocks'] ?? []),
            'summary' => Sanitize::multiline($data['summary'] ?? '', 800),
            'image' => preg_replace('/[^a-z0-9]/', '', (string) ($data['image'] ?? '')) ?? '',
            'category' => $type !== 'page' && (Categories::get($category)['kind'] ?? '') === $type ? $category : '',
            'author' => Users::find($author) ? $author : (string) ($old['author'] ?? $user['id']),
            'created' => (int) ($old['created'] ?? $now),
            'date' => $date,
            // Reprise d'un ancien site : la date de dernière modification d'origine est gardée (clé « modified », que seuls
            // les imports envoient), sinon tout le site paraîtrait modifié le jour de la migration (sitemap, dateModified).
            'updated' => ($m = is_string($data['modified'] ?? null) ? (int) strtotime($data['modified']) : 0) >= $date && $m > 0 && $m <= $now ? $m : $now,
            'seo' => [
                'title' => Sanitize::text($seo['title'] ?? '', 120),
                'description' => Sanitize::text($seo['description'] ?? '', 320),
                'keyword' => Sanitize::text($seo['keyword'] ?? '', 80),
                'canonical' => Sanitize::url((string) ($seo['canonical'] ?? '')),
                'noindex' => !empty($seo['noindex']),
            ],
            'options' => [
                'hide_title' => !empty($options['hide_title']),
                'toc' => in_array($toc, ['auto', 'on', 'off'], true) ? $toc : 'auto',
                'wide' => !empty($options['wide']),
                // « Sans publicité » (éditeur, quand la publicité est en service) : aucune annonce sur ce contenu.
                'noads' => !empty($options['noads']),
            ],
        ];
        if ($type === 'product') {
            $item['product'] = Shop::normalize($data['product'] ?? []);
        }
        $analysis = Analyzer::run($item);
        $item['score'] = ['seo' => $analysis['seo'], 'geo' => $analysis['geo']];
        return $item;
    }

    /**
     * Extrait d'un contenu, tel que l'index le garde : sa description SEO, sinon le début de son premier paragraphe, sinon
     * de son texte. Sans calculer tout le résumé (nombre de mots…) : pour les sorties qui parcourent tout le site.
     */
    public static function excerptOf(array $item, ?string $text = null): string
    {
        return $item['seo']['description'] !== '' ? $item['seo']['description']
            : excerpt(Blocks::intro($item['blocks']) ?: ($text ?? Blocks::text($item['blocks'])), 220);
    }

    /** Résumé d'un contenu pour l'index. */
    public static function entry(array $item): array
    {
        $text = Blocks::text($item['blocks']);
        $excerpt = self::excerptOf($item, $text);
        return [
            'id' => $item['id'],
            'type' => $item['type'],
            'status' => $item['status'],
            'title' => $item['title'],
            'slug' => $item['slug'],
            'lang' => $item['lang'],
            'tgroup' => $item['tgroup'],
            'date' => $item['date'],
            'updated' => $item['updated'],
            'created' => $item['created'],
            'category' => $item['category'],
            'author' => $item['author'],
            'image' => $item['image'],
            'excerpt' => $excerpt,
            'summary' => excerpt($item['summary'], 300),
            'noindex' => $item['seo']['noindex'],
            'words' => word_count($text),
            'score' => $item['score'] ?? ['seo' => 0, 'geo' => 0],
        ] + (($item['route'] ?? '') !== '' ? ['route' => $item['route']] : []) + (isset($item['product']) ? self::productEntry($item) : []);
    }

    /** Résumé d'un produit pour l'index : prix le plus bas (« à partir de » si les variantes diffèrent), disponibilité. */
    private static function productEntry(array $item): array
    {
        $offers = Shop::offers($item);
        $prices = array_values(array_unique(array_filter(array_column($offers, 'price'), fn ($p) => $p !== '')));
        sort($prices, SORT_NUMERIC);
        // Disponibilité affichée : la meilleure des offres (« En stock » dès qu'une variante l'est, « Épuisé » si toutes le sont).
        $states = array_column($offers, 'availability');
        return [
            'price' => $prices[0] ?? '',
            'from' => count($prices) > 1,
            'compare' => $item['product']['compare'],
            'availability' => in_array('in_stock', $states, true) ? 'in_stock' : (in_array('preorder', $states, true) ? 'preorder' : 'out_of_stock'),
        ];
    }

    public static function delete(string $id, string $redirectTo = ''): void
    {
        // Dès la première écriture, le cache est vidé même si une écriture suivante échoue (voir save()).
        $written = false;
        try {
            Store::locked(function () use ($id, $redirectTo, &$written): void {
                self::hold(null);
                $index = self::index();
                if (!isset($index[$id])) {
                    return;
                }
                $entry = $index[$id];
                $written = true;
                // Le fichier d'abord : s'il ne peut pas être supprimé (droits du dossier), rien ne change et l'erreur est signalée,
                // au lieu d'un fichier orphelin qu'une reconstruction de l'index remettrait en ligne.
                Store::delete('content/' . $id);
                if (is_file(Store::file('content/' . $id))) {
                    throw new \RuntimeException("Impossible de supprimer « content/" . $id . '.php » : vérifie les droits du dossier.');
                }
                if (self::visible($entry) && $redirectTo !== '') {
                    Redirects::add(self::path($entry), $redirectTo, false);
                }
                unset($index[$id]);
                self::writeIndex($index);
                self::hold($index);
                foreach (Store::names('revisions/' . $id) as $rev) {
                    Store::delete($rev);
                }
                @rmdir(ECLAIR_DATA . '/revisions/' . $id);
                $search = self::searchTexts($index, $id, null);
                if ($search !== null) {
                    self::putSearch($search[0], $search[1], $search[2]);
                }
                if (Settings::get('home') === $id) {
                    Settings::save(['home' => '']);
                }
            });
        } finally {
            if ($written) {
                Cache::purge();
            }
        }
    }

    public static function duplicate(string $id, array $user): ?array
    {
        $item = self::get($id);
        if (!$item) {
            return null;
        }
        $item['id'] = '';
        $item['status'] = 'draft';
        $item['title'] = $item['title'] . ' ' . t('(copie)');
        $item['slug'] = $item['slug'] . '-copie';
        $item['tgroup'] = '';
        $item['date'] = '';
        return self::save($item, $user);
    }

    /**
     * Relie deux contenus comme traductions l'un de l'autre. Sous verrou, à partir des versions enregistrées : un
     * enregistrement concurrent (un contenu dépublié pendant ce temps) n'est jamais écrasé par une copie périmée.
     */
    public static function linkTranslation(string $id, string $otherId): void
    {
        $linked = Store::locked(function () use ($id, $otherId): bool {
            self::hold(null);
            $index = self::index();
            $a = isset($index[$id]) ? self::get($id) : null;
            $b = isset($index[$otherId]) ? self::get($otherId) : null;
            if (!$a || !$b || $id === $otherId) {
                return false;
            }
            $group = $b['tgroup'] !== '' ? $b['tgroup'] : ($a['tgroup'] !== '' ? $a['tgroup'] : $b['id']);
            foreach ([$a, $b] as $item) {
                $item['tgroup'] = $group;
                Store::write('content/' . $item['id'], $item);
                $index[$item['id']] = self::entry($item);
            }
            self::writeIndex($index);
            self::hold($index);
            return true;
        });
        if ($linked) {
            Cache::purge();
        }
    }

    /**
     * Reconstruit l'index à partir des fichiers (réparation). Sous verrou, les contenus relus sous ce verrou : un
     * enregistrement concurrent (un contenu dépublié pendant la reconstruction) n'est jamais écrasé par l'état d'avant.
     */
    public static function reindex(): array
    {
        return Store::locked(function (): array {
            $previous = Store::read('index');
            $index = [];
            foreach (Store::names('content') as $name) {
                $item = Store::read($name);
                if (is_array($item) && isset($item['id'], $item['blocks'])) {
                    // Fichier que l'index lisible ne connaît pas (laissé par une suppression ratée avant la 1.10.3) : il revient
                    // en brouillon, à relire, et jamais en ligne avec son ancien statut. Sans index lisible, rien ne change.
                    if (is_array($previous) && !isset($previous[$item['id']]) && ($item['status'] ?? '') !== 'draft') {
                        $item['status'] = 'draft';
                        Store::write($name, $item);
                    }
                    // Fichier incomplet (écrit à la main ou par un outil, sans les champs qu'un enregistrement normal garantit) :
                    // laissé de côté et signalé dans le journal, au lieu de faire répondre 500 à toutes les pages du site.
                    try {
                        $index[$item['id']] = self::entry($item);
                    } catch (\Throwable $e) {
                        Log::error(new \RuntimeException('Contenu ignoré par l’index (fichier incomplet) : ' . $name, 0, $e));
                    }
                }
                Store::drop($name);
            }
            if (Settings::installed()) {
                self::writeIndex($index);
            }
            return self::hold($index);
        });
    }

    /* ────────────────────────────── Révisions ─────────────────────────────── */

    private static function addRevision(array $item): void
    {
        Store::write('revisions/' . $item['id'] . '/' . $item['updated'] . '-' . substr(bin2hex(random_bytes(2)), 0, 4), $item);
        $names = Store::names('revisions/' . $item['id']);
        sort($names);
        foreach (array_slice($names, 0, max(0, count($names) - self::MAX_REVISIONS)) as $name) {
            Store::delete($name);
        }
    }

    public static function revisions(string $id): array
    {
        $out = [];
        foreach (Store::names('revisions/' . preg_replace('/[^a-z0-9]/', '', $id)) as $name) {
            $rev = Store::read($name);
            if (is_array($rev)) {
                $out[] = ['name' => basename($name), 'updated' => (int) $rev['updated'], 'title' => $rev['title'], 'words' => word_count(Blocks::text($rev['blocks']))];
            }
        }
        usort($out, fn ($a, $b) => $b['updated'] <=> $a['updated']);
        return $out;
    }

    public static function revision(string $id, string $name): ?array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) {
            return null;
        }
        $rev = Store::read('revisions/' . preg_replace('/[^a-z0-9]/', '', $id) . '/' . $name);
        return is_array($rev) ? $rev : null;
    }

    /* ─────────────────────────────── Outils ─────────────────────────────── */

    /** Slug libre dans son espace d'URL (racine pour les pages, blog pour les articles). */
    public static function uniqueSlug(string $slug, string $type, string $id, ?array $index = null): string
    {
        $index ??= self::index();
        $taken = [];
        foreach ($index as $e) {
            if ($e['id'] !== $id && $e['type'] === $type) {
                $taken[$e['slug']] = true;
            }
        }
        if ($type === 'page') {
            foreach (Settings::reserved() as $word) {
                $taken[$word] = true;
            }
        } else {
            foreach (Categories::of($type) as $cat) {
                $taken[$cat['slug']] = true;
            }
            $words = Theme::words();
            $taken[$words['page']] = $taken[$words['author']] = $taken['feed'] = true;
        }
        $first = $type === 'page' ? explode('/', $slug)[0] : $slug;
        if (!isset($taken[$slug]) && !($type === 'page' && in_array($first, Settings::reserved(), true))) {
            return $slug;
        }
        for ($n = 2; ; $n++) {
            if (!isset($taken[$slug . '-' . $n])) {
                return $slug . '-' . $n;
            }
        }
    }

    /**
     * [Index de recherche (data/search.php) après l'écriture d'un contenu, sa taille estimée, clés touchées], ou null s'il ne change pas. Seul le texte des
     * contenus publiés (programmés compris) y figure : la recherche publique ne lit jamais celui d'un brouillon, et l'assistant
     * retrouve les brouillons par leur titre, leur adresse et leur extrait. Les textes que d'anciennes versions gardaient pour
     * les brouillons partent au passage. L'index ne dépasse jamais MAX_SEARCH_BYTES : quand un texte le ferait déborder, seul
     * son début y entre (SEARCH_SHORT), ou rien s'il n'y a plus de place. La publication n'est jamais refusée pour autant :
     * le contenu reste trouvable par son titre, son extrait et son résumé (Content::search les lit dans l'index).
     */
    private static function searchTexts(array $index, string $id, ?string $text): ?array
    {
        [$current, $bytes] = self::searchNow();
        $published = array_filter($index, fn (array $e): bool => $e['status'] === 'published');
        $all = array_intersect_key($current, $published);
        // Fichier d'une version précédente : textes mis en minuscules, une fois.
        $upgrade = ($current[self::SEARCH_MARK] ?? null) !== self::SEARCH_VERSION;
        if ($upgrade) {
            $all = array_map(static fn (mixed $t): string => mb_strtolower((string) $t), $all);
        }
        $size = static fn (string $s): int => strlen((string) json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP));
        $head = static fn (array $e): string => mb_strtolower(($e['excerpt'] ?? '') . ' ' . ($e['summary'] ?? ''));
        // Place restante, d'après la taille du fichier actuel (majorant : un fichier d'avant la 2.2 est indenté).
        $room = self::MAX_SEARCH_BYTES - $bytes - 64;
        $touched = [$id => true, self::SEARCH_HEAD . $id => true, self::SEARCH_MARK => true];
        if ($text === null) {
            unset($all[$id]);
        } else {
            // Un texte nouveau prend aussi la place de sa clé (« "id": », virgule).
            $had = isset($current[$id]) ? $size((string) $current[$id]) : -(strlen($id) + 4);
            $text = mb_strtolower($text);
            foreach ([self::SEARCH_TEXT, self::SEARCH_SHORT, 0] as $keep) {
                $kept = mb_substr($text, 0, $keep);
                if ($keep === 0 || $size($kept) - $had <= $room) {
                    break;
                }
            }
            if ($keep === 0) {
                unset($all[$id]);
            } else {
                $all[$id] = $kept;
                $room -= $size($kept) - $had;
            }
            if ($keep !== self::SEARCH_TEXT && mb_strlen($text) > $keep) {
                Log::alert('search-full', 'Index de recherche plein (' . self::MAX_SEARCH_BYTES . ' octets) : les contenus publiés depuis n’y entrent plus en entier ; '
                    . 'ils restent trouvables par leur titre, leur extrait et leur résumé.');
            }
        }
        // Extrait et résumé de chaque contenu publié : repris tels quels, refaits pour le contenu enregistré et pour ceux qui
        // n'en ont pas encore (fichier d'une version précédente, contenus arrivés autrement). S'il n'y a plus de place, un
        // contenu reste trouvable par son titre.
        foreach ($published as $pid => $entry) {
            $key = self::SEARCH_HEAD . $pid;
            if ((string) $pid !== $id && isset($current[$key]) && !$upgrade) {
                $all[$key] = $current[$key];
                continue;
            }
            $value = $head($entry);
            $grows = $size($value) + strlen($key) + 4 - (isset($current[$key]) ? $size((string) $current[$key]) + strlen($key) + 4 : 0);
            if ($grows <= $room) {
                $all[$key] = $value;
                $room -= $grows;
                $touched[$key] = true;
            }
        }
        $all[self::SEARCH_MARK] = self::SEARCH_VERSION;
        if ($all === $current) {
            return null;
        }
        // Clés retirées (contenus qui ne sont plus publiés) ou, à la mise à niveau, toutes : pour la fusion de fin de groupe.
        $touched += array_fill_keys(array_keys($upgrade ? $all + $current : array_diff_key($current, $all)), true);
        return [$all, self::MAX_SEARCH_BYTES - 64 - $room, $touched];
    }

    /** Durée de lecture en minutes. */
    public static function readingTime(int $words): int
    {
        return max(1, (int) round($words / 220));
    }
}
