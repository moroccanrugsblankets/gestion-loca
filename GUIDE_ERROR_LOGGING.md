# Guide d'utilisation du logging pour finalize-etat-lieux.php

## Problème résolu

Le fichier `/admin-v2/finalize-etat-lieux.php?id=1` générait une erreur mais il n'était pas clair quelle était la cause. Un système de logging complet a été ajouté pour localiser précisément les problèmes.

## Modifications apportées

### 1. admin-v2/finalize-etat-lieux.php

**Logging ajouté :**

- **Au démarrage de la requête** (ligne ~17-20) :
  - Log l'ID demandé
  - Log les erreurs d'ID invalide

- **Lors de la récupération des données** (ligne ~26-72) :
  - Requête base de données encapsulée dans un try-catch
  - Log tous les champs récupérés (id, contrat_id, type, reference_unique, locataire_email, locataire_nom_complet, adresse, date_etat, contrat_ref)
  - Détection et log des champs manquants/NULL
  - Log des erreurs SQL avec stack trace complète

- **Lors de la finalisation (POST)** (ligne ~76-151) :
  - Log du début du traitement
  - Log de la génération du PDF avec résultat
  - Log de la configuration SMTP (sans le mot de passe)
  - Log des destinataires de l'email
  - Log de chaque étape de l'envoi
  - Log des mises à jour de la base de données
  - Gestion d'erreur améliorée avec type d'exception et stack trace

### 2. pdf/generate-etat-lieux.php

**Logging ajouté :**

- **Fonction generateEtatDesLieuxPDF** (ligne ~22-182) :
  - Log des paramètres d'entrée
  - Log de chaque requête base de données
  - Log du contrat et des locataires récupérés
  - Log de la génération HTML
  - Log de la création TCPDF
  - Log de la sauvegarde du fichier PDF avec taille
  - Gestion d'erreur améliorée avec stack traces

- **Fonction createDefaultEtatLieux** (ligne ~187-291) :
  - Log de la création d'un nouvel état des lieux
  - Log de chaque locataire ajouté
  - Meilleure gestion des erreurs
  - Ajout des champs locataire_email et locataire_nom_complet

## Comment utiliser le logging

### 1. Accéder au fichier error.log

Le fichier de log est configuré dans `includes/config.php` (ligne 166) :

```php
ini_set('error_log', dirname(__DIR__) . '/error.log');
```

Il se trouve donc à la racine du projet : `/error.log`

### 2. Reproduire l'erreur

Pour diagnostiquer le problème avec l'ID 1 :

```
1. Accédez à http://votre-site/admin-v2/finalize-etat-lieux.php?id=1
2. Consultez le fichier error.log
```

### 3. Interpréter les logs

Les logs suivent ce format :

```
=== FINALIZE ETAT LIEUX - START ===
Requested ID: 1
Fetching etat des lieux from database with ID: 1
État des lieux found - ID: 1
Contrat ID: 123
Type: entree
Reference unique: EDL-ENTREE-CONT-001-20240205
Locataire email: exemple@email.com
Locataire nom complet: Jean Dupont
Adresse: 123 Rue de la Paix
Date etat: 2024-02-05
Contrat ref: CONT-001
```

Si un champ est NULL, il sera indiqué comme tel :

```
WARNING: Missing required fields: locataire_email, locataire_nom_complet
```

### 4. Erreurs communes et solutions

#### Erreur : "Missing required fields"

**Cause** : Les champs `locataire_email` et/ou `locataire_nom_complet` sont NULL dans la table `etats_lieux`

**Solution** : Ces champs ont été ajoutés par la migration 027. Vérifier que :
1. La migration 027 a été exécutée
2. Les données ont été migrées depuis la table `locataires`

**Commande pour vérifier** :
```sql
SELECT id, locataire_email, locataire_nom_complet 
FROM etats_lieux 
WHERE id = 1;
```

**Commande pour corriger** :
```sql
-- Récupérer l'email et nom du premier locataire du contrat
UPDATE etats_lieux edl
INNER JOIN contrats c ON edl.contrat_id = c.id
INNER JOIN locataires l ON c.id = l.contrat_id
SET edl.locataire_email = l.email,
    edl.locataire_nom_complet = CONCAT(l.prenom, ' ', l.nom)
WHERE edl.id = 1 
AND l.ordre = 1;
```

#### Erreur : "Erreur lors de la génération du PDF"

**Logs à consulter** :
```
=== generateEtatDesLieuxPDF - START ===
... (chercher ERROR)
=== generateEtatDesLieuxPDF - ERROR ===
```

**Causes possibles** :
1. Contrat non trouvé
2. Aucun locataire trouvé
3. Erreur TCPDF lors de la conversion HTML
4. Problème de permissions sur le répertoire `/pdf/etat_des_lieux/`

#### Erreur : Email non envoyé

**Logs à consulter** :
```
Preparing email with PHPMailer...
Configuring SMTP settings...
SMTP Password configured: No/Yes
```

**Causes possibles** :
1. SMTP_PASSWORD non configuré (vérifier `includes/config.local.php`)
2. Paramètres SMTP incorrects
3. Email destinataire invalide

### 5. Niveaux de logging

Tous les logs sont écrits dans `/error.log` via la fonction `error_log()`.

**Format des logs** :
- `=== SECTION - START ===` : Début d'une opération
- `=== SECTION - SUCCESS ===` : Succès d'une opération  
- `=== SECTION - ERROR ===` : Erreur dans une opération
- `ERROR:` : Erreur critique
- `WARNING:` : Avertissement (non bloquant)
- Logs normaux : Informations de debug

### 6. Tester le système de logging

Un script de test a été créé : `test-finalize-error-logging.php`

```bash
php test-finalize-error-logging.php
```

Ce script vérifie :
- Le fichier error.log existe et est accessible
- La connexion à la base de données
- La structure de la table etats_lieux
- Les enregistrements existants
- La disponibilité de PHPMailer

## Checklist de diagnostic

Lorsque l'erreur se produit, vérifier dans l'ordre :

1. ✅ Le fichier error.log est-il créé et accessible ?
2. ✅ L'ID existe-t-il dans la table etats_lieux ?
3. ✅ Tous les champs requis sont-ils renseignés ?
   - contrat_id
   - type
   - locataire_email
   - locataire_nom_complet
   - adresse
   - date_etat
4. ✅ Le contrat associé existe-t-il ?
5. ✅ Des locataires sont-ils associés au contrat ?
6. ✅ Le répertoire `/pdf/etat_des_lieux/` est-il accessible en écriture ?
7. ✅ PHPMailer est-il installé ? (vendor/autoload.php)
8. ✅ Les paramètres SMTP sont-ils configurés ?

## Fichiers modifiés

1. `admin-v2/finalize-etat-lieux.php` - Ajout de logging complet
2. `pdf/generate-etat-lieux.php` - Ajout de logging dans la génération PDF
3. `test-finalize-error-logging.php` - Script de test (nouveau)
4. `GUIDE_ERROR_LOGGING.md` - Cette documentation (nouveau)

## Support

Si l'erreur persiste après avoir consulté les logs :

1. Vérifier les dernières lignes du fichier error.log
2. Copier le stack trace complet
3. Vérifier l'état de la base de données
4. Vérifier les permissions des fichiers et dossiers
