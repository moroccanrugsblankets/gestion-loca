# Candidature Detail Page - Complete Form Information

## Summary of Changes

This document lists all the form fields now displayed on the `admin-v2/candidature-detail.php` page. All information from the candidature form is now being retrieved and displayed.

## 📋 Complete List of Fields Displayed

### 🏢 Logement Information (New Section)
- **Référence**: Reference number of the property
- **Adresse**: Full address of the property
- **Type**: Property type (e.g., Studio, T2, T3)
- **Loyer**: Rent amount with charges

### 👤 Personal Information
- **Nom complet**: Full name (first name + last name)
- **Email**: Email address (clickable mailto link)
- **Téléphone**: Phone number (clickable tel link)

### 💼 Professional Situation
- **Statut professionnel**: Employment status (CDI, CDD, Indépendant, Autre)
- **Période d'essai**: Trial period status

### 💰 Financial Situation
- **Revenus nets mensuels**: Monthly net income
- **Type de revenus**: Type of income

### 🏠 Housing Situation
- **Situation actuelle**: Current housing situation
- **Préavis donné**: Notice given status
- **Nombre d'occupants**: Number of occupants
  - **Additional detail**: When "Autre" is selected, displays the specific number in parentheses (NEW)

### 🛡️ Guarantees
- **Garantie Visale**: Visale guarantee status

### 📎 Documents
- All uploaded documents grouped by type:
  - Pièce d'identité ou passeport
  - 3 derniers bulletins de salaire
  - Contrat de travail
  - Dernier avis d'imposition
  - 3 dernières quittances de loyer

### 🔄 Workflow/Response Information (New Section)
- **Réponse automatique**: Automatic response status (accepté/refusé/en_attente)
- **Date de soumission**: Submission date
- **Date réponse auto**: Automatic response date
- **Date réponse envoyée**: Response sent date
- **Motif de refus**: Refusal reason (if applicable)

### 📅 Visit Information (New Section)
Displayed only when visit data exists:
- **Date de visite**: Visit date and time
- **Visite confirmée**: Visit confirmation status (Yes/No badge)
- **Notes de visite**: Visit notes

### ⚙️ Administrative Information (New Section)
- **Référence unique**: Unique reference code (displayed in monospace font)
- **Priorité**: Priority level (0-10, color-coded badge)
- **Notes admin**: Administrative notes (if any)

### 📊 Action History
- Timeline of all actions performed on the candidature
- Includes date, action type, and details

## 🔧 Technical Improvements Made

### 1. Database Query Enhancement
- Added LEFT JOIN to fetch property information from `logements` table
- Now retrieves: reference, address, type, rent, and charges

### 2. Status Handling Fix
- Added mapping function to convert database enum values (e.g., `en_cours`) to display values (e.g., "En cours")
- Fixed status comparison in Quick Actions section
- Fixed status selection in modal to use correct database enum values

### 3. Display Reference Fix
- Changed header to display `reference_unique` instead of non-existent `reference` field

### 4. Conditional Display
- Housing information section: Only shown if logement_id is set
- Visit information section: Only shown if visit data exists
- Admin notes: Only shown if notes exist
- All date fields: Only shown if dates are set

## 📝 Fields from Database Schema

The following table shows all fields from the `candidatures` table and their display status:

| Field Name | Database Type | Displayed | Location |
|-----------|--------------|-----------|----------|
| id | INT | ✓ | URL parameter |
| reference_unique | VARCHAR | ✓ | Header + Admin section |
| response_token | VARCHAR | ❌ | Internal use only |
| logement_id | INT | ✓ | Logement section (via JOIN) |
| nom | VARCHAR | ✓ | Personal info |
| prenom | VARCHAR | ✓ | Personal info |
| email | VARCHAR | ✓ | Personal info |
| telephone | VARCHAR | ✓ | Personal info |
| statut_professionnel | ENUM | ✓ | Professional section |
| periode_essai | ENUM | ✓ | Professional section |
| revenus_mensuels | ENUM | ✓ | Financial section |
| type_revenus | ENUM | ✓ | Financial section |
| situation_logement | ENUM | ✓ | Housing section |
| preavis_donne | ENUM | ✓ | Housing section |
| nb_occupants | ENUM | ✓ | Housing section |
| nb_occupants_autre | VARCHAR | ✓ | Housing section (conditional) |
| garantie_visale | ENUM | ✓ | Guarantees section |
| statut | ENUM | ✓ | Header badge |
| date_soumission | TIMESTAMP | ✓ | Workflow section |
| date_reponse_auto | TIMESTAMP | ✓ | Workflow section (conditional) |
| date_reponse_envoyee | TIMESTAMP | ✓ | Workflow section (conditional) |
| reponse_automatique | ENUM | ✓ | Workflow section |
| motif_refus | TEXT | ✓ | Workflow section (conditional) |
| visite_confirmee | BOOLEAN | ✓ | Visit section |
| date_visite | DATETIME | ✓ | Visit section (conditional) |
| notes_visite | TEXT | ✓ | Visit section (conditional) |
| priorite | INT | ✓ | Admin section |
| notes_admin | TEXT | ✓ | Admin section (conditional) |
| created_at | TIMESTAMP | ✓ | Header |
| updated_at | TIMESTAMP | ❌ | Not displayed (tracked internally) |

## ✅ Result

**All form information from the candidatures table is now retrieved and displayed on the page.**

The page now provides a complete view of:
1. All applicant information
2. All property/logement information
3. All workflow and processing information
4. All visit details
5. All administrative metadata
6. All uploaded documents
7. Complete action history

This ensures that administrators have access to all the information needed to evaluate and process rental applications.
