# Refactoring: Configuration avec `$config` Array

## Vue d'ensemble

Le projet a été refactoré pour utiliser un tableau de configuration `$config` au lieu de constantes définies avec `define()`. Cette approche moderne offre plus de flexibilité et facilite la gestion de la configuration.

## Changements effectués

### 1. Fichier `includes/config.php`

**Avant:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bail_signature');
define('SMTP_HOST', 'smtp.gmail.com');
// etc.
```

**Après:**
```php
$config = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'bail_signature',
    'SMTP_HOST' => 'smtp.gmail.com',
    // etc.
];
```

### 2. Chargement de la configuration locale

Le système charge maintenant `config.local.php` s'il existe et fusionne les valeurs:

```php
if (file_exists(__DIR__ . '/config.local.php')) {
    $localConfig = require __DIR__ . '/config.local.php';
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}
```

### 3. Fichier `includes/config.local.php`

Nouveau fichier qui retourne un tableau au lieu de définir des constantes:

```php
<?php
return [
    // 'DB_PASS' => 'votre_mot_de_passe',
    // 'SMTP_PASSWORD' => 'votre_mot_de_passe_smtp',
];
```

## Comment utiliser la nouvelle configuration

### Dans les scripts PHP

**1. Inclure la configuration:**
```php
require_once __DIR__ . '/includes/config.php';
```

**2. Accéder aux valeurs:**
```php
// Au niveau global
echo $config['DB_HOST'];
echo $config['SITE_URL'];
```

**3. Dans les fonctions:**
```php
function maFonction() {
    global $config;
    
    $host = $config['DB_HOST'];
    $email = $config['COMPANY_EMAIL'];
    // ...
}
```

### Fichiers modifiés

Les fichiers suivants ont été mis à jour pour utiliser `$config`:

#### Core (includes/)
- ✅ `includes/config.php` - Conversion complète en array
- ✅ `includes/db.php` - Connexion base de données
- ✅ `includes/mail-templates.php` - Templates email
- ✅ `includes/functions.php` - Fonctions utilitaires

#### Admin
- ✅ `admin/generate-link.php`
- ✅ `admin-v2/envoyer-signature.php`

#### Candidature
- ✅ `candidature/confirmer-interet.php`

#### Cron
- ✅ `cron/process-candidatures.php`

#### PDF
- ✅ `pdf/download.php`
- ✅ `pdf/generate-bail.php`

#### Signature
- ✅ `signature/confirmation.php`

#### Tests
- ✅ `test.php`
- ✅ `test-phpmailer.php`
- ✅ `validate-consolidation.php`
- ✅ `test-config.php` (nouveau)

## Clés de configuration disponibles

### Base de données
- `DB_HOST` - Hôte de la base de données
- `DB_NAME` - Nom de la base de données
- `DB_USER` - Utilisateur de la base de données
- `DB_PASS` - Mot de passe de la base de données
- `DB_CHARSET` - Charset de la base de données

### Email / SMTP
- `MAIL_FROM` - Email expéditeur
- `MAIL_FROM_NAME` - Nom expéditeur
- `SMTP_HOST` - Serveur SMTP
- `SMTP_PORT` - Port SMTP
- `SMTP_SECURE` - Sécurité SMTP (tls/ssl)
- `SMTP_AUTH` - Authentification SMTP
- `SMTP_USERNAME` - Utilisateur SMTP
- `SMTP_PASSWORD` - Mot de passe SMTP
- `SMTP_DEBUG` - Niveau de debug SMTP

### URLs
- `SITE_URL` - URL principale du site
- `CANDIDATURE_URL` - URL des candidatures
- `ADMIN_URL` - URL de l'administration

### Répertoires
- `UPLOAD_DIR` - Répertoire des uploads
- `PDF_DIR` - Répertoire des PDFs
- `DOCUMENTS_DIR` - Répertoire des documents

### Entreprise
- `COMPANY_NAME` - Nom de l'entreprise
- `COMPANY_EMAIL` - Email de l'entreprise
- `COMPANY_PHONE` - Téléphone de l'entreprise

### Coordonnées bancaires
- `IBAN` - IBAN
- `BIC` - BIC
- `BANK_NAME` - Nom de la banque

### Workflow
- `DELAI_REPONSE_JOURS_OUVRES` - Délai de réponse en jours ouvrés
- `JOURS_OUVRES` - Array des jours ouvrés
- `REVENUS_MIN_ACCEPTATION` - Revenus minimum pour acceptation
- `STATUTS_PRO_ACCEPTES` - Statuts professionnels acceptés

### Contrat
- `BAILLEUR_NOM` - Nom du bailleur
- `BAILLEUR_REPRESENTANT` - Représentant du bailleur
- `BAILLEUR_EMAIL` - Email du bailleur

### DPE
- `DPE_CLASSE_ENERGIE` - Classe énergie DPE
- `DPE_CLASSE_GES` - Classe GES DPE
- `DPE_VALIDITE` - Date de validité DPE

### Pagination
- `ITEMS_PER_PAGE` - Items par page
- `MAX_ITEMS_PER_PAGE` - Maximum d'items par page

### Sécurité
- `CSRF_TOKEN_NAME` - Nom du token CSRF
- `CSRF_KEY` - Clé CSRF
- `REFERENCE_SALT` - Salt pour références
- `MAX_FILE_SIZE` - Taille max des fichiers
- `ALLOWED_EXTENSIONS` - Extensions autorisées
- `ALLOWED_MIME_TYPES` - Types MIME autorisés
- `TOKEN_EXPIRY_HOURS` - Expiration des tokens en heures

## Avantages de cette approche

1. **Flexibilité** - Facile de surcharger des valeurs avec `config.local.php`
2. **Sécurité** - Les valeurs sensibles restent dans `config.local.php` (ignoré par Git)
3. **Maintenabilité** - Plus facile à lire et à modifier qu'avec `define()`
4. **Testabilité** - Peut facilement être mocké dans les tests
5. **Performance** - Légèrement plus performant que les constantes

## Migration depuis l'ancien système

Si vous avez des fichiers personnalisés utilisant les anciennes constantes:

**Avant:**
```php
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
```

**Après:**
```php
$dsn = "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'];
```

**Dans une fonction:**
```php
function myFunction() {
    global $config;  // Important!
    
    $email = $config['COMPANY_EMAIL'];
    // ...
}
```

## Tests

Exécutez `php test-config.php` pour vérifier que la configuration fonctionne correctement:

```bash
cd /chemin/vers/projet
php test-config.php
```

Le test vérifie:
- ✅ Que `$config` est un tableau
- ✅ Que toutes les clés essentielles sont présentes
- ✅ Que les fonctions utilitaires fonctionnent avec `$config`
- ✅ Que `config.local.php` est chargé s'il existe

## Notes importantes

1. **Toujours utiliser `global $config;`** dans les fonctions
2. **Ne jamais commiter `config.local.php`** avec des mots de passe réels
3. **Les valeurs calculées** (comme `CANDIDATURE_URL`) sont générées automatiquement
4. **Les fonctions utilitaires** (calculerJoursOuvres, etc.) utilisent `global $config`

## Support

Pour toute question concernant cette refactorisation, consultez:
- `test-config.php` - Test complet de la configuration
- `includes/config.php` - Configuration principale
- Ce fichier - Documentation complète
