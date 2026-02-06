# Comparaison Visuelle : HTML vs PDF - Problème de Bordures TCPDF

## Vue d'ensemble

Ce document illustre visuellement la différence entre le rendu HTML (correct) et le rendu PDF TCPDF (avec bordures indésirables).

---

## 📊 État des Lieux - Augmentation des Tailles de Signatures

### ❌ AVANT (Trop petit)

```
Signature Style: max-width: 15mm; max-height: 8mm;
```

**Rendu approximatif :**
```
┌────────────────────────────────────────┐
│  Le bailleur :                         │
│                                        │
│  ┌───┐   ← 15mm × 8mm (minuscule!)   │
│  │sig│                                │
│  └───┘                                │
│                                        │
│  Fait à Annemasse                      │
│  Le 06/02/2026                         │
│  MY INVEST IMMOBILIER                  │
└────────────────────────────────────────┘
```

### ✅ APRÈS (Augmenté)

```
Signature Style: max-width: 50mm; max-height: 25mm;
```

**Rendu approximatif :**
```
┌────────────────────────────────────────┐
│  Le bailleur :                         │
│                                        │
│  ┌──────────────────┐                 │
│  │                  │  ← 50mm × 25mm  │
│  │   [signature]    │                 │
│  │                  │  (233% plus     │
│  └──────────────────┘   grand!)       │
│                                        │
│  Fait à Annemasse                      │
│  Le 06/02/2026                         │
│  MY INVEST IMMOBILIER                  │
└────────────────────────────────────────┘
```

**Amélioration :** +233% en largeur et hauteur

---

## 🔍 Problème de Bordures : HTML vs PDF

### Cas 1 : Signature dans Contrat

#### Vue HTML (via test-html-preview-contrat.php)

```
┌──────────────────────────────────────────────────┐
│  Signatures                                       │
├──────────────────────────────────────────────────┤
│                                                  │
│  Le bailleur :                                   │
│                                                  │
│  ┌─────────────────────┐  ← PAS DE BORDURE ✅  │
│  │                     │                        │
│  │   [signature clean] │                        │
│  │                     │                        │
│  └─────────────────────┘                        │
│                                                  │
│  Validé le : 06/02/2026 à 14:30:00             │
│  MY INVEST IMMOBILIER                            │
└──────────────────────────────────────────────────┘
```

#### PDF Final (après TCPDF)

```
┌──────────────────────────────────────────────────┐
│  Signatures                                       │
├──────────────────────────────────────────────────┤
│                                                  │
│  Le bailleur :                                   │
│                                                  │
│  ╔═════════════════════╗  ← BORDURE GRISE ! ❌  │
│  ║                     ║                        │
│  ║   [signature]       ║  (ajoutée par TCPDF)  │
│  ║                     ║                        │
│  ╚═════════════════════╝                        │
│                                                  │
│  Validé le : 06/02/2026 à 14:30:00             │
│  MY INVEST IMMOBILIER                            │
└──────────────────────────────────────────────────┘
```

---

### Cas 2 : Tableau de Signatures dans Bail

#### Vue HTML (via test-html-preview-bail.php)

```
┌─────────────────────────────────────────────────────────────────┐
│  Signatures                                                      │
│                                                                  │
│  ┌───────────────────┬───────────────────┬───────────────────┐  │
│  │ Le bailleur       │ Locataire 1       │ Locataire 2       │  │
│  │                   │                   │                   │  │
│  │ ┌───────────┐     │ ┌───────────┐     │ ┌───────────┐     │  │
│  │ │ [sign.]   │     │ │ [sign.]   │     │ │ [sign.]   │     │  │
│  │ └───────────┘     │ └───────────┘     │ └───────────┘     │  │
│  │                   │                   │                   │  │
│  └───────────────────┴───────────────────┴───────────────────┘  │
│                                                                  │
│  ↑ PAS de bordures visibles ✅                                  │
└─────────────────────────────────────────────────────────────────┘
```

#### PDF Final (après TCPDF)

```
┌─────────────────────────────────────────────────────────────────┐
│  Signatures                                                      │
│                                                                  │
│  ╔═══════════════════╦═══════════════════╦═══════════════════╗  │
│  ║ Le bailleur       ║ Locataire 1       ║ Locataire 2       ║  │
│  ║                   ║                   ║                   ║  │
│  ║ ╔═══════════╗     ║ ╔═══════════╗     ║ ╔═══════════╗     ║  │
│  ║ ║ [sign.]   ║     ║ ║ [sign.]   ║     ║ ║ [sign.]   ║     ║  │
│  ║ ╚═══════════╝     ║ ╚═══════════╝     ║ ╚═══════════╝     ║  │
│  ║                   ║                   ║                   ║  │
│  ╚═══════════════════╩═══════════════════╩═══════════════════╝  │
│                                                                  │
│  ↑ Bordures PARTOUT ajoutées par TCPDF ❌                       │
└─────────────────────────────────────────────────────────────────┘
```

**Note :** Les cellules du tableau ET les images ont des bordures dans le PDF !

---

### Cas 3 : État des Lieux

#### Vue HTML (via test-html-preview-etat-lieux.php)

```
┌──────────────────────────────────────────────────┐
│  Signatures                                       │
│                                                  │
│  Le bailleur :              Locataire :          │
│                                                  │
│  ┌──────────────┐           ┌──────────────┐    │
│  │              │           │              │    │
│  │ [signature]  │           │ [signature]  │    │
│  │  50mm×25mm   │           │  50mm×25mm   │    │
│  └──────────────┘           └──────────────┘    │
│                                                  │
│  ↑ Grandes signatures, pas de bordures ✅        │
└──────────────────────────────────────────────────┘
```

#### PDF Final (après TCPDF)

```
┌──────────────────────────────────────────────────┐
│  Signatures                                       │
│                                                  │
│  Le bailleur :              Locataire :          │
│                                                  │
│  ╔══════════════╗           ╔══════════════╗    │
│  ║              ║           ║              ║    │
│  ║ [signature]  ║           ║ [signature]  ║    │
│  ║  50mm×25mm   ║           ║  50mm×25mm   ║    │
│  ╚══════════════╝           ╚══════════════╝    │
│                                                  │
│  ↑ Grandes signatures MAIS bordures grises ❌    │
└──────────────────────────────────────────────────┘
```

---

## 📈 Tableau Comparatif des Rendus

| Aspect | HTML Preview | PDF Final (TCPDF) |
|--------|--------------|-------------------|
| **Bordures sur images** | ✅ Aucune | ❌ Bordures grises 1-2px |
| **Bordures sur tableaux** | ✅ Aucune (border:0) | ❌ Bordures noires visibles |
| **Transparence PNG** | ✅ Préservée | ⚠️ Parfois perdue |
| **Tailles signatures** | ✅ Correctes | ✅ Correctes |
| **Aspect général** | ✅ Professionnel | ❌ Bordures gênantes |

---

## 🔧 CSS Appliqué (Identique dans HTML et PDF)

Voici le style CSS complet appliqué aux signatures :

```css
border: 0;
border-width: 0;
border-style: none;
border-color: transparent;
outline: none;
outline-width: 0;
padding: 0;
background: transparent;
box-shadow: none;
```

**Résultat :**
- ✅ **Dans le navigateur (HTML)** : Fonctionne parfaitement, aucune bordure
- ❌ **Dans TCPDF (PDF)** : Ignoré partiellement, bordures ajoutées

---

## 💡 Conclusion Visuelle

### Ce qui fonctionne ✅
1. Les tailles des signatures sont correctes et bien visibles
2. Le HTML généré est propre et sans bordures
3. Les propriétés CSS sont toutes présentes

### Ce qui ne fonctionne pas ❌
1. TCPDF ignore les propriétés CSS anti-bordure
2. TCPDF ajoute ses propres bordures par défaut
3. Les tableaux sont également affectés

### Preuve
Comparez :
1. `http://localhost/test-html-preview-contrat.php?id=51` → Parfait ✅
2. PDF généré → Bordures présentes ❌

**→ Le problème est clairement dans le moteur de rendu TCPDF**

---

## 🎯 Solution Requise

Pour éliminer complètement les bordures, il faut :

1. **Abandonner les balises HTML `<img>`** dans le HTML passé à `writeHTML()`
2. **Utiliser `$pdf->Image()` natif** après `writeHTML()` avec le paramètre `border=0`

Voir `SOLUTION_BORDURES_TCPDF.md` et `AVANT_APRES_SIGNATURES_TCPDF.md` pour les détails d'implémentation.

---

## 📋 Fichiers de Test

### Tester vous-même

1. **HTML Preview (sans bordures)**
   ```
   http://localhost/test-html-preview-contrat.php?id=51
   http://localhost/test-html-preview-bail.php?id=51
   http://localhost/test-html-preview-etat-lieux.php?id=51&type=entree
   ```

2. **Générer PDF (avec bordures)**
   ```php
   require_once 'pdf/generate-contrat-pdf.php';
   generateContratPDF(51);
   ```

3. **Comparer** les deux rendus pour confirmer le problème TCPDF

---

**Créé le :** 2026-02-06  
**Auteur :** GitHub Copilot  
**Status :** En Cours - Solution complète nécessite refonte avec $pdf->Image()
