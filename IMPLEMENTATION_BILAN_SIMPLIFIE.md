# Bilan du Logement Simplifié - Documentation d'Implémentation

## Vue d'Ensemble

Ce document décrit l'implémentation du système de bilan du logement simplifié dans l'état de sortie, conformément au cahier des charges fourni.

## Objectif

Intégrer un formulaire simplifié de bilan du logement directement dans l'édition de l'état de sortie, permettant de saisir des équipements et commentaires sous chaque rubrique principale.

## Changements Effectués

### 1. Suppression de la Section "État Général du Logement"

**Fichier:** `admin-v2/edit-etat-lieux.php`

**Éléments supprimés:**
- Section complète "État général du logement" (lignes 1084-1150)
- Champs de formulaire:
  - `etat_general_conforme` - Conformité à l'état d'entrée
  - `degradations_constatees` - Checkbox dégradations
  - `degradations_details` - Détails des dégradations
  - Photos de l'état général
- Fonction JavaScript `toggleDegradationsDetails()`
- Mapping photo `photo_etat_general`

### 2. Ajout des Formulaires de Bilan Simplifiés

**Fichier:** `admin-v2/edit-etat-lieux.php`

**Sections ajoutées:**
Chaque section de l'état de sortie dispose maintenant de son propre formulaire de bilan:

1. **Relevé des compteurs** - ID: `bilan_compteurs`
2. **Restitution des clés** - ID: `bilan_cles`
3. **Pièce principale** - ID: `bilan_piece_principale`
4. **Coin cuisine** - ID: `bilan_cuisine`
5. **Salle d'eau et WC** - ID: `bilan_salle_eau`

**Structure du formulaire:**
```html
<div class="bilan-section">
    <div class="bilan-section-title">
        <i class="bi bi-clipboard-check"></i>
        Bilan du logement - [Nom de la section]
    </div>
    <table class="bilan-table">
        <thead>
            <tr>
                <th>Équipement</th>
                <th>Commentaire</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <!-- Lignes dynamiques -->
        </tbody>
    </table>
    <button onclick="addBilanRow('section_id')">
        Ajouter une ligne
    </button>
</div>
```

**Caractéristiques:**
- Uniquement visible pour les états de sortie (`$isSortie`)
- Colonnes: Équipement (35%) et Commentaire (65%)
- Bouton "Ajouter une ligne" pour chaque section
- Bouton de suppression (icône corbeille) pour chaque ligne

### 3. Modifications de la Base de Données

**Fichier:** `migrations/047_add_bilan_sections_data.sql`

**Colonne ajoutée:**
```sql
ALTER TABLE etats_lieux 
ADD COLUMN bilan_sections_data JSON NULL 
COMMENT 'Simplified bilan data organized by section';
```

**Structure des données JSON:**
```json
{
  "compteurs": [
    {
      "equipement": "Compteur électrique",
      "commentaire": "Index relevé conforme"
    }
  ],
  "cles": [
    {
      "equipement": "Clé appartement",
      "commentaire": "1 clé manquante"
    }
  ],
  "piece_principale": [...],
  "cuisine": [...],
  "salle_eau": [...]
}
```

### 4. Logique de Sauvegarde

**Fichier:** `admin-v2/edit-etat-lieux.php`

**Code ajouté (lignes ~28-48):**
```php
// Prepare bilan sections data if sortie and bilan data is submitted
$bilanSectionsData = null;
if ($etat['type'] === 'sortie' && isset($_POST['bilan']) && is_array($_POST['bilan'])) {
    $bilanSections = [];
    foreach ($_POST['bilan'] as $section => $rows) {
        $bilanSections[$section] = [];
        foreach ($rows as $rowData) {
            if (!empty($rowData['equipement']) || !empty($rowData['commentaire'])) {
                $bilanSections[$section][] = [
                    'equipement' => trim($rowData['equipement'] ?? ''),
                    'commentaire' => trim($rowData['commentaire'] ?? '')
                ];
            }
        }
    }
    $bilanSectionsData = json_encode($bilanSections);
}
```

**Requête UPDATE:**
- Ajout du champ `bilan_sections_data = ?` dans la requête
- Passage de `$bilanSectionsData` dans les paramètres

### 5. Fonctions JavaScript

**Fichier:** `admin-v2/edit-etat-lieux.php`

**Fonctions ajoutées:**

1. **`addBilanRow(section)`** - Ajoute une nouvelle ligne à une section de bilan
2. **`removeBilanRow(rowId, section)`** - Supprime une ligne du bilan
3. **`loadBilanData()`** - Charge les données existantes lors de l'édition

**Chargement initial:**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    // ... autres initialisations
    if (isSortie) {
        loadBilanData();
    }
});
```

### 6. Intégration PDF

**Fichier:** `pdf/generate-etat-lieux.php`

**Modifications (lignes ~555-628):**

1. Chargement des données `bilan_sections_data`
2. Affichage par section avec titres appropriés:
   - Relevé des compteurs
   - Restitution des clés
   - Pièce principale
   - Coin cuisine
   - Salle d'eau et WC

3. Tableau simplifié (2 colonnes uniquement):
   ```html
   <table>
     <thead>
       <tr>
         <th>Équipement</th>
         <th>Commentaire</th>
       </tr>
     </thead>
     <tbody>
       <!-- lignes -->
     </tbody>
   </table>
   ```

4. Rétrocompatibilité avec l'ancien format `bilan_logement_data`

## Styles CSS

**Fichier:** `admin-v2/edit-etat-lieux.php`

**Classes ajoutées:**
- `.bilan-section` - Container principal
- `.bilan-section-title` - Titre de chaque section
- `.bilan-table` - Tableau du bilan
- `.btn-add-bilan-row` - Bouton d'ajout
- `.btn-remove-bilan-row` - Bouton de suppression

## Sécurité

### Mesures Implémentées

1. **Protection XSS:**
   - Utilisation de `htmlspecialchars()` pour l'affichage
   - Échappement des données JSON

2. **Protection SQL Injection:**
   - Requêtes préparées avec PDO
   - Paramètres liés (bind)

3. **Validation des Données:**
   - Vérification du type d'état (sortie uniquement)
   - Filtrage des entrées vides
   - Validation JSON avant décodage

## Tests Effectués

- ✅ Validation syntaxe PHP (php -l)
- ✅ Revue de code automatisée (pas de problèmes)
- ✅ Vérification sécurité CodeQL (pas de vulnérabilités)
- ✅ Prévisualisation UI (screenshot disponible)

## Migration

### Instructions de Déploiement

1. Fusionner la branche dans main
2. Exécuter les migrations:
   ```bash
   php run-migrations.php
   ```
3. Vérifier que la colonne `bilan_sections_data` a été ajoutée:
   ```sql
   DESCRIBE etats_lieux;
   ```

## Utilisation

### Pour l'Utilisateur Final

1. Accéder à un état de sortie existant
2. Sous chaque rubrique, remplir le tableau de bilan:
   - Colonne "Équipement": Nom de l'élément concerné
   - Colonne "Commentaire": Description de l'état/problème
3. Cliquer sur "Ajouter une ligne" pour ajouter des entrées
4. Cliquer sur l'icône corbeille pour supprimer une ligne
5. Sauvegarder l'état des lieux normalement
6. Les données apparaîtront automatiquement dans le PDF généré

### Évolutivité

Le système est conçu pour être facilement étendu à d'autres modules (inventaire, etc.):

1. **Réutilisabilité:** Les styles et fonctions JavaScript peuvent être extraits dans des fichiers séparés
2. **Structure modulaire:** Chaque section de bilan est indépendante
3. **Format de données:** Le JSON permet d'ajouter facilement de nouvelles sections

## Fichiers Modifiés

1. `admin-v2/edit-etat-lieux.php` - Formulaire principal
2. `pdf/generate-etat-lieux.php` - Génération PDF
3. `migrations/047_add_bilan_sections_data.sql` - Migration base de données

## Points d'Attention

1. **Rétrocompatibilité:** Le système supporte à la fois l'ancien et le nouveau format de bilan
2. **États de sortie uniquement:** Les formulaires n'apparaissent que pour les états de sortie
3. **Validation:** Les lignes vides sont automatiquement filtrées lors de la sauvegarde
4. **Performance:** L'utilisation de JSON permet un stockage efficace et flexible

## Support et Maintenance

Pour toute question ou problème:
1. Consulter ce document
2. Vérifier les logs d'erreur PHP
3. Vérifier la console JavaScript du navigateur
4. Consulter les commentaires dans le code source

---

**Date de création:** 12 février 2026  
**Auteur:** GitHub Copilot Agent  
**Version:** 1.0
