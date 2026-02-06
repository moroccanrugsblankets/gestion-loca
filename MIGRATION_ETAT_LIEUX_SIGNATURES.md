# Migration des Signatures État des Lieux : Base64 → Fichiers JPG

## Contexte

### Problème Identifié
- **TCPDF affiche des bordures** autour des signatures dans les PDFs d'état des lieux
- Les styles CSS pour supprimer les bordures ne sont **pas respectés** pour les images inline en base64
- Les signatures étaient stockées en base64 dans la base de données (`data:image/jpeg;base64,...`)

### Solution
- Convertir toutes les signatures de **base64** vers des **fichiers physiques JPG**
- Stocker uniquement le **chemin relatif** dans la base de données (`uploads/signatures/xxx.jpg`)
- TCPDF charge les images via URL publique et **respecte le style CSS** sans bordures

## Prérequis

1. **Accès au serveur** avec droits d'exécution PHP
2. **Permissions d'écriture** sur le répertoire `uploads/signatures/`
3. **Accès à la base de données** (configuré dans `includes/config.php`)
4. **Sauvegarde de la base de données** (recommandé avant migration)

## Exécution de la Migration

### Étape 1 : Sauvegarde de la base de données (RECOMMANDÉ)

```bash
# Créer une sauvegarde complète
mysqldump -u [username] -p [database_name] > backup_before_signature_migration_$(date +%Y%m%d_%H%M%S).sql
```

### Étape 2 : Vérifier les permissions du répertoire

```bash
# Créer le répertoire s'il n'existe pas
mkdir -p uploads/signatures

# Définir les bonnes permissions
chmod 755 uploads/signatures
```

### Étape 3 : Exécuter le script de migration

```bash
# Via ligne de commande
php migrate-etat-lieux-signatures-to-files.php

# OU via navigateur web
# https://votre-domaine.com/migrate-etat-lieux-signatures-to-files.php
```

### Étape 4 : Vérifier les résultats

Le script affichera un rapport détaillé :

```
=== Migration: Convert État des Lieux Base64 Signatures to Physical JPG Files ===

✓ Directory already exists: uploads/signatures

=== Part 1: Migrating Tenant Signatures ===

Found 5 tenant(s) with base64 signatures

Processing etat_lieux_locataire ID 1 (etat_lieux ID: 10)...
  ✓ Converted to physical file: uploads/signatures/tenant_etat_lieux_10_1_migrated_1707234567_1.jpg
Processing etat_lieux_locataire ID 2 (etat_lieux ID: 10)...
  ✓ Converted to physical file: uploads/signatures/tenant_etat_lieux_10_2_migrated_1707234567_2.jpg
...

--- Tenant Signatures Migration Summary ---
Successfully converted: 5
Failed: 0
Total: 5

=== Part 2: Migrating Landlord Signatures ===

Checking parameter: signature_societe_etat_lieux_image...
  ✓ Converted to physical file: uploads/signatures/landlord_signature_societe_etat_lieux_image_migrated_1707234567_1.jpg
Checking parameter: signature_societe_image...
  ℹ Already a file path: uploads/signatures/landlord_123.jpg

--- Landlord Signatures Migration Summary ---
Successfully converted: 1
Failed: 0

=== Migration Complete ===
Total signatures converted: 6
Total failures: 0

✓ Migration successful! All base64 signatures have been converted to physical JPG files.
  Signatures are now stored in: uploads/signatures/
  TCPDF will use these files without borders.
```

## Ce que fait le script

### Partie 1 : Signatures des Locataires

1. **Recherche** toutes les signatures base64 dans la table `etat_lieux_locataires`
2. Pour chaque signature :
   - Décode le base64
   - Crée un fichier JPG physique : `tenant_etat_lieux_{id}_{tenant_id}_migrated_{timestamp}_{counter}.jpg`
   - Met à jour la base de données avec le chemin relatif
   - En cas d'erreur, nettoie le fichier créé

### Partie 2 : Signatures du Bailleur

1. **Vérifie** les paramètres suivants :
   - `signature_societe_etat_lieux_image` (signature spécifique aux états des lieux)
   - `signature_societe_image` (signature générale, utilisée comme fallback)
2. Pour chaque paramètre avec une signature base64 :
   - Décode le base64
   - Crée un fichier JPG physique : `landlord_{param_key}_migrated_{timestamp}_{counter}.jpg`
   - Met à jour le paramètre avec le chemin relatif

## Format des Fichiers

- **Extension** : Toujours `.jpg` (quel que soit le format original)
- **Emplacement** : `uploads/signatures/`
- **Nommage Locataires** : `tenant_etat_lieux_{etat_lieux_id}_{tenant_id}_migrated_{timestamp}_{counter}.jpg`
- **Nommage Bailleur** : `landlord_{param_key}_migrated_{timestamp}_{counter}.jpg`

## Base de Données

### Avant Migration

```sql
-- Table etat_lieux_locataires
signature_data = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAAA...'

-- Table parametres
valeur = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAAA...'
```

### Après Migration

```sql
-- Table etat_lieux_locataires
signature_data = 'uploads/signatures/tenant_etat_lieux_10_1_migrated_1707234567_1.jpg'

-- Table parametres
valeur = 'uploads/signatures/landlord_signature_societe_etat_lieux_image_migrated_1707234567_1.jpg'
```

## Vérification Post-Migration

### 1. Vérifier les fichiers créés

```bash
ls -lh uploads/signatures/
# Devrait afficher tous les fichiers JPG créés
```

### 2. Vérifier la base de données

```sql
-- Vérifier qu'il ne reste plus de base64 dans etat_lieux_locataires
SELECT COUNT(*) as nb_base64
FROM etat_lieux_locataires
WHERE signature_data LIKE 'data:image/%';
-- Devrait retourner 0

-- Vérifier les chemins de fichiers
SELECT id, signature_data
FROM etat_lieux_locataires
WHERE signature_data IS NOT NULL;
-- Devrait afficher uniquement des chemins : uploads/signatures/...

-- Vérifier les paramètres
SELECT cle, valeur
FROM parametres
WHERE cle IN ('signature_societe_etat_lieux_image', 'signature_societe_image');
```

### 3. Tester la génération d'un PDF

1. Générer un PDF d'état des lieux existant
2. Vérifier que **les signatures s'affichent sans bordures**
3. Vérifier dans les logs que les fichiers JPG sont utilisés

## Dépannage

### Problème : Répertoire non accessible

```
✗ Failed to create uploads/signatures directory
```

**Solution** : Vérifier les permissions

```bash
chmod 755 uploads
chmod 755 uploads/signatures
```

### Problème : Échec d'enregistrement de fichier

```
✗ Failed to save file: uploads/signatures/xxx.jpg (Permission denied)
```

**Solution** : Vérifier les permissions d'écriture

```bash
# Donner les droits d'écriture au serveur web
chown -R www-data:www-data uploads/signatures
chmod 755 uploads/signatures
```

### Problème : Échec de mise à jour de la base de données

**Cause possible** : Connexion à la base de données
**Solution** : Vérifier `includes/config.php` et les credentials

### Problème : Certaines signatures ne sont pas converties

**Diagnostic** : Regarder les messages d'erreur du script
**Actions** :
- Vérifier le format de la signature (doit être `data:image/...;base64,...`)
- Vérifier que le base64 est valide
- Exécuter à nouveau le script (il ignorera les signatures déjà converties)

## Réexécution du Script

Le script peut être exécuté **plusieurs fois sans risque** :
- Il ignore les signatures déjà converties (qui ne commencent pas par `data:image/`)
- Il crée de nouveaux fichiers avec des noms uniques (timestamp + counter)
- Il ne supprime jamais les fichiers existants

## Sécurité

### Points de sécurité

- ✅ **Validation du format** : Seules les images PNG/JPEG/JPG sont acceptées
- ✅ **Nettoyage en cas d'erreur** : Les fichiers sont supprimés si la mise à jour DB échoue
- ✅ **Permissions** : Fichiers créés avec permissions 0644 (lecture seule pour les autres)
- ✅ **Répertoire sécurisé** : `uploads/signatures/` avec permissions 0755

### Après Migration

- Considérer l'ajout d'un `.htaccess` dans `uploads/signatures/` pour limiter l'accès direct
- Vérifier que le répertoire n'est pas listé publiquement

## Impact

### Avantages

1. **✅ PDFs sans bordures** : TCPDF respecte maintenant le style CSS
2. **✅ Performance** : Pas de conversion à la volée lors de la génération PDF
3. **✅ Taille de BDD réduite** : ~90% de réduction par signature
4. **✅ Cohérence** : Format uniforme pour toutes les signatures

### Compatibilité

- ✅ **Code existant** : Compatible avec le code actuel (qui gère déjà les deux formats)
- ✅ **Nouvelles signatures** : Déjà enregistrées comme fichiers JPG
- ✅ **Fallback** : Le code conserve un fallback base64 en cas d'erreur

## Support

Pour toute question ou problème :
1. Vérifier ce document
2. Consulter les logs du script
3. Vérifier les logs PHP du serveur
4. Contacter le support technique

---

**Date de création** : 2026-02-06  
**Version** : 1.0  
**Auteur** : Migration automatique vers fichiers JPG
