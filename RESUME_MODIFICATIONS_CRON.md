# Résumé des modifications - Amélioration du processus de candidatures (Cron Jobs)

## Vue d'ensemble

Cette PR corrige le système de réponses automatiques pour les candidatures en respectant le délai configuré dans les Paramètres (10 minutes par défaut) au lieu d'une exécution quotidienne fixe à 9h00.

## Problèmes résolus

### 1. ❌ Avant : Message incorrect
La page `admin-v2/cron-jobs.php` affichait :
> "Le traitement automatique s'exécute quotidiennement à 9h00. Les candidatures marquées 'Prêt à traiter' seront traitées lors de la prochaine exécution du cron."

### 1. ✅ Après : Message supprimé et documentation améliorée
- Le message incorrect a été supprimé
- Ajout d'un message clair indiquant le délai configuré
- Documentation du cron avec exemples pour différentes fréquences

### 2. ❌ Avant : Candidatures refusées non programmées
Les candidatures automatiquement refusées (revenus < 3000€) avaient :
- `reponse_automatique = 'refuse'` (déjà traitée)
- N'apparaissaient pas dans "Réponses Automatiques Programmées"
- Email envoyé immédiatement (ou jamais)

### 2. ✅ Après : Programmation du délai pour toutes les candidatures
Toutes les candidatures (acceptées et refusées) ont :
- `reponse_automatique = 'en_attente'` (en attente d'envoi)
- Apparaissent dans "Réponses Automatiques Programmées"
- Email envoyé après le délai configuré (10 minutes par défaut)

### 3. ❌ Avant : Paramètres par défaut inadaptés
- Délai : 4 jours ouvrés
- Trop long pour un processus de réponse rapide

### 3. ✅ Après : Paramètres adaptés
- Délai : 10 minutes
- Conforme aux besoins métier

## Fichiers modifiés

### 1. `admin-v2/cron-jobs.php`
**Lignes 220** : Mise à jour de la description
```php
- <small>Candidatures en attente d'évaluation et d'envoi de réponse automatique (acceptation ou refus)</small>
+ <small>Candidatures en attente d'envoi automatique de mail de réponse (après le délai configuré)</small>
```

**Lignes 228-231** : Ajout d'informations sur le délai
```php
<strong>Délai configuré:</strong> <?php echo $delaiValeur; ?> <?php echo $delaiUnite; ?><br>
<small>Les mails seront envoyés automatiquement <?php echo $delaiValeur; ?> <?php echo $delaiUnite; ?> après la soumission de la candidature.</small>
```

**Lignes 312-316** : SUPPRESSION du message incorrect
```php
- <div class="alert alert-warning">
-     <i class="bi bi-exclamation-triangle"></i> 
-     <strong>Note:</strong> Le traitement automatique s'exécute quotidiennement à 9h00. 
-     Les candidatures marquées "Prêt à traiter" seront traitées lors de la prochaine exécution du cron.
- </div>
```

**Lignes 478-489** : Documentation améliorée du cron
```php
# Traitement des candidatures - toutes les 5 minutes (pour un délai de 10 minutes)
*/5 * * * * /usr/bin/php /path/to/cron/process-candidatures.php

# Alternative: Toutes les 10 minutes
# */10 * * * * /usr/bin/php /path/to/cron/process-candidatures.php

# Alternative: Toutes les heures (si délai en jours)
# 0 * * * * /usr/bin/php /path/to/cron/process-candidatures.php
```

**Lignes 491-497** : Ajout d'une alerte explicative
```php
<div class="alert alert-warning mt-2">
    <strong>Important:</strong> Ajustez la fréquence du cron en fonction du délai configuré dans les paramètres.
    <br>• Pour un délai de 10 minutes → Exécuter toutes les 5 minutes (*/5 * * * *)
    <br>• Pour un délai en heures → Exécuter toutes les heures (0 * * * *)
    <br>• Pour un délai en jours → Exécuter quotidiennement (0 9 * * *)
</div>
```

### 2. `candidature/submit.php`
**Lignes 172-182** : Modification de la logique de programmation
```php
- // If candidature is rejected (e.g., income < 2300€), set status to 'refuse'
- // Otherwise, set to 'en_cours' and wait for cron job evaluation
+ // All candidatures are set to 'en_attente' for automatic response processing
+ // The cron job will send acceptance or rejection emails after the configured delay
if (!$evaluation['accepted']) {
    $initialStatut = 'refuse';
-   $reponseAutomatique = 'refuse';
+   $reponseAutomatique = 'en_attente'; // Changed: schedule rejection email after delay
    $motifRefus = $evaluation['motif'];
} else {
    $initialStatut = 'en_cours';
    $reponseAutomatique = 'en_attente';
    $motifRefus = null;
}
```

### 3. `migrations/014_update_delay_to_minutes.sql` (NOUVEAU)
Migration pour mettre à jour les paramètres :
```sql
UPDATE parametres 
SET valeur = '10' 
WHERE cle = 'delai_reponse_valeur';

UPDATE parametres 
SET valeur = 'minutes' 
WHERE cle = 'delai_reponse_unite';
```

### 4. `SYSTEME_REPONSES_AUTOMATIQUES.md` (NOUVEAU)
Documentation complète du système :
- Fonctionnement détaillé
- Configuration recommandée
- Exemples d'utilisation
- Guide de monitoring
- Troubleshooting

### 5. `test-automatic-response-scheduling.php` (NOUVEAU)
Tests automatisés pour vérifier :
- ✅ Candidatures refusées programmées correctement
- ✅ Candidatures acceptées programmées correctement
- ✅ Calcul du délai correct
- ✅ Toutes apparaissent dans la liste en attente

### 6. `GUIDE_VERIFICATION_CHANGEMENTS.md` (NOUVEAU)
Guide pour tester manuellement :
- Étapes de vérification
- Captures d'écran attendues
- Liste de contrôle
- Procédure de rollback si nécessaire

## Impact sur le workflow

### Ancien workflow
1. Candidature soumise → Évaluation immédiate
2. Si refusée → `reponse_automatique = 'refuse'` (déjà traitée)
3. N'apparaît pas dans l'admin
4. Pas d'email envoyé (ou envoyé immédiatement selon l'ancienne logique)

### Nouveau workflow
1. Candidature soumise → Évaluation immédiate
2. Si refusée → `statut = 'refuse'` + `reponse_automatique = 'en_attente'`
3. **Apparaît dans "Réponses Automatiques Programmées"**
4. **Après 10 minutes** → Cron s'exécute
5. **Email de refus envoyé** → `reponse_automatique = 'refuse'`
6. N'apparaît plus dans la liste

## Configuration requise

### Base de données
Exécuter la migration 014 :
```bash
mysql -u user -p database < migrations/014_update_delay_to_minutes.sql
```

### Serveur (Cron)
Mettre à jour la crontab pour exécuter toutes les 5 minutes :
```bash
# Éditer la crontab
crontab -e

# Ajouter (pour un délai de 10 minutes)
*/5 * * * * /usr/bin/php /path/to/cron/process-candidatures.php
MAILTO=admin@example.com
```

## Tests

### Tests automatisés
```bash
php test-automatic-response-scheduling.php
```
**Résultat** : 4/4 tests passés ✅

### Tests manuels
Suivre le guide : `GUIDE_VERIFICATION_CHANGEMENTS.md`

## Bénéfices

1. ✅ **Conformité** : Le système respecte le paramètre configuré
2. ✅ **Transparence** : Vue en temps réel des candidatures en attente
3. ✅ **Flexibilité** : Délai configurable (minutes/heures/jours)
4. ✅ **Traçabilité** : Toutes les actions sont loggées
5. ✅ **Documentation** : Guide complet et exemples

## Livrables

- [x] Section "Réponses Automatiques Programmées" fonctionnelle
- [x] Cron ajusté pour respecter le paramètre configuré (10 minutes)
- [x] Suppression du message incorrect sur l'exécution quotidienne à 9h00
- [x] Tests automatisés validant le fonctionnement
- [x] Documentation complète du système
- [x] Guide de vérification pour les tests manuels

## Migration vers la production

1. **Backup** : Sauvegarder la base de données
2. **Code** : Déployer les fichiers modifiés
3. **Migration** : Exécuter `014_update_delay_to_minutes.sql`
4. **Cron** : Mettre à jour la crontab avec la nouvelle fréquence
5. **Test** : Soumettre une candidature test et vérifier
6. **Monitoring** : Surveiller les logs pendant 24h

## Rollback (si nécessaire)

```sql
-- Restaurer l'ancien délai (4 jours)
UPDATE parametres SET valeur = '4' WHERE cle = 'delai_reponse_valeur';
UPDATE parametres SET valeur = 'jours' WHERE cle = 'delai_reponse_unite';

-- Restaurer l'ancien cron (quotidien à 9h00)
-- crontab -e
-- 0 9 * * * /usr/bin/php /path/to/cron/process-candidatures.php
```

## Support

- Documentation : `SYSTEME_REPONSES_AUTOMATIQUES.md`
- Tests : `test-automatic-response-scheduling.php`
- Vérification : `GUIDE_VERIFICATION_CHANGEMENTS.md`

---

**Date de création** : 31 janvier 2026  
**Auteur** : GitHub Copilot  
**Status** : ✅ Prêt pour déploiement
