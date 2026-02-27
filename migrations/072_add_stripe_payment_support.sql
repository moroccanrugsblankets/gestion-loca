-- Migration 072: Ajout du support paiement Stripe/SEPA pour les loyers
-- Date: 2026-02-26
-- Description: Crée la table stripe_payment_sessions pour suivre les sessions de paiement Stripe,
--              et ajoute les colonnes de référence Stripe dans loyers_tracking.

-- 1. Créer la table stripe_payment_sessions pour suivre chaque session de paiement
CREATE TABLE IF NOT EXISTS stripe_payment_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loyer_tracking_id INT NOT NULL COMMENT 'Référence vers loyers_tracking.id',
    contrat_id INT NOT NULL,
    logement_id INT NOT NULL,
    mois INT NOT NULL,
    annee INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL COMMENT 'Montant en euros',
    stripe_session_id VARCHAR(255) UNIQUE COMMENT 'Identifiant Stripe Checkout Session',
    stripe_payment_intent_id VARCHAR(255) COMMENT 'Identifiant Stripe PaymentIntent',
    stripe_customer_id VARCHAR(255) COMMENT 'Identifiant Stripe Customer',
    statut ENUM('en_attente','paye','annule','expire','rembourse') DEFAULT 'en_attente',
    token_acces VARCHAR(64) UNIQUE NOT NULL COMMENT 'Token sécurisé pour le lien de paiement',
    token_expiration DATETIME NOT NULL COMMENT 'Date d''expiration du lien de paiement',
    email_invitation_envoye BOOLEAN DEFAULT FALSE,
    date_email_invitation TIMESTAMP NULL,
    quittance_generee BOOLEAN DEFAULT FALSE,
    quittance_id INT NULL COMMENT 'ID de la quittance générée après paiement',
    date_paiement TIMESTAMP NULL COMMENT 'Date de confirmation du paiement par Stripe',
    stripe_event_id VARCHAR(255) COMMENT 'ID de l''événement Stripe webhook (idempotence)',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_loyer_tracking (loyer_tracking_id),
    INDEX idx_contrat (contrat_id),
    INDEX idx_stripe_session (stripe_session_id),
    INDEX idx_token (token_acces),
    INDEX idx_statut (statut),
    INDEX idx_mois_annee (mois, annee),

    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
    FOREIGN KEY (logement_id) REFERENCES logements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ajouter les colonnes Stripe dans loyers_tracking
ALTER TABLE loyers_tracking
    ADD COLUMN stripe_session_id VARCHAR(255) NULL COMMENT 'Dernière session Stripe associée',
    ADD COLUMN mode_paiement ENUM('manuel','stripe','virement','especes','cheque') DEFAULT 'manuel' COMMENT 'Mode de paiement utilisé';
