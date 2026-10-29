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
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = t($k);
        }
        return $out;
    }
}
