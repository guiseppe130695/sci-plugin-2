<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestionnaire des campagnes de lettres
 */
class SCI_Campaign_Manager {
    
    private $campaigns_table;
    private $campaign_letters_table;
    
    public function __construct() {
        global $wpdb;
        $this->campaigns_table = $wpdb->prefix . 'sci_campaigns';
        $this->campaign_letters_table = $wpdb->prefix . 'sci_campaign_letters';
        
        // Créer les tables lors de l'activation
        add_action('init', array($this, 'create_tables'));
    }
    
    /**
     * Crée les tables des campagnes
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table des campagnes
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS {$this->campaigns_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            status varchar(50) DEFAULT 'draft',
            total_letters int(11) DEFAULT 0,
            sent_letters int(11) DEFAULT 0,
            failed_letters int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Table des lettres individuelles
        $sql_letters = "CREATE TABLE IF NOT EXISTS {$this->campaign_letters_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            sci_siren varchar(20) NOT NULL,
            sci_denomination varchar(255) NOT NULL,
            sci_dirigeant varchar(255),
            sci_adresse text,
            sci_ville varchar(100),
            sci_code_postal varchar(10),
            laposte_uid varchar(100),
            status varchar(50) DEFAULT 'pending',
            error_message text,
            sent_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status),
            KEY laposte_uid (laposte_uid),
            FOREIGN KEY (campaign_id) REFERENCES {$this->campaigns_table}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_campaigns);
        dbDelta($sql_letters);
    }
    
    /**
     * Crée une nouvelle campagne
     */
    public function create_campaign($title, $content, $entries) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Utilisateur non connecté');
        }
        
        // Insérer la campagne
        $result = $wpdb->insert(
            $this->campaigns_table,
            array(
                'user_id' => $user_id,
                'title' => sanitize_text_field($title),
                'content' => wp_kses_post($content),
                'status' => 'processing',
                'total_letters' => count($entries)
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la création de la campagne');
        }
        
        $campaign_id = $wpdb->insert_id;
        
        // Insérer les lettres individuelles
        foreach ($entries as $entry) {
            $wpdb->insert(
                $this->campaign_letters_table,
                array(
                    'campaign_id' => $campaign_id,
                    'sci_siren' => sanitize_text_field($entry['siren']),
                    'sci_denomination' => sanitize_text_field($entry['denomination']),
                    'sci_dirigeant' => sanitize_text_field($entry['dirigeant']),
                    'sci_adresse' => sanitize_text_field($entry['adresse']),
                    'sci_ville' => sanitize_text_field($entry['ville']),
                    'sci_code_postal' => sanitize_text_field($entry['code_postal']),
                    'status' => 'pending'
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        return $campaign_id;
    }
    
    /**
     * Met à jour le statut d'une lettre
     */
    public function update_letter_status($campaign_id, $siren, $status, $laposte_uid = null, $error_message = null) {
        global $wpdb;
        
        $data = array(
            'status' => $status
        );
        $format = array('%s');
        
        if ($laposte_uid) {
            $data['laposte_uid'] = $laposte_uid;
            $data['sent_at'] = current_time('mysql');
            $format[] = '%s';
            $format[] = '%s';
        }
        
        if ($error_message) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }
        
        $result = $wpdb->update(
            $this->campaign_letters_table,
            $data,
            array(
                'campaign_id' => $campaign_id,
                'sci_siren' => $siren
            ),
            $format,
            array('%d', '%s')
        );
        
        // Mettre à jour les compteurs de la campagne
        $this->update_campaign_counters($campaign_id);
        
        return $result;
    }
    
    /**
     * Met à jour les compteurs de la campagne
     */
    private function update_campaign_counters($campaign_id) {
        global $wpdb;
        
        $sent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->campaign_letters_table} WHERE campaign_id = %d AND status = 'sent'",
            $campaign_id
        ));
        
        $failed_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->campaign_letters_table} WHERE campaign_id = %d AND status = 'failed'",
            $campaign_id
        ));
        
        $total_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->campaign_letters_table} WHERE campaign_id = %d",
            $campaign_id
        ));
        
        // Déterminer le statut de la campagne
        $status = 'processing';
        if ($sent_count + $failed_count >= $total_count) {
            $status = ($failed_count > 0) ? 'completed_with_errors' : 'completed';
        }
        
        $wpdb->update(
            $this->campaigns_table,
            array(
                'sent_letters' => $sent_count,
                'failed_letters' => $failed_count,
                'status' => $status
            ),
            array('id' => $campaign_id),
            array('%d', '%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Récupère toutes les campagnes de l'utilisateur
     */
    public function get_user_campaigns($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Récupère les détails d'une campagne
     */
    public function get_campaign_details($campaign_id) {
        global $wpdb;
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table} WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
        
        if (!$campaign) {
            return null;
        }
        
        $letters = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->campaign_letters_table} WHERE campaign_id = %d ORDER BY created_at ASC",
            $campaign_id
        ), ARRAY_A);
        
        $campaign['letters'] = $letters;
        
        return $campaign;
    }
    
    /**
     * Récupère les données utilisateur pour l'expédition
     */
    public function get_user_expedition_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('ID', $user_id);
        
        return array(
            "civilite" => get_field('civilite_user', 'user_' . $user_id) ?: 'M.',
            "prenom" => $user->first_name ?: get_field('prenom_user', 'user_' . $user_id) ?: '',
            "nom" => $user->last_name ?: get_field('nom_user', 'user_' . $user_id) ?: '',
            "nom_societe" => get_field('societe_user', 'user_' . $user_id) ?: '',
            "adresse_ligne1" => get_field('adresse_user', 'user_' . $user_id) ?: '',
            "adresse_ligne2" => get_field('adresse2_user', 'user_' . $user_id) ?: '',
            "code_postal" => get_field('cp_user', 'user_' . $user_id) ?: '',
            "ville" => get_field('ville_user', 'user_' . $user_id) ?: '',
            "pays" => "FRANCE",
        );
    }
}

// Initialise le gestionnaire de campagnes
$sci_campaign_manager = new SCI_Campaign_Manager();

/**
 * Fonction helper pour accéder au gestionnaire
 */
function sci_campaign_manager() {
    global $sci_campaign_manager;
    return $sci_campaign_manager;
}