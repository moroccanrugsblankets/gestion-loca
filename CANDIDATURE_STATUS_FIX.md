# Correction de l'administration des candidatures

## Problèmes corrigés

### 1. Champ Statut vide après création

**Problème:** Le champ "Statut" restait vide après création d'une candidature, même si elle devait être automatiquement refusée (ex: salaire < 3000€).

**Solution:**
- Ajout de la fonction `evaluateCandidature()` dans `includes/functions.php` pour évaluer les candidatures selon les règles strictes
- Modification de `candidature/submit.php` pour évaluer immédiatement la candidature lors de la création
- Le statut est maintenant défini dès la création:
  - `refuse` si les critères ne sont pas remplis (avec motif de refus)
  - `en_cours` si tous les critères sont remplis

**Règles d'évaluation:**
1. Statut professionnel: doit être CDI ou CDD
2. Revenus mensuels nets: minimum 3000€ requis
3. Type de revenus: doit être "Salaires"
4. Nombre d'occupants: doit être 1 ou 2
5. Garantie Visale: requise (doit être "Oui")
6. Si CDI: la période d'essai doit être dépassée

### 2. Cron - Refus automatique

**Problème:** 
- Le cron principal n'était pas visible dans l'interface d'administration
- Aucune instruction de configuration n'était fournie
- Pas de logs clairs lors de l'exécution

**Solution:**
- Modification de `admin-v2/cron-jobs.php` pour inclure le cron principal de traitement des candidatures
- Amélioration du modal d'instructions avec:
  - Lignes de commande exactes pour chaque cron job actif
  - Exemple de configuration complète avec MAILTO
  - Instructions de vérification des logs
  - Chemin vers le fichier de log cron
- Modification de `cron/process-candidatures.php` pour afficher les messages dans stdout (visible dans les logs cron)

### 3. Affichage des statuts

**Problème:** Les statuts n'étaient pas affichés correctement dans l'interface d'administration car les valeurs de la base de données (enum) ne correspondaient pas aux valeurs d'affichage.

**Solution:**
- Ajout de la fonction `formatStatut()` dans `includes/functions.php` pour convertir les valeurs enum en libellés d'affichage
- Mise à jour de `admin-v2/candidatures.php` pour:
  - Utiliser les bonnes valeurs enum dans les filtres
  - Afficher les statuts formatés avec `formatStatut()`
  - Corriger les classes CSS des badges de statut

## Fichiers modifiés

1. **includes/functions.php**
   - Ajout de `evaluateCandidature()` - évaluation des candidatures selon les règles
   - Ajout de `formatStatut()` - formatage des statuts pour l'affichage

2. **candidature/submit.php**
   - Évaluation immédiate de la candidature lors de la création
   - Définition du statut initial selon les règles
   - Stockage du motif de refus si applicable

3. **cron/process-candidatures.php**
   - Suppression de la fonction `evaluateCandidature()` dupliquée (utilise celle de functions.php)
   - Ajout de l'output stdout pour les logs cron
   - Amélioration des messages de log

4. **admin-v2/cron-jobs.php**
   - Affichage du cron principal de traitement des candidatures
   - Modal d'instructions amélioré avec exemples complets
   - Instructions de vérification des logs

5. **admin-v2/candidatures.php**
   - Correction des valeurs de filtre de statut (enum)
   - Utilisation de `formatStatut()` pour l'affichage
   - Correction des classes CSS des badges

## Tests effectués

Des tests automatisés ont été créés pour valider la logique d'évaluation:

1. **test-candidature-evaluation-standalone.php** - Tests unitaires de la fonction `evaluateCandidature()`
   - ✓ Revenus < 3000€ → REFUSÉ
   - ✓ Revenus >= 3000€ + tous critères OK → ACCEPTÉ
   - ✓ Statut professionnel Indépendant → REFUSÉ
   - ✓ Période d'essai en cours → REFUSÉ
   - ✓ Pas de garantie Visale → REFUSÉ
   - ✓ Revenus < 2300€ → REFUSÉ

Tous les tests passent avec succès (6/6).

## Configuration du Cron

Pour activer le traitement automatique des candidatures sur le serveur:

1. Se connecter au serveur via SSH
2. Éditer le crontab: `crontab -e`
3. Ajouter la ligne suivante:

```bash
# Traitement des candidatures - tous les jours à 9h00
0 9 * * * /usr/bin/php /chemin/vers/projet/cron/process-candidatures.php
```

4. Vérifier que le cron fonctionne:
   - Logs système: `tail -f /var/log/syslog | grep CRON`
   - Fichier de log: `cron/cron-log.txt`
   - Exécution manuelle depuis l'admin avec le bouton "Exécuter maintenant"

## Résultat

Les trois livrables attendus sont maintenant fonctionnels:

✓ Champ Statut correctement alimenté dans `candidatures.php`
✓ Cron fonctionnel avec exécution des règles de refus automatique
✓ Instructions de configuration du cron affichées correctement dans l'admin
✓ Tests validant le comportement pour candidature < 3000 € → statut "Refusé"
