# Résolution des Problèmes du Contrat

## Problèmes Identifiés et Résolus

### 1. Téléchargement du Contrat - "ID de contrat invalide"

**Symptôme:**
- Sur la page `admin-v2/contrats.php`, cliquer sur le bouton "Télécharger le contrat" ouvre une nouvelle page avec l'erreur : "ID de contrat invalide"
- URL générée : `pdf/download.php?contract_id=6`

**Cause:**
- Incohérence dans le nom du paramètre URL
- `contrats.php` envoyait `contract_id` (avec underscore anglais)
- `pdf/download.php` attendait `contrat_id` (avec underscore français)

**Solution:**
- ✅ **Corrigé dans** : `admin-v2/contrats.php` ligne 272
- **Changement** : `contract_id` → `contrat_id`
- **Résultat** : Le téléchargement fonctionne maintenant correctement et reste sur la même page (le navigateur gère automatiquement le téléchargement du fichier)

```php
// AVANT
<a href="../pdf/download.php?contract_id=<?php echo $contrat['id']; ?>" ...>

// APRÈS
<a href="../pdf/download.php?contrat_id=<?php echo $contrat['id']; ?>" ...>
```

---

### 2. Variable d'Expiration du Lien Non Interprétée

**Symptôme:**
- Dans l'email de signature du contrat, le texte affiche littéralement : `⚠️ IMPORTANT : Ce lien expire le {{date_expiration_lien_contrat}}`
- La variable `{{date_expiration_lien_contrat}}` n'est pas remplacée par la date réelle

**Diagnostic:**
- ✅ Le code PHP passe correctement la variable :
  - `envoyer-signature.php` ligne 70
  - `renvoyer-lien-signature.php` ligne 86
- ✅ La fonction `replaceTemplateVariables()` fonctionne correctement
- ✅ La migration 019 existe et est correcte
- ❌ **La migration 019 n'a probablement pas été exécutée en production**

**Solution - Action Requise:**

**Pour résoudre ce problème, exécuter la migration 019 :**

```bash
cd /home/runner/work/contrat-de-bail/contrat-de-bail
php run-migrations.php
```

**Ou manuellement en base de données :**

```sql
UPDATE email_templates 
SET 
    corps_html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 15px 30px; background: #667eea; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .expiry-warning { background: #fee; border-left: 4px solid #f00; padding: 15px; margin: 20px 0; border-radius: 4px; color: #d00; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Contrat de Bail à Signer</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Merci de prendre connaissance de la procédure ci-dessous.</p>
            
            <div class="alert-box">
                <strong>⏰ Action immédiate requise</strong><br>
                Procédure à compléter avant la date limite indiquée ci-dessous
            </div>
            
            <h3>📋 Procédure de signature du bail</h3>
            <p>Merci de compléter l''ensemble de la procédure avant la date d''expiration, incluant :</p>
            <ol>
                <li><strong>La signature du contrat de bail en ligne</strong></li>
                <li><strong>La transmission d''une pièce d''identité</strong> en cours de validité (CNI ou passeport)</li>
                <li><strong>Le règlement du dépôt de garantie</strong> (2 mois de loyer) par virement bancaire instantané</li>
            </ol>
            
            <div class="info-box">
                <p style="margin: 0;"><strong>Important :</strong></p>
                <ul style="margin: 10px 0 0 0;">
                    <li>La prise d''effet du bail et la remise des clés interviendront uniquement après réception complète de l''ensemble des éléments</li>
                    <li>À défaut de réception complète du dossier dans le délai indiqué, la réservation du logement pourra être remise en disponibilité sans autre formalité</li>
                </ul>
            </div>
            
            <div class="expiry-warning">
                <strong>⚠️ IMPORTANT :</strong> Ce lien expire le <strong>{{date_expiration_lien_contrat}}</strong>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{lien_signature}}" class="btn">🖊️ Accéder au Contrat de Bail</a>
            </div>
            
            <p>Nous restons à votre disposition en cas de question.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    variables_disponibles = '["nom", "prenom", "email", "adresse", "lien_signature", "date_expiration_lien_contrat"]'
WHERE identifiant = 'contrat_signature';
```

---

## Vérification

### Tester le Téléchargement du Contrat

1. Se connecter à l'admin : `admin-v2/contrats.php`
2. Trouver un contrat avec statut "Signé"
3. Cliquer sur le bouton "Télécharger PDF" (icône download)
4. **Résultat attendu** : Le fichier PDF se télécharge directement sans ouvrir de nouvelle page

### Tester la Variable d'Expiration Email

Après avoir exécuté la migration 019 :

1. Créer ou renvoyer un lien de signature via `admin-v2/envoyer-signature.php`
2. Vérifier l'email reçu
3. **Résultat attendu** : Le texte affiche une date formatée comme :
   ```
   ⚠️ IMPORTANT : Ce lien expire le 02/02/2026 à 15:30
   ```

---

## Fichiers Modifiés

### Changements de Code

1. **admin-v2/contrats.php**
   - Ligne 272 : Changement du paramètre URL de `contract_id` à `contrat_id`

### Migrations Requises

1. **migrations/019_add_date_expiration_to_email_template.sql**
   - Ajoute la variable `{{date_expiration_lien_contrat}}` au template d'email
   - Mise à jour du champ `variables_disponibles` dans `email_templates`

---

## Fonctionnement Technique

### Flux de la Variable d'Expiration

```
1. admin-v2/envoyer-signature.php ou renvoyer-lien-signature.php
   ↓
2. Calcul de date_expiration (Y-m-d H:i:s)
   ↓
3. Formatage pour email : date('d/m/Y à H:i')
   ↓
4. Passage à sendTemplatedEmail() avec variable 'date_expiration_lien_contrat'
   ↓
5. replaceTemplateVariables() remplace {{date_expiration_lien_contrat}}
   ↓
6. Email envoyé avec date formatée visible
```

### Téléchargement PDF

```
1. Utilisateur clique sur bouton "Télécharger PDF"
   ↓
2. Requête GET vers pdf/download.php?contrat_id=X
   ↓
3. Vérification du contrat (existe + statut = 'signe')
   ↓
4. Recherche ou génération du fichier PDF
   ↓
5. Envoi des headers (Content-Type, Content-Disposition)
   ↓
6. Lecture et envoi du fichier (readfile)
   ↓
7. Navigateur déclenche le téléchargement (reste sur même page)
```

---

## Notes de Sécurité

- ✅ Les IDs de contrat sont validés et convertis en entiers
- ✅ Vérification du statut du contrat avant téléchargement
- ✅ Variables d'email échappées automatiquement par `htmlspecialchars()`
- ✅ Utilisation de requêtes préparées (PDO)
