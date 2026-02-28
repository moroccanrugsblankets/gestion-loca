-- Migration 077: Paramètres de configuration SMTP
-- Date: 2026-02-28
-- Description: Ajoute les paramètres de configuration SMTP dans la table parametres
-- pour permettre la gestion de l'envoi d'emails directement depuis l'interface admin

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('mail_from',      '', 'string',  'Adresse email d\'expédition des emails', 'email'),
('mail_from_name', '', 'string',  'Nom affiché comme expéditeur des emails', 'email'),
('smtp_host',      '', 'string',  'Serveur SMTP (ex: smtp.gmail.com, smtp.office365.com)', 'email'),
('smtp_port',      '587',                              'integer', 'Port SMTP (587 pour TLS, 465 pour SSL)', 'email'),
('smtp_secure',    'tls',                              'string',  'Protocole de sécurité SMTP : tls ou ssl', 'email'),
('smtp_username',  '', 'string',  'Identifiant SMTP (généralement votre adresse email)', 'email'),
('smtp_password',  '',                                 'string',  'Mot de passe SMTP ou mot de passe d\'application (App Password)', 'email')
ON DUPLICATE KEY UPDATE cle = cle;
