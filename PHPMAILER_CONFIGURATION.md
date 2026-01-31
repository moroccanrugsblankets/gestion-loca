# Configuration de PHPMailer pour l'envoi d'emails

## Présentation

Ce projet utilise maintenant **PHPMailer** pour l'envoi d'emails au format HTML. PHPMailer offre plusieurs avantages :

- ✅ Envoi d'emails au format HTML avec des designs professionnels
- ✅ Support SMTP pour une meilleure délivrabilité
- ✅ Gestion automatique des pièces jointes
- ✅ Fallback vers `mail()` natif en cas d'échec SMTP
- ✅ Gestion des erreurs améliorée

## Installation

PHPMailer est déjà installé dans le projet via Composer. Les fichiers sont dans le dossier `vendor/` (qui est exclu du git).

Pour réinstaller PHPMailer si nécessaire :
```bash
composer install
```

Ou manuellement :
```bash
composer require phpmailer/phpmailer
```

## Configuration SMTP

### ⚠️ IMPORTANT : Configuration obligatoire pour envoyer des emails

**Les emails ne seront PAS envoyés tant que la configuration SMTP n'est pas complétée.**

Si vous voyez un message "Email envoyé" mais ne recevez rien, c'est que la configuration SMTP n'est pas correcte.

### 1. Créer le fichier de configuration local

**ÉTAPE OBLIGATOIRE** : Copiez le fichier template et ajoutez vos credentials :

```bash
cp includes/config.local.php.template includes/config.local.php
```

### 2. Éditer le fichier de configuration locale

Ouvrez le fichier `includes/config.local.php` et configurez les paramètres SMTP selon votre fournisseur d'email :

```php
<?php
return [
    // ⚠️ OBLIGATOIRE : Renseignez votre mot de passe SMTP
    'SMTP_PASSWORD' => 'votre-mot-de-passe-ou-app-password',
    
    // Optionnel : Modifier si nécessaire
    // 'SMTP_HOST' => 'smtp.gmail.com',
    // 'SMTP_USERNAME' => 'votre-email@gmail.com',
];
```

**Note** : Le fichier `config.local.php` est ignoré par Git pour des raisons de sécurité. Vos credentials ne seront jamais commitées.

### 3. Exemples de configuration selon les fournisseurs

#### Gmail
Dans `includes/config.local.php` :
```php
<?php
return [
    'SMTP_HOST' => 'smtp.gmail.com',
    'SMTP_PORT' => 587,
    'SMTP_SECURE' => 'tls',
    'SMTP_USERNAME' => 'votre-email@gmail.com',
    'SMTP_PASSWORD' => 'mot-de-passe-application', // Utiliser un mot de passe d'application
];
```

**Note :** Pour Gmail, vous devez créer un "mot de passe d'application" :
1. Allez dans votre compte Google
2. Sécurité > Validation en deux étapes
3. Mots de passe d'application
4. Générez un mot de passe pour "Mail"

#### Office 365 / Outlook
```php
<?php
return [
    'SMTP_HOST' => 'smtp.office365.com',
    'SMTP_PORT' => 587,
    'SMTP_SECURE' => 'tls',
    'SMTP_USERNAME' => 'votre-email@outlook.com',
    'SMTP_PASSWORD' => 'votre-mot-de-passe',
];
```

#### OVH
```php
<?php
return [
    'SMTP_HOST' => 'ssl0.ovh.net',
    'SMTP_PORT' => 587,
    'SMTP_SECURE' => 'tls',
    'SMTP_USERNAME' => 'votre-email@votredomaine.com',
    'SMTP_PASSWORD' => 'votre-mot-de-passe',
];
```

#### SendGrid
```php
<?php
return [
    'SMTP_HOST' => 'smtp.sendgrid.net',
    'SMTP_PORT' => 587,
    'SMTP_SECURE' => 'tls',
    'SMTP_USERNAME' => 'apikey',
    'SMTP_PASSWORD' => 'votre-api-key-sendgrid',
];
```

### 4. Mode sans SMTP (fallback)

Si vous ne souhaitez pas utiliser SMTP, définissez dans `config.local.php` :
```php
<?php
return [
    'SMTP_AUTH' => false,
];
```

Dans ce cas, PHPMailer utilisera la fonction `mail()` native de PHP.

## Utilisation

### Envoi d'email simple

```php
require_once 'includes/mail-templates.php';

$success = sendEmail(
    'destinataire@example.com',
    'Sujet de l\'email',
    '<h1>Bonjour</h1><p>Ceci est un email HTML</p>',
    null,  // Pas de pièce jointe
    true   // Format HTML
);

if ($success) {
    echo "Email envoyé avec succès";
} else {
    echo "Erreur lors de l'envoi";
}
```

### Envoi avec pièce jointe

```php
$success = sendEmail(
    'destinataire@example.com',
    'Votre contrat',
    '<p>Veuillez trouver ci-joint votre contrat</p>',
    '/chemin/vers/contrat.pdf',  // Chemin du fichier
    true
);
```

### Utilisation des templates HTML

Le projet inclut plusieurs templates HTML pré-configurés :

#### Email de candidature reçue
```php
$htmlBody = getCandidatureRecueEmailHTML($prenom, $nom, $logement, $uploaded_count);
sendEmail($email, 'Candidature reçue', $htmlBody, null, true);
```

#### Email d'invitation à signer
```php
$htmlBody = getInvitationSignatureEmailHTML($signatureLink, $adresse, $nb_locataires);
sendEmail($email, 'Contrat à signer', $htmlBody, null, true);
```

#### Email de changement de statut
```php
$htmlBody = getStatusChangeEmailHTML($nom_complet, $statut, $commentaire);
sendEmail($email, 'Mise à jour', $htmlBody, null, true);
```

## Test de la configuration

Pour vérifier que PHPMailer est correctement installé et configuré :

```bash
php test-phpmailer.php
```

Ce script vérifie :
- ✓ Chargement de PHPMailer
- ✓ Présence des fonctions d'envoi
- ✓ Fonctionnement des templates HTML
- ✓ Configuration SMTP

## Dépannage

### L'email n'est pas envoyé

**Symptôme** : Le message "Email envoyé avec succès" apparaît mais aucun email n'est reçu.

**Solution** :
1. **Vérifiez que `config.local.php` existe et contient SMTP_PASSWORD**
   ```bash
   cat includes/config.local.php
   ```
   Si le fichier n'existe pas, créez-le à partir du template (voir section Configuration ci-dessus)

2. **Vérifiez les logs d'erreur** dans `error.log`
   ```bash
   tail -50 error.log
   ```
   Cherchez les messages contenant "ERREUR CRITIQUE: Configuration SMTP incomplète"

3. **Activez le mode debug** dans `config.local.php` :
   ```php
   'SMTP_DEBUG' => 2,
   ```

4. **Vérifiez vos credentials SMTP** (username, password)
5. **Vérifiez le port et le protocole** (TLS/SSL)
6. **Vérifiez que votre serveur autorise les connexions SMTP sortantes**

### Les emails arrivent en spam

1. Configurez SPF, DKIM et DMARC pour votre domaine
2. Utilisez un serveur SMTP réputé (Gmail, SendGrid, etc.)
3. Vérifiez que l'adresse FROM correspond à votre domaine

### Erreur "Could not authenticate"

- Vérifiez vos identifiants SMTP
- Pour Gmail, utilisez un mot de passe d'application
- Vérifiez que l'authentification est activée sur votre compte email

### Erreur "Connection refused"

- Vérifiez que le port SMTP est correct (587 pour TLS, 465 pour SSL)
- Vérifiez que votre pare-feu autorise les connexions sortantes sur ce port
- Vérifiez que votre hébergeur ne bloque pas les connexions SMTP

## Sécurité

⚠️ **Important** :
- Ne commitez JAMAIS vos mots de passe SMTP dans Git
- Utilisez des variables d'environnement ou un fichier de config local
- Créez un fichier `includes/config.local.php` (qui est ignoré par git) pour vos credentials :

```php
<?php
// includes/config.local.php
define('SMTP_PASSWORD', 'votre-mot-de-passe-secret');
```

Puis dans `includes/config.php` :
```php
// Charger la config locale si elle existe
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
```

## Support

Pour plus d'informations sur PHPMailer, consultez :
- Documentation officielle : https://github.com/PHPMailer/PHPMailer
- Guide de configuration : https://github.com/PHPMailer/PHPMailer/wiki

## Résolution du problème de candidature

Le message d'erreur "Une erreur est survenue lors de l'envoi de votre candidature" était causé par l'échec de l'envoi d'email avec la fonction `mail()` native de PHP.

Avec PHPMailer :
1. L'envoi d'email est plus fiable (SMTP)
2. Un système de fallback est en place si SMTP échoue
3. Les erreurs sont mieux gérées et loggées
4. Les emails sont au format HTML professionnel

La candidature sera maintenant enregistrée même si l'email échoue, et l'erreur sera loggée pour investigation.
