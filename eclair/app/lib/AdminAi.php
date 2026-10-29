<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Assistant IA dans l'administration : conversation en flux, retouches ciblées, propositions, profil éditorial. */
final class AdminAi
{
    /** Données passées au JavaScript de l'assistant (jamais de clé). */
    public static function client(array $user): ?array
    {
        if (!Ai::allowed($user)) {
            return null;
        }
        return [
            'model' => Ai::label(),
            'images' => Ai::imager() !== '' ? (string) preg_replace('/\s*\(.*\)$/', '', Ai::IMAGES[Ai::imager()][0]) : '',
            'name' => explode(' ', trim((string) $user['name']))[0],
            'admin' => $user['role'] === 'admin',
            'langs' => Content::LANGS,
        ];
    }

    private static function guard(array $user): void
    {
        if (!Ai::allowed($user)) {
            Http::json(['ok' => false, 'error' => t('L’assistant IA n’est pas activé pour ton compte.')], 403);
        }
    }

    /* ───────────────────────────── Conversation ───────────────────────────── */

    public static function chat(array $user): never
    {
        self::guard($user);
        $data = Req::json();
        $message = trim(mb_substr(Sanitize::utf8(str_replace("\0", '', (string) ($data['message'] ?? ''))), 0, 8000));
        if ($message === '') {
            Http::json(['ok' => false, 'error' => t('Écris ta demande.')], 422);
        }
        if (($error = Ai::reserve($user)) !== null) {
            Http::json(['ok' => false, 'error' => $error], 429);
        }
        $editing = self::editing($data['context'] ?? null, $user);
        self::stream();
        $emit = static function (array $event): void {
            echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        };
        try {
            AiChat::run($user, preg_replace('/[^a-z0-9]/', '', (string) ($data['chat'] ?? '')) ?? '', $message, (array) ($data['media'] ?? []), $editing, $emit);
        } catch (\Throwable $e) {
            $emit(['t' => 'error', 'error' => t('Erreur interne : réessaie. Référence : {id}', ['id' => Log::error($e)])]);
            $emit(['t' => 'done']);
        }
        exit;
    }

    /** Réponse envoyée au fil de l'eau (une ligne JSON par événement), sans tampon ni compression. */
    private static function stream(): void
    {
        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(600);
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-store, no-transform');
        header('X-Accel-Buffering: no');
        header('X-Content-Type-Options: nosniff');
        // Quelques hébergeurs attendent 2 à 4 Ko avant d'envoyer : une ligne d'espaces débloque le flux.
        echo str_repeat(' ', 4096), "\n";
        flush();
    }

    /** Contenu ouvert dans l'éditeur (données non enregistrées comprises), nettoyé comme à l'enregistrement. */
    private static function editing(mixed $ctx, array $user): ?array
    {
        if (!is_array($ctx) || !is_array($ctx['item'] ?? null)) {
            return null;
        }
        $data = $ctx['item'];
        $id = preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '';
        $old = $id !== '' ? Content::get($id) : null;
        if ($id !== '' && !$old) {
            return null;
        }
        $item = Content::build($data, $user, $old);
        $item['id'] = $id;
        return ['id' => $id !== '' ? $id : 'current', 'item' => $item];
    }

    public static function chats(array $user): never
    {
        self::guard($user);
        Http::json(['ok' => true, 'chats' => AiChat::list($user['id'])]);
    }

    public static function load(array $user, string $id): never
    {
        self::guard($user);
        $chat = AiChat::load($user['id'], $id);
        Http::json($chat ? ['ok' => true, 'chat' => AiChat::export($chat)] : ['ok' => false], $chat ? 200 : 404);
    }

    public static function forget(array $user): never
    {
        self::guard($user);
        AiChat::delete($user['id'], (string) (Req::json()['id'] ?? ''));
        Http::json(['ok' => true]);
    }

    /* ─────────────────────── Cartes : appliquer, annuler, publier ─────────────────────── */

    public static function apply(array $user): never
    {
        $pid = preg_replace('/[^a-z0-9]/', '', (string) (Req::json()['pid'] ?? '')) ?? '';
        $p = $pid !== '' ? Store::read('ai/proposals/' . $pid) : null;
        if (!is_array($p) || ($p['user'] !== $user['id'] && $user['role'] !== 'admin')) {
            Http::json(['ok' => false, 'error' => t('Proposition introuvable ou déjà traitée.')], 404);
        }
        $current = Content::get((string) $p['content']);
        if (!$current) {
            Http::json(['ok' => false, 'error' => t('Ce contenu n’existe plus.')], 404);
        }
        if ((int) $current['updated'] !== (int) $p['base']) {
            Http::json(['ok' => false, 'error' => t('Ce contenu a été modifié depuis la proposition : demande une nouvelle proposition à l’assistant.')], 409);
        }
        $undo = AiTools::snapshot($current, $user['id']);
        $saved = Content::save($p['data'], $user);
        Store::delete('ai/proposals/' . $pid);
        AiChat::markProposal((string) $p['user'], $pid, ['applied' => true, 'undo' => $undo]);
        self::ping($saved);
        Log::security('ai_change_applied', ['content' => $saved['id']]);
        Http::json(['ok' => true, 'undo' => $undo, 'url' => path_to(Content::path(Content::entry($saved)))]);
    }

    /** Aperçu d'une proposition, rendu comme la page publique. */
    public static function preview(array $user, string $pid): never
    {
        $p = Store::read('ai/proposals/' . $pid);
        if (!is_array($p) || ($p['user'] !== $user['id'] && $user['role'] !== 'admin')) {
            Site::notFound();
        }
        $item = Content::build($p['data'], $user, Content::get((string) $p['content']));
        Site::single(Content::entry($item), true, $item);
    }

    public static function dismiss(array $user): never
    {
        $pid = preg_replace('/[^a-z0-9]/', '', (string) (Req::json()['pid'] ?? '')) ?? '';
        $p = $pid !== '' ? Store::read('ai/proposals/' . $pid) : null;
        if (is_array($p) && ($p['user'] === $user['id'] || $user['role'] === 'admin')) {
            Store::delete('ai/proposals/' . $pid);
            AiChat::markProposal((string) $p['user'], $pid, ['dismissed' => true]);
        }
        Http::json(['ok' => true]);
    }

    /** Rétablit la version d'avant une modification faite par l'IA. */
    public static function undo(array $user): never
    {
        $token = preg_replace('/[^a-z0-9]/', '', (string) (Req::json()['token'] ?? '')) ?? '';
        $u = $token !== '' ? Store::read('ai/undo/' . $token) : null;
        if (!is_array($u) || ($u['user'] !== $user['id'] && $user['role'] !== 'admin')) {
            Http::json(['ok' => false, 'error' => t('Annulation impossible : cette version n’est plus disponible.')], 404);
        }
        $item = $u['item'];
        if (!Content::get((string) $item['id'])) {
            Http::json(['ok' => false, 'error' => t('Ce contenu n’existe plus.')], 404);
        }
        $item['date'] = '@' . (int) $item['date'];
        $saved = Content::save($item, $user);
        Store::delete('ai/undo/' . $token);
        self::ping($saved);
        Http::json(['ok' => true]);
    }

    /** Publie (ou programme) un brouillon préparé par l'assistant : c'est toujours une personne qui clique. */
    public static function publish(array $user): never
    {
        $data = Req::json();
        $item = Content::get(preg_replace('/[^a-z0-9]/', '', (string) ($data['id'] ?? '')) ?? '');
        if (!$item) {
            Http::json(['ok' => false, 'error' => t('Ce contenu n’existe plus.')], 404);
        }
        $date = trim((string) ($data['date'] ?? ''));
        if ($date !== '' && strtotime($date) === false) {
            Http::json(['ok' => false, 'error' => t('Date invalide.')], 422);
        }
        $item['status'] = 'published';
        $item['date'] = $date !== '' ? $date : '@' . time();
        $saved = Content::save($item, $user);
        self::ping($saved);
        $entry = Content::entry($saved);
        Http::json(['ok' => true, 'card' => AiTools::draftCard($saved), 'url' => path_to(Content::path($entry)), 'visible' => Content::visible($entry)]);
    }

    /** Signale un contenu publié à IndexNow, après la réponse (comme l'enregistrement depuis l'éditeur). */
    private static function ping(array $item): void
    {
        $entry = Content::entry($item);
        if (Content::visible($entry) && !$item['seo']['noindex'] && IndexNow::enabled()) {
            $path = Content::path($entry);
            register_shutdown_function(static function () use ($path): void {
                Http::finish();
                IndexNow::ping([$path]);
            });
        }
    }

    /* ──────────────────────────── Retouches ciblées ──────────────────────────── */

    public static function assist(array $user): never
    {
        self::guard($user);
        $data = Req::json();
        $task = (string) ($data['task'] ?? '');
        if (($error = Ai::reserve($user)) !== null) {
            Http::json(['ok' => false, 'error' => $error], 429);
        }
        session_write_close();
        @set_time_limit(300);
        $str = ['type' => 'string'];
        $obj = static fn (array $props): array => ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false];
        $item = is_array($data['item'] ?? null) ? Content::build($data['item'], $user, Content::get((string) ($data['item']['id'] ?? ''))) : null;
        $doc = $item ? 'Type: ' . ($item['type'] === 'page' ? 'page' : 'article') . "\nLanguage: " . $item['lang'] . "\nTitle: " . $item['title']
            . "\nKey phrase: " . ($item['seo']['keyword'] ?: '(none)') . "\n\n" . mb_substr(Markdown::fromBlocks($item['blocks']), 0, 20000) : '';

        switch ($task) {
            case 'rewrite':
                $modes = [
                    'improve' => 'Improve clarity, rhythm and impact, keeping the meaning and the voice of the profile.',
                    'shorten' => 'Make it about 40% shorter, keeping the key information.',
                    'expand' => 'Develop it with useful, concrete details (about 50% longer); invent no facts about the business.',
                    'fix' => 'Fix spelling, grammar, punctuation and typography only (French typography rules for French text); change nothing else.',
                    'simplify' => 'Make it simpler and easier to read: shorter sentences, everyday words.',
                    'warmer' => 'Make it warmer and more engaging, closer to the reader.',
                    'formal' => 'Make it more professional and formal.',
                    'translate' => 'Translate it into ' . (Content::LANGS[(string) ($data['lang'] ?? '')] ?? 'English') . ', naturally, as a native writer would.',
                ];
                $mode = (string) ($data['mode'] ?? 'improve');
                $html = Sanitize::inline((string) ($data['html'] ?? ''));
                if ($html === '' || !isset($modes[$mode])) {
                    Http::json(['ok' => false, 'error' => t('Sélectionne d’abord le texte à retravailler.')], 422);
                }
                $res = AiChat::ask(
                    "Rewrite the passage given by the person. " . $modes[$mode] . ' The passage is inline HTML (only <strong>, <em>, <a href>, <code>, <br> are allowed): keep its links and formatting where they still make sense, and answer with the rewritten passage only, as inline HTML without <p>, in the "html" field.'
                    . ($item ? "\nIt comes from this " . ($item['type'] === 'page' ? 'page' : 'article') . ': "' . $item['title'] . '".' : ''),
                    $html, $obj(['html' => $str]));
                self::charged($user, $res);
                Http::json(['ok' => true, 'html' => Sanitize::inline((string) ($res['data']['html'] ?? ''))]);

            case 'titles':
            case 'meta':
            case 'summary':
            case 'faq':
            case 'keyword':
                if (!$item || $item['blocks'] === []) {
                    Http::json(['ok' => false, 'error' => t('Écris d’abord un peu de contenu : l’IA s’appuie sur ton texte.')], 422);
                }
                [$instruction, $schema] = match ($task) {
                    'titles' => ['Suggest 5 different titles for this content (40 to 60 characters, with the key phrase if there is one; varied angles: benefit, question, number, how-to, promise). Content language.',
                        $obj(['titles' => ['type' => 'array', 'items' => $str]])],
                    'meta' => ['Write the SEO title (at most 60 characters) and the meta description (120 to 155 characters, with the key phrase and a reason to click) of this content. Content language.',
                        $obj(['seo_title' => $str, 'seo_description' => $str])],
                    'summary' => ['Write « L’essentiel » of this content: 2 to 3 sentences, 25 to 90 words, a direct and self-contained answer to the reader\'s question that answer engines can quote. Content language, plain text.',
                        $obj(['summary' => $str])],
                    'faq' => ['Write 4 questions readers really ask about this subject that the content does not fully answer yet, each with a 2 to 4 sentence answer (Markdown inline allowed). Content language. Invent no facts about the business.',
                        $obj(['items' => ['type' => 'array', 'items' => $obj(['question' => $str, 'answer' => $str])]])],
                    default => ['Suggest the main key phrase (2 to 4 words) this content should target in search engines: the query its readers type. Content language.',
                        $obj(['keyword' => $str])],
                };
                $res = AiChat::ask($instruction, $doc, $schema);
                self::charged($user, $res);
                $out = $res['data'];
                if ($task === 'faq') {
                    $out = ['items' => array_values(array_filter(array_map(fn ($q) => is_array($q) ? ['q' => Sanitize::text($q['question'] ?? '', 300), 'a' => Markdown::inline((string) ($q['answer'] ?? ''))] : null, (array) ($out['items'] ?? [])),
                        fn ($q) => $q && $q['q'] !== '' && $q['a'] !== ''))];
                } elseif ($task === 'titles') {
                    $out = ['titles' => array_values(array_filter(array_map(fn ($x) => Sanitize::text($x, 200), (array) ($out['titles'] ?? []))))];
                } else {
                    $out = array_map(fn ($v) => Sanitize::multiline(is_scalar($v) ? (string) $v : '', 800), $out);
                }
                Http::json(['ok' => true] + $out);

            case 'alt':
                $m = Media::get((string) ($data['media'] ?? ''));
                $image = AiChat::imageData($m);
                if (!$m || !$image) {
                    Http::json(['ok' => false, 'error' => t('Image introuvable')], 404);
                }
                $lang = isset(Content::LANGS[(string) ($data['lang'] ?? '')]) ? (string) $data['lang'] : (string) Settings::get('lang');
                $res = AiChat::ask('Write the alternative text of this image for a visually impaired reader, in ' . Content::LANGS[$lang]
                    . ': what it shows that matters, under 125 characters, without "image of" or "photo of".'
                    . ($item ? ' It illustrates: "' . $item['title'] . '".' : ''), 'File: ' . $m['name'], $obj(['alt' => $str]), [$image]);
                self::charged($user, $res);
                $m = Media::update($m['id'], ['alt' => (string) ($res['data']['alt'] ?? '')]) ?? $m;
                Http::json(['ok' => true, 'media' => AdminMedia::export($m)]);

            case 'cover':
                if (Ai::imager() === '') {
                    Http::json(['ok' => false, 'error' => t('Aucun générateur d’images n’est configuré (Réglages › IA).')], 422);
                }
                if (!$item || ($item['title'] === t('Sans titre') && $item['blocks'] === [])) {
                    Http::json(['ok' => false, 'error' => t('Écris d’abord un peu de contenu : l’IA s’appuie sur ton texte.')], 422);
                }
                $res = AiChat::ask('Design the featured image of this content: a prompt for the image AI (English, 40 to 90 words: subject, setting, composition, light, colours, mood, following the visual style of the profile; natural editorial photograph by default; no text, logos or recognisable real people), its alternative text in the content language (under 125 characters) and a short file name (2 to 5 words).',
                    $doc, $obj(['prompt' => $str, 'alt' => $str, 'name' => $str]));
                self::charged($user, $res);
                try {
                    $m = AiImage::generate((string) ($res['data']['prompt'] ?? ''), 'landscape', (string) ($res['data']['name'] ?? ''), (string) ($res['data']['alt'] ?? ''), $user);
                } catch (\RuntimeException $e) {
                    Http::json(['ok' => false, 'error' => $e->getMessage()], 502);
                }
                Http::json(['ok' => true, 'media' => AdminMedia::export($m)]);
        }
        Http::json(['ok' => false, 'error' => t('Action inconnue.')], 400);
    }

    /** Compte la dépense d'une tâche ponctuelle ; renvoie l'erreur au navigateur si elle a échoué. */
    private static function charged(array $user, array $res): void
    {
        Ai::charge($user['id'], (float) ($res['cost'] ?? 0));
        if (!$res['ok']) {
            Http::json(['ok' => false, 'error' => $res['error']], 502);
        }
    }

    /* ─────────────────────────────── Réglages ─────────────────────────────── */

    /** Vérifie une clé sans rien dépenser (lecture de la fiche du modèle chez le fournisseur). */
    public static function test(array $user): never
    {
        $data = Req::json();
        $provider = (string) ($data['provider'] ?? '');
        if (!isset(Ai::PROVIDERS[$provider])) {
            Http::json(['ok' => false, 'error' => t('Choisis d’abord un fournisseur.')], 422);
        }
        $key = trim((string) ($data['key'] ?? '')) ?: Vault::get('ai.' . $provider);
        if ($key === '') {
            Http::json(['ok' => false, 'error' => t('Colle d’abord ta clé d’API.')], 422);
        }
        $model = isset(Ai::MODELS[$provider][(string) ($data['model'] ?? '')]) ? (string) $data['model'] : Ai::model($provider);
        $error = Ai::verify($provider, $key, $model);
        Http::json($error === null
            ? ['ok' => true, 'message' => t('Connexion réussie : {model} répond avec cette clé.', ['model' => Ai::MODELS[$provider][$model][0]])]
            : ['ok' => false, 'error' => $error]);
    }

    /** Rédige le profil éditorial à partir du site (la personne le relit avant d'enregistrer). */
    public static function profile(array $user): never
    {
        if (!Ai::enabled()) {
            Http::json(['ok' => false, 'error' => t('Enregistre d’abord un fournisseur d’IA et sa clé.')], 422);
        }
        if (($error = Ai::reserve($user)) !== null) {
            Http::json(['ok' => false, 'error' => $error], 429);
        }
        session_write_close();
        @set_time_limit(300);
        $s = Settings::all();
        $parts = ['Site: ' . $s['name'] . "\nTagline: " . $s['tagline'] . "\nDescription: " . $s['description']
            . "\nPresentation for AI engines: " . $s['geo']['about'] . "\nOrganisation: " . ($s['seo']['entity_name'] ?: $s['name']) . ' (' . $s['seo']['entity'] . ')'
            . ($s['seo']['city'] !== '' ? ', ' . $s['seo']['city'] . ' ' . $s['seo']['country'] : '')];
        $pages = Content::published('page');
        $home = (string) $s['home'];
        uksort($pages, fn ($a, $b) => ((string) $b === $home) <=> ((string) $a === $home));
        foreach (array_merge(array_slice($pages, 0, 5, true), array_slice(Content::published('post'), 0, 4, true)) as $e) {
            $item = Content::get($e['id']);
            if ($item) {
                $parts[] = '## ' . ($e['type'] === 'page' ? 'Page' : 'Article') . ': ' . $item['title'] . "\n" . mb_substr(Markdown::fromBlocks($item['blocks']), 0, 3500);
            }
        }
        $str = ['type' => 'string'];
        $fields = ['about' => $str, 'audience' => $str, 'tone' => $str, 'values' => $str, 'rules' => $str, 'visual' => $str];
        $res = AiChat::ask(
            'Write the editorial profile of this site, in ' . (Content::LANGS[$s['lang']] ?? 'French') . ', from its content below. Each field: 1 to 4 short, concrete sentences specific to this site, based only on the material (invent nothing). '
            . 'about: who they are, what they offer, where, key facts. audience: who the content is for and what these readers need. tone: the voice (formal or informal address, sentence style, vocabulary, humour or not). '
            . 'values: what they stand for. rules: writing rules worth enforcing (calls to action, words to avoid, claims never to make). visual: the visual style of illustrations (light, colours, mood, subjects), consistent with the brand.',
            mb_substr(implode("\n\n", $parts), 0, 30000),
            ['type' => 'object', 'properties' => $fields, 'required' => array_keys($fields), 'additionalProperties' => false],
            [],
            'medium'
        );
        self::charged($user, $res);
        Http::json(['ok' => true, 'profile' => array_map(fn ($v) => Sanitize::multiline(is_scalar($v) ? (string) $v : '', 2000), $res['data'])]);
    }
}
