-- Migration 073: Paramètres de configuration Stripe/SEPA
-- Date: 2026-02-26
-- Description: Ajoute les paramètres nécessaires à la configuration de l'intégration Stripe

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('stripe_actif', '0', 'boolean', 'Active ou désactive le paiement en ligne via Stripe', 'stripe'),
('stripe_mode', 'test', 'string', 'Mode Stripe: "test" ou "live"', 'stripe'),
('stripe_public_key_test', '', 'string', 'Clé publique Stripe (mode test) - commence par pk_test_', 'stripe'),
('stripe_secret_key_test', '', 'string', 'Clé secrète Stripe (mode test) - commence par sk_test_', 'stripe'),
('stripe_public_key_live', '', 'string', 'Clé publique Stripe (mode production) - commence par pk_live_', 'stripe'),
('stripe_secret_key_live', '', 'string', 'Clé secrète Stripe (mode production) - commence par sk_live_', 'stripe'),
('stripe_webhook_secret', '', 'string', 'Secret de validation des webhooks Stripe (whsec_...)', 'stripe'),
('stripe_paiement_invitation_jour', '1', 'integer', 'Jour du mois où envoyer automatiquement les invitations de paiement', 'stripe'),
('stripe_paiement_rappel_jours', '[7, 14]', 'json', 'Jours du mois où envoyer des rappels aux locataires en retard', 'stripe'),
('stripe_lien_expiration_heures', '168', 'integer', 'Durée de validité du lien de paiement en heures (168 = 7 jours)', 'stripe'),
('stripe_methodes_paiement', '["card", "sepa_debit"]', 'json', 'Méthodes de paiement acceptées (card, sepa_debit)', 'stripe')
ON DUPLICATE KEY UPDATE cle = cle;
