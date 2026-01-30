# Documentation des Corrections Apportées

## Résumé des Modifications

Ce document décrit les corrections et améliorations apportées au système de gestion des candidatures locatives.

---

## 1. Gestion Centralisée des Signatures Email

### Problème Initial
- Les signatures étaient dupliquées et hardcodées dans plusieurs templates d'email
- Exemple trouvé dans `admin-v2/send-email-candidature.php` :
  ```php
  Cordialement,<br>
  L'équipe MY Invest Immobilier
  ```

### Solution Implémentée
✅ **Remplacement par le placeholder `{{signature}}`**

**Fichier modifié :** `admin-v2/send-email-candidature.php`
- Ligne ~86-89 : Signature hardcodée remplacée par `{{signature}}`
- Le placeholder est automatiquement remplacé par la fonction `sendEmail()` dans `includes/mail-templates.php`

**Configuration de la signature :**
- **Base de données :** Table `parametres`, clé `email_signature`
- **Migration :** `migrations/005_add_email_signature.sql`
- **Interface admin :** `admin-v2/parametres.php` (section "Configuration Email")

**Fonctionnement :**
1. L'administrateur configure la signature HTML dans Paramètres
2. La valeur est stockée en base de données
3. La fonction `sendEmail()` récupère et cache la signature
4. Tous les emails utilisant `{{signature}}` reçoivent automatiquement la signature configurée

**Exemple de signature par défaut :**
```html
<table><tbody><tr>
  <td><img src="https://www.myinvest-immobilier.com/images/logo.png"></td>
  <td>&nbsp;</td>
  <td><h3>MY INVEST IMMOBILIER</h3></td>
</tr></tbody></table>
```

---

## 2. Correction du Téléchargement de Documents

### Problème Initial
- Erreurs 404 lors du téléchargement de documents de candidature
- Messages d'erreur peu informatifs
- URL d'exemple problématique : `/candidatures/10/bulletins_salaire_0_aa11bc935f579d1f.jpg`

### Analyse du Problème
Le système fonctionne correctement avec le flux suivant :
1. **Upload** → Fichiers sauvegardés dans `/uploads/candidatures/{id}/filename`
2. **Base de données** → Chemin stocké comme `candidatures/{id}/filename`
3. **Téléchargement** → Script `download-document.php` construit `/uploads/ + candidatures/{id}/filename`

**Problème identifié :** 
- L'erreur se produisait lorsque `realpath()` était appelée sur un fichier inexistant (retourne `false`)
- Cela générait un message "Chemin invalide" au lieu de "Fichier non trouvé"
- Pas de logs pour diagnostiquer le problème

### Solution Implémentée
✅ **Amélioration de la gestion d'erreurs dans `admin-v2/download-document.php`**

**Changements effectués :**

1. **Vérification d'existence avant realpath()** (ligne ~43-48)
   ```php
   // Check if file exists first (before realpath which returns false for non-existent files)
   if (!file_exists($fullPath)) {
       error_log("File not found: $fullPath (Document path: $documentPath, Candidature ID: $candidatureId)");
       die('Fichier non trouvé sur le serveur. Le fichier a peut-être été supprimé ou n\'a jamais été uploadé correctement.');
   }
   ```

2. **Ajout de logging détaillé** pour faciliter le diagnostic
   - Log du chemin complet du fichier
   - Log de l'ID de candidature
   - Log du chemin relatif depuis la base de données

3. **Messages d'erreur plus explicites**
   - "Fichier non trouvé" → indique que le fichier n'existe pas physiquement
   - "Chemin invalide" → indique une tentative d'accès en dehors du répertoire uploads
   - "Erreur de vérification" → problème inattendu avec realpath()

**Architecture de stockage confirmée :**
```
/uploads/
  /candidatures/
    /10/
      bulletins_salaire_0_aa11bc935f579d1f.jpg
      piece_identite_0_b2c3d4e5f6g7h8i9.pdf
      ...
```

**Flux de téléchargement correct :**
```
1. Utilisateur clique sur "Télécharger" dans admin-v2/candidature-detail.php
2. URL générée: download-document.php?candidature_id=10&path=candidatures/10/file.jpg
3. Script vérifie l'existence du fichier
4. Script valide la sécurité (pas de directory traversal)
5. Script envoie le fichier avec les bons headers
```

---

## 3. Affichage du Champ "Revenus nets mensuels"

### Problème Initial
- Le champ "Revenus nets mensuels" manquait dans la section appropriée
- La section devait s'appeler "Revenus & Solvabilité"

### Analyse
En réalité, le champ existait déjà mais avec un label différent :
- **Colonne DB :** `revenus_mensuels` (ENUM: '< 2300', '2300-3000', '3000+')
- **Ancien label :** "Revenus mensuels"
- **Section :** "Revenus"

### Solution Implémentée
✅ **Mise à jour des labels dans `admin-v2/candidature-detail.php`**

**Changements effectués :**

1. **Titre de section** (ligne ~309)
   - Ancien : `<h5>Revenus</h5>`
   - Nouveau : `<h5>Revenus & Solvabilité</h5>`

2. **Label du champ** (ligne ~311)
   - Ancien : `Revenus mensuels:`
   - Nouveau : `Revenus nets mensuels:`

**Affichage :**
Le champ utilise la fonction `formatRevenus()` qui convertit les valeurs ENUM en texte lisible :
- `< 2300` → "< 2300 €"
- `2300-3000` → "2300-3000 €"
- `3000+` → "3000 € et +"

**Localisation dans l'interface :**
```
Section: Revenus & Solvabilité
├── Revenus nets mensuels: 2300-3000 €
└── Type de revenus: Salaires
```

---

## Tests de Validation

Un script de test complet a été créé : `test-fixes.php`

### Résultats des Tests
✅ Tous les tests passent avec succès :
- Signature email utilise le placeholder `{{signature}}`
- Pas de signature hardcodée dans send-email-candidature.php
- Fonction sendEmail() remplace correctement le placeholder
- Vérification d'existence de fichier avant realpath()
- Logging d'erreurs activé pour diagnostic
- Chemins d'upload et download cohérents
- Label "Revenus nets mensuels" présent
- Section "Revenus & Solvabilité" correctement nommée

### Commande pour exécuter les tests
```bash
php test-fixes.php
```

---

## Livrables

### Fichiers Modifiés
1. ✅ `admin-v2/send-email-candidature.php` - Signature email centralisée
2. ✅ `admin-v2/download-document.php` - Meilleure gestion d'erreurs
3. ✅ `admin-v2/candidature-detail.php` - Labels de revenus mis à jour

### Fichiers de Support
4. ✅ `test-fixes.php` - Script de validation des corrections
5. ✅ `FIXES_DOCUMENTATION.md` - Documentation complète (ce fichier)

### Migrations et Configuration
- ✅ Migration `005_add_email_signature.sql` déjà existante
- ✅ Interface admin `parametres.php` déjà configurée

---

## Instructions de Déploiement

### 1. Déployer les fichiers modifiés
```bash
# Fichiers à déployer
admin-v2/send-email-candidature.php
admin-v2/download-document.php
admin-v2/candidature-detail.php
```

### 2. Vérifier la migration
```bash
# La migration devrait déjà être appliquée
# Si nécessaire, vérifier avec:
mysql -u user -p database_name < migrations/005_add_email_signature.sql
```

### 3. Configurer la signature
1. Se connecter à l'admin : `/admin-v2/`
2. Aller dans "Paramètres"
3. Section "Configuration Email"
4. Modifier "Signature des emails" selon vos besoins
5. Cliquer sur "Enregistrer"

### 4. Valider le fonctionnement
- Envoyer un email test depuis l'admin
- Vérifier que la signature apparaît correctement
- Tester le téléchargement d'un document de candidature
- Vérifier l'affichage de la page de détail d'une candidature

---

## Recommandations Futures

### Pour éviter les erreurs 404 de documents
1. **Vérifier les uploads réussis :** Ajouter une validation côté serveur que le fichier existe après upload
2. **Ajouter des tests automatiques :** Vérifier périodiquement la cohérence entre la base et les fichiers physiques
3. **Script de nettoyage :** Supprimer les enregistrements DB sans fichiers correspondants

### Pour la signature email
1. **Prévisualisation :** L'interface admin affiche déjà un aperçu de la signature
2. **Versionnage :** Considérer l'ajout d'un historique des modifications de signature
3. **Variables supplémentaires :** Ajouter `{{nom_agence}}`, `{{telephone}}`, etc.

### Pour les revenus
- Le champ est désormais correctement labellisé
- Aucune modification future requise sauf si le modèle de données change

---

## Support et Maintenance

Pour toute question ou problème :
1. Consulter les logs : `/var/log/apache2/error.log` ou logs PHP
2. Vérifier le script de test : `php test-fixes.php`
3. Vérifier la base de données : table `parametres` pour email_signature

**Date de création :** 2026-01-30  
**Version :** 1.0
