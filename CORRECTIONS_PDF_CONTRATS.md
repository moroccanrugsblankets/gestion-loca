# Corrections du Module PDF - Contrats de Bail

## Date
2 février 2026

## Problèmes corrigés

### 1. Signature électronique de l'agence non ajoutée au PDF

**Problème**: La signature électronique de l'agence ({{signature_agence}}) n'était pas ajoutée automatiquement au PDF final après validation du contrat.

**Solution**:
- ✅ Vérification du statut du contrat (`valide`) avant d'ajouter la signature
- ✅ Récupération des paramètres `signature_societe_image` et `signature_societe_enabled`
- ✅ Validation du format de l'image (data URI base64)
- ✅ Vérification de la taille de l'image (< 2 MB)
- ✅ Ajout de la signature avec la date de validation
- ✅ Ajout de logs détaillés pour tracer l'insertion

**Code modifié**: 
- `pdf/generate-contrat-pdf.php` - fonction `replaceContratTemplateVariables()`
- Mode HTML: balise `<img>` avec `max-width: 150px; border: none;`
- Mode Legacy: méthode `Image()` avec largeur de 30mm

### 2. Signature du client trop grande

**Problème**: La signature du client apparaissait trop grande dans le PDF.

**Solution**:
- ✅ **Mode HTML**: Réduction de 120px à **100px** (max-width)
- ✅ **Mode Legacy**: Réduction de 30mm à **25mm**
- ✅ Meilleur rendu proportionnel et harmonieux

**Code modifié**:
- `pdf/generate-contrat-pdf.php`:
  - Ligne ~168: `max-width: 100px` (HTML)
  - Ligne ~556: largeur de 25mm (Legacy)

### 3. Contour gris autour de la signature du client

**Problème**: La signature du client affichait un contour gris (border).

**Solution**:
- ✅ Ajout explicite de `border: none;` dans le style CSS des balises `<img>` pour les signatures
- ✅ Application à la signature client ET à la signature agence

**Code modifié**:
- `pdf/generate-contrat-pdf.php`:
  - Signatures clients: `style="max-width: 100px; height: auto; border: none;"`
  - Signature agence: `style="max-width: 150px; height: auto; border: none;"`

### 4. Images de la template ne s'affichent pas dans le PDF

**Problème**: Les images insérées dans la template (chemins relatifs ou absolus) ne s'affichaient pas dans le PDF généré.

**Solution**:
- ✅ **Conversion automatique des chemins relatifs en URLs absolues**:
  - Chemins commençant par `../` → conversion en URL absolue
  - Chemins commençant par `./` → conversion en URL absolue
  - Chemins commençant par `/` → ajout du domaine
  - Chemins relatifs simples → ajout du domaine
- ✅ **Préservation des formats spéciaux**:
  - Data URIs (`data:image/...`) → inchangés
  - URLs absolues (`http://`, `https://`) → inchangées
- ✅ **Utilisation de l'URL du site** depuis `$config['SITE_URL']`
- ✅ **Logs détaillés** pour chaque image traitée

**Code modifié**:
- `pdf/generate-contrat-pdf.php` - fonction `replaceContratTemplateVariables()`
- Ajout d'un `preg_replace_callback()` pour traiter toutes les balises `<img>`

### 5. PDF non basé sur la template HTML configurée

**Problème**: Le PDF était généré par un processus séparé utilisant une mise en page prédéfinie, ignorant la template HTML de `/admin-v2/contrat-configuration.php`.

**Solution**:
- ✅ **Priorisation de la template HTML**:
  - Récupération de la template depuis `parametres` (clé: `contrat_template_html`)
  - Utilisation de cette template pour générer le PDF via TCPDF
  - Logs confirmant la source de la template
- ✅ **Fallback vers le mode legacy**:
  - Si aucune template HTML n'est configurée
  - Logs indiquant le mode utilisé

**Code modifié**:
- `pdf/generate-contrat-pdf.php` - fonction `generateContratPDF()`

### 6. Logs détaillés ajoutés

**Nouveaux logs disponibles** dans le fichier error_log PHP:

1. **Début/Fin de génération**:
   ```
   === PDF Generation START pour contrat #123 ===
   === PDF Generation END pour contrat #123 - SUCCÈS ===
   ```

2. **Source de la template**:
   ```
   PDF Generation: Template HTML récupérée depuis /admin-v2/contrat-configuration.php (longueur: XXXX caractères)
   PDF Generation: Utilisation du système LEGACY pour contrat #123
   ```

3. **Signatures**:
   ```
   PDF Generation: Contrat validé, traitement de la signature agence
   PDF Generation: Signature agence activée = OUI
   PDF Generation: Signature agence ajoutée avec succès au PDF
   PDF Generation: Signature client 1 ajoutée (taille réduite à 100px, sans bordure)
   ```

4. **Images**:
   ```
   PDF Generation: Conversion des chemins d'images (URL de base: https://...)
   PDF Generation: Image 1 - Chemin relatif ../ converti: ../assets/logo.jpg => https://.../assets/logo.jpg
   PDF Generation: 3 image(s) traitée(s) dans le template
   ```

5. **Erreurs**:
   ```
   PDF Generation: ERREUR - Signature agence trop volumineuse, ignorée
   PDF Generation: AVERTISSEMENT - Signature client 1 trop volumineuse, ignorée
   ```

## Fichiers modifiés

1. **pdf/generate-contrat-pdf.php** (principal)
   - Fonction `generateContratPDF()` - logs et gestion d'erreurs
   - Fonction `replaceContratTemplateVariables()` - signatures, images, logs
   - Fonction `generateContratPDFLegacy()` - logs
   - Classe `ContratBailPDF` - signatures, logs

## Test des corrections

Un script de test a été créé: `test-pdf-fixes.php`

**Utilisation**:
```bash
php test-pdf-fixes.php
```

**Ce que vérifie le script**:
1. Présence de la template HTML dans la configuration
2. Configuration de la signature agence (activée, image présente, taille)
3. Recherche d'un contrat de test
4. Génération d'un PDF de test avec logs détaillés

## Prochaines étapes

- [ ] Tester sur plusieurs contrats avec différents statuts
- [ ] Vérifier le rendu visuel des signatures dans le PDF
- [ ] Vérifier que les images s'affichent correctement
- [ ] Valider que le système utilise bien la template HTML configurée
- [ ] Consulter les logs pour diagnostiquer les problèmes éventuels

## Notes importantes

### Limites de taille
- Signature client: < 5 MB (base64)
- Signature agence: < 2 MB (base64)

### Formats supportés
- Images: PNG, JPEG, JPG
- Data URIs: `data:image/(png|jpeg|jpg);base64,...`

### Configuration requise
Pour que la signature agence soit ajoutée automatiquement:
1. Le contrat doit avoir le statut `valide`
2. Le paramètre `signature_societe_enabled` doit être `true`
3. Le paramètre `signature_societe_image` doit contenir une image valide

### URLs des images
L'URL de base utilisée pour les images provient de `$config['SITE_URL']`.
Assurez-vous qu'elle est correctement configurée dans `includes/config.php`.

## Résolution des problèmes

### La signature agence n'apparaît pas
1. Vérifiez le statut du contrat (doit être `valide`)
2. Consultez les logs: `grep "PDF Generation.*signature agence" /path/to/error_log`
3. Vérifiez la configuration dans `/admin-v2/contrat-configuration.php`

### Les images ne s'affichent pas
1. Consultez les logs: `grep "PDF Generation.*Image" /path/to/error_log`
2. Vérifiez que `$config['SITE_URL']` est correct
3. Vérifiez que les images sont accessibles via HTTP/HTTPS

### Le PDF utilise le mode legacy au lieu de la template
1. Vérifiez qu'une template HTML est configurée dans la base de données
2. Consultez les logs: `grep "PDF Generation.*template" /path/to/error_log`
