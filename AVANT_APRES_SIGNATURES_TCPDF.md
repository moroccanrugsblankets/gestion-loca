# Comparaison visuelle : AVANT / APRÈS - Signatures PDF

## Vue d'ensemble

Ce document montre la différence entre l'ancienne méthode (avec bordures) et la nouvelle méthode (sans bordures) pour l'insertion des signatures dans les PDFs.

---

## 🔴 AVANT : Méthode HTML `<img>` (avec bordures)

### Code utilisé
```php
// Dans replaceContratTemplateVariables()
$sig .= '<img src="data:image/png;base64,iVBORw0KGgoAAAANS..." 
         alt="Signature" 
         width="150" 
         height="60" 
         border="0" 
         style="background:transparent; border:0; border-style:none;"><br>';
```

### Rendu dans le PDF
```
┌─────────────────────────────────────────┐
│  Locataire :                             │
│  Jean Dupont                             │
│  Lu et approuvé                          │
│                                          │
│  ╔═══════════════════════════════════╗  │ ← BORDURE GRISE (problème)
│  ║  [signature manuscrite]           ║  │
│  ║                                   ║  │
│  ╚═══════════════════════════════════╝  │
│                                          │
│  Horodatage : 03/02/2026 à 14:30:00     │
└─────────────────────────────────────────┘
```

### ❌ Problèmes
- Bordure grise visible autour de la signature
- Impossible à supprimer avec CSS ou attribut `border="0"`
- Aspect non professionnel
- Causé par le moteur de rendu TCPDF pour les images base64 dans HTML

---

## 🟢 APRÈS : Méthode native `$pdf->Image()` (sans bordures)

### Code utilisé

#### Étape 1 : Espace réservé dans le HTML
```php
// Dans replaceContratTemplateVariables()
$sig .= '<div style="height: 20mm; margin-bottom: 5mm;"></div>';

// Stockage séparé des données
$signatureData[] = [
    'type' => 'SIGNATURE_LOCATAIRE_1',
    'base64Data' => $base64Data,
    'format' => 'png',
    'x' => 15,
    'y' => 0
];
```

#### Étape 2 : Insertion après writeHTML()
```php
// Dans insertSignaturesDirectly()
$pdf->Image(
    '@' . $imageData,      // Données binaires
    20,                     // X position (mm)
    200,                    // Y position (mm)
    40,                     // Largeur (mm)
    20,                     // Hauteur (mm)
    'PNG',                  // Format
    '',                     // Lien
    '',                     // Alignement
    false,                  // Resize
    300,                    // DPI
    '',                     // Palette
    false,                  // Mask
    false,                  // Image mask
    0,                      // ⭐ BORDER = 0
    false,                  // Fit box
    false,                  // Hidden
    false                   // Fit on page
);
```

### Rendu dans le PDF
```
┌─────────────────────────────────────────┐
│  Locataire :                             │
│  Jean Dupont                             │
│  Lu et approuvé                          │
│                                          │
│  ┌───────────────────────────────────┐  │ ← PAS DE BORDURE ✅
│  │  [signature manuscrite]           │  │
│  │                                   │  │
│  └───────────────────────────────────┘  │
│                                          │
│  Horodatage : 03/02/2026 à 14:30:00     │
└─────────────────────────────────────────┘
```

### ✅ Avantages
- Aucune bordure visible
- Fond transparent préservé
- Dimensions fixes (40mm × 20mm)
- Haute qualité (300 DPI)
- Aspect professionnel

---

## Comparaison technique

| Aspect | AVANT (HTML) | APRÈS (TCPDF::Image) |
|--------|--------------|----------------------|
| **Méthode** | `<img src="data:...">` | `$pdf->Image('@' . $data, ...)` |
| **Bordure** | ❌ Grise, 1-2px | ✅ Aucune |
| **CSS/Attributs** | ❌ Ignorés par TCPDF | ✅ Paramètre `border=0` fonctionne |
| **Dimensions** | Variables (150×60px) | ✅ Fixes (40×20mm) |
| **Qualité** | Standard | ✅ DPI 300 |
| **Data URI** | ❌ Dans HTML (lourd) | ✅ Données séparées |
| **Transparence** | ⚠️ Parfois perdue | ✅ Toujours préservée |
| **Position** | ⚠️ Dépend du flux HTML | ✅ Contrôle précis (X, Y en mm) |

---

## Exemple concret : Contrat avec 2 locataires + signature agence

### AVANT
```
┌─────────────────────────────────────────────────────┐
│  SIGNATURES                                          │
├─────────────────────────────────────────────────────┤
│  Locataire 1 :                                       │
│  Jean Dupont                                         │
│  Lu et approuvé                                      │
│  ╔═══════════════════════════════════════════════╗  │ ← Bordure
│  ║  [signature Jean]                             ║  │
│  ╚═══════════════════════════════════════════════╝  │
│  Horodatage : 03/02/2026 à 14:30:00                 │
│                                                      │
│  Locataire 2 :                                       │
│  Marie Martin                                        │
│  Lu et approuvé                                      │
│  ╔═══════════════════════════════════════════════╗  │ ← Bordure
│  ║  [signature Marie]                            ║  │
│  ╚═══════════════════════════════════════════════╝  │
│  Horodatage : 03/02/2026 à 15:45:00                 │
│                                                      │
│  Signature électronique de la société                │
│  ╔═══════════════════════════════════════════════╗  │ ← Bordure
│  ║  MY INVEST IMMOBILIER                         ║  │
│  ║  [logo/signature]                             ║  │
│  ╚═══════════════════════════════════════════════╝  │
│  Validé le : 03/02/2026 à 16:00:00                  │
└─────────────────────────────────────────────────────┘
```

### APRÈS
```
┌─────────────────────────────────────────────────────┐
│  SIGNATURES                                          │
├─────────────────────────────────────────────────────┤
│  Locataire 1 :                                       │
│  Jean Dupont                                         │
│  Lu et approuvé                                      │
│  ┌───────────────────────────────────────────────┐  │ ← Propre
│  │  [signature Jean]                             │  │
│  └───────────────────────────────────────────────┘  │
│  Horodatage : 03/02/2026 à 14:30:00                 │
│                                                      │
│  Locataire 2 :                                       │
│  Marie Martin                                        │
│  Lu et approuvé                                      │
│  ┌───────────────────────────────────────────────┐  │ ← Propre
│  │  [signature Marie]                            │  │
│  └───────────────────────────────────────────────┘  │
│  Horodatage : 03/02/2026 à 15:45:00                 │
│                                                      │
│  Signature électronique de la société                │
│  ┌───────────────────────────────────────────────┐  │ ← Propre
│  │  MY INVEST IMMOBILIER                         │  │
│  │  [logo/signature]                             │  │
│  └───────────────────────────────────────────────┘  │
│  Validé le : 03/02/2026 à 16:00:00                  │
└─────────────────────────────────────────────────────┘
```

---

## Workflow de génération

### AVANT (HTML uniquement)
```
Contrat → replaceVariables() → HTML avec <img> → writeHTML() → PDF
                                  ↓
                          [signature base64]
                                  ↓
                          ❌ TCPDF ajoute bordure
```

### APRÈS (Hybride HTML + Image native)
```
Contrat → replaceVariables() → HTML avec espace vide → writeHTML() → PDF
              ↓                        +
        Données signature         signatures stockées
              ↓                        ↓
              └──────────────→ insertSignaturesDirectly()
                                      ↓
                              $pdf->Image(..., border=0)
                                      ↓
                              ✅ Pas de bordure
```

---

## Paramètres de `$pdf->Image()`

```php
$pdf->Image(
    '@' . $imageData,  // [1]  Données binaires (préfixe @)
    20,                 // [2]  X position (mm depuis la gauche)
    200,                // [3]  Y position (mm depuis le haut)
    40,                 // [4]  Largeur (mm) - FIXE
    20,                 // [5]  Hauteur (mm) - FIXE
    'PNG',              // [6]  Format image
    '',                 // [7]  Lien URL (none)
    '',                 // [8]  Alignement (default)
    false,              // [9]  Resize (no)
    300,                // [10] DPI - HAUTE QUALITÉ
    '',                 // [11] Palette align (default)
    false,              // [12] Is mask (no)
    false,              // [13] Image mask (no)
    0,                  // [14] ⭐ BORDER - 0 = PAS DE BORDURE
    false,              // [15] Fit box (no)
    false,              // [16] Hidden (no)
    false               // [17] Fit on page (no)
);
```

**Paramètre clé :** Position [14] = `0` → Supprime complètement la bordure

---

## Logs de confirmation

### Génération d'un PDF
```
PDF Generation: Signature client 1 - Sera insérée via TCPDF::Image() après writeHTML
PDF Generation: ✓ Espace réservé créé pour signature locataire 1
PDF Generation: === INSERTION DES SIGNATURES VIA TCPDF::Image() ===
PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure
  - Type: SIGNATURE_LOCATAIRE_1
  - Position: (20mm, 200mm)
  - Dimensions: 40x20mm
  - Format: PNG
  ✅ Confirmation : Aucune bordure
```

---

## Résumé

| ✅ Objectif | ❌ Avant | ✅ Après |
|------------|----------|----------|
| Pas de bordure | Bordure grise | Aucune bordure |
| Dimensions proportionnées | 150×60px variables | 40×20mm fixes |
| Fond transparent | Parfois perdu | Toujours préservé |
| Qualité professionnelle | Standard | DPI 300 |

**Conclusion :** Le problème de bordure grise est **résolu** en utilisant `$pdf->Image()` avec `border=0` au lieu de balises HTML `<img>`.
