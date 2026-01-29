/**
 * JavaScript pour le formulaire de candidature multi-étapes
 * Gestion de la navigation, validation et upload de fichiers
 */

let currentSection = 1;
const totalSections = 7;

// Structure pour stocker les fichiers par type de document
let documentsByType = {
    piece_identite: [],
    bulletins_salaire: [],
    contrat_travail: [],
    avis_imposition: [],
    quittances_loyer: []
};

// Navigation entre les sections
function nextSection(sectionNumber) {
    // Valider la section actuelle avant de passer à la suivante
    if (!validateCurrentSection()) {
        return;
    }
    
    showSection(sectionNumber);
}

function prevSection(sectionNumber) {
    showSection(sectionNumber);
}

function showSection(sectionNumber) {
    // Masquer toutes les sections
    document.querySelectorAll('.form-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Afficher la section demandée
    const targetSection = document.querySelector(`[data-section="${sectionNumber}"]`);
    if (targetSection) {
        targetSection.classList.add('active');
        currentSection = sectionNumber;
        
        // Mettre à jour la barre de progression
        updateProgressBar();
        
        // Si c'est le récapitulatif, le générer
        if (sectionNumber === 7) {
            generateRecapitulatif();
        }
        
        // Scroll vers le haut
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function updateProgressBar() {
    const progress = (currentSection / totalSections) * 100;
    document.getElementById('progressBar').style.width = progress + '%';
}

function validateCurrentSection() {
    const currentSectionElement = document.querySelector('.form-section.active');
    const requiredFields = currentSectionElement.querySelectorAll('[required]');
    
    let isValid = true;
    requiredFields.forEach(field => {
        if (!field.value || field.value.trim() === '') {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        alert('Merci de remplir tous les champs obligatoires.');
    }
    
    return isValid;
}

// Gestion de l'upload de fichiers
document.addEventListener('DOMContentLoaded', function() {
    // Pour chaque type de document
    const documentTypes = ['piece_identite', 'bulletins_salaire', 'contrat_travail', 'avis_imposition', 'quittances_loyer'];
    
    documentTypes.forEach(docType => {
        const uploadZone = document.querySelector(`.document-upload-zone[data-doc-type="${docType}"]`);
        const fileInput = document.querySelector(`.document-input[data-doc-type="${docType}"]`);
        const fileList = document.querySelector(`.file-list[data-doc-type="${docType}"]`);
        
        if (!uploadZone || !fileInput || !fileList) return;
        
        // Drag & Drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('drag-over');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            handleFiles(e.dataTransfer.files, docType);
        });
        
        // Click pour parcourir
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files, docType);
        });
    });
    
    function handleFiles(files, docType) {
        Array.from(files).forEach(file => {
            // Vérifier la taille du fichier (max 5 Mo)
            if (file.size > 5 * 1024 * 1024) {
                alert(`Le fichier "${file.name}" dépasse la taille maximale de 5 Mo.`);
                return;
            }
            
            // Vérifier le format
            const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!validTypes.includes(file.type)) {
                alert(`Le fichier "${file.name}" n'est pas au bon format. Formats acceptés : PDF, JPG, PNG.`);
                return;
            }
            
            // Ajouter à la liste du type de document correspondant
            documentsByType[docType].push(file);
            displayFile(file, docType);
        });
        
        // Mettre à jour le badge requis
        updateRequiredBadge(docType);
    }
    
    function displayFile(file, docType) {
        const fileList = document.querySelector(`.file-list[data-doc-type="${docType}"]`);
        const fileItem = document.createElement('div');
        fileItem.className = 'file-list-item';
        fileItem.setAttribute('data-filename', file.name);
        fileItem.innerHTML = `
            <div>
                <i class="bi bi-file-earmark-text me-2"></i>
                <span>${file.name}</span>
                <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
            </div>
            <i class="bi bi-trash btn-remove-file" onclick="removeFile('${docType}', '${file.name.replace(/'/g, "\\'")}')"></i>
        `;
        fileList.appendChild(fileItem);
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    
    function updateRequiredBadge(docType) {
        const fileInput = document.querySelector(`.document-input[data-doc-type="${docType}"]`);
        if (documentsByType[docType].length > 0) {
            fileInput.removeAttribute('required');
            fileInput.classList.remove('is-invalid');
        } else {
            fileInput.setAttribute('required', 'required');
        }
    }
});

function removeFile(docType, fileName) {
    documentsByType[docType] = documentsByType[docType].filter(f => f.name !== fileName);
    
    // Mettre à jour l'affichage
    const fileList = document.querySelector(`.file-list[data-doc-type="${docType}"]`);
    const items = fileList.querySelectorAll('.file-list-item');
    items.forEach(item => {
        if (item.getAttribute('data-filename') === fileName) {
            item.remove();
        }
    });
    
    // Mettre à jour le badge requis
    const fileInput = document.querySelector(`.document-input[data-doc-type="${docType}"]`);
    if (documentsByType[docType].length === 0) {
        fileInput.setAttribute('required', 'required');
    }
}

function generateRecapitulatif() {
    const form = document.getElementById('candidatureForm');
    const formData = new FormData(form);
    
    let html = '<div class="table-responsive">';
    html += '<table class="table table-bordered">';
    
    // Informations personnelles
    html += '<tr class="table-primary"><th colspan="2"><i class="bi bi-person-fill me-2"></i>Informations Personnelles</th></tr>';
    html += `<tr><td><strong>Nom</strong></td><td>${formData.get('nom')} ${formData.get('prenom')}</td></tr>`;
    html += `<tr><td><strong>Email</strong></td><td>${formData.get('email')}</td></tr>`;
    html += `<tr><td><strong>Téléphone</strong></td><td>${formData.get('telephone')}</td></tr>`;
    
    const logementSelect = document.getElementById('logement_id');
    const logementText = logementSelect.options[logementSelect.selectedIndex].text;
    html += `<tr><td><strong>Logement souhaité</strong></td><td>${logementText}</td></tr>`;
    
    // Situation professionnelle
    html += '<tr class="table-primary"><th colspan="2"><i class="bi bi-briefcase-fill me-2"></i>Situation Professionnelle</th></tr>';
    html += `<tr><td><strong>Statut</strong></td><td>${formData.get('statut_professionnel')}</td></tr>`;
    html += `<tr><td><strong>Période d'essai</strong></td><td>${formData.get('periode_essai')}</td></tr>`;
    
    // Revenus
    html += '<tr class="table-primary"><th colspan="2"><i class="bi bi-cash-stack me-2"></i>Revenus</th></tr>';
    html += `<tr><td><strong>Revenus mensuels</strong></td><td>${formData.get('revenus_mensuels')}</td></tr>`;
    html += `<tr><td><strong>Type de revenus</strong></td><td>${formData.get('type_revenus')}</td></tr>`;
    
    // Logement actuel
    html += '<tr class="table-primary"><th colspan="2"><i class="bi bi-house-fill me-2"></i>Logement Actuel</th></tr>';
    html += `<tr><td><strong>Situation</strong></td><td>${formData.get('situation_logement')}</td></tr>`;
    html += `<tr><td><strong>Préavis donné</strong></td><td>${formData.get('preavis_donne')}</td></tr>`;
    
    // Occupation
    html += '<tr class="table-primary"><th colspan="2"><i class="bi bi-people-fill me-2"></i>Occupation</th></tr>';
    html += `<tr><td><strong>Nombre d'occupants</strong></td><td>${formData.get('nb_occupants')}</td></tr>`;
    html += `<tr><td><strong>Garantie Visale</strong></td><td>${formData.get('garantie_visale')}</td></tr>`;
    
    // Documents
    html += '<tr class="table-primary"><th colspan="2"><i class="bi bi-file-earmark-text-fill me-2"></i>Documents</th></tr>';
    
    const documentLabels = {
        piece_identite: 'Pièce d\'identité',
        bulletins_salaire: 'Bulletins de salaire',
        contrat_travail: 'Contrat de travail',
        avis_imposition: 'Avis d\'imposition',
        quittances_loyer: 'Quittances de loyer'
    };
    
    let totalDocs = 0;
    for (const [docType, files] of Object.entries(documentsByType)) {
        totalDocs += files.length;
        html += `<tr><td><strong>${documentLabels[docType]}</strong></td><td>${files.length} fichier(s)</td></tr>`;
    }
    html += `<tr class="table-light"><td><strong>Total</strong></td><td><strong>${totalDocs} document(s)</strong></td></tr>`;
    
    html += '</table>';
    html += '</div>';
    
    document.getElementById('recapitulatif').innerHTML = html;
}

// Validation du formulaire avant soumission
document.getElementById('candidatureForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Vérifier que tous les types de documents ont au moins un fichier
    const requiredDocTypes = ['piece_identite', 'bulletins_salaire', 'contrat_travail', 'avis_imposition', 'quittances_loyer'];
    const documentLabels = {
        piece_identite: 'Pièce d\'identité',
        bulletins_salaire: '3 derniers bulletins de salaire',
        contrat_travail: 'Contrat de travail',
        avis_imposition: 'Dernier avis d\'imposition',
        quittances_loyer: '3 dernières quittances de loyer'
    };
    
    let missingDocs = [];
    for (const docType of requiredDocTypes) {
        if (!documentsByType[docType] || documentsByType[docType].length === 0) {
            missingDocs.push(documentLabels[docType]);
        }
    }
    
    if (missingDocs.length > 0) {
        alert('Documents manquants :\n- ' + missingDocs.join('\n- '));
        showSection(6);
        return;
    }
    
    // Vérifier la case RGPD
    if (!document.getElementById('accepte_conditions').checked) {
        alert('Merci d\'accepter les conditions de traitement des données.');
        return;
    }
    
    // Désactiver le bouton pour éviter les doubles soumissions
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Envoi en cours...';
    
    // Créer un FormData avec tous les fichiers
    const formData = new FormData(this);
    
    // Supprimer les inputs de fichiers vides et ajouter nos fichiers
    for (const docType of requiredDocTypes) {
        formData.delete(`${docType}[]`);
        documentsByType[docType].forEach((file) => {
            formData.append(`${docType}[]`, file);
        });
    }
    
    // Envoyer via AJAX
    fetch('submit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Rediriger vers la page de confirmation
            window.location.href = 'confirmation.php?id=' + data.candidature_id;
        } else {
            alert('Erreur lors de l\'envoi : ' + (data.error || 'Erreur inconnue'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Envoyer ma candidature';
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue lors de l\'envoi de votre candidature. Merci de réessayer.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Envoyer ma candidature';
    });
});

// Initialisation
updateProgressBar();
