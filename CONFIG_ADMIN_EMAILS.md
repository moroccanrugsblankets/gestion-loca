# Configuration des Emails Administrateurs

## Objectif

Le système permet d'envoyer tous les emails destinés aux administrateurs vers **deux adresses distinctes** : une adresse principale et une adresse secondaire.

## Configuration

### 1. Créer le fichier de configuration locale

Si le fichier `includes/config.local.php` n'existe pas encore :

```bash
cp includes/config.local.php.template includes/config.local.php
```

### 2. Configurer les adresses emails

Éditez le fichier `includes/config.local.php` et configurez les adresses :

```php
return [
    // Email principal de l'administrateur
    'ADMIN_EMAIL' => 'votre-email@domaine.com',
    
    // Email secondaire de l'administrateur (optionnel)
    'ADMIN_EMAIL_SECONDARY' => 'deuxieme-admin@domaine.com',
    
    // Autres configurations...
];
```

### 3. Notes importantes

- Le fichier `config.local.php` est **automatiquement ignoré par Git** (voir `.gitignore`)
- **Ne commitez JAMAIS** ce fichier dans Git car il contient des informations sensibles
- Si `ADMIN_EMAIL_SECONDARY` n'est pas défini, seul l'email principal recevra les notifications
- Les deux emails recevront les notifications **en parallèle** (pas en CC)

## Fonctionnalités

### Notifications automatiques aux administrateurs

Les administrateurs reçoivent des notifications pour :

1. **Nouvelle candidature soumise** : Dès qu'un candidat soumet son dossier
2. **Changement de statut** : Lorsqu'une candidature change de statut
3. **Événements importants** : Signature de contrat, paiement reçu, etc.

### Fonction d'envoi

La fonction `sendEmailToAdmins()` est disponible dans `includes/mail-templates.php` :

```php
// Envoyer une notification aux administrateurs
$result = sendEmailToAdmins(
    'Sujet de l\'email',
    $htmlBody,          // Corps HTML
    '/path/to/file.pdf', // Pièce jointe (optionnel)
    true                // Format HTML (true par défaut)
);

// Vérifier le résultat
if ($result['success']) {
    echo "Email envoyé à : " . implode(', ', $result['sent_to']);
} else {
    echo "Erreurs : " . implode(', ', $result['errors']);
}
```

### Structure du résultat

La fonction `sendEmailToAdmins()` retourne un tableau :

```php
[
    'success' => bool,        // true si au moins un email a été envoyé
    'sent_to' => array,      // Liste des emails envoyés avec succès
    'errors' => array        // Liste des erreurs rencontrées
]
```

## Exemple complet

```php
require_once 'includes/mail-templates.php';

// Préparer le contenu de l'email
$subject = 'Nouvelle candidature reçue';
$body = '<h1>Une nouvelle candidature vient d\'être soumise</h1>';

// Envoyer aux administrateurs
$result = sendEmailToAdmins($subject, $body);

if ($result['success']) {
    error_log("Notification envoyée aux admins : " . implode(', ', $result['sent_to']));
}
```

## Dépannage

### Les emails ne sont pas envoyés

1. Vérifiez que les adresses sont correctement configurées dans `config.local.php`
2. Vérifiez les logs d'erreur PHP : `tail -f error.log`
3. Vérifiez la configuration SMTP dans `config.local.php`
4. Testez l'envoi d'email avec `test-phpmailer.php`

### Un seul email reçoit les notifications

- Vérifiez que `ADMIN_EMAIL_SECONDARY` est bien défini dans `config.local.php`
- Vérifiez que la valeur n'est pas vide

### Les emails arrivent en spam

- Configurez correctement SPF, DKIM et DMARC pour votre domaine
- Utilisez un serveur SMTP authentifié (Gmail, SendGrid, etc.)

## Modifications apportées

### Fichiers créés/modifiés

1. **includes/config.local.php.template** : Template de configuration
2. **includes/mail-templates.php** : Ajout de la fonction `sendEmailToAdmins()` et du template `getAdminNewCandidatureEmailHTML()`
3. **candidature/submit.php** : Ajout de la notification admin lors de la soumission
4. **admin-v2/candidature-detail.php** : Correction de l'erreur SQL

### Migration depuis l'ancien système

Si vous utilisiez auparavant un seul email admin, aucune migration n'est nécessaire. Le système est rétrocompatible :

- Si seul `ADMIN_EMAIL` est défini : fonctionne comme avant
- Si `ADMIN_EMAIL` et `ADMIN_EMAIL_SECONDARY` sont définis : envoi aux deux adresses
