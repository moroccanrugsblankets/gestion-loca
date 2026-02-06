# Fix: État des Lieux Signature Rendering - Documentation

## Problème Résolu

### Problème Initial
Les signatures dans les PDFs d'états des lieux apparaissaient avec :
- ✗ Des bordures visibles autour des images de signature
- ✗ Un rendu dégradé de la qualité d'image
- ✗ Une apparence inconsistante par rapport aux contrats de bail

### Cause Racine
- Les signatures étaient stockées et utilisées en format **base64 data URI**
- TCPDF (générateur de PDF) rend mal les images base64:
  - Ajoute des bordures par défaut
  - Qualité d'image réduite
  - Taille de fichier plus importante

### Solution Appliquée
- Conversion automatique des signatures base64 → fichiers physiques JPG
- Utilisation d'URLs publiques pour les images dans le PDF
- Application du même principe que dans le module Contrats de bail

---

## Changements Implémentés

### 1. Nouvelle Fonction: `convertSignatureToPhysicalFile()`

**Fichier:** `pdf/generate-etat-lieux.php` (lignes 1043-1097)

**Fonctionnalité:**
```php
function convertSignatureToPhysicalFile($signatureData, $prefix, $etatLieuxId, $tenantId = null)
```

**Comportement:**
1. Détecte si la signature est en format base64 data URI
2. Si non base64 → retourne le chemin inchangé (déjà un fichier physique)
3. Si base64 → convertit en fichier JPG physique:
   - Extrait et décode les données base64
   - Crée le répertoire `uploads/signatures/` si nécessaire
   - Génère un nom de fichier unique: `{prefix}_etat_lieux_{id}_{timestamp}.jpg`
   - Sauvegarde le fichier sur le serveur
   - Retourne le chemin relatif `uploads/signatures/...`
4. En cas d'échec → retourne les données originales (fallback sécurisé)

**Avantages:**
- ✅ Conversion automatique et transparente
- ✅ Noms de fichiers uniques (évite les collisions)
- ✅ Gestion d'erreur robuste
- ✅ Logging détaillé pour debugging

### 2. Mise à Jour: Signature Bailleur

**Fichier:** `pdf/generate-etat-lieux.php` (lignes 1141-1177)

**Changements:**
- Appel de `convertSignatureToPhysicalFile()` avant utilisation
- Mise à jour automatique de la base de données si conversion réussie
- Vérification de l'existence du fichier physique
- Utilisation d'URL publique pour TCPDF
- Fallback sur base64 si conversion échoue (avec warning)

**Code clé:**
```php
$landlordSigPath = convertSignatureToPhysicalFile($landlordSigPath, 'landlord', $etatLieuxId);

// Update database with physical path if converted
if (!preg_match('/^data:image/', $landlordSigPath) && preg_match('/^uploads\/signatures\//', $landlordSigPath)) {
    $updateStmt = $pdo->prepare("UPDATE parametres SET valeur = ? WHERE cle = ?");
    $updateStmt->execute([$landlordSigPath, $paramKey]);
}
```

### 3. Mise à Jour: Signatures Locataires

**Fichier:** `pdf/generate-etat-lieux.php` (lignes 1200-1236)

**Changements similaires:**
- Conversion automatique de chaque signature locataire
- Mise à jour de la table `etat_lieux_locataires`
- Utilisation de fichiers physiques dans le PDF

**Code clé:**
```php
$signatureData = convertSignatureToPhysicalFile($signatureData, 'tenant', $etatLieuxId, $tenantDbId);

// Update database if signature was converted
if ($tenantDbId && preg_match('/^uploads\/signatures\//', $signatureData)) {
    $updateStmt = $pdo->prepare("UPDATE etat_lieux_locataires SET signature_data = ? WHERE id = ?");
    $updateStmt->execute([$signatureData, $tenantDbId]);
}
```

---

## Style CSS des Signatures

Le style CSS était déjà correct (mis à jour dans le commit précédent):

```css
ETAT_LIEUX_SIGNATURE_IMG_STYLE = 
  'max-width: 30mm; 
   max-height: 15mm; 
   display: block; 
   border: 0; 
   border-width: 0; 
   border-style: none; 
   border-color: transparent; 
   outline: none; 
   outline-width: 0; 
   box-shadow: none; 
   background: transparent; 
   padding: 0; 
   margin: 0 auto;'
```

**Propriétés critiques pour supprimer les bordures:**
- `border: 0` - Supprime toutes les bordures
- `border-width: 0` - Force largeur à zéro
- `border-style: none` - Aucun style de bordure
- `border-color: transparent` - Couleur transparente
- `outline: none` - Supprime le contour
- `outline-width: 0` - Force largeur contour à zéro
- `background: transparent` - Fond transparent

---

## Impact et Bénéfices

### Qualité d'Image
- ✅ **Avant:** Signatures floues/pixelisées avec base64
- ✅ **Après:** Signatures nettes et claires depuis fichiers JPG

### Bordures
- ✅ **Avant:** Bordures visibles autour des signatures
- ✅ **Après:** Aucune bordure, rendu propre

### Performance
- ✅ **Avant:** Base64 augmente la taille HTML/PDF
- ✅ **Après:** Fichiers physiques plus efficaces

### Cohérence
- ✅ Même rendu que les contrats de bail
- ✅ Expérience utilisateur homogène

### Base de Données
- ✅ Mise à jour automatique lors de la génération PDF
- ✅ Pas de migration nécessaire (conversion à la volée)
- ✅ Futures générations utilisent directement les fichiers

---

## Tests Effectués

### Test Unitaire
**Fichier:** `test-signature-standalone.php`

**Résultats:**
```
✓ Test 1: Physical file path input - PASS
✓ Test 2: Base64 PNG conversion - PASS
✓ Test 3: Base64 JPEG conversion (landlord) - PASS
✓ Test 4: Invalid base64 data - PASS
✓ Test 5: Non-base64 string input - PASS
```

**Vérifications:**
- Conversion base64 → fichier JPG fonctionne
- Fichiers créés dans `uploads/signatures/`
- Noms de fichiers corrects et uniques
- Données invalides gérées en sécurité
- Chemins physiques préservés

---

## Migration des Données Existantes

### Automatique
La conversion se fait **automatiquement** lors de la génération du PDF:
- Pas besoin de script de migration
- Pas de downtime
- Conversion transparente pour l'utilisateur

### Processus
1. Utilisateur génère un PDF d'état des lieux
2. Fonction détecte signatures base64
3. Convertit en fichiers physiques
4. Met à jour la base de données
5. Génère le PDF avec les fichiers physiques

### Première Génération
- Signatures base64 → converties et sauvegardées
- Base de données mise à jour avec chemins fichiers

### Générations Suivantes
- Utilise directement les fichiers physiques
- Pas de reconversion nécessaire
- Performance optimale

---

## Vérification en Production

### Étapes de Test

1. **Générer un État des Lieux avec Signatures**
   ```
   - Créer/modifier un état des lieux
   - Ajouter signatures bailleur + locataire
   - Générer le PDF
   ```

2. **Vérifier les Fichiers Physiques**
   ```bash
   ls -la uploads/signatures/
   # Devrait contenir des fichiers .jpg récents
   ```

3. **Vérifier le PDF**
   - Ouvrir le PDF généré
   - Vérifier: **pas de bordures** autour des signatures
   - Vérifier: qualité d'image nette
   - Vérifier: taille appropriée (30mm × 15mm max)
   - Vérifier: pas de pages supplémentaires

4. **Vérifier la Base de Données**
   ```sql
   -- Signatures locataires
   SELECT id, signature_data 
   FROM etat_lieux_locataires 
   WHERE signature_data IS NOT NULL;
   
   -- Devrait montrer 'uploads/signatures/...' au lieu de 'data:image/...'
   
   -- Signature bailleur
   SELECT valeur 
   FROM parametres 
   WHERE cle = 'signature_societe_etat_lieux_image';
   ```

5. **Comparer avec Contrat de Bail**
   - Générer un contrat de bail avec signatures
   - Comparer le rendu visuel avec état des lieux
   - Les signatures doivent avoir la même qualité

---

## Logs et Debugging

### Messages de Log Clés

**Conversion réussie:**
```
✓ Signature converted to physical file: uploads/signatures/tenant_etat_lieux_123_tenant_456_1234567890.jpg
✓ Updated tenant signature in database to physical file
```

**Utilisation fichier existant:**
```
(Aucun message - pas de conversion nécessaire)
```

**Échec de conversion (fallback):**
```
WARNING: Using base64 signature for tenant (conversion may have failed)
Failed to decode base64 signature
```

### Vérifier les Logs
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/php-fpm/error.log
```

---

## Sécurité

### Validation des Données
- ✅ Regex strict pour détecter format base64
- ✅ Validation du format d'image (PNG/JPEG)
- ✅ base64_decode avec mode strict
- ✅ Vérification existence fichier avant utilisation

### Noms de Fichiers
- ✅ Générés programmatiquement (pas d'input utilisateur)
- ✅ Timestamp unique (évite écrasement)
- ✅ Extensions fixes (.jpg)

### Permissions
- ✅ Répertoire créé avec 0755
- ✅ Fichiers lisibles par serveur web
- ✅ Pas d'exécution de code

### Fallback
- ✅ En cas d'échec, retourne données originales
- ✅ Pas de crash ou d'erreur fatale
- ✅ PDF généré même si conversion échoue

---

## Maintenance Future

### Nettoyage des Anciens Fichiers
Si nécessaire, créer un script pour supprimer les vieux fichiers de signature:

```php
// Exemple: supprimer fichiers > 2 ans
$dir = 'uploads/signatures/';
$files = glob($dir . '*_etat_lieux_*.jpg');
$twoYearsAgo = time() - (2 * 365 * 24 * 60 * 60);

foreach ($files as $file) {
    if (filemtime($file) < $twoYearsAgo) {
        // Vérifier que le fichier n'est plus référencé en DB
        // avant de supprimer
    }
}
```

### Monitoring
- Surveiller la taille du répertoire `uploads/signatures/`
- Vérifier les logs pour échecs de conversion
- Alerter si trop de fallback base64

---

## Résumé Technique

| Aspect | Avant | Après |
|--------|-------|-------|
| Format signature | Base64 data URI | Fichier JPG physique |
| Qualité PDF | Dégradée | Nette |
| Bordures | Visibles | Aucune |
| Taille fichier | Plus grande | Optimisée |
| Performance | Moyenne | Meilleure |
| Cohérence | Non | Oui (comme contrats) |
| Migration | - | Automatique |

---

## Support

En cas de problème:

1. Vérifier les logs d'erreur PHP
2. Vérifier permissions répertoire `uploads/signatures/`
3. Vérifier que les signatures sont bien en base64 initialement
4. Tester avec le script `test-signature-standalone.php`
5. Vérifier que TCPDF peut accéder aux fichiers physiques

---

**Date:** 2026-02-06  
**Version:** 1.0  
**Statut:** ✅ Implémenté et testé
