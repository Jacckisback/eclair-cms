<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Skills livrés avec Éclair (skills/<nom>/) : manifeste lisible par les outils de skills, fichiers cités présents, et
 * squelette de thème du skill de direction artistique réellement installable, activable et rendu comme sa référence
 * l'annonce (variables du contrat, mode sombre retiré quand le site est réglé sur « Toujours clair »). Inclus par
 * tests/run.php.
 */
declare(strict_types=1);

use Eclair\Settings;
use Eclair\Store;
use Eclair\Theme;
use Eclair\Themes;

(static function (): void {
    section('Skills livrés avec Éclair (skills/) : manifeste, références, squelette de thème');
    $root = dirname(__DIR__) . '/skills';
    $skills = glob($root . '/*/SKILL.md') ?: [];
    check('au moins un skill livré, chacun avec son SKILL.md', $skills !== []);
    foreach ($skills as $file) {
        $dir = dirname($file);
        $name = basename($dir);
        $src = (string) file_get_contents($file);
        $front = preg_match('~\A---\n(.*?)\n---\n~s', $src, $m) ? $m[1] : '';
        $field = static fn (string $k): string => preg_match('~^' . $k . ':\s*(.+)$~m', $front, $f) ? trim($f[1]) : '';
        check("$name : en-tête avec name égal au dossier et description de 1 024 caractères au plus",
            $field('name') === $name && $field('description') !== '' && mb_strlen($field('description')) <= 1024, $field('name'));
        preg_match_all('~`((?:references|scripts|assets)/[^`\s]+)`~', $src, $refs);
        $missing = array_values(array_filter(array_unique($refs[1]), static fn (string $rel): bool => !file_exists($dir . '/' . $rel)));
        check("$name : chaque fichier cité par SKILL.md existe", $missing === [], implode(', ', $missing));
    }

    $skeleton = $root . '/thierry-skill/assets/eclair-theme/mon-theme';
    check('squelette de thème du skill : conforme au contrat', is_dir($skeleton) && Themes::validate($skeleton) === [], implode(' | ', Themes::validate($skeleton)));
    $r = Themes::install($skeleton);
    $activated = $r['ok'] ? Themes::activate('mon-theme') : ['ok' => false];
    $reset = static function (): void {
        Store::forget();
        Settings::forget();
        Themes::forget();
        (new ReflectionProperty(Theme::class, 'css'))->setValue(null, null);
    };
    $reset();
    $css = Theme::css();
    check('squelette installé et activé : variables du contrat et mode sombre du thème dans la page', $r['ok'] && $activated['ok']
        && str_contains($css, '--on-accent:oklch(0.985') && str_contains($css, 'oklch(0.18 0.01 250)'), (string) ($r['error'] ?? $activated['error'] ?? ''));
    Settings::save(['appearance' => ['dark' => 'light']]);
    $reset();
    check('site réglé sur « Toujours clair » : le mode sombre du thème est retiré, le reste du thème gardé',
        !str_contains(Theme::css(), 'oklch(0.18 0.01 250)') && str_contains(Theme::css(), '--on-accent:oklch(0.985'));
    Settings::save(['appearance' => ['dark' => 'auto']]);
    Themes::deactivate();
    $reset();
})();
