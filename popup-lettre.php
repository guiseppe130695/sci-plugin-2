<?php
if (!defined('ABSPATH')) exit;

/**
 * Fichier pour la gestion des popups de lettres
 * Ce fichier contient les fonctions nécessaires pour le système de campagnes de lettres
 */

// Fonction pour afficher le popup de sélection des lettres (déjà intégré dans le fichier principal)
// Cette fonction peut être étendue si nécessaire

/**
 * Fonction utilitaire pour logger les actions de lettres
 */
function lettre_laposte_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[SCI Lettre] ' . $message);
    }
}

/**
 * Fonction pour valider les données de campagne
 */
function validate_campaign_data($data) {
    $errors = [];
    
    if (empty($data['title'])) {
        $errors[] = 'Le titre de la campagne est requis';
    }
    
    if (empty($data['content'])) {
        $errors[] = 'Le contenu de la lettre est requis';
    }
    
    if (empty($data['entries']) || !is_array($data['entries'])) {
        $errors[] = 'Aucune SCI sélectionnée';
    }
    
    return $errors;
}

/**
 * Fonction pour préparer les données d'expédition
 */
function prepare_expedition_data($user_data = null) {
    $current_user = wp_get_current_user();
    
    return [
        'prenom' => $user_data['prenom'] ?? $current_user->first_name ?? '',
        'nom' => $user_data['nom'] ?? $current_user->last_name ?? '',
        'adresse' => $user_data['adresse'] ?? '',
        'code_postal' => $user_data['code_postal'] ?? '',
        'ville' => $user_data['ville'] ?? '',
    ];
}