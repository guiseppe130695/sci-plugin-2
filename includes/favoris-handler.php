<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestionnaire des favoris SCI
 */
class SCI_Favoris_Handler {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sci_favoris';
        
        // Créer la table lors de l'activation du plugin
        add_action('init', array($this, 'create_favoris_table'));
    }
    
    /**
     * Crée la table des favoris si elle n'existe pas
     */
    public function create_favoris_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            siren varchar(20) NOT NULL,
            denomination text NOT NULL,
            dirigeant text,
            adresse text,
            ville varchar(100),
            code_postal varchar(10),
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_siren (user_id, siren),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Ajoute un favori pour l'utilisateur courant
     */
    public function add_favori($sci_data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Utilisateur non connecté');
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'siren' => sanitize_text_field($sci_data['siren']),
                'denomination' => sanitize_text_field($sci_data['denomination']),
                'dirigeant' => sanitize_text_field($sci_data['dirigeant']),
                'adresse' => sanitize_text_field($sci_data['adresse']),
                'ville' => sanitize_text_field($sci_data['ville']),
                'code_postal' => sanitize_text_field($sci_data['code_postal'])
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de l\'ajout en base de données');
        }
        
        return true;
    }
    
    /**
     * Supprime un favori pour l'utilisateur courant
     */
    public function remove_favori($siren) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Utilisateur non connecté');
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'siren' => sanitize_text_field($siren)
            ),
            array('%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la suppression');
        }
        
        return true;
    }
    
    /**
     * Récupère tous les favoris de l'utilisateur courant
     */
    public function get_favoris() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array();
        }
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT siren, denomination, dirigeant, adresse, ville, code_postal 
                 FROM {$this->table_name} 
                 WHERE user_id = %d 
                 ORDER BY date_added DESC",
                $user_id
            ),
            ARRAY_A
        );
        
        return $results ? $results : array();
    }
    
    /**
     * Vérifie si un SIREN est en favori pour l'utilisateur courant
     */
    public function is_favori($siren) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE user_id = %d AND siren = %s",
                $user_id,
                sanitize_text_field($siren)
            )
        );
        
        return $count > 0;
    }
}

// Initialise le gestionnaire de favoris
$sci_favoris_handler = new SCI_Favoris_Handler();

/**
 * Gestionnaire AJAX pour les favoris
 */
function sci_manage_favoris_ajax() {
    // Vérification du nonce
    if (!wp_verify_nonce($_POST['nonce'], 'sci_favoris_nonce')) {
        wp_send_json_error('Nonce invalide');
        return;
    }
    
    global $sci_favoris_handler;
    
    $operation = sanitize_text_field($_POST['operation']);
    
    switch ($operation) {
        case 'add':
            if (!isset($_POST['sci_data'])) {
                wp_send_json_error('Données manquantes');
                return;
            }
            
            $sci_data = json_decode(stripslashes($_POST['sci_data']), true);
            if (!$sci_data) {
                wp_send_json_error('Données invalides');
                return;
            }
            
            $result = $sci_favoris_handler->add_favori($sci_data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('Favori ajouté');
            }
            break;
            
        case 'remove':
            if (!isset($_POST['sci_data'])) {
                wp_send_json_error('Données manquantes');
                return;
            }
            
            $sci_data = json_decode(stripslashes($_POST['sci_data']), true);
            if (!$sci_data || !isset($sci_data['siren'])) {
                wp_send_json_error('SIREN manquant');
                return;
            }
            
            $result = $sci_favoris_handler->remove_favori($sci_data['siren']);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('Favori supprimé');
            }
            break;
            
        case 'get':
            $favoris = $sci_favoris_handler->get_favoris();
            wp_send_json_success($favoris);
            break;
            
        default:
            wp_send_json_error('Opération invalide');
    }
}

add_action('wp_ajax_sci_manage_favoris', 'sci_manage_favoris_ajax');
add_action('wp_ajax_nopriv_sci_manage_favoris', 'sci_manage_favoris_ajax');