# Modification du Processus de Signature de Contrat - BCC Admin

## Vue d'ensemble

Cette modification implémente l'envoi en **copie cachée (BCC)** des emails de demande de justificatif de paiement aux administrateurs, garantissant que le client ne voit pas les adresses email des administrateurs.

## Contexte

### Problème Initial

Après la signature d'un contrat, le système envoie plusieurs emails :
1. Email de confirmation au client avec le contrat PDF
2. Email de demande de justificatif de paiement au client
3. Email de notification aux administrateurs

**Exigence** : Les administrateurs doivent recevoir une copie de l'email de demande de justificatif en **BCC (copie cachée)**, invisible pour le client.

## Solution Implémentée

### 1. Nouvelles Fonctionnalités Email

#### Paramètre `$addAdminBcc`

Ajout d'un nouveau paramètre optionnel aux fonctions d'envoi d'email :

**`sendEmail()`** - `includes/mail-templates.php`
```php
function sendEmail($to, $subject, $body, $attachmentPath = null, $isHtml = true, 
                   $isAdminEmail = false, $replyTo = null, $replyToName = null, 
                   $addAdminBcc = false)
```

**`sendTemplatedEmail()`** - `includes/functions.php`
```php
function sendTemplatedEmail($templateId, $to, $variables = [], $attachmentPath = null, 
                           $isAdminEmail = false, $addAdminBcc = false)
```

#### Comportement de `$addAdminBcc`

Quand `$addAdminBcc = true` :
1. **Récupère tous les administrateurs actifs** depuis la table `administrateurs`
2. **Ajoute leurs emails en BCC** (copie cachée invisible)
3. **Ajoute également** l'email configuré dans `$config['ADMIN_EMAIL_BCC']`

**Important** : Les emails sont ajoutés en **BCC**, pas en CC, donc :
- ✅ Les administrateurs reçoivent l'email
- ✅ Le client ne voit PAS les adresses des administrateurs
- ✅ Les administrateurs ne voient PAS les adresses des autres administrateurs

### 2. Modification du Workflow de Signature

**Fichier** : `signature/step3-documents.php`

```php
// Envoyer l'email de confirmation avec le contrat PDF
sendTemplatedEmail('contrat_finalisation_client', $locataire['email'], 
                   $variables, $pdfPath, false, false);

// Envoyer l'email de demande de justificatif de paiement avec admin en BCC
sendTemplatedEmail('demande_justificatif_paiement', $locataire['email'], 
                   $variables, null, false, true);  // ← true = addAdminBcc
```

**Changement** : Le dernier paramètre de l'appel `demande_justificatif_paiement` est maintenant `true`.

### 3. Template Email Configurable

Le template `demande_justificatif_paiement` est entièrement configurable dans l'interface d'administration.

**Accès** : `/admin-v2/email-templates.php`

**Variables disponibles** :
- `{{nom}}` - Nom du locataire
- `{{prenom}}` - Prénom du locataire
- `{{reference}}` - Référence du contrat
- `{{depot_garantie}}` - Montant du dépôt de garantie formaté
- `{{lien_upload}}` - Lien pour uploader le justificatif
- `{{signature}}` - Signature email automatique

## Configuration

### Configuration des Emails Administrateurs

**Dans `includes/config.php`** :

```php
// Emails administrateurs pour les notifications
'ADMIN_EMAIL' => 'location@myinvest-immobilier.com',
'ADMIN_EMAIL_BCC' => 'contact@myinvest-immobilier.com',
```

### Configuration de la Base de Données

Les administrateurs actifs sont automatiquement récupérés depuis la table `administrateurs` :

```sql
SELECT email FROM administrateurs WHERE actif = TRUE
```

Pour ajouter/modifier des administrateurs, utilisez l'interface d'administration ou exécutez :

```sql
INSERT INTO administrateurs (nom, prenom, email, actif) 
VALUES ('Nom', 'Prénom', 'admin@myinvest-immobilier.com', TRUE);
```

## Flux de Travail Complet

### Après Signature du Contrat

1. **Client reçoit** :
   - ✉️ Email de confirmation avec PDF du contrat
   - ✉️ Email de demande de justificatif de paiement

2. **Administrateurs reçoivent** :
   - ✉️ Email de notification séparé avec PDF du contrat
   - 🔒 **Copie BCC** de l'email de demande de justificatif (invisible pour le client)

### Avantages

- ✅ **Transparence** : Le client ne voit que son adresse email
- ✅ **Visibilité** : Les admins reçoivent tous les emails importants
- ✅ **Flexibilité** : Template entièrement configurable
- ✅ **Traçabilité** : Admins au courant de toutes les communications

## Tests

### Test Manuel

1. **Configurer SMTP** dans `includes/config.local.php`
2. **Créer un contrat de test** et générer un lien de signature
3. **Compléter le workflow de signature**
4. **Vérifier** :
   - Client reçoit 2 emails
   - Admins reçoivent l'email de demande de justificatif en BCC
   - Client ne voit pas les adresses admin dans les headers

### Script de Test

Un script de test est disponible : `test-admin-bcc.php`

```bash
php test-admin-bcc.php
```

Ce script vérifie :
- ✅ Signatures des fonctions correctes
- ✅ Templates email existent
- ✅ Configuration BCC présente
- ✅ Code workflow correct

## Rétrocompatibilité

**Tous les appels existants continuent de fonctionner** car le nouveau paramètre `$addAdminBcc` a une valeur par défaut de `false`.

### Exemples

```php
// Ancien code - continue de fonctionner
sendTemplatedEmail('template_id', 'client@email.com', $vars);

// Nouveau code - avec BCC admin
sendTemplatedEmail('template_id', 'client@email.com', $vars, null, false, true);
```

## Migrations Requises

Les migrations suivantes doivent être exécutées :

1. **Migration 038** : Crée le template `demande_justificatif_paiement`
2. **Migration 041** : Met à jour le template avec le bouton d'upload

```bash
php run-migrations.php
```

## Sécurité

### Validation des Emails

- ✅ Tous les emails sont validés avec `filter_var($email, FILTER_VALIDATE_EMAIL)`
- ✅ Seuls les administrateurs **actifs** reçoivent les emails
- ✅ Gestion des erreurs avec logs appropriés

### Protection des Données

- 🔒 **BCC** garantit que les adresses email ne sont pas exposées
- 🔒 Aucun email admin visible par le client
- 🔒 Conformité RGPD : minimisation des données exposées

## Dépannage

### Les Admins Ne Reçoivent Pas d'Emails

1. **Vérifier la table administrateurs** :
   ```sql
   SELECT * FROM administrateurs WHERE actif = TRUE;
   ```

2. **Vérifier la configuration** :
   ```php
   var_dump($config['ADMIN_EMAIL_BCC']);
   ```

3. **Vérifier les logs** :
   - Les erreurs sont loggées dans les error logs PHP
   - Rechercher : "Could not fetch admin emails for BCC"

### Template Introuvable

1. **Vérifier que les migrations ont été exécutées** :
   ```bash
   php run-migrations.php
   ```

2. **Vérifier dans l'interface admin** :
   - Aller sur `/admin-v2/email-templates.php`
   - Rechercher `demande_justificatif_paiement`

### SMTP Non Configuré

Si vous voyez "Configuration SMTP incomplète" :

1. **Créer** `includes/config.local.php`
2. **Configurer** :
   ```php
   <?php
   $config['SMTP_PASSWORD'] = 'votre-mot-de-passe';
   $config['SMTP_USERNAME'] = 'contact@myinvest-immobilier.com';
   ```

## Documentation Associée

- **SUPPRESSION_ETAPE_PAIEMENT.md** - Contexte de la suppression de l'étape
- **IMPLEMENTATION_COMPLETE_PAYMENT_STEP.md** - Implémentation initiale
- **CONFIG_ADMIN_EMAILS.md** - Configuration des emails admin

## Support

Pour toute question :
1. Consulter les logs d'erreur PHP
2. Vérifier la configuration SMTP
3. Tester avec `test-admin-bcc.php`
4. Consulter l'interface admin `/admin-v2/email-templates.php`

---

**Date de modification** : 2026-02-10  
**Version** : 1.0  
**Statut** : ✅ Implémenté et testé
