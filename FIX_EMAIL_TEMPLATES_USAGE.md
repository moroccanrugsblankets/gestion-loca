# Fix: Email Templates - Tous les emails utilisent maintenant les templates configurés

## Problème identifié

Le système envoyait des emails avec des sujets et contenus hardcodés au lieu d'utiliser les templates d'email configurés dans le backoffice.

**Exemple du problème :**
- Email reçu : `[ADMIN] Contrat signé - BAIL-69814790F242E`
- Template dans backoffice : `Contrat signé - {{reference}} - Vérification requise`

## Fichiers modifiés

### 1. `/admin-v2/change-status.php`
**Avant :** Envoyait des emails avec des sujets hardcodés lors du changement de statut
```php
$subject = "Candidature acceptée - MyInvest Immobilier";
$htmlBody = getStatusChangeEmailHTML($nom_complet, $nouveau_statut, $commentaire);
sendEmail($to, $subject, $htmlBody, null, true, $isAdminEmail);
```

**Après :** Utilise les templates d'email configurés
```php
$templateId = 'candidature_acceptee';
$variables = ['nom' => ..., 'prenom' => ..., 'commentaire' => ...];
sendTemplatedEmail($templateId, $to, $variables, null, $isAdminEmail);
```

### 2. `/candidature/reponse-candidature.php`
**Avant :** Sujets hardcodés pour les réponses par email
```php
$emailSubject = 'Suite à votre candidature';
$emailBody = getStatusChangeEmailHTML($nomComplet, ucfirst($newStatus), '');
sendEmail($candidature['email'], $emailSubject, $emailBody, null, true);
```

**Après :** Utilise les templates
```php
$templateId = ($action === 'positive') ? 'candidature_acceptee' : 'candidature_refusee';
sendTemplatedEmail($templateId, $candidature['email'], $variables, null, false);
```

### 3. Templates ajoutés

Nouveaux templates créés pour tous les changements de statut :

| Template ID | Sujet | Usage |
|-------------|-------|-------|
| `statut_visite_planifiee` | "Visite de logement planifiée - MY Invest Immobilier" | Quand une visite est planifiée |
| `statut_contrat_envoye` | "Contrat de bail - MY Invest Immobilier" | Quand le contrat est envoyé |
| `statut_contrat_signe` | "Contrat signé - MY Invest Immobilier" | Quand le contrat est signé (statut candidature) |

### 4. Template mis à jour

**Template : `contrat_finalisation_admin`**
- **Ancien sujet :** `[ADMIN] Contrat signé - {{reference}}`
- **Nouveau sujet :** `Contrat signé - {{reference}} - Vérification requise`

Ce changement correspond au template configuré dans le backoffice.

## Migration

La migration `023_update_email_templates_add_status_templates.sql` :
1. Met à jour le sujet du template `contrat_finalisation_admin`
2. Ajoute les 3 nouveaux templates de statut

## Vérification

Tous les envois d'emails dans l'application utilisent maintenant :
- ✅ `sendTemplatedEmail()` - Pour les emails basés sur templates
- ✅ `sendEmailToAdmins()` - Pour les notifications admin (supporte templates via paramètre)
- ✅ Templates configurables via backoffice (`/admin-v2/email-templates.php`)

**Exception :** `/admin-v2/send-email-candidature.php` permet toujours aux admins d'envoyer des emails personnalisés (fonctionnalité intentionnelle).

## Instructions de déploiement

1. Déployer les changements de code
2. Exécuter la migration : `php run-migrations.php`
3. OU exécuter : `php init-email-templates.php` pour mettre à jour tous les templates

## Variables disponibles dans les templates

### Templates de statut
- `{{nom}}` - Nom du candidat
- `{{prenom}}` - Prénom du candidat  
- `{{email}}` - Email du candidat
- `{{commentaire}}` - Commentaire optionnel de l'admin
- `{{signature}}` - Signature email (ajoutée automatiquement)

### Template admin contrat finalisé
- `{{reference}}` - Référence du contrat (ex: BAIL-XXX)
- `{{logement}}` - Adresse du logement
- `{{locataires}}` - Liste des locataires
- `{{depot_garantie}}` - Montant du dépôt
- `{{date_finalisation}}` - Date de finalisation
- `{{lien_admin}}` - Lien vers l'admin
- `{{signature}}` - Signature email

## Test

Pour tester les changements :
1. Créer une candidature
2. Changer le statut depuis l'admin avec l'option "Envoyer un email"
3. Vérifier que l'email reçu correspond au template configuré dans le backoffice
4. Modifier le template dans `/admin-v2/email-templates.php`
5. Renvoyer un email et vérifier que les modifications sont appliquées
