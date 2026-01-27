# Schéma de la Base de Données Unifiée

## 🗄️ Structure de `bail_signature`

Cette base de données unique contient **10 tables** et **2 vues** qui gèrent l'ensemble du cycle de vie des baux locatifs.

## 📊 Diagramme des Relations

```
┌─────────────────┐
│   logements     │ ← Table centrale des biens immobiliers
│   (id, ref)     │
└────────┬────────┘
         │
         │ 1:N (logement_id)
         ↓
┌─────────────────────────┐
│    candidatures         │ ← Candidatures locatives
│  (id, reference_unique) │
└─────┬──────────┬────────┘
      │          │
      │ 1:N      │ 1:1
      ↓          ↓
┌──────────────┐  ┌────────────────┐
│ candidature_ │  │   contrats     │ ← Contrats de bail
│  documents   │  │ (id, ref_uniq) │
└──────────────┘  └────┬───────────┘
                       │
         ┌─────────────┼─────────────┐
         │             │             │
         │ 1:N         │ 1:N         │ 1:N
         ↓             ↓             ↓
   ┌───────────┐  ┌──────────┐  ┌──────────┐
   │locataires │  │etats_lieux│  │paiements │
   └───────────┘  └─────┬────┘  └──────────┘
                        │
                        │ 1:N
                        ↓
                  ┌──────────────┐
                  │ degradations │
                  └──────────────┘

┌──────────────┐       ┌──────┐
│administrateurs│       │ logs │ ← Traçabilité (toutes entités)
└──────────────┘       └──────┘
```

## 📋 Tables Détaillées

### 1. **logements**
Gestion des biens immobiliers disponibles à la location.

**Clés:**
- `id` (PRIMARY KEY)
- `reference` (UNIQUE)

**Relations:**
- 1:N → candidatures (via logement_id)
- 1:N → contrats (via logement_id)

**Champs importants:**
- adresse, type, surface
- loyer, charges, depot_garantie
- statut (disponible, en_location, maintenance, indisponible)
- parking, date_disponibilite

---

### 2. **candidatures**
Gestion du workflow de candidature et de sélection des locataires.

**Clés:**
- `id` (PRIMARY KEY)
- `reference_unique` (UNIQUE)
- `logement_id` (FOREIGN KEY → logements.id)

**Relations:**
- N:1 ← logements
- 1:N → candidature_documents
- 1:1 → contrats (via candidature_id)

**Champs importants:**
- Informations personnelles (nom, prenom, email, telephone)
- Situation professionnelle (statut_professionnel, periode_essai)
- Revenus (revenus_mensuels, type_revenus)
- Statut du workflow (en_cours, refuse, accepte, visite_planifiee, contrat_envoye, contrat_signe)
- Réponse automatique (reponse_automatique, date_reponse_auto)

---

### 3. **candidature_documents**
Documents uploadés par les candidats (pièce d'identité, justificatifs).

**Clés:**
- `id` (PRIMARY KEY)
- `candidature_id` (FOREIGN KEY → candidatures.id, ON DELETE CASCADE)

**Relations:**
- N:1 ← candidatures

**Champs importants:**
- type_document (piece_identite, justificatif_revenus, justificatif_domicile, autre)
- nom_fichier, chemin_fichier, taille_fichier, mime_type

---

### 4. **contrats**
Contrats de bail avec lien vers les candidatures et logements.

**Clés:**
- `id` (PRIMARY KEY)
- `reference_unique` (UNIQUE)
- `candidature_id` (FOREIGN KEY → candidatures.id, ON DELETE SET NULL)
- `logement_id` (FOREIGN KEY → logements.id, ON DELETE CASCADE)
- `token_signature` (UNIQUE)

**Relations:**
- N:1 ← candidatures
- N:1 ← logements
- 1:N → locataires
- 1:N → etats_lieux
- 1:N → paiements

**Champs importants:**
- Dates (date_creation, date_prise_effet, date_fin_prevue, date_signature)
- Statut (en_attente, signe, expire, annule, actif, termine)
- Financier (depot_recu, montant_depot, date_reception_depot)
- nb_locataires

---

### 5. **locataires**
Informations et signatures des locataires pour chaque contrat.

**Clés:**
- `id` (PRIMARY KEY)
- `contrat_id` (FOREIGN KEY → contrats.id, ON DELETE CASCADE)

**Relations:**
- N:1 ← contrats

**Champs importants:**
- Informations personnelles (nom, prenom, date_naissance, email, telephone)
- Signature électronique (signature_data, signature_ip, signature_timestamp)
- Documents (piece_identite_recto, piece_identite_verso)
- ordre (pour multi-locataires)

---

### 6. **etats_lieux**
États des lieux d'entrée et de sortie pour chaque contrat.

**Clés:**
- `id` (PRIMARY KEY)
- `contrat_id` (FOREIGN KEY → contrats.id, ON DELETE CASCADE)

**Relations:**
- N:1 ← contrats
- 1:N → degradations

**Champs importants:**
- type (entree, sortie)
- date_etat, locataire_present, bailleur_representant
- etat_general, observations
- details_pieces (JSON), photos (JSON)
- Signatures (signature_locataire, signature_bailleur, date_signature)

---

### 7. **degradations**
Dégradations identifiées lors des états des lieux avec calcul de vétusté.

**Clés:**
- `id` (PRIMARY KEY)
- `etat_lieux_id` (FOREIGN KEY → etats_lieux.id, ON DELETE CASCADE)
- `contrat_id` (FOREIGN KEY → contrats.id, ON DELETE CASCADE)

**Relations:**
- N:1 ← etats_lieux
- N:1 ← contrats

**Champs importants:**
- Description (piece, element, description)
- Coûts (cout_reparation, taux_vetuste, cout_final)
- photos (JSON)
- statut (identifie, evalue, facture, paye)

---

### 8. **paiements**
Gestion financière: loyers, dépôts de garantie, remboursements.

**Clés:**
- `id` (PRIMARY KEY)
- `contrat_id` (FOREIGN KEY → contrats.id, ON DELETE CASCADE)

**Relations:**
- N:1 ← contrats

**Champs importants:**
- type (depot_garantie, loyer, charges, remboursement_depot, reparation, autre)
- montant, date_paiement, mode_paiement, reference_paiement
- statut (attendu, recu, rembourse)

---

### 9. **logs**
Traçabilité de toutes les actions sur toutes les entités du système.

**Clés:**
- `id` (PRIMARY KEY)

**Champs importants:**
- type_entite (candidature, contrat, logement, paiement, etat_lieux, autre)
- entite_id (ID de l'entité concernée)
- action, details, ip_address, user_agent
- created_at

---

### 10. **administrateurs**
Comptes administrateurs avec gestion des rôles.

**Clés:**
- `id` (PRIMARY KEY)
- `username` (UNIQUE)

**Champs importants:**
- username, password_hash, email
- nom, prenom
- role (admin, gestionnaire, comptable)
- actif, derniere_connexion

---

## 🔍 Vues SQL

### Vue: **candidatures_a_traiter**
Liste des candidatures en attente de traitement automatique après 4 jours ouvrés.

**Contient:**
- Toutes les informations de candidature
- Référence et adresse du logement
- Nombre de jours depuis soumission
- Indicateur de validation des critères

**Utilisation:** Processus automatique (cron) de sélection.

---

### Vue: **dashboard_stats**
Statistiques pour le tableau de bord administrateur.

**Contient:**
- Nombre de candidatures (en_cours, acceptees, refusees)
- Nombre de contrats actifs
- Nombre de logements disponibles
- Nombre de candidatures de la semaine

**Utilisation:** Affichage du dashboard admin.

---

## 🔗 Flux de Données Complet

```
1. CANDIDATURE
   └─> Table: candidatures (statut: en_cours)
       └─> Table: candidature_documents (upload docs)

2. TRAITEMENT AUTOMATIQUE (4 jours ouvrés)
   └─> Vue: candidatures_a_traiter
       └─> Update: candidatures (statut: accepte/refuse)

3. GÉNÉRATION CONTRAT
   └─> Table: contrats (candidature_id lié)
       └─> Update: candidatures (statut: contrat_envoye)
       └─> Update: logements (statut: en_location)

4. SIGNATURE
   └─> Table: locataires (signatures)
       └─> Update: contrats (statut: signe → actif)

5. ÉTAT DES LIEUX ENTRÉE
   └─> Table: etats_lieux (type: entree)
       └─> Table: paiements (depot_garantie)

6. GESTION LOCATION
   └─> Table: paiements (loyer mensuel)
       └─> Table: logs (traçabilité)

7. ÉTAT DES LIEUX SORTIE
   └─> Table: etats_lieux (type: sortie)
       └─> Table: degradations (si nécessaire)
       └─> Table: paiements (remboursement_depot)

8. CLÔTURE
   └─> Update: contrats (statut: termine)
       └─> Update: logements (statut: disponible)
```

---

## 📈 Intégrité Référentielle

Toutes les tables sont reliées par des **clés étrangères** garantissant:

1. **Cohérence des données**
   - Impossible de créer un contrat sans candidature ou logement valide
   - Impossible d'avoir un locataire sans contrat

2. **Cascade de suppression**
   - Supprimer une candidature → supprime les documents associés
   - Supprimer un contrat → supprime locataires, états des lieux, paiements

3. **Gestion des NULL**
   - Supprimer une candidature → contrat.candidature_id devient NULL (historique préservé)
   - Supprimer un logement → candidature.logement_id devient NULL

4. **Traçabilité complète**
   - Table `logs` enregistre toutes les actions
   - Aucune suppression de données historiques importantes

---

## 🎯 Avantages de Cette Architecture

✅ **Une seule source de vérité** - Toutes les données dans une base  
✅ **Relations fortes** - Clés étrangères assurent l'intégrité  
✅ **Workflow complet** - De la candidature à la fin du bail  
✅ **Traçabilité** - Logs sur toutes les entités  
✅ **Performance** - Pas de jointures entre bases  
✅ **Maintenance** - Un seul schéma à gérer  
✅ **Sauvegarde** - Une seule base à sauvegarder  

---

**Base de données:** `bail_signature`  
**Tables:** 10  
**Vues:** 2  
**Version:** 2.0 - Unifiée
