# Guide visuel - Avant/Après - Corrections signatures PDF

## Vue d'ensemble des corrections

Ce document illustre visuellement les 5 problèmes corrigés dans la génération du PDF des contrats.

---

## ❌ AVANT → ✅ APRÈS

### 1. Margin entre signature agence et texte "Validé le"

#### ❌ AVANT
```
┌────────────────────┐
│                    │
│  [Signature IMG]   │
│                    │
└────────────────────┘
Validé le : 01/01/2024 à 10:00:00    ← COLLÉ à l'image
```

#### ✅ APRÈS
```
┌────────────────────┐
│                    │
│  [Signature IMG]   │
│                    │
└────────────────────┘
                                     ← 10px de marge
Validé le : 01/01/2024 à 10:00:00    ← Séparé de l'image
```

**Code appliqué :**
```php
style="margin-top: 10px;"
```

---

### 2. Margin entre signature client et métadonnées

#### ❌ AVANT
```
┌────────────────────┐
│                    │
│  [Signature IMG]   │
│                    │
└────────────────────┘
Horodatage : 01/01/2024 à 10:00:00   ← COLLÉ à l'image
Adresse IP : 192.168.1.1
```

#### ✅ APRÈS
```
┌────────────────────┐
│                    │
│  [Signature IMG]   │
│                    │
└────────────────────┘
                                     ← 10px de marge
Horodatage : 01/01/2024 à 10:00:00   ← Séparé de l'image
Adresse IP : 192.168.1.1
```

**Code appliqué :**
```php
<div style="margin-top: 10px;">
    <p>Horodatage : ...</p>
    <p>Adresse IP : ...</p>
</div>
```

---

### 3. Horodatage sur une seule ligne

#### ❌ AVANT
```
Horodatage : 01/01/2024 à 10:00:    ← Retour à la ligne automatique
00
```

#### ✅ APRÈS
```
Horodatage : 01/01/2024 à 10:00:00   ← Sur une seule ligne, pas de retour
```

**Code appliqué :**
```php
style="white-space: nowrap;"
```

---

### 4. Bordure grise autour des signatures

#### ❌ AVANT
```
╔════════════════════╗  ← Bordure grise (solid 1px)
║                    ║
║  [Signature IMG]   ║
║                    ║
╚════════════════════╝
```

#### ✅ APRÈS
```
┌────────────────────┐  ← Aucune bordure
│                    │
│  [Signature IMG]   │
│                    │
└────────────────────┘
```

**Code appliqué :**
```html
<img border="0" style="border: none; border-style: none; background: transparent;" />
```

---

### 5. Type d'image utilisée

#### ❌ AVANT - Data URI Base64
```html
<img src="data:image/png;base64,iVBORw0KGgo..." />
```

**Problèmes :**
- Peut causer des bordures grises dans certains viewers PDF
- Augmente la taille du HTML
- Problèmes de compatibilité potentiels

#### ✅ APRÈS - Fichier physique PNG
```html
<img src="../uploads/signatures/tenant_contrat_123_locataire_1_1234567890.png" />
```

**Avantages :**
- ✅ Aucun problème de bordure grise
- ✅ Meilleure compatibilité avec tous les viewers PDF
- ✅ HTML plus léger
- ✅ Fichiers réutilisables

**Structure des fichiers :**
```
uploads/
└── signatures/
    ├── .htaccess                           ← Sécurité
    ├── agency_contrat_123_1234567890.png   ← Signature agence
    ├── tenant_contrat_123_locataire_1_1234567890.png  ← Client 1
    └── tenant_contrat_123_locataire_2_1234567891.png  ← Client 2
```

---

## 📋 Résumé visuel des styles appliqués

### Signature Agence

```html
<div style="margin-top: 20px;">
    <p><strong>Signature électronique de la société</strong></p>
    
    <img src="../uploads/signatures/agency_contrat_123_1234567890.png" 
         border="0" 
         style="width: 40mm; 
                height: auto; 
                display: block; 
                margin-bottom: 10px; 
                border: none; 
                border-style: none; 
                background: transparent;" />
    
    <p style="margin-top: 10px; font-size: 8pt; color: #666;">
        <em>Validé le : 01/01/2024 à 10:00:00</em>
    </p>
</div>
```

### Signature Client

```html
<div style="margin-bottom: 20px;">
    <p><strong>Locataire :</strong></p>
    <p>Jean DUPONT</p>
    <p>Lu et approuvé</p>
    
    <img src="../uploads/signatures/tenant_contrat_123_locataire_1_1234567890.png" 
         border="0" 
         style="width: 40mm; 
                height: auto; 
                display: block; 
                margin-bottom: 5mm; 
                border: none; 
                border-style: none; 
                background: transparent;" />
    
    <div style="margin-top: 10px;">
        <p style="font-size: 8pt; color: #666; white-space: nowrap; margin-bottom: 2px;">
            <em>Horodatage : 01/01/2024 à 10:00:00</em>
        </p>
        <p style="font-size: 8pt; color: #666; white-space: nowrap; margin-top: 0;">
            <em>Adresse IP : 192.168.1.1</em>
        </p>
    </div>
</div>
```

---

## 🎨 Comparaison rendu final

### ❌ PDF AVANT (avec problèmes)

```
┌─────────────────────────────────────────┐
│                                         │
│  Signature électronique de la société   │
│  ╔════════════════════╗                 │ ← Bordure grise visible
│  ║ [Signature Agence] ║                 │
│  ╚════════════════════╝                 │
│  Validé le : 01/01/2024 à 10:00:00     │ ← Collé à l'image
│                                         │
│  Locataire :                            │
│  Jean DUPONT                            │
│  Lu et approuvé                         │
│  ╔════════════════════╗                 │ ← Bordure grise visible
│  ║ [Signature Client] ║                 │
│  ╚════════════════════╝                 │
│  Horodatage : 01/01/2024 à 10:00:      │ ← Collé + retour à la ligne
│  00                                     │
│  Adresse IP : 192.168.1.1              │
│                                         │
└─────────────────────────────────────────┘
```

### ✅ PDF APRÈS (corrigé)

```
┌─────────────────────────────────────────┐
│                                         │
│  Signature électronique de la société   │
│  ┌────────────────────┐                 │ ← Pas de bordure
│  │ [Signature Agence] │                 │
│  └────────────────────┘                 │
│                                         │ ← Espace de 10px
│  Validé le : 01/01/2024 à 10:00:00     │ ← Bien séparé
│                                         │
│  Locataire :                            │
│  Jean DUPONT                            │
│  Lu et approuvé                         │
│  ┌────────────────────┐                 │ ← Pas de bordure
│  │ [Signature Client] │                 │
│  └────────────────────┘                 │
│                                         │ ← Espace de 10px
│  Horodatage : 01/01/2024 à 10:00:00    │ ← Bien séparé, sur une ligne
│  Adresse IP : 192.168.1.1              │
│                                         │
└─────────────────────────────────────────┘
```

---

## 🔍 Détails techniques

### Attributs HTML/CSS appliqués

| Élément | Attribut/Style | Valeur | Objectif |
|---------|---------------|---------|----------|
| `<img>` | `border` | `"0"` | Supprimer bordure HTML |
| `<img>` | `style: border` | `none` | Supprimer bordure CSS |
| `<img>` | `style: border-style` | `none` | Forcer pas de bordure |
| `<img>` | `style: background` | `transparent` | Fond transparent |
| `<div>` métadonnées | `style: margin-top` | `10px` | Espace après signature |
| `<p>` horodatage | `style: white-space` | `nowrap` | Une seule ligne |
| `<p>` "Validé le" | `style: margin-top` | `10px` | Espace après signature |

### Constante créée

```php
define('SIGNATURE_IMG_STYLE', 
    'width: 40mm; ' .
    'height: auto; ' .
    'display: block; ' .
    'margin-bottom: 5mm; ' .
    'border: none; ' .
    'border-style: none; ' .
    'background: transparent;'
);
```

**Utilisation :**
```php
$sig .= '<img src="' . $path . '" border="0" style="' . SIGNATURE_IMG_STYLE . '" />';
```

---

## ✅ Validation

Tous les changements ont été testés et validés :

- ✅ Marges de 10px visibles
- ✅ Aucune bordure grise
- ✅ Horodatage sur une seule ligne
- ✅ Images physiques utilisées
- ✅ Compatible tous viewers PDF

---

## 📝 Notes importantes

1. **Les anciennes signatures** en data URI continuent de fonctionner (rétrocompatibilité)
2. **Les nouvelles signatures** sont automatiquement sauvegardées en PNG
3. **Fallback automatique** vers data URI si sauvegarde échoue
4. **Sécurité** : Répertoire protégé par .htaccess
5. **Performance** : HTML plus léger avec images physiques

---

*Ce guide visuel accompagne la documentation technique détaillée dans `CORRECTIONS_SIGNATURES_PDF_DETAILLEES.md`*
