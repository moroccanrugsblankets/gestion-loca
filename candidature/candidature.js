/**
 * JavaScript pour le formulaire de candidature multi-étapes
 * Gestion de la navigation, validation et upload de fichiers
 */

let currentSection = 1;
const totalSections = 7;
let uploadedFiles = [];

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
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('documents');
    const fileList = document.getElementById('fileList');
    
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
        handleFiles(e.dataTransfer.files);
    });
    
    // Click pour parcourir
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
    
    function handleFiles(files) {
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
            
            // Ajouter à la liste
            uploadedFiles.push(file);
            displayFile(file);
        });
    }
    
    function displayFile(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-list-item';
        fileItem.innerHTML = `
            <div>
                <i class="bi bi-file-earmark-text me-2"></i>
                <span>${file.name}</span>
                <small class="text-muted ms-2">(${formatFileSize(file.size)})</small>
            </div>
            <i class="bi bi-trash btn-remove-file" onclick="removeFile('${file.name}')"></i>
        `;
        fileList.appendChild(fileItem);
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
});

function removeFile(fileName) {
    uploadedFiles = uploadedFiles.filter(f => f.name !== fileName);
    
    // Mettre à jour l'affichage
    const fileList = document.getElementById('fileList');
    const items = fileList.querySelectorAll('.file-list-item');
    items.forEach(item => {
        if (item.textContent.includes(fileName)) {
            item.remove();
        }
    });
    
    // Réinitialiser l'input si plus de fichiers
    if (uploadedFiles.length === 0) {
        document.getElementById('documents').value = '';
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
    html += `<tr><td><strong>Fichiers joints</strong></td><td>${uploadedFiles.length} document(s)</td></tr>`;
    
    html += '</table>';
    html += '</div>';
    
    document.getElementById('recapitulatif').innerHTML = html;
}

// Validation du formulaire avant soumission
document.getElementById('candidatureForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Vérifier que des fichiers ont été uploadés
    if (uploadedFiles.length === 0) {
        alert('Merci de joindre au moins un document justificatif.');
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
    
    // Remplacer les fichiers par ceux de notre liste
    formData.delete('documents[]');
    uploadedFiles.forEach((file, index) => {
        formData.append('documents[]', file);
    });
    
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
