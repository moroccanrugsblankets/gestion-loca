-- Migration 048: Création de la table quittances
-- Cette migration crée la structure pour stocker les quittances de loyer générées

CREATE TABLE IF NOT EXISTS quittances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrat_id INT NOT NULL,
    reference_unique VARCHAR(100) UNIQUE NOT NULL,
    mois INT NOT NULL,
    annee INT NOT NULL,
    
    -- Montants
    montant_loyer DECIMAL(10,2) NOT NULL,
    montant_charges DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    
    -- Dates
    date_generation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_debut_periode DATE NOT NULL,
    date_fin_periode DATE NOT NULL,
    
    -- PDF
    fichier_pdf VARCHAR(255),
    
    -- Email
    email_envoye BOOLEAN DEFAULT FALSE,
    date_envoi_email TIMESTAMP NULL,
    
    -- Metadata
    genere_par INT,
    notes TEXT,
    
    -- Indexes
    INDEX idx_contrat_id (contrat_id),
    INDEX idx_mois_annee (mois, annee),
    INDEX idx_reference_unique (reference_unique),
    
    -- Foreign key
    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
    FOREIGN KEY (genere_par) REFERENCES administrateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter un index composite pour éviter les duplicatas mois/année par contrat
ALTER TABLE quittances ADD UNIQUE KEY unique_contrat_mois_annee (contrat_id, mois, annee);
