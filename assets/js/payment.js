document.addEventListener('DOMContentLoaded', function() {
    // Remplacer la fonction d'envoi de campagne existante
    const originalSendCampaignBtn = document.getElementById('send-campaign');
    if (originalSendCampaignBtn) {
        // Supprimer l'ancien event listener et en ajouter un nouveau
        originalSendCampaignBtn.replaceWith(originalSendCampaignBtn.cloneNode(true));
        
        const sendCampaignBtn = document.getElementById('send-campaign');
        sendCampaignBtn.addEventListener('click', handleCampaignPayment);
    }
    
    function handleCampaignPayment() {
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
        
        // Afficher l'étape de paiement
        showPaymentStep(selectedEntries, campaignTitle.value, campaignContent.value);
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
    
    function showPaymentStep(entries, title, content) {
        const step2 = document.getElementById('step-2');
        const sciCount = entries.length;
        
        // Récupérer le prix unitaire depuis PHP
        const unitPrice = parseFloat(sciPaymentData.unit_price || 5.00);
        const totalPrice = (sciCount * unitPrice).toFixed(2);
        
        // Créer l'interface de paiement avec checkout embarqué
        const paymentHtml = `
            <h2>💳 Finalisation de la campagne</h2>
            
            <div class="payment-summary">
                <h3>📊 Récapitulatif de votre commande</h3>
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px; margin-bottom: 15px;">
                    <div><strong>Campagne :</strong> ${escapeHtml(title)}</div>
                    <div></div>
                    <div>Nombre de SCI à contacter :</div>
                    <div>${sciCount}</div>
                    <div>Prix unitaire :</div>
                    <div>${unitPrice}€</div>
                    <hr style="grid-column: 1 / -1; margin: 10px 0;">
                    <div><strong>Total à payer :</strong></div>
                    <div class="payment-total" style="background: #0073aa; color: white; padding: 5px 10px; border-radius: 3px; font-weight: bold;">
                        ${totalPrice}€
                    </div>
                </div>
            </div>
            
            <div class="payment-features">
                <h4>📋 Services inclus :</h4>
                <ul>
                    <li>✅ Génération automatique des PDFs personnalisés</li>
                    <li>✅ Envoi en lettre recommandée avec accusé de réception</li>
                    <li>✅ Suivi de la distribution en temps réel</li>
                    <li>✅ Accusé de réception dématérialisé</li>
                    <li>✅ Historique complet dans vos campagnes</li>
                </ul>
            </div>
            
            <div id="payment-processing" style="display: none;">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 18px; margin-bottom: 15px;">⏳ Création de la commande...</div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="payment-progress"></div>
                    </div>
                    <div style="font-size: 14px; color: #666; margin-top: 10px;">
                        Préparation du paiement sécurisé...
                    </div>
                </div>
            </div>
            
            <div id="checkout-container" style="display: none;">
                <h3>💳 Paiement sécurisé</h3>
                <div id="embedded-checkout" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #fafafa;">
                    <!-- Le checkout WooCommerce sera chargé ici -->
                </div>
            </div>
            
            <div id="payment-buttons">
                <button id="proceed-payment" class="button button-primary" style="font-size: 16px; padding: 12px 24px;">
                    💳 Procéder au paiement (${totalPrice}€)
                </button>
                <button id="back-to-step-1-payment" class="button" style="margin-left: 10px;">
                    ← Modifier la campagne
                </button>
                <button id="close-popup-payment" class="button" style="margin-left: 10px;">
                    Annuler
                </button>
            </div>
            
            <div id="payment-success" style="display: none; text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 20px;">✅</div>
                <h3 style="color: #28a745; margin-bottom: 15px;">Paiement confirmé !</h3>
                <p style="margin-bottom: 20px;">Votre campagne est en cours de traitement.</p>
                <div class="progress-bar">
                    <div class="progress-bar-fill" id="sending-progress" style="width: 0%;"></div>
                </div>
                <div id="sending-status" style="margin-top: 15px; font-size: 14px; color: #666;">
                    Génération des PDFs en cours...
                </div>
            </div>
        `;
        
        step2.innerHTML = paymentHtml;
        
        // Event listeners pour les nouveaux boutons
        document.getElementById('proceed-payment').addEventListener('click', function() {
            createOrderAndShowCheckout(entries, title, content);
        });
        
        document.getElementById('back-to-step-1-payment').addEventListener('click', function() {
            // Revenir à l'étape de rédaction
            showContentStep(title, content);
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
                    
                    // Charger le checkout dans l'iframe ou via AJAX
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
        
        // Créer un iframe pour le checkout
        const iframe = document.createElement('iframe');
        iframe.src = checkoutUrl + '&embedded=1'; // Paramètre pour indiquer que c'est embarqué
        iframe.style.width = '100%';
        iframe.style.height = '600px';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '5px';
        iframe.name = 'checkout-frame';
        
        // Ajouter un message de chargement
        checkoutDiv.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 16px; margin-bottom: 10px;">🔒 Chargement du paiement sécurisé...</div>
                <div style="font-size: 14px; color: #666;">Veuillez patienter quelques secondes</div>
            </div>
        `;
        
        // Charger l'iframe après un court délai
        setTimeout(() => {
            checkoutDiv.innerHTML = '';
            checkoutDiv.appendChild(iframe);
            
            // Ajouter un bouton de retour
            const backButton = document.createElement('button');
            backButton.className = 'button';
            backButton.style.marginTop = '15px';
            backButton.textContent = '← Retour au récapitulatif';
            backButton.onclick = function() {
                document.getElementById('checkout-container').style.display = 'none';
                document.getElementById('payment-buttons').style.display = 'block';
            };
            checkoutDiv.appendChild(backButton);
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
        
        // Arrêter le polling après 10 minutes
        setTimeout(() => {
            clearInterval(pollInterval);
        }, 600000);
    }
    
    function handlePaymentSuccess(orderId) {
        const checkoutContainer = document.getElementById('checkout-container');
        const successDiv = document.getElementById('payment-success');
        
        // Masquer le checkout et afficher le succès
        checkoutContainer.style.display = 'none';
        successDiv.style.display = 'block';
        
        // Simuler le processus d'envoi
        simulateSendingProcess();
        
        // Programmer la fermeture automatique après 10 secondes
        setTimeout(() => {
            document.getElementById('letters-popup').style.display = 'none';
            resetPopup();
            
            // Rediriger vers la page des campagnes
            if (confirm('Paiement confirmé ! Voulez-vous consulter vos campagnes ?')) {
                window.location.href = sciPaymentData.campaigns_url || (window.location.origin + '/wp-admin/admin.php?page=sci-campaigns');
            }
        }, 10000);
    }
    
    function simulateSendingProcess() {
        const progressBar = document.getElementById('sending-progress');
        const statusDiv = document.getElementById('sending-status');
        
        const steps = [
            { progress: 20, text: 'Génération des PDFs personnalisés...' },
            { progress: 40, text: 'Préparation des adresses...' },
            { progress: 60, text: 'Connexion à l\'API La Poste...' },
            { progress: 80, text: 'Envoi des lettres en cours...' },
            { progress: 100, text: 'Campagne envoyée avec succès ! 🎉' }
        ];
        
        let currentStep = 0;
        
        const stepInterval = setInterval(() => {
            if (currentStep < steps.length) {
                const step = steps[currentStep];
                animateProgress(progressBar, progressBar.style.width.replace('%', '') || 0, step.progress, 800);
                statusDiv.textContent = step.text;
                currentStep++;
            } else {
                clearInterval(stepInterval);
            }
        }, 1500);
    }
    
    function showContentStep(title, content) {
        const step2 = document.getElementById('step-2');
        
        const contentHtml = `
            <h2>✍️ Contenu de la campagne</h2>
            <label for="campaign-title">Titre de la campagne :</label><br>
            <input type="text" id="campaign-title" style="width:100%; margin-bottom:15px;" required placeholder="Ex: Proposition d'achat SCI" value="${escapeHtml(title)}"><br>

            <label for="campaign-content">Contenu de la lettre :</label><br>
            <textarea id="campaign-content" style="width:100%; height:120px; margin-bottom:15px;" required placeholder="Utilisez [NOM] pour personnaliser avec le nom du dirigeant">${escapeHtml(content)}</textarea>

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
                💳 Continuer vers le paiement
            </button>
            <button id="back-to-step-1" class="button" style="margin-left:10px;">← Précédent</button>
            <button id="close-popup-2" class="button" style="margin-left:10px;">Fermer</button>
        `;
        
        step2.innerHTML = contentHtml;
        
        // Réattacher les event listeners
        document.getElementById('send-campaign').addEventListener('click', handleCampaignPayment);
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
        const buttons = step2.querySelector('#send-campaign').parentNode;
        step2.insertBefore(errorDiv, buttons);
        
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