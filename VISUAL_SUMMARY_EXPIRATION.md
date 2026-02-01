# Visual Summary: Contract Link Expiration Fix

## Problem
```
❌ BEFORE:
- Links expire before 24 hours (too early)
- Expiration time is hardcoded in multiple places
- Users don't see when the link will expire
- No way to configure expiration without code changes
```

## Solution
```
✅ AFTER:
- Links expire after configurable delay (default: 24 hours)
- Single source of truth: delai_expiration_lien_contrat parameter
- Users see exact expiration date in emails
- Administrators can change expiration in admin panel
```

## Visual Flow

### 1. Admin Panel - Parameters Section
```
┌─────────────────────────────────────────────────┐
│ Paramètres > Général                            │
├─────────────────────────────────────────────────┤
│                                                  │
│ Délai d'expiration du lien de signature         │
│ ┌──────┐                                        │
│ │  24  │ heures                                 │
│ └──────┘                                        │
│                                                  │
│ Délai d'expiration du lien de signature         │
│ (en heures)                                     │
│                                                  │
│ [Enregistrer les paramètres]                   │
└─────────────────────────────────────────────────┘
```

### 2. Email Template - Expiration Display
```
┌─────────────────────────────────────────────────────────┐
│ 📝 Contrat de Bail à Signer                            │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ Bonjour,                                                │
│                                                          │
│ ⏰ Action immédiate requise                            │
│ Procédure à compléter avant la date limite             │
│                                                          │
│ 📋 Procédure de signature du bail                      │
│                                                          │
│ Merci de compléter l'ensemble de la procédure          │
│ avant la date d'expiration, incluant :                  │
│ 1. La signature du contrat de bail en ligne            │
│ 2. La transmission d'une pièce d'identité              │
│ 3. Le règlement du dépôt de garantie                   │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ ⚠️ IMPORTANT :                                    │  │
│ │ Ce lien expire le 02/02/2026 à 15:30            │  │
│ └───────────────────────────────────────────────────┘  │
│                                                          │
│            [🖊️ Accéder au Contrat de Bail]            │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 3. Code Flow Diagram
```
┌──────────────────┐
│ Create Contract  │
└────────┬─────────┘
         │
         v
┌─────────────────────────────────────────┐
│ getParameter(                           │
│   'delai_expiration_lien_contrat',     │
│   fallback: TOKEN_EXPIRY_HOURS         │
│ )                                       │
└────────┬────────────────────────────────┘
         │
         v
┌─────────────────────────────────────────┐
│ Calculate expiration:                   │
│ NOW + X hours                          │
│ Store in: contrats.date_expiration     │
└────────┬────────────────────────────────┘
         │
         v
┌─────────────────────────────────────────┐
│ Format expiration date:                 │
│ "02/02/2026 à 15:30"                   │
└────────┬────────────────────────────────┘
         │
         v
┌─────────────────────────────────────────┐
│ Pass to email template as:              │
│ {{date_expiration_lien_contrat}}       │
└─────────────────────────────────────────┘
```

## Database Changes

### New Parameter
```sql
┌─────────────────────────────────────────────────────────────┐
│ TABLE: parametres                                           │
├─────────────────────────────────────────────────────────────┤
│ cle: delai_expiration_lien_contrat                         │
│ valeur: 24                                                  │
│ type: integer                                               │
│ description: Délai d'expiration du lien (en heures)        │
│ groupe: general                                             │
└─────────────────────────────────────────────────────────────┘
```

### Updated Email Template
```sql
┌─────────────────────────────────────────────────────────────┐
│ TABLE: email_templates                                      │
├─────────────────────────────────────────────────────────────┤
│ identifiant: contrat_signature                             │
│ variables_disponibles:                                      │
│   - nom                                                     │
│   - prenom                                                  │
│   - email                                                   │
│   - adresse                                                 │
│   - lien_signature                                          │
│   - date_expiration_lien_contrat  ← NEW!                  │
└─────────────────────────────────────────────────────────────┘
```

## Files Modified

```
includes/
  ├── functions.php ..................... Updated createContract()
  └── mail-templates.php ................ Updated email template

admin/
  └── generate-link.php ................. Pass expiration to template

admin-v2/
  ├── envoyer-signature.php ............. Use parameter + pass expiration
  └── renvoyer-lien-signature.php ....... Use parameter + pass expiration

migrations/
  ├── 018_add_delai_expiration_lien_parameter.sql ... New parameter
  └── 019_add_date_expiration_to_email_template.sql . Update template
```

## Testing Checklist

- [ ] Run migrations: `php run-migrations.php`
- [ ] Verify parameter appears in admin panel (Paramètres > Général)
- [ ] Generate a new contract link
- [ ] Check email displays expiration date
- [ ] Verify expiration is 24 hours from now (or configured value)
- [ ] Change parameter to 48 hours
- [ ] Generate another link
- [ ] Verify new expiration is 48 hours from now
- [ ] Try accessing an expired link
- [ ] Verify error message shows expiration date

## Benefits

| Aspect | Before | After |
|--------|--------|-------|
| Expiration time | Hardcoded | Configurable |
| User awareness | None | Exact date shown |
| Admin control | Code changes required | UI setting |
| Consistency | Multiple places | Single parameter |
| Flexibility | Fixed 24h | Any duration |
