document.addEventListener('DOMContentLoaded', function() {
    const sendLettersBtn = document.getElementById('send-letters-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const lettersPopup = document.getElementById('letters-popup');
    const checkboxes = document.querySelectorAll('.send-letter-checkbox');
    
    // Éléments du popup
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const selectedSciList = document.getElementById('selected-sci-list');
    
    // Boutons de navigation
    const toStep2Btn = document.getElementById('to-step-2');
    const closePopupBtns = document.querySelectorAll('#close-popup-1, #close-popup-2');
    
    let selectedEntries = [];

    // Contenu par défaut pour le courriel
    const defaultEmailContent = `Madame, Monsieur [NOM],

Nous espérons que ce courrier vous trouve en bonne santé.

Nous nous permettons de vous contacter concernant votre SCI et souhaitons vous faire part de notre intérêt pour d'éventuelles opportunités de collaboration ou d'acquisition.

Notre société, spécialisée dans l'investissement immobilier, recherche activement des biens et des structures juridiques adaptées à nos projets de développement.

Nous serions ravis de pouvoir échanger avec vous sur les possibilités qui pourraient s'offrir à nous mutuellement.

Si cette démarche vous intéresse, nous vous invitons à nous contacter afin de convenir d'un rendez-vous à votre convenance.

Dans l'attente de votre retour, nous vous prions d'agréer, Madame, Monsieur [NOM], l'expression de nos salutations distinguées.

Cordialement,

[Vos coordonnées]`;

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

    // Fermer le popup
    closePopupBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            lettersPopup.style.display = 'none';
            resetPopup();
        });
    });

    // Fermer le popup en cliquant sur l'arrière-plan
    lettersPopup.addEventListener('click', function(e) {
        if (e.target === lettersPopup) {
            lettersPopup.style.display = 'none';
            resetPopup();
        }
    });

    function resetPopup() {
        // Réinitialiser les champs
        const campaignTitle = document.getElementById('campaign-title');
        const campaignContent = document.getElementById('campaign-content');
        if (campaignTitle) campaignTitle.value = '';
        if (campaignContent) campaignContent.value = '';
        
        // Revenir à l'étape 1
        step1.style.display = 'block';
        step2.style.display = 'none';
        
        // Réinitialiser le contenu de l'étape 2 au contenu original
        resetStep2Content();
    }

    function resetStep2Content() {
        step2.innerHTML = `
            <h2>✍️ Contenu du courriel</h2>
            <p style="color: #666; margin-bottom: 20px;">Rédigez le titre et le contenu de votre courriel</p>
            
            <label for="campaign-title"><strong>Titre de la campagne :</strong></label><br>
            <input type="text" id="campaign-title" style="width:100%; margin-bottom:20px; padding:10px; border:1px solid #ddd; border-radius:4px;" required placeholder="Ex: Proposition d'acquisition SCI" value="Contact SCI - Opportunité d'acquisition"><br>

            <label for="campaign-content"><strong>Contenu du courriel :</strong></label><br>
            <textarea id="campaign-content" style="width:100%; height:200px; margin-bottom:20px; padding:10px; border:1px solid #ddd; border-radius:4px;" required placeholder="Rédigez votre message...">${defaultEmailContent}</textarea>

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
        
        // Réattacher les event listeners
        attachStep2Listeners();
    }

    function attachStep2Listeners() {
        const backToStep1Btn = document.getElementById('back-to-step-1');
        const closePopup2Btn = document.getElementById('close-popup-2');
        
        if (backToStep1Btn) {
            backToStep1Btn.addEventListener('click', function() {
                step2.style.display = 'none';
                step1.style.display = 'block';
            });
        }
        
        if (closePopup2Btn) {
            closePopup2Btn.addEventListener('click', function() {
                lettersPopup.style.display = 'none';
                resetPopup();
            });
        }
    }

    // Initialiser le contenu de l'étape 2
    resetStep2Content();

    // Initialiser le compteur
    updateSelectedCount();

    // Fonction utilitaire pour obtenir les entrées sélectionnées (utilisée par payment.js)
    window.getSelectedEntries = function() {
        return selectedEntries;
    };

    // Fonction utilitaire pour réinitialiser le popup (utilisée par payment.js)
    window.resetSciPopup = function() {
        resetPopup();
        
        // Réinitialiser les sélections
        const checkboxes = document.querySelectorAll('.send-letter-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedCount();
    };
});