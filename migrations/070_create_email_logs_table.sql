-- Migration 070: Création de la table email_logs pour le suivi des emails
-- Date: 2026-02-23
-- Description: Table pour enregistrer tous les emails envoyés via l'application,
--              permettant de les consulter dans la section "Suivi des Emails" de l'admin.

CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Destinataire
    destinataire VARCHAR(255) NOT NULL,

    -- Sujet
    sujet VARCHAR(500) NOT NULL,

    -- Corps de l'email (HTML)
    corps_html LONGTEXT NULL,

    -- Statut d'envoi
    statut ENUM('success', 'error') NOT NULL DEFAULT 'success',
    message_erreur TEXT NULL,

    -- Template utilisé (si applicable)
    template_id VARCHAR(100) NULL,

    -- Contexte (informations supplémentaires: contrat_id, logement_id, etc.)
    contexte VARCHAR(255) NULL,

    -- Pièce jointe (nom du fichier, si applicable)
    piece_jointe VARCHAR(255) NULL,

    -- Timestamps
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_email_logs_destinataire (destinataire),
    INDEX idx_email_logs_statut (statut),
    INDEX idx_email_logs_date_envoi (date_envoi),
    INDEX idx_email_logs_template_id (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
