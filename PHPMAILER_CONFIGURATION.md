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

### 1. Éditer le fichier de configuration

Ouvrez le fichier `includes/config.php` et configurez les paramètres SMTP selon votre fournisseur d'email :

```php
// Configuration SMTP pour PHPMailer
define('SMTP_HOST', 'smtp.gmail.com');              // Serveur SMTP
define('SMTP_PORT', 587);                            // Port SMTP (587 pour TLS, 465 pour SSL)
define('SMTP_SECURE', 'tls');                        // 'tls' ou 'ssl'
define('SMTP_AUTH', true);                           // Authentification SMTP
define('SMTP_USERNAME', 'votre-email@example.com');  // Votre email SMTP
define('SMTP_PASSWORD', 'votre-mot-de-passe');       // Votre mot de passe SMTP
define('SMTP_DEBUG', 0);                             // 0 = off, 1 = client, 2 = client et serveur
```

### 2. Exemples de configuration selon les fournisseurs

#### Gmail
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'votre-email@gmail.com');
define('SMTP_PASSWORD', 'mot-de-passe-application'); // Utiliser un mot de passe d'application
```

**Note :** Pour Gmail, vous devez créer un "mot de passe d'application" :
1. Allez dans votre compte Google
2. Sécurité > Validation en deux étapes
3. Mots de passe d'application
4. Générez un mot de passe pour "Mail"

#### Office 365 / Outlook
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'votre-email@outlook.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe');
```

#### OVH
```php
define('SMTP_HOST', 'ssl0.ovh.net');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'votre-email@votredomaine.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe');
```

#### SendGrid
```php
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'apikey');
define('SMTP_PASSWORD', 'votre-api-key-sendgrid');
```

### 3. Mode sans SMTP (fallback)

Si vous ne souhaitez pas utiliser SMTP, définissez :
```php
define('SMTP_AUTH', false);
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

1. **Vérifiez les logs d'erreur** dans `error.log`
2. **Activez le mode debug** : `define('SMTP_DEBUG', 2);` dans `config.php`
3. **Vérifiez vos credentials SMTP** (username, password)
4. **Vérifiez le port et le protocole** (TLS/SSL)
5. **Vérifiez que votre serveur autorise les connexions SMTP sortantes**

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
- Vérifiez que votre hébergeur n'bloque pas les connexions SMTP

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
