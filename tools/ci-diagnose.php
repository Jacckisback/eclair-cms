<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Diagnostic de la CI (outil de développement, jamais livré) : installe une copie d'Éclair en mode débogage sur le
 * serveur intégré, demande quelques pages et affiche le détail de la première erreur, ainsi que les journaux.
 *   php tools/ci-diagnose.php
 */
declare(strict_types=1);

$copy = sys_get_temp_dir() . '/eclair-diagnose-' . getmypid();
exec('rm -rf ' . escapeshellarg($copy) . ' && cp -r ' . escapeshellarg(dirname(__DIR__) . '/eclair') . ' ' . escapeshellarg($copy));
file_put_contents($copy . '/eclair-config.php', "<?php\ndefined('ECLAIR') || exit;\ndefine('ECLAIR_DEBUG', true);\n");
$port = random_int(40000, 49999);
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $copy, __DIR__ . '/router.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', $copy . '-server.log', 'w']], $pipes);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}
$jar = tempnam(sys_get_temp_dir(), 'jar');
$req = static function (string $method, string $path, array $data = [], array $headers = []) use ($port, $jar): array {
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20] + ($data ? [CURLOPT_POSTFIELDS => http_build_query($data)] : []));
    $body = (string) curl_exec($ch);
    return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body];
};
[, $form] = $req('GET', '/');
preg_match('/name="_ft" value="([a-f0-9]+)"/', $form, $m);
[$code, $body] = $req('POST', '/', ['_ft' => $m[1] ?? '', 'ui' => 'fr', 'site_name' => 'Diagnostic', 'site_tagline' => 'CI', 'site_lang' => 'fr', 'site_type' => 'business',
    'user_name' => 'Camille Test', 'user_email' => 'ci@example.com', 'user_pass' => 'Un-mot-de-passe-solide', 'index' => '1', 'ai' => 'open', 'admin_path' => 'gestion'], ['Accept: application/json']);
echo "Installation : $code " . substr($body, 0, 300) . "\n";
foreach (['/', '/a-propos', '/sitemap.xml', '/gestion/login'] as $path) {
    [$code, $body] = $req('GET', $path);
    echo "GET $path : $code\n";
    if ($code >= 500) {
        echo html_entity_decode(strip_tags(substr($body, 0, 6000))), "\n";
    }
}
proc_terminate($server);
echo "--- journal du serveur ---\n" . substr((string) @file_get_contents($copy . '-server.log'), -3000) . "\n";
foreach (glob($copy . '/data/logs/*.php') ?: [] as $f) {
    echo "--- $f ---\n" . substr((string) file_get_contents($f), -3000) . "\n";
}
