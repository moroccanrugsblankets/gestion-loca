# Migration 020 - Ajout des colonnes de validation de contrat

## Problème résolu
Cette migration corrige l'erreur suivante lors de la validation d'un contrat :
```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'validated_by' in 'field list'
```

## Solution temporaire déjà en place
Le code a été modifié pour vérifier l'existence de la colonne `validated_by` avant de l'utiliser. Cela permet au système de fonctionner même si la migration n'a pas encore été exécutée.

## Migration requise
Pour bénéficier de toutes les fonctionnalités (traçabilité des validations par administrateur), vous devez exécuter la migration 020.

### Méthode 1 : Utiliser le script de migration automatique
```bash
cd /home/barconcecc/contrat.myinvest-immobilier.com
php run-migrations.php
```

### Méthode 2 : Exécuter manuellement via MySQL
Si le script automatique ne fonctionne pas, exécutez directement le fichier SQL :

```bash
cd /home/barconcecc/contrat.myinvest-immobilier.com
mysql -u [votre_utilisateur] -p [nom_base_de_donnees] < migrations/020_add_contract_signature_and_workflow.sql
```

### Méthode 3 : Vérifier et créer manuellement les colonnes
Si vous préférez vérifier d'abord l'état actuel, connectez-vous à MySQL et exécutez :

```sql
-- Vérifier si les colonnes existent
SELECT COLUMN_NAME 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'bail_signature' 
AND TABLE_NAME = 'contrats' 
AND COLUMN_NAME IN ('validated_by', 'verified_by', 'validation_notes', 'motif_annulation', 'date_validation', 'date_verification');
```

Si certaines colonnes manquent, vous pouvez les ajouter manuellement :

```sql
-- Ajouter les colonnes manquantes si nécessaire
ALTER TABLE contrats ADD COLUMN IF NOT EXISTS date_verification TIMESTAMP NULL COMMENT 'Date de vérification par admin';
ALTER TABLE contrats ADD COLUMN IF NOT EXISTS date_validation TIMESTAMP NULL COMMENT 'Date de validation finale';
ALTER TABLE contrats ADD COLUMN IF NOT EXISTS validation_notes TEXT NULL COMMENT 'Notes de vérification/validation';
ALTER TABLE contrats ADD COLUMN IF NOT EXISTS motif_annulation TEXT NULL COMMENT 'Raison de l''annulation du contrat';
ALTER TABLE contrats ADD COLUMN IF NOT EXISTS verified_by INT NULL COMMENT 'Admin qui a vérifié';
ALTER TABLE contrats ADD COLUMN IF NOT EXISTS validated_by INT NULL COMMENT 'Admin qui a validé';

-- Ajouter les contraintes de clé étrangère
ALTER TABLE contrats ADD CONSTRAINT fk_contrats_verified_by FOREIGN KEY (verified_by) REFERENCES administrateurs(id) ON DELETE SET NULL;
ALTER TABLE contrats ADD CONSTRAINT fk_contrats_validated_by FOREIGN KEY (validated_by) REFERENCES administrateurs(id) ON DELETE SET NULL;
```

## Vérification
Après avoir exécuté la migration, vérifiez que tout fonctionne :

1. Connectez-vous à l'interface admin
2. Allez sur un contrat avec le statut "Signé"
3. Essayez de valider le contrat
4. L'opération devrait réussir sans erreur

## Note importante
Même si vous n'exécutez pas la migration immédiatement, le système continuera à fonctionner grâce aux vérifications de sécurité ajoutées dans le code. Cependant, certaines informations de traçabilité (quel admin a validé quel contrat) ne seront pas enregistrées tant que la migration n'aura pas été exécutée.
