# Corrections apportées au module Contrats

## Problèmes identifiés et résolus

### 1. Template non utilisée lors de la génération PDF
**Problème:** La template HTML configurée dans `/admin-v2/contrat-configuration.php` n'était jamais utilisée par le générateur de PDF.

**Solution:** 
- Modification de `pdf/generate-contrat-pdf.php` pour récupérer et utiliser la template depuis la table `parametres`
- Création d'une fonction `replaceTemplateVariables()` qui remplace 17 variables dynamiques dans la template
- Utilisation de TCPDF->writeHTML() pour générer le PDF à partir du HTML de la template
- Mise en place d'un système de fallback vers l'ancien système si aucune template n'est configurée

### 2. Signature électronique de l'agence
**Problème:** La signature devait être ajoutée automatiquement lors de la validation du contrat.

**Solution:**
- Ajout de la variable `{{signature_agence}}` dans la template par défaut
- La fonction `replaceTemplateVariables()` vérifie si le contrat est validé (`statut === 'valide'`)
- Si validé ET signature activée ET image configurée → la signature est incluse dans le HTML
- La signature inclut l'image et la date de validation
- Si le contrat n'est pas validé, `{{signature_agence}}` est remplacé par une chaîne vide

## Fichiers modifiés

### 1. `/pdf/generate-contrat-pdf.php`
**Modifications:**
- Fonction `generateContratPDF()` refactorisée pour utiliser la template HTML
- Nouvelle fonction `replaceTemplateVariables()` pour remplacer les variables de template
- Nouvelle fonction `generateContratPDFLegacy()` pour le fallback vers l'ancien système
- Utilisation de TCPDF->writeHTML() au lieu de la classe ContratBailPDF personnalisée

**Variables supportées:**
```php
- {{reference_unique}}       // Référence unique du contrat
- {{locataires_info}}        // Liste des locataires avec infos
- {{locataires_signatures}}  // Signatures des locataires avec timestamps
- {{signature_agence}}       // Signature électronique (si validé)
- {{adresse}}               // Adresse du logement
- {{appartement}}           // Numéro d'appartement
- {{type}}                  // Type de logement
- {{surface}}               // Surface en m²
- {{parking}}               // Info parking
- {{date_prise_effet}}      // Date de début du bail
- {{date_signature}}        // Date de signature
- {{loyer}}                 // Loyer mensuel
- {{charges}}               // Charges mensuelles
- {{loyer_total}}           // Loyer + charges
- {{depot_garantie}}        // Montant du dépôt
- {{iban}}                  // IBAN
- {{bic}}                   // BIC
```

### 2. `/admin-v2/contrat-configuration.php`
**Modifications:**
- Ajout de `{{signature_agence}}` dans la fonction `getDefaultContractTemplate()`
- Amélioration de la section signatures dans le template par défaut
- Séparation claire entre signature bailleur et locataires

## Fonctionnement

### Génération PDF lors de la signature client

1. Client signe le contrat dans `signature/step3-documents.php`
2. Appel de `generateBailPDF($contratId)` qui appelle `generateContratPDF()`
3. Récupération de la template depuis `parametres.contrat_template_html`
4. Remplacement des variables avec les données du contrat
5. **Signature agence NON incluse** car statut ≠ 'valide'
6. Génération du PDF via TCPDF->writeHTML()
7. Sauvegarde dans `/pdf/contrats/`

### Validation du contrat par l'admin

1. Admin valide dans `admin-v2/contrat-detail.php`
2. Statut du contrat → 'valide'
3. `date_validation` → NOW()
4. **Régénération immédiate du PDF** via `generateBailPDF()`
5. Cette fois, `{{signature_agence}}` est remplacé par:
   - Image de signature (si configurée et activée)
   - Date de validation
6. Le nouveau PDF avec signature est sauvegardé
7. Email envoyé au client avec le PDF final

### Annulation du contrat

1. Admin annule dans `admin-v2/contrat-detail.php`
2. Statut → 'annule'
3. `motif_annulation` enregistré
4. Email envoyé au client
5. Possibilité de régénérer un nouveau contrat

## Tests à effectuer

### Test 1: Génération PDF avec template
```bash
php test-template-pdf.php
```
Vérifie:
- Présence de la template
- Toutes les variables sont reconnues
- Configuration de la signature
- Remplacement correct des variables
- Génération PDF valide

### Test 2: Signature client
1. Créer un lien de signature
2. Signer en tant que client
3. Vérifier le PDF généré → pas de signature agence
4. Vérifier les variables remplacées correctement

### Test 3: Validation admin
1. Prendre un contrat signé
2. Valider via l'interface admin
3. Vérifier le PDF regénéré → signature agence présente
4. Vérifier la date de validation affichée

### Test 4: Template personnalisée
1. Modifier la template dans `/admin-v2/contrat-configuration.php`
2. Ajouter/retirer des sections
3. Générer un nouveau contrat
4. Vérifier que le PDF utilise la nouvelle template

## Avantages de la solution

1. **Template configurable** : Les admins peuvent modifier le contenu du contrat sans toucher au code
2. **Signature conditionnelle** : La signature n'apparaît que lorsque le contrat est validé
3. **Traçabilité** : Date de validation affichée avec la signature
4. **Fallback sécurisé** : Si pas de template, utilise l'ancien système
5. **17 variables dynamiques** : Remplacement automatique de toutes les données
6. **Support des data URI** : Les images de signature en base64 fonctionnent
7. **Compatibilité TCPDF** : Utilise writeHTML() natif de TCPDF

## Variables de configuration requises

Dans la table `parametres`:

1. **contrat_template_html** (text)
   - Template HTML avec variables {{}}
   - Créée automatiquement si absente

2. **signature_societe_enabled** (boolean: 'true'/'false')
   - Active/désactive l'ajout de la signature

3. **signature_societe_image** (string)
   - Data URI de l'image de signature (base64)
   - Format: `data:image/png;base64,iVBORw0KG...`

## Sécurité

- Toutes les données sont échappées avec `htmlspecialchars()`
- Les dates sont validées avant formatage
- Les montants sont formatés correctement
- Les images sont vérifiées (data URI)
- Pas d'injection SQL (requêtes préparées)
- Pas d'injection HTML/XSS

## Compatibilité

- PHP >= 7.2
- TCPDF >= 6.6
- MySQL/MariaDB
- Compatible avec l'ancien système (fallback)

## Notes importantes

1. La template par défaut est créée automatiquement si absente
2. Les anciennes installations continuent de fonctionner (fallback)
3. La signature n'est JAMAIS ajoutée avant validation
4. Le PDF est régénéré à chaque validation
5. Les contrats annulés peuvent être régénérés
