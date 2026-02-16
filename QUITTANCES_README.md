# Génération de Quittances de Loyer

## Vue d'ensemble

Ce module permet la génération automatique de quittances de loyer au format PDF et leur envoi par email aux locataires depuis l'interface de gestion des contrats.

## Fonctionnalités

### 1. Génération de PDF
- Génération automatique de quittances au format PDF conforme aux normes
- Template HTML personnalisable via l'interface d'administration
- Variables dynamiques pour la personnalisation (locataire, montants, dates, etc.)
- Un PDF par mois sélectionné

### 2. Sélection des Mois
- **Interface de sélection par plage de dates** - Sélectionnez simplement une date de début et une date de fin
- Génération automatique de toutes les quittances pour les mois compris dans la période sélectionnée
- **Visibilité limitée aux 3 dernières années** ou depuis le début du contrat si celui-ci a moins de 3 ans
- **Aucune génération de quittances pour les mois futurs** - uniquement le mois en cours et les mois antérieurs
- Exemple : le 28 janvier, impossible de générer une quittance pour février (disponible à partir du 1er février)
- Historique complet des quittances émises

### 3. Envoi Automatique par Email
- Envoi automatique aux locataires après génération
- Copie cachée (BCC) aux administrateurs
- Template d'email personnalisable
- Confirmation de l'envoi

### 4. Gestion et Configuration
- Page de configuration dédiée pour le template PDF
- Accès depuis le menu Contrats > Configuration Quittances
- Variables disponibles documentées
- Réinitialisation au template par défaut possible

## Installation

### Étape 1: Exécuter les migrations

```bash
php run-migrations.php
```

Cela créera:
- La table `quittances` pour stocker les quittances générées
- Le template email `quittance_envoyee` pour l'envoi aux locataires

### Étape 2: Vérifier la configuration

1. Connectez-vous à l'interface d'administration
2. Accédez à **Contrats > Configuration Quittances**
3. Personnalisez le template si nécessaire
4. Vérifiez les informations de la société dans les paramètres

## Utilisation

### Générer des quittances

1. Accédez à la page de détails d'un contrat validé
2. Cliquez sur le bouton **"Générer une quittance"**
3. Sélectionnez une période en utilisant les champs de date :
   - **Date de début (Depuis)** : Premier mois de la période
   - **Date de fin (Jusqu'à)** : Dernier mois de la période (inclus)
4. Cliquez sur **"Générer et Envoyer les Quittances"**
5. Les quittances sont générées et envoyées automatiquement pour tous les mois de la période

#### Règles de Sélection des Périodes

Le système applique automatiquement les règles suivantes lors de la sélection des périodes :

1. **Période historique limitée** : Maximum 3 ans en arrière (36 mois)
   - Si le contrat a moins de 3 ans, la période commence à la date de prise d'effet du contrat
   - Exemple : Contrat démarré le 15 juin 2024, on ne peut pas générer de quittance pour mai 2024

2. **Pas de mois futurs** : Impossible de générer une quittance pour un mois qui n'a pas encore commencé
   - Le 28 janvier 2026 → Impossible de générer pour février 2026
   - Le 1er février 2026 → Possible de générer pour février 2026
   - Cette règle empêche la génération anticipée de quittances

3. **Validation de la plage de dates** : La date de début doit être antérieure ou égale à la date de fin
   - Validation côté client (interface) et côté serveur (sécurité)

4. **Validation côté serveur** : Même si l'utilisateur contourne l'interface, le serveur refuse les demandes invalides

Ces règles garantissent que :
- Les locataires ne reçoivent que des quittances pour les périodes où ils occupaient effectivement le logement
- Aucune quittance ne peut être générée pour des périodes futures
- L'historique reste gérable (limité à 3 ans)
- La sélection de périodes multiples est simplifiée

### Personnaliser le template PDF

1. Allez dans **Contrats > Configuration Quittances**
2. Modifiez le code HTML du template
3. Utilisez les variables disponibles (ex: `{{montant_total}}`, `{{periode}}`)
4. Sauvegardez vos modifications

## Structure de la Base de Données

### Table `quittances`

```sql
CREATE TABLE quittances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrat_id INT NOT NULL,
    reference_unique VARCHAR(100) UNIQUE NOT NULL,
    mois INT NOT NULL,
    annee INT NOT NULL,
    montant_loyer DECIMAL(10,2) NOT NULL,
    montant_charges DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    date_generation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_debut_periode DATE NOT NULL,
    date_fin_periode DATE NOT NULL,
    fichier_pdf VARCHAR(255),
    email_envoye BOOLEAN DEFAULT FALSE,
    date_envoi_email TIMESTAMP NULL,
    genere_par INT,
    notes TEXT,
    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
    UNIQUE KEY unique_contrat_mois_annee (contrat_id, mois, annee)
);
```

## Fichiers du Module

### Backend (PHP)
- `/pdf/generate-quittance.php` - Génération des PDF
- `/admin-v2/generer-quittances.php` - Interface de sélection des mois
- `/admin-v2/quittance-configuration.php` - Configuration du template

### Migrations
- `/migrations/048_create_quittances_table.sql` - Création de la table
- `/migrations/049_add_quittance_email_template.sql` - Template email

### Configuration
- Template PDF stocké dans la table `parametres` avec la clé `quittance_template_html`
- Template email stocké dans la table `email_templates` avec l'identifiant `quittance_envoyee`

## Variables Disponibles

### Template PDF

#### Informations Quittance
- `{{reference_quittance}}` - Référence unique de la quittance
- `{{periode}}` - Période au format "Janvier 2024"
- `{{mois}}` - Nom du mois
- `{{annee}}` - Année
- `{{date_generation}}` - Date de génération
- `{{date_debut_periode}}` - Premier jour du mois
- `{{date_fin_periode}}` - Dernier jour du mois

#### Montants
- `{{montant_loyer}}` - Montant du loyer formaté
- `{{montant_charges}}` - Montant des charges formaté
- `{{montant_total}}` - Total (loyer + charges) formaté

#### Locataires
- `{{locataires_noms}}` - Noms de tous les locataires
- `{{locataire_nom}}` - Nom du premier locataire
- `{{locataire_prenom}}` - Prénom du premier locataire

#### Logement
- `{{adresse}}` - Adresse complète du logement
- `{{logement_reference}}` - Référence du logement

#### Société
- `{{nom_societe}}` - Nom de la société
- `{{adresse_societe}}` - Adresse de la société
- `{{tel_societe}}` - Téléphone de la société
- `{{email_societe}}` - Email de la société

### Template Email

Les mêmes variables sont disponibles dans le template email.

## Sécurité

### Restrictions d'Accès
- Accessible uniquement aux administrateurs authentifiés
- Le bouton n'apparaît que pour les contrats validés
- Validation des données côté serveur

### Protection des Données
- Les PDF sont stockés dans un répertoire protégé
- Les quittances sont liées au contrat via une clé étrangère avec suppression en cascade
- Pas de duplication : contrainte unique sur (contrat_id, mois, annee)

### Validation des Entrées
- Validation de l'ID du contrat
- Validation du mois (1-12) et de l'année
- Protection contre les injections SQL avec PDO
- Échappement HTML des variables dans les templates

## Logs et Débogage

Les logs sont enregistrés dans le fichier error_log du serveur:
- Génération des PDF
- Envoi des emails
- Erreurs éventuelles

Exemple:
```
Nouvelle quittance créée: ID 123
PDF de quittance généré avec succès: /path/to/pdf
Erreur envoi email quittance à locataire@example.com
```

## Maintenance

### Nettoyage des Anciennes Quittances

Si nécessaire, vous pouvez supprimer les anciennes quittances:

```sql
-- Supprimer les quittances de plus de 5 ans
DELETE FROM quittances 
WHERE date_generation < DATE_SUB(NOW(), INTERVAL 5 YEAR);
```

### Réinitialiser le Template

Pour revenir au template par défaut:
1. Allez dans Configuration Quittances
2. Cliquez sur "Réinitialiser au modèle par défaut"

## Support

Pour toute question ou problème:
1. Vérifiez les logs du serveur
2. Assurez-vous que les migrations ont été exécutées
3. Vérifiez la configuration SMTP pour l'envoi d'emails
4. Consultez la configuration des templates

## Évolutions Futures

Améliorations possibles:
- Export en masse (toutes les quittances d'un contrat)
- Génération automatique mensuelle via cron
- Statistiques de génération et d'envoi
- Rappels automatiques pour les locataires
- Intégration avec un système de paiement
