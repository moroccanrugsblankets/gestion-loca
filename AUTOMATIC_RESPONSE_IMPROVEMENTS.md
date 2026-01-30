# Améliorations du Système de Réponses Automatiques

## Changements Effectués

### 1. Modification de `candidature/submit.php`

**Avant:**
- Les candidatures ne répondant pas aux critères étaient immédiatement marquées comme `statut='refuse'` et `reponse_automatique='refuse'`
- Les candidatures répondant aux critères étaient marquées comme `statut='en_cours'` et `reponse_automatique='en_attente'`

**Après:**
- **Toutes les candidatures** sont maintenant marquées comme `statut='en_cours'` et `reponse_automatique='en_attente'`
- L'évaluation des critères n'est plus effectuée lors de la soumission
- Le champ `motif_refus` n'est plus renseigné lors de la soumission (sera renseigné par le cron job)
- Le cron job évaluera les candidatures après le délai configuré et enverra l'email approprié

**Impact:**
- Plus de refus immédiat à la soumission
- Tous les candidats reçoivent leur réponse (acceptation ou refus) selon le délai configuré dans les Paramètres

### 2. Modification de `admin-v2/cron-jobs.php`

**Changements:**

a) **Requête "Réponses Automatiques Programmées" mise à jour:**
   - **Avant:** `WHERE c.statut = 'en_cours' AND c.reponse_automatique = 'en_attente'`
   - **Après:** `WHERE c.reponse_automatique = 'en_attente'`
   - Affiche maintenant **toutes** les candidatures en attente de réponse automatique

b) **Suppression du bloc "Candidatures Auto-Refusées Récemment":**
   - Ce bloc a été complètement supprimé (86 lignes)
   - Plus nécessaire car toutes les candidatures passent par le système de réponses programmées

c) **Description mise à jour:**
   - Nouveau texte: "Candidatures en attente d'évaluation et d'envoi de réponse automatique (acceptation ou refus)"
   - Clarifie que le bloc contient toutes les candidatures attendant leur évaluation

### 3. Modification de `cron/process-candidatures.php`

**Changements:**

a) **Logique de requête simplifiée:**
   - Suppression de la dépendance à la vue `v_candidatures_a_traiter` (qui n'existait pas)
   - Requête unique pour tous les types de délai (jours, heures, minutes)
   - **Nouvelle requête:** `WHERE c.reponse_automatique = 'en_attente' AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) >= ?`

b) **Calcul du délai unifié:**
   - Jours → heures (× 24)
   - Heures → heures (× 1)
   - Minutes → heures (÷ 60)
   - Utilise `TIMESTAMPDIFF(HOUR, ...)` pour tous les cas

**Impact:**
- Code plus simple et plus robuste
- Fonctionne pour tous les types de délai configurés
- Plus de dépendance à des vues SQL inexistantes

### 4. Mise à jour de `test-auto-refused-display.php`

**Changements:**
- Titre et description mis à jour pour refléter le nouveau système
- Tests adaptés pour vérifier `reponse_automatique='en_attente'` sans filtre de statut
- Suppression des tests sur le bloc "Candidatures Auto-Refusées Récemment"
- Ajout d'un test pour compter les candidatures déjà traitées

## Fonctionnement du Nouveau Système

### Lors de la Soumission d'une Candidature

1. La candidature est enregistrée avec:
   - `statut = 'en_cours'`
   - `reponse_automatique = 'en_attente'`
   - `motif_refus = NULL`

2. Les documents sont téléchargés et enregistrés

3. Un email de confirmation est envoyé au candidat

4. Une notification est envoyée aux administrateurs

### Dans l'Interface Admin (cron-jobs.php)

Le bloc **"Réponses Automatiques Programmées"** affiche:
- Toutes les candidatures avec `reponse_automatique = 'en_attente'`
- La date de soumission
- La date prévue d'envoi (calculée selon le délai configuré)
- Badge "Prêt à traiter" si la date prévue est dépassée

### Lors de l'Exécution du Cron Job

1. Le cron récupère toutes les candidatures avec:
   - `reponse_automatique = 'en_attente'`
   - Délai écoulé depuis `created_at`

2. Pour chaque candidature:
   - Évaluation selon les critères (revenus, statut pro, etc.)
   - Si acceptée:
     - `statut = 'accepte'`
     - `reponse_automatique = 'accepte'`
     - Email d'acceptation envoyé
   - Si refusée:
     - `statut = 'refuse'`
     - `reponse_automatique = 'refuse'`
     - `motif_refus` renseigné avec les raisons
     - Email de refus envoyé

## Avantages du Nouveau Système

1. **Équité:** Tous les candidats reçoivent leur réponse après le même délai
2. **Transparence:** Plus de traitement différencié entre candidatures
3. **Simplicité:** Un seul flux de traitement pour toutes les candidatures
4. **Configurabilité:** Le délai est configurable dans les Paramètres (minutes/heures/jours)
5. **Visibilité:** L'admin peut voir toutes les candidatures en attente au même endroit

## Tests à Effectuer

1. **Soumettre une candidature qui ne répond pas aux critères:**
   - Revenus < 3000€
   - Statut professionnel ≠ CDI/CDD
   - Etc.

2. **Vérifier dans l'admin (cron-jobs.php):**
   - La candidature apparaît dans "Réponses Automatiques Programmées"
   - Le statut est "en_cours"
   - La date prévue d'envoi est affichée

3. **Exécuter le cron manuellement:**
   - Bouton "Exécuter maintenant" dans l'admin
   - Ou: `php cron/process-candidatures.php`

4. **Vérifier après le cron:**
   - La candidature a disparu de "Réponses Automatiques Programmées"
   - Le statut est passé à "refuse"
   - Un email de refus a été envoyé
   - Le `motif_refus` est renseigné

## Configuration

Le délai de réponse automatique se configure dans **Paramètres**:
- `delai_reponse_valeur`: nombre (ex: 2, 4, 48)
- `delai_reponse_unite`: "minutes", "heures", ou "jours"

Exemple:
- 2 jours = 48 heures
- 4 jours = 96 heures (valeur par défaut)
- 30 minutes = 0.5 heures
