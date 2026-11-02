<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Directions artistiques : un univers visuel complet (palette, police de titre, échelle, formes, compositions)
 * au lieu d'un simple habillage. Méthode et garde-fous adaptés d'Impeccable (Paul Bakaus, licence Apache 2.0) :
 * des choix francs tirés du monde du client, jamais les tics du « design générique ».
 *
 * Une direction, livrée ou proposée par l'IA, n'est qu'une liste de valeurs permises, jamais du CSS : tout est
 * validé ici, et les contrastes sont garantis (WCAG) quoi qu'on lui donne. Sans direction, le site garde son
 * thème classique, à l'octet près.
 */
final class Design
{
    /**
     * Polices de titre : Fontsource (Google Fonts, licence SIL OFL 1.1), une graisse, jeu latin (12 à 27 Ko).
     * Téléchargées une fois par le serveur, puis servies par le site : aucune requête vers un tiers pour les visiteurs.
     * Absentes : Inter, Roboto, Fraunces, Space Grotesk, Instrument… (les polices surutilisées relevées par Impeccable).
     */
    public const FONTS = [
        'bodoni-moda' => ['Bodoni Moda', 700, 'serif', 'didone de haute couture : mode, luxe, magazine'],
        'cormorant-garamond' => ['Cormorant Garamond', 600, 'serif', 'garalde élancée : bien-être, mariage, poésie'],
        'dm-serif-display' => ['DM Serif Display', 400, 'serif', 'serif chaleureuse et dense : restaurant, éditorial'],
        'young-serif' => ['Young Serif', 400, 'serif', 'serif ronde à caractère : artisanat, boulangerie, épicerie'],
        'gloock' => ['Gloock', 400, 'serif', 'serif tranchante : culture, architecture, galerie'],
        'libre-caslon-display' => ['Libre Caslon Display', 400, 'serif', 'Caslon classique : droit, librairie, institution'],
        'newsreader' => ['Newsreader', 600, 'serif', 'serif de journal : presse, analyse, blog'],
        'zilla-slab' => ['Zilla Slab', 700, 'slab', 'égyptienne : atelier, garage, menuiserie'],
        'bricolage-grotesque' => ['Bricolage Grotesque', 700, 'sans', 'grotesque espiègle : créatif, agence, événement'],
        'schibsted-grotesk' => ['Schibsted Grotesk', 700, 'sans', 'grotesque nette : conseil, technologie, médias'],
        'familjen-grotesk' => ['Familjen Grotesk', 700, 'sans', 'grotesque scandinave : design, architecture'],
        'archivo-black' => ['Archivo Black', 400, 'sans', 'affiche suisse : sport, culture, manifeste'],
        'big-shoulders-display' => ['Big Shoulders Display', 800, 'condensed', 'condensée industrielle : nuit, musique, chantier'],
        'barlow-condensed' => ['Barlow Condensed', 700, 'condensed', 'condensée de signalétique : transport, sport, terrain'],
        'syne' => ['Syne', 700, 'sans', 'expressive : studio, art, mode'],
        'unbounded' => ['Unbounded', 700, 'sans', 'large et ronde : technologie, jeu, jeunesse'],
        'fredoka' => ['Fredoka', 600, 'rounded', 'arrondie : enfants, crèche, loisirs'],
        'sora' => ['Sora', 700, 'sans', 'géométrique : jeune entreprise, finance'],
    ];
    private const FALLBACK = [
        'serif' => 'Georgia,"Times New Roman",serif',
        'slab' => 'Rockwell,"Roboto Slab",Georgia,serif',
        'sans' => '"Helvetica Neue",Arial,sans-serif',
        'condensed' => '"Arial Narrow","Roboto Condensed",sans-serif',
        'rounded' => 'ui-rounded,"Arial Rounded MT",sans-serif',
    ];
    /** Polices du texte : celles du système (zéro téléchargement). */
    public const TEXT = ['system', 'humanist', 'geometric', 'rounded', 'serif', 'oldstyle'];
    /** Choix permis, la première valeur est celle par défaut. */
    public const CHOICES = [
        'case' => ['normal', 'upper'],
        'scale' => ['regular', 'quiet', 'bold'],
        'radius' => ['soft', 'none', 'round'],
        'density' => ['regular', 'compact', 'airy'],
        'surfaces' => ['line', 'flat', 'raised'],
        'hero' => ['split', 'poster', 'fullbleed', 'editorial', 'centered', 'reverse', 'framed'],
        'features' => ['cards', 'rows', 'columns', 'numbers'],
        'sections' => ['plain', 'bands', 'rules'],
        'icons' => ['line', 'none', 'emoji'],
        'buttons' => ['solid', 'outline', 'underline'],
    ];

    /** Directions livrées : couleurs, police de titre et compositions, pensées pour un univers chacune. */
    public const DIRECTIONS = [
        'nocturne' => ['name' => 'Nocturne', 'idea' => 'La ville la nuit : bleu profond, lumière ambrée, titres condensés.',
            'ground' => '#0f1724', 'surface' => '#182235', 'ink' => '#f3eee4', 'muted' => '#b3b9c6', 'accent' => '#f2a93b', 'display' => 'big-shoulders-display', 'text' => 'humanist',
            'case' => 'upper', 'scale' => 'bold', 'radius' => 'none', 'density' => 'airy', 'surfaces' => 'flat', 'hero' => 'fullbleed', 'features' => 'rows', 'sections' => 'bands', 'icons' => 'line'],
        'galerie' => ['name' => 'Galerie', 'idea' => 'Murs blancs, cartels noirs, un rouge de signalétique : l’œuvre d’abord.',
            'ground' => '#ffffff', 'surface' => '#f2f2f0', 'ink' => '#111111', 'muted' => '#5c5c5c', 'accent' => '#d7261e', 'display' => 'familjen-grotesk', 'text' => 'system',
            'case' => 'normal', 'scale' => 'bold', 'radius' => 'none', 'density' => 'airy', 'surfaces' => 'line', 'hero' => 'poster', 'features' => 'columns', 'sections' => 'plain', 'icons' => 'none'],
        'argile' => ['name' => 'Argile', 'idea' => 'L’atelier de céramique : sable, terre cuite, serif ronde et formes douces.',
            'ground' => '#ebe0d2', 'surface' => '#dfd0bd', 'ink' => '#2b1c13', 'muted' => '#5f4a3b', 'accent' => '#a8431f', 'display' => 'young-serif', 'text' => 'humanist',
            'case' => 'normal', 'scale' => 'regular', 'radius' => 'soft', 'density' => 'regular', 'surfaces' => 'raised', 'hero' => 'editorial', 'features' => 'rows', 'sections' => 'bands', 'icons' => 'line'],
        'botanique' => ['name' => 'Botanique', 'idea' => 'L’herbier et la serre : vert profond, papier sauge, serif généreuse.',
            'ground' => '#f3f5ee', 'surface' => '#e2e9d9', 'ink' => '#14281c', 'muted' => '#4b5c4f', 'accent' => '#2f6b3a', 'display' => 'dm-serif-display', 'text' => 'serif',
            'case' => 'normal', 'scale' => 'regular', 'radius' => 'soft', 'density' => 'airy', 'surfaces' => 'flat', 'hero' => 'centered', 'features' => 'columns', 'sections' => 'bands', 'icons' => 'line'],
        'affiche' => ['name' => 'Affiche', 'idea' => 'L’affiche suisse : noir sur blanc cassé, un orange vif, des titres en capitales.',
            'ground' => '#f3f1ec', 'surface' => '#e6e3dc', 'ink' => '#0d0d0d', 'muted' => '#4a4a4a', 'accent' => '#e04a00', 'display' => 'archivo-black', 'text' => 'system',
            'case' => 'upper', 'scale' => 'bold', 'radius' => 'none', 'density' => 'regular', 'surfaces' => 'flat', 'hero' => 'poster', 'features' => 'columns', 'sections' => 'plain', 'icons' => 'none'],
        'riviera' => ['name' => 'Riviera', 'idea' => 'Le bord de mer : bleu marine, ciel pâle, corail du soleil couchant.',
            'ground' => '#f4f8fb', 'surface' => '#e1ecf5', 'ink' => '#0b2440', 'muted' => '#4b6178', 'accent' => '#b04b36', 'display' => 'gloock', 'text' => 'humanist',
            'case' => 'normal', 'scale' => 'bold', 'radius' => 'round', 'density' => 'airy', 'surfaces' => 'raised', 'hero' => 'fullbleed', 'features' => 'rows', 'sections' => 'bands', 'icons' => 'line'],
        'cabinet' => ['name' => 'Cabinet', 'idea' => 'Le papier à en-tête : encre marine, bordeaux, Caslon et filets fins.',
            'ground' => '#fbfaf7', 'surface' => '#efebe3', 'ink' => '#1a1f2b', 'muted' => '#535a68', 'accent' => '#7a1f2b', 'display' => 'libre-caslon-display', 'text' => 'serif',
            'case' => 'normal', 'scale' => 'quiet', 'radius' => 'none', 'density' => 'regular', 'surfaces' => 'line', 'hero' => 'editorial', 'features' => 'columns', 'sections' => 'plain', 'icons' => 'none'],
        'neon' => ['name' => 'Néon', 'idea' => 'L’écran de création la nuit : quasi-noir, vert citron, titres larges.',
            'ground' => '#111114', 'surface' => '#1b1b21', 'ink' => '#f4f4f0', 'muted' => '#a9a9b3', 'accent' => '#c8ff2e', 'display' => 'unbounded', 'text' => 'system',
            'case' => 'normal', 'scale' => 'bold', 'radius' => 'soft', 'density' => 'airy', 'surfaces' => 'line', 'hero' => 'poster', 'features' => 'rows', 'sections' => 'plain', 'icons' => 'line'],
        'douceur' => ['name' => 'Douceur', 'idea' => 'Le cabinet de soin : poudre rosée, prune, garalde élancée et beaucoup d’air.',
            'ground' => '#f8f1ee', 'surface' => '#efe1db', 'ink' => '#34202a', 'muted' => '#6b5360', 'accent' => '#9c3f5f', 'display' => 'cormorant-garamond', 'text' => 'humanist',
            'case' => 'normal', 'scale' => 'regular', 'radius' => 'round', 'density' => 'airy', 'surfaces' => 'flat', 'hero' => 'centered', 'features' => 'columns', 'sections' => 'bands', 'icons' => 'line'],
        'chantier' => ['name' => 'Chantier', 'idea' => 'Béton, jaune de sécurité et signalétique condensée : du solide.',
            'ground' => '#e9e8e4', 'surface' => '#d8d6d0', 'ink' => '#1b1b19', 'muted' => '#4e4d49', 'accent' => '#ffcc00', 'display' => 'barlow-condensed', 'text' => 'system',
            'case' => 'upper', 'scale' => 'bold', 'radius' => 'none', 'density' => 'compact', 'surfaces' => 'line', 'hero' => 'fullbleed', 'features' => 'rows', 'sections' => 'bands', 'icons' => 'line'],
        'recreation' => ['name' => 'Récréation', 'idea' => 'La cour d’école : bleu ciel, rouge tomate, lettres rondes et joyeuses.',
            'ground' => '#f2f7ff', 'surface' => '#dde9ff', 'ink' => '#1d2433', 'muted' => '#4e5871', 'accent' => '#c7362b', 'display' => 'fredoka', 'text' => 'rounded',
            'case' => 'normal', 'scale' => 'bold', 'radius' => 'round', 'density' => 'regular', 'surfaces' => 'raised', 'hero' => 'split', 'features' => 'columns', 'sections' => 'bands', 'icons' => 'line'],
        'presse' => ['name' => 'Presse', 'idea' => 'Le quotidien du matin : papier journal, noir d’imprimerie, rouge de une.',
            'ground' => '#f7f6f2', 'surface' => '#eceae3', 'ink' => '#121212', 'muted' => '#555555', 'accent' => '#c1121f', 'display' => 'newsreader', 'text' => 'serif',
            'case' => 'normal', 'scale' => 'regular', 'radius' => 'none', 'density' => 'compact', 'surfaces' => 'line', 'hero' => 'editorial', 'features' => 'columns', 'sections' => 'plain', 'icons' => 'none'],
    ];

    /** Icônes dessinées (tracés dérivés de Lucide, licence ISC) à la place des émojis des blocs « Atouts » : 65 icônes. */
    private const ICONS = [
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'rocket' => '<path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2.1-.1-2.9a2.2 2.2 0 0 0-2.9-.1z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.9A12.9 12.9 0 0 1 22 2c0 2.7-.8 7.5-6 11a22.4 22.4 0 0 1-4 2z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'heart' => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>',
        'bulb' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5M9 18h6M10 22h4"/>',
        'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'chat' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'pin' => '<path d="M20 10c0 5-5.5 10.2-7.4 11.8a1 1 0 0 1-1.2 0C9.5 20.2 4 15 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2m10 0H9m10 0h2a1 1 0 0 0 1-1v-3.7a1 1 0 0 0-.2-.6l-3.5-4.4A1 1 0 0 0 17.5 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z"/>',
        'leaf' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10ZM2 21c0-3 1.9-5.4 5.1-6"/>',
        'zap' => '<path d="M4 14a1 1 0 0 1-.8-1.6l9.9-10.2a.5.5 0 0 1 .9.5l-1.9 6A1 1 0 0 0 13 10h7a1 1 0 0 1 .8 1.6l-9.9 10.2a.5.5 0 0 1-.9-.5l1.9-6A1 1 0 0 0 11 14z"/>',
        'card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20M2 12h20"/>',
        'home' => '<path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/>',
        'food' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20m14-7V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/>',
        'smile' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
        'palette' => '<path d="M12 22a10 10 0 1 1 10-10c0 2.8-2.2 4-4 4h-2a2 2 0 0 0-1.5 3.3A1.6 1.6 0 0 1 12 22z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="11" cy="7.5" r="1"/><circle cx="16" cy="9" r="1"/>',
        'baby' => '<path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/><path d="M15 12h.01"/><path d="M19.4 6.8A9 9 0 0 1 20.8 10.2a2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1"/><path d="M9 12h.01"/>',
        'bike' => '<circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>',
        'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
        'building-2' => '<path d="M10 12h4"/><path d="M10 8h4"/><path d="M14 21v-3a2 2 0 0 0-4 0v3"/><path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/>',
        'cake' => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 8v3"/><path d="M17 8v3"/><path d="M7 4h.01"/><path d="M12 4h.01"/><path d="M17 4h.01"/>',
        'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
        'carrot' => '<path d="M15 16a1 1 0 0 0-7-7q-4 4-6 12.4a.5.5 0 0 0 .6.6Q11 20 15 16l-3-3"/><path d="M15 9q4 4 7 0-3-4-7 0 4-4 0-7-4 3 0 7"/><path d="m8 15-2.6-2.6"/>',
        'cloud' => '<path d="M17.5 19H9a7 7 0 1 1 6.7-9h1.8a4.5 4.5 0 1 1 0 9Z"/>',
        'coffee' => '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/>',
        'droplet' => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
        'dumbbell' => '<path d="M17.6 12.8a2 2 0 1 0 2.8-2.8l-1.8-1.8a2 2 0 0 0 2.8-2.8l-2.8-2.8a2 2 0 0 0-2.8 2.8l-1.8-1.8a2 2 0 1 0-2.8 2.8z"/><path d="m2.5 21.5 1.4-1.4"/><path d="m20.1 3.9 1.4-1.4"/><path d="M5.3 21.5a2 2 0 1 0 2.8-2.8l1.8 1.8a2 2 0 1 0 2.8-2.8l-6.4-6.4a2 2 0 1 0-2.8 2.8l1.8 1.8a2 2 0 0 0-2.8 2.8z"/><path d="m9.6 14.4 4.8-4.8"/>',
        'fish' => '<path d="M6.5 12c.9-3.5 4.9-6 8.5-6 3.6 0 6.1 2.5 7 6-.9 3.5-3.4 6-7 6s-7.6-2.5-8.5-6Z"/><path d="M18 12v.5"/><path d="M16 17.9a9.8 9.8 0 0 1 0-11.9"/><path d="M7 10.7C7 8 5.6 6 2.7 5.5c-1 1.5-1 5 .2 6.5-1.2 1.5-1.2 5-.2 6.5C5.6 18 7 16 7 13.3"/><path d="M10.5 7.3C10.2 5.9 9.2 4.2 8 3h5.8a2 2 0 0 1 2 1.7l.2 1.4"/><path d="m16 17.9-.2 1.4A2 2 0 0 1 13.8 21H9.5a6 6 0 0 0 1.5-4"/>',
        'flame' => '<path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4"/>',
        'flower' => '<circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/><path d="M12 7.5V9"/><path d="M7.5 12H9"/><path d="M16.5 12H15"/><path d="M12 16.5V15"/><path d="m8 8 1.9 1.9"/><path d="M14.1 9.9 16 8"/><path d="m8 16 1.9-1.9"/><path d="M14.1 14.1 16 16"/>',
        'gem' => '<path d="M10.5 3 8 9l4 13 4-13-2.5-6"/><path d="M17 3a2 2 0 0 1 1.6.8l3 4a2 2 0 0 1 .01 2.4l-8 11a2 2 0 0 1-3.2 0l-8-11A2 2 0 0 1 2.4 7.8l3-4A2 2 0 0 1 7 3z"/><path d="M2 9h20"/>',
        'gift' => '<path d="M12 7v14"/><path d="M20 11v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8"/><path d="M7.5 7a1 1 0 0 1 0-5A4.8 8 0 0 1 12 7a4.8 8 0 0 1 4.5-5 1 1 0 0 1 0 5"/><rect x="3" y="7" width="18" height="4" rx="1"/>',
        'graduation-cap' => '<path d="M21.4 10.9a1 1 0 0 0-.02-1.8L12.8 5.2a2 2 0 0 0-1.7 0L2.6 9.1a1 1 0 0 0 0 1.8l8.6 3.9a2 2 0 0 0 1.7 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
        'handshake' => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.9-3.9a3 3 0 0 0-4.2 0l-.9.9a1 1 0 1 1-3-3l2.8-2.8a5.8 5.8 0 0 1 7.1-.9l.5.3a2 2 0 0 0 1.4.2L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
        'key' => '<path d="m2 21 9.6-9.6"/><path d="m7.5 15.5 2.3 2.3a1 1 0 0 1 0 1.4l-2.1 2.1a1 1 0 0 1-1.4 0L4 19"/><circle cx="15.5" cy="7.5" r="5.5"/>',
        'laptop' => '<path d="M18 5a2 2 0 0 1 2 2v8.5a2 2 0 0 0 .2.9l1.1 2.1a1 1 0 0 1-.9 1.4H3.6a1 1 0 0 1-.9-1.4l1.1-2.1A2 2 0 0 0 4 15.5V7a2 2 0 0 1 2-2z"/><path d="M20.1 16H3.9"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'megaphone' => '<path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/>',
        'mountain' => '<path d="m8 3 4 8 5-5 5 15H2L8 3z"/>',
        'music' => '<path d="M21 16V3a1 1 0 0 0-1.3-1l-10 2.9a1 1 0 0 0-.71V19"/><circle cx="18" cy="16" r="3"/><circle cx="6" cy="19" r="3"/>',
        'package' => '<path d="M11 21.7a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7z"/><path d="M12 22V12"/><polyline points="3.3 7 12 12 20.7 7"/><path d="m7.5 4.3 9 5.2"/>',
        'paw-print' => '<circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 0 1 5 5v3.5a3.5 3.5 0 0 1-6.8 1Q6.5 17.5 4.5 16.8A3.5 3.5 0 0 1 5.5 10Z"/>',
        'plane' => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
        'recycle' => '<path d="M7 19H4.8a1.8 1.8 0 0 1-1.6-.9 1.8 1.8 0 0 10-1.8L7.2 9.5"/><path d="M11 19h8.2a1.8 1.8 0 0 0 1.6-.9 1.8 1.8 0 0 0 0-1.8l-1.2-2.1"/><path d="m14 16-3 3 3 3"/><path d="M8.3 13.6 7.2 9.5 3.1 10.6"/><path d="m9.3 5.8 1.1-1.9A1.8 1.8 0 0 1 12 3a1.8 1.8 0 0 1 1.5.9l3.9 6.8"/><path d="m13.4 9.6 4.1 1.1 1.1-4.1"/>',
        'scale' => '<path d="M12 3v18"/><path d="m19 8 3 8a5 5 0 0 1-6 0zV7"/><path d="M3 7h1a17 17 0 0 0 8-2 17 17 0 0 0 8 2h1"/><path d="m5 8 3 8a5 5 0 0 1-6 0zV7"/><path d="M7 21h10"/>',
        'scissors' => '<circle cx="6" cy="6" r="3"/><path d="M8.1 8.1 12 12"/><path d="M20 4 8.1 15.9"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/>',
        'shirt' => '<path d="M20.4 3.5 16 2a4 4 0 0 1-8 0L3.6 3.5a2 2 0 0 0-1.3 2.2l.6 3.5a1 1 0 0 0 1.8H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.1a1 1 0 0 0 1-.8l.6-3.5a2 2 0 0 0-1.3-2.2z"/>',
        'shopping-bag' => '<path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.1 6h17.8"/><path d="M3.4 5.5a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.7a2 2 0 0 0-.4-1.2l-2-2.7A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/>',
        'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
        'stethoscope' => '<path d="M11 2v2"/><path d="M5 2v2"/><path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/>',
        'waves' => '<path d="M2 12q2.5 2 5 0t5 0 5 0 5 0"/><path d="M2 19q2.5 2 5 0t5 0 5 0 5 0"/><path d="M2 5q2.5 2 5 0t5 0 5 0 5 0"/>',
        'wine' => '<path d="M8 22h8"/><path d="M7 10h10"/><path d="M12 15v7"/><path d="M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5Z"/>',
    ];
    private const EMOJI = [
        'check' => '✅✔☑✓👌', 'rocket' => '🚀', 'users' => '👥👤🧑👨👩👪', 'clock' => '⏱⏰🕐⌛⏳', 'shield' => '🛡', 'star' => '⭐🌟✨💫',
        'heart' => '❤💚💙💛🧡💜🤍💖', 'bulb' => '💡🧠', 'target' => '🎯', 'chat' => '💬🗣', 'phone' => '📞☎', 'pin' => '📍🗺🧭', 'truck' => '🚚🚛',
        'wrench' => '🛠🔧🔨⚙🪛', 'leaf' => '🌱🌿🍃🌳', 'zap' => '⚡', 'card' => '💶💰💸💳💵🏷', 'calendar' => '📅🗓', 'chart' => '📈📊',
        'globe' => '🌍🌎🌏🌐', 'home' => '🏠🏡', 'book' => '📚📖✏📝', 'camera' => '📷📸🎬', 'food' => '🍽🍴🥐', 'sun' => '☀🌞', 'mail' => '✉📧📩',
        'award' => '🏆🥇🏅🎖', 'smile' => '😊🙂😀😄', 'palette' => '🎨🖌🏺',
        'gift' => '🎁🎀', 'music' => '🎵🎶🎸🎹🎻🎷', 'coffee' => '☕🍵', 'car' => '🚗🚙🚕🚘🏎', 'plane' => '✈🛫🛬🧳', 'bike' => '🚲🚴🛵',
        'paw-print' => '🐾🐶🐱🐕🐈', 'baby' => '👶🍼🧸', 'scissors' => '✂💇💈', 'dumbbell' => '🏋💪🤸🧘', 'graduation-cap' => '🎓🏫', 'briefcase' => '💼👔',
        'building-2' => '🏢🏬🏛', 'key' => '🔑🗝', 'lock' => '🔒🔐', 'cloud' => '☁🌥', 'recycle' => '♻', 'flower' => '🌸🌺🌷💐🌻🌼', 'wine' => '🍷🥂🍾',
        'cake' => '🎂🍰🧁', 'stethoscope' => '🩺⚕🏥💊', 'shirt' => '👕👗👚🧥', 'shopping-bag' => '🛍🛒', 'package' => '📦', 'mountain' => '⛰🏔🗻',
        'waves' => '🌊🏄🏊', 'handshake' => '🤝', 'laptop' => '💻🖥⌨', 'smartphone' => '📱📲', 'megaphone' => '📣📢', 'scale' => '⚖', 'gem' => '💎💍',
        'flame' => '🔥', 'droplet' => '💧💦🚿', 'carrot' => '🥕🥗🥦🍅', 'fish' => '🐟🐠🎣🦐',
    ];

    private static array $files = [];

    /** Direction prête à l'emploi : valeurs permises seulement, contrastes garantis ; null si elle est inutilisable. */
    public static function normalize(mixed $d): ?array
    {
        if (!is_array($d)) {
            return null;
        }
        $out = ['id' => substr((string) preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($d['id'] ?? ''))), 0, 30) ?: 'custom',
            'name' => Sanitize::text($d['name'] ?? '', 40), 'idea' => Sanitize::text($d['idea'] ?? '', 200)];
        foreach (['ground', 'surface', 'ink', 'muted', 'accent'] as $k) {
            if (!is_string($d[$k] ?? null) || !preg_match('/^#[0-9a-f]{6}$/i', $d[$k])) {
                return null;
            }
            $out[$k] = strtolower($d[$k]);
        }
        $display = (string) ($d['display'] ?? '');
        $out['display'] = isset(self::FONTS[$display]) || in_array($display, self::TEXT, true) ? $display : 'system';
        $out['text'] = in_array($d['text'] ?? '', self::TEXT, true) ? $d['text'] : 'system';
        foreach (self::CHOICES as $k => $allowed) {
            $out[$k] = in_array($d[$k] ?? '', $allowed, true) ? $d[$k] : $allowed[0];
        }
        // Fond franchement clair ou sombre (un gris moyen ne peut porter aucun texte à 7:1), bandes du même côté,
        // puis contrastes WCAG garantis quelles que soient les couleurs reçues : texte ≥ 7:1, le reste ≥ 4,5:1, partout.
        $dark = Theme::contrast('#ffffff', $out['ground']) > Theme::contrast('#000000', $out['ground']);
        $out['ground'] = self::settle($out['ground'], $dark, 7.5);
        if (Theme::contrast('#ffffff', $out['surface']) > Theme::contrast('#000000', $out['surface']) !== $dark) {
            $out['surface'] = self::mix($dark ? '#ffffff' : '#000000', $out['ground'], 0.06);
        }
        $out['surface'] = self::settle($out['surface'], $dark, 6);
        foreach (['#ffffff', '#111111'] as $text) {
            if (Theme::contrast($out['accent'], $text) >= 4.5) {
                $out['on_accent'] = $text;
                break;
            }
        }
        if (!isset($out['on_accent'])) {
            $out['accent'] = Theme::readable($out['accent'], '#ffffff');
            $out['on_accent'] = '#ffffff';
        }
        // Fonds teintés d'accent du thème (encadré 7 %, succès 9 %, variante 10 %, pastille et menu 13 %, initiales 14 %,
        // surlignage 22 %) : le fond est éclairci (ou assombri) s'il le faut pour qu'ils portent encore du texte, puis chaque
        // couleur de texte est rapprochée du noir (ou du blanc) jusqu'au contraste voulu sur tous ces fonds. Toujours dans le
        // même sens : ça converge. Un mélange peut être plus sombre que ses deux couleurs : chaque niveau est donc vérifié.
        $text = $dark ? '#ffffff' : '#000000';
        $tints = static fn (array $levels): array => array_merge([$out['ground'], $out['surface']], ...array_map(fn ($t) => [self::mix($out['accent'], $out['ground'], $t), self::mix($out['accent'], $out['surface'], $t)], $levels));
        foreach (['ground', 'surface'] as $k) {
            foreach ([0.07, 0.09, 0.1, 0.13, 0.14, 0.22] as $t) {
                for ($i = 0; $i < 60 && Theme::contrast($text, self::mix($out['accent'], $out[$k], $t)) < 4.7; $i++) {
                    $out[$k] = self::mix($dark ? '#000000' : '#ffffff', $out[$k], 0.1);
                }
            }
        }
        foreach (['ink' => [7, [0.07, 0.09, 0.1, 0.13, 0.14, 0.22]], 'muted' => [4.5, [0.07, 0.09, 0.1]], 'link' => [4.5, [0.07, 0.09, 0.1, 0.13, 0.14, 0.22]]] as $k => [$ratio, $levels]) {
            $color = $k === 'link' ? $out['accent'] : $out[$k];
            foreach ($tints($levels) as $i => $bg) {
                $color = self::fit($color, $bg, $i === 0 ? $ratio : ($i === 1 ? 4.5 : 4.55), $dark);
            }
            $out[$k] = $color;
        }
        $out['scheme'] = $dark ? 'dark' : 'light';
        return $out;
    }

    /** Direction livrée (identifiant) ou valeurs reçues de l'aperçu (JSON en base64url). */
    public static function find(string $value): ?array
    {
        if (isset(self::DIRECTIONS[$value])) {
            return self::normalize(['id' => $value] + self::DIRECTIONS[$value]);
        }
        $json = base64_decode(strtr($value, '-_', '+/'), true);
        return $json !== false && strlen($json) < 4000 ? self::normalize(json_decode($json, true)) : null;
    }

    /** Variables, police et morceaux de directions.css utiles à cette direction (le reste n'est jamais envoyé). */
    public static function css(array $d): string
    {
        $num = static fn (string $k, array $map): string => $map[array_search($d[$k], self::CHOICES[$k], true)];
        $web = self::FONTS[$d['display']] ?? null;
        $head = $web ? '"' . $web[0] . '",' . self::FALLBACK[$web[2]] : Theme::FONTS[$d['display']][1];
        $ink = implode(' ', self::rgb($d['ink']));
        $vars = ':root{--bg:' . $d['ground'] . ';--bg-soft:' . $d['surface'] . ';--card:' . ($d['surfaces'] === 'flat' ? $d['surface'] : $d['ground'])
            . ';--text:' . $d['ink'] . ';--muted:' . $d['muted'] . ';--line:' . self::mix($d['ink'], $d['ground'], 0.16) . ';--code:' . self::mix($d['ink'], $d['ground'], 0.07)
            . ';--accent:' . $d['accent'] . ';--on-accent:' . $d['on_accent'] . ';--link:' . $d['link'] . ';--link-dark:' . $d['link'] . ';--link-c:' . $d['link']
            . ';--font:' . Theme::FONTS[$d['text']][1] . ';--font-head:' . $head . ';--hw:' . ($web[1] ?? 700)
            . ';--radius:' . $num('radius', ['10px', '0px', '22px']) . ';--btn-r:' . $num('radius', ['8px', '0px', '999px'])
            . ';--hs:' . $num('scale', ['1', '.88', '1.18']) . ';--dn:' . $num('density', ['1', '.8', '1.3'])
            . ';--shadow:' . ($d['surfaces'] === 'raised' ? '0 1px 2px rgb(' . $ink . ' / .08),0 14px 34px -18px rgb(' . $ink . ' / .38)' : 'none')
            . ';color-scheme:' . $d['scheme'] . '}';
        $file = $web ? self::fontFile($d['display']) : '';
        $face = $file !== '' ? '@font-face{font-family:"' . $web[0] . '";src:url(' . Req::base() . '/media/fonts/' . $file . ') format("woff2");font-weight:' . $web[1] . ';font-display:optional}' : '';
        $chunks = [];
        preg_match_all('~/\*@([a-z]+(?::[a-z]+)?)\*/(.*?)/\*@\*/~s', (string) file_get_contents(ECLAIR_ROOT . '/app/theme/directions.css'), $m, PREG_SET_ORDER);
        foreach ($m as [, $key, $css]) {
            $chunks[$key] = $css;
        }
        $css = $chunks['base'] ?? '';
        foreach (['case', 'hero', 'sections', 'features', 'surfaces', 'icons', 'buttons'] as $k) {
            $css .= $chunks[$k . ':' . $d[$k]] ?? '';
        }
        // Bannière plein cadre : les boutons sans fond restent blancs sur la photo assombrie.
        if ($d['hero'] === 'fullbleed' && $d['buttons'] !== 'solid') {
            $css .= $chunks['over:fullbleed'] ?? '';
        }
        return $face . $vars . $css;
    }

    /** Préchargement de la police de titre (dans <head>) : elle arrive avant le premier affichage. */
    public static function preload(array $d): string
    {
        $file = isset(self::FONTS[$d['display']]) ? self::fontFile($d['display']) : '';
        return $file === '' ? '' : '<link rel="preload" href="' . e(Req::base() . '/media/fonts/' . $file) . '" as="font" type="font/woff2" crossorigin>';
    }

    /** Fichier local de la police (« » tant qu'elle n'est pas téléchargée : le site garde alors sa police de secours). */
    public static function fontFile(string $id): string
    {
        if (!isset(self::FONTS[$id])) {
            return '';
        }
        $name = $id . '-' . self::FONTS[$id][1] . '.woff2';
        return self::$files[$id] ??= is_file(Media::dir() . '/fonts/' . $name) ? $name : '';
    }

    /**
     * Télécharge une police de la liste (une seule fois) : adresse fixe, taille bornée, signature WOFF2 vérifiée,
     * nom de fichier choisi ici. Renvoie false sans rien écrire si quelque chose cloche (pas d'accès sortant…).
     */
    public static function fetchFont(string $id): bool
    {
        if (!isset(self::FONTS[$id])) {
            return false;
        }
        if (self::fontFile($id) !== '') {
            return true;
        }
        $base = defined('ECLAIR_FONTS_URL') ? (string) ECLAIR_FONTS_URL : 'https://cdn.jsdelivr.net/fontsource/fonts';
        $ch = curl_init($base . '/' . $id . '@latest/latin-' . self::FONTS[$id][1] . '-normal.woff2');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (str_starts_with($base, 'http://127.0.0.1') ? CURLPROTO_HTTP : 0), CURLOPT_MAXFILESIZE => 400000]);
        $body = (string) curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && strlen($body) > 1000 && strlen($body) < 400000 && str_starts_with($body, 'wOF2');
        if (!$ok) {
            return false;
        }
        $dir = Media::dir() . '/fonts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            @file_put_contents($dir . '/index.html', '');
        }
        $name = $id . '-' . self::FONTS[$id][1] . '.woff2';
        $tmp = $dir . '/.' . $name . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $body) !== strlen($body) || !@rename($tmp, $dir . '/' . $name)) {
            @unlink($tmp);
            return false;
        }
        self::$files[$id] = $name;
        return true;
    }

    /** Icône dessinée correspondant à l'émoji d'un atout (« » si aucune ne convient : rien n'est affiché). */
    public static function icon(string $emoji): string
    {
        $emoji = str_replace(["\u{FE0F}", "\u{200D}"], '', trim($emoji));
        if ($emoji === '') {
            return '';
        }
        foreach (self::EMOJI as $name => $list) {
            if (str_contains($list, mb_substr($emoji, 0, 1))) {
                return '<svg viewBox="0 0 24 24" aria-hidden="true">' . self::ICONS[$name] . '</svg>';
            }
        }
        return '';
    }

    /* ─────────────────────────────── Couleurs ─────────────────────────────── */

    private static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /** Fond poussé vers le blanc (ou le noir) jusqu'à pouvoir porter un texte noir (ou blanc) à ce contraste. */
    private static function settle(string $color, bool $dark, float $ratio): string
    {
        for ($i = 0; $i < 60 && Theme::contrast($dark ? '#ffffff' : '#000000', $color) < $ratio; $i++) {
            $color = self::mix($dark ? '#000000' : '#ffffff', $color, 0.1);
        }
        return $color;
    }

    /** Couleur de texte rapprochée du noir (thème clair) ou du blanc (thème sombre) jusqu'au rapport voulu sur ce fond. */
    private static function fit(string $color, string $background, float $ratio, bool $dark): string
    {
        $to = $dark ? '#ffffff' : '#000000';
        for ($i = 0; $i < 40 && Theme::contrast($color, $background) < $ratio; $i++) {
            $color = self::mix($to, $color, 0.12);
        }
        return Theme::contrast($color, $background) >= $ratio ? $color : $to;
    }

    /** Mélange de deux couleurs : $share de la première dans la seconde. */
    private static function mix(string $a, string $b, float $share): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);
        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($x[$i] * $share + $y[$i] * (1 - $share)), [0, 1, 2]));
    }
}
