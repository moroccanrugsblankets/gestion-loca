# Module de Gestion et Rappel des Loyers

## 📋 Vue d'ensemble

Ce module permet d'automatiser l'envoi de rappels par email aux administrateurs concernant le paiement des loyers. Il offre une interface visuelle intuitive pour suivre l'état des paiements et gérer les rappels.

## ✨ Fonctionnalités principales

### 1. 📊 Interface de Gestion Visuelle
- **Tableau coloré** affichant l'état des paiements mois par mois
  - 🟢 **Vert** : Loyer payé
  - 🔴 **Rouge** : Loyer impayé
  - 🟡 **Orange** : En attente de confirmation
- **Vue synthétique** : Tous les biens côte à côte pour une compréhension immédiate
- **Statistiques en temps réel** : Nombre de loyers payés/impayés/en attente
- **Modification manuelle** : Cliquez sur une case pour changer le statut (cycle : attente → payé → impayé)

### 2. 📧 Rappels Automatiques par Email
- **Envoi automatique** aux dates configurées (par défaut : 7, 9, 15 du mois)
- **Deux types d'emails** :
  - ✅ **Confirmation** si tous les loyers sont payés
  - ⚠️ **Rappel** s'il y a des loyers impayés
- **Variable dynamique** : Un seul template qui génère automatiquement le message adapté
- **Bouton intégré** : Lien direct vers l'interface de gestion dans l'email

### 3. ⚙️ Configuration Flexible
- **Dates d'envoi personnalisables** : Sélectionnez n'importe quel jour du mois (1-31)
- **Destinataires configurables** : Choisissez les administrateurs qui reçoivent les rappels
- **Activation/Désactivation** : Toggle pour activer ou désactiver le module
- **Options d'email** : Inclure ou non le bouton vers l'interface

### 4. 🎯 Actions Manuelles
- **Envoi de rappel manuel** : Bouton pour envoyer immédiatement un rappel aux administrateurs
- **Rappel au locataire** : Depuis l'interface, envoyez un rappel directement au locataire pour un loyer impayé
- **Tracking des rappels** : Le système enregistre quand et combien de rappels ont été envoyés

## 📁 Structure des Fichiers

```
gestion-loca/
│
├── migrations/
│   └── migration_loyers_tracking.sql    # Migration pour créer les tables et paramètres
│
├── cron/
│   ├── rappel-loyers.php               # Script cron pour rappels automatiques
│   └── rappel-loyers-log.txt           # Log des exécutions (créé automatiquement)
│
└── admin-v2/
    ├── gestion-loyers.php                     # Interface principale de gestion
    └── configuration-rappels-loyers.php       # Interface de configuration
```

## 🚀 Installation

### 1. Exécuter la Migration

```bash
# Se connecter à MySQL
mysql -u root -p

# Exécuter la migration
mysql -u root -p bail_signature < migrations/migration_loyers_tracking.sql
```

Ou depuis un outil comme phpMyAdmin, importez le fichier `migrations/migration_loyers_tracking.sql`.

### 2. Vérifier l'Installation

La migration crée automatiquement :

**Tables :**
- `loyers_tracking` : Suivi mensuel des paiements par bien

**Paramètres de configuration :**
- `rappel_loyers_dates_envoi` : `[7, 9, 15]` (jours d'envoi)
- `rappel_loyers_destinataires` : `[]` (emails admins)
- `rappel_loyers_actif` : `true` (module activé)
- `rappel_loyers_inclure_bouton` : `true` (bouton dans emails)

**Templates d'email :**
- `rappel_loyers_impaye` : Email de rappel pour loyers impayés
- `confirmation_loyers_payes` : Email de confirmation si tout est payé

**Cron job :**
- `Rappel Loyers` : Configuré pour s'exécuter quotidiennement à 9h

### 3. Configurer le Cron

Le script doit être exécuté quotidiennement. Le système vérifie automatiquement si c'est un jour de rappel configuré.

**Option A : Cron système (Linux/Mac)**

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne (exécution à 9h tous les jours)
0 9 * * * php /chemin/vers/gestion-loca/cron/rappel-loyers.php
```

**Option B : Exécution manuelle via l'interface admin**

1. Connectez-vous à l'interface admin
2. Allez dans **Tâches Automatisées** (menu de gauche)
3. Trouvez "Rappel Loyers" dans la liste
4. Cliquez sur "Exécuter maintenant" pour tester

**Option C : Hébergement web (cPanel, Plesk, etc.)**

Ajoutez une tâche cron dans votre panneau d'hébergement :
```
0 9 * * * php /home/votre-user/public_html/cron/rappel-loyers.php
```

### 4. Configuration Initiale

1. **Accédez à l'interface admin** : `https://votre-domaine.com/admin-v2/`
2. **Cliquez sur "Gestion des Loyers"** dans le menu
3. **Cliquez sur "Configuration"** en haut à droite
4. **Configurez** :
   - ✅ Activez le module
   - 📅 Sélectionnez les jours d'envoi (par défaut : 7, 9, 15)
   - 👥 Cochez les administrateurs destinataires
   - ✉️ Activez le bouton vers l'interface dans les emails
5. **Enregistrez** la configuration

## 📖 Utilisation

### Interface de Gestion

#### Accès
Menu Admin → **Gestion des Loyers**

#### Fonctionnalités

**1. Vue d'ensemble**
- Cartes de statistiques en haut montrant :
  - Total de biens en location
  - Nombre de loyers payés ce mois
  - Nombre de loyers impayés
  - Nombre en attente de confirmation

**2. Tableau des paiements**
- **Colonnes** : 12 derniers mois (avec le mois en cours surligné en bleu)
- **Lignes** : Un bien par ligne avec référence, locataire et adresse
- **Cellules colorées** :
  - Cliquez pour changer le statut (cycle automatique)
  - Montant du loyer affiché dans chaque cellule
  - Pour les impayés : bouton 📧 pour envoyer un rappel au locataire

**3. Actions disponibles**
- **Envoyer rappel maintenant** : Envoie immédiatement un rappel aux administrateurs
- **Configuration** : Accède à la page de configuration
- **Rappel au locataire** : Depuis une cellule "impayé", cliquez sur l'icône enveloppe

### Configuration des Rappels

#### Accès
Menu Admin → Gestion des Loyers → **Configuration**

#### Sections

**1. Activation du Module**
- Toggle pour activer/désactiver les rappels automatiques
- Affiche le statut actuel et la date du dernier rappel

**2. Dates d'Envoi Automatique**
- Grille de 31 jours pour sélectionner les jours du mois
- Par défaut : 7, 9, 15
- Peut sélectionner autant de jours que nécessaire

**3. Administrateurs Destinataires**
- Liste de tous les administrateurs avec checkboxes
- Sélectionnez ceux qui doivent recevoir les rappels
- Si aucun n'est sélectionné, utilise `ADMIN_EMAIL` par défaut

**4. Options d'Email**
- Toggle pour inclure le bouton vers l'interface dans les emails

**5. Informations Cron**
- Affiche le statut du cron job
- Dernière exécution et son résultat
- Prochaine exécution prévue

## 🔄 Fonctionnement Technique

### Workflow Automatique

```
┌─────────────────────────────────────┐
│   Cron Job (quotidien à 9h)         │
└───────────────┬─────────────────────┘
                │
                ▼
┌─────────────────────────────────────┐
│ 1. Module actif ?                   │
│    Non → Arrêt                      │
└───────────────┬─────────────────────┘
                │ Oui
                ▼
┌─────────────────────────────────────┐
│ 2. Aujourd'hui = jour de rappel ?   │
│    Non → Arrêt                      │
└───────────────┬─────────────────────┘
                │ Oui
                ▼
┌─────────────────────────────────────┐
│ 3. Créer entries tracking si besoin │
│    (pour biens en location)         │
└───────────────┬─────────────────────┘
                │
                ▼
┌─────────────────────────────────────┐
│ 4. Vérifier statut paiements        │
│    mois en cours                    │
└───────────────┬─────────────────────┘
                │
                ▼
┌─────────────────────────────────────┐
│ 5. Générer message adapté           │
│    - Tous payés → Confirmation      │
│    - Impayés → Rappel avec détails  │
└───────────────┬─────────────────────┘
                │
                ▼
┌─────────────────────────────────────┐
│ 6. Envoyer email aux destinataires  │
└───────────────┬─────────────────────┘
                │
                ▼
┌─────────────────────────────────────┐
│ 7. Marquer rappel comme envoyé      │
│    (date + compteur)                │
└─────────────────────────────────────┘
```

### Génération Automatique des Entries

Le système crée automatiquement des entrées de tracking pour :
- Tous les logements avec statut `en_location`
- Ayant un contrat avec statut `actif`
- Pour le mois en cours
- Au moment du premier rappel du mois

Les entrées incluent :
- Montant attendu (loyer + charges du bien)
- Statut par défaut : `attente`
- Lien vers le contrat actif

### Templates d'Email Dynamiques

**Variable `{{status_paiements}}`**

Le cron génère automatiquement :
- **Résumé** : Total, payés, impayés, en attente
- **Tableau HTML** : Liste de tous les biens avec leur statut
  - Code couleur identique à l'interface
  - Référence, locataire, adresse, montant, statut

**Variable `{{bouton_interface}}`**

Si activé dans la configuration :
```html
<a href="https://votre-domaine.com/admin-v2/gestion-loyers.php" 
   class="btn">📊 Accéder à l'interface de gestion</a>
```

## 📊 Base de Données

### Table `loyers_tracking`

```sql
CREATE TABLE loyers_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    logement_id INT NOT NULL,              -- Lien vers le bien
    contrat_id INT NULL,                   -- Lien vers le contrat actif
    
    mois INT NOT NULL,                     -- 1-12
    annee INT NOT NULL,                    -- Ex: 2026
    
    statut_paiement ENUM('paye', 'impaye', 'attente'),
    
    date_paiement DATE NULL,               -- Date effective du paiement
    montant_attendu DECIMAL(10,2) NOT NULL,
    montant_recu DECIMAL(10,2) NULL,
    
    rappel_envoye BOOLEAN DEFAULT FALSE,   -- Rappel envoyé ?
    date_rappel TIMESTAMP NULL,            -- Date du dernier rappel
    nb_rappels INT DEFAULT 0,              -- Nombre de rappels envoyés
    
    notes TEXT NULL,
    
    UNIQUE(logement_id, mois, annee)
);
```

### Paramètres Système

Stockés dans la table `parametres` :

| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| `rappel_loyers_dates_envoi` | JSON | `[7, 9, 15]` | Jours du mois pour envoi |
| `rappel_loyers_destinataires` | JSON | `[]` | Liste emails admins |
| `rappel_loyers_actif` | Boolean | `true` | Module activé/désactivé |
| `rappel_loyers_inclure_bouton` | Boolean | `true` | Bouton dans emails |

## 🔍 Logs et Traçabilité

### Logs du Cron

Fichier : `cron/rappel-loyers-log.txt`

Format :
```
[2026-02-16 09:00:01] [INFO] ===== DÉMARRAGE DU SCRIPT DE RAPPEL LOYERS =====
[2026-02-16 09:00:01] [INFO] Jour de rappel détecté: 15
[2026-02-16 09:00:01] [INFO] Destinataires: admin@example.com
[2026-02-16 09:00:01] [INFO] Vérification des paiements pour: 2/2026
[2026-02-16 09:00:02] [INFO] Statut: Impayés détectés
[2026-02-16 09:00:02] [INFO]   - Total: 5 biens
[2026-02-16 09:00:02] [INFO]   - Payés: 3
[2026-02-16 09:00:02] [INFO]   - Impayés: 2
[2026-02-16 09:00:03] [INFO] Email envoyé avec succès à: admin@example.com
[2026-02-16 09:00:03] [INFO] Rappels envoyés: 1 réussi(s), 0 échec(s)
[2026-02-16 09:00:03] [INFO] ✅ Rappel envoyé avec succès
[2026-02-16 09:00:03] [INFO] ===== FIN DU SCRIPT DE RAPPEL LOYERS =====
```

### Base de Données

La table `loyers_tracking` enregistre :
- `rappel_envoye` : Boolean indiquant si un rappel a été envoyé
- `date_rappel` : Timestamp du dernier rappel
- `nb_rappels` : Compteur incrémenté à chaque rappel

La table `cron_jobs` enregistre :
- Dernière exécution
- Statut (success/error)
- Logs de la dernière exécution (5000 caractères max)

## 🔧 Maintenance

### Vérifier le Bon Fonctionnement

**1. Tester manuellement**
```bash
php cron/rappel-loyers.php
```

**2. Vérifier les logs**
```bash
tail -f cron/rappel-loyers-log.txt
```

**3. Interface admin**
- Aller dans **Tâches Automatisées**
- Vérifier le statut du job "Rappel Loyers"
- Voir la dernière exécution et son résultat

### Résolution de Problèmes

**Les emails ne sont pas envoyés**
- ✅ Vérifier que `SMTP_PASSWORD` est configuré dans `includes/config.local.php`
- ✅ Vérifier que le module est activé dans la configuration
- ✅ Vérifier qu'au moins un destinataire est configuré
- ✅ Vérifier les logs pour les erreurs

**Le cron ne s'exécute pas**
- ✅ Vérifier que la tâche cron est bien configurée
- ✅ Vérifier les permissions d'exécution du fichier PHP
- ✅ Vérifier les logs système : `/var/log/cron.log`
- ✅ Tester manuellement avec `php cron/rappel-loyers.php`

**Les statuts ne changent pas**
- ✅ Vérifier la connexion à la base de données
- ✅ Vérifier la console JavaScript du navigateur pour les erreurs
- ✅ Vérifier les permissions sur la table `loyers_tracking`

## 🎨 Personnalisation

### Modifier les Templates d'Email

1. Aller dans **Templates d'Email** (menu admin)
2. Trouver `rappel_loyers_impaye` ou `confirmation_loyers_payes`
3. Modifier le HTML selon vos besoins
4. Variables disponibles :
   - `{{status_paiements}}` : Message généré automatiquement (NE PAS MODIFIER)
   - `{{bouton_interface}}` : Bouton vers l'interface (NE PAS MODIFIER)
   - `{{signature}}` : Signature email configurée dans les paramètres

### Modifier les Dates par Défaut

Dans la configuration ou directement en base :
```sql
UPDATE parametres 
SET valeur = '[5, 10, 20, 25]' 
WHERE cle = 'rappel_loyers_dates_envoi';
```

### Ajouter des Destinataires par Défaut

```sql
UPDATE parametres 
SET valeur = '["admin1@example.com", "admin2@example.com"]' 
WHERE cle = 'rappel_loyers_destinataires';
```

## 📝 Notes Importantes

### Sécurité
- ✅ Tous les emails sont échappés avec `htmlspecialchars()`
- ✅ Requêtes SQL préparées (PDO) pour éviter les injections
- ✅ Validation des emails avant envoi
- ✅ Authentification requise pour accéder aux interfaces admin

### Performance
- La création automatique des entries ne se fait que pour les biens en location
- Les statuts de paiement sont cachés pour éviter les requêtes répétées
- Index sur les colonnes `logement_id`, `mois`, `annee` pour des recherches rapides

### Limitations
- Le système gère uniquement les loyers mensuels
- Un bien = un loyer par mois (pas de gestion de loyers multiples)
- Les montants sont basés sur les valeurs `loyer` + `charges` du bien
- Pas de gestion automatique des quittances (module séparé existant)

## 🆘 Support

Pour toute question ou problème :
1. Consultez d'abord cette documentation
2. Vérifiez les logs : `cron/rappel-loyers-log.txt`
3. Contactez l'équipe technique

## 📜 Historique des Versions

### Version 1.0 (2026-02-16)
- ✅ Création initiale du module
- ✅ Table `loyers_tracking` pour suivi mensuel
- ✅ Interface de gestion avec tableau coloré
- ✅ Configuration des rappels automatiques
- ✅ Cron job pour envoi automatique
- ✅ Templates d'email dynamiques
- ✅ Rappels manuels aux locataires
- ✅ Intégration au menu admin

---

**Développé pour MY Invest Immobilier**  
*Module de Gestion et Rappel des Loyers*
