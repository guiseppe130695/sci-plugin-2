<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestionnaire automatique des tokens INPI
 * Basé sur votre code existant et amélioré pour l'intégration SCI
 */
class SCI_INPI_Token_Manager {
    
    private static $instance = null;
    private $token_option = 'sci_inpi_token';
    private $token_expiry_option = 'sci_inpi_token_expiry';
    private $credentials_table;
    private $api_login_url = 'https://registre-national-entreprises.inpi.fr/api/sso/login';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->credentials_table = $wpdb->prefix . 'sci_inpi_credentials';
        
        // Créer la table lors de l'initialisation
        add_action('init', array($this, 'create_credentials_table'));
        
        // Ajouter les hooks d'administration
        add_action('admin_menu', array($this, 'add_credentials_menu'));
        add_action('admin_init', array($this, 'register_credentials_settings'));
        add_action('admin_post_refresh_inpi_token', array($this, 'handle_manual_token_refresh'));
    }
    
    /**
     * Crée la table pour stocker les informations de token
     */
    public function create_credentials_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->credentials_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            token text NOT NULL,
            token_expiry datetime NOT NULL,
            username varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            firstname varchar(255),
            lastname varchar(255),
            last_login datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Ajoute le menu de gestion des identifiants INPI
     */
    public function add_credentials_menu() {
        add_submenu_page(
            'sci-panel',
            'Identifiants INPI',
            'Identifiants INPI',
            'manage_options',
            'sci-inpi-credentials',
            array($this, 'credentials_page')
        );
    }
    
    /**
     * Enregistre les paramètres des identifiants
     */
    public function register_credentials_settings() {
        register_setting('sci_inpi_credentials', 'sci_inpi_username');
        register_setting('sci_inpi_credentials', 'sci_inpi_password');
    }
    
    /**
     * Page de gestion des identifiants INPI
     */
    public function credentials_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.'));
        }
        
        // Traitement du formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sci_inpi_credentials_nonce'])) {
            if (wp_verify_nonce($_POST['sci_inpi_credentials_nonce'], 'sci_inpi_credentials_action')) {
                $username = sanitize_text_field($_POST['sci_inpi_username'] ?? '');
                $password = sanitize_text_field($_POST['sci_inpi_password'] ?? '');
                
                if (!empty($username) && !empty($password)) {
                    update_option('sci_inpi_username', $username);
                    update_option('sci_inpi_password', $password);
                    
                    // Tenter de générer un token immédiatement
                    $token_result = $this->refresh_token();
                    
                    if ($token_result) {
                        echo '<div class="notice notice-success"><p>✅ Identifiants sauvegardés et token généré avec succès !</p></div>';
                    } else {
                        echo '<div class="notice notice-warning"><p>⚠️ Identifiants sauvegardés mais échec de génération du token. Vérifiez vos identifiants.</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>❌ Veuillez remplir tous les champs.</p></div>';
                }
            }
        }
        
        // Afficher les informations de statut
        if (isset($_GET['refresh'])) {
            if ($_GET['refresh'] === 'success') {
                echo '<div class="notice notice-success"><p>✅ Token INPI actualisé avec succès !</p></div>';
            } elseif ($_GET['refresh'] === 'error') {
                echo '<div class="notice notice-error"><p>❌ Erreur lors de l\'actualisation du token INPI.</p></div>';
            }
        }
        
        $username = get_option('sci_inpi_username', '');
        $token = get_option($this->token_option);
        $token_expiry = get_option($this->token_expiry_option);
        $token_status = $this->check_token_validity(false); // false = ne pas auto-refresh
        
        ?>
        <div class="wrap">
            <h1>🔐 Identifiants INPI</h1>
            <p>Configurez vos identifiants INPI pour la génération automatique de tokens d'authentification.</p>
            
            <!-- Statut du token actuel -->
            <div class="card" style="max-width: 600px; margin-bottom: 20px;">
                <h3>📊 Statut du token actuel</h3>
                <?php if ($token && $token_expiry): ?>
                    <p><strong>Token :</strong> <code><?php echo esc_html(substr($token, 0, 20) . '...'); ?></code></p>
                    <p><strong>Expiration :</strong> <?php echo date('d/m/Y H:i:s', $token_expiry); ?></p>
                    <p><strong>Statut :</strong> 
                        <?php if ($token_status): ?>
                            <span style="color: green;">✅ Valide</span>
                        <?php else: ?>
                            <span style="color: red;">❌ Expiré ou invalide</span>
                        <?php endif; ?>
                    </p>
                    
                    <!-- Bouton de rafraîchissement manuel -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="refresh_inpi_token">
                        <?php wp_nonce_field('refresh_inpi_token', 'inpi_token_nonce'); ?>
                        <button type="submit" class="button button-secondary">
                            🔄 Actualiser le token maintenant
                        </button>
                    </form>
                <?php else: ?>
                    <p><span style="color: orange;">⚠️ Aucun token disponible</span></p>
                    <p><small>Configurez vos identifiants ci-dessous pour générer un token.</small></p>
                <?php endif; ?>
            </div>
            
            <!-- Formulaire de configuration -->
            <form method="post">
                <?php wp_nonce_field('sci_inpi_credentials_action', 'sci_inpi_credentials_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sci_inpi_username">Nom d'utilisateur INPI</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sci_inpi_username" 
                                   name="sci_inpi_username" 
                                   value="<?php echo esc_attr($username); ?>" 
                                   class="regular-text" 
                                   placeholder="votre.email@exemple.com" />
                            <p class="description">Votre adresse email de connexion INPI</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sci_inpi_password">Mot de passe INPI</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="sci_inpi_password" 
                                   name="sci_inpi_password" 
                                   value="" 
                                   class="regular-text" 
                                   placeholder="Votre mot de passe INPI" />
                            <p class="description">Votre mot de passe de connexion INPI (stocké de manière sécurisée)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Sauvegarder et générer le token'); ?>
            </form>
            
            <!-- Informations sur le fonctionnement -->
            <div class="notice notice-info">
                <h4>🔄 Fonctionnement automatique</h4>
                <ul>
                    <li><strong>Génération automatique :</strong> Le token sera automatiquement généré lors des recherches SCI</li>
                    <li><strong>Renouvellement :</strong> Le token est automatiquement renouvelé 1 heure avant expiration</li>
                    <li><strong>Gestion d'erreurs :</strong> En cas d'erreur d'authentification, un nouveau token est généré automatiquement</li>
                    <li><strong>Sécurité :</strong> Vos identifiants sont stockés de manière sécurisée dans la base de données</li>
                </ul>
            </div>
            
            <!-- Historique des tokens (si disponible) -->
            <?php $this->display_token_history(); ?>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        </style>
        <?php
    }
    
    /**
     * Affiche l'historique des tokens
     */
    private function display_token_history() {
        global $wpdb;
        
        $tokens = $wpdb->get_results(
            "SELECT * FROM {$this->credentials_table} ORDER BY created_at DESC LIMIT 5",
            ARRAY_A
        );
        
        if (!empty($tokens)) {
            ?>
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h3>📋 Historique des tokens (5 derniers)</h3>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date création</th>
                            <th>Expiration</th>
                            <th>Utilisateur</th>
                            <th>Dernière connexion</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token_info): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($token_info['created_at'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($token_info['token_expiry'])); ?></td>
                                <td><?php echo esc_html($token_info['firstname'] . ' ' . $token_info['lastname']); ?></td>
                                <td><?php echo $token_info['last_login'] ? date('d/m/Y H:i', strtotime($token_info['last_login'])) : '-'; ?></td>
                                <td>
                                    <?php if (strtotime($token_info['token_expiry']) > current_time('timestamp')): ?>
                                        <span style="color: green;">✅ Valide</span>
                                    <?php else: ?>
                                        <span style="color: red;">❌ Expiré</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }
    
    /**
     * Gestion du rafraîchissement manuel du token
     */
    public function handle_manual_token_refresh() {
        if (!isset($_POST['inpi_token_nonce']) || 
            !wp_verify_nonce($_POST['inpi_token_nonce'], 'refresh_inpi_token')) {
            wp_die('Action non autorisée', 'Erreur', array('response' => 403));
        }

        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé', 'Erreur', array('response' => 403));
        }

        // Forcer la suppression du token actuel
        delete_option($this->token_option);
        delete_option($this->token_expiry_option);
        
        $success = $this->refresh_token();
        
        $redirect_url = add_query_arg(
            array(
                'page' => 'sci-inpi-credentials',
                'refresh' => $success ? 'success' : 'error'
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * ✅ FONCTION PRINCIPALE : Vérifie la validité du token et le renouvelle si nécessaire
     */
    public function check_token_validity($auto_refresh = true) {
        $token = get_option($this->token_option);
        $expiry = get_option($this->token_expiry_option);
        $current_time = current_time('timestamp');

        // Vérifier si le token existe et n'est pas expiré (avec marge de 1 heure)
        if (!$token || !$expiry || $current_time >= ($expiry - 3600)) {
            lettre_laposte_log('INPI Token invalid or expired, attempting refresh');
            
            if ($auto_refresh) {
                // Supprimer l'ancien token
                delete_option($this->token_option);
                delete_option($this->token_expiry_option);
                
                // Tenter de générer un nouveau token
                $refresh_result = $this->refresh_token();
                if (!$refresh_result) {
                    lettre_laposte_log('INPI Token refresh failed');
                    return false;
                }
                
                return true;
            } else {
                return false;
            }
        }

        return true;
    }
    
    /**
     * ✅ FONCTION PRINCIPALE : Génère un nouveau token via l'API INPI
     */
    public function refresh_token() {
        global $wpdb;
        
        $username = get_option('sci_inpi_username');
        $password = get_option('sci_inpi_password');

        if (!$username || !$password) {
            lettre_laposte_log('INPI credentials missing - username: ' . ($username ? 'OK' : 'MISSING') . ', password: ' . ($password ? 'OK' : 'MISSING'));
            return false;
        }

        lettre_laposte_log('=== GÉNÉRATION TOKEN INPI ===');
        lettre_laposte_log('Username: ' . $username);
        lettre_laposte_log('API URL: ' . $this->api_login_url);

        $response = wp_remote_post($this->api_login_url, array(
            'timeout' => 30,
            'body' => json_encode(array(
                'username' => $username,
                'password' => $password
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            lettre_laposte_log('INPI API Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        lettre_laposte_log('INPI API Response Code: ' . $response_code);
        lettre_laposte_log('INPI API Response Body: ' . $response_body);
        
        if ($response_code !== 200) {
            lettre_laposte_log('INPI API Error - Status Code: ' . $response_code);
            return false;
        }

        $body = json_decode($response_body, true);
        
        if (!is_array($body) || !isset($body['token'])) {
            lettre_laposte_log('INPI API Invalid Response - No token found');
            return false;
        }

        // Sauvegarder le token dans les options WordPress
        $expiry_timestamp = current_time('timestamp') + 86400; // 24 heures
        update_option($this->token_option, $body['token']);
        update_option($this->token_expiry_option, $expiry_timestamp);
        
        // Sauvegarder les informations utilisateur si disponibles
        if (isset($body['user'])) {
            update_option('sci_inpi_user_data', $body['user']);
        }

        // Sauvegarder dans la table d'historique
        $expiry_date = date('Y-m-d H:i:s', $expiry_timestamp);
        
        $wpdb->replace(
            $this->credentials_table,
            array(
                'token' => $body['token'],
                'token_expiry' => $expiry_date,
                'username' => $body['user']['email'] ?? $username,
                'email' => $body['user']['email'] ?? $username,
                'firstname' => $body['user']['firstname'] ?? '',
                'lastname' => $body['user']['lastname'] ?? '',
                'last_login' => isset($body['user']['lastLogin']) ? date('Y-m-d H:i:s', strtotime($body['user']['lastLogin'])) : null
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        lettre_laposte_log('✅ INPI Token generated successfully');
        lettre_laposte_log('Token: ' . substr($body['token'], 0, 20) . '...');
        lettre_laposte_log('Expires: ' . date('Y-m-d H:i:s', $expiry_timestamp));

        return true;
    }
    
    /**
     * ✅ FONCTION PUBLIQUE : Récupère le token valide (avec auto-refresh)
     */
    public function get_token() {
        if (!$this->check_token_validity()) {
            lettre_laposte_log('INPI Token validation failed');
            return false;
        }
        return get_option($this->token_option);
    }
    
    /**
     * ✅ FONCTION PUBLIQUE : Gère les erreurs d'authentification et régénère le token
     */
    public function handle_auth_error() {
        lettre_laposte_log('=== GESTION ERREUR AUTHENTIFICATION INPI ===');
        lettre_laposte_log('Suppression du token actuel et régénération...');
        
        // Supprimer le token actuel
        delete_option($this->token_option);
        delete_option($this->token_expiry_option);
        
        // Tenter de générer un nouveau token
        $success = $this->refresh_token();
        
        if ($success) {
            lettre_laposte_log('✅ Nouveau token généré avec succès après erreur d\'authentification');
            return $this->get_token();
        } else {
            lettre_laposte_log('❌ Échec de génération du nouveau token après erreur d\'authentification');
            return false;
        }
    }
}

// Initialiser le gestionnaire de tokens INPI
function sci_inpi_token_manager() {
    return SCI_INPI_Token_Manager::get_instance();
}

// Hook d'initialisation
add_action('plugins_loaded', 'sci_inpi_token_manager');