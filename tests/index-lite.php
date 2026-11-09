<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Index léger (inclus par tests/run.php) : les pages publiques trouvent adresses, listes, comptes et traductions sans lire
 * l'index complet, avec exactement les mêmes réponses que lui ; il se refait seul quand l'index change sans lui.
 */
declare(strict_types=1);

use Eclair\Categories;
use Eclair\Content;
use Eclair\Settings;
use Eclair\Store;

(static function (): void {
    section('Index léger : mêmes réponses que l’index complet, sans le lire');
    $author = ['id' => 'liteauteur', 'role' => 'admin', 'name' => 'Camille Lite', 'email' => 'lite@example.com'];
    $read = static fn (string $name): mixed => json_decode(Store::strip((string) @file_get_contents(ECLAIR_DATA . '/' . $name . '.php')), true);
    $raw = static fn (string $name, mixed $data): int|false => file_put_contents(ECLAIR_DATA . '/' . $name . '.php',
        Store::GUARD . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    // Comme une autre requête : rien de ce que ce processus garde en mémoire.
    $fresh = static function (): void {
        Content::forget();
        Store::forget();
    };
    $loaded = static function (): bool {
        $p = new ReflectionProperty(Content::class, 'index');
        $p->setAccessible(true);
        return $p->getValue() !== null;
    };
    foreach (['content', 'revisions'] as $dir) {
        exec('rm -rf ' . escapeshellarg(ECLAIR_DATA . '/' . $dir));
    }
    Store::write('index', []);
    Store::write('search', []);
    Store::delete('index-lite');
    Settings::installed() || Settings::save(['lang' => 'fr']);
    $homeBefore = (string) Settings::get('home');
    $fresh();

    $cat = Categories::save(['name' => 'Guides lite', 'kind' => 'post']);
    $save = static fn (array $data): array => Content::save($data + ['type' => 'post', 'status' => 'published',
        'blocks' => [['type' => 'paragraph', 'text' => 'Texte du contenu « ' . ($data['title'] ?? '') . ' », assez long pour donner un extrait.']]], $author);
    $home = $save(['type' => 'page', 'title' => 'Accueil lite']);
    $services = $save(['type' => 'page', 'title' => 'Services lite', 'slug' => 'services-lite']);
    $save(['type' => 'page', 'title' => 'Détail', 'slug' => 'services-lite/detail']);
    $old = $save(['type' => 'page', 'title' => 'Ancienne page', 'route' => '/2019/ancienne-page/']);
    $save(['type' => 'page', 'title' => 'Page brouillon', 'status' => 'draft']);
    $posts = [];
    for ($i = 1; $i <= 12; $i++) {
        // Dates égales deux à deux : l'ordre de l'index départage, comme dans la liste complète.
        $posts[$i] = $save(['title' => 'Article lite ' . $i, 'date' => date('Y-m-d H:i', time() - 86400 * intdiv($i + 1, 2)), 'category' => $i % 3 ? $cat['id'] : '',
            'summary' => 'Résumé ' . $i, 'seo' => ['noindex' => $i === 5]]);
    }
    $save(['title' => 'Article programmé', 'date' => date('Y-m-d H:i', time() + 86400)]);
    $save(['title' => 'Article brouillon', 'status' => 'draft']);
    $fr = $save(['title' => 'Bonjour', 'lang' => 'fr', 'tgroup' => 'groupelite']);
    $save(['title' => 'Hello', 'lang' => 'en', 'tgroup' => 'groupelite']);
    Settings::save(['home' => $home['id']]);

    $lite = (array) $read('index-lite');
    $index = (array) $read('index');
    // (un identifiant tout en chiffres devient une clé entière en PHP : comparés comme des textes)
    check('index léger écrit avec l’index : une ligne par contenu, dans le même ordre', count($lite['rows'] ?? []) === count($index) && count($index) === 21
        && array_column($lite['rows'], 0) === array_map('strval', array_keys($index)) && ($lite['of'] ?? '') === filesize(ECLAIR_DATA . '/index.php') . '.' . filemtime(ECLAIR_DATA . '/index.php'),
        count($lite['rows'] ?? []) . ' lignes, ' . count($index) . ' contenus, ' . ($lite['of'] ?? '') . ' / ' . filesize(ECLAIR_DATA . '/index.php') . '.' . filemtime(ECLAIR_DATA . '/index.php'));
    check('index léger : au moins trois fois plus petit que l’index', filesize(ECLAIR_DATA . '/index-lite.php') * 3 < filesize(ECLAIR_DATA . '/index.php'),
        filesize(ECLAIR_DATA . '/index-lite.php') . ' / ' . filesize(ECLAIR_DATA . '/index.php'));

    // Réponses de référence : l'index complet, comme avant l'index léger.
    $published = array_filter($index, static fn (array $e): bool => $e['status'] === 'published' && (int) $e['date'] <= time());
    uasort($published, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
    $path = static fn (array $e): string => Content::path($e);
    $byPath = static function (string $p) use ($published, $path): ?array {
        foreach ($published as $e) {
            if ($path($e) === $p) {
                return $e;
            }
        }
        return null;
    };
    $refPosts = array_values(array_filter($published, static fn (array $e): bool => $e['type'] === 'post'));
    $inCat = array_values(array_filter($refPosts, static fn (array $e): bool => $e['category'] === $cat['id']));

    $fresh();
    $found = [];
    foreach (['/', '/services-lite', '/services-lite/detail', '/2019/ancienne-page/', '/blog/article-lite-3', '/blog/article-programme', '/blog/article-brouillon', '/page-brouillon', '/rien'] as $p) {
        $found[$p] = Content::findByPath($p) === $byPath($p);
    }
    check('adresse → contenu : identique à l’index (accueil, page, sous-page, adresse exacte, article, programmé, brouillon, inconnue)', !in_array(false, $found, true)
        && Content::findByPath('/blog/article-lite-3') !== null && Content::findByPath('/blog/article-programme') === null, json_encode($found));
    check('adresse exacte : trouvée telle quelle, jamais sans son « / » final', (Content::byRoute('/2019/ancienne-page/')['id'] ?? '') === $old['id'] && Content::byRoute('/2019/ancienne-page') === null);
    check('derniers articles : mêmes contenus, même ordre (dates égales comprises)', Content::latest(5) === array_slice($refPosts, 0, 5)
        && Content::latest(3, $cat['id'], $posts[1]['id']) === array_slice(array_values(array_filter($inCat, static fn (array $e): bool => $e['id'] !== $posts[1]['id'])), 0, 3)
        && Content::latest(0) === []);
    [$pageTwo, $total] = Content::page('post', [], 5, 5);
    [$catPage, $catTotal] = Content::page('post', ['category' => $cat['id']], 0, 4);
    [$indexable, $indexableTotal] = Content::page('post', ['indexable' => true], 0, 50);
    check('page de liste : contenus de la page et total identiques ; par catégorie ; sans les noindex', $pageTwo === array_slice($refPosts, 5, 5) && $total === count($refPosts)
        && $catPage === array_slice($inCat, 0, 4) && $catTotal === count($inCat) && $indexableTotal === count($refPosts) - 1
        && !in_array($posts[5]['id'], array_column($indexable, 'id'), true) && Content::page('post', ['author' => 'personne'])[1] === 0
        && Content::page('post', ['author' => $author['id']])[1] === count($refPosts), $total . ' ' . $catTotal . ' ' . $indexableTotal);
    check('articles par catégorie : même compte', Categories::counts() === [$cat['id'] => count($inCat)], json_encode(Categories::counts()));
    $translations = Content::translations($index[$fr['id']]);
    check('traductions : les deux langues, résumés identiques à l’index', array_keys($translations) === ['en', 'fr'] || array_keys($translations) === ['fr', 'en'], json_encode(array_keys($translations)));
    check('…et aucune pour un contenu sans groupe', Content::translations($index[$services['id']]) === [] && ($translations['fr'] ?? null) === $index[$fr['id']]);
    check('contenu par identifiant : résumé complet identique à l’index, ou l’essentiel sans lire son fichier', Content::find($services['id']) === $index[$services['id']]
        && Content::find('inconnu') === null && (Content::brief($old['id'])['route'] ?? '') === '/2019/ancienne-page/' && Content::brief($services['id'])['title'] === 'Services lite'
        && Content::path((array) Content::brief($home['id'])) === '/' && Content::visible((array) Content::brief($services['id'])) && Content::brief('inconnu') === null);
    check('prochain article programmé : même date', Content::nextScheduled() > time() && Content::nextScheduled() === (int) min(array_column(array_filter($index,
        static fn (array $e): bool => (int) $e['date'] > time()), 'date')));
    check('tout cela sans lire l’index complet', $loaded() === false);
    // Avec l'index complet en mémoire (administration) : mêmes réponses.
    $fresh();
    Content::index();
    check('index complet déjà en mémoire : mêmes réponses', Content::findByPath('/services-lite') === $index[$services['id']] && Content::latest(5) === array_slice($refPosts, 0, 5)
        && Content::page('post', [], 5, 5) === [array_slice($refPosts, 5, 5), count($refPosts)] && Categories::counts() === [$cat['id'] => count($inCat)]);

    section('Index léger : refait seul quand l’index a changé sans lui');
    @unlink(ECLAIR_DATA . '/index-lite.php');
    $fresh();
    check('fichier absent (site mis à jour depuis une version sans index léger) : refait à la première page', Content::findByPath('/services-lite') !== null
        && is_file(ECLAIR_DATA . '/index-lite.php') && count((array) ($read('index-lite')['rows'] ?? [])) === count($index));
    // Index modifié par un autre outil, sur place (même fichier) : un contenu dépublié.
    $changed = $index;
    $changed[$services['id']]['status'] = 'draft';
    $raw('content/' . $services['id'], ['status' => 'draft'] + (array) $read('content/' . $services['id']));
    $raw('index', $changed);
    $fresh();
    check('index modifié sans passer par Éclair : la page dépubliée n’est plus trouvée, l’index léger est refait', Content::findByPath('/services-lite') === null
        && Content::findByPath('/services-lite/detail') !== null && ((array) $read('index-lite'))['rows'][array_search($services['id'], array_map('strval', array_keys($changed)), true)][2] === 'draft');
    // Index remplacé dans la même seconde, à taille égale (adresse changée d'une lettre) : reconnu à son numéro de fichier.
    $swapped = $changed;
    $swapped[$old['id']]['route'] = '/2019/ancienne-pagf/';
    $fresh();
    Content::findByPath('/x');
    $stampBefore = ((array) $read('index-lite'))['of'] ?? '';
    Store::write('index', $swapped);
    touch(ECLAIR_DATA . '/index.php', (int) explode('.', (string) $stampBefore)[1]);
    $fresh();
    check('index remplacé dans la même seconde, à taille égale : pris en compte', filesize(ECLAIR_DATA . '/index.php') . '.' . filemtime(ECLAIR_DATA . '/index.php') === $stampBefore
        && Content::byRoute('/2019/ancienne-pagf/') !== null && Content::byRoute('/2019/ancienne-page/') === null);
    // Hébergement sans numéros de fichier stables : l'index léger n'est refait qu'une fois, pas à chaque page.
    $lite = (array) $read('index-lite');
    $raw('index-lite', ['ino' => (int) $lite['ino'] + 1] + $lite);
    $fresh();
    Content::findByPath('/x');
    $after = (array) $read('index-lite');
    $mtime = filemtime(ECLAIR_DATA . '/index-lite.php');
    $inode = fileinode(ECLAIR_DATA . '/index-lite.php');
    $fresh();
    Content::findByPath('/x');
    clearstatcache();
    check('numéro de fichier instable : index léger refait une fois, puis gardé (numéro non comparé)', ($after['ino'] ?? null) === 0 && $after['rows'] === $lite['rows']
        && fileinode(ECLAIR_DATA . '/index-lite.php') === $inode && filemtime(ECLAIR_DATA . '/index-lite.php') === $mtime && $loaded() === false);
    // L'index dit « en ligne », le fichier du contenu dit « brouillon » : jamais affiché.
    $raw('content/' . $posts[2]['id'], ['status' => 'draft'] + (array) $read('content/' . $posts[2]['id']));
    $fresh();
    check('contenu en ligne dans l’index mais brouillon dans son fichier : absent des listes et introuvable', Content::findByPath('/blog/article-lite-2') === null
        && !in_array($posts[2]['id'], array_column(Content::page('post', [], 0, 50)[0], 'id'), true) && !in_array($posts[2]['id'], array_column(Content::latest(50), 'id'), true));
    // Un enregistrement normal remet tout d'accord.
    $again = $save(['title' => 'Après les changements']);
    $fresh();
    $lite = (array) $read('index-lite');
    check('enregistrement suivant : index léger à jour, numéro de fichier de nouveau noté', (Content::findByPath('/blog/apres-les-changements')['id'] ?? '') === $again['id']
        && ($lite['ino'] ?? 0) === fileinode(ECLAIR_DATA . '/index.php') && array_column($lite['rows'], 0) === array_map('strval', array_keys((array) $read('index'))),
        json_encode([Content::findByPath('/blog/apres-les-changements')['id'] ?? null, $again['id'], $lite['ino'] ?? null, fileinode(ECLAIR_DATA . '/index.php')]));
    Content::delete($again['id']);
    $fresh();
    check('suppression : retirée de l’index léger', Content::findByPath('/blog/apres-les-changements') === null && !in_array((string) $again['id'], array_column(((array) $read('index-lite'))['rows'], 0), true));

    section('Gros sites : plafond de contenus et mémoire des parcours complets');
    check('plafond de contenus : 10 000', Content::MAX_ITEMS === 10000);
    $memo = new ReflectionProperty(Store::class, 'memo');
    $memo->setAccessible(true);
    $fresh();
    foreach (array_keys((array) $read('index')) as $id) {
        Content::peek((string) $id);
    }
    check('parcours de tous les contenus : aucun fichier gardé en mémoire', array_filter(array_keys($memo->getValue()), static fn (string $k): bool => str_starts_with($k, 'content/')) === []);

    Settings::save(['home' => $homeBefore]);
    Categories::delete($cat['id']);
    foreach (['content', 'revisions'] as $dir) {
        exec('rm -rf ' . escapeshellarg(ECLAIR_DATA . '/' . $dir));
    }
    Store::write('index', []);
    Store::write('search', []);
    Store::delete('index-lite');
    $fresh();
})();
