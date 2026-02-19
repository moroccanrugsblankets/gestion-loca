# Plan de Test - Corrections Gestion des Loyers

**Date:** 19 février 2026  
**PR:** copilot/fix-logement-status-filter  
**Fichier modifié:** `admin-v2/gestion-loyers.php`

## Contexte

Deux correctifs ont été appliqués au module de gestion des loyers:
1. **Suppression du filtre de statut du logement** - Pour afficher tous les logements avec contrats actifs
2. **Statut par défaut intelligent** - Pour marquer les mois passés comme impayés au lieu de en attente

## Prérequis

- Serveur web avec PHP 7.4+ installé
- Base de données MySQL avec le schéma à jour
- Au moins 2 logements avec contrats valides dans la base
- Accès à l'interface admin (`admin-v2/gestion-loyers.php`)

## Données de Test Recommandées

### Logement 1: RP-01
- **Statut logement:** `en_location`
- **Contrat:** Valide, date_prise_effet dans le passé
- **Locataire:** Jean Dupont

### Logement 2: RP-05
- **Statut logement:** `disponible` (important!)
- **Contrat:** Valide, date_prise_effet dans le passé
- **Locataire:** Marie Martin
- **Scénario:** Le locataire a donné son préavis, le logement est déjà proposé à la location, mais le contrat est toujours actif

### Données de Paiement
Pour chaque logement, créer des enregistrements dans `loyers_tracking`:
- **Janvier 2026:** Aucun enregistrement (pour tester le statut par défaut)
- **Février 2026 (mois courant):** Aucun enregistrement (devrait être "attente")
- **Décembre 2025:** Optionnel - Enregistrement avec `statut_paiement = 'paye'`

## Tests à Effectuer

### Test 1: Affichage de Tous les Logements ✅

**Objectif:** Vérifier que tous les logements avec contrats actifs sont affichés, peu importe leur statut.

#### Étapes:
1. Accéder à `admin-v2/gestion-loyers.php` (vue globale)
2. Observer la section "État des Logements"

#### Résultat Attendu:
- ✅ RP-01 (statut: en_location) est affiché
- ✅ RP-05 (statut: disponible) est affiché
- ✅ Les deux logements apparaissent dans la grille colorée
- ✅ Les deux logements apparaissent dans le tableau des loyers

#### Résultat Avant le Fix:
- ❌ Seulement RP-01 était affiché
- ❌ RP-05 n'apparaissait pas car son statut était "disponible"

#### Commande SQL pour Vérifier:
```sql
-- Cette requête devrait retourner TOUS les logements avec contrats actifs
SELECT l.reference, l.statut as statut_logement, c.statut as statut_contrat, c.date_prise_effet
FROM logements l
INNER JOIN contrats c ON c.logement_id = l.id
INNER JOIN (
    SELECT logement_id, MAX(date_prise_effet) AS max_date
    FROM contrats c 
    WHERE c.statut = 'valide' 
      AND c.date_prise_effet IS NOT NULL 
      AND c.date_prise_effet <= CURDATE()
    GROUP BY logement_id
) derniers_contrats 
    ON c.logement_id = derniers_contrats.logement_id
   AND c.date_prise_effet = derniers_contrats.max_date
ORDER BY l.reference;
```

---

### Test 2: Statut par Défaut des Mois Passés ✅

**Objectif:** Vérifier que les mois passés sans enregistrement sont marqués comme "impayé" (rouge).

#### Étapes:
1. Accéder à `admin-v2/gestion-loyers.php`
2. Observer la grille des mois pour RP-01 et RP-05
3. Vérifier la couleur des mois précédents (ex: Janvier 2026)

#### Résultat Attendu:
- ✅ Janvier 2026 (mois passé, aucun enregistrement): **ROUGE** ✗ "Impayé"
- ✅ Février 2026 (mois courant, aucun enregistrement): **ORANGE** ⏳ "Attente"
- ✅ Décembre 2025 (avec enregistrement 'paye'): **VERT** ✓ "Payé"

#### Résultat Avant le Fix:
- ❌ Janvier 2026: ORANGE ⏳ "Attente" (incorrect!)
- ❌ Février 2026: ORANGE ⏳ "Attente" (correct, mais pas différencié)

#### Points de Vérification:
```
Mois          | Enregistrement | Statut Attendu | Couleur
--------------|----------------|----------------|----------
Déc 2025      | Oui (paye)     | Payé           | 🟢 Vert
Jan 2026      | Non            | Impayé         | 🔴 Rouge
Fév 2026      | Non            | Attente        | 🟠 Orange
```

---

### Test 3: Statistiques Globales ✅

**Objectif:** Vérifier que les statistiques (nb payés, impayés, attente) sont correctes.

#### Étapes:
1. Observer les cartes de statistiques en haut de la page
2. Compter manuellement:
   - Nombre de loyers payés (tous les mois avec statut 'paye')
   - Nombre de loyers impayés (mois passés sans enregistrement + enregistrements 'impaye')
   - Nombre de loyers en attente (mois courant sans enregistrement + enregistrements 'attente')

#### Résultat Attendu:
- ✅ Les chiffres correspondent au comptage manuel
- ✅ Les mois passés sans enregistrement sont comptés comme impayés
- ✅ Le mois courant sans enregistrement est compté comme attente

#### Exemple avec 2 logements:
```
Si nous avons 3 mois affichés (Déc, Jan, Fév) × 2 logements = 6 cellules:
- Déc 2025: 2 payés (enregistrements explicites)
- Jan 2026: 2 impayés (aucun enregistrement, mois passé)
- Fév 2026: 2 attente (aucun enregistrement, mois courant)

Résultat: 2 payés, 2 impayés, 2 attente
```

---

### Test 4: Sélecteur de Contrats ✅

**Objectif:** Vérifier que le sélecteur affiche tous les contrats actifs et permet de filtrer correctement.

#### Étapes:
1. Observer la liste déroulante "Filtrer par logement"
2. Sélectionner RP-01
3. Sélectionner RP-05
4. Cliquer sur "Réinitialiser" pour revenir à la vue globale

#### Résultat Attendu:
- ✅ Les deux logements (RP-01 et RP-05) apparaissent dans la liste déroulante
- ✅ Sélectionner RP-01 affiche uniquement RP-01 (vue détaillée avec flexbox)
- ✅ Sélectionner RP-05 affiche uniquement RP-05
- ✅ Le bouton "Réinitialiser" revient à la vue globale (tous les logements)

---

### Test 5: Changement de Statut Manuel ✅

**Objectif:** Vérifier que le changement de statut fonctionne toujours correctement.

#### Étapes:
1. Cliquer sur une cellule de mois avec statut "impaye" (rouge)
2. Le modal de changement de statut devrait s'ouvrir
3. Changer le statut à "paye"
4. Sauvegarder

#### Résultat Attendu:
- ✅ La cellule devient verte (payé)
- ✅ Un enregistrement est créé dans `loyers_tracking`
- ✅ Les statistiques sont mises à jour
- ✅ Le changement persiste après rafraîchissement de la page

---

### Test 6: Vue Détaillée (Flexbox) ✅

**Objectif:** Vérifier que la vue détaillée fonctionne correctement avec les nouveaux statuts.

#### Étapes:
1. Sélectionner un logement dans le filtre (ex: RP-05)
2. Observer l'affichage en flexbox (blocs de mois côte à côte)

#### Résultat Attendu:
- ✅ Les mois s'affichent en blocs colorés
- ✅ Les mois passés sans enregistrement sont rouges
- ✅ Le mois courant sans enregistrement est orange
- ✅ L'indicateur "Mois en cours" est visible sur le mois actuel

---

### Test 7: Cohérence Vue Globale / Vue Détaillée ✅

**Objectif:** Vérifier la cohérence des statuts entre les deux vues.

#### Étapes:
1. Noter les statuts de tous les mois dans la vue globale (tableau)
2. Filtrer sur un logement spécifique
3. Comparer les statuts dans la vue détaillée (flexbox)

#### Résultat Attendu:
- ✅ Les statuts sont identiques dans les deux vues
- ✅ Les couleurs sont cohérentes
- ✅ Les montants affichés sont les mêmes

---

## Tests de Régression

### Test R1: Fonction `updatePreviousMonthsToImpaye()` ✅

**Objectif:** Vérifier que la fonction existante continue de fonctionner.

#### Étapes:
1. Créer manuellement un enregistrement dans `loyers_tracking` pour un mois passé avec `statut_paiement = 'attente'`
2. Rafraîchir la page `gestion-loyers.php`
3. Vérifier que le statut a été mis à jour automatiquement

#### SQL pour Créer un Test:
```sql
INSERT INTO loyers_tracking (logement_id, mois, annee, statut_paiement, montant_attendu)
VALUES (1, 12, 2025, 'attente', 1000.00);
```

#### Résultat Attendu:
- ✅ Après le chargement de la page, le statut est automatiquement changé à 'impaye'
- ✅ La cellule apparaît en rouge dans l'interface

### Test R2: Envoi de Rappels ✅

**Objectif:** Vérifier que l'envoi de rappels fonctionne toujours.

#### Étapes:
1. Identifier un loyer impayé (rouge)
2. Cliquer sur le bouton d'envoi de rappel (icône enveloppe)
3. Vérifier le message de confirmation

#### Résultat Attendu:
- ✅ Le bouton d'envoi de rappel est visible sur les loyers impayés
- ✅ Le clic déclenche l'envoi (ou affiche un message approprié)

---

## Tests de Performance

### Test P1: Temps de Chargement ✅

**Objectif:** Vérifier que les modifications n'ont pas dégradé les performances.

#### Étapes:
1. Mesurer le temps de chargement de la page avec les outils de développement du navigateur
2. Comparer avec les temps de chargement antérieurs (si disponibles)

#### Résultat Attendu:
- ✅ Temps de chargement similaire ou amélioré
- ✅ Pas de requêtes SQL lentes dans les logs

#### Note:
La requête simplifiée (sans `WHERE l.statut = 'en_location'`) devrait être légèrement plus rapide.

---

## Checklist de Validation Complète

- [ ] **Test 1:** Tous les logements avec contrats actifs sont affichés
- [ ] **Test 2:** Mois passés affichés en rouge (impayé)
- [ ] **Test 3:** Mois courant affiché en orange (attente)
- [ ] **Test 4:** Statistiques correctes
- [ ] **Test 5:** Sélecteur de contrats fonctionne
- [ ] **Test 6:** Changement de statut manuel fonctionne
- [ ] **Test 7:** Vue détaillée (flexbox) correcte
- [ ] **Test 8:** Cohérence entre vues globale et détaillée
- [ ] **Test R1:** `updatePreviousMonthsToImpaye()` fonctionne
- [ ] **Test R2:** Envoi de rappels fonctionne
- [ ] **Test P1:** Performance acceptable

---

## Résultats des Tests

### Environnement de Test
- **Date:** _____________
- **Testeur:** _____________
- **Navigateur:** _____________
- **Version PHP:** _____________
- **Version MySQL:** _____________

### Notes et Observations
```
[Espace pour noter les observations pendant les tests]




```

### Problèmes Identifiés
```
[Liste des problèmes découverts, s'il y en a]




```

### Conclusion
- [ ] ✅ Tous les tests passent - Prêt pour la production
- [ ] ⚠️ Tests partiellement réussis - Corrections mineures nécessaires
- [ ] ❌ Tests échoués - Corrections majeures requises

---

## Commandes Utiles pour le Débogage

### Vérifier les Contrats Actifs
```sql
SELECT l.reference, l.statut as statut_logement, 
       c.id, c.statut as statut_contrat, c.date_prise_effet
FROM logements l
INNER JOIN contrats c ON c.logement_id = l.id
WHERE c.statut = 'valide' 
  AND c.date_prise_effet IS NOT NULL 
  AND c.date_prise_effet <= CURDATE()
ORDER BY l.reference;
```

### Vérifier les Statuts de Paiement
```sql
SELECT l.reference, lt.mois, lt.annee, lt.statut_paiement
FROM loyers_tracking lt
INNER JOIN logements l ON lt.logement_id = l.id
ORDER BY l.reference, lt.annee DESC, lt.mois DESC;
```

### Compter les Statuts
```sql
SELECT statut_paiement, COUNT(*) as nombre
FROM loyers_tracking
WHERE annee = YEAR(CURDATE()) 
  AND mois >= MONTH(CURDATE()) - 2
GROUP BY statut_paiement;
```

### Activer les Logs PHP (en cas de problème)
Ajouter temporairement en haut de `gestion-loyers.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

**Document créé le:** 19 février 2026  
**Dernière mise à jour:** 19 février 2026  
**Version:** 1.0
