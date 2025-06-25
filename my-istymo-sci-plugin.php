<?php
/*
Plugin Name: SCI
Description: Plugin personnalisé SCI avec un panneau admin et un sélecteur de codes postaux.
Version: 1.6
Author: Brio Guiseppe
*/

if (!defined('ABSPATH')) exit; // Sécurité : Empêche l'accès direct au fichier

include plugin_dir_path(__FILE__) . 'popup-lettre.php';
require_once plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php';
require_once plugin_dir_path(__FILE__) . 'includes/favoris-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/config-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/campaign-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-integration.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php'; // ✅ NOUVEAU


// --- Ajout du menu SCI dans l'admin WordPress ---
add_action('admin_menu', 'sci_ajouter_menu');

function sci_ajouter_menu() {
    add_menu_page(
        'SCI',
        'SCI',
        'read',
        'sci-panel',
        'sci_afficher_panel',
        'dashicons-admin-home',
        6
    );

    add_submenu_page(
        'sci-panel',
        'Favoris',
        'Mes Favoris',
        'read',
        'sci-favoris',
        'sci_favoris_page'
    );

    // Ajouter une page pour les campagnes
    add_submenu_page(
        'sci-panel',
        'Campagnes',
        'Mes Campagnes',
        'read',
        'sci-campaigns',
        'sci_campaigns_page'
    );

    // Ajouter une page pour voir les logs d'API
    add_submenu_page(
        'sci-panel',
        'Logs API',
        'Logs API',
        'manage_options',
        'sci-logs',
        'sci_logs_page'
    );
}


// --- Affichage du panneau d'administration SCI ---
function sci_afficher_panel() {
    $current_user = wp_get_current_user(); // Récupère l'utilisateur courant
    // Récupère via ACF (Advanced Custom Fields) le(s) code(s) postal(aux) de l'utilisateur
    $codePostal = get_field('code_postal_user', 'user_' . $current_user->ID);
    $codesPostauxArray = [];

    // Si un ou plusieurs codes postaux sont récupérés, on les prépare
    if ($codePostal) {
        // Supprime les espaces et sépare les codes postaux par ';'
        $codePostal = str_replace(' ', '', $codePostal);
        $codesPostauxArray = explode(';', $codePostal);
    }

    $resultats = []; // Initialise un tableau vide pour les résultats

    // Vérifier si la configuration API est complète
    $config_manager = sci_config_manager();
    if (!$config_manager->is_configured()) {
        echo '<div class="notice notice-error"><p><strong>⚠️ Configuration manquante :</strong> Veuillez configurer vos tokens API dans <a href="' . admin_url('admin.php?page=sci-config') . '">Configuration</a>.</p></div>';
    }

    // Vérifier WooCommerce
    $woocommerce_integration = sci_woocommerce();
    if (!$woocommerce_integration->is_woocommerce_ready()) {
        echo '<div class="notice notice-warning"><p><strong>⚠️ WooCommerce requis :</strong> Veuillez installer et configurer WooCommerce pour utiliser le système de paiement. <br><small>En attendant, vous pouvez utiliser le mode envoi direct (sans paiement).</small></p></div>';
    }

    // Vérifier la configuration des données expéditeur
    $campaign_manager = sci_campaign_manager();
    $expedition_data = $campaign_manager->get_user_expedition_data();
    $validation_errors = $campaign_manager->validate_expedition_data($expedition_data);
    
    if (!empty($validation_errors)) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>⚠️ Configuration expéditeur incomplète :</strong></p>';
        echo '<ul>';
        foreach ($validation_errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
        echo $campaign_manager->get_configuration_help();
        echo '</div>';
    }

    // ✅ NOUVEAU : Affichage des shortcodes disponibles avec URLs configurées
    echo '<div class="notice notice-info">';
    echo '<h4>📋 Shortcodes disponibles pour vos pages/articles :</h4>';
    echo '<ul>';
    echo '<li><code>[sci_panel]</code> - Panneau de recherche SCI complet';
    if ($config_manager->get_sci_panel_page_url()) {
        echo ' <small>(<a href="' . esc_url($config_manager->get_sci_panel_page_url()) . '" target="_blank">Voir la page</a>)</small>';
    }
    echo '</li>';
    echo '<li><code>[sci_favoris]</code> - Liste des SCI favoris';
    if ($config_manager->get_sci_favoris_page_url()) {
        echo ' <small>(<a href="' . esc_url($config_manager->get_sci_favoris_page_url()) . '" target="_blank">Voir la page</a>)</small>';
    }
    echo '</li>';
    echo '<li><code>[sci_campaigns]</code> - Liste des campagnes de lettres';
    if ($config_manager->get_sci_campaigns_page_url()) {
        echo ' <small>(<a href="' . esc_url($config_manager->get_sci_campaigns_page_url()) . '" target="_blank">Voir la page</a>)</small>';
    }
    echo '</li>';
    echo '</ul>';
    echo '<p><small>Copiez-collez ces shortcodes dans vos pages ou articles pour afficher les fonctionnalités SCI sur votre site.</small></p>';
    echo '<p><small>💡 <strong>Astuce :</strong> Configurez les URLs de vos pages dans <a href="' . admin_url('admin.php?page=sci-config') . '">Configuration</a> pour des redirections automatiques.</small></p>';
    echo '</div>';

    // Si un formulaire POST a été envoyé avec un code postal sélectionné
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['codePostal'])) {
        error_log('POST reçu: ' . print_r($_POST, true));

        $postal_code = sanitize_text_field($_POST['codePostal']);
        $resultats = sci_fetch_inpi_data($postal_code);

        // Vérification si $resultats est une erreur
        if (is_wp_error($resultats)) {
            echo '<div class="notice notice-error"><p>Erreur API : ' . esc_html($resultats->get_error_message()) . '</p></div>';
            $results = []; // Pas de résultats à afficher
        } elseif (empty($resultats)) {
            echo '<div class="notice notice-warning"><p>Aucun résultat trouvé.</p></div>';
            $results = [];
        } else {
            $results = sci_format_inpi_results($resultats);

            //echo '<pre>';
            //print_r($results);
            //echo '</pre>';

            //echo '<pre>Résultats bruts: ' . print_r($resultats, true) . '</pre>';
        }
    }

    
    // Affichage du formulaire et des résultats
    ?>
    <div class="wrap">
        <h1>🏢 SCI – Recherche et Contact</h1>
        <form method="post">
            <label for="codePostal">Sélectionnez votre code postal :</label><br><br>
            <select name="codePostal" id="codePostal" required>
                <option value="">— Choisir un code postal —</option>
                <?php foreach ($codesPostauxArray as $value): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($_POST['codePostal'] ?? '', $value); ?>>
                        <?php echo esc_html($value); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <input type="submit" class="button button-primary" value="🔍 Rechercher les SCI">
            <button id="send-letters-btn" type="button" class="button button-secondary" disabled>
                📬 Créer une campagne (<span id="selected-count">0</span>)
            </button>
        </form>

        <?php if (!empty($results)): ?>
            <h2>📋 Résultats de recherche :</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Favoris</th>
                        <th>Dénomination</th>
                        <th>Dirigeant</th>
                        <th>SIREN</th>
                        <th>Adresse</th>
                        <th>Ville</th>
                        <th>Code Postal</th>
                        <th>Déjà contacté ?</th>
                        <th>Géolocalisation</th> <!-- ✅ NOUVELLE COLONNE -->
                        <th>Sélection</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $res): ?>
                        <tr>                                    
                            <td><button class="fav-btn" 
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
                                <!-- ✅ NOUVELLE CELLULE : LIEN GOOGLE MAPS -->
                                <?php 
                                $maps_query = urlencode($res['adresse'] . ' ' . $res['code_postal'] . ' ' . $res['ville']);
                                $maps_url = 'https://www.google.com/maps/place/' . $maps_query;
                                ?>
                                <a href="<?php echo esc_url($maps_url); ?>" 
                                   target="_blank" 
                                   class="maps-link"
                                   title="Localiser <?php echo esc_attr($res['denomination']); ?> sur Google Maps">
                                    Géolocalisation
                                </a>
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

<!-- ✅ POPUP LETTRE SANS OMBRES -->
<div id="letters-popup" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:10000; justify-content:center; align-items:center;">
  <div style="background:#fff; padding:25px; width:700px; max-width:95vw; max-height:95vh; overflow-y:auto; border-radius:12px;">

    <!-- Étape 1 : Liste des SCI sélectionnées -->
    <div class="step" id="step-1">
      <h2>📋 SCI sélectionnées</h2>
      <p style="color: #666; margin-bottom: 20px;">Vérifiez votre sélection avant de continuer</p>
      <ul id="selected-sci-list" style="max-height:350px; overflow-y:auto; border:1px solid #ddd; padding:15px; margin-bottom:25px; border-radius:6px;"></ul>
      <div style="text-align: center;">
        <button id="to-step-2" class="button button-primary button-large">
          ✍️ Rédiger le courriel →
        </button>
        <button id="close-popup-1" class="button" style="margin-left:15px;">Fermer</button>
      </div>
    </div>

    <!-- Étape 2 : Saisie titre et contenu lettre (sera remplacée dynamiquement) -->
    <div class="step" id="step-2" style="display:none;">
      <!-- Le contenu sera généré par JavaScript -->
    </div>

  </div>
</div>

    <?php
}

// --- Appel API INPI pour récupérer les entreprises SCI par code postal ---
function sci_fetch_inpi_data($code_postal) {
    // Récupère le token depuis la configuration sécurisée
    $config_manager = sci_config_manager();
    $token = $config_manager->get_inpi_token();
    $api_url = $config_manager->get_inpi_api_url();

    if (empty($token)) {
        return new WP_Error('token_manquant', 'Token INPI non configuré. Veuillez configurer vos API dans les paramètres.');
    }

    // URL de l'API avec le code postal passé en paramètre dynamique
    $url = $api_url . '?companyName=SCI&pageSize=100&zipCodes[]=' . urlencode($code_postal);

    // Configuration des headers avec authorization et accept JSON
    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json'
        ],
        'timeout' => 20
    ];

    // Effectue la requête HTTP GET via WordPress HTTP API
    $reponse = wp_remote_get($url, $args);

    // Vérifie s'il y a une erreur réseau
    if (is_wp_error($reponse)) {
        return new WP_Error('requete_invalide', 'Erreur lors de la requête : ' . $reponse->get_error_message());
    }

    // Récupère le code HTTP et le corps de la réponse
    $code_http = wp_remote_retrieve_response_code($reponse);
    $corps     = wp_remote_retrieve_body($reponse);

    // Si le code HTTP n'est pas 200 OK, retourne une erreur
    if ($code_http !== 200) {
        return new WP_Error('api_inpi', "Erreur de l'API (code $code_http) : $corps");
    }

    // Décode le JSON en tableau associatif PHP
    $donnees = json_decode($corps, true);

    return $donnees; // Retourne les données brutes
}

// --- Formatage des données reçues de l'API pour affichage dans le tableau ---
function sci_format_inpi_results(array $data): array {
    $results = [];

    // Parcourt chaque société retournée par l'API
    foreach ($data as $company) {
        // Récupère en toute sécurité les données imbriquées avec l'opérateur ?? (existe ou vide)
        $denomination = $company['formality']['content']['personneMorale']['identite']['entreprise']['denomination'] ?? '';
        $siren       = $company['formality']['content']['personneMorale']['identite']['entreprise']['siren'] ?? '';

        $adresseData = $company['formality']['content']['personneMorale']['adresseEntreprise']['adresse'] ?? [];

        // Compose l'adresse complète (numéro + type de voie + nom de voie)
        $adresse_complete = array_filter([
            $adresseData['numVoie'] ?? '',
            $adresseData['typeVoie'] ?? '',
            $adresseData['voie'] ?? '',
        ]);
        $adresse_texte = implode(' ', $adresse_complete);

        // Récupère le premier dirigeant s'il existe
        $pouvoirs = $company['formality']['content']['personneMorale']['composition']['pouvoirs'] ?? [];
        $dirigeant = '';

        if (isset($pouvoirs[0]['individu']['descriptionPersonne'])) {
            $pers = $pouvoirs[0]['individu']['descriptionPersonne'];
            // Concatène nom + prénoms
            $dirigeant = trim(($pers['nom'] ?? '') . ' ' . implode(' ', $pers['prenoms'] ?? []));
        }

        // Ajoute les données formatées au tableau final
        $results[] = [
            'denomination' => $denomination,
            'siren'        => $siren,
            'dirigeant'    => $dirigeant,
            'adresse'      => $adresse_texte,
            'ville'        => $adresseData['commune'] ?? '',
            'code_postal'  => $adresseData['codePostal'] ?? '',
        ];
    }

    return $results;
}

add_action('admin_enqueue_scripts', 'sci_enqueue_admin_scripts');

function sci_enqueue_admin_scripts() {
    // Charge ton script JS personnalisé
    wp_enqueue_script(
        'sci-favoris',
        plugin_dir_url(__FILE__) . 'assets/js/favoris.js',
        array(), // dépendances, si tu utilises jQuery par exemple, mets ['jquery']
        '1.0',
        true // true = placer dans le footer
    );

    wp_enqueue_script(
        'sci-lettre-js',
        plugin_dir_url(__FILE__) . 'assets/js/lettre.js',
        array(), // ajouter 'jquery' si nécessaire
        '1.0',
        true
    );

    // Nouveau script pour le paiement
    wp_enqueue_script(
        'sci-payment-js',
        plugin_dir_url(__FILE__) . 'assets/js/payment.js',
        array(), 
        '1.0',
        true
    );

    // ✅ NOUVEAU : Récupérer les SIRENs contactés pour l'admin
    $campaign_manager = sci_campaign_manager();
    $contacted_sirens = $campaign_manager->get_user_contacted_sirens();

    // Localisation des variables AJAX pour le script favoris
    wp_localize_script('sci-favoris', 'sci_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sci_favoris_nonce'),
        'contacted_sirens' => $contacted_sirens // ✅ NOUVEAU : Liste des SIRENs contactés
    ));

    // Localisation pour le paiement - UTILISE L'URL STOCKÉE
    $woocommerce_integration = sci_woocommerce();
    $config_manager = sci_config_manager();
    wp_localize_script('sci-payment-js', 'sciPaymentData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sci_campaign_nonce'),
        'unit_price' => $woocommerce_integration->get_unit_price(),
        'woocommerce_ready' => $woocommerce_integration->is_woocommerce_ready(),
        'campaigns_url' => $config_manager->get_sci_campaigns_page_url() // ✅ MODIFIÉ : Utilise l'URL stockée
    ));

    // Localisation pour lettre.js (ajaxurl)
    wp_localize_script('sci-lettre-js', 'ajaxurl', admin_url('admin-ajax.php'));

    // Facultatif : ajouter ton CSS si besoin
    wp_enqueue_style(
        'sci-style',
        plugin_dir_url(__FILE__) . 'assets/css/style.css'
    );
}

// --- NOUVELLE FONCTION AJAX POUR ENVOYER UNE LETTRE VIA L'API LA POSTE ---
add_action('wp_ajax_sci_envoyer_lettre_laposte', 'sci_envoyer_lettre_laposte_ajax');
add_action('wp_ajax_nopriv_sci_envoyer_lettre_laposte', 'sci_envoyer_lettre_laposte_ajax');

function sci_envoyer_lettre_laposte_ajax() {
    // Vérification des données reçues
    if (!isset($_POST['entry']) || !isset($_POST['pdf_base64'])) {
        wp_send_json_error('Données manquantes');
        return;
    }

    $entry = json_decode(stripslashes($_POST['entry']), true);
    $pdf_base64 = $_POST['pdf_base64'];
    $campaign_title = sanitize_text_field($_POST['campaign_title'] ?? '');
    $campaign_id = intval($_POST['campaign_id'] ?? 0);

    if (!$entry || !$pdf_base64) {
        wp_send_json_error('Données invalides');
        return;
    }

    // Récupérer les données de l'expéditeur depuis le gestionnaire de campagnes
    $campaign_manager = sci_campaign_manager();
    $expedition_data = $campaign_manager->get_user_expedition_data();
    
    // Vérifier que les données essentielles sont présentes
    $validation_errors = $campaign_manager->validate_expedition_data($expedition_data);
    if (!empty($validation_errors)) {
        wp_send_json_error('Données expéditeur incomplètes : ' . implode(', ', $validation_errors));
        return;
    }
    
    // Récupérer les paramètres configurés depuis le gestionnaire de configuration
    $config_manager = sci_config_manager();
    $laposte_params = $config_manager->get_laposte_payload_params();
    
    // Préparer le payload pour l'API La Poste avec les paramètres dynamiques
    $payload = array_merge($laposte_params, [
        // Adresse expéditeur (récupérée depuis le profil utilisateur)
        "adresse_expedition" => $expedition_data,

        // Adresse destinataire (SCI sélectionnée)
        "adresse_destination" => [
            "civilite" => "", // Pas de civilité pour les SCI
            "prenom" => "",   // Pas de prénom pour les SCI
            "nom" => $entry['dirigeant'] ?? '',
            "nom_societe" => $entry['denomination'] ?? '',
            "adresse_ligne1" => $entry['adresse'] ?? '',
            "adresse_ligne2" => "",
            "code_postal" => $entry['code_postal'] ?? '',
            "ville" => $entry['ville'] ?? '',
            "pays" => "FRANCE",
        ],

        // PDF encodé
        "fichier" => [
            "format" => "pdf",
            "contenu_base64" => $pdf_base64,
        ],
    ]);

    // Récupérer le token depuis la configuration sécurisée
    $token = $config_manager->get_laposte_token();

    if (empty($token)) {
        wp_send_json_error('Token La Poste non configuré');
        return;
    }

    // Logger le payload avant envoi (sans le PDF pour éviter les logs trop volumineux)
    $payload_for_log = $payload;
    $payload_for_log['fichier']['contenu_base64'] = '[PDF_BASE64_CONTENT_' . strlen($pdf_base64) . '_CHARS]';
    lettre_laposte_log("=== ENVOI LETTRE POUR {$entry['denomination']} ===");
    lettre_laposte_log("Payload envoyé: " . json_encode($payload_for_log, JSON_PRETTY_PRINT));

    // Envoyer via l'API La Poste
    $response = envoyer_lettre_via_api_la_poste_my_istymo($payload, $token);

    // Logger la réponse complète
    lettre_laposte_log("Réponse complète API: " . json_encode($response, JSON_PRETTY_PRINT));

    if ($response['success']) {
        lettre_laposte_log("✅ SUCCÈS pour {$entry['denomination']} - UID: " . ($response['uid'] ?? 'N/A'));
        
        // Mettre à jour le statut dans la base de données
        if ($campaign_id > 0) {
            $campaign_manager->update_letter_status(
                $campaign_id, 
                $entry['siren'], 
                'sent', 
                $response['uid'] ?? null
            );
        }
        
        wp_send_json_success([
            'message' => 'Lettre envoyée avec succès',
            'uid' => $response['uid'] ?? 'non disponible',
            'denomination' => $entry['denomination']
        ]);
    } else {
        $error_msg = 'Erreur API : ';
        if (isset($response['message']) && is_array($response['message'])) {
            $error_msg .= json_encode($response['message']);
        } elseif (isset($response['error'])) {
            $error_msg .= $response['error'];
        } else {
            $error_msg .= 'Erreur inconnue';
        }

        lettre_laposte_log("❌ ERREUR pour {$entry['denomination']}: $error_msg");
        lettre_laposte_log("Code HTTP: " . ($response['code'] ?? 'N/A'));
        lettre_laposte_log("Message détaillé: " . json_encode($response['message'] ?? [], JSON_PRETTY_PRINT));
        
        // Mettre à jour le statut d'erreur dans la base de données
        if ($campaign_id > 0) {
            $campaign_manager->update_letter_status(
                $campaign_id, 
                $entry['siren'], 
                'failed', 
                null, 
                $error_msg
            );
        }
        
        wp_send_json_error($error_msg);
    }
}

function envoyer_lettre_via_api_la_poste_my_istymo($payload, $token) {
    // Récupère l'URL depuis la configuration sécurisée
    $config_manager = sci_config_manager();
    $api_url = $config_manager->get_laposte_api_url();

    $headers = [
        'apiKey'       => $token, // ✅ Authentification via apiKey
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ];

    $body = wp_json_encode($payload);

    $args = [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 30,
    ];

    // Logger la requête (sans le body pour éviter les logs trop volumineux)
    lettre_laposte_log("=== REQUÊTE API LA POSTE ===");
    lettre_laposte_log("URL: $api_url");
    lettre_laposte_log("Headers: " . json_encode($headers, JSON_PRETTY_PRINT));
    lettre_laposte_log("Body size: " . strlen($body) . " caractères");

    $response = wp_remote_post($api_url, $args);

    // Gestion des erreurs WordPress
    if (is_wp_error($response)) {
        lettre_laposte_log("❌ Erreur WordPress HTTP: " . $response->get_error_message());
        return [
            'success' => false,
            'error'   => $response->get_error_message(),
        ];
    }

    $code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_headers = wp_remote_retrieve_headers($response);
    
    // Logger la réponse complète
    lettre_laposte_log("=== RÉPONSE API LA POSTE ===");
    lettre_laposte_log("Code HTTP: $code");
    lettre_laposte_log("Headers de réponse: " . json_encode($response_headers->getAll(), JSON_PRETTY_PRINT));
    lettre_laposte_log("Body de réponse: $response_body");

    $data = json_decode($response_body, true);
    
    // Logger les données décodées
    lettre_laposte_log("Données JSON décodées: " . json_encode($data, JSON_PRETTY_PRINT));

    if ($code >= 200 && $code < 300) {
        lettre_laposte_log("✅ Succès API (code $code)");
        return [
            'success' => true,
            'data'    => $data,
            'uid'     => $data['uid'] ?? null, // ✅ Extraction de l'UID
        ];
    } else {
        lettre_laposte_log("❌ Erreur API (code $code)");
        return [
            'success' => false,
            'code'    => $code,
            'message' => $data,
            'raw_response' => $response_body,
        ];
    }
}

// --- AJOUT AJAX POUR RECUPERER LES FAVORIS ---

function sci_favoris_page() {
    global $sci_favoris_handler;
    $favoris = $sci_favoris_handler->get_favoris();
    ?>
    <div class="wrap">
        <h1>⭐ Mes SCI Favoris</h1>
        <table id="table-favoris" class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Dénomination</th>
                    <th>Dirigeant</th>
                    <th>SIREN</th>
                    <th>Adresse</th>
                    <th>Ville</th>
                    <th>Code Postal</th>
                    <th>📍 Localiser</th> <!-- ✅ NOUVELLE COLONNE -->
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($favoris)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">Aucun favori pour le moment.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($favoris as $fav): ?>
                        <tr>
                            <td><?php echo esc_html($fav['denomination']); ?></td>
                            <td><?php echo esc_html($fav['dirigeant']); ?></td>
                            <td><?php echo esc_html($fav['siren']); ?></td>
                            <td><?php echo esc_html($fav['adresse']); ?></td>
                            <td><?php echo esc_html($fav['ville']); ?></td>
                            <td><?php echo esc_html($fav['code_postal']); ?></td>
                            <td>
                                <!-- ✅ NOUVELLE CELLULE : LIEN GOOGLE MAPS -->
                                <?php 
                                $maps_query = urlencode($fav['adresse'] . ' ' . $fav['code_postal'] . ' ' . $fav['ville']);
                                $maps_url = 'https://www.google.com/maps/place/' . $maps_query;
                                ?>
                                <a href="<?php echo esc_url($maps_url); ?>" 
                                   target="_blank" 
                                   class="maps-link"
                                   title="Localiser <?php echo esc_attr($fav['denomination']); ?> sur Google Maps">
                                    📍 Maps
                                </a>
                            </td>
                            <td>
                                <button class="remove-fav-btn button button-small" 
                                        data-siren="<?php echo esc_attr($fav['siren']); ?>">
                                    🗑️ Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
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
                        location.reload(); // Recharger la page
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
}

// --- PAGE POUR AFFICHER LES CAMPAGNES ---
function sci_campaigns_page() {
    $campaign_manager = sci_campaign_manager();
    $campaigns = $campaign_manager->get_user_campaigns();
    
    // Gestion de l'affichage des détails d'une campagne
    if (isset($_GET['view']) && is_numeric($_GET['view'])) {
        $campaign_details = $campaign_manager->get_campaign_details(intval($_GET['view']));
        if ($campaign_details) {
            sci_display_campaign_details($campaign_details);
            return;
        }
    }
    ?>
    <div class="wrap">
        <h1>📬 Mes Campagnes de Lettres</h1>
        
        <?php if (empty($campaigns)): ?>
            <div class="notice notice-info">
                <p>Aucune campagne trouvée. Créez votre première campagne depuis la page principale SCI.</p>
            </div>
        <?php else: ?>
            <table class="widefat fixed striped">
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
                                <a href="<?php echo admin_url('admin.php?page=sci-campaigns&view=' . $campaign['id']); ?>" 
                                   class="button button-small">
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
}

function sci_display_campaign_details($campaign) {
    ?>
    <div class="wrap">
        <h1>📬 Détails de la campagne : <?php echo esc_html($campaign['title']); ?></h1>
        
        <a href="<?php echo admin_url('admin.php?page=sci-campaigns'); ?>" class="button">
            ← Retour aux campagnes
        </a>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
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
            <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;">
                <?php echo nl2br(esc_html($campaign['content'])); ?>
            </div>
        </div>
        
        <h3>📋 Détail des envois</h3>
        <table class="widefat fixed striped">
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
                                <code><?php echo esc_html($letter['laposte_uid']); ?></code>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $letter['sent_at'] ? date('d/m/Y H:i', strtotime($letter['sent_at'])) : '-'; ?>
                        </td>
                        <td>
                            <?php if ($letter['error_message']): ?>
                                <span style="color: red; font-size: 12px;">
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
}

// --- PAGE POUR AFFICHER LES LOGS D'API ---
function sci_logs_page() {
    ?>
    <div class="wrap">
        <h1>📋 Logs API La Poste</h1>
        <p>Consultez ici les logs détaillés des appels à l'API La Poste pour diagnostiquer les erreurs.</p>
        
        <div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>🔍 Derniers logs</h3>
            <?php
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/lettre-laposte/logs.txt';
            
            if (file_exists($log_file)) {
                $logs = file_get_contents($log_file);
                $log_lines = explode("\n", $logs);
                $recent_logs = array_slice($log_lines, -100); // 100 dernières lignes
                
                echo '<div style="background: #fff; padding: 10px; border: 1px solid #ccc; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">';
                echo esc_html(implode("\n", $recent_logs));
                echo '</div>';
                
                echo '<p><strong>Fichier de log :</strong> ' . esc_html($log_file) . '</p>';
                echo '<p><strong>Taille :</strong> ' . size_format(filesize($log_file)) . '</p>';
                echo '<p><strong>Dernière modification :</strong> ' . date('Y-m-d H:i:s', filemtime($log_file)) . '</p>';
            } else {
                echo '<p>Aucun fichier de log trouvé. Les logs apparaîtront après le premier envoi de lettre.</p>';
            }
            ?>
        </div>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;">
            <h4>💡 Comment utiliser ces logs :</h4>
            <ul>
                <li><strong>Payload envoyé :</strong> Vérifiez que toutes les données sont correctement formatées</li>
                <li><strong>Code HTTP :</strong> 
                    <ul>
                        <li>200-299 = Succès</li>
                        <li>400-499 = Erreur client (données invalides, authentification, etc.)</li>
                        <li>500-599 = Erreur serveur</li>
                    </ul>
                </li>
                <li><strong>Body de réponse :</strong> Contient les détails de l'erreur retournée par l'API</li>
                <li><strong>Headers :</strong> Informations sur l'authentification et le format des données</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=sci-logs&clear=1'); ?>" 
               class="button button-secondary"
               onclick="return confirm('Êtes-vous sûr de vouloir effacer tous les logs ?')">
                🗑️ Effacer les logs
            </a>
        </div>
    </div>
    
    <?php
    // Gestion de l'effacement des logs
    if (isset($_GET['clear']) && $_GET['clear'] == '1') {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/lettre-laposte/logs.txt';
        if (file_exists($log_file)) {
            unlink($log_file);
            echo '<div class="notice notice-success"><p>Logs effacés avec succès.</p></div>';
            echo '<script>window.location.href = "' . admin_url('admin.php?page=sci-logs') . '";</script>';
        }
    }
}

// --- FONCTION AJAX POUR GÉNÉRER LES PDFS (CORRIGÉE) ---
add_action('wp_ajax_sci_generer_pdfs', 'sci_generer_pdfs');
add_action('wp_ajax_nopriv_sci_generer_pdfs', 'sci_generer_pdfs');

function sci_generer_pdfs() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sci_campaign_nonce')) {
        wp_send_json_error('Nonce invalide');
        return;
    }

    if (!isset($_POST['data'])) {
        wp_send_json_error("Aucune donnée reçue.");
        return;
    }

    $data = json_decode(stripslashes($_POST['data']), true);
    if (!isset($data['entries']) || !is_array($data['entries'])) {
        wp_send_json_error("Entrées invalides.");
        return;
    }

    lettre_laposte_log("=== DÉBUT GÉNÉRATION PDFs ===");
    lettre_laposte_log("Titre campagne: " . ($data['title'] ?? 'N/A'));
    lettre_laposte_log("Nombre d'entrées: " . count($data['entries']));

    // Créer la campagne en base de données
    $campaign_manager = sci_campaign_manager();
    $campaign_id = $campaign_manager->create_campaign($data['title'], $data['content'], $data['entries']);
    
    if (is_wp_error($campaign_id)) {
        lettre_laposte_log("❌ Erreur création campagne: " . $campaign_id->get_error_message());
        wp_send_json_error("Erreur lors de la création de la campagne : " . $campaign_id->get_error_message());
        return;
    }

    lettre_laposte_log("✅ Campagne créée avec ID: $campaign_id");

    // Inclure TCPDF
    if (!class_exists('TCPDF')) {
        require_once plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php';
    }

    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/campagnes/';
    $pdf_url_base = $upload_dir['baseurl'] . '/campagnes/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
        lettre_laposte_log("📁 Dossier créé: $pdf_dir");
    }

    $pdf_links = [];

    foreach ($data['entries'] as $index => $entry) {
        try {
            lettre_laposte_log("📄 Génération PDF " . ($index + 1) . "/" . count($data['entries']) . " pour: " . ($entry['denomination'] ?? 'N/A'));
            
            $nom = $entry['dirigeant'] ?? 'Dirigeant';
            $texte = str_replace('[NOM]', $nom, $data['content']);

            // Créer le PDF avec TCPDF
            $pdf = new TCPDF();
            $pdf->SetCreator('SCI Plugin');
            $pdf->SetAuthor('SCI Plugin');
            $pdf->SetTitle('Lettre pour ' . ($entry['denomination'] ?? 'SCI'));
            $pdf->SetSubject('Lettre SCI');
            
            // Paramètres de page
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(20, 20, 20);
            $pdf->SetAutoPageBreak(TRUE, 25);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Ajouter une page
            $pdf->AddPage();
            
            // Définir la police
            $pdf->SetFont('helvetica', '', 12);
            
            // Ajouter le contenu
            $pdf->writeHTML(nl2br(htmlspecialchars($texte)), true, false, true, false, '');

            // Générer le nom de fichier sécurisé
            $filename = sanitize_file_name($entry['denomination'] . '-' . $nom . '-' . time() . '-' . $index) . '.pdf';
            $filepath = $pdf_dir . $filename;
            $fileurl = $pdf_url_base . $filename;

            // Sauvegarder le PDF
            $pdf->Output($filepath, 'F');

            // Vérifier que le fichier a été créé
            if (file_exists($filepath)) {
                $pdf_links[] = [
                    'url' => $fileurl,
                    'name' => $filename,
                    'path' => $filepath
                ];
                
                lettre_laposte_log("✅ PDF généré avec succès : $filename pour {$entry['denomination']}");
            } else {
                lettre_laposte_log("❌ Erreur : PDF non créé pour {$entry['denomination']}");
            }

        } catch (Exception $e) {
            lettre_laposte_log("❌ Erreur lors de la génération PDF pour {$entry['denomination']}: " . $e->getMessage());
        }
    }

    if (empty($pdf_links)) {
        lettre_laposte_log("❌ Aucun PDF généré");
        wp_send_json_error('Aucun PDF n\'a pu être généré');
        return;
    }

    lettre_laposte_log("✅ Génération terminée : " . count($pdf_links) . " PDFs créés sur " . count($data['entries']) . " demandés");

    wp_send_json_success([
        'files' => $pdf_links,
        'campaign_id' => $campaign_id,
        'message' => count($pdf_links) . ' PDFs générés avec succès'
    ]);
}

?>