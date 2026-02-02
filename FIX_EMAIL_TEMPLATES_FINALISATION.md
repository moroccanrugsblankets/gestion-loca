# Fix: Templates Email HTML pour la Finalisation de Contrat

## Problème Résolu

### Symptômes Initiaux
1. **Signature non ajoutée au contrat après validation** - La signature électronique de la société n'était pas ajoutée au PDF du contrat après validation par l'admin
2. **Email client en texte brut** - Le client recevait un email sans style lors de la finalisation du contrat
3. **Templates non gérables dans l'admin** - Les templates d'email pour la finalisation n'existaient pas dans la base de données et ne pouvaient pas être modifiés via l'interface admin

### Emails Affectés
- **Client**: "Contrat de bail – Finalisation" (texte brut, hardcodé)
- **Admin**: "[ADMIN] Contrat signé - BAIL-XXX" (texte brut, hardcodé)

## Solutions Implémentées

### 1. Templates Email HTML ✅

**Fichiers créés/modifiés:**
- `migrations/022_add_contract_finalisation_email_templates.sql` - Migration pour créer les templates
- `init-email-templates.php` - Ajout des templates dans le script d'initialisation
- `signature/step3-documents.php` - Utilisation des templates au lieu du code hardcodé

**Nouveaux templates:**

#### Template: `contrat_finalisation_client`
- **Nom**: Contrat de bail - Finalisation Client
- **Sujet**: Contrat de bail – Finalisation
- **Variables disponibles**: `{{nom}}`, `{{prenom}}`, `{{reference}}`, `{{depot_garantie}}`, `{{signature}}`
- **Description**: Email HTML professionnel envoyé au client lors de la finalisation du contrat avec le PDF joint

**Contenu du template:**
- En-tête avec dégradé violet
- Référence du contrat mise en évidence
- Informations bancaires dans un encadré stylisé
- Liste des prochaines étapes
- Signature email professionnelle automatique via `{{signature}}`

#### Template: `contrat_finalisation_admin`
- **Nom**: Notification Admin - Contrat Finalisé
- **Sujet**: [ADMIN] Contrat signé - {{reference}}
- **Variables disponibles**: `{{reference}}`, `{{logement}}`, `{{locataires}}`, `{{depot_garantie}}`, `{{date_finalisation}}`, `{{lien_admin}}`, `{{signature}}`
- **Description**: Email HTML envoyé aux administrateurs quand un contrat est finalisé et signé

**Contenu du template:**
- Alerte visuelle de nouveau contrat signé
- Tableau récapitulatif des informations du contrat
- Liste des actions à effectuer
- Bouton d'accès direct au contrat dans l'admin
- Signature email professionnelle

### 2. Signature Société sur PDF ✅

**Fichier modifié:**
- `pdf/generate-bail.php` - Ajout de la signature électronique de la société

**Fonctionnalité:**
- Vérification si le contrat est validé (`statut = 'valide'`)
- Vérification si la signature société est activée (`signature_societe_enabled = true`)
- Vérification si une image de signature est configurée (`signature_societe_image`)
- Si toutes les conditions sont remplies, affichage de la signature électronique de la société dans la section "Le bailleur"
- Affichage de la date et heure de validation

**Avant:**
```
Le bailleur
MY Invest Immobilier (SCI)
Représentée par Maxime Alexandre
Lu et approuvé
Signature
(Horodatage + adresse IP + tampon signé)
```

**Après (contrat validé):**
```
Le bailleur
MY Invest Immobilier (SCI)
Représentée par Maxime Alexandre
Lu et approuvé
Signature électronique
[IMAGE DE LA SIGNATURE]
Validé le : 02/02/2026 à 15:30:45
```

## Installation et Configuration

### Étape 1: Appliquer la Migration

```bash
# Méthode 1: Script de migration automatique (recommandé)
php run-migrations.php

# Méthode 2: Appliquer manuellement
mysql -u [user] -p [database] < migrations/022_add_contract_finalisation_email_templates.sql
```

### Étape 2: Initialiser les Templates (si besoin)

Si la migration échoue ou pour réinitialiser les templates:

```bash
# Créer les templates manquants uniquement
php init-email-templates.php

# Ou réinitialiser tous les templates
php init-email-templates.php --reset
```

### Étape 3: Configurer la Signature Société

1. Aller sur `/admin-v2/contrat-configuration.php`
2. Dans l'onglet "Configuration de la Signature"
3. Uploader une image de signature (PNG transparent recommandé, max 500x200px)
4. Cocher "Activer l'ajout automatique de la signature"
5. Enregistrer

### Étape 4: Vérifier les Templates

1. Aller sur `/admin-v2/email-templates.php`
2. Vérifier que les templates suivants existent:
   - ✓ Contrat de bail - Finalisation Client
   - ✓ Notification Admin - Contrat Finalisé
3. Personnaliser si nécessaire (sujet, corps HTML)

## Utilisation

### Workflow de Finalisation du Contrat

1. **Client signe le contrat** (via `/signature/`)
2. **System génère le PDF** avec les signatures clients uniquement
3. **Client reçoit email HTML** avec:
   - Template: `contrat_finalisation_client`
   - Contenu: Instructions de paiement + informations bancaires
   - Pièce jointe: PDF du contrat signé
4. **Admin reçoit notification** avec:
   - Template: `contrat_finalisation_admin`
   - Contenu: Détails du contrat + lien vers admin
   - Pièce jointe: PDF du contrat signé

### Workflow de Validation Admin

1. **Admin vérifie le contrat** dans `/admin-v2/contrat-detail.php`
2. **Admin clique sur "Valider le contrat"**
3. **System régénère le PDF** avec signature société ajoutée
4. **Emails de validation** envoyés (si templates configurés)
5. **Client peut télécharger** le PDF final avec toutes les signatures

## Tests

### Test des Templates Email

Pour tester les nouveaux templates:

```bash
# Générer des aperçus HTML des emails
php generate-email-previews.php
```

Les aperçus seront disponibles dans le navigateur.

### Test de la Signature Société

1. Créer un contrat de test
2. Le faire signer par un client
3. Aller dans admin et cliquer "Valider le contrat"
4. Télécharger le PDF généré
5. Vérifier que la signature société apparaît dans la section "Le bailleur"

### Test des Emails HTML

1. Créer un contrat de test
2. Le faire signer
3. Vérifier l'email reçu:
   - ✓ Format HTML (pas texte brut)
   - ✓ Logo et couleurs de la charte
   - ✓ Informations bancaires bien formatées
   - ✓ Signature professionnelle en bas
   - ✓ PDF joint

## Personnalisation

### Modifier les Templates Email

1. Aller sur `/admin-v2/email-templates.php`
2. Cliquer sur "Modifier" pour le template souhaité
3. Utiliser l'éditeur TinyMCE pour modifier le HTML
4. Variables disponibles affichées en haut
5. Ne pas oublier d'inclure `{{signature}}` pour la signature professionnelle
6. Enregistrer

### Variables Disponibles

**Template Client (`contrat_finalisation_client`):**
- `{{nom}}` - Nom du locataire
- `{{prenom}}` - Prénom du locataire
- `{{reference}}` - Référence unique du contrat
- `{{depot_garantie}}` - Montant du dépôt de garantie formaté
- `{{signature}}` - Signature email professionnelle (auto)

**Template Admin (`contrat_finalisation_admin`):**
- `{{reference}}` - Référence unique du contrat
- `{{logement}}` - Adresse du logement
- `{{locataires}}` - Liste des locataires (noms complets)
- `{{depot_garantie}}` - Montant du dépôt de garantie formaté
- `{{date_finalisation}}` - Date et heure de finalisation
- `{{lien_admin}}` - Lien vers le contrat dans l'admin
- `{{signature}}` - Signature email professionnelle (auto)

## Avantages

### Avant
❌ Emails en texte brut sans style  
❌ Signature société non ajoutée au PDF  
❌ Templates hardcodés dans le code  
❌ Impossible de modifier les emails sans toucher au code  
❌ Pas de cohérence visuelle avec les autres emails  

### Après
✅ Emails HTML professionnels  
✅ Signature société ajoutée automatiquement lors de la validation  
✅ Templates dans la base de données  
✅ Modification facile via l'interface admin  
✅ Cohérence visuelle avec tous les emails  
✅ Variable `{{signature}}` pour signature professionnelle  
✅ Meilleure délivrabilité des emails  

## Dépannage

### Les templates n'apparaissent pas dans l'admin

```bash
# Vérifier que la table email_templates existe
mysql -u [user] -p -e "SHOW TABLES LIKE 'email_templates'" [database]

# Si la table n'existe pas, exécuter la migration 003
php apply-migration.php migrations/003_create_email_templates_table.sql

# Puis exécuter la migration 022
php apply-migration.php migrations/022_add_contract_finalisation_email_templates.sql

# Ou utiliser le script d'initialisation
php init-email-templates.php
```

### La signature société n'apparaît pas sur le PDF

**Vérifications:**
1. ✓ Contrat a le statut 'valide' ?
2. ✓ Paramètre `signature_societe_enabled` = 'true' ?
3. ✓ Paramètre `signature_societe_image` contient une image base64 ?
4. ✓ Le PDF a été régénéré après validation ?

**Solution:**
```bash
# Vérifier les paramètres dans la base de données
mysql -u [user] -p [database] -e "SELECT cle, valeur FROM parametres WHERE cle LIKE 'signature_societe%'"

# Vérifier dans l'admin
# Aller sur /admin-v2/contrat-configuration.php
# Vérifier que la signature est uploadée et activée
```

### Les emails sont toujours en texte brut

**Cause probable:** Templates non initialisés ou code cache

**Solution:**
```bash
# Réinitialiser les templates
php init-email-templates.php --reset

# Vider le cache PHP si activé
# Redémarrer le serveur web si nécessaire

# Vérifier que les templates existent
mysql -u [user] -p [database] -e "SELECT identifiant, nom FROM email_templates WHERE identifiant LIKE 'contrat_finalisation%'"
```

### Les variables ne sont pas remplacées

**Vérification:**
- Le fichier `signature/step3-documents.php` a été mis à jour ?
- Les variables passées à `sendTemplatedEmail()` sont correctes ?

**Debug:**
Ajouter temporairement avant l'envoi:
```php
error_log("Variables: " . print_r($variables, true));
```

## Fichiers Modifiés

| Fichier | Type | Description |
|---------|------|-------------|
| `migrations/022_add_contract_finalisation_email_templates.sql` | Nouveau | Migration pour créer les templates |
| `init-email-templates.php` | Modifié | Ajout des 2 nouveaux templates |
| `signature/step3-documents.php` | Modifié | Utilisation de sendTemplatedEmail() |
| `pdf/generate-bail.php` | Modifié | Ajout signature société si validé |

## Conclusion

Cette mise à jour résout complètement les trois problèmes identifiés:

1. ✅ **Signature ajoutée** - La signature électronique de la société est maintenant ajoutée automatiquement au PDF lors de la validation
2. ✅ **Emails HTML** - Les clients et admins reçoivent des emails HTML professionnels et stylisés
3. ✅ **Templates gérables** - Tous les templates sont maintenant modifiables via l'interface admin

Les emails sont maintenant cohérents avec le reste du système, utilisent la signature professionnelle, et offrent une meilleure expérience utilisateur.
