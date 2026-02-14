# Guide Visuel - Reconfiguration du Workflow du Contrat

## Vue d'ensemble des changements

Cette mise à jour reconfigure le workflow du contrat pour intégrer correctement le bilan de logement, avec des améliorations visuelles et une meilleure organisation de la navigation.

---

## 1. Navigation - Menu Contrats

### AVANT
```
Contrats
  ├── Configuration Contrats
  └── Configuration Quittances

Paramètres
  └── Bilan de Logement
```

### APRÈS ✅
```
Contrats
  ├── Configuration Contrats
  ├── Configuration Bilan      ← DÉPLACÉ ICI
  └── Configuration Quittances

Paramètres
  (plus de sous-menu)
```

**Chemin d'accès**: 
- Menu latéral > Contrats > Configuration Bilan
- URL: `admin-v2/bilan-logement-configuration.php`

---

## 2. Bilan de Logement - Template PDF

### Changements visuels

#### A. Line-height réduit
```css
/* AVANT */
body { line-height: 1.6; }  /* Trop d'espace */

/* APRÈS */
body { line-height: 1.4; }  /* Plus compact */
```

#### B. Tableaux optimisés
```css
/* AVANT */
table th { padding: 12px; font-size: 11pt; }
table td { padding: 10px; font-size: 11pt; }

/* APRÈS */
table th { padding: 8px; font-size: 10pt; line-height: 1.3; }
table td { padding: 6px; font-size: 10pt; line-height: 1.3; }
```

#### C. Section Commentaire
```css
/* AVANT */
.commentaire-section {
  background: #fff3cd;
  border-left: 4px solid #ffc107;  /* Jaune */
}

/* APRÈS */
.commentaire-section {
  background: #f9f9f9;  /* Gris neutre */
  /* Plus de bordure jaune */
}
```

---

## 3. Inventaire - Section Observations

### Changement visuel dans le PDF

```css
/* AVANT */
.observations {
  background-color: #fffef0;  /* Jaune pâle */
  border-left: 3px solid #f39c12;  /* Bordure jaune */
}

/* APRÈS */
.observations {
  background-color: #f9f9f9;  /* Gris neutre */
  /* Plus de bordure jaune */
}
```

**Impact**: Section "Observations générales" plus sobre et professionnelle

---

## 4. PDF Contrat - Tableaux optimisés

### Tableau des locataires
```css
/* AVANT */
th, td { 
  padding: 8px; 
  /* pas de font-size spécifié */
}

/* APRÈS */
th, td { 
  padding: 6px; 
  font-size: 10pt;  /* Explicite pour lisibilité */
}
```

### Tableau des signatures
```css
/* AVANT */
td { 
  padding: 15px; 
  /* pas de font-size spécifié */
}

/* APRÈS */
td { 
  padding: 10px; 
  font-size: 10pt;  /* Plus compact, reste lisible */
}
```

---

## 5. Emails - Configuration BCC

### Système existant (confirmé) ✅

**Code dans `includes/mail-templates.php` (ligne 143+)**:

```php
function sendEmail($to, $subject, $body, $attachmentPath = null, 
                   $isHtml = true, $isAdminEmail = false, 
                   $replyTo = null, $replyToName = null, 
                   $addAdminBcc = false) {
    
    // Si isAdminEmail = true, ajoute tous les admins en BCC
    if ($isAdminEmail && $pdo) {
        $stmt = $pdo->prepare("SELECT email FROM administrateurs WHERE actif = TRUE");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            $mail->addBCC($admin['email']);  // ← BCC invisible
        }
    }
}
```

**Utilisation dans les contrats**:
```php
// admin-v2/envoyer-signature.php (ligne 74)
sendTemplatedEmail('contrat_signature', $email_principal, $variables, null, true);
//                                                                              ↑
//                                                                    isAdminEmail = true

// admin-v2/contrat-detail.php (ligne 109)
sendTemplatedEmail('contrat_valide_client', $locataire['email'], [...], null, true);
```

**Garanties**:
- ✅ Les administrateurs reçoivent toujours une copie en BCC
- ✅ Les clients ne voient jamais les adresses administratives (BCC = copie cachée)
- ✅ Les templates sont chargés depuis la base de données (table `email_templates`)

---

## 6. Points de validation

### Tests visuels à effectuer

#### PDF Bilan de Logement
1. ✅ Vérifier que le line-height est réduit (plus compact)
2. ✅ Vérifier que les tableaux utilisent 10pt
3. ✅ Vérifier que la section commentaire n'a plus de bordure jaune
4. ✅ Télécharger un PDF et vérifier la lisibilité

#### PDF Inventaire
1. ✅ Vérifier que "Observations générales" n'a plus de bordure jaune
2. ✅ Vérifier que le fond est gris neutre (#f9f9f9)

#### PDF Contrat
1. ✅ Vérifier que les tableaux sont plus compacts
2. ✅ Vérifier que le texte reste lisible (10pt minimum)
3. ✅ Vérifier que les signatures sont bien alignées

#### Navigation
1. ✅ Vérifier que "Configuration Bilan" apparaît dans le menu Contrats
2. ✅ Vérifier que le lien n'apparaît plus dans Paramètres
3. ✅ Vérifier que le bouton "Retour" mène vers contrats.php

#### Emails
1. ✅ Envoyer un email de contrat et vérifier que les admins reçoivent une copie
2. ✅ Vérifier que le client ne voit pas les adresses admin dans les destinataires
3. ✅ Vérifier que le template est bien chargé depuis la BDD

---

## Résumé des bénéfices

### Utilisabilité
- 📱 **Navigation plus logique**: Bilan dans Contrats (là où il est utilisé)
- 📄 **PDFs plus compacts**: Meilleure utilisation de l'espace
- 👀 **Accessibilité respectée**: Font 10pt minimum, line-height 1.4+

### Professionnalisme
- 🎨 **Design sobre**: Suppression des bordures jaunes
- 📊 **Cohérence visuelle**: Tous les tableaux suivent le même style
- ✉️ **Confidentialité**: Admins en BCC invisible

### Maintenance
- 🔧 **Code organisé**: Configuration centralisée
- 📝 **Templates modifiables**: Via interface admin
- 🔒 **Sécurité vérifiée**: CodeQL sans alertes

---

## Support

Pour toute question sur ces changements:
1. Consulter le code source dans les fichiers modifiés
2. Tester en environnement de staging d'abord
3. Vérifier les logs d'erreur PHP si problème

**Fichiers clés**:
- Navigation: `admin-v2/includes/menu.php`
- Bilan: `admin-v2/bilan-logement-configuration.php`
- Contrat PDF: `pdf/generate-contrat-pdf.php`
- Emails: `includes/mail-templates.php`
