# Migration 033: État des Lieux de Sortie HTML Template

## Description

Cette migration ajoute le template HTML pour les états des lieux de sortie (Move-Out Inventory) dans la base de données.

Le template inclut tous les champs spécifiques aux sorties:
- Section "Dépôt de garantie" (statut de restitution, montant retenu, motif)
- Section "Bilan du logement" (tableau des dégradations avec valeurs)
- Badges de conformité pour les clés et l'état général
- Numérotation dynamique des sections
- Observations et dégradations conditionnelles

## Comment Exécuter

### Option 1: Exécution Directe (Recommandé)

```bash
php migrations/033_add_etat_lieux_sortie_template.php
```

### Option 2: Via le Script de Migration Principal

**Note:** Le script `run-migrations.php` ne gère actuellement que les fichiers `.sql`. 
Pour les migrations PHP, utilisez l'Option 1 ci-dessus.

## Vérification

Après avoir exécuté la migration, vérifiez dans la base de données:

```sql
SELECT cle, type, groupe, description, LENGTH(valeur) as template_length 
FROM parametres 
WHERE cle = 'etat_lieux_sortie_template_html';
```

Résultat attendu:
- **cle:** `etat_lieux_sortie_template_html`
- **type:** `string`
- **groupe:** `templates`
- **description:** Template HTML pour l'état des lieux de sortie (exit inspection)
- **template_length:** ~7332 caractères

## Dépendances

Cette migration nécessite:
- ✅ Migration 002: `002_create_parametres_table.sql` (table `parametres`)
- ✅ Fichier: `includes/etat-lieux-template.php` (fonction `getDefaultExitEtatLieuxTemplate()`)

## Comportement

- Si le template existe déjà → **mise à jour** du template
- Si le template n'existe pas → **création** d'une nouvelle entrée

La migration est **idempotente** : elle peut être exécutée plusieurs fois sans causer d'erreurs.

## Rollback

Pour annuler cette migration:

```sql
DELETE FROM parametres WHERE cle = 'etat_lieux_sortie_template_html';
```

## Utilisation du Template

Une fois la migration exécutée, le template sera automatiquement utilisé lors de la génération des PDF d'états des lieux de sortie via:

```php
$pdfPath = generateEtatDesLieuxPDF($contratId, 'sortie');
```

Le système chargera le template depuis la base de données (table `parametres`) et remplacera les placeholders par les données réelles.

## Support

En cas de problème:

1. Vérifiez que la table `parametres` existe
2. Vérifiez que le fichier `includes/etat-lieux-template.php` est présent
3. Consultez les logs d'erreur de la migration
4. Vérifiez les permissions de connexion à la base de données

## Changelog

- **2026-02-07**: Création initiale de la migration
  - Ajout du template HTML de sortie avec 7332 caractères
  - Support des sections conditionnelles (dépôt de garantie, bilan du logement)
  - Badges de conformité et numérotation dynamique
