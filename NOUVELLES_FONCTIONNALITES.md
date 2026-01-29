# Guide de Déploiement et Configuration - Nouvelles Fonctionnalités

## Vue d'ensemble

Cette mise à jour ajoute plusieurs fonctionnalités importantes au système My Invest Immobilier :

1. **Amélioration de la gestion des logements**
2. **Système de paramètres configurables**
3. **Règles d'élimination automatique renforcées**
4. **Configuration des emails administrateurs**
5. **Système de templates d'emails en base de données**

---

## Installation

### 1. Appliquer les migrations de base de données

Les migrations créent les nouvelles tables et colonnes nécessaires.

```bash
cd /chemin/vers/contrat-de-bail
php run-migrations.php
```

Cela va exécuter les migrations suivantes :
- `001_add_logements_new_fields.sql` - Ajoute les champs calculés aux logements
- `002_create_parametres_table.sql` - Crée la table des paramètres
- `003_create_email_templates_table.sql` - Crée la table des templates d'emails

### 2. Configuration des emails administrateurs

Copiez le template de configuration locale :

```bash
cp includes/config.local.php.template includes/config.local.php
```

Éditez `includes/config.local.php` et configurez :

```php
return [
    'ADMIN_EMAIL' => 'admin@myinvest-immobilier.com',
    'ADMIN_EMAIL_SECONDARY' => 'admin2@myinvest-immobilier.com', // Optionnel
    'SMTP_PASSWORD' => 'votre_mot_de_passe_smtp',
];
```

**Important** : `config.local.php` est dans `.gitignore` et ne sera jamais commité.

---

## Nouvelles Fonctionnalités

### 1. Gestion des Logements Améliorée

#### Nouveaux champs

**Champs calculés automatiquement** :
- **Total mensuel** : Calculé automatiquement (loyer + charges)
- **Revenus requis** : Calculé automatiquement (total mensuel × 3)

**Champs manuels** :
- **Date de disponibilité** : Date à laquelle le logement sera disponible

#### Type de logement

Le champ "Type" est maintenant un menu déroulant avec deux valeurs :
- T1 Bis
- T2

#### Correction du bug statut

Le statut du logement est maintenant correctement sauvegardé et affiché lors de l'édition.

**Statuts disponibles** :
- Disponible
- Réservé
- Loué
- Maintenance

---

### 2. Système de Paramètres

Accessible via : **Admin → Paramètres**

#### Paramètres de Workflow

- **Délai de réponse automatique** : Nombre de jours ouvrés avant envoi de la réponse (défaut: 4)
- **Jours ouvrés** : Configuration du début et fin de semaine ouvrée

#### Critères d'Acceptation

Paramètres configurables pour l'acceptation automatique :

- **Revenus minimum requis** : Revenus nets mensuels minimum en € (défaut: 3000)
- **Statuts professionnels acceptés** : Liste JSON, ex: `["CDI", "CDD"]`
- **Type de revenus accepté** : Type requis (défaut: "Salaires")
- **Nombres d'occupants acceptés** : Liste JSON, ex: `["1", "2"]`
- **Garantie Visale requise** : true/false

---

### 3. Règles d'Élimination Automatique Renforcées

Le système de traitement automatique des candidatures (`cron/process-candidatures.php`) applique maintenant des règles strictes :

#### Conditions de Refus Automatique

1. **Statut professionnel** : Refus si pas CDI ou CDD
2. **Revenus mensuels** : Refus si < 3000 €
3. **Type de revenus** : Refus si ≠ "Salaires"
4. **Nombre d'occupants** : Refus si "Autre" (pas 1 ou 2)
5. **Garantie Visale** : Refus si "Non"
6. **Période d'essai** : Pour CDI, refus si période d'essai en cours

#### Motif de refus

Le motif de refus est enregistré en base de données dans le champ `motif_refus` de la table `candidatures`.

---

### 4. Emails Administrateurs Multiples

#### Configuration

Dans `includes/config.local.php` :

```php
'ADMIN_EMAIL' => 'admin-principal@example.com',
'ADMIN_EMAIL_SECONDARY' => 'admin-secondaire@example.com', // Optionnel
```

#### Fonctionnement

Lorsqu'un email est envoyé aux administrateurs avec le paramètre `$isAdminEmail = true`, il sera automatiquement envoyé :
- Au destinataire principal
- En copie (CC) à l'adresse secondaire si configurée

Exemple d'utilisation :

```php
sendEmail(
    $config['ADMIN_EMAIL'], 
    'Nouvelle candidature', 
    $emailBody, 
    null, 
    true, 
    true  // isAdminEmail = true
);
```

---

### 5. Templates d'Emails en Base de Données

Accessible via : **Admin → Templates d'Email**

#### Fonctionnalités

- **Liste des templates** : Voir tous les templates disponibles
- **Édition en ligne** : Modifier le sujet et le corps HTML
- **Variables dynamiques** : Utiliser des placeholders comme `{{nom}}`, `{{prenom}}`, etc.
- **Prévisualisation** : Voir les variables disponibles pour chaque template

#### Templates par défaut

1. **candidature_recue** : Accusé de réception de candidature
   - Variables: nom, prenom, email, logement, reference, date

2. **candidature_acceptee** : Notification d'acceptation
   - Variables: nom, prenom, email, logement, reference, date, lien_confirmation

3. **candidature_refusee** : Notification de refus
   - Variables: nom, prenom, email

4. **admin_nouvelle_candidature** : Notification admin
   - Variables: nom, prenom, email, telephone, logement, reference, date, revenus, statut_pro, lien_admin

#### Utilisation dans le code

```php
// Préparer les variables
$variables = [
    'nom' => 'Dupont',
    'prenom' => 'Jean',
    'email' => 'jean.dupont@example.com',
    'logement' => 'T2 - 15 rue de la Paix',
    'reference' => 'CAND-20260129-ABC123',
    'date' => date('d/m/Y')
];

// Envoyer l'email avec template
sendTemplatedEmail(
    'candidature_recue',  // Identifiant du template
    'jean.dupont@example.com',  // Destinataire
    $variables  // Variables à remplacer
);
```

#### Format des variables

Les variables utilisent la syntaxe : `{{nom_variable}}`

Exemple dans un template :
```html
<p>Bonjour {{prenom}} {{nom}},</p>
<p>Votre candidature pour le logement <strong>{{logement}}</strong> a été reçue.</p>
<p>Référence: {{reference}}</p>
```

---

## Tests Recommandés

### 1. Test des logements

1. Aller dans **Admin → Logements**
2. Ajouter un nouveau logement :
   - Sélectionner un type (T1 Bis ou T2)
   - Entrer loyer et charges
   - Vérifier que Total mensuel et Revenus requis sont calculés
   - Ajouter une date de disponibilité
3. Éditer le logement et vérifier que le statut est correctement récupéré

### 2. Test des paramètres

1. Aller dans **Admin → Paramètres**
2. Modifier le délai de réponse automatique
3. Modifier les critères d'acceptation
4. Sauvegarder et vérifier que les modifications sont enregistrées

### 3. Test du traitement automatique

1. Créer une candidature test
2. Exécuter manuellement le cron :
   ```bash
   php cron/process-candidatures.php
   ```
3. Vérifier dans les logs (`cron/cron-log.txt`) que :
   - La candidature a été traitée
   - Le motif de refus est enregistré si applicable
   - L'email a été envoyé

### 4. Test des templates d'emails

1. Aller dans **Admin → Templates d'Email**
2. Éditer un template (ex: candidature_recue)
3. Modifier le sujet ou le corps
4. Sauvegarder
5. Créer une candidature test et vérifier que l'email utilise le nouveau template

### 5. Test des emails administrateurs multiples

1. Configurer `ADMIN_EMAIL_SECONDARY` dans `config.local.php`
2. Créer une candidature test
3. Vérifier que les deux adresses reçoivent les notifications

---

## Dépannage

### Les migrations ne s'appliquent pas

```bash
# Vérifier les erreurs
php run-migrations.php 2>&1 | tee migration-errors.log

# Vérifier manuellement la structure
mysql -u root -p bail_signature
DESCRIBE logements;
SHOW TABLES;
```

### Les paramètres ne se chargent pas

Vérifier que la table `parametres` existe et contient des données :

```sql
SELECT * FROM parametres;
```

### Les templates d'emails ne fonctionnent pas

Vérifier que la table `email_templates` existe et contient des templates :

```sql
SELECT identifiant, nom FROM email_templates;
```

### L'email secondaire ne reçoit pas les messages

1. Vérifier que `config.local.php` est bien chargé
2. Vérifier que `ADMIN_EMAIL_SECONDARY` est non vide
3. Vérifier les logs d'emails dans le fichier d'erreur PHP

---

## Sécurité

### Bonnes pratiques

1. **Ne jamais commiter** `config.local.php` (déjà dans `.gitignore`)
2. **Changer le mot de passe admin** par défaut
3. **Utiliser HTTPS** en production
4. **Configurer SMTP** avec des credentials sécurisés
5. **Limiter les accès** à l'interface admin

### Vulnérabilités corrigées

- Validation stricte des statuts de logements
- Protection contre les injections SQL (prepared statements)
- Échappement HTML dans les templates
- Validation des paramètres avant utilisation

---

## Support

Pour toute question ou problème :
- Consulter les logs : `error.log` et `cron/cron-log.txt`
- Vérifier la documentation dans `/docs`
- Contacter : contact@myinvest-immobilier.com

---

*Dernière mise à jour : 29 janvier 2026*
