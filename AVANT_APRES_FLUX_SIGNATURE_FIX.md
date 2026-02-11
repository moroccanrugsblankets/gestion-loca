# Comparaison Avant/Après - Correction du Flux de Signature

## Problème Initial

**Symptôme**: Le flux de signature sautait des étapes et affichait locataire 2 avant locataire 1.

**Impact**: Confusion pour les utilisateurs, ordre incorrect des signatures et documents.

---

## AVANT (Comportement Problématique)

### Flux pour 2 Locataires

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. /signature/index.php                                         │
│    - Utilisateur accepte le contrat                             │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. /signature/step1-info.php                                    │
│    - Saisie informations Locataire 1                            │
│    - Création en base: locataire 1                              │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. /signature/step2-signature.php                               │
│    - Signature Locataire 1                                      │
│    - Enregistrement signature en base                           │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 │ ⚠️ REDIRECTION DIRECTE (PROBLÈME)
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. /signature/step3-documents.php                               │
│    ❌ PROBLÈME: Affiche "Locataire 1"                           │
│    - Upload documents Locataire 1                               │
│    - EN MÊME TEMPS: Question "Y a-t-il un 2ème locataire?"     │
│    ⚠️ Confusion: l'utilisateur upload et répond en même temps   │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 │ Si réponse "Oui"
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. /signature/step1-info.php                                    │
│    - Saisie informations Locataire 2                            │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. /signature/step2-signature.php                               │
│    - Signature Locataire 2                                      │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. /signature/step3-documents.php                               │
│    ❌ PROBLÈME: Pourrait afficher "Locataire 2" en premier!     │
│    - Upload documents (ordre incertain)                         │
└─────────────────────────────────────────────────────────────────┘
```

### Problèmes Identifiés

1. ❌ Question posée au mauvais moment (pendant l'upload de documents)
2. ❌ Ordre d'affichage des locataires incertain dans step3
3. ❌ Flux confus et pas intuitif
4. ❌ Possibilité de voir "Locataire 2" avant "Locataire 1"

---

## APRÈS (Comportement Corrigé)

### Flux pour 2 Locataires

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. /signature/index.php                                         │
│    - Utilisateur accepte le contrat                             │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. /signature/step1-info.php                                    │
│    - Saisie informations Locataire 1                            │
│    - Création en base: locataire 1                              │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. /signature/step2-signature.php                               │
│    - Signature Locataire 1                                      │
│    - Enregistrement signature en base                           │
│    ✓ Message: "Votre signature a été enregistrée avec succès!" │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 │ ✓ NOUVEAU: Reste sur la même page
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. /signature/step2-signature.php (mode question)               │
│    ✓ Question claire: "Y a-t-il un second locataire ?"         │
│    - Choix: "Oui, il y a un second locataire"                  │
│    - Choix: "Non, je suis le seul locataire"                   │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ├─── Si "Non" ──────────────────┐
                 │                                │
                 │ Si "Oui"                       ▼
                 ▼                    ┌───────────────────────────┐
┌────────────────────────────────┐   │ 5a. /signature/step3-...  │
│ 5b. /signature/step1-info.php │   │     - Upload docs Loc. 1  │
│     - Infos Locataire 2        │   │     - Finalisation        │
└────────────────┬───────────────┘   └───────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. /signature/step2-signature.php                               │
│    - Signature Locataire 2                                      │
│    - Pas de question (déjà locataire 2)                         │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. /signature/step3-documents.php                               │
│    ✓ TOUJOURS: Affiche "Locataire 1" en premier                │
│    - Upload documents Locataire 1                               │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 8. /signature/step3-documents.php                               │
│    ✓ Ensuite: Affiche "Locataire 2"                            │
│    - Upload documents Locataire 2                               │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│ 9. /signature/confirmation.php                                  │
│    - Confirmation finale                                        │
└─────────────────────────────────────────────────────────────────┘
```

### Améliorations Apportées

1. ✅ Question posée au bon moment (juste après signature de locataire 1)
2. ✅ Ordre garanti: Locataire 1 TOUJOURS avant Locataire 2
3. ✅ Flux logique et intuitif
4. ✅ Séparation claire: signature → question → documents
5. ✅ Une seule action par page (pas de confusion)

---

## Changements Techniques

### Fichiers Modifiés

#### 1. `signature/step2-signature.php`

**Avant**:
```php
// Après signature
if (updateTenantSignature($locataireId, $signatureData, null, $certifieExact)) {
    logAction($contratId, 'signature_locataire', "Locataire $numeroLocataire a signé");
    
    // Redirection directe vers step3
    header('Location: step3-documents.php');
    exit;
}
```

**Après**:
```php
// Après signature
if (updateTenantSignature($locataireId, $signatureData, null, $certifieExact)) {
    logAction($contratId, 'signature_locataire', "Locataire $numeroLocataire a signé");
    
    // Vérifier si on doit poser la question du 2ème locataire
    if ($numeroLocataire === 1 && $contrat['nb_locataires'] > 1) {
        // Afficher la question (reste sur step2)
        $signatureSaved = true;
    } else {
        // Locataire 2 ou contrat à 1 seul locataire
        unset($_SESSION['current_locataire_id']);
        unset($_SESSION['current_locataire_numero']);
        header('Location: step3-documents.php');
        exit;
    }
}
```

#### 2. `signature/step3-documents.php`

**Avant**:
```php
// Dans le formulaire
<?php if ($numeroLocataire === 1 && $contrat['nb_locataires'] > 1): ?>
    <div class="mb-4">
        <label>Y a-t-il un second locataire ? *</label>
        <!-- Champs radio oui/non -->
    </div>
<?php endif; ?>
```

**Après**:
```php
// Question supprimée de step3
// Logique simplifiée pour gérer l'ordre des uploads
foreach ($locatairesExistants as $locataire) {
    if (!empty($locataire['signature_timestamp']) && 
        empty($locataire['piece_identite_recto']) && 
        $locataire['id'] != $locataireId) {
        // Passer au locataire suivant
        $_SESSION['current_locataire_id'] = $locataire['id'];
        $_SESSION['current_locataire_numero'] = $locataire['ordre'];
        break;
    }
}
```

---

## Validation

### Points de Contrôle

- ✅ Locataire 1 apparaît TOUJOURS en premier dans step3
- ✅ Question posée au bon moment (après signature, avant documents)
- ✅ Flux clair: signature → question → documents
- ✅ Pas de saut d'étapes
- ✅ Session correctement gérée pour assurer l'ordre

### Test Rapide

Pour vérifier que la correction fonctionne:

1. Créer un contrat avec `nb_locataires = 2`
2. Suivre le flux de signature
3. **Vérifier**: Après signature de locataire 1, la question apparaît immédiatement
4. **Vérifier**: Dans step3, "Locataire 1" apparaît en premier
5. **Vérifier**: Après upload de locataire 1, "Locataire 2" apparaît

---

## Conclusion

La correction garantit un flux logique et intuitif:
1. **Locataire 1**: Info → Signature → Question
2. **Locataire 2** (si oui): Info → Signature
3. **Documents**: Locataire 1 → Locataire 2
4. **Finalisation**: Confirmation

Le système respecte maintenant l'ordre attendu: **locataire 1 d'abord, ensuite la question, puis locataire 2 si applicable**.
