# Fix Migration 020 - SQL Syntax Error

## Problème

Lors de l'exécution de `run-migrations.php`, la migration 020 échouait avec l'erreur suivante :

```
Applying migration: 020_add_contract_signature_and_workflow.sql
✗ Error: SQLSTATE[42000]: Syntax error or access violation: 1064 
You have an error in your SQL syntax; check the manual that corresponds to 
your MySQL server version for the right syntax to use near 'annulation du contrat'' at line 1
Migration failed - changes rolled back
```

## Cause

Le problème se situait à la ligne 52 du fichier `migrations/020_add_contract_signature_and_workflow.sql` :

```sql
-- AVANT (incorrect)
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE contrats ADD COLUMN motif_annulation TEXT NULL COMMENT ''Raison de l''annulation du contrat''', 
    'SELECT "Column motif_annulation already exists" as message'
);
```

Le problème : `l''annulation` contient **trois quotes consécutives** qui cassent la syntaxe SQL car le texte est utilisé dans du SQL dynamique.

### Explication technique

Dans MySQL, quand on utilise du SQL dynamique (une chaîne SQL à l'intérieur d'une autre chaîne SQL), il faut échapper les quotes de manière particulière :

1. **String SQL normal** : `'l'annulation'` → Erreur de syntaxe
2. **String SQL avec échappement simple** : `'l''annulation'` → Correct (devient `l'annulation`)
3. **String SQL dynamique** : `'l''''annulation'` → Correct (devient `l'annulation` dans le SQL exécuté)

Dans le cas du SQL dynamique :
- La première paire de quotes `''` représente un apostrophe dans la chaîne externe
- La deuxième paire de quotes `''` représente un apostrophe dans la chaîne interne (le SQL qui sera exécuté)
- Résultat : `''''` → `'`

## Solution

Changement appliqué à la ligne 52 :

```sql
-- APRÈS (correct)
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE contrats ADD COLUMN motif_annulation TEXT NULL COMMENT ''Raison de l''''annulation du contrat''', 
    'SELECT "Column motif_annulation already exists" as message'
);
```

Maintenant `l''''annulation` est correctement échappé avec **quatre quotes consécutives**.

## Vérification

Un script de test a été créé pour valider la correction :

```bash
php test-migration-020.php
```

Ce script vérifie :
- ✅ Que le pattern problématique (triple quotes) n'existe plus
- ✅ Que le pattern correct (quatre quotes) est présent
- ✅ Que toutes les structures SQL attendues sont présentes
- ✅ Que le fichier est syntaxiquement valide

## Exécution de la migration

Maintenant que l'erreur de syntaxe est corrigée, vous pouvez exécuter la migration :

```bash
php run-migrations.php
```

La migration devrait maintenant s'exécuter avec succès et afficher :

```
Applying migration: 020_add_contract_signature_and_workflow.sql
✓ Successfully applied: 020_add_contract_signature_and_workflow.sql
```

## Colonnes ajoutées

Cette migration ajoute les colonnes suivantes à la table `contrats` :

1. `date_verification` - Date de vérification par admin
2. `date_validation` - Date de validation finale
3. `validation_notes` - Notes de vérification/validation
4. `motif_annulation` - Raison de l'annulation du contrat ⬅ **Cette colonne causait l'erreur**
5. `verified_by` - ID de l'admin qui a vérifié
6. `validated_by` - ID de l'admin qui a validé

Et configure également :
- Les paramètres de signature électronique de la société
- Les nouvelles valeurs ENUM pour le statut des contrats
- Les contraintes de clés étrangères vers la table `administrateurs`

## Note pour les développeurs

Quand vous créez des migrations SQL avec du SQL dynamique (PREPARE/EXECUTE), pensez toujours à :

1. **Doubler l'échappement des quotes** : `''''` au lieu de `''`
2. **Tester la migration** avant de la commiter
3. **Utiliser des scripts de validation** comme `test-migration-020.php`

### Exemples d'échappement correct

```sql
-- String simple (non dynamique)
ALTER TABLE t ADD COLUMN c TEXT COMMENT 'C''est bon';

-- String dynamique (dans IF, CONCAT, etc.)
SET @sql = 'ALTER TABLE t ADD COLUMN c TEXT COMMENT ''C''''est bon''';

-- String dans une string dans une string (triple niveau)
SET @sql = CONCAT('SET @s = ''', 'C''''''''est bon', '''');
```
