-- Migration 054: Add bilan_send_history table
-- This table tracks the history of when bilans were sent to tenants

CREATE TABLE IF NOT EXISTS bilan_send_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etat_lieux_id INT NOT NULL,
    contrat_id INT NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_by INT NOT NULL COMMENT 'User ID who sent the bilan',
    recipient_emails TEXT NOT NULL COMMENT 'JSON array of recipient emails',
    notes TEXT COMMENT 'Optional notes about the send',
    FOREIGN KEY (etat_lieux_id) REFERENCES etats_lieux(id) ON DELETE CASCADE,
    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
    INDEX idx_etat_lieux (etat_lieux_id),
    INDEX idx_contrat (contrat_id),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
