<?php
/*
Plugin Name: SCI
Description: Plugin personnalisé SCI avec un panneau admin et un sélecteur de codes postaux.
Version: 1.1
Author: Brio Guiseppe
*/

if (!defined('ABSPATH')) exit; // Sécurité : Empêche l'accès direct au fichier

include plugin_dir_path(__FILE__) . 'popup-lettre.php';
require_once plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php';
require_once plugin_dir_path(__FILE__) . 'includes/favoris-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/config-manager.php';


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


function lettre_laposte_handle_form_admin_my_istymo() {
    //lettre_laposte_log("Début traitement formulaire");

    // Vérifie l'upload du PDF
    if (!isset($_FILES['lettre_pdf']) || $_FILES['lettre_pdf']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Erreur lors de l\'upload du fichier PDF.'];
    }

    // Vérifie le type MIME
    $pdf_tmp_path = $_FILES['lettre_pdf']['tmp_name'];
    $pdf_mime = mime_content_type($pdf_tmp_path);
    if ($pdf_mime !== 'application/pdf') {
        return ['success' => false, 'error' => 'Le fichier doit être un PDF valide.'];
    }

    // Convertit le PDF en base64
    $pdf_base64 = base64_encode(file_get_contents($pdf_tmp_path));

    // Prépare le payload avec les données du formulaire
    $payload = [
        "type_affranchissement" => "lrar",
        "type_enveloppe" => "auto",
        "enveloppe" => "fenetre",
        "couleur" => "nb",
        "recto_verso" => "rectoverso",
        "placement_adresse" => "insertion_page_adresse",
        "surimpression_adresses_document" => true,
        "impression_expediteur" => false,
        "ar_scan" => true,

        // Accusé de réception : on utilise prénom + nom expéditeur
        "ar_expediteur_champ1" => sanitize_text_field($_POST['exp_prenom'] ?? ''),
        "ar_expediteur_champ2" => sanitize_text_field($_POST['exp_nom'] ?? ''),

        // Adresse expéditeur (Étape 1)
        "adresse_expedition" => [
            "civilite" => "", // Pas de champ prévu dans le formulaire actuel
            "prenom" => sanitize_text_field($_POST['exp_prenom'] ?? ''),
            "nom" => sanitize_text_field($_POST['exp_nom'] ?? ''),
            "nom_societe" => "", // Pas de champ société pour l'expéditeur
            "adresse_ligne1" => sanitize_text_field($_POST['exp_adresse'] ?? ''),
            "adresse_ligne2" => "",
            "code_postal" => sanitize_text_field($_POST['exp_cp'] ?? ''),
            "ville" => sanitize_text_field($_POST['exp_ville'] ?? ''),
            "pays" => "FRANCE",
        ],

        // Adresse destinataire (Étape 2)
        "adresse_destination" => [
            "civilite" => "", // Pas de civilité ni prénom pour destinataire actuellement
            "prenom" => "",   // À ajouter si tu veux le gérer
            "nom" => sanitize_text_field($_POST['dest_nom'] ?? ''),
            "nom_societe" => sanitize_text_field($_POST['dest_societe'] ?? ''),
            "adresse_ligne1" => sanitize_text_field($_POST['dest_adresse1'] ?? ''),
            "adresse_ligne2" => "",
            "code_postal" => sanitize_text_field($_POST['dest_cp'] ?? ''),
            "ville" => sanitize_text_field($_POST['dest_ville'] ?? ''),
            "pays" => "FRANCE",
        ],

        // PDF encodé
        "fichier" => [
            "format" => "pdf",
            "contenu_base64" => $pdf_base64,
        ],
    ];

    // Log pour debug
    //lettre_laposte_log("Payload JSON : " . json_encode($payload));

    // Récupère le token depuis la configuration sécurisée
    $config_manager = sci_config_manager();
    $token = $config_manager->get_laposte_token();

    if (empty($token)) {
        return [
            'success' => false,
            'error' => 'Token La Poste non configuré. Veuillez configurer vos API dans les paramètres.'
        ];
    }

    // Envoi API
    $response = envoyer_lettre_via_api_la_poste_my_istymo($payload, $token);

    // Traitement réponse
    if ($response['success']) {
        $uid = $response['uid'] ?? 'non disponible';
        return [
            'success' => true,
            'message' => 'Lettre envoyée avec succès. UID : ' . esc_html($uid),
        ];
    } else {
        $error_msg = 'Erreur API : ';
        if (isset($response['message']) && is_array($response['message'])) {
            $error_msg .= json_encode($response['message']);
        } elseif (isset($response['error'])) {
            $error_msg .= $response['error'];
        } else {
            $error_msg .= 'Erreur inconnue';
        }

        return [
            'success' => false,
            'error' => $error_msg,
        ];
    }
}

    
    // Affichage du formulaire et des résultats
    ?>
    <div class="wrap">
        <h1>SCI – Code Postal</h1>
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
            <input type="submit" class="button button-primary" value="Rechercher les SCI">
            <button id="send-letters-btn" type="button" class="button button-secondary" disabled>
                📬 Envoyer les lettres (<span id="selected-count">0</span>)
            </button>
        </form>

        <?php if (!empty($results)): ?>
            <h2>Résultats :</h2>
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
                        <th>Campagne</th>
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

<!-- Popup lettre -->
<div id="letters-popup" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center;">
  <div style="background:#fff; padding:20px; width:400px; max-height:80vh; overflow-y:auto; border-radius:8px; box-shadow:0 0 15px rgba(0,0,0,0.3);">

    <!-- Step 1 : Liste des SCI sélectionnées -->
    <div class="step" id="step-1">
      <h2>SCI sélectionnées</h2>
      <ul id="selected-sci-list" style="max-height:300px; overflow-y:auto; border:1px solid #ccc; padding:10px; margin-bottom:20px;"></ul>
      <button id="to-step-2" class="button button-primary">Suivant</button>
      <button id="close-popup-1" class="button" style="margin-left:10px;">Fermer</button>
    </div>

    <!-- Step 2 : Saisie titre et contenu lettre -->
    <div class="step" id="step-2" style="display:none;">
      <h2>Campagne de lettre</h2>
      <label for="campaign-title">Titre de la campagne :</label><br>
      <input type="text" id="campaign-title" style="width:100%; margin-bottom:10px;" required><br>

      <label for="campaign-content">Contenu de la lettre :</label><br>
      <textarea id="campaign-content" style="width:100%; height:120px;" required></textarea><br><br>

      <button id="send-campaign" class="button button-primary">Envoyer la campagne</button>
      <button id="back-to-step-1" class="button" style="margin-left:10px;">Précédent</button>
      <button id="close-popup-2" class="button" style="margin-left:10px;">Fermer</button>
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

    // Localisation des variables AJAX pour le script favoris
    wp_localize_script('sci-favoris', 'sci_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sci_favoris_nonce')
    ));

    // Facultatif : ajouter ton CSS si besoin
    wp_enqueue_style(
        'sci-style',
        plugin_dir_url(__FILE__) . 'assets/css/style.css'
    );
}

// Déclencher la fonction quand le formulaire est soumis via AJAX
add_action('wp_ajax_sci_envoyer_lettre', 'lettre_laposte_handle_form_admin_wrapper');
add_action('wp_ajax_nopriv_sci_envoyer_lettre', 'lettre_laposte_handle_form_admin_wrapper');
add_action('wp_ajax_sci_envoyer_lettre', 'lettre_laposte_handle_form_admin');

// Le wrapper pour retourner un résultat JSON à JS
function lettre_laposte_handle_form_admin_wrapper() {
    $result = lettre_laposte_handle_form_admin_my_istymo();

    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['error']);
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

    $response = wp_remote_post($api_url, $args);

    // Gestion des erreurs WordPress
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error'   => $response->get_error_message(),
        ];
    }

    $code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    //lettre_laposte_log("Réponse API ($code) : $response_body");

    if ($code >= 200 && $code < 300) {
        return [
            'success' => true,
            'data'    => $data,
            'uid'     => $data['uid'] ?? null, // ✅ Extraction de l'UID
        ];
    } else {
        return [
            'success' => false,
            'code'    => $code,
            'message' => $data,
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($favoris)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">Aucun favori pour le moment.</td>
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

add_action('wp_ajax_sci_generer_pdfs', 'sci_generer_pdfs');
add_action('wp_ajax_nopriv_sci_generer_pdfs', 'sci_generer_pdfs'); // si non-connecté


function sci_generer_pdfs() {
    if (!isset($_POST['data'])) {
        wp_send_json_error("Aucune donnée reçue.");
    }

    $data = json_decode(stripslashes($_POST['data']), true);
    if (!isset($data['entries']) || !is_array($data['entries'])) {
        wp_send_json_error("Entrées invalides.");
    }

    // Inclure TCPDF ou FPDF
    require_once plugin_dir_path(__FILE__) . 'lib/tcpdf/tcpdf.php';

    $upload_dir = wp_upload_dir();
    $pdf_links = [];

    foreach ($data['entries'] as $entry) {
        $nom = $entry['dirigeant'] ?? 'Dirigeant';
        $texte = str_replace('[NOM]', $nom, $data['content']);

        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, $texte, '', 0, 'L', true);

        $filename = sanitize_title($entry['denomination'] . '-' . $nom) . '.pdf';
        $filepath = $upload_dir['basedir'] . '/campagnes/' . $filename;
        $fileurl  = $upload_dir['baseurl'] . '/campagnes/' . $filename;

        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $pdf->Output($filepath, 'F');

        $pdf_links[] = [
            'url' => $fileurl,
            'name' => $filename
        ];
    }

    wp_send_json_success(['files' => $pdf_links]);
}

add_action('admin_enqueue_scripts', function () {
    wp_localize_script('lettre-js', 'ajaxurl', admin_url('admin-ajax.php'));
});

?>