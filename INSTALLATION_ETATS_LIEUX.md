# Installation - États des Lieux Compréhensifs

## Prérequis

- Base de données MySQL/MariaDB configurée
- PHP 7.4 ou supérieur
- PHPMailer installé via Composer
- Serveur web (Apache/Nginx)

## Instructions d'installation

### 1. Exécuter les migrations

Les migrations doivent être exécutées dans l'ordre pour ajouter tous les champs nécessaires à la table `etats_lieux`.

```bash
# Migration 026 - Ajoute les champs de base
php migrations/026_fix_etats_lieux_schema.php

# Migration 027 - Ajoute les champs complémentaires
php migrations/027_enhance_etats_lieux_comprehensive.php
```

### 2. Vérifier les permissions des dossiers

```bash
# Créer le dossier d'upload s'il n'existe pas
mkdir -p uploads/etats_lieux

# Donner les permissions appropriées
chmod 755 uploads/etats_lieux
```

### 3. Configuration email

Vérifier que la configuration SMTP est correctement définie dans `includes/config.php`:

```php
$config['smtp'] = [
    'host' => 'smtp.example.com',
    'username' => 'votre-email@example.com',
    'password' => 'votre-mot-de-passe',
    'port' => 587,
];
```

### 4. Tester l'installation

1. Se connecter à l'administration
2. Accéder à "États des lieux"
3. Créer un nouvel état des lieux de test
4. Vérifier que tous les champs sont présents

## Structure des fichiers

### Fichiers principaux

- `admin-v2/etats-lieux.php` - Liste des états des lieux
- `admin-v2/create-etat-lieux.php` - Création d'un nouvel état des lieux
- `admin-v2/edit-etat-lieux.php` - Formulaire complet d'édition
- `admin-v2/view-etat-lieux.php` - Vue en lecture seule
- `admin-v2/finalize-etat-lieux.php` - Finalisation et envoi
- `admin-v2/delete-etat-lieux.php` - Suppression
- `admin-v2/download-etat-lieux.php` - Téléchargement PDF

### Fichiers de gestion des photos

- `admin-v2/upload-etat-lieux-photo.php` - Upload de photos
- `admin-v2/delete-etat-lieux-photo.php` - Suppression de photos

### Migrations

- `migrations/026_fix_etats_lieux_schema.php` - Migration de base
- `migrations/027_enhance_etats_lieux_comprehensive.php` - Migration complémentaire

## Tables de base de données

Après exécution des migrations, les tables suivantes seront créées/modifiées:

### `etats_lieux`
Table principale contenant tous les champs de l'état des lieux.

### `etat_lieux_locataires`
Lien entre état des lieux et locataires avec informations de signature.

### `etat_lieux_photos`
Stockage des informations sur les photos uploadées.

## Dépannage

### Erreur "Table etat_lieux_photos doesn't exist"

Exécuter la migration 026:
```bash
php migrations/026_fix_etats_lieux_schema.php
```

### Erreur "Column 'locataire_email' doesn't exist"

Exécuter la migration 027:
```bash
php migrations/027_enhance_etats_lieux_comprehensive.php
```

### Erreur lors de l'upload de photos

Vérifier les permissions:
```bash
ls -la uploads/
# Le dossier doit être accessible en écriture par le serveur web
```

### Email non envoyé

1. Vérifier la configuration SMTP dans `includes/config.php`
2. Vérifier les logs d'erreur PHP
3. Tester l'envoi d'email avec un script de test

## Sécurité

### Recommandations

1. **Permissions des fichiers**
   - Fichiers PHP: 644
   - Dossiers: 755
   - Dossier uploads: 755

2. **Protection du dossier uploads**
   Un fichier `.htaccess` est déjà présent dans `uploads/` pour bloquer l'exécution de scripts PHP.

3. **Validation des uploads**
   - Types de fichiers autorisés: JPEG, PNG, GIF
   - Taille maximale: 5MB
   - Validation par MIME type (finfo_file)

4. **Protection SQL**
   - Toutes les requêtes utilisent PDO avec prepared statements
   - Échappement HTML avec htmlspecialchars()

## Support

Pour toute question ou problème:
1. Consulter les logs d'erreur PHP
2. Vérifier que toutes les migrations sont exécutées
3. Vérifier les permissions des dossiers
4. Contacter l'administrateur système

## Mise à jour depuis une version antérieure

Si vous avez déjà une table `etats_lieux` existante:

1. **Sauvegarder la base de données**
   ```bash
   mysqldump -u user -p database_name > backup.sql
   ```

2. **Exécuter les migrations**
   Les migrations vérifient l'existence des colonnes avant de les ajouter, donc elles sont sûres à exécuter même si certaines colonnes existent déjà.

3. **Vérifier les données**
   Après migration, vérifier que les états des lieux existants sont toujours accessibles.

## Checklist de vérification

- [ ] Migrations 026 et 027 exécutées
- [ ] Dossier `uploads/etats_lieux` créé avec bonnes permissions
- [ ] Configuration SMTP validée
- [ ] Accès à la page de liste des états des lieux
- [ ] Création d'un état des lieux de test réussie
- [ ] Upload de photo testé
- [ ] Génération PDF testée
- [ ] Envoi d'email testé
- [ ] Suppression testée

## Fonctionnalités validées

Une fois l'installation terminée, vous devriez pouvoir:

✅ Créer un nouvel état des lieux (entrée ou sortie)
✅ Remplir tous les champs obligatoires
✅ Uploader des photos pour chaque section
✅ Sauvegarder en mode brouillon
✅ Finaliser et envoyer par email
✅ Visualiser l'état des lieux
✅ Télécharger le PDF
✅ Supprimer un état des lieux

Si toutes ces fonctionnalités fonctionnent, l'installation est complète et réussie.
