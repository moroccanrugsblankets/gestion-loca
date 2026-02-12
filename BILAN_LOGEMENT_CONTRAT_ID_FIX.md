# Fix: Bilan du Logement Now Uses Contract ID

## Problem
The `edit-bilan-logement.php` page was previously accessing bilan data using the état des lieux (exit inventory) ID parameter (`id=10`). This caused two issues:
1. No dynamic information was being retrieved from the inventaires table
2. The page was tied to a specific état des lieux rather than the contract

## Solution
Changed the page to use `contrat_id` parameter instead, allowing it to:
1. Access data from both `etats_lieux` AND `inventaires` tables
2. Automatically retrieve or create an état des lieux de sortie for the contract
3. Pre-populate bilan data from inventaires if no état des lieux exists yet

## Changes Made

### 1. Modified `admin-v2/edit-bilan-logement.php`
- **Parameter change**: Now accepts `contrat_id` instead of `id`
- **Data retrieval**: Queries contract, then finds associated état des lieux de sortie and inventaire de sortie
- **Auto-population**: If no état des lieux exists, data is populated from inventaire (missing/damaged equipment)
- **Form submission**: Creates état des lieux de sortie if it doesn't exist, otherwise updates existing one
- **JavaScript**: Added check to prevent file uploads when no état des lieux exists yet (user must save first)

### 2. Updated All Links
Updated all pages that link to `edit-bilan-logement.php` to pass `contrat_id` instead of `id`:
- `admin-v2/etats-lieux.php` - List of états des lieux
- `admin-v2/view-etat-lieux.php` - View single état des lieux
- `admin-v2/edit-etat-lieux.php` - Edit état des lieux
- `admin-v2/contrat-detail.php` - Added new "Bilan du Logement" section

### 3. Data Integration
The page now retrieves:
- **From contrats**: Contract reference, logement information
- **From etats_lieux**: Existing bilan data if available
- **From inventaires**: Equipment data to pre-populate bilan rows

## Usage

### Accessing Bilan du Logement
**Old way**: `edit-bilan-logement.php?id=10` (état des lieux ID)
**New way**: `edit-bilan-logement.php?contrat_id=5` (contract ID)

### Data Flow
1. User accesses page with contract ID
2. System finds or creates état des lieux de sortie for that contract
3. If no bilan data exists in état des lieux, system attempts to populate from inventaire
4. User can add/edit bilan rows
5. On save, data is stored in the état des lieux record

### File Uploads
- File uploads require an existing état des lieux record
- If user tries to upload before first save, they'll be prompted to save first
- After first save, état des lieux is created and files can be uploaded

## Benefits
1. **Unified access**: All contract-related data (états des lieux, inventaires, bilan) accessible from contract ID
2. **Data integration**: Bilan can now be populated from inventaires automatically
3. **Better UX**: Added bilan link directly in contract detail page
4. **Flexibility**: System creates état des lieux automatically if needed

## Testing
All PHP files pass syntax check:
- ✅ admin-v2/edit-bilan-logement.php
- ✅ admin-v2/contrat-detail.php
- ✅ admin-v2/view-etat-lieux.php
- ✅ admin-v2/edit-etat-lieux.php
- ✅ admin-v2/etats-lieux.php

## Database Impact
No database schema changes required. The existing structure already supports this:
- `etats_lieux.contrat_id` links to contracts
- `inventaires.contrat_id` links to contracts
- Both use `type` enum to distinguish entrée/sortie
