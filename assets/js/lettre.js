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
        sendCampaignBtn.textContent = 'Envoi en cours...';
        
        // Préparer les données pour l'envoi
        const campaignData = {
            title: title,
            content: content,
            entries: selectedEntries
        };
        
        // Envoyer via AJAX
        const formData = new FormData();
        formData.append('action', 'sci_generer_pdfs');
        formData.append('data', JSON.stringify(campaignData));
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Campagne générée avec succès !');
                
                // Afficher les liens de téléchargement
                if (data.data.files && data.data.files.length > 0) {
                    let downloadLinks = 'Fichiers générés :\n\n';
                    data.data.files.forEach(file => {
                        downloadLinks += `• ${file.name}\n`;
                    });
                    
                    if (confirm(downloadLinks + '\nVoulez-vous ouvrir le dossier de téléchargement ?')) {
                        // Ouvrir le premier fichier pour montrer l'emplacement
                        window.open(data.data.files[0].url, '_blank');
                    }
                }
                
                // Fermer le popup
                lettersPopup.style.display = 'none';
                
                // Réinitialiser les sélections
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateSelectedCount();
                
            } else {
                alert('Erreur lors de la génération : ' + (data.data || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur réseau lors de l\'envoi');
        })
        .finally(() => {
            // Réactiver le bouton
            sendCampaignBtn.disabled = false;
            sendCampaignBtn.textContent = 'Envoyer la campagne';
            
            // Réinitialiser les champs
            campaignTitle.value = '';
            campaignContent.value = '';
        });
    });

    // Initialiser le compteur
    updateSelectedCount();
});