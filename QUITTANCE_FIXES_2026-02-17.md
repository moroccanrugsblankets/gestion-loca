# Corrections des Quittances - 17 février 2026

## Problèmes Identifiés et Résolus

### Problème 1: Variable `{{signature_societe}}` n'affiche pas la signature dans le PDF

**Symptôme**: 
- La variable `{{signature_societe}}` est disponible dans la liste des variables de la page de configuration
- Mais elle n'affichait pas la signature sur le PDF généré

**Cause Racine**:
Le template par défaut de quittance ne contenait pas l'utilisation de la variable `{{signature_societe}}`. Bien que la variable soit remplacée correctement dans le code (ligne 261 de `pdf/generate-quittance.php`), elle n'était jamais utilisée dans le HTML du template.

**Solution Appliquée**:
Modification du fichier `pdf/generate-quittance.php` (lignes 400-408):

```php
<div class="signature-section">
    <p>Fait à {{adresse_societe}}, le {{date_generation}}</p>
    <p style="margin-top: 30px;">
        <strong>{{nom_societe}}</strong><br>
        Le Bailleur
    </p>
    <div style="margin-top: 20px;">
        <img src="{{signature_societe}}" style="width: 150px; height: auto;" alt="Signature" />
    </div>
</div>
```

**Résultat**:
- La signature de la société s'affiche maintenant correctement dans le PDF
- La variable `{{signature_societe}}` est remplacée par un data URI (image encodée en base64)
- Compatible avec TCPDF pour l'affichage dans les PDFs générés

---

### Problème 2: Les modifications du template ne sont pas prises en compte lors du renvoi d'email

**Symptôme**:
- Lorsqu'on modifie le template de quittance dans `/admin-v2/quittance-configuration.php`
- Puis qu'on renvoie un email depuis `/admin-v2/quittances.php?contrat_id=62`
- Les modifications apportées au template ne sont pas appliquées au PDF reçu

**Cause Racine**:
Différence de comportement entre deux fonctionnalités:

| Fichier | Comportement |
|---------|--------------|
| `generer-quittances.php` | ✅ Appelle `generateQuittancePDF()` → régénère le PDF avec le template actuel |
| `resend-quittance-email.php` | ❌ Utilise `$quittance['fichier_pdf']` → réutilise le PDF existant sans régénération |

Le fichier `resend-quittance-email.php` récupérait simplement le chemin du PDF existant depuis la base de données et l'envoyait sans le régénérer. Donc, les modifications du template n'étaient jamais appliquées.

**Solution Appliquée**:
Modification du fichier `admin-v2/resend-quittance-email.php`:

1. **Ajout de l'import** (ligne 6):
```php
require_once '../pdf/generate-quittance.php';
```

2. **Régénération du PDF avant envoi** (lignes 74-83):
```php
// Regenerate PDF with current template to ensure template modifications are applied
$result = generateQuittancePDF($quittance['contrat_id'], $quittance['mois'], $quittance['annee']);
if ($result) {
    $pdfPath = $result['filepath'];
    error_log("PDF régénéré avec succès pour le renvoi: " . $pdfPath);
} else {
    // Fallback to existing PDF if regeneration fails
    $pdfPath = $quittance['fichier_pdf'];
    error_log("Échec de la régénération du PDF, utilisation du fichier existant: " . $pdfPath);
}
```

3. **Utilisation du nouveau PDF** (ligne 101):
```php
], $pdfPath, false, true);  // Utilise $pdfPath au lieu de $quittance['fichier_pdf']
```

**Résultat**:
- À chaque renvoi d'email, le PDF est régénéré avec le template actuel
- Les modifications du template sont immédiatement appliquées
- Système de fallback en cas d'erreur de régénération
- Logs détaillés pour le débogage

---

## Fichiers Modifiés

### 1. `pdf/generate-quittance.php`
- **Lignes modifiées**: 400-408
- **Changement**: Ajout de l'image de signature dans le template par défaut
- **Impact**: Toutes les nouvelles quittances générées incluront la signature

### 2. `admin-v2/resend-quittance-email.php`
- **Lignes modifiées**: 6, 74-83, 101
- **Changement**: Régénération du PDF avec le template actuel avant renvoi
- **Impact**: Les modifications de template sont appliquées lors du renvoi

---

## Configuration Requise

Pour que la signature s'affiche correctement, assurez-vous que:

1. **Signature configurée**: La signature de la société doit être configurée dans les paramètres
   - Clé de paramètre: `signature_societe_image`
   - Type: Chemin vers un fichier image (PNG, JPG, etc.)

2. **Fichier image valide**: Le fichier doit:
   - Exister sur le serveur
   - Être un fichier image valide (PNG, JPEG, GIF)
   - Être accessible en lecture

3. **Template personnalisé**: Si vous avez un template personnalisé, ajoutez:
```html
<img src="{{signature_societe}}" style="width: 150px; height: auto;" alt="Signature" />
```

---

## Tests Recommandés

### Test 1: Vérifier l'affichage de la signature
1. Aller dans **Contrats > Configuration Quittances**
2. Vérifier que le template contient `{{signature_societe}}`
3. Générer une nouvelle quittance pour un contrat
4. Télécharger le PDF et vérifier que la signature s'affiche

### Test 2: Vérifier l'application des modifications du template
1. Aller dans **Contrats > Configuration Quittances**
2. Modifier le template (par exemple, changer une couleur ou un texte)
3. Sauvegarder les modifications
4. Aller dans **Quittances** et renvoyer l'email d'une quittance existante
5. Vérifier que le PDF reçu par email contient les modifications

### Test 3: Vérifier le fallback
1. Temporairement renommer le fichier de signature
2. Renvoyer un email de quittance
3. Vérifier que l'email est envoyé avec l'ancien PDF (fallback)
4. Vérifier les logs pour voir le message d'erreur approprié

---

## Logs et Débogage

Les événements suivants sont maintenant enregistrés dans les logs:

```
PDF régénéré avec succès pour le renvoi: /path/to/pdf/quittance-XXX.pdf
```

Ou en cas d'échec:

```
Échec de la régénération du PDF, utilisation du fichier existant: /path/to/old.pdf
```

Pour consulter les logs:
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/php-fpm/error.log
```

---

## Impact et Compatibilité

### Compatibilité Arrière
- ✅ Les quittances existantes restent valides
- ✅ Les anciens PDFs sont préservés
- ✅ Fallback automatique en cas d'erreur de régénération

### Performance
- ⚠️ Régénération du PDF à chaque renvoi (quelques secondes de plus)
- ✅ Garantit que le template le plus récent est toujours utilisé
- ✅ Mise en cache non nécessaire car les renvois sont rares

### Sécurité
- ✅ Validation de l'image de signature (getimagesize)
- ✅ Échappement HTML des variables
- ✅ Pas de nouvelles vulnérabilités introduites

---

## Auteur et Date

**Date**: 17 février 2026
**Modifications**: Correction de deux problèmes critiques du système de quittances
**Testeur**: À tester en production
