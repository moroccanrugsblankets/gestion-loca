# Migration 020 - Avant et Après la Correction

## ❌ AVANT la Correction

Lorsque vous exécutiez `php run-migrations.php` :

```bash
#!/usr/bin/env php
=== Migration Runner ===

✓ Migration tracking table ready
Found 24 migration file(s).

⊘ Skipping (already executed): 001_add_logements_new_fields.sql
⊘ Skipping (already executed): 002_create_parametres_table.sql
⊘ Skipping (already executed): 003_create_email_templates_table.sql
... (autres migrations déjà exécutées)
⊘ Skipping (already executed): 019_add_date_expiration_to_email_template.sql

Applying migration: 020_add_contract_signature_and_workflow.sql

✗ Error: SQLSTATE[42000]: Syntax error or access violation: 1064 
You have an error in your SQL syntax; check the manual that corresponds to 
your MySQL server version for the right syntax to use near 'annulation du contrat'' at line 1

Migration failed - changes rolled back
Please fix the error and run migrations again.
```

**Problème :** La migration s'arrêtait avec une erreur de syntaxe SQL et ne créait aucune colonne.

---

## ✅ APRÈS la Correction

Maintenant, lorsque vous exécutez `php run-migrations.php` :

```bash
#!/usr/bin/env php
=== Migration Runner ===

✓ Migration tracking table ready
Found 24 migration file(s).

⊘ Skipping (already executed): 001_add_logements_new_fields.sql
⊘ Skipping (already executed): 002_create_parametres_table.sql
⊘ Skipping (already executed): 003_create_email_templates_table.sql
... (autres migrations déjà exécutées)
⊘ Skipping (already executed): 019_add_date_expiration_to_email_template.sql

Applying migration: 020_add_contract_signature_and_workflow.sql

✓ Successfully applied: 020_add_contract_signature_and_workflow.sql

=== All migrations completed successfully ===
```

**Résultat :** La migration s'exécute avec succès et crée toutes les colonnes nécessaires.

---

## 🔍 Détails Techniques de la Correction

### Ligne 52 - Le Problème

```sql
-- INCORRECT (causait l'erreur)
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE contrats ADD COLUMN motif_annulation TEXT NULL COMMENT ''Raison de l''annulation du contrat''',
    'SELECT "Column motif_annulation already exists" as message'
);
```

**Analyse du problème :**
- String externe : `'ALTER TABLE ... COMMENT ''...'''`
- Dans le COMMENT : `''Raison de l''annulation ...''`
- L'apostrophe dans "l'annulation" crée : `l''annulation`
- Avec les quotes du COMMENT : `''Raison de l''annulation''`
- **3 quotes consécutives** (`l''a`) → MySQL pense que la string se termine prématurément

### Ligne 52 - La Solution

```sql
-- CORRECT (fonctionne maintenant)
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE contrats ADD COLUMN motif_annulation TEXT NULL COMMENT ''Raison de l''''annulation du contrat''',
    'SELECT "Column motif_annulation already exists" as message'
);
```

**Analyse de la solution :**
- String externe : `'ALTER TABLE ... COMMENT ''...'''`
- Dans le COMMENT : `''Raison de l''''annulation ...''`
- L'apostrophe dans "l'annulation" : `l''''annulation`
- **4 quotes consécutives** (`l''''a`) → Interprété correctement comme une apostrophe

**Résultat final exécuté par MySQL :**
```sql
ALTER TABLE contrats ADD COLUMN motif_annulation TEXT NULL COMMENT 'Raison de l'annulation du contrat'
```

---

## 📊 Ce que la Migration Crée

Lorsque la migration 020 s'exécute avec succès, elle :

### 1. Ajoute des Paramètres
```sql
signature_societe_image = ''
signature_societe_enabled = 'false'
```

### 2. Modifie l'ENUM du Statut
```sql
'en_attente', 'signe', 'en_verification', 'valide', 'expire', 'annule', 'actif', 'termine'
```

### 3. Ajoute 6 Nouvelles Colonnes à `contrats`

| Colonne | Type | Description |
|---------|------|-------------|
| `date_verification` | TIMESTAMP NULL | Date de vérification par admin |
| `date_validation` | TIMESTAMP NULL | Date de validation finale |
| `validation_notes` | TEXT NULL | Notes de vérification/validation |
| `motif_annulation` | TEXT NULL | Raison de l'annulation du contrat ⭐ |
| `verified_by` | INT NULL | ID admin qui a vérifié |
| `validated_by` | INT NULL | ID admin qui a validé |

### 4. Ajoute 2 Contraintes de Clés Étrangères
```sql
FOREIGN KEY (verified_by) REFERENCES administrateurs(id) ON DELETE SET NULL
FOREIGN KEY (validated_by) REFERENCES administrateurs(id) ON DELETE SET NULL
```

---

## ✅ Vérification Post-Migration

Pour vérifier que la migration a fonctionné :

```sql
-- Connectez-vous à MySQL
mysql -u votre_utilisateur -p bail_signature

-- Vérifiez que les colonnes existent
DESCRIBE contrats;

-- Vous devriez voir les nouvelles colonnes :
+-------------------+-----------+------+-----+---------+-------+
| Field             | Type      | Null | Key | Default | Extra |
+-------------------+-----------+------+-----+---------+-------+
| ...               | ...       | ...  | ... | ...     | ...   |
| date_verification | timestamp | YES  |     | NULL    |       |
| date_validation   | timestamp | YES  |     | NULL    |       |
| validation_notes  | text      | YES  |     | NULL    |       |
| motif_annulation  | text      | YES  |     | NULL    |       |
| verified_by       | int(11)   | YES  | MUL | NULL    |       |
| validated_by      | int(11)   | YES  | MUL | NULL    |       |
+-------------------+-----------+------+-----+---------+-------+
```

---

## 🎓 Leçon Apprise

**Règle d'or pour le SQL dynamique :**

| Contexte | Échappement | Exemple |
|----------|-------------|---------|
| SQL simple | `''` (2 quotes) | `'c''est bon'` → `c'est bon` |
| SQL dynamique | `''''` (4 quotes) | `'c''''est bon'` → `c'est bon` |
| SQL triple niveau | `''''''''` (8 quotes) | Rare, évitez si possible |

**Conseil :** Utilisez toujours des outils de validation SQL avant de commiter des migrations !

---

## 📝 Résumé

- ❌ **Avant :** Migration échoue avec erreur de syntaxe
- ✅ **Après :** Migration réussit et crée toutes les colonnes
- 🔧 **Correction :** `l''` → `l''''` (3 quotes → 4 quotes)
- ✅ **Testé :** Validation automatique avec `test-migration-020.php`
- 📚 **Documenté :** Guide complet dans `FIX_MIGRATION_020_SYNTAX.md`
