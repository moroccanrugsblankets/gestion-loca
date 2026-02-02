# Guide de Validation des Corrections - Module Contrats

## Contexte

Trois problèmes ont été identifiés et corrigés dans le module de gestion des contrats :

1. La signature électronique de l'agence n'était pas ajoutée automatiquement au PDF
2. La signature du client était trop grande avec une bordure grise
3. L'affichage des labels de signatures n'était pas adapté au nombre de locataires

## Corrections appliquées

### 1. Taille et apparence des signatures

**Signatures client:**
- Taille: 200px → **120px** (HTML)
- Taille: 40mm → **30mm** (PDF legacy)
- Bordure grise: **Supprimée**
- Padding: **Supprimé**

**Signatures agence:**
- Taille: 200px → **150px** (HTML)
- Taille: 40mm → **35mm** (PDF legacy)
- Bordure grise: **Supprimée**
- Padding: **Supprimé**

### 2. Labels des locataires

**Pour 1 locataire:**
```
Avant:
Le bailleur

Le(s) locataire(s)
Locataire 1 : Jean DUPONT

Après:
Le bailleur

Locataire : Jean DUPONT
```

**Pour 2 locataires:**
```
Avant:
Le bailleur

Le(s) locataire(s)
Locataire 1 : Jean DUPONT
Locataire 2 : Marie MARTIN

Après:
Le bailleur

Locataire 1 : Jean DUPONT

Locataire 2 : Marie MARTIN
```

### 3. Signature agence automatique

La signature de l'agence est maintenant ajoutée **automatiquement** lors de la validation du contrat, avec :
- L'image de la signature configurée
- La date de validation
- Un rendu propre et professionnel

## Tests à effectuer

### Prérequis

1. **Configurer la signature de l'agence**
   - Aller dans `/admin-v2/contrat-configuration.php`
   - Section "Signature de la société"
   - Uploader une image de signature (PNG ou JPEG, max 2 MB)
   - Cocher "Activer l'ajout automatique de la signature"
   - Enregistrer

### Test 1: Contrat avec 1 locataire

**Étapes:**
1. Créer un nouveau contrat avec **1 seul locataire**
2. Envoyer le lien de signature au locataire
3. Le locataire signe le contrat
4. Télécharger le PDF généré

**Vérifications:**
- [ ] Le PDF contient "Locataire :" (sans "s", sans numéro)
- [ ] La signature du locataire est visible
- [ ] La signature est de taille réduite (environ 3-4 cm de large)
- [ ] **Aucune bordure grise** autour de la signature
- [ ] Pas de signature agence (contrat non validé)

5. Valider le contrat dans l'admin
6. Télécharger à nouveau le PDF

**Vérifications après validation:**
- [ ] La signature de l'agence est maintenant présente
- [ ] La date de validation est affichée
- [ ] La signature agence est sans bordure
- [ ] Le label reste "Locataire :" (singulier)

### Test 2: Contrat avec 2 locataires

**Étapes:**
1. Créer un nouveau contrat avec **2 locataires**
2. Envoyer le lien de signature
3. Les locataires signent
4. Télécharger le PDF

**Vérifications:**
- [ ] Le PDF contient "Locataire 1 :" et "Locataire 2 :"
- [ ] Les deux signatures sont visibles
- [ ] Les signatures sont de taille réduite
- [ ] **Aucune bordure grise** autour des signatures
- [ ] Pas de signature agence (contrat non validé)

5. Valider le contrat
6. Télécharger le PDF final

**Vérifications après validation:**
- [ ] La signature agence est ajoutée
- [ ] La date de validation est affichée
- [ ] Les labels restent "Locataire 1 :" et "Locataire 2 :"

### Test 3: Vérification visuelle

Pour chaque PDF généré, vérifier que :

**Signatures client:**
- [ ] Taille proportionnelle (environ 3 cm de large)
- [ ] Pas de cadre/bordure autour
- [ ] Fond transparent (pas de bloc blanc)
- [ ] Image nette et claire
- [ ] Horodatage et IP affichés en petit texte

**Signature agence (après validation):**
- [ ] Taille proportionnelle (environ 3.5-4 cm de large)
- [ ] Pas de cadre/bordure autour
- [ ] Texte "Signature électronique de la société"
- [ ] Date de validation au format "Validé le : JJ/MM/AAAA à HH:MM:SS"

**Mise en page:**
- [ ] Section "Le bailleur" visible
- [ ] Section locataire(s) avec labels corrects
- [ ] Espacement approprié entre les sections
- [ ] Document professionnel et lisible

## Problèmes potentiels et solutions

### La signature agence n'apparaît pas

**Causes possibles:**
1. Le contrat n'est pas au statut "validé" → Valider le contrat
2. L'option n'est pas activée → Vérifier dans `/admin-v2/contrat-configuration.php`
3. Aucune image n'est configurée → Uploader une signature
4. L'image est trop grande (> 2 MB) → Réduire la taille de l'image

**Vérification:**
```sql
SELECT valeur FROM parametres WHERE cle = 'signature_societe_enabled';
-- Doit retourner: 'true'

SELECT LENGTH(valeur) FROM parametres WHERE cle = 'signature_societe_image';
-- Doit retourner un nombre > 0
```

### Les signatures ont encore des bordures

**Solution:**
- Effacer le cache du navigateur
- Régénérer le PDF (supprimer l'ancien et en créer un nouveau)
- Vérifier que les modifications sont bien déployées

### Les labels sont incorrects

**Solution:**
- Vérifier le nombre de locataires dans le contrat
- S'assurer que le code a bien été mis à jour
- Régénérer le PDF

## Fichiers modifiés

Les corrections ont été appliquées dans :

1. **`pdf/generate-contrat-pdf.php`**
   - Fonction `replaceContratTemplateVariables()` (lignes 138-178, 180-208)
   - Classe `ContratBailPDF::generateContrat()` (lignes 489, 512-524, 547-548)

2. **`admin-v2/contrat-configuration.php`**
   - Fonction `getDefaultContractTemplate()` (ligne 310)

## Validation réussie ✅

Si tous les tests passent, vous devriez constater :

✅ Signatures client sans bordure et de taille réduite
✅ Signature agence ajoutée automatiquement lors de la validation
✅ Labels corrects selon le nombre de locataires (1 ou plusieurs)
✅ PDF professionnel et propre
✅ Toutes les informations légales présentes (horodatage, IP, date de validation)

## Support

En cas de problème :
1. Consulter le fichier `CORRECTIONS_SIGNATURES_CONTRATS.md` pour plus de détails
2. Exécuter le script de test : `php test-signature-fixes.php`
3. Vérifier les logs d'erreurs PHP dans `error.log`

---

**Note:** Ces corrections sont rétrocompatibles. Les contrats existants continuent de fonctionner normalement, et les nouveaux contrats bénéficient automatiquement des améliorations.
