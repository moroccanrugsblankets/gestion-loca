# Fix: Système de Candidatures - Diagnostic et Résolution

## Problème Rapporté

**Description originale:**
> Il faut revoir le systeme de candidatures, on a fait une candidature sur https://contrat.myinvest-immobilier.com/candidature/, on reçoit bien le mail, mais rien actualiser dans l'espace d'administration

**Traduction:**
- ✅ La soumission du formulaire de candidature fonctionne
- ✅ Les emails sont envoyés et reçus correctement
- ❌ Les candidatures n'apparaissent pas dans l'espace d'administration (admin-v2/candidatures.php)

## Analyse du Problème

### Flux Normal du Système

1. **Soumission** (`candidature/submit.php`):
   - Vérifie les champs obligatoires
   - Vérifie les documents requis (5 types)
   - Démarre une transaction de base de données
   - Insère la candidature dans la table `candidatures`
   - Enregistre les documents dans `candidature_documents`
   - **COMMIT** la transaction
   - Envoie les emails de confirmation

2. **Affichage Admin** (`admin-v2/candidatures.php`):
   - Se connecte à la base de données
   - Exécute une requête SELECT sur la table `candidatures`
   - Affiche les résultats

### Causes Potentielles Identifiées

#### 1. **Connexion Base de Données Non Établie**
Si `$pdo` est `null` dans `submit.php`, les insertions échouent silencieusement mais les emails peuvent quand même être envoyés.

**Solution:** Ajout d'une vérification explicite de `$pdo` avant toute opération de base de données.

#### 2. **Transaction Non Commitée**
Si la transaction est démarrée mais jamais commitée (ou rollback silencieux), les données ne sont pas persistées.

**Solution:** Ajout d'une vérification post-commit pour confirmer que la candidature a bien été sauvegardée.

#### 3. **Erreurs SQL Silencieuses**
Si une erreur SQL se produit mais n'est pas correctement capturée, les données peuvent ne pas être sauvegardées.

**Solution:** Ajout de logs détaillés et de gestion d'exceptions pour toutes les opérations de base de données.

#### 4. **Différentes Bases de Données**
Si l'environnement utilise différentes bases de données pour la soumission et l'admin (ex: via `config.local.php`).

**Solution:** Vérification et logging du nom de base de données utilisé.

## Modifications Apportées

### 1. `candidature/submit.php`

#### Ajout de Vérification de Connexion
```php
try {
    logDebug("Début du traitement de la candidature");
    
    // Vérifier que la connexion à la base de données est établie
    if (!isset($pdo) || $pdo === null) {
        logDebug("ERREUR CRITIQUE: Connexion à la base de données non établie");
        throw new Exception('Erreur de connexion à la base de données');
    }
    logDebug("Connexion base de données vérifiée");
    
    // ... reste du code
}
```

**Bénéfice:** Détecte immédiatement si la connexion DB est manquante avant toute opération.

#### Ajout de Vérification Post-Commit
```php
// Valider la transaction
$pdo->commit();
logDebug("Transaction validée");

// Vérifier que la candidature a bien été enregistrée
$stmt = $pdo->prepare("SELECT id, reference_unique, statut FROM candidatures WHERE id = ?");
$stmt->execute([$candidature_id]);
$savedCandidature = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$savedCandidature) {
    logDebug("ERREUR CRITIQUE: Candidature non trouvée après commit", ['candidature_id' => $candidature_id]);
    throw new Exception('Erreur lors de l\'enregistrement de la candidature');
}
logDebug("Candidature vérifiée dans la base de données", $savedCandidature);
```

**Bénéfice:** Confirme que la candidature existe bien dans la base après le commit.

### 2. `admin-v2/candidatures.php`

#### Ajout de Vérification et Logging
```php
// Verify database connection
if (!isset($pdo) || $pdo === null) {
    error_log("[ADMIN CANDIDATURES] ERREUR: Connexion à la base de données non établie");
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[ADMIN CANDIDATURES] Nombre de candidatures trouvées: " . count($candidatures));
} catch (PDOException $e) {
    error_log("[ADMIN CANDIDATURES] Erreur SQL: " . $e->getMessage());
    error_log("[ADMIN CANDIDATURES] Query: " . $query);
    error_log("[ADMIN CANDIDATURES] Params: " . json_encode($params));
    die("Erreur lors de la récupération des candidatures. Détails logués.");
}
```

**Bénéfice:** 
- Détecte les problèmes de connexion côté admin
- Log le nombre de candidatures trouvées pour diagnostic
- Capture et log les erreurs SQL avec détails

### 3. `test-candidature-database.php` (Nouveau)

Script de diagnostic complet qui vérifie:
- ✅ Chargement de la configuration
- ✅ Connexion à la base de données
- ✅ Existence de la table `candidatures`
- ✅ Statistiques des candidatures
- ✅ Liste des dernières candidatures
- ✅ Table `candidature_documents`
- ✅ Permissions du dossier uploads
- ✅ Table `logements` et disponibilité

## Comment Diagnostiquer

### Étape 1: Exécuter le Script de Test
```bash
php test-candidature-database.php
```

Ou via navigateur:
```
http://votre-site.com/test-candidature-database.php
```

### Étape 2: Vérifier les Logs
Consultez le fichier `error.log` à la racine du projet:
```bash
tail -f error.log
```

Recherchez les entrées:
- `[CANDIDATURE DEBUG]` - Logs de soumission
- `[ADMIN CANDIDATURES]` - Logs de l'interface admin

### Étape 3: Tester une Soumission

1. Soumettez une nouvelle candidature via le formulaire
2. Vérifiez immédiatement les logs:
   ```bash
   grep "CANDIDATURE DEBUG" error.log | tail -20
   ```
3. Vérifiez si la candidature apparaît dans la base:
   ```bash
   php -r "require 'includes/config.php'; require 'includes/db.php'; \$stmt = \$pdo->query('SELECT * FROM candidatures ORDER BY date_soumission DESC LIMIT 1'); var_dump(\$stmt->fetch());"
   ```
4. Vérifiez l'admin:
   - Accédez à `admin-v2/candidatures.php`
   - Consultez les logs:
     ```bash
     grep "ADMIN CANDIDATURES" error.log | tail -5
     ```

## Solutions par Scénario

### Scénario 1: Test échoue à "Connexion Base de Données"
**Problème:** La base de données n'est pas accessible

**Solutions:**
1. Vérifiez que MySQL est démarré: `service mysql status`
2. Vérifiez les credentials dans `includes/config.php`
3. Vérifiez `includes/config.local.php` si il existe
4. Testez la connexion manuellement:
   ```bash
   mysql -u root -p -h localhost bail_signature
   ```

### Scénario 2: Test échoue à "Table candidatures"
**Problème:** La table n'existe pas

**Solutions:**
1. Exécutez le script SQL principal:
   ```bash
   mysql -u root -p bail_signature < database.sql
   ```
2. Ou exécutez les migrations:
   ```bash
   php run-migrations.php
   ```

### Scénario 3: Table existe mais 0 candidatures
**Problème:** Les soumissions ne s'enregistrent pas

**Solutions:**
1. Soumettez une candidature de test
2. Vérifiez les logs immédiatement
3. Recherchez les messages d'erreur dans `error.log`
4. Vérifiez que `$pdo` n'est pas null dans les logs

### Scénario 4: Candidatures existent mais admin affiche 0
**Problème:** Problème de requête SQL ou de connexion côté admin

**Solutions:**
1. Vérifiez les logs pour `[ADMIN CANDIDATURES]`
2. Vérifiez si le nombre affiché correspond au nombre réel
3. Désactivez les filtres dans l'interface admin
4. Videz le cache du navigateur (Ctrl+F5)

### Scénario 5: Deux bases de données différentes
**Problème:** Submit utilise une DB et Admin une autre

**Solutions:**
1. Vérifiez `includes/config.local.php`
2. Ajoutez du logging pour afficher le nom de la base:
   ```php
   error_log("DB utilisée: " . $config['DB_NAME']);
   ```
3. Vérifiez que les deux fichiers utilisent bien la même configuration

## Prochaines Étapes

1. **Déployer les changements** sur le serveur de production
2. **Exécuter le script de test** pour vérifier l'état actuel
3. **Tester une soumission** et vérifier qu'elle apparaît dans l'admin
4. **Consulter les logs** pour identifier tout problème restant

## Prévention Future

### Monitoring
Ajoutez une alerte automatique si:
- Une soumission réussit mais n'apparaît pas dans la base
- L'admin ne trouve aucune candidature pendant X jours
- Des erreurs SQL sont détectées

### Tests Automatisés
Créez des tests end-to-end qui:
1. Soumettent une candidature
2. Vérifient qu'elle existe dans la base
3. Vérifient qu'elle apparaît dans l'admin
4. Vérifient que l'email est envoyé

## Support

Si le problème persiste après ces modifications:

1. Exécutez `test-candidature-database.php` et partagez les résultats
2. Partagez les dernières lignes de `error.log` (filtré pour `CANDIDATURE`)
3. Vérifiez la version de PHP: `php -v` (doit être >= 7.4)
4. Vérifiez la version de MySQL: `mysql --version` (doit être >= 5.7)

## Fichiers Modifiés

- ✅ `candidature/submit.php` - Ajout de vérifications et logs
- ✅ `admin-v2/candidatures.php` - Ajout de logs et gestion d'erreurs
- ✅ `test-candidature-database.php` - Nouveau script de diagnostic
- ✅ `FIX_CANDIDATURE_SYSTEM.md` - Cette documentation
