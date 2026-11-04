<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Tests du lot ergonomie (1.11) : suppression annulable ou confirmée dans une fenêtre de l'administration, barre du mode
 * Visuel en icônes dessinées, couleur « à surveiller » sans brun, filtres sur une ligne, micro-animations (administration
 * et site public). Inclus par tests/run.php.
 */
declare(strict_types=1);

use Eclair\Design;
use Eclair\Settings;
use Eclair\Theme;

section('1.11 : micro-animations du site (Réglages › Apparence)');
check('coupées par défaut : un site d’avant la 1.11 garde sa page à l’octet près', Settings::DEFAULTS['appearance']['motion'] === false);
Theme::preview([]);
$off = Theme::css();
Theme::preview(['motion' => true]);
$on = Theme::css();
Theme::preview(['motion' => true, 'direction' => Design::find('nocturne')]);
$onDirection = Theme::css();
Theme::preview([]);
check('coupées : aucune règle ajoutée', !str_contains($off, 'eclair-in') && $off === Theme::css());
check('activées : apparition au défilement, seulement pour les visiteurs qui acceptent le mouvement', str_contains($on, '@keyframes eclair-in')
    && str_contains($on, 'animation-timeline:view()') && str_contains($on, '@media screen and (prefers-reduced-motion:no-preference)'));
check('activées : moins de 1 Ko ajouté au style de la page', strlen($on) > strlen($off) && strlen($on) - strlen($off) < 1024, (string) (strlen($on) - strlen($off)));
check('activées aussi avec une direction artistique', str_contains($onDirection, '@keyframes eclair-in') && str_contains($onDirection, '--bg:#0f1724'));
$motion = (string) file_get_contents(ECLAIR_ROOT . '/app/theme/motion.css');
preg_match_all('~@keyframes[^{]+\{(.*?)\}\}~s', $motion, $frames);
check('animations sur les transformations (et l’opacité de la FAQ ouverte) : aucun décalage de mise en page', $frames[1] !== []
    && !preg_match('~(?<![-\w])(width|height|top|left|right|bottom|margin|padding)\s*:~', implode('', $frames[1])));
preg_match('~@keyframes eclair-in\{(.*?)\}\}~s', $motion, $reveal);
check('apparition au défilement sans transparence : texte jamais pâli, contraste intact (Lighthouse)', isset($reveal[1]) && str_contains($reveal[1], 'translate')
    && !str_contains($reveal[1], 'opacity'));
check('le haut de page n’est jamais animé (bannière exclue)', !preg_match('~\.hero[^{]*\{[^}]*animation~', $motion));

section('1.11 : suppression, barre du mode Visuel, couleurs et filtres de l’administration');
// Sources du dépôt ou de l'archive (JavaScript allégé, vues compressées) : comparées sans espaces.
$adminDir = ECLAIR_ROOT . '/assets/admin/';
$flat = static fn (string $code): string => (string) preg_replace('~\s+~', '', $code);
$js = implode("\n", array_map(static fn (string $f): string => (string) file_get_contents($f), glob($adminDir . '*.js') ?: []));
check('plus aucune boîte native confirm() ni prompt() : fenêtres de l’administration', $js !== '' && !preg_match('~(?<![\w.])(confirm|prompt)\(\s*t\(~', $js));
$visual = $flat((string) file_get_contents($adminDir . 'visual.js'));
check('mode Visuel : outils en icônes dessinées, aucun caractère qui s’afficherait en petit carré', !preg_match('~[⧉✕＋↑↓]~u', $visual)
    && substr_count($visual, "['del','<path") === 1 && str_contains($visual, "'ev-sep'"));
check('mode Visuel : Suppr supprime le bloc sélectionné, Ctrl/Cmd+Maj+Z rétablit', str_contains($visual, "k==='delete'") && str_contains($visual, "'redo'"));
$editor = $flat((string) file_get_contents($adminDir . 'editor.js'));
$viewFile = ECLAIR_ROOT . '/app/admin/views/editor.php';
$view = is_file($viewFile . '.gz') ? (string) gzdecode((string) file_get_contents($viewFile . '.gz')) : (string) file_get_contents($viewFile);
check('éditeur : boutons annuler et rétablir, historique commun aux deux modes', str_contains($view, 'id="btn-undo"') && str_contains($view, 'id="btn-redo"')
    && str_contains($editor, "op==='redo'") && !str_contains($editor, 'conststack='));
check('éditeur : bloc et élément supprimés s’annulent depuis la notification', str_contains($editor, $flat("t('Bloc supprimé.')")) && str_contains($editor, $flat("t('Élément supprimé.')"))
    && !str_contains($editor, '{row.remove();changed();}'));
$css = (string) file_get_contents($adminDir . 'admin.css');
preg_match('~--warn:oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)~', $css, $warn);
check('« à surveiller » en orange franc, plus en brun (chroma ≥ 0,15)', isset($warn[2]) && (float) $warn[2] >= 0.15, $warn[0] ?? '');
check('filtres des contenus sur une seule ligne (défilement horizontal faute de place)', (bool) preg_match('~\.filters\{[^}]*flex-wrap:nowrap[^}]*overflow-x:auto~', $css));
check('micro-animations de l’administration coupées par le système ou dans Mon profil', str_contains($css, '[data-motion=off] *') && str_contains($css, 'prefers-reduced-motion:reduce'));
