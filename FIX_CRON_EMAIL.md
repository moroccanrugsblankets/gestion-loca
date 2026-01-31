# Fix pour le problème d'envoi d'emails du cron

## Problème identifié

Le cron était bien programmé, mais les clients ne recevaient pas les emails après le délai configuré (10 minutes dans l'exemple). Même en exécutant le cron toutes les minutes, le problème persistait.

### Cause racine

**BUG CRITIQUE** dans `/cron/process-candidatures.php` :

1. Le statut de la candidature était mis à jour **AVANT** l'envoi de l'email
2. Si l'envoi de l'email échouait (problème SMTP, mauvaise configuration, etc.), le statut était quand même changé de `en_attente` à `accepte`/`refuse`
3. Une fois le statut changé, le cron ne sélectionnait plus jamais cette candidature (ligne 57 : `WHERE c.reponse_automatique = 'en_attente'`)
4. Résultat : **Le client ne recevait JAMAIS l'email !**

## Solution implémentée

### Changement dans l'ordre des opérations

**AVANT (bugué)** :
```php
// Mise à jour du statut AVANT l'envoi
$updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'accepte', reponse_automatique = 'accepte', ... WHERE id = ?");
$updateStmt->execute([$id]);

// Envoi de l'email APRÈS
if (sendTemplatedEmail('candidature_acceptee', $email, $variables)) {
    logMessage("Email envoyé");
} else {
    logMessage("ERROR: Failed to send email"); // Trop tard ! Le statut est déjà changé !
}
```

**APRÈS (corrigé)** :
```php
// Envoi de l'email D'ABORD
if (sendTemplatedEmail('candidature_acceptee', $email, $variables)) {
    // Mise à jour du statut SEULEMENT si l'email a été envoyé avec succès
    $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'accepte', reponse_automatique = 'accepte', ... WHERE id = ?");
    $updateStmt->execute([$id]);
    logMessage("Email envoyé avec succès");
} else {
    logMessage("ERROR: Failed to send email - candidature will be retried");
    // Le statut reste 'en_attente' - sera retenté au prochain cron !
}
```

### Avantages de cette correction

1. ✅ **Mécanisme de retry automatique** : Si l'envoi échoue, la candidature reste en `en_attente` et sera automatiquement retentée au prochain passage du cron

2. ✅ **Logs détaillés** : Les échecs d'envoi sont maintenant enregistrés dans la table `logs` avec l'action `email_error`

3. ✅ **Fiabilité** : Garantit que le client reçoit toujours l'email, même si le serveur SMTP a des problèmes temporaires

4. ✅ **Traçabilité** : Les administrateurs peuvent voir dans les logs quelles candidatures ont eu des problèmes d'envoi

## Comment vérifier que ça fonctionne

### 1. Vérifier les logs de la base de données

```sql
-- Voir les emails qui ont échoué et qui seront retentés
SELECT 
    c.id,
    c.reference_unique,
    c.nom,
    c.prenom,
    c.email,
    c.reponse_automatique,
    COUNT(l.id) as error_count
FROM candidatures c
LEFT JOIN logs l ON l.entite_id = c.id 
    AND l.type_entite = 'candidature' 
    AND l.action = 'email_error'
WHERE c.reponse_automatique = 'en_attente'
GROUP BY c.id
HAVING error_count > 0;
```

### 2. Vérifier le fichier de log du cron

```bash
tail -f /chemin/vers/cron/cron-log.txt
```

Vous devriez voir :
- `"Acceptance email sent to xxx@example.com for application #123"` pour les succès
- `"ERROR: Failed to send acceptance email to xxx@example.com - candidature #123 will be retried in next cron run"` pour les échecs

### 3. Exécuter le script de test

```bash
php test-cron-email-fix.php
```

Ce script affiche :
- Les candidatures en attente de traitement
- Les logs d'erreurs récents
- Les candidatures qui seront retentées

## Configuration recommandée du cron

Pour un délai de **10 minutes** :
```bash
# Exécuter toutes les 5 minutes pour avoir des retries rapides
*/5 * * * * /usr/bin/php /chemin/vers/cron/process-candidatures.php
```

Pour un délai en **heures** :
```bash
# Exécuter toutes les heures
0 * * * * /usr/bin/php /chemin/vers/cron/process-candidatures.php
```

Pour un délai en **jours** :
```bash
# Exécuter quotidiennement à 9h
0 9 * * * /usr/bin/php /chemin/vers/cron/process-candidatures.php
```

## Débogage en cas de problèmes persistants

Si les emails ne sont toujours pas envoyés après cette correction :

1. **Vérifier la configuration SMTP** dans `includes/config.php` :
   - `SMTP_HOST` : correct ?
   - `SMTP_USERNAME` : correct ?
   - `SMTP_PASSWORD` : correct ?
   - `SMTP_PORT` : 587 pour TLS, 465 pour SSL
   - `SMTP_SECURE` : 'tls' ou 'ssl'

2. **Vérifier les templates d'emails** :
   ```sql
   SELECT * FROM email_templates WHERE id IN ('candidature_acceptee', 'candidature_refusee');
   ```
   Assurez-vous que `actif = 1`

3. **Vérifier les logs système** :
   ```bash
   tail -f /var/log/mail.log
   tail -f /var/log/syslog | grep CRON
   ```

4. **Tester l'envoi manuel** depuis l'interface admin :
   - Aller dans "Tâches Automatisées"
   - Cliquer sur "Exécuter maintenant"
   - Vérifier les logs affichés

## Fichiers modifiés

- `/cron/process-candidatures.php` - Logique corrigée pour l'envoi d'emails

## Résumé

Cette correction garantit que :
- ✅ Les emails sont toujours envoyés aux clients
- ✅ En cas d'échec temporaire, le système réessaie automatiquement
- ✅ Les administrateurs sont alertés via les logs en cas de problème
- ✅ Aucune candidature ne "se perd" à cause d'un problème d'envoi
