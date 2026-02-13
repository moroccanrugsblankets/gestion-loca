# Migration 050: Ajout de la catégorie Équipement 2 (Linge / Entretien)

## Contexte
Cette migration ajoute la catégorie manquante "Équipement 2 (Linge / Entretien)" à tous les logements existants dans la base de données.

## Items ajoutés
La catégorie inclut les équipements suivants :
- Matelas (1)
- Oreillers (2)
- Taies d'oreiller (2)
- Draps du dessous (1)
- Couette (1)
- Housse de couette (1)
- Alaise (1)
- Plaid (1)

## Comment exécuter cette migration

### Méthode 1 : Utiliser le script run-migrations.php
```bash
cd /path/to/gestion-loca
php run-migrations.php
```
Ce script exécute automatiquement toutes les migrations en attente, y compris la migration 050.

### Méthode 2 : Exécuter directement la migration
```bash
cd /path/to/gestion-loca
php migrations/050_add_equipement_2_linge_entretien.php
```

## Vérification après la migration

Vous pouvez vérifier que la migration a bien fonctionné en :

1. Accédant à l'interface d'administration : `/admin-v2/manage-inventory-equipements.php?logement_id=X`
2. Vérifiant que la catégorie "Équipement 2 (Linge / Entretien)" apparaît avec tous ses items
3. Exécutant cette requête SQL :
```sql
SELECT logement_id, COUNT(*) as count 
FROM inventaire_equipements 
WHERE categorie = 'Équipement 2 (Linge / Entretien)' 
GROUP BY logement_id;
```

## Sécurité

- La migration vérifie si la catégorie existe déjà avant d'ajouter les items
- Les changements sont effectués dans une transaction (rollback en cas d'erreur)
- La migration peut être exécutée plusieurs fois sans créer de doublons

## Notes importantes

1. Cette migration complète les modifications apportées au fichier `includes/inventaire-standard-items.php`
2. Les nouveaux logements créés après l'application de cette migration auront automatiquement cette catégorie (grâce au fichier standard items)
3. Les logements existants ont besoin de cette migration pour avoir la catégorie ajoutée
