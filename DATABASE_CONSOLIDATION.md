# Consolidation de la Base de Données

## 📋 Résumé

Cette mise à jour consolide les deux bases de données précédemment séparées en une **base de données unique** `bail_signature` pour tout le système.

## ⚠️ Changements Importants

### Avant
- **Base de données 1**: `bail_signature` (système de signature uniquement)
  - Tables: logements, contrats, locataires, logs
  - Fichier: `database.sql`
  - Configuration: `includes/config.php`

- **Base de données 2**: `myinvest_location` (système de candidatures)
  - Tables: logements, candidatures, contrats, locataires, paiements, états des lieux, etc.
  - Fichier: `database-candidature.sql`
  - Configuration: `includes/config-v2.php`

### Après (Maintenant)
- **Base de données unique**: `bail_signature`
  - Toutes les 11 tables consolidées avec clés étrangères
  - Fichier: `database.sql` (version complète et consolidée)
  - Configuration: `includes/config.php` (configuration unifiée)

## 🗃️ Structure de la Base Unique

La base de données unique `bail_signature` contient:

### Tables Principales
1. **logements** - Gestion des biens immobiliers
2. **candidatures** - Workflow de sélection des locataires
3. **candidature_documents** - Documents attachés aux candidatures
4. **contrats** - Contrats de bail
5. **locataires** - Informations et signatures des locataires
6. **etats_lieux** - États des lieux d'entrée et sortie
7. **degradations** - Suivi des dégradations et vétusté
8. **paiements** - Gestion financière (loyers, dépôts, remboursements)
9. **logs** - Traçabilité de toutes les actions
10. **administrateurs** - Comptes administrateurs

### Vues SQL
- **candidatures_a_traiter** - Candidatures en attente de traitement automatique
- **dashboard_stats** - Statistiques pour le tableau de bord

## 🔗 Relations Entre Tables

```
logements
    ↓ (1:N)
candidatures → candidature_documents
    ↓ (1:1)
contrats
    ↓ (1:N)
    ├── locataires
    ├── etats_lieux → degradations
    └── paiements

logs (trace toutes les entités)
administrateurs (gestion des accès)
```

## 📝 Fichiers Modifiés

### Fichiers Supprimés
- ❌ `database-candidature.sql` (fusionné dans `database.sql`)
- ❌ `includes/config-v2.php` (fusionné dans `includes/config.php`)

### Fichiers Mis à Jour
- ✅ `database.sql` - Base de données unique consolidée
- ✅ `includes/config.php` - Configuration unifiée avec toutes les constantes
- ✅ Tous les fichiers PHP mis à jour pour utiliser `config.php`
- ✅ `README.md` - Documentation mise à jour
- ✅ `CONFIGURATION.md` - Guide de configuration mis à jour

### Fichiers PHP Mis à Jour (14 fichiers)
- `admin-v2/*.php` (9 fichiers)
- `candidature/*.php` (4 fichiers)
- `cron/process-candidatures.php`

## 🚀 Migration

### Pour les Nouvelles Installations
```bash
# Importer la base de données unique
mysql -u root -p < database.sql

# Configurer includes/config.php avec vos paramètres
define('DB_NAME', 'bail_signature');
```

### Pour les Installations Existantes

**Si vous aviez `myinvest_location`:**
```sql
-- Option 1: Renommer la base existante
RENAME DATABASE myinvest_location TO bail_signature;

-- Option 2: Créer une nouvelle base et migrer
mysql -u root -p < database.sql
-- Puis migrer vos données manuellement si nécessaire
```

**Si vous aviez `bail_signature` (ancienne version):**
```sql
-- Sauvegarder vos données
mysqldump -u root -p bail_signature > backup_old.sql

-- Supprimer l'ancienne base
DROP DATABASE bail_signature;

-- Créer la nouvelle base unifiée
mysql -u root -p < database.sql

-- Migrer vos données depuis backup_old.sql si nécessaire
```

## ✅ Avantages de la Consolidation

1. **Cohérence des données** - Une seule source de vérité
2. **Intégrité référentielle** - Clés étrangères entre toutes les tables
3. **Simplicité de maintenance** - Un seul schéma à gérer
4. **Performance** - Pas de jointures entre bases de données
5. **Sauvegarde simplifiée** - Une seule base à sauvegarder
6. **Configuration unique** - Un seul fichier de configuration

## 🔧 Configuration Consolidée

Le fichier `includes/config.php` contient maintenant toutes les configurations:

- **Base de données** - Connexion unique
- **Email** - Configuration SMTP
- **URLs** - Chemins de l'application
- **Workflow** - Critères d'acceptation automatique
- **Sécurité** - Tokens CSRF, salt
- **Pagination** - Limites par page
- **Fonctions utilitaires** - Gestion des jours ouvrés

## 📊 Workflow Complet Unifié

```
1. Candidature (table: candidatures)
   ↓
2. Documents uploadés (table: candidature_documents)
   ↓
3. Traitement automatique après 4 jours ouvrés
   ↓
4. Génération contrat (table: contrats)
   ↓
5. Signature électronique (table: locataires)
   ↓
6. État des lieux entrée (table: etats_lieux)
   ↓
7. Gestion paiements (table: paiements)
   ↓
8. État des lieux sortie (table: etats_lieux)
   ↓
9. Calcul dégradations (table: degradations)
   ↓
10. Remboursement dépôt (table: paiements)
```

Toutes les étapes utilisent la même base de données unique!

## 🔍 Vérification

Pour vérifier que la consolidation est correcte:

```bash
# Vérifier qu'il n'y a qu'un seul fichier de config
ls includes/config*.php
# Doit afficher uniquement: includes/config.php

# Vérifier qu'il n'y a qu'un seul fichier SQL
ls *.sql
# Doit afficher uniquement: database.sql

# Vérifier la base de données
mysql -u root -p
> SHOW DATABASES LIKE 'bail%';
# Doit afficher uniquement: bail_signature

> USE bail_signature;
> SHOW TABLES;
# Doit afficher 10 tables + 2 vues
```

## 📞 Support

Pour toute question concernant cette consolidation:
- Email: contact@myinvest-immobilier.com
- Voir: `CONFIGURATION.md` pour les détails de configuration

---

**Date de consolidation**: 27 janvier 2026
**Version**: 2.0 - Base de données unique
