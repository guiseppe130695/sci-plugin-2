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
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/lettre-laposte';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    $logfile = $log_dir . '/logs.txt';
    $datetime = date('Y-m-d H:i:s');
    error_log("[$datetime] $message\n", 3, $logfile);
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

/**
 * Fonction pour formater l'adresse destinataire pour l'API La Poste
 */
function format_destination_address($entry) {
    return [
        "civilite" => "", // Pas de civilité pour les SCI
        "prenom" => "",   // Pas de prénom pour les SCI
        "nom" => $entry['dirigeant'] ?? '',
        "nom_societe" => $entry['denomination'] ?? '',
        "adresse_ligne1" => $entry['adresse'] ?? '',
        "adresse_ligne2" => "",
        "code_postal" => $entry['code_postal'] ?? '',
        "ville" => $entry['ville'] ?? '',
        "pays" => "FRANCE",
    ];
}

/**
 * Fonction pour formater l'adresse expéditeur depuis le profil utilisateur
 */
function format_expedition_address($user_id) {
    $current_user = get_user_by('ID', $user_id);
    
    return [
        "civilite" => get_field('civilite_user', 'user_' . $user_id) ?? 'M.',
        "prenom" => $current_user->first_name ?? '',
        "nom" => $current_user->last_name ?? '',
        "nom_societe" => get_field('societe_user', 'user_' . $user_id) ?? '',
        "adresse_ligne1" => get_field('adresse_user', 'user_' . $user_id) ?? '',
        "adresse_ligne2" => get_field('adresse2_user', 'user_' . $user_id) ?? '',
        "code_postal" => get_field('cp_user', 'user_' . $user_id) ?? '',
        "ville" => get_field('ville_user', 'user_' . $user_id) ?? '',
        "pays" => "FRANCE",
    ];
}