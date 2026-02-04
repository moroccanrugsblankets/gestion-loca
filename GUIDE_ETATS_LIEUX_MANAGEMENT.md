# États des Lieux - Guide d'utilisation

## Fonctionnalités implémentées

Ce document décrit les nouvelles fonctionnalités de gestion des états des lieux.

### 1. Page de liste (etats-lieux.php)

**Avant:**
- Boutons "Voir" et "Télécharger" non fonctionnels (href="#")
- Impossible de consulter ou télécharger les états des lieux

**Après:**
- Bouton "👁 Voir" → Redirige vers la page de visualisation
- Bouton "📥 Télécharger" → Télécharge le PDF directement
- Chaque bouton affiche une info-bulle au survol

### 2. Page de visualisation (view-etat-lieux.php)

Nouvelle page permettant de:

#### Mode Lecture
- Afficher toutes les informations de l'état des lieux
- Voir les détails du contrat associé
- Voir les informations du locataire
- Voir les informations du logement
- Consulter l'état général et les observations

**Structure de la page:**
```
┌─────────────────────────────────────────┐
│ En-tête                                  │
│ - Titre: "État des lieux Entrée/Sortie" │
│ - Boutons: Retour | Modifier | PDF      │
└─────────────────────────────────────────┘

┌──────────────────┐  ┌──────────────────┐
│ Colonne Gauche   │  │ Colonne Droite   │
│                  │  │                  │
│ • Informations   │  │ • Logement       │
│   générales      │  │   - Adresse      │
│   - Type         │  │   - Type         │
│   - Date         │  │   - Surface      │
│   - Contrat      │  │                  │
│                  │  │ • Observations   │
│ • Locataire      │  │   - État général │
│   - Nom          │  │   - Observations │
│   - Email        │  │                  │
└──────────────────┘  └──────────────────┘
```

#### Mode Édition
- Modifier la date de l'état des lieux
- Modifier l'état général (zone de texte)
- Modifier les observations (zone de texte)
- Boutons: Annuler | Enregistrer

**Accès au mode édition:**
- Cliquer sur le bouton "✏️ Modifier" en haut de la page
- L'URL devient: `view-etat-lieux.php?id=X&edit=1`

### 3. Téléchargement PDF (download-etat-lieux.php)

Fonctionnalités:
- Génère le PDF à la volée en utilisant la fonction existante
- Nom de fichier sécurisé et descriptif
- Téléchargement automatique (pas d'affichage dans le navigateur)

**Format du nom de fichier:**
```
etat_lieux_{type}_{reference_contrat}.pdf
```

Exemple: `etat_lieux_entree_BAIL-2024-001.pdf`

### 4. Corrections techniques

#### Table de base de données
- Correction du nom de table: `etat_lieux` → `etats_lieux`
- 6 requêtes SQL corrigées dans `pdf/generate-etat-lieux.php`

#### Sécurité
- Validation de tous les paramètres d'entrée
- Protection XSS avec `htmlspecialchars()`
- Authentification obligatoire sur toutes les pages
- Sanitisation des noms de fichiers (espaces → underscores)
- Validation des dates

## Flux utilisateur

### Consulter un état des lieux

1. Aller sur `/admin-v2/etats-lieux.php`
2. Cliquer sur l'icône 👁 d'un état des lieux
3. Consulter les informations affichées

### Modifier un état des lieux

1. Sur la page de visualisation
2. Cliquer sur "✏️ Modifier"
3. Modifier les champs souhaités
4. Cliquer sur "✓ Enregistrer"
5. Message de confirmation affiché

### Télécharger le PDF

**Option 1:** Depuis la liste
1. Aller sur `/admin-v2/etats-lieux.php`
2. Cliquer sur l'icône 📥
3. Le PDF se télécharge automatiquement

**Option 2:** Depuis la page de visualisation
1. Afficher un état des lieux
2. Cliquer sur "📥 Télécharger PDF"
3. Le PDF se télécharge automatiquement

## Architecture des fichiers

```
admin-v2/
├── etats-lieux.php         (Liste - MODIFIÉ)
├── view-etat-lieux.php     (Visualisation/Édition - NOUVEAU)
├── download-etat-lieux.php (Téléchargement - NOUVEAU)
└── create-etat-lieux.php   (Création - EXISTANT)

pdf/
└── generate-etat-lieux.php (Génération PDF - CORRIGÉ)

tests/
└── test-etat-lieux-view-download.php (Tests - NOUVEAU)
```

## Tests

Un fichier de test complet a été créé: `test-etat-lieux-view-download.php`

**Exécution:**
```bash
php test-etat-lieux-view-download.php
```

**Tests effectués:**
1. Vérification de l'existence des fichiers
2. Validation de la syntaxe PHP
3. Vérification du contenu des pages
4. Vérification des mesures de sécurité
5. Vérification de la correction des tables

**Résultat:** ✅ Tous les tests passent

## Compatibilité

- PHP >= 7.2
- Base de données MySQL/MariaDB
- Navigateurs modernes (Chrome, Firefox, Safari, Edge)
- Bootstrap 5.3
- Bootstrap Icons 1.11

## Notes

- Les modifications sont minimales et ciblées
- Aucune modification de la base de données n'est requise
- Compatible avec le code existant
- Suit les conventions de codage du projet
