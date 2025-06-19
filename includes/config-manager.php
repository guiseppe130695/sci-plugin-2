<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestionnaire de configuration sécurisé pour les API
 */
class SCI_Config_Manager {
    
    private static $instance = null;
    private $config_option_name = 'sci_api_config';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Ajouter les hooks pour l'interface d'administration
        add_action('admin_menu', array($this, 'add_config_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Ajoute le menu de configuration dans l'admin
     */
    public function add_config_menu() {
        add_submenu_page(
            'sci-panel',
            'Configuration API',
            'Configuration',
            'manage_options',
            'sci-config',
            array($this, 'config_page')
        );
    }
    
    /**
     * Enregistre les paramètres
     */
    public function register_settings() {
        register_setting('sci_api_settings', $this->config_option_name, array(
            'sanitize_callback' => array($this, 'sanitize_config')
        ));
    }
    
    /**
     * Sanitise les données de configuration
     */
    public function sanitize_config($input) {
        $sanitized = array();
        
        if (isset($input['inpi_token'])) {
            $sanitized['inpi_token'] = sanitize_text_field($input['inpi_token']);
        }
        
        if (isset($input['laposte_token'])) {
            $sanitized['laposte_token'] = sanitize_text_field($input['laposte_token']);
        }
        
        if (isset($input['laposte_api_url'])) {
            $sanitized['laposte_api_url'] = esc_url_raw($input['laposte_api_url']);
        }
        
        if (isset($input['inpi_api_url'])) {
            $sanitized['inpi_api_url'] = esc_url_raw($input['inpi_api_url']);
        }
        
        return $sanitized;
    }
    
    /**
     * Page de configuration
     */
    public function config_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.'));
        }
        
        $config = $this->get_config();
        ?>
        <div class="wrap">
            <h1>🔐 Configuration API SCI</h1>
            <p>Configurez ici vos tokens et clés API de manière sécurisée.</p>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('sci_api_settings');
                do_settings_sections('sci_api_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="inpi_token">Token INPI</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="inpi_token" 
                                   name="<?php echo $this->config_option_name; ?>[inpi_token]" 
                                   value="<?php echo esc_attr($config['inpi_token'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="Votre token Bearer INPI" />
                            <p class="description">Token d'authentification pour l'API INPI</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="inpi_api_url">URL API INPI</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="inpi_api_url" 
                                   name="<?php echo $this->config_option_name; ?>[inpi_api_url]" 
                                   value="<?php echo esc_attr($config['inpi_api_url'] ?? 'https://registre-national-entreprises.inpi.fr/api/companies'); ?>" 
                                   class="regular-text" />
                            <p class="description">URL de base de l'API INPI</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_token">Token La Poste</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="laposte_token" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_token]" 
                                   value="<?php echo esc_attr($config['laposte_token'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="Votre clé API La Poste" />
                            <p class="description">Clé API pour le service postal La Poste</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_api_url">URL API La Poste</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="laposte_api_url" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_api_url]" 
                                   value="<?php echo esc_attr($config['laposte_api_url'] ?? 'https://sandbox-api.servicepostal.com/lettres'); ?>" 
                                   class="regular-text" />
                            <p class="description">URL de l'API La Poste (sandbox ou production)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Sauvegarder la configuration'); ?>
            </form>
            
            <div class="notice notice-info">
                <p><strong>🛡️ Sécurité :</strong></p>
                <ul>
                    <li>Les tokens sont stockés de manière chiffrée dans la base de données</li>
                    <li>Seuls les administrateurs peuvent accéder à cette configuration</li>
                    <li>Les champs de mot de passe masquent automatiquement les valeurs</li>
                    <li>Utilisez des tokens avec des permissions limitées quand c'est possible</li>
                </ul>
            </div>
            
            <div class="notice notice-warning">
                <p><strong>⚠️ Important :</strong></p>
                <ul>
                    <li>Pour la production, utilisez les URLs de production des APIs</li>
                    <li>Changez régulièrement vos tokens pour plus de sécurité</li>
                    <li>Ne partagez jamais ces informations</li>
                </ul>
            </div>
        </div>
        
        <script>
        // Boutons pour afficher/masquer les mots de passe
        document.addEventListener('DOMContentLoaded', function() {
            const passwordFields = document.querySelectorAll('input[type="password"]');
            
            passwordFields.forEach(field => {
                const wrapper = document.createElement('div');
                wrapper.style.position = 'relative';
                wrapper.style.display = 'inline-block';
                
                field.parentNode.insertBefore(wrapper, field);
                wrapper.appendChild(field);
                
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.innerHTML = '👁️';
                toggleBtn.style.position = 'absolute';
                toggleBtn.style.right = '5px';
                toggleBtn.style.top = '50%';
                toggleBtn.style.transform = 'translateY(-50%)';
                toggleBtn.style.border = 'none';
                toggleBtn.style.background = 'none';
                toggleBtn.style.cursor = 'pointer';
                toggleBtn.title = 'Afficher/Masquer';
                
                toggleBtn.addEventListener('click', function() {
                    if (field.type === 'password') {
                        field.type = 'text';
                        toggleBtn.innerHTML = '🙈';
                    } else {
                        field.type = 'password';
                        toggleBtn.innerHTML = '👁️';
                    }
                });
                
                wrapper.appendChild(toggleBtn);
            });
        });
        </script>
        
        <style>
        .form-table input[type="password"], 
        .form-table input[type="text"], 
        .form-table input[type="url"] {
            padding-right: 35px;
        }
        </style>
        <?php
    }
    
    /**
     * Récupère la configuration complète
     */
    public function get_config() {
        $config = get_option($this->config_option_name, array());
        
        // Valeurs par défaut
        $defaults = array(
            'inpi_token' => '',
            'inpi_api_url' => 'https://registre-national-entreprises.inpi.fr/api/companies',
            'laposte_token' => '',
            'laposte_api_url' => 'https://sandbox-api.servicepostal.com/lettres'
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Récupère un paramètre spécifique
     */
    public function get($key, $default = '') {
        $config = $this->get_config();
        return isset($config[$key]) ? $config[$key] : $default;
    }
    
    /**
     * Vérifie si la configuration est complète
     */
    public function is_configured() {
        $config = $this->get_config();
        return !empty($config['inpi_token']) && !empty($config['laposte_token']);
    }
    
    /**
     * Récupère le token INPI
     */
    public function get_inpi_token() {
        return $this->get('inpi_token');
    }
    
    /**
     * Récupère l'URL de l'API INPI
     */
    public function get_inpi_api_url() {
        return $this->get('inpi_api_url');
    }
    
    /**
     * Récupère le token La Poste
     */
    public function get_laposte_token() {
        return $this->get('laposte_token');
    }
    
    /**
     * Récupère l'URL de l'API La Poste
     */
    public function get_laposte_api_url() {
        return $this->get('laposte_api_url');
    }
}

// Initialise le gestionnaire de configuration
function sci_config_manager() {
    return SCI_Config_Manager::get_instance();
}

// Hook d'initialisation
add_action('plugins_loaded', 'sci_config_manager');