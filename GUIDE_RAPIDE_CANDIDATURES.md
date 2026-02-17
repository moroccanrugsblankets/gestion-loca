# Guide Rapide - Diagnostic Système de Candidatures

## 🎯 Objectif
Résoudre le problème: "Les candidatures envoient des emails mais n'apparaissent pas dans l'admin"

## 🚀 Démarrage Rapide (5 minutes)

### Étape 1: Déployer les Fichiers
Copiez ces fichiers sur votre serveur:
- `candidature/submit.php` (modifié)
- `admin-v2/candidatures.php` (modifié)
- `test-candidature-database.php` (nouveau)

### Étape 2: Tester le Système
```bash
# Connectez-vous en SSH à votre serveur
cd /path/to/gestion-loca

# Exécutez le script de diagnostic
php test-candidature-database.php
```

### Étape 3: Interpréter les Résultats

#### ✅ Si tous les tests passent:
```
=== Test 1: Configuration ===
✓ Configuration chargée avec succès

=== Test 2: Connexion Base de Données ===
✓ Connexion à la base de données établie

=== Test 3: Vérification Table candidatures ===
✓ Table 'candidatures' existe

=== Test 4: Statistiques Candidatures ===
✓ Nombre total de candidatures: 5
```

**Le système est OK!** Le problème est ailleurs:
- Vérifiez le cache du navigateur (Ctrl+F5)
- Vérifiez que vous êtes connecté en tant qu'admin
- Vérifiez les filtres dans l'interface admin

#### ❌ Si un test échoue:

**Test 1 échoue (Configuration)**
```
✗ Erreur lors du chargement de la configuration
```
→ Vérifiez que `includes/config.php` existe

**Test 2 échoue (Connexion DB)**
```
✗ Erreur de connexion
```
→ Vérifiez:
1. MySQL est démarré: `sudo service mysql status`
2. Les credentials dans `includes/config.php`
3. La base de données existe: `mysql -u root -p -e "SHOW DATABASES;"`

**Test 3 échoue (Table candidatures)**
```
✗ Table 'candidatures' n'existe pas
```
→ Créez la table:
```bash
mysql -u root -p bail_signature < database.sql
```

### Étape 4: Activer le Mode Debug (Si Nécessaire)

Si les tests passent mais le problème persiste:

```php
// Créez ou modifiez includes/config.local.php
<?php
return [
    'DEBUG_MODE' => true,
];
```

### Étape 5: Tester une Candidature

1. Soumettez une candidature via le formulaire
2. Consultez immédiatement les logs:

```bash
# Voir les logs en temps réel
tail -f error.log

# Ou filtrer seulement les candidatures
grep "[CANDIDATURE DEBUG]" error.log | tail -20
```

### Étape 6: Vérifier l'Admin

```bash
# Vérifier combien de candidatures l'admin trouve
grep "[ADMIN CANDIDATURES]" error.log | tail -5
```

## 📊 Logs à Chercher

### Logs Normaux (✅ Tout va bien)
```
[CANDIDATURE DEBUG] Début du traitement de la candidature
[CANDIDATURE DEBUG] Connexion base de données vérifiée
[CANDIDATURE DEBUG] Transaction démarrée
[CANDIDATURE DEBUG] Candidature insérée | Data: {"id":123,...}
[CANDIDATURE DEBUG] Transaction validée et candidature persistée
[CANDIDATURE DEBUG] Email de confirmation envoyé
[CANDIDATURE DEBUG] Notification admin envoyée
```

### Logs Problématiques (❌ Erreur)

**Connexion DB manquante:**
```
[CANDIDATURE DEBUG] ERREUR CRITIQUE: Connexion à la base de données non établie
```
→ Vérifier includes/config.php et MySQL

**Transaction échoue:**
```
[CANDIDATURE DEBUG] Transaction démarrée
[CANDIDATURE DEBUG] ERREUR | Data: {"message":"..."}
[CANDIDATURE DEBUG] Transaction annulée
```
→ Regarder le message d'erreur exact

**Admin ne trouve rien:**
```
[ADMIN CANDIDATURES] Nombre de candidatures trouvées: 0
```
→ Vérifier que la DB est la même (config.local.php?)

## 🔧 Solutions Rapides par Symptôme

### Symptôme: "0 candidatures" dans l'admin
```bash
# Vérifier qu'il y a vraiment des candidatures
mysql -u root -p -D bail_signature -e "SELECT COUNT(*) FROM candidatures;"

# Si COUNT > 0 mais admin affiche 0:
# → Problème de requête ou de connexion côté admin
# → Activer DEBUG_MODE et vérifier les logs
```

### Symptôme: Emails envoyés mais pas de données
```bash
# Vérifier les logs de soumission
grep "Transaction validée" error.log

# Si absent:
# → La transaction a rollback
# → Regarder le message d'erreur juste avant
```

### Symptôme: Erreur "Call to member function"
```
Fatal error: Call to a member function prepare() on null
```
→ $pdo est null, MySQL n'est pas connecté

## ⚡ Commandes Utiles

```bash
# Nettoyer les logs
> error.log

# Compter les candidatures
mysql -u root -p -D bail_signature -e "SELECT COUNT(*) FROM candidatures;"

# Voir les 5 dernières
mysql -u root -p -D bail_signature -e "SELECT id, reference_unique, email, statut, date_soumission FROM candidatures ORDER BY date_soumission DESC LIMIT 5;"

# Redémarrer MySQL
sudo service mysql restart

# Redémarrer Apache
sudo service apache2 restart
```

## 📞 Si Rien Ne Fonctionne

1. Copiez le résultat de `php test-candidature-database.php`
2. Copiez les dernières lignes de `error.log`:
   ```bash
   tail -50 error.log > debug-output.txt
   ```
3. Partagez ces informations pour obtenir de l'aide

## ✅ Checklist Finale

- [ ] test-candidature-database.php exécuté - tous les tests passent
- [ ] Candidature de test soumise
- [ ] Email reçu
- [ ] Candidature visible dans admin
- [ ] DEBUG_MODE désactivé (si activé)
- [ ] Logs nettoyés

## 🎉 C'est Résolu!

Une fois que tout fonctionne:

1. **Désactivez DEBUG_MODE**:
   ```php
   // includes/config.local.php
   return [
       'DEBUG_MODE' => false, // ou supprimez cette ligne
   ];
   ```

2. **Nettoyez les logs**:
   ```bash
   > error.log
   ```

3. **Testez une fois de plus** pour confirmer

## 📚 Fichiers de Référence

- `FIX_CANDIDATURE_SYSTEM.md` - Documentation complète
- `SUMMARY_CANDIDATURE_FIX.md` - Résumé technique
- `test-candidature-database.php` - Script de diagnostic

---

**Temps estimé de résolution:** 5-30 minutes selon le problème
