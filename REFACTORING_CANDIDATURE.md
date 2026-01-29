# Refactoring du Formulaire de Candidature - Documentation Technique

## Vue d'ensemble
Ce document décrit les changements apportés au formulaire de candidature locative pour gérer les documents justificatifs individuellement, comme sur l'ancien formulaire https://www.myinvest-immobilier.com/candidature/.

## Changements effectués

### 1. Corrections des erreurs PHP dans l'admin ✅

#### Fichier: `admin-v2/index.php`
- **Ligne 211**: Corrigé `$cand['reference_candidature']` → `$cand['reference_unique']`
- **Correction**: Utilisation du nom de champ correct selon le schéma de base de données

#### Fichier: `admin-v2/candidatures.php`
- **Ligne 20**: Corrigé `c.reference_candidature` → `c.reference_unique` dans la requête de recherche
- **Ligne 186**: Corrigé `$cand['reference_candidature']` → `$cand['reference_unique']`
- **Ligne 197**: Corrigé `$cand['revenus_nets_mensuels']` → `$cand['revenus_mensuels']`
- **Ajout**: Valeur par défaut 'N/A' avec l'opérateur `??` pour éviter les erreurs si les données sont manquantes

### 2. Refactoring du formulaire frontend ✅

#### Fichier: `candidature/index.php` (Section 6)
**Avant**: Un seul champ `documents[]` pour tous les documents

**Après**: 5 champs distincts obligatoires:
1. **Pièce d'identité** (`piece_identite[]`)
   - Icône: bi-person-vcard
   - Label: "Pièce d'identité ou passeport en cours de validité"

2. **Bulletins de salaire** (`bulletins_salaire[]`)
   - Icône: bi-file-earmark-text
   - Label: "3 derniers bulletins de salaire"

3. **Contrat de travail** (`contrat_travail[]`)
   - Icône: bi-file-earmark-check
   - Label: "Contrat de travail"

4. **Avis d'imposition** (`avis_imposition[]`)
   - Icône: bi-file-earmark-ruled
   - Label: "Dernier avis d'imposition"

5. **Quittances de loyer** (`quittances_loyer[]`)
   - Icône: bi-receipt
   - Label: "3 dernières quittances de loyer"

**Caractéristiques**:
- Chaque champ accepte plusieurs fichiers (multiple)
- Formats: PDF, JPG, PNG
- Taille max: 5 Mo par fichier
- Drag & Drop activé pour chaque zone
- Affichage de la liste des fichiers par type

### 3. Refactoring du JavaScript ✅

#### Fichier: `candidature/candidature.js`

**Structure de données**:
```javascript
let documentsByType = {
    piece_identite: [],
    bulletins_salaire: [],
    contrat_travail: [],
    avis_imposition: [],
    quittances_loyer: []
};
```

**Nouvelles fonctionnalités**:
- Gestion individuelle de chaque type de document
- Validation obligatoire: chaque type doit avoir au moins 1 fichier
- Drag & Drop pour chaque zone d'upload
- Affichage des documents groupés par type dans le récapitulatif
- Messages d'erreur spécifiques indiquant les documents manquants
- Fonction `removeFile(docType, fileName)` mise à jour pour gérer la suppression par type

**Validation avant soumission**:
```javascript
// Vérification que tous les 5 types de documents sont présents
const requiredDocTypes = ['piece_identite', 'bulletins_salaire', 
                          'contrat_travail', 'avis_imposition', 
                          'quittances_loyer'];
```

### 4. Refactoring du backend ✅

#### Fichier: `candidature/submit.php`

**Améliorations majeures**:

1. **Logging détaillé**:
```php
function logDebug($message, $data = null) {
    $logMessage = "[CANDIDATURE DEBUG] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage);
}
```

Logs ajoutés à chaque étape critique:
- Début du traitement
- Validation du token CSRF
- Validation des champs
- Validation des documents
- Insertion en base de données
- Upload de chaque fichier
- Transaction commit
- Envoi d'email

2. **Validation des 5 types de documents**:
```php
$required_doc_types = [
    'piece_identite' => 'Pièce d\'identité ou passeport',
    'bulletins_salaire' => '3 derniers bulletins de salaire',
    'contrat_travail' => 'Contrat de travail',
    'avis_imposition' => 'Dernier avis d\'imposition',
    'quittances_loyer' => '3 dernières quittances de loyer'
];
```

3. **Traitement individualisé**:
- Parcours de chaque type de document séparément
- Enregistrement du type exact dans la base de données
- Nommage des fichiers par type: `{type}_{index}_{random}.{ext}`
- Compteur de fichiers par type
- Résumé détaillé de l'upload

4. **Messages d'erreur spécifiques**:
- Token CSRF invalide
- Champs manquants (nom du champ précis)
- Documents manquants (liste des types manquants)
- Email invalide
- Logement non disponible
- Erreur d'upload avec détails

5. **Génération de référence unique**:
```php
$reference_unique = 'CAND-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
```

6. **Réponse JSON enrichie**:
```json
{
    "success": true,
    "candidature_id": 123,
    "message": "Candidature enregistrée avec succès",
    "documents_uploaded": 8
}
```
Ou en cas d'erreur:
```json
{
    "success": false,
    "error": "Documents manquants : Contrat de travail, Avis d'imposition",
    "debug_info": "Consultez les logs du serveur pour plus de détails"
}
```

### 5. Mise à jour du schéma de base de données ✅

#### Fichier: `database.sql`
Mise à jour de l'ENUM `type_document` dans la table `candidature_documents`:

**Avant**:
```sql
type_document ENUM('piece_identite', 'justificatif_revenus', 'justificatif_domicile', 'autre')
```

**Après**:
```sql
type_document ENUM(
    'piece_identite',        -- Nouveau type spécifique
    'bulletins_salaire',     -- NOUVEAU
    'contrat_travail',       -- NOUVEAU
    'avis_imposition',       -- NOUVEAU
    'quittances_loyer',      -- NOUVEAU
    'justificatif_revenus',  -- Conservé pour compatibilité
    'justificatif_domicile', -- Conservé pour compatibilité
    'autre'                  -- Conservé pour compatibilité
)
```

#### Fichier: `migrations/update_document_types.sql`
Script de migration créé pour les bases de données existantes:
```sql
ALTER TABLE candidature_documents 
MODIFY COLUMN type_document ENUM(
    'piece_identite', 
    'bulletins_salaire', 
    'contrat_travail', 
    'avis_imposition', 
    'quittances_loyer',
    'justificatif_revenus', 
    'justificatif_domicile', 
    'autre'
) NOT NULL;
```

## Installation et déploiement

### Étape 1: Appliquer la migration de base de données

**Option A - Depuis phpMyAdmin ou ligne de commande MySQL**:
```bash
mysql -u root -p bail_signature < migrations/update_document_types.sql
```

**Option B - Script PHP automatique**:
```bash
php apply-migration.php
```

Le script `apply-migration.php` a été créé pour faciliter l'application de la migration avec vérification.

### Étape 2: Vérifier la migration
```sql
SHOW COLUMNS FROM candidature_documents WHERE Field = 'type_document';
```

Vous devriez voir les nouveaux types dans l'ENUM.

### Étape 3: Tester le formulaire

1. **Accéder au formulaire**: 
   - URL: `http://votre-domaine/candidature/`

2. **Remplir les sections 1-5** normalement

3. **Section 6 - Documents**: 
   - Uploader au moins 1 fichier pour chaque type
   - Vérifier que les fichiers s'affichent bien dans la liste
   - Vérifier le compteur par type

4. **Section 7 - Récapitulatif**:
   - Vérifier que les documents sont listés par type
   - Vérifier le total

5. **Soumettre le formulaire**:
   - Vérifier la redirection vers la page de confirmation
   - Vérifier l'email de confirmation

### Étape 4: Vérifier dans l'admin

1. **Accéder à l'admin**: 
   - URL: `http://votre-domaine/admin-v2/`

2. **Vérifier l'absence d'erreurs PHP**:
   - Dashboard (index.php) → Plus d'erreur "Undefined index: reference_candidature"
   - Liste des candidatures → Plus d'erreur "Undefined index: revenus_nets_mensuels"

3. **Voir les documents**:
   - Cliquer sur une candidature
   - Vérifier que les documents sont affichés avec leur type correct

## Debugging

### Consulter les logs

Les logs sont écrits dans le fichier d'erreur PHP configuré. Par défaut:
```
/home/runner/work/contrat-de-bail/contrat-de-bail/error.log
```

**Exemple de logs**:
```
[CANDIDATURE DEBUG] Début du traitement de la candidature
[CANDIDATURE DEBUG] Token CSRF validé
[CANDIDATURE DEBUG] Tous les champs obligatoires sont présents
[CANDIDATURE DEBUG] Tous les documents obligatoires sont présents
[CANDIDATURE DEBUG] Données validées | Data: {"nom":"Dupont","prenom":"Jean",...}
[CANDIDATURE DEBUG] Logement trouvé et disponible | Data: {"logement_ref":"LOG-001"}
[CANDIDATURE DEBUG] Transaction démarrée
[CANDIDATURE DEBUG] Candidature insérée | Data: {"id":123,"reference":"CAND-20260129-A1B2C3D4"}
[CANDIDATURE DEBUG] Fichier uploadé | Data: {"type":"piece_identite","filename":"piece_identite_0_..."}
...
[CANDIDATURE DEBUG] Résumé upload | Data: {"piece_identite":1,"bulletins_salaire":3,...}
[CANDIDATURE DEBUG] Transaction validée
[CANDIDATURE DEBUG] Email de confirmation envoyé | Data: {"email":"jean.dupont@example.com"}
[CANDIDATURE DEBUG] Candidature traitée avec succès | Data: {"id":123,"documents":8}
```

### Erreurs courantes et solutions

#### 1. "Documents manquants : ..."
**Cause**: Un ou plusieurs types de documents n'ont pas été uploadés
**Solution**: Vérifier que tous les 5 champs ont au moins un fichier

#### 2. "Aucun document n'a pu être uploadé"
**Cause**: Problème de permissions ou de format
**Solution**: 
- Vérifier les permissions du dossier `uploads/candidatures/`
- Vérifier que les fichiers sont en PDF, JPG ou PNG
- Vérifier la taille < 5 Mo

#### 3. "Token CSRF invalide"
**Cause**: Session expirée ou formulaire ouvert dans plusieurs onglets
**Solution**: Rafraîchir la page du formulaire

#### 4. Undefined index dans l'admin
**Cause**: Migration de base de données non appliquée ou données manquantes
**Solution**: 
- Appliquer la migration
- Les champs utilisent maintenant l'opérateur `??` pour gérer les valeurs nulles

## Tests de validation

### Checklist de test

- [ ] PHP: Syntaxe validée (`php -l`)
- [ ] JavaScript: Syntaxe validée (`node -c`)
- [ ] Migration: Appliquée avec succès
- [ ] Admin: Pas d'erreurs PHP "Undefined index"
- [ ] Formulaire: Affichage correct des 5 champs
- [ ] Formulaire: Drag & Drop fonctionnel
- [ ] Formulaire: Validation client-side (messages d'erreur si documents manquants)
- [ ] Backend: Logs détaillés dans error.log
- [ ] Backend: Upload des 5 types de documents
- [ ] Backend: Stockage correct dans candidature_documents avec type
- [ ] Email: Confirmation envoyée
- [ ] Admin: Documents visibles avec type correct

## Compatibilité

### Rétrocompatibilité
Les anciens types de documents (`justificatif_revenus`, `justificatif_domicile`, `autre`) sont conservés dans l'ENUM pour assurer la compatibilité avec les candidatures existantes.

### Migration des données existantes
Si vous avez des candidatures existantes avec l'ancien système, aucune migration de données n'est nécessaire. Les anciennes entrées continueront de fonctionner.

## Support

En cas de problème:
1. Consulter les logs (`error.log`)
2. Vérifier que la migration est appliquée
3. Tester avec les outils de développement du navigateur (Console, Network)
4. Vérifier les permissions des dossiers uploads

## Auteur
Refactoring effectué le 29 janvier 2026
Version: 2.0
