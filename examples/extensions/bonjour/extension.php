<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Exemple de fonction optionnelle (contrat eclair-extension/1). Le fichier d'entrée renvoie un objet ; le noyau appelle
 * ses méthodes déclarées dans extension.json, avec un contexte ($ctx : id, settings validés, lang, data = préfixe Store).
 * Toute donnée affichée est échappée avec e() : le HTML renvoyé est inséré tel quel.
 */
defined('ECLAIR') || exit;

use Eclair\Store;

return new class {
    /** Page publique /bonjour : contenu seulement ; titre et description passent par l'en-tête SEO du noyau. */
    public function page(array $ctx): array
    {
        $count = (int) ((Store::read($ctx['data'] . '/compteur', []))['visites'] ?? 0);
        return [
            'title' => 'Bonjour',
            'description' => 'Page d’exemple d’une fonction optionnelle.',
            'content' => '<h1>' . e((string) $ctx['settings']['message']) . '</h1><p class="bonjour-note">' . e('Compteur initialisé par la migration : ' . $count) . '</p>',
        ];
    }

    /** Page d'administration /x/bonjour (droit « content » vérifié par le noyau). */
    public function adminPage(array $user, array $ctx): array
    {
        return ['title' => 'Bonjour', 'html' => '<section class="card"><p>' . e('Message actuel : ' . $ctx['settings']['message']) . '</p></section>'];
    }

    /** Carte du tableau de bord : titre, texte et lien, échappés par le noyau. */
    public function card(array $user, array $ctx): array
    {
        return ['title' => 'Bonjour', 'text' => 'Message : ' . $ctx['settings']['message'], 'link' => 'x/bonjour'];
    }

    /** Rendu du bloc bonjour/salut (champs déjà validés par le noyau). */
    public function salut(array $b, array $ctx): string
    {
        $class = $b['style'] === 'encadre' ? 'bonjour-salut encadre' : 'bonjour-salut';
        return '<p class="' . e($class) . '">' . e('Salut ' . ($b['name'] !== '' ? $b['name'] : 'à toi') . ' !') . "</p>\n";
    }

    /** Tâche quotidienne (adresse secrète des tâches des fonctions). */
    public function daily(array $ctx): void
    {
        Store::write($ctx['data'] . '/derniere-tache', ['date' => time()]);
    }

    /** Migrations des données, chacune exécutée une seule fois (journal du noyau). Additive : ne détruit rien. */
    public function migrate(string $id, array $ctx): void
    {
        if ($id === '001-compteur' && Store::read($ctx['data'] . '/compteur') === null) {
            Store::write($ctx['data'] . '/compteur', ['visites' => 0]);
        }
    }
};
