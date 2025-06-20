<?php
if (!defined('ABSPATH')) exit;

/**
 * Intégration WooCommerce pour le paiement des campagnes SCI
 */
class SCI_WooCommerce_Integration {
    
    private $product_id;
    
    public function __construct() {
        // Hooks d'initialisation
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_sci_create_order', array($this, 'create_order_ajax'));
        add_action('wp_ajax_nopriv_sci_create_order', array($this, 'create_order_ajax'));
        add_action('wp_ajax_sci_check_order_status', array($this, 'check_order_status_ajax'));
        add_action('wp_ajax_nopriv_sci_check_order_status', array($this, 'check_order_status_ajax'));
        
        // Hook pour traiter les commandes payées
        add_action('woocommerce_order_status_completed', array($this, 'process_paid_campaign'));
        add_action('woocommerce_order_status_processing', array($this, 'process_paid_campaign'));
        
        // Hook pour les paiements instantanés (cartes, PayPal, etc.)
        add_action('woocommerce_payment_complete', array($this, 'process_paid_campaign'));
        
        // Hooks pour personnaliser le checkout embarqué
        add_action('wp_head', array($this, 'add_checkout_scripts'));
        add_filter('woocommerce_checkout_redirect_empty_cart', array($this, 'prevent_empty_cart_redirect'));
        
        // Hook pour masquer la barre d'admin dans le checkout embarqué
        add_action('wp', array($this, 'maybe_hide_admin_bar'));
    }
    
    public function init() {
        // Récupérer l'ID du produit SCI depuis les options
        $this->product_id = get_option('sci_woocommerce_product_id', 0);
        
        // Créer le produit automatiquement s'il n'existe pas
        if (!$this->product_id || !get_post($this->product_id)) {
            $this->create_sci_product();
        }
    }
    
    /**
     * Masque la barre d'administration WordPress pour le checkout embarqué
     */
    public function maybe_hide_admin_bar() {
        if (isset($_GET['embedded']) && $_GET['embedded'] == '1') {
            // Masquer la barre d'admin
            show_admin_bar(false);
            
            // Ajouter des styles pour optimiser l'affichage embarqué
            add_action('wp_head', function() {
                ?>
                <style>
                /* Masquer complètement la barre d'admin */
                #wpadminbar {
                    display: none !important;
                }
                
                /* Ajuster le margin-top du body */
                body.admin-bar {
                    margin-top: 0 !important;
                }
                
                html {
                    margin-top: 0 !important;
                }
                
                /* Optimiser l'affichage pour l'iframe */
                body {
                    background: #f9f9f9 !important;
                    margin: 0 !important;
                    padding: 15px !important;
                }
                
                /* Masquer les éléments non essentiels */
                .site-header,
                .site-footer,
                .breadcrumb,
                .woocommerce-breadcrumb,
                .site-navigation,
                .widget-area {
                    display: none !important;
                }
                
                /* Optimiser le contenu principal */
                .site-content,
                .content-area,
                main {
                    width: 100% !important;
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                
                /* Améliorer l'affichage du checkout */
                .woocommerce {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                
                .woocommerce-checkout .col2-set {
                    width: 100% !important;
                    display: block !important;
                }
                
                .woocommerce-checkout .col-1,
                .woocommerce-checkout .col-2 {
                    width: 100% !important;
                    float: none !important;
                    margin-bottom: 20px;
                }
                
                /* Améliorer les formulaires */
                .woocommerce-checkout-payment {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 6px;
                    margin-top: 20px;
                    border: 1px solid #e9ecef;
                }
                
                .woocommerce-checkout-payment .payment_methods {
                    background: white;
                    padding: 15px;
                    border-radius: 4px;
                    border: 1px solid #dee2e6;
                }
                
                /* Améliorer les boutons */
                .woocommerce #payment #place_order {
                    background: linear-gradient(135deg, #0073aa 0%, #005a87 100%) !important;
                    border: none !important;
                    border-radius: 6px !important;
                    padding: 15px 30px !important;
                    font-size: 16px !important;
                    font-weight: 600 !important;
                    width: 100% !important;
                    margin-top: 15px !important;
                }
                
                .woocommerce #payment #place_order:hover {
                    background: linear-gradient(135deg, #005a87 0%, #004a73 100%) !important;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 8px rgba(0,115,170,0.3) !important;
                }
                
                /* Messages d'erreur et de succès */
                .woocommerce-message,
                .woocommerce-error,
                .woocommerce-info {
                    border-radius: 6px !important;
                    padding: 15px 20px !important;
                    margin-bottom: 20px !important;
                }
                
                .woocommerce-message {
                    background: #d4edda !important;
                    border-color: #c3e6cb !important;
                    color: #155724 !important;
                }
                
                .woocommerce-error {
                    background: #f8d7da !important;
                    border-color: #f5c6cb !important;
                    color: #721c24 !important;
                }
                
                /* Responsive pour mobile */
                @media (max-width: 768px) {
                    body {
                        padding: 10px !important;
                    }
                    
                    .woocommerce {
                        padding: 15px !important;
                    }
                    
                    .woocommerce-checkout-payment {
                        padding: 15px !important;
                    }
                }
                </style>
                <?php
            });
        }
    }
    
    /**
     * Ajoute des scripts pour améliorer l'expérience checkout embarqué
     */
    public function add_checkout_scripts() {
        if (is_wc_endpoint_url('order-pay') || is_checkout()) {
            ?>
            <script>
            // Script pour communiquer avec la fenêtre parent (popup)
            document.addEventListener('DOMContentLoaded', function() {
                // Détecter si on est dans un iframe
                if (window.parent !== window) {
                    console.log('Checkout embarqué détecté');
                    
                    // Écouter les changements de statut de commande
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            // Détecter les messages de succès WooCommerce
                            if (mutation.addedNodes) {
                                mutation.addedNodes.forEach(function(node) {
                                    if (node.nodeType === 1) {
                                        // Succès de paiement
                                        if (node.classList && (
                                            node.classList.contains('woocommerce-message') || 
                                            node.classList.contains('woocommerce-order-received') ||
                                            node.classList.contains('woocommerce-thankyou-order-received')
                                        )) {
                                            console.log('Paiement réussi détecté');
                                            window.parent.postMessage({
                                                type: 'woocommerce_checkout_success',
                                                message: 'Paiement confirmé'
                                            }, '*');
                                        }
                                        
                                        // Erreur de paiement
                                        if (node.classList && (
                                            node.classList.contains('woocommerce-error') || 
                                            node.classList.contains('woocommerce-notice--error')
                                        )) {
                                            console.log('Erreur de paiement détectée');
                                            window.parent.postMessage({
                                                type: 'woocommerce_checkout_error',
                                                message: node.textContent || 'Erreur de paiement'
                                            }, '*');
                                        }
                                    }
                                });
                            }
                        });
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                    
                    // Détecter la redirection vers la page de confirmation
                    if (window.location.href.includes('order-received') || 
                        window.location.href.includes('checkout/order-received')) {
                        console.log('Page de confirmation détectée');
                        window.parent.postMessage({
                            type: 'woocommerce_checkout_success',
                            message: 'Commande confirmée'
                        }, '*');
                    }
                    
                    // Détecter les formulaires de paiement soumis
                    const checkoutForm = document.querySelector('form.checkout, form#order_review');
                    if (checkoutForm) {
                        checkoutForm.addEventListener('submit', function() {
                            console.log('Formulaire de paiement soumis');
                            // Attendre un peu puis vérifier le résultat
                            setTimeout(function() {
                                // Vérifier s'il y a des erreurs
                                const errors = document.querySelectorAll('.woocommerce-error, .woocommerce-notice--error');
                                if (errors.length === 0) {
                                    // Pas d'erreur visible, probablement un succès
                                    const successElements = document.querySelectorAll('.woocommerce-message, .woocommerce-order-received');
                                    if (successElements.length > 0) {
                                        window.parent.postMessage({
                                            type: 'woocommerce_checkout_success',
                                            message: 'Paiement traité avec succès'
                                        }, '*');
                                    }
                                }
                            }, 2000);
                        });
                    }
                    
                    // Envoyer un message de chargement terminé
                    window.parent.postMessage({
                        type: 'checkout_loaded',
                        message: 'Checkout chargé'
                    }, '*');
                }
            });
            </script>
            <?php
        }
    }
    
    /**
     * Empêche la redirection automatique si le panier est vide (pour les commandes directes)
     */
    public function prevent_empty_cart_redirect($redirect) {
        if (isset($_GET['order-pay']) || isset($_GET['embedded'])) {
            return false;
        }
        return $redirect;
    }
    
    /**
     * Crée automatiquement le produit SCI dans WooCommerce
     */
    private function create_sci_product() {
        if (!class_exists('WC_Product_Simple')) {
            return false;
        }
        
        $product = new WC_Product_Simple();
        $product->set_name('Contact SCI - Envoi de lettre');
        $product->set_description('Service d\'envoi de lettre recommandée vers une SCI');
        $product->set_short_description('Envoi de lettre recommandée avec accusé de réception');
        $product->set_regular_price('5.00'); // Prix par défaut, modifiable
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        $product->set_virtual(true); // Produit virtuel
        $product->set_downloadable(false);
        $product->set_catalog_visibility('hidden'); // Caché du catalogue
        $product->set_status('publish');
        
        // Catégorie spéciale pour les services SCI
        $category_id = $this->get_or_create_sci_category();
        if ($category_id) {
            $product->set_category_ids(array($category_id));
        }
        
        $product_id = $product->save();
        
        if ($product_id) {
            update_option('sci_woocommerce_product_id', $product_id);
            $this->product_id = $product_id;
            
            // Ajouter des métadonnées pour identifier ce produit
            update_post_meta($product_id, '_sci_service_product', 'yes');
            
            return $product_id;
        }
        
        return false;
    }
    
    /**
     * Crée ou récupère la catégorie SCI
     */
    private function get_or_create_sci_category() {
        $category = get_term_by('slug', 'services-sci', 'product_cat');
        
        if (!$category) {
            $result = wp_insert_term(
                'Services SCI',
                'product_cat',
                array(
                    'slug' => 'services-sci',
                    'description' => 'Services d\'envoi de lettres pour SCI'
                )
            );
            
            if (!is_wp_error($result)) {
                return $result['term_id'];
            }
        } else {
            return $category->term_id;
        }
        
        return false;
    }
    
    /**
     * AJAX - Crée une commande WooCommerce pour la campagne
     */
    public function create_order_ajax() {
        // Vérifications de sécurité
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sci_campaign_nonce')) {
            wp_send_json_error('Nonce invalide');
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Utilisateur non connecté');
            return;
        }
        
        // Récupération des données
        $campaign_data = json_decode(stripslashes($_POST['campaign_data'] ?? ''), true);
        if (!$campaign_data || !isset($campaign_data['entries'])) {
            wp_send_json_error('Données de campagne invalides');
            return;
        }
        
        $sci_count = count($campaign_data['entries']);
        if ($sci_count <= 0) {
            wp_send_json_error('Aucune SCI sélectionnée');
            return;
        }
        
        lettre_laposte_log("=== CRÉATION COMMANDE WOOCOMMERCE ===");
        lettre_laposte_log("Utilisateur: " . get_current_user_id());
        lettre_laposte_log("Nombre SCI: $sci_count");
        lettre_laposte_log("Titre campagne: " . ($campaign_data['title'] ?? 'N/A'));
        
        // Créer la commande WooCommerce
        $order_id = $this->create_woocommerce_order($campaign_data, $sci_count);
        
        if (is_wp_error($order_id)) {
            lettre_laposte_log("❌ Erreur création commande: " . $order_id->get_error_message());
            wp_send_json_error('Erreur lors de la création de la commande : ' . $order_id->get_error_message());
            return;
        }
        
        lettre_laposte_log("✅ Commande créée avec ID: $order_id");
        
        // Retourner l'URL de paiement avec paramètres optimisés
        $order = wc_get_order($order_id);
        $checkout_url = $order->get_checkout_payment_url() . '&embedded=1&hide_admin_bar=1';
        
        wp_send_json_success(array(
            'order_id' => $order_id,
            'checkout_url' => $checkout_url,
            'total' => $order->get_total(),
            'sci_count' => $sci_count
        ));
    }
    
    /**
     * AJAX - Vérifie le statut d'une commande
     */
    public function check_order_status_ajax() {
        // Vérifications de sécurité
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sci_campaign_nonce')) {
            wp_send_json_error('Nonce invalide');
            return;
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('ID de commande invalide');
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Commande introuvable');
            return;
        }
        
        // Vérifier que l'utilisateur est propriétaire de la commande
        if ($order->get_customer_id() !== get_current_user_id()) {
            wp_send_json_error('Accès non autorisé');
            return;
        }
        
        $status = $order->get_status();
        $is_paid = in_array($status, ['processing', 'completed', 'on-hold']);
        
        lettre_laposte_log("Vérification statut commande #$order_id: $status (payé: " . ($is_paid ? 'oui' : 'non') . ")");
        
        wp_send_json_success(array(
            'status' => $is_paid ? 'paid' : 'pending',
            'order_status' => $status,
            'total' => $order->get_total()
        ));
    }
    
    /**
     * Crée une commande WooCommerce pour la campagne
     */
    private function create_woocommerce_order($campaign_data, $sci_count) {
        if (!class_exists('WC_Order')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce non disponible');
        }
        
        if (!$this->product_id) {
            return new WP_Error('product_missing', 'Produit SCI non configuré');
        }
        
        $product = wc_get_product($this->product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'Produit SCI introuvable');
        }
        
        // Créer la commande
        $order = wc_create_order();
        
        // Ajouter le produit avec la quantité = nombre de SCI
        $order->add_product($product, $sci_count);
        
        // Définir l'utilisateur
        $user_id = get_current_user_id();
        $order->set_customer_id($user_id);
        
        // Récupérer les adresses de facturation depuis le profil utilisateur
        $this->set_order_addresses($order, $user_id);
        
        // Ajouter les métadonnées de la campagne
        $order->update_meta_data('_sci_campaign_data', json_encode($campaign_data));
        $order->update_meta_data('_sci_campaign_title', $campaign_data['title']);
        $order->update_meta_data('_sci_campaign_count', $sci_count);
        $order->update_meta_data('_sci_campaign_status', 'pending_payment');
        
        // Calculer les totaux
        $order->calculate_totals();
        
        // Définir le statut
        $order->set_status('pending');
        
        // Ajouter une note
        $order->add_order_note(sprintf(
            'Campagne SCI "%s" - %d lettres à envoyer',
            $campaign_data['title'],
            $sci_count
        ));
        
        // Sauvegarder
        $order->save();
        
        return $order->get_id();
    }
    
    /**
     * Définit les adresses de facturation et livraison depuis le profil utilisateur
     */
    private function set_order_addresses($order, $user_id) {
        $user = get_user_by('ID', $user_id);
        
        // Récupérer les données depuis différentes sources (comme dans campaign-manager.php)
        $campaign_manager = sci_campaign_manager();
        $user_data = $campaign_manager->get_user_expedition_data($user_id);
        
        // Adresse de facturation
        $billing_address = array(
            'first_name' => $user_data['prenom'] ?: $user->first_name,
            'last_name'  => $user_data['nom'] ?: $user->last_name,
            'company'    => $user_data['nom_societe'] ?: '',
            'address_1'  => $user_data['adresse_ligne1'] ?: '',
            'address_2'  => $user_data['adresse_ligne2'] ?: '',
            'city'       => $user_data['ville'] ?: '',
            'postcode'   => $user_data['code_postal'] ?: '',
            'country'    => 'FR',
            'email'      => $user->user_email,
            'phone'      => get_user_meta($user_id, 'billing_phone', true) ?: ''
        );
        
        $order->set_address($billing_address, 'billing');
        $order->set_address($billing_address, 'shipping'); // Même adresse pour la livraison
    }
    
    /**
     * Traite une campagne après paiement réussi
     */
    public function process_paid_campaign($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Vérifier si c'est une commande SCI
        $campaign_data = $order->get_meta('_sci_campaign_data');
        if (!$campaign_data) {
            return; // Pas une commande SCI
        }
        
        // Vérifier si déjà traité
        $campaign_status = $order->get_meta('_sci_campaign_status');
        if ($campaign_status === 'processed' || $campaign_status === 'processing') {
            return; // Déjà traité
        }
        
        lettre_laposte_log("=== TRAITEMENT CAMPAGNE PAYÉE ===");
        lettre_laposte_log("Commande #$order_id - Statut: " . $order->get_status());
        
        // Marquer comme en cours de traitement
        $order->update_meta_data('_sci_campaign_status', 'processing');
        $order->save();
        
        // Décoder les données de campagne
        $campaign_data = json_decode($campaign_data, true);
        if (!$campaign_data) {
            $order->add_order_note('Erreur : données de campagne invalides');
            lettre_laposte_log("❌ Données de campagne invalides");
            return;
        }
        
        lettre_laposte_log("Données campagne décodées: " . json_encode($campaign_data, JSON_PRETTY_PRINT));
        
        // Créer la campagne en base de données
        $campaign_manager = sci_campaign_manager();
        $campaign_id = $campaign_manager->create_campaign(
            $campaign_data['title'],
            $campaign_data['content'],
            $campaign_data['entries']
        );
        
        if (is_wp_error($campaign_id)) {
            $error_msg = 'Erreur lors de la création de la campagne : ' . $campaign_id->get_error_message();
            $order->add_order_note($error_msg);
            $order->update_meta_data('_sci_campaign_status', 'error');
            $order->save();
            lettre_laposte_log("❌ " . $error_msg);
            return;
        }
        
        lettre_laposte_log("✅ Campagne créée avec ID: $campaign_id");
        
        // Sauvegarder l'ID de campagne
        $order->update_meta_data('_sci_campaign_id', $campaign_id);
        
        // Programmer l'envoi des lettres (en arrière-plan)
        wp_schedule_single_event(time() + 30, 'sci_process_paid_campaign', array($order_id, $campaign_id));
        
        $order->add_order_note(sprintf(
            'Paiement confirmé. Campagne #%d créée. Envoi programmé.',
            $campaign_id
        ));
        
        $order->update_meta_data('_sci_campaign_status', 'scheduled');
        $order->save();
        
        lettre_laposte_log("✅ Envoi programmé pour dans 30 secondes");
    }
    
    /**
     * Récupère le prix unitaire du produit SCI
     */
    public function get_unit_price() {
        if (!$this->product_id) {
            return 5.00; // Prix par défaut
        }
        
        $product = wc_get_product($this->product_id);
        if (!$product) {
            return 5.00;
        }
        
        return floatval($product->get_price());
    }
    
    /**
     * Récupère l'ID du produit SCI
     */
    public function get_product_id() {
        return $this->product_id;
    }
    
    /**
     * Vérifie si WooCommerce est actif et configuré
     */
    public function is_woocommerce_ready() {
        return class_exists('WooCommerce') && $this->product_id > 0;
    }
}

// Initialiser l'intégration WooCommerce
$sci_woocommerce = new SCI_WooCommerce_Integration();

// Action programmée pour traiter les campagnes payées
add_action('sci_process_paid_campaign', 'sci_process_paid_campaign_background', 10, 2);

function sci_process_paid_campaign_background($order_id, $campaign_id) {
    lettre_laposte_log("=== DÉBUT TRAITEMENT ARRIÈRE-PLAN ===");
    lettre_laposte_log("Commande #$order_id - Campagne #$campaign_id");
    
    $order = wc_get_order($order_id);
    if (!$order) {
        lettre_laposte_log("❌ Commande introuvable");
        return;
    }
    
    $campaign_data = json_decode($order->get_meta('_sci_campaign_data'), true);
    if (!$campaign_data) {
        lettre_laposte_log("❌ Données de campagne introuvables");
        return;
    }
    
    lettre_laposte_log("Traitement de " . count($campaign_data['entries']) . " lettres");
    
    // Générer les PDFs
    if (!class_exists('TCPDF')) {
        require_once plugin_dir_path(__FILE__) . '../lib/tcpdf/tcpdf.php';
    }
    
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/campagnes/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $pdf_files = array();
    
    foreach ($campaign_data['entries'] as $index => $entry) {
        try {
            lettre_laposte_log("Génération PDF " . ($index + 1) . " pour: " . ($entry['denomination'] ?? 'N/A'));
            
            $nom = $entry['dirigeant'] ?? 'Dirigeant';
            $texte = str_replace('[NOM]', $nom, $campaign_data['content']);
            
            $pdf = new TCPDF();
            $pdf->SetCreator('SCI Plugin');
            $pdf->SetAuthor('SCI Plugin');
            $pdf->SetTitle('Lettre pour ' . ($entry['denomination'] ?? 'SCI'));
            
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(20, 20, 20);
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            $pdf->writeHTML(nl2br(htmlspecialchars($texte)), true, false, true, false, '');
            
            $filename = sanitize_file_name($entry['denomination'] . '-' . $nom . '-' . time() . '-' . $index) . '.pdf';
            $filepath = $pdf_dir . $filename;
            
            $pdf->Output($filepath, 'F');
            
            if (file_exists($filepath)) {
                $pdf_files[] = array(
                    'path' => $filepath,
                    'entry' => $entry
                );
                
                lettre_laposte_log("✅ PDF généré: $filename");
            } else {
                lettre_laposte_log("❌ Échec génération PDF pour: " . ($entry['denomination'] ?? 'N/A'));
            }
            
        } catch (Exception $e) {
            lettre_laposte_log("❌ Erreur génération PDF: " . $e->getMessage());
        }
    }
    
    lettre_laposte_log("PDFs générés: " . count($pdf_files) . "/" . count($campaign_data['entries']));
    
    // Envoyer les lettres une par une
    $campaign_manager = sci_campaign_manager();
    $config_manager = sci_config_manager();
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($pdf_files as $index => $pdf_data) {
        $entry = $pdf_data['entry'];
        
        lettre_laposte_log("Envoi lettre " . ($index + 1) . "/" . count($pdf_files) . " pour: " . ($entry['denomination'] ?? 'N/A'));
        
        // Lire le PDF et l'encoder en base64
        $pdf_content = file_get_contents($pdf_data['path']);
        $pdf_base64 = base64_encode($pdf_content);
        
        // Récupérer les données expéditeur
        $expedition_data = $campaign_manager->get_user_expedition_data($order->get_customer_id());
        
        // Préparer le payload
        $laposte_params = $config_manager->get_laposte_payload_params();
        $payload = array_merge($laposte_params, [
            "adresse_expedition" => $expedition_data,
            "adresse_destination" => [
                "civilite" => "",
                "prenom" => "",
                "nom" => $entry['dirigeant'] ?? '',
                "nom_societe" => $entry['denomination'] ?? '',
                "adresse_ligne1" => $entry['adresse'] ?? '',
                "adresse_ligne2" => "",
                "code_postal" => $entry['code_postal'] ?? '',
                "ville" => $entry['ville'] ?? '',
                "pays" => "FRANCE",
            ],
            "fichier" => [
                "format" => "pdf",
                "contenu_base64" => $pdf_base64,
            ],
        ]);
        
        // Envoyer via l'API La Poste
        $response = envoyer_lettre_via_api_la_poste_my_istymo($payload, $config_manager->get_laposte_token());
        
        if ($response['success']) {
            $campaign_manager->update_letter_status(
                $campaign_id,
                $entry['siren'],
                'sent',
                $response['uid'] ?? null
            );
            $success_count++;
            lettre_laposte_log("✅ Lettre envoyée - UID: " . ($response['uid'] ?? 'N/A'));
        } else {
            $error_msg = isset($response['message']) ? json_encode($response['message']) : ($response['error'] ?? 'Erreur inconnue');
            $campaign_manager->update_letter_status(
                $campaign_id,
                $entry['siren'],
                'failed',
                null,
                $error_msg
            );
            $error_count++;
            lettre_laposte_log("❌ Erreur envoi: $error_msg");
        }
        
        // Nettoyer le fichier PDF temporaire
        if (file_exists($pdf_data['path'])) {
            unlink($pdf_data['path']);
        }
        
        // Pause entre les envois
        sleep(2);
    }
    
    // Mettre à jour la commande
    $order->add_order_note(sprintf(
        'Campagne terminée : %d lettres envoyées, %d erreurs',
        $success_count,
        $error_count
    ));
    
    $order->update_meta_data('_sci_campaign_status', 'completed');
    $order->update_meta_data('_sci_campaign_success_count', $success_count);
    $order->update_meta_data('_sci_campaign_error_count', $error_count);
    $order->save();
    
    lettre_laposte_log("=== CAMPAGNE TERMINÉE ===");
    lettre_laposte_log("Succès: $success_count, Erreurs: $error_count");
}

/**
 * Fonction helper pour accéder à l'intégration WooCommerce
 */
function sci_woocommerce() {
    global $sci_woocommerce;
    return $sci_woocommerce;
}