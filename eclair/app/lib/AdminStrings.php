<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Textes utilisés par le JavaScript de l'administration, traduits côté serveur. */
final class AdminStrings
{
    public static function js(): array
    {
        $keys = [
            'Enregistrer', 'Enregistré', 'Enregistrement…', 'Modifications non enregistrées', 'Publier', 'Mettre à jour', 'Programmer',
            'Brouillon', 'Publié', 'Programmé', 'Aperçu', 'Supprimer', 'Dupliquer', 'Monter', 'Descendre', 'Déplacer', 'Annuler',
            'Bloc supprimé.', 'Rechercher un bloc…', 'Aucun bloc trouvé', 'Ajouter un bloc', 'Écris ou tape « / » pour choisir un bloc…',
            'Titre', 'Intertitre', 'Texte', 'Liste', 'Citation', 'Image', 'Encadré', 'FAQ', 'Tableau', 'Bouton', 'Vidéo', 'Code',
            'Séparateur', 'Bannière', 'Atouts', 'Derniers articles', 'Formulaire de contact',
            'Paragraphe de texte', 'Titre de section (H2, H3, H4)', 'À puces ou numérotée', 'Une phrase mise en avant', 'Depuis la médiathèque',
            'Astuce, info, attention', 'Questions-réponses (FAQPage)', 'Données en lignes et colonnes', 'Appel à l’action',
            'YouTube ou Vimeo, sans cookies', 'Extrait de code source', 'Ligne de séparation', 'Grand titre d’accueil avec boutons',
            'Grille de services ou d’avantages', 'Liste automatique des articles', 'Formulaire anti-spam intégré',
            'Ton titre…', 'Écris ici…', 'Élément de liste', 'Citation…', 'Auteur de la citation', 'Légende (facultatif)',
            'Texte alternatif (décris l’image)', 'Lien (facultatif)', 'Taille', 'Normale', 'Large', 'Petite', 'Titre de l’encadré (facultatif)',
            'Question', 'Réponse', 'Ajouter une question', 'Ajouter une ligne', 'Ajouter une colonne', 'Supprimer la dernière ligne',
            'Supprimer la dernière colonne', 'Première ligne = en-têtes', 'Texte du bouton', 'Adresse (URL)', 'Principal', 'Secondaire',
            'Centré', 'À gauche', 'Lien YouTube ou Vimeo', 'Lien vidéo non reconnu', 'Langage (facultatif)', 'Grand titre', 'Phrase d’accroche',
            'Bouton principal', 'Lien du bouton', 'Bouton secondaire', 'Lien du bouton secondaire', 'Ajouter une image', 'Changer l’image',
            'Retirer l’image', 'Titre de la section (facultatif)', 'Ajouter un atout', 'Icône', 'Nombre d’articles', 'Toutes les catégories',
            'Le formulaire de contact s’affichera ici, avec protection anti-spam.', 'Texte du bouton (facultatif)',
            'Lien', 'Gras', 'Italique', 'Code', 'Surligner', 'Retirer le lien', 'Coller une adresse ou chercher une page…', 'Ouvrir dans un nouvel onglet',
            'Choisir', 'Médiathèque', 'Glisse tes images ici ou clique pour choisir', 'Envoi en cours…', 'Envoi terminé', 'Échec de l’envoi',
            'Texte alternatif enregistré.', 'Cette image est utilisée ici :', 'Supprimer quand même ?', 'Supprimer définitivement ?',
            'Lien copié !', 'Copier', 'Fermer', 'Aucune image pour l’instant.', 'Erreur réseau : vérifie ta connexion.',
            'Une version non enregistrée a été retrouvée. La restaurer ?', 'Version restaurée.', 'Tu as des modifications non enregistrées.',
            'Très faible', 'Faible', 'Correct', 'Solide', 'Excellent', 'Afficher', 'Masquer', 'Restaurer cette version ?',
            'Aucune révision pour l’instant.', 'Contenu supprimé.', 'Supprimer ce contenu ?', 'Rediriger l’ancienne adresse vers (facultatif) :',
            'Analyse en cours…', 'Session expirée : reconnecte-toi dans un autre onglet, puis réessaie.', 'Mots', 'min de lecture',
            'Sélectionner', 'Ajouter', 'Retirer', 'Vérification…', 'Protégé', 'Exposé !', 'Réécriture d’URL active', 'Réécriture d’URL inactive',
            'Raccourcis clavier', 'Copié !', 'Rechercher une image…', 'Lié à :', 'Aucune', 'Aucun', 'Restaurer', 'Publié ✓', 'Programmé ✓',
            'Passer en brouillon', 'Nouvelle version disponible', 'Choisir une image', 'Sans titre', 'Articles', 'Pages', 'Supprimer la ligne',
            'Image introuvable', 'Glisse une image ici ou clique pour choisir', 'Texte du lien', 'Nouvel onglet',
            'Astuce', 'Bon à savoir', 'Attention', 'À retenir', 'Note', 'Catégorie',
            // Assistant IA
            'Demande un article, une page, une image…', 'Ton message à l’assistant', 'Envoyer (Entrée)', 'Arrêter', 'Joindre une image',
            'L’IA peut se tromper : relis avant de publier.', 'Assistant IA', 'Assistant', 'Conversations', 'Nouvelle conversation', 'Fermer (Échap)',
            'Ajouter une FAQ', 'Ajoute une FAQ à ce contenu.', 'Introduction plus percutante', 'Rends l’introduction plus percutante.',
            'Optimiser pour Google et les IA', 'Optimise ce contenu pour Google et les IA (titre, L’essentiel, méta-description).',
            'Créer l’image à la une', 'Crée une image à la une pour ce contenu.', 'Écrire un article', 'Écris un article sur ',
            'Ajouter un membre à l’équipe', 'Ajoute à la page équipe : Prénom Nom, fonction, avec la photo jointe.', '5 idées d’articles',
            'Propose-moi 5 idées d’articles pour attirer de nouveaux clients.', 'Améliorer ma page d’accueil',
            'Relis ma page d’accueil et propose des améliorations.', 'Bonjour {name} ! Que préparons-nous aujourd’hui ?',
            'J’écris dans le ton de ton site et je prépare des brouillons : tu relis, tu publies.', 'Chargement…',
            'Aucune conversation pour l’instant.', 'Supprimer cette conversation ?', 'Page', 'Article', 'Modification annulée.', 'Supprimé depuis.',
            'mots', 'Ouvrir', 'Publier…', 'Image ajoutée à la médiathèque', 'Image à la une', 'Image à la une mise à jour.',
            'Modification proposée : « {title} »', 'Appliquée', 'Ignorée', 'Proposition expirée', 'Appliquer', 'Voir', 'Ignorer',
            '« {title} » mis à jour', 'Modifié dans l’éditeur : pense à enregistrer', 'Date de publication', 'Publier maintenant',
            'Publier « {title} » maintenant ?', 'ou', 'Réflexion…', 'Demande interrompue.', 'Conversation : {cost}', 'Équipe',
            'Portraits, fonctions et présentations', 'Prénom et nom', 'Fonction', 'Présentation en une ou deux phrases', 'Lien du profil (LinkedIn…)',
            'Choisir la photo', 'Retirer la photo', 'Ajouter une personne', 'L’IA retravaille le passage…', 'Passage réécrit.', 'Améliorer',
            'Corriger les fautes', 'Raccourcir', 'Développer', 'Simplifier', 'Plus chaleureux', 'Plus professionnel', 'Retoucher avec l’IA',
            'Traduire…', 'Rédiger', 'Rédiger « L’essentiel » avec l’IA', 'Titre SEO et description', 'Expression-clé', 'Idées de titres',
            'Titre mis à jour.', 'FAQ ajoutée : relis les réponses.', 'Textes alternatifs', 'Toutes les images ont un texte alternatif.',
            'Textes alternatifs ajoutés.', 'Améliorer avec l’assistant', 'Analyse ce contenu et améliore ses points faibles pour le SEO et le GEO.',
            'Créer avec l’IA', 'Création de l’image (10 à 60 secondes)…', 'Image à la une créée.', 'Décrire avec l’IA',
            'Remplacer le profil actuel par celui rédigé par l’IA ?', 'L’IA lit ton site… (20 à 60 secondes)',
            'Profil rédigé : relis-le, ajuste-le, puis enregistre.',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = t($k);
        }
        return $out;
    }
}
