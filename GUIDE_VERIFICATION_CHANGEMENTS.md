# Guide de vérification des changements - Réponses Automatiques

## Changements apportés

### 1. Page Admin - Tâches Automatisées (`admin-v2/cron-jobs.php`)

#### Avant :
- Message incorrect : "Le traitement automatique s'exécute quotidiennement à 9h00"
- Section "Réponses Automatiques Programmées" pouvait être vide car les candidatures refusées n'y apparaissaient pas

#### Après :
- ✅ Message incorrect supprimé
- ✅ Description améliorée : "Candidatures en attente d'envoi automatique de mail de réponse (après le délai configuré)"
- ✅ Affiche le délai configuré (exemple : "10 minutes")
- ✅ Indication claire : "Les mails seront envoyés automatiquement 10 minutes après la soumission de la candidature"
- ✅ Documentation améliorée pour la configuration du cron avec exemples selon le délai

### 2. Soumission de candidatures (`candidature/submit.php`)

#### Avant :
- Candidature refusée → `reponse_automatique = 'refuse'` (déjà traitée)
- N'apparaissait pas dans "Réponses Automatiques Programmées"

#### Après :
- Candidature refusée → `reponse_automatique = 'en_attente'` (programmée)
- ✅ Apparaît dans "Réponses Automatiques Programmées"
- ✅ Email de refus envoyé après le délai configuré

### 3. Paramètres système

#### Nouvelle migration (`migrations/014_update_delay_to_minutes.sql`)
- Change le délai par défaut de **4 jours** à **10 minutes**
- `delai_reponse_valeur = 10`
- `delai_reponse_unite = 'minutes'`

## Comment tester

### Étape 1 : Appliquer la migration

```bash
# Se connecter à la base de données MySQL
mysql -u [utilisateur] -p [base_de_donnees]

# Exécuter la migration
source migrations/014_update_delay_to_minutes.sql;

# Vérifier les paramètres
SELECT * FROM parametres WHERE cle IN ('delai_reponse_valeur', 'delai_reponse_unite');
```

Résultat attendu :
```
+---------------------+--------+
| cle                 | valeur |
+---------------------+--------+
| delai_reponse_valeur| 10     |
| delai_reponse_unite | minutes|
+---------------------+--------+
```

### Étape 2 : Tester avec une candidature refusée

1. **Soumettre une candidature avec revenus insuffisants**
   - Aller sur le formulaire de candidature
   - Remplir avec :
     - Revenus mensuels : "< 2300" ou "2300-3000"
     - Autres champs valides (CDI, Visale Oui, etc.)
   - Soumettre

2. **Vérifier dans l'admin**
   - Aller sur `admin-v2/cron-jobs.php`
   - Vérifier la section "Réponses Automatiques Programmées"
   - **La candidature doit apparaître** avec :
     - ✅ Référence de la candidature
     - ✅ Nom et prénom du candidat
     - ✅ Email
     - ✅ Logement
     - ✅ Date de soumission
     - ✅ Date prévue d'envoi (soumission + 10 minutes)
     - ✅ Statut "refuse"
     - ✅ Badge "Prêt à traiter" si le délai est dépassé

3. **Attendre 10 minutes et exécuter le cron**
   - Cliquer sur "Exécuter maintenant" pour la tâche de traitement des candidatures
   - OU attendre l'exécution automatique du cron
   - L'email de refus doit être envoyé
   - La candidature ne doit plus apparaître dans "Réponses Automatiques Programmées"

### Étape 3 : Vérifier la configuration du cron

1. **Accéder à la page**
   - `admin-v2/cron-jobs.php`

2. **Cliquer sur "comment configurer" dans la section info**
   - Vérifier que la modal affiche :
     - ✅ Exemples pour différentes fréquences (5 minutes, 10 minutes, horaire, quotidien)
     - ✅ Alerte expliquant qu'il faut ajuster la fréquence selon le délai
     - ✅ Pas de mention de "9h00 quotidien" comme configuration unique

3. **Configurer le cron sur le serveur** (exemple pour 10 minutes)
   ```bash
   # Éditer la crontab
   crontab -e
   
   # Ajouter cette ligne (exécution toutes les 5 minutes pour un délai de 10 minutes)
   */5 * * * * /usr/bin/php /chemin/vers/cron/process-candidatures.php
   
   # Ajouter l'email de notification
   MAILTO=votre-email@example.com
   ```

## Captures d'écran attendues

### 1. Section "Réponses Automatiques Programmées" avec candidatures

Devrait afficher un tableau avec :
- En-tête : "Réponses Automatiques Programmées"
- Sous-titre : "Candidatures en attente d'envoi automatique de mail de réponse (après le délai configuré)"
- Alerte info : "Délai configuré: 10 minutes"
- Message : "Les mails seront envoyés automatiquement 10 minutes après la soumission de la candidature."
- Tableau avec colonnes : Référence | Candidat | Email | Logement | Date Soumission | Réponse Prévue | Statut | Action

### 2. Section "Réponses Automatiques Programmées" vide

Devrait afficher :
- Message : "Aucune candidature en attente de réponse automatique."

### 3. Modal de configuration du cron

Devrait afficher :
- Exemples multiples de configuration cron
- Alerte orange expliquant comment ajuster la fréquence
- Pas de mention unique de "9h00 quotidien"

## Vérification du bon fonctionnement

✅ **Liste de contrôle**

1. [ ] La migration 014 a été appliquée avec succès
2. [ ] Les paramètres affichent bien 10 minutes dans Admin > Paramètres
3. [ ] Une candidature refusée apparaît dans "Réponses Automatiques Programmées"
4. [ ] La date prévue d'envoi est correcte (soumission + 10 minutes)
5. [ ] Le badge "Prêt à traiter" apparaît après le délai
6. [ ] Le cron envoie bien l'email après le délai
7. [ ] La candidature disparaît de la liste après envoi
8. [ ] Le message "9h00 quotidien" n'apparaît plus
9. [ ] La documentation du cron montre plusieurs exemples de fréquence

## Rollback (si nécessaire)

Si vous devez revenir en arrière :

```sql
-- Restaurer les anciens paramètres (4 jours)
UPDATE parametres SET valeur = '4' WHERE cle = 'delai_reponse_valeur';
UPDATE parametres SET valeur = 'jours' WHERE cle = 'delai_reponse_unite';
```

## Support

Pour toute question, consulter :
- `SYSTEME_REPONSES_AUTOMATIQUES.md` : Documentation complète du système
- `test-automatic-response-scheduling.php` : Tests automatisés

## Tests automatisés

Pour exécuter les tests :

```bash
php test-automatic-response-scheduling.php
```

Tous les tests doivent passer (4/4 ✓).
