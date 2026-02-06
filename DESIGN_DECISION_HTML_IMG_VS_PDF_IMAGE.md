# Choix de Design : HTML `<img>` vs `$pdf->Image()`

## 🎯 Décision de Design

**Ce projet utilise HTML `<img>` tags, PAS `$pdf->Image()`**

## 📋 Raisons

### 1. Flexibilité de Template

Avec HTML `<img>` :
```php
// generate-contrat-pdf.php (CORRECT)
$html .= '<img src="' . htmlspecialchars($publicUrl) . '" 
          alt="Signature Société" 
          border="0" 
          style="max-width: 150px; border: 0; ...">';
$pdf->writeHTML($html);
```

✅ **La signature suit le flux HTML** - Si vous modifiez le template (ajoutez du texte, changez la mise en page), la signature reste au bon endroit.

Avec `$pdf->Image()` :
```php
// Ce qu'on NE FAIT PAS
$pdf->writeHTML($html);
$pdf->Image('@' . $imageData, 20, 200, 40, 20, 'PNG', ...);
//                            ↑   ↑
//                            X   Y (coordonnées fixes en mm)
```

❌ **Position absolue fixe** - Si vous modifiez le template, vous devez recalculer manuellement les coordonnées X, Y de chaque signature !

### 2. Exemple Concret

**Scénario :** Vous ajoutez une section dans le template

#### Avec HTML `<img>` (CORRECT) ✅

```php
// Template original
$html = '<h1>Contrat</h1>
         <p>Parties...</p>
         <img src="signature.png" style="max-width: 150px;">'; // Position automatique

// Après ajout d'une section
$html = '<h1>Contrat</h1>
         <p>Parties...</p>
         <h2>Nouvelle section</h2>  ← Ajout ici
         <p>Texte additionnel...</p>
         <img src="signature.png" style="max-width: 150px;">'; // ✅ Toujours au bon endroit !

$pdf->writeHTML($html);
// ✅ Aucun changement de code nécessaire
```

#### Avec `$pdf->Image()` (PROBLÉMATIQUE) ❌

```php
// Template original
$html = '<h1>Contrat</h1>
         <p>Parties...</p>';
$pdf->writeHTML($html);
$pdf->Image('@' . $data, 20, 200, 40, 20, 'PNG', ...); // Y = 200mm
//                           ↑
//                        Position fixe

// Après ajout d'une section
$html = '<h1>Contrat</h1>
         <p>Parties...</p>
         <h2>Nouvelle section</h2>  ← Ajout ici
         <p>Texte additionnel...</p>'; // ← Pousse le contenu vers le bas
$pdf->writeHTML($html);
$pdf->Image('@' . $data, 20, 200, 40, 20, 'PNG', ...); // Y = 200mm
//                           ↑
//                        ❌ FAUX ! Devrait être 250mm maintenant
// ❌ La signature est maintenant AU MILIEU du texte !
// ❌ Il faut recalculer Y manuellement à chaque changement
```

### 3. Maintenance

| Aspect | HTML `<img>` | `$pdf->Image()` |
|--------|--------------|-----------------|
| **Ajout de contenu** | ✅ Automatique | ❌ Recalcul manuel de Y |
| **Modification mise en page** | ✅ S'adapte | ❌ Recalcul X, Y |
| **Changement de police** | ✅ Pas d'impact | ❌ Affecte les positions |
| **Ajout de locataires** | ✅ Flux HTML gère | ❌ Recalcul de toutes les positions |
| **Modification marges** | ✅ Relatif | ❌ Recalcul absolu |

### 4. Code de Référence

**Fichier de référence :** `pdf/generate-contrat-pdf.php`

```php
// Lignes 181 et 208 - Implémentation CORRECTE
$html .= '<img src="' . htmlspecialchars($publicUrl) . '" 
          alt="Signature Société" 
          border="0" 
          style="max-width: 150px; 
                 border: 0; 
                 border-width: 0; 
                 border-style: none; 
                 border-color: transparent; 
                 outline: none; 
                 outline-width: 0; 
                 padding: 0; 
                 background: transparent;">';
```

**Tous les fichiers utilisent cette approche :**
- ✅ `pdf/generate-contrat-pdf.php` - HTML `<img>`
- ✅ `pdf/generate-bail.php` - HTML `<img>`
- ✅ `pdf/generate-etat-lieux.php` - HTML `<img>`

## 🚫 Pourquoi PAS `$pdf->Image()` ?

### Inconvénients de `$pdf->Image()` :

1. **Couplage fort avec la structure du template**
   - Chaque modification de template nécessite un ajustement du code PHP
   - Les coordonnées X, Y doivent être calculées manuellement

2. **Difficulté de maintenance**
   - Ajouter un paragraphe ? → Recalculer Y
   - Changer la police ? → Recalculer Y
   - Modifier les marges ? → Recalculer X et Y
   - Ajouter un locataire ? → Recalculer toutes les positions

3. **Testabilité réduite**
   - Les HTML previews ne peuvent pas montrer les signatures
   - Impossible de voir le rendu avant génération PDF

4. **Code complexe**
   - Nécessite des calculs de position
   - Gestion manuelle de l'espace
   - Plus de code = plus d'erreurs potentielles

### Exemple de code complexe avec `$pdf->Image()` :

```php
// Ce qu'on NE VEUT PAS :
$currentY = $pdf->GetY(); // Position actuelle
$signatureY = $currentY + 10; // +10mm d'espace

// Pour chaque locataire
$signatureX = 15; // Marge gauche
foreach ($locataires as $i => $loc) {
    $pdf->Image('@' . $loc['signature'], 
                $signatureX, 
                $signatureY, 
                40, 20, 'PNG', ...);
    $signatureX += 65; // +65mm pour le suivant
    // ❌ Si on change la largeur des colonnes ? Tout à recalculer !
}
```

## ✅ Avantages de HTML `<img>`

### Avantages :

1. **Position automatique**
   - Suit le flux du document HTML
   - S'adapte automatiquement aux changements

2. **Maintenance facile**
   - Modifications de template sans toucher au code PHP
   - Pas de calcul de coordonnées

3. **Preview HTML fonctionnel**
   - Les fichiers `test-html-preview-*.php` montrent le rendu exact
   - Diagnostic facile des problèmes

4. **Code simple et lisible**
   - Balise `<img>` standard
   - Styles CSS compréhensibles

5. **Cohérence**
   - Même approche dans tous les PDFs
   - Même rendu dans browser et PDF (sauf bordures TCPDF)

### Exemple de code simple avec HTML `<img>` :

```php
// Ce qu'on FAIT (SIMPLE et MAINTENABLE) :
$html .= '<img src="' . $signatureUrl . '" 
          style="max-width: 150px; border: 0; ...">';
// ✅ C'est tout ! La position est gérée par le HTML
```

## 🔍 Cas d'Usage Réels

### Cas 1 : Ajout d'un Nouveau Champ

**Besoin :** Ajouter le numéro de téléphone du locataire dans le contrat

#### Avec HTML `<img>` (ACTUEL) ✅
```php
// Avant
$html = '<p>Nom : ' . $nom . '</p>
         <p>Email : ' . $email . '</p>
         <img src="signature.png">';

// Après - AUCUN changement de code signatures !
$html = '<p>Nom : ' . $nom . '</p>
         <p>Email : ' . $email . '</p>
         <p>Téléphone : ' . $tel . '</p>  ← Ajout
         <img src="signature.png">'; // ✅ Position automatique
```

#### Avec `$pdf->Image()` ❌
```php
// Avant
$html = '<p>Nom : ' . $nom . '</p>
         <p>Email : ' . $email . '</p>';
$pdf->writeHTML($html);
$pdf->Image('@' . $sig, 20, 150, 40, 20, 'PNG', ...); // Y = 150

// Après - Il faut recalculer Y !
$html = '<p>Nom : ' . $nom . '</p>
         <p>Email : ' . $email . '</p>
         <p>Téléphone : ' . $tel . '</p>';  ← Ajout
$pdf->writeHTML($html);
$pdf->Image('@' . $sig, 20, 165, 40, 20, 'PNG', ...); // Y = 165 (calculé manuellement !)
//                          ↑
//                       ❌ Il faut mesurer la nouvelle hauteur !
```

### Cas 2 : Template Multilingue

**Besoin :** Supporter français et anglais (textes de longueurs différentes)

#### Avec HTML `<img>` ✅
```php
// Français
$html = '<p>Parties au contrat...</p>'; // Court
$html .= '<img src="signature.png">';

// Anglais
$html = '<p>Parties to the contract...</p>'; // Plus long
$html .= '<img src="signature.png">'; // ✅ Même code, position automatique
```

#### Avec `$pdf->Image()` ❌
```php
// Français
$pdf->writeHTML('<p>Parties au contrat...</p>');
$pdf->Image('@' . $sig, 20, 100, ...); // Y = 100

// Anglais
$pdf->writeHTML('<p>Parties to the contract...</p>'); // Plus long !
$pdf->Image('@' . $sig, 20, 100, ...); // ❌ Y = 100 (trop haut !)
//                          ↑
//                       Il faudrait Y = 110 pour l'anglais
```

## 📊 Comparaison Finale

| Critère | HTML `<img>` | `$pdf->Image()` | Gagnant |
|---------|--------------|-----------------|---------|
| Flexibilité template | ✅ Excellente | ❌ Faible | HTML |
| Maintenance | ✅ Facile | ❌ Difficile | HTML |
| Code complexité | ✅ Simple | ❌ Complexe | HTML |
| Preview HTML | ✅ Fonctionne | ❌ Impossible | HTML |
| Position précise | ⚠️ Relative | ✅ Absolue | Égalité* |
| Bordures TCPDF | ⚠️ Possible | ✅ Contrôlées | `$pdf->Image()` |

\* Pour ce projet, la position relative est préférable

**Gagnant global :** HTML `<img>` (5 vs 1)

## 🎯 Conclusion

**HTML `<img>` est le bon choix pour ce projet** car :

1. ✅ Flexibilité de template (priorité #1)
2. ✅ Maintenance facile
3. ✅ Code simple
4. ✅ Preview HTML fonctionnel
5. ✅ Pas de recalcul de coordonnées

**`$pdf->Image()` n'est PAS utilisé** car :

1. ❌ Position fixe (X, Y) incompatible avec modifications de template
2. ❌ Maintenance difficile
3. ❌ Code complexe
4. ❌ Preview HTML impossible

## 📚 Références

- `pdf/generate-contrat-pdf.php` - Implémentation de référence (lignes 181, 208)
- `pdf/generate-bail.php` - Utilise HTML `<img>` (lignes 383, 397, 405, 448, 453)
- `pdf/generate-etat-lieux.php` - Utilise HTML `<img>` (ligne 1168, 1176, 1225, 1233)

---

**Date :** 2026-02-06  
**Auteur :** GitHub Copilot  
**Status :** ✅ Design Decision Documentée
