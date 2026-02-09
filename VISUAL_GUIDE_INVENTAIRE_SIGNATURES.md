# Visual Guide: Inventaire Signature Feature

## Before and After Comparison

### BEFORE (Original edit-inventaire.php)
```
┌─────────────────────────────────────────────────┐
│ Header: Edit Inventaire                        │
│ - Reference, Type, Address                     │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Equipment Section 1: Électroménager            │
│ - Refrigerator: [qty] [état] [observations]    │
│ - Oven:        [qty] [état] [observations]    │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Equipment Section 2: Mobilier                  │
│ - Bed:         [qty] [état] [observations]    │
│ - Table:       [qty] [état] [observations]    │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Observations générales                          │
│ [Text area for general comments]                │
└─────────────────────────────────────────────────┘

[Annuler] [Enregistrer]
```

### AFTER (With Signature Functionality)
```
┌─────────────────────────────────────────────────┐
│ Header: Edit Inventaire                        │
│ - Reference, Type, Address                     │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Equipment Section 1: Électroménager            │
│ - Refrigerator: [qty] [état] [observations]    │
│ - Oven:        [qty] [état] [observations]    │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Equipment Section 2: Mobilier                  │
│ - Bed:         [qty] [état] [observations]    │
│ - Table:       [qty] [état] [observations]    │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Observations générales                          │
│ [Text area for general comments]                │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ ✍️ Signatures des locataires                    │
│                                                 │
│ ℹ️ Info: Les locataires peuvent signer         │
│    ci-dessous pour confirmer l'inventaire.      │
│                                                 │
│ Lieu de signature: [Paris______________]        │
│                                                 │
│ Signature locataire 1 - Jean Dupont            │
│ ┌─────────────────────────────────────┐        │
│ │ ✓ Signé le 15/01/2024 à 14:30       │        │
│ └─────────────────────────────────────┘        │
│ [Signature image preview]                       │
│                                                 │
│ Veuillez signer dans le cadre ci-dessous:      │
│ ┌──────────────────────────────┐               │
│ │                              │               │
│ │   [Signature Canvas]         │               │
│ │                              │               │
│ └──────────────────────────────┘               │
│ [Effacer]                                       │
│                                                 │
│ ☑ Certifié exact                                │
│                                                 │
│ Signature locataire 2 - Marie Martin           │
│ (Similar structure as above)                    │
│                                                 │
└─────────────────────────────────────────────────┘

[Annuler] [Enregistrer]
```

## Component Breakdown

### 1. Signature Section Header
```
┌─────────────────────────────────────────────────┐
│ ✍️ Signatures des locataires                    │
├─────────────────────────────────────────────────┤
│ Style: section-title                            │
│ - Font-size: 1.2rem                             │
│ - Border-bottom: 2px solid #e9ecef             │
│ - Icon: bi-pen (Bootstrap Icons)                │
└─────────────────────────────────────────────────┘
```

### 2. Information Alert
```
┌─────────────────────────────────────────────────┐
│ ℹ️ Signatures                                    │
│ Les locataires peuvent signer ci-dessous pour   │
│ confirmer l'inventaire.                         │
├─────────────────────────────────────────────────┤
│ Style: alert alert-info                         │
│ - Background: Light blue                        │
│ - Icon: bi-info-circle                          │
└─────────────────────────────────────────────────┘
```

### 3. Common Signature Location Field
```
┌─────────────────────────────────────────────────┐
│ Lieu de signature                               │
│ [Paris____________________________________]     │
├─────────────────────────────────────────────────┤
│ Field: lieu_signature                           │
│ - Saves to inventaires.lieu_signature           │
│ - Common for all tenant signatures              │
│ - Placeholder: "Ex: Paris"                      │
└─────────────────────────────────────────────────┘
```

### 4. Tenant Signature Block (Repeats per Tenant)
```
┌─────────────────────────────────────────────────┐
│ Signature locataire 1 - Jean Dupont            │
├─────────────────────────────────────────────────┤
│ Status Alert (if signed):                       │
│ ┌───────────────────────────────────────────┐  │
│ │ ✓ Signé le 15/01/2024 à 14:30             │  │
│ └───────────────────────────────────────────┘  │
│                                                 │
│ Existing Signature Preview:                     │
│ ┌──────────────────┐                            │
│ │ [Signature IMG]  │ (max 200x80px)             │
│ └──────────────────┘                            │
│                                                 │
│ Veuillez signer dans le cadre ci-dessous:      │
│ ┌──────────────────────────────────────────┐   │
│ │                                          │   │
│ │      ████  ████  ████                    │   │
│ │     █      █  █  █   █                   │   │
│ │     ████   ████  ████                    │   │
│ │                                          │   │
│ └──────────────────────────────────────────┘   │
│                                                 │
│ [🗑️ Effacer]                                    │
│                                                 │
│ ☑ Certifié exact                                │
└─────────────────────────────────────────────────┘
```

### 5. Signature Canvas Details
```
Canvas Element:
┌────────────────────────────────────────────────┐
│ ID: tenantCanvas_[TENANT_ID]                   │
│ Width: 300px                                    │
│ Height: 150px                                   │
│ Border: 2px solid #000000                      │
│ Background: white                               │
│ Cursor: crosshair                               │
│                                                │
│ Drawing Properties:                             │
│ - strokeStyle: #000000 (black)                  │
│ - lineWidth: 2                                  │
│ - lineCap: round                                │
│ - lineJoin: round                               │
│                                                │
│ Event Listeners:                                │
│ - mousedown, mousemove, mouseup                 │
│ - touchstart, touchmove, touchend               │
└────────────────────────────────────────────────┘
```

## User Interaction Flow

### Drawing a Signature
```
1. User hovers over canvas
   └─> Cursor changes to crosshair
   
2. User clicks/touches and drags
   └─> Black line appears following mouse/finger
   
3. User releases mouse/finger
   └─> Drawing stops
   └─> Signature automatically saved to hidden field
   └─> Data format: data:image/jpeg;base64,[...]
   
4. User clicks "Effacer"
   └─> Canvas cleared
   └─> Hidden field emptied
   └─> User can redraw
```

### Saving the Form
```
1. User clicks [Enregistrer]
   └─> Form submitted via POST
   
2. Backend Processing:
   ├─> Begin transaction
   ├─> Update equipment data
   ├─> Update observations_generales
   ├─> Update lieu_signature
   └─> For each tenant:
       ├─> Update certifie_exact status
       └─> If signature provided:
           ├─> Validate format
           ├─> Call updateInventaireTenantSignature()
           ├─> Save as physical file
           └─> Store file path in database
   
3. Success:
   └─> Commit transaction
   └─> Redirect with success message
   
4. Error:
   └─> Rollback transaction
   └─> Display error message
```

## Database Flow

### Before Signature Implementation
```
inventaires table:
┌─────────────────────────────────────────────────┐
│ id | contrat_id | logement_id | equipements_data│
│ 1  | 10         | 5           | {...}           │
└─────────────────────────────────────────────────┘

inventaire_locataires table:
┌─────────────────────────────────────────────────┐
│ id | inventaire_id | nom    | prenom | email    │
│ 1  | 1             | Dupont | Jean   | j@e.com  │
│ 2  | 1             | Martin | Marie  | m@e.com  │
└─────────────────────────────────────────────────┘
```

### After Signature Implementation
```
inventaires table:
┌──────────────────────────────────────────────────────────┐
│ id | ... | lieu_signature | updated_at             │
│ 1  | ... | Paris          | 2024-01-15 14:30:00   │
└──────────────────────────────────────────────────────────┘

inventaire_locataires table:
┌─────────────────────────────────────────────────────────────┐
│ id | inventaire_id | nom    | signature             | date_signature      | certifie_exact │
│ 1  | 1             | Dupont | uploads/signatures/.. | 2024-01-15 14:30:00| 1              │
│ 2  | 1             | Martin | uploads/signatures/.. | 2024-01-15 14:31:00| 1              │
└─────────────────────────────────────────────────────────────┘

File System:
uploads/
└── signatures/
    ├── inventaire_tenant_1_1_1705329000.jpg
    └── inventaire_tenant_1_2_1705329060.jpg
```

## Technical Architecture

### Frontend (Browser)
```
┌──────────────────────────────────────────────┐
│ HTML Form                                    │
│ ┌──────────────────────────────────────────┐│
│ │ Equipment inputs                         ││
│ │ Observations textarea                    ││
│ │ Lieu de signature input                  ││
│ │ ┌──────────────────────────────────────┐ ││
│ │ │ Signature Canvas (per tenant)        │ ││
│ │ │ - Mouse/Touch event handlers         │ ││
│ │ │ - Drawing context (2D)               │ ││
│ │ └──────────────────────────────────────┘ ││
│ │ Hidden signature field (base64)          ││
│ │ Certifié exact checkbox                  ││
│ └──────────────────────────────────────────┘│
└──────────────────────────────────────────────┘
           │
           │ POST Request
           ▼
┌──────────────────────────────────────────────┐
│ Backend (PHP)                                │
│ ┌──────────────────────────────────────────┐│
│ │ Form Handler                             ││
│ │ ├─> Start Transaction                    ││
│ │ ├─> Update inventaires table             ││
│ │ └─> Process each tenant:                 ││
│ │     ├─> Update certifie_exact            ││
│ │     └─> Call updateInventaireTenant...() ││
│ │         ├─> Validate signature           ││
│ │         ├─> Decode base64                ││
│ │         ├─> Save to file                 ││
│ │         └─> Update database              ││
│ └──────────────────────────────────────────┘│
└──────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────┐
│ Storage                                      │
│ ┌──────────────────────────────────────────┐│
│ │ Database (MySQL)                         ││
│ │ - inventaires.lieu_signature             ││
│ │ - inventaire_locataires.signature        ││
│ │ - inventaire_locataires.date_signature   ││
│ │ - inventaire_locataires.certifie_exact   ││
│ └──────────────────────────────────────────┘│
│ ┌──────────────────────────────────────────┐│
│ │ File System                              ││
│ │ uploads/signatures/*.jpg                 ││
│ └──────────────────────────────────────────┘│
└──────────────────────────────────────────────┘
```

## Code Organization

### File Structure
```
contrat-de-bail/
├── admin-v2/
│   └── edit-inventaire.php
│       ├── [Lines 1-9]     PHP Includes
│       ├── [Lines 11-35]   Data Loading
│       ├── [Lines 37-117]  Form Submission Handler ⭐ NEW
│       ├── [Lines 119-173] Tenant Data Fetching ⭐ NEW
│       ├── [Lines 175-247] HTML Head & CSS ⭐ ENHANCED
│       ├── [Lines 276-329] Equipment Sections
│       ├── [Lines 331-335] Observations
│       ├── [Lines 337-437] Signature Section ⭐ NEW
│       ├── [Lines 439-448] Form Actions
│       └── [Lines 450-569] JavaScript ⭐ NEW
│
└── includes/
    └── functions.php
        └── updateInventaireTenantSignature() (lines 351-416)
```

### Key Variables
```php
// Main data
$inventaire_id         // Inventaire ID from URL
$inventaire           // Full inventaire record
$equipements_data     // JSON decoded equipment list
$existing_tenants     // Array of tenant records

// Per-tenant display data
$tenant['id']                  // inventaire_locataires.id
$tenant['nom']                 // Last name
$tenant['prenom']              // First name
$tenant['signature_data']      // File path or data URL
$tenant['signature_timestamp'] // When signed
$tenant['certifie_exact']      // Boolean flag
```

## Styling Reference

### CSS Classes Applied
```css
/* Section Headers */
.section-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e9ecef;
}

/* Signature Subsections */
.section-subtitle {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

/* Signature Canvas Container */
.signature-container {
    border: 2px solid #000000;
    border-radius: 4px;
    display: inline-block;
    background: white;
    margin-bottom: 10px;
}

.signature-container canvas {
    display: block;
    cursor: crosshair;
}

/* From Bootstrap */
.form-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}
```

## API Reference

### JavaScript Functions
```javascript
// Initialize signature canvas for a tenant
initTenantSignature(id: number)
  → Sets up canvas drawing context
  → Attaches mouse and touch event listeners
  → Parameters: tenant ID from database

// Save current canvas to hidden field
saveTenantSignature(id: number)
  → Converts canvas to JPEG with white background
  → Stores base64 data URL in hidden input
  → Parameters: tenant ID from database

// Clear canvas and reset hidden field
clearTenantSignature(id: number)
  → Clears canvas content
  → Empties hidden input value
  → Parameters: tenant ID from database
```

### PHP Functions
```php
// Save tenant signature to file system
updateInventaireTenantSignature(
    int $inventaireLocataireId,
    string $signatureData,
    int $inventaireId
): bool
  → Validates signature format and size
  → Saves to uploads/signatures/
  → Updates database with file path
  → Returns: success boolean
```

---

**Visual Guide Version**: 1.0  
**Last Updated**: 2024  
**Companion to**: IMPLEMENTATION_INVENTAIRE_SIGNATURES.md
