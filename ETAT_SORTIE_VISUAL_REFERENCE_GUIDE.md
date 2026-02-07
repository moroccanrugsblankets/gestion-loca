# État de Sortie - Visual Reference Implementation

## Vue d'ensemble

Cette implémentation permet de créer des états de sortie avec affichage visuel des données d'entrée comme **référence uniquement** (pas de copie automatique).

## Principe Fondamental

**DIFFÉRENCE MAJEURE** avec l'implémentation précédente :
- **Avant** : Copie automatique des données d'entrée → sortie
- **Maintenant** : Affichage visuel des données d'entrée comme référence, l'utilisateur saisit les données de sortie manuellement

## Fonctionnalités Implémentées

### 1. Création d'État de Sortie

**Fichier**: `/admin-v2/create-etat-lieux.php`

#### Comportement
- **État d'entrée** : Champs pré-remplis avec valeurs par défaut du logement
- **État de sortie** : Champs VIDES - aucune copie automatique
  - Seul l'ID de l'état d'entrée est stocké pour référence ultérieure
  - Aucune donnée copiée dans la base de données
  - Aucune photo dupliquée

```php
// Pour état de sortie: pas de copie, juste stockage de l'ID d'entrée
if ($type === 'sortie') {
    $stmt = $pdo->prepare("SELECT id FROM etats_lieux WHERE contrat_id = ? AND type = 'entree'");
    $stmt->execute([$contrat_id]);
    $etat_entree = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Tous les champs restent NULL/vides
    // L'utilisateur remplira manuellement
}
```

### 2. Affichage des Références Visuelles

**Fichier**: `/admin-v2/edit-etat-lieux.php`

#### Récupération des Données d'Entrée

Pour les états de sortie, le système récupère :
1. L'état d'entrée complet du même contrat
2. Toutes les photos de l'état d'entrée

```php
if ($isSortie && !empty($etat['contrat_id'])) {
    // Récupérer état d'entrée
    $stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE contrat_id = ? AND type = 'entree' ORDER BY date_etat DESC LIMIT 1");
    $stmt->execute([$etat['contrat_id']]);
    $etat_entree = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer photos d'entrée
    if ($etat_entree) {
        $stmt = $pdo->prepare("SELECT * FROM etat_lieux_photos WHERE etat_lieux_id = ?");
        $stmt->execute([$etat_entree['id']]);
        $etat_entree_photos_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Grouper par catégorie
    }
}
```

#### Structure d'Affichage

Pour chaque champ, la structure est :

```html
<!-- Référence d'entrée (vert) -->
<?php if ($isSortie && $etat_entree): ?>
    <div class="entry-reference">
        <span class="icon-green">🟢</span>
        <span class="entry-reference-label">État d'entrée :</span>
        <span class="entry-reference-value">
            [Valeur d'entrée]
        </span>
    </div>
<?php endif; ?>

<!-- Champ de saisie sortie (rouge) -->
<label class="exit-input-label">
    <span class="icon-red">🔴</span>
    [Libellé] - Sortie
</label>
<input type="text" name="[field]" value="" /> <!-- Vide -->
```

### 3. Styles Visuels

#### Classes CSS Ajoutées

```css
/* Boîte de référence verte */
.entry-reference {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 8px;
}

/* Icône verte pour données d'entrée */
.icon-green {
    color: #28a745;
    font-size: 1.1rem;
}

/* Label rouge pour champs de sortie */
.exit-input-label {
    color: #dc3545;
    font-weight: 600;
}

.icon-red {
    color: #dc3545;
}

/* Photos d'entrée en miniature */
.entry-photo-thumbnail {
    border: 2px solid #28a745;
    border-radius: 4px;
    position: relative;
}

.entry-photo-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}
```

### 4. Champs avec Référence Visuelle

#### Compteurs
- **Électricité** : Affiche index d'entrée + champ vide pour sortie
- **Eau froide** : Affiche index d'entrée + champ vide pour sortie
- Photos des compteurs d'entrée affichées en miniature

#### Clés
- **Résumé** : Affiche tous les comptages d'entrée (appartement, boîte, autre, total)
- **Champs de saisie** : Vides pour saisie sortie
- Photos des clés d'entrée affichées

#### Descriptions des Pièces
- **Pièce principale** : Texte d'entrée affiché + zone de saisie vide pour sortie
- **Coin cuisine** : Texte d'entrée affiché + zone de saisie vide pour sortie
- **Salle d'eau/WC** : Texte d'entrée affiché + zone de saisie vide pour sortie
- Photos de chaque pièce d'entrée affichées

#### Observations
- **État général** : Observations d'entrée affichées + zone vide pour sortie
- **Observations complémentaires** : Observations d'entrée + zone vide pour sortie

## Message d'Information

En haut du formulaire d'état de sortie :

```html
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>État de sortie :</strong> Les données affichées en 
    <span class="text-success fw-bold">🟢 VERT</span> proviennent de 
    l'état d'entrée et servent de référence. Veuillez saisir l'état 
    de sortie dans les champs marqués en 
    <span class="text-danger fw-bold">🔴 ROUGE</span>.
</div>
```

Si aucun état d'entrée trouvé :
```html
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Attention :</strong> Aucun état d'entrée trouvé pour ce 
    contrat. Les références ne pourront pas être affichées.
</div>
```

## Workflow Utilisateur

### Étape 1 : Créer État d'Entrée
1. États des lieux → Nouvel état des lieux
2. Sélectionner logement
3. Type : **Entrée**
4. Remplir tous les champs
5. Ajouter photos
6. Enregistrer

### Étape 2 : Créer État de Sortie
1. États des lieux → Nouvel état des lieux
2. Sélectionner **même logement**
3. Type : **Sortie**
4. Date de sortie
5. Système créé état VIDE (pas de copie)

### Étape 3 : Remplir État de Sortie
1. Formulaire s'ouvre avec références vertes
2. **Pour chaque champ** :
   - 🟢 Voir valeur d'entrée (référence)
   - 🔴 Saisir valeur de sortie
   - Comparer facilement
3. **Pour chaque section de photos** :
   - 🟢 Voir photos d'entrée en miniature
   - 📷 Ajouter nouvelles photos de sortie
4. Enregistrer

### Étape 4 : Générer PDF
- PDF contient UNIQUEMENT les données de sortie
- Pas de données d'entrée dans le PDF
- Format standard avec signatures

## Exemples Visuels

### Exemple 1 : Compteur Électricité

```
┌─────────────────────────────────────────┐
│ 🟢 État d'entrée : 12345 kWh           │  ← Référence (lecture seule)
└─────────────────────────────────────────┘

🔴 Index relevé (kWh) - Sortie              ← Label de saisie
┌─────────────────────────────────────────┐
│                                         │  ← Champ vide
└─────────────────────────────────────────┘

🟢 Photos de l'état d'entrée (référence):
┌────┐ ┌────┐ ┌────┐
│ 🟢 │ │ 🟢 │ │ 🟢 │                       ← Miniatures avec badge vert
└────┘ └────┘ └────┘

Vos photos de sortie:
[📷 Ajouter une photo]                     ← Zone d'upload
```

### Exemple 2 : Clés

```
┌───────────────────────────────────────────────────────────┐
│ 🟢 État d'entrée :                                       │
│    Appartement: 2, Boîte lettres: 1, Autre: 0, Total: 3 │
└───────────────────────────────────────────────────────────┘

🔴 Clés de l'appartement        🔴 Boîte aux lettres
┌────┐                          ┌────┐
│    │ (vide)                   │    │ (vide)
└────┘                          └────┘

🔴 Autre                        Total des clés
┌────┐                          ┌────┐
│    │ (vide)                   │    │ (calculé auto)
└────┘                          └────┘
```

### Exemple 3 : Description Pièce

```
┌─────────────────────────────────────────────────────┐
│ 🟢 État d'entrée :                                 │
│                                                     │
│ • Revêtement de sol : parquet très bon état        │
│ • Murs : peintures très bon état                   │
│ • Plafond : peintures très bon état                │
│ • Installations : fonctionnelles                   │
└─────────────────────────────────────────────────────┘

🔴 État de sortie
┌─────────────────────────────────────────────────────┐
│                                                     │
│                                                     │  ← Zone vide
│                                                     │     pour saisie
│                                                     │
└─────────────────────────────────────────────────────┘
```

## Avantages de cette Approche

### 1. Comparaison Facile
L'utilisateur voit directement l'état d'entrée au-dessus de chaque champ

### 2. Évite les Erreurs
- Pas de risque de modifier accidentellement les données d'entrée
- Données d'entrée en lecture seule
- Séparation claire entrée/sortie

### 3. Saisie Indépendante
- Utilisateur décrit l'état réel à la sortie
- Pas influencé par des valeurs pré-remplies
- Traçabilité complète

### 4. Visuel Clair
- Codes couleur universels (vert = référence, rouge = saisie)
- Icônes visuelles 🟢/🔴
- Photos miniatures facilement identifiables

## Contraintes Techniques Respectées

✅ **PHP 7.4** : Code compatible  
✅ **Pas de copie automatique** : Champs vides pour sortie  
✅ **Affichage référence** : Données d'entrée affichées visuellement  
✅ **Base de données** : Aucune modification du schéma  
✅ **TCPDF** : Génération PDF inchangée  
✅ **Distinction visuelle** : Vert/Rouge claire  

## Fichiers Modifiés

### 1. `/admin-v2/create-etat-lieux.php`
- **Suppression** : Logique de copie automatique des données
- **Suppression** : Duplication automatique des photos
- **Ajout** : Vérification existence état d'entrée
- Lignes modifiées : ~100

### 2. `/admin-v2/edit-etat-lieux.php`
- **Ajout** : Récupération état d'entrée pour référence
- **Ajout** : Récupération photos d'entrée
- **Ajout** : Styles CSS pour référence visuelle
- **Ajout** : Affichage références pour tous les champs
- **Modification** : Message d'information
- **Modification** : Champs vides par défaut pour sortie
- Lignes modifiées : ~330

## Tests à Effectuer

### Test 1 : Création État d'Entrée
1. Créer état d'entrée avec toutes les données
2. Ajouter photos à toutes les sections
3. Vérifier sauvegarde correcte

### Test 2 : Création État de Sortie
1. Créer état de sortie pour même contrat
2. Vérifier champs vides (pas de copie)
3. Vérifier références vertes affichées

### Test 3 : Affichage Références
1. Ouvrir formulaire état de sortie
2. Vérifier message d'information
3. Vérifier références vertes pour tous les champs
4. Vérifier photos d'entrée affichées en miniature

### Test 4 : Saisie Sortie
1. Remplir tous les champs de sortie
2. Comparer avec références d'entrée
3. Ajouter photos de sortie
4. Enregistrer

### Test 5 : Génération PDF
1. Finaliser état de sortie
2. Générer PDF
3. Vérifier UNIQUEMENT données de sortie dans PDF
4. Vérifier absence données d'entrée

## Support et Dépannage

### Problème : Références ne s'affichent pas
**Cause** : Pas d'état d'entrée trouvé  
**Solution** : Vérifier qu'un état d'entrée existe pour ce contrat

### Problème : Photos d'entrée manquantes
**Cause** : Chemins de fichiers invalides  
**Solution** : Vérifier que les fichiers existent dans `uploads/etats_lieux/`

### Problème : Champs pré-remplis
**Cause** : Ancienne implémentation encore active  
**Solution** : Vérifier version code dans `create-etat-lieux.php`

## Évolutions Futures Possibles

1. **Comparaison côte à côte** : Afficher entrée et sortie en 2 colonnes
2. **Mise en évidence différences** : Surligner automatiquement les changements
3. **Export comparatif** : PDF avec entrée + sortie ensemble
4. **Calcul automatique dégradations** : Comparer photos avec IA
5. **Historique modifications** : Tracer qui a modifié quoi et quand
