<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Contenus de départ créés à l'installation selon le type de site choisi
 * (en français, espagnol ou anglais ; les autres langues reçoivent l'anglais).
 */
final class Starter
{
    public const TYPES = ['blog', 'business', 'freelance', 'landing'];

    /** Textes [fr, es, en]. Les « {site} » et « {name} » sont remplacés. */
    private const T = [
        'about' => ['À propos', 'Sobre nosotros', 'About'],
        'about_slug' => ['a-propos', 'sobre-nosotros', 'about'],
        'contact' => ['Contact', 'Contacto', 'Contact'],
        'contact_slug' => ['contact', 'contacto', 'contact'],
        'services' => ['Services', 'Servicios', 'Services'],
        'services_slug' => ['services', 'servicios', 'services'],
        'home' => ['Accueil', 'Inicio', 'Home'],
        'home_slug' => ['accueil', 'inicio', 'home'],
        'legal' => ['Mentions légales', 'Aviso legal', 'Legal notice'],
        'legal_slug' => ['mentions-legales', 'aviso-legal', 'legal-notice'],
        'privacy' => ['Politique de confidentialité', 'Política de privacidad', 'Privacy policy'],
        'privacy_slug' => ['politique-de-confidentialite', 'politica-de-privacidad', 'privacy-policy'],
        'category' => ['Guides', 'Guías', 'Guides'],
        'category_desc' => [
            'Des guides pratiques, clairs et à jour pour passer à l’action.',
            'Guías prácticas, claras y actualizadas para pasar a la acción.',
            'Practical, clear and up-to-date guides to help you take action.',
        ],
        'blog_desc' => [
            'Conseils, guides et actualités de {site}.',
            'Consejos, guías y novedades de {site}.',
            'Tips, guides and news from {site}.',
        ],
        // Article de démonstration
        'post_title' => ['Premiers pas avec Éclair : le guide en 5 minutes', 'Primeros pasos con Éclair: la guía en 5 minutos', 'Getting started with Éclair: the 5-minute guide'],
        'post_slug' => ['premiers-pas-avec-eclair', 'primeros-pasos-con-eclair', 'getting-started-with-eclair'],
        'post_summary' => [
            'Éclair est un CMS ultra léger : pas de base de données, pas d’extensions, des pages servies en quelques millisecondes et un référencement SEO et GEO automatique. Ce guide montre comment écrire, structurer et publier un contenu efficace.',
            'Éclair es un CMS ultraligero: sin base de datos, sin plugins, páginas servidas en milisegundos y un posicionamiento SEO y GEO automático. Esta guía muestra cómo escribir, estructurar y publicar un contenido eficaz.',
            'Éclair is an ultra-light CMS: no database, no plugins, pages served in milliseconds and automatic SEO and GEO. This guide shows how to write, structure and publish effective content.',
        ],
        'post_desc' => [
            'Découvre comment écrire, structurer et publier un contenu optimisé pour Google et les IA avec Éclair, le CMS ultra léger sans base de données.',
            'Descubre cómo escribir, estructurar y publicar un contenido optimizado para Google y la IA con Éclair, el CMS ultraligero sin base de datos.',
            'Learn how to write, structure and publish content optimised for Google and AI engines with Éclair, the ultra-light CMS without a database.',
        ],
        'post_kw' => ['CMS ultra léger', 'CMS ultraligero', 'ultra-light CMS'],
        'p1' => [
            'Tu viens d’installer <strong>Éclair</strong>, un CMS ultra léger. Cet article de démonstration présente les blocs disponibles : modifie-le ou supprime-le quand tu veux depuis l’administration.',
            'Acabas de instalar <strong>Éclair</strong>, un CMS ultraligero. Este artículo de demostración presenta los bloques disponibles: modifícalo o elimínalo cuando quieras desde la administración.',
            'You have just installed <strong>Éclair</strong>, an ultra-light CMS. This demo post shows the available blocks: edit or delete it whenever you like from the admin.',
        ],
        'h1' => ['Comment écrire un article qui plaît à Google et aux IA ?', '¿Cómo escribir un artículo que guste a Google y a la IA?', 'How do you write a post that Google and AI engines love?'],
        'p2' => [
            'Commence par une réponse directe en deux ou trois phrases, puis développe. Les moteurs de réponse comme ChatGPT, Perplexity ou les AI Overviews de Google citent plus volontiers les contenus clairs, structurés et sourcés.',
            'Empieza con una respuesta directa en dos o tres frases y luego desarrolla. Los motores de respuesta como ChatGPT, Perplexity o los AI Overviews de Google citan más los contenidos claros, estructurados y con fuentes.',
            'Start with a direct answer in two or three sentences, then expand. Answer engines such as ChatGPT, Perplexity or Google’s AI Overviews are more likely to cite clear, structured and sourced content.',
        ],
        'li' => [
            ['Un titre précis qui contient ton expression-clé', 'Une méta-description de 110 à 160 caractères', 'Des intertitres, idéalement sous forme de questions', 'Des listes, des tableaux et des chiffres concrets', 'Un bloc FAQ en fin d’article'],
            ['Un título preciso que contenga tu palabra clave', 'Una meta descripción de 110 a 160 caracteres', 'Subtítulos, idealmente en forma de preguntas', 'Listas, tablas y cifras concretas', 'Un bloque de preguntas frecuentes al final'],
            ['A precise title that contains your key phrase', 'A meta description of 110 to 160 characters', 'Subheadings, ideally phrased as questions', 'Lists, tables and concrete figures', 'An FAQ block at the end of the post'],
        ],
        'tip' => [
            'Le panneau <strong>Analyse</strong> de l’éditeur te donne une note SEO et une note GEO en temps réel, avec des conseils concrets pour progresser.',
            'El panel <strong>Análisis</strong> del editor te da una nota SEO y una nota GEO en tiempo real, con consejos concretos para mejorar.',
            'The editor’s <strong>Analysis</strong> panel gives you an SEO score and a GEO score in real time, with concrete tips to improve.',
        ],
        'h2' => ['Combien de temps faut-il pour publier une page ?', '¿Cuánto tiempo se tarda en publicar una página?', 'How long does it take to publish a page?'],
        'p3' => [
            'Moins d’une minute : écris ton texte, ajoute des blocs avec le bouton + (ou en tapant « / » sur une ligne vide), puis clique sur Publier. Éclair génère automatiquement le sitemap, les données structurées, le fichier llms.txt et la version Markdown de la page.',
            'Menos de un minuto: escribe tu texto, añade bloques con el botón + (o escribiendo «/» en una línea vacía) y pulsa Publicar. Éclair genera automáticamente el sitemap, los datos estructurados, el archivo llms.txt y la versión Markdown de la página.',
            'Less than a minute: write your text, add blocks with the + button (or by typing “/” on an empty line), then click Publish. Éclair automatically generates the sitemap, structured data, the llms.txt file and the Markdown version of the page.',
        ],
        'table' => [
            [['Fonction', 'CMS classique', 'Éclair'], ['Base de données', 'Obligatoire', 'Aucune'], ['Référencement SEO et GEO', 'Extensions à installer', 'Intégré'], ['Pages publiques', 'Générées à chaque visite', 'Servies depuis un cache statique'], ['Cookies sur le site public', 'Souvent nombreux', 'Aucun']],
            [['Función', 'CMS clásico', 'Éclair'], ['Base de datos', 'Obligatoria', 'Ninguna'], ['Posicionamiento SEO y GEO', 'Plugins que instalar', 'Integrado'], ['Páginas públicas', 'Generadas en cada visita', 'Servidas desde una caché estática'], ['Cookies en el sitio público', 'A menudo muchas', 'Ninguna']],
            [['Feature', 'Classic CMS', 'Éclair'], ['Database', 'Required', 'None'], ['SEO and GEO', 'Plugins to install', 'Built in'], ['Public pages', 'Generated on every visit', 'Served from a static cache'], ['Cookies on the public site', 'Often many', 'None']],
        ],
        'quote' => ['La simplicité est la sophistication suprême.', 'La simplicidad es la máxima sofisticación.', 'Simplicity is the ultimate sophistication.'],
        'quote_cite' => ['Attribué à Léonard de Vinci', 'Atribuido a Leonardo da Vinci', 'Attributed to Leonardo da Vinci'],
        'h3' => ['Et la sécurité ?', '¿Y la seguridad?', 'What about security?'],
        'p4' => [
            'Sans base de données, pas d’injection SQL. Les mots de passe sont protégés par Argon2id, les tentatives de connexion sont limitées et tu peux activer la double authentification depuis ton profil.',
            'Sin base de datos no hay inyección SQL. Las contraseñas se protegen con Argon2id, los intentos de conexión están limitados y puedes activar la autenticación en dos pasos desde tu perfil.',
            'No database means no SQL injection. Passwords are protected with Argon2id, login attempts are rate-limited and you can turn on two-factor authentication from your profile.',
        ],
        'faq' => [
            [['Faut-il installer des extensions ?', 'Non. L’essentiel est intégré : SEO, GEO, sitemap, formulaire de contact, redirections et sauvegardes.'],
                ['Mon site sera-t-il rapide sur un hébergement mutualisé ?', 'Oui. Chaque page est générée une seule fois puis servie depuis un cache statique, sans base de données.'],
                ['Comment les IA trouvent-elles mon contenu ?', 'Éclair publie un fichier llms.txt, une version Markdown de chaque page et des règles adaptées aux robots IA dans le fichier robots.txt.']],
            [['¿Hay que instalar plugins?', 'No. Lo esencial está integrado: SEO, GEO, sitemap, formulario de contacto, redirecciones y copias de seguridad.'],
                ['¿Mi sitio será rápido en un alojamiento compartido?', 'Sí. Cada página se genera una sola vez y se sirve desde una caché estática, sin base de datos.'],
                ['¿Cómo encuentran las IA mi contenido?', 'Éclair publica un archivo llms.txt, una versión Markdown de cada página y reglas adaptadas a los robots de IA en el archivo robots.txt.']],
            [['Do I need to install plugins?', 'No. The essentials are built in: SEO, GEO, sitemap, contact form, redirects and backups.'],
                ['Will my site be fast on shared hosting?', 'Yes. Each page is generated once, then served from a static cache, with no database.'],
                ['How do AI engines find my content?', 'Éclair publishes an llms.txt file, a Markdown version of every page and AI-crawler rules in robots.txt.']],
        ],
        // Pages
        'about_p' => [
            'Présente-toi ici : qui tu es, ce que tu fais et pour qui. Une page « À propos » sincère renforce la confiance des visiteurs… et celle des moteurs de recherche, qui évaluent l’expertise et la fiabilité des auteurs.',
            'Preséntate aquí: quién eres, qué haces y para quién. Una página «Sobre nosotros» sincera refuerza la confianza de los visitantes… y la de los buscadores, que evalúan la experiencia y la fiabilidad de los autores.',
            'Introduce yourself here: who you are, what you do and for whom. An honest About page builds trust with visitors… and with search engines, which assess the expertise and reliability of authors.',
        ],
        'about_h' => ['Notre histoire', 'Nuestra historia', 'Our story'],
        'about_p2' => [
            'Raconte en quelques lignes comment l’aventure a commencé, ce qui te rend unique et les résultats obtenus pour tes clients. Les chiffres concrets (années d’expérience, clients accompagnés) sont très appréciés.',
            'Cuenta en pocas líneas cómo empezó la aventura, qué te hace único y los resultados obtenidos para tus clientes. Las cifras concretas (años de experiencia, clientes atendidos) se valoran mucho.',
            'Tell in a few lines how it all started, what makes you unique and the results you have achieved for your clients. Concrete figures (years of experience, clients helped) are much appreciated.',
        ],
        'contact_p' => [
            'Une question, un projet ? Écrivez-nous via ce formulaire : nous répondons en général sous 24 heures ouvrées.',
            '¿Una pregunta, un proyecto? Escríbenos con este formulario: solemos responder en 24 horas laborables.',
            'A question or a project? Write to us using this form: we usually reply within one business day.',
        ],
        'contact_h' => ['Envoyez-nous un message', 'Envíanos un mensaje', 'Send us a message'],
        'legal_p' => [
            'Complète cette page avec tes informations légales. En France, la loi pour la confiance dans l’économie numérique (LCEN) impose de les afficher.',
            'Completa esta página con tus datos legales. En España, la Ley de Servicios de la Sociedad de la Información (LSSI) obliga a mostrarlos.',
            'Fill in this page with your legal information, as required by the law of your country.',
        ],
        'legal_items' => [
            ['<strong>Éditeur du site :</strong> [Nom ou raison sociale], [forme juridique, capital], [adresse], [SIRET / RCS]', '<strong>Directeur de la publication :</strong> [Nom]', '<strong>Contact :</strong> [e-mail], [téléphone]', '<strong>Hébergeur :</strong> [Nom de l’hébergeur], [adresse], [téléphone]'],
            ['<strong>Titular del sitio:</strong> [Nombre o razón social], [NIF], [domicilio]', '<strong>Datos registrales:</strong> [Registro Mercantil, si procede]', '<strong>Contacto:</strong> [correo electrónico], [teléfono]', '<strong>Alojamiento:</strong> [Nombre del proveedor], [dirección]'],
            ['<strong>Site publisher:</strong> [Name or company name], [address], [company number]', '<strong>Publication manager:</strong> [Name]', '<strong>Contact:</strong> [email], [phone]', '<strong>Hosting provider:</strong> [Provider name], [address]'],
        ],
        'privacy_p' => [
            'Ce site respecte ta vie privée : il n’utilise aucun cookie de suivi ni outil de mesure d’audience tiers. Seul un cookie technique est déposé pour les personnes qui se connectent à l’administration.',
            'Este sitio respeta tu privacidad: no utiliza cookies de seguimiento ni herramientas de analítica de terceros. Solo se usa una cookie técnica para las personas que acceden a la administración.',
            'This site respects your privacy: it uses no tracking cookies and no third-party analytics. Only a technical cookie is set for people who log in to the admin.',
        ],
        'privacy_h' => ['Données collectées par le formulaire de contact', 'Datos recogidos por el formulario de contacto', 'Data collected through the contact form'],
        'privacy_items' => [
            ['<strong>Données :</strong> nom, adresse e-mail et message.', '<strong>Finalité :</strong> répondre à votre demande.', '<strong>Conservation :</strong> [durée, par exemple 3 ans].', '<strong>Responsable du traitement :</strong> [Nom, e-mail].', '<strong>Vos droits :</strong> accès, rectification, effacement, opposition et portabilité, en écrivant à [e-mail]. Vous pouvez aussi saisir la CNIL.'],
            ['<strong>Datos:</strong> nombre, correo electrónico y mensaje.', '<strong>Finalidad:</strong> responder a tu solicitud.', '<strong>Conservación:</strong> [plazo, por ejemplo 3 años].', '<strong>Responsable del tratamiento:</strong> [Nombre, correo].', '<strong>Tus derechos:</strong> acceso, rectificación, supresión, oposición y portabilidad, escribiendo a [correo]. También puedes reclamar ante la AEPD.'],
            ['<strong>Data:</strong> name, email address and message.', '<strong>Purpose:</strong> replying to your request.', '<strong>Retention:</strong> [period, e.g. 3 years].', '<strong>Data controller:</strong> [Name, email].', '<strong>Your rights:</strong> access, rectification, erasure, objection and portability by writing to [email]. You may also contact your data protection authority.'],
        ],
        // Accueil vitrine / freelance / landing
        'hero_business' => ['Bienvenue chez {site}', 'Bienvenido a {site}', 'Welcome to {site}'],
        'hero_text' => [
            'Présente ici ton offre en une phrase claire : ce que tu proposes, pour qui, et pourquoi c’est le meilleur choix.',
            'Presenta aquí tu oferta en una frase clara: qué ofreces, para quién y por qué es la mejor opción.',
            'Present your offer here in one clear sentence: what you offer, for whom, and why it is the best choice.',
        ],
        'cta_contact' => ['Nous contacter', 'Contáctanos', 'Contact us'],
        'cta_services' => ['Découvrir nos services', 'Ver nuestros servicios', 'See our services'],
        'cta_quote' => ['Demander un devis gratuit', 'Pedir presupuesto gratis', 'Get a free quote'],
        'why' => ['Pourquoi nous choisir ?', '¿Por qué elegirnos?', 'Why choose us?'],
        'features' => [
            [['🚀', 'Rapide', 'Décris un premier atout concret de ton offre.'], ['🤝', 'Proche de vous', 'Un deuxième atout : accompagnement, disponibilité, écoute…'], ['✅', 'Des résultats', 'Un troisième atout, idéalement chiffré (ex. : 98 % de clients satisfaits).']],
            [['🚀', 'Rápido', 'Describe una primera ventaja concreta de tu oferta.'], ['🤝', 'Cercano', 'Una segunda ventaja: acompañamiento, disponibilidad, escucha…'], ['✅', 'Resultados', 'Una tercera ventaja, idealmente con cifras (p. ej.: 98 % de clientes satisfechos).']],
            [['🚀', 'Fast', 'Describe a first concrete benefit of your offer.'], ['🤝', 'By your side', 'A second benefit: support, availability, listening…'], ['✅', 'Results', 'A third benefit, ideally with figures (e.g. 98% satisfied clients).']],
        ],
        'latest' => ['Nos derniers conseils', 'Nuestros últimos consejos', 'Our latest tips'],
        'services_p' => [
            'Détaille ici chacun de tes services : à qui il s’adresse, ce qu’il comprend et le résultat attendu. Une page par service importante est encore mieux pour le référencement.',
            'Detalla aquí cada uno de tus servicios: a quién se dirige, qué incluye y el resultado esperado. Una página por servicio importante es aún mejor para el posicionamiento.',
            'Describe each of your services here: who it is for, what it includes and the expected result. One page per key service is even better for SEO.',
        ],
        'services_faq' => [
            [['Combien coûtent vos services ?', 'Indique une fourchette de prix ou ta façon de construire un devis : les IA reprennent volontiers ce type d’information.'], ['Quels sont vos délais ?', 'Donne un délai moyen concret, par exemple « deux semaines entre la commande et la livraison ».']],
            [['¿Cuánto cuestan vuestros servicios?', 'Indica un rango de precios o cómo preparas un presupuesto: la IA suele reutilizar este tipo de información.'], ['¿Cuáles son los plazos?', 'Da un plazo medio concreto, por ejemplo «dos semanas entre el pedido y la entrega».']],
            [['How much do your services cost?', 'Give a price range or explain how you build a quote: AI engines readily reuse this kind of information.'], ['What are your lead times?', 'Give a concrete average lead time, for example “two weeks from order to delivery”.']],
        ],
        'hero_freelance' => ['{name}', '{name}', '{name}'],
        'hero_freelance_text' => [
            'Décris en une phrase ce que tu fais et pour qui. Exemple : « Consultant indépendant, j’aide les PME à gagner des clients grâce au web. »',
            'Describe en una frase qué haces y para quién. Ejemplo: «Consultor independiente, ayudo a las pymes a conseguir clientes gracias a la web.»',
            'Describe in one sentence what you do and for whom. Example: “Independent consultant, I help small businesses win clients online.”',
        ],
        'skills' => ['Ce que je peux faire pour vous', 'Lo que puedo hacer por ti', 'What I can do for you'],
        'cta_hire' => ['Travaillons ensemble', 'Trabajemos juntos', 'Let’s work together'],
        'landing_faq_title' => ['Vos questions', 'Tus preguntas', 'Your questions'],
    ];

    public static function create(string $type, string $lang, array $user, string $site): void
    {
        $i = ['fr' => 0, 'es' => 1][$lang] ?? 2;
        $s = static fn (string $key) => self::T[$key][$i];
        $r = static fn (string $text): string => strtr($text, ['{site}' => $site, '{name}' => $user['name']]);
        $p = static fn (string $text): array => ['type' => 'paragraph', 'text' => $text];
        $h = static fn (string $text, int $level = 2): array => ['type' => 'heading', 'level' => $level, 'text' => e($text)];
        $save = static function (array $data) use ($user, $lang): array {
            return Content::save($data + ['status' => 'published', 'lang' => $lang, 'author' => $user['id']], $user);
        };
        $legalSlugs = [$s('legal_slug'), $s('privacy_slug')];

        $blog = $type !== 'landing';
        $category = $blog ? Categories::save(['name' => $s('category'), 'description' => $s('category_desc')]) : null;
        $main = [];
        $footer = [];

        if ($blog) {
            $save([
                'type' => 'post',
                'title' => $s('post_title'),
                'slug' => $s('post_slug'),
                'category' => $category['id'],
                'summary' => $s('post_summary'),
                'seo' => ['description' => $s('post_desc'), 'keyword' => $s('post_kw')],
                'blocks' => [
                    $p($s('p1')),
                    $h($s('h1')),
                    $p($s('p2')),
                    ['type' => 'list', 'style' => 'ul', 'items' => array_map('e', $s('li'))],
                    ['type' => 'callout', 'variant' => 'tip', 'title' => '', 'text' => $s('tip')],
                    $h($s('h2')),
                    $p($s('p3')),
                    ['type' => 'table', 'head' => true, 'caption' => '', 'rows' => array_map(fn ($row) => array_map('e', $row), $s('table'))],
                    ['type' => 'quote', 'text' => e($s('quote')), 'cite' => $s('quote_cite')],
                    $h($s('h3')),
                    $p($s('p4')),
                    ['type' => 'faq', 'title' => '', 'items' => array_map(fn ($qa) => ['q' => $qa[0], 'a' => e($qa[1])], $s('faq'))],
                ],
            ]);
        }

        $contact = $save([
            'type' => 'page',
            'title' => $s('contact'),
            'slug' => $s('contact_slug'),
            'blocks' => [$p(e($s('contact_p'))), ['type' => 'contact', 'title' => $s('contact_h'), 'button' => '']],
            'seo' => ['description' => $r($s('contact_p'))],
        ]);
        $legal = $save([
            'type' => 'page',
            'title' => $s('legal'),
            'slug' => $legalSlugs[0],
            'blocks' => [$p(e($s('legal_p'))), ['type' => 'list', 'style' => 'ul', 'items' => $s('legal_items')]],
            'seo' => ['noindex' => true],
        ]);
        $privacy = $save([
            'type' => 'page',
            'title' => $s('privacy'),
            'slug' => $legalSlugs[1],
            'blocks' => [$p(e($s('privacy_p'))), $h($s('privacy_h')), ['type' => 'list', 'style' => 'ul', 'items' => $s('privacy_items')]],
            'seo' => ['noindex' => true],
        ]);
        $footer = [['type' => 'content', 'id' => $legal['id']], ['type' => 'content', 'id' => $privacy['id']]];

        if ($type !== 'landing') {
            $about = $save([
                'type' => 'page',
                'title' => $s('about'),
                'slug' => $s('about_slug'),
                'blocks' => [$p(e($s('about_p'))), $h($s('about_h')), $p(e($s('about_p2')))],
            ]);
        }

        $features = ['type' => 'features', 'title' => $type === 'freelance' ? $s('skills') : $s('why'),
            'items' => array_map(fn ($f) => ['icon' => $f[0], 'title' => $f[1], 'text' => e($f[2])], $s('features'))];
        $contactUrl = '/' . $contact['slug'];
        $home = null;

        switch ($type) {
            case 'blog':
                $main = [['type' => 'content', 'id' => $about['id']], ['type' => 'content', 'id' => $contact['id']]];
                break;
            case 'business':
                $services = $save([
                    'type' => 'page',
                    'title' => $s('services'),
                    'slug' => $s('services_slug'),
                    'blocks' => [$p(e($s('services_p'))), $features,
                        ['type' => 'faq', 'title' => '', 'items' => array_map(fn ($qa) => ['q' => $qa[0], 'a' => e($qa[1])], $s('services_faq'))],
                        ['type' => 'button', 'text' => $s('cta_quote'), 'url' => $contactUrl, 'style' => 'primary', 'align' => 'center']],
                ]);
                $home = $save([
                    'type' => 'page',
                    'title' => $s('home'),
                    'slug' => $s('home_slug'),
                    'options' => ['hide_title' => true, 'wide' => false, 'toc' => 'off'],
                    'blocks' => [
                        ['type' => 'hero', 'title' => $r($s('hero_business')), 'text' => e($s('hero_text')),
                            'button' => $s('cta_contact'), 'url' => $contactUrl, 'button2' => $s('cta_services'), 'url2' => '/' . $services['slug']],
                        $features,
                        ['type' => 'posts', 'title' => $s('latest'), 'count' => 3],
                        ['type' => 'button', 'text' => $s('cta_quote'), 'url' => $contactUrl, 'style' => 'primary', 'align' => 'center'],
                    ],
                ]);
                $main = [['type' => 'content', 'id' => $services['id']], ['type' => 'content', 'id' => $about['id']], ['type' => 'blog'], ['type' => 'content', 'id' => $contact['id']]];
                break;
            case 'freelance':
                $home = $save([
                    'type' => 'page',
                    'title' => $s('home'),
                    'slug' => $s('home_slug'),
                    'options' => ['hide_title' => true, 'wide' => false, 'toc' => 'off'],
                    'blocks' => [
                        ['type' => 'hero', 'title' => $r($s('hero_freelance')), 'text' => e($s('hero_freelance_text')),
                            'button' => $s('cta_hire'), 'url' => $contactUrl, 'button2' => $s('about'), 'url2' => '/' . $about['slug']],
                        $features,
                        ['type' => 'posts', 'title' => $s('latest'), 'count' => 3],
                    ],
                ]);
                $main = [['type' => 'content', 'id' => $about['id']], ['type' => 'blog'], ['type' => 'content', 'id' => $contact['id']]];
                break;
            case 'landing':
                $home = $save([
                    'type' => 'page',
                    'title' => $s('home'),
                    'slug' => $s('home_slug'),
                    'options' => ['hide_title' => true, 'wide' => false, 'toc' => 'off'],
                    'blocks' => [
                        ['type' => 'hero', 'title' => $r($s('hero_business')), 'text' => e($s('hero_text')), 'button' => $s('cta_contact'), 'url' => '#contact'],
                        $features,
                        ['type' => 'faq', 'title' => $s('landing_faq_title'), 'items' => array_map(fn ($qa) => ['q' => $qa[0], 'a' => e($qa[1])], $s('services_faq'))],
                        ['type' => 'heading', 'level' => 2, 'text' => e($s('contact_h')), 'anchor' => 'contact'],
                        ['type' => 'contact', 'title' => '', 'button' => ''],
                    ],
                ]);
                break;
        }

        Menus::save('main', $main);
        Menus::save('footer', $footer);
        Settings::save([
            'home' => $home['id'] ?? '',
            'blog' => [
                'enabled' => $blog,
                'base' => ['fr' => 'blog', 'es' => 'blog'][$lang] ?? 'blog',
                'title' => 'Blog',
                'description' => $r($s('blog_desc')),
            ],
            'seo' => ['entity' => $type === 'freelance' ? 'Person' : 'Organization', 'entity_name' => $type === 'freelance' ? $user['name'] : ''],
        ]);
    }
}
