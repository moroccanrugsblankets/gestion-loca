# Fix: Signature de l'agence ne s'affiche plus sur le PDF du contrat de bail

## Problème

La signature de l'agence (MY INVEST IMMOBILIER) ne s'affichait plus sur les PDFs des contrats de bail validés.

## Cause Racine

Le code dans `pdf/generate-contrat-pdf.php`, fonction `buildSignaturesTable()`, ne vérifiait **PAS** si le paramètre `signature_societe_enabled` était activé avant d'afficher la signature de l'agence.

### Code Défectueux (Avant)

```php
if ($contrat['statut'] === 'valide') {
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'");
    $stmt->execute();
    $signatureSociete = $stmt->fetchColumn();

    if (!empty($signatureSociete) && preg_match('/^uploads\/signatures\//', $signatureSociete)) {
        $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($signatureSociete, '/');
        $html .= '<img src="..." alt="Signature Société" ...>';
    }
}
```

**Problème**: Le code récupérait directement l'image de signature sans vérifier si la fonctionnalité était activée via `signature_societe_enabled`.

## Solution

Ajout de la vérification du paramètre `signature_societe_enabled` AVANT de tenter d'afficher la signature.

### Code Corrigé (Après)

```php
if ($contrat['statut'] === 'valide') {
    // Check if signature feature is enabled using getParameter
    $signatureEnabled = getParameter('signature_societe_enabled', false);
    $isSignatureEnabled = toBooleanParam($signatureEnabled);
    
    if ($isSignatureEnabled) {
        $signatureSociete = getParameter('signature_societe_image', '');

        if (!empty($signatureSociete) && preg_match('/^uploads\/signatures\//', $signatureSociete)) {
            $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($signatureSociete, '/');
            $html .= '<img src="..." alt="Signature Société" ...>';
        }
    }
}
```

## Changements Apportés

### Fichier: `pdf/generate-contrat-pdf.php`

1. **Ajout de la vérification `signature_societe_enabled`**:
   - Utilise `getParameter('signature_societe_enabled', false)` pour récupérer le paramètre
   - Utilise `toBooleanParam()` pour convertir correctement les valeurs booléennes

2. **Alignement avec `generate-bail.php`**:
   - Le code suit maintenant le même pattern que celui utilisé dans `generate-bail.php` (lignes 351-363)
   - Cohérence entre les deux fichiers de génération PDF

3. **Import de la fonction helper**:
   - Ajout de `require_once __DIR__ . '/../includes/functions.php';` pour accéder aux fonctions `getParameter()` et `toBooleanParam()`

## Conditions pour l'affichage de la signature

La signature de l'agence s'affiche UNIQUEMENT si **TOUTES** ces conditions sont remplies:

1. ✅ Le contrat a le statut `'valide'`
2. ✅ Le paramètre `signature_societe_enabled` est défini à `true` ou `'1'`
3. ✅ Le paramètre `signature_societe_image` contient un chemin valide
4. ✅ Le chemin de l'image commence par `'uploads/signatures/'`

## Comment Activer la Signature

### 1. Vérifier que les paramètres existent dans la base de données

```sql
SELECT cle, valeur, type FROM parametres WHERE cle LIKE '%signature_societe%';
```

Devrait retourner:
- `signature_societe_enabled` (type: boolean, valeur: 'true' ou 'false')
- `signature_societe_image` (type: string, valeur: chemin vers l'image)

### 2. Si les paramètres n'existent pas

Exécuter la migration:
```bash
php run-migrations.php
```

Ou manuellement:
```sql
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('signature_societe_image', '', 'string', 'Image de la signature électronique de la société', 'contrats'),
('signature_societe_enabled', 'false', 'boolean', 'Activer l''ajout automatique de la signature société', 'contrats')
ON DUPLICATE KEY UPDATE cle=cle;
```

### 3. Activer la signature

```sql
UPDATE parametres SET valeur = 'true' WHERE cle = 'signature_societe_enabled';
```

### 4. Uploader l'image de signature

Via l'interface admin ou directement:
```sql
UPDATE parametres 
SET valeur = 'uploads/signatures/company_signature.png' 
WHERE cle = 'signature_societe_image';
```

L'image doit être physiquement présente dans le dossier `uploads/signatures/`.

## Test de Validation

Pour vérifier que la correction fonctionne:

1. S'assurer que `signature_societe_enabled = 'true'`
2. S'assurer qu'une image existe dans `uploads/signatures/`
3. Générer un PDF pour un contrat avec statut `'valide'`
4. Vérifier que la signature de l'agence apparaît sur le PDF

## Compatibilité

- ✅ Compatible avec les signatures stockées comme fichiers physiques (`uploads/signatures/...`)
- ✅ Compatible avec le système de paramètres existant
- ✅ Cohérent avec la logique dans `generate-bail.php`
- ✅ Utilise les fonctions helper existantes (`getParameter`, `toBooleanParam`)

## Fichiers Modifiés

- `pdf/generate-contrat-pdf.php` - Fonction `buildSignaturesTable()`

## Référence

- Migration: `migrations/020_add_contract_signature_and_workflow.sql`
- Fonctions helper: `includes/functions.php` (lignes 583-612 pour `getParameter`, 884-892 pour `toBooleanParam`)
