-- Migration: Update email signature format with logo and company info
-- Date: 2026-01-30
-- Description: Update email signature to include logo and formatted company information

UPDATE parametres 
SET valeur = '
<p>Sincères salutations</p>
<br><br>
<table>
    <tbody>
        <tr>
            <td>
                <img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px;">
            </td>
            <td>&nbsp;&nbsp;&nbsp;</td>
            <td>
                <h3 style="margin: 0; color: #2c3e50;">
                    MY INVEST IMMOBILIER
                </h3>
            </td>
        </tr>
    </tbody>
</table>'
WHERE cle = 'email_signature';

-- Add parameter for additional admin email (if it doesn't exist)
INSERT INTO parametres (cle, valeur, type, description, groupe)
VALUES ('email_admin_candidature', '', 'string', 'Email d\'un administrateur supplémentaire pour recevoir les notifications de candidatures', 'email')
ON DUPLICATE KEY UPDATE description = 'Email d\'un administrateur supplémentaire pour recevoir les notifications de candidatures';
