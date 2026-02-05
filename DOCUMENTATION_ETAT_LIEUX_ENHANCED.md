# Module État des Lieux - Documentation des Améliorations

## Vue d'ensemble

Cette documentation décrit les améliorations complètes apportées au module État des Lieux pour répondre au cahier des charges. Le système permet désormais de gérer efficacement les états des lieux d'entrée et de sortie avec support multi-locataires, comparaison automatique, et valeurs par défaut configurables.

## Fonctionnalités Implémentées

### 1. Interface Utilisateur Modernisée

#### Liste des États des Lieux (etats-lieux.php)
- **Affichage en tableau** : Remplacement de l'affichage en cartes par un tableau DataTables professionnel
- **Onglets séparés** : Distinction claire entre "États des lieux d'entrée" et "États des lieux de sortie"
- **Recherche en temps réel** : Recherche instantanée par référence, contrat, adresse, locataire
- **Filtres** : Filtrage par statut (brouillon, finalisé, envoyé)
- **Tri et pagination** : Gestion efficace de grandes quantités de données
- **Bouton Comparer** : Ajout automatique pour les contrats ayant entrée ET sortie

#### Actions Disponibles
- **Modifier** : Éditer un état des lieux existant
- **Voir** : Consultation en lecture seule
- **Comparer** : Comparaison côte à côte entrée/sortie (si disponible)
- **Télécharger** : Génération du PDF
- **Supprimer** : Suppression avec confirmation

### 2. Gestion Multi-Locataires

#### Fonctionnalités
- **Ajout dynamique de locataires** : Interface intuitive pour ajouter/supprimer des locataires
- **Informations individuelles** : Nom, prénom, email pour chaque locataire
- **Signatures individuelles** : Chaque locataire peut signer sur un canvas dédié
- **Support tactile** : Signatures possibles à la souris ou au doigt (tablettes)
- **Sauvegarde des signatures** : Stockage des signatures au format JPEG base64
- **Horodatage** : Date, heure et IP de chaque signature
- **Persistance** : Les locataires et signatures sont chargés lors de l'édition

#### Utilisation
1. Naviguer vers la section "Gestion multi-locataires" dans le formulaire
2. Cliquer sur "Ajouter un locataire"
3. Remplir nom, prénom, email
4. Signer dans le canvas
5. Répéter pour chaque locataire
6. Enregistrer le formulaire

### 3. Comparaison Entrée/Sortie

#### Page de Comparaison (compare-etat-lieux.php)
Accessible via le bouton "Comparer" dans la liste des états des lieux.

**Sections de comparaison :**

1. **Compteurs**
   - Électricité : Index entrée vs sortie + consommation calculée
   - Eau froide : Index entrée vs sortie + consommation calculée

2. **Clés**
   - Nombre de clés d'appartement : Comparaison avec indicateur de conformité
   - Clés de boîte aux lettres : Comparaison avec indicateur de conformité
   - Conformité générale : Badge visuel (Conforme/Non conforme)

3. **État des Pièces**
   - Pièce principale : Texte côte à côte
   - Coin cuisine : Texte côte à côte
   - Salle d'eau et WC : Texte côte à côte
   - État général : Texte côte à côte

4. **Bilan Financier** (si applicable)
   - Décision dépôt de garantie (restitution totale/partielle/retenue)
   - Montant retenu sur caution
   - Motif détaillé de la retenue
   - Détails des dégradations

**Codes couleur :**
- Bleu clair : Valeurs d'entrée
- Orange clair : Valeurs de sortie
- Vert : Conformité validée
- Rouge : Non-conformité détectée

### 4. Valeurs par Défaut

#### Migration 028
Ajout de colonnes à la table `logements` :
```sql
default_cles_appartement INT DEFAULT 2
default_cles_boite_lettres INT DEFAULT 1
default_equipements JSON NULL
default_etat_piece_principale TEXT NULL
default_etat_cuisine TEXT NULL
default_etat_salle_eau TEXT NULL
```

#### Comportement

**Pour un État d'Entrée :**
1. Les valeurs par défaut du logement sont chargées
2. Le formulaire est pré-rempli avec ces valeurs
3. L'utilisateur peut les modifier si nécessaire
4. Défauts standards si non configurés dans le logement

**Pour un État de Sortie :**
1. Le système recherche l'état d'entrée correspondant
2. Les valeurs de l'entrée sont copiées (clés, descriptions de pièces)
3. Facilite la comparaison et la saisie
4. L'utilisateur ajuste selon l'état réel de sortie

### 5. Formulaire Complet d'Édition

#### Sections du Formulaire

**1. Identification**
- Date de l'état des lieux
- Adresse du logement
- Nom du bailleur (pré-rempli)
- Nom complet du/des locataire(s)
- Email du locataire (pour envoi PDF)
- Section multi-locataires (voir ci-dessus)

**2. Relevé des Compteurs**
- Électricité : Index en kWh + photo optionnelle
- Eau froide : Index en m³ + photo optionnelle

**3. Remise/Restitution des Clés**
- Nombre de clés appartement
- Nombre de clés boîte aux lettres
- Total calculé automatiquement
- Photo des clés (optionnel)
- Conformité (sortie uniquement)
- Observations

**4. Description du Logement**
- Pièce principale : État détaillé + photos
- Coin cuisine : État détaillé + photos
- Salle d'eau et WC : État détaillé + photos
- État général : Observations + photos
- Conformité à l'entrée (sortie uniquement)
- Dégradations constatées (sortie uniquement)

**5. Conclusion - Dépôt de Garantie** (sortie uniquement)
- Décision : Restitution totale/partielle/retenue totale
- Montant retenu (si applicable)
- Justificatif/motif de retenue

**6. Signatures**
- Lieu de signature
- Observations complémentaires
- Signature du bailleur (canvas)
- Signature du locataire principal (canvas)
- Signatures des locataires additionnels (dans section multi-locataires)

## Architecture Technique

### Base de Données

#### Tables Utilisées

**etats_lieux**
- Table principale contenant tous les champs de l'état des lieux
- Colonnes ajoutées par migrations 026 et 027
- Stocke les signatures principales (bailleur + 1er locataire)

**etat_lieux_locataires**
- Table de liaison pour les locataires additionnels
- Colonnes : id, etat_lieux_id, locataire_id, ordre, nom, prenom, email
- Signatures individuelles : signature_data, signature_timestamp, signature_ip

**etat_lieux_photos**
- Stockage des photos par catégorie
- Catégories : compteur_electricite, compteur_eau, cles, piece_principale, cuisine, salle_eau, autre

**logements** (modifié)
- Nouvelles colonnes pour valeurs par défaut
- Permet la configuration par logement

### Fichiers Modifiés

**admin-v2/etats-lieux.php**
- Conversion de cartes en tableau DataTables
- Ajout des onglets Entrée/Sortie
- Intégration de la recherche et des filtres
- Ajout du bouton Comparer

**admin-v2/edit-etat-lieux.php**
- Ajout de la section multi-locataires
- Gestion des signatures multiples
- Sauvegarde/chargement des locataires
- JavaScript pour canvas de signature dynamique

**admin-v2/create-etat-lieux.php**
- Chargement des valeurs par défaut du logement
- Pré-remplissage intelligent selon le type
- Copie de l'entrée vers la sortie

### Fichiers Créés

**admin-v2/compare-etat-lieux.php**
- Page dédiée à la comparaison
- Affichage côte à côte
- Calculs de consommation
- Indicateurs de conformité

**migrations/028_add_logement_default_values.php**
- Migration pour les valeurs par défaut
- Ajout des colonnes à la table logements
- Initialisation des valeurs existantes

## Technologies Utilisées

- **PHP 7.4+** : Langage backend
- **MySQL/MariaDB** : Base de données
- **Bootstrap 5.3** : Framework CSS
- **jQuery 3.7** : Manipulation DOM
- **DataTables 1.13.6** : Tableaux interactifs
- **Canvas API** : Signatures électroniques
- **TCPDF** : Génération PDF (existant, à améliorer)

## Workflow Utilisateur

### Création d'un État d'Entrée

1. Naviguer vers "États des lieux"
2. Cliquer sur "Nouvel état d'entrée" dans l'onglet "États des lieux d'entrée"
3. Sélectionner le contrat
4. Choisir la date
5. Cliquer "Créer"
6. Le formulaire s'ouvre pré-rempli avec les valeurs par défaut du logement
7. Compléter les informations (compteurs, clés, état des pièces)
8. Ajouter des photos si nécessaire
9. Ajouter des locataires additionnels si besoin
10. Signer (bailleur et locataire)
11. Enregistrer comme brouillon OU finaliser et envoyer

### Création d'un État de Sortie

1. Naviguer vers "États des lieux"
2. Cliquer sur "Nouvel état de sortie" dans l'onglet "États des lieux de sortie"
3. Sélectionner le contrat (doit avoir un état d'entrée)
4. Choisir la date
5. Cliquer "Créer"
6. Le formulaire s'ouvre pré-rempli avec les valeurs de l'état d'entrée
7. Ajuster les informations selon l'état actuel
8. Remplir les sections spécifiques à la sortie :
   - Conformité des clés
   - Conformité générale
   - Dégradations constatées (si applicable)
   - Bilan du dépôt de garantie
9. Signer (bailleur et locataires)
10. Finaliser et envoyer

### Comparaison Entrée/Sortie

1. Depuis la liste des états des lieux
2. Repérer un contrat ayant entrée ET sortie (icône comparaison visible)
3. Cliquer sur le bouton "Comparer" (icône flèches)
4. Consulter la page de comparaison
5. Vérifier les différences
6. Imprimer ou télécharger si nécessaire

## Sécurité

### Mesures de Sécurité Implémentées

1. **Validation des entrées**
   - Vérification des types de données
   - Validation des formats (dates, emails)
   - Sanitization des données HTML

2. **Requêtes préparées**
   - Toutes les requêtes SQL utilisent des prepared statements
   - Protection contre les injections SQL

3. **Authentification**
   - Vérification de session via `auth.php`
   - Contrôle d'accès aux pages admin

4. **Validation des signatures**
   - Vérification du format base64
   - Validation du type MIME (image/jpeg)

5. **Upload de fichiers**
   - Validation des types de fichiers
   - Limite de taille (5MB)
   - Stockage sécurisé

## Améliorations Futures

### Phase 6 : PDF Amélioré (À implémenter)

1. **Intégration multi-locataires dans PDF**
   - Affichage de toutes les signatures
   - Section dédiée par locataire

2. **Tableau de comparaison dans PDF de sortie**
   - Tableau entrée/sortie côte à côte
   - Mise en évidence des différences

3. **Bilan financier dans PDF**
   - Section dédiée au dépôt de garantie
   - Calculs détaillés des retenues
   - Justificatifs référencés

### Phase 7 : Tests et Documentation

1. **Tests fonctionnels**
   - Test de création d'entrée avec valeurs par défaut
   - Test de création de sortie avec copie d'entrée
   - Test d'ajout/suppression de locataires
   - Test de signatures multiples
   - Test de comparaison

2. **Guide utilisateur**
   - Tutoriels avec captures d'écran
   - Vidéos de démonstration
   - FAQ

## Support et Maintenance

### Logs

Les logs se trouvent dans :
- PHP error log : `/var/log/php/error.log`
- Application logs : Utiliser `error_log()` pour tracer les erreurs

### Débogage

Pour activer le mode debug :
```php
// Dans includes/config.php
$config['debug'] = true;
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Base de Données

Pour appliquer les migrations :
```bash
php migrations/026_fix_etats_lieux_schema.php
php migrations/027_enhance_etats_lieux_comprehensive.php
php migrations/028_add_logement_default_values.php
```

## Conclusion

Le module État des Lieux est maintenant conforme aux exigences du cahier des charges avec :

✅ Interface moderne avec tableau et onglets  
✅ Gestion multi-locataires avec signatures individuelles  
✅ Valeurs par défaut configurables par logement  
✅ Comparaison automatique entrée/sortie  
✅ Bilan financier intégré  
✅ Code sécurisé et documenté  

Prêt pour utilisation en production après tests finaux et amélioration du PDF.
