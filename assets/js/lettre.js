document.addEventListener('DOMContentLoaded', function() {
    const sendLettersBtn = document.getElementById('send-letters-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const lettersPopup = document.getElementById('letters-popup');
    const checkboxes = document.querySelectorAll('.send-letter-checkbox');
    
    // Éléments du popup
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const selectedSciList = document.getElementById('selected-sci-list');
    const campaignTitle = document.getElementById('campaign-title');
    const campaignContent = document.getElementById('campaign-content');
    
    // Boutons de navigation
    const toStep2Btn = document.getElementById('to-step-2');
    const backToStep1Btn = document.getElementById('back-to-step-1');
    const sendCampaignBtn = document.getElementById('send-campaign');
    const closePopupBtns = document.querySelectorAll('#close-popup-1, #close-popup-2');
    
    let selectedEntries = [];

    // Mise à jour du compteur et activation du bouton
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.send-letter-checkbox:checked');
        const count = checkedBoxes.length;
        
        selectedCountSpan.textContent = count;
        sendLettersBtn.disabled = count === 0;
        
        // Mettre à jour les entrées sélectionnées
        selectedEntries = [];
        checkedBoxes.forEach(checkbox => {
            selectedEntries.push({
                denomination: checkbox.getAttribute('data-denomination'),
                dirigeant: checkbox.getAttribute('data-dirigeant'),
                siren: checkbox.getAttribute('data-siren'),
                adresse: checkbox.getAttribute('data-adresse'),
                ville: checkbox.getAttribute('data-ville'),
                code_postal: checkbox.getAttribute('data-code-postal')
            });
        });
    }

    // Écouter les changements sur les checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    // Ouvrir le popup
    sendLettersBtn.addEventListener('click', function() {
        if (selectedEntries.length === 0) {
            alert('Veuillez sélectionner au moins une SCI');
            return;
        }
        
        // Remplir la liste des SCI sélectionnées
        selectedSciList.innerHTML = '';
        selectedEntries.forEach(entry => {
            const li = document.createElement('li');
            li.innerHTML = `
                <strong>${entry.denomination}</strong><br>
                <small>Dirigeant: ${entry.dirigeant}</small><br>
                <small>SIREN: ${entry.siren}</small><br>
                <small>${entry.adresse}, ${entry.code_postal} ${entry.ville}</small>
            `;
            selectedSciList.appendChild(li);
        });
        
        // Afficher le popup
        lettersPopup.style.display = 'flex';
        step1.style.display = 'block';
        step2.style.display = 'none';
    });

    // Navigation vers l'étape 2
    toStep2Btn.addEventListener('click', function() {
        step1.style.display = 'none';
        step2.style.display = 'block';
    });

    // Retour à l'étape 1
    backToStep1Btn.addEventListener('click', function() {
        step2.style.display = 'none';
        step1.style.display = 'block';
    });

    // Fermer le popup
    closePopupBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            lettersPopup.style.display = 'none';
            // Réinitialiser les champs
            campaignTitle.value = '';
            campaignContent.value = '';
        });
    });

    // Fermer le popup en cliquant sur l'arrière-plan
    lettersPopup.addEventListener('click', function(e) {
        if (e.target === lettersPopup) {
            lettersPopup.style.display = 'none';
        }
    });

    // Fonction pour afficher le progrès d'envoi
    function showProgress(current, total, message) {
        const progressHtml = `
            <div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
                <div style="font-weight: bold; margin-bottom: 10px;">📬 Envoi en cours...</div>
                <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div style="background: #0073aa; height: 100%; width: ${(current/total)*100}%; transition: width 0.3s;"></div>
                </div>
                <div style="margin-top: 10px; font-size: 14px;">
                    ${current}/${total} lettres envoyées
                </div>
                <div style="margin-top: 5px; font-size: 12px; color: #666;">
                    ${message}
                </div>
            </div>
        `;
        
        // Remplacer le contenu de l'étape 2
        step2.innerHTML = progressHtml;
    }

    // Envoyer la campagne
    sendCampaignBtn.addEventListener('click', function() {
        const title = campaignTitle.value.trim();
        const content = campaignContent.value.trim();
        
        if (!title || !content) {
            alert('Veuillez remplir le titre et le contenu de la campagne');
            return;
        }
        
        // Désactiver le bouton pendant l'envoi
        sendCampaignBtn.disabled = true;
        sendCampaignBtn.textContent = 'Génération des PDFs...';
        
        // Préparer les données pour l'envoi
        const campaignData = {
            title: title,
            content: content,
            entries: selectedEntries
        };
        
        // Étape 1: Générer les PDFs
        const formData = new FormData();
        formData.append('action', 'sci_generer_pdfs');
        formData.append('data', JSON.stringify(campaignData));
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.files) {
                // Étape 2: Envoyer chaque lettre via l'API La Poste
                sendCampaignBtn.textContent = 'Envoi des lettres...';
                showProgress(0, selectedEntries.length, 'Préparation de l\'envoi...');
                
                return sendLettersSequentially(data.data.files, selectedEntries, campaignData);
            } else {
                throw new Error('Erreur lors de la génération des PDFs: ' + (data.data || 'Erreur inconnue'));
            }
        })
        .then(results => {
            // Afficher les résultats
            const successCount = results.filter(r => r.success).length;
            const errorCount = results.length - successCount;
            
            let message = `✅ Campagne terminée !\n\n`;
            message += `📊 Résultats :\n`;
            message += `• ${successCount} lettres envoyées avec succès\n`;
            if (errorCount > 0) {
                message += `• ${errorCount} erreurs d'envoi\n`;
            }
            
            // Afficher les détails des erreurs si nécessaire
            const errors = results.filter(r => !r.success);
            if (errors.length > 0) {
                message += `\n❌ Erreurs :\n`;
                errors.forEach((error, index) => {
                    message += `• ${error.denomination}: ${error.error}\n`;
                });
            }
            
            alert(message);
            
            // Fermer le popup
            lettersPopup.style.display = 'none';
            
            // Réinitialiser les sélections
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
            
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'envoi : ' + error.message);
        })
        .finally(() => {
            // Réactiver le bouton
            sendCampaignBtn.disabled = false;
            sendCampaignBtn.textContent = 'Envoyer la campagne';
        });
    });

    // Fonction pour envoyer les lettres séquentiellement
    async function sendLettersSequentially(pdfFiles, entries, campaignData) {
        const results = [];
        
        for (let i = 0; i < entries.length; i++) {
            const entry = entries[i];
            const pdfFile = pdfFiles[i];
            
            showProgress(i, entries.length, `Envoi vers ${entry.denomination}...`);
            
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
            
            // Petite pause entre les envois pour éviter de surcharger l'API
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        showProgress(entries.length, entries.length, 'Envoi terminé !');
        
        return results;
    }

    // Fonction utilitaire pour convertir un blob en base64
    function blobToBase64(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                const base64 = reader.result.split(',')[1]; // Enlever le préfixe data:
                resolve(base64);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    // Initialiser le compteur
    updateSelectedCount();
});