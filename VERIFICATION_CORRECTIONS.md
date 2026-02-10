# Vérification des Corrections

## Problèmes Identifiés et Résolus

### 1. Erreur SQL dans `/admin-v2/change-status.php` (ligne 62)

**Problème Original:**
```
Fatal error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'candidature_id' in 'field list'
```

**Cause:**
La table `logs` a été migrée pour utiliser une structure polymorphique avec les colonnes `type_entite` et `entite_id` au lieu de colonnes spécifiques comme `candidature_id`.

**Solution Appliquée:**

#### Ligne 62-72 (Log de changement de statut):
```php
// AVANT:
INSERT INTO logs (candidature_id, action, details, ip_address, created_at)
VALUES (?, ?, ?, ?, NOW())

// APRÈS:
INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
VALUES (?, ?, ?, ?, ?, NOW())
```

Avec les paramètres:
```php
['candidature', $candidature_id, $action, $details, $_SERVER['REMOTE_ADDR']]
```

#### Ligne 104-114 (Log d'envoi d'email):
```php
// AVANT:
INSERT INTO logs (candidature_id, action, details, ip_address, created_at)
VALUES (?, ?, ?, ?, NOW())

// APRÈS:
INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
VALUES (?, ?, ?, ?, ?, NOW())
```

Avec les paramètres:
```php
['candidature', $candidature_id, "Email envoyé", "Template: $templateId", $_SERVER['REMOTE_ADDR']]
```

### 2. Suppression des envois à email_admin

**Problème:**
Lors du changement de statut, les emails de refus (`nouveau_statut === 'refuse'`) étaient envoyés avec `isAdminEmail = true`, ce qui entraînait l'envoi de copies aux administrateurs.

**Solution Appliquée:**

#### Ligne 100:
```php
// AVANT:
$isAdminEmail = ($nouveau_statut === 'refuse');
$emailSent = sendTemplatedEmail($templateId, $to, $variables, null, $isAdminEmail);

// APRÈS:
$emailSent = sendTemplatedEmail($templateId, $to, $variables, null, false);
```

**Effet:**
- Les emails sont maintenant envoyés uniquement au candidat
- Aucune copie n'est envoyée aux administrateurs (ni BCC, ni secondaire)
- L'email reste identique pour le candidat, seules les copies admin sont supprimées

### 3. Vérification de `/admin-v2/parametres.php`

**Résultat:**
Le fichier `parametres.php` ne contient **AUCUN code d'envoi d'email**. Il gère uniquement la configuration des paramètres, y compris le paramètre `email_signature` qui est utilisé dans les templates d'emails.

Les seules références à "email" dans ce fichier sont:
- Ligne 146: Label du groupe "Signature Email"
- Ligne 214: Label du paramètre `email_signature`
- Ligne 241-256: Formulaire pour éditer la signature HTML des emails

**Conclusion:**
Aucune modification n'est nécessaire dans `parametres.php`.

## Compatibilité avec la Base de Données

### Structure de la table `logs` (database.sql)

```sql
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_entite ENUM('candidature', 'contrat', 'logement', 'paiement', 'etat_lieux', 'autre') NOT NULL,
    entite_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_type_entite (type_entite, entite_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Compatibilité:**
✓ La nouvelle syntaxe SQL est parfaitement compatible avec cette structure
✓ `type_entite = 'candidature'` correspond à l'ENUM défini
✓ Toutes les colonnes requises sont présentes

## Résumé des Changements

| Fichier | Lignes Modifiées | Changement |
|---------|------------------|------------|
| `/admin-v2/change-status.php` | 62-72 | Structure polymorphique des logs (ajout de `type_entite`) |
| `/admin-v2/change-status.php` | 100 | Suppression de `isAdminEmail` (false au lieu de conditionnel) |
| `/admin-v2/change-status.php` | 104-114 | Structure polymorphique des logs (ajout de `type_entite`) |
| `/admin-v2/parametres.php` | - | Aucune modification nécessaire |

## Tests Recommandés

1. ✓ Vérifier que le changement de statut fonctionne sans erreur 500
2. ✓ Vérifier que les logs sont correctement créés dans la table
3. ✓ Vérifier que les emails sont envoyés uniquement au candidat (pas aux admins)
4. ✓ Tester avec différents statuts: accepte, refuse, visite_planifiee, etc.
