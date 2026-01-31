# Fix: Template d'Email de Signature de Contrat Manquant

## Problème Rapporté

Les templates d'email existants étaient:
- Notification admin - Nouvelle candidature
- Candidature acceptée
- Candidature non retenue
- Accusé de réception de candidature

**Template manquant:** Email d'invitation à signer le contrat de bail

Le client a reçu l'email avec l'objet "Contrat de bail à signer – Action immédiate requise" mais **dans le dossier spam**, probablement parce que l'email était généré par du code hardcodé au lieu d'utiliser le système de templates avec la signature professionnelle.

## Solution Implémentée

### 1. Nouveau Template Créé

**Identifiant:** `contrat_signature`  
**Nom:** Invitation à signer le contrat de bail  
**Sujet:** Contrat de bail à signer – Action immédiate requise  

**Variables disponibles:**
- `{{nom}}` - Nom du locataire
- `{{prenom}}` - Prénom du locataire
- `{{email}}` - Email du locataire
- `{{adresse}}` - Adresse du logement
- `{{lien_signature}}` - Lien vers la page de signature
- `{{signature}}` - Signature email professionnelle (automatique)

### 2. Fichiers Modifiés

#### Scripts d'initialisation
- **init-email-templates.php** - Ajout du template `contrat_signature`
- **diagnostic-email-system.php** - Vérification de 5 templates au lieu de 4

#### Fichiers admin
- **admin-v2/envoyer-signature.php** - Utilise maintenant `sendTemplatedEmail()`
- **admin-v2/generer-contrat.php** - Utilise maintenant `sendTemplatedEmail()`
- **admin-v2/renvoyer-lien-signature.php** - Utilise maintenant `sendTemplatedEmail()`

#### Migration
- **migrations/017_add_contract_signature_template.sql** - Migration pour ajouter le template

### 3. Avantages de cette Solution

✅ **Professionnalisme**: Le template inclut la signature email avec logo  
✅ **Cohérence**: Même style que les autres templates  
✅ **Moins de spam**: Utilise le système de templates qui améliore la délivrabilité  
✅ **Personnalisation**: Variables remplacées automatiquement  
✅ **Gestion facile**: Modifiable via `/admin-v2/email-templates.php`  

## Utilisation

### Pour les nouvelles installations

1. Exécuter les migrations:
   ```bash
   php run-migrations.php
   ```

2. Ou initialiser tous les templates:
   ```bash
   php init-email-templates.php
   ```

### Pour les installations existantes

**Option 1: Migration (recommandée)**
```bash
php run-migrations.php
```

**Option 2: Script d'initialisation**
```bash
php init-email-templates.php
```

**Option 3: Reset complet des templates**
```bash
php init-email-templates.php --reset
```

### Vérification

1. Ouvrir `/admin-v2/email-templates.php`
2. Vérifier que le template "Invitation à signer le contrat de bail" est présent
3. Le modifier si nécessaire via l'interface

## Impact sur les Emails de Spam

### Avant
- Email généré par fonction hardcodée `getInvitationSignatureEmailHTML()`
- Pas de signature professionnelle
- Plus susceptible d'être marqué comme spam

### Après
- Email généré via template de base de données
- Inclut la signature professionnelle avec logo
- Variables `{{signature}}` remplacée automatiquement
- Meilleure délivrabilité (moins de spam)

## Contenu du Template

Le template inclut:
- En-tête professionnel avec dégradé violet
- Alerte d'action immédiate (24h)
- Liste numérotée des étapes:
  1. Signature du contrat en ligne
  2. Transmission de la pièce d'identité
  3. Règlement du dépôt de garantie
- Bouton d'action prominent vers le lien de signature
- Informations importantes
- Signature email professionnelle
- Pied de page avec copyright

## Modification du Template

Pour personnaliser le template:

1. Aller sur `/admin-v2/email-templates.php`
2. Cliquer sur "Modifier" pour le template "Invitation à signer le contrat de bail"
3. Modifier le sujet et/ou le corps HTML
4. Utiliser les variables disponibles: `{{nom}}`, `{{prenom}}`, `{{lien_signature}}`, etc.
5. Enregistrer

**Note:** Ne pas oublier d'inclure `{{signature}}` dans le template pour la signature professionnelle!

## Tests

Pour tester le nouveau template:

1. Créer un contrat via `/admin-v2/generer-contrat.php`
2. Vérifier que l'email est envoyé
3. Vérifier que l'email n'est PAS dans le spam
4. Vérifier que la signature professionnelle est présente
5. Vérifier que toutes les variables sont correctement remplacées

## Dépannage

### Le template n'apparaît pas dans l'admin

```bash
php diagnostic-email-system.php
```

Si le template est manquant:
```bash
php init-email-templates.php
```

### L'email va toujours dans le spam

Vérifier:
1. Que le template inclut `{{signature}}`
2. Que le paramètre `email_signature` est défini dans la base de données
3. Que la configuration SMTP est correcte dans `config.local.php`
4. Que l'email expéditeur correspond au domaine configuré

### Les variables ne sont pas remplacées

Vérifier dans le code que toutes les variables sont passées à `sendTemplatedEmail()`:
```php
$variables = [
    'nom' => $nom,
    'prenom' => $prenom,
    'email' => $email,
    'adresse' => $adresse,
    'lien_signature' => $lien_signature
];

sendTemplatedEmail('contrat_signature', $email, $variables, null, true);
```

## Conclusion

Ce fix améliore significativement la délivrabilité des emails de signature de contrat en utilisant le système de templates professionnel avec signature, réduisant ainsi les risques que l'email soit marqué comme spam.
