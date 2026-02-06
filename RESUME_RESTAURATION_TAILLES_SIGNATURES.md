# Résumé des Modifications - Tailles des Signatures PDF

## Contexte
Les signatures dans les PDF générés par TCPDF étaient trop petites après les dernières modifications.
L'utilisateur a créé un fichier de test pour visualiser le HTML avant l'exécution TCPDF et a constaté que :
- Le HTML brut affiche correctement les signatures (elles sont même meilleures en plus grand)
- C'est TCPDF qui génère les bordures dans le PDF final

## Modifications Effectuées

### 1. `pdf/generate-contrat-pdf.php`

#### Signature de l'Agence (Bailleur)
```php
// AVANT (trop petit)
max-width: 100px; max-height: 50px;

// APRÈS (restauré)
max-width: 150px;
```
- Augmentation de 50% de la largeur (100px → 150px)
- Suppression de la contrainte max-height qui limitait la hauteur

#### Signature des Locataires
```php
// AVANT (trop petit)
max-width: 100px; max-height: 50px;

// APRÈS (restauré)
max-width: 150px;
```
- Même changement que pour l'agence
- Les signatures des locataires ont maintenant la même taille que celle de l'agence

### 2. `pdf/generate-bail.php`

#### Signature de l'Agence (Classe CSS + Inline)
```php
// AVANT (trop petit)
max-width: 40px; max-height: 20px;

// APRÈS (restauré)
max-width: 50px; max-height: 25px;
```
- Augmentation de 25% de la largeur et la hauteur
- Appliqué dans :
  - Classe CSS `.company-signature`
  - 3 instances inline dans le code HTML généré

#### Signature des Locataires (Classe CSS + Inline)
```php
// AVANT (trop petit)
max-width: 30px; max-height: 15px;

// APRÈS (restauré)
max-width: 40px; max-height: 20px;
```
- Augmentation de 33% de la largeur et la hauteur
- Appliqué dans :
  - Classe CSS `.signature-image`
  - 2 instances inline dans le code HTML généré

## Propriétés de Bordure Conservées

Toutes les propriétés anti-bordure ont été **conservées** dans les deux fichiers :
```css
border: 0;
border-width: 0;
border-style: none;
border-color: transparent;
outline: none;
outline-width: 0;
padding: 0;
background: transparent;
```

## Fichiers de Test Créés

Deux nouveaux fichiers ont été créés pour faciliter le diagnostic des problèmes TCPDF :

### `test-html-preview-contrat.php`
- Affiche le HTML généré par `generate-contrat-pdf.php` **AVANT** l'exécution de TCPDF
- Permet de voir les signatures telles qu'elles apparaissent dans le HTML brut
- Usage: `http://localhost/test-html-preview-contrat.php?id=51`

### `test-html-preview-bail.php`
- Affiche le HTML généré par `generate-bail.php` **AVANT** l'exécution de TCPDF
- Permet de voir les signatures telles qu'elles apparaissent dans le HTML brut
- Usage: `http://localhost/test-html-preview-bail.php?id=51`

## Résultat Attendu

Les signatures devraient maintenant :
1. ✅ Être **plus grandes** et **mieux proportionnées** dans les PDFs
2. ✅ Maintenir les propriétés anti-bordure (bien que TCPDF puisse toujours générer des bordures)
3. ✅ S'afficher correctement dans le HTML de prévisualisation

## Problème Résiduel : Bordures TCPDF

**Important :** Les bordures visibles dans le PDF final sont générées par TCPDF lui-même et non par le HTML.
Selon la documentation du projet (voir `AVANT_APRES_SIGNATURES_TCPDF.md`), la solution complète nécessiterait :

1. Utiliser `$pdf->Image()` avec le paramètre `border=0` au lieu de balises HTML `<img>`
2. Insérer les signatures après `writeHTML()` plutôt que dans le HTML

Cette approche est documentée mais n'a pas été implémentée dans cette correction car :
- Elle nécessiterait une refonte plus importante du code
- Le focus de cette PR est uniquement sur la **restauration des tailles** des signatures

## Fichiers Modifiés

- `pdf/generate-contrat-pdf.php` - Lignes 181, 208
- `pdf/generate-bail.php` - Lignes 152-153, 164-165, 383, 397, 405, 448, 453

## Fichiers Créés

- `test-html-preview-contrat.php` - Nouveau fichier de test
- `test-html-preview-bail.php` - Nouveau fichier de test
