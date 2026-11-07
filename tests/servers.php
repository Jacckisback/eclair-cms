<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Vérification d'une installation servie par un VRAI serveur web (Apache/LiteSpeed avec .htaccess, ou Nginx avec les
 * règles de docs/UPGRADE-2.md), hors de la suite principale :
 *
 *   php tests/servers.php http://127.0.0.1:8081 /srv/site/eclair
 *
 * Le dossier doit être une archive fraîchement décompressée, servie à cette adresse, non encore installée, et inscriptible
 * par ce script (des fichiers pièges y sont déposés pour vérifier qu'ils ne sont jamais servis ni exécutés).
 */
declare(strict_types=1);

[$base, $root] = [rtrim((string) ($argv[1] ?? ''), '/'), rtrim((string) ($argv[2] ?? ''), '/')];
if ($base === '' || !is_file($root . '/index.php')) {
    fwrite(STDERR, "Usage : php tests/servers.php <adresse> <dossier de l'installation>\n");
    exit(2);
}
$fail = 0;
$ok = 0;
$check = static function (string $label, bool $cond, string $detail = '') use (&$fail, &$ok): void {
    $cond ? $ok++ : $fail++;
    echo ($cond ? '  ✓ ' : '  ✗ ') . $label . ($cond || $detail === '' ? '' : "\n      " . $detail) . "\n";
};
$jar = tempnam(sys_get_temp_dir(), 'srv');
$req = static function (string $method, string $path, array|string $data = [], array $headers = []) use ($base, $jar): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_HTTPHEADER => $headers, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30]);
    if ($data !== [] && $data !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
    }
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $head[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $head, 'body' => substr($raw, $size)];
};
$token = static fn (string $body): array => preg_match('/name="(_ft|_csrf)" value="([a-f0-9]+)"/', $body, $m) ? [$m[1] => $m[2]] : [];

echo "▶ Installation\n";
$form = $req('GET', '/');
$res = $req('POST', '/', $token($form['body']) + ['ui' => 'fr', 'site_name' => 'Atelier Serveur', 'site_tagline' => 'Test', 'site_lang' => 'fr', 'site_type' => 'blog',
    'user_name' => 'Camille Test', 'user_email' => 'camille@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion'],
    ['Accept: application/json']);
$check('installation réussie', $res['status'] === 200 && str_contains($res['body'], '"ok":true'), substr($res['body'], 0, 300));
$page = $req('GET', '/gestion/login');
$req('POST', '/gestion/login', $token($page['body']) + ['email' => 'camille@example.com', 'password' => 'Un-mot-de-passe-solide']);
$admin = $req('GET', '/gestion/');
$check('administration accessible après connexion', $admin['status'] === 200 && str_contains($admin['body'], 'Tableau de bord'), (string) $admin['status']);
preg_match('/"csrf":"([a-f0-9]+)"/', $admin['body'], $m);
$api = static fn (string $path, array $data): array => json_decode($req('POST', '/gestion/' . $path, (string) json_encode($data),
    ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . ($m[1] ?? '')])['body'], true) ?: [];
$para = [['type' => 'paragraph', 'text' => 'Texte de test.']];
$api('api/save', ['type' => 'page', 'title' => 'Ancienne page', 'status' => 'published', 'route' => '/2024/03/ancienne-page/', 'blocks' => $para]);
$api('api/save', ['type' => 'page', 'title' => 'Page accentuée', 'status' => 'published', 'route' => '/café/crème', 'blocks' => $para]);
$api('api/save', ['type' => 'page', 'title' => 'Page html', 'status' => 'published', 'route' => '/vieille-page.html', 'blocks' => $para]);

echo "▶ Pages publiques\n";
$home = $req('GET', '/');
$check('accueil 200, en-têtes de sécurité (CSP, nosniff, referrer)', $home['status'] === 200 && str_contains($home['headers']['content-security-policy'] ?? '', "default-src")
    && ($home['headers']['x-content-type-options'] ?? '') === 'nosniff' && ($home['headers']['referrer-policy'] ?? '') !== '', json_encode($home['headers']));
$check('accueil servi par le cache au second appel', ($req('GET', '/')['headers']['x-cache'] ?? '') === 'HIT');
foreach (['/sitemap.xml' => 'xml', '/robots.txt' => 'text/plain', '/feed.xml' => 'rss'] as $p => $type) {
    $r = $req('GET', $p);
    $check("$p servi ($type)", $r['status'] === 200 && str_contains($r['headers']['content-type'] ?? '', $type), $r['status'] . ' ' . ($r['headers']['content-type'] ?? ''));
}
$js = preg_match('~src="([^"]*/assets/site/site\.js[^"]*)"~', $home['body'], $c) ? $req('GET', (string) preg_replace('~^https?://[^/]+~', '', html_entity_decode($c[1]))) : ['status' => 0, 'headers' => []];
$check('script du site servi directement par le serveur (JavaScript, mis en cache)', $js['status'] === 200 && str_contains($js['headers']['content-type'] ?? '', 'javascript'),
    $js['status'] . ' ' . ($js['headers']['content-type'] ?? '') . ' ' . ($c[1] ?? 'introuvable'));
$check('page inconnue : 404', $req('GET', '/page-qui-n-existe-pas')['status'] === 404);
$r = $req('GET', '/2024/03/ancienne-page/');
$check('adresse exacte avec « / » final servie', $r['status'] === 200 && str_contains($r['body'], 'Ancienne page'), (string) $r['status']);
$r = $req('GET', '/2024/03/ancienne-page');
$check('sans « / » final : 301 vers l’adresse exacte', $r['status'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/2024/03/ancienne-page/'), $r['status'] . ' ' . ($r['headers']['location'] ?? ''));
$check('adresse .html servie (règle « fichier absent → index.php »)', $req('GET', '/vieille-page.html')['status'] === 200);
$check('adresse accentuée servie', $req('GET', '/caf%C3%A9/cr%C3%A8me')['status'] === 200);
$check('ancienne adresse /index.php/x : redirigée', in_array($req('GET', '/index.php/a-propos')['status'], [301, 404], true));

echo "▶ Fichiers jamais servis\n";
@mkdir($root . '/site/themes/piege', 0755, true);
@mkdir($root . '/site/extensions/piege', 0755, true);
@mkdir($root . '/site/assets', 0755, true);
@mkdir($root . '/media/2026', 0755, true);
$secret = 'SECRET-' . bin2hex(random_bytes(4));
$traps = [
    'site/themes/piege/theme.json' => '{"secret":"' . $secret . '"}',
    'site/extensions/piege/extension.php' => '<?php echo "' . $secret . '";',
    'site/assets/x.php' => '<?php echo "' . $secret . '";',
    'site/assets/x.html' => '<p>' . $secret . '</p>',
    'media/2026/x.php' => '<?php echo "' . $secret . '";',
    'media/2026/x.phtml' => '<?php echo "' . $secret . '";',
    'media/2026/x.php.jpg' => '<?php echo "' . $secret . '";',
    'cache/x.txt' => $secret,
    'assets/x.php' => '<?php echo "' . $secret . '";',
];
foreach ($traps as $rel => $content) {
    file_put_contents($root . '/' . $rel, $content);
    @chmod($root . '/' . $rel, 0644);
}
$probe = array_merge(array_diff(array_keys($traps), ['media/2026/x.php.jpg']), ['data/site.php', 'data/users.php', 'app/bootstrap.php', 'app/lib/App.php', 'app/manifest.php', 'cache/runtime.php',
    'eclair-config.exemple.php', '.htaccess', 'data/', 'app/', 'site/themes/', 'site/extensions/', 'app/lang/en.json.gz', 'app/bundled/wordpress/extension.json']);
foreach ($probe as $rel) {
    $r = $req('GET', '/' . $rel);
    $leak = str_contains($r['body'], $secret) || str_contains($r['body'], '<?php') || str_contains($r['body'], 'password') || str_contains($r['body'], '"pass"');
    $check("/$rel : refusé ou sans contenu ({$r['status']})", !$leak && $r['status'] !== 200 || (!$leak && str_contains($r['headers']['content-type'] ?? '', 'text/html') && str_contains($r['body'], '404')),
        $r['status'] . ' ' . substr(strip_tags($r['body']), 0, 120));
}
$r = $req('GET', '/media/2026/x.php.jpg');
$check('fichier « .php.jpg » de media/ jamais exécuté (au plus servi tel quel)', !str_contains($r['body'], $secret) || str_contains($r['body'], '<?php'), $r['status'] . ' ' . substr($r['body'], 0, 80));
foreach (array_keys($traps) as $rel) {
    @unlink($root . '/' . $rel);
}
@rmdir($root . '/site/themes/piege');
@rmdir($root . '/site/extensions/piege');
@unlink($jar);
echo "\n" . ($fail ? "❌ $fail échec(s), $ok vérifications réussies\n" : "✅ $ok vérifications réussies\n");
exit($fail ? 1 : 0);
