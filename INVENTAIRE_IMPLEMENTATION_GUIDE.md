# IMPLEMENTATION GUIDE: Inventaire Feature

## Overview
This document provides a complete guide for deploying and using the new Inventaire (Inventory) feature in the rental contract management system.

## Installation Instructions

### 1. Database Migration
Run the migration to create the required database tables:

```bash
cd /home/runner/work/contrat-de-bail/contrat-de-bail
php migrations/034_create_inventaire_tables.php
```

This will create:
- `inventaire_equipements` - Equipment definitions per housing unit
- `inventaires` - Main inventory records (entry/exit)
- `inventaire_locataires` - Tenant signatures
- `inventaire_photos` - Photo attachments (structure ready)
- Template parameters in `parametres` table

### 2. File Permissions
Ensure the uploads directory has proper permissions:

```bash
chmod 755 uploads/inventaires
```

### 3. Verify Installation
1. Log into the admin panel
2. Check that "Inventaire" appears in the main menu
3. Navigate to Logements and verify the new "Définir l'inventaire" button (box icon)

## User Workflow

### Step 1: Define Standard Equipment
1. Go to **Logements** page
2. Click the green box icon (🗃️) next to any housing unit
3. Add equipment items by category:
   - Électroménager (Appliances)
   - Mobilier (Furniture)
   - Cuisine (Kitchen items)
   - Salle de bain (Bathroom)
   - Linge de maison (Linens)
   - Électronique (Electronics)
   - Autre (Other)
4. For each item, specify:
   - Name (required)
   - Description (optional)
   - Quantity (default: 1)
   - Estimated value in € (optional)
   - Display order (optional)

### Step 2: Create an Inventory
1. Go to **Inventaire** page (new menu item)
2. Click "Nouvel inventaire"
3. Select:
   - Housing unit (must have equipment defined)
   - Type (Entry or Exit)
   - Date
4. Click "Créer l'inventaire"

The system will:
- Find the active contract for the housing
- Load all tenants from the contract
- Create a draft inventory with all defined equipment
- For entry: pre-fill quantities as "present"
- For exit: leave quantities blank for manual entry

### Step 3: Complete the Inventory
1. Click "Modifier" (pencil icon) on the draft inventory
2. For each equipment item:
   - Verify quantity present
   - Set condition: Bon (Good), Moyen (Fair), Mauvais (Poor), Manquant (Missing)
   - Add observations if needed
3. Add general observations at the bottom
4. Click "Enregistrer"

### Step 4: Generate PDF
1. Click "Voir le PDF" (eye icon) to preview
2. Click "Télécharger" (download icon) to save
3. The PDF includes:
   - Inventory reference and date
   - Housing and tenant information
   - Equipment grouped by category
   - Quantities (expected vs present)
   - Condition and observations
   - Signature placeholders

### Step 5: Compare Entry vs Exit
1. When both entry and exit inventories exist
2. Click the comparison icon (⇄) to see side-by-side view
3. Differences are highlighted

## Configuration

### PDF Templates
1. Go to **Inventaire → Configuration**
2. Customize HTML templates for:
   - Entry inventories
   - Exit inventories
3. Leave blank to use default templates
4. Available variables:
   - `{{reference}}` - Inventory reference
   - `{{date}}` - Inventory date
   - `{{adresse}}` - Property address
   - `{{locataire_nom}}` - Tenant name(s)
   - `{{equipements}}` - Equipment table (auto-generated)
   - `{{comparaison}}` - Comparison table (exit only)

## Technical Details

### Database Schema

**inventaire_equipements**
- Links to `logements` (housing units)
- Stores standard equipment definitions
- Categories for organization
- Cascade delete when housing is deleted

**inventaires**
- Links to `contrats` and `logements`
- Type: 'entree' or 'sortie'
- Equipment data stored as JSON
- Status workflow: brouillon → finalise → envoye
- Cascade delete when contract is deleted

**inventaire_locataires**
- Stores tenant signatures per inventory
- Links to `inventaires`
- Support for multiple tenants per contract

**inventaire_photos**
- Reserved for future photo attachment feature
- Links to `inventaires` and specific equipment

### Security Features
✅ Input validation on all forms
✅ SQL injection prevention via prepared statements
✅ Date range validation (within 5 years)
✅ Contract validation (must be active)
✅ Equipment validation (must be defined)
✅ XSS protection via htmlspecialchars()
✅ Session-based authentication

### File Organization
```
admin-v2/
├── inventaires.php              # Main dashboard
├── create-inventaire.php        # Create new inventory
├── edit-inventaire.php          # Edit equipment checklist
├── view-inventaire.php          # Read-only view
├── compare-inventaire.php       # Side-by-side comparison
├── delete-inventaire.php        # Delete with cleanup
├── download-inventaire.php      # PDF export
├── manage-inventory-equipements.php  # Define equipment per housing
└── inventaire-configuration.php      # Template management

pdf/
└── generate-inventaire.php      # PDF generation engine

migrations/
└── 034_create_inventaire_tables.php  # Database setup
```

## Integration Points

### Menu System
- Added to `admin-v2/includes/menu.php`
- Icon: box-seam (Bootstrap Icons)
- Position: Between "États des lieux" and "Administrateurs"
- Submenu: Configuration page

### Logements Module
- New button in actions column
- Green color to distinguish from other actions
- Direct link to equipment management

### Architecture Consistency
Following the same patterns as État des lieux:
- ✅ Status workflow (draft/finalized/sent)
- ✅ JSON storage for flexible data
- ✅ TCPDF for PDF generation
- ✅ Template-based customization
- ✅ Cascade delete with cleanup
- ✅ Multi-tenant support
- ✅ Session-based state management

## Troubleshooting

### "No equipment defined" error
**Solution:** Go to Logements, click the box icon, add at least one equipment item

### "No active contract" error
**Solution:** Ensure the housing unit has a validated contract (statut = 'valide')

### PDF generation fails
**Solution:** Check that TCPDF vendor library is installed via Composer

### Photos not uploading (future feature)
**Note:** Photo upload functionality is prepared but not yet implemented
**Structure ready in:** `inventaire_photos` table and `uploads/inventaires/` directory

## Future Enhancements (Not Yet Implemented)
- [ ] Photo upload/attachment per equipment
- [ ] Email notification on finalization
- [ ] Financial calculations for missing/damaged items
- [ ] Equipment value depreciation tracking
- [ ] Bulk import of standard equipment sets
- [ ] Mobile-responsive signature capture
- [ ] PDF email delivery integration

## Support
For issues or questions:
1. Check application logs in `/tmp/` or configured log directory
2. Verify database migration completed successfully
3. Ensure file permissions are correct on uploads directory
4. Review this guide for workflow steps
