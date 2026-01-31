# Fix: Templates d'email manquants

## Problème

Sur la page `/admin-v2/email-templates.php`, aucun template d'email n'apparaît. Cela empêche l'envoi d'emails automatiques aux candidats et aux administrateurs, même si la configuration SMTP est correcte.

## Cause

Les templates d'email ne sont pas présents dans la table `email_templates` de la base de données. Cela peut se produire si:
- Les migrations n'ont pas été exécutées
- La base de données a été réinitialisée sans exécuter les migrations
- Les templates ont été supprimés accidentellement

## Solution

Un script d'initialisation a été créé pour créer les templates par défaut : `init-email-templates.php`

### Utilisation

1. **Exécuter le script d'initialisation:**
   ```bash
   php init-email-templates.php
   ```

   Pour réinitialiser tous les templates aux valeurs par défaut:
   ```bash
   php init-email-templates.php --reset
   ```

2. **Vérifier que les templates ont été créés:**
   - Ouvrir `/admin-v2/email-templates.php` dans un navigateur
   - Vous devriez voir 4 templates:
     - Accusé de réception de candidature
     - Candidature acceptée
     - Candidature non retenue
     - Notification admin - Nouvelle candidature

### Templates créés

Le script crée automatiquement les 4 templates d'email suivants:

1. **candidature_recue** - Envoyé immédiatement au candidat lors de la soumission
2. **candidature_acceptee** - Envoyé au candidat si sa candidature est acceptée
3. **candidature_refusee** - Envoyé au candidat si sa candidature est refusée
4. **admin_nouvelle_candidature** - Envoyé aux administrateurs pour chaque nouvelle candidature

### Variables disponibles

Chaque template supporte des variables qui sont automatiquement remplacées:

- **Communes**: `{{nom}}`, `{{prenom}}`, `{{email}}`, `{{reference}}`, `{{date}}`
- **Spécifiques**: `{{logement}}`, `{{telephone}}`, `{{revenus}}`, `{{statut_pro}}`, `{{lien_admin}}`
- **Signature**: `{{signature}}` - Insère la signature email configurée

### Modification des templates

Une fois créés, les templates peuvent être modifiés via l'interface admin:
1. Aller sur `/admin-v2/email-templates.php`
2. Cliquer sur "Modifier" pour un template
3. Modifier le sujet et/ou le corps HTML
4. Enregistrer les modifications

## Alternative: Exécuter les migrations

Si le script d'initialisation ne fonctionne pas, vous pouvez aussi exécuter toutes les migrations:

```bash
php run-migrations.php
```

Cela créera la table `email_templates` et insérera les templates par défaut (si la migration n'a pas déjà été exécutée).

## Vérification

Pour vérifier que les templates fonctionnent correctement:

1. **Test unitaire:**
   ```bash
   php test-email-templates.php
   ```

2. **Vérification manuelle:**
   - Les templates doivent apparaître dans `/admin-v2/email-templates.php`
   - Chaque template doit contenir `{{signature}}`
   - Le paramètre `email_signature` doit être défini dans la table `parametres`

## Notes importantes

- Les templates utilisent HTML avec des styles inline pour assurer la compatibilité email
- La variable `{{signature}}` est remplacée lors de l'envoi par la fonction `sendEmail()`
- Les templates sont marqués comme "actifs" par défaut
- Si vous modifiez un template, les modifications s'appliquent immédiatement aux prochains emails envoyés
- **Option --reset**: Utilisez `php init-email-templates.php --reset` pour réinitialiser tous les templates aux valeurs par défaut (écrase les modifications personnalisées)

## Support

Si les templates ne s'affichent toujours pas après avoir exécuté le script:
1. Vérifier que la base de données est accessible
2. Vérifier que la table `email_templates` existe: `SHOW TABLES LIKE 'email_templates'`
3. Vérifier le contenu de la table: `SELECT identifiant, nom FROM email_templates`
4. Consulter les logs d'erreurs: `error.log` à la racine du projet
