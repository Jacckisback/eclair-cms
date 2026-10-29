<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Thème du site public : gabarits, CSS en ligne, couleurs accessibles et textes en 6 langues. */
final class Theme
{
    public const FONTS = [
        'system' => ['Système (moderne)', 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'grotesque' => ['Néo-grotesque', 'Inter,Roboto,"Helvetica Neue","Arial Nova","Nimbus Sans",Arial,sans-serif'],
        'humanist' => ['Humaniste', 'Seravek,"Gill Sans Nova",Ubuntu,Calibri,"DejaVu Sans",source-sans-pro,sans-serif'],
        'geometric' => ['Géométrique', 'Avenir,Montserrat,Corbel,"URW Gothic",source-sans-pro,sans-serif'],
        'rounded' => ['Arrondie', 'ui-rounded,"Hiragino Maru Gothic ProN",Quicksand,Comfortaa,Manjari,"Arial Rounded MT","Arial Rounded MT Bold",Calibri,source-sans-pro,sans-serif'],
        'serif' => ['Serif moderne', 'Charter,"Bitstream Charter","Sitka Text",Cambria,serif'],
        'oldstyle' => ['Serif élégante', '"Iowan Old Style","Palatino Linotype","URW Palladio L",P052,serif'],
        'mono' => ['Machine à écrire', 'ui-monospace,"Cascadia Code","Source Code Pro",Menlo,Consolas,"DejaVu Sans Mono",monospace'],
    ];

    private const LANG_ORDER = ['fr', 'es', 'en', 'de', 'it', 'pt'];
    private const STRINGS = [
        'skip' => ['Aller au contenu', 'Ir al contenido', 'Skip to content', 'Zum Inhalt springen', 'Vai al contenuto', 'Ir para o conteúdo'],
        'menu' => ['Menu principal', 'Menú principal', 'Main menu', 'Hauptmenü', 'Menu principale', 'Menu principal'],
        'footer_menu' => ['Liens utiles', 'Enlaces útiles', 'Useful links', 'Nützliche Links', 'Link utili', 'Links úteis'],
        'breadcrumb' => ['Fil d’Ariane', 'Ruta de navegación', 'Breadcrumb', 'Brotkrümelnavigation', 'Percorso', 'Navegação estrutural'],
        'published_on' => ['Publié le', 'Publicado el', 'Published', 'Veröffentlicht am', 'Pubblicato il', 'Publicado em'],
        'updated_on' => ['Mis à jour le', 'Actualizado el', 'Updated', 'Aktualisiert am', 'Aggiornato il', 'Atualizado em'],
        'by' => ['Par', 'Por', 'By', 'Von', 'Di', 'Por'],
        'reading' => ['{n} min de lecture', '{n} min de lectura', '{n} min read', '{n} Min. Lesezeit', '{n} min di lettura', '{n} min de leitura'],
        'toc' => ['Sommaire', 'Índice', 'Contents', 'Inhalt', 'Indice', 'Índice'],
        'key_points' => ['L’essentiel', 'Lo esencial', 'Key takeaways', 'Das Wichtigste', 'In breve', 'O essencial'],
        'faq' => ['Questions fréquentes', 'Preguntas frecuentes', 'Frequently asked questions', 'Häufige Fragen', 'Domande frequenti', 'Perguntas frequentes'],
        'team' => ['Notre équipe', 'Nuestro equipo', 'Our team', 'Unser Team', 'Il nostro team', 'Nossa equipe'],
        'latest_posts' => ['Derniers articles', 'Últimos artículos', 'Latest posts', 'Neueste Beiträge', 'Ultimi articoli', 'Últimos artigos'],
        'all_posts' => ['Voir tous les articles', 'Ver todos los artículos', 'See all posts', 'Alle Beiträge ansehen', 'Vedi tutti gli articoli', 'Ver todos os artigos'],
        'related' => ['À lire aussi', 'También te puede interesar', 'Read next', 'Auch lesenswert', 'Leggi anche', 'Leia também'],
        'about_author' => ['À propos de l’auteur', 'Sobre el autor', 'About the author', 'Über den Autor', 'Sull’autore', 'Sobre o autor'],
        'posts_by' => ['Articles de {name}', 'Artículos de {name}', 'Posts by {name}', 'Beiträge von {name}', 'Articoli di {name}', 'Artigos de {name}'],
        'search' => ['Rechercher', 'Buscar', 'Search', 'Suchen', 'Cerca', 'Pesquisar'],
        'search_label' => ['Rechercher sur le site', 'Buscar en el sitio', 'Search this site', 'Website durchsuchen', 'Cerca nel sito', 'Pesquisar no site'],
        'search_results' => ['{n} résultat(s) pour « {q} »', '{n} resultado(s) para «{q}»', '{n} result(s) for “{q}”', '{n} Ergebnis(se) für „{q}“', '{n} risultato/i per «{q}»', '{n} resultado(s) para «{q}»'],
        'no_results' => ['Aucun résultat. Essayez d’autres mots-clés.', 'Sin resultados. Prueba con otras palabras.', 'No results. Try other keywords.', 'Keine Ergebnisse. Versuchen Sie andere Suchbegriffe.', 'Nessun risultato. Prova con altre parole.', 'Nenhum resultado. Tente outras palavras.'],
        'not_found' => ['Page introuvable', 'Página no encontrada', 'Page not found', 'Seite nicht gefunden', 'Pagina non trovata', 'Página não encontrada'],
        'not_found_text' => ['Cette page n’existe pas ou a été déplacée. Une recherche ou le menu vous aideront à retrouver votre chemin.', 'Esta página no existe o se ha movido. Usa el buscador o el menú para encontrar lo que buscas.', 'This page doesn’t exist or has moved. Try searching or use the menu.', 'Diese Seite existiert nicht oder wurde verschoben. Nutzen Sie die Suche oder das Menü.', 'Questa pagina non esiste o è stata spostata. Usa la ricerca o il menu.', 'Esta página não existe ou foi movida. Use a pesquisa ou o menu.'],
        'back_home' => ['Retour à l’accueil du site', 'Volver a la página principal', 'Back to the homepage', 'Zur Startseite', 'Torna alla pagina principale', 'Voltar à página inicial'],
        'page_n' => ['Page {n}', 'Página {n}', 'Page {n}', 'Seite {n}', 'Pagina {n}', 'Página {n}'],
        'newer' => ['Articles plus récents', 'Artículos más recientes', 'Newer posts', 'Neuere Beiträge', 'Articoli più recenti', 'Artigos mais recentes'],
        'older' => ['Articles plus anciens', 'Artículos anteriores', 'Older posts', 'Ältere Beiträge', 'Articoli meno recenti', 'Artigos anteriores'],
        'pagination' => ['Pagination', 'Paginación', 'Pagination', 'Seitennavigation', 'Paginazione', 'Paginação'],
        'powered' => ['Propulsé par', 'Creado con', 'Powered by', 'Erstellt mit', 'Realizzato con', 'Feito com'],
        'callout_info' => ['Bon à savoir', 'Bueno saberlo', 'Good to know', 'Gut zu wissen', 'Buono a sapersi', 'Bom saber'],
        'callout_tip' => ['Astuce', 'Consejo', 'Tip', 'Tipp', 'Consiglio', 'Dica'],
        'callout_warning' => ['Attention', 'Atención', 'Warning', 'Achtung', 'Attenzione', 'Atenção'],
        'callout_success' => ['À retenir', 'Para recordar', 'Remember', 'Merken', 'Da ricordare', 'Para lembrar'],
        'callout_note' => ['Note', 'Nota', 'Note', 'Hinweis', 'Nota', 'Nota'],
        'video' => ['Vidéo', 'Vídeo', 'Video', 'Video', 'Video', 'Vídeo'],
        'play_video' => ['Lire la vidéo', 'Ver el vídeo', 'Play video', 'Video abspielen', 'Guarda il video', 'Ver o vídeo'],
        'form_name' => ['Nom', 'Nombre', 'Name', 'Name', 'Nome', 'Nome'],
        'form_email' => ['E-mail', 'Correo electrónico', 'Email', 'E-Mail', 'E-mail', 'E-mail'],
        'form_message' => ['Message', 'Mensaje', 'Message', 'Nachricht', 'Messaggio', 'Mensagem'],
        'form_send' => ['Envoyer', 'Enviar', 'Send', 'Senden', 'Invia', 'Enviar'],
        'form_sending' => ['Envoi…', 'Enviando…', 'Sending…', 'Wird gesendet…', 'Invio…', 'A enviar…'],
        'form_thanks' => ['Merci ! Votre message a bien été envoyé.', '¡Gracias! Tu mensaje se ha enviado.', 'Thank you! Your message has been sent.', 'Danke! Ihre Nachricht wurde gesendet.', 'Grazie! Il tuo messaggio è stato inviato.', 'Obrigado! A sua mensagem foi enviada.'],
        'form_error' => ['L’envoi a échoué. Vérifiez les champs et réessayez.', 'No se pudo enviar. Revisa los campos e inténtalo de nuevo.', 'Sending failed. Check the fields and try again.', 'Senden fehlgeschlagen. Bitte prüfen und erneut versuchen.', 'Invio non riuscito. Controlla i campi e riprova.', 'O envio falhou. Verifique os campos e tente de novo.'],
        'form_notice' => ['Vos données servent uniquement à vous répondre.', 'Tus datos solo se usan para responderte.', 'Your data is only used to reply to you.', 'Ihre Daten werden nur zur Beantwortung verwendet.', 'I tuoi dati servono solo per risponderti.', 'Os seus dados servem apenas para lhe responder.'],
        'privacy' => ['Politique de confidentialité', 'Política de privacidad', 'Privacy policy', 'Datenschutzerklärung', 'Informativa sulla privacy', 'Política de privacidade'],
        'website' => ['Site', 'Sitio web', 'Website', 'Website', 'Sito', 'Site'],
        'contact' => ['Contact', 'Contacto', 'Contact', 'Kontakt', 'Contatto', 'Contacto'],
        'pages' => ['Pages', 'Páginas', 'Pages', 'Seiten', 'Pagine', 'Páginas'],
        'posts' => ['Articles', 'Artículos', 'Posts', 'Beiträge', 'Articoli', 'Artigos'],
        'categories' => ['Catégories', 'Categorías', 'Categories', 'Kategorien', 'Categorie', 'Categorias'],
        'full_content' => ['Tout le contenu en Markdown', 'Todo el contenido en Markdown', 'Full content in Markdown', 'Gesamter Inhalt in Markdown', 'Tutto il contenuto in Markdown', 'Todo o conteúdo em Markdown'],
        'read_in' => ['Lire en', 'Leer en', 'Read in', 'Lesen auf', 'Leggi in', 'Ler em'],
        'edit' => ['Modifier', 'Editar', 'Edit', 'Bearbeiten', 'Modifica', 'Editar'],
        'social' => ['Réseaux sociaux', 'Redes sociales', 'Social media', 'Soziale Medien', 'Social', 'Redes sociais'],
    ];
    private const WORDS = [
        'fr' => ['page' => 'page', 'author' => 'auteur', 'search' => 'recherche'],
        'es' => ['page' => 'pagina', 'author' => 'autor', 'search' => 'buscar'],
        'en' => ['page' => 'page', 'author' => 'author', 'search' => 'search'],
        'de' => ['page' => 'seite', 'author' => 'autor', 'search' => 'suche'],
        'it' => ['page' => 'pagina', 'author' => 'autore', 'search' => 'cerca'],
        'pt' => ['page' => 'pagina', 'author' => 'autor', 'search' => 'pesquisa'],
    ];

    private static ?string $css = null;

    public static function t(string $key, ?string $lang = null, array $vars = []): string
    {
        $lang = $lang ?: (string) Settings::get('lang', 'fr');
        $i = array_search($lang, self::LANG_ORDER, true);
        $text = self::STRINGS[$key][$i === false ? 0 : $i] ?? $key;
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $text;
    }

    /** Mots réservés des URL (pagination, auteur, recherche) dans la langue du site. */
    public static function words(?string $lang = null): array
    {
        return self::WORDS[$lang ?: (string) Settings::get('lang', 'fr')] ?? self::WORDS['fr'];
    }

    public static function authorPath(array $profile): string
    {
        return Content::blogPath() . '/' . self::words()['author'] . '/' . $profile['slug'];
    }

    /* ─────────────────────────────── Rendu ─────────────────────────────── */

    public static function render(string $view, array $v): string
    {
        $v['content'] = self::partial($view, $v);
        return self::typography(self::partial('layout', $v), (string) ($v['lang'] ?? ''));
    }

    /**
     * Typographie française : espace insécable avant « : ; ! ? » et à l'intérieur des guillemets.
     * Seuls les nœuds texte sont modifiés (jamais les balises, scripts, styles ni blocs de code).
     */
    public static function typography(string $html, string $lang): string
    {
        if ($lang !== 'fr') {
            return $html;
        }
        // En cas d'échec (texte démesuré), la page est rendue telle quelle plutôt que vide.
        return preg_replace_callback(
            '~(<(script|style|pre|code|textarea)\b.*?</\2>)|(<[^>]*>)|([^<]+)~si',
            static function (array $m): string {
                if (!isset($m[4]) || $m[4] === '') {
                    return $m[0];
                }
                $text = preg_replace('/[ \x{00A0}]+([:;!?»])/u', "\u{00A0}$1", $m[4]) ?? $m[4];
                return preg_replace('/«[ \x{00A0}]*/u', "«\u{00A0}", $text) ?? $text;
            },
            $html
        ) ?? $html;
    }

    public static function partial(string $name, array $v): string
    {
        ob_start();
        try {
            (static function (string $__file, array $v): void {
                include $__file;
            })(ECLAIR_ROOT . '/app/theme/' . $name . '.php', $v);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Grille de cartes d'articles. */
    public static function cards(array $entries, string $lang, int $level = 3): string
    {
        return self::partial('cards', ['entries' => $entries, 'lang' => $lang, 'level' => $level]);
    }

    /* ─────────────────────────────── Styles ─────────────────────────────── */

    /** CSS du thème, personnalisé et minifié, destiné à être inséré dans la page. */
    public static function css(): string
    {
        if (self::$css !== null) {
            return self::$css;
        }
        $a = Settings::get('appearance');
        $accent = Sanitize::color($a['accent'] ?? '', '#5b4cf0');
        $vars = ':root{--accent:' . $accent
            . ';--on-accent:' . (self::contrast($accent, '#ffffff') >= 4.5 ? '#ffffff' : '#111111')
            . ';--link:' . self::readable($accent, '#ffffff')
            . ';--link-dark:' . self::readable($accent, '#121218')
            . ';--font:' . (self::FONTS[$a['font_body'] ?? 'system'][1] ?? self::FONTS['system'][1])
            . ';--font-head:' . (self::FONTS[$a['font_head'] ?? 'system'][1] ?? self::FONTS['system'][1]) . '}';
        $css = (string) file_get_contents(ECLAIR_ROOT . '/app/theme/theme.css');
        if (($a['dark'] ?? 'auto') !== 'auto') {
            $css = (string) preg_replace('~/\*dark\*/.*?/\*/dark\*/~s', '', $css);
        }
        return self::$css = self::minify($vars . $css);
    }

    public static function minify(string $css): string
    {
        // Prudent : on ne touche jamais aux espaces autour de + et - (obligatoires dans calc()).
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
        $css = preg_replace('~\s+~', ' ', $css) ?? $css;
        $css = preg_replace('~\s*([{};,>])\s*~', '$1', $css) ?? $css;
        $css = preg_replace('~:\s+~', ':', $css) ?? $css;
        $css = preg_replace('~([{;])([a-z-]+)\s+:~i', '$1$2:', $css) ?? $css;
        return trim(str_replace(';}', '}', $css));
    }

    /** Empreinte SHA-256 du style en ligne, pour la politique de sécurité (CSP). */
    public static function hash(string $css): string
    {
        return base64_encode(hash('sha256', $css, true));
    }

    /* ─────────────────────────── Couleurs (WCAG) ─────────────────────────── */

    private static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    private static function luminance(string $hex): float
    {
        $c = array_map(function (int $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** Version de la couleur assez contrastée (≥ 4,5:1) sur le fond donné. */
    public static function readable(string $color, string $background): string
    {
        $towards = self::luminance($background) > 0.5 ? 0 : 255;
        $rgb = self::rgb($color);
        for ($i = 0; $i < 20 && self::contrast(self::hex($rgb), $background) < 4.5; $i++) {
            $rgb = array_map(fn ($v) => (int) round($v + ($towards - $v) * 0.12), $rgb);
        }
        return self::hex($rgb);
    }

    private static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn ($v) => max(0, min(255, (int) $v)), $rgb));
    }
}
