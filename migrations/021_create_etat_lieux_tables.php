<?php
/**
 * Migration 021: Create État des lieux tables
 * 
 * Creates tables for storing entry and exit inventory data
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    // Table: etat_lieux
    // Stores the main inventory data for entry and exit inspections
    $sql = "
    CREATE TABLE IF NOT EXISTS etat_lieux (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contrat_id INT NOT NULL,
        type ENUM('entree', 'sortie') NOT NULL,
        reference_unique VARCHAR(100) UNIQUE NOT NULL,
        
        -- Identification
        date_etat DATE NOT NULL,
        adresse TEXT NOT NULL,
        appartement VARCHAR(50),
        bailleur_nom VARCHAR(255) DEFAULT 'MY INVEST IMMOBILIER',
        bailleur_representant VARCHAR(255),
        
        -- Relevé des compteurs
        compteur_electricite VARCHAR(50),
        compteur_eau_froide VARCHAR(50),
        compteur_electricite_photo VARCHAR(500),
        compteur_eau_froide_photo VARCHAR(500),
        
        -- Remise/Restitution des clés
        cles_appartement INT DEFAULT 0,
        cles_boite_lettres INT DEFAULT 0,
        cles_total INT DEFAULT 0,
        cles_photo VARCHAR(500),
        cles_conformite ENUM('conforme', 'non_conforme', 'non_applicable') DEFAULT 'non_applicable',
        cles_observations TEXT,
        
        -- Description du logement
        piece_principale TEXT,
        coin_cuisine TEXT,
        salle_eau_wc TEXT,
        etat_general TEXT,
        
        -- Conclusion (pour sortie uniquement)
        comparaison_entree TEXT,
        depot_garantie_status ENUM('restitution_totale', 'restitution_partielle', 'retenue_totale', 'non_applicable') DEFAULT 'non_applicable',
        depot_garantie_montant_retenu DECIMAL(10,2),
        depot_garantie_motif_retenue TEXT,
        
        -- Signatures
        lieu_signature VARCHAR(255),
        date_signature TIMESTAMP NULL,
        bailleur_signature VARCHAR(500),
        statut ENUM('brouillon', 'finalise', 'envoye') DEFAULT 'brouillon',
        
        -- Emails
        email_envoye BOOLEAN DEFAULT FALSE,
        date_envoi_email TIMESTAMP NULL,
        
        -- Metadata
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by VARCHAR(100),
        
        FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
        INDEX idx_contrat (contrat_id),
        INDEX idx_type (type),
        INDEX idx_reference (reference_unique),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✓ Table etat_lieux créée\n";
    
    // Table: etat_lieux_locataires
    // Stores tenant signatures for each inventory
    $sql = "
    CREATE TABLE IF NOT EXISTS etat_lieux_locataires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        etat_lieux_id INT NOT NULL,
        locataire_id INT NOT NULL,
        ordre INT DEFAULT 1,
        
        -- Copie des infos locataire au moment de l'état des lieux
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        
        -- Signature
        signature_data VARCHAR(500),
        signature_timestamp TIMESTAMP NULL,
        signature_ip VARCHAR(45),
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (etat_lieux_id) REFERENCES etat_lieux(id) ON DELETE CASCADE,
        FOREIGN KEY (locataire_id) REFERENCES locataires(id) ON DELETE CASCADE,
        INDEX idx_etat_lieux (etat_lieux_id),
        INDEX idx_locataire (locataire_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✓ Table etat_lieux_locataires créée\n";
    
    // Table: etat_lieux_photos
    // Stores optional photos (internal only, not sent to tenant)
    $sql = "
    CREATE TABLE IF NOT EXISTS etat_lieux_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        etat_lieux_id INT NOT NULL,
        categorie ENUM('compteur_electricite', 'compteur_eau', 'cles', 'piece_principale', 'cuisine', 'salle_eau', 'autre') NOT NULL,
        nom_fichier VARCHAR(255) NOT NULL,
        chemin_fichier VARCHAR(500) NOT NULL,
        description TEXT,
        ordre INT DEFAULT 0,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (etat_lieux_id) REFERENCES etat_lieux(id) ON DELETE CASCADE,
        INDEX idx_etat_lieux (etat_lieux_id),
        INDEX idx_categorie (categorie)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✓ Table etat_lieux_photos créée\n";
    
    // Insert default templates for email
    $sql = "
    INSERT IGNORE INTO parametres (cle, valeur, description, type, created_at) VALUES
    ('etat_lieux_email_subject', 'État des lieux - {{type}} - {{adresse}}', 'Sujet de l\'email pour l\'état des lieux', 'email', NOW()),
    ('etat_lieux_email_template', 
        'Bonjour,\n\nVeuillez trouver ci-joint l''état des lieux {{type_label}} pour le logement situé au :\n{{adresse}}\n\nDate de l''état des lieux : {{date_etat}}\n\nCe document est à conserver précieusement.\n\nCordialement,\nMY INVEST IMMOBILIER',
        'Template email pour l\'état des lieux',
        'email',
        NOW()
    );
    ";
    
    $pdo->exec($sql);
    echo "✓ Templates email créés\n";
    
    // Commit transaction
    $pdo->commit();
    echo "\n✅ Migration 021 terminée avec succès\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Erreur lors de la migration 021: " . $e->getMessage() . "\n";
    exit(1);
}
