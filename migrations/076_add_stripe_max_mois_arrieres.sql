-- Migration 076: Paramètre de limite des mois arriérés pour les rappels Stripe
-- Date: 2026-02-28
-- Description: Ajoute le paramètre stripe_rappel_mois_arrieres_max pour limiter le nombre
--              de mois passés non payés traités lors de chaque exécution du cron stripe-paiements.php.
--              Évite les envois massifs d'emails lorsqu'un contrat présente de nombreux arriérés.

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('stripe_rappel_mois_arrieres_max', '3', 'integer', 'Nombre maximum de mois passés non payés à inclure dans chaque exécution du cron de rappels Stripe (0 = illimité)', 'stripe')
ON DUPLICATE KEY UPDATE cle = cle;
