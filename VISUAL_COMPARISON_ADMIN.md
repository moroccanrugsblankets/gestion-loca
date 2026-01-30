# Comparaison Visuelle - Interface Admin (cron-jobs.php)

## AVANT les changements

### Structure de la page:

```
┌─────────────────────────────────────────────────────────────────┐
│ Tâches Automatisées (Cron Jobs)                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 📋 Réponses Automatiques Programmées                        │ │
│ │ Candidatures qui recevront une réponse automatique          │ │
│ ├─────────────────────────────────────────────────────────────┤ │
│ │ Requête: WHERE c.statut = 'en_cours'                        │ │
│ │          AND c.reponse_automatique = 'en_attente'          │ │
│ │                                                             │ │
│ │ ℹ️  Aucune candidature en attente de réponse automatique.   │ │
│ │                                                             │ │
│ │ ⚠️  PROBLÈME: Les candidatures refusées ne sont pas         │ │
│ │    affichées ici car elles ont statut='refuse'             │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ ❌ Candidatures Auto-Refusées Récemment                     │ │
│ │ Candidatures automatiquement refusées lors de la soumission │ │
│ ├─────────────────────────────────────────────────────────────┤ │
│ │ Requête: WHERE c.statut = 'refuse'                          │ │
│ │          AND c.reponse_automatique = 'refuse'              │ │
│ │          AND c.motif_refus IS NOT NULL                     │ │
│ │                                                             │ │
│ │ Table avec colonnes:                                        │ │
│ │ - Référence | Candidat | Email | Logement                  │ │
│ │ - Date Soumission | Motif Refus | Action                   │ │
│ │                                                             │ │
│ │ ⚠️  Note: Ces candidatures ont reçu un email de refus       │ │
│ │    immédiatement lors de la soumission.                    │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ ⚙️ Tâches Planifiées Configurées                            │ │
│ │ [Liste des cron jobs...]                                    │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### Problèmes identifiés:

1. ❌ Les candidatures refusées ne sont **jamais** visibles dans "Réponses Automatiques Programmées"
2. ❌ Le bloc "Candidatures Auto-Refusées Récemment" est **redondant**
3. ❌ Les candidatures sont refusées **immédiatement** à la soumission
4. ❌ Pas de planification d'envoi pour les refus

---

## APRÈS les changements

### Structure de la page:

```
┌─────────────────────────────────────────────────────────────────┐
│ Tâches Automatisées (Cron Jobs)                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 📋 Réponses Automatiques Programmées                        │ │
│ │ Candidatures en attente d'évaluation et d'envoi de réponse  │ │
│ │ automatique (acceptation ou refus)                          │ │
│ ├─────────────────────────────────────────────────────────────┤ │
│ │ Requête: WHERE c.reponse_automatique = 'en_attente'        │ │
│ │                                                             │ │
│ │ ℹ️  Délai configuré: 4 jours                                 │ │
│ │                                                             │ │
│ │ Table avec colonnes:                                        │ │
│ │ ┌────────────────────────────────────────────────────────┐ │ │
│ │ │ Référence | Candidat | Email | Logement                │ │ │
│ │ │ Date Soumission | Réponse Prévue | Statut | Action     │ │ │
│ │ ├────────────────────────────────────────────────────────┤ │ │
│ │ │ CAND-20260130-A1B2 | Jean Dupont | jean@...           │ │ │
│ │ │ contact@myinvest.com | LOG-001                         │ │ │
│ │ │ 28/01/2026 14:30 | 01/02/2026 14:30 | en_cours | 👁️   │ │ │
│ │ ├────────────────────────────────────────────────────────┤ │ │
│ │ │ CAND-20260129-C3D4 | Marie Martin | marie@...         │ │ │
│ │ │ contact@myinvest.com | LOG-002                         │ │ │
│ │ │ 27/01/2026 10:15 | 31/01/2026 10:15 ⚠️ Prêt à traiter │ │ │
│ │ │ en_cours | 👁️                                          │ │ │
│ │ └────────────────────────────────────────────────────────┘ │ │
│ │                                                             │ │
│ │ ⚠️  Note: Le traitement automatique s'exécute quotidiennement│ │
│ │    à 9h00. Les candidatures marquées "Prêt à traiter"      │ │
│ │    seront traitées lors de la prochaine exécution du cron. │ │
│ │                                                             │ │
│ │ ✅ TOUTES les candidatures sont visibles ici                │ │
│ │ ✅ Incluant celles qui seront refusées                      │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ ⚙️ Tâches Planifiées Configurées                            │ │
│ │ [Liste des cron jobs...]                                    │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### Améliorations apportées:

1. ✅ **Un seul bloc** "Réponses Automatiques Programmées"
2. ✅ Affiche **toutes** les candidatures en attente (acceptées ET refusées)
3. ✅ Badge "Prêt à traiter" pour les candidatures dont la date est dépassée
4. ✅ Suppression du bloc redondant "Candidatures Auto-Refusées Récemment"
5. ✅ Description clarifiée: "acceptation ou refus"

---

## Scénario d'Utilisation

### Exemple 1: Candidature qui sera refusée

**Étape 1 - Soumission (28/01/2026 14:30)**
- Candidat: Jean Dupont
- Revenus: 2500€ (< 3000€ requis)
- Statut: Indépendant (≠ CDI/CDD requis)

```
AVANT:
✗ statut = 'refuse' immédiatement
✗ reponse_automatique = 'refuse'
✗ Email envoyé immédiatement
✗ Visible dans "Candidatures Auto-Refusées Récemment"
✗ PAS visible dans "Réponses Automatiques Programmées"

APRÈS:
✓ statut = 'en_cours'
✓ reponse_automatique = 'en_attente'
✓ Visible dans "Réponses Automatiques Programmées"
✓ Date prévue d'envoi: 01/02/2026 14:30 (4 jours)
```

**Étape 2 - Affichage Admin (29/01/2026)**

```
┌─────────────────────────────────────────────────────────────┐
│ 📋 Réponses Automatiques Programmées                       │
├─────────────────────────────────────────────────────────────┤
│ CAND-20260128-A1B2 | Jean Dupont | jean.dupont@email.com  │
│ LOG-001 | 28/01/2026 14:30 | 01/02/2026 14:30           │
│ en_cours | 👁️                                             │
└─────────────────────────────────────────────────────────────┘
```

**Étape 3 - Exécution du Cron (01/02/2026 09:00)**
1. Le cron évalue la candidature
2. Détecte que revenus < 3000€ et statut ≠ CDI/CDD
3. Met à jour:
   - `statut = 'refuse'`
   - `reponse_automatique = 'refuse'`
   - `motif_refus = "Revenus nets mensuels insuffisants (minimum 3000€ requis), Statut professionnel non accepté (doit être CDI ou CDD)"`
4. Envoie l'email de refus avec le template

**Étape 4 - Après le Cron**
- La candidature disparaît de "Réponses Automatiques Programmées"
- Visible dans la liste générale des Candidatures avec statut "Refusé"

---

## Résumé des Changements

| Aspect | AVANT | APRÈS |
|--------|-------|-------|
| **Nombre de blocs** | 2 blocs distincts | 1 seul bloc unifié |
| **Requête** | `statut='en_cours' AND reponse_automatique='en_attente'` | `reponse_automatique='en_attente'` |
| **Candidatures visibles** | Seulement celles qui seront acceptées | Toutes (acceptées + refusées) |
| **Refus immédiat** | Oui, à la soumission | Non, après le délai |
| **Planification** | Seulement pour acceptations | Pour acceptations ET refus |
| **Équité** | Non, traitement différencié | Oui, même délai pour tous |

---

## Impact pour l'Utilisateur Final

### Avant:
❌ Candidat rejeté → Email de refus immédiat (parfois quelques minutes après soumission)
✅ Candidat accepté → Email après 4 jours

**Problème:** Impression de traitement automatisé et impersonnel pour les refus

### Après:
✅ Candidat rejeté → Email de refus après 4 jours
✅ Candidat accepté → Email après 4 jours

**Avantage:** Tous les candidats ont l'impression d'une évaluation humaine et équitable
