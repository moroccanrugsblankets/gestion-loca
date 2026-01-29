# Comparaison Avant/Après - Formulaire de Candidature

## Section Documents - AVANT

```html
<!-- Un seul champ pour tous les documents -->
<div class="document-upload-zone" id="uploadZone">
    <i class="bi bi-cloud-upload fs-1 text-muted"></i>
    <p class="mb-2">Glissez-déposez vos fichiers ici</p>
    <p class="text-muted small">ou</p>
    <label for="documents" class="btn btn-outline-primary">
        <i class="bi bi-folder2-open"></i> Parcourir les fichiers
    </label>
    <input type="file" class="d-none" id="documents" name="documents[]" 
           multiple accept=".pdf,.jpg,.jpeg,.png" required>
    <p class="text-muted small mt-2">Formats acceptés : PDF, JPG, PNG</p>
</div>
```

**Problèmes**:
- ❌ Impossible de savoir quel document est quel type
- ❌ Pas de validation spécifique par type
- ❌ Difficile de vérifier que tous les documents requis sont présents
- ❌ Backend doit deviner le type de chaque document

---

## Section Documents - APRÈS

```html
<!-- 5 champs distincts, un par type de document -->

<!-- 1. Pièce d'identité -->
<div class="mb-4">
    <label class="form-label required-field">
        <i class="bi bi-person-vcard me-2"></i>
        Pièce d'identité ou passeport en cours de validité
    </label>
    <div class="document-upload-zone" data-doc-type="piece_identite">
        ...
        <input type="file" name="piece_identite[]" multiple required>
    </div>
    <div class="file-list" data-doc-type="piece_identite"></div>
</div>

<!-- 2. Bulletins de salaire -->
<div class="mb-4">
    <label class="form-label required-field">
        <i class="bi bi-file-earmark-text me-2"></i>
        3 derniers bulletins de salaire
    </label>
    <div class="document-upload-zone" data-doc-type="bulletins_salaire">
        ...
        <input type="file" name="bulletins_salaire[]" multiple required>
    </div>
    <div class="file-list" data-doc-type="bulletins_salaire"></div>
</div>

<!-- 3. Contrat de travail -->
<div class="mb-4">
    <label class="form-label required-field">
        <i class="bi bi-file-earmark-check me-2"></i>
        Contrat de travail
    </label>
    <div class="document-upload-zone" data-doc-type="contrat_travail">
        ...
        <input type="file" name="contrat_travail[]" multiple required>
    </div>
    <div class="file-list" data-doc-type="contrat_travail"></div>
</div>

<!-- 4. Avis d'imposition -->
<div class="mb-4">
    <label class="form-label required-field">
        <i class="bi bi-file-earmark-ruled me-2"></i>
        Dernier avis d'imposition
    </label>
    <div class="document-upload-zone" data-doc-type="avis_imposition">
        ...
        <input type="file" name="avis_imposition[]" multiple required>
    </div>
    <div class="file-list" data-doc-type="avis_imposition"></div>
</div>

<!-- 5. Quittances de loyer -->
<div class="mb-4">
    <label class="form-label required-field">
        <i class="bi bi-receipt me-2"></i>
        3 dernières quittances de loyer
    </label>
    <div class="document-upload-zone" data-doc-type="quittances_loyer">
        ...
        <input type="file" name="quittances_loyer[]" multiple required>
    </div>
    <div class="file-list" data-doc-type="quittances_loyer"></div>
</div>
```

**Avantages**:
- ✅ Chaque type de document est clairement identifié
- ✅ Validation obligatoire par type
- ✅ Le candidat sait exactement quoi uploader
- ✅ Interface comme sur https://www.myinvest-immobilier.com/candidature/
- ✅ Backend reçoit directement le type de document

---

## JavaScript - AVANT

```javascript
let uploadedFiles = [];

// Un seul gestionnaire pour tous les documents
fileInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
});

function handleFiles(files) {
    Array.from(files).forEach(file => {
        uploadedFiles.push(file);
        displayFile(file);
    });
}

// Validation simple
if (uploadedFiles.length === 0) {
    alert('Merci de joindre au moins un document justificatif.');
}
```

---

## JavaScript - APRÈS

```javascript
// Structure organisée par type
let documentsByType = {
    piece_identite: [],
    bulletins_salaire: [],
    contrat_travail: [],
    avis_imposition: [],
    quittances_loyer: []
};

// Gestionnaire par type de document
documentTypes.forEach(docType => {
    const fileInput = document.querySelector(`.document-input[data-doc-type="${docType}"]`);
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files, docType);
    });
});

function handleFiles(files, docType) {
    Array.from(files).forEach(file => {
        documentsByType[docType].push(file);
        displayFile(file, docType);
    });
    updateRequiredBadge(docType);
}

// Validation spécifique par type
const requiredDocTypes = ['piece_identite', 'bulletins_salaire', 
                          'contrat_travail', 'avis_imposition', 
                          'quittances_loyer'];
let missingDocs = [];
for (const docType of requiredDocTypes) {
    if (!documentsByType[docType] || documentsByType[docType].length === 0) {
        missingDocs.push(documentLabels[docType]);
    }
}

if (missingDocs.length > 0) {
    alert('Documents manquants :\n- ' + missingDocs.join('\n- '));
}
```

---

## Backend PHP - AVANT

```php
// Traitement générique
$documents = $_FILES['documents'];

for ($i = 0; $i < count($documents['name']); $i++) {
    // ...upload...
    
    $stmt->execute([
        $candidature_id,
        'Pièce justificative',  // ❌ Type générique
        $documents['name'][$i],
        $filepath
    ]);
}
```

**Problèmes**:
- ❌ Tous les documents ont le même type "Pièce justificative"
- ❌ Impossible de filtrer par type dans l'admin
- ❌ Pas de validation par type
- ❌ Erreurs génériques peu utiles

---

## Backend PHP - APRÈS

```php
// Types de documents requis avec labels
$required_doc_types = [
    'piece_identite' => 'Pièce d\'identité ou passeport',
    'bulletins_salaire' => '3 derniers bulletins de salaire',
    'contrat_travail' => 'Contrat de travail',
    'avis_imposition' => 'Dernier avis d\'imposition',
    'quittances_loyer' => '3 dernières quittances de loyer'
];

// Validation des types manquants
$missing_docs = [];
foreach ($required_doc_types as $doc_type => $doc_label) {
    if (empty($_FILES[$doc_type]['name'][0])) {
        $missing_docs[] = $doc_label;
    }
}
if (!empty($missing_docs)) {
    throw new Exception('Documents manquants : ' . implode(', ', $missing_docs));
}

// Traitement individualisé par type
foreach ($required_doc_types as $doc_type => $doc_label) {
    $documents = $_FILES[$doc_type];
    
    for ($i = 0; $i < count($documents['name']); $i++) {
        // ...upload...
        
        logDebug("Fichier uploadé", [
            'type' => $doc_type,  // ✅ Type spécifique
            'filename' => $filename
        ]);
        
        $stmt->execute([
            $candidature_id,
            $doc_type,  // ✅ Type exact: piece_identite, bulletins_salaire, etc.
            $filename,
            $documents['name'][$i],
            $filepath,
            $documents['size'][$i],
            $mime_type
        ]);
    }
}
```

**Avantages**:
- ✅ Type de document exact enregistré en base
- ✅ Validation spécifique: message "Documents manquants: Contrat de travail, Avis d'imposition"
- ✅ Logs détaillés avec `logDebug()` à chaque étape
- ✅ Compteur par type de document
- ✅ Filtrage possible dans l'admin

---

## Messages d'erreur - AVANT

```json
{
    "success": false,
    "error": "Une erreur est survenue lors de l'enregistrement de votre candidature. Merci de réessayer."
}
```

❌ Message générique, aucune info utile

---

## Messages d'erreur - APRÈS

```json
{
    "success": false,
    "error": "Documents manquants : Contrat de travail, Dernier avis d'imposition",
    "debug_info": "Consultez les logs du serveur pour plus de détails"
}
```

✅ Message précis indiquant exactement le problème

**OU**

```json
{
    "success": false,
    "error": "Le champ 'telephone' est obligatoire"
}
```

**OU**

```json
{
    "success": false,
    "error": "Email invalide"
}
```

**Dans les logs**:
```
[CANDIDATURE DEBUG] ERREUR | Data: {
    "message": "Documents manquants : Contrat de travail",
    "trace": "..."
}
```

---

## Récapitulatif Section 7 - AVANT

```
Documents
Fichiers joints: 8 document(s)
```

❌ Pas de détail par type

---

## Récapitulatif Section 7 - APRÈS

```
Documents
Pièce d'identité:        1 fichier(s)
Bulletins de salaire:    3 fichier(s)
Contrat de travail:      1 fichier(s)
Avis d'imposition:       1 fichier(s)
Quittances de loyer:     2 fichier(s)
─────────────────────────────────────
Total:                   8 document(s)
```

✅ Détail complet par type

---

## Base de données - AVANT

```sql
type_document ENUM('piece_identite', 'justificatif_revenus', 
                   'justificatif_domicile', 'autre')
```

Exemple d'entrée:
```
| id | candidature_id | type_document        | nom_fichier |
|----|----------------|----------------------|-------------|
| 1  | 123           | autre                | doc1.pdf    |
| 2  | 123           | autre                | doc2.pdf    |
| 3  | 123           | autre                | doc3.pdf    |
```

❌ Tous les documents ont le type "autre" ou un type générique

---

## Base de données - APRÈS

```sql
type_document ENUM('piece_identite', 'bulletins_salaire', 
                   'contrat_travail', 'avis_imposition', 
                   'quittances_loyer', 'justificatif_revenus', 
                   'justificatif_domicile', 'autre')
```

Exemple d'entrée:
```
| id | candidature_id | type_document      | nom_fichier              |
|----|----------------|--------------------|--------------------------|
| 1  | 123           | piece_identite     | piece_identite_0_a1b2.pdf|
| 2  | 123           | bulletins_salaire  | bulletins_salaire_0.pdf  |
| 3  | 123           | bulletins_salaire  | bulletins_salaire_1.pdf  |
| 4  | 123           | bulletins_salaire  | bulletins_salaire_2.pdf  |
| 5  | 123           | contrat_travail    | contrat_travail_0.pdf    |
| 6  | 123           | avis_imposition    | avis_imposition_0.pdf    |
| 7  | 123           | quittances_loyer   | quittances_loyer_0.pdf   |
| 8  | 123           | quittances_loyer   | quittances_loyer_1.pdf   |
```

✅ Type exact pour chaque document
✅ Nommage de fichier avec le type
✅ Possibilité de requêtes SQL par type
✅ Comptage facile par type

---

## Requêtes SQL possibles - APRÈS

```sql
-- Compter les candidatures avec tous les documents requis
SELECT candidature_id, 
       SUM(type_document = 'piece_identite') as nb_piece_id,
       SUM(type_document = 'bulletins_salaire') as nb_bulletins,
       SUM(type_document = 'contrat_travail') as nb_contrats,
       SUM(type_document = 'avis_imposition') as nb_avis,
       SUM(type_document = 'quittances_loyer') as nb_quittances
FROM candidature_documents
GROUP BY candidature_id
HAVING nb_piece_id > 0 AND nb_contrats > 0 AND nb_avis > 0;

-- Lister les candidatures incomplètes
SELECT c.id, c.nom, c.prenom
FROM candidatures c
LEFT JOIN candidature_documents cd ON c.id = cd.candidature_id
GROUP BY c.id
HAVING COUNT(DISTINCT cd.type_document) < 5;

-- Statistiques par type de document
SELECT type_document, COUNT(*) as total
FROM candidature_documents
GROUP BY type_document;
```

---

## Résumé des améliorations

| Aspect | Avant | Après |
|--------|-------|-------|
| **UX** | Confus, un seul champ | Clair, 5 champs distincts avec icônes |
| **Validation** | Générique | Spécifique par type |
| **Messages d'erreur** | "Une erreur est survenue" | "Documents manquants: Contrat de travail" |
| **Logs** | Basiques | Détaillés avec logDebug() |
| **Base de données** | Type générique | Type exact pour chaque document |
| **Admin** | Erreurs PHP "Undefined index" | Pas d'erreurs, valeurs par défaut |
| **Compatibilité** | - | Reference https://www.myinvest-immobilier.com/candidature/ |
| **Maintenance** | Difficile de debug | Logs détaillés, erreurs précises |
