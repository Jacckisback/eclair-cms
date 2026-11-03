<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Annonce de mise à jour (dist/latest.json) : signature et vérification, pour tools/build.php et pour les tests.
 *
 *   {"payload": annonce d'origine, "signature": sa signature,
 *    "v2": {"payload": annonce du format 2, "signature": sa signature}}
 *
 * L'annonce d'origine garde exactement ses champs (version, date, url, sha256, size, php, notes) et sa signature : les
 * sites en 1.10.0 à 1.10.2 la vérifient comme avant et ignorent le champ v2. L'annonce du format 2 reprend ces champs et
 * ajoute serial (numéro strictement croissant) et expires (échéance, horodatage Unix) ; sa signature porte sur le contexte
 * d'app/lib/Update.php suivi de l'annonce, si bien qu'elle ne vaut que pour ce format.
 */
declare(strict_types=1);

/** Champs de l'annonce d'origine, dans leur ordre (forme inchangée depuis la 1.10.0). */
const ECLAIR_ANNOUNCE_LEGACY = ['version', 'date', 'url', 'sha256', 'size', 'php', 'notes'];

/**
 * Annonce signée des deux façons. $fields : les champs de l'annonce d'origine. $legacy : annonce d'origine déjà signée,
 * gardée telle quelle (re-signature sans nouvelle version) ; sinon elle est faite à partir de $fields.
 */
function eclair_announce_sign(array $fields, int $serial, int $expires, string $secret, string $context, ?array $legacy = null): array
{
    $old = [];
    foreach (ECLAIR_ANNOUNCE_LEGACY as $name) {
        $old[$name] = $fields[$name] ?? null;
    }
    $payload = is_string($legacy['payload'] ?? null) ? $legacy['payload'] : (string) json_encode($old, JSON_UNESCAPED_SLASHES);
    $signature = is_string($legacy['signature'] ?? null) ? $legacy['signature'] : base64_encode(sodium_crypto_sign_detached($payload, $secret));
    $v2 = (string) json_encode(['version' => $old['version'], 'serial' => $serial, 'expires' => $expires] + $old, JSON_UNESCAPED_SLASHES);
    return ['payload' => $payload, 'signature' => $signature, 'v2' => ['payload' => $v2, 'signature' => base64_encode(sodium_crypto_sign_detached($context . $v2, $secret))]];
}

/** Contenu de dist/latest.json. */
function eclair_announce_json(array $channel): string
{
    return json_encode($channel, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

/**
 * Annonce vérifiée avec la clé publique : ['fields' => champs (ceux du format 2 s'il est là), 'serial', 'expires' (0 sans
 * format 2), 'channel' => fichier décodé] ; ou la raison du refus (signature fausse, annonces qui se contredisent…).
 */
function eclair_announce_read(string $json, string $public, string $context): array|string
{
    $channel = json_decode($json, true);
    if (!is_array($channel) || !is_string($channel['payload'] ?? null) || !is_string($channel['signature'] ?? null)) {
        return 'annonce illisible';
    }
    $verify = static function (string $message, mixed $signature) use ($public): bool {
        $sig = is_string($signature) ? base64_decode($signature, true) : false;
        return is_string($sig) && strlen($sig) === SODIUM_CRYPTO_SIGN_BYTES && sodium_crypto_sign_verify_detached($sig, $message, $public);
    };
    $old = json_decode($channel['payload'], true);
    if (!$verify($channel['payload'], $channel['signature']) || !is_array($old)) {
        return 'signature d’origine fausse';
    }
    if (!array_key_exists('v2', $channel)) {
        return ['fields' => $old, 'serial' => 0, 'expires' => 0, 'channel' => $channel];
    }
    $v2 = $channel['v2'];
    $new = is_array($v2) && is_string($v2['payload'] ?? null) ? json_decode($v2['payload'], true) : null;
    if (!is_array($new) || !$verify($context . $v2['payload'], $v2['signature'] ?? null)) {
        return 'signature du format 2 fausse';
    }
    if (!is_int($new['serial'] ?? null) || $new['serial'] < 1 || !is_int($new['expires'] ?? null)) {
        return 'numéro de série ou échéance absents';
    }
    foreach (['version', 'url', 'sha256', 'size'] as $name) {
        if (($new[$name] ?? null) !== ($old[$name] ?? null)) {
            return 'les deux annonces ne désignent pas la même archive';
        }
    }
    return ['fields' => array_diff_key($new, ['serial' => 0, 'expires' => 0]), 'serial' => $new['serial'], 'expires' => $new['expires'], 'channel' => $channel];
}
