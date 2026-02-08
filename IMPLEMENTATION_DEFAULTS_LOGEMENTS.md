# Implémentation : Bouton "Valeurs par Défaut" pour les Logements

## Résumé

Cette fonctionnalité permet aux administrateurs de définir des valeurs par défaut pour chaque logement. Ces valeurs seront automatiquement utilisées lors de la création d'un état des lieux d'entrée.

## Changements Apportés

### Fichier Modifié
- `/admin-v2/logements.php` (+205 lignes, -2 lignes)

### 1. Backend - Handler POST (lignes 89-167)

Nouveau cas `set_defaults` ajouté dans le switch statement avec :

**Validation stricte :**
- Validation des clés numériques (0-100 pour chaque type)
- Validation de la longueur des textes (max 5000 caractères)
- Sanitization avec `trim()` sur tous les champs texte
- Messages d'erreur appropriés pour chaque type d'erreur

**Valeurs par défaut :**
```php
// Clés
$cles_appartement: 2 clés par défaut
$cles_boite_lettres: 1 clé par défaut

// Descriptions de pièces (si vides)
- Pièce principale et cuisine : "• Revêtement de sol : parquet..."
- Salle d'eau : "• Revêtement de sol : carrelage..."
```

### 2. Requête SQL (lignes 175-182)

**Avant :**
```sql
SELECT * FROM logements WHERE 1=1
```

**Après :**
```sql
SELECT id, reference, adresse, appartement, type, surface, loyer, charges, 
       depot_garantie, parking, statut, date_disponibilite, created_at, updated_at,
       COALESCE(default_cles_appartement, 2) as default_cles_appartement,
       COALESCE(default_cles_boite_lettres, 1) as default_cles_boite_lettres,
       default_etat_piece_principale,
       default_etat_cuisine,
       default_etat_salle_eau
FROM logements WHERE 1=1
```

**Raisons du changement :**
- Évite les colonnes dupliquées (problème identifié en code review)
- Sélection explicite des colonnes nécessaires
- COALESCE pour fournir des valeurs par défaut si NULL

### 3. UI - Bouton dans la Table (lignes 433-445)

Nouveau bouton ajouté dans la colonne Actions :

```html
<button class="btn btn-outline-info defaults-btn" 
        data-id="..."
        data-reference="..."
        data-default-cles-appartement="..."
        data-default-cles-boite-lettres="..."
        data-default-etat-piece-principale="..."
        data-default-etat-cuisine="..."
        data-default-etat-salle-eau="..."
        data-bs-toggle="modal" 
        data-bs-target="#setDefaultsModal"
        title="Définir les valeurs par défaut">
    <i class="bi bi-gear"></i>
</button>
```

**Caractéristiques :**
- Classe `btn-outline-info` pour couleur cyan
- Icône engrenage (`bi-gear`)
- Toutes les valeurs actuelles stockées en data attributes
- Tooltip descriptif

### 4. UI - Modal Bootstrap (lignes 661-742)

Modal complet avec :

**Structure :**
- Titre dynamique avec référence du logement
- Form POST vers `logements.php`
- Deux sections principales

**Section 1 - Remise des clés :**
```html
<input type="number" name="default_cles_appartement" 
       min="0" max="100" value="2" required>
<input type="number" name="default_cles_boite_lettres" 
       min="0" max="100" value="1" required>
```

**Section 2 - Descriptions :**
```html
<textarea name="default_etat_piece_principale" 
          rows="4" maxlength="5000" placeholder="..."></textarea>
<textarea name="default_etat_cuisine" 
          rows="4" maxlength="5000" placeholder="..."></textarea>
<textarea name="default_etat_salle_eau" 
          rows="4" maxlength="5000" placeholder="..."></textarea>
```

**Éléments UX :**
- Note informative en haut du modal
- Placeholders avec exemples de texte
- Texte d'aide sous chaque textarea
- Alert info expliquant pourquoi les compteurs ne sont pas configurables

### 5. JavaScript (lignes 775-787)

Handler pour le bouton defaults :

```javascript
document.querySelectorAll('.defaults-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Remplir les champs cachés
        document.getElementById('defaults_id').value = this.dataset.id;
        document.getElementById('defaults_reference').textContent = this.dataset.reference;
        
        // Remplir les champs du formulaire
        document.getElementById('defaults_cles_appartement').value = 
            this.dataset.defaultClesAppartement || '2';
        // ... etc
    });
});
```

## Utilisation des Valeurs par Défaut

Ces valeurs sont utilisées par `/admin-v2/create-etat-lieux.php` lors de la création d'un état des lieux d'entrée.

**Processus existant (non modifié) :**

```php
// Dans create-etat-lieux.php
if ($type === 'entree') {
    $default_cles_appartement = (int)($contrat['default_cles_appartement'] ?? 2);
    $default_cles_boite_lettres = (int)($contrat['default_cles_boite_lettres'] ?? 1);
    
    $default_piece_principale = 
        $contrat['default_etat_piece_principale'] ?? $default_room_description;
    $default_coin_cuisine = 
        $contrat['default_etat_cuisine'] ?? $default_room_description;
    $default_salle_eau_wc = 
        $contrat['default_etat_salle_eau'] ?? $default_bathroom_description;
}
```

## Sécurité

### Validation Client-Side
- `min="0" max="100"` sur les inputs numériques
- `maxlength="5000"` sur les textareas
- `required` sur les champs clés

### Validation Server-Side
1. **Validation numérique :**
   ```php
   if ($cles_appartement < 0 || $cles_appartement > 100) {
       $_SESSION['error'] = "...";
       exit;
   }
   ```

2. **Validation longueur :**
   ```php
   if (strlen($piece_principale) > 5000) {
       $_SESSION['error'] = "...";
       exit;
   }
   ```

3. **Sanitization :**
   ```php
   $piece_principale = trim($_POST['default_etat_piece_principale']);
   ```

4. **Requête préparée :**
   ```php
   $stmt = $pdo->prepare("UPDATE logements SET ... WHERE id = ?");
   $stmt->execute([...]);
   ```

### Protection XSS
- `htmlspecialchars()` sur toutes les sorties dans les data attributes
- Validation stricte avant insertion en base

## Base de Données

### Colonnes (déjà existantes via migration 028)

```sql
ALTER TABLE logements 
ADD COLUMN default_cles_appartement INT DEFAULT 2,
ADD COLUMN default_cles_boite_lettres INT DEFAULT 1,
ADD COLUMN default_etat_piece_principale TEXT NULL,
ADD COLUMN default_etat_cuisine TEXT NULL,
ADD COLUMN default_etat_salle_eau TEXT NULL;
```

### Migration
```bash
php apply-migration.php 028
```

## Tests Recommandés

### Test 1 : Affichage du bouton
1. Aller sur `/admin-v2/logements.php`
2. Vérifier que le bouton ⚙️ apparaît dans chaque ligne
3. Vérifier le tooltip au survol

### Test 2 : Ouverture du modal
1. Cliquer sur le bouton ⚙️
2. Vérifier que le modal s'ouvre
3. Vérifier que la référence du logement est affichée dans le titre

### Test 3 : Valeurs par défaut
1. Ouvrir le modal pour un nouveau logement
2. Vérifier que les clés sont à 2 et 1
3. Vérifier que les textareas sont vides ou contiennent les valeurs actuelles

### Test 4 : Sauvegarde
1. Modifier les valeurs dans le modal
2. Cliquer sur "Enregistrer"
3. Vérifier le message de succès
4. Réouvrir le modal pour vérifier que les valeurs sont sauvegardées

### Test 5 : Validation
1. Tenter de saisir -1 pour les clés → Erreur attendue
2. Tenter de saisir 101 pour les clés → Erreur attendue
3. Tenter de saisir un texte de 6000 caractères → Erreur attendue

### Test 6 : Utilisation dans état des lieux
1. Définir des valeurs par défaut pour un logement
2. Créer un nouvel état des lieux d'entrée pour ce logement
3. Vérifier que les valeurs par défaut sont pré-remplies

## Compatibilité

- PHP 7.4+
- MySQL 5.7+ ou MariaDB 10.2+
- Bootstrap 5.3.0
- Bootstrap Icons 1.11.0

## Notes de Développement

### Choix de Design

1. **Modal au lieu de page séparée :**
   - Cohérent avec le pattern existant (edit/delete)
   - Expérience utilisateur plus fluide
   - Moins de navigation

2. **Valeurs par défaut strictes :**
   - 2 clés d'appartement (standard français)
   - 1 clé de boîte aux lettres (standard)
   - Descriptions pré-formatées avec bullet points

3. **Textareas optionnelles :**
   - Si vides, utilisation des valeurs hardcodées
   - Permet la personnalisation par logement
   - Fallback sur des valeurs sensées

4. **Pas de configuration des compteurs :**
   - Les relevés varient à chaque état des lieux
   - Doivent être saisis manuellement
   - Note explicative dans le modal

### Limitations Connues

1. Les compteurs ne sont pas configurables (par design)
2. Les valeurs par défaut ne s'appliquent qu'aux états d'entrée
3. Maximum 100 clés par type (validation arbitraire mais raisonnable)
4. Maximum 5000 caractères par description (largement suffisant)

## Améliorations Futures Possibles

1. **Prévisualisation :**
   - Aperçu de comment les valeurs apparaîtront dans l'état des lieux

2. **Templates :**
   - Permettre de créer des templates réutilisables
   - Ex: "Studio standard", "T2 rénové", etc.

3. **Import/Export :**
   - Copier les valeurs d'un logement à l'autre
   - Export CSV des configurations

4. **Historique :**
   - Tracer les modifications des valeurs par défaut
   - Utile pour l'audit

5. **Validation avancée :**
   - Vérifier la cohérence des descriptions
   - Suggérer des améliorations

## Conclusion

Cette fonctionnalité améliore significativement l'efficacité de la création d'états des lieux en :
- Réduisant la saisie manuelle répétitive
- Assurant la cohérence des descriptions
- Permettant la personnalisation par logement
- Maintenant la flexibilité pour les cas particuliers

Le code est robuste, bien validé et suit les patterns existants de l'application.
