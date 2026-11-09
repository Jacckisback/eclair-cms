<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Faux Google PageSpeed Insights pour les tests (php -S … tests/fake-psi.php) : l'ancien site est lent, le nouveau rapide. */
declare(strict_types=1);

parse_str((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_QUERY), $q);
header('Content-Type: application/json');
if (!str_ends_with((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/runPagespeed') || ($q['category'] ?? '') !== 'performance') {
    http_response_code(400);
    echo '{"error":{"message":"bad request"}}';
    return;
}
$old = str_contains((string) ($q['url'] ?? ''), 'ancien');
$v = static fn (float $n): array => ['numericValue' => $n];
echo json_encode(['lighthouseResult' => [
    'categories' => ['performance' => ['score' => $old ? 0.42 : 0.98]],
    'audits' => [
        'first-contentful-paint' => $v($old ? 3100 : 700), 'largest-contentful-paint' => $v($old ? 6200 : 1100),
        'total-blocking-time' => $v($old ? 900 : 0), 'cumulative-layout-shift' => $v($old ? 0.21 : 0.0), 'speed-index' => $v($old ? 5400 : 900),
        'total-byte-weight' => $v($old ? 3800000 : 160000), 'network-requests' => ['details' => ['items' => array_fill(0, $old ? 96 : 9, [])]],
    ],
]]);
