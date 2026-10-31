<?php
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
        'hero' => ['split', 'poster', 'fullbleed', 'editorial', 'centered'],
        'features' => ['cards', 'rows', 'columns'],
        'sections' => ['plain', 'bands'],
        'icons' => ['line', 'none', 'emoji'],
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

    /** Icônes dessinées (tracés dérivés de Lucide, licence ISC) à la place des émojis des blocs « Atouts ». */
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
    ];
    private const EMOJI = [
        'check' => '✅✔☑✓👌', 'rocket' => '🚀', 'users' => '👥👤🤝🧑👨👩👪', 'clock' => '⏱⏰🕐⌛⏳', 'shield' => '🔒🛡🔐', 'star' => '⭐🌟✨💫',
        'heart' => '❤💚💙💛🧡💜🤍💖', 'bulb' => '💡🧠', 'target' => '🎯', 'chat' => '💬🗣', 'phone' => '📞☎📱', 'pin' => '📍🗺🧭', 'truck' => '🚚📦🚛',
        'wrench' => '🛠🔧🔨⚙🪛', 'leaf' => '🌱🌿🍃🌳🌻🌸', 'zap' => '⚡🔥', 'card' => '💶💰💸💳💵🏷', 'calendar' => '📅🗓', 'chart' => '📈📊',
        'globe' => '🌍🌎🌏🌐', 'home' => '🏠🏡🏢', 'book' => '📚📖🎓✏📝', 'camera' => '📷📸🎬', 'food' => '🍽🍴☕🍷🥐🍰', 'sun' => '☀🌞', 'mail' => '✉📧📩',
        'award' => '🏆🥇🏅🎖', 'smile' => '😊🙂😀😄', 'palette' => '🎨🖌🏺',
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
        foreach (['ink' => 7, 'muted' => 4.5] as $k => $ratio) {
            $out[$k] = self::fit(self::fit($out[$k], $out['ground'], $ratio), $out['surface'], 4.5);
        }
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
        $out['link'] = self::fit(self::fit($out['accent'], $out['ground'], 4.5), $out['surface'], 4.5);
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
        foreach (['case', 'hero', 'sections', 'features', 'surfaces', 'icons'] as $k) {
            $css .= $chunks[$k . ':' . $d[$k]] ?? '';
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

    /** Couleur rapprochée du noir ou du blanc (le côté qui contraste avec ce fond) jusqu'au rapport voulu. */
    private static function fit(string $color, string $background, float $ratio): string
    {
        $to = Theme::contrast('#000000', $background) >= Theme::contrast('#ffffff', $background) ? '#000000' : '#ffffff';
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
