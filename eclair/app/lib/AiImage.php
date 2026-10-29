<?php
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
        $prompt = trim(mb_substr($prompt, 0, 3500));
        if (mb_strlen($prompt) < 10) {
            throw new \RuntimeException('The image prompt is too short: describe the subject, setting, light and style.');
        }
        [, $model, $price] = Ai::IMAGES[$provider];
        $budget = (float) Ai::config()['budget'];
        if ($budget > 0 && Ai::usage()['cost'] + $price > $budget) {
            throw new \RuntimeException(t('Budget IA du mois atteint ({spent} sur {budget}). Un administrateur peut l’augmenter dans Réglages › Assistant IA.', [
                'spent' => Ai::money(Ai::usage()['cost']), 'budget' => Ai::money($budget),
            ]));
        }
        $size = self::FORMATS[$format] ?? self::FORMATS['landscape'];
        $key = Ai::imageKey($provider);
        @set_time_limit(300);
        [$bytes, $cost] = match ($provider) {
            'openai' => self::openai($key, $model, $prompt, $size[0]),
            'bfl' => self::bfl($key, $model, $prompt, $size[1]),
            default => self::ideogram($key, $model, $prompt, $size[2]),
        };
        Ai::charge($user['id'], $cost ?? $price, 1);
        $ext = str_starts_with($bytes, "\x89PNG") ? 'png' : (substr($bytes, 8, 4) === 'WEBP' ? 'webp' : 'jpg');
        $base = slugify($name !== '' ? $name : $alt, (string) Settings::get('lang'), 50) ?: 'image-ia';
        $m = Media::import($bytes, $base . '.' . $ext, $user['id']);
        $alt = Sanitize::text($alt, 250);
        return $alt !== '' ? (Media::update($m['id'], ['alt' => $alt]) ?? $m) : $m;
    }

    /** OpenAI Images : réponse en base64, coût calculé sur les jetons quand ils sont fournis. */
    private static function openai(string $key, string $model, string $prompt, string $size): array
    {
        $res = Ai::request('POST', Ai::endpoint('openai') . '/v1/images/generations', [
            'Authorization: Bearer ' . $key, 'Content-Type: application/json',
        ], ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => 'medium', 'output_format' => 'webp', 'output_compression' => 85, 'n' => 1], 240);
        $json = json_decode($res['body'], true);
        $b64 = is_array($json) ? (string) ($json['data'][0]['b64_json'] ?? '') : '';
        if ($res['status'] !== 200 || $b64 === '') {
            throw new \RuntimeException(Ai::failure('openai', $res));
        }
        $u = (array) ($json['usage'] ?? []);
        $cost = isset($u['output_tokens']) ? ((int) ($u['input_tokens'] ?? 0) * 5 + (int) $u['output_tokens'] * 30) / 1_000_000 : null;
        return [(string) base64_decode($b64, true), $cost];
    }

    /** FLUX (Black Forest Labs) : tâche asynchrone, suivie sur l'adresse de suivi renvoyée, puis téléchargée aussitôt. */
    private static function bfl(string $key, string $model, string $prompt, string $ratio): array
    {
        $res = Ai::request('POST', Ai::endpoint('bfl') . '/v1/' . $model, [
            'x-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json',
        ], ['prompt' => $prompt, 'aspect_ratio' => $ratio, 'resolution' => '1k'], 60);
        $job = json_decode($res['body'], true);
        $poll = is_array($job) ? (string) ($job['polling_url'] ?? '') : '';
        if ($res['status'] >= 300 || $poll === '' || !self::safe($poll)) {
            throw new \RuntimeException(Ai::failure('bfl', $res));
        }
        $cost = is_numeric($job['cost'] ?? null) ? (float) $job['cost'] / 100 : null;
        $until = time() + 180;
        do {
            usleep(1_500_000);
            $state = Ai::request('GET', $poll, ['x-key: ' . $key, 'Accept: application/json'], null, 30);
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
    private static function ideogram(string $key, string $model, string $prompt, string $ratio): array
    {
        $res = Ai::request('POST', Ai::endpoint('ideogram') . '/v2/image/generate/' . $model, ['Api-Key: ' . $key, 'Accept: application/json'], [
            'prompt' => $prompt, 'aspect_ratio' => $ratio, 'rendering_speed' => 'default', 'magic_prompt' => 'off', 'num_images' => '1',
        ], 180, true);
        $json = json_decode($res['body'], true);
        $image = is_array($json) ? (array) ($json['data'][0] ?? []) : [];
        if ($res['status'] === 422 || ($image && empty($image['is_image_safe']))) {
            throw new \RuntimeException(t('Image refusée par le filtre de sécurité de {name} : reformule la demande.', ['name' => Ai::IMAGES['ideogram'][0]]));
        }
        if ($res['status'] !== 200 || empty($image['url'])) {
            throw new \RuntimeException(Ai::failure('ideogram', $res));
        }
        return [self::download((string) $image['url']), null];
    }

    /** Téléchargement d'une image livrée par le fournisseur (https public, 25 Mo maximum, sans redirection). */
    private static function download(string $url): string
    {
        if (!self::safe($url)) {
            throw new \RuntimeException(t('Adresse d’image refusée.'));
        }
        $res = Ai::request('GET', $url, ['Accept: image/*'], null, 90);
        if ($res['status'] !== 200 || strlen($res['body']) < 100) {
            throw new \RuntimeException(t('Téléchargement de l’image impossible : réessaie.'));
        }
        return $res['body'];
    }

    /**
     * Adresse sûre à appeler : https et hôte public (aucune adresse interne du serveur).
     * Avec ECLAIR_AI_ENDPOINTS (passerelle ou tests), les adresses configurées sont acceptées.
     */
    private static function safe(string $url): bool
    {
        $p = parse_url($url);
        $host = (string) ($p['host'] ?? '');
        if ($host === '' || isset($p['user'])) {
            return false;
        }
        if (defined('ECLAIR_AI_ENDPOINTS')) {
            return in_array($p['scheme'] ?? '', ['http', 'https'], true);
        }
        if (($p['scheme'] ?? '') !== 'https') {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return $ips !== [];
    }
}
