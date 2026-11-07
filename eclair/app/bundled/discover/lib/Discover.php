<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace EclairExt\Discover;

use Eclair\Content;
use Eclair\Media;
use Eclair\Security;
use Eclair\Seo;
use Eclair\Settings;
use Eclair\Users;

defined('ECLAIR') || exit;

/**
 * « Prêt pour Discover » : ce que Google recommande, vérifié sur le site et sur ses derniers articles.
 *
 * Google n'offre aucun réglage pour « se positionner » sur Discover : un contenu indexé y est éligible automatiquement
 * (developers.google.com/search/docs/appearance/google-discover). La page recommande de grandes images (1200 px de large au
 * moins, max-image-preview:large, jamais le logo du site), des titres qui résument le contenu sans sensationnalisme, et un
 * contenu utile et fiable (auteur présenté, éditeur identifiable). Éclair sert déjà max-image-preview:large et les données
 * structurées ; ces contrôles montrent ce qu'il reste à faire de son côté.
 */
final class Discover
{
    /** Largeur minimale recommandée pour l'image d'un article. */
    public const WIDTH = 1200;
    /** En dessous, l'article est signalé comme court (un repère, pas une règle de Google). */
    public const WORDS = 300;
    /** Articles contrôlés : les plus récents (Discover met surtout en avant des contenus frais). */
    public const MAX = 100;

    /** Contrôles du site : ouvert aux moteurs, HTTPS, logo de l'éditeur, pages « À propos » et « Contact ». */
    public static function site(): array
    {
        $kinds = array_map(fn (array $p): string => Seo::pageType($p), Content::published('page'));
        return [
            'index' => (bool) Settings::get('seo.index'),
            'https' => Security::httpsOnly(),
            'logo' => Media::get((string) Settings::get('appearance.logo')) !== null,
            'about' => in_array('AboutPage', $kinds, true),
            'contact' => in_array('ContactPage', $kinds, true),
        ];
    }

    /** Contrôles des derniers articles publiés, du plus récent au plus ancien. */
    public static function posts(): array
    {
        $logo = (string) Settings::get('appearance.logo');
        $bios = [];
        $out = [];
        foreach (array_slice(Content::published('post'), 0, self::MAX, true) as $id => $e) {
            $m = $e['image'] !== '' ? Media::get($e['image']) : null;
            $bios[$e['author']] ??= trim((string) (Users::profile(Users::find((string) $e['author']))['bio'] ?? '')) !== '';
            $image = match (true) {
                $m === null => 'none',
                $e['image'] === $logo => 'logo',
                (int) $m['w'] < self::WIDTH => 'small',
                default => 'ok',
            };
            $title = self::title((string) $e['title']);
            $row = [
                'entry' => $e,
                'image' => $image,
                'width' => $m ? (int) $m['w'] : 0,
                'title' => $title,
                'author' => $bios[$e['author']],
                'index' => empty($e['noindex']),
                'words' => (int) ($e['words'] ?? 0),
            ];
            $row['ready'] = $image === 'ok' && $title === '' && $row['author'] && $row['index'] && $row['words'] >= self::WORDS;
            $out[$id] = $row;
        }
        return $out;
    }

    /**
     * Titre à revoir ? 'caps' (surtout des majuscules), 'punct' (« !! », « ?! »…), 'long' (coupé au-delà de 110 caractères
     * dans les données structurées), sinon chaîne vide. Des signes visibles de sensationnalisme, pas un jugement sur le fond.
     */
    public static function title(string $title): string
    {
        $letters = (string) preg_replace('/\P{L}/u', '', $title);
        $upper = (string) preg_replace('/\P{Lu}/u', '', $title);
        return match (true) {
            mb_strlen($letters) >= 12 && mb_strlen($upper) > mb_strlen($letters) * 0.6 => 'caps',
            (bool) preg_match('/[!?]\s*[!?]/u', $title) => 'punct',
            mb_strlen($title) > 110 => 'long',
            default => '',
        };
    }
}
