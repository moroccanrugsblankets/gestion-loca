# Implémentation du Contrat PDF - Format MY INVEST IMMOBILIER

## 📄 Objectif

Générer des contrats de bail au format PDF conforme au modèle MY INVEST IMMOBILIER avec:
- Format 1 page
- Style professionnel
- Toutes les sections obligatoires
- Injection dynamique des données
- Cases à cocher pour les options
- Signatures électroniques

## ✅ Fonctionnalités Implémentées

### 1. Bibliothèque PDF - TCPDF

**Installation:**
```json
"tecnickcom/tcpdf": "^6.6"
```

**Avantages:**
- Génération PDF serveur-side sécurisée
- Support complet Unicode et caractères spéciaux (☒, ☐)
- Contrôle précis de la mise en page
- Génération rapide et fiable
- Format PDF/A compatible

### 2. Structure du PDF

#### En-tête (Header)
```
MY INVEST IMMOBILIER
CONTRAT DE BAIL
(Location meublée - résidence principale)
```
- Police: Helvetica Bold 16pt pour le titre
- Couleur: Bleu foncé (RGB: 0, 51, 102)
- Centré

#### Corps du Document

**14 Sections Numérotées:**

1. **Parties**
   - Bailleur: MY INVEST IMMOBILIER (SCI)
   - Représenté par: Maxime ALEXANDRE
   - Email: contact@myinvest-immobilier.com
   - Locataire(s): [Dynamique] Nom, prénom, date de naissance, email

2. **Désignation du logement**
   - Adresse: [Dynamique]
   - Appartement: [Dynamique]
   - Type: [Dynamique] - Logement meublé
   - Surface: [Dynamique] m²
   - Usage: Résidence principale
   - ☒ Parking: [Dynamique]
   - ☒ Mobilier conforme à la réglementation
   - ☒ Cuisine équipée

3. **Durée**
   - Durée: 1 an à compter du [Date dynamique]
   - Renouvelable par tacite reconduction

4. **Conditions financières**
   - Loyer mensuel HC: [Dynamique] €
   - Charges mensuelles: [Dynamique] €
   - Total mensuel: [Calculé dynamiquement] €
   - Paiement: mensuel, avant le 5 de chaque mois
   - Modalité: Virement bancaire

5. **Dépôt de garantie**
   - Montant: [Dynamique] € (2 mois de loyer HC)
   - Condition suspensive: Le contrat prend effet à réception du dépôt

6. **Charges**
   - ☒ Provisionnelles avec régularisation annuelle
   - Incluses: eau, électricité, ordures ménagères, internet

7. **État des lieux**
   - Établi contradictoirement à l'entrée et à la sortie

8. **Obligations du locataire**
   - User paisiblement du logement
   - Maintenir en bon état
   - Répondre des dégradations
   - Assurance risques locatifs

9. **Clause résolutoire**
   - Résiliation de plein droit en cas de non-paiement ou défaut d'assurance

10. **Interdictions**
    - ☒ Sous-location interdite sans accord écrit
    - Animaux tolérés sous conditions (aucune nuisance/dégradation)

11. **Résiliation**
    - Par le locataire: préavis 1 mois (LRE obligatoire via AR24)
    - Par le bailleur: conditions légales

12. **DPE (Diagnostic de Performance Énergétique)**
    - Classe énergie: D
    - Classe climat: B
    - Validité: 01/06/2035

13. **Coordonnées bancaires**
    - IBAN: [Depuis config]
    - BIC: [Depuis config]
    - Titulaire: MY INVEST IMMOBILIER

14. **Signatures**
    - Fait à Annemasse, le [Date signature]
    - Le bailleur: MY INVEST IMMOBILIER (Représenté par M. ALEXANDRE)
    - Le(s) locataire(s): [Noms dynamiques]

#### Pied de page (Footer)
```
MY INVEST IMMOBILIER - contact@myinvest-immobilier.com
```
- Police: Helvetica Italic 8pt
- Couleur: Gris (RGB: 128, 128, 128)
- Centré

### 3. Champs Dynamiques

**Sources de données:**
- Table `contrats` - Informations du contrat
- Table `logements` - Détails du logement
- Table `locataires` - Informations des locataires
- Table `candidatures` - Données du candidat
- Fichier `config.php` - IBAN/BIC

**Injection automatique:**
```php
// Exemple de récupération des données
$stmt = $pdo->prepare("
    SELECT c.*, l.*, 
           ca.nom as candidat_nom, ca.prenom as candidat_prenom
    FROM contrats c
    INNER JOIN logements l ON c.logement_id = l.id
    LEFT JOIN candidatures ca ON c.candidature_id = ca.id
    WHERE c.id = ?
");
```

### 4. Génération et Archivage

**Flux de génération:**

1. **Création du contrat** (`admin-v2/generer-contrat.php`)
   - Contrat créé dans la base de données
   - Email envoyé avec lien de signature
   - PDF non encore généré

2. **Signature du contrat** (`signature/step3-documents.php`)
   - Tous les locataires ont signé
   - Documents uploadés
   - **Génération du PDF** via `generateContratPDF($contratId)`
   - Email envoyé avec PDF en pièce jointe

3. **Archivage**
   - Répertoire: `/pdf/contrats/`
   - Format de nom: `bail-{reference_unique}.pdf`
   - Exemple: `bail-BAIL-20260131-A1B2C3D4.pdf`

**Code de génération:**
```php
require_once __DIR__ . '/../pdf/generate-bail.php';
$pdfPath = generateBailPDF($contratId);

// Envoi aux locataires avec PDF
foreach ($locataires as $locataire) {
    $emailData = getFinalisationEmailTemplate($contrat, $contrat, $locataires);
    sendEmail($locataire['email'], $emailData['subject'], $emailData['body'], $pdfPath);
}
```

### 5. Envoi Email avec PDF

**Destinataires:**
1. Tous les locataires du contrat
2. Copie (CC) à tous les administrateurs actifs

**Pièce jointe:**
- PDF du contrat signé
- Nom du fichier: `bail-{reference}.pdf`
- Taille typique: ~8-15 KB

**Exemple d'envoi:**
```php
// Email au locataire
sendEmail($locataire['email'], $subject, $body, $pdfPath, true, false);

// Email aux admins (avec CC automatique)
sendEmail($firstLocataire['email'], $adminSubject, $adminBody, $pdfPath, true, true);
```

## 📐 Optimisation pour 1 Page

### Techniques utilisées:

1. **Taille de police réduite**: 9pt pour le corps du texte
2. **Marges optimisées**: 15mm de chaque côté
3. **Espacement minimal**: 2-4pt entre les sections
4. **Texte concis**: Formulations courtes et directes
5. **Sections condensées**: Regroupement logique

### Test de pagination:

```php
// Dans ContratBailPDF::generateContrat()
$this->SetFont('helvetica', '', 9);  // Police compacte
$this->Ln(2);  // Espacement minimal entre sections
```

## 🔒 Sécurité

### Mesures implémentées:

1. **Génération serveur-side**: Aucun accès client au code de génération
2. **Stockage sécurisé**: Répertoire `/pdf/contrats/` protégé
3. **Noms de fichiers uniques**: Utilisation de références uniques
4. **Validation des données**: Échappement de toutes les données avant injection
5. **Logs complets**: Traçabilité de la génération et envoi

## 📊 Tests et Validation

### Tests créés:

1. **test-pdf-standalone.php**
   - Test sans base de données
   - Vérifie TCPDF est fonctionnel
   - Génère un PDF de test
   - Valide le format PDF

2. **test-pdf-generation.php**
   - Test avec connexion base de données
   - Utilise un contrat réel
   - Vérifie injection de données

### Commandes de test:

```bash
# Test standalone (ne nécessite pas la BDD)
php test-pdf-standalone.php

# Test avec BDD
php test-pdf-generation.php
```

### Résultats attendus:

```
=== Test de génération PDF (standalone) ===

Test 1: TCPDF disponible... ✓
Test 2: Création d'un PDF de test... ✓
  Fichier créé : /pdf/test/test-contrat-20260131-180919.pdf
  Taille : 8,005 octets
  Format : ✓ PDF valide

✓ Tous les tests réussis!
```

## 🎨 Personnalisation Future

### Améliorations possibles:

1. **Logo graphique**: Ajouter une vraie image du logo MY INVEST
2. **Couleurs**: Personnaliser la charte graphique
3. **Polices custom**: Utiliser des polices spécifiques
4. **QR Code**: Ajouter un QR code pour vérification
5. **Filigrane**: Ajouter "SPECIMEN" pour les tests
6. **Multi-pages**: Support de contrats plus longs si nécessaire

### Exemple d'ajout de logo:

```php
public function Header() {
    // Logo
    $logoPath = __DIR__ . '/../assets/images/logo.png';
    if (file_exists($logoPath)) {
        $this->Image($logoPath, 15, 10, 30);
    }
    
    // Titre
    $this->SetFont('helvetica', 'B', 16);
    $this->Cell(0, 10, 'MY INVEST IMMOBILIER', 0, 1, 'C');
}
```

## 🚀 Déploiement

### Prérequis:

1. PHP 7.2+ avec extensions:
   - GD ou Imagick
   - mbstring
   - zlib

2. TCPDF installé via Composer:
   ```bash
   composer install
   ```

3. Permissions du répertoire:
   ```bash
   chmod 755 pdf/contrats/
   ```

### Vérification:

```bash
# Vérifier TCPDF
php -r "require 'vendor/autoload.php'; echo class_exists('TCPDF') ? 'OK' : 'KO';"

# Test rapide
php test-pdf-standalone.php
```

## 📝 Maintenance

### Modifications courantes:

**Changer le texte d'une section:**
```php
// Dans pdf/generate-contrat-pdf.php, méthode generateContrat()
$this->addText('Nouveau texte pour la section X');
```

**Ajouter un champ dynamique:**
```php
// Récupérer la donnée
$nouveauChamp = $contrat['nouveau_champ'];

// L'afficher
$this->addText('Nouveau champ : ' . $nouveauChamp);
```

**Modifier le format:**
```php
// Changer la taille de police
$this->SetFont('helvetica', '', 10);  // Au lieu de 9

// Modifier les marges
$this->SetMargins(20, 20, 20);  // Au lieu de 15, 15, 15
```

## ✅ Conformité

### Checklist de conformité:

- [x] Format PDF professionnel
- [x] En-tête MY INVEST IMMOBILIER
- [x] 14 sections numérotées
- [x] Cases à cocher (☒/☐)
- [x] Données dynamiques injectées
- [x] IBAN/BIC affichés
- [x] Signatures avec dates
- [x] Format A4
- [x] Optimisé pour 1 page
- [x] Email avec pièce jointe
- [x] Copie aux administrateurs
- [x] Archivage sécurisé

---

**Version:** 1.0  
**Date:** 31 Janvier 2026  
**Status:** ✅ PRODUCTION READY
