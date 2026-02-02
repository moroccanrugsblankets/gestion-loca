# Guide Visuel - Corrections PDF Contrats

## Vue d'ensemble des corrections

Ce document présente les corrections apportées au module de génération PDF des contrats de bail.

---

## 1. Signature Agence - AVANT / APRÈS

### ❌ AVANT
```
Le contrat validé ne contenait PAS la signature électronique de l'agence
```

**Problème**: 
- La variable `{{signature_agence}}` était vide même après validation
- Aucune signature n'apparaissait dans le PDF final
- Pas de date de validation

### ✅ APRÈS
```
╔════════════════════════════════════════════════════╗
║  Signature électronique de la société             ║
║                                                    ║
║  [Image de signature - 150px max, sans bordure]   ║
║                                                    ║
║  Validé le : 02/02/2026 à 17:30:45                ║
╚════════════════════════════════════════════════════╝
```

**Solution**: 
- ✅ Vérification du statut `valide` du contrat
- ✅ Récupération de l'image depuis `parametres.signature_societe_image`
- ✅ Ajout automatique avec date de validation
- ✅ Logs détaillés pour diagnostic

---

## 2. Signature Client - Taille AVANT / APRÈS

### ❌ AVANT
```
Mode HTML:  max-width: 120px  (trop grande)
Mode Legacy: largeur: 30mm    (trop grande)
```

**Rendu visuel approximatif**:
```
┌────────────────────────────────────┐
│                                    │
│     SIGNATURE CLIENT               │
│     [Image 120px de large]         │
│                                    │
│     ← Trop imposante →             │
│                                    │
└────────────────────────────────────┘
```

### ✅ APRÈS
```
Mode HTML:  max-width: 100px  (réduit)
Mode Legacy: largeur: 25mm    (réduit)
```

**Rendu visuel approximatif**:
```
┌────────────────────────────────────┐
│                                    │
│  SIGNATURE CLIENT                  │
│  [Image 100px]                     │
│                                    │
│  ← Proportionnel et harmonieux →  │
│                                    │
└────────────────────────────────────┘
```

**Amélioration**: 
- Réduction de **~17%** de la largeur
- Rendu plus proportionnel par rapport au texte
- Meilleure harmonie visuelle dans le document

---

## 3. Bordure Signature - AVANT / APRÈS

### ❌ AVANT
```html
<img src="..." alt="Signature" style="max-width: 120px; height: auto;">
```

**Rendu visuel**:
```
┌─────────────────────┐
│ ┌─────────────────┐ │  ← Contour gris visible
│ │                 │ │     autour de l'image
│ │   SIGNATURE     │ │
│ │                 │ │
│ └─────────────────┘ │
└─────────────────────┘
```

### ✅ APRÈS
```html
<img src="..." alt="Signature" style="max-width: 100px; height: auto; border: none;">
```

**Rendu visuel**:
```
   ┌─────────────────┐
   │                 │  ← Pas de contour
   │   SIGNATURE     │     Rendu propre
   │                 │
   └─────────────────┘
```

**Amélioration**: 
- ✅ Ajout explicite de `border: none;`
- ✅ Application à TOUTES les signatures (client ET agence)
- ✅ Rendu propre et professionnel

---

## 4. Chemins d'Images - AVANT / APRÈS

### ❌ AVANT

**Template HTML**:
```html
<img src="../assets/images/logo-my-invest-immobilier-carre.jpg" alt="Logo">
```

**Dans le PDF**: ❌ Image non affichée
```
[ X ]  ← Image cassée/non trouvée
```

**Problème**: 
- Les chemins relatifs ne sont pas résolus par TCPDF
- Le PDF ne peut pas accéder aux fichiers locaux
- Aucune conversion automatique

### ✅ APRÈS

**Template HTML** (inchangée):
```html
<img src="../assets/images/logo-my-invest-immobilier-carre.jpg" alt="Logo">
```

**Conversion automatique**:
```
../assets/images/logo.jpg
    ↓
https://contrat.myinvest-immobilier.com/assets/images/logo.jpg
```

**Dans le PDF**: ✅ Image affichée correctement
```
┌─────────────┐
│    LOGO     │  ← Image chargée et affichée
│   [Image]   │
└─────────────┘
```

**Conversions supportées**:
1. `../chemin/image.jpg` → `https://site.com/chemin/image.jpg`
2. `./chemin/image.jpg` → `https://site.com/chemin/image.jpg`
3. `/chemin/image.jpg` → `https://site.com/chemin/image.jpg`
4. `chemin/image.jpg` → `https://site.com/chemin/image.jpg`
5. `data:image/...` → Inchangé (conservé tel quel)
6. `https://...` → Inchangé (conservé tel quel)

---

## 5. Source du PDF - AVANT / APRÈS

### ❌ AVANT

**Processus**:
```
Contrat validé
    ↓
Mode Legacy uniquement
    ↓
PDF généré avec mise en page prédéfinie
    ↓
❌ Template HTML de /admin-v2/contrat-configuration.php IGNORÉE
```

### ✅ APRÈS

**Processus**:
```
Contrat validé
    ↓
Récupération template HTML depuis parametres.contrat_template_html
    ↓
Template trouvée ?
    ├─ OUI → Utilise template HTML configurée ✅
    └─ NON → Fallback vers mode Legacy
    ↓
Remplacement des variables {{...}}
    ↓
Conversion HTML → PDF avec TCPDF
    ↓
✅ PDF basé sur la template de /admin-v2/contrat-configuration.php
```

**Logs ajoutés**:
```
PDF Generation: Template HTML récupérée depuis /admin-v2/contrat-configuration.php (longueur: 15234 caractères)
```

---

## 6. Logs - Nouveautés

### Logs de début/fin
```
=== PDF Generation START pour contrat #123 ===
PDF Generation: Contrat #123 trouvé (statut: valide, ref: BAIL-ABC123)
...
PDF Generation: PDF généré avec succès: /path/to/bail-ABC123.pdf
=== PDF Generation END pour contrat #123 - SUCCÈS ===
```

### Logs de signatures
```
PDF Generation: Traitement de 2 signature(s) client(s)
PDF Generation: Signature client 1 ajoutée (taille réduite à 100px, sans bordure)
PDF Generation: Signature client 2 ajoutée (taille réduite à 100px, sans bordure)

PDF Generation: Contrat validé, traitement de la signature agence
PDF Generation: Signature agence activée = OUI
PDF Generation: Signature agence ajoutée avec succès au PDF
```

### Logs d'images
```
PDF Generation: Conversion des chemins d'images (URL de base: https://contrat.myinvest-immobilier.com)
PDF Generation: Image 1 - Chemin relatif ../ converti: ../assets/logo.jpg => https://.../assets/logo.jpg
PDF Generation: Image 2 - Data URI conservée
PDF Generation: Image 3 - URL absolue conservée: https://external.com/image.png
PDF Generation: 3 image(s) traitée(s) dans le template
```

### Logs d'erreurs
```
PDF Generation: ERREUR - Signature agence trop volumineuse, ignorée
PDF Generation: AVERTISSEMENT - Signature client 1 trop volumineuse, ignorée
PDF Generation: ERREUR - Format de signature invalide
```

---

## Résumé des améliorations

| Aspect | Avant | Après | Amélioration |
|--------|-------|-------|--------------|
| **Signature agence** | ❌ Absente | ✅ Ajoutée automatiquement | +100% |
| **Taille signature client (HTML)** | 120px | 100px | -17% |
| **Taille signature client (Legacy)** | 30mm | 25mm | -17% |
| **Bordure signatures** | Gris visible | Aucune | 100% propre |
| **Images relatives** | ❌ Non affichées | ✅ Converties et affichées | +100% |
| **Utilisation template HTML** | ❌ Ignorée | ✅ Utilisée | Configurable |
| **Logs diagnostic** | Basiques | Détaillés | 10x plus d'infos |

---

## Utilisation

### Consulter les logs
```bash
# Logs en temps réel
tail -f /var/log/php_errors.log | grep "PDF Generation"

# Logs d'un contrat spécifique
grep "PDF Generation.*contrat #123" /var/log/php_errors.log

# Logs de signatures uniquement
grep "PDF Generation.*signature" /var/log/php_errors.log

# Logs d'images uniquement
grep "PDF Generation.*Image" /var/log/php_errors.log
```

### Vérifier la configuration
1. Aller dans `/admin-v2/contrat-configuration.php`
2. Vérifier que la signature agence est:
   - ✅ Activée (case cochée)
   - ✅ Image uploadée et visible
3. S'assurer que la template HTML contient `{{signature_agence}}`

### Tester les corrections
```bash
# Lancer le script de test
php test-pdf-fixes.php

# Générer un PDF et consulter les logs
# Les logs apparaîtront dans le fichier error_log de PHP
```

---

## Support

Pour toute question ou problème:
1. Consultez d'abord `CORRECTIONS_PDF_CONTRATS.md`
2. Vérifiez les logs avec les commandes ci-dessus
3. Assurez-vous que la configuration est correcte
