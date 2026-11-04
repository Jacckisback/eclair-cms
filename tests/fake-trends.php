<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Faux Google Trends pour les tests (serveur intégré de PHP) : flux « Tendances du moment » (/trending/rss?geo=XX).
 * FR : trois tendances (titre avec entité, adresse « javascript: » à écarter) ; ES : erreur 500 ; DE : flux illisible.
 * Chaque requête est journalisée dans $FAKE_TR_DIR/log.jsonl.
 *
 *   FAKE_TR_DIR=/tmp/x php -S 127.0.0.1:9997 tests/fake-trends.php
 */
declare(strict_types=1);

$dir = (string) getenv('FAKE_TR_DIR') ?: sys_get_temp_dir();
$uri = (string) $_SERVER['REQUEST_URI'];
file_put_contents($dir . '/log.jsonl', json_encode(['u' => $uri], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);

if (parse_url($uri, PHP_URL_PATH) !== '/trending/rss') {
    http_response_code(404);
    exit('not found');
}
$geo = (string) ($q['geo'] ?? '');
if ($geo === 'ES') {
    http_response_code(500);
    exit('Internal error');
}
if ($geo === 'DE') {
    header('Content-Type: text/xml; charset=utf-8');
    exit('<html><body>Pas un flux</body></html>');
}
header('Content-Type: text/xml; charset=utf-8');
echo <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<rss xmlns:atom="http://www.w3.org/2005/Atom" xmlns:ht="https://trends.google.com/trending/rss" version="2.0">
  <channel>
    <title>Daily Search Trends</title>
    <description>Recent searches</description>
    <link>https://trends.google.com/trending/rss?geo={$geo}</link>
    <item>
      <title>pain au levain maison</title>
      <ht:approx_traffic>2000+</ht:approx_traffic>
      <description/>
      <link>https://trends.google.com/trending/rss?geo={$geo}</link>
      <pubDate>Wed, 4 Nov 2026 06:20:00 -0800</pubDate>
      <ht:picture>https://encrypted-tbn3.gstatic.com/images?q=tbn:test</ht:picture>
      <ht:news_item>
        <ht:news_item_title>Le levain fait son grand retour &amp;#39;chez soi&amp;#39;</ht:news_item_title>
        <ht:news_item_snippet/>
        <ht:news_item_url>https://journal.example/levain-retour</ht:news_item_url>
        <ht:news_item_source>Journal Test</ht:news_item_source>
      </ht:news_item>
      <ht:news_item>
        <ht:news_item_title>Lien piégé</ht:news_item_title>
        <ht:news_item_url>javascript:alert(1)</ht:news_item_url>
        <ht:news_item_source>Piège</ht:news_item_source>
      </ht:news_item>
    </item>
    <item>
      <title>Match &amp; résultats</title>
      <ht:approx_traffic>500+</ht:approx_traffic>
      <pubDate>Wed, 4 Nov 2026 05:00:00 -0800</pubDate>
    </item>
    <item>
      <title>   </title>
      <ht:approx_traffic>100+</ht:approx_traffic>
    </item>
    <item>
      <title>célébrité du jour</title>
      <ht:approx_traffic>200+</ht:approx_traffic>
      <pubDate>Wed, 4 Nov 2026 04:00:00 -0800</pubDate>
      <ht:news_item>
        <ht:news_item_title><![CDATA[Une <b>célébrité</b> fait parler d’elle]]></ht:news_item_title>
        <ht:news_item_url>https://people.example/celebrite</ht:news_item_url>
        <ht:news_item_source>People Test</ht:news_item_source>
      </ht:news_item>
    </item>
  </channel>
</rss>
XML;
