# Correction du Système de Candidatures - Résumé des Modifications

## Problème Résolu

### Problème Initial
Lorsque le paramètre **"Délai de réponse automatique"** était modifié, toutes les tâches programmées voyaient leur date de "Réponse Prévue" recalculée dynamiquement. Ce comportement était incorrect car la date devait rester **fixe** et conserver la valeur programmée lors de la création de la tâche.

### Solution Implémentée
Nous avons ajouté un nouveau champ `scheduled_response_date` dans la table `candidatures` qui stocke la date de réponse prévue de manière **permanente** au moment où la candidature est refusée.

## Modifications Apportées

### 1. Base de Données

**Migration créée**: `015_add_scheduled_response_date_and_cleanup.sql`

```sql
-- Ajout de la colonne scheduled_response_date
ALTER TABLE candidatures 
ADD COLUMN scheduled_response_date DATETIME NULL 
COMMENT 'Date fixe de réponse prévue, calculée lors du refus' 
AFTER date_reponse_auto;

-- Suppression des paramètres obsolètes
DELETE FROM parametres WHERE cle = 'delai_reponse_jours';
DELETE FROM parametres WHERE cle = 'delai_refus_auto_heures';
```

### 2. Code Backend

#### a) Nouvelle fonction de calcul (`includes/functions.php`)
```php
function calculateScheduledResponseDate($fromDate) {
    // Calcule la date de réponse prévue en fonction des paramètres actuels
    // Cette fonction est appelée UNE SEULE FOIS lors du refus
}
```

#### b) Modifications des fichiers de traitement

**`admin-v2/change-status.php`**
- Lors du refus manuel d'une candidature, calcule et stocke `scheduled_response_date`
- La date reste fixe même si les paramètres globaux changent ensuite

**`candidature/reponse-candidature.php`**
- Lors du refus via lien email, calcule et stocke également `scheduled_response_date`

**`cron/process-candidatures.php`**
- Utilise maintenant `scheduled_response_date` si disponible
- Compatibilité avec les anciennes candidatures (calcul depuis `created_at`)
- Les nouvelles candidatures refusées utilisent le délai configuré au moment du refus

**`admin-v2/cron-jobs.php`**
- Affiche la date stockée `scheduled_response_date` au lieu de la recalculer
- Filtre les candidatures dont la date prévue n'est pas encore passée

**`admin-v2/candidature-detail.php`**
- Affiche la date de réponse prévue avec indication qu'elle est fixe

### 3. Interface Utilisateur

**`admin-v2/parametres.php`**
- Suppression de l'affichage des anciens paramètres :
  - ❌ "Délai de réponse automatique (jours ouvrés) - ANCIEN"
  - ❌ "Délai d'envoi automatique de refus (heures) - ANCIEN"
- Ces paramètres sont maintenant cachés de l'interface

## Comportement Attendu

### Scénario 1 : Nouvelle candidature refusée manuellement
1. Admin refuse une candidature dans le panneau d'administration
2. Le système calcule `scheduled_response_date` = `created_at` + délai configuré actuel
3. Cette date est **stockée** et ne changera plus

### Scénario 2 : Modification du paramètre de délai
1. Paramètre initial : 4 jours
2. Candidature A refusée → `scheduled_response_date` = created_at + 4 jours
3. Changement du paramètre → 2 jours
4. ✅ Candidature A garde sa date (created_at + 4 jours)
5. ✅ Nouvelle candidature B refusée → `scheduled_response_date` = created_at + 2 jours

### Scénario 3 : Cron automatique
1. Le cron vérifie les candidatures en attente
2. Pour chaque candidature :
   - Si `scheduled_response_date` existe → utilise cette date
   - Sinon (anciennes candidatures) → calcule depuis `created_at`
3. Envoie les emails uniquement si la date est dépassée

## Tests de Validation

### Script de test automatique
Exécuter : `php test-scheduled-response-fix.php`

Ce script vérifie :
- ✅ Existence de la colonne `scheduled_response_date`
- ✅ Suppression des paramètres obsolètes
- ✅ Fonctionnement de `calculateScheduledResponseDate()`
- ✅ État des candidatures programmées

### Test manuel complet

#### Étape 1 : Appliquer la migration
```bash
php run-migrations.php
```

#### Étape 2 : Créer une candidature test
1. Accéder au formulaire de candidature
2. Remplir et soumettre (peut être acceptée ou refusée automatiquement)

#### Étape 3 : Refuser manuellement
1. Dans l'admin, aller sur la liste des candidatures
2. Sélectionner une candidature "en_cours"
3. Changer le statut à "refusé"
4. Vérifier dans la base de données que `scheduled_response_date` est rempli

```sql
SELECT id, reference_unique, created_at, scheduled_response_date, statut
FROM candidatures 
WHERE statut = 'refuse' AND scheduled_response_date IS NOT NULL
LIMIT 5;
```

#### Étape 4 : Modifier le paramètre
1. Aller dans **Paramètres** > **Workflow et Délais**
2. Changer "Délai de réponse automatique" (ex: de 4 jours à 1 jour)
3. Enregistrer

#### Étape 5 : Vérifier la date reste fixe
1. Retourner sur **Tâches Automatisées**
2. Vérifier que la "Réponse Prévue" de la candidature refusée précédemment n'a **PAS** changé
3. Re-vérifier en base de données :

```sql
-- La date doit être identique à celle de l'étape 3
SELECT id, reference_unique, scheduled_response_date
FROM candidatures 
WHERE id = <ID_de_la_candidature_test>;
```

#### Étape 6 : Refuser une nouvelle candidature
1. Refuser une autre candidature
2. Vérifier qu'elle utilise le **nouveau** délai (1 jour au lieu de 4)

#### Étape 7 : Test du cron
```bash
php cron/process-candidatures.php
```

Vérifier dans les logs que :
- Les candidatures avec `scheduled_response_date` dépassée sont traitées
- Les nouvelles candidatures utilisent le bon délai

## Vérification Visuelle

### Page Paramètres
Avant :
- ✅ Délai de réponse automatique (jours ouvrés) - ANCIEN
- ✅ Délai d'envoi automatique de refus (heures) - ANCIEN
- ✅ Délai de réponse automatique (valeur + unité)

Après :
- ❌ ~~Délai de réponse automatique (jours ouvrés) - ANCIEN~~ (caché)
- ❌ ~~Délai d'envoi automatique de refus (heures) - ANCIEN~~ (caché)
- ✅ Délai de réponse automatique (valeur + unité)

### Page Tâches Automatisées
La colonne "Réponse Prévue" affiche maintenant :
- La date **stockée** dans `scheduled_response_date` (pour les nouvelles)
- La date **calculée** depuis `created_at` (pour compatibilité anciennes candidatures)

### Page Détails Candidature
Ajout d'une ligne si `scheduled_response_date` existe :
```
Réponse prévue le: 15/02/2024 à 14:30 (Date fixe calculée lors du refus)
```

## Impacts et Compatibilité

### ✅ Compatibilité Ascendante
- Les anciennes candidatures sans `scheduled_response_date` continuent de fonctionner
- Le cron calcule la date depuis `created_at` pour elles
- Aucune perte de données

### ✅ Suppression Progressive
- Les anciens paramètres sont supprimés de la base de données par la migration
- Plus de confusion avec plusieurs paramètres de délai

### ✅ Code Propre
- Une seule source de vérité : `scheduled_response_date`
- Calcul effectué une seule fois lors du refus
- Pas de recalcul dynamique

## Résumé des Fichiers Modifiés

| Fichier | Type de Modification |
|---------|---------------------|
| `migrations/015_add_scheduled_response_date_and_cleanup.sql` | ➕ Nouveau |
| `includes/functions.php` | ✏️ Ajout fonction `calculateScheduledResponseDate()` |
| `admin-v2/change-status.php` | ✏️ Calcul et stockage de `scheduled_response_date` |
| `candidature/reponse-candidature.php` | ✏️ Calcul et stockage de `scheduled_response_date` |
| `cron/process-candidatures.php` | ✏️ Utilisation de `scheduled_response_date` |
| `admin-v2/cron-jobs.php` | ✏️ Affichage de `scheduled_response_date` |
| `admin-v2/parametres.php` | ✏️ Masquage paramètres obsolètes |
| `admin-v2/candidature-detail.php` | ✏️ Affichage de `scheduled_response_date` |
| `test-scheduled-response-fix.php` | ➕ Nouveau |

## Conclusion

Cette correction garantit que :
1. ✅ La date de "Réponse Prévue" reste **fixe** après programmation
2. ✅ Les modifications du paramètre "Délai de réponse automatique" n'affectent **pas** les tâches déjà créées
3. ✅ Seules les **nouvelles** candidatures refusées utilisent le nouveau délai configuré
4. ✅ Les anciens paramètres obsolètes sont supprimés de l'interface et de la base
5. ✅ Le système reste compatible avec les données existantes
