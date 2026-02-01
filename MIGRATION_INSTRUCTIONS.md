# Instructions de Migration - Résolution des Erreurs contrat-detail.php

## Problèmes Résolus

Ce correctif résout les problèmes suivants sur la page `/admin-v2/contrat-detail.php`:

1. **Erreur "Undefined index"** pour `date_validation` (ligne 363)
2. **Erreur "Undefined index"** pour `validation_notes` (ligne 380)
3. **Erreur "Undefined index"** pour `motif_annulation` (ligne 386)
4. **Erreur fatale SQL** "Column not found: date_validation" lors de la validation du contrat
5. **Suppression des bordures** autour des signatures dans le PDF et sur la page de détails

## Étapes pour Appliquer le Correctif

### 1. Sauvegarder la Base de Données

Avant d'appliquer toute migration, créez une sauvegarde complète de votre base de données:

```bash
mysqldump -u [username] -p [database_name] > backup_$(date +%Y%m%d_%H%M%S).sql
```

**Note:** Remplacez `[database_name]` par le nom réel de votre base de données (par défaut: `bail_signature`)

### 2. Exécuter les Migrations

Les modifications du code PHP ont déjà été appliquées. Il vous reste à exécuter la migration de base de données:

#### Option A: Via le script de migration PHP (Recommandé)

```bash
php run-migrations.php
```

Ce script va:
- Vérifier quelles migrations ont déjà été exécutées
- Exécuter uniquement les nouvelles migrations
- Enregistrer chaque migration dans la table `migrations` pour éviter les doublons

#### Option B: Manuellement via MySQL

Si vous préférez exécuter la migration manuellement:

```bash
mysql -u [username] -p [database_name] < migrations/020_add_contract_signature_and_workflow.sql
```

**Note:** Remplacez `[database_name]` par le nom réel de votre base de données (par défaut: `bail_signature`)

### 3. Vérifier l'Application de la Migration

Connectez-vous à votre base de données et vérifiez que les nouvelles colonnes existent:

```sql
DESCRIBE contrats;
```

Vous devriez voir les colonnes suivantes dans la table `contrats`:
- `date_verification` (TIMESTAMP NULL)
- `date_validation` (TIMESTAMP NULL)
- `validation_notes` (TEXT NULL)
- `motif_annulation` (TEXT NULL)
- `verified_by` (INT NULL)
- `validated_by` (INT NULL)

### 4. Tester le Système

Après avoir appliqué la migration:

1. Accédez à `/admin-v2/contrat-detail.php?id=[id_contrat]`
2. Vérifiez qu'il n'y a plus d'erreurs "Undefined index"
3. Testez la validation d'un contrat signé
4. Vérifiez que le PDF généré n'affiche plus de bordures autour des signatures

## Détails Techniques

### Modifications du Code PHP

1. **admin-v2/contrat-detail.php**:
   - Ajout de vérifications `isset()` pour les index `date_validation`, `validation_notes`, et `motif_annulation`
   - Suppression de la bordure CSS sur `.signature-preview`

2. **pdf/generate-bail.php**:
   - Suppression de la bordure CSS sur `.signature-image`

3. **migrations/020_add_contract_signature_and_workflow.sql**:
   - Amélioration de la gestion des clés étrangères pour éviter les erreurs si elles existent déjà

### Nouvelles Colonnes

Les colonnes suivantes ont été ajoutées à la table `contrats`:

- `date_verification`: Date de vérification par un administrateur
- `date_validation`: Date de validation finale du contrat
- `validation_notes`: Notes internes de validation
- `motif_annulation`: Raison de l'annulation du contrat
- `verified_by`: ID de l'administrateur qui a vérifié
- `validated_by`: ID de l'administrateur qui a validé

Ces colonnes permettent un suivi complet du workflow de validation des contrats.

## Support

Si vous rencontrez des problèmes lors de l'application de cette migration, vérifiez:

1. Que vous avez bien sauvegardé votre base de données
2. Que l'utilisateur MySQL a les droits nécessaires (ALTER, CREATE, etc.)
3. Les logs d'erreur PHP et MySQL pour plus de détails

En cas de problème persistant, vous pouvez restaurer votre sauvegarde et contacter le support technique.
