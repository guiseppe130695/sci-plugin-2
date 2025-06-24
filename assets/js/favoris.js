document.addEventListener('DOMContentLoaded', function() {
    const favButtons = document.querySelectorAll('.fav-btn');

    // Charger les favoris depuis localStorage (tableau d'objets)
    let favoris = JSON.parse(localStorage.getItem('sci_favoris') || '[]');

    // Met à jour l'affichage des boutons favoris
    function updateFavButtons() {
        favButtons.forEach(btn => {
            const siren = btn.getAttribute('data-siren');
            const isFavori = favoris.some(fav => fav.siren === siren);

            if (isFavori) {
                btn.textContent = '★'; // étoile pleine
                btn.classList.add('favori');
            } else {
                btn.textContent = '☆'; // étoile vide
                btn.classList.remove('favori');
            }
        });
    }

    // ✅ NOUVELLE FONCTION : Met à jour le statut de contact des SCI
    function updateContactStatus() {
        // Vérifier que les données sont disponibles
        if (typeof sci_ajax === 'undefined' || !sci_ajax.contacted_sirens) {
            console.log('Données de contact non disponibles');
            return;
        }

        const contactedSirens = sci_ajax.contacted_sirens;
        console.log('SIRENs contactés:', contactedSirens);

        // Parcourir tous les éléments de statut de contact
        const statusElements = document.querySelectorAll('.contact-status');
        
        statusElements.forEach(statusElement => {
            const siren = statusElement.getAttribute('data-siren');
            const iconElement = statusElement.querySelector('.contact-status-icon');
            const textElement = statusElement.querySelector('.contact-status-text');
            
            if (!siren || !iconElement || !textElement) {
                return;
            }

            // Vérifier si cette SCI a été contactée
            const isContacted = contactedSirens.includes(siren);
            
            if (isContacted) {
                // SCI déjà contactée - Afficher le badge
                statusElement.className = 'contact-status contacted';
                statusElement.style.display = 'inline-block';
                iconElement.textContent = '✅';
                textElement.textContent = 'Contacté';
                statusElement.title = 'Cette SCI a déjà été contactée dans une campagne précédente';
            } else {
                // SCI non contactée - Masquer complètement le badge
                statusElement.style.display = 'none';
                statusElement.title = '';
            }
        });

        console.log('Statut de contact mis à jour pour', statusElements.length, 'SCI');
    }

    // Synchronise les favoris avec la base de données
    function syncFavorisWithDB(action, sciData = null) {
        const formData = new FormData();
        formData.append('action', 'sci_manage_favoris');
        formData.append('operation', action);
        formData.append('nonce', sci_ajax.nonce);
        
        if (sciData) {
            formData.append('sci_data', JSON.stringify(sciData));
        }

        fetch(sci_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Favoris synchronisés avec la DB');
                if (action === 'get') {
                    // Met à jour localStorage avec les données de la DB
                    favoris = data.data || [];
                    localStorage.setItem('sci_favoris', JSON.stringify(favoris));
                    updateFavButtons();
                }
            } else {
                console.error('Erreur sync DB:', data.data);
            }
        })
        .catch(error => {
            console.error('Erreur réseau:', error);
        });
    }

    // Charge les favoris depuis la DB au démarrage
    syncFavorisWithDB('get');

    // Ajoute ou retire un favori
    favButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const siren = btn.getAttribute('data-siren');
            // Récupérer toutes les données du SCI depuis les data-attributes du bouton
            const sciData = {
                siren: siren,
                denomination: btn.getAttribute('data-denomination'),
                dirigeant: btn.getAttribute('data-dirigeant'),
                adresse: btn.getAttribute('data-adresse'),
                ville: btn.getAttribute('data-ville'),
                code_postal: btn.getAttribute('data-code-postal'),
            };

            const index = favoris.findIndex(fav => fav.siren === siren);
            if (index !== -1) {
                // Supprimer des favoris
                favoris.splice(index, 1);
                syncFavorisWithDB('remove', sciData);
            } else {
                // Ajouter aux favoris
                favoris.push(sciData);
                syncFavorisWithDB('add', sciData);
            }

            localStorage.setItem('sci_favoris', JSON.stringify(favoris));
            updateFavButtons();
        });
    });

    // Initialiser l'affichage
    updateFavButtons();
    
    // ✅ NOUVEAU : Mettre à jour le statut de contact après un court délai
    setTimeout(updateContactStatus, 500);

    // ✅ NOUVEAU : Exposer la fonction globalement pour les autres scripts
    window.updateContactStatus = updateContactStatus;
});