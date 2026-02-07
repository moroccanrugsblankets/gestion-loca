# Configuration des Templates d'État des Lieux - Guide Visuel

## Page: /admin-v2/etat-lieux-configuration.php

### Vue d'ensemble de la page

La page de configuration contient maintenant trois sections principales :

---

## 1️⃣ Section : Signature Électronique de la Société
*(Existant - Inchangé)*

```
┌─────────────────────────────────────────────────────────────┐
│ 🖊️ Signature Électronique de la Société (États des Lieux)   │
├─────────────────────────────────────────────────────────────┤
│ Téléchargez l'image de la signature...                      │
│                                                              │
│ [Choisir un fichier]                                        │
│ ☐ Activer l'ajout automatique de la signature               │
│                                                              │
│ [📤 Télécharger la signature] [🗑️ Supprimer]                │
│                                                              │
│ Aperçu actuel:                                              │
│ ┌────────────────┐                                          │
│ │  [Signature]   │                                          │
│ └────────────────┘                                          │
└─────────────────────────────────────────────────────────────┘
```

---

## 2️⃣ NOUVEAU : Template État des Lieux d'Entrée
*(Section verte)*

```
┌─────────────────────────────────────────────────────────────┐
│ 🟢 Template État des Lieux d'Entrée                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ ℹ️ Variables disponibles                                     │
│ Cliquez sur une variable pour la copier...                  │
│                                                              │
│ {{reference}} {{type}} {{type_label}} {{date_etat}}        │
│ {{adresse}} {{appartement}} {{type_logement}} {{surface}}   │
│ {{bailleur_nom}} {{bailleur_representant}}                  │
│ {{locataires_info}} {{compteur_electricite}}                │
│ {{compteur_eau_froide}} {{cles_appartement}}                │
│ {{cles_boite_lettres}} {{cles_autre}} {{cles_total}}        │
│ {{piece_principale}} {{coin_cuisine}} {{salle_eau_wc}}      │
│ {{etat_general}} {{observations}} {{lieu_signature}}        │
│ {{date_signature}} {{signatures_table}} {{signature_agence}}│
│                                                              │
│ Template HTML de l'État des Lieux d'Entrée                  │
│ ┌──────────────────────────────────────────────────────┐   │
│ │ [TinyMCE WYSIWYG Editor]                             │   │
│ │                                                       │   │
│ │ <!DOCTYPE html>                                       │   │
│ │ <html lang="fr">                                      │   │
│ │   <head>                                              │   │
│ │     <title>État des lieux {{type}}</title>           │   │
│ │   </head>                                             │   │
│ │   <body>                                              │   │
│ │     ...                                               │   │
│ │                                                       │   │
│ │                                                       │   │
│ └──────────────────────────────────────────────────────┘   │
│                                                              │
│ [✅ Enregistrer le Template d'Entrée] [👁️ Prévisualiser]    │
│ [↺ Réinitialiser par défaut]                               │
└─────────────────────────────────────────────────────────────┘
```

**Couleur du bouton d'enregistrement :** Vert (btn-success)

---

## 3️⃣ NOUVEAU : Template État des Lieux de Sortie
*(Section rouge)*

```
┌─────────────────────────────────────────────────────────────┐
│ 🔴 Template État des Lieux de Sortie                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ ℹ️ Variables disponibles                                     │
│ Cliquez sur une variable pour la copier...                  │
│                                                              │
│ {{reference}} {{type}} {{type_label}} {{date_etat}}        │
│ {{adresse}} {{appartement}} {{type_logement}} {{surface}}   │
│ {{bailleur_nom}} {{bailleur_representant}}                  │
│ {{locataires_info}} {{compteur_electricite}}                │
│ {{compteur_eau_froide}} {{cles_appartement}}                │
│ {{cles_boite_lettres}} {{cles_autre}} {{cles_total}}        │
│ {{piece_principale}} {{coin_cuisine}} {{salle_eau_wc}}      │
│ {{etat_general}} {{observations}} {{lieu_signature}}        │
│ {{date_signature}} {{signatures_table}} {{signature_agence}}│
│                                                              │
│ Template HTML de l'État des Lieux de Sortie                 │
│ ┌──────────────────────────────────────────────────────┐   │
│ │ [TinyMCE WYSIWYG Editor]                             │   │
│ │                                                       │   │
│ │ <!DOCTYPE html>                                       │   │
│ │ <html lang="fr">                                      │   │
│ │   <head>                                              │   │
│ │     <title>État des lieux {{type}}</title>           │   │
│ │   </head>                                             │   │
│ │   <body>                                              │   │
│ │     ...                                               │   │
│ │                                                       │   │
│ │                                                       │   │
│ └──────────────────────────────────────────────────────┘   │
│                                                              │
│ [🔴 Enregistrer le Template de Sortie] [👁️ Prévisualiser]   │
│ [↺ Réinitialiser par défaut]                               │
└─────────────────────────────────────────────────────────────┘
```

**Couleur du bouton d'enregistrement :** Rouge (btn-danger)

---

## Cartes de Prévisualisation (masquées par défaut)

### Prévisualisation - État d'Entrée
```
┌─────────────────────────────────────────────────────────────┐
│ 👁️ Prévisualisation - État d'Entrée                         │
├─────────────────────────────────────────────────────────────┤
│ [Contenu HTML rendu ici après clic sur "Prévisualiser"]    │
│                                                              │
│ ...                                                          │
└─────────────────────────────────────────────────────────────┘
```

### Prévisualisation - État de Sortie
```
┌─────────────────────────────────────────────────────────────┐
│ 👁️ Prévisualisation - État de Sortie                        │
├─────────────────────────────────────────────────────────────┤
│ [Contenu HTML rendu ici après clic sur "Prévisualiser"]    │
│                                                              │
│ ...                                                          │
└─────────────────────────────────────────────────────────────┘
```

---

## Différences Visuelles Clés

| Élément | État d'Entrée | État de Sortie |
|---------|---------------|----------------|
| **Icône** | 🟢 bi-box-arrow-in-right (vert) | 🔴 bi-box-arrow-right (rouge) |
| **Titre** | Template État des Lieux d'Entrée | Template État des Lieux de Sortie |
| **Éditeur ID** | `#template_html` | `#template_html_sortie` |
| **Action POST** | `update_template` | `update_template_sortie` |
| **Bouton Enregistrer** | Vert (btn-success) | Rouge (btn-danger) |
| **Carte Preview** | `#preview-card` | `#preview-card-sortie` |

---

## Interactions Utilisateur

### 1. Copier une Variable
- **Action :** Cliquer sur un badge de variable (ex: `{{reference}}`)
- **Résultat :** Variable copiée dans le presse-papier
- **Feedback :** Notification "Copié !" apparaît brièvement au centre de l'écran

### 2. Éditer le Template
- **Action :** Modifier le HTML dans l'éditeur TinyMCE
- **Outils disponibles :**
  - Formatage (gras, italique, couleur)
  - Alignement (gauche, centre, droite, justifié)
  - Listes (puces, numérotées)
  - Tableaux
  - Code source (pour édition HTML directe)
  - Prévisualisation

### 3. Prévisualiser
- **Action :** Cliquer sur "Prévisualiser"
- **Résultat :** La carte de prévisualisation apparaît en dessous avec le HTML rendu
- **Scroll :** La page défile automatiquement vers la prévisualisation

### 4. Enregistrer
- **Action :** Cliquer sur "Enregistrer le Template d'Entrée/Sortie"
- **Résultat :** 
  - Template sauvegardé dans la base de données
  - Message de succès : "Template d'état des lieux d'entrée/sortie mis à jour avec succès"
  - Redirection vers la même page

### 5. Réinitialiser
- **Action :** Cliquer sur "Réinitialiser par défaut"
- **Confirmation :** Dialog "Êtes-vous sûr...?"
- **Résultat :** Template restauré à la version par défaut

---

## Messages d'État

### Succès (vert)
```
✅ Template d'état des lieux d'entrée mis à jour avec succès
```
```
✅ Template d'état des lieux de sortie mis à jour avec succès
```

### Erreur (rouge)
```
⚠️ Une erreur s'est produite lors de la mise à jour du template
```

---

## Comportement de Génération PDF

### Pour un État d'Entrée
1. Système charge `etat_lieux_template_html`
2. Si absent, utilise le template par défaut
3. Variables remplacées avec données réelles
4. PDF généré

### Pour un État de Sortie
1. Système essaie de charger `etat_lieux_sortie_template_html`
2. Si absent, charge `etat_lieux_template_html` (fallback)
3. Si toujours absent, utilise le template par défaut
4. Variables remplacées avec données réelles
5. PDF généré

---

## Compatibilité

✅ **Rétrocompatibilité totale**
- Les installations existantes continuent de fonctionner
- Les états d'entrée utilisent le template existant
- Les états de sortie peuvent partager le même template jusqu'à personnalisation

✅ **Migration progressive**
- L'administrateur peut d'abord personnaliser l'entrée
- Puis personnaliser la sortie quand nécessaire
- Aucune action requise si les deux templates doivent être identiques
