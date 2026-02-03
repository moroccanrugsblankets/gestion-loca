# Centralisation de la Configuration des Contrats

## Problème Résolu

**Avant**: Double configuration confusante
- `/admin-v2/parametres.php` affichait les paramètres de contrat
- `/admin-v2/contrat-configuration.php` avait aussi une interface dédiée
- Les utilisateurs pouvaient modifier les mêmes paramètres à deux endroits différents

**Après**: Configuration unique et claire
- `/admin-v2/parametres.php` ne montre plus les paramètres de contrat
- `/admin-v2/contrat-configuration.php` est la seule page pour gérer ces paramètres
- Bannière d'information guide les utilisateurs vers la bonne page

## Paramètres Centralisés

Les paramètres suivants sont maintenant uniquement gérés dans `/admin-v2/contrat-configuration.php`:

1. **contrat_template_html**
   - Template HTML du contrat avec variables dynamiques
   - Interface complète avec éditeur TinyMCE
   - Prévisualisation en temps réel
   - Variables cliquables pour copie facile

2. **signature_societe_image**
   - Image de la signature électronique (base64)
   - Upload d'image avec redimensionnement automatique
   - Aperçu de l'image actuelle
   - Bouton de suppression

3. **signature_societe_enabled**
   - Activer/désactiver la signature automatique
   - Checkbox simple dans le formulaire d'upload
   - Mise à jour lors de l'upload de signature

## Changements Techniques

### `/admin-v2/parametres.php`

**Ligne 38-39 - Requête SQL modifiée:**
```php
// AVANT
$stmt = $pdo->query("SELECT * FROM parametres ORDER BY groupe, cle");

// APRÈS
// Exclude 'contrats' group as it's managed in contrat-configuration.php
$stmt = $pdo->query("SELECT * FROM parametres WHERE groupe != 'contrats' ORDER BY groupe, cle");
```

**Lignes 111-117 - Bannière d'information ajoutée:**
```html
<div class="alert alert-info alert-dismissible fade show">
    <i class="bi bi-info-circle"></i>
    <strong>Configuration des contrats :</strong> 
    Les paramètres relatifs aux contrats (template HTML, signature électronique) sont gérés dans 
    <a href="contrat-configuration.php" class="alert-link"><strong>Configuration du Template de Contrat</strong></a>.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

### `/admin-v2/contrat-configuration.php`

**Aucun changement nécessaire** - Cette page gérait déjà correctement tous les paramètres:
- ✅ Interface complète de gestion du template HTML
- ✅ Interface complète de gestion de la signature
- ✅ Toutes les opérations CRUD fonctionnelles
- ✅ Validation et sécurité en place

## Navigation

La page `contrat-configuration.php` est accessible via:
1. **Menu latéral**: Contrats > Configuration
2. **Lien direct** dans la bannière de parametres.php
3. **URL directe**: `/admin-v2/contrat-configuration.php`

## Avantages

### Pour les Utilisateurs
- ✅ **Plus de confusion** - un seul endroit pour gérer les contrats
- ✅ **Interface dédiée** - mieux adaptée aux besoins spécifiques
- ✅ **Guidance claire** - bannière informative si on arrive sur parametres.php

### Pour les Développeurs
- ✅ **Séparation des responsabilités** - chaque page a un rôle clair
- ✅ **Maintenance simplifiée** - modifications centralisées
- ✅ **Code plus propre** - filtre SQL simple et explicite

### Pour la Sécurité
- ✅ **Pas de duplication de code** - moins de risques d'incohérence
- ✅ **Validation centralisée** - toutes les validations au même endroit
- ✅ **Audit facilité** - un seul point de modification à surveiller

## Tests à Effectuer

### Test 1: Vérifier que parametres.php n'affiche plus les paramètres de contrat
1. Accéder à `/admin-v2/parametres.php`
2. ✅ Vérifier qu'aucune section "Contrats" n'apparaît
3. ✅ Vérifier que la bannière d'information est présente
4. ✅ Cliquer sur le lien vers contrat-configuration.php

### Test 2: Vérifier que contrat-configuration.php fonctionne correctement
1. Accéder à `/admin-v2/contrat-configuration.php`
2. ✅ Modifier le template HTML et sauvegarder
3. ✅ Uploader une signature et activer l'option
4. ✅ Vérifier que les modifications sont bien enregistrées
5. ✅ Supprimer la signature

### Test 3: Vérifier que les paramètres sont utilisés correctement
1. Créer un nouveau contrat
2. ✅ Vérifier que le template HTML est appliqué
3. ✅ Vérifier que la signature est ajoutée si activée
4. ✅ Générer le PDF et vérifier le rendu

## Fichiers Modifiés

```
modified:   admin-v2/parametres.php
```

## Impact

- **Aucune modification de la base de données** - les paramètres restent inchangés
- **Aucune modification du comportement** - seule l'interface change
- **Aucun impact sur les fonctionnalités existantes** - tout continue de fonctionner
- **Rétrocompatible** - les anciennes références continuent de fonctionner

## Documentation

Ce changement améliore l'architecture sans modifier le comportement fonctionnel.
Les développeurs doivent savoir que:
- Les paramètres du groupe 'contrats' ne sont pas affichés dans parametres.php
- Pour ajouter un nouveau paramètre de contrat, l'ajouter dans contrat-configuration.php
- La navigation vers contrat-configuration.php est disponible dans le menu

