# Module État des Lieux - Documentation Complète

## Vue d'ensemble

Le module "État des lieux d'entrée/sortie" permet de générer des documents PDF structurés pour les inventaires de fixtures lors de l'entrée et de la sortie des locataires. Le système génère automatiquement un PDF professionnel et l'envoie par email au locataire avec une copie à gestion@myinvest-immobilier.com.

## Fonctionnalités

### État des lieux d'entrée
- Identification complète (date, adresse, bailleur, locataire(s))
- Relevé des compteurs (électricité, eau froide) avec possibilité d'ajouter des photos
- Remise des clés (appartement, boîte aux lettres, total)
- Description détaillée du logement (pièce principale, cuisine, salle d'eau/WC, état général)
- Signatures électroniques (bailleur + locataire(s))

### État des lieux de sortie
- Identification complète
- Relevé des compteurs à la sortie
- Restitution des clés avec conformité
- Description du logement à la sortie
- Conclusion avec comparaison avec l'état d'entrée
- Gestion du dépôt de garantie (restitution totale/partielle/retenue)
- Signatures électroniques

## Structure de la base de données

### Table `etat_lieux`
Stocke les données principales de l'état des lieux.

**Colonnes principales:**
- `id`: Identifiant unique
- `contrat_id`: Référence au contrat (FK)
- `type`: 'entree' ou 'sortie'
- `reference_unique`: Référence unique de l'état des lieux
- `date_etat`: Date de l'état des lieux
- `adresse`, `appartement`: Informations du logement
- `bailleur_nom`, `bailleur_representant`: Informations du bailleur
- `compteur_electricite`, `compteur_eau_froide`: Relevés des compteurs
- `compteur_electricite_photo`, `compteur_eau_froide_photo`: Chemins vers les photos (optionnel)
- `cles_appartement`, `cles_boite_lettres`, `cles_total`: Nombre de clés
- `cles_conformite`: Conformité de la restitution ('conforme', 'non_conforme', 'non_applicable')
- `cles_observations`: Observations sur les clés
- `piece_principale`, `coin_cuisine`, `salle_eau_wc`, `etat_general`: Descriptions du logement
- `comparaison_entree`: Comparaison avec l'état d'entrée (sortie uniquement)
- `depot_garantie_status`: Statut du dépôt ('restitution_totale', 'restitution_partielle', 'retenue_totale', 'non_applicable')
- `depot_garantie_montant_retenu`: Montant retenu
- `depot_garantie_motif_retenue`: Motif de la retenue
- `lieu_signature`: Lieu de signature
- `date_signature`: Date de signature
- `statut`: 'brouillon', 'finalise', 'envoye'
- `email_envoye`: Booléen indiquant si l'email a été envoyé
- `date_envoi_email`: Date d'envoi de l'email

### Table `etat_lieux_locataires`
Stocke les signatures des locataires pour chaque état des lieux.

**Colonnes principales:**
- `id`: Identifiant unique
- `etat_lieux_id`: Référence à l'état des lieux (FK)
- `locataire_id`: Référence au locataire (FK)
- `ordre`: Ordre du locataire
- `nom`, `prenom`, `email`: Informations du locataire (copie au moment de l'état des lieux)
- `signature_data`: Chemin vers l'image de signature
- `signature_timestamp`: Date et heure de la signature
- `signature_ip`: Adresse IP du signataire

### Table `etat_lieux_photos`
Stocke les photos optionnelles (usage interne uniquement).

**Colonnes principales:**
- `id`: Identifiant unique
- `etat_lieux_id`: Référence à l'état des lieux (FK)
- `categorie`: Type de photo ('compteur_electricite', 'compteur_eau', 'cles', 'piece_principale', 'cuisine', 'salle_eau', 'autre')
- `nom_fichier`: Nom du fichier
- `chemin_fichier`: Chemin complet du fichier
- `description`: Description de la photo
- `ordre`: Ordre d'affichage

## Fichiers du module

### `/pdf/generate-etat-lieux.php`
Fichier principal contenant toutes les fonctions de génération de PDF.

**Fonctions principales:**

#### `generateEtatDesLieuxPDF($contratId, $type)`
Fonction principale pour générer le PDF de l'état des lieux.

**Paramètres:**
- `$contratId` (int): ID du contrat
- `$type` (string): 'entree' ou 'sortie'

**Retour:**
- (string|false): Chemin du fichier PDF généré, ou false en cas d'erreur

**Exemple d'utilisation:**
```php
require_once __DIR__ . '/pdf/generate-etat-lieux.php';

// Générer un état des lieux d'entrée
$pdfPath = generateEtatDesLieuxPDF(123, 'entree');
if ($pdfPath) {
    echo "PDF généré: $pdfPath";
}

// Générer un état des lieux de sortie
$pdfPath = generateEtatDesLieuxPDF(123, 'sortie');
if ($pdfPath) {
    echo "PDF généré: $pdfPath";
}
```

#### `sendEtatDesLieuxEmail($contratId, $type, $pdfPath)`
Envoie l'état des lieux par email au locataire et à gestion@myinvest-immobilier.com.

**Paramètres:**
- `$contratId` (int): ID du contrat
- `$type` (string): 'entree' ou 'sortie'
- `$pdfPath` (string): Chemin du fichier PDF

**Retour:**
- (bool): True si l'email a été envoyé avec succès

**Exemple d'utilisation:**
```php
// Générer et envoyer l'état des lieux
$pdfPath = generateEtatDesLieuxPDF(123, 'entree');
if ($pdfPath) {
    $success = sendEtatDesLieuxEmail(123, 'entree', $pdfPath);
    if ($success) {
        echo "Email envoyé avec succès";
    }
}
```

#### `createDefaultEtatLieux($contratId, $type, $contrat, $locataires)`
Crée un état des lieux par défaut avec des données de base.

Cette fonction est appelée automatiquement par `generateEtatDesLieuxPDF()` si aucun état des lieux n'existe pour le contrat.

#### `generateEntreeHTML($contrat, $locataires, $etatLieux)`
Génère le code HTML pour l'état des lieux d'entrée.

#### `generateSortieHTML($contrat, $locataires, $etatLieux)`
Génère le code HTML pour l'état des lieux de sortie.

#### `buildSignaturesTableEtatLieux($contrat, $locataires, $etatLieux)`
Construit le tableau de signatures pour l'état des lieux.

#### `getDefaultPropertyDescriptions($type)`
Retourne les descriptions par défaut du logement selon le type d'état des lieux.

### `/migrations/021_create_etat_lieux_tables.php`
Script de migration pour créer les tables nécessaires.

**Exécution:**
```bash
php migrations/021_create_etat_lieux_tables.php
```

## Workflow d'utilisation

### Scénario 1: État des lieux d'entrée automatique

```php
// Dans votre code de gestion de contrat
require_once __DIR__ . '/pdf/generate-etat-lieux.php';

// Après la signature du bail
$contratId = 123;

// 1. Générer le PDF
$pdfPath = generateEtatDesLieuxPDF($contratId, 'entree');

if ($pdfPath) {
    // 2. Envoyer par email
    $emailSent = sendEtatDesLieuxEmail($contratId, 'entree', $pdfPath);
    
    if ($emailSent) {
        echo "État des lieux d'entrée généré et envoyé avec succès";
    } else {
        echo "PDF généré mais erreur d'envoi email";
    }
} else {
    echo "Erreur lors de la génération du PDF";
}
```

### Scénario 2: État des lieux de sortie avec données personnalisées

```php
require_once __DIR__ . '/pdf/generate-etat-lieux.php';
require_once __DIR__ . '/includes/db.php';

$contratId = 123;

// 1. Créer ou mettre à jour l'état des lieux avec données personnalisées
$stmt = $pdo->prepare("
    INSERT INTO etat_lieux (
        contrat_id, type, reference_unique, date_etat, adresse, appartement,
        compteur_electricite, compteur_eau_froide,
        cles_appartement, cles_boite_lettres, cles_total, cles_conformite,
        piece_principale, coin_cuisine, salle_eau_wc, etat_general,
        comparaison_entree, depot_garantie_status,
        lieu_signature, statut
    ) VALUES (?, 'sortie', ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'conforme', ?, ?, ?, ?, ?, 'restitution_totale', ?, 'brouillon')
    ON DUPLICATE KEY UPDATE
        compteur_electricite = VALUES(compteur_electricite),
        compteur_eau_froide = VALUES(compteur_eau_froide),
        cles_conformite = VALUES(cles_conformite)
");

$reference = 'EDL-SORTIE-' . time();
$stmt->execute([
    $contratId, $reference, '123 Rue Example', 'Apt 4B',
    '12345', '67890',  // compteurs
    2, 1, 3,  // clés
    'Bon état général', 'Cuisine propre', 'Salle de bain nickel', 'Excellent état',
    'Aucune dégradation constatée',
    'Nice'
]);

// 2. Générer le PDF
$pdfPath = generateEtatDesLieuxPDF($contratId, 'sortie');

// 3. Envoyer par email
if ($pdfPath) {
    sendEtatDesLieuxEmail($contratId, 'sortie', $pdfPath);
}
```

### Scénario 3: Ajouter des photos (usage interne)

```php
require_once __DIR__ . '/includes/db.php';

$etatLieuxId = 45;
$uploadDir = __DIR__ . '/uploads/etat_lieux_photos/';

// Créer le répertoire si nécessaire
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Enregistrer une photo
$photoPath = $uploadDir . 'compteur_elec_' . time() . '.jpg';
move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath);

// Ajouter à la base de données
$stmt = $pdo->prepare("
    INSERT INTO etat_lieux_photos (etat_lieux_id, categorie, nom_fichier, chemin_fichier, description)
    VALUES (?, 'compteur_electricite', ?, ?, 'Photo du compteur électricité')
");
$stmt->execute([$etatLieuxId, basename($photoPath), $photoPath]);

// Note: Les photos sont stockées en interne uniquement
// Elles ne sont PAS incluses dans le PDF envoyé au locataire
```

## Format du PDF généré

### Structure du document

Le PDF généré est au format A4 portrait avec:
- Marges: 15mm de chaque côté
- Police: Arial 10pt
- Encodage: UTF-8
- Titre centré et souligné
- Sections hiérarchisées avec titres en gras
- Tableaux pour les données structurées (compteurs, clés)
- Zone de signature pour chaque partie

### Sections (État des lieux d'entrée)

1. **IDENTIFICATION**
   - Date de l'état des lieux
   - Adresse du logement
   - Bailleur et représentant
   - Locataire(s) avec emails

2. **RELEVÉ DES COMPTEURS**
   - Tableau avec électricité et eau froide
   - Index relevés
   - Note sur les photos facultatives

3. **REMISE DES CLÉS**
   - Tableau avec types de clés
   - Nombre de clés remises
   - Total

4. **DESCRIPTION DU LOGEMENT**
   - 4.1 Pièce principale
   - 4.2 Coin cuisine
   - 4.3 Salle d'eau / WC
   - 4.4 État général

5. **SIGNATURES**
   - Tableau avec bailleur et locataire(s)
   - Signatures électroniques si disponibles
   - Date et lieu de signature

### Sections supplémentaires (État des lieux de sortie)

5. **CONCLUSION** (avant signatures)
   - 5.1 Comparaison avec l'état des lieux d'entrée
   - 5.2 Dépôt de garantie (cases à cocher)
     - Restitution totale
     - Restitution partielle
     - Retenue totale
     - Montant et motif si applicable

## Gestion des emails

### Destinataires
- **Principal**: Email du/des locataire(s)
- **Copie**: gestion@myinvest-immobilier.com

### Format de l'email

**Sujet:** État des lieux [d'entrée|de sortie] - [adresse]

**Corps:**
```
Bonjour,

Veuillez trouver ci-joint l'état des lieux [d'entrée|de sortie] pour le logement situé au :
[adresse complète]

Date de l'état des lieux : [date]

Ce document est à conserver précieusement.

Cordialement,
MY INVEST IMMOBILIER
```

**Pièce jointe:** PDF de l'état des lieux

## Stockage des fichiers

### PDFs générés
- **Répertoire**: `/pdf/etat_des_lieux/`
- **Format du nom**: `etat_lieux_{type}_{reference}_{date}.pdf`
  - Exemple: `etat_lieux_entree_REF123_20260204.pdf`

### Photos (optionnelles)
- **Répertoire**: `/uploads/etat_lieux_photos/`
- **Usage**: Interne uniquement (pas dans le PDF envoyé au locataire)
- **Stockage**: Base de données + fichiers physiques

## Sécurité et conformité

### Données personnelles (RGPD)
- Les données des locataires sont stockées avec leur consentement
- Les photos sont conservées en interne uniquement
- Les états des lieux sont liés aux contrats avec suppression en cascade

### Signatures électroniques
- Utilisation des signatures existantes du système
- Horodatage et IP enregistrés
- Images stockées dans `/uploads/signatures/`

### Validation des données
- Tous les IDs sont validés et castés en entiers
- Les types d'état des lieux sont limités à 'entree' et 'sortie'
- Échappement HTML pour toutes les données affichées

## Tests et validation

### Script de test
Un script de test complet est fourni: `test-etat-lieux-module.php`

**Exécution:**
```bash
php test-etat-lieux-module.php
```

**Vérifications effectuées:**
- Présence de TCPDF
- Existence des fichiers
- Présence de toutes les fonctions requises
- Structure HTML complète pour entrée et sortie
- Gestion du répertoire PDF
- Fonction d'envoi d'email
- Tables de base de données
- Syntaxe PHP

## Dépendances

### PHP
- Version: 7.4+
- Extensions: PDO, PDO_MySQL

### Composer
- `phpmailer/phpmailer`: ^6.12.0 (envoi d'emails)
- `tecnickcom/tcpdf`: ^6.10.1 (génération PDF)

### Base de données
- MySQL 5.7+ ou MariaDB 10.2+

## Intégration avec le système existant

Le module s'intègre naturellement avec le système de gestion locative:

1. **Utilise la même structure** que `generate-contrat-pdf.php`
2. **Réutilise les fonctions** existantes (sendEmail, etc.)
3. **Suit les mêmes conventions** (nommage, structure de fichiers)
4. **Compatible avec** le workflow de signatures électroniques

## Maintenance et évolution

### Modifications courantes

#### Changer les descriptions par défaut
Modifier la fonction `getDefaultPropertyDescriptions()` dans `/pdf/generate-etat-lieux.php`

#### Ajouter un champ
1. Ajouter la colonne dans la migration
2. Modifier les fonctions `generateEntreeHTML()` ou `generateSortieHTML()`
3. Mettre à jour `createDefaultEtatLieux()` si nécessaire

#### Personnaliser l'email
Modifier la fonction `sendEtatDesLieuxEmail()` ou créer un template dans la table `parametres`

### Logs et débogage

Tous les événements importants sont enregistrés avec `error_log()`:
- Génération de PDF
- Envoi d'emails
- Erreurs et exceptions

## Support et contact

Pour toute question ou problème:
- **Email technique**: contact@myinvest-immobilier.com
- **Documentation projet**: README.md dans le dossier racine
- **Issues GitHub**: Créer une issue dans le repository

## Licence

Ce module fait partie du système de gestion locative MY INVEST IMMOBILIER.
Tous droits réservés © 2026 MY INVEST IMMOBILIER.
