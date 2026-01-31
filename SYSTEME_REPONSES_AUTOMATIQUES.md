# Système de Réponses Automatiques

## Vue d'ensemble

Le système de réponses automatiques permet d'envoyer automatiquement des emails d'acceptation ou de refus aux candidatures après un délai configurable. Ce délai permet de laisser une période de réflexion avant l'envoi définitif des emails.

## Fonctionnement

### 1. Soumission d'une candidature

Lorsqu'une candidature est soumise via le formulaire :

1. **Évaluation immédiate** : La candidature est évaluée automatiquement selon les critères configurés dans les Paramètres :
   - Revenus mensuels minimums (défaut : 3000€)
   - Statut professionnel accepté (défaut : CDI, CDD)
   - Type de revenus accepté (défaut : Salaires)
   - Nombre d'occupants acceptés (défaut : 1 ou 2)
   - Garantie Visale requise
   - Période d'essai terminée (pour CDI)

2. **Marquage pour traitement différé** :
   - Si la candidature **ne remplit pas** les critères → statut = `refuse`, `reponse_automatique` = `en_attente`
   - Si la candidature **remplit** les critères → statut = `en_cours`, `reponse_automatique` = `en_attente`
   - Dans les deux cas, aucun email n'est envoyé immédiatement

3. **Programmation de l'envoi** : La candidature est programmée pour recevoir un email après le délai configuré

### 2. Délai configurable

Le délai avant l'envoi automatique se configure dans **Admin > Paramètres > Workflow et Délais** :

- **Valeur** : Nombre d'unités de temps (ex: 10)
- **Unité** : Minutes, Heures, ou Jours (ouvrés pour les jours)

**Configuration recommandée actuelle** : 10 minutes

### 3. Traitement automatique (Cron Job)

Le script `cron/process-candidatures.php` s'exécute périodiquement et :

1. **Récupère** toutes les candidatures avec `reponse_automatique = 'en_attente'`
2. **Filtre** celles dont le délai configuré est dépassé
3. **Réévalue** chaque candidature (au cas où les paramètres auraient changé)
4. **Envoie** l'email approprié (acceptation ou refus)
5. **Met à jour** le statut : `reponse_automatique` = `'accepte'` ou `'refuse'`

### 4. Section "Réponses Automatiques Programmées"

Dans **Admin > Tâches Automatisées**, cette section affiche :

- Toutes les candidatures en attente d'envoi automatique
- La date de soumission
- La date prévue d'envoi (calculée selon le délai configuré)
- Un badge "Prêt à traiter" pour celles dont le délai est dépassé

## Configuration du Cron

### Fréquence recommandée

La fréquence du cron doit être adaptée au délai configuré :

| Délai configuré | Fréquence du cron | Expression cron |
|----------------|-------------------|-----------------|
| **10 minutes** | Toutes les 5 minutes | `*/5 * * * *` |
| **1 heure** | Toutes les 30 minutes | `*/30 * * * *` |
| **Plusieurs heures** | Toutes les heures | `0 * * * *` |
| **Jours** | Une fois par jour (9h00) | `0 9 * * *` |

### Installation

1. Connectez-vous au serveur via SSH
2. Ouvrez la configuration cron : `crontab -e`
3. Ajoutez la ligne correspondante (exemple pour 10 minutes) :

```bash
# Traitement automatique des candidatures - toutes les 5 minutes
*/5 * * * * /usr/bin/php /chemin/vers/cron/process-candidatures.php

# Configuration email pour recevoir les notifications d'erreur
MAILTO=votre-email@example.com
```

4. Sauvegardez et vérifiez avec : `crontab -l`

### Test manuel

Vous pouvez exécuter manuellement le traitement depuis **Admin > Tâches Automatisées** en cliquant sur "Exécuter maintenant" pour la tâche concernée.

## Avantages du système

1. **Délai de réflexion** : Permet de vérifier/modifier les candidatures avant l'envoi automatique
2. **Flexibilité** : Le délai est configurable selon les besoins
3. **Transparence** : Vue en temps réel des candidatures en attente de traitement
4. **Traçabilité** : Tous les emails envoyés sont loggés dans la base de données
5. **Récupération** : Si les critères changent entre la soumission et l'envoi, la réévaluation prend en compte les nouveaux critères

## Cas d'usage

### Exemple 1 : Délai de 10 minutes

**Scénario** : Une candidature est soumise à 14h00 avec des revenus < 3000€

1. **14h00** : Candidature soumise et marquée `refuse` / `en_attente`
2. **14h00-14h10** : Candidature visible dans "Réponses Automatiques Programmées"
3. **14h10** : Le cron s'exécute (toutes les 5 minutes), détecte que le délai est passé
4. **14h10** : Email de refus envoyé, `reponse_automatique` = `refuse`
5. **Après 14h10** : La candidature n'apparaît plus dans la liste des réponses programmées

### Exemple 2 : Délai de 4 jours ouvrés

**Scénario** : Candidature soumise lundi à 9h00

1. **Lundi 9h00** : Candidature soumise
2. **Lundi-Vendredi** : Visible dans "Réponses Automatiques Programmées"
3. **Vendredi 9h00** : Le cron quotidien s'exécute, 4 jours ouvrés sont passés
4. **Vendredi 9h00** : Email envoyé automatiquement

## Monitoring

### Vérification du bon fonctionnement

1. **Interface Admin** : Vérifier régulièrement la section "Réponses Automatiques Programmées"
2. **Logs système** : `tail -f /var/log/syslog | grep CRON`
3. **Log applicatif** : `cat cron/cron-log.txt`
4. **Base de données** : Vérifier la table `logs` pour les actions `email_acceptation` et `email_refus`

### Alertes

Si les emails ne sont pas envoyés :
- Vérifier que le cron est bien configuré et actif
- Vérifier la configuration SMTP dans `includes/config.php`
- Consulter les logs pour identifier les erreurs

## Personnalisation

### Modifier les templates d'email

Les templates se configurent dans **Admin > Templates d'email** :
- `candidature_acceptee` : Email d'acceptation
- `candidature_refusee` : Email de refus

### Modifier les critères d'évaluation

Les critères se configurent dans **Admin > Paramètres > Critères d'Acceptation**
