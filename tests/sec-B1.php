<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité du lot B1 (contenus, outils de l'IA, cache des pages, rendu public), inclus par tests/run.php :
 * index des contenus et de la recherche bornés, modification directe d'un brouillon décidée sur la version écrite,
 * cache des pages versionné par purge, index reconstruit sous verrou et rendu fidèle à la version enregistrée,
 * cache vidé quand un compte disparaît ou change d'adresse publique.
 */
declare(strict_types=1);

use Eclair\Ai;
use Eclair\AiTools;
use Eclair\Cache;
use Eclair\Content;
use Eclair\Seo;
use Eclair\Settings;
use Eclair\Shop;
use Eclair\Store;
use Eclair\Users;

if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 512) {
    ini_set('memory_limit', '512M'); // index de recherche rempli jusqu'à sa limite (16 Mo)
}
$b1Author = ['id' => 'b1auteur', 'role' => 'admin', 'name' => 'Camille B1', 'email' => 'b1@example.com'];
// Comme une autre requête : rien de ce que ce processus garde en mémoire (index, données lues).
$b1Forget = static function (): void {
    $p = new ReflectionProperty(Content::class, 'index');
    $p->setAccessible(true);
    $p->setValue(null, null);
    Store::forget();
};
$b1Read = static fn (string $name): mixed => json_decode(Store::strip((string) @file_get_contents(ECLAIR_DATA . '/' . $name . '.php')), true);
// Écriture faite par une autre requête : directement sur le disque, sans passer par la mémoire de ce processus.
$b1Write = static fn (string $name, mixed $data): int|false => file_put_contents(ECLAIR_DATA . '/' . $name . '.php',
    Store::GUARD . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$b1Save = static fn (array $data): array => Content::save($data + ['type' => 'post', 'status' => 'draft', 'title' => 'Article B1',
    'blocks' => [['type' => 'paragraph', 'text' => 'Texte de test B1.']]], $b1Author);
$b1Reset = static function () use ($b1Forget): void {
    foreach (['content', 'revisions', 'ai'] as $dir) {
        exec('rm -rf ' . escapeshellarg(ECLAIR_DATA . '/' . $dir));
    }
    Store::write('index', []);
    Store::write('search', []);
    $b1Forget();
};
$b1Ctx = static fn (): array => ['user' => $b1Author, 'editing' => null, 'turn' => ['drafts' => 0, 'images' => 0, 'updates' => 0, 'undo' => [], 'proposals' => []]];
// Publication (ou dépublication) faite par une autre requête : contenu et index changés sur le disque.
$b1Status = static function (array $item, string $status) use ($b1Read, $b1Write): void {
    $b1Write('content/' . $item['id'], ['status' => $status] + (array) $b1Read('content/' . $item['id']));
    $index = (array) $b1Read('index');
    $index[$item['id']]['status'] = $status;
    $b1Write('index', $index);
};
Settings::installed() || Settings::save(['lang' => 'fr']);

/* ─────────────────────── Index des contenus et de la recherche ─────────────────────── */
section('Sécurité B1 : index des contenus et de la recherche bornés');
$b1Reset();
$draft = $b1Save(['title' => 'Brouillon zèbre', 'blocks' => [['type' => 'paragraph', 'text' => 'Introduction avec le mot ornithorynque.'],
    ['type' => 'paragraph', 'text' => 'Suite du texte : motcorpsb1.']]]);
check('brouillon : aucun texte dans l’index de recherche', !array_key_exists($draft['id'], (array) $b1Read('search')));
$found = static fn (array $res, string $id): bool => in_array($id, array_column($res['result']['results'] ?? [], 'id'), true);
$ctx = $b1Ctx();
check('assistant : brouillon toujours retrouvé par son titre, son adresse et son extrait', $found(AiTools::run('search_content', ['query' => 'zèbre'], $ctx), $draft['id'])
    && $found(AiTools::run('search_content', ['query' => 'brouillon-zebre'], $ctx), $draft['id'])
    && $found(AiTools::run('search_content', ['query' => 'ornithorynque'], $ctx), $draft['id']));
$pub = $b1Save(['status' => 'published'] + $draft);
check('publié : texte cherchable, trouvé par la recherche des visiteurs', array_key_exists($pub['id'], (array) $b1Read('search'))
    && in_array($pub['id'], array_column(Content::search('motcorpsb1'), 'id'), true));
$unpub = $b1Save(['status' => 'draft'] + $pub);
check('dépublié : texte retiré de l’index de recherche', !array_key_exists($unpub['id'], (array) $b1Read('search')) && Content::search('motcorpsb1') === []);
$legacy = (array) $b1Read('search');
$legacy[$unpub['id']] = 'texte de brouillon gardé par une version précédente';
$b1Write('search', $legacy);
$b1Forget();
$other = $b1Save(['title' => 'Autre brouillon']);
check('mise à jour : textes de brouillons d’une version précédente retirés au premier enregistrement', !array_key_exists($unpub['id'], (array) $b1Read('search')));
if (method_exists(Content::class, 'terms')) {
    check('recherche : termes sans doublon, 8 au plus', Content::terms('Aa bb BB cc dd ee ff gg hh ii jj') === ['aa', 'bb', 'cc', 'dd', 'ee', 'ff', 'gg', 'hh']);
} else {
    check('recherche : termes sans doublon, 8 au plus', false, 'Content::terms absent');
}

// Nombre de contenus : au plafond, plus de création ; un contenu existant reste modifiable.
$maxItems = defined(Content::class . '::MAX_ITEMS') ? Content::MAX_ITEMS : 3000;
$entry = Content::index()[$other['id']];
$full = [$other['id'] => $entry];
for ($i = 0; count($full) < $maxItems; $i++) {
    $full['f' . str_pad((string) $i, 9, '0', STR_PAD_LEFT)] = ['id' => 'f' . str_pad((string) $i, 9, '0', STR_PAD_LEFT), 'slug' => 'plein-' . $i] + $entry;
}
Store::write('index', $full);
$b1Forget();
$refused = null;
try {
    $b1Save(['title' => 'Un contenu de trop']);
} catch (OverflowException $e) {
    $refused = $e->getMessage();
}
check('contenus plafonnés : création refusée avec un message clair, rien d’écrit', $refused !== null && str_contains($refused, (string) $maxItems)
    && count((array) $b1Read('index')) === $maxItems, (string) $refused);
$kept = null;
try {
    $kept = $b1Save(['title' => 'Modifié au plafond'] + $other);
} catch (OverflowException) {
}
check('contenus plafonnés : un contenu existant reste modifiable', ($kept['title'] ?? '') === 'Modifié au plafond');

// Brouillons de l'IA : plus de création quand le site en compte déjà le maximum (assistant, connecteur MCP).
$b1Reset();
$maxDrafts = defined(AiTools::class . '::MAX_SITE_DRAFTS') ? AiTools::MAX_SITE_DRAFTS : 500;
$seed = $b1Save(['title' => 'Graine']);
$entry = Content::index()[$seed['id']];
$drafts = [];
for ($i = 0; $i < $maxDrafts; $i++) {
    $drafts['d' . str_pad((string) $i, 9, '0', STR_PAD_LEFT)] = ['id' => 'd' . str_pad((string) $i, 9, '0', STR_PAD_LEFT), 'slug' => 'brouillon-' . $i] + $entry;
}
Store::write('index', $drafts);
$b1Forget();
$ctx = $b1Ctx();
$res = AiTools::run('create_draft', ['type' => 'post', 'title' => 'Encore un brouillon', 'body' => 'Un texte.'], $ctx);
check('IA : aucun brouillon de plus quand le site en compte ' . $maxDrafts . ' (erreur claire pour le modèle)', $res['error'] === true
    && str_contains((string) ($res['result']['error'] ?? ''), (string) $maxDrafts) && count((array) $b1Read('index')) === $maxDrafts, json_encode($res['result']));
unset($drafts[array_key_first($drafts)]);
Store::write('index', $drafts);
$b1Forget();
$ctx = $b1Ctx();
$res = AiTools::run('create_draft', ['type' => 'post', 'title' => 'Encore un brouillon', 'body' => 'Un texte.'], $ctx);
check('IA : brouillon créé sous la limite', $res['error'] === false && ($res['card']['kind'] ?? '') === 'draft', json_encode($res['result']));

// Index de recherche : rempli presque jusqu'à sa limite, un texte qui le ferait déborder est refusé avant toute écriture.
$b1Reset();
$maxSearch = defined(Content::class . '::MAX_SEARCH_BYTES') ? Content::MAX_SEARCH_BYTES : 16_000_000;
$big = $b1Save(['title' => 'Article à rallonger', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Court.']]]);
$entry = Content::index()[$big['id']];
$index = [$big['id'] => $entry];
$texts = [$big['id'] => 'Court.'];
$chunk = str_repeat('ceramique ', 2900);
for ($i = 0, $size = 100; $size < $maxSearch - 20000; $i++) {
    $id = 's' . str_pad((string) $i, 9, '0', STR_PAD_LEFT);
    $index[$id] = ['id' => $id, 'slug' => 'texte-' . $i] + $entry;
    $texts[$id] = substr($chunk, 0, min(strlen($chunk), $maxSearch - 20000 - $size));
    $size += strlen($texts[$id]) + 20;
}
Store::write('index', $index);
Store::write('search', $texts);
unset($texts, $index);
$b1Forget();
$before = filesize(ECLAIR_DATA . '/search.php');
$refused = null;
try {
    $b1Save(['blocks' => [['type' => 'paragraph', 'text' => str_repeat('nouveau ', 3750)]]] + $big);
} catch (OverflowException $e) {
    $refused = $e->getMessage();
}
$stored = $b1Read('content/' . $big['id']);
check('index de recherche plafonné : texte refusé avec un message clair', $before < $maxSearch && $refused !== null && str_contains($refused, 'recherche'), (string) $refused);
check('…sans rien enregistrer à moitié (contenu, révision, index de recherche)', ($stored['blocks'][0]['text'] ?? '') === 'Court.' && Content::revisions($big['id']) === []
    && filesize(ECLAIR_DATA . '/search.php') === $before);
$hidden = null;
try {
    $hidden = $b1Save(['status' => 'draft'] + $big);
} catch (OverflowException) {
}
check('index de recherche plein : dépublier ou raccourcir reste possible', ($hidden['status'] ?? '') === 'draft');

/* ───────────────────── Modifications de l'IA : sur la version écrite ───────────────────── */
section('Sécurité B1 : l’IA ne modifie directement qu’un brouillon resté brouillon');
$b1Reset();
// SCN1 : la personne publie le brouillon de l'IA pendant le tour ; l'index chargé au début du tour le dit encore brouillon.
$d = $b1Save(['title' => 'Atelier d’automne']);
Content::index();
$b1Status($d, 'published');
Store::forget(); // comme Ai::charge entre deux étapes : seul l'index du tour reste périmé
$ctx = $b1Ctx();
$res = AiTools::run('update_content', ['id' => $d['id'], 'note' => 'Titre', 'title' => 'Titre changé par l’IA après publication'], $ctx);
$stored = $b1Read('content/' . $d['id']);
check('brouillon publié pendant le tour : proposition, contenu en ligne inchangé', ($res['card']['kind'] ?? '') === 'proposal'
    && $stored['title'] === 'Atelier d’automne' && $stored['status'] === 'published', json_encode([$res['card']['kind'] ?? null, $stored['title'], $stored['status']]));
// SCN3 : page publiée, proposition dans le tour, puis la personne la dépublie ; seconde modification du même tour.
$p = $b1Save(['type' => 'page', 'title' => 'Horaires de l’atelier', 'status' => 'published']);
$ctx = $b1Ctx();
$r1 = AiTools::run('update_content', ['id' => $p['id'], 'note' => 'Titre', 'title' => 'Titre proposé jamais appliqué'], $ctx);
$b1Save(['status' => 'draft'] + $p);
$r2 = AiTools::run('update_content', ['id' => $p['id'], 'note' => 'Résumé', 'summary' => 'Résumé ajouté par la seconde modification du tour.'], $ctx);
$stored = $b1Read('content/' . $p['id']);
$pending = Store::read('ai/proposals/' . (string) ($r1['card']['pid'] ?? 'x'));
check('page dépubliée pendant le tour : reste hors ligne, la proposition en attente est complétée', ($r1['card']['kind'] ?? '') === 'proposal'
    && ($r2['card']['kind'] ?? '') === 'proposal' && $stored['status'] === 'draft' && $stored['title'] === 'Horaires de l’atelier'
    && str_starts_with((string) ($pending['data']['summary'] ?? ''), 'Résumé ajouté'), json_encode([$r2['card']['kind'] ?? null, $stored['status'], $stored['title']]));
// Publication arrivée entre la lecture du brouillon et son écriture : l'écriture conditionnelle est refusée sous verrou.
$d2 = $b1Save(['title' => 'Brouillon lu avant publication']);
$read = (array) $b1Read('content/' . $d2['id']);
$b1Status($d2, 'published');
$b1Forget();
$thrown = false;
try {
    Content::save(['title' => 'Écrit sur une version périmée'] + $read, $b1Author, static fn (?array $s): bool => $s !== null && $s['status'] === 'draft');
} catch (UnexpectedValueException) {
    $thrown = true;
}
$stored = $b1Read('content/' . $d2['id']);
check('écriture conditionnelle : refusée si le contenu a changé depuis sa lecture (publication gardée)', $thrown && $stored['status'] === 'published'
    && $stored['title'] === 'Brouillon lu avant publication');
// Lot MCP : un message précédent a chargé l'index ; une publication faite entre-temps est vue par les outils suivants.
$d3 = $b1Save(['title' => 'Statut relu à chaque outil']);
Content::index();
$b1Status($d3, 'published');
$ctx = $b1Ctx();
$res = AiTools::run('search_content', ['query' => 'statut relu'], $ctx);
$row = array_values(array_filter($res['result']['results'] ?? [], fn (array $r): bool => $r['id'] === $d3['id']))[0] ?? [];
check('outils de l’IA : état du contenu relu à chaque appel (lot MCP)', ($row['status'] ?? '') === 'published', json_encode($row));
// Témoin : un brouillon que personne ne touche reste modifié directement, avec annulation.
$d4 = $b1Save(['title' => 'Brouillon tranquille']);
$ctx = $b1Ctx();
$res = AiTools::run('update_content', ['id' => $d4['id'], 'note' => 'Titre', 'title' => 'Brouillon amélioré'], $ctx);
check('brouillon inchangé entre-temps : modifié directement, annulable', ($res['card']['kind'] ?? '') === 'updated'
    && ($b1Read('content/' . $d4['id'])['title'] ?? '') === 'Brouillon amélioré' && ($res['card']['undo'] ?? '') !== '');
// Proposition : elle garde l'empreinte de sa version de base (une modification de la même seconde la rend périmée).
$fpOf = static function (array $item): string {
    $m = new ReflectionMethod(AiTools::class, 'fingerprint');
    $m->setAccessible(true);
    return (string) $m->invoke(null, $item);
};
$basePage = $b1Save(['type' => 'page', 'title' => 'Page de base', 'status' => 'published']);
$ctx = $b1Ctx();
$res = AiTools::run('update_content', ['id' => $basePage['id'], 'note' => 'Titre', 'title' => 'Titre proposé'], $ctx);
$prop = Store::read('ai/proposals/' . (string) ($res['card']['pid'] ?? 'x'));
check('proposition : empreinte de sa version de base gardée (modification de la même seconde repérée)', is_array($prop)
    && ($prop['fp'] ?? '') === $fpOf((array) $b1Read('content/' . $basePage['id'])));
// Médiathèque : termes sans doublon, 8 au plus (chacun parcourt toute la médiathèque).
Store::write('media', ['m1b1' => ['id' => 'm1b1', 'file' => '2026/11/x', 'ext' => 'jpg', 'w' => 10, 'h' => 10, 'webp' => [], 'alt' => '', 'name' => 'neuviemeb1.jpg', 'created' => time()]]);
$mediaIds = static fn (array $res): array => array_column($res['result']['media'] ?? [], 'id');
$ctx = $b1Ctx();
check('médiathèque (find_media) : 8 termes au plus, le neuvième est ignoré', $mediaIds(AiTools::run('find_media', ['query' => 'neuviemeb1'], $ctx)) === ['m1b1']
    && $mediaIds(AiTools::run('find_media', ['query' => 'aa bb cc dd ee ff gg hh neuviemeb1'], $ctx)) === []);
Store::delete('media');
// Propositions en attente bornées (les plus récentes gardées) : le dossier reste petit et vite parcouru.
$pdir = ECLAIR_DATA . '/ai/proposals';
@mkdir($pdir, 0700, true);
for ($i = 0; $i < 1005; $i++) {
    file_put_contents($pdir . '/old' . $i . '.php', Store::GUARD . '{}');
    touch($pdir . '/old' . $i . '.php', time() - 3600 - $i);
}
if (property_exists(AiTools::class, 'pruned')) {
    $pruned = new ReflectionProperty(AiTools::class, 'pruned');
    $pruned->setAccessible(true);
    $pruned->setValue(null, []);
}
$page = $b1Save(['type' => 'page', 'title' => 'Page à proposer', 'status' => 'published']);
$ctx = $b1Ctx();
$res = AiTools::run('update_content', ['id' => $page['id'], 'note' => 'Titre', 'title' => 'Titre proposé'], $ctx);
$left = count(glob($pdir . '/*.php') ?: []);
check('propositions en attente : 1 000 au plus, les plus récentes gardées', ($res['card']['kind'] ?? '') === 'proposal' && $left <= 1001
    && is_file($pdir . '/old0.php') && !is_file($pdir . '/old1004.php'), (string) $left);

/* ───────────────────── Index reconstruit sous verrou, écriture sûre, rendu fidèle ───────────────────── */
section('Sécurité B1 : index fidèle aux contenus enregistrés');
$b1Reset();
// Reconstruction : un contenu dépublié par une autre requête n'est jamais remis en ligne par une copie d'avant.
$x = $b1Save(['title' => 'Retiré pendant la reconstruction', 'status' => 'published']);
$b1Status($x, 'draft'); // la copie publiée reste dans la mémoire de ce processus
$rebuilt = Content::reindex();
check('reconstruction de l’index : contenus relus sous verrou (jamais une copie d’avant)', ($rebuilt[$x['id']]['status'] ?? '') === 'draft'
    && (($b1Read('index')[$x['id']]['status'] ?? '') === 'draft'));
$a = $b1Save(['type' => 'page', 'title' => 'About us', 'lang' => 'en', 'status' => 'published']);
$b = $b1Save(['type' => 'page', 'title' => 'À propos', 'status' => 'published']);
$b1Status($b, 'draft');
Content::linkTranslation($a['id'], $b['id']);
$sb = (array) $b1Read('content/' . $b['id']);
check('lien de traduction : la dépublication faite entre-temps n’est jamais écrasée', ($sb['status'] ?? '') === 'draft' && ($sb['tgroup'] ?? '') !== ''
    && (($b1Read('index')[$b['id']]['status'] ?? '') === 'draft'));
// Dépublication interrompue (le contenu ne peut pas être écrit) : l'index le dit déjà hors ligne.
$h = $b1Save(['title' => 'Masqué malgré une panne', 'status' => 'published']);
$file = ECLAIR_DATA . '/content/' . $h['id'] . '.php';
$interrupted = false;
Store::locked(function () use ($h, $file, $b1Author, &$interrupted): void {
    Store::read('content/' . $h['id']); // version déjà lue par l'enregistrement
    rename($file, $file . '.bak');
    mkdir($file); // un dossier à sa place : l'écriture du contenu échoue
    try {
        Content::save(['status' => 'draft'] + $h, $b1Author);
    } catch (RuntimeException) {
        $interrupted = true;
    }
    rmdir($file);
    rename($file . '.bak', $file);
});
check('dépublication interrompue : l’index dit le contenu hors ligne avant l’écriture du contenu', $interrupted && (($b1Read('index')[$h['id']]['status'] ?? '') === 'draft'));
// Index périmé (il dit publié) devant une version enregistrée brouillon : flux, llms-full, plan du site et flux produits l'ignorent.
$b1Reset();
Settings::save(['shop' => ['enabled' => true], 'url' => 'https://atelier.example']);
$v = $b1Save(['title' => 'Version publiée', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Texte public.']]]);
$prod = $b1Save(['type' => 'product', 'title' => 'Bol publié', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Un bol.']],
    'product' => ['price' => '20', 'link' => 'https://buy.stripe.com/test_b1']]);
foreach ([$v, $prod] as $it) {
    $b1Write('content/' . $it['id'], ['status' => 'draft', 'summary' => 'DIVERGENCEB1', 'blocks' => [['type' => 'paragraph', 'text' => 'DIVERGENCEB1 jamais publié.']]]
        + (array) $b1Read('content/' . $it['id']));
}
$b1Forget();
$generated = Seo::feed() . Seo::llms(true) . Seo::sitemap() . Shop::feed();
check('index périmé : flux RSS, llms-full, plan du site et flux produits ignorent la version brouillon', !str_contains($generated, 'DIVERGENCEB1')
    && !str_contains($generated, Content::path(Content::entry($v))) && !str_contains($generated, Content::path(Content::entry($prod))));
Settings::save(['shop' => ['enabled' => false], 'url' => '']);

/* ─────────────────────────── Cache, comptes, IA (unitaire) ─────────────────────────── */
section('Sécurité B1 : génération du cache, comptes, dépenses de l’IA');
$runtime = static fn (): array => (array) json_decode(Store::strip((string) @file_get_contents(ECLAIR_ROOT . '/cache/runtime.php')), true);
Cache::purge();
$g1 = (string) ($runtime()['g'] ?? '');
Cache::purge();
$g2 = (string) ($runtime()['g'] ?? '');
check('cache : chaque purge publie une nouvelle génération', $g1 !== '' && $g2 !== '' && $g1 !== $g2);
// Adresse publique d'un compte changée par une écriture sans rapport (connexion) : le cache est vidé.
Store::ensureDir(Cache::dir());
$probe = Cache::dir() . '/' . md5('/sonde-b1');
$u1 = Users::create(['name' => 'Alex Témoin', 'email' => 'alex.b1@example.com', 'pass' => 'x', 'role' => 'editor']);
$u2 = Users::create(['name' => 'Alex Témoin', 'email' => 'alex2.b1@example.com', 'pass' => 'x', 'role' => 'editor']);
Users::delete($u1['id']);
file_put_contents($probe, "{}\nsonde");
$moved = Users::update($u2['id'], fn (array $u): array => ['last_login' => time()] + $u);
check('compte : adresse publique changée à la connexion, cache vidé', ($moved['slug'] ?? '') === 'alex-temoin' && !is_file($probe));
file_put_contents($probe, "{}\nsonde");
Users::update($u2['id'], fn (array $u): array => ['last_login' => time()] + $u);
check('compte : écriture sans effet public, cache gardé', is_file($probe));
@unlink($probe);
Users::delete($u2['id']);
// Coûts négatifs annoncés par un fournisseur défaillant : jamais une dépense en moins.
$month = static fn (): float => (float) (((array) Store::read('ai/usage', []))['months'][date('Y-m')]['cost'] ?? 0.0);
Ai::charge('b1', 0.5);
$spent = $month();
Ai::charge('b1', -0.4, 1);
check('IA : un coût négatif ne réduit jamais la dépense du mois', $month() >= $spent && $spent > 0
    && Ai::cost('anthropic', 'claude-opus-5-5', ['in' => -1_000_000, 'out' => -1_000_000]) == 0.0, $spent . ' → ' . $month());
// Flux RSS sans adresse enregistrée : liens relatifs, jamais l'hôte annoncé par le premier visiteur.
Settings::save(['url' => '']);
$_SERVER['HTTP_HOST'] = 'evil.example';
check('flux sans adresse enregistrée : aucun hôte venu de la requête', Seo::origin() === '');
unset($_SERVER['HTTP_HOST']);
$b1Reset();

/* ─────────────────────────── Intégration HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Sécurité B1 : verrou des données, cache des pages, rendu public, comptes (serveur)');
// Reconstruction de l'index lancée par une autre requête pendant qu'un enregistrement tient le verrou : elle l'attend.
$child = <<<'PHP'
define('ECLAIR', true);
define('ECLAIR_VERSION', 'test');
define('ECLAIR_ROOT', (string) getenv('B1_ROOT'));
define('ECLAIR_DATA', (string) getenv('B1_DATA'));
define('ECLAIR_DEBUG', true);
spl_autoload_register(static function (string $class): void {
    $file = ECLAIR_ROOT . '/app/lib/' . substr($class, 7) . '.php';
    if (str_starts_with($class, 'Eclair\\') && is_file($file)) {
        require $file;
    }
});
require ECLAIR_ROOT . '/app/lib/helpers.php';
Eclair\Content::reindex();
PHP;
$b1Save(['title' => 'Contenu à réindexer', 'status' => 'published']);
$proc = proc_open([PHP_BINARY, '-r', $child], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, null,
    ['B1_ROOT' => ECLAIR_ROOT, 'B1_DATA' => ECLAIR_DATA, 'PATH' => (string) getenv('PATH')]);
$waiting = Store::locked(static function () use ($proc): bool {
    usleep(700000);
    return proc_get_status($proc)['running'];
});
proc_close($proc);
check('reconstruction de l’index : attend le verrou des données (jamais au milieu d’un enregistrement)', $waiting && count((array) $b1Read('index')) === 1);
$b1Reset();

// Assistant de l'administration, tour complet (faux fournisseur) : la personne agit pendant le tour, au moment où un outil part.
$fakeDir = sys_get_temp_dir() . '/eclair-fake-ai-b1-' . getmypid();
@mkdir($fakeDir);
[$fakePort, $fake] = start_server([__DIR__ . '/fake-ai.php'], ['FAKE_AI_DIR' => $fakeDir, 'PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
try {
    defined('ECLAIR_AI_ENDPOINTS') || define('ECLAIR_AI_ENDPOINTS', ['anthropic' => 'http://127.0.0.1:' . $fakePort]);
    Eclair\Vault::set('ai.anthropic', 'sk-ant-test-0123456789');
    Settings::save(['ai' => ['provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'budget' => 10, 'daily' => 60, 'editors' => true]]);
    $chatUser = Users::create(['name' => 'Camille Chat', 'email' => 'chat.b1@example.com', 'pass' => 'x', 'role' => 'admin']);
    $turn = static function (string $message, callable $atFirstTool) use ($chatUser): array {
        $events = [];
        Eclair\AiChat::run($chatUser, '', $message, [], null, static function (array $e) use (&$events, $atFirstTool): void {
            if ($e['t'] === 'tool' && !array_filter($events, fn (array $x): bool => $x['t'] === 'tool')) {
                $atFirstTool();
            }
            $events[] = $e;
        });
        return $events;
    };
    $kinds = static fn (array $events): array => array_column(array_column(array_values(array_filter($events, fn (array $e): bool => $e['t'] === 'card')), 'card'), 'kind');
    // Brouillon de l'IA publié par la personne pendant le tour : l'index chargé au début du tour (contexte) le dit encore brouillon.
    $d = $b1Save(['title' => 'Brouillon du tour']);
    $events = $turn('MODIFIE ' . $d['id'], static fn () => $b1Status($d, 'published'));
    $stored = (array) $b1Read('content/' . $d['id']);
    check('assistant (tour complet) : brouillon publié pendant le tour, proposition et rien de changé en ligne', $kinds($events) === ['proposal']
        && $stored['status'] === 'published' && $stored['title'] === 'Brouillon du tour', json_encode([$kinds($events), $stored['title'], $stored['status']]));
    // Compte supprimé pendant le tour : plus aucun outil ne part en son nom.
    $d2 = $b1Save(['title' => 'Brouillon d’un compte supprimé']);
    $events = $turn('MODIFIE ' . $d2['id'], static fn () => Users::delete($chatUser['id']));
    $errors = array_column(array_values(array_filter($events, fn (array $e): bool => $e['t'] === 'error')), 'error');
    check('assistant : compte supprimé pendant le tour, aucune modification ensuite', ($b1Read('content/' . $d2['id'])['title'] ?? '') === 'Brouillon d’un compte supprimé'
        && $kinds($events) === [] && $errors !== [], json_encode([$kinds($events), $errors]));
} finally {
    proc_terminate($fake);
    exec('rm -rf ' . escapeshellarg($fakeDir));
    Eclair\Vault::set('ai.anthropic', '');
    Settings::save(['ai' => ['provider' => '', 'model' => '']]);
    $b1Reset();
}

[$copy, $port, $server] = serve('secb1');
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Atelier B1', 'site_tagline' => 'Céramique', 'site_lang' => 'fr', 'site_type' => 'blog',
        'user_name' => 'Camille B1', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    preg_match('/"csrf":"([a-f0-9]+)"/', $c->req('GET', '/gestion')['body'], $m);
    $csrf = $m[1] ?? '';
    $api = static function (string $path, array $data) use ($c, $csrf): array {
        $res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data), ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
        return $res + ['json' => (array) json_decode($res['body'], true)];
    };
    $read = static fn (string $name): mixed => json_decode(Store::strip((string) @file_get_contents($copy . '/data/' . $name . '.php')), true);
    $write = static fn (string $name, mixed $data): int|false => file_put_contents($copy . '/data/' . $name . '.php',
        Store::GUARD . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $hit = static fn (array $res): bool => ($res['headers']['x-cache'] ?? '') === 'HIT';
    $visitor = client($port);

    // Cache : une page rendue à partir de données lues avant une dépublication, et écrite après sa purge, n'est jamais servie.
    $post = (array) ($api('api/save', ['type' => 'post', 'title' => 'Billet RETRAITB1', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Texte RETRAITB1.']]])['json']['item'] ?? []);
    $url = '/blog/' . ($post['slug'] ?? 'x');
    $visitor->req('GET', '/');
    $visitor->req('GET', $url);
    $warm = $visitor->req('GET', $url);
    $late = [];
    foreach (['/', $url] as $p) {
        $late[md5($p)] = (string) @file_get_contents($copy . '/cache/pages/' . md5($p));
    }
    $off = $api('api/save', ['status' => 'draft'] + $post);
    foreach ($late as $name => $body) {
        file_put_contents($copy . '/cache/pages/' . $name, $body); // rendus commencés avant la dépublication, écrits après sa purge
    }
    $home = $visitor->req('GET', '/');
    $single = $visitor->req('GET', $url);
    check('cache : page rendue avant une dépublication jamais servie après elle (accueil, article)', $hit($warm) && ($off['json']['visible'] ?? true) === false
        && !$hit($home) && !str_contains($home['body'], 'RETRAITB1') && $single['status'] === 404 && !str_contains($single['body'], 'RETRAITB1'),
        json_encode([$home['headers']['x-cache'] ?? '-', $single['status'], $single['headers']['x-cache'] ?? '-']));
    $again = $visitor->req('GET', '/');
    check('cache : la page suivante est de nouveau servie depuis le cache', $hit($again) && !str_contains($again['body'], 'RETRAITB1'));

    // Rendu : l'index dit publié, la version enregistrée est un brouillon (reconstruction concurrente, écriture interrompue).
    $v = (array) ($api('api/save', ['type' => 'post', 'title' => 'Version publiée B1', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Texte public.']]])['json']['item'] ?? []);
    $write('content/' . $v['id'], ['status' => 'draft', 'summary' => 'DIVERGENCEB1', 'blocks' => [['type' => 'paragraph', 'text' => 'DIVERGENCEB1 jamais publié.']]]
        + (array) $read('content/' . $v['id']));
    exec('rm -f ' . escapeshellarg($copy) . '/cache/pages/*');
    $leaks = [];
    foreach (['/blog/' . $v['slug'], '/blog/' . $v['slug'] . '.md', '/feed.xml', '/llms-full.txt', '/sitemap.xml'] as $p) {
        $r = $visitor->req('GET', $p);
        if (str_contains($r['body'], 'DIVERGENCEB1') || ($p === '/sitemap.xml' && str_contains($r['body'], '/blog/' . $v['slug']))) {
            $leaks[] = $p . ' ' . $r['status'];
        }
    }
    check('index périmé : version brouillon jamais servie (article, .md, flux, llms-full, plan du site)', $leaks === [], implode(' | ', $leaks));
    settings($copy, ['shop' => ['enabled' => true]]);
    $prod = (array) ($api('api/save', ['type' => 'product', 'title' => 'Bol B1', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Un bol.']],
        'product' => ['price' => '20', 'link' => 'https://buy.stripe.com/test_b1']])['json']['item'] ?? []);
    $write('content/' . $prod['id'], ['status' => 'draft', 'summary' => 'DIVERGENCEB1', 'blocks' => [['type' => 'paragraph', 'text' => 'DIVERGENCEB1 jamais publié.']]]
        + (array) $read('content/' . $prod['id']));
    exec('rm -f ' . escapeshellarg($copy) . '/cache/pages/*');
    $sheet = $visitor->req('GET', '/boutique/' . $prod['slug']);
    $feed = $visitor->req('GET', '/products.xml');
    check('index périmé : fiche produit et flux produits sans la version brouillon', $sheet['status'] === 404 && !str_contains($sheet['body'] . $feed['body'], 'DIVERGENCEB1'),
        $sheet['status'] . ' ' . substr($feed['body'], 0, 200));
    settings($copy, ['shop' => ['enabled' => false]]);

    // Proposition modifiée dans la même seconde que sa version de base : l'éditeur la dit périmée, l'application la refuse.
    $admin = array_column((array) $read('users'), null, 'email')['camille@example.com'] ?? ['id' => 'x'];
    $page = (array) ($api('api/save', ['type' => 'page', 'title' => 'Horaires B1', 'status' => 'published', 'blocks' => [['type' => 'paragraph', 'text' => 'Ouvert le samedi.']]])['json']['item'] ?? []);
    $base = (array) $read('content/' . $page['id']);
    @mkdir($copy . '/data/ai/proposals', 0700, true);
    $proposal = ['user' => $admin['id'], 'content' => $page['id'], 'base' => (int) $base['updated'], 'data' => ['title' => 'Titre proposé B1', 'date' => ''] + $base,
        'note' => 'Titre', 'changes' => ['Titre'], 'created' => time()];
    $write('ai/proposals/b1fp000001', ['id' => 'b1fp000001', 'fp' => $fpOf($base)] + $proposal);
    $write('content/' . $page['id'], ['summary' => 'Résumé ajouté dans la même seconde.'] + $base);
    $view = $c->req('GET', '/gestion/edit/' . $page['id'] . '?proposal=b1fp000001');
    $applied = $api('api/ai/apply', ['pid' => 'b1fp000001']);
    check('proposition : modification de la même seconde repérée (l’éditeur la dit périmée, l’application la refuse)', str_contains($view['body'], '"proposal":{"stale":true}')
        && $applied['status'] === 409 && ($read('content/' . $page['id'])['title'] ?? '') === 'Horaires B1', $applied['status'] . ' ' . substr($applied['body'], 0, 200));
    // Proposition d'avant la mise à jour (sans empreinte) et page dépubliée dans la même seconde : appliquée sans jamais la remettre en ligne.
    $write('ai/proposals/b1old00001', ['id' => 'b1old00001'] + $proposal);
    $write('content/' . $page['id'], ['status' => 'draft'] + $base);
    $index = (array) $read('index');
    $index[$page['id']]['status'] = 'draft';
    $write('index', $index);
    $applied = $api('api/ai/apply', ['pid' => 'b1old00001']);
    $after = (array) $read('content/' . $page['id']);
    check('proposition appliquée : jamais de changement de publication (page dépubliée entre-temps)', ($applied['json']['ok'] ?? false) === true
        && ($after['title'] ?? '') === 'Titre proposé B1' && ($after['status'] ?? '') === 'draft' && $visitor->req('GET', '/' . $page['slug'])['status'] === 404,
        json_encode([$applied['status'], $after['status'] ?? null, $after['title'] ?? null]));

    // Plafond du nombre de contenus : message clair dans l'éditeur.
    $index = (array) $read('index');
    $saved = $index;
    $first = reset($index);
    for ($i = 0; count($index) < (defined(Content::class . '::MAX_ITEMS') ? Content::MAX_ITEMS : 3000); $i++) {
        $index['h' . str_pad((string) $i, 9, '0', STR_PAD_LEFT)] = ['id' => 'h' . str_pad((string) $i, 9, '0', STR_PAD_LEFT), 'slug' => 'plein-' . $i, 'status' => 'draft'] + $first;
    }
    $write('index', $index);
    $over = $api('api/save', ['type' => 'post', 'title' => 'Un de trop', 'status' => 'draft', 'blocks' => [['type' => 'paragraph', 'text' => 'x']]]);
    check('éditeur : création au-delà du nombre maximal de contenus refusée, message clair', $over['status'] === 422 && ($over['json']['ok'] ?? true) === false
        && str_contains((string) ($over['json']['error'] ?? ''), 'contenus'), $over['status'] . ' ' . $over['body']);
    $write('index', $saved);

    // Numéros de page non canoniques : jamais rendus (ils réécrivaient la page en cache de l'accueil à chaque visite).
    check('numéro de page non canonique (« /blog/page/01 ») : introuvable', $visitor->req('GET', '/blog/page/01')['status'] === 404
        && $visitor->req('GET', '/blog/page/000001')['status'] === 404);

    // Comptes : suppression d'un auteur, et page d'auteur d'un compte qui n'a rien publié.
    $users = $c->req('GET', '/gestion/users');
    $c->req('POST', '/gestion/users', $c->token($users['body']) + ['name' => 'Zoé Signatureb1', 'email' => 'zoe.b1@example.com', 'role' => 'editor']);
    $users = $c->req('GET', '/gestion/users');
    $c->req('POST', '/gestion/users', $c->token($users['body']) + ['name' => 'Sans Billetb1', 'email' => 'sans.b1@example.com', 'role' => 'editor']);
    $accounts = array_column((array) $read('users'), null, 'email');
    $zoe = $accounts['zoe.b1@example.com'] ?? ['id' => 'x', 'slug' => 'x'];
    $zp = (array) ($api('api/save', ['type' => 'post', 'title' => 'Billet de Zoé', 'status' => 'published', 'author' => $zoe['id'],
        'blocks' => [['type' => 'paragraph', 'text' => 'Bonjour.']]])['json']['item'] ?? []);
    $authorUrl = '/blog/auteur/' . $zoe['slug'];
    $visitor->req('GET', $authorUrl);
    $visitor->req('GET', '/blog/' . $zp['slug']);
    $cachedAuthor = $visitor->req('GET', $authorUrl);
    $form = $c->req('GET', '/gestion/users/' . $zoe['id']);
    $c->req('POST', '/gestion/users/' . $zoe['id'], $c->token($form['body']) + ['action' => 'delete']);
    $ap = $visitor->req('GET', $authorUrl);
    $pp = $visitor->req('GET', '/blog/' . $zp['slug']);
    check('compte supprimé : sa page d’auteur et sa signature quittent aussitôt le cache', $hit($cachedAuthor) && str_contains($cachedAuthor['body'], 'Signatureb1')
        && !isset(((array) $read('users'))[$zoe['id']]) && $ap['status'] === 404 && $pp['status'] === 200 && !str_contains($pp['body'], 'Signatureb1'),
        json_encode([$ap['status'], $ap['headers']['x-cache'] ?? '-', $pp['status']]));
    $nobody = $accounts['sans.b1@example.com'] ?? ['slug' => 'x'];
    check('page d’auteur d’un compte qui n’a rien publié : introuvable', $visitor->req('GET', '/blog/auteur/' . $nobody['slug'])['status'] === 404);
} finally {
    proc_terminate($server);
    exec('rm -rf ' . escapeshellarg($copy));
}
