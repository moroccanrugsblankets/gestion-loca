# Fix: Bordure sur le logo de signature dans les emails - RÉSOLUTION FINALE

## Date
2026-02-03

## Problème Rapporté
> "Erreur de {{signature}} sur tous les mails reçu !"
> "toujours meme erreur de signature avec border ! c'est sur que c'est au moment de creation de pdf car la signature n'a pas de border !"

### Symptômes
- Bordure visible autour du **logo MY INVEST IMMOBILIER** dans les emails
- Le problème apparaît dans les PDFs générés à partir des emails
- Les logs indiquent "border=0" pour les signatures locataires mais pas pour le logo de la signature email

## Analyse du Problème

### 1. Identification de la Source
Le problème se situe dans la **signature email** stockée dans la table `parametres` avec la clé `email_signature`.

**Location:** Table `parametres` dans la base de données
- Cette signature est insérée/mise à jour via les migrations SQL
- Elle est utilisée pour remplacer le placeholder `{{signature}}` dans tous les emails

### 2. Code d'Insertion de la Signature
**Location:** `includes/mail-templates.php` lignes 216-233

```php
// Replace {{signature}} placeholder if present in body
if ($isHtml && strpos($body, '{{signature}}') !== false) {
    // Get email signature from parametres
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'email_signature' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $signature = ($result && !empty($result['valeur'])) ? $result['valeur'] : '';
    $finalBody = str_replace('{{signature}}', $signature, $body);
}
```

### 3. Cause Racine
Les migrations qui créent/mettent à jour la signature email contenaient:

```html
<img src="https://www.myinvest-immobilier.com/images/logo.png" 
     alt="MY Invest Immobilier" 
     style="max-width: 120px;">
```

**Problème:** L'attribut HTML `border="0"` était **manquant**.

#### Pourquoi c'est Important
1. **TCPDF (générateur de PDF):** TCPDF ajoute une bordure par défaut aux images qui n'ont pas l'attribut `border="0"`
2. **Clients Email:** Certains clients email (Outlook, Gmail) ajoutent aussi des bordures par défaut
3. **CSS seul ne suffit pas:** Les styles CSS `border: 0` ne sont pas toujours respectés par TCPDF

## Solution Appliquée

### Migrations Modifiées

#### 1. Migration `005_add_email_signature.sql`
**AVANT:**
```html
<img src="https://www.myinvest-immobilier.com/images/logo.png" 
     style="border: 0; border-style: none; outline: none; display: block;">
```

**APRÈS:**
```html
<img src="https://www.myinvest-immobilier.com/images/logo.png" 
     style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" 
     border="0">
```

#### 2. Migration `013_update_email_signature_format.sql`
**AVANT:**
```html
<img src="https://www.myinvest-immobilier.com/images/logo.png" 
     alt="MY Invest Immobilier" 
     style="max-width: 120px;">
```

**APRÈS:**
```html
<img src="https://www.myinvest-immobilier.com/images/logo.png" 
     alt="MY Invest Immobilier" 
     style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" 
     border="0">
```

#### 3. Migration `025_fix_email_signature_border.sql`
Cette migration a été **complètement réécrite** pour:
- Ajouter l'attribut HTML `border="0"`
- Conserver tous les styles CSS anti-bordure
- Restaurer le texte "Sincères salutations"
- Maintenir la structure complète de la signature

**Format Final de la Signature:**
```html
<p>Sincères salutations</p>
<p style="margin-top: 20px;">
    <table style="border: 0; border-collapse: collapse;">
        <tbody>
            <tr>
                <td style="padding-right: 15px;">
                    <img src="https://www.myinvest-immobilier.com/images/logo.png" 
                         alt="MY Invest Immobilier" 
                         style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" 
                         border="0">
                </td>
                <td>
                    <h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3>
                </td>
            </tr>
        </tbody>
    </table>
</p>
```

### Attributs Anti-Bordure (Combinaison pour Maximum Compatibilité)

1. **Attribut HTML:** `border="0"`
   - Interprété par TCPDF
   - Reconnu par tous les clients email
   
2. **Style CSS:** `border: 0;`
   - Pour les navigateurs modernes
   
3. **Style CSS:** `border-style: none;`
   - Supprime explicitement le style de bordure
   
4. **Style CSS:** `outline: none;`
   - Supprime les contours de focus
   
5. **Style CSS:** `display: block;`
   - Évite les problèmes d'alignement inline

## Outils Créés

### 1. Script de Test: `test-signature-border-fix.php`

**Usage:**
```bash
php test-signature-border-fix.php
```

**Vérifie:**
- ✅ Présence de l'attribut HTML `border="0"`
- ✅ Présence des styles CSS anti-bordure
- ✅ Structure complète de la signature
- ✅ Balise `<img>` correctement formatée

### 2. Script de Mise à Jour Manuelle: `update-signature-border.php`

**Usage:**
```bash
php update-signature-border.php
```

**Fonction:**
- Met à jour directement la signature dans la base de données
- Utile si les migrations ont déjà été exécutées
- Applique le format correct immédiatement

## Procédure d'Application

### Option 1: Exécuter les Migrations (Recommandé)
```bash
php run-migrations.php
```

Cela appliquera toutes les migrations en attente, incluant les corrections.

### Option 2: Mise à Jour Manuelle (Si migrations déjà exécutées)
```bash
php update-signature-border.php
```

Cela met à jour directement la signature dans la base de données.

### Option 3: Mise à Jour SQL Directe
```sql
UPDATE parametres 
SET valeur = '<p>Sincères salutations</p><p style="margin-top: 20px;"><table style="border: 0; border-collapse: collapse;"><tbody><tr><td style="padding-right: 15px;"><img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" border="0"></td><td><h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3></td></tr></tbody></table></p>',
    updated_at = NOW()
WHERE cle = 'email_signature';
```

## Vérification

### 1. Vérifier la Signature en Base de Données
```bash
php test-signature-border-fix.php
```

Résultat attendu:
```
✓ TOUS LES TESTS SONT PASSÉS
La signature email devrait s'afficher correctement sans bordure
```

### 2. Tester l'Envoi d'Email
1. Déclencher l'envoi d'un email (ex: nouvel contrat)
2. Vérifier l'email reçu
3. Vérifier le PDF généré
4. Le logo ne doit **pas** avoir de bordure

### 3. Vérifier les Logs
Dans les logs, vous devriez voir:
```
PDF Generation: Image #1 - Type: Chemin relatif ../, Converti: ...
PDF Generation: Image #2 - Type: Data URI (signature/image encodée), conservée telle quelle
PDF Generation: === FIN TRAITEMENT IMAGES - 2/2 image(s) traitée(s) avec succès ===
```

## Fichiers Modifiés

### Migrations SQL
- `migrations/005_add_email_signature.sql`
- `migrations/013_update_email_signature_format.sql`
- `migrations/025_fix_email_signature_border.sql`

### Scripts de Test/Utilitaires Créés
- `test-signature-border-fix.php`
- `update-signature-border.php`

## Notes Importantes

### Ce Fix NE Concerne PAS
- ❌ Les signatures des locataires dans les PDFs (déjà corrigées)
- ❌ La signature de l'agence dans les PDFs (déjà corrigée)
- ❌ L'aperçu des signatures dans l'admin (déjà corrigé)

### Ce Fix Concerne UNIQUEMENT
- ✅ Le **logo dans la signature email** (table `parametres.email_signature`)
- ✅ Visible dans **tous les emails envoyés** par le système
- ✅ Visible dans les **PDFs générés à partir des emails**

## Comparaison Avant/Après

### AVANT
```
┌─────────────────────────────────────┐  ← Bordure indésirable
│  [LOGO MY INVEST IMMOBILIER]        │
└─────────────────────────────────────┘
   MY INVEST IMMOBILIER
```

### APRÈS
```
   [LOGO MY INVEST IMMOBILIER]           ← Pas de bordure
   MY INVEST IMMOBILIER
```

## Conclusion

✅ **Problème résolu:** Le logo de signature email n'aura plus de bordure

✅ **Compatibilité maximale:** Utilisation de l'attribut HTML + styles CSS

✅ **Testable:** Scripts de test et mise à jour disponibles

✅ **Pas d'impact:** Ne modifie pas les signatures locataires/agence existantes

## Actions Requises

1. **Exécuter:** `php run-migrations.php` OU `php update-signature-border.php`
2. **Tester:** `php test-signature-border-fix.php`
3. **Vérifier:** Envoyer un email test et vérifier le PDF généré
