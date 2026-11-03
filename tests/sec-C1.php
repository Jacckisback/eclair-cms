<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests de sécurité de la 1.10.3, groupe C1 (canal de mise à jour, outils de publication, sauvegardes, Google Drive,
 * secrets, installation, règles du serveur), inclus par tests/run.php. Les parties HTTP ne tournent pas avec --unit.
 */
declare(strict_types=1);

use Eclair\Store;

/**
 * Lance un petit programme PHP dans un processus séparé, avec ses propres constantes (ECLAIR_ROOT, ECLAIR_DATA…) :
 * $code reçoit $lib (classes d'Éclair) et $args ; il écrit son résultat en JSON.
 */
function c1_child(string $code, array $args): array
{
    $script = sys_get_temp_dir() . '/eclair-c1-child-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($script, "<?php\ndeclare(strict_types=1);\n\$lib = \$argv[1];\n\$args = json_decode(\$argv[2], true);\n"
        . "define('ECLAIR', true);\ndefine('ECLAIR_VERSION', 'test');\ndefine('ECLAIR_TESTING', true);\n"
        . "define('ECLAIR_ROOT', \$args['root']);\ndefine('ECLAIR_DATA', \$args['data']);\n"
        . "spl_autoload_register(static function (string \$class) use (\$lib): void {\n"
        . "    \$file = \$lib . '/' . substr(\$class, 7) . '.php';\n"
        . "    if (str_starts_with(\$class, 'Eclair\\\\') && is_file(\$file)) { require \$file; }\n"
        . "});\nrequire \$lib . '/helpers.php';\n" . $code);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(ECLAIR_ROOT . '/app/lib') . ' ' . escapeshellarg((string) json_encode($args)) . ' 2>&1', $out, $rc);
    @unlink($script);
    $json = json_decode(implode("\n", $out), true);
    return is_array($json) ? $json : ['error' => $rc . ' ' . implode("\n", $out)];
}

/* ───────────── Sauvegarde : ECLAIR_DATA écrit avec une barre finale (processus séparé) ───────────── */
section('Sauvegarde : secrets exclus quelle que soit l’écriture du dossier des données');
$c1Root = sys_get_temp_dir() . '/eclair-c1-root-' . getmypid();
$c1Data = sys_get_temp_dir() . '/eclair-c1-data-' . getmypid();
exec('rm -rf ' . escapeshellarg($c1Root) . ' ' . escapeshellarg($c1Data));
foreach (['/media/2026/11', '/media/fonts'] as $dir) {
    @mkdir($c1Root . $dir, 0755, true);
}
foreach (['sessions', 'limits', 'ai/chats', 'content', 'logs'] as $dir) {
    @mkdir($c1Data . '/' . $dir, 0700, true);
}
file_put_contents($c1Root . '/media/2026/11/photo.jpg', "\xFF\xD8\xFF\xE0image");
foreach (['keys', 'users', 'site', 'vault', 'drive', 'update', 'reset', 'sessions/' . str_repeat('a', 64), 'limits/abc', 'ai/mcp', 'ai/chats/c1', 'content/page1', 'logs/security'] as $name) {
    file_put_contents($c1Data . '/' . $name . '.php', Store::GUARD . '{"secret":"' . $name . '"}');
}
// Lien symbolique vers le coffre, rangé parmi les contenus : jamais archivé (exclusion par l'emplacement réel).
$c1Link = function_exists('symlink') && @symlink($c1Data . '/vault.php', $c1Data . '/content/copie.php');
$c1Listing = static fn (string $data): array => c1_child(<<<'PHP'
    $out = ['backup' => array_keys(Eclair\Backup::files()), 'scan' => Eclair\Integrity::scan(['data'])];
    $zip = sys_get_temp_dir() . '/eclair-c1-backup-' . getmypid() . '.zip';
    Eclair\Backup::create($zip);
    clearstatcache();
    $out['mode'] = fileperms($zip) & 0777;
    $z = new ZipArchive();
    $out['zip'] = [];
    if ($z->open($zip) === true) {
        for ($i = 0; $i < $z->numFiles; $i++) {
            $out['zip'][] = (string) $z->getNameIndex($i);
        }
        $z->close();
    }
    @unlink($zip);
    echo json_encode($out);
    PHP, ['root' => $c1Root, 'data' => $data]);
$secretNames = static fn (array $names): array => array_values(array_filter($names, static fn (string $n): bool
    => (bool) preg_match('~^data/(vault|drive|update|reset)\.php$|^data/(sessions|limits|ai)/~', $n) || $n === 'data/content/copie.php'));
$expected = ['data/content/page1.php', 'data/keys.php', 'data/logs/security.php', 'data/site.php', 'data/users.php', 'media/2026/11/photo.jpg'];
foreach (['sans barre finale' => $c1Data, 'avec une barre finale' => $c1Data . '/', 'avec deux barres finales' => $c1Data . '//'] as $label => $data) {
    $res = $c1Listing($data);
    $backup = (array) ($res['backup'] ?? []);
    check("ECLAIR_DATA $label : aucun secret ni état de service dans la liste de sauvegarde", isset($res['backup']) && $secretNames($backup) === [], json_encode($res));
    check("ECLAIR_DATA $label : chemins exacts (contenus, comptes, clés, réglages, images)", $backup === $expected, json_encode($backup));
    check("ECLAIR_DATA $label : archive créée sans secret, lisible par PHP seulement (0600)", isset($res['zip']) && $secretNames((array) $res['zip']) === []
        && in_array('data/keys.php', (array) $res['zip'], true) && in_array('LISEZ-MOI.txt', (array) $res['zip'], true) && ($res['mode'] ?? 0) === 0600,
        json_encode(['mode' => decoct((int) ($res['mode'] ?? 0)), 'zip' => $res['zip'] ?? null, 'error' => $res['error'] ?? '']));
    check("ECLAIR_DATA $label : contrôle d’intégrité avec les vrais noms de fichiers", in_array('data/vault.php', (array) ($res['scan'] ?? []), true)
        && in_array('data/sessions/' . str_repeat('a', 64) . '.php', (array) ($res['scan'] ?? []), true) && !in_array('data/ault.php', (array) ($res['scan'] ?? []), true),
        json_encode($res['scan'] ?? $res));
}
check('lien symbolique vers le coffre parmi les contenus : exclu (test effectif)', $c1Link, 'symlink indisponible');
exec('rm -rf ' . escapeshellarg($c1Root) . ' ' . escapeshellarg($c1Data));
