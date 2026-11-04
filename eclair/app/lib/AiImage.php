<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Génération d'images par IA (GPT Image d'OpenAI, FLUX de Black Forest Labs, Ideogram).
 * L'image reçue passe par la médiathèque comme un envoi (contrôle, ré-encodage, déclinaisons WebP).
 */
final class AiImage
{
    private const FORMATS = [
        'landscape' => ['1536x1024', '16:9', '16x9'],
        'square' => ['1024x1024', '1:1', '1x1'],
        'portrait' => ['1024x1536', '3:4', '3x4'],
    ];

    /** Crée une image, l'ajoute à la médiathèque et renvoie l'enregistrement du média. */
    public static function generate(string $prompt, string $format, string $name, string $alt, array $user): array
    {
        $provider = Ai::imager();
        if ($provider === '') {
            throw new \RuntimeException(t('Aucun générateur d’images n’est configuré (Réglages › Assistant IA).'));
        }
        // Médiathèque pleine : refusé avant de payer une image qui ne pourrait pas être enregistrée.
        if (Media::full()) {
            throw new \RuntimeException(t('Limite de {n} images atteinte : supprime celles qui ne servent plus.', ['n' => Media::MAX_ITEMS]));
        }
        $prompt = trim(mb_substr($prompt, 0, 3500));
        if (mb_strlen($prompt) < 10) {
            throw new \RuntimeException('The image prompt is too short: describe the subject, setting, light and style.');
        }
        [, $model, $price] = Ai::IMAGES[$provider];
        if (($error = Ai::overBudget($price)) !== null) {
            throw new \RuntimeException($error);
        }
        $size = self::FORMATS[$format] ?? self::FORMATS['landscape'];
        $key = Ai::imageKey($provider);
        extend_time(300);
        // Dépense due dès que le fournisseur a accepté le travail (coût annoncé, sinon prix de liste) : null avant.
        $owed = null;
        try {
            [$bytes, $cost] = match ($provider) {
                'openai' => self::openai($key, $model, $prompt, $size[0], $owed),
                'bfl' => self::bfl($key, $model, $prompt, $size[1], $owed),
                default => self::ideogram($key, $model, $prompt, $size[2], $owed),
            };
        } catch (\RuntimeException $e) {
            // Image refusée par le filtre du fournisseur, échec après l'envoi ou fenêtre fermée pendant la création : le
            // fournisseur a sans doute déjà facturé l'image, qui compte donc dans le budget du mois.
            if ($owed !== null || connection_aborted()) {
                Ai::charge($user['id'], $owed ?? $price);
            }
            throw $e;
        }
        Ai::charge($user['id'], $cost ?? $price, 1);
        $ext = str_starts_with($bytes, "\x89PNG") ? 'png' : (substr($bytes, 8, 4) === 'WEBP' ? 'webp' : 'jpg');
        $base = slugify($name !== '' ? $name : $alt, (string) Settings::get('lang'), 50) ?: 'image-ia';
        $m = Media::import($bytes, $base . '.' . $ext, $user['id']);
        $alt = Sanitize::text($alt, 250);
        return $alt !== '' ? (Media::update($m['id'], ['alt' => $alt]) ?? $m) : $m;
    }

    /** OpenAI Images : réponse en base64, coût calculé sur les jetons quand ils sont fournis. */
    private static function openai(string $key, string $model, string $prompt, string $size, ?float &$owed): array
    {
        $res = Ai::request('POST', Ai::endpoint('openai') . '/v1/images/generations', [
            'Authorization: Bearer ' . $key, 'Content-Type: application/json',
        ], ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => 'medium', 'output_format' => 'webp', 'output_compression' => 85, 'n' => 1], 240);
        $json = json_decode($res['body'], true);
        $b64 = is_array($json) ? (string) ($json['data'][0]['b64_json'] ?? '') : '';
        $u = is_array($json) ? (array) ($json['usage'] ?? []) : [];
        $cost = isset($u['output_tokens']) ? ((int) ($u['input_tokens'] ?? 0) * 5 + (int) $u['output_tokens'] * 30) / 1_000_000 : null;
        if ($res['status'] === 200) {
            // Image créée (et facturée) par OpenAI, même si sa réponse est inutilisable.
            $owed = $cost ?? Ai::IMAGES['openai'][2];
        }
        if ($res['status'] !== 200 || $b64 === '') {
            throw new \RuntimeException(Ai::failure('openai', $res));
        }
        return [(string) base64_decode($b64, true), $cost];
    }

    /** FLUX (Black Forest Labs) : tâche asynchrone, suivie sur l'adresse de suivi renvoyée, puis téléchargée aussitôt. */
    private static function bfl(string $key, string $model, string $prompt, string $ratio, ?float &$owed): array
    {
        $res = Ai::request('POST', Ai::endpoint('bfl') . '/v1/' . $model, [
            'x-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json',
        ], ['prompt' => $prompt, 'aspect_ratio' => $ratio, 'resolution' => '1k'], 60);
        $job = json_decode($res['body'], true);
        $poll = is_array($job) ? (string) ($job['polling_url'] ?? '') : '';
        $cost = is_array($job) && is_numeric($job['cost'] ?? null) ? (float) $job['cost'] / 100 : null;
        if ($res['status'] < 300 && $poll !== '') {
            // Travail accepté : facturé même si l'image est ensuite refusée par le filtre ou n'arrive jamais.
            $owed = $cost ?? Ai::IMAGES['bfl'][2];
        }
        // La clé ne part que vers Black Forest Labs (ou l'adresse configurée), à une adresse IP vérifiée.
        $pin = $poll !== '' && self::official($poll, 'bfl', 'bfl.ai') ? self::pin($poll) : null;
        if ($res['status'] >= 300 || $pin === null) {
            throw new \RuntimeException(Ai::failure('bfl', $res));
        }
        $until = time() + 180;
        do {
            usleep(1_500_000);
            if (!Ai::tick()) {
                throw new \RuntimeException(t('Demande interrompue.'));
            }
            $state = Ai::request('GET', $poll, ['x-key: ' . $key, 'Accept: application/json'], null, 30, false, $pin);
            $data = json_decode($state['body'], true);
            $status = is_array($data) ? (string) ($data['status'] ?? '') : '';
            if ($status === 'Ready') {
                return [self::download((string) ($data['result']['sample'] ?? '')), $cost];
            }
            if (in_array($status, ['Request Moderated', 'Content Moderated'], true)) {
                throw new \RuntimeException(t('Image refusée par le filtre de sécurité de {name} : reformule la demande.', ['name' => Ai::IMAGES['bfl'][0]]));
            }
            if (in_array($status, ['Error', 'Task not found', 'No Card Generated'], true)) {
                throw new \RuntimeException(Ai::failure('bfl', $state, $status));
            }
        } while (time() < $until);
        throw new \RuntimeException(t('{name} n’a pas répondu à temps : réessaie.', ['name' => Ai::IMAGES['bfl'][0]]));
    }

    /** Ideogram (API v2) : formulaire multipart, adresse de l'image à télécharger aussitôt. */
    private static function ideogram(string $key, string $model, string $prompt, string $ratio, ?float &$owed): array
    {
        $res = Ai::request('POST', Ai::endpoint('ideogram') . '/v2/image/generate/' . $model, ['Api-Key: ' . $key, 'Accept: application/json'], [
            'prompt' => $prompt, 'aspect_ratio' => $ratio, 'rendering_speed' => 'default', 'magic_prompt' => 'off', 'num_images' => '1',
        ], 180, true);
        $json = json_decode($res['body'], true);
        $image = is_array($json) ? (array) ($json['data'][0] ?? []) : [];
        if ($res['status'] === 200 && $image) {
            // Image créée (et facturée), même si le filtre la refuse ou si son téléchargement échoue.
            $owed = Ai::IMAGES['ideogram'][2];
        }
        if ($res['status'] === 422 || ($image && empty($image['is_image_safe']))) {
            throw new \RuntimeException(t('Image refusée par le filtre de sécurité de {name} : reformule la demande.', ['name' => Ai::IMAGES['ideogram'][0]]));
        }
        if ($res['status'] !== 200 || empty($image['url'])) {
            throw new \RuntimeException(Ai::failure('ideogram', $res));
        }
        return [self::download((string) $image['url']), null];
    }

    /** Téléchargement d'une image livrée par le fournisseur (https public, 25 Mo maximum, sans redirection ni clé). */
    private static function download(string $url): string
    {
        $pin = self::pin($url);
        if ($pin === null) {
            throw new \RuntimeException(t('Adresse d’image refusée.'));
        }
        $res = Ai::request('GET', $url, ['Accept: image/*'], null, 90, false, $pin);
        if ($res['status'] !== 200 || strlen($res['body']) < 100) {
            throw new \RuntimeException($res['error'] === 'aborted' ? t('Demande interrompue.') : t('Téléchargement de l’image impossible : réessaie.'));
        }
        return $res['body'];
    }

    /** L'adresse appartient-elle au fournisseur : son domaine officiel, ou l'adresse configurée dans ECLAIR_AI_ENDPOINTS ? */
    private static function official(string $url, string $service, string $domain): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $host !== '' && ($host === $domain || str_ends_with($host, '.' . $domain)
            || $host === strtolower((string) parse_url(Ai::endpoint($service), PHP_URL_HOST)));
    }

    /**
     * Options cURL pour appeler une adresse renvoyée par un fournisseur : https et hôte public, dont l'adresse IP
     * vérifiée est imposée à cURL (aucune seconde résolution DNS ne peut viser le serveur lui-même). Null si refusée.
     * Avec ECLAIR_AI_ENDPOINTS (passerelle ou tests), seuls les hôtes configurés sont acceptés tels quels.
     */
    private static function pin(string $url): ?array
    {
        $p = parse_url($url);
        $host = strtolower((string) ($p['host'] ?? ''));
        if ($host === '' || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        foreach (defined('ECLAIR_AI_ENDPOINTS') ? (array) constant('ECLAIR_AI_ENDPOINTS') : [] as $endpoint) {
            if (strtolower((string) parse_url((string) $endpoint, PHP_URL_HOST)) === $host) {
                return [];
            }
        }
        // Nom d'hôte ASCII seulement : cURL décode « %2e » et convertit les noms internationaux avant de résoudre,
        // et la résolution imposée ci-dessous ne s'appliquerait plus au nom réellement contacté.
        if (($p['scheme'] ?? '') !== 'https' || !preg_match('~^[a-z0-9.-]+$~', $host)) {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (!self::publicIp($ip)) {
                return null;
            }
        }
        return $ips === [] ? null : [
            CURLOPT_RESOLVE => [$host . ':' . (int) ($p['port'] ?? 443) . ':' . $ips[0]],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];
    }

    /**
     * Adresse IPv4 publique et joignable : ni privée ni réservée, ni partagée (CGNAT 100.64/10, dont des adresses de
     * métadonnées de nuages), ni de test, de documentation, de banc d'essai, de relais 6to4 ou de multidiffusion.
     */
    private static function publicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $n = (int) ip2long($ip);
        foreach (['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24',
            '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits) & 0xFFFFFFFF;
            if (($n & $mask) === ((int) ip2long($net) & $mask)) {
                return false;
            }
        }
        return true;
    }
}
