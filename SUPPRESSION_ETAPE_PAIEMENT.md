# Suppression de l'étape de justificatif de paiement du workflow de signature

## Contexte

### Problème Initial
- Lorsqu'un contrat était signé, le processus comportait une étape supplémentaire (Step 3/4) demandant au client de fournir un justificatif de virement
- Cette étape était intégrée dans le flux de signature et bloquait la progression
- Le justificatif était ensuite traité manuellement par l'équipe
- Cela ajoutait de la complexité et ralentissait le processus de finalisation

### Objectif
- Supprimer l'étape de demande de justificatif de virement du flux de signature
- Déclencher directement un envoi d'e-mail automatique après la signature du contrat
- Simplifier le parcours utilisateur tout en maintenant la demande de justificatif

## Solution Implémentée

### 1. Simplification du Workflow de Signature

**Avant (4 étapes):**
1. Step 1/4 - Informations du locataire (25%)
2. Step 2/4 - Signature électronique (50%)
3. **Step 3/4 - Versement du dépôt de garantie (75%)** ← SUPPRIMÉ
4. Step 4/4 - Vérification d'identité (100%)

**Après (3 étapes):**
1. Step 1/3 - Informations du locataire (33%)
2. Step 2/3 - Signature électronique (66%)
3. Step 3/3 - Vérification d'identité (100%)

### 2. Nouveau Template Email

Un nouveau template d'email a été créé : **`demande_justificatif_paiement`**

**Contenu du template:**
- Confirmation que le contrat a été signé avec succès
- Référence du contrat
- Demande explicite du justificatif de virement
- Rappel des coordonnées bancaires (IBAN, BIC, montant)
- Instructions claires sur comment transmettre le justificatif (email, téléphone)
- Mention que la prise d'effet du bail et la remise des clés sont conditionnées à la réception du justificatif

**Variables disponibles:**
- `{{nom}}` - Nom du locataire
- `{{prenom}}` - Prénom du locataire
- `{{reference}}` - Référence du contrat
- `{{depot_garantie}}` - Montant du dépôt de garantie formaté

### 3. Envoi Automatique des Emails

Après la finalisation du contrat (signature + upload des documents d'identité), **deux emails sont maintenant envoyés en parallèle** à chaque locataire:

1. **Email de confirmation** (`contrat_finalisation_client`)
   - Contient le PDF du contrat signé en pièce jointe
   - Informe de la finalisation du contrat
   - Fournit les coordonnées bancaires pour le virement

2. **Email de demande de justificatif** (`demande_justificatif_paiement`) ← NOUVEAU
   - Demande explicite de transmettre le justificatif
   - Rappelle les coordonnées bancaires
   - Indique comment envoyer le justificatif (email, téléphone)

### 4. Mise à Jour de la Page de Confirmation

La page de confirmation (`signature/confirmation.php`) a été mise à jour pour informer l'utilisateur qu'il recevra **2 emails**:
- Un email de confirmation avec le contrat signé
- Un email demandant de transmettre le justificatif de virement

## Fichiers Modifiés

### Fichiers Supprimés
- ❌ `signature/step3-payment.php` - Étape de téléchargement du justificatif de paiement
- ❌ `signature/step4-documents.php` - Ancienne étape de vérification d'identité

### Fichiers Créés
- ✅ `signature/step3-documents.php` - Nouvelle étape 3 (anciennement étape 4)
- ✅ `migrations/038_add_payment_proof_request_email_template.sql` - Migration pour le nouveau template email

### Fichiers Modifiés
- 📝 `signature/step1-info.php` - Barre de progression 1/3 (était 1/4)
- 📝 `signature/step2-signature.php` - Barre de progression 2/3 (était 2/4), redirection vers step3-documents.php
- 📝 `signature/step3-documents.php` - Barre de progression 3/3 (était 4/4), envoi de l'email de demande de justificatif
- 📝 `signature/confirmation.php` - Mention des 2 emails envoyés
- 📝 `init-email-templates.php` - Ajout du nouveau template email

## Changements Techniques

### Base de Données

**Migration créée:** `migrations/038_add_payment_proof_request_email_template.sql`

```sql
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'demande_justificatif_paiement',
    'Demande de justificatif de paiement',
    'Justificatif de virement - Contrat {{reference}}',
    '<!DOCTYPE html>...',
    '["nom", "prenom", "reference", "depot_garantie"]',
    'Email automatique envoyé après signature du contrat pour demander le justificatif de paiement du dépôt de garantie'
);
```

**Note:** Le champ `preuve_paiement_depot` dans la table `locataires` a été conservé pour permettre aux administrateurs de stocker manuellement le justificatif si nécessaire.

### Code

**Dans `signature/step3-documents.php` (lignes 105-109):**
```php
// Envoyer l'email de confirmation avec le contrat PDF
sendTemplatedEmail('contrat_finalisation_client', $locataire['email'], $variables, $pdfPath, false);

// Envoyer l'email de demande de justificatif de paiement (en parallèle)
sendTemplatedEmail('demande_justificatif_paiement', $locataire['email'], $variables, null, false);
```

## Bénéfices

1. **Simplification du parcours utilisateur**
   - Réduction de 4 à 3 étapes dans le workflow de signature
   - Moins de friction pour le client
   - Processus plus fluide et rapide

2. **Meilleure expérience utilisateur**
   - Plus besoin de télécharger le justificatif pendant la signature
   - Le client peut finaliser le contrat plus rapidement
   - La demande de justificatif est traitée en parallèle par email

3. **Flexibilité**
   - Le justificatif peut être envoyé par email à tout moment
   - Le client a le temps d'effectuer le virement et de récupérer le justificatif
   - Pas de blocage dans le processus de signature

4. **Communication claire**
   - Email dédié avec instructions précises
   - Template configurable dans l'interface d'administration
   - Coordonnées bancaires et montant clairement indiqués

## Déploiement

### Étapes de Déploiement

1. **Déployer le code**
   ```bash
   git pull origin copilot/remove-payment-proof-step
   ```

2. **Exécuter la migration**
   ```bash
   php run-migrations.php
   ```

3. **Initialiser le template email (si nécessaire)**
   ```bash
   php init-email-templates.php
   ```

4. **Vérifier le template dans l'admin**
   - Aller sur `/admin-v2/email-templates.php`
   - Vérifier que le template `demande_justificatif_paiement` existe et est actif
   - Personnaliser le contenu si nécessaire

### Tests à Effectuer

1. ✅ Vérifier que le workflow de signature comporte 3 étapes (et non 4)
2. ✅ Signer un contrat et vérifier que 2 emails sont envoyés
3. ✅ Vérifier que l'email de demande de justificatif contient les bonnes informations
4. ✅ Vérifier que la page de confirmation mentionne les 2 emails
5. ✅ Vérifier que le template est configurable dans l'admin

## Compatibilité

### Contrats Existants
- Les contrats déjà signés ne sont pas affectés
- Seuls les nouveaux contrats utilisent le nouveau workflow

### Database Field
- Le champ `preuve_paiement_depot` reste dans la base de données
- Il peut toujours être utilisé manuellement par les administrateurs
- Les fonctions existantes (`updateTenantPaymentProof`) restent disponibles

## Configuration

Le template email peut être personnalisé dans l'interface d'administration :
- URL : `/admin-v2/email-templates.php`
- Identifiant : `demande_justificatif_paiement`
- Variables disponibles : `{{nom}}`, `{{prenom}}`, `{{reference}}`, `{{depot_garantie}}`

## Support

Pour toute question ou problème :
- Vérifier que la migration a bien été exécutée
- Vérifier que le template email est actif
- Consulter les logs d'emails pour diagnostiquer les problèmes d'envoi
- Tester avec un contrat de test avant de mettre en production
