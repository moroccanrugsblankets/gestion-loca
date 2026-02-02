# Corrections Module Contrats - Signatures

## Problèmes résolus

### 1. Signature agence non ajoutée automatiquement au PDF ✅

**Avant:**
- La signature agence n'était pas toujours visible dans le PDF final
- La taille était trop grande (200px HTML / 40mm PDF)

**Après:**
- La signature agence est **automatiquement ajoutée** lors de la validation du contrat
- Taille optimisée: **150px** (HTML) / **35mm** (PDF legacy)
- Date de validation affichée avec la signature
- Condition: `statut === 'valide'` + `signature_societe_enabled === 'true'` + image configurée

**Code modifié:**
- `pdf/generate-contrat-pdf.php` - Ligne 193-216

---

### 2. Signature client trop grande avec bordure grise ✅

**Avant:**
```html
<img src="..." style="max-width: 200px; border: 1px solid #ddd; padding: 5px;">
```
- Taille: 200px (HTML) / 40mm (PDF)
- Bordure grise visible autour de la signature
- Padding de 5px créant un espace indésirable

**Après:**
```html
<img src="..." style="max-width: 120px; height: auto;">
```
- Taille réduite: **120px** (HTML) / **30mm** (PDF legacy)
- **Aucune bordure** - rendu propre et transparent
- **Aucun padding** - intégration harmonieuse
- `height: auto` pour préserver les proportions

**Code modifié:**
- `pdf/generate-contrat-pdf.php` - Lignes 158, 167, 547-548

---

### 3. Affichage incorrect des labels de locataires ✅

**Avant:**
- Pour 1 locataire: "Le(s) locataire(s)" puis "Locataire 1 :"
  ```
  Le bailleur
  Le(s) locataire(s)
  Locataire 1 : Jean DUPONT
  ```
  → Rendu incorrect avec "1" pour un seul locataire

- Pour 2 locataires: Même problème avec le label statique

**Après:**
- Pour 1 locataire: "Locataire :" (sans "s", sans numéro)
  ```
  Le bailleur
  
  Locataire : Jean DUPONT
  ```

- Pour 2+ locataires: "Locataire 1 :", "Locataire 2 :", etc.
  ```
  Le bailleur
  
  Locataire 1 : Jean DUPONT
  
  Locataire 2 : Marie MARTIN
  ```

**Code modifié:**
```php
// Adapter le label selon le nombre de locataires
if ($nbLocataires === 1) {
    // Pour un seul locataire: "Locataire :"
    $sig .= '<p><strong>Locataire : ' . htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . '</strong></p>';
} else {
    // Pour plusieurs locataires: "Locataire 1 :", "Locataire 2 :", etc.
    $sig .= '<p><strong>Locataire ' . ($i + 1) . ' : ' . htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . '</strong></p>';
}
```

**Fichiers modifiés:**
- `pdf/generate-contrat-pdf.php` - Lignes 140-153 (HTML), 512-524 (PDF legacy)
- `admin-v2/contrat-configuration.php` - Ligne 310 (suppression du label statique)

---

## Résumé des changements

| Élément | Avant | Après |
|---------|-------|-------|
| **Signature client (HTML)** | 200px avec bordure | **120px sans bordure** |
| **Signature client (PDF)** | 40mm | **30mm** |
| **Signature agence (HTML)** | 200px avec bordure | **150px sans bordure** |
| **Signature agence (PDF)** | 40mm | **35mm** |
| **Bordure** | `border: 1px solid #ddd` | **Supprimée** |
| **Padding** | `padding: 5px` | **Supprimé** |
| **Label 1 locataire** | "Locataire 1 :" | **"Locataire :"** |
| **Label 2+ locataires** | "Locataire 1/2 :" | **"Locataire 1/2 :"** (inchangé) |

---

## Fichiers modifiés

1. **`pdf/generate-contrat-pdf.php`**
   - Fonction `replaceContratTemplateVariables()` - Signatures HTML
   - Classe `ContratBailPDF::generateContrat()` - Signatures PDF legacy
   
2. **`admin-v2/contrat-configuration.php`**
   - Fonction `getDefaultContractTemplate()` - Template par défaut

---

## Configuration requise

Pour que la signature agence soit ajoutée au PDF:

1. ✅ Le contrat doit avoir le statut `'valide'`
2. ✅ Le paramètre `signature_societe_enabled` doit être `'true'` dans la base de données
3. ✅ Le paramètre `signature_societe_image` doit contenir une image valide (data URI base64)

Configuration via: `/admin-v2/contrat-configuration.php`

---

## Tests

Script de test créé: `test-signature-fixes.php`

**Résultats:**
- ✅ Tailles correctes pour toutes les signatures
- ✅ Bordures et padding supprimés
- ✅ Labels adaptatifs selon le nombre de locataires
- ✅ Signature agence conditionnée au statut validé
- ✅ Date de validation incluse

---

## Bénéfices

1. **Rendu professionnel**: Signatures sans cadre, intégrées proprement
2. **Taille optimale**: Proportionnelles et harmonieuses dans le document
3. **Clarté**: Labels corrects selon le contexte (1 ou plusieurs locataires)
4. **Automatisation**: Signature agence ajoutée automatiquement lors de la validation
5. **Traçabilité**: Date de validation affichée avec la signature agence

---

## Compatibilité

- ✅ Compatible avec les contrats existants
- ✅ Pas de modification de base de données requise
- ✅ Fonctionne avec le nouveau système (template HTML) et l'ancien (legacy PDF)
- ✅ Aucun changement cassant

---

## Actions à réaliser

### Par l'administrateur:
1. Vérifier que la signature de l'agence est bien configurée dans `/admin-v2/contrat-configuration.php`
2. S'assurer que l'option "Activer la signature" est cochée
3. Tester la génération d'un PDF après validation d'un contrat

### Validation:
1. Créer un contrat avec 1 locataire → Vérifier le label "Locataire :"
2. Créer un contrat avec 2 locataires → Vérifier les labels "Locataire 1 :" et "Locataire 2 :"
3. Valider un contrat → Vérifier que la signature agence apparaît dans le PDF
4. Vérifier que les signatures sont sans bordure et de taille réduite
