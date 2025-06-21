document.addEventListener('DOMContentLoaded', function() {
    // Attendre que lettre.js soit chargé
    setTimeout(function() {
        attachPaymentHandlers();
    }, 100);
    
    function attachPaymentHandlers() {
        // Remplacer la fonction d'envoi de campagne existante
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'send-campaign') {
                e.preventDefault();
                e.stopPropagation();
                handleCampaignValidation();
            }
        });
    }
    
    function handleCampaignValidation() {
        const campaignTitle = document.getElementById('campaign-title');
        const campaignContent = document.getElementById('campaign-content');
        
        if (!campaignTitle || !campaignContent) {
            showValidationError('Éléments de formulaire introuvables');
            return;
        }
        
        if (!campaignTitle.value.trim() || !campaignContent.value.trim()) {
            showValidationError('Veuillez remplir le titre et le contenu de la campagne');
            return;
        }
        
        const selectedEntries = window.getSelectedEntries ? window.getSelectedEntries() : [];
        
        if (selectedEntries.length === 0) {
            showValidationError('Aucune SCI sélectionnée');
            return;
        }
        
        // Vérifier si WooCommerce est disponible
        if (!sciPaymentData.woocommerce_ready) {
            // Fallback vers l'ancien système d'envoi direct
            handleDirectSending(selectedEntries, campaignTitle.value, campaignContent.value);
            return;
        }
        
        // Passer à l'étape 3 (récapitulatif simplifié)
        showRecapStep(selectedEntries, campaignTitle.value, campaignContent.value);
    }
    
    function handleDirectSending(entries, title, content) {
        if (!confirm('WooCommerce n\'est pas disponible. Voulez-vous envoyer directement les lettres (sans paiement) ?')) {
            return;
        }
        
        const step2 = document.getElementById('step-2');
        
        // Afficher le processus d'envoi direct
        step2.innerHTML = `
            <h2>📬 Envoi en cours</h2>
            <div class="processing-container">
                <div class="processing-icon">⏳</div>
                <div class="processing-text">Génération des PDFs...</div>
                <div class="progress-bar">
                    <div class="progress-bar-fill" id="direct-progress"></div>
                </div>
                <div class="processing-subtext">Préparation des lettres...</div>
            </div>
        `;
        
        // Désactiver le bouton pendant l'envoi
        const sendBtn = document.getElementById('send-letters-btn');
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.textContent = 'Envoi en cours...';
        }
        
        // Préparer les données pour l'envoi
        const campaignData = {
            title: title,
            content: content,
            entries: entries
        };
        
        // Étape 1: Générer les PDFs et créer la campagne en BDD
        const formData = new FormData();
        formData.append('action', 'sci_generer_pdfs');
        formData.append('data', JSON.stringify(campaignData));
        formData.append('nonce', sciPaymentData.nonce);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.files && data.data.campaign_id) {
                // Étape 2: Envoyer chaque lettre via l'API La Poste
                updateDirectProgress(30, 'Envoi des lettres...');
                
                return sendLettersSequentially(data.data.files, entries, campaignData, data.data.campaign_id);
            } else {
                throw new Error('Erreur lors de la génération des PDFs: ' + (data.data || 'Erreur inconnue'));
            }
        })
        .then(results => {
            // Afficher les résultats
            const successCount = results.filter(r => r.success).length;
            const errorCount = results.length - successCount;
            
            updateDirectProgress(100, 'Envoi terminé !');
            
            setTimeout(() => {
                let message = `✅ Campagne terminée !\n\n`;
                message += `📊 Résultats :\n`;
                message += `• ${successCount} lettres envoyées avec succès\n`;
                if (errorCount > 0) {
                    message += `• ${errorCount} erreurs d'envoi\n`;
                }
                message += `\n📋 Consultez le détail dans "SCI > Mes Campagnes"`;
                
                alert(message);
                
                // Fermer le popup et réinitialiser
                document.getElementById('letters-popup').style.display = 'none';
                if (window.resetSciPopup) window.resetSciPopup();
            }, 1000);
            
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'envoi : ' + error.message);
        })
        .finally(() => {
            // Réactiver le bouton
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.textContent = '📬 Créer une campagne (0)';
            }
        });
    }
    
    function updateDirectProgress(percent, text) {
        const progressBar = document.getElementById('direct-progress');
        const statusText = document.querySelector('.processing-text');
        
        if (progressBar) {
            animateProgress(progressBar, progressBar.style.width.replace('%', '') || 0, percent, 500);
        }
        
        if (statusText) {
            statusText.textContent = text;
        }
    }
    
    // Fonction pour envoyer les lettres séquentiellement (reprise de l'ancien code)
    async function sendLettersSequentially(pdfFiles, entries, campaignData, campaignId) {
        const results = [];
        
        for (let i = 0; i < entries.length; i++) {
            const entry = entries[i];
            const pdfFile = pdfFiles[i];
            
            updateDirectProgress(30 + (i / entries.length) * 60, `Envoi vers ${entry.denomination}...`);
            
            try {
                // Télécharger le PDF généré
                const pdfResponse = await fetch(pdfFile.url);
                const pdfBlob = await pdfResponse.blob();
                const pdfBase64 = await blobToBase64(pdfBlob);
                
                // Préparer les données pour l'API La Poste
                const letterData = new FormData();
                letterData.append('action', 'sci_envoyer_lettre_laposte');
                letterData.append('entry', JSON.stringify(entry));
                letterData.append('pdf_base64', pdfBase64);
                letterData.append('campaign_title', campaignData.title);
                letterData.append('campaign_id', campaignId);
                
                // Envoyer via AJAX
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    body: letterData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    results.push({
                        success: true,
                        denomination: entry.denomination,
                        uid: result.data.uid
                    });
                } else {
                    results.push({
                        success: false,
                        denomination: entry.denomination,
                        error: result.data || 'Erreur inconnue'
                    });
                }
                
            } catch (error) {
                results.push({
                    success: false,
                    denomination: entry.denomination,
                    error: error.message
                });
            }
            
            // Petite pause entre les envois
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        return results;
    }
    
    // Fonction utilitaire pour convertir un blob en base64
    function blobToBase64(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                const base64 = reader.result.split(',')[1];
                resolve(base64);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }
    
    function showRecapStep(entries, title, content) {
        const step2 = document.getElementById('step-2');
        const sciCount = entries.length;
        
        // Récupérer le prix unitaire depuis PHP
        const unitPrice = parseFloat(sciPaymentData.unit_price || 5.00);
        const totalPrice = (sciCount * unitPrice).toFixed(2);
        
        // Créer l'interface de récapitulatif simplifiée (étape 3)
        const recapHtml = `
            <h2>📋 Récapitulatif de votre campagne</h2>
            
            <div class="campaign-recap">
                <div class="recap-section">
                    <h3>💰 Tarification</h3>
                    <div class="pricing-table">
                        <div class="pricing-row">
                            <span>Nombre de lettres :</span>
                            <span>${sciCount}</span>
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
                        <div class="service-item">✅ Envoi en lettre recommandée</div>
                        <div class="service-item">✅ Suivi de la distribution en temps réel</div>
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
            // Redirection directe vers le paiement WooCommerce
            createOrderAndRedirectToPayment(entries, title, content);
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
                if (window.resetSciPopup) window.resetSciPopup();
            }
        });
    }
    
    function createOrderAndRedirectToPayment(entries, title, content) {
        const step2 = document.getElementById('step-2');
        
        // Afficher le loader
        step2.innerHTML = `
            <h2>💳 Préparation du paiement</h2>
            <div class="processing-container">
                <div class="processing-icon">⏳</div>
                <div class="processing-text">Création de la commande...</div>
                <div class="progress-bar">
                    <div class="progress-bar-fill" id="payment-progress"></div>
                </div>
                <div class="processing-subtext">Redirection vers le paiement sécurisé...</div>
            </div>
        `;
        
        // Animation de la barre de progression
        const progressBar = document.getElementById('payment-progress');
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
                    // Redirection directe vers la page de paiement WooCommerce
                    window.location.href = data.data.checkout_url;
                }, 500);
            } else {
                showPaymentError(data.data || 'Erreur lors de la création de la commande');
                // Retour au récapitulatif en cas d'erreur
                showRecapStep(entries, title, content);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showPaymentError('Erreur réseau lors de la création de la commande');
            // Retour au récapitulatif en cas d'erreur
            showRecapStep(entries, title, content);
        });
    }
    
    function showContentStep(title, content) {
        const step2 = document.getElementById('step-2');
        
        const contentHtml = `
            <h2>✍️ Contenu du courriel</h2>
            <p style="color: #666; margin-bottom: 20px;">Rédigez le titre et le contenu de votre courriel</p>
            
            <label for="campaign-title"><strong>Titre de la campagne :</strong></label><br>
            <input type="text" id="campaign-title" style="width:100%; margin-bottom:20px; padding:10px; border:1px solid #ddd; border-radius:4px;" required placeholder="Ex: Proposition d'acquisition SCI" value="${escapeHtml(title)}"><br>

            <label for="campaign-content"><strong>Contenu du courriel :</strong></label><br>
            <textarea id="campaign-content" style="width:100%; height:200px; margin-bottom:20px; padding:10px; border:1px solid #ddd; border-radius:4px;" required placeholder="Rédigez votre message...">${escapeHtml(content)}</textarea>

            <div style="background: #e7f3ff; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
                <h4 style="margin-top: 0; color: #0056b3;">💡 Conseils pour votre courriel :</h4>
                <ul style="margin-bottom: 0; font-size: 14px; color: #495057;">
                    <li>Ajoutez <code style="background:#f8f9fa; padding:2px 4px; border-radius:3px;">[NOM]</code> à votre message pour qu'il soit remplacé par le nom du destinataire lors de l'envoi</li>
                    <li>Soyez professionnel et courtois dans votre approche</li>
                    <li>Précisez clairement l'objet de votre demande</li>
                    <li>N'oubliez pas d'ajouter vos coordonnées de contact</li>
                </ul>
            </div>

            <div style="text-align: center;">
                <button id="send-campaign" class="button button-primary button-large">
                    📋 Voir le récapitulatif →
                </button>
                <button id="back-to-step-1" class="button" style="margin-left:15px;">← Précédent</button>
                <button id="close-popup-2" class="button" style="margin-left:15px;">Fermer</button>
            </div>
        `;
        
        step2.innerHTML = contentHtml;
        
        // Réattacher les event listeners
        document.getElementById('back-to-step-1').addEventListener('click', function() {
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('step-1').style.display = 'block';
        });
        document.getElementById('close-popup-2').addEventListener('click', function() {
            document.getElementById('letters-popup').style.display = 'none';
            if (window.resetSciPopup) window.resetSciPopup();
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
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});