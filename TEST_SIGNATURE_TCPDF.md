# Test de génération PDF avec signatures via TCPDF::Image()

## Changements effectués

### AVANT (problème)
```php
// Dans replaceContratTemplateVariables()
$sig .= '<img src="' . $locataire['signature_data'] . '" 
         alt="Signature" width="150" height="60" 
         border="0" style="background:transparent;"><br>';
```

**Problème:** TCPDF dessine une bordure grise autour des images base64, même avec `border="0"` et CSS.

### APRÈS (solution)
```php
// 1. Dans replaceContratTemplateVariables() - Créer un placeholder
$placeholderId = 'SIGNATURE_LOCATAIRE_' . ($i + 1);
$sig .= '<p>[' . $placeholderId . ']</p>';

// Stocker les données pour insertion ultérieure
$signatureData[] = [
    'type' => $placeholderId,
    'base64Data' => $base64Data,
    'format' => $imageFormat,
    'x' => 15,
    'y' => 0
];

// 2. Après writeHTML() - Insérer via TCPDF::Image()
$pdf->Image(
    '@' . $imageData,      // @ prefix pour données binaires
    $xPos,                  // Position X
    $yPos,                  // Position Y
    40,                     // Largeur en mm (fixe)
    20,                     // Hauteur en mm (fixe)
    $format,                // Format (PNG, JPEG)
    '',                     // Lien
    '',                     // Alignement
    false,                  // Resize
    300,                    // DPI pour qualité
    '',                     // Alignement palette
    false,                  // Mask
    false,                  // Image mask
    0,                      // BORDER = 0 ⬅️ SUPPRIME LA BORDURE
    false,                  // Fit box
    false,                  // Hidden
    false                   // Fit on page
);
```

## Avantages de cette approche

1. ✅ **Pas de bordure** - Le paramètre `border=0` (position 13) fonctionne avec `$pdf->Image()`
2. ✅ **Dimensions fixes** - 40mm x 20mm proportionnées
3. ✅ **Fond transparent** - Préservé via données PNG
4. ✅ **Haute qualité** - DPI 300 pour rendu professionnel
5. ✅ **Logs détaillés** - Confirmation de chaque insertion

## Flux d'exécution

```
generateContratPDF($contratId)
    ↓
replaceContratTemplateVariables($template, $contrat, $locataires)
    ├─ Génère HTML avec placeholders: [SIGNATURE_AGENCE], [SIGNATURE_LOCATAIRE_1]
    └─ Retourne: ['html' => $html, 'signatures' => $signatureData]
    ↓
$pdf->writeHTML($html)  // Render HTML sans les images
    ↓
insertSignaturesDirectly($pdf, $signatureData)
    └─ Pour chaque signature:
        ├─ Décode base64
        ├─ Calcule position (X, Y)
        └─ $pdf->Image('@' . $imageData, ..., border=0)
    ↓
$pdf->Output($filepath, 'F')  // Sauvegarder PDF
```

## Exemple de sortie des logs

```
PDF Generation: Début du remplacement des variables pour contrat #123
PDF Generation: === TRAITEMENT DES SIGNATURES CLIENTS ===
PDF Generation: Nombre de locataires à traiter: 2
PDF Generation: Signature client 1 - Données présentes (taille: 12345 octets)
PDF Generation: Signature client 1 - Format: png, Taille base64: 12345 octets
PDF Generation: Signature client 1 - Sera insérée via TCPDF::Image() après writeHTML
PDF Generation: ✓ Placeholder créé: [SIGNATURE_LOCATAIRE_1] - Signature sera insérée via TCPDF::Image() sans bordure
PDF Generation: === TRAITEMENT SIGNATURE AGENCE ===
PDF Generation: Contrat validé (statut='valide'), traitement de la signature agence
PDF Generation: ✓ Placeholder signature agence créé: [SIGNATURE_AGENCE]
PDF Generation: Signature agence sera insérée via TCPDF::Image() après writeHTML
PDF Generation: Nombre de signatures à insérer via TCPDF::Image(): 3
PDF Generation: === INSERTION DES SIGNATURES VIA TCPDF::Image() ===
PDF Generation: Début insertion signatures - 3 signature(s) à insérer
PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure - Type: SIGNATURE_LOCATAIRE_1, Page: 1, Position: (20mm, 200mm), Dimensions: 40x20mm, Format: PNG
PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure - Type: SIGNATURE_LOCATAIRE_2, Page: 1, Position: (20mm, 230mm), Dimensions: 40x20mm, Format: PNG
PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure - Type: SIGNATURE_AGENCE, Page: 1, Position: (20mm, 240mm), Dimensions: 40x20mm, Format: PNG
PDF Generation: === FIN INSERTION SIGNATURES ===
```

## Tests de validation

Exécuter:
```bash
php test-syntax-check.php
```

Résultat attendu:
- ✓ Syntaxe PHP valide
- ✓ Fonction insertSignaturesDirectly présente
- ✓ Placeholders créés au lieu de <img>
- ✓ border=0 configuré
- ✓ Dimensions 40x20mm
- ✓ DPI 300

## Pour tester avec un PDF réel

1. Configurer la base de données dans `includes/config.php`
2. Créer un contrat avec signatures
3. Exécuter: `php test-signature-tcpdf.php`
4. Vérifier le PDF généré dans `pdf/contrats/`

## Résultat attendu

Les signatures apparaissent maintenant **sans bordure grise**, avec:
- Dimensions proportionnées: 40mm x 20mm
- Fond transparent préservé
- Qualité professionnelle (300 DPI)
- Pas de cadre ni de bord
