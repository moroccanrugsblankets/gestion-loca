# Exit State Template Configuration - Implementation Summary

## Overview
This implementation adds a separate template configuration for "État de Sortie" (Exit State) in the `/admin-v2/etat-lieux-configuration.php` file, allowing administrators to customize the PDF template for exit inspections separately from entry inspections.

## Changes Made

### 1. Database Parameters
- **New Parameter**: `etat_lieux_sortie_template_html`
  - Type: `text`
  - Group: `etats_lieux`
  - Description: "Template HTML de l'état des lieux de sortie avec variables dynamiques"

- **Existing Parameter**: `etat_lieux_template_html` (Entry State)
  - Kept for backward compatibility
  - Used for entry state inspections

### 2. Admin Configuration Page (`admin-v2/etat-lieux-configuration.php`)

#### Updated Header
- Changed from: "Configuration du Template d'État des Lieux"
- Changed to: "Configuration des Templates d'État des Lieux"
- Subtitle now mentions both entry and exit states

#### New Form Actions
1. **`update_template`**: Saves entry template (existing, with improved message)
2. **`update_template_sortie`**: Saves exit template (NEW)

#### UI Sections
The page now has three main sections:

1. **Signature Configuration** (existing, unchanged)
   - Upload company signature
   - Enable/disable signature

2. **Entry Template Editor** (NEW visual distinction)
   - Green icon: `bi-box-arrow-in-right`
   - TinyMCE editor: `#template_html`
   - Variables panel with all available placeholders
   - Actions:
     - Save (green button)
     - Preview
     - Reset to default

3. **Exit Template Editor** (NEW)
   - Red icon: `bi-box-arrow-right`
   - TinyMCE editor: `#template_html_sortie`
   - Same variables panel
   - Actions:
     - Save (red button)
     - Preview
     - Reset to default

#### Preview Cards
- **Entry Preview**: `#preview-card`
- **Exit Preview**: `#preview-card-sortie`

### 3. PDF Generation (`pdf/generate-etat-lieux.php`)

#### Template Selection Logic
```php
// Use different template for exit state if available
if ($type === 'sortie') {
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_sortie_template_html'");
    $stmt->execute();
    $templateHtml = $stmt->fetchColumn();
    
    // If no exit template, fall back to entry template
    if (empty($templateHtml)) {
        error_log("No exit template found, falling back to entry template");
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_template_html'");
        $stmt->execute();
        $templateHtml = $stmt->fetchColumn();
    }
} else {
    // Use entry template
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_template_html'");
    $stmt->execute();
    $templateHtml = $stmt->fetchColumn();
}

// If still no template, use default
if (empty($templateHtml)) {
    $templateHtml = getDefaultEtatLieuxTemplate();
}
```

## Template Variables
Both templates support the same variables:

### Basic Information
- `{{reference}}` - Reference number
- `{{type}}` - Type (entree/sortie)
- `{{type_label}}` - Type label (D'ENTRÉE/DE SORTIE)
- `{{date_etat}}` - Inspection date
- `{{date_signature}}` - Signature date

### Property Information
- `{{adresse}}` - Address
- `{{appartement}}` - Apartment number
- `{{type_logement}}` - Property type
- `{{surface}}` - Surface area

### Parties
- `{{bailleur_nom}}` - Landlord name
- `{{bailleur_representant}}` - Landlord representative
- `{{locataires_info}}` - Tenants information

### Meters & Keys
- `{{compteur_electricite}}` - Electricity meter
- `{{compteur_eau_froide}}` - Cold water meter
- `{{cles_appartement}}` - Apartment keys
- `{{cles_boite_lettres}}` - Mailbox keys
- `{{cles_autre}}` - Other keys
- `{{cles_total}}` - Total keys

### Property Description
- `{{piece_principale}}` - Main room description
- `{{coin_cuisine}}` - Kitchen description
- `{{salle_eau_wc}}` - Bathroom description
- `{{etat_general}}` - General condition
- `{{observations}}` - Additional observations

### Signatures
- `{{lieu_signature}}` - Signature location
- `{{signatures_table}}` - Signatures table
- `{{signature_agence}}` - Agency signature

## Backward Compatibility
✅ **Fully backward compatible**
- If no exit template exists, the system falls back to the entry template
- Existing installations continue to work without any changes
- The default template is used if neither entry nor exit templates are configured

## User Workflow

### Initial Setup
1. Admin navigates to: `/admin-v2/etat-lieux-configuration.php`
2. Both entry and exit templates are initialized with the default template
3. Admin can customize each template independently

### Customizing Entry Template
1. Scroll to "Template État des Lieux d'Entrée" section (green icon)
2. Edit HTML in TinyMCE editor
3. Use variable tags by clicking them (auto-copy)
4. Click "Enregistrer le Template d'Entrée" (green button)
5. Optionally preview before saving

### Customizing Exit Template
1. Scroll to "Template État des Lieux de Sortie" section (red icon)
2. Edit HTML in TinyMCE editor
3. Use variable tags by clicking them (auto-copy)
4. Click "Enregistrer le Template de Sortie" (red button)
5. Optionally preview before saving

### PDF Generation
- **Entry inspection**: Uses `etat_lieux_template_html`
- **Exit inspection**: Uses `etat_lieux_sortie_template_html` (or falls back to entry template)

## Benefits
1. **Flexibility**: Different templates for different inspection types
2. **Visual Distinction**: Color-coded UI (green for entry, red for exit)
3. **Independent Control**: Edit each template separately
4. **Fallback Safety**: System works even if exit template is not configured
5. **Same Variables**: Consistency in variable usage across both templates

## Testing
To test the implementation:
1. Navigate to `/admin-v2/etat-lieux-configuration.php`
2. Verify both template editors are visible
3. Make changes to the exit template
4. Save and verify success message
5. Create an exit state inspection
6. Generate PDF and verify it uses the exit template

## Files Modified
1. `/admin-v2/etat-lieux-configuration.php` - Main configuration page
2. `/pdf/generate-etat-lieux.php` - PDF generation logic

## Files Created
1. `/test-exit-template-config.php` - Test script for validation
