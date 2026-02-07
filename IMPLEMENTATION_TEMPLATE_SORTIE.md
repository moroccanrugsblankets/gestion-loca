# Implémentation Template État des Lieux de Sortie

## 📋 Résumé

Ce PR ajoute un template HTML dédié pour les **États des Lieux de Sortie** incluant tous les champs spécifiques à la sortie, et pas uniquement les mêmes champs que l'entrée.

## ✅ Problème Résolu

**Issue originale :**
> il faut ajouter que "Template HTML de l'État des Lieux de Sortie" soit basée sur le formulaire de "État des lieux de sortie"
> il faut ajouter les autres champs sur le pdf et pas garder seulement les memes champs que l'entrée

**Solution :**
- Nouveau template HTML complet pour les états des lieux de sortie
- Tous les champs sortie-spécifiques sont maintenant inclus dans le PDF
- Les sections se génèrent dynamiquement selon les données disponibles

## 🎯 Changements Apportés

### 1. Nouveau Template (`includes/etat-lieux-template.php`)

Ajout de la fonction `getDefaultExitEtatLieuxTemplate()` qui inclut :

#### Sections communes (Entrée & Sortie)
1. Informations générales
2. Bien loué
3. Parties
4. Relevé des compteurs
5. Remise des clés
6. Description de l'état du logement

#### Sections spécifiques Sortie
7. **Dépôt de garantie** (conditionnelle)
   - Statut : Restitution totale/partielle ou retenue totale
   - Montant retenu
   - Motif de la retenue

8. **Bilan du logement** (conditionnelle)
   - Tableau dynamique des dégradations
   - Colonnes : Poste/Équipement, Commentaires, Valeur (€), Montant dû (€)
   - Totaux automatiques
   - Commentaires généraux

9. Signatures (numéro adaptatif selon sections présentes)

### 2. Variables Template Ajoutées

#### Conformité et Dégradations
- `{{cles_conformite}}` - Badge conforme/non conforme pour les clés
- `{{cles_observations_section}}` - Section observations clés (conditionnelle)
- `{{etat_general_conforme}}` - Badge conformité état général
- `{{degradations_section}}` - Section dégradations (conditionnelle)

#### Sections Dynamiques
- `{{depot_garantie_section}}` - Section complète dépôt de garantie
- `{{bilan_logement_section}}` - Section complète bilan du logement
- `{{signatures_section_number}}` - Numéro de section signatures (7, 8, ou 9)

### 3. Génération PDF (`pdf/generate-etat-lieux.php`)

#### Améliorations
- Détection automatique du type (entrée/sortie)
- Chargement du bon template selon le type
- Fonction helper `convertAndEscapeText()` pour traiter les textes
- Génération du tableau "Bilan du logement" depuis JSON
- Numérotation dynamique des sections
- Badges de conformité avec styles CSS

#### Sécurité
- Tous les textes échappés via `htmlspecialchars()`
- Validation des données JSON
- Filtrage des lignes vides dans le bilan

## 📊 Statistiques

- **Template Entrée** : ~5,784 caractères
- **Template Sortie** : ~7,332 caractères
- **Différence** : +1,548 caractères (+26.8%)
- **Nouveaux champs** : 7 variables sortie-spécifiques
- **Champs communs préservés** : 8/8

## 🧪 Tests Effectués

✅ Vérification de l'existence de la fonction `getDefaultExitEtatLieuxTemplate()`
✅ Tous les placeholders sortie présents (7/7)
✅ Tous les placeholders communs préservés (8/8)
✅ Syntaxe PHP validée (pas d'erreurs)
✅ Code review effectué et feedback intégré
✅ Sécurité vérifiée (CodeQL - aucun problème)

## 🔄 Rétrocompatibilité

- Le template d'entrée reste inchangé
- Fallback automatique vers template entrée si template sortie non disponible
- Aucune modification des données existantes
- Compatible avec toutes les fonctionnalités existantes

## 📝 Utilisation

### Génération Automatique
Le système choisit automatiquement le bon template :
```php
$pdfPath = generateEtatDesLieuxPDF($contratId, 'sortie');
```

### Personnalisation
Pour personnaliser le template sortie, modifier la valeur en base :
```sql
UPDATE parametres 
SET valeur = '<html>...</html>' 
WHERE cle = 'etat_lieux_sortie_template_html';
```

## 🎨 Exemple de Rendu

### Section Bilan du Logement
```
┌─────────────────────────────────────────────────────────────────┐
│ Poste/Équipement │ Commentaires    │ Valeur (€) │ Montant dû │
├──────────────────┼─────────────────┼────────────┼────────────┤
│ Peinture salon   │ Traces sur mur  │ 200.00     │ 150.00     │
│ Porte cuisine    │ Rayures         │ 100.00     │  80.00     │
├──────────────────┴─────────────────┼────────────┼────────────┤
│ Total des frais constatés :        │ 300.00 €   │ 230.00 €   │
└────────────────────────────────────┴────────────┴────────────┘
```

### Badge de Conformité
```html
<span class="conformity-badge conformity-conforme">CONFORME</span>
<span class="conformity-badge conformity-non-conforme">NON CONFORME</span>
```

## 🔒 Sécurité

- Échappement HTML de toutes les entrées utilisateur
- Validation des types de données
- Filtrage des données JSON
- Aucune injection possible
- CodeQL : 0 vulnérabilité détectée

## 📚 Fichiers Modifiés

1. `includes/etat-lieux-template.php` - Nouveau template sortie
2. `pdf/generate-etat-lieux.php` - Variables et génération PDF
3. `.gitignore` - Exclusion fichiers de test

## 🚀 Prochaines Étapes

Le template est maintenant prêt à être utilisé en production. Pour générer un PDF de sortie avec toutes les sections :

1. Créer un état des lieux de type "sortie" via l'interface admin
2. Remplir les champs sortie-spécifiques (bilan, dépôt de garantie)
3. Finaliser l'état des lieux
4. Le PDF généré incluera automatiquement toutes les sections appropriées

## ✨ Résultat

Le PDF d'état des lieux de sortie contient maintenant **TOUS** les champs du formulaire :
- ✅ Conformité des clés
- ✅ Conformité état général  
- ✅ Dégradations détaillées
- ✅ Dépôt de garantie (statut, montant, motif)
- ✅ Bilan du logement (tableau complet avec totaux)
- ✅ Commentaires généraux

**Et pas seulement les mêmes champs que l'entrée !** 🎉
