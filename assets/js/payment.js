document.addEventListener('DOMContentLoaded', function() {
    // Remplacer la fonction d'envoi de campagne existante
    const originalSendCampaignBtn = document.getElementById('send-campaign');
    if (originalSendCampaignBtn) {
        // Supprimer l'ancien event listener et en ajouter un nouveau
        originalSendCampaignBtn.replaceWith(originalSendCampaignBtn.cloneNode(true));
        
        const sendCampaignBtn = document.getElementById('send-campaign');
        sendCampaignBtn.addEventListener('click', handleCampaignValidation);
    }
    
    function handleCampaignValidation() {
        const campaignTitle = document.getElementById('campaign-title');
        const campaignContent = document.getElementById('campaign-content');
        const selectedEntries = getSelectedEntries();
        
        if (!campaignTitle.value.trim() || !campaignContent.value.trim()) {
            showValidationError('Veuillez remplir le titre et le contenu de la campagne');
            return;
        }
        
        if (selectedEntries.length === 0) {
            showValidationError('Aucune SCI sélectionnée');
            return;
        }
        
        // Passer à l'étape 3 (récapitulatif)
        showRecapStep(selectedEntries, campaignTitle.value, campaignContent.value);
    }
    
    function getSelectedEntries() {
        const checkboxes = document.querySelectorAll('.send-letter-checkbox:checked');
        const entries = [];
        
        checkboxes.forEach(checkbox => {
            entries.push({
                denomination: checkbox.getAttribute('data-denomination'),
                dirigeant: checkbox.getAttribute('data-dirigeant'),
                siren: checkbox.getAttribute('data-siren'),
                adresse: checkbox.getAttribute('data-adresse'),
                ville: checkbox.getAttribute('data-ville'),
                code_postal: checkbox.getAttribute('data-code-postal')
            });
        });
        
        return entries;
    }
    
    function showRecapStep(entries, title, content) {
        const step2 = document.getElementById('step-2');
        const sciCount = entries.length;
        
        // Récupérer le prix unitaire depuis PHP
        const unitPrice = parseFloat(sciPaymentData.unit_price || 5.00);
        const totalPrice = (sciCount * unitPrice).toFixed(2);
        
        // Créer l'interface de récapitulatif (étape 3)
        const recapHtml = `
            <h2>📋 Récapitulatif de votre campagne</h2>
            
            <div class="campaign-recap">
                <div class="recap-section">
                    <h3>📝 Informations de la campagne</h3>
                    <div class="recap-item">
                        <strong>Titre :</strong> ${escapeHtml(title)}
                    </div>
                    <div class="recap-item">
                        <strong>Contenu :</strong>
                        <div class="content-preview">${escapeHtml(content).substring(0, 200)}${content.length > 200 ? '...' : ''}</div>
                    </div>
                </div>
                
                <div class="recap-section">
                    <h3>🏢 SCI sélectionnées (${sciCount})</h3>
                    <div class="sci-list-recap">
                        ${entries.map(entry => `
                            <div class="sci-item-recap">
                                <strong>${escapeHtml(entry.denomination)}</strong><br>
                                <small>Dirigeant: ${escapeHtml(entry.dirigeant)}</small><br>
                                <small>${escapeHtml(entry.adresse)}, ${escapeHtml(entry.code_postal)} ${escapeHtml(entry.ville)}</small>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="recap-section">
                    <h3>💰 Tarification</h3>
                    <div class="pricing-table">
                        <div class="pricing-row">
                            <span>Nombre de lettres :</span>
                            <span>${sciCount}</span>
                        </div>
                        <div class="pricing-row">
                            <span>Prix unitaire :</span>
                            <span>${unitPrice}€</span>
                        </div>
                        <div class="pricing-row total-row">
                            <span><strong>Total TTC :</strong></span>
                            <span><strong>${totalPrice}€</strong></span>
                        </div>
                    </div>
                </div>
                
                <div class="recap-section">
                    <h3>📦 Services inclus</h3>
                    <div class="services-list">
                        <div class="service-item">✅ Génération automatique des PDFs personnalisés</div>
                        <div class="service-item">✅ Envoi en lettre recommandée avec accusé de réception (LRAR)</div>
                        <div class="service-item">✅ Suivi de la distribution en temps réel</div>
                        <div class="service-item">✅ Accusé de réception dématérialisé</div>
                        <div class="service-item">✅ Historique complet dans vos campagnes</div>
                        <div class="service-item">✅ Support technique inclus</div>
                    </div>
                </div>
            </div>
            
            <div class="recap-buttons">
                <button id="proceed-to-payment" class="button button-primary button-large">
                    💳 Procéder au paiement (${totalPrice}€)
                </button>
                <button id="back-to-content" class="button" style="margin-left: 10px;">
                    ← Modifier le contenu
                </button>
                <button id="back-to-selection" class="button" style="margin-left: 10px;">
                    ← Modifier la sélection
                </button>
                <button id="close-popup-recap" class="button" style="margin-left: 10px;">
                    Annuler
                </button>
            </div>
        `;
        
        step2.innerHTML = recapHtml;
        
        // Event listeners pour les boutons de récapitulatif
        document.getElementById('proceed-to-payment').addEventListener('click', function() {
            showPaymentStep(entries, title, content);
        });
        
        document.getElementById('back-to-content').addEventListener('click', function() {
            showContentStep(title, content);
        });
        
        document.getElementById('back-to-selection').addEventListener('click', function() {
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('step-1').style.display = 'block';
        });
        
        document.getElementById('close-popup-recap').addEventListener('click', function() {
            if (confirm('Êtes-vous sûr de vouloir annuler ? Votre campagne ne sera pas sauvegardée.')) {
                document.getElementById('letters-popup').style.display = 'none';
                resetPopup();
            }
        });
    }
    
    function showPaymentStep(entries, title, content) {
        const step2 = document.getElementById('step-2');
        const sciCount = entries.length;
        
        // Récupérer le prix unitaire depuis PHP
        const unitPrice = parseFloat(sciPaymentData.unit_price || 5.00);
        const totalPrice = (sciCount * unitPrice).toFixed(2);
        
        // Créer l'interface de paiement (étape 4)
        const paymentHtml = `
            <h2>💳 Paiement sécurisé</h2>
            
            <div class="payment-header">
                <div class="payment-summary-compact">
                    <div class="summary-item">
                        <span>Campagne :</span>
                        <span><strong>${escapeHtml(title)}</strong></span>
                    </div>
                    <div class="summary-item">
                        <span>${sciCount} lettre${sciCount > 1 ? 's' : ''} :</span>
                        <span><strong>${totalPrice}€</strong></span>
                    </div>
                </div>
            </div>
            
            <div id="payment-processing" style="display: none;">
                <div class="processing-container">
                    <div class="processing-icon">⏳</div>
                    <div class="processing-text">Création de la commande...</div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="payment-progress"></div>
                    </div>
                    <div class="processing-subtext">Préparation du paiement sécurisé...</div>
                </div>
            </div>
            
            <div id="checkout-container" style="display: none;">
                <div class="checkout-header">
                    <h3>🔒 Paiement sécurisé WooCommerce</h3>
                    <p>Vos données de paiement sont protégées et chiffrées</p>
                </div>
                <div id="embedded-checkout">
                    <!-- Le checkout WooCommerce sera chargé ici -->
                </div>
            </div>
            
            <div id="payment-buttons">
                <button id="create-order-btn" class="button button-primary button-large">
                    🛒 Créer la commande
                </button>
                <button id="back-to-recap" class="button" style="margin-left: 10px;">
                    ← Retour au récapitulatif
                </button>
                <button id="close-popup-payment" class="button" style="margin-left: 10px;">
                    Annuler
                </button>
            </div>
            
            <div id="payment-success" style="display: none;">
                <div class="success-container">
                    <div class="success-icon">✅</div>
                    <h3>Paiement confirmé !</h3>
                    <p>Votre campagne est en cours de traitement.</p>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="sending-progress"></div>
                    </div>
                    <div id="sending-status">Génération des PDFs en cours...</div>
                    <div class="success-actions">
                        <button id="view-campaigns" class="button button-primary">
                            📋 Voir mes campagnes
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        step2.innerHTML = paymentHtml;
        
        // Event listeners pour les boutons de paiement
        document.getElementById('create-order-btn').addEventListener('click', function() {
            createOrderAndShowCheckout(entries, title, content);
        });
        
        document.getElementById('back-to-recap').addEventListener('click', function() {
            showRecapStep(entries, title, content);
        });
        
        document.getElementById('close-popup-payment').addEventListener('click', function() {
            if (confirm('Êtes-vous sûr de vouloir annuler ? Votre campagne ne sera pas sauvegardée.')) {
                document.getElementById('letters-popup').style.display = 'none';
                resetPopup();
            }
        });
    }
    
    function createOrderAndShowCheckout(entries, title, content) {
        const processingDiv = document.getElementById('payment-processing');
        const buttonsDiv = document.getElementById('payment-buttons');
        const checkoutContainer = document.getElementById('checkout-container');
        const progressBar = document.getElementById('payment-progress');
        
        // Afficher le loader
        processingDiv.style.display = 'block';
        buttonsDiv.style.display = 'none';
        
        // Animation de la barre de progression
        animateProgress(progressBar, 0, 90, 1500);
        
        // Préparer les données
        const campaignData = {
            title: title,
            content: content,
            entries: entries
        };
        
        const formData = new FormData();
        formData.append('action', 'sci_create_order');
        formData.append('campaign_data', JSON.stringify(campaignData));
        formData.append('nonce', sciPaymentData.nonce);
        
        // Créer la commande
        fetch(sciPaymentData.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            animateProgress(progressBar, 90, 100, 300);
            
            if (data.success) {
                setTimeout(() => {
                    // Masquer le processing et afficher le checkout embarqué
                    processingDiv.style.display = 'none';
                    checkoutContainer.style.display = 'block';
                    
                    // Charger le checkout dans l'iframe optimisé
                    loadEmbeddedCheckout(data.data.order_id, data.data.checkout_url);
                }, 500);
            } else {
                showPaymentError(data.data || 'Erreur lors de la création de la commande');
                processingDiv.style.display = 'none';
                buttonsDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showPaymentError('Erreur réseau lors de la création de la commande');
            processingDiv.style.display = 'none';
            buttonsDiv.style.display = 'block';
        });
    }
    
    function loadEmbeddedCheckout(orderId, checkoutUrl) {
        const checkoutDiv = document.getElementById('embedded-checkout');
        
        // Créer un iframe optimisé pour le checkout
        const iframe = document.createElement('iframe');
        iframe.src = checkoutUrl + '&embedded=1&hide_admin_bar=1'; // Paramètres pour optimiser l'affichage
        iframe.style.width = '100%';
        iframe.style.height = '700px'; // Hauteur augmentée pour plus de confort
        iframe.style.border = 'none';
        iframe.style.borderRadius = '8px';
        iframe.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        iframe.name = 'checkout-frame';
        iframe.id = 'checkout-iframe';
        
        // Message de chargement avec style amélioré
        checkoutDiv.innerHTML = `
            <div class="checkout-loading">
                <div class="loading-spinner"></div>
                <div class="loading-text">🔒 Chargement du paiement sécurisé...</div>
                <div class="loading-subtext">Connexion sécurisée en cours</div>
            </div>
        `;
        
        // Charger l'iframe après un court délai
        setTimeout(() => {
            checkoutDiv.innerHTML = '';
            checkoutDiv.appendChild(iframe);
            
            // Ajouter les boutons de navigation
            const navigationDiv = document.createElement('div');
            navigationDiv.className = 'checkout-navigation';
            navigationDiv.innerHTML = `
                <button id="back-to-recap-from-checkout" class="button">
                    ← Retour au récapitulatif
                </button>
                <button id="refresh-checkout" class="button" style="margin-left: 10px;">
                    🔄 Actualiser
                </button>
            `;
            checkoutDiv.appendChild(navigationDiv);
            
            // Event listeners pour la navigation
            document.getElementById('back-to-recap-from-checkout').onclick = function() {
                if (confirm('Êtes-vous sûr de vouloir revenir au récapitulatif ? La commande en cours sera annulée.')) {
                    showRecapStep(getSelectedEntries(), 
                        document.getElementById('campaign-title')?.value || '', 
                        document.getElementById('campaign-content')?.value || '');
                }
            };
            
            document.getElementById('refresh-checkout').onclick = function() {
                iframe.src = iframe.src; // Recharger l'iframe
            };
        }, 1000);
        
        // Écouter les messages de l'iframe pour détecter le succès du paiement
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'woocommerce_checkout_success') {
                handlePaymentSuccess(orderId);
            } else if (event.data && event.data.type === 'woocommerce_checkout_error') {
                showPaymentError(event.data.message || 'Erreur lors du paiement');
            }
        });
        
        // Polling pour vérifier le statut de la commande
        startPaymentStatusPolling(orderId);
    }
    
    function startPaymentStatusPolling(orderId) {
        const pollInterval = setInterval(() => {
            const formData = new FormData();
            formData.append('action', 'sci_check_order_status');
            formData.append('order_id', orderId);
            formData.append('nonce', sciPaymentData.nonce);
            
            fetch(sciPaymentData.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.status === 'paid') {
                    clearInterval(pollInterval);
                    handlePaymentSuccess(orderId);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification du statut:', error);
            });
        }, 3000); // Vérifier toutes les 3 secondes
        
        // Arrêter le polling après 15 minutes
        setTimeout(() => {
            clearInterval(pollInterval);
        }, 900000);
    }
    
    function handlePaymentSuccess(orderId) {
        const checkoutContainer = document.getElementById('checkout-container');
        const successDiv = document.getElementById('payment-success');
        
        // Masquer le checkout et afficher le succès
        checkoutContainer.style.display = 'none';
        successDiv.style.display = 'block';
        
        // Simuler le processus d'envoi
        simulateSendingProcess();
        
        // Event listener pour le bouton "Voir mes campagnes"
        document.getElementById('view-campaigns').addEventListener('click', function() {
            document.getElementById('letters-popup').style.display = 'none';
            resetPopup();
            window.location.href = sciPaymentData.campaigns_url || (window.location.origin + '/wp-admin/admin.php?page=sci-campaigns');
        });
        
        // Programmer la fermeture automatique après 15 secondes
        setTimeout(() => {
            if (confirm('Paiement confirmé ! Voulez-vous consulter vos campagnes maintenant ?')) {
                document.getElementById('view-campaigns').click();
            } else {
                document.getElementById('letters-popup').style.display = 'none';
                resetPopup();
            }
        }, 15000);
    }
    
    function simulateSendingProcess() {
        const progressBar = document.getElementById('sending-progress');
        const statusDiv = document.getElementById('sending-status');
        
        const steps = [
            { progress: 15, text: 'Validation du paiement...' },
            { progress: 30, text: 'Génération des PDFs personnalisés...' },
            { progress: 50, text: 'Préparation des adresses destinataires...' },
            { progress: 70, text: 'Connexion à l\'API La Poste...' },
            { progress: 90, text: 'Envoi des lettres en cours...' },
            { progress: 100, text: 'Campagne envoyée avec succès ! 🎉' }
        ];
        
        let currentStep = 0;
        
        const stepInterval = setInterval(() => {
            if (currentStep < steps.length) {
                const step = steps[currentStep];
                animateProgress(progressBar, progressBar.style.width.replace('%', '') || 0, step.progress, 1000);
                statusDiv.textContent = step.text;
                currentStep++;
            } else {
                clearInterval(stepInterval);
            }
        }, 1800);
    }
    
    function showContentStep(title, content) {
        const step2 = document.getElementById('step-2');
        
        const contentHtml = `
            <h2>✍️ Contenu de la campagne</h2>
            <label for="campaign-title">Titre de la campagne :</label><br>
            <input type="text" id="campaign-title" style="width:100%; margin-bottom:15px;" required placeholder="Ex: Proposition d'achat SCI" value="${escapeHtml(title)}"><br>

            <label for="campaign-content">Contenu de la lettre :</label><br>
            <textarea id="campaign-content" style="width:100%; height:120px; margin-bottom:15px;" required placeholder="Utilisez [NOM] pour personnaliser avec le nom du dirigeant

Exemple:
Madame, Monsieur [NOM],

Nous sommes intéressés par l'acquisition de votre SCI...">${escapeHtml(content)}</textarea>

            <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">💡 Conseils pour votre lettre :</h4>
                <ul style="margin-bottom: 0; font-size: 14px;">
                    <li>Utilisez <code>[NOM]</code> pour personnaliser avec le nom du dirigeant</li>
                    <li>Soyez professionnel et courtois</li>
                    <li>Précisez clairement votre demande</li>
                    <li>Ajoutez vos coordonnées de contact</li>
                </ul>
            </div>

            <button id="send-campaign" class="button button-primary" style="font-size: 16px; padding: 8px 16px;">
                📋 Voir le récapitulatif →
            </button>
            <button id="back-to-step-1" class="button" style="margin-left:10px;">← Précédent</button>
            <button id="close-popup-2" class="button" style="margin-left:10px;">Fermer</button>
        `;
        
        step2.innerHTML = contentHtml;
        
        // Réattacher les event listeners
        document.getElementById('send-campaign').addEventListener('click', handleCampaignValidation);
        document.getElementById('back-to-step-1').addEventListener('click', function() {
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('step-1').style.display = 'block';
        });
        document.getElementById('close-popup-2').addEventListener('click', function() {
            document.getElementById('letters-popup').style.display = 'none';
            resetPopup();
        });
    }
    
    function animateProgress(element, from, to, duration) {
        const start = parseFloat(from) || 0;
        const end = parseFloat(to);
        const startTime = Date.now();
        
        function update() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = start + (end - start) * progress;
            
            element.style.width = current + '%';
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }
    
    function showValidationError(message) {
        // Supprimer les anciennes erreurs
        const existingErrors = document.querySelectorAll('.validation-error');
        existingErrors.forEach(error => error.remove());
        
        // Créer la nouvelle erreur
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error';
        errorDiv.textContent = message;
        
        // L'insérer avant les boutons
        const step2 = document.getElementById('step-2');
        const buttons = step2.querySelector('#send-campaign')?.parentNode || 
                       step2.querySelector('#proceed-to-payment')?.parentNode ||
                       step2.querySelector('#create-order-btn')?.parentNode;
        if (buttons) {
            step2.insertBefore(errorDiv, buttons);
        }
        
        // La supprimer après 5 secondes
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
    
    function showPaymentError(message) {
        alert('Erreur de paiement : ' + message);
    }
    
    function resetPopup() {
        // Réinitialiser les sélections
        const checkboxes = document.querySelectorAll('.send-letter-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Réinitialiser les champs
        const titleField = document.getElementById('campaign-title');
        const contentField = document.getElementById('campaign-content');
        if (titleField) titleField.value = '';
        if (contentField) contentField.value = '';
        
        // Mettre à jour le compteur
        const selectedCount = document.getElementById('selected-count');
        if (selectedCount) selectedCount.textContent = '0';
        
        // Désactiver le bouton
        const sendBtn = document.getElementById('send-letters-btn');
        if (sendBtn) sendBtn.disabled = true;
        
        // Revenir à l'étape 1
        document.getElementById('step-1').style.display = 'block';
        document.getElementById('step-2').style.display = 'none';
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});