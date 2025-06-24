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
        
        // Tokens et URLs
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
        
        // ✅ NOUVEAU : URLs des pages shortcodes
        if (isset($input['sci_panel_page_url'])) {
            $sanitized['sci_panel_page_url'] = esc_url_raw($input['sci_panel_page_url']);
        }
        
        if (isset($input['sci_favoris_page_url'])) {
            $sanitized['sci_favoris_page_url'] = esc_url_raw($input['sci_favoris_page_url']);
        }
        
        if (isset($input['sci_campaigns_page_url'])) {
            $sanitized['sci_campaigns_page_url'] = esc_url_raw($input['sci_campaigns_page_url']);
        }
        
        // Paramètres La Poste - Validation des énumérations
        $valid_affranchissement = ['lrar', 'lr', 'prioritaire', 'suivi', 'verte', 'vertesuivi', 'ecopli', 'performance', 'perfsuivi'];
        if (isset($input['laposte_type_affranchissement']) && in_array($input['laposte_type_affranchissement'], $valid_affranchissement)) {
            $sanitized['laposte_type_affranchissement'] = $input['laposte_type_affranchissement'];
        }
        
        $valid_enveloppe_type = ['c4', 'c5', 'c6', 'auto'];
        if (isset($input['laposte_type_enveloppe']) && in_array($input['laposte_type_enveloppe'], $valid_enveloppe_type)) {
            $sanitized['laposte_type_enveloppe'] = $input['laposte_type_enveloppe'];
        }
        
        $valid_enveloppe = ['fenetre', 'imprime'];
        if (isset($input['laposte_enveloppe']) && in_array($input['laposte_enveloppe'], $valid_enveloppe)) {
            $sanitized['laposte_enveloppe'] = $input['laposte_enveloppe'];
        }
        
        $valid_couleur = ['nb', 'couleur'];
        if (isset($input['laposte_couleur']) && in_array($input['laposte_couleur'], $valid_couleur)) {
            $sanitized['laposte_couleur'] = $input['laposte_couleur'];
        }
        
        $valid_recto_verso = ['recto', 'rectoverso'];
        if (isset($input['laposte_recto_verso']) && in_array($input['laposte_recto_verso'], $valid_recto_verso)) {
            $sanitized['laposte_recto_verso'] = $input['laposte_recto_verso'];
        }
        
        $valid_placement = ['insertion_page_adresse', 'premiere_page'];
        if (isset($input['laposte_placement_adresse']) && in_array($input['laposte_placement_adresse'], $valid_placement)) {
            $sanitized['laposte_placement_adresse'] = $input['laposte_placement_adresse'];
        }
        
        // Paramètres booléens
        $sanitized['laposte_surimpression_adresses'] = isset($input['laposte_surimpression_adresses']) ? 1 : 0;
        $sanitized['laposte_impression_expediteur'] = isset($input['laposte_impression_expediteur']) ? 1 : 0;
        $sanitized['laposte_ar_scan'] = isset($input['laposte_ar_scan']) ? 1 : 0;
        
        // Champs texte optionnels
        if (isset($input['laposte_ar_champ1'])) {
            $sanitized['laposte_ar_champ1'] = sanitize_text_field(substr($input['laposte_ar_champ1'], 0, 38));
        }
        
        if (isset($input['laposte_ar_champ2'])) {
            $sanitized['laposte_ar_champ2'] = sanitize_text_field(substr($input['laposte_ar_champ2'], 0, 38));
        }
        
        if (isset($input['laposte_reference'])) {
            $sanitized['laposte_reference'] = sanitize_text_field(substr($input['laposte_reference'], 0, 50));
        }
        
        if (isset($input['laposte_nom_entite'])) {
            $sanitized['laposte_nom_entite'] = strtoupper(sanitize_text_field(substr($input['laposte_nom_entite'], 0, 100)));
        }
        
        if (isset($input['laposte_nom_dossier'])) {
            $sanitized['laposte_nom_dossier'] = sanitize_text_field(substr($input['laposte_nom_dossier'], 0, 50));
        }
        
        if (isset($input['laposte_nom_sousdossier'])) {
            $sanitized['laposte_nom_sousdossier'] = sanitize_text_field(substr($input['laposte_nom_sousdossier'], 0, 100));
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
            <p>Configurez ici vos tokens et paramètres API de manière sécurisée.</p>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('sci_api_settings');
                do_settings_sections('sci_api_settings');
                ?>
                
                <!-- ✅ NOUVELLE SECTION : URLs des pages shortcodes -->
                <h2>🔗 URLs des pages shortcodes</h2>
                <p>Configurez les liens vers vos pages contenant les shortcodes SCI. Ces URLs seront utilisées pour les redirections et liens internes.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sci_panel_page_url">Page principale SCI ([sci_panel])</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sci_panel_page_url" 
                                   name="<?php echo $this->config_option_name; ?>[sci_panel_page_url]" 
                                   value="<?php echo esc_attr($config['sci_panel_page_url'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="https://monsite.com/sci-recherche" />
                            <p class="description">URL complète de la page contenant le shortcode [sci_panel]</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sci_favoris_page_url">Page des favoris ([sci_favoris])</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sci_favoris_page_url" 
                                   name="<?php echo $this->config_option_name; ?>[sci_favoris_page_url]" 
                                   value="<?php echo esc_attr($config['sci_favoris_page_url'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="https://monsite.com/mes-favoris" />
                            <p class="description">URL complète de la page contenant le shortcode [sci_favoris]</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sci_campaigns_page_url">Page des campagnes ([sci_campaigns])</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sci_campaigns_page_url" 
                                   name="<?php echo $this->config_option_name; ?>[sci_campaigns_page_url]" 
                                   value="<?php echo esc_attr($config['sci_campaigns_page_url'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="https://monsite.com/mes-campagnes" />
                            <p class="description">URL complète de la page contenant le shortcode [sci_campaigns]</p>
                        </td>
                    </tr>
                </table>
                
                <!-- Section API Tokens -->
                <h2>🔑 Tokens API</h2>
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
                
                <!-- Section Paramètres La Poste -->
                <h2>📮 Paramètres d'envoi La Poste</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="laposte_type_affranchissement">Type d'affranchissement</label>
                        </th>
                        <td>
                            <select id="laposte_type_affranchissement" 
                                    name="<?php echo $this->config_option_name; ?>[laposte_type_affranchissement]" 
                                    class="regular-text">
                                <?php
                                $affranchissement_options = [
                                    'lrar' => 'LRAR - Lettre recommandée avec accusé de réception (J+3)',
                                    'lr' => 'LR - Lettre recommandée simple (J+3)',
                                    'prioritaire' => 'Prioritaire',
                                    'suivi' => 'Suivi',
                                    'verte' => 'Lettre verte (J+3)',
                                    'vertesuivi' => 'Lettre verte avec suivi (J+3)',
                                    'ecopli' => 'Ecopli (J+4)',
                                    'performance' => 'Performance (J+2)',
                                    'perfsuivi' => 'Performance avec suivi (J+2)'
                                ];
                                $selected_affranchissement = $config['laposte_type_affranchissement'] ?? 'lrar';
                                foreach ($affranchissement_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($selected_affranchissement, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Détermine les services (suivi, preuve de distribution) et la rapidité</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_type_enveloppe">Taille d'enveloppe</label>
                        </th>
                        <td>
                            <select id="laposte_type_enveloppe" 
                                    name="<?php echo $this->config_option_name; ?>[laposte_type_enveloppe]" 
                                    class="regular-text">
                                <?php
                                $enveloppe_type_options = [
                                    'auto' => 'Auto - Choix automatique selon le nombre de feuillets',
                                    'c4' => 'C4 - Format A4 (documents non pliés)',
                                    'c5' => 'C5 - Demi format (pliage en deux)',
                                    'c6' => 'C6 - Tiers format (pliage en trois)'
                                ];
                                $selected_type_enveloppe = $config['laposte_type_enveloppe'] ?? 'auto';
                                foreach ($enveloppe_type_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($selected_type_enveloppe, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Taille de l'enveloppe selon vos besoins de pliage</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_enveloppe">Type d'enveloppe</label>
                        </th>
                        <td>
                            <select id="laposte_enveloppe" 
                                    name="<?php echo $this->config_option_name; ?>[laposte_enveloppe]" 
                                    class="regular-text">
                                <?php
                                $enveloppe_options = [
                                    'fenetre' => 'Fenêtre - Enveloppe à fenêtres (adresse visible)',
                                    'imprime' => 'Imprimée - Adresse imprimée sur l\'enveloppe (plus confidentiel)'
                                ];
                                $selected_enveloppe = $config['laposte_enveloppe'] ?? 'fenetre';
                                foreach ($enveloppe_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($selected_enveloppe, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Enveloppe à fenêtre ou avec adresse imprimée</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_couleur">Couleur d'impression</label>
                        </th>
                        <td>
                            <select id="laposte_couleur" 
                                    name="<?php echo $this->config_option_name; ?>[laposte_couleur]" 
                                    class="regular-text">
                                <?php
                                $couleur_options = [
                                    'nb' => 'Noir et blanc',
                                    'couleur' => 'Couleur'
                                ];
                                $selected_couleur = $config['laposte_couleur'] ?? 'nb';
                                foreach ($couleur_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($selected_couleur, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Impression en noir et blanc ou couleur (s'applique à tout le courrier)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_recto_verso">Mode d'impression</label>
                        </th>
                        <td>
                            <select id="laposte_recto_verso" 
                                    name="<?php echo $this->config_option_name; ?>[laposte_recto_verso]" 
                                    class="regular-text">
                                <?php
                                $recto_verso_options = [
                                    'rectoverso' => 'Recto-verso (recommandé - écologique et économique)',
                                    'recto' => 'Recto seulement (verso vierge)'
                                ];
                                $selected_recto_verso = $config['laposte_recto_verso'] ?? 'rectoverso';
                                foreach ($recto_verso_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($selected_recto_verso, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Impression recto-verso pour réduire le nombre de feuilles</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_placement_adresse">Placement de l'adresse</label>
                        </th>
                        <td>
                            <select id="laposte_placement_adresse" 
                                    name="<?php echo $this->config_option_name; ?>[laposte_placement_adresse]" 
                                    class="regular-text">
                                <?php
                                $placement_options = [
                                    'insertion_page_adresse' => 'Insertion page adresse (recommandé - feuille dédiée)',
                                    'premiere_page' => 'Première page (économise 1 feuille mais peut se superposer)'
                                ];
                                $selected_placement = $config['laposte_placement_adresse'] ?? 'insertion_page_adresse';
                                foreach ($placement_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($selected_placement, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Comment intégrer l'adresse destinataire au courrier</p>
                        </td>
                    </tr>
                </table>
                
                <!-- Section Options avancées -->
                <h2>⚙️ Options avancées</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Options d'impression</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo $this->config_option_name; ?>[laposte_surimpression_adresses]" 
                                           value="1" 
                                           <?php checked($config['laposte_surimpression_adresses'] ?? 1, 1); ?> />
                                    Surimpression des adresses sur le document
                                </label>
                                <p class="description">Laissez coché sauf si votre document contient déjà l'adresse formatée</p>
                                
                                <br><br>
                                
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo $this->config_option_name; ?>[laposte_impression_expediteur]" 
                                           value="1" 
                                           <?php checked($config['laposte_impression_expediteur'] ?? 0, 1); ?> />
                                    Imprimer l'adresse expéditeur sur l'enveloppe
                                </label>
                                <p class="description">Affiche votre adresse sur l'enveloppe à fenêtre</p>
                                
                                <br><br>
                                
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo $this->config_option_name; ?>[laposte_ar_scan]" 
                                           value="1" 
                                           <?php checked($config['laposte_ar_scan'] ?? 1, 1); ?> />
                                    Accusé de réception dématérialisé (LRAR uniquement)
                                </label>
                                <p class="description">L'AR sera scanné et disponible en ligne au lieu d'être renvoyé physiquement</p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_ar_champ1">AR - Champ personnalisé 1</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laposte_ar_champ1" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_ar_champ1]" 
                                   value="<?php echo esc_attr($config['laposte_ar_champ1'] ?? ''); ?>" 
                                   class="regular-text" 
                                   maxlength="38"
                                   placeholder="Ex: Référence dossier" />
                            <p class="description">Première ligne personnalisée sur l'AR (38 caractères max, LRAR uniquement)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_ar_champ2">AR - Champ personnalisé 2</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laposte_ar_champ2" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_ar_champ2]" 
                                   value="<?php echo esc_attr($config['laposte_ar_champ2'] ?? ''); ?>" 
                                   class="regular-text" 
                                   maxlength="38"
                                   placeholder="Ex: Numéro client" />
                            <p class="description">Deuxième ligne personnalisée sur l'AR (38 caractères max, LRAR uniquement)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_reference">Référence</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laposte_reference" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_reference]" 
                                   value="<?php echo esc_attr($config['laposte_reference'] ?? ''); ?>" 
                                   class="regular-text" 
                                   maxlength="50"
                                   placeholder="Référence dossier ou client" />
                            <p class="description">Référence libre pour vos services (50 caractères max)</p>
                        </td>
                    </tr>
                </table>
                
                <!-- Section Facturation analytique -->
                <h2>💰 Facturation analytique (optionnel)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="laposte_nom_entite">Nom entité</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laposte_nom_entite" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_nom_entite]" 
                                   value="<?php echo esc_attr($config['laposte_nom_entite'] ?? ''); ?>" 
                                   class="regular-text" 
                                   maxlength="100"
                                   placeholder="ENTITE POUR REFACTURATION"
                                   style="text-transform: uppercase;" />
                            <p class="description">Entité pour facture analytique (100 caractères max, majuscules uniquement)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_nom_dossier">Nom dossier</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laposte_nom_dossier" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_nom_dossier]" 
                                   value="<?php echo esc_attr($config['laposte_nom_dossier'] ?? ''); ?>" 
                                   class="regular-text" 
                                   maxlength="50"
                                   placeholder="Nom du dossier" />
                            <p class="description">Nom du dossier pour facture analytique (50 caractères max)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="laposte_nom_sousdossier">Nom sous-dossier</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laposte_nom_sousdossier" 
                                   name="<?php echo $this->config_option_name; ?>[laposte_nom_sousdossier]" 
                                   value="<?php echo esc_attr($config['laposte_nom_sousdossier'] ?? ''); ?>" 
                                   class="regular-text" 
                                   maxlength="100"
                                   placeholder="Nom du sous-dossier" />
                            <p class="description">Nom du sous-dossier pour facture analytique (100 caractères max)</p>
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
                    <li>L'accusé de réception dématérialisé n'est disponible qu'avec LRAR vers la France</li>
                </ul>
            </div>
            
            <!-- ✅ NOUVELLE SECTION : Aide pour les URLs shortcodes -->
            <div class="notice notice-success">
                <p><strong>🔗 Configuration des URLs shortcodes :</strong></p>
                <ul>
                    <li>Créez vos pages avec les shortcodes : <code>[sci_panel]</code>, <code>[sci_favoris]</code>, <code>[sci_campaigns]</code></li>
                    <li>Copiez les URLs complètes de ces pages dans les champs ci-dessus</li>
                    <li>Ces URLs seront utilisées pour les redirections automatiques et les liens internes</li>
                    <li>Vous pouvez modifier ces URLs à tout moment sans affecter le code</li>
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
            
            // Forcer les majuscules pour le nom d'entité
            const entiteField = document.getElementById('laposte_nom_entite');
            if (entiteField) {
                entiteField.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
        });
        </script>
        
        <style>
        .form-table input[type="password"], 
        .form-table input[type="text"], 
        .form-table input[type="url"] {
            padding-right: 35px;
        }
        
        .form-table fieldset label {
            display: block;
            margin-bottom: 10px;
        }
        
        .form-table fieldset .description {
            margin-left: 25px;
            margin-top: 5px;
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
            'laposte_api_url' => 'https://sandbox-api.servicepostal.com/lettres',
            
            // ✅ NOUVEAU : URLs par défaut pour les shortcodes
            'sci_panel_page_url' => '',
            'sci_favoris_page_url' => '',
            'sci_campaigns_page_url' => '',
            
            // Paramètres La Poste avec valeurs par défaut
            'laposte_type_affranchissement' => 'lrar',
            'laposte_type_enveloppe' => 'auto',
            'laposte_enveloppe' => 'fenetre',
            'laposte_couleur' => 'nb',
            'laposte_recto_verso' => 'rectoverso',
            'laposte_placement_adresse' => 'insertion_page_adresse',
            'laposte_surimpression_adresses' => 1,
            'laposte_impression_expediteur' => 0,
            'laposte_ar_scan' => 1,
            'laposte_ar_champ1' => '',
            'laposte_ar_champ2' => '',
            'laposte_reference' => '',
            'laposte_nom_entite' => '',
            'laposte_nom_dossier' => '',
            'laposte_nom_sousdossier' => ''
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
    
    // ✅ NOUVELLES MÉTHODES : URLs des pages shortcodes
    
    /**
     * Récupère l'URL de la page principale SCI
     */
    public function get_sci_panel_page_url() {
        $url = $this->get('sci_panel_page_url');
        return !empty($url) ? $url : home_url('/sci-recherche');
    }
    
    /**
     * Récupère l'URL de la page des favoris
     */
    public function get_sci_favoris_page_url() {
        $url = $this->get('sci_favoris_page_url');
        return !empty($url) ? $url : home_url('/mes-favoris');
    }
    
    /**
     * Récupère l'URL de la page des campagnes
     */
    public function get_sci_campaigns_page_url() {
        $url = $this->get('sci_campaigns_page_url');
        return !empty($url) ? $url : home_url('/mes-campagnes');
    }
    
    // === MÉTHODES POUR LES PARAMÈTRES LA POSTE ===
    
    public function get_laposte_type_affranchissement() {
        return $this->get('laposte_type_affranchissement', 'lrar');
    }
    
    public function get_laposte_type_enveloppe() {
        return $this->get('laposte_type_enveloppe', 'auto');
    }
    
    public function get_laposte_enveloppe() {
        return $this->get('laposte_enveloppe', 'fenetre');
    }
    
    public function get_laposte_couleur() {
        return $this->get('laposte_couleur', 'nb');
    }
    
    public function get_laposte_recto_verso() {
        return $this->get('laposte_recto_verso', 'rectoverso');
    }
    
    public function get_laposte_placement_adresse() {
        return $this->get('laposte_placement_adresse', 'insertion_page_adresse');
    }
    
    public function get_laposte_surimpression_adresses() {
        return (bool) $this->get('laposte_surimpression_adresses', 1);
    }
    
    public function get_laposte_impression_expediteur() {
        return (bool) $this->get('laposte_impression_expediteur', 0);
    }
    
    public function get_laposte_ar_scan() {
        return (bool) $this->get('laposte_ar_scan', 1);
    }
    
    public function get_laposte_ar_champ1() {
        return $this->get('laposte_ar_champ1', '');
    }
    
    public function get_laposte_ar_champ2() {
        return $this->get('laposte_ar_champ2', '');
    }
    
    public function get_laposte_reference() {
        return $this->get('laposte_reference', '');
    }
    
    public function get_laposte_nom_entite() {
        return $this->get('laposte_nom_entite', '');
    }
    
    public function get_laposte_nom_dossier() {
        return $this->get('laposte_nom_dossier', '');
    }
    
    public function get_laposte_nom_sousdossier() {
        return $this->get('laposte_nom_sousdossier', '');
    }
    
    /**
     * Récupère tous les paramètres La Poste formatés pour l'API
     */
    public function get_laposte_payload_params() {
        $params = array(
            'type_affranchissement' => $this->get_laposte_type_affranchissement(),
            'type_enveloppe' => $this->get_laposte_type_enveloppe(),
            'enveloppe' => $this->get_laposte_enveloppe(),
            'couleur' => $this->get_laposte_couleur(),
            'recto_verso' => $this->get_laposte_recto_verso(),
            'placement_adresse' => $this->get_laposte_placement_adresse(),
            'surimpression_adresses_document' => $this->get_laposte_surimpression_adresses(),
            'impression_expediteur' => $this->get_laposte_impression_expediteur(),
            'ar_scan' => $this->get_laposte_ar_scan()
        );
        
        // Ajouter les champs optionnels s'ils sont remplis
        $ar_champ1 = $this->get_laposte_ar_champ1();
        if (!empty($ar_champ1)) {
            $params['ar_expediteur_champ1'] = $ar_champ1;
        }
        
        $ar_champ2 = $this->get_laposte_ar_champ2();
        if (!empty($ar_champ2)) {
            $params['ar_expediteur_champ2'] = $ar_champ2;
        }
        
        $reference = $this->get_laposte_reference();
        if (!empty($reference)) {
            $params['reference'] = $reference;
        }
        
        $nom_entite = $this->get_laposte_nom_entite();
        if (!empty($nom_entite)) {
            $params['nom_entite'] = $nom_entite;
        }
        
        $nom_dossier = $this->get_laposte_nom_dossier();
        if (!empty($nom_dossier)) {
            $params['nom_dossier'] = $nom_dossier;
        }
        
        $nom_sousdossier = $this->get_laposte_nom_sousdossier();
        if (!empty($nom_sousdossier)) {
            $params['nom_sousdossier'] = $nom_sousdossier;
        }
        
        return $params;
    }
}

// Initialise le gestionnaire de configuration
function sci_config_manager() {
    return SCI_Config_Manager::get_instance();
}

// Hook d'initialisation
add_action('plugins_loaded', 'sci_config_manager');