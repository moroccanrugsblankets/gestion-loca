# Application de Signature de Bail en Ligne

Application web PHP complète pour la signature électronique de contrats de bail - **MY Invest Immobilier**

## 📋 Description

Cette application permet de gérer le processus complet de signature électronique de baux d'habitation :
- Génération de liens sécurisés avec expiration 24h
- Parcours de signature pour 1 ou 2 locataires
- Signature électronique sur canvas HTML5
- Upload sécurisé de pièces d'identité
- Génération automatique de PDF du bail signé
- Envoi d'emails automatiques
- Interface d'administration pour le suivi

## 🚀 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web Apache ou Nginx
- Extension PHP : PDO, GD, mbstring, fileinfo
- (Optionnel) wkhtmltopdf pour génération PDF avancée

### Étapes d'installation

1. **Cloner le repository**
   ```bash
   git clone <repository-url>
   cd contrat-de-bail
   ```

2. **Configurer la base de données**
   ```bash
   mysql -u root -p < database.sql
   ```
   
   Cela créera :
   - La base de données `bail_signature`
   - Les tables nécessaires (logements, contrats, locataires, logs)
   - Un logement de test (RP-01)

3. **Configurer l'application**
   
   Éditer le fichier `includes/config.php` et ajuster :
   ```php
   // Configuration de la base de données
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'bail_signature');
   define('DB_USER', 'votre_utilisateur');
   define('DB_PASS', 'votre_mot_de_passe');
   
   // URL de base de l'application
   define('SITE_URL', 'http://votre-domaine.com');
   ```

4. **Créer les dossiers et permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 pdf/
   ```

5. **Configurer le serveur web**
   
   Pour Apache, le fichier `.htaccess` est déjà fourni.
   
   Pour Nginx, ajouter dans la configuration :
   ```nginx
   location /uploads/ {
       location ~ \.php$ {
           deny all;
       }
   }
   ```

6. **Configurer l'envoi d'emails**
   
   Par défaut, l'application utilise la fonction `mail()` de PHP.
   Pour un environnement de production, il est recommandé de configurer SMTP.

## 📁 Structure du projet

```
contrat-de-bail/
├── admin/                      # Interface d'administration
│   ├── index.php              # Redirection vers generate-link.php
│   ├── generate-link.php      # Génération de liens de signature
│   ├── dashboard.php          # Tableau de bord des contrats
│   └── contract-details.php   # Détails d'un contrat (AJAX)
│
├── signature/                  # Espace de signature locataire
│   ├── index.php              # Validation du lien et acceptation
│   ├── step1-info.php         # Saisie informations locataire
│   ├── step2-signature.php    # Signature électronique
│   ├── step3-documents.php    # Upload pièces d'identité
│   └── confirmation.php       # Page de confirmation
│
├── includes/                   # Fichiers communs
│   ├── config.php             # Configuration
│   ├── db.php                 # Connexion base de données
│   ├── functions.php          # Fonctions utilitaires
│   └── mail-templates.php     # Templates d'emails
│
├── assets/                     # Ressources statiques
│   ├── css/
│   │   └── style.css          # Styles CSS
│   ├── js/
│   │   └── signature.js       # Gestion signature canvas
│   └── images/
│       └── logo.png           # Logo (à ajouter)
│
├── uploads/                    # Documents uploadés (sécurisé)
│   └── .htaccess              # Protection Apache
│
├── pdf/                        # PDF générés
│   ├── generate-bail.php      # Génération PDF
│   └── download.php           # Téléchargement PDF
│
├── database.sql                # Script de création DB
├── .htaccess                   # Configuration Apache
└── README.md                   # Ce fichier
```

## 🎯 Utilisation

### Interface d'administration

1. **Accéder à l'administration**
   ```
   http://votre-domaine.com/admin/
   ```

2. **Générer un lien de signature**
   - Sélectionner le logement (ex: RP-01)
   - Choisir le nombre de locataires (1 ou 2)
   - Cliquer sur "Générer le lien"
   - Copier l'email pré-formaté et l'envoyer au locataire

3. **Suivre les contrats**
   - Accéder au tableau de bord
   - Filtrer par statut (en attente, signé, expiré)
   - Voir les détails de chaque contrat
   - Télécharger les PDF des baux signés

### Parcours locataire

1. **Cliquer sur le lien reçu par email**
   - Le lien est valide 24h
   - Accepter ou refuser la procédure

2. **Remplir les informations**
   - Nom, prénom, date de naissance, email

3. **Signer électroniquement**
   - Dessiner la signature sur le canvas
   - Recopier "Lu et approuvé"

4. **Uploader les pièces d'identité**
   - Recto et verso (JPG, PNG ou PDF, max 5 Mo)
   - Indiquer s'il y a un second locataire

5. **Confirmation**
   - Recevoir l'email de confirmation avec le bail en PDF
   - Effectuer le virement du dépôt de garantie

## 🔒 Sécurité

L'application implémente plusieurs mesures de sécurité :

- **Tokens CSRF** : Protection contre les attaques CSRF sur tous les formulaires
- **Validation des uploads** : Vérification du type MIME réel des fichiers
- **Tokens uniques** : Génération cryptographiquement sécurisée avec `random_bytes()`
- **Expiration** : Les liens expirent après 24h
- **Protection des uploads** : `.htaccess` empêche l'exécution de scripts
- **Échappement** : Toutes les données utilisateur sont nettoyées
- **Logs** : Toutes les actions importantes sont enregistrées
- **IP tracking** : Enregistrement de l'IP lors de la signature

## 📊 Base de données

### Tables

- **logements** : Informations sur les logements disponibles
- **contrats** : Contrats de bail avec leur statut
- **locataires** : Informations et signatures des locataires
- **logs** : Traçabilité de toutes les actions

### Statuts des contrats

- `en_attente` : Lien envoyé, en attente de signature
- `signe` : Bail signé par tous les locataires
- `expire` : Lien expiré (24h dépassées)
- `annule` : Contrat refusé par le locataire

## 🎨 Personnalisation

### Logo

Placer votre logo dans :
```
assets/images/logo.png
```

### Couleurs et styles

Modifier le fichier `assets/css/style.css`

### Emails

Modifier les templates dans `includes/mail-templates.php`

## 📧 Configuration email

### Utiliser SMTP (recommandé en production)

Installer PHPMailer via Composer :
```bash
composer require phpmailer/phpmailer
```

Puis modifier `includes/mail-templates.php` pour utiliser SMTP.

## 🧪 Données de test

Un logement de test est automatiquement créé :

- **Référence** : RP-01
- **Adresse** : 15 rue de la Paix, 74100 Annemasse
- **Type** : T1 Bis
- **Surface** : 26 m²
- **Loyer** : 890 €
- **Charges** : 140 €
- **Dépôt de garantie** : 1 780 €

## 🔧 Dépannage

### Les emails ne sont pas envoyés

- Vérifier la configuration de `mail()` sur le serveur
- Consulter les logs PHP
- Utiliser PHPMailer avec SMTP pour plus de fiabilité

### Erreur de connexion à la base de données

- Vérifier les identifiants dans `includes/config.php`
- S'assurer que la base de données existe
- Vérifier que l'utilisateur MySQL a les droits nécessaires

### Les fichiers ne s'uploadent pas

- Vérifier les permissions du dossier `uploads/` (755)
- Augmenter `upload_max_filesize` et `post_max_size` dans php.ini
- Vérifier que l'extension `fileinfo` est activée

### Le canvas de signature ne fonctionne pas

- Vérifier que JavaScript est activé dans le navigateur
- Consulter la console du navigateur pour les erreurs
- Tester sur un navigateur récent

## 📝 TODO / Améliorations possibles

- [ ] Authentification admin avec login/password
- [ ] Support multi-langues
- [ ] Notifications par SMS
- [ ] Interface responsive améliorée
- [ ] Export Excel des contrats
- [ ] Rappels automatiques avant expiration
- [ ] Signature électronique qualifiée (eIDAS)
- [ ] Intégration paiement en ligne
- [ ] API REST pour intégrations tierces

## 🔐 Conformité RGPD

L'application enregistre les données suivantes :
- Informations personnelles des locataires
- Signatures électroniques
- Adresses IP (pour traçabilité légale)
- Pièces d'identité

**Durée de conservation** : À définir selon vos besoins légaux

**Droits des utilisateurs** : Prévoir un mécanisme pour l'exercice des droits RGPD (accès, rectification, effacement)

## 📄 Licence

Propriétaire - MY Invest Immobilier

## 👥 Support

Pour toute question ou support :
- Email : contact@myinvest-immobilier.com

## 🙏 Remerciements

Application développée pour MY Invest Immobilier

---

**Version** : 1.0.0  
**Date** : Janvier 2026
