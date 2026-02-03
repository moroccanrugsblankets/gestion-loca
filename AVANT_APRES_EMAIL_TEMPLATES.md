# Avant / Après - Comparaison des Emails

## 🔴 AVANT (Problème)

### Email Admin - Contrat Signé
```
Sujet: [ADMIN] Contrat signé - BAIL-69814790F242E
```
❌ Sujet hardcodé dans le code  
❌ Ne correspond pas au template configuré  
❌ Impossible de modifier sans toucher au code  

**Code source (avant):**
```php
// Dans step3-documents.php (ligne ~133)
$subject = "[ADMIN] Contrat signé - " . $contrat['reference_unique'];
sendEmailToAdmins($subject, $body, $pdfPath);
```

---

### Email Changement de Statut
```
Sujet: Candidature acceptée - MyInvest Immobilier
```
❌ Sujet hardcodé dans change-status.php  
❌ Message HTML généré par fonction getStatusChangeEmailHTML()  
❌ Pas configurable via le backoffice  

**Code source (avant):**
```php
// Dans change-status.php (lignes 82-100)
switch ($nouveau_statut) {
    case 'Accepté':
        $subject = "Candidature acceptée - MyInvest Immobilier";
        break;
    case 'Refusé':
        $subject = "Suite à votre candidature - MyInvest Immobilier";
        break;
    // ... plus de cas hardcodés
}
$htmlBody = getStatusChangeEmailHTML($nom_complet, $nouveau_statut, $commentaire);
sendEmail($to, $subject, $htmlBody, null, true, $isAdminEmail);
```

---

## ✅ APRÈS (Solution)

### Email Admin - Contrat Signé
```
Sujet: Contrat signé - BAIL-69814790F242E - Vérification requise
```
✅ Utilise le template `contrat_finalisation_admin`  
✅ Correspond au template configuré dans le backoffice  
✅ Modifiable via `/admin-v2/email-templates.php`  

**Code source (après):**
```php
// Dans step3-documents.php (ligne ~133)
$variables = [
    'reference' => $contrat['reference_unique'],
    'logement' => $contrat['adresse'],
    'locataires' => $locatairesStr,
    'depot_garantie' => formatMontant($contrat['depot_garantie']),
    'date_finalisation' => date('d/m/Y à H:i'),
    'lien_admin' => $lienAdmin
];
sendTemplatedEmail('contrat_finalisation_admin', $locataires[0]['email'], $variables, $pdfPath, true);
```

**Template dans la base de données:**
```
Identifiant: contrat_finalisation_admin
Sujet: Contrat signé - {{reference}} - Vérification requise
```

---

### Email Changement de Statut
```
Sujet: Visite de logement planifiée - MY Invest Immobilier
```
✅ Utilise le template `statut_visite_planifiee`  
✅ Contenu HTML vient de la base de données  
✅ Modifiable via le backoffice sans toucher au code  

**Code source (après):**
```php
// Dans change-status.php (lignes 74-120)
$templateMap = [
    'accepte' => 'candidature_acceptee',
    'refuse' => 'candidature_refusee',
    'visite_planifiee' => 'statut_visite_planifiee',
    'contrat_envoye' => 'statut_contrat_envoye',
    'contrat_signe' => 'statut_contrat_signe'
];

$templateId = $templateMap[$nouveau_statut] ?? null;

$variables = [
    'nom' => $candidature['nom'],
    'prenom' => $candidature['prenom'],
    'email' => $candidature['email'],
    'commentaire' => $commentaire ? '<p>Note: ' . nl2br(htmlspecialchars($commentaire)) . '</p>' : ''
];

sendTemplatedEmail($templateId, $to, $variables, null, $isAdminEmail);
```

**Template dans la base de données:**
```
Identifiant: statut_visite_planifiee
Sujet: Visite de logement planifiée - MY Invest Immobilier
Corps: <HTML avec variables {{nom}}, {{prenom}}, {{commentaire}}, {{signature}}>
```

---

## 📊 Comparaison Fonctionnelle

| Aspect | AVANT | APRÈS |
|--------|-------|-------|
| **Modification du sujet** | Modifier le code PHP | Éditer dans le backoffice |
| **Modification du contenu** | Modifier le code PHP | Éditer dans le backoffice |
| **Uniformité** | Sujets incohérents | Tous utilisent les templates |
| **Variables dynamiques** | Concaténation de strings | Système de template `{{variable}}` |
| **Testabilité** | Déployer pour tester | Tester en temps réel dans l'admin |
| **Maintenance** | Développeur requis | Admin peut gérer |
| **Traçabilité** | Changements dans Git | Historique dans la base |

---

## 🎨 Interface Backoffice

Maintenant tous les templates sont gérables via:  
**URL:** `/admin-v2/email-templates.php`

### Liste des Templates
```
┌─────────────────────────────────────────────────────┐
│ Templates d'Email                                   │
├─────────────────────────────────────────────────────┤
│                                                      │
│  📧 Candidature reçue                    [Modifier] │
│     Sujet: Votre candidature a bien été reçue...    │
│                                                      │
│  📧 Candidature acceptée                 [Modifier] │
│     Sujet: Suite à votre candidature                │
│                                                      │
│  📧 Notification Admin - Contrat Finalisé [Modifier]│
│     Sujet: Contrat signé - {{reference}} - Vér...   │
│     ⭐ MIS À JOUR                                    │
│                                                      │
│  📧 Visite planifiée                     [Modifier] │
│     Sujet: Visite de logement planifiée...          │
│     ⭐ NOUVEAU                                       │
│                                                      │
│  📧 Contrat envoyé                       [Modifier] │
│     Sujet: Contrat de bail - MY Invest...           │
│     ⭐ NOUVEAU                                       │
│                                                      │
│  📧 Contrat signé                        [Modifier] │
│     Sujet: Contrat signé - MY Invest...             │
│     ⭐ NOUVEAU                                       │
│                                                      │
└─────────────────────────────────────────────────────┘
```

### Éditeur de Template
Cliquer sur [Modifier] ouvre l'éditeur avec:
- Champ **Nom du template**
- Champ **Sujet** (avec variables)
- Éditeur **HTML WYSIWYG** (TinyMCE)
- Liste des **variables disponibles** : `{{nom}}`, `{{prenom}}`, `{{reference}}`, etc.
- Bouton **Enregistrer**

---

## 🎯 Impact

### Pour les Développeurs
✅ Moins de code à maintenir  
✅ Changements centralisés dans la base de données  
✅ Pas de déploiement pour modifier un email  

### Pour les Admins
✅ Contrôle total sur les emails  
✅ Modifications en temps réel  
✅ Interface conviviale (éditeur WYSIWYG)  

### Pour les Utilisateurs
✅ Emails cohérents et professionnels  
✅ Messages personnalisés avec variables  
✅ Meilleure expérience globale  

---

## 📝 Nouveaux Templates Créés

### 1. statut_visite_planifiee
```html
Sujet: Visite de logement planifiée - MY Invest Immobilier

Bonjour {{nom}},

📅 Votre visite du logement a été planifiée.

Nous vous contacterons prochainement pour confirmer 
la date et l'heure de la visite.

{{commentaire}}
```

### 2. statut_contrat_envoye
```html
Sujet: Contrat de bail - MY Invest Immobilier

Bonjour {{nom}},

📄 Votre contrat de bail est prêt.

Vous allez recevoir un lien pour le signer électroniquement.

{{commentaire}}
```

### 3. statut_contrat_signe
```html
Sujet: Contrat signé - MY Invest Immobilier

Bonjour {{nom}},

✓ Nous avons bien reçu votre contrat signé.

Nous vous contacterons prochainement pour les modalités 
d'entrée dans le logement.

{{commentaire}}
```

---

## ✅ Résultat Final

**Avant :** 5 fichiers PHP avec emails hardcodés  
**Après :** 10 templates configurables dans la base de données

**Avant :** Développeur requis pour chaque modification  
**Après :** Admin autonome via interface web

**Avant :** Incohérence entre code et templates configurés  
**Après :** ✅ 100% des emails utilisent les templates du backoffice
