# Guide de Configuration

## Configuration de base

### 1. Base de données unique

⚠️ **Important:** Ce système utilise maintenant une base de données unique `bail_signature` pour toutes les fonctionnalités (candidatures, contrats, signature électronique, états des lieux, paiements).

Créer la base de données :
```bash
mysql -u root -p < database.sql
```

Cette commande créera:
- Base de données unique `bail_signature`
- 11 tables interconnectées avec clés étrangères
- Système complet de gestion du cycle de vie des baux
- Données de test et compte administrateur

### 2. Fichier de configuration unique

Le fichier `includes/config.php` contient toutes les configurations de l'application.

**Variables importantes à modifier :**

```php
// Base de données
define('DB_HOST', 'localhost');          // Hôte de la base de données
define('DB_NAME', 'bail_signature');     // Nom de la base de données unique
define('DB_USER', 'root');               // Utilisateur MySQL
define('DB_PASS', '');                   // Mot de passe MySQL

// URL de l'application
define('SITE_URL', 'http://localhost/contrat-bail');  // URL complète de votre site
define('CANDIDATURE_URL', SITE_URL . '/candidature/');
define('ADMIN_URL', SITE_URL . '/admin/');

// Email
define('MAIL_FROM', 'contact@myinvest-immobilier.com');
define('MAIL_FROM_NAME', 'MY Invest Immobilier');
```

### 3. Permissions des dossiers

```bash
chmod 755 uploads/
chmod 755 pdf/
```

### 4. Configuration Apache

Le fichier `.htaccess` est déjà configuré. Assurez-vous que `mod_rewrite` est activé :

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 5. Configuration PHP

Paramètres recommandés dans `php.ini` :

```ini
upload_max_filesize = 5M
post_max_size = 6M
max_execution_time = 300
memory_limit = 128M

; Extensions requises
extension=pdo_mysql
extension=gd
extension=mbstring
extension=fileinfo
```

## Configuration avancée

### SMTP pour l'envoi d'emails

Pour utiliser SMTP au lieu de `mail()` :

1. Installer PHPMailer :
```bash
composer require phpmailer/phpmailer
```

2. Modifier `includes/mail-templates.php` pour utiliser PHPMailer :

```php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body, $attachmentPath = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'votre-email@example.com';
        $mail->Password = 'votre-mot-de-passe';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Expéditeur et destinataire
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Contenu
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Pièce jointe
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email: {$mail->ErrorInfo}");
        return false;
    }
}
```

### Génération PDF avec wkhtmltopdf

1. Installer wkhtmltopdf :

Ubuntu/Debian :
```bash
sudo apt-get install wkhtmltopdf
```

CentOS/RHEL :
```bash
sudo yum install wkhtmltopdf
```

macOS :
```bash
brew install wkhtmltopdf
```

2. Le code de génération PDF détectera automatiquement wkhtmltopdf et l'utilisera.

### Sécurité en production

**Important : En production, modifiez ces paramètres dans `includes/config.php` :**

```php
// Désactiver l'affichage des erreurs
error_reporting(0);
ini_set('display_errors', 0);

// Activer le logging des erreurs
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');
```

### HTTPS (SSL/TLS)

Pour activer HTTPS :

1. Obtenir un certificat SSL (Let's Encrypt recommandé)
2. Modifier `SITE_URL` dans config.php :
```php
define('SITE_URL', 'https://votre-domaine.com');
```

3. Forcer HTTPS dans `.htaccess` :
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## Variables d'environnement

Pour plus de sécurité, vous pouvez utiliser des variables d'environnement :

1. Créer un fichier `.env` (ne pas committer) :
```
DB_HOST=localhost
DB_NAME=bail_signature
DB_USER=root
DB_PASS=your_password
SITE_URL=https://example.com
```

2. Installer vlucas/phpdotenv :
```bash
composer require vlucas/phpdotenv
```

3. Charger les variables dans `config.php` :
```php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
// etc.
```

## Tests

### Tester l'application

1. Créer la base de données
2. Accéder à : `http://localhost/admin/`
3. Générer un lien de signature pour le logement RP-01
4. Tester le parcours de signature complet

### Problèmes courants

**Erreur de connexion à la base de données**
- Vérifier les credentials dans `config.php`
- S'assurer que MySQL est démarré
- Vérifier les droits de l'utilisateur MySQL

**Les emails ne partent pas**
- Configurer SMTP (voir ci-dessus)
- Vérifier les logs PHP
- Tester avec un service SMTP tiers (SendGrid, Mailgun, etc.)

**Upload de fichiers ne fonctionne pas**
- Vérifier les permissions du dossier `uploads/`
- Augmenter `upload_max_filesize` dans php.ini
- Vérifier que l'extension `fileinfo` est activée

## Maintenance

### Sauvegarde

**Base de données :**
```bash
mysqldump -u root -p bail_signature > backup_$(date +%Y%m%d).sql
```

**Fichiers uploadés :**
```bash
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/
```

**PDF générés :**
```bash
tar -czf pdf_backup_$(date +%Y%m%d).tar.gz pdf/
```

### Nettoyage

Supprimer les contrats expirés après X jours :
```sql
DELETE FROM contrats WHERE statut = 'expire' AND date_expiration < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Logs

Consulter les logs d'actions :
```sql
SELECT * FROM logs ORDER BY created_at DESC LIMIT 100;
```

## Support

Pour toute question : contact@myinvest-immobilier.com
