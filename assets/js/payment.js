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
            alert('Veuillez remplir le titre et le contenu de la campagne');
            return;
        }
        
        if (selectedEntries.length === 0) {
            alert('Aucune SCI sélectionnée');
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
        
        // Récupérer le prix unitaire depuis PHP (sera injecté via wp_localize_script)
        const unitPrice = parseFloat(sciPaymentData.unit_price || 5.00);
        const totalPrice = (sciCount * unitPrice).toFixed(2);
        
        // Créer l'interface de paiement
        const paymentHtml = `
            <h2>💳 Paiement de la campagne</h2>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">📊 Récapitulatif</h3>
                <p><strong>Titre :</strong> ${escapeHtml(title)}</p>
                <p><strong>Nombre de SCI :</strong> ${sciCount}</p>
                <p><strong>Prix unitaire :</strong> ${unitPrice}€ par lettre</p>
                <p><strong>Total à payer :</strong> <span style="font-size: 1.2em; color: #0073aa;"><strong>${totalPrice}€</strong></span></p>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">📋 Ce qui est inclus :</h4>
                <ul style="margin-bottom: 0;">
                    <li>✅ Génération automatique des PDFs personnalisés</li>
                    <li>✅ Envoi en lettre recommandée avec accusé de réception</li>
                    <li>✅ Suivi de la distribution</li>
                    <li>✅ Accusé de réception dématérialisé</li>
                    <li>✅ Historique complet dans vos campagnes</li>
                </ul>
            </div>
            
            <div id="payment-processing" style="display: none; text-align: center; padding: 20px;">
                <div style="font-size: 18px; margin-bottom: 10px;">⏳ Création de la commande...</div>
                <div style="background: #ddd; height: 4px; border-radius: 2px; overflow: hidden;">
                    <div style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;" id="payment-progress"></div>
                </div>
            </div>
            
            <div id="payment-buttons">
                <button id="proceed-payment" class="button button-primary" style="font-size: 16px; padding: 10px 20px;">
                    💳 Procéder au paiement (${totalPrice}€)
                </button>
                <button id="back-to-step-1-payment" class="button" style="margin-left: 10px;">
                    ← Modifier la campagne
                </button>
                <button id="close-popup-payment" class="button" style="margin-left: 10px;">
                    Annuler
                </button>
            </div>
        `;
        
        step2.innerHTML = paymentHtml;
        
        // Event listeners pour les nouveaux boutons
        document.getElementById('proceed-payment').addEventListener('click', function() {
            createOrderAndRedirect(entries, title, content);
        });
        
        document.getElementById('back-to-step-1-payment').addEventListener('click', function() {
            // Revenir à l'étape 1
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('step-1').style.display = 'block';
        });
        
        document.getElementById('close-popup-payment').addEventListener('click', function() {
            document.getElementById('letters-popup').style.display = 'none';
        });
    }
    
    function createOrderAndRedirect(entries, title, content) {
        const processingDiv = document.getElementById('payment-processing');
        const buttonsDiv = document.getElementById('payment-buttons');
        const progressBar = document.getElementById('payment-progress');
        
        // Afficher le loader
        processingDiv.style.display = 'block';
        buttonsDiv.style.display = 'none';
        
        // Animation de la barre de progression
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            progressBar.style.width = progress + '%';
            if (progress >= 90) {
                clearInterval(progressInterval);
            }
        }, 100);
        
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
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            
            if (data.success) {
                // Rediriger vers la page de paiement WooCommerce
                setTimeout(() => {
                    window.location.href = data.data.checkout_url;
                }, 500);
            } else {
                // Afficher l'erreur
                alert('Erreur lors de la création de la commande : ' + (data.data || 'Erreur inconnue'));
                
                // Réafficher les boutons
                processingDiv.style.display = 'none';
                buttonsDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur réseau lors de la création de la commande');
            
            clearInterval(progressInterval);
            processingDiv.style.display = 'none';
            buttonsDiv.style.display = 'block';
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});