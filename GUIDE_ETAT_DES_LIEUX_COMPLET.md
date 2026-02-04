# Guide complet - Module État des lieux d'entrée et de sortie

## Vue d'ensemble

Le module **État des lieux d'entrée et de sortie** permet de générer des documents PDF complets et structurés pour documenter l'état d'un logement lors de l'entrée et de la sortie des locataires.

### Fonctionnalités principales

✅ **Génération de PDF structuré** pour entrée ET sortie  
✅ **Tous les champs obligatoires** sauf les photos  
✅ **Photos optionnelles** stockées uniquement pour My Invest (non envoyées au locataire)  
✅ **Envoi automatique par email** au locataire + copie à gestion@myinvest-immobilier.com  
✅ **Interface web complète** pour saisir toutes les données  

---

## 1. Structure du module

### Fichiers principaux

```
admin-v2/
├── etats-lieux.php              # Liste de tous les états des lieux
├── create-etat-lieux.php        # Création d'un nouvel état des lieux
├── edit-etat-lieux.php          # Formulaire d'édition complet ⭐
├── view-etat-lieux.php          # Visualisation d'un état des lieux
├── finalize-etat-lieux.php      # Finalisation et envoi par email ⭐
├── download-etat-lieux.php      # Téléchargement du PDF
├── delete-etat-lieux.php        # Suppression
├── upload-etat-lieux-photo.php  # Upload de photos (optionnel)
└── delete-etat-lieux-photo.php  # Suppression de photos

pdf/
└── generate-etat-lieux.php      # Génération du PDF ⭐

migrations/
├── 026_fix_etats_lieux_schema.php      # Création/mise à jour schéma DB
└── 027_enhance_etats_lieux_comprehensive.php
```

### Tables de base de données

**etats_lieux** - Table principale
```sql
- id, contrat_id, type ('entree'/'sortie')
- reference_unique
- date_etat, adresse, appartement
- bailleur_nom, bailleur_representant
- locataire_nom_complet, locataire_email
- compteur_electricite, compteur_eau_froide
- cles_appartement, cles_boite_lettres, cles_total
- cles_conformite, cles_observations
- piece_principale, coin_cuisine, salle_eau_wc, etat_general
- observations (observations complémentaires)
- etat_general_conforme, degradations_constatees, degradations_details
- depot_garantie_status, depot_garantie_montant_retenu, depot_garantie_motif_retenue
- lieu_signature, date_signature, bailleur_signature
- statut ('brouillon', 'finalise', 'envoye')
- email_envoye, date_envoi_email
```

**etat_lieux_locataires** - Signatures des locataires
```sql
- id, etat_lieux_id, locataire_id
- nom, prenom, email
- signature_data, signature_timestamp, signature_ip
```

**etat_lieux_photos** - Photos (usage interne uniquement)
```sql
- id, etat_lieux_id, categorie
- nom_fichier, chemin_fichier
- description, ordre
```

---

## 2. Spécifications détaillées

### 2.1 État des lieux d'ENTRÉE

#### Sections du document

1. **IDENTIFICATION**
   - ✅ Date de l'état des lieux (obligatoire)
   - ✅ Adresse du logement (automatique depuis contrat)
   - ✅ Bailleur : MY INVEST IMMOBILIER (automatique)
   - ✅ Locataire(s) : nom complet (obligatoire)
   - ✅ Email du locataire (obligatoire)

2. **RELEVÉ DES COMPTEURS**
   - ✅ Électricité : index relevé (obligatoire)
   - 📷 Photo du compteur électrique (optionnel)
   - ✅ Eau froide : index relevé (obligatoire)
   - 📷 Photo du compteur eau (optionnel)

3. **REMISE DES CLÉS**
   - ✅ Nombre de clés appartement (obligatoire)
   - ✅ Nombre de clés boîte aux lettres (obligatoire)
   - ✅ Total des clés (calculé automatiquement)
   - 📷 Photo des clés (optionnel)

4. **DESCRIPTION DU LOGEMENT**
   - ✅ Pièce principale : état détaillé (obligatoire)
   - 📷 Photos de la pièce principale (optionnel)
   - ✅ Coin cuisine : état détaillé (obligatoire)
   - 📷 Photos du coin cuisine (optionnel)
   - ✅ Salle d'eau / WC : état détaillé (obligatoire)
   - 📷 Photos de la salle d'eau (optionnel)
   - ✅ État général du logement (obligatoire)
   - 📷 Photos de l'état général (optionnel)

5. **SIGNATURES**
   - ✅ Lieu de signature (obligatoire)
   - Observations complémentaires (optionnel)
   - Signature bailleur (automatique depuis paramètres)
   - Signature locataire(s) (si disponible)

#### Textes par défaut (entrée)

```
Pièce principale:
• Revêtement de sol : parquet très bon état d'usage
• Murs : peintures très bon état
• Plafond : peintures très bon état
• Installations électriques et plomberie : fonctionnelles

Coin cuisine:
• Revêtement de sol : parquet très bon état d'usage
• Murs : peintures très bon état
• Plafond : peintures très bon état
• Installations électriques et plomberie : fonctionnelles

Salle d'eau et WC:
• Revêtement de sol : carrelage très bon état d'usage
• Faïence : très bon état
• Plafond : peintures très bon état
• Installations électriques et plomberie : fonctionnelles

État général:
Le logement a fait l'objet d'une remise en état générale avant l'entrée dans les lieux.
Il est propre, entretenu et ne présente aucune dégradation apparente au jour de l'état des lieux.
Aucune anomalie constatée.
```

### 2.2 État des lieux de SORTIE

Toutes les sections de l'entrée, PLUS :

3. **RESTITUTION DES CLÉS** (au lieu de "Remise")
   - ✅ Conformité : ☑ Conforme / ☑ Non conforme (sélection)
   - Observations sur les clés (si non conforme)

4. **DESCRIPTION DU LOGEMENT**
   - ✅ Conformité à l'état d'entrée : ☑ Conforme / ☑ Non conforme
   - ✅ Dégradations constatées : case à cocher
   - Détails des dégradations (si coché)

5. **CONCLUSION - DÉPÔT DE GARANTIE** ⭐ NOUVEAU
   - ✅ Décision (obligatoire, une seule option) :
     * ☑ Restitution totale (aucune dégradation imputable)
     * ☑ Restitution partielle (dégradations mineures)
     * ☑ Retenue totale (dégradations importantes)
   - Montant retenu en € (si partielle ou totale)
   - Justificatif / Motif de la retenue (si partielle ou totale)

6. **SIGNATURES**
   - (identique à l'entrée)

---

## 3. Utilisation

### 3.1 Créer un état des lieux

1. Aller dans **États des lieux** (menu admin)
2. Cliquer sur **"Nouvel état des lieux"**
3. Sélectionner :
   - Type : Entrée ou Sortie
   - Contrat associé
   - Date de l'état des lieux
4. Cliquer sur **"Créer"**

### 3.2 Remplir le formulaire

1. Le système ouvre automatiquement le formulaire d'édition
2. **Remplir TOUS les champs obligatoires** (marqués d'un *)
3. Ajouter des photos si nécessaire (optionnel)
4. Pour un état de **sortie**, remplir également :
   - Conformité des clés
   - Conformité générale
   - Dégradations éventuelles
   - **Décision dépôt de garantie**
5. Choisir :
   - **"Enregistrer le brouillon"** : sauvegarde sans envoyer
   - **"Finaliser et envoyer"** : génère le PDF et l'envoie par email

### 3.3 Finalisation et envoi

Lorsque vous cliquez sur **"Finaliser et envoyer"** :

1. ✅ Le PDF est généré automatiquement
2. ✅ Le PDF est sauvegardé dans `/pdf/etat_des_lieux/`
3. ✅ Un email est envoyé au locataire avec le PDF en pièce jointe
4. ✅ Une copie est envoyée à `gestion@myinvest-immobilier.com`
5. ✅ Le statut passe à "Envoyé"

### 3.4 Photos - Usage interne uniquement

**IMPORTANT** : Les photos téléchargées sont :
- ✅ Stockées dans la base de données (table `etat_lieux_photos`)
- ✅ Visibles dans l'interface My Invest
- ❌ **NON incluses dans le PDF envoyé au locataire**
- ✅ Disponibles pour référence interne My Invest uniquement

---

## 4. Génération du PDF

### 4.1 Fonction principale

```php
generateEtatDesLieuxPDF($contratId, $type)
```

**Paramètres** :
- `$contratId` : ID du contrat (int)
- `$type` : 'entree' ou 'sortie' (string)

**Retour** :
- Chemin du fichier PDF généré (string)
- `false` en cas d'erreur

### 4.2 Structure du PDF

Le PDF généré contient :
- ✅ Titre centré et souligné
- ✅ Sections numérotées et hiérarchisées (h1, h2, h3)
- ✅ Tableaux pour compteurs et signatures
- ✅ Mise en page claire et professionnelle
- ✅ Format A4, marges 15mm
- ✅ Police Arial 10pt, line-height 1.5

### 4.3 Emplacement des PDFs

```
/pdf/etat_des_lieux/
└── etat_lieux_{type}_{reference_contrat}_{date}.pdf

Exemple:
etat_lieux_entree_CONT-2024-001_20240201.pdf
etat_lieux_sortie_CONT-2024-001_20240615.pdf
```

---

## 5. Envoi d'email

### 5.1 Configuration SMTP

Le système utilise PHPMailer avec la configuration définie dans `includes/config.php` :

```php
$config['SMTP_HOST']     // smtp.gmail.com
$config['SMTP_PORT']     // 587
$config['SMTP_SECURE']   // 'tls'
$config['SMTP_USERNAME'] // contact@myinvest-immobilier.com
$config['SMTP_PASSWORD'] // ⚠️ À configurer dans config.local.php
```

### 5.2 Template d'email

**Sujet** : État des lieux {d'entrée/de sortie} - {adresse}

**Corps** :
```
Bonjour,

Veuillez trouver ci-joint l'état des lieux {d'entrée/de sortie} pour le logement situé au :
{adresse}

Date de l'état des lieux : {date}

Ce document est à conserver précieusement.

Cordialement,
SCI My Invest Immobilier
Représentée par Maxime ALEXANDRE
```

**Pièce jointe** : PDF de l'état des lieux

### 5.3 Destinataires

- **TO** : Email du locataire (saisi dans le formulaire)
- **CC** : gestion@myinvest-immobilier.com

---

## 6. Validation des champs

### 6.1 Champs obligatoires (requis côté client ET serveur)

**Pour ENTRÉE et SORTIE** :
- ✅ Date de l'état des lieux
- ✅ Locataire(s) : nom complet
- ✅ Email du locataire
- ✅ Compteur électricité (index)
- ✅ Compteur eau froide (index)
- ✅ Nombre de clés appartement
- ✅ Nombre de clés boîte aux lettres
- ✅ Description pièce principale
- ✅ Description coin cuisine
- ✅ Description salle d'eau / WC
- ✅ État général du logement
- ✅ Lieu de signature

**Uniquement pour SORTIE** :
- ✅ Décision dépôt de garantie (sélection parmi les 3 options)

### 6.2 Champs optionnels

- Photos (toutes catégories)
- Observations complémentaires
- Observations sur les clés (sortie)
- Détails des dégradations (sortie, si case cochée)
- Montant retenu (sortie, si retenue partielle/totale)
- Motif de la retenue (sortie, si retenue partielle/totale)

---

## 7. Workflow complet

```
1. Création du contrat
   └─> Contrat signé (statut: 'signe')

2. Création état des lieux d'ENTRÉE
   ├─> Remplir le formulaire complet
   ├─> Ajouter photos (optionnel)
   ├─> Enregistrer en brouillon (statut: 'brouillon')
   └─> Finaliser et envoyer
       ├─> Génération PDF
       ├─> Envoi email locataire + copie gestion@myinvest-immobilier.com
       └─> Statut: 'envoye'

3. Pendant la location
   └─> (locataire occupe le logement)

4. Fin de location - Création état des lieux de SORTIE
   ├─> Remplir le formulaire complet
   ├─> Remplir section CONFORMITÉ
   ├─> Remplir section DÉPÔT DE GARANTIE ⭐
   ├─> Ajouter photos (optionnel)
   ├─> Enregistrer en brouillon (statut: 'brouillon')
   └─> Finaliser et envoyer
       ├─> Génération PDF (avec décision dépôt de garantie)
       ├─> Envoi email locataire + copie gestion@myinvest-immobilier.com
       └─> Statut: 'envoye'

5. Archivage
   └─> PDFs conservés dans /pdf/etat_des_lieux/
   └─> Photos conservées dans base de données (usage interne)
```

---

## 8. Points techniques importants

### 8.1 Sécurité

- ✅ Authentification requise (`auth.php`)
- ✅ Validation des types MIME pour photos
- ✅ Taille maximale photos : 5 MB
- ✅ Injection SQL prévenue (requêtes préparées PDO)
- ✅ Échappement HTML dans les PDFs

### 8.2 Photos

```php
// Upload
uploads/etats_lieux/{etat_lieux_id}/{unique_id}_{timestamp}.{ext}

// Catégories supportées
'compteur_electricite', 'compteur_eau', 'cles', 
'piece_principale', 'cuisine', 'salle_eau', 'autre'

// Formats acceptés
JPEG, JPG, PNG, GIF

// Taille max
5 MB par photo
```

### 8.3 Signatures

- **Bailleur** : Image depuis `parametres.signature_societe_image`
- **Locataire(s)** : Stockées dans `etat_lieux_locataires.signature_data`
- Les signatures sont intégrées dans le PDF via URL publique (SITE_URL)

---

## 9. Dépannage

### Problème : Email non envoyé

**Solution** :
1. Vérifier la configuration SMTP dans `includes/config.php`
2. S'assurer que `SMTP_PASSWORD` est configuré
3. Vérifier les logs d'erreur : `error_log`
4. Tester avec `test-phpmailer.php`

### Problème : PDF non généré

**Solution** :
1. Vérifier les permissions du dossier `/pdf/etat_des_lieux/` (755)
2. Vérifier que TCPDF est installé (`vendor/autoload.php`)
3. Consulter les logs PHP

### Problème : Photos non uploadées

**Solution** :
1. Vérifier les permissions du dossier `/uploads/etats_lieux/` (755)
2. Vérifier la taille du fichier (max 5 MB)
3. Vérifier le format (JPEG, PNG, GIF uniquement)

### Problème : Champs manquants dans le PDF

**Solution** :
1. S'assurer que tous les champs obligatoires sont remplis
2. Vérifier que le formulaire soumet bien toutes les données
3. Vérifier la fonction `generateEntreeHTML()` ou `generateSortieHTML()`

---

## 10. Améliorations futures possibles

- [ ] Signature électronique locataire directement dans l'interface
- [ ] Comparaison automatique entrée/sortie
- [ ] Export Excel des états des lieux
- [ ] Historique des modifications
- [ ] Templates personnalisables par type de logement
- [ ] Calcul automatique retenue sur dépôt de garantie
- [ ] Notifications automatiques (rappels)

---

## 11. Références

### Fichiers clés à consulter

- `admin-v2/edit-etat-lieux.php` - Formulaire principal
- `pdf/generate-etat-lieux.php` - Génération PDF
- `admin-v2/finalize-etat-lieux.php` - Envoi email
- `migrations/026_fix_etats_lieux_schema.php` - Schéma DB

### Documentation associée

- `PHPMAILER_CONFIGURATION.md` - Configuration email
- `DATABASE_SCHEMA.md` - Schéma complet base de données
- `ETAT_LIEUX_COMPREHENSIVE_DOCUMENTATION.md` - Documentation technique

---

**Version** : 1.0  
**Date** : Février 2026  
**Auteur** : MY INVEST IMMOBILIER

