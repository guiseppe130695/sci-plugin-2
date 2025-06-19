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
        
        // Hook pour traiter les commandes payées
        add_action('woocommerce_order_status_completed', array($this, 'process_paid_campaign'));
        add_action('woocommerce_order_status_processing', array($this, 'process_paid_campaign'));
        
        // Hook pour les paiements instantanés (cartes, PayPal, etc.)
        add_action('woocommerce_payment_complete', array($this, 'process_paid_campaign'));
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
        
        // Créer la commande WooCommerce
        $order_id = $this->create_woocommerce_order($campaign_data, $sci_count);
        
        if (is_wp_error($order_id)) {
            wp_send_json_error('Erreur lors de la création de la commande : ' . $order_id->get_error_message());
            return;
        }
        
        // Retourner l'URL de paiement
        $order = wc_get_order($order_id);
        $checkout_url = $order->get_checkout_payment_url();
        
        wp_send_json_success(array(
            'order_id' => $order_id,
            'checkout_url' => $checkout_url,
            'total' => $order->get_total(),
            'sci_count' => $sci_count
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
        
        // Marquer comme en cours de traitement
        $order->update_meta_data('_sci_campaign_status', 'processing');
        $order->save();
        
        // Décoder les données de campagne
        $campaign_data = json_decode($campaign_data, true);
        if (!$campaign_data) {
            $order->add_order_note('Erreur : données de campagne invalides');
            return;
        }
        
        // Créer la campagne en base de données
        $campaign_manager = sci_campaign_manager();
        $campaign_id = $campaign_manager->create_campaign(
            $campaign_data['title'],
            $campaign_data['content'],
            $campaign_data['entries']
        );
        
        if (is_wp_error($campaign_id)) {
            $order->add_order_note('Erreur lors de la création de la campagne : ' . $campaign_id->get_error_message());
            $order->update_meta_data('_sci_campaign_status', 'error');
            $order->save();
            return;
        }
        
        // Sauvegarder l'ID de campagne
        $order->update_meta_data('_sci_campaign_id', $campaign_id);
        
        // Programmer l'envoi des lettres (en arrière-plan)
        wp_schedule_single_event(time() + 60, 'sci_process_paid_campaign', array($order_id, $campaign_id));
        
        $order->add_order_note(sprintf(
            'Paiement confirmé. Campagne #%d créée. Envoi programmé.',
            $campaign_id
        ));
        
        $order->update_meta_data('_sci_campaign_status', 'scheduled');
        $order->save();
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
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $campaign_data = json_decode($order->get_meta('_sci_campaign_data'), true);
    if (!$campaign_data) {
        return;
    }
    
    // Générer les PDFs
    require_once plugin_dir_path(__FILE__) . '../lib/tcpdf/tcpdf.php';
    
    $upload_dir = wp_upload_dir();
    $pdf_files = array();
    
    foreach ($campaign_data['entries'] as $entry) {
        $nom = $entry['dirigeant'] ?? 'Dirigeant';
        $texte = str_replace('[NOM]', $nom, $campaign_data['content']);
        
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, $texte, '', 0, 'L', true);
        
        $filename = sanitize_title($entry['denomination'] . '-' . $nom) . '.pdf';
        $filepath = $upload_dir['basedir'] . '/campagnes/' . $filename;
        $fileurl = $upload_dir['baseurl'] . '/campagnes/' . $filename;
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $pdf->Output($filepath, 'F');
        
        $pdf_files[] = array(
            'url' => $fileurl,
            'path' => $filepath,
            'entry' => $entry
        );
    }
    
    // Envoyer les lettres une par une
    $campaign_manager = sci_campaign_manager();
    $config_manager = sci_config_manager();
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($pdf_files as $pdf_data) {
        $entry = $pdf_data['entry'];
        
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
        }
        
        // Nettoyer le fichier PDF temporaire
        unlink($pdf_data['path']);
        
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
}

/**
 * Fonction helper pour accéder à l'intégration WooCommerce
 */
function sci_woocommerce() {
    global $sci_woocommerce;
    return $sci_woocommerce;
}