# Corrections Admin-v2 - Février 2026

## Résumé des corrections

Ce document décrit les corrections apportées pour résoudre trois problèmes identifiés dans l'interface d'administration.

## Problème 1: Erreur header.php dans edit-quittance.php

### Symptômes
```
Warning: include(header.php): failed to open stream: No such file or directory
in /admin-v2/edit-quittance.php on line 95
```

### Cause
Le fichier `admin-v2/edit-quittance.php` utilisait `include 'header.php'` alors que ce fichier n'existe pas. Les autres pages de l'admin utilisent `includes/menu.php`.

### Solution
**Fichier modifié:** `admin-v2/edit-quittance.php`

- Ajout de `<?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>` dans le `<head>`
- Remplacement de `<?php include 'header.php'; ?>` par `<?php require_once __DIR__ . '/includes/menu.php'; ?>`
- Ajout de la div wrapper `<div class="main-content">` pour la cohérence avec les autres pages
- Fermeture appropriée de la div `</div><!-- end main-content -->`

### Résultat
La page s'affiche maintenant correctement avec le menu latéral et le style cohérent avec le reste de l'interface admin.

---

## Problème 2: Erreur SQL dans resend-quittance-email.php

### Symptômes
```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 
Unknown column 'admin_id' in 'field list' 
in /admin-v2/resend-quittance-email.php:106
```

### Cause
Le code tentait d'insérer un log avec la colonne `admin_id` qui n'existe pas dans la table `logs`. Le schéma correct de la table logs est:
- `type_entite` (ENUM: 'candidature', 'contrat', 'logement', 'paiement', 'etat_lieux', 'autre')
- `entite_id` (INT)
- `action` (VARCHAR)
- `details` (TEXT)
- `created_at` (TIMESTAMP)

### Solution
**Fichier modifié:** `admin-v2/resend-quittance-email.php`

Remplacement de l'ancienne requête:
```php
INSERT INTO logs (admin_id, action, details, date_action)
VALUES (?, 'renvoi_quittance', ?, NOW())
```

Par la nouvelle requête conforme au schéma:
```php
INSERT INTO logs (type_entite, entite_id, action, details, created_at)
VALUES (?, ?, ?, ?, NOW())
```

Avec les valeurs:
- `type_entite`: 'autre'
- `entite_id`: ID de la quittance
- `action`: 'renvoi_quittance'
- `details`: Description complète de l'action

### Résultat
Le renvoi d'email de quittance fonctionne maintenant sans erreur et enregistre correctement l'action dans les logs.

---

## Problème 3: Système de rappel de paiement aux locataires

### Besoin
Implémenter un système d'envoi automatique d'emails de rappel aux locataires pour les loyers impayés, avec un template spécifique.

### Solution

#### 1. Nouveau template email
**Fichier créé:** `migrations/058_add_rappel_loyer_locataire_template.sql`

Création d'un nouveau template email avec:
- **Identifiant:** `rappel_loyer_impaye_locataire`
- **Sujet:** "My Invest Immobilier - Rappel loyer non réceptionné"
- **Contenu:** Conforme au texte fourni dans les spécifications
- **Variables disponibles:**
  - `{{locataire_nom}}`
  - `{{locataire_prenom}}`
  - `{{periode}}` (ex: "Janvier 2026")
  - `{{adresse}}` (adresse du logement)
  - `{{montant_total}}` (loyer + charges)
  - `{{signature}}` (signature email de l'agence)

#### 2. Fonctionnalité d'envoi aux locataires
**Fichier modifié:** `cron/rappel-loyers.php`

Ajout d'une nouvelle fonction `envoyerRappelLocataires()` qui:
1. Récupère tous les logements avec loyers impayés ou en attente
2. Pour chaque logement, récupère les locataires du contrat actif
3. Envoie un email personnalisé à chaque locataire avec le template
4. Log les succès et échecs d'envoi

#### 3. Intégration au workflow existant
Le cron job de rappel de loyers maintenant:
1. Envoie un email récapitulatif aux administrateurs (comportement existant)
2. **NOUVEAU:** Si des impayés sont détectés, envoie aussi un email de rappel à chaque locataire concerné

### Configuration
Le système de rappel est configurable via l'interface admin:
- **URL:** `/admin-v2/configuration-rappels-loyers.php`
- Jours d'envoi configurables (défaut: 7, 9, 15 du mois)
- Destinataires administrateurs
- Heure d'exécution du cron
- Activation/désactivation du module

### Déploiement
Pour activer le nouveau template:
```bash
php run-migrations.php
```

Ou exécuter manuellement:
```bash
mysql -u user -p database < migrations/058_add_rappel_loyer_locataire_template.sql
```

### Exemple d'email locataire
```
Objet: My Invest Immobilier - Rappel loyer non réceptionné

Bonjour [Prénom] [Nom],

Sauf erreur de notre part, nous n'avons pas encore réceptionné le règlement 
du loyer relatif à la période en cours.

Période concernée : Janvier 2026
Montant attendu : 850,00 €
Logement : 123 Rue de la Paix, 75001 Paris

Il peut bien entendu s'agir d'un simple oubli ou d'un décalage bancaire. 
Nous vous serions reconnaissants de bien vouloir vérifier la situation et, 
le cas échéant, procéder au règlement dans les meilleurs délais.

Si le paiement a déjà été effectué, nous vous remercions de nous fournir 
la preuve de règlement.

Nous restons naturellement à votre disposition pour toute question.

[Signature de l'agence]
```

---

## Tests effectués

✅ Vérification de la syntaxe PHP de tous les fichiers modifiés
✅ Validation de la structure SQL de la migration
✅ Vérification de la cohérence avec le schéma de base de données existant
✅ Validation du respect des conventions de code du projet

## Fichiers modifiés

1. `admin-v2/edit-quittance.php` - Correction de l'inclusion du header
2. `admin-v2/resend-quittance-email.php` - Correction de la requête SQL logs
3. `cron/rappel-loyers.php` - Ajout de l'envoi aux locataires
4. `migrations/058_add_rappel_loyer_locataire_template.sql` - Nouveau template email

## Impacts

- ✅ Aucun impact sur les fonctionnalités existantes
- ✅ Correction de bugs bloquants
- ✅ Ajout de fonctionnalité demandée (rappel locataires)
- ✅ Cohérence améliorée de l'interface admin
- ✅ Logs correctement enregistrés

## Notes importantes

1. **Migration requise:** La migration 058 doit être exécutée pour créer le nouveau template email
2. **Signature email:** Assurez-vous que le paramètre `email_signature` est configuré dans la table `parametres`
3. **Emails locataires:** Les locataires doivent avoir une adresse email valide dans la base de données
4. **Cron job:** Le cron doit être configuré pour s'exécuter quotidiennement (recommandé: 9h00)

## Date de déploiement
2026-02-17
