-- Migration 079: Ajouter la variable {{reference}} aux templates email Stripe
-- Date: 2026-03-01
-- Description: Ajoute la référence du logement ({{reference}}) dans les templates
--              stripe_invitation_paiement et stripe_rappel_paiement

-- 1. Mettre à jour le template d'invitation
UPDATE email_templates
SET
    corps_html = REPLACE(
        corps_html,
        '<tr>\n                    <td style="padding: 6px 0;"><strong>Logement :</strong></td>\n                    <td style="padding: 6px 0; text-align: right;">{{adresse}}</td>\n                </tr>',
        '<tr>\n                    <td style="padding: 6px 0;"><strong>Référence :</strong></td>\n                    <td style="padding: 6px 0; text-align: right;">{{reference}}</td>\n                </tr>\n                <tr>\n                    <td style="padding: 6px 0;"><strong>Logement :</strong></td>\n                    <td style="padding: 6px 0; text-align: right;">{{adresse}}</td>\n                </tr>'
    ),
    variables_disponibles = '["locataire_nom", "locataire_prenom", "adresse", "reference", "periode", "montant_loyer", "montant_charges", "montant_total", "lien_paiement", "date_expiration", "signature"]',
    updated_at = NOW()
WHERE identifiant = 'stripe_invitation_paiement';

-- 2. Mettre à jour le template de rappel
UPDATE email_templates
SET
    corps_html = REPLACE(
        corps_html,
        '<br><small>Logement : {{adresse}} | Période : {{periode}}</small>',
        '<br><small>Référence : {{reference}} | Logement : {{adresse}} | Période : {{periode}}</small>'
    ),
    variables_disponibles = '["locataire_nom", "locataire_prenom", "adresse", "reference", "periode", "montant_loyer", "montant_charges", "montant_total", "lien_paiement", "date_expiration", "signature"]',
    updated_at = NOW()
WHERE identifiant = 'stripe_rappel_paiement';
