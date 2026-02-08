<?php
/**
 * Migration 034: Create Inventaire tables
 * 
 * Creates tables for managing equipment inventory per housing unit
 * and tracking inventory at entry/exit
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 034: Create Inventaire Tables ===\n";
    
    // Table 1: inventaire_equipements
    // Stores equipment definitions for each logement
    echo "Creating table: inventaire_equipements...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS inventaire_equipements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        categorie VARCHAR(100) NOT NULL,
        nom VARCHAR(255) NOT NULL,
        description TEXT,
        quantite INT DEFAULT 1,
        valeur_estimee DECIMAL(10,2),
        ordre INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (logement_id) REFERENCES logements(id) ON DELETE CASCADE,
        INDEX idx_logement (logement_id),
        INDEX idx_categorie (categorie)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "✓ Table inventaire_equipements créée\n";
    
    // Table 2: inventaires
    // Main table storing inventory snapshots at entry/exit
    echo "Creating table: inventaires...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS inventaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contrat_id INT NOT NULL,
        logement_id INT NOT NULL,
        type ENUM('entree', 'sortie') NOT NULL,
        reference_unique VARCHAR(100) UNIQUE NOT NULL,
        
        -- Identification
        date_inventaire DATE NOT NULL,
        adresse TEXT NOT NULL,
        appartement VARCHAR(50),
        
        -- Participants
        locataire_nom_complet VARCHAR(255),
        locataire_email VARCHAR(255),
        locataire_present BOOLEAN DEFAULT TRUE,
        bailleur_nom VARCHAR(255) DEFAULT 'MY INVEST IMMOBILIER',
        bailleur_representant VARCHAR(255),
        
        -- Equipment data (JSON: [{equipement_id, nom, categorie, etat, quantite_presente, observations, photos:[]}])
        equipements_data JSON,
        
        -- Comparison (for sortie only)
        comparaison_entree TEXT,
        equipements_manquants JSON,
        equipements_endommages JSON,
        
        -- Financial
        valeur_equipements_manquants DECIMAL(10,2),
        valeur_equipements_endommages DECIMAL(10,2),
        depot_garantie_retenue DECIMAL(10,2),
        depot_garantie_motif TEXT,
        
        -- Observations générales
        observations_generales TEXT,
        
        -- Signatures
        lieu_signature VARCHAR(255),
        date_signature TIMESTAMP NULL,
        signature_bailleur VARCHAR(500),
        
        -- Status
        statut ENUM('brouillon', 'finalise', 'envoye') DEFAULT 'brouillon',
        
        -- Email tracking
        email_envoye BOOLEAN DEFAULT FALSE,
        date_envoi_email TIMESTAMP NULL,
        
        -- Metadata
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by VARCHAR(100),
        
        FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
        FOREIGN KEY (logement_id) REFERENCES logements(id) ON DELETE CASCADE,
        INDEX idx_contrat (contrat_id),
        INDEX idx_logement (logement_id),
        INDEX idx_type (type),
        INDEX idx_reference (reference_unique),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "✓ Table inventaires créée\n";
    
    // Table 3: inventaire_locataires
    // Stores tenant signatures for each inventory
    echo "Creating table: inventaire_locataires...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS inventaire_locataires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inventaire_id INT NOT NULL,
        locataire_id INT,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(255),
        signature VARCHAR(500),
        date_signature TIMESTAMP NULL,
        certifie_exact BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (inventaire_id) REFERENCES inventaires(id) ON DELETE CASCADE,
        INDEX idx_inventaire (inventaire_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "✓ Table inventaire_locataires créée\n";
    
    // Table 4: inventaire_photos
    // Stores photos for equipment in inventories
    echo "Creating table: inventaire_photos...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS inventaire_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inventaire_id INT NOT NULL,
        equipement_id INT,
        categorie VARCHAR(100),
        fichier VARCHAR(500) NOT NULL,
        description TEXT,
        ordre INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (inventaire_id) REFERENCES inventaires(id) ON DELETE CASCADE,
        INDEX idx_inventaire (inventaire_id),
        INDEX idx_categorie (categorie)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "✓ Table inventaire_photos créée\n";
    
    // Add inventory template parameters
    echo "Adding inventory template parameters...\n";
    
    // Check if parameters already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parametres WHERE cle IN ('inventaire_template_html', 'inventaire_sortie_template_html')");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        $sql = "
        INSERT INTO parametres (cle, valeur, description) VALUES
        ('inventaire_template_html', NULL, 'Template HTML personnalisé pour l''inventaire d''entrée'),
        ('inventaire_sortie_template_html', NULL, 'Template HTML personnalisé pour l''inventaire de sortie')
        ";
        $pdo->exec($sql);
        echo "✓ Paramètres de templates ajoutés\n";
    } else {
        echo "ℹ Paramètres de templates déjà existants\n";
    }
    
    $pdo->commit();
    echo "\n=== Migration 034 terminée avec succès ===\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Erreur lors de la migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
