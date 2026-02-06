# Guide d'Utilisation : Fichiers de Test HTML Preview

## 🎯 Objectif

Ces fichiers permettent de visualiser le HTML généré **AVANT** le traitement TCPDF pour diagnostiquer les problèmes de rendu PDF.

## 📁 Fichiers Disponibles

### 1. test-html-preview-contrat.php
Visualise le HTML de `pdf/generate-contrat-pdf.php`

### 2. test-html-preview-bail.php
Visualise le HTML de `pdf/generate-bail.php`

### 3. test-html-preview-etat-lieux.php
Visualise le HTML de `pdf/generate-etat-lieux.php`

---

## 🚀 Comment Utiliser

### Prérequis

1. ✅ Serveur PHP local actif (Apache, Nginx, ou `php -S`)
2. ✅ Base de données configurée avec des contrats de test
3. ✅ Fichiers `includes/config.php` et `includes/db.php` configurés

### Étape 1 : Démarrer le Serveur Local

```bash
# Option A : Avec un serveur web (Apache/Nginx)
# Aller sur http://localhost/

# Option B : Avec le serveur PHP intégré
cd /path/to/contrat-de-bail
php -S localhost:8000
# Aller sur http://localhost:8000/
```

### Étape 2 : Trouver un ID de Contrat de Test

```sql
-- Dans MySQL/PhpMyAdmin
SELECT id, reference, statut FROM contrats LIMIT 5;
```

Exemple de résultat :
```
+----+-------------+--------+
| id | reference   | statut |
+----+-------------+--------+
| 51 | CONT-2026-1 | valide |
| 52 | CONT-2026-2 | actif  |
+----+-------------+--------+
```

Utiliser un ID de cette liste (ex: `51`)

### Étape 3 : Ouvrir les Fichiers de Test

#### Pour Contrat

```
http://localhost/test-html-preview-contrat.php?id=51
```

Remplacer `51` par votre ID de contrat

#### Pour Bail

```
http://localhost/test-html-preview-bail.php?id=51
```

#### Pour État des Lieux d'Entrée

```
http://localhost/test-html-preview-etat-lieux.php?id=51&type=entree
```

#### Pour État des Lieux de Sortie

```
http://localhost/test-html-preview-etat-lieux.php?id=51&type=sortie
```

---

## 📊 Que Regarder

### ✅ Points à Vérifier (Doivent être Corrects)

1. **Signatures sans bordures**
   - Les images de signature ne doivent avoir AUCUNE bordure visible
   - Le fond doit être transparent

2. **Tailles des signatures appropriées**
   - Contrat: 150px de largeur
   - Bail: 50px × 25px (agence), 40px × 20px (locataire)
   - État des lieux: 50mm × 25mm

3. **Tableaux sans bordures externes**
   - Le tableau de signatures ne doit pas avoir de bordure noire autour
   - Les cellules peuvent avoir des bordures internes si spécifié dans le design

4. **Mise en page générale**
   - Les textes sont bien alignés
   - Les espacements sont corrects
   - Les polices sont lisibles

### ❌ Problèmes Potentiels

Si vous voyez des bordures dans le **HTML Preview** :
- ⚠️ Problème CSS à corriger dans le code
- Vérifier les styles inline dans le fichier PHP concerné

Si vous NE voyez PAS de bordures dans le **HTML Preview** mais qu'elles apparaissent dans le **PDF** :
- ✅ C'est normal - c'est le problème TCPDF documenté
- Voir `SOLUTION_BORDURES_TCPDF.md` pour explication

---

## 🔍 Diagnostic Étape par Étape

### Workflow de Diagnostic

```
┌─────────────────────────────────────────────────┐
│ 1. Ouvrir test-html-preview-*.php              │
│    → Vérifier le rendu HTML                     │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ 2. Le HTML a-t-il des bordures ?               │
└─────────────────────────────────────────────────┘
         ↓                           ↓
    ✅ NON                      ❌ OUI
         ↓                           ↓
┌──────────────────┐    ┌──────────────────────────┐
│ HTML correct !   │    │ Corriger le CSS dans     │
│                  │    │ generate-*.php           │
└──────────────────┘    └──────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│ 3. Générer le PDF correspondant                │
│    (avec test-pdf-generation.php ou admin)      │
└─────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│ 4. Le PDF a-t-il des bordures ?                │
└─────────────────────────────────────────────────┘
         ↓                           ↓
    ✅ NON                      ❌ OUI
         ↓                           ↓
┌──────────────────┐    ┌──────────────────────────┐
│ Parfait ! ✨     │    │ C'est TCPDF              │
│ Tout fonctionne  │    │ → Voir documentation     │
└──────────────────┘    │ SOLUTION_BORDURES_TCPDF  │
                        └──────────────────────────┘
```

---

## 💡 Exemples Concrets

### Exemple 1 : Tester un Contrat

```bash
# 1. Ouvrir le HTML Preview
http://localhost/test-html-preview-contrat.php?id=51

# 2. Vérifier visuellement
# - Signature agence : 150px de large, pas de bordure ✅
# - Signature locataire : 150px de large, pas de bordure ✅
# - Tableau : pas de bordure externe ✅

# 3. Générer le PDF correspondant
# Aller dans l'admin → Contrats → Générer PDF

# 4. Comparer
# HTML : Pas de bordures ✅
# PDF : Bordures présentes ❌ → Problème TCPDF connu
```

### Exemple 2 : Tester État des Lieux d'Entrée

```bash
# 1. Ouvrir le HTML Preview
http://localhost/test-html-preview-etat-lieux.php?id=51&type=entree

# 2. Vérifier les signatures
# - Taille : 50mm × 25mm (grandes et visibles) ✅
# - Bordures : Aucune ✅
# - Transparence : Préservée ✅

# 3. Générer le PDF
# Aller dans l'admin → États des lieux → Générer PDF

# 4. Comparer les rendus
```

---

## 🎨 Interprétation des Résultats

### Cas 1 : HTML ✅ / PDF ✅
```
┌──────────────┐     ┌──────────────┐
│ HTML Preview │     │  PDF Final   │
│              │     │              │
│  ┌────────┐  │     │  ┌────────┐  │
│  │[sign.] │  │     │  │[sign.] │  │
│  └────────┘  │     │  └────────┘  │
│              │     │              │
│ Pas bordure  │     │ Pas bordure  │
└──────────────┘     └──────────────┘
```
**→ Parfait ! Aucun problème** ✨

### Cas 2 : HTML ✅ / PDF ❌
```
┌──────────────┐     ┌──────────────┐
│ HTML Preview │     │  PDF Final   │
│              │     │              │
│  ┌────────┐  │     │  ╔════════╗  │
│  │[sign.] │  │     │  ║[sign.] ║  │ ← Bordure !
│  └────────┘  │     │  ╚════════╝  │
│              │     │              │
│ Pas bordure  │     │ AVEC bordure │
└──────────────┘     └──────────────┘
```
**→ Problème TCPDF connu** - Voir `SOLUTION_BORDURES_TCPDF.md`

### Cas 3 : HTML ❌ / PDF ❌
```
┌──────────────┐     ┌──────────────┐
│ HTML Preview │     │  PDF Final   │
│              │     │              │
│  ╔════════╗  │     │  ╔════════╗  │
│  ║[sign.] ║  │     │  ║[sign.] ║  │
│  ╚════════╝  │     │  ╚════════╝  │
│              │     │              │
│ AVEC bordure │     │ AVEC bordure │
└──────────────┘     └──────────────┘
```
**→ Problème CSS** - Corriger dans `pdf/generate-*.php`

---

## 🛠️ Dépannage

### Erreur : "Contrat not found"

**Cause :** L'ID spécifié n'existe pas dans la base de données

**Solution :**
```sql
SELECT id FROM contrats LIMIT 10;
```
Utiliser un ID valide

### Erreur : "No tenants found"

**Cause :** Le contrat n'a pas de locataires associés

**Solution :**
```sql
SELECT * FROM locataires WHERE contrat_id = 51;
```
Vérifier qu'il y a des locataires pour ce contrat

### Erreur : "État des lieux - Type invalide"

**Cause :** Le paramètre `type` est incorrect

**Solution :** Utiliser `type=entree` ou `type=sortie`

### Page Blanche

**Cause :** Erreur PHP non affichée

**Solution :**
1. Activer l'affichage des erreurs :
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

2. Vérifier les logs Apache/PHP

3. Vérifier la configuration de la base de données

---

## 📚 Documentation Liée

- `SOLUTION_BORDURES_TCPDF.md` - Explication du problème TCPDF
- `COMPARAISON_HTML_VS_PDF_TCPDF.md` - Comparaisons visuelles
- `AVANT_APRES_SIGNATURES_TCPDF.md` - Solution technique complète
- `RESUME_RESTAURATION_TAILLES_SIGNATURES.md` - Détails sur les tailles

---

## ✅ Checklist d'Utilisation

Avant de reporter un problème, vérifier :

- [ ] Le serveur PHP fonctionne
- [ ] La base de données est accessible
- [ ] L'ID de contrat existe
- [ ] Le contrat a des locataires
- [ ] Les fichiers `includes/config.php` et `includes/db.php` sont configurés
- [ ] Les signatures existent dans la base de données
- [ ] J'ai comparé HTML vs PDF pour identifier où est le problème

---

**Créé le :** 2026-02-06  
**Auteur :** GitHub Copilot  
**Status :** ✅ Opérationnel
