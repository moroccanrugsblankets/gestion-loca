-- Migration 075: Enregistrement du cron job de paiements Stripe
-- Date: 2026-02-26

INSERT INTO cron_jobs (nom, description, fichier, frequence, cron_expression, actif) VALUES
(
    'Paiements Stripe',
    'Envoie automatiquement les invitations de paiement Stripe aux locataires en début de mois, et des rappels pour les loyers impayés selon les jours configurés',
    'cron/stripe-paiements.php',
    'daily',
    '0 8 * * *',
    0
)
ON DUPLICATE KEY UPDATE
    nom = VALUES(nom),
    description = VALUES(description),
    frequence = VALUES(frequence),
    cron_expression = VALUES(cron_expression);
