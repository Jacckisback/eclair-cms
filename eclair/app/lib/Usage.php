<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Usage du site (type choisi à l'installation, modifiable dans Réglages › Général) : le tableau de bord met en premier les
 * outils de cet usage, avec leur état, et « Premiers pas » commence par l'étape qui lui est propre. Sans usage précisé
 * (site installé avant la 1.14), le tableau de bord reste celui de toujours.
 */
final class Usage
{
    /** Usages proposés, dans l'ordre de l'installation : clé => [icône, nom, description]. */
    public static function all(): array
    {
        return [
            'business' => ['store', t('Site vitrine'), t('Entreprise, artisan, association : accueil, services, contact, blog.')],
            'blog' => ['pen', t('Blog'), t('Des articles avant tout, avec catégories et flux RSS.')],
            'freelance' => ['briefcase', t('Freelance / portfolio'), t('Ta présentation, tes compétences, ton blog d’expert.')],
            'landing' => ['target', t('Page unique'), t('Une page de vente ou de lancement, avec formulaire.')],
            'media' => ['compass', t('Média / Google Discover'), t('Des articles de fond pensés pour Google Discover, avec publicité AdSense si tu le souhaites.')],
            'affiliate' => ['bag', t('Affiliation'), t('Guides d’achat et comparatifs, avec la mention publicitaire ajoutée pour toi.')],
            'revival' => ['clock', t('Relancer un ancien site'), t('Ton domaine a déjà eu un site : Éclair retrouve ses anciennes adresses pour leur redonner une page.')],
        ];
    }

    /** Fonctions livrées que chaque usage ajoute (préréglage) : usage => [id => nom affiché]. */
    public const PRESETS = ['revival' => ['wayback' => 'Ancien site'], 'media' => ['discover' => 'Google Discover']];

    /** Fonctions livrées utiles à un usage (celles qui existent dans cette version). */
    public static function extensions(string $usage): array
    {
        return array_intersect_key(self::PRESETS[$usage] ?? [], Extensions::bundled());
    }

    /** Usage du site, ou chaîne vide s'il n'est pas précisé. */
    public static function current(): string
    {
        $usage = (string) Settings::get('usage');
        return in_array($usage, Starter::TYPES, true) ? $usage : '';
    }

    /** Étape propre à l'usage, placée en tête de « Premiers pas » (null si l'usage n'en a pas). */
    public static function step(array $user): ?array
    {
        return match (self::current()) {
            'media' => ['done' => (bool) array_filter(self::discover() ?? [], fn (array $p): bool => $p['ready']), 'label' => t('Préparer un article pour Google Discover'),
                'hint' => t('Grande image de 1200 px, auteur présenté, titre sans excès : ce que Google recommande.'),
                'url' => admin_url(Extensions::enabled('discover') ? 'x/discover' : 'extensions')],
            'affiliate' => ['done' => Affiliate::links() !== [], 'label' => t('Ajouter ton premier lien d’affiliation'),
                'hint' => t('La mention publicitaire s’affiche alors d’elle-même sur les pages concernées.'), 'url' => admin_url('affiliate')],
            'revival' => Auth::can('settings') ? ['done' => Extensions::enabled('wayback') && self::revival()['scanned'] > 0, 'label' => t('Retrouver les anciennes adresses de ton domaine'),
                'hint' => t('Les liens qui pointent encore vers elles mèneront de nouveau quelque part.'), 'url' => admin_url(Extensions::enabled('wayback') ? 'x/wayback' : 'extensions')] : null,
            default => null,
        };
    }

    /**
     * Carte « Ton site… » du tableau de bord : titre et tuiles [icône, nom, état, lien, ton (ok, warn ou vide)]. Null sans
     * usage précisé. $stats : chiffres déjà calculés par le tableau de bord (articles, brouillons, messages) ; $ai : rapport
     * des robots IA sur 30 jours, ou null si la mesure est coupée.
     */
    public static function card(array $user, array $stats, ?array $ai): ?array
    {
        $usage = self::current();
        if ($usage === '') {
            return null;
        }
        $home = Content::index()[(string) Settings::get('home')] ?? null;
        $tiles = [
            'messages' => ['mail', t('Messages'), $stats['messages'] ? t('{n} non lus', ['n' => $stats['messages']]) : t('Aucun en attente'), admin_url('messages'), $stats['messages'] ? 'warn' : ''],
            'home' => ['home', t('Page d’accueil'), t('Modifier'), $home ? admin_url('edit/' . $home['id']) : admin_url('settings'), ''],
            'posts' => ['pen', t('Articles'), t('{n} publiés', ['n' => $stats['posts']]), admin_url('content?type=post'), ''],
            'drafts' => ['file', t('Brouillons'), $stats['drafts'] ? t('{n} à relire', ['n' => $stats['drafts']]) : t('Aucun'), admin_url('content?status=draft'), $stats['drafts'] ? 'warn' : ''],
        ];
        if ($ai !== null) {
            $tiles['bots'] = ['bot', t('Robots IA'), t('{n} visites en 30 jours', ['n' => $ai['total']]), admin_url('x/ai-visits'), ''];
        }
        if (in_array($usage, ['blog', 'freelance', 'media', 'affiliate'], true)) {
            $planned = count(array_filter(Plan::all(), fn (array $i): bool => in_array(Plan::state($i), ['idea', 'draft'], true)));
            $tiles['plan'] = ['calendar', t('Planning'), $planned ? t('{n} idées à venir', ['n' => $planned]) : t('Aucune idée à venir'), admin_url('plan'), $planned ? '' : 'warn'];
        }
        // Discover est une fonction optionnelle depuis la 2.0 : sa tuile n'apparaît que si le site l'a ajoutée.
        if (in_array($usage, ['blog', 'media', 'affiliate', 'revival'], true) && ($posts = self::discover()) !== null) {
            $ready = count(array_filter($posts, fn (array $p): bool => $p['ready']));
            $tiles['discover'] = ['compass', t('Prêt pour Discover'), $posts ? t('{ready} articles sur {n}', ['ready' => $ready, 'n' => count($posts)]) : t('Aucun article'),
                admin_url('x/discover'), $posts && $ready === count($posts) ? 'ok' : 'warn'];
            $tiles['trends'] = ['chart', t('Tendances du jour'), t('Voir les tendances'), admin_url('x/discover#trends'), ''];
        }
        [$title, $keys] = match ($usage) {
            'business' => [t('Ton site vitrine'), ['messages', 'home', 'bots', 'posts']],
            'freelance' => [t('Ton site de freelance'), ['profile', 'messages', 'posts', 'plan']],
            'landing' => [t('Ta page unique'), ['home', 'messages', 'view']],
            'blog' => [t('Ton blog'), ['plan', 'discover', 'bots', 'drafts']],
            'media' => [t('Ton média'), ['discover', 'trends', 'plan', 'ads']],
            'affiliate' => [t('Ton site d’affiliation'), ['links', 'write', 'plan', 'discover']],
            default => [t('Ton ancien site'), ['wayback', 'redirects', 'drafts', 'discover']],
        };
        foreach ($keys as $key) {
            $tiles[$key] ??= match ($key) {
                'profile' => ['user', t('Ton profil d’auteur'), mb_strlen((string) ($user['bio'] ?? '')) >= 40 ? t('Complet') : t('À compléter'), admin_url('profile'),
                    mb_strlen((string) ($user['bio'] ?? '')) >= 40 ? 'ok' : 'warn'],
                'view' => ['eye', t('Voir la page'), t('Ouvrir'), path_to('/'), ''],
                'ads' => Auth::can('settings') ? ['target', t('Publicité'), Ads::active() ? t('Active sur les articles') : t('Inactive'), admin_url('ads'), Ads::active() ? 'ok' : ''] : null,
                'links' => ['bag', t('Liens d’affiliation'), ($n = count(Affiliate::links())) ? t('{n} liens', ['n' => $n]) : t('Aucun lien'), admin_url('affiliate'), $n ? '' : 'warn'],
                'write' => Ai::allowed($user)
                    ? ['spark', t('Article par l’IA'), t('Comparatif, guide d’achat'), admin_url('affiliate'), '']
                    : ['spark', t('Article par l’IA'), t('Active l’assistant IA'), admin_url(Auth::can('settings') ? 'settings?tab=ai' : 'affiliate'), ''],
                'wayback' => self::wayback(),
                'redirects' => ['redirect', t('Redirections'), t('{n} redirections', ['n' => count(Redirects::all())]), admin_url('redirects'), ''],
                default => null,
            };
        }
        return ['usage' => $usage, 'title' => $title, 'tiles' => array_values(array_filter(array_map(fn (string $k): ?array => $tiles[$k] ?? null, $keys)))];
    }

    /** Contrôles « prêt pour Discover » des derniers articles, ou null si la fonction Discover n'est pas active. */
    private static function discover(): ?array
    {
        $posts = Extensions::enabled('discover') ? Extensions::invoke('discover', 'posts') : null;
        return is_array($posts) ? $posts : null;
    }

    /**
     * Tuile « Ancien site » (administrateurs seulement, comme la page). La fonction est optionnelle depuis la 2.0 : absente,
     * la tuile propose de l'ajouter ; présente, elle résume ses données (lues sans charger son code).
     */
    private static function wayback(): ?array
    {
        if (!Auth::can('settings')) {
            return null;
        }
        if (!Extensions::enabled('wayback')) {
            return ['clock', t('Ancien site'), t('À ajouter'), admin_url('extensions'), 'warn'];
        }
        $d = self::revival();
        if ($d['scanned'] === 0) {
            return ['clock', t('Ancien site'), t('À lancer'), admin_url('x/wayback'), 'warn'];
        }
        $built = count(array_filter($d['urls'], fn (mixed $u): bool => is_array($u) && ($u['content'] ?? '') !== ''));
        return ['clock', t('Ancien site'), t('{found} adresses · {built} pages neuves', ['found' => count($d['urls']), 'built' => $built]), admin_url('x/wayback'), ''];
    }

    /** Données de la fonction « Ancien site » (data/wayback.php), sans charger son code. */
    private static function revival(): array
    {
        $d = Store::read('wayback', []);
        return ['scanned' => (int) (is_array($d) ? ($d['scanned'] ?? 0) : 0), 'urls' => is_array($d['urls'] ?? null) ? $d['urls'] : []];
    }
}
