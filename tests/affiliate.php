<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Affiliation : liens enregistrés ; mention publicitaire (phrase Amazon de la boutique) et rel="sponsored nofollow" sur
 * les seules pages qui contiennent un lien affilié, et dans le flux ; article rédigé par l'IA (faux fournisseur) à partir
 * des noms et notes, avec les vraies adresses posées à l'identique. Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Affiliate;
use Eclair\Store;

section('Affiliation : liens, mention publicitaire, liens marqués');
check('programme : boutiques Amazon et liens courts reconnus', Affiliate::program('https://www.amazon.fr/dp/B0X?tag=x-21') === 'amazon'
    && Affiliate::program('https://amzn.to/3abc') === 'amazon' && Affiliate::program('https://www.amazon.co.uk/x') === 'amazon'
    && Affiliate::program('https://amazon-fake.example/x') === 'other' && Affiliate::program('https://www.fnac.com/x') === 'other');
check('phrase du Partenariat Amazon : celle de la boutique, sinon celle de la langue de la page',
    str_starts_with(Affiliate::amazonStatement('https://www.amazon.es/x', 'fr'), 'En calidad de Afiliado de Amazon')
    && Affiliate::amazonStatement('https://www.amazon.co.uk/x', 'fr') === 'As an Amazon Associate I earn from qualifying purchases.'
    && str_starts_with(Affiliate::amazonStatement('https://amzn.to/x', 'it'), 'In qualità di Affiliato Amazon')
    && Affiliate::amazonStatement('https://amzn.to/x', 'pt') === 'As an Amazon Associate I earn from qualifying purchases.');
$affFile = ECLAIR_DATA . '/affiliate.php';
$affBackup = is_file($affFile) ? (string) file_get_contents($affFile) : null;
try {
    @unlink($affFile);
    $affBlocks = [['type' => 'paragraph', 'text' => 'Voir <a href="https://www.amazon.fr/dp/B01?tag=moi-21&amp;th=1" target="_blank" rel="noopener">ici</a>.'],
        ['type' => 'button', 'text' => 'Fnac', 'url' => 'https://www.fnac.com/a1?awc=123', 'style' => 'primary', 'align' => 'left']];
    $affHtml = '<p>Voir <a href="https://www.amazon.fr/dp/B01?tag=moi-21&amp;th=1" target="_blank" rel="noopener">ici</a>.</p>'
        . '<p class="btns"><a class="btn btn-primary" href="https://www.fnac.com/a1?awc=123">Fnac</a></p>';
    check('aucun lien enregistré : HTML inchangé, même avec un lien Amazon', Affiliate::mark($affHtml, $affBlocks, 'fr') === $affHtml);
    check('lien refusé : adresse incomplète ou dangereuse, nom manquant', is_string(Affiliate::put(['name' => 'X', 'url' => 'javascript:alert(1)']))
        && is_string(Affiliate::put(['name' => 'X', 'url' => 'fnac.com/a1'])) && is_string(Affiliate::put(['name' => ' ', 'url' => 'https://www.fnac.com/a1'])));
    $affLink = Affiliate::put(['name' => 'Machine Fnac', 'url' => 'https://www.fnac.com/a1?awc=123', 'notes' => "Pour débuter\n12 programmes"]);
    check('lien enregistré : programme détecté, notes gardées', is_array($affLink) && $affLink['program'] === 'other' && $affLink['notes'] === "Pour débuter\n12 programmes");
    $affMarked = Affiliate::mark($affHtml, $affBlocks, 'fr');
    check('page avec liens affiliés : mention en tête, avec la phrase Amazon de la boutique', str_starts_with($affMarked, '<aside class="callout callout-info">')
        && str_contains($affMarked, 'Liens d’affiliation') && str_contains($affMarked, 'si vous achetez par l’un d’eux')
        && str_contains($affMarked, 'En tant que Partenaire Amazon, je réalise un bénéfice sur les achats remplissant les conditions requises.'), $affMarked);
    check('liens marqués rel="sponsored nofollow", rel existant gardé', str_contains($affMarked, 'target="_blank" rel="noopener sponsored nofollow">ici</a>')
        && str_contains($affMarked, 'href="https://www.fnac.com/a1?awc=123" rel="sponsored nofollow">Fnac</a>'), $affMarked);
    $affHero = ['type' => 'hero', 'title' => 'Titre', 'text' => '', 'button' => '', 'url' => '', 'button2' => '', 'url2' => '', 'media' => ''];
    $affPlaced = Affiliate::mark('<section class="hero"><h1>Titre</h1></section>' . "\n" . $affHtml, array_merge([$affHero], $affBlocks), 'fr');
    check('bandeau d’ouverture sans lien affilié : mention juste après lui, avant le premier lien', str_starts_with($affPlaced, '<section class="hero"><h1>Titre</h1></section>' . "\n" . '<aside class="callout callout-info">'), $affPlaced);
    $affLinked = Affiliate::mark('<section class="hero"><a class="btn" href="https://www.fnac.com/a1?awc=123">Fnac</a></section>' . "\n", [['url' => 'https://www.fnac.com/a1?awc=123', 'button' => 'Fnac'] + $affHero], 'fr');
    check('bandeau avec un lien affilié : mention au-dessus de lui', str_starts_with($affLinked, '<aside class="callout callout-info">'), $affLinked);
    check('page sans lien affilié : HTML inchangé', Affiliate::mark('<p>x</p>', [['type' => 'paragraph', 'text' => '<a href="https://www.fnac.com/autre">autre</a>']], 'fr') === '<p>x</p>');
    Affiliate::setDisclosure('Liens sponsorisés : merci de votre soutien.');
    check('mention personnalisée, phrase Amazon de la boutique toujours ajoutée', str_contains(Affiliate::mark($affHtml, $affBlocks, 'es'),
        'Liens sponsorisés : merci de votre soutien. En tant que Partenaire Amazon'));
    $affLinks = [['name' => 'Premier', 'url' => 'https://www.amazon.fr/dp/B01?tag=moi-21&th=1'], ['name' => 'Second', 'url' => 'https://www.fnac.com/a1?awc=123']];
    $affFilled = Affiliate::fill([
        ['type' => 'button', 'text' => 'Voir', 'url' => 'https://aff.invalid/1'],
        ['type' => 'paragraph', 'text' => '<a href="https://aff.invalid/1">un</a>, <a href="https://aff.invalid/10">dix</a> et https://aff.invalid/2 seul.'],
    ], $affLinks, 'fr');
    check('IA : vraies adresses posées à l’identique (champ d’adresse) et échappées (texte)', $affFilled[0]['url'] === 'https://www.amazon.fr/dp/B01?tag=moi-21&th=1'
        && str_contains($affFilled[1]['text'], '<a href="https://www.amazon.fr/dp/B01?tag=moi-21&amp;th=1">un</a>'), json_encode($affFilled, JSON_UNESCAPED_SLASHES));
    check('IA : lien vers un produit inconnu retiré (texte gardé), adresse provisoire hors lien retirée', str_contains($affFilled[1]['text'], ', dix et ')
        && !str_contains((string) json_encode($affFilled, JSON_UNESCAPED_SLASHES), 'aff.invalid'));
    check('IA : produit qu’elle n’a pas relié → son bouton à la fin', count($affFilled) === 3 && ($affFilled[2]['type'] ?? '') === 'button'
        && ($affFilled[2]['url'] ?? '') === 'https://www.fnac.com/a1?awc=123' && ($affFilled[2]['text'] ?? '') === 'Second : voir l’offre');
    Affiliate::remove($affLink['id']);
    Affiliate::setDisclosure('');
} finally {
    $affBackup === null ? @unlink($affFile) : file_put_contents($affFile, $affBackup);
}

/* ─────────────────────────── Parcours HTTP ─────────────────────────── */
if (in_array('--unit', $argv, true)) {
    return;
}
section('Affiliation : page, article rédigé par l’IA, mention sur le site public et dans le flux');
[$copy, $port, $server] = serve('affiliate');
$aiDir = sys_get_temp_dir() . '/eclair-fake-aff-' . getmypid();
@mkdir($aiDir);
[$aiPort, $aiServer] = start_server([__DIR__ . '/fake-ai.php'], ['FAKE_AI_DIR' => $aiDir, 'PHP_CLI_SERVER_WORKERS' => '2'], 40001, 50000);
file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_AI_ENDPOINTS', ['anthropic' => 'http://127.0.0.1:$aiPort', 'openai' => 'http://127.0.0.1:$aiPort']);\n");
usleep(400000);
$read = static fn (string $file): array => json_decode(substr((string) @file_get_contents($copy . '/data/' . $file . '.php'), strlen(Store::GUARD)), true) ?: [];
try {
    $c = client($port);
    $form = $c->req('GET', '/');
    $c->req('POST', '/', $c->token($form['body']) + [
        'ui' => 'fr', 'site_name' => 'Fournil Test', 'site_tagline' => 'Pain au levain', 'site_lang' => 'fr', 'site_type' => 'blog',
        'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion',
    ], ['Accept: application/json']);
    $c->login('camille@example.com', 'Un-mot-de-passe-solide');
    $page = $c->req('GET', '/gestion/affiliate');
    check('page Affiliation : menu, formulaire, mention par défaut, sans attribut style (CSP)', $page['status'] === 200 && str_contains($page['body'], 'name="action" value="add"')
        && str_contains($page['body'], 'href="/gestion/affiliate"') && str_contains($page['body'], 'si vous achetez par l’un d’eux') && !preg_match('~<[^>]+\sstyle="~', $page['body']));
    $c->req('POST', '/gestion/affiliate', $c->token($page['body']) + ['action' => 'add', 'name' => 'Piège', 'url' => 'javascript:alert(1)']);
    check('adresse dangereuse refusée', ($read('affiliate')['links'] ?? []) === []);
    $page = $c->req('GET', '/gestion/affiliate');
    $c->req('POST', '/gestion/affiliate', $c->token($page['body']) + ['action' => 'add', 'name' => 'Machine Premier', 'url' => 'https://www.amazon.fr/dp/B01?tag=moi-21&th=1',
        'notes' => "Pour débuter\n12 programmes"]);
    $c->req('POST', '/gestion/affiliate', $c->token($page['body']) + ['action' => 'add', 'name' => 'Machine Second', 'url' => 'https://www.fnac.com/a1?awc=123']);
    $links = array_column($read('affiliate')['links'] ?? [], null, 'name');
    check('liens ajoutés sans JavaScript, Amazon reconnu', count($links) === 2 && ($links['Machine Premier']['program'] ?? '') === 'amazon'
        && ($links['Machine Premier']['url'] ?? '') === 'https://www.amazon.fr/dp/B01?tag=moi-21&th=1');
    $page = $c->req('GET', '/gestion/affiliate');
    check('assistant IA coupé : pas de rédaction, invitation à l’activer', !str_contains($page['body'], 'data-aff-write') && str_contains($page['body'], 'settings?tab=ai'));

    // Assistant IA activé (faux fournisseur).
    $set = $c->req('GET', '/gestion/settings?tab=ai');
    $c->req('POST', '/gestion/settings', $c->token($set['body']) + ['tab' => 'ai', 'provider' => 'anthropic', 'key_ai_anthropic' => 'sk-ant-test-0123456789',
        'model_anthropic' => 'claude-sonnet-5-5', 'budget' => '10', 'daily' => '60', 'editors' => '1']);
    $page = $c->req('GET', '/gestion/affiliate');
    check('assistant IA actif : rédaction proposée, coût indicatif affiché', str_contains($page['body'], 'data-aff-write')
        && (bool) preg_match('#Rédiger avec les liens cochés <small>· ~[\d,]+.\$#u', $page['body']));
    preg_match('/"csrf":"([a-f0-9]+)"/', $page['body'], $m);
    $api = static fn (string $path, array $data): array => ($res = $c->req('POST', '/gestion/' . $path, (string) json_encode($data),
        ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])) + ['json' => json_decode($res['body'], true)];
    check('aucun lien coché : refusé sans appel payant', ($api('api/affiliate/write', ['links' => [], 'format' => 'review'])['json']['ok'] ?? true) === false);
    $write = $api('api/affiliate/write', ['links' => [$links['Machine Premier']['id'] ?? '', $links['Machine Second']['id'] ?? ''], 'format' => 'compare', 'keyword' => 'machine à pain']);
    preg_match('~/edit/([a-z0-9]+)~', (string) ($write['json']['edit'] ?? ''), $e);
    $draft = $read('content/' . ($e[1] ?? 'x'));
    check('article rédigé par l’IA : brouillon jamais publié, ouvert dans l’éditeur', ($write['json']['ok'] ?? false) === true && ($draft['status'] ?? '') === 'draft', $write['body']);
    $blocks = (string) json_encode($draft['blocks'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    check('vraies adresses posées à l’identique, rien de provisoire, produit oublié relié par son bouton',
        str_contains($blocks, '"url":"https://www.amazon.fr/dp/B01?tag=moi-21&th=1"') && str_contains($blocks, 'href=\"https://www.amazon.fr/dp/B01?tag=moi-21&amp;th=1\"')
        && str_contains($blocks, '"url":"https://www.fnac.com/a1?awc=123"') && !str_contains($blocks, 'aff.invalid'), $blocks);
    $lines = array_values(array_filter(explode("\n", (string) @file_get_contents($aiDir . '/log.jsonl'))));
    $prompt = (string) json_encode(json_decode((string) end($lines), true)['b'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    check('IA : noms et notes seulement, adresses provisoires, aucun prix ni avis inventé, pas de mention à écrire', str_contains($prompt, 'Machine Premier')
        && str_contains($prompt, 'Pour débuter') && str_contains($prompt, 'https://aff.invalid/1') && !str_contains($prompt, 'tag=moi-21')
        && str_contains($prompt, 'Never invent a price') && str_contains($prompt, 'the product links given in the brief') && str_contains($prompt, 'Do not write any advertising disclosure'));
    check('coût compté dans le budget du mois', (float) ($read('ai/usage')['months'][date('Y-m')]['cost'] ?? 0) > 0);

    $pub = $api('api/ai/publish', ['id' => $e[1] ?? '']);
    $public = $c->req('GET', (string) ($pub['json']['url'] ?? '/x'));
    check('page publique : mention en tête, avec la phrase Amazon de la boutique', $public['status'] === 200 && str_contains($public['body'], 'Liens d’affiliation')
        && str_contains($public['body'], 'En tant que Partenaire Amazon, je réalise un bénéfice sur les achats remplissant les conditions requises.'), (string) ($pub['json']['url'] ?? ''));
    check('page publique : liens affiliés marqués rel="sponsored nofollow"', (bool) preg_match('~href="https://www\.amazon\.fr/dp/B01\?tag=moi-21&amp;th=1"[^>]*rel="[^"]*sponsored nofollow"~', $public['body'])
        && (bool) preg_match('~href="https://www\.fnac\.com/a1\?awc=123"[^>]*rel="sponsored nofollow"~', $public['body']));
    check('page sans lien affilié : aucune mention', !str_contains($c->req('GET', '/a-propos')['body'], 'Liens d’affiliation'));
    check('flux RSS : mention dans le contenu de l’article', str_contains($c->req('GET', '/feed.xml')['body'], 'Liens d’affiliation'));

    $page = $c->req('GET', '/gestion/affiliate');
    $c->req('POST', '/gestion/affiliate', $c->token($page['body']) + ['action' => 'disclosure', 'disclosure' => 'Liens sponsorisés : merci de votre soutien.']);
    $again = $c->req('GET', (string) ($pub['json']['url'] ?? '/x'))['body'];
    check('mention personnalisée en ligne aussitôt (pages recalculées), phrase Amazon gardée', str_contains($again, 'merci de votre soutien. En tant que Partenaire Amazon'),
        (preg_match('~<aside class="callout callout-info">.*?</aside>~s', $again, $aside) ? $aside[0] : 'pas de mention') . ' | stocké : ' . ($read('affiliate')['disclosure'] ?? '?'));
    $page = $c->req('GET', '/gestion/affiliate');
    $c->req('POST', '/gestion/affiliate', $c->token($page['body']) + ['action' => 'delete', 'id' => $links['Machine Second']['id'] ?? '']);
    check('lien retiré de la liste', count($read('affiliate')['links'] ?? []) === 1);
} finally {
    proc_terminate($server);
    proc_terminate($aiServer);
    exec('rm -rf ' . escapeshellarg($copy) . ' ' . escapeshellarg($aiDir));
}
