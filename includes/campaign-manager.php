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
            KEY sci_siren (sci_siren),
            FOREIGN KEY (campaign_id) REFERENCES {$this->campaigns_table}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_campaigns);
        dbDelta($sql_letters);
    }
    
    /**
     * ✅ NOUVELLE MÉTHODE : Récupère les SIRENs des SCI déjà contactées par l'utilisateur
     */
    public function get_user_contacted_sirens($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        // Récupérer tous les SIRENs des SCI pour lesquelles l'utilisateur a envoyé des lettres
        // (statut 'sent' ou 'processing' - on exclut 'failed' et 'pending')
        $sirens = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT cl.sci_siren 
             FROM {$this->campaign_letters_table} cl
             INNER JOIN {$this->campaigns_table} c ON cl.campaign_id = c.id
             WHERE c.user_id = %d 
             AND cl.status IN ('sent', 'processing')
             ORDER BY cl.sent_at DESC",
            $user_id
        ));
        
        return $sirens ? $sirens : array();
    }
    
    /**
     * ✅ NOUVELLE MÉTHODE : Récupère les détails des SCI contactées avec informations de campagne
     */
    public function get_user_contacted_sci_details($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        // Récupérer les détails complets des SCI contactées
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                cl.sci_siren,
                cl.sci_denomination,
                cl.sci_dirigeant,
                cl.status,
                cl.sent_at,
                cl.laposte_uid,
                c.title as campaign_title,
                c.created_at as campaign_date,
                COUNT(*) as contact_count,
                MAX(cl.sent_at) as last_contact_date
             FROM {$this->campaign_letters_table} cl
             INNER JOIN {$this->campaigns_table} c ON cl.campaign_id = c.id
             WHERE c.user_id = %d 
             AND cl.status IN ('sent', 'processing')
             GROUP BY cl.sci_siren
             ORDER BY last_contact_date DESC",
            $user_id
        ), ARRAY_A);
        
        // Organiser par SIREN pour un accès rapide
        $contacted_sci = array();
        foreach ($results as $result) {
            $contacted_sci[$result['sci_siren']] = $result;
        }
        
        return $contacted_sci;
    }
    
    /**
     * ✅ NOUVELLE MÉTHODE : Vérifie si une SCI spécifique a été contactée
     */
    public function is_sci_contacted($siren, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id || !$siren) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$this->campaign_letters_table} cl
             INNER JOIN {$this->campaigns_table} c ON cl.campaign_id = c.id
             WHERE c.user_id = %d 
             AND cl.sci_siren = %s
             AND cl.status IN ('sent', 'processing')",
            $user_id,
            $siren
        ));
        
        return $count > 0;
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
     * Compatible WordPress + WooCommerce + ACF
     */
    public function get_user_expedition_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('ID', $user_id);
        
        // Récupération des données depuis différentes sources
        $data = array();
        
        // === CIVILITÉ ===
        $data['civilite'] = $this->get_user_field($user_id, [
            'civilite_user',           // ACF personnalisé
            'billing_title',           // WooCommerce
            'user_title'              // WordPress standard
        ], 'M.');
        
        // === PRÉNOM ===
        $data['prenom'] = $this->get_user_field($user_id, [
            $user->first_name,         // WordPress natif
            'prenom_user',             // ACF personnalisé
            'billing_first_name',      // WooCommerce
            'first_name'               // WordPress meta
        ]);
        
        // === NOM ===
        $data['nom'] = $this->get_user_field($user_id, [
            $user->last_name,          // WordPress natif
            'nom_user',                // ACF personnalisé
            'billing_last_name',       // WooCommerce
            'last_name'                // WordPress meta
        ]);
        
        // === SOCIÉTÉ ===
        $data['nom_societe'] = $this->get_user_field($user_id, [
            'societe_user',            // ACF personnalisé
            'billing_company',         // WooCommerce
            'company',                 // WordPress meta
            'user_company'             // Autre champ possible
        ]);
        
        // === ADRESSE LIGNE 1 ===
        $data['adresse_ligne1'] = $this->get_user_field($user_id, [
            'adresse_user',            // ACF personnalisé
            'billing_address_1',       // WooCommerce
            'address_1',               // WordPress meta
            'user_address'             // Autre champ possible
        ]);
        
        // === ADRESSE LIGNE 2 ===
        $data['adresse_ligne2'] = $this->get_user_field($user_id, [
            'adresse2_user',           // ACF personnalisé
            'billing_address_2',       // WooCommerce
            'address_2'                // WordPress meta
        ]);
        
        // === CODE POSTAL ===
        $data['code_postal'] = $this->get_user_field($user_id, [
            'cp_user',                 // ACF personnalisé
            'billing_postcode',        // WooCommerce
            'postcode',                // WordPress meta
            'user_postcode'            // Autre champ possible
        ]);
        
        // === VILLE ===
        $data['ville'] = $this->get_user_field($user_id, [
            'ville_user',              // ACF personnalisé
            'billing_city',            // WooCommerce
            'city',                    // WordPress meta
            'user_city'                // Autre champ possible
        ]);
        
        // === PAYS ===
        $data['pays'] = $this->get_user_field($user_id, [
            'pays_user',               // ACF personnalisé
            'billing_country',         // WooCommerce
            'country'                  // WordPress meta
        ], 'FRANCE');
        
        // Log des données récupérées pour debug
        lettre_laposte_log("=== DONNÉES EXPÉDITEUR RÉCUPÉRÉES ===");
        lettre_laposte_log("User ID: $user_id");
        lettre_laposte_log("Données: " . json_encode($data, JSON_PRETTY_PRINT));
        
        // Validation des champs obligatoires
        $required_fields = ['adresse_ligne1', 'code_postal', 'ville'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        // Validation nom/prénom OU société
        if (empty($data['prenom']) && empty($data['nom']) && empty($data['nom_societe'])) {
            $missing_fields[] = 'nom/prénom ou société';
        }
        
        if (!empty($missing_fields)) {
            lettre_laposte_log("❌ CHAMPS MANQUANTS: " . implode(', ', $missing_fields));
        } else {
            lettre_laposte_log("✅ TOUTES LES DONNÉES REQUISES SONT PRÉSENTES");
        }
        
        return $data;
    }
    
    /**
     * Récupère une valeur depuis plusieurs sources possibles
     */
    private function get_user_field($user_id, $field_names, $default = '') {
        if (!is_array($field_names)) {
            $field_names = [$field_names];
        }
        
        foreach ($field_names as $field_name) {
            // Si c'est déjà une valeur (pas un nom de champ)
            if (!empty($field_name) && !is_string($field_name)) {
                return $field_name;
            }
            
            if (empty($field_name)) {
                continue;
            }
            
            // Essayer ACF en premier
            if (function_exists('get_field')) {
                $value = get_field($field_name, 'user_' . $user_id);
                if (!empty($value)) {
                    return $value;
                }
            }
            
            // Essayer les meta WordPress/WooCommerce
            $value = get_user_meta($user_id, $field_name, true);
            if (!empty($value)) {
                return $value;
            }
        }
        
        return $default;
    }
    
    /**
     * Valide les données d'expédition
     */
    public function validate_expedition_data($data) {
        $errors = [];
        
        // Vérifier les champs obligatoires
        if (empty($data['adresse_ligne1'])) {
            $errors[] = 'Adresse ligne 1 manquante';
        }
        
        if (empty($data['code_postal'])) {
            $errors[] = 'Code postal manquant';
        }
        
        if (empty($data['ville'])) {
            $errors[] = 'Ville manquante';
        }
        
        // Vérifier nom/prénom OU société
        if (empty($data['prenom']) && empty($data['nom']) && empty($data['nom_societe'])) {
            $errors[] = 'Nom/prénom ou nom de société requis';
        }
        
        return $errors;
    }
    
    /**
     * Affiche un message d'aide pour configurer les champs utilisateur
     */
    public function get_configuration_help() {
        return "
        <div class='notice notice-info'>
            <h4>🔧 Configuration des données expéditeur</h4>
            <p>Le système recherche automatiquement vos données dans cet ordre :</p>
            <ul>
                <li><strong>Nom/Prénom :</strong> Profil WordPress → Champs ACF → WooCommerce</li>
                <li><strong>Adresse :</strong> Champs ACF → WooCommerce → WordPress</li>
                <li><strong>Société :</strong> Champs ACF → WooCommerce</li>
            </ul>
            <p><strong>Champs ACF supportés :</strong> civilite_user, prenom_user, nom_user, societe_user, adresse_user, adresse2_user, cp_user, ville_user, pays_user</p>
            <p><strong>Champs WooCommerce supportés :</strong> billing_first_name, billing_last_name, billing_company, billing_address_1, billing_address_2, billing_postcode, billing_city, billing_country</p>
        </div>";
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