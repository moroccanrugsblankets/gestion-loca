# États des Lieux - Documentation Complète

## Vue d'ensemble

Le système d'états des lieux permet de créer, gérer et envoyer des documents d'état des lieux d'entrée et de sortie conformes aux exigences légales et aux besoins de My Invest Immobilier.

## Fonctionnalités

### 1. Création d'un nouvel état des lieux

**Page:** `admin-v2/etats-lieux.php`

- Sélection du type (entrée ou sortie)
- Sélection du contrat associé
- Définition de la date
- Redirection automatique vers le formulaire complet

### 2. Formulaire Complet

**Page:** `admin-v2/edit-etat-lieux.php`

Le formulaire comprend toutes les sections obligatoires :

#### Section 1 : Identification
- Date de l'état des lieux (obligatoire)
- Adresse du logement
- Nom du bailleur
- Nom complet du/des locataire(s) (obligatoire)
- Email du locataire (obligatoire) - pour l'envoi du PDF

#### Section 2 : Relevé des compteurs
- **Électricité**
  - Index relevé en kWh (obligatoire)
  - Photo du compteur (optionnel)
- **Eau froide**
  - Index relevé en m³ (obligatoire)
  - Photo du compteur (optionnel)

#### Section 3 : Remise/Restitution des clés
- Nombre de clés de l'appartement (obligatoire)
- Nombre de clés de la boîte aux lettres (obligatoire)
- Total calculé automatiquement
- Photo des clés (optionnel)
- **Pour sortie uniquement:** Conformité (conforme/non conforme)

#### Section 4 : Description du logement
- **Pièce principale**
  - État détaillé (obligatoire)
  - Photos (optionnel)
- **Coin cuisine**
  - État détaillé (obligatoire)
  - Photos (optionnel)
- **Salle d'eau et WC**
  - État détaillé (obligatoire)
  - Photos (optionnel)
- **État général**
  - Observations (obligatoire)
  - Photos (optionnel)
  - **Pour sortie uniquement:** 
    - Conformité à l'état d'entrée
    - Dégradations constatées (checkbox)
    - Détails des dégradations

#### Section 5 : Signatures
- Lieu de signature (obligatoire)
- Observations complémentaires

### 3. Gestion des photos

**Upload:** `admin-v2/upload-etat-lieux-photo.php`
**Delete:** `admin-v2/delete-etat-lieux-photo.php`

- Les photos sont optionnelles pour toutes les sections
- Formats acceptés: JPEG, PNG, GIF
- Taille maximale: 5MB par fichier
- Les photos sont stockées dans `uploads/etats_lieux/{id}/`
- **Important:** Les photos sont uniquement conservées dans le dossier interne et NE SONT PAS envoyées au locataire

### 4. Finalisation et envoi

**Page:** `admin-v2/finalize-etat-lieux.php`

Avant l'envoi, récapitulatif de :
- Type d'état des lieux
- Référence unique
- Date
- Adresse
- Informations du locataire

**Envoi automatique par email :**
- Destinataire principal : Email du locataire
- Copie : gestion@myinvest-immobilier.com
- Pièce jointe : PDF de l'état des lieux
- Les photos restent dans le dossier interne uniquement

### 5. Visualisation

**Page:** `admin-v2/view-etat-lieux.php`

Affichage en lecture seule de tous les détails de l'état des lieux.

### 6. Suppression

**Page:** `admin-v2/delete-etat-lieux.php`

- Confirmation requise
- Suppression en cascade :
  - L'état des lieux
  - Les photos associées (base de données et fichiers)
  - Les données des locataires liées
- Action irréversible

### 7. Téléchargement PDF

**Page:** `admin-v2/download-etat-lieux.php`

Génération et téléchargement du PDF à la demande.

## Structure de la base de données

### Table principale : `etats_lieux`

Champs principaux :
- `id` : Identifiant unique
- `contrat_id` : Référence au contrat
- `type` : 'entree' ou 'sortie'
- `reference_unique` : Référence unique (ex: EDL-E-20260204-1234)
- `date_etat` : Date de l'état des lieux
- `statut` : 'brouillon', 'finalise', ou 'envoye'

Sections de données :
- Identification : `adresse`, `appartement`, `bailleur_nom`, `locataire_nom_complet`, `locataire_email`
- Compteurs : `compteur_electricite`, `compteur_eau_froide`
- Clés : `cles_appartement`, `cles_boite_lettres`, `cles_total`, `cles_conformite`
- Description : `piece_principale`, `coin_cuisine`, `salle_eau_wc`, `etat_general`
- Photos : JSON fields pour stockage des références
- Conformité (sortie) : `etat_general_conforme`, `degradations_constatees`, `degradations_details`
- Email : `email_envoye`, `date_envoi_email`

### Tables associées

**`etat_lieux_locataires`**
- Lien entre état des lieux et locataires
- Copie des informations du locataire au moment de l'état des lieux
- Gestion des signatures

**`etat_lieux_photos`**
- Stockage des informations des photos
- Catégories : compteur_electricite, compteur_eau, cles, piece_principale, cuisine, salle_eau, autre
- Chemin vers le fichier physique

## Workflow complet

1. **Création** (`etats-lieux.php` → `create-etat-lieux.php`)
   - Sélection type et contrat
   - Création de l'enregistrement avec statut 'brouillon'
   - Redirection vers le formulaire d'édition

2. **Édition** (`edit-etat-lieux.php`)
   - Remplissage de tous les champs obligatoires
   - Upload optionnel des photos
   - Sauvegarde intermédiaire possible (brouillon)

3. **Finalisation** (`finalize-etat-lieux.php`)
   - Vérification des informations
   - Génération du PDF
   - Envoi par email
   - Changement de statut à 'envoye'

4. **Consultation** (`view-etat-lieux.php`)
   - Visualisation en lecture seule
   - Téléchargement du PDF
   - Modification possible (retour à l'édition)

5. **Suppression** (`delete-etat-lieux.php`)
   - Confirmation obligatoire
   - Suppression complète avec nettoyage des fichiers

## Migration de la base de données

Pour mettre à jour la base de données avec les nouveaux champs :

```bash
php migrations/027_enhance_etats_lieux_comprehensive.php
```

Cette migration ajoute :
- Champs JSON pour les détails des pièces
- Champs pour la conformité (sortie)
- Champs pour les informations du locataire
- Champs pour les dégradations

## Sécurité

- Authentification requise pour toutes les pages
- Validation des types de fichiers uploadés
- Limitation de la taille des fichiers (5MB)
- Protection contre les injections SQL via PDO
- Vérification des permissions sur les répertoires d'upload
- Suppression sécurisée des fichiers

## Notes importantes

⚠️ **Photos** : Les photos téléchargées ne sont PAS envoyées au locataire. Elles restent dans le dossier interne pour My Invest Immobilier uniquement.

✅ **Champs obligatoires** : Tous les champs marqués d'un astérisque (*) sont obligatoires pour finaliser l'état des lieux.

📧 **Email** : L'email est envoyé automatiquement à l'adresse du locataire avec une copie à gestion@myinvest-immobilier.com lors de la finalisation.

🔒 **Statuts** :
- `brouillon` : En cours de rédaction
- `finalise` : Complété mais pas encore envoyé
- `envoye` : Finalisé et envoyé par email

## Support

Pour toute question ou problème, contacter l'administrateur système.
