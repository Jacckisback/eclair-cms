<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Outils de l'assistant IA. Ce que l'IA peut faire est volontairement borné : chercher et lire les contenus,
 * créer des BROUILLONS, modifier un brouillon (annulable) ou PROPOSER une modification d'un contenu publié
 * (appliquée d'un clic par la personne), chercher et créer des images. Elle ne publie, ne supprime et ne règle rien.
 */
final class AiTools
{
    private const MAX_DRAFTS = 4;
    private const MAX_IMAGES = 3;
    private const MAX_UPDATES = 8;
    private const KEEP_DAYS = 14; // annulations et propositions en attente

    /** Définitions envoyées au modèle (schémas JSON). */
    public static function definitions(): array
    {
        $str = static fn (string $d = ''): array => $d === '' ? ['type' => 'string'] : ['type' => 'string', 'description' => $d];
        $qa = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['question' => $str(), 'answer' => $str('2-4 sentences, Markdown allowed')],
            'required' => ['question', 'answer'], 'additionalProperties' => false]];
        $person = ['type' => 'object', 'properties' => [
            'name' => $str(), 'role' => $str('Job title'), 'bio' => $str('1-3 sentences, only facts given by the user or the site'),
            'photo' => $str('media id of the portrait (optional)'), 'link' => $str('Profile URL, e.g. LinkedIn (optional)'),
        ], 'required' => ['name'], 'additionalProperties' => false];
        $tools = [
            [
                'name' => 'search_content',
                'description' => 'Search the site\'s pages and articles by keywords (title and text). With an empty query, lists the most recent content. Use it to avoid duplicates, to find the content to change (for example the team page) and to choose internal links.',
                'schema' => ['type' => 'object', 'properties' => [
                    'query' => $str('Keywords; empty to list recent content'),
                    'type' => ['type' => 'string', 'enum' => ['any', 'post', 'page']],
                ], 'additionalProperties' => false],
            ],
            [
                'name' => 'get_content',
                'description' => 'Read one page or article: metadata and full text as Markdown (images appear as media:ID, special blocks in [brackets]).',
                'schema' => ['type' => 'object', 'properties' => ['id' => $str()], 'required' => ['id'], 'additionalProperties' => false],
            ],
            [
                'name' => 'create_draft',
                'description' => 'Create a new article or page as a DRAFT (never published: the user publishes it). Returns its id and its SEO/GEO scores out of 100.',
                'schema' => ['type' => 'object', 'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['post', 'page'], 'description' => 'post = blog article, page = site page'],
                    'title' => $str('Title shown on the page (H1)'),
                    'body' => $str('Content in Markdown, without the title and without the FAQ'),
                    'summary' => $str('« L’essentiel »: 2-3 sentences, 25-90 words, a direct answer AI engines can quote'),
                    'keyword' => $str('Main key phrase (2-4 words)'),
                    'seo_title' => $str('Max 60 characters'),
                    'seo_description' => $str('120-155 characters'),
                    'slug' => $str('Short URL slug (optional)'),
                    'category' => $str('Category id from the context (articles, optional)'),
                    'image' => $str('media id of the featured image (optional)'),
                    'faq' => $qa,
                    'team' => ['type' => 'array', 'items' => $person, 'description' => 'Team members block (pages, optional)'],
                    'lang' => $str('Content language code if different from the site language (optional)'),
                ], 'required' => ['type', 'title', 'body'], 'additionalProperties' => false],
            ],
            [
                'name' => 'update_content',
                'description' => 'Change an existing page or article. Send only what changes. A draft is updated directly (the user can undo); for published content the changes become a proposal the user previews and applies in one click; the content open in the editor is changed in the editor.',
                'schema' => ['type' => 'object', 'properties' => [
                    'id' => $str(),
                    'note' => $str('What you change, in the user\'s language, under 12 words'),
                    'title' => $str(),
                    'summary' => $str('« L’essentiel »'),
                    'keyword' => $str(),
                    'seo_title' => $str(),
                    'seo_description' => $str(),
                    'image' => $str('media id of the new featured image'),
                    'replace' => ['type' => 'array', 'description' => 'Rewrite passages: exact text copied from get_content, and its replacement (Markdown inline allowed)',
                        'items' => ['type' => 'object', 'properties' => ['find' => $str(), 'replace' => $str()], 'required' => ['find', 'replace'], 'additionalProperties' => false]],
                    'append' => $str('New Markdown section(s) to add'),
                    'after_heading' => $str('Exact text of the heading after whose section "append" is inserted (default: end of content)'),
                    'faq' => $qa,
                    'team_member' => $person,
                ], 'required' => ['id', 'note'], 'additionalProperties' => false],
            ],
            [
                'name' => 'find_media',
                'description' => 'Search the media library by file name or description. Returns media ids usable in content (images, portraits).',
                'schema' => ['type' => 'object', 'properties' => ['query' => $str('Keywords; empty for the latest images')], 'additionalProperties' => false],
            ],
        ];
        if (Ai::imager() !== '') {
            $tools[] = [
                'name' => 'generate_image',
                'description' => 'Create an image with the image AI and add it to the media library. Takes 10 to 60 seconds and costs about $'
                    . number_format(Ai::IMAGES[Ai::imager()][2], 2) . '. Returns its media id.',
                'schema' => ['type' => 'object', 'properties' => [
                    'prompt' => $str('Detailed visual description in English, 40-90 words'),
                    'alt' => $str('Alternative text in the content language, under 125 characters'),
                    'format' => ['type' => 'string', 'enum' => ['landscape', 'square', 'portrait']],
                    'name' => $str('Short file name, 2-5 words'),
                ], 'required' => ['prompt', 'alt'], 'additionalProperties' => false],
            ];
        }
        return $tools;
    }

    /** Libellé affiché pendant l'exécution d'un outil. */
    public static function status(string $name): string
    {
        return match ($name) {
            'search_content' => t('Recherche dans le site…'),
            'get_content' => t('Lecture du contenu…'),
            'create_draft' => t('Rédaction du brouillon…'),
            'update_content' => t('Préparation des modifications…'),
            'find_media' => t('Recherche dans la médiathèque…'),
            'generate_image' => t('Création de l’image (10 à 60 secondes)…'),
            default => t('Travail en cours…'),
        };
    }

    /**
     * Exécute un outil. $ctx : user, editing (contenu ouvert dans l'éditeur : id + item), turn (compteurs du tour).
     * Renvoie ['result' => données pour le modèle, 'card' => carte à afficher ou null, 'error' => bool].
     */
    public static function run(string $name, array $in, array &$ctx): array
    {
        try {
            return match ($name) {
                'search_content' => ['result' => self::search($in), 'card' => null, 'error' => false],
                'get_content' => self::read($in, $ctx),
                'create_draft' => self::create($in, $ctx),
                'update_content' => self::update($in, $ctx),
                'find_media' => ['result' => self::findMedia((string) ($in['query'] ?? '')), 'card' => null, 'error' => false],
                'generate_image' => self::image($in, $ctx),
                default => self::fail('Unknown tool "' . $name . '". Available: search_content, get_content, create_draft, update_content, find_media'
                    . (Ai::imager() !== '' ? ', generate_image' : '') . '.'),
            };
        } catch (\RuntimeException $e) {
            return self::fail($e->getMessage());
        }
    }

    private static function fail(string $message): array
    {
        return ['result' => ['error' => $message], 'card' => null, 'error' => true];
    }

    /* ─────────────────────────────── Lecture ─────────────────────────────── */

    private static function search(array $in): array
    {
        $type = in_array($in['type'] ?? '', ['post', 'page'], true) ? $in['type'] : '';
        $terms = array_filter(preg_split('/\s+/u', mb_strtolower(trim((string) ($in['query'] ?? '')))) ?: [], fn ($w) => mb_strlen($w) >= 2);
        $texts = Store::read('search', []);
        $found = [];
        foreach (Content::index() as $id => $e) {
            if ($type !== '' && $e['type'] !== $type) {
                continue;
            }
            $score = 1;
            if ($terms) {
                $hay = mb_strtolower($e['title'] . ' ' . $e['slug'] . ' ' . $e['excerpt']);
                $body = mb_strtolower((string) ($texts[$id] ?? ''));
                $score = 0;
                foreach ($terms as $w) {
                    $score += (str_contains($hay, $w) ? 5 : 0) + min(3, substr_count($body, $w));
                }
            }
            if ($score > 0) {
                $found[] = [$score, $e];
            }
        }
        usort($found, fn ($a, $b) => $b[0] <=> $a[0] ?: $b[1]['updated'] <=> $a[1]['updated']);
        return ['results' => array_map(fn ($f) => self::summary($f[1]), array_slice($found, 0, 15))];
    }

    private static function summary(array $e): array
    {
        return [
            'id' => $e['id'], 'type' => $e['type'], 'title' => $e['title'], 'status' => self::state($e),
            'path' => Content::path($e), 'lang' => $e['lang'], 'words' => $e['words'], 'excerpt' => excerpt($e['excerpt'], 160),
        ];
    }

    private static function state(array $e): string
    {
        return $e['status'] === 'draft' ? 'draft' : ((int) $e['date'] > time() ? 'scheduled' : 'published');
    }

    private static function read(array $in, array $ctx): array
    {
        $id = preg_replace('/[^a-z0-9]/', '', (string) ($in['id'] ?? '')) ?? '';
        $item = ($ctx['editing']['id'] ?? '') === $id && $id !== '' ? $ctx['editing']['item'] : Content::get($id);
        if (!$item) {
            return self::fail('No content with id "' . $id . '". Use search_content to find ids.');
        }
        $entry = Content::entry($item + ['score' => ['seo' => 0, 'geo' => 0]]);
        return ['result' => [
            'id' => $item['id'] ?: $id, 'type' => $item['type'], 'status' => $item['id'] === '' ? 'draft' : self::state($entry),
            'title' => $item['title'], 'path' => Content::path($entry), 'lang' => $item['lang'], 'category' => $item['category'],
            'summary' => $item['summary'], 'keyword' => $item['seo']['keyword'], 'seo_title' => $item['seo']['title'],
            'seo_description' => $item['seo']['description'], 'image' => $item['image'],
            'markdown' => mb_substr(Markdown::fromBlocks($item['blocks']), 0, 24000),
        ], 'card' => null, 'error' => false];
    }

    private static function findMedia(string $query): array
    {
        $terms = array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [], fn ($w) => mb_strlen($w) >= 2);
        $out = [];
        foreach (Media::all() as $m) {
            $hay = mb_strtolower($m['name'] . ' ' . $m['alt'] . ' ' . basename((string) $m['file']));
            if (!$terms || array_filter($terms, fn ($w) => str_contains($hay, $w))) {
                $out[] = ['id' => $m['id'], 'name' => $m['name'], 'alt' => $m['alt'], 'width' => $m['w'], 'height' => $m['h'], 'added' => date('Y-m-d', (int) $m['created'])];
            }
            if (count($out) >= 12) {
                break;
            }
        }
        return ['media' => $out, 'total_in_library' => count(Media::all())];
    }

    /* ───────────────────────────── Brouillons ───────────────────────────── */

    private static function create(array $in, array &$ctx): array
    {
        if (++$ctx['turn']['drafts'] > self::MAX_DRAFTS) {
            return self::fail('Limit reached: at most ' . self::MAX_DRAFTS . ' drafts per request. Tell the user and stop.');
        }
        $type = ($in['type'] ?? '') === 'page' ? 'page' : 'post';
        $title = Sanitize::text($in['title'] ?? '', 200);
        $blocks = Markdown::toBlocks((string) ($in['body'] ?? ''));
        if ($title === '' || !$blocks) {
            return self::fail('A draft needs a title and a non-empty Markdown body.');
        }
        // Le titre est déjà le H1 : un « # Titre » répété en tête de texte est retiré.
        if ($blocks[0]['type'] === 'heading' && mb_strtolower(plain($blocks[0]['text'])) === mb_strtolower($title)) {
            array_shift($blocks);
        }
        if (!empty($in['team']) && is_array($in['team'])) {
            $blocks[] = ['type' => 'team', 'title' => '', 'items' => array_map([self::class, 'person'], array_filter($in['team'], 'is_array'))];
        }
        if (!empty($in['faq']) && is_array($in['faq'])) {
            $blocks[] = ['type' => 'faq', 'title' => '', 'items' => self::faqItems($in['faq'])];
        }
        $category = (string) ($in['category'] ?? '');
        $lang = (string) ($in['lang'] ?? '');
        $image = (string) ($in['image'] ?? '');
        $item = Content::save([
            'type' => $type,
            'status' => 'draft',
            'title' => $title,
            'slug' => (string) ($in['slug'] ?? ''),
            'lang' => isset(Content::LANGS[$lang]) ? $lang : Settings::get('lang'),
            'blocks' => $blocks,
            'summary' => (string) ($in['summary'] ?? ''),
            'image' => Media::get($image) ? $image : '',
            'category' => $type === 'post' && Categories::get($category) ? $category : '',
            'author' => $ctx['user']['id'],
            'seo' => ['title' => (string) ($in['seo_title'] ?? ''), 'description' => (string) ($in['seo_description'] ?? ''), 'keyword' => (string) ($in['keyword'] ?? '')],
            'options' => ['toc' => 'auto'],
        ], $ctx['user']);
        $analysis = Analyzer::run($item);
        $todo = array_values(array_map(fn ($c) => $c['label'], array_filter($analysis['checks'], fn ($c) => $c['status'] === 'bad')));
        return [
            'result' => [
                'id' => $item['id'], 'status' => 'draft (not published)', 'words' => $analysis['words'],
                'seo_score' => $analysis['seo'], 'geo_score' => $analysis['geo'], 'weak_points' => array_slice($todo, 0, 5),
            ],
            'card' => self::draftCard($item),
            'error' => false,
        ];
    }

    /** Carte « brouillon » affichée dans la conversation. */
    public static function draftCard(array $item): array
    {
        $entry = Content::entry($item);
        $m = Media::get($item['image']);
        return [
            'kind' => 'draft', 'id' => $item['id'], 'type' => $item['type'], 'title' => $item['title'], 'state' => self::state($entry),
            'words' => $entry['words'], 'seo' => $item['score']['seo'] ?? 0, 'geo' => $item['score']['geo'] ?? 0,
            'thumb' => $m ? path_to(Media::thumb($m)) : '', 'edit' => admin_url('edit/' . $item['id']), 'preview' => admin_url('preview/' . $item['id']),
        ];
    }

    private static function person(array $p): array
    {
        $photo = preg_replace('/[^a-z0-9]/', '', (string) ($p['photo'] ?? '')) ?? '';
        return [
            'name' => (string) ($p['name'] ?? ''),
            'role' => (string) ($p['role'] ?? ''),
            'bio' => Markdown::inline((string) ($p['bio'] ?? '')),
            'media' => Media::get($photo) ? $photo : '',
            'link' => (string) ($p['link'] ?? ''),
        ];
    }

    private static function faqItems(array $faq): array
    {
        $out = [];
        foreach (array_slice($faq, 0, 12) as $q) {
            if (is_array($q) && trim((string) ($q['question'] ?? '')) !== '' && trim((string) ($q['answer'] ?? '')) !== '') {
                $out[] = ['q' => (string) $q['question'], 'a' => Markdown::inline((string) $q['answer'])];
            }
        }
        return $out;
    }

    /* ───────────────────────────── Modifications ───────────────────────────── */

    private static function update(array $in, array &$ctx): array
    {
        if (++$ctx['turn']['updates'] > self::MAX_UPDATES) {
            return self::fail('Limit reached: at most ' . self::MAX_UPDATES . ' changes per request. Tell the user and stop.');
        }
        $id = preg_replace('/[^a-z0-9]/', '', (string) ($in['id'] ?? '')) ?? '';
        $editing = ($ctx['editing']['id'] ?? null) === $id ? $ctx['editing']['item'] : null;
        // Contenu publié déjà modifié dans ce tour : la nouvelle modification complète la même proposition.
        // Si la personne l'a déjà appliquée ou ignorée entre-temps, une nouvelle proposition repart du contenu actuel.
        $pid = (string) ($ctx['turn']['proposals'][$id] ?? '');
        $proposal = $pid !== '' ? Store::read('ai/proposals/' . $pid) : null;
        $pid = is_array($proposal) ? $pid : '';
        $item = $editing ?? (is_array($proposal) ? $proposal['data'] : Content::get($id));
        if (!$item) {
            return self::fail('No content with id "' . $id . '". Use search_content to find ids.');
        }
        [$changed, $labels, $missing] = self::apply($item, $in);
        if (!$labels) {
            return self::fail($missing ? 'Nothing changed: these passages were not found word for word in the visible text, or they cover only part of a link or of a formatted phrase: "'
                . implode('", "', $missing) . '". Copy the exact text from get_content, including whole links and whole bold or italic phrases.'
                : 'Nothing to change: send at least one field to modify.');
        }
        $note = Sanitize::text($in['note'] ?? '', 120);
        $warn = $missing ? ['not_found' => $missing] : [];
        $built = Content::build($changed, $ctx['user'], $item['id'] !== '' ? Content::get($item['id']) : null);
        $scores = ['seo_score' => $built['score']['seo'], 'geo_score' => $built['score']['geo']];
        // Plusieurs modifications du même contenu dans un tour : une seule carte, qui les récapitule toutes.
        $merge = static function (string $key) use (&$ctx, $labels, $note): array {
            $prev = $ctx['turn']['cards'][$key] ?? ['changes' => [], 'notes' => []];
            $merged = ['changes' => array_values(array_unique(array_merge($prev['changes'], $labels))),
                'notes' => array_values(array_unique(array_filter(array_merge($prev['notes'], [$note]))))];
            $ctx['turn']['cards'][$key] = $merged;
            return ['changes' => $merged['changes'], 'note' => implode(' · ', $merged['notes'])];
        };

        // Contenu ouvert dans l'éditeur : la modification s'applique dans l'éditeur (la personne enregistre).
        if ($editing !== null) {
            // Les modifications suivantes du tour (et get_content) partent de cette version.
            $ctx['editing']['item'] = ['id' => $item['id']] + $built;
            $media = [];
            foreach (array_filter(array_merge([$built['image']], Blocks::media($built['blocks']))) as $mid) {
                if ($m = Media::get($mid)) {
                    $media[$mid] = AdminMedia::export($m);
                }
            }
            $patch = ['title' => $built['title'], 'summary' => $built['summary'], 'image' => $built['image'], 'seo' => $built['seo'], 'blocks' => $built['blocks']];
            return ['result' => ['done' => 'Applied in the editor; the user saves it.'] + $scores + $warn,
                'card' => ['kind' => 'editor', 'id' => $id, 'title' => $built['title'], 'note' => $note, 'changes' => $labels, 'patch' => $patch, 'media' => $media],
                'error' => false];
        }
        $entry = Content::index()[$id] ?? null;
        if ($entry && $entry['status'] === 'draft') {
            $saved = Content::save($changed, $ctx['user']);
            // Une seule annulation par brouillon et par tour : elle rétablit la version d'avant la demande.
            $token = (string) ($ctx['turn']['undo'][$id] ?? '');
            $undo = $token !== '' ? Store::read('ai/undo/' . $token) : null;
            if (is_array($undo)) {
                Store::write('ai/undo/' . $token, ['after' => self::fingerprint($saved)] + $undo);
            } else {
                $token = self::snapshot($item, $saved, $ctx['user']['id']);
                unset($ctx['turn']['cards']['u-' . $id]); // modification précédente annulée entre-temps : nouveau récapitulatif
            }
            $ctx['turn']['undo'][$id] = $token;
            return ['result' => ['done' => 'Draft updated (the user can undo).'] + $scores + $warn,
                'card' => ['kind' => 'updated', 'key' => 'u-' . $id, 'id' => $id, 'title' => $saved['title'], 'undo' => $token,
                    'edit' => admin_url('edit/' . $id), 'preview' => admin_url('preview/' . $id)] + $merge('u-' . $id),
                'error' => false];
        }
        if ($pid === '') {
            self::prune('ai/proposals');
            $pid = random_id();
        }
        $ctx['turn']['proposals'][$id] = $pid;
        $card = ['kind' => 'proposal', 'key' => 'p-' . $pid, 'pid' => $pid, 'id' => $id, 'title' => (string) ($entry['title'] ?? $item['title'])] + $merge('p-' . $pid);
        Store::write('ai/proposals/' . $pid, [
            'id' => $pid, 'user' => $ctx['user']['id'], 'content' => $id, 'base' => is_array($proposal) ? (int) $proposal['base'] : (int) $item['updated'],
            'data' => $changed, 'note' => $card['note'], 'changes' => $card['changes'], 'created' => time(),
        ]);
        return ['result' => ['done' => 'Proposal ready: the user previews and applies it with one click (not applied yet).'] + $scores + $warn,
            'card' => $card,
            'error' => false];
    }

    /** Copie d'un contenu avant modification par l'IA (pour « Annuler »), valable tant que le contenu n'a pas changé depuis. */
    public static function snapshot(array $before, array $after, string $userId): string
    {
        self::prune('ai/undo');
        $token = random_id();
        Store::write('ai/undo/' . $token, ['user' => $userId, 'item' => $before, 'after' => self::fingerprint($after), 'created' => time()]);
        return $token;
    }

    /** L'annulation est-elle encore possible (version gardée, et contenu inchangé depuis la modification) ? */
    public static function undoable(string $token): bool
    {
        $u = preg_match('/^[a-z0-9]{1,32}$/', $token) ? Store::read('ai/undo/' . $token) : null;
        $current = is_array($u) ? Content::get((string) ($u['item']['id'] ?? '')) : null;
        return $current !== null && self::fingerprint($current) === ($u['after'] ?? '');
    }

    /** Empreinte d'un contenu, hors date de mise à jour : elle change dès que quelqu'un modifie quoi que ce soit. */
    private static function fingerprint(array $item): string
    {
        unset($item['updated'], $item['score']);
        ksort($item);
        return md5((string) json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Supprime les annulations et propositions de plus de 14 jours. */
    private static function prune(string $dir): void
    {
        foreach (Store::names($dir) as $name) {
            if (@filemtime(Store::file($name)) < time() - self::KEEP_DAYS * 86400) {
                Store::delete($name);
            }
        }
    }

    /**
     * Applique les changements demandés à une copie du contenu.
     * Renvoie [données à enregistrer, libellés des changements, passages introuvables].
     */
    public static function apply(array $item, array $in): array
    {
        $labels = [];
        $missing = [];
        $set = static function (string $field, string $value) use (&$item): bool {
            $parts = explode('.', $field);
            $ref = &$item;
            foreach ($parts as $p) {
                $ref = &$ref[$p];
            }
            $changed = (string) $ref !== $value;
            $ref = $value;
            return $changed;
        };
        if (isset($in['title']) && trim((string) $in['title']) !== '' && $set('title', Sanitize::text($in['title'], 200))) {
            $labels[] = t('Titre');
        }
        if (isset($in['summary']) && trim((string) $in['summary']) !== '' && $set('summary', Sanitize::multiline($in['summary'], 800))) {
            $labels[] = t('L’essentiel');
        }
        $seo = false;
        foreach (['keyword' => 'seo.keyword', 'seo_title' => 'seo.title', 'seo_description' => 'seo.description'] as $k => $field) {
            if (isset($in[$k]) && trim((string) $in[$k]) !== '') {
                $seo = $set($field, Sanitize::text($in[$k], $k === 'seo_description' ? 320 : 120)) || $seo;
            }
        }
        if ($seo) {
            $labels[] = t('Référencement (titre SEO, description, expression-clé)');
        }
        if (!empty($in['image']) && Media::get((string) $in['image']) && $set('image', (string) $in['image'])) {
            $labels[] = t('Image à la une');
        }
        $blocks = $item['blocks'];
        if (!empty($in['replace']) && is_array($in['replace'])) {
            $done = 0;
            foreach (array_slice($in['replace'], 0, 30) as $r) {
                $find = trim((string) ($r['find'] ?? ''));
                if ($find === '') {
                    continue;
                }
                if (self::replace($blocks, $find, (string) ($r['replace'] ?? ''))) {
                    $done++;
                } else {
                    $missing[] = mb_substr($find, 0, 80);
                }
            }
            if ($done > 0) {
                $labels[] = t('{n} passage(s) réécrit(s)', ['n' => $done]);
            }
        }
        if (trim((string) ($in['append'] ?? '')) !== '') {
            $new = Markdown::toBlocks((string) $in['append']);
            if ($new) {
                array_splice($blocks, self::insertAt($blocks, (string) ($in['after_heading'] ?? '')), 0, $new);
                $labels[] = t('Nouvelle section ajoutée');
            }
        }
        if (!empty($in['faq']) && is_array($in['faq']) && ($faq = self::faqItems($in['faq']))) {
            $at = array_search('faq', array_column($blocks, 'type'), true);
            if ($at === false) {
                array_splice($blocks, self::insertAt($blocks, ''), 0, [['type' => 'faq', 'title' => '', 'items' => $faq]]);
            } else {
                $known = array_map(fn ($q) => mb_strtolower($q['q']), $blocks[$at]['items']);
                foreach ($faq as $q) {
                    if (!in_array(mb_strtolower($q['q']), $known, true)) {
                        $blocks[$at]['items'][] = $q;
                    }
                }
            }
            $labels[] = t('{n} question(s) ajoutée(s) à la FAQ', ['n' => count($faq)]);
        }
        if (!empty($in['team_member']) && is_array($in['team_member']) && trim((string) ($in['team_member']['name'] ?? '')) !== '') {
            $person = self::person($in['team_member']);
            $at = array_search('team', array_column($blocks, 'type'), true);
            if ($at === false) {
                array_splice($blocks, self::insertAt($blocks, ''), 0, [['type' => 'team', 'title' => '', 'items' => [$person]]]);
            } else {
                $found = false;
                foreach ($blocks[$at]['items'] as $k => $p) {
                    if (mb_strtolower($p['name']) === mb_strtolower($person['name'])) {
                        $blocks[$at]['items'][$k] = array_filter($person, fn ($v) => $v !== '') + $p;
                        $found = true;
                    }
                }
                if (!$found) {
                    $blocks[$at]['items'][] = $person;
                }
            }
            $labels[] = t('Nouveau membre dans l’équipe : {name}', ['name' => Sanitize::text($person['name'], 120)]);
        }
        $item['blocks'] = Blocks::normalize($blocks);
        // Données au format de l'éditeur : la date existante est conservée telle quelle.
        $item['date'] = '';
        return [$item, $labels, $missing];
    }

    /**
     * Remplace un passage dans le texte enrichi des blocs (première occurrence). La recherche porte sur le texte
     * visible, jamais sur les balises ni sur les adresses des liens, et la mise en forme autour du passage est conservée.
     */
    private static function replace(array &$blocks, string $find, string $with): bool
    {
        // Le modèle copie le passage depuis le Markdown de get_content : seul son texte visible est cherché.
        // S'il a repris la mise en forme (**gras**, [lien](…)), les éléments qu'il couvre entièrement sont remplacés avec.
        $found = Markdown::inline($find);
        $needle = trim(html_entity_decode(strip_tags($found), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($needle === '') {
            return false;
        }
        $formatted = str_contains($found, '<');
        $html = Markdown::inline($with);
        $swap = static function (string &$field) use ($needle, $html, $formatted): bool {
            $new = self::swapText($field, $needle, $html, $formatted);
            if ($new === null) {
                return false;
            }
            $field = $new;
            return true;
        };
        foreach ($blocks as &$b) {
            if (in_array($b['type'], ['paragraph', 'heading', 'quote', 'callout'], true) && $swap($b['text'])) {
                return true;
            }
            if ($b['type'] === 'list') {
                foreach ($b['items'] as &$li) {
                    if ($swap($li)) {
                        return true;
                    }
                }
                unset($li);
            }
            if ($b['type'] === 'faq') {
                foreach ($b['items'] as &$qa) {
                    if ($swap($qa['a'])) {
                        return true;
                    }
                }
                unset($qa);
            }
            if (in_array($b['type'], ['features', 'team'], true)) {
                $key = $b['type'] === 'team' ? 'bio' : 'text';
                foreach ($b['items'] as &$it) {
                    if ($swap($it[$key])) {
                        return true;
                    }
                }
                unset($it);
            }
        }
        unset($b);
        return false;
    }

    /**
     * Remplace $needle (texte visible, espaces souples) par le HTML $replacement dans un champ de texte enrichi.
     * Un passage qui couvre des éléments entiers (lien, gras…) les remplace ; un passage qui n'en couvre
     * qu'une partie n'est pas touché, pour ne pas abîmer la mise en forme. Un passage qui correspond exactement
     * au texte d'un élément reste dans l'élément, sauf si le modèle a repris sa mise en forme ($formatted).
     * Renvoie null si rien n'est remplacé.
     */
    private static function swapText(string $field, string $needle, string $replacement, bool $formatted = false): ?string
    {
        if ($field === '') {
            return null;
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="eclair-old">' . $field . '</div><div id="eclair-new">' . $replacement . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('eclair-old');
        $new = $doc->getElementById('eclair-new');
        if (!$root || !$new) {
            return null;
        }
        // Texte visible et position de chacun de ses morceaux (un <br> compte comme un saut de ligne).
        $parts = [];
        $text = '';
        $walk = static function (\DOMNode $node) use (&$walk, &$parts, &$text): void {
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMText || ($child instanceof \DOMElement && strtolower($child->nodeName) === 'br')) {
                    $t = $child instanceof \DOMText ? $child->data : "\n";
                    $parts[] = [$child, strlen($text), strlen($text) + strlen($t)];
                    $text .= $t;
                } elseif ($child instanceof \DOMElement) {
                    $walk($child);
                }
            }
        };
        $walk($root);
        $space = '[\s\x{00A0}\x{202F}]+';
        $words = preg_split('/' . $space . '/u', $needle) ?: [];
        $pattern = implode($space, array_map(static fn (string $w): string => strtr(preg_quote($w, '/'), ["'" => "['’]", '’' => "['’]"]), $words));
        if (!preg_match('/' . $pattern . '/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $s = (int) $m[0][1];
        $e = $s + strlen($m[0][0]);
        $first = $last = null;
        foreach ($parts as $i => $part) {
            if ($first === null && $s < $part[2]) {
                $first = $i;
            }
            if ($e <= $part[2]) {
                $last = $i;
                break;
            }
        }
        if ($first === null || $last === null) {
            return null;
        }
        [$startNode, $startAt] = $parts[$first];
        [$endNode, $endAt] = $parts[$last];
        $ancestors = static function (\DOMNode $n) use ($root): array {
            $out = [];
            for ($p = $n->parentNode; $p !== null && !$p->isSameNode($root); $p = $p->parentNode) {
                $out[] = $p;
            }
            return $out;
        };
        $within = static fn (\DOMNode $x, array $list): bool => array_filter($list, fn (\DOMNode $y): bool => $y->isSameNode($x)) !== [];
        // Début ou fin du texte visible d'un élément.
        $edge = static function (\DOMNode $x, bool $end) use ($parts): int {
            $at = -1;
            foreach ($parts as [$node, $a, $b]) {
                for ($p = $node; $p !== null; $p = $p->parentNode) {
                    if ($p->isSameNode($x)) {
                        if (!$end) {
                            return $a;
                        }
                        $at = $b;
                        break;
                    }
                }
            }
            return $at;
        };
        $up = $ancestors($startNode);
        $down = $ancestors($endNode);
        foreach ($up as $x) {
            if (!$within($x, $down) && $edge($x, false) !== $s) {
                return null; // le passage commence au milieu d'un lien ou d'un texte en gras
            }
        }
        foreach ($down as $x) {
            if (!$within($x, $up) && $edge($x, true) !== $e) {
                return null; // … ou s'y termine
            }
        }
        $common = $root;
        foreach ($up as $x) {
            if ($within($x, $down)) {
                $common = $x;
                break;
            }
        }
        while ($formatted && !$common->isSameNode($root) && $edge($common, false) === $s && $edge($common, true) === $e) {
            $common = $common->parentNode;
        }
        $top = static function (\DOMNode $n) use ($common): \DOMNode {
            while (!$n->parentNode->isSameNode($common)) {
                $n = $n->parentNode;
            }
            return $n;
        };
        $from = $top($startNode);
        $to = $top($endNode);
        $before = $from instanceof \DOMText ? substr($from->data, 0, $s - $startAt) : '';
        $after = $to instanceof \DOMText ? substr($to->data, $e - $endAt) : '';
        // Pas de lien dans un lien : dans un <a>, les liens du texte de remplacement deviennent du texte simple.
        for ($p = $common; $p !== null && !$p->isSameNode($root); $p = $p->parentNode) {
            if (strtolower($p->nodeName) === 'a') {
                foreach (iterator_to_array($new->getElementsByTagName('a')) as $a) {
                    while ($a->firstChild) {
                        $a->parentNode->insertBefore($a->firstChild, $a);
                    }
                    $a->parentNode->removeChild($a);
                }
                break;
            }
        }
        $gone = [];
        for ($n = $from; $n !== null; $n = $n->nextSibling) {
            $gone[] = $n;
            if ($n->isSameNode($to)) {
                break;
            }
        }
        if ($before !== '') {
            $common->insertBefore($doc->createTextNode($before), $from);
        }
        while ($new->firstChild) {
            $common->insertBefore($new->firstChild, $from);
        }
        if ($after !== '') {
            $common->insertBefore($doc->createTextNode($after), $from);
        }
        foreach ($gone as $n) {
            $common->removeChild($n);
        }
        return Sanitize::inline((string) $doc->saveHTML($root));
    }

    /** Position d'insertion : après la section d'un intertitre donné, sinon en fin (avant formulaire et derniers articles). */
    private static function insertAt(array $blocks, string $heading): int
    {
        $heading = mb_strtolower(trim($heading, " #\t"));
        if ($heading !== '') {
            foreach ($blocks as $i => $b) {
                if ($b['type'] === 'heading' && mb_strtolower(plain($b['text'])) === $heading) {
                    for ($j = $i + 1; $j < count($blocks); $j++) {
                        if (($blocks[$j]['type'] === 'heading' && $blocks[$j]['level'] <= $b['level']) || in_array($blocks[$j]['type'], ['faq', 'contact', 'posts'], true)) {
                            return $j;
                        }
                    }
                    return count($blocks);
                }
            }
        }
        $at = count($blocks);
        while ($at > 0 && in_array($blocks[$at - 1]['type'], ['contact', 'posts', 'button'], true)) {
            $at--;
        }
        return $at;
    }

    /* ─────────────────────────────── Images ─────────────────────────────── */

    private static function image(array $in, array &$ctx): array
    {
        if (Ai::imager() === '') {
            return self::fail('Image generation is not configured.');
        }
        if (++$ctx['turn']['images'] > self::MAX_IMAGES) {
            return self::fail('Limit reached: at most ' . self::MAX_IMAGES . ' images per request.');
        }
        $m = AiImage::generate((string) ($in['prompt'] ?? ''), (string) ($in['format'] ?? 'landscape'), (string) ($in['name'] ?? ''), (string) ($in['alt'] ?? ''), $ctx['user']);
        return [
            'result' => ['media_id' => $m['id'], 'alt' => $m['alt'], 'width' => $m['w'], 'height' => $m['h']],
            'card' => ['kind' => 'image', 'media' => AdminMedia::export($m)],
            'error' => false,
        ];
    }
}
