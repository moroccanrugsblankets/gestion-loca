# État de Sortie - Module Implémentation

## Vue d'ensemble

Ce document décrit l'implémentation du module d'état des lieux de sortie (move-out inspection) avec rappel automatique des données de l'état d'entrée.

## Fonctionnalités Implémentées

### 1. Rappel Automatique des Données

Lors de la création d'un état de sortie, **tous** les champs de l'état d'entrée sont automatiquement copiés :

#### Champs Copiés

- **Compteurs** :
  - `compteur_electricite` (relevé du compteur électrique)
  - `compteur_eau_froide` (relevé du compteur d'eau froide)

- **Clés** :
  - `cles_appartement` (nombre de clés d'appartement)
  - `cles_boite_lettres` (nombre de clés de boîte aux lettres)
  - `cles_autre` (autres clés)
  - `cles_total` (total des clés)
  - `cles_observations` (observations sur les clés)

- **Descriptions des pièces** :
  - `piece_principale` (état de la pièce principale)
  - `coin_cuisine` (état du coin cuisine)
  - `salle_eau_wc` (état de la salle d'eau/WC)

- **Observations** :
  - `observations` (observations générales)
  - `etat_general` (état général du logement)

#### Photos Copiées

Les photos sont **dupliquées physiquement** :
- Fichiers images copiés vers un nouveau répertoire : `uploads/etats_lieux/{exit_id}/`
- Nouveaux enregistrements créés dans la table `etat_lieux_photos`
- Catégories préservées : `compteur_electricite`, `compteur_eau`, `cles`, `piece_principale`, `cuisine`, `salle_eau`, `autre`
- Description et ordre préservés

### 2. Interface Utilisateur

#### Message Informatif

Un message d'information s'affiche en haut du formulaire d'édition pour les états de sortie :

```
ℹ️ État de sortie : Les champs et photos ont été automatiquement pré-remplis à partir 
de l'état des lieux d'entrée. Vous pouvez modifier, compléter ou supprimer ces données 
pour refléter l'état réel du logement à la sortie.
```

#### Icônes Distinctives

- État d'entrée : 🟢 `bi-box-arrow-in-right` (flèche entrante verte)
- État de sortie : 🔴 `bi-box-arrow-right` (flèche sortante rouge)

### 3. Modification des Données

Toutes les données pré-remplies sont **entièrement modifiables** :
- L'utilisateur peut mettre à jour les relevés de compteurs
- Les descriptions de pièces peuvent être ajustées
- Les observations peuvent être complétées
- Les photos peuvent être supprimées ou de nouvelles ajoutées

### 4. Gestion des Sauts de Ligne

Le système gère correctement les sauts de ligne dans les observations et descriptions :

**Dans le formulaire** :
- Les utilisateurs peuvent utiliser des retours à la ligne normaux

**Dans le PDF** :
- Les `\n` sont convertis en `<br>` pour l'affichage HTML
- Les `<br>` existants sont d'abord convertis en `\n` puis re-convertis en `<br>` pour cohérence
- Pas d'interligne excessif grâce à la gestion appropriée

## Workflow Utilisateur

### Étape 1 : Créer un État d'Entrée
1. Aller dans "États des lieux" → "Nouvel état des lieux"
2. Sélectionner le logement (ex: Appartement RPTrois)
3. Choisir type : **Entrée**
4. Saisir la date
5. Compléter le formulaire avec :
   - Relevés de compteurs
   - Nombre de clés
   - Description des pièces
   - Observations
   - Photos

### Étape 2 : Créer un État de Sortie
1. Aller dans "États des lieux" → "Nouvel état des lieux"
2. Sélectionner le **même logement**
3. Choisir type : **Sortie**
4. Saisir la date de sortie
5. Le système :
   - ✅ Recherche l'état d'entrée du contrat
   - ✅ Copie tous les champs
   - ✅ Duplique toutes les photos
   - ✅ Redirige vers le formulaire d'édition

### Étape 3 : Modifier l'État de Sortie
1. Le formulaire s'ouvre avec toutes les données pré-remplies
2. Message informatif affiché en haut
3. Modifier les champs selon l'état réel à la sortie :
   - Mettre à jour les relevés de compteurs (nouveaux relevés)
   - Vérifier le nombre de clés rendues
   - Ajuster les descriptions si des dégradations sont constatées
   - Ajouter des observations sur les anomalies
4. Gérer les photos :
   - Supprimer les photos qui ne sont plus pertinentes
   - Ajouter de nouvelles photos des dégradations

### Étape 4 : Générer le PDF
1. Finaliser l'état des lieux
2. Générer le PDF
3. Le PDF inclut :
   - Type clairement identifié : "ÉTAT DES LIEUX DE SORTIE"
   - Numéro du logement
   - Adresse complète
   - Données mises à jour
   - Signatures (agence, propriétaire, locataire)

## Fichiers Modifiés

### `/admin-v2/create-etat-lieux.php`

**Modifications** :
- Ajout de variables pour tous les champs à copier
- Logique étendue pour copier les compteurs, clés avec observations, descriptions complètes
- Fonction de duplication des photos :
  ```php
  // For exit state: copy photos from entry state
  if ($type === 'sortie' && $etat_entree_id) {
      // Get all photos from entry state
      $stmt = $pdo->prepare("SELECT * FROM etat_lieux_photos WHERE etat_lieux_id = ?");
      $stmt->execute([$etat_entree_id]);
      $entry_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      foreach ($entry_photos as $photo) {
          // Copy file and create new record
          copy($source_path, $dest_path);
          // Insert new photo record
      }
  }
  ```

### `/admin-v2/edit-etat-lieux.php`

**Modifications** :
- Ajout d'un message d'information pour les états de sortie
- Message Bootstrap avec classe `alert-info`
- Condition `if ($isSortie)` pour affichage conditionnel

## Validation et Tests

### Test de Validation Automatique

Exécuter le script : `php test-etat-sortie-functionality.php`

**Vérifications effectuées** :
- ✅ Copie des compteurs
- ✅ Copie des clés (avec cles_autre)
- ✅ Copie des observations
- ✅ Copie des photos
- ✅ Message informatif dans le formulaire
- ✅ Gestion des sauts de ligne dans le PDF

### Test Manuel

1. **Créer un état d'entrée complet** :
   ```
   - Date : 2024-01-15
   - Compteur électrique : 12345
   - Compteur eau : 67890
   - Clés appart : 2
   - Clés boîte : 1
   - Description pièce : "État neuf. Murs propres."
   - Photos : 3 photos de différentes catégories
   ```

2. **Créer un état de sortie** :
   - Vérifier que tous les champs sont pré-remplis
   - Vérifier que les 3 photos sont présentes
   - Modifier quelques champs
   - Générer le PDF

3. **Vérifier le PDF** :
   - Type = "DE SORTIE"
   - Données modifiées apparaissent
   - Sauts de ligne corrects

## Contraintes Techniques Respectées

✅ **PHP 7.4** : Code compatible (pas de typage strict PHP 8)  
✅ **TCPDF** : Génération PDF existante réutilisée  
✅ **Sauts de ligne** : Gestion via `<br>` comme spécifié  
✅ **Base de données** : Utilisation de la structure existante  
✅ **Sécurité** : Validation des inputs, échappement HTML, prepared statements

## Évolutions Futures Possibles

1. **Comparaison visuelle** :
   - Afficher côte à côte entrée vs sortie
   - Surligner les différences

2. **Export combiné** :
   - Générer un PDF unique avec entrée et sortie
   - Tableau de synthèse des dégradations

3. **Tableau des dégradations** :
   - Liste automatique des différences constatées
   - Calcul automatique des retenues sur dépôt de garantie

4. **Intégration photos dans PDF** :
   - Actuellement photos stockées mais pas dans PDF
   - Ajouter galerie photo dans le document PDF généré

## Support et Documentation

Pour toute question ou problème :
1. Consulter les logs d'erreur PHP
2. Vérifier les permissions sur `uploads/etats_lieux/`
3. S'assurer que les migrations sont appliquées
4. Vérifier que l'état d'entrée existe avant de créer la sortie
