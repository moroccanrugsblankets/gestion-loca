<?php
/**
 * Migration 026: Fix États des Lieux Schema
 * 
 * Adds missing columns to the existing etats_lieux table to support
 * the detailed entry/exit inventory requirements.
 * 
 * This migration fixes the table name inconsistency and adds all required columns.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 026: Fix États des Lieux Schema ===\n\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'etats_lieux'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("Table etats_lieux does not exist. Please run base schema first.");
    }
    
    // Get existing columns
    $stmt = $pdo->query("SHOW COLUMNS FROM etats_lieux");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "Current columns in etats_lieux: " . count($existingColumns) . "\n";
    
    // Define columns to add
    $columnsToAdd = [
        // Identification
        "ADD COLUMN reference_unique VARCHAR(100) UNIQUE NULL AFTER type",
        "ADD COLUMN adresse TEXT NULL AFTER date_etat",
        "ADD COLUMN appartement VARCHAR(50) NULL AFTER adresse",
        "ADD COLUMN bailleur_nom VARCHAR(255) DEFAULT 'MY INVEST IMMOBILIER' AFTER appartement",
        
        // Relevé des compteurs
        "ADD COLUMN compteur_electricite VARCHAR(50) NULL AFTER bailleur_representant",
        "ADD COLUMN compteur_eau_froide VARCHAR(50) NULL AFTER compteur_electricite",
        "ADD COLUMN compteur_electricite_photo VARCHAR(500) NULL AFTER compteur_eau_froide",
        "ADD COLUMN compteur_eau_froide_photo VARCHAR(500) NULL AFTER compteur_electricite_photo",
        
        // Remise/Restitution des clés
        "ADD COLUMN cles_appartement INT DEFAULT 0 AFTER compteur_eau_froide_photo",
        "ADD COLUMN cles_boite_lettres INT DEFAULT 0 AFTER cles_appartement",
        "ADD COLUMN cles_total INT DEFAULT 0 AFTER cles_boite_lettres",
        "ADD COLUMN cles_photo VARCHAR(500) NULL AFTER cles_total",
        "ADD COLUMN cles_conformite ENUM('conforme', 'non_conforme', 'non_applicable') DEFAULT 'non_applicable' AFTER cles_photo",
        "ADD COLUMN cles_observations TEXT NULL AFTER cles_conformite",
        
        // Description du logement (individual rooms)
        "ADD COLUMN piece_principale TEXT NULL AFTER cles_observations",
        "ADD COLUMN coin_cuisine TEXT NULL AFTER piece_principale",
        "ADD COLUMN salle_eau_wc TEXT NULL AFTER coin_cuisine",
        
        // Conclusion (pour sortie uniquement)
        "ADD COLUMN comparaison_entree TEXT NULL AFTER etat_general",
        "ADD COLUMN depot_garantie_status ENUM('restitution_totale', 'restitution_partielle', 'retenue_totale', 'non_applicable') DEFAULT 'non_applicable' AFTER comparaison_entree",
        "ADD COLUMN depot_garantie_montant_retenu DECIMAL(10,2) NULL AFTER depot_garantie_status",
        "ADD COLUMN depot_garantie_motif_retenue TEXT NULL AFTER depot_garantie_montant_retenu",
        
        // Signatures and status
        "ADD COLUMN lieu_signature VARCHAR(255) NULL AFTER depot_garantie_motif_retenue",
        "ADD COLUMN bailleur_signature VARCHAR(500) NULL AFTER date_signature",
        "ADD COLUMN statut ENUM('brouillon', 'finalise', 'envoye') DEFAULT 'brouillon' AFTER bailleur_signature",
        
        // Emails
        "ADD COLUMN email_envoye BOOLEAN DEFAULT FALSE AFTER statut",
        "ADD COLUMN date_envoi_email TIMESTAMP NULL AFTER email_envoye",
        
        // Metadata
        "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        "ADD COLUMN created_by VARCHAR(100) NULL AFTER updated_at",
    ];
    
    // Add each column if it doesn't exist
    $addedCount = 0;
    foreach ($columnsToAdd as $columnDef) {
        // Extract column name from ADD COLUMN statement
        preg_match('/ADD COLUMN (\w+)/', $columnDef, $matches);
        $columnName = $matches[1];
        
        if (!in_array($columnName, $existingColumns)) {
            try {
                $sql = "ALTER TABLE etats_lieux $columnDef";
                $pdo->exec($sql);
                echo "  ✓ Added column: $columnName\n";
                $addedCount++;
            } catch (PDOException $e) {
                // Column might already exist or other issue
                echo "  ⚠ Could not add $columnName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  - Column already exists: $columnName\n";
        }
    }
    
    // Add indexes if they don't exist
    echo "\nAdding indexes...\n";
    
    $indexes = [
        "ADD INDEX idx_reference (reference_unique)",
        "ADD INDEX idx_statut (statut)",
    ];
    
    foreach ($indexes as $indexDef) {
        try {
            $sql = "ALTER TABLE etats_lieux $indexDef";
            $pdo->exec($sql);
            preg_match('/ADD INDEX (\w+)/', $indexDef, $matches);
            echo "  ✓ Added index: {$matches[1]}\n";
        } catch (PDOException $e) {
            // Index might already exist
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "  ⚠ Index issue: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Create etat_lieux_locataires table (note: uses etats_lieux FK)
    echo "\nCreating etat_lieux_locataires table...\n";
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
        
        FOREIGN KEY (etat_lieux_id) REFERENCES etats_lieux(id) ON DELETE CASCADE,
        FOREIGN KEY (locataire_id) REFERENCES locataires(id) ON DELETE CASCADE,
        INDEX idx_etat_lieux (etat_lieux_id),
        INDEX idx_locataire (locataire_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "  ✓ Table etat_lieux_locataires created\n";
    
    // Create etat_lieux_photos table
    echo "\nCreating etat_lieux_photos table...\n";
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
        
        FOREIGN KEY (etat_lieux_id) REFERENCES etats_lieux(id) ON DELETE CASCADE,
        INDEX idx_etat_lieux (etat_lieux_id),
        INDEX idx_categorie (categorie)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "  ✓ Table etat_lieux_photos created\n";
    
    // Insert default email templates if they don't exist
    echo "\nCreating email templates...\n";
    $sql = "
    INSERT IGNORE INTO parametres (cle, valeur, description, type, created_at) VALUES
    ('etat_lieux_email_subject', 'État des lieux - {{type}} - {{adresse}}', 'Sujet de l\'email pour l\'état des lieux', 'email', NOW()),
    ('etat_lieux_email_template', 
        'Bonjour,\n\nVeuillez trouver ci-joint l''état des lieux {{type_label}} pour le logement situé au :\n{{adresse}}\n\nDate de l''état des lieux : {{date_etat}}\n\nCe document est à conserver précieusement.\n\nCordialement,\nMY INVEST IMMOBILIER',
        'Template email pour l\'état des lieux',
        'email',
        NOW()
    )
    ";
    $pdo->exec($sql);
    echo "  ✓ Email templates created\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Migration 026 completed successfully\n";
    echo "Added $addedCount new columns to etats_lieux table\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error during migration 026: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
