# Fix: Missing date_validation Column Error

## Problème

Lors de la validation d'un contrat sur la page `/admin-v2/contrat-detail.php`, l'erreur suivante apparaissait :

```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'date_validation' in 'field list'
```

## Cause Racine

Le fichier de migration `migrations/020_add_contract_signature_and_workflow.sql` contenait une syntaxe SQL non supportée par MySQL :

```sql
ALTER TABLE contrats
ADD COLUMN IF NOT EXISTS date_validation TIMESTAMP NULL ...
```

**Problème** : MySQL ne supporte pas `IF NOT EXISTS` pour l'ajout de colonnes avec `ALTER TABLE ADD COLUMN`. Cette syntaxe n'est supportée que pour la création de tables (`CREATE TABLE IF NOT EXISTS`).

En conséquence, lorsque les utilisateurs exécutaient `run-migrations.php`, la migration échouait silencieusement ou générait une erreur, empêchant l'ajout des colonnes nécessaires.

## Solution Appliquée

### 1. Migration Corrigée (020_add_contract_signature_and_workflow.sql)

Le fichier de migration a été corrigé pour utiliser du SQL dynamique compatible avec MySQL :

```sql
-- Vérifier si la colonne existe avant de l'ajouter
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'contrats' 
                   AND COLUMN_NAME = 'date_validation');

-- Ajouter la colonne seulement si elle n'existe pas
SET @sql = IF(@col_exists = 0, 
              'ALTER TABLE contrats ADD COLUMN date_validation TIMESTAMP NULL COMMENT ''Date de validation finale''', 
              'SELECT "Column date_validation already exists" as message');
              
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

Cette approche :
- ✅ Vérifie l'existence de la colonne dans `information_schema.COLUMNS`
- ✅ N'ajoute la colonne que si elle n'existe pas
- ✅ Fonctionne sur toutes les versions de MySQL/MariaDB
- ✅ Est idempotente (peut être exécutée plusieurs fois sans erreur)

### 2. Colonnes Ajoutées

La migration ajoute les colonnes suivantes à la table `contrats` :

- `date_verification` : Date de vérification par un administrateur
- `date_validation` : Date de validation finale du contrat
- `validation_notes` : Notes de vérification/validation
- `motif_annulation` : Raison de l'annulation du contrat
- `verified_by` : ID de l'administrateur qui a vérifié (FK vers `administrateurs`)
- `validated_by` : ID de l'administrateur qui a validé (FK vers `administrateurs`)

### 3. Statuts de Contrat Améliorés

La migration met également à jour les statuts possibles d'un contrat :
- `en_attente` : Contrat en attente de signature
- `signe` : Contrat signé par les locataires
- `en_verification` : ✨ NOUVEAU - Contrat en cours de vérification
- `valide` : ✨ NOUVEAU - Contrat validé par l'administration
- `expire` : Contrat expiré
- `annule` : Contrat annulé
- `actif` : Contrat actif
- `termine` : Contrat terminé

### 4. Schema de Base Mis à Jour

Le fichier `database.sql` a été mis à jour pour inclure ces nouvelles colonnes, assurant que les nouvelles installations auront la structure correcte dès le départ.

## Instructions pour Appliquer le Fix

### Pour les Installations Existantes

1. **Récupérez les dernières modifications** :
   ```bash
   git pull origin main
   ```

2. **Exécutez les migrations** :
   ```bash
   php run-migrations.php
   ```

   La migration 020 sera appliquée automatiquement et ajoutera les colonnes manquantes.

3. **Vérifiez que tout fonctionne** :
   - Accédez à `/admin-v2/contrat-detail.php?id=<un_contrat_id>`
   - Essayez de valider un contrat
   - L'erreur ne devrait plus apparaître

### Pour les Nouvelles Installations

Utilisez simplement le fichier `database.sql` mis à jour qui contient déjà toutes les colonnes nécessaires.

## Vérification

Pour vérifier que les colonnes ont été ajoutées correctement, exécutez cette requête SQL :

```sql
SHOW COLUMNS FROM contrats LIKE '%validation%';
```

Vous devriez voir :
```
+------------------+-----------+------+-----+---------+-------+
| Field            | Type      | Null | Key | Default | Extra |
+------------------+-----------+------+-----+---------+-------+
| date_validation  | timestamp | YES  |     | NULL    |       |
| validation_notes | text      | YES  |     | NULL    |       |
+------------------+-----------+------+-----+---------+-------+
```

## Fichiers Modifiés

1. `migrations/020_add_contract_signature_and_workflow.sql` - Migration corrigée
2. `database.sql` - Schema de base mis à jour avec les nouvelles colonnes

## Prévention Future

Pour éviter ce type de problème à l'avenir :

1. ✅ Toujours tester les migrations sur une base de données de développement avant le déploiement
2. ✅ Utiliser des requêtes dynamiques pour les opérations `IF NOT EXISTS` avec MySQL
3. ✅ Maintenir `database.sql` à jour avec toutes les migrations appliquées
4. ✅ Vérifier la compatibilité de la syntaxe SQL avec la version de MySQL utilisée

## Support

Si vous rencontrez toujours des problèmes après avoir appliqué ce fix :

1. Vérifiez la version de MySQL/MariaDB : `SELECT VERSION();`
2. Vérifiez les logs d'erreur de la migration
3. Vérifiez que toutes les tables référencées existent (notamment `administrateurs`)
4. Contactez le support technique avec les détails de l'erreur
