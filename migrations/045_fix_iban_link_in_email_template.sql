-- Migration: Update demande_justificatif_paiement email template to prevent IBAN link
-- Date: 2026-02-11
-- Description: Move white-space: nowrap from div to span to prevent email clients from creating a link on the IBAN

UPDATE email_templates 
SET corps_html = REPLACE(
    corps_html,
    '<div style="margin: 10px 0; white-space: nowrap;">
                    <strong style="display: inline-block; min-width: 120px; color: #555;">IBAN :</strong> <span style="font-family: monospace; letter-spacing: 1px;">FR76&nbsp;1027&nbsp;8021&nbsp;6000&nbsp;0206&nbsp;1834&nbsp;585</span>
                </div>',
    '<div style="margin: 10px 0;">
                    <strong style="display: inline-block; min-width: 120px; color: #555;">IBAN :</strong> <span style="font-family: monospace; letter-spacing: 1px; white-space: nowrap;">FR76&nbsp;1027&nbsp;8021&nbsp;6000&nbsp;0206&nbsp;1834&nbsp;585</span>
                </div>'
)
WHERE identifiant = 'demande_justificatif_paiement';
