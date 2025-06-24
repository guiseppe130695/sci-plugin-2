<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestionnaire des shortcodes pour le plugin SCI
 */
class SCI_Shortcodes {
    
    public function __construct() {
        // Enregistrer les shortcodes
        add_shortcode('sci_panel', array($this, 'sci_panel_shortcode'));
        add_shortcode('sci_favoris', array($this, 'sci_favoris_shortcode'));
        add_shortcode('sci_campaigns', array($this, 'sci_campaigns_shortcode'));
        
        // ✅ NOUVEAU : Forcer le chargement des scripts sur toutes les pages avec shortcodes
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'), 5);
        add_action('wp_head', array($this, 'force_enqueue_on_shortcode_pages'), 1);
        add_action('wp_footer', array($this, 'ensure_scripts_loaded'), 999);
        
        // AJAX handlers pour le frontend
        add_action('wp_ajax_sci_frontend_search', array($this, 'frontend_search_ajax'));
        add_action('wp_ajax_nopriv_sci_frontend_search', array($this, 'frontend_search_ajax'));
    }
    
    /**
     * ✅ NOUVEAU : Force le chargement sur les pages avec shortcodes
     */
    public function force_enqueue_on_shortcode_pages() {
        global $post;
        
        // Vérifier si on est sur une page avec un shortcode SCI
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'sci_panel') ||
            has_shortcode($post->post_content, 'sci_favoris') ||
            has_shortcode($post->post_content, 'sci_campaigns')
        )) {
            // Forcer le chargement immédiat
            $this->force_enqueue_assets();
        }
    }
    
    /**
     * ✅ NOUVEAU : S'assurer que les scripts sont chargés en footer
     */
    public function ensure_scripts_loaded() {
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'sci_panel') ||
            has_shortcode($post->post_content, 'sci_favoris') ||
            has_shortcode($post->post_content, 'sci_campaigns')
        )) {
            // Vérifier si les scripts sont chargés, sinon les charger
            if (!wp_script_is('sci-frontend-favoris', 'done')) {
                $this->force_enqueue_assets();
            }
        }
    }
    
    /**
     * ✅ AMÉLIORÉ : Enqueue les scripts pour le frontend avec détection renforcée
     */
    public function enqueue_frontend_scripts() {
        global $post;
        
        $should_load = false;
        
        // Méthode 1 : Vérifier le post actuel
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'sci_panel') ||
            has_shortcode($post->post_content, 'sci_favoris') ||
            has_shortcode($post->post_content, 'sci_campaigns')
        )) {
            $should_load = true;
        }
        
        // Méthode 2 : Vérifier via les paramètres GET (pour les pages dynamiques)
        if (!$should_load && (
            isset($_GET['sci_view']) || 
            strpos($_SERVER['REQUEST_URI'] ?? '', 'sci') !== false
        )) {
            $should_load = true;
        }
        
        // Méthode 3 : Forcer sur certaines pages spécifiques
        if (!$should_load && (
            is_page() || 
            is_single() || 
            is_front_page() ||
            is_home()
        )) {
            // Vérifier le contenu de la page actuelle
            $content = get_the_content();
            if (strpos($content, '[sci_') !== false) {
                $should_load = true;
            }
        }
        
        if ($should_load) {
            $this->force_enqueue_assets();
        }
    }
    
    /**
     * ✅ NOUVEAU : Force le chargement des assets
     */
    private function force_enqueue_assets() {
        // ✅ CSS en premier
        if (!wp_style_is('sci-frontend-style', 'enqueued')) {
            wp_enqueue_style(
                'sci-frontend-style',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/style.css',
                array(),
                '1.0.2' // Version incrémentée pour forcer le rechargement
            );
        }
        
        // ✅ Scripts JavaScript
        if (!wp_script_is('sci-frontend-favoris', 'enqueued')) {
            wp_enqueue_script(
                'sci-frontend-favoris',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/favoris.js',
                array(),
                '1.0.2',
                true
            );
        }
        
        if (!wp_script_is('sci-frontend-lettre', 'enqueued')) {
            wp_enqueue_script(
                'sci-frontend-lettre',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/lettre.js',
                array(),
                '1.0.2',
                true
            );
        }
        
        if (!wp_script_is('sci-frontend-payment', 'enqueued')) {
            wp_enqueue_script(
                'sci-frontend-payment',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/payment.js',
                array(),
                '1.0.2',
                true
            );
        }
        
        // ✅ Localisation des variables AJAX (une seule fois)
        static $localized = false;
        if (!$localized) {
            // ✅ NOUVEAU : Récupérer les SIRENs contactés pour le frontend
            $campaign_manager = sci_campaign_manager();
            $contacted_sirens = $campaign_manager->get_user_contacted_sirens();
            
            wp_localize_script('sci-frontend-favoris', 'sci_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sci_favoris_nonce'),
                'contacted_sirens' => $contacted_sirens // ✅ NOUVEAU : Liste des SIRENs contactés
            ));
            
            // ✅ Localisation pour le paiement - UTILISE L'URL STOCKÉE
            $woocommerce_integration = sci_woocommerce();
            $config_manager = sci_config_manager();
            wp_localize_script('sci-frontend-payment', 'sciPaymentData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sci_campaign_nonce'),
                'unit_price' => $woocommerce_integration->get_unit_price(),
                'woocommerce_ready' => $woocommerce_integration->is_woocommerce_ready(),
                'campaigns_url' => $config_manager->get_sci_campaigns_page_url() // ✅ MODIFIÉ : Utilise l'URL stockée
            ));
            
            // ✅ Localisation pour lettre.js
            wp_localize_script('sci-frontend-lettre', 'ajaxurl', admin_url('admin-ajax.php'));
            
            $localized = true;
        }
    }
    
    /**
     * Shortcode [sci_panel] - Panneau principal de recherche SCI
     */
    public function sci_panel_shortcode($atts) {
        // ✅ FORCER LE CHARGEMENT DES ASSETS
        $this->force_enqueue_assets();
        
        $atts = shortcode_atts(array(
            'title' => '🏢 SCI – Recherche et Contact',
            'show_config_warnings' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="sci-error">Vous devez être connecté pour utiliser cette fonctionnalité.</div>';
        }
        
        $current_user = wp_get_current_user();
        $codePostal = get_field('code_postal_user', 'user_' . $current_user->ID);
        $codesPostauxArray = [];
        
        if ($codePostal) {
            $codePostal = str_replace(' ', '', $codePostal);
            $codesPostauxArray = explode(';', $codePostal);
        }
        
        $resultats = [];
        
        // Traitement de la recherche
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['codePostal']) && wp_verify_nonce($_POST['sci_search_nonce'], 'sci_search_action')) {
            $postal_code = sanitize_text_field($_POST['codePostal']);
            $resultats = sci_fetch_inpi_data($postal_code);
            
            if (is_wp_error($resultats)) {
                $error_message = $resultats->get_error_message();
                $resultats = [];
            } elseif (empty($resultats)) {
                $warning_message = 'Aucun résultat trouvé.';
                $resultats = [];
            } else {
                $results = sci_format_inpi_results($resultats);
            }
        }
        
        ob_start();
        ?>
        <div class="sci-frontend-wrapper">
            <!-- ✅ CSS INLINE POUR GARANTIR LE STYLE -->
            <style>
            .sci-frontend-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper h1 {
                color: #333;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .sci-frontend-wrapper .sci-form {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            .sci-frontend-wrapper .sci-form label {
                font-weight: 600;
                color: #333;
                display: block;
                margin-bottom: 8px;
            }
            .sci-frontend-wrapper .sci-form select {
                width: 100%;
                max-width: 300px;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .sci-frontend-wrapper .sci-button {
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                margin-right: 10px;
                margin-bottom: 10px;
                transition: all 0.3s ease;
            }
            .sci-frontend-wrapper .sci-button:hover {
                background: linear-gradient(135deg, #005a87 0%, #004a73 100%);
                transform: translateY(-1px);
            }
            .sci-frontend-wrapper .sci-button:disabled {
                background: #ccc;
                cursor: not-allowed;
                transform: none;
            }
            .sci-frontend-wrapper .sci-button.secondary {
                background: #6c757d;
            }
            .sci-frontend-wrapper .sci-button.secondary:hover {
                background: #5a6268;
            }
            .sci-frontend-wrapper .sci-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                background: white;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper .sci-table th,
            .sci-frontend-wrapper .sci-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .sci-frontend-wrapper .sci-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #333;
            }
            .sci-frontend-wrapper .sci-table tr:hover {
                background: #f8f9fa;
            }
            .sci-error {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
                margin: 15px 0;
            }
            .sci-warning {
                background: #fff3cd;
                color: #856404;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #ffeaa7;
                margin: 15px 0;
            }
            .sci-success {
                background: #d4edda;
                color: #155724;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #c3e6cb;
                margin: 15px 0;
            }
            .sci-info {
                background: #d1ecf1;
                color: #0c5460;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #bee5eb;
                margin: 15px 0;
            }
            
            /* ✅ STYLES POUR LES FAVORIS */
            .fav-btn {
                font-size: 1.5rem;
                background: none;
                border: none;
                cursor: pointer;
                color: #ccc;
                transition: color 0.3s;
            }
            .fav-btn.favori {
                color: gold;
            }
            .fav-btn:hover {
                color: orange;
            }
            
            /* ✅ STYLES POUR LES CHECKBOXES */
            .send-letter-checkbox {
                transform: scale(1.2);
                margin: 0;
            }
            
            /* ✅ STYLES POUR LE STATUT DE CONTACT */
            .contact-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transition: all 0.3s ease;
            }
            
            .contact-status.contacted {
                background: #e8f5e8;
                color: #2d5a2d;
                border: 1px solid #c3e6c3;
            }
            
            .contact-status-icon {
                margin-right: 4px;
                font-size: 12px;
            }
            
            .contact-status-text {
                font-size: 10px;
            }
            
            @media (max-width: 768px) {
                .sci-frontend-wrapper {
                    padding: 15px;
                }
                .sci-frontend-wrapper .sci-table {
                    font-size: 14px;
                }
                .sci-frontend-wrapper .sci-table th,
                .sci-frontend-wrapper .sci-table td {
                    padding: 8px;
                }
                
                .contact-status {
                    font-size: 10px;
                    padding: 2px 6px;
                }
                
                .contact-status-icon {
                    margin-right: 2px;
                    font-size: 10px;
                }
                
                .contact-status-text {
                    font-size: 9px;
                }
            }
            </style>
            
            <h1><?php echo esc_html($atts['title']); ?></h1>
            
            <?php if ($atts['show_config_warnings'] === 'true'): ?>
                <?php
                // Vérifications de configuration
                $config_manager = sci_config_manager();
                if (!$config_manager->is_configured()) {
                    echo '<div class="sci-error"><strong>⚠️ Configuration manquante :</strong> Veuillez configurer vos tokens API dans l\'administration.</div>';
                }
                
                $woocommerce_integration = sci_woocommerce();
                if (!$woocommerce_integration->is_woocommerce_ready()) {
                    echo '<div class="sci-warning"><strong>⚠️ WooCommerce requis :</strong> Veuillez installer et configurer WooCommerce pour utiliser le système de paiement.</div>';
                }
                
                $campaign_manager = sci_campaign_manager();
                $expedition_data = $campaign_manager->get_user_expedition_data();
                $validation_errors = $campaign_manager->validate_expedition_data($expedition_data);
                
                if (!empty($validation_errors)) {
                    echo '<div class="sci-warning">';
                    echo '<strong>⚠️ Configuration expéditeur incomplète :</strong><br>';
                    foreach ($validation_errors as $error) {
                        echo '• ' . esc_html($error) . '<br>';
                    }
                    echo '</div>';
                }
                ?>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="sci-error">Erreur API : <?php echo esc_html($error_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($warning_message)): ?>
                <div class="sci-warning"><?php echo esc_html($warning_message); ?></div>
            <?php endif; ?>
            
            <div class="sci-form">
                <form method="post">
                    <?php wp_nonce_field('sci_search_action', 'sci_search_nonce'); ?>
                    <label for="codePostal">Sélectionnez votre code postal :</label>
                    <select name="codePostal" id="codePostal" required>
                        <option value="">— Choisir un code postal —</option>
                        <?php foreach ($codesPostauxArray as $value): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($_POST['codePostal'] ?? '', $value); ?>>
                                <?php echo esc_html($value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                    <button type="submit" class="sci-button">🔍 Rechercher les SCI</button>
                    <button id="send-letters-btn" type="button" class="sci-button secondary" disabled>
                        📬 Créer une campagne (<span id="selected-count">0</span>)
                    </button>
                </form>
            </div>
            
            <?php if (!empty($results)): ?>
                <h2>📋 Résultats de recherche :</h2>
                <table class="sci-table">
                    <thead>
                        <tr>
                            <th>⭐</th>
                            <th>Dénomination</th>
                            <th>Dirigeant</th>
                            <th>SIREN</th>
                            <th>Adresse</th>
                            <th>Ville</th>
                            <th>Code Postal</th>
                            <th>Statut</th> <!-- ✅ NOUVELLE COLONNE -->
                            <th>Sélection</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $res): ?>
                            <tr>
                                <td>
                                    <button class="fav-btn" 
                                            data-siren="<?php echo esc_attr($res['siren']); ?>"
                                            data-denomination="<?php echo esc_attr($res['denomination']); ?>"
                                            data-dirigeant="<?php echo esc_attr($res['dirigeant']); ?>"
                                            data-adresse="<?php echo esc_attr($res['adresse']); ?>"
                                            data-ville="<?php echo esc_attr($res['ville']); ?>"
                                            data-code-postal="<?php echo esc_attr($res['code_postal']); ?>"
                                            aria-label="Ajouter aux favoris">☆</button>
                                </td>
                                <td><?php echo esc_html($res['denomination']); ?></td>
                                <td><?php echo esc_html($res['dirigeant']); ?></td>
                                <td><?php echo esc_html($res['siren']); ?></td>
                                <td><?php echo esc_html($res['adresse']); ?></td>
                                <td><?php echo esc_html($res['ville']); ?></td>
                                <td><?php echo esc_html($res['code_postal']); ?></td>
                                <td>
                                    <!-- ✅ CELLULE POUR LE STATUT DE CONTACT - VIDE PAR DÉFAUT -->
                                    <span class="contact-status" data-siren="<?php echo esc_attr($res['siren']); ?>" style="display: none;">
                                        <span class="contact-status-icon"></span>
                                        <span class="contact-status-text"></span>
                                    </span>
                                </td>
                                <td>
                                    <input type="checkbox" class="send-letter-checkbox"
                                        data-denomination="<?php echo esc_attr($res['denomination']); ?>"
                                        data-dirigeant="<?php echo esc_attr($res['dirigeant']); ?>"
                                        data-siren="<?php echo esc_attr($res['siren']); ?>"
                                        data-adresse="<?php echo esc_attr($res['adresse']); ?>"
                                        data-ville="<?php echo esc_attr($res['ville']); ?>"
                                        data-code-postal="<?php echo esc_attr($res['code_postal']); ?>"
                                    />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- ✅ POPUP LETTRE AVEC STYLES INLINE -->
        <div id="letters-popup" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:10000; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:25px; width:700px; max-width:95vw; max-height:95vh; overflow-y:auto; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.3);">
                <!-- Étape 1 : Liste des SCI sélectionnées -->
                <div class="step" id="step-1">
                    <h2>📋 SCI sélectionnées</h2>
                    <p style="color: #666; margin-bottom: 20px;">Vérifiez votre sélection avant de continuer</p>
                    <ul id="selected-sci-list" style="max-height:350px; overflow-y:auto; border:1px solid #ddd; padding:15px; margin-bottom:25px; border-radius:6px; background-color: #f9f9f9; list-style: none;">
                        <!-- Les SCI sélectionnées seront ajoutées ici par JavaScript -->
                    </ul>
                    <div style="text-align: center;">
                        <button id="to-step-2" class="sci-button" style="background: linear-gradient(135deg, #0073aa 0%, #005a87 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px;">
                            ✍️ Rédiger le courriel →
                        </button>
                        <button id="close-popup-1" class="sci-button" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-left: 15px;">Fermer</button>
                    </div>
                </div>
                
                <!-- Étape 2 : Contenu dynamique -->
                <div class="step" id="step-2" style="display:none;">
                    <!-- Le contenu sera généré par JavaScript -->
                </div>
            </div>
        </div>
        
        <!-- ✅ SCRIPT INLINE POUR GARANTIR LE FONCTIONNEMENT -->
        <script>
        // Vérifier que les variables AJAX sont disponibles
        if (typeof sci_ajax === 'undefined') {
            window.sci_ajax = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('sci_favoris_nonce'); ?>',
                contacted_sirens: <?php echo json_encode($campaign_manager->get_user_contacted_sirens()); ?>
            };
        }
        
        if (typeof sciPaymentData === 'undefined') {
            window.sciPaymentData = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('sci_campaign_nonce'); ?>',
                unit_price: <?php echo $woocommerce_integration->get_unit_price(); ?>,
                woocommerce_ready: <?php echo $woocommerce_integration->is_woocommerce_ready() ? 'true' : 'false'; ?>,
                campaigns_url: '<?php echo $config_manager->get_sci_campaigns_page_url(); ?>' // ✅ MODIFIÉ : Utilise l'URL stockée
            };
        }
        
        if (typeof ajaxurl === 'undefined') {
            window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        }
        
        // Forcer le rechargement des scripts si nécessaire
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier si les fonctions principales existent
            if (typeof updateSelectedCount === 'undefined' || typeof window.getSelectedEntries === 'undefined') {
                console.log('Scripts SCI non chargés, tentative de rechargement...');
                
                // Charger les scripts manuellement
                const scripts = [
                    '<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/js/favoris.js'; ?>',
                    '<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/js/lettre.js'; ?>',
                    '<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/js/payment.js'; ?>'
                ];
                
                scripts.forEach(function(src) {
                    const script = document.createElement('script');
                    script.src = src + '?v=' + Date.now();
                    script.async = false;
                    document.head.appendChild(script);
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode [sci_favoris] - Liste des favoris
     */
    public function sci_favoris_shortcode($atts) {
        // ✅ FORCER LE CHARGEMENT DES ASSETS
        $this->force_enqueue_assets();
        
        $atts = shortcode_atts(array(
            'title' => '⭐ Mes SCI Favoris',
            'show_empty_message' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="sci-error">Vous devez être connecté pour voir vos favoris.</div>';
        }
        
        global $sci_favoris_handler;
        $favoris = $sci_favoris_handler->get_favoris();
        
        ob_start();
        ?>
        <div class="sci-frontend-wrapper">
            <!-- ✅ CSS INLINE POUR GARANTIR LE STYLE -->
            <style>
            .sci-frontend-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper h1 {
                color: #333;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .sci-frontend-wrapper .sci-button {
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                margin-right: 10px;
                margin-bottom: 10px;
                transition: all 0.3s ease;
            }
            .sci-frontend-wrapper .sci-button:hover {
                background: linear-gradient(135deg, #005a87 0%, #004a73 100%);
                transform: translateY(-1px);
            }
            .sci-frontend-wrapper .sci-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                background: white;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper .sci-table th,
            .sci-frontend-wrapper .sci-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .sci-frontend-wrapper .sci-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #333;
            }
            .sci-frontend-wrapper .sci-table tr:hover {
                background: #f8f9fa;
            }
            .sci-error {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
                margin: 15px 0;
            }
            .sci-info {
                background: #d1ecf1;
                color: #0c5460;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #bee5eb;
                margin: 15px 0;
            }
            </style>
            
            <h1><?php echo esc_html($atts['title']); ?></h1>
            
            <?php if (empty($favoris) && $atts['show_empty_message'] === 'true'): ?>
                <div class="sci-info">
                    <p>Aucun favori pour le moment. Ajoutez des SCI à vos favoris depuis la recherche pour les retrouver ici facilement.</p>
                </div>
            <?php else: ?>
                <table class="sci-table" id="table-favoris">
                    <thead>
                        <tr>
                            <th>Dénomination</th>
                            <th>Dirigeant</th>
                            <th>SIREN</th>
                            <th>Adresse</th>
                            <th>Ville</th>
                            <th>Code Postal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($favoris as $fav): ?>
                            <tr>
                                <td><?php echo esc_html($fav['denomination']); ?></td>
                                <td><?php echo esc_html($fav['dirigeant']); ?></td>
                                <td><?php echo esc_html($fav['siren']); ?></td>
                                <td><?php echo esc_html($fav['adresse']); ?></td>
                                <td><?php echo esc_html($fav['ville']); ?></td>
                                <td><?php echo esc_html($fav['code_postal']); ?></td>
                                <td>
                                    <button class="remove-fav-btn sci-button" 
                                            data-siren="<?php echo esc_attr($fav['siren']); ?>"
                                            style="background: #dc3545; font-size: 12px; padding: 6px 12px;">
                                        🗑️ Supprimer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <script>
        // Vérifier que les variables AJAX sont disponibles
        if (typeof sci_ajax === 'undefined') {
            window.sci_ajax = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('sci_favoris_nonce'); ?>'
            };
        }
        
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.remove-fav-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer ce favori ?')) {
                        return;
                    }

                    const siren = btn.getAttribute('data-siren');
                    const formData = new FormData();
                    formData.append('action', 'sci_manage_favoris');
                    formData.append('operation', 'remove');
                    formData.append('nonce', sci_ajax.nonce);
                    formData.append('sci_data', JSON.stringify({siren: siren}));

                    fetch(sci_ajax.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Erreur lors de la suppression : ' + data.data);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur réseau');
                    });
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode [sci_campaigns] - Liste des campagnes
     */
    public function sci_campaigns_shortcode($atts) {
        // ✅ FORCER LE CHARGEMENT DES ASSETS
        $this->force_enqueue_assets();
        
        $atts = shortcode_atts(array(
            'title' => '📬 Mes Campagnes de Lettres',
            'show_empty_message' => 'true'
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="sci-error">Vous devez être connecté pour voir vos campagnes.</div>';
        }
        
        $campaign_manager = sci_campaign_manager();
        $config_manager = sci_config_manager();
        
        // Gestion de l'affichage des détails d'une campagne
        if (isset($_GET['sci_view']) && $_GET['sci_view'] === 'campaign' && isset($_GET['id']) && is_numeric($_GET['id'])) {
            $campaign_details = $campaign_manager->get_campaign_details(intval($_GET['id']));
            if ($campaign_details) {
                return $this->display_campaign_details_frontend($campaign_details, $atts);
            }
        }
        
        $campaigns = $campaign_manager->get_user_campaigns();
        
        ob_start();
        ?>
        <div class="sci-frontend-wrapper">
            <!-- ✅ CSS INLINE POUR GARANTIR LE STYLE -->
            <style>
            .sci-frontend-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper h1 {
                color: #333;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .sci-frontend-wrapper .sci-button {
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                margin-right: 10px;
                margin-bottom: 10px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }
            .sci-frontend-wrapper .sci-button:hover {
                background: linear-gradient(135deg, #005a87 0%, #004a73 100%);
                transform: translateY(-1px);
                color: white;
                text-decoration: none;
            }
            .sci-frontend-wrapper .sci-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                background: white;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper .sci-table th,
            .sci-frontend-wrapper .sci-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .sci-frontend-wrapper .sci-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #333;
            }
            .sci-frontend-wrapper .sci-table tr:hover {
                background: #f8f9fa;
            }
            .sci-error {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
                margin: 15px 0;
            }
            .sci-info {
                background: #d1ecf1;
                color: #0c5460;
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #bee5eb;
                margin: 15px 0;
            }
            </style>
            
            <h1><?php echo esc_html($atts['title']); ?></h1>
            
            <?php if (empty($campaigns) && $atts['show_empty_message'] === 'true'): ?>
                <div class="sci-info">
                    <p>Aucune campagne trouvée. Créez votre première campagne depuis la recherche SCI.</p>
                </div>
            <?php else: ?>
                <table class="sci-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Statut</th>
                            <th>Total</th>
                            <th>Envoyées</th>
                            <th>Erreurs</th>
                            <th>Date création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr>
                                <td><strong><?php echo esc_html($campaign['title']); ?></strong></td>
                                <td>
                                    <?php
                                    $status_labels = [
                                        'draft' => '📝 Brouillon',
                                        'processing' => '⏳ En cours',
                                        'completed' => '✅ Terminée',
                                        'completed_with_errors' => '⚠️ Terminée avec erreurs'
                                    ];
                                    echo $status_labels[$campaign['status']] ?? $campaign['status'];
                                    ?>
                                </td>
                                <td><?php echo intval($campaign['total_letters']); ?></td>
                                <td><?php echo intval($campaign['sent_letters']); ?></td>
                                <td><?php echo intval($campaign['failed_letters']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo add_query_arg(array('sci_view' => 'campaign', 'id' => $campaign['id'])); ?>" 
                                       class="sci-button" style="font-size: 12px; padding: 6px 12px;">
                                        👁️ Voir détails
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Affichage des détails d'une campagne pour le frontend
     */
    private function display_campaign_details_frontend($campaign, $atts) {
        $config_manager = sci_config_manager();
        
        ob_start();
        ?>
        <div class="sci-frontend-wrapper">
            <!-- ✅ CSS INLINE POUR GARANTIR LE STYLE -->
            <style>
            .sci-frontend-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper h1 {
                color: #333;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .sci-frontend-wrapper .sci-button {
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                margin-right: 10px;
                margin-bottom: 10px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }
            .sci-frontend-wrapper .sci-button:hover {
                background: linear-gradient(135deg, #005a87 0%, #004a73 100%);
                transform: translateY(-1px);
                color: white;
                text-decoration: none;
            }
            .sci-frontend-wrapper .sci-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                background: white;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .sci-frontend-wrapper .sci-table th,
            .sci-frontend-wrapper .sci-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .sci-frontend-wrapper .sci-table th {
                background: #f8f9fa;
                font-weight: 600;
                color: #333;
            }
            .sci-frontend-wrapper .sci-table tr:hover {
                background: #f8f9fa;
            }
            </style>
            
            <h1>📬 Détails de la campagne : <?php echo esc_html($campaign['title']); ?></h1>
            
            <a href="<?php echo $config_manager->get_sci_campaigns_page_url(); ?>" class="sci-button">
                ← Retour aux campagnes
            </a>
            
            <div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 6px; border: 1px solid #ddd;">
                <h3>📊 Résumé</h3>
                <p><strong>Statut :</strong> 
                    <?php
                    $status_labels = [
                        'draft' => '📝 Brouillon',
                        'processing' => '⏳ En cours',
                        'completed' => '✅ Terminée',
                        'completed_with_errors' => '⚠️ Terminée avec erreurs'
                    ];
                    echo $status_labels[$campaign['status']] ?? $campaign['status'];
                    ?>
                </p>
                <p><strong>Total lettres :</strong> <?php echo intval($campaign['total_letters']); ?></p>
                <p><strong>Envoyées :</strong> <?php echo intval($campaign['sent_letters']); ?></p>
                <p><strong>Erreurs :</strong> <?php echo intval($campaign['failed_letters']); ?></p>
                <p><strong>Date création :</strong> <?php echo date('d/m/Y H:i:s', strtotime($campaign['created_at'])); ?></p>
                
                <h4>📝 Contenu de la lettre :</h4>
                <div style="background: white; padding: 15px; border-left: 4px solid #0073aa; border-radius: 4px;">
                    <?php echo nl2br(esc_html($campaign['content'])); ?>
                </div>
            </div>
            
            <h3>📋 Détail des envois</h3>
            <table class="sci-table">
                <thead>
                    <tr>
                        <th>SCI</th>
                        <th>Dirigeant</th>
                        <th>SIREN</th>
                        <th>Adresse</th>
                        <th>Statut</th>
                        <th>UID La Poste</th>
                        <th>Date envoi</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaign['letters'] as $letter): ?>
                        <tr>
                            <td><?php echo esc_html($letter['sci_denomination']); ?></td>
                            <td><?php echo esc_html($letter['sci_dirigeant']); ?></td>
                            <td><?php echo esc_html($letter['sci_siren']); ?></td>
                            <td><?php echo esc_html($letter['sci_adresse'] . ', ' . $letter['sci_code_postal'] . ' ' . $letter['sci_ville']); ?></td>
                            <td>
                                <?php
                                $status_icons = [
                                    'pending' => '⏳ En attente',
                                    'sent' => '✅ Envoyée',
                                    'failed' => '❌ Erreur'
                                ];
                                echo $status_icons[$letter['status']] ?? $letter['status'];
                                ?>
                            </td>
                            <td>
                                <?php if ($letter['laposte_uid']): ?>
                                    <code style="background: #f1f1f1; padding: 2px 4px; border-radius: 3px; font-size: 12px;">
                                        <?php echo esc_html($letter['laposte_uid']); ?>
                                    </code>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $letter['sent_at'] ? date('d/m/Y H:i', strtotime($letter['sent_at'])) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($letter['error_message']): ?>
                                    <span style="color: #dc3545; font-size: 12px;">
                                        <?php echo esc_html($letter['error_message']); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler pour la recherche frontend
     */
    public function frontend_search_ajax() {
        // Même logique que l'admin mais pour le frontend
        // Cette fonction peut être utilisée pour des recherches AJAX si nécessaire
        wp_send_json_error('Non implémenté');
    }
}

// Initialiser les shortcodes
new SCI_Shortcodes();