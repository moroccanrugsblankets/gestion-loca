-- Base de données pour l'application de signature de bail
-- My Invest Immobilier

CREATE DATABASE IF NOT EXISTS bail_signature CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bail_signature;

-- Table des logements
CREATE TABLE IF NOT EXISTS logements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(20) UNIQUE NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    appartement VARCHAR(50),
    type VARCHAR(50),
    surface DECIMAL(5,2),
    loyer DECIMAL(10,2),
    charges DECIMAL(10,2),
    depot_garantie DECIMAL(10,2),
    parking ENUM('Aucun', '1 place') DEFAULT 'Aucun',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des contrats de bail
CREATE TABLE IF NOT EXISTS contrats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_unique VARCHAR(100) UNIQUE NOT NULL,
    logement_id INT NOT NULL,
    statut ENUM('en_attente', 'signe', 'expire', 'annule') DEFAULT 'en_attente',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_expiration TIMESTAMP,
    date_signature TIMESTAMP NULL,
    date_prise_effet DATE NULL,
    nb_locataires INT DEFAULT 1,
    FOREIGN KEY (logement_id) REFERENCES logements(id) ON DELETE CASCADE,
    INDEX idx_reference_unique (reference_unique),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des locataires
CREATE TABLE IF NOT EXISTS locataires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrat_id INT NOT NULL,
    ordre INT DEFAULT 1,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    email VARCHAR(255) NOT NULL,
    signature_data TEXT,
    signature_ip VARCHAR(45),
    signature_timestamp TIMESTAMP NULL,
    piece_identite_recto VARCHAR(255),
    piece_identite_verso VARCHAR(255),
    mention_lu_approuve TEXT,
    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
    INDEX idx_contrat_ordre (contrat_id, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrat_id INT,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE SET NULL,
    INDEX idx_contrat_id (contrat_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de données de test (logement RP-01)
INSERT INTO logements (reference, adresse, appartement, type, surface, loyer, charges, depot_garantie, parking) 
VALUES ('RP-01', '15 rue de la Paix, 74100 Annemasse', '1', 'T1 Bis', 26.00, 890.00, 140.00, 1780.00, 'Aucun')
ON DUPLICATE KEY UPDATE reference=reference;
