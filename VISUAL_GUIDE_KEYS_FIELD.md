# Guide Visuel - Modifications du champ "Remise des clés"

## AVANT vs APRÈS

### 1. Formulaire d'édition (edit-etat-lieux.php)

#### AVANT (2 champs + total)
```
┌─────────────────────────────────────────────────────────────────┐
│ 3. Remise des clés                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  [Clés appartement]    [Clés boîte lettres]    [Total]         │
│       col-md-4              col-md-4           col-md-4         │
│      [    2    ]           [    1    ]        [   3   ]         │
│                                                 (readonly)       │
└─────────────────────────────────────────────────────────────────┘
```

#### APRÈS (3 champs + total)
```
┌─────────────────────────────────────────────────────────────────┐
│ 3. Remise des clés                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  [Clés appart]  [Clés boîte]   [Autre]      [Total]           │
│    col-md-3       col-md-3     col-md-3     col-md-3           │
│    [    2    ]   [    1    ]  [    1   ]   [   4   ]          │
│                                              (readonly)          │
└─────────────────────────────────────────────────────────────────┘
```

**Changements visuels:**
- Layout changé de 3 colonnes (col-md-4) à 4 colonnes (col-md-3)
- Nouveau champ "Autre" ajouté entre "Clés boîte aux lettres" et "Total"
- Le total se calcule automatiquement: 2 + 1 + 1 = 4

---

### 2. PDF État des lieux d'entrée

#### AVANT
```
3. REMISE DES CLÉS
┌────────────────────────────────┬──────────────────┐
│ Type de clé                    │ Nombre remis     │
├────────────────────────────────┼──────────────────┤
│ Clés de l'appartement          │ 2                │
├────────────────────────────────┼──────────────────┤
│ Clés de la boîte aux lettres   │ 1                │
├────────────────────────────────┼──────────────────┤
│ TOTAL                          │ 3                │
└────────────────────────────────┴──────────────────┘
```

#### APRÈS
```
3. REMISE DES CLÉS
┌────────────────────────────────┬──────────────────┐
│ Type de clé                    │ Nombre remis     │
├────────────────────────────────┼──────────────────┤
│ Clés de l'appartement          │ 2                │
├────────────────────────────────┼──────────────────┤
│ Clés de la boîte aux lettres   │ 1                │
├────────────────────────────────┼──────────────────┤
│ Autre                          │ 1                │  ← NOUVEAU
├────────────────────────────────┼──────────────────┤
│ TOTAL                          │ 4                │
└────────────────────────────────┴──────────────────┘
```

---

### 3. PDF État des lieux de sortie

#### AVANT
```
3. RESTITUTION DES CLÉS
┌────────────────────────────────┬──────────────────┐
│ Type de clé                    │ Nombre restitué  │
├────────────────────────────────┼──────────────────┤
│ Clés de l'appartement          │ 2                │
├────────────────────────────────┼──────────────────┤
│ Clés de la boîte aux lettres   │ 1                │
├────────────────────────────────┼──────────────────┤
│ TOTAL                          │ 3                │
└────────────────────────────────┴──────────────────┘

Conformité : ☑ Conforme à l'entrée
```

#### APRÈS
```
3. RESTITUTION DES CLÉS
┌────────────────────────────────┬──────────────────┐
│ Type de clé                    │ Nombre restitué  │
├────────────────────────────────┼──────────────────┤
│ Clés de l'appartement          │ 2                │
├────────────────────────────────┼──────────────────┤
│ Clés de la boîte aux lettres   │ 1                │
├────────────────────────────────┼──────────────────┤
│ Autre                          │ 1                │  ← NOUVEAU
├────────────────────────────────┼──────────────────┤
│ TOTAL                          │ 4                │
└────────────────────────────────┴──────────────────┘

Conformité : ☑ Conforme à l'entrée
```

---

### 4. Page de comparaison (compare-etat-lieux.php)

#### AVANT
```
┌─ Clés ───────────────────────────────────────────────────────────┐
│                                                                   │
│  Type                 │ Remise (Entrée) │ Restitution (Sortie)  │
│  ─────────────────────┼─────────────────┼──────────────────────  │
│  Clés appartement     │       2         │  2  ✓ Conforme        │
│  Clés boîte lettres   │       1         │  1  ✓ Conforme        │
│  Conformité           │       -         │  [Conforme]           │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

#### APRÈS
```
┌─ Clés ───────────────────────────────────────────────────────────┐
│                                                                   │
│  Type                 │ Remise (Entrée) │ Restitution (Sortie)  │
│  ─────────────────────┼─────────────────┼──────────────────────  │
│  Clés appartement     │       2         │  2  ✓ Conforme        │
│  Clés boîte lettres   │       1         │  1  ✓ Conforme        │
│  Autre                │       1         │  1  ✓ Conforme        │  ← NOUVEAU
│  Conformité           │       -         │  [Conforme]           │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

---

## Calcul automatique du total

### JavaScript (calculateTotalKeys)

#### AVANT
```javascript
function calculateTotalKeys() {
    const appart = parseInt(document.querySelector('[name="cles_appartement"]').value) || 0;
    const boite = parseInt(document.querySelector('[name="cles_boite_lettres"]').value) || 0;
    document.getElementById('cles_total').value = appart + boite;
}
```

**Exemple:** 2 + 1 = **3**

#### APRÈS
```javascript
function calculateTotalKeys() {
    const appart = parseInt(document.querySelector('[name="cles_appartement"]').value) || 0;
    const boite = parseInt(document.querySelector('[name="cles_boite_lettres"]').value) || 0;
    const autre = parseInt(document.querySelector('[name="cles_autre"]').value) || 0;  // ← NOUVEAU
    document.getElementById('cles_total').value = appart + boite + autre;
}
```

**Exemple:** 2 + 1 + 1 = **4**

---

## Correction TCPDF - Signatures

### AVANT (❌ Erreur TCPDF)
```php
// Utilisation de htmlspecialchars() sur les chemins d'images
$html .= '<img src="' . htmlspecialchars($fullPath) . '" ...>';

// Résultat HTML:
<img src="/home/runner/.../uploads&#x2F;signatures&#x2F;signature.png" ...>
                     ^^^^^^^^ Entités HTML cassent TCPDF
```

**Erreur générée:**
```
TCPDF ERROR: [Image] Unable to get image: /home/runner/.../uploads&#x2F;...
```

### APRÈS (✅ Corrigé)
```php
// Chemin brut sans htmlspecialchars()
$html .= '<img src="' . $fullPath . '" ...>';

// Résultat HTML:
<img src="/home/runner/.../uploads/signatures/signature.png" ...>
         ^^^^^^^^^ Chemin correct pour TCPDF
```

**Résultat:** PDF généré avec succès ✅

---

## Impact visuel dans l'interface utilisateur

### Formulaire
- **Largeur des colonnes:** Légèrement réduite (de 33% à 25% chacune)
- **Espacement:** Conservé (mb-3)
- **Responsivité:** Toujours responsive (Bootstrap col-md-3)

### PDF
- **Hauteur de la section:** +1 ligne dans le tableau
- **Format:** Identique, juste une ligne supplémentaire
- **Pagination:** Pas d'impact (la section reste compacte)

### Page de comparaison
- **Hauteur du tableau:** +1 ligne
- **Indicateurs de conformité:** Fonctionnent pour le nouveau champ

---

## Rétrocompatibilité

### États des lieux existants
```
┌──────────────────────────────────────────────────────┐
│ Anciens états des lieux (sans cles_autre)           │
├──────────────────────────────────────────────────────┤
│                                                      │
│  cles_appartement = 2                                │
│  cles_boite_lettres = 1                              │
│  cles_autre = 0 (par défaut)  ← Ajouté auto         │
│  cles_total = 3                                      │
│                                                      │
│  Affichage dans le formulaire:                      │
│  Appartement: 2  |  Boîte: 1  |  Autre: 0  | Total: 3 │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### Nouveaux états des lieux
```
┌──────────────────────────────────────────────────────┐
│ Nouveaux états des lieux (avec cles_autre)          │
├──────────────────────────────────────────────────────┤
│                                                      │
│  cles_appartement = 2                                │
│  cles_boite_lettres = 1                              │
│  cles_autre = 1  ← Peut être saisi                  │
│  cles_total = 4                                      │
│                                                      │
│  Affichage dans le formulaire:                      │
│  Appartement: 2  |  Boîte: 1  |  Autre: 1  | Total: 4 │
│                                                      │
└──────────────────────────────────────────────────────┘
```

---

## Cas d'usage

### Exemple 1: Clés de parking
```
Clés de l'appartement: 2
Clés boîte aux lettres: 1
Autre: 1 (clé de parking)
Total: 4 clés
```

### Exemple 2: Clés de cave
```
Clés de l'appartement: 3
Clés boîte aux lettres: 1
Autre: 1 (clé de cave)
Total: 5 clés
```

### Exemple 3: Badge d'accès
```
Clés de l'appartement: 2
Clés boîte aux lettres: 1
Autre: 2 (badges d'accès immeuble)
Total: 5 clés
```

### Exemple 4: Sans clés supplémentaires
```
Clés de l'appartement: 2
Clés boîte aux lettres: 1
Autre: 0
Total: 3 clés
```
