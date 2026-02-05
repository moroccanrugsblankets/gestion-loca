# Installation Rapide - Guide de Dépannage

## ⚠️ Erreur "TCPDF ERROR:" lors de l'accès à `/admin-v2/finalize-etat-lieux.php`

### Cause

Cette erreur se produit lorsque les dépendances Composer n'ont pas été installées. L'application nécessite :
- **TCPDF** : pour la génération de PDF
- **PHPMailer** : pour l'envoi d'emails

### Solution

Exécutez simplement la commande suivante dans le répertoire du projet :

```bash
composer install
```

### Étapes détaillées

1. **Vérifier que Composer est installé**
   ```bash
   composer --version
   ```
   
   Si Composer n'est pas installé, installez-le depuis : https://getcomposer.org/

2. **Se placer dans le répertoire du projet**
   ```bash
   cd /chemin/vers/contrat-de-bail
   ```

3. **Installer les dépendances**
   ```bash
   composer install
   ```
   
   Cette commande va :
   - Créer le dossier `vendor/`
   - Télécharger TCPDF et PHPMailer
   - Générer l'autoloader

4. **Vérifier l'installation**
   ```bash
   ls -la vendor/
   ```
   
   Vous devriez voir :
   - `vendor/autoload.php`
   - `vendor/phpmailer/`
   - `vendor/tecnickcom/tcpdf/`

### Test de l'installation

Vous pouvez tester que TCPDF est correctement installé en exécutant :

```bash
php test-tcpdf-installation.php
```

Ce script vérifie :
- ✓ vendor/autoload.php existe
- ✓ Autoloader se charge correctement
- ✓ TCPDF class est disponible
- ✓ Instance TCPDF peut être créée
- ✓ Un PDF de test peut être généré

### Pourquoi le dossier `vendor/` n'est pas dans le repository ?

Le dossier `vendor/` contient les bibliothèques tierces et peut être très volumineux (plusieurs Mo). 

**Bonne pratique** : 
- Le dossier `vendor/` est exclu du repository via `.gitignore`
- Chaque développeur/serveur doit exécuter `composer install` après le clonage
- Cela garantit que chacun utilise les versions exactes spécifiées dans `composer.lock`

### En production

Sur le serveur de production, après avoir déployé le code :

```bash
# Se placer dans le répertoire du projet
cd /var/www/html/contrat-de-bail

# Installer les dépendances (sans dev)
composer install --no-dev --optimize-autoloader

# Vérifier les permissions
chmod -R 755 vendor/
```

### Erreurs courantes

#### "composer: command not found"
Composer n'est pas installé. Installez-le :
```bash
# Télécharger et installer Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### "Could not authenticate against github.com"
Si vous rencontrez des problèmes avec GitHub API, utilisez :
```bash
COMPOSER_NO_INTERACTION=1 composer install --prefer-dist
```

#### Le dossier vendor/ existe mais l'erreur persiste
Supprimez le dossier et réinstallez :
```bash
rm -rf vendor/
composer install
```

## Support

Pour toute question :
- Consulter : [README.md](README.md)
- Documentation complète : [RAPPORT_FINAL.md](RAPPORT_FINAL.md)
- Email : contact@myinvest-immobilier.com

---

**Note** : Cette installation n'est nécessaire qu'une seule fois par environnement (développement, staging, production).
