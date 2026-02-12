# Guide d'utilisation - Module Inventaire Amélioré

## Vue d'ensemble

Le module Inventaire a été amélioré pour correspondre exactement aux spécifications du cahier des charges. Il permet maintenant de gérer un inventaire complet avec des colonnes Entrée/Sortie et des cases à cocher pour l'état des équipements.

## Nouvelles fonctionnalités

### 1. Format de Grille Amélioré

L'inventaire utilise maintenant un format de grille avec:
- **Colonnes Entrée**: Nombre, Bon, D'usage, Mauvais
- **Colonnes Sortie**: Nombre, Bon, D'usage, Mauvais  
- **Colonne Commentaires**: Champ libre pour observations

### 2. Catégories d'Équipements

Le système comprend maintenant toutes les catégories spécifiées:

#### 3.1 État des pièces
- Entrée (Porte, Sonnette/interphone, Mur, Sol, Vitrage et volets, etc.)
- Séjour/salle à manger
- Cuisine
- Chambres 1, 2, 3
- Salles de bain 1, 2
- WC 1, 2
- Autres pièces

#### 3.2 Meubles
- Chaises (séjour, chambres, cuisine, autres)
- Tabourets, Canapés, Fauteuils
- Tables (séjour, chambres, cuisine, nuit, autres)
- Bureaux, Commodes, Armoires, Buffets
- Lits simples/doubles, Placards
- Lustres/plafonniers, Lampes/appliques

#### 3.3 Électroménager
- Réfrigérateur, Congélateur, Cuisinière, Four
- Four micro-ondes, Grille-pain, Bouilloire, Cafetière
- Lave-vaisselle, Lave-linge, Sèche-linge
- Télévision, Lecteur DVD, Vidéoprojecteur, Chaîne Hi-fi
- Fer à repasser, Aspirateur

#### 3.4 Équipements divers
- **Vaisselle**: Assiettes, verres, bols, tasses, etc.
- **Couverts**: Fourchettes, cuillères, couteaux, etc.
- **Ustensiles**: Pelles, seaux, torchons, planches, etc.
- **Literie et linge**: Matelas, draps, couettes, etc.
- **Linge de salle de bain**: Peignoirs, serviettes, etc.
- **Linge de maison**: Nappes, serviettes de table
- **Divers**: Coussins

## Installation et Configuration

### Étape 1: Exécuter la Migration

```bash
cd /path/to/gestion-loca
php migrations/046_populate_complete_inventaire_items.php
```

Cette migration crée un template complet dans la table `parametres` avec tous les équipements.

### Étape 2: Peupler les Équipements pour un Logement

Accédez à l'URL suivante (remplacez X par l'ID du logement):

```
/admin-v2/populate-logement-equipment.php?logement_id=X
```

Exemple: `/admin-v2/populate-logement-equipment.php?logement_id=1`

Cela va créer tous les équipements par défaut pour le logement sélectionné.

### Étape 3: Créer un Inventaire

1. Allez dans **Inventaire** dans le menu
2. Cliquez sur **Nouvel inventaire**
3. Sélectionnez:
   - Le logement (doit avoir des équipements définis)
   - Le type (Entrée ou Sortie)
   - La date de l'inventaire
4. Cliquez sur **Créer**

## Utilisation

### Inventaire d'Entrée

Lors de la création d'un inventaire d'entrée:

1. Remplissez les **colonnes Entrée** (les colonnes Sortie sont en lecture seule)
2. Pour chaque équipement:
   - Entrez le **Nombre** d'éléments présents
   - Cochez l'état: **Bon**, **D'usage**, ou **Mauvais**
   - Ajoutez des **Commentaires** si nécessaire
3. Complétez les **Observations générales**
4. Ajoutez le **Lieu de signature**
5. Les locataires peuvent signer électroniquement
6. Cliquez sur **Enregistrer le brouillon** ou **Finaliser et envoyer**

### Inventaire de Sortie

Lors de la création d'un inventaire de sortie:

1. Les **colonnes Entrée** affichent les données de l'inventaire d'entrée (lecture seule)
2. Remplissez les **colonnes Sortie** avec l'état actuel
3. Utilisez le bouton **Dupliquer Entrée → Sortie** pour:
   - Copier automatiquement les données d'entrée vers la sortie
   - Gagner du temps si l'état n'a pas changé
   - Puis modifier uniquement les éléments qui ont changé
4. Complétez et signez comme pour l'inventaire d'entrée

## Validation Automatique

Le système valide automatiquement que:
- Si une case d'état est cochée (Bon, D'usage, Mauvais), un nombre doit être renseigné
- Les signatures sont présentes avant finalisation
- La case "Certifié exact" est cochée

## Génération PDF

Le PDF généré reproduit fidèlement le format de grille avec:
- Colonnes Entrée et Sortie côte à côte
- Cases cochées visibles (☑) ou non cochées (☐)
- Toutes les catégories d'équipements
- Signatures des locataires et du bailleur
- Observations générales

## Exemples d'Utilisation

### Exemple 1: Meuble en Bon État à l'Entrée

```
Élément: Canapés
Entrée: Nombre=1, Bon=☑
Commentaires: "3 places, couleur gris anthracite"
```

### Exemple 2: Vaisselle avec Quantité

```
Élément: Grandes assiettes  
Entrée: Nombre=6, Bon=☑
Commentaires: "Service complet"
```

### Exemple 3: Équipement Détérioré à la Sortie

```
Élément: Réfrigérateur
Entrée: Nombre=1, Bon=☑
Sortie: Nombre=1, D'usage=☑
Commentaires: "Joint de porte usé"
```

## Conseils d'Utilisation

1. **Personnalisation par Logement**: Utilisez le menu Logements pour gérer les équipements spécifiques à chaque logement

2. **Duplication Intelligente**: Pour les inventaires de sortie, utilisez "Dupliquer Entrée → Sortie" puis modifiez uniquement ce qui a changé

3. **Photos**: Vous pouvez ajouter des photos pour documenter l'état des équipements

4. **Comparaison**: Utilisez la fonctionnalité de comparaison pour voir les différences entre entrée et sortie

5. **Archivage**: Les inventaires sont automatiquement archivés et liés au contrat

## Dépannage

### Le logement n'apparaît pas dans la liste

➜ Assurez-vous que le logement a des équipements définis. Utilisez `populate-logement-equipment.php` pour les créer.

### Les colonnes Entrée/Sortie sont vides

➜ Les données sont enregistrées au format JSON dans `equipements_data`. Vérifiez que le format inclut les clés `entree` et `sortie`.

### Le PDF ne génère pas correctement

➜ Vérifiez que TCPDF est installé via Composer: `composer install`

### Les cases à cocher ne s'affichent pas dans le PDF

➜ Le système utilise des symboles Unicode (☐ ☑). Assurez-vous que la police supporte ces caractères.

## Support

Pour toute question ou problème, consultez:
- La documentation complète dans `/README.md`
- Les exemples de code dans `/test-etat-lieux.php`
- L'interface de configuration dans `/admin-v2/inventaire-configuration.php`

---

**Version**: 1.0  
**Date**: Février 2026  
**Auteur**: Module Inventaire Amélioré
