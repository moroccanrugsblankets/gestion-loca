# Suppression du paramètre email_admin_candidature

## Contexte
Le paramètre `email_admin_candidature` permettait de configurer un email supplémentaire pour recevoir les notifications de candidatures. Cependant, cette approche était limitée car elle ne permettait d'ajouter qu'un seul email supplémentaire.

## Changements effectués

### 1. Migration de base de données
**Fichier:** `migrations/017_remove_email_admin_candidature.sql`

La migration supprime le paramètre `email_admin_candidature` de la table `parametres`:
```sql
DELETE FROM parametres WHERE cle = 'email_admin_candidature';
```

### 2. Mise à jour de la fonction sendEmailToAdmins
**Fichier:** `includes/mail-templates.php`

La fonction `sendEmailToAdmins()` a été modifiée pour :
- **Supprimer** la récupération du paramètre `email_admin_candidature`
- **Ajouter** une requête SQL pour récupérer tous les emails des administrateurs actifs depuis la table `administrateurs`

#### Avant :
```php
// Email candidature additionnel (si configuré dans parametres)
if ($pdo) {
    try {
        $emailAdminCand = getParameter('email_admin_candidature', '');
        if (!empty($emailAdminCand) && filter_var($emailAdminCand, FILTER_VALIDATE_EMAIL)) {
            $adminEmails[] = $emailAdminCand;
        }
    } catch (Exception $e) {
        error_log("Could not fetch email_admin_candidature parameter: " . $e->getMessage());
    }
}
```

#### Après :
```php
// Récupérer tous les emails des administrateurs actifs depuis la base de données
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT email FROM administrateurs WHERE actif = TRUE AND email IS NOT NULL AND email != ''");
        $adminUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($adminUsers as $adminUserEmail) {
            // Validate email format and avoid duplicates
            if (filter_var($adminUserEmail, FILTER_VALIDATE_EMAIL) && !in_array($adminUserEmail, $adminEmails)) {
                $adminEmails[] = $adminUserEmail;
            }
        }
    } catch (Exception $e) {
        error_log("Could not fetch administrators emails: " . $e->getMessage());
    }
}
```

## Avantages de cette approche

1. **Évolutivité** : Il n'y a plus de limitation sur le nombre d'administrateurs pouvant recevoir les notifications
2. **Centralisation** : La gestion des administrateurs se fait via l'interface d'administration (`/admin-v2/administrateurs.php`)
3. **Simplicité** : Pas besoin de paramètre de configuration séparé pour les emails
4. **Sécurité** : Seuls les comptes actifs reçoivent les notifications

## Comportement actuel

Les notifications admin sont maintenant envoyées à :
1. `ADMIN_EMAIL` (depuis config.php)
2. `ADMIN_EMAIL_SECONDARY` (depuis config.php, si configuré)
3. **Tous les emails des administrateurs actifs** depuis la table `administrateurs`
4. `COMPANY_EMAIL` (depuis config.php, si aucun email admin n'est configuré)

## Instructions de déploiement

1. **Déployer le code** : Mettre à jour les fichiers sur le serveur
2. **Exécuter la migration** :
   ```bash
   mysql -u [user] -p [database] < migrations/017_remove_email_admin_candidature.sql
   ```
   Ou utiliser le script de migration :
   ```bash
   php run-migrations.php
   ```
3. **Vérifier les administrateurs** : S'assurer que tous les administrateurs devant recevoir les notifications :
   - Sont dans la table `administrateurs`
   - Ont un statut `actif = TRUE`
   - Ont un email valide configuré
4. **Tester** : Soumettre une candidature de test et vérifier que tous les administrateurs actifs reçoivent la notification

## Tests effectués

Le script de test `test-remove-email-admin-parameter.php` vérifie :
- ✓ La fonction `sendEmailToAdmins` existe
- ✓ La documentation de la fonction est à jour
- ✓ Les références à `email_admin_candidature` ont été supprimées
- ✓ La requête vers la table `administrateurs` est présente
- ✓ Le fichier de migration existe et contient le bon SQL

## Notes importantes

- Les emails configurés dans `config.php` (`ADMIN_EMAIL` et `ADMIN_EMAIL_SECONDARY`) continuent de fonctionner comme avant
- La déduplication des emails est gérée automatiquement pour éviter d'envoyer plusieurs fois au même destinataire
- Les erreurs de récupération des emails d'administrateurs sont loguées mais n'empêchent pas l'envoi aux autres destinataires
