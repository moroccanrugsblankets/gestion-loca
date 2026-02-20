-- =====================================================
-- MIGRATION: MODULE DE GESTION ET RAPPEL DES LOYERS
-- Date: 2026-02-16
-- Description: Création des tables pour le suivi des paiements de loyers
--              et la configuration des rappels automatiques
-- =====================================================

-- =====================================================
-- TABLE: loyers_tracking
-- Suivi mensuel de l'état des paiements de loyers par bien
-- =====================================================
CREATE TABLE IF NOT EXISTS loyers_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT NOT NULL,
    contrat_id INT NULL COMMENT 'Optionnel - lien vers le contrat en cours',
    
    -- Période concernée
    mois INT NOT NULL COMMENT 'Mois (1-12)',
    annee INT NOT NULL COMMENT 'Année (ex: 2026)',
    
    -- Statut du paiement
    statut_paiement ENUM('paye', 'impaye', 'attente') DEFAULT 'attente' COMMENT 'Statut: payé (vert), impayé (rouge), en attente',
    
    -- Informations de paiement
    date_paiement DATE NULL COMMENT 'Date effective du paiement',
    montant_attendu DECIMAL(10,2) NOT NULL COMMENT 'Montant total attendu (loyer + charges)',
    montant_recu DECIMAL(10,2) NULL COMMENT 'Montant effectivement reçu',
    
    -- Rappels envoyés
    rappel_envoye BOOLEAN DEFAULT FALSE COMMENT 'Un rappel a été envoyé pour cette période',
    date_rappel TIMESTAMP NULL COMMENT 'Date du dernier rappel envoyé',
    nb_rappels INT DEFAULT 0 COMMENT 'Nombre de rappels envoyés',
    
    -- Notes
    notes TEXT NULL COMMENT 'Notes administratives',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Contraintes
    UNIQUE KEY unique_logement_periode (logement_id, mois, annee),
    FOREIGN KEY (logement_id) REFERENCES logements(id) ON DELETE CASCADE,
    FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE SET NULL,
    
    -- Index pour performances
    INDEX idx_statut (statut_paiement),
    INDEX idx_periode (annee, mois),
    INDEX idx_rappel (rappel_envoye, date_rappel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Suivi mensuel des paiements de loyers par bien';

-- =====================================================
-- CONFIGURATION DES RAPPELS
-- =====================================================
-- La configuration sera stockée dans la table 'parametres' existante
-- avec les clés suivantes:
-- 
-- rappel_loyers_dates_envoi : JSON array des jours du mois (ex: [7, 9, 15])
-- rappel_loyers_destinataires : JSON array des emails admins (ex: ["admin1@...", "admin2@..."])
-- rappel_loyers_actif : boolean - active/désactive les rappels automatiques
-- rappel_loyers_inclure_bouton : boolean - inclure le bouton vers l'interface dans les emails
-- 

-- Insertion des paramètres par défaut pour le module de rappels
INSERT INTO parametres (cle, valeur, type, description) VALUES
('rappel_loyers_dates_envoi', '[7, 9, 15]', 'json', 'Jours du mois où les rappels de loyers sont envoyés automatiquement')
ON DUPLICATE KEY UPDATE cle = cle;

INSERT INTO parametres (cle, valeur, type, description) VALUES
('rappel_loyers_destinataires', '[]', 'json', 'Liste des emails administrateurs destinataires des rappels')
ON DUPLICATE KEY UPDATE cle = cle;

INSERT INTO parametres (cle, valeur, type, description) VALUES
('rappel_loyers_actif', '1', 'boolean', 'Active ou désactive les rappels automatiques de loyers')
ON DUPLICATE KEY UPDATE cle = cle;

INSERT INTO parametres (cle, valeur, type, description) VALUES
('rappel_loyers_inclure_bouton', '1', 'boolean', 'Inclure un bouton vers l\'interface de gestion dans les emails de rappel')
ON DUPLICATE KEY UPDATE cle = cle;

-- =====================================================
-- TEMPLATES D'EMAIL POUR LES RAPPELS
-- =====================================================

-- Email de rappel pour loyers impayés
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles) VALUES
(
    'rappel_loyers_impaye',
    'Rappel Loyers Impayés',
    'Rappel: Loyers impayés à vérifier',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .btn:hover { background: #0056b3; }
        .message { background: white; padding: 15px; border-left: 4px solid #dc3545; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Rappel de Loyers</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <div class="message">
                {{status_paiements}}
            </div>
            
            {{bouton_interface}}
            
            <p>Cordialement,<br>
            <strong>My Invest Immobilier</strong></p>
        </div>
        <div class="footer">
            {{signature}}
        </div>
    </div>
</body>
</html>',
    '{{status_paiements}}, {{bouton_interface}}, {{signature}}'
)
ON DUPLICATE KEY UPDATE corps_html = VALUES(corps_html);

-- Email de confirmation quand tous les loyers sont payés
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles) VALUES
(
    'confirmation_loyers_payes',
    'Confirmation Loyers Payés',
    'Confirmation: Tous les loyers sont à jour',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .btn:hover { background: #0056b3; }
        .message { background: white; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Loyers à Jour</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <div class="message">
                {{status_paiements}}
            </div>
            
            {{bouton_interface}}
            
            <p>Cordialement,<br>
            <strong>My Invest Immobilier</strong></p>
        </div>
        <div class="footer">
            {{signature}}
        </div>
    </div>
</body>
</html>',
    '{{status_paiements}}, {{bouton_interface}}, {{signature}}'
)
ON DUPLICATE KEY UPDATE corps_html = VALUES(corps_html);

-- =====================================================
-- CRON JOB POUR LES RAPPELS AUTOMATIQUES
-- =====================================================

INSERT INTO cron_jobs (nom, description, fichier, frequence, cron_expression, actif) VALUES
(
    'Rappel Loyers',
    'Envoi automatique de rappels concernant le paiement des loyers aux administrateurs selon les dates configurées (par défaut: 7, 9, 15 du mois)',
    'cron/rappel-loyers.php',
    'Quotidien (vérifie si c\'est un jour de rappel configuré)',
    '0 9 * * *',
    1
)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- =====================================================
-- INDEX ET OPTIMISATIONS
-- =====================================================

-- Index pour recherche rapide des loyers impayés du mois en cours
CREATE INDEX IF NOT EXISTS idx_statut_mois_courant ON loyers_tracking (statut_paiement, annee, mois);

-- =====================================================
-- MIGRATION TERMINÉE
-- =====================================================
-- Tables créées:
-- - loyers_tracking : Suivi mensuel des paiements
--
-- Paramètres ajoutés:
-- - rappel_loyers_dates_envoi
-- - rappel_loyers_destinataires
-- - rappel_loyers_actif
-- - rappel_loyers_inclure_bouton
--
-- Templates email créés:
-- - rappel_loyers_impaye
-- - confirmation_loyers_payes
--
-- Cron job ajouté:
-- - Rappel Loyers (quotidien à 9h)
-- =====================================================
