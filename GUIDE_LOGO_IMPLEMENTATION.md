# Guide d'Implémentation - Logo de la Société

## Vue d'ensemble

Cette fonctionnalité ajoute un logo configurable de la société dans le menu d'administration, remplaçant le texte "MY Invest Immobilier" par une image.

## Fichiers Modifiés

### 1. Base de données
- **`migrations/040_add_logo_societe_parameter.sql`** - Migration pour ajouter le paramètre

### 2. Code PHP
- **`admin-v2/includes/menu.php`** - Affichage du logo dans le menu
- **`admin-v2/parametres.php`** - Configuration du logo

### 3. Nouveaux fichiers
- **`assets/images/logo-my-invest-immobilier-carre.svg`** - Logo temporaire (à remplacer)
- **`assets/images/README_LOGO.md`** - Instructions
- **`test-logo-implementation.php`** - Script de test
- **`LOGO_IMPLEMENTATION_PREVIEW.html`** - Documentation visuelle

## Installation

### Étape 1: Exécuter la migration

```bash
php run-migrations.php
```

Cela ajoutera le paramètre `logo_societe` dans la table `parametres` avec la valeur par défaut:
```
/assets/images/logo-my-invest-immobilier-carre.jpg
```

### Étape 2: Ajouter le fichier logo

Placez votre logo de société à l'emplacement:
```
/assets/images/logo-my-invest-immobilier-carre.jpg
```

**Recommandations:**
- Format: JPG, PNG, ou SVG
- Taille: 100x100 à 200x200 pixels (carré de préférence)
- Poids: < 500 KB
- Fond: Transparent (PNG/SVG) ou blanc

### Étape 3: Vérifier l'affichage

1. Connectez-vous à l'interface d'administration
2. Le logo devrait apparaître dans le menu latéral gauche
3. Si le logo n'apparaît pas, vérifiez:
   - Le fichier existe au bon chemin
   - Les permissions du fichier sont correctes (644)
   - Le chemin dans la base de données est correct

## Configuration

### Via l'interface Paramètres

1. Accédez à **Paramètres** dans le menu admin
2. Trouvez le paramètre **"Logo de la société"** dans la section Général
3. Modifiez le chemin si nécessaire
4. Un aperçu du logo s'affiche automatiquement
5. Cliquez sur **"Enregistrer les paramètres"**

### Via la base de données (alternative)

```sql
UPDATE parametres 
SET valeur = '/assets/images/votre-logo.jpg' 
WHERE cle = 'logo_societe';
```

## Formats supportés

- ✅ JPG/JPEG
- ✅ PNG
- ✅ SVG
- ✅ GIF
- ✅ WebP

## Fonctionnement

### Affichage avec logo
Quand le paramètre `logo_societe` existe et que le fichier est trouvé:
```html
<img src="/assets/images/logo-my-invest-immobilier-carre.jpg" 
     alt="Logo société" 
     style="max-width: 100%; max-height: 80px;">
```

### Affichage sans logo (fallback)
Quand le logo n'est pas trouvé ou le paramètre n'existe pas:
```html
<i class="bi bi-building"></i>
<h4>MY Invest</h4>
<small>Immobilier</small>
```

## Test

Exécutez le script de test:
```bash
php test-logo-implementation.php
```

Résultat attendu:
```
=== Test Summary ===
✓ All tests passed! Implementation looks good.
```

## Dépannage

### Le logo ne s'affiche pas

**Problème**: Le logo n'apparaît pas dans le menu

**Solutions**:
1. Vérifiez que la migration a été exécutée:
   ```sql
   SELECT * FROM parametres WHERE cle = 'logo_societe';
   ```

2. Vérifiez que le fichier existe:
   ```bash
   ls -la /assets/images/logo-my-invest-immobilier-carre.jpg
   ```

3. Vérifiez les permissions:
   ```bash
   chmod 644 /assets/images/logo-my-invest-immobilier-carre.jpg
   ```

4. Vérifiez le chemin dans la base de données correspond au fichier

### Message d'erreur "Logo non trouvé"

**Problème**: Message d'erreur dans la page Paramètres

**Solutions**:
1. Le chemin dans la base de données est incorrect
2. Le fichier n'existe pas à l'emplacement spécifié
3. Mettez à jour le chemin via la page Paramètres

### Le logo est trop grand/petit

**Problème**: Le logo ne s'affiche pas correctement

**Solutions**:
1. Le CSS limite automatiquement à 80px de hauteur
2. Pour modifier, éditez `admin-v2/includes/menu.php`:
   ```php
   style="max-width: 100%; max-height: 120px;"  // Augmenter à 120px
   ```

## Sécurité

### Mesures de sécurité implémentées

- ✅ Échappement HTML (`htmlspecialchars()`)
- ✅ Requêtes préparées (protection SQL injection)
- ✅ Vérification d'existence du fichier
- ✅ Authentification requise pour modification
- ✅ Pas d'exécution de fichiers

### Recommandations

1. Ne pas permettre l'upload de fichiers sans validation
2. Limiter les formats de fichiers acceptés
3. Valider la taille des fichiers
4. Stocker les fichiers avec permissions restrictives

## Migration depuis l'ancienne version

Si vous aviez un logo hardcodé dans le code:

1. Exécutez la migration
2. Copiez votre logo vers `/assets/images/`
3. Mettez à jour le paramètre dans la base de données
4. Le nouveau système prendra effet automatiquement

## Support

Pour toute question ou problème:
1. Consultez `LOGO_IMPLEMENTATION_PREVIEW.html` pour la documentation visuelle
2. Exécutez `test-logo-implementation.php` pour diagnostiquer
3. Vérifiez `SECURITY_SUMMARY_LOGO.md` pour les aspects sécurité

## Roadmap / Améliorations futures

Fonctionnalités qui pourraient être ajoutées:
- [ ] Upload de logo via l'interface
- [ ] Redimensionnement automatique
- [ ] Validation du format de fichier
- [ ] Support de plusieurs logos (thème clair/sombre)
- [ ] Cache du logo pour performances

---

**Version**: 1.0  
**Date**: 2026-02-09  
**Statut**: ✅ Production Ready
