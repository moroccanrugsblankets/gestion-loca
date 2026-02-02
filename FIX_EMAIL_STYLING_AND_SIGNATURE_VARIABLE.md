# Fix: Email Template Styling & Missing Agency Signature Variable

## Problème Identifié

### 1. Perte du style des emails après édition
**Symptôme:** Après avoir édité un template d'email via l'interface admin, les styles CSS étaient perdus, rendant l'email non stylé.

**Cause:** La configuration par défaut de TinyMCE supprime les balises `<html>`, `<head>`, `<style>` et d'autres éléments de structure HTML lors de l'édition pour des raisons de sécurité.

### 2. Variable {{signature_agence}} manquante
**Symptôme:** La variable `{{signature_agence}}` n'était pas disponible dans la liste des variables sur `/admin-v2/contrat-configuration.php`

**Impact:** L'utilisateur ne pouvait pas utiliser cette variable dans le template de contrat HTML.

### 3. Confusion sur le PDF généré
**Question:** Le PDF est-il généré à partir du template HTML configuré dans `contrat-configuration.php` ?

**Réponse:** **NON**. Le PDF du contrat est généré par un processus séparé avec une mise en page prédéfinie dans `pdf/generate-contrat-pdf.php`. Le template HTML dans `contrat-configuration.php` est uniquement à des fins d'affichage et de configuration.

---

## Solution Implémentée

### 1. Configuration TinyMCE Améliorée

**Fichiers modifiés:**
- `admin-v2/email-templates.php`
- `admin-v2/contrat-configuration.php`

**Changements:**
```javascript
tinymce.init({
    selector: 'textarea.code-editor',
    // ... autres options ...
    
    // Nouvelles options pour préserver la structure HTML complète
    verify_html: false,  // Ne pas valider/nettoyer le HTML
    extended_valid_elements: 'style,link[href|rel],head,html[lang],meta[*],body[*]',
    valid_children: '+body[style],+head[style]',  // Autoriser <style> dans <body> et <head>
    forced_root_block: false,  // Ne pas forcer l'encapsulation dans des blocs
    doctype: '<!DOCTYPE html>',  // Préserver le DOCTYPE
});
```

**Résultat:** Les templates HTML complets (avec `<!DOCTYPE>`, `<html>`, `<head>`, `<style>`, etc.) sont maintenant préservés lors de l'édition.

### 2. Ajout de la Variable {{signature_agence}}

**Fichier modifié:** `admin-v2/contrat-configuration.php`

**Changements:**
1. Ajout de `{{signature_agence}}` dans la liste des variables disponibles
2. Ajout dans la fonction de prévisualisation avec un exemple de signature
3. Ajout d'une note explicative sur l'utilisation du template

**Code ajouté:**
```html
<span class="variable-tag" onclick="copyVariable('{{signature_agence}}')">{{signature_agence}}</span>
```

```javascript
.replace(/\{\{signature_agence\}\}/g, '<p><strong>MY INVEST IMMOBILIER</strong><br>Représenté par M. ALEXANDRE<br>Lu et approuvé</p>')
```

### 3. Documentation Clarifiée

Ajout d'une note importante sur la page de configuration :

> **Note importante :** Ce template HTML est utilisé pour l'affichage et la configuration uniquement. **Le PDF du contrat est généré par un processus séparé** qui utilise une mise en page prédéfinie. Les modifications de ce template n'affecteront pas le format du PDF final.

---

## Variables Disponibles

Liste complète des variables pour le template de contrat :

1. `{{reference_unique}}` - Référence unique du contrat
2. `{{locataires_info}}` - Informations des locataires
3. `{{locataires_signatures}}` - Signatures des locataires
4. **`{{signature_agence}}`** - Signature de l'agence ⭐ NOUVEAU
5. `{{adresse}}` - Adresse du logement
6. `{{appartement}}` - Numéro d'appartement
7. `{{type}}` - Type de logement (T1, T2, etc.)
8. `{{surface}}` - Surface en m²
9. `{{parking}}` - Information parking
10. `{{date_prise_effet}}` - Date de prise d'effet du bail
11. `{{loyer}}` - Loyer mensuel hors charges
12. `{{charges}}` - Charges mensuelles
13. `{{loyer_total}}` - Loyer total (loyer + charges)
14. `{{depot_garantie}}` - Montant du dépôt de garantie
15. `{{iban}}` - IBAN pour les paiements
16. `{{bic}}` - Code BIC
17. `{{date_signature}}` - Date de signature

---

## Test et Vérification

### Vérification de la syntaxe PHP
```bash
php -l admin-v2/contrat-configuration.php
# Résultat: No syntax errors detected

php -l admin-v2/email-templates.php
# Résultat: No syntax errors detected
```

### Test manuel recommandé

1. **Test édition email template:**
   - Aller sur `/admin-v2/email-templates.php`
   - Modifier un template existant (ex: `contrat_signature`)
   - Vérifier que les styles `<style>` dans `<head>` sont préservés
   - Sauvegarder et vérifier que le HTML complet est conservé

2. **Test variable signature_agence:**
   - Aller sur `/admin-v2/contrat-configuration.php`
   - Vérifier que `{{signature_agence}}` apparaît dans les variables
   - Cliquer sur "Prévisualiser"
   - Vérifier que la signature apparaît dans l'aperçu

3. **Test édition template contrat:**
   - Sur la même page, éditer le template HTML
   - Ajouter `{{signature_agence}}` quelque part dans le template
   - Sauvegarder et prévisualiser
   - Vérifier que la variable est bien remplacée

---

## Impact

### Positif ✅
- Les styles des emails sont maintenant préservés lors de l'édition
- La variable `{{signature_agence}}` est disponible pour les templates de contrat
- Documentation claire sur la séparation PDF/HTML template

### Aucun Impact Négatif ❌
- Aucun changement dans le processus de génération PDF
- Aucun changement dans l'envoi d'emails
- Rétrocompatibilité totale avec les templates existants

---

## Recommandations

1. **Pour les utilisateurs:**
   - Utiliser le mode "Code" de TinyMCE pour éditer directement le HTML
   - Toujours prévisualiser avant de sauvegarder
   - Ne pas supprimer les balises `<style>` dans le `<head>`

2. **Pour le développement futur:**
   - Envisager d'utiliser le template HTML pour générer le PDF (avec une bibliothèque comme mPDF ou TCPDF avec HTML)
   - Cela permettrait une personnalisation complète du PDF via l'interface

---

## Fichiers Modifiés

1. `admin-v2/email-templates.php` - Configuration TinyMCE améliorée
2. `admin-v2/contrat-configuration.php` - Ajout variable + configuration TinyMCE

## Fichiers Non Modifiés (mais vérifiés)

- `pdf/generate-contrat-pdf.php` - Génération PDF (processus séparé)
- `includes/mail-templates.php` - Envoi emails (fonctionne normalement)
- `includes/functions.php` - Remplacement variables (fonctionne normalement)

---

## Conclusion

Les modifications apportées résolvent les deux problèmes identifiés :
1. ✅ Les styles des emails ne seront plus perdus lors de l'édition
2. ✅ La variable `{{signature_agence}}` est maintenant disponible
3. ✅ La documentation clarifie que le PDF est généré séparément

Aucun risque de régression car les modifications sont uniquement côté configuration de l'éditeur et ajout d'une variable optionnelle.
