# Auto-sélection du Logement dans le Formulaire de Candidature

## Description

Cette fonctionnalité permet d'automatiquement pré-sélectionner un logement spécifique dans le formulaire de candidature en utilisant un paramètre URL crypté.

## Fonctionnement

### 1. Création du lien

Pour chaque page de logement, créez un lien vers le formulaire de candidature avec le paramètre `ref` contenant le hash MD5 de la référence du logement :

```php
<a href="https://contrat.myinvest-immobilier.com/candidature/?ref=<?php echo md5('RP-01'); ?>">
    Postuler pour ce logement
</a>
```

### 2. Traitement automatique

Lorsqu'un utilisateur clique sur ce lien :

1. Le système récupère le paramètre `ref` de l'URL
2. Il compare ce hash MD5 avec tous les logements disponibles
3. Si une correspondance est trouvée, le logement est automatiquement sélectionné dans le formulaire

### 3. Exemple de code

#### Sur une page de logement individuel :

```php
<?php
$reference = 'RP-01'; // Référence du logement
$md5_hash = md5($reference);
?>

<a href="https://contrat.myinvest-immobilier.com/candidature/?ref=<?php echo $md5_hash; ?>" 
   class="btn btn-primary">
    Postuler pour ce logement
</a>
```

#### Dans une boucle de logements :

```php
<?php foreach ($logements as $logement): ?>
    <div class="logement-card">
        <h3><?php echo htmlspecialchars($logement['reference']); ?></h3>
        <p><?php echo htmlspecialchars($logement['adresse']); ?></p>
        
        <a href="https://contrat.myinvest-immobilier.com/candidature/?ref=<?php echo md5($logement['reference']); ?>" 
           class="btn btn-primary">
            Candidater
        </a>
    </div>
<?php endforeach; ?>
```

## Exemples de liens générés

| Référence Logement | Hash MD5 | URL Complète |
|-------------------|----------|--------------|
| RP-01 | 4e732ced3463d06de0ca9a15b6153677 | candidature/?ref=4e732ced3463d06de0ca9a15b6153677 |
| RP-02 | 3b8dca3d42e2c50626c0c5a950f3a095 | candidature/?ref=3b8dca3d42e2c50626c0c5a950f3a095 |
| RP-03 | f73fc7e5b729c3d1555f95b7b174f5fc | candidature/?ref=f73fc7e5b729c3d1555f95b7b174f5fc |

## Test de la fonctionnalité

Un script de test a été créé pour vérifier que la fonctionnalité fonctionne correctement :

```bash
# Accéder au script de test dans votre navigateur :
https://votre-domaine.com/test-candidature-auto-selection.php
```

Ce script affichera :
- Les références de logements disponibles
- Leurs hash MD5 correspondants
- Des liens de test cliquables
- Une validation de la logique de matching

## Sécurité

- Le hash MD5 est utilisé uniquement pour masquer la référence dans l'URL, pas pour la sécurité
- Seuls les logements avec le statut `'disponible'` sont accessibles
- La validation complète du formulaire reste en place
- Aucun accès privilégié n'est accordé par cette fonctionnalité

## Comportement

- **Si le paramètre `ref` est valide** : Le logement correspondant est pré-sélectionné
- **Si le paramètre `ref` est invalide ou absent** : L'utilisateur voit la liste complète et doit sélectionner manuellement
- **Si le logement n'est plus disponible** : Il n'apparaît pas dans la liste et ne peut pas être sélectionné

## Code source modifié

Les modifications ont été apportées dans :
- `candidature/index.php` : Logique de traitement du paramètre `ref` et auto-sélection

## Maintenance

Pour ajouter cette fonctionnalité à d'autres pages :

1. Récupérez la référence du logement
2. Générez le hash MD5 avec `md5($reference)`
3. Créez un lien vers `candidature/?ref={hash_md5}`

Aucune autre modification n'est nécessaire, le formulaire gère automatiquement la sélection.
