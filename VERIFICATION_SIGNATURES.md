# Vérification des Correctifs de Signatures

## Problèmes Identifiés et Corrigés

### 1. Signature de l'agence ({{signature_agence}}) absente du PDF

**Cause identifiée:**
- La signature agence n'est ajoutée que si TOUTES les conditions suivantes sont remplies:
  - Le contrat a le statut `valide` (pas seulement `signe`)
  - Le paramètre `signature_societe_enabled` est défini à `true` ou `1`
  - Le paramètre `signature_societe_image` contient une image valide (data URI)

**Corrections apportées:**
- ✅ Ajout de logs explicites pour identifier quelle condition échoue
- ✅ Messages d'erreur actionnables indiquant exactement quoi faire
- ✅ Taille de signature standardisée à 150x60px (au lieu de 80x40px)

**Logs ajoutés pour le diagnostic:**
```
PDF Generation: ✗ SIGNATURE AGENCE NON ACTIVÉE
PDF Generation: Action requise → Activer la signature dans /admin-v2/contrat-configuration.php
PDF Generation: Paramètre à vérifier: signature_societe_enabled doit être défini à '1' ou 'true'
```

### 2. Taille incorrecte de la signature client

**Problème:**
- La signature client apparaissait avec une taille incorrecte (60x30px au lieu de 150x60px)

**Corrections apportées:**
- ✅ Modification de `max-width: 60px; max-height: 30px` à `max-width: 150px; max-height: 60px`
- ✅ Ajout de `width: auto; display: inline-block` pour un rendu proportionnel
- ✅ Logs détaillés des dimensions appliquées

**Code corrigé:**
```html
<img src="..." style="max-width: 150px; max-height: 60px; width: auto; height: auto; border: 0; border-style: none; outline: none; background: transparent; display: inline-block;">
```

### 3. Fond gris ou bordure autour de la signature client

**Problème:**
- Un fond gris ou une bordure grise entourait la signature

**Corrections apportées:**
- ✅ Ajout explicite de `background: transparent;` au canvas dans le HTML
- ✅ Style inline `background: transparent; border: 0; border-style: none; outline: none` sur les images
- ✅ Le canvas JavaScript utilise déjà `clearRect()` pour un fond transparent

## Logs Ajoutés

### Côté Client (signature.js)
```javascript
console.log('Initialisation du canvas de signature');
console.log('- Dimensions:', canvas.width, 'x', canvas.height, 'px');
console.log('- Fond: transparent (clearRect appliqué)');
console.log('- Style de trait: noir (#000000), largeur 2px');
```

### Côté Serveur (step2-signature.php)
```php
error_log("Step2-Signature: === RÉCEPTION SIGNATURE CLIENT ===");
error_log("Step2-Signature: Locataire ID: $locataireId, Numéro: $numeroLocataire");
error_log("Step2-Signature: Signature data length: " . strlen($signatureData) . " octets");
```

### Génération PDF (generate-contrat-pdf.php)
```php
error_log("PDF Generation: Signature client " . ($i + 1) . " - Dimensions appliquées: max-width 150px, max-height 60px");
error_log("PDF Generation: Signature client " . ($i + 1) . " - Style: SANS bordure, fond transparent, affichage proportionné");
error_log("PDF Generation: ✓ Signature agence AJOUTÉE avec succès au PDF");
error_log("PDF Generation: Dimensions signature agence appliquées: max-width 150px, max-height 60px");
```

## Tests à Effectuer

### 1. Test de la signature client

1. Accéder à l'interface de signature client
2. Ouvrir la console développeur du navigateur (F12)
3. Dessiner une signature dans le canvas
4. Vérifier les logs console:
   - ✅ "Initialisation du canvas de signature"
   - ✅ "Fond: transparent (clearRect appliqué)"
   - ✅ "Signature captured: ... bytes"
5. Soumettre le formulaire
6. Vérifier les logs serveur (error_log):
   - ✅ "Step2-Signature: === RÉCEPTION SIGNATURE CLIENT ==="
   - ✅ "Step2-Signature: Format image validé: png"
   - ✅ "Step2-Signature: ✓ Signature enregistrée avec succès"

### 2. Test de la signature agence

1. S'assurer qu'un contrat est signé par tous les locataires (statut='signe')
2. En tant qu'admin, aller dans `/admin-v2/contrat-configuration.php`
3. Vérifier que:
   - ✅ La signature électronique de la société est activée
   - ✅ Une image de signature est téléchargée
4. Aller dans `/admin-v2/contrat-detail.php?id=XXX`
5. Cliquer sur "Valider le contrat"
6. Vérifier les logs serveur:
   - ✅ "PDF Generation: Contrat validé (statut='valide')"
   - ✅ "PDF Generation: Configuration signature agence - Activée: OUI"
   - ✅ "PDF Generation: ✓ Signature agence AJOUTÉE avec succès"
   - ✅ "PDF Generation: Dimensions signature agence appliquées: max-width 150px"

### 3. Test du PDF final

1. Télécharger le PDF généré
2. Vérifier dans le PDF:
   - ✅ La signature client apparaît avec une taille proportionnée (~150x60px max)
   - ✅ La signature client n'a PAS de fond gris ou bordure
   - ✅ La signature agence apparaît (si contrat validé)
   - ✅ La signature agence a une taille proportionnée (~150x60px max)

## Workflow Complet

```
1. Création du contrat (statut='en_attente')
   ↓
2. Tous les locataires signent
   → Statut passe à 'signe'
   → PDF généré SANS signature agence
   ↓
3. Admin valide le contrat
   → Statut passe à 'valide'
   → PDF régénéré AVEC signature agence (si activée)
   ↓
4. PDF final prêt avec:
   - Signatures clients (150x60px max, transparent)
   - Signature agence (150x60px max, transparent)
```

## Diagnostic en Cas de Problème

### Si la signature agence n'apparaît pas:

1. Vérifier le statut du contrat:
   ```sql
   SELECT id, reference_unique, statut, date_validation 
   FROM contrats 
   WHERE id = XXX;
   ```
   → Le statut doit être 'valide' (pas 'signe')

2. Vérifier la configuration:
   ```sql
   SELECT cle, valeur 
   FROM parametres 
   WHERE cle IN ('signature_societe_enabled', 'signature_societe_image');
   ```
   → `signature_societe_enabled` doit être '1' ou 'true'
   → `signature_societe_image` doit contenir un data URI commençant par "data:image/"

3. Consulter les logs serveur:
   ```bash
   tail -f /var/log/apache2/error.log | grep "PDF Generation"
   ```

### Si la signature client a un fond gris:

1. Vérifier le canvas dans le navigateur:
   - Inspecter l'élément `<canvas id="signatureCanvas">`
   - Vérifier que le style contient `background: transparent;`

2. Vérifier le data URI capturé:
   - Ouvrir la console développeur
   - Le data URI doit commencer par `data:image/png;base64,`
   - Copier le data URI et l'ouvrir dans un nouvel onglet
   - L'image doit avoir un fond transparent (pas de blanc/gris)

3. Vérifier le style dans le PDF:
   - Consulter les logs pour confirmer:
     ```
     PDF Generation: Signature client X - Style: SANS bordure, fond transparent
     ```

## Fichiers Modifiés

1. `/signature/step2-signature.php` - Logs de capture signature + canvas transparent
2. `/assets/js/signature.js` - Logs console côté client
3. `/pdf/generate-contrat-pdf.php` - Dimensions 150x60px + logs améliorés

## Commandes Utiles

### Visualiser les logs en temps réel:
```bash
# Logs PHP
tail -f /var/log/apache2/error.log | grep -E "(Step2-Signature|PDF Generation)"

# Ou si utilisation d'un fichier de log personnalisé
tail -f /var/log/php_errors.log | grep -E "(Step2-Signature|PDF Generation)"
```

### Tester un contrat spécifique:
```bash
cd /path/to/contrat-de-bail
php test-pdf-generation.php
```
