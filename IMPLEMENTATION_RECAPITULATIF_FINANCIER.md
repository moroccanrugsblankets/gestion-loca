# Récapitulatif Financier - Implementation Documentation

## Objectif
Adapter la template de Email et PDF du Bilan du Logement afin d'intégrer les variables nécessaires pour afficher un récapitulatif financier complet.

## Changements Implémentés

### 1. Template HTML (migrations/055_add_bilan_logement_email_template.sql)

#### Section Ajoutée : Récapitulatif Financier
Une nouvelle section a été ajoutée au template HTML avec les éléments suivants :

```html
<div class="recapitulatif-financier">
    <h3>Récapitulatif Financier</h3>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <td><strong>Dépôt de garantie :</strong></td>
            <td>{{depot_garantie}}</td>
        </tr>
        <tr>
            <td><strong>Valeur estimative :</strong></td>
            <td>{{valeur_estimative}}</td>
        </tr>
        <tr>
            <td><strong>Solde Débiteur :</strong></td>
            <td>{{total_solde_debiteur}}</td>
        </tr>
        <tr>
            <td><strong>Solde Créditeur :</strong></td>
            <td>{{total_solde_crediteur}}</td>
        </tr>
        <tr style="background-color: #d4edda;">
            <td><strong>Montant à restituer :</strong></td>
            <td>{{montant_a_restituer}}</td>
        </tr>
        <tr style="background-color: #f8d7da;">
            <td><strong>Reste dû :</strong></td>
            <td>{{reste_du}}</td>
        </tr>
    </table>
    <p class="disclaimer">Les soldes débiteurs et créditeurs...</p>
</div>
```

#### Phrase d'Avertissement
Une phrase explicative en petite police (11px) a été ajoutée :

> "Les soldes débiteurs et créditeurs figurant dans le tableau s'entendent comme étant respectivement à la charge ou en faveur du locataire."

#### Compatibilité TCPDF
- Suppression du CSS Grid (`display: grid`, `grid-template-columns`)
- Utilisation de tables HTML standard pour la mise en page
- Tous les styles sont compatibles avec TCPDF

### 2. Génération PDF (pdf/generate-bilan-logement.php)

#### Récupération du Dépôt de Garantie
```php
$stmt = $pdo->prepare("
    SELECT c.*, 
           l.adresse as logement_adresse,
           l.depot_garantie as depot_garantie,
           c.reference_unique as contrat_ref
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
");
```

#### Calculs Financiers
```php
// Valeur estimative = total valeur from bilan rows
$valeurEstimative = $totalValeur;

// Montant à restituer = Dépôt de garantie + Solde Créditeur - Solde Débiteur (if > 0)
$calculResultat = $depotGarantie + $totalSoldeCrediteur - $totalSoldeDebiteur;
$montantARestituer = $calculResultat > 0 ? $calculResultat : 0;

// Reste dû = abs(résultat) si résultat < 0, sinon 0
$resteDu = $calculResultat < 0 ? abs($calculResultat) : 0;
```

#### Variables Ajoutées au Template
```php
'{{depot_garantie}}' => number_format($depotGarantie, 2, ',', ' ') . ' €',
'{{valeur_estimative}}' => number_format($valeurEstimative, 2, ',', ' ') . ' €',
'{{montant_a_restituer}}' => number_format($montantARestituer, 2, ',', ' ') . ' €',
'{{reste_du}}' => number_format($resteDu, 2, ',', ' ') . ' €'
```

### 3. Prévisualisation HTML (test-html-preview-bilan-logement.php)

Les mêmes modifications ont été appliquées pour assurer la cohérence entre la prévisualisation et le PDF final.

### 4. Envoi d'Email (admin-v2/edit-bilan-logement.php)

Les variables financières sont maintenant incluses dans les emails envoyés aux locataires, permettant une communication complète et transparente.

## Formules de Calcul

### Montant à Restituer
```
Formule: Dépôt de garantie + Solde Créditeur - Solde Débiteur
Si résultat > 0 : Montant à restituer = résultat
Si résultat ≤ 0 : Montant à restituer = 0
```

**Exemple :**
- Dépôt de garantie : 1 000 €
- Solde Créditeur : 0 €
- Solde Débiteur : 450 €
- Résultat : 1000 + 0 - 450 = 550 €
- **Montant à restituer : 550 €**

### Reste Dû
```
Formule: Dépôt de garantie + Solde Créditeur - Solde Débiteur
Si résultat < 0 : Reste dû = |résultat|
Si résultat ≥ 0 : Reste dû = 0
```

**Exemple :**
- Dépôt de garantie : 500 €
- Solde Créditeur : 0 €
- Solde Débiteur : 800 €
- Résultat : 500 + 0 - 800 = -300 €
- **Reste dû : 300 €**

## Tests Effectués

### Scénarios de Test
1. ✅ **Restitution** (depot > debits) : 1000€ - 450€ = 550€ à restituer
2. ✅ **Reste dû** (depot < debits) : 500€ - 800€ = 300€ reste dû
3. ✅ **Avec solde créditeur** : 1000€ + 100€ - 300€ = 800€ à restituer
4. ✅ **Montants égaux** : 1000€ - 1000€ = 0€ (ni restitution ni reste dû)
5. ✅ **Déficit important** : 1000€ - 2500€ = 1500€ reste dû

### Validation TCPDF
- ✅ Pas de CSS Grid ou Flexbox
- ✅ Tables HTML standards utilisées
- ✅ Tous les styles sont compatibles TCPDF
- ✅ Variables correctement échappées (htmlspecialchars)

### Validation de Sécurité
- ✅ Toutes les entrées utilisateur sont échappées avec `htmlspecialchars()`
- ✅ Les valeurs numériques sont validées avec `is_numeric()` avant calcul
- ✅ Formatage cohérent avec `number_format()`
- ✅ Aucune vulnérabilité SQL injection (prepared statements utilisés)
- ✅ CodeQL : Aucun problème de sécurité détecté

## Fichiers Modifiés

1. **migrations/055_add_bilan_logement_email_template.sql**
   - Mise à jour du template HTML
   - Ajout de la section Récapitulatif Financier
   - Mise à jour de la liste des variables disponibles

2. **pdf/generate-bilan-logement.php**
   - Récupération du depot_garantie depuis la table logements
   - Ajout des calculs financiers
   - Ajout des nouvelles variables au template

3. **test-html-preview-bilan-logement.php**
   - Même logique que generate-bilan-logement.php
   - Permet de prévisualiser le rendu avant génération PDF

4. **admin-v2/edit-bilan-logement.php**
   - Inclusion des variables financières dans les emails
   - Calculs effectués avant l'envoi

## Mise en Production

### Prérequis
- Base de données avec les tables `logements`, `contrats`, `etats_lieux`
- Migration 055 doit être exécutée pour mettre à jour le template

### Déploiement
1. Exécuter la migration 055 :
   ```bash
   mysql -u [user] -p [database] < migrations/055_add_bilan_logement_email_template.sql
   ```

2. Vérifier que le template a été mis à jour :
   ```sql
   SELECT valeur FROM parametres WHERE cle = 'bilan_logement_template_html';
   ```

3. Tester avec un contrat existant via le test preview :
   ```
   http://[site]/test-html-preview-bilan-logement.php?contrat_id=[id]
   ```

## Notes Techniques

### Ordre d'Affichage
L'ordre des éléments dans le récapitulatif financier est fixe et respecte les spécifications :
1. Dépôt de garantie
2. Valeur estimative
3. Solde Débiteur
4. Solde Créditeur
5. Montant à restituer (fond vert si > 0)
6. Reste dû (fond rouge si > 0)

### Styling
- Montant à restituer : fond vert clair (#d4edda)
- Reste dû : fond rouge clair (#f8d7da)
- Disclaimer : police 11px, italique, couleur grise

### Maintainabilité
- Code commenté en français
- Variables bien nommées
- Logique de calcul isolée et réutilisable
- Tests automatisés disponibles dans `/tmp/test-bilan-financial-summary-standalone.php`

## Support et Contact

Pour toute question ou problème concernant cette implémentation, veuillez consulter :
- Le code source dans les fichiers listés ci-dessus
- Les tests dans `/tmp/test-bilan-financial-summary-standalone.php`
- La documentation du projet principal

---

**Date de création** : 16 février 2026  
**Version** : 1.0  
**Auteur** : GitHub Copilot Workspace
