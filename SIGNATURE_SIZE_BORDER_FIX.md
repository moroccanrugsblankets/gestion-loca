# Correction des Problèmes de Signature et Erreurs SQL

## Résumé Exécutif

Ce document détaille les corrections apportées pour résoudre trois problèmes critiques identifiés dans les logs d'erreur :
1. Erreurs SQL `Column not found: contrat_id`
2. Erreur PHP `Undefined index: BASE_URL`
3. Signatures trop petites dans les PDFs générés

## 📊 Comparaison Visuelle des Changements

### Dimensions des Signatures

```
AVANT (trop petit):
┌──────────────────┐
│   Signature      │  150x60px
│    Client        │  (50% du canvas)
└──────────────────┘

APRÈS (lisible):
┌─────────────────────────┐
│      Signature          │  200x100px
│       Client            │  (33% réduction vs canvas)
└─────────────────────────┘

Canvas original: 300x150px
```

### Bordures des Signatures

Les signatures sont configurées SANS BORDURE :
```css
border: 0;
border-style: none;
outline: none;
background: transparent;
```

✅ Pas de changement nécessaire - déjà correctement implémenté

## 🔧 Corrections Appliquées

### 1. Erreur SQL - Table `logs`

**Problème:**
```
Erreur SQL: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'contrat_id' in 'field list'
```

**Cause:**
Le schéma de la table `logs` a été modifié pour utiliser `type_entite` et `entite_id` au lieu de `contrat_id`, mais le code n'a pas été mis à jour partout.

**Fichiers corrigés:**
- `includes/functions.php` (ligne 75)
- `admin-v2/envoyer-signature.php` (ligne 83)

**Changement dans `includes/functions.php`:**
```php
// AVANT
function logAction($contratId, $action, $details = '') {
    $sql = "INSERT INTO logs (contrat_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)";
    $stmt = executeQuery($sql, [$contratId, $action, $details, getClientIp()]);
    return $stmt !== false;
}

// APRÈS
function logAction($contratId, $action, $details = '') {
    $sql = "INSERT INTO logs (type_entite, entite_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = executeQuery($sql, ['contrat', $contratId, $action, $details, getClientIp()]);
    return $stmt !== false;
}
```

**Changement dans `admin-v2/envoyer-signature.php`:**
```php
// AVANT
$stmt = $pdo->prepare("
    INSERT INTO logs (contrat_id, action, details, ip_address)
    VALUES (?, 'signature_link_sent', ?, ?)
");
$stmt->execute([
    $contrat_id,
    "Lien de signature envoyé...",
    $_SERVER['REMOTE_ADDR']
]);

// APRÈS
$stmt = $pdo->prepare("
    INSERT INTO logs (type_entite, entite_id, action, details, ip_address)
    VALUES (?, ?, 'signature_link_sent', ?, ?)
");
$stmt->execute([
    'contrat',
    $contrat_id,
    "Lien de signature envoyé...",
    $_SERVER['REMOTE_ADDR']
]);
```

### 2. Erreur BASE_URL

**Problème:**
```
Error [8]: Undefined index: BASE_URL in step3-documents.php on line 103
```

**Cause:**
Le tableau de configuration `$config` utilise la clé `SITE_URL`, pas `BASE_URL`.

**Fichier corrigé:**
- `signature/step3-documents.php` (ligne 103)

**Changement:**
```php
// AVANT
$lienAdmin = $config['BASE_URL'] . '/admin-v2/contract-details.php?id=' . $contratId;

// APRÈS
$lienAdmin = $config['SITE_URL'] . '/admin-v2/contract-details.php?id=' . $contratId;
```

### 3. Taille des Signatures

**Problème:**
Les signatures apparaissaient trop petites dans le PDF (150x60px alors que le canvas fait 300x150px).

**Fichier corrigé:**
- `pdf/generate-contrat-pdf.php` (lignes 220-223 et 282)

**Changements pour les signatures clients:**
```php
// AVANT
$sig .= '<p><img src="' . $locataire['signature_data'] . '" 
         alt="Signature" 
         style="max-width: 150px; max-height: 60px; ..."></p>';

// APRÈS
$sig .= '<p><img src="' . $locataire['signature_data'] . '" 
         alt="Signature" 
         style="max-width: 200px; max-height: 100px; ..."></p>';
```

**Changements pour la signature agence:**
```php
// AVANT
$signatureAgence .= '<p><img src="' . $signatureImage . '" 
                    alt="Signature Société" 
                    style="max-width: 150px; max-height: 60px; ..."></p>';

// APRÈS
$signatureAgence .= '<p><img src="' . $signatureImage . '" 
                    alt="Signature Société" 
                    style="max-width: 200px; max-height: 100px; ..."></p>';
```

## 📈 Amélioration de la Taille

| Élément | Avant | Après | Amélioration |
|---------|-------|-------|--------------|
| Largeur max | 150px | 200px | +33% |
| Hauteur max | 60px | 100px | +67% |
| Surface totale | 9,000px² | 20,000px² | +122% |
| Ratio vs Canvas | 50% | 66% | +16 points |

## ✅ Tests et Vérification

### Tests de Syntaxe
```bash
✓ php -l includes/functions.php          # No syntax errors
✓ php -l signature/step3-documents.php   # No syntax errors  
✓ php -l pdf/generate-contrat-pdf.php    # No syntax errors
✓ php -l admin-v2/envoyer-signature.php  # No syntax errors
```

### Vérification Manuelle Requise

Pour confirmer que les corrections fonctionnent en production :

1. **Test du flux de signature complet:**
   - Créer un nouveau contrat
   - Envoyer le lien de signature
   - Signer le contrat
   - Vérifier `error.log` : plus d'erreur SQL `contrat_id`

2. **Test de finalisation:**
   - Compléter tout le processus de signature
   - Vérifier l'email admin : pas d'erreur `BASE_URL`
   - Vérifier que le lien dans l'email fonctionne

3. **Test de génération PDF:**
   - Générer un PDF avec signature
   - Ouvrir le PDF et vérifier la taille de signature
   - La signature doit être clairement lisible
   - Pas de bordure autour de la signature

## 📝 Logs Attendus

### Avant les Corrections
```
[03-Feb-2026 00:56:38] Erreur SQL: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'contrat_id' in 'field list'
[03-Feb-2026 00:57:05] Error [8]: Undefined index: BASE_URL in step3-documents.php on line 103
[03-Feb-2026 00:57:04] PDF Generation: Dimensions appliquées: max-width 150px, max-height 60px
```

### Après les Corrections
```
[Date] Step2-Signature: ✓ Signature enregistrée avec succès
[Date] Email envoyé avec succès à: admin@... - Sujet: Contrat signé...
[Date] PDF Generation: Signature client 1 - Dimensions appliquées: max-width 200px, max-height 100px
[Date] PDF Generation: Style: SANS bordure, fond transparent, affichage proportionné
```

## 📦 Fichiers Modifiés

```
modified:   admin-v2/envoyer-signature.php
modified:   includes/functions.php
modified:   pdf/generate-contrat-pdf.php
modified:   signature/step3-documents.php
```

## 🎯 Impact

### Erreurs SQL ❌ → ✅
- ✅ Toutes les opérations de logging fonctionneront
- ✅ Plus d'interruption du flux de signature
- ✅ Logs correctement enregistrés dans la base

### Erreur Configuration ❌ → ✅
- ✅ Email admin envoyé sans erreur PHP
- ✅ Lien admin correct dans les emails
- ✅ Notification complète aux administrateurs

### Lisibilité Signatures 📉 → 📈
- ✅ Signatures 33% plus grandes
- ✅ Meilleure lisibilité dans les PDFs
- ✅ Respect du ratio 2:1 du canvas
- ✅ Apparence professionnelle maintenue

## 🔒 Sécurité

Aucun problème de sécurité introduit ou résolu par ces changements.
Les validations et protections existantes sont préservées.

## 📅 Date de Correction

**Date:** 3 février 2026  
**Branche:** copilot/debug-signature-size-issue  
**Commit:** Fix SQL errors, BASE_URL issue, and signature size

---

**Note:** Tous les changements sont rétrocompatibles et n'affectent pas les contrats existants.
