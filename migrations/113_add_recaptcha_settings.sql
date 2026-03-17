-- Migration 113: Add reCAPTCHA settings to parametres table
-- Adds global configuration for Google reCAPTCHA (V2 and V3)

INSERT INTO parametres (cle, valeur, groupe, description, type) VALUES
('recaptcha_enabled',    '0',   'recaptcha', 'Activer le reCAPTCHA sur les formulaires publics', 'boolean'),
('recaptcha_type',       'v2',  'recaptcha', 'Type de reCAPTCHA : v2 (case à cocher) ou v3 (score invisible)', 'text'),
('recaptcha_site_key',   '',    'recaptcha', 'Clé site reCAPTCHA fournie par Google (publique, affichée côté client)', 'text'),
('recaptcha_secret_key', '',    'recaptcha', 'Clé secrète reCAPTCHA fournie par Google (confidentielle, utilisée côté serveur uniquement)', 'password')
ON DUPLICATE KEY UPDATE updated_at = updated_at;
