# Résolution: Problème de bordure grise sur les signatures PDF

## Problème initial

Les signatures (agence et locataires) apparaissaient avec une **bordure grise** dans les PDF générés, malgré l'utilisation de `border="0"` et de styles CSS `border: 0; border-style: none;`.

**Cause:** TCPDF dessine par défaut un cadre autour des images encodées en base64 lorsqu'elles sont insérées via des balises `<img>` dans le HTML.

## Solution implémentée

### Approche
Ne plus insérer les signatures via `<img>` dans le HTML, mais utiliser la méthode TCPDF native `$pdf->Image()` pour insérer les signatures **directement** dans le PDF après le rendu HTML.

### Modifications du code

#### 1. Fonction `replaceContratTemplateVariables()` (lignes 235-605)

**AVANT:**
```php
// Insertion via balise <img> dans le HTML
$sig .= '<img src="' . $locataire['signature_data'] . '" 
         alt="Signature" width="150" height="60" 
         border="0" style="background:transparent;"><br>';
```

**APRÈS:**
```php
// Création d'un espace réservé (20mm de hauteur)
$sig .= '<div style="height: 20mm; margin-bottom: 5mm;"></div>';

// Stockage des données pour insertion ultérieure
$signatureData[] = [
    'type' => 'SIGNATURE_LOCATAIRE_' . ($i + 1),
    'base64Data' => $base64Data,
    'format' => $imageFormat,
    'x' => 15,
    'y' => 0
];

// Retour modifié
return [
    'html' => $html,
    'signatures' => $signatureData
];
```

#### 2. Fonction `generateContratPDF()` (lignes 110-138)

**AVANT:**
```php
$html = replaceContratTemplateVariables($templateHtml, $contrat, $locataires);
$pdf->writeHTML($html, true, false, true, false, '');
```

**APRÈS:**
```php
// Récupérer HTML ET données de signatures
$replacementResult = replaceContratTemplateVariables($templateHtml, $contrat, $locataires);
$html = $replacementResult['html'];
$signatureData = $replacementResult['signatures'];

// Rendre le HTML
$pdf->writeHTML($html, true, false, true, false, '');

// Insérer les signatures via TCPDF::Image()
insertSignaturesDirectly($pdf, $signatureData);
```

#### 3. Nouvelle fonction `insertSignaturesDirectly()` (lignes 168-233)

```php
function insertSignaturesDirectly($pdf, $signatureData) {
    foreach ($signatureData as $sig) {
        // Décoder base64
        $imageData = base64_decode($sig['base64Data']);
        $format = strtoupper($sig['format']);
        
        // Calculer position
        if ($sig['type'] === 'SIGNATURE_AGENCE') {
            $yPos = 240; // mm depuis le haut
        } else {
            $locataireNum = $sig['locataireNum'] ?? 1;
            $yPos = 200 + ($locataireNum - 1) * 30;
        }
        $xPos = 20; // mm depuis la gauche
        
        // ⭐ INSERTION VIA TCPDF::Image() AVEC BORDER=0
        $pdf->Image(
            '@' . $imageData,      // @ = données binaires
            $xPos,                  // Position X (mm)
            $yPos,                  // Position Y (mm)
            40,                     // Largeur (mm)
            20,                     // Hauteur (mm)
            $format,                // PNG ou JPEG
            '',                     // Lien
            '',                     // Alignement
            false,                  // Resize
            300,                    // DPI
            '',                     // Palette align
            false,                  // Mask
            false,                  // Image mask
            0,                      // ⭐ BORDER = 0 (SUPPRIME LA BORDURE)
            false,                  // Fit box
            false,                  // Hidden
            false                   // Fit on page
        );
        
        error_log("✓ Signature insérée via TCPDF::Image() sans bordure");
    }
}
```

### Paramètres clés de `$pdf->Image()`

| Paramètre | Valeur | Description |
|-----------|--------|-------------|
| 1 | `'@' . $imageData` | Données binaires (préfixe `@`) |
| 2-3 | `$xPos, $yPos` | Position en mm |
| 4-5 | `40, 20` | Dimensions fixes 40×20 mm |
| 6 | `$format` | Format PNG ou JPEG |
| 10 | `300` | DPI pour qualité professionnelle |
| **14** | **`0`** | **BORDER = 0 → Supprime la bordure** |

## Résultat

### ✅ Avantages

1. **Pas de bordure grise** - Le paramètre `border=0` fonctionne correctement avec `$pdf->Image()`
2. **Dimensions fixes** - 40mm × 20mm, proportionnées et professionnelles
3. **Fond transparent** - Préservé grâce aux données PNG
4. **Haute qualité** - DPI 300 pour un rendu optimal
5. **Code plus propre** - Pas de data URI base64 dans le HTML
6. **Logs détaillés** - Confirmation de chaque insertion

### 📊 Comparaison

| Aspect | Avant (❌ avec bordure) | Après (✅ sans bordure) |
|--------|------------------------|-------------------------|
| Méthode | `<img>` dans HTML | `$pdf->Image()` natif |
| Bordure | ❌ Grise, visible | ✅ Aucune |
| Dimensions | Variables | Fixes 40×20mm |
| Qualité | Standard | DPI 300 |
| HTML | Data URI base64 | Espace réservé |

## Logs de confirmation

Lors de la génération d'un PDF, les logs suivants confirment l'utilisation correcte :

```
PDF Generation: Signature client 1 - Sera insérée via TCPDF::Image() après writeHTML
PDF Generation: ✓ Espace réservé créé pour signature locataire 1
PDF Generation: Signature agence sera insérée via TCPDF::Image() après writeHTML
PDF Generation: ✓ Espace réservé créé pour signature agence
PDF Generation: === INSERTION DES SIGNATURES VIA TCPDF::Image() ===
PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure - Type: SIGNATURE_LOCATAIRE_1, Position: (20mm, 200mm), Dimensions: 40x20mm, Format: PNG
PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure - Type: SIGNATURE_AGENCE, Position: (20mm, 240mm), Dimensions: 40x20mm, Format: PNG
```

## Tests de validation

### Test automatique
```bash
php test-syntax-check.php
```

Résultat attendu : ✅ Tous les tests passent

### Test avec base de données
```bash
php test-signature-tcpdf.php
```

Génère un PDF réel avec signatures et vérifie l'absence de bordures.

## Fichiers modifiés

- `pdf/generate-contrat-pdf.php` - Logique principale de génération PDF

## Compatibilité

- ✅ TCPDF 6.6+
- ✅ PHP 7.2+
- ✅ Formats PNG et JPEG
- ✅ Transparence PNG préservée
- ✅ Rétrocompatible (les anciens PDFs restent valides)

## Documentation technique

Pour plus de détails sur l'implémentation :
- `TEST_SIGNATURE_TCPDF.md` - Guide complet
- `test-syntax-check.php` - Tests automatisés

## Conclusion

Le problème de bordure grise est **résolu** par l'utilisation de la méthode native `$pdf->Image()` avec le paramètre `border=0`, qui fonctionne correctement contrairement à l'attribut HTML `border="0"` dans les balises `<img>`.

**Objectif atteint :** Les signatures s'affichent maintenant correctement, sans bordure ni fond gris, avec des dimensions proportionnées.
