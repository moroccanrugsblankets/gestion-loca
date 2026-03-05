-- Migration 091 : Workflow collaborateur (4 boutons) et module Décomptes d'intervention
-- Date: 2026-03-05
-- Description:
--   1. Ajoute les nouveaux statuts de workflow aux signalements : pris_en_charge, sur_place, reporte
--   2. Ajoute des champs d'intervention (nb_heures, cout_materiaux) sur les signalements
--   3. Ajoute un champ photo_type (avant/apres) sur signalements_photos
--   4. Ajoute action_token et colonne statut sur signalements_collaborateurs (pour boutons dans les emails)
--   5. Crée la table signalements_decomptes (un seul décompte par signalement)
--   6. Crée la table signalements_decomptes_lignes (lignes du décompte)
--   7. Crée la table signalements_decomptes_fichiers (PJ du décompte)
--   8. Ajoute les templates email pour les 4 actions collaborateur + notifications admin + décompte

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Modifier le statut ENUM pour inclure les nouveaux états de workflow
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE signalements
    MODIFY COLUMN statut ENUM(
        'nouveau',
        'en_cours',
        'pris_en_charge',
        'sur_place',
        'en_attente',
        'reporte',
        'resolu',
        'clos'
    ) NOT NULL DEFAULT 'nouveau';

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Champs d'intervention sur les signalements
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE signalements
    ADD COLUMN nb_heures DECIMAL(5,2) NULL
        COMMENT 'Nombre d''heures d''intervention (renseigné par le collaborateur)',
    ADD COLUMN cout_materiaux DECIMAL(10,2) NULL
        COMMENT 'Coût des matériaux en euros (renseigné par le collaborateur)',
    ADD COLUMN notes_intervention TEXT NULL
        COMMENT 'Notes de fin d''intervention rédigées par le collaborateur';

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Type de photo (avant/après travaux) sur signalements_photos
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE signalements_photos
    ADD COLUMN photo_type ENUM('signalement', 'avant_travaux', 'apres_travaux') NOT NULL DEFAULT 'signalement'
        COMMENT 'Type de photo : signalement initial, avant travaux, après travaux',
    ADD COLUMN uploaded_by VARCHAR(255) NULL
        COMMENT 'Nom du collaborateur ou admin ayant uploadé la photo',
    ADD COLUMN collaborateur_id INT NULL
        COMMENT 'Collaborateur ayant uploadé la photo';

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Token d'action et statut collaborateur dans signalements_collaborateurs
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE signalements_collaborateurs
    ADD COLUMN action_token VARCHAR(64) NULL UNIQUE
        COMMENT 'Token sécurisé pour les boutons d''action dans les emails',
    ADD COLUMN statut_collab ENUM('attribue', 'pris_en_charge', 'sur_place', 'termine', 'impossible') NOT NULL DEFAULT 'attribue'
        COMMENT 'Statut de prise en charge du collaborateur',
    ADD COLUMN date_prise_en_charge TIMESTAMP NULL,
    ADD COLUMN date_sur_place TIMESTAMP NULL,
    ADD COLUMN date_fin_intervention TIMESTAMP NULL,
    ADD INDEX idx_sc_action_token (action_token);

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Table des décomptes d'intervention (un seul par signalement)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS signalements_decomptes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    signalement_id INT NOT NULL UNIQUE
        COMMENT 'Un seul décompte par signalement',
    reference VARCHAR(50) NOT NULL UNIQUE
        COMMENT 'Référence unique du décompte (ex: DEC-20260305-ABCD)',

    -- Statut
    statut ENUM('brouillon', 'valide', 'facture_envoyee') NOT NULL DEFAULT 'brouillon',

    -- Montants calculés (mis à jour à chaque modification)
    montant_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- Informations administratives
    date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_validation TIMESTAMP NULL,
    date_facture TIMESTAMP NULL,
    cree_par VARCHAR(255) NULL COMMENT 'Admin ayant créé le décompte',
    valide_par VARCHAR(255) NULL,

    -- Notes libres
    notes TEXT NULL,

    -- Pièce jointe facture (PDF généré)
    facture_pdf VARCHAR(255) NULL,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dec_signalement (signalement_id),
    FOREIGN KEY (signalement_id) REFERENCES signalements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Décomptes d''intervention locative (un seul par signalement)';

-- ─────────────────────────────────────────────────────────────────────────────
-- 6. Table des lignes du décompte
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS signalements_decomptes_lignes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    decompte_id INT NOT NULL,
    ordre INT NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
    intitule VARCHAR(255) NOT NULL COMMENT 'Intitulé de la ligne (modifiable)',
    montant DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Montant en euros',

    INDEX idx_dl_decompte (decompte_id),
    FOREIGN KEY (decompte_id) REFERENCES signalements_decomptes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lignes d''un décompte d''intervention';

-- ─────────────────────────────────────────────────────────────────────────────
-- 7. Table des pièces jointes du décompte
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS signalements_decomptes_fichiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    decompte_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    taille INT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(255) NULL,

    INDEX idx_df_decompte (decompte_id),
    FOREIGN KEY (decompte_id) REFERENCES signalements_decomptes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pièces jointes d''un décompte d''intervention';

-- ─────────────────────────────────────────────────────────────────────────────
-- 8. Templates email pour les 4 boutons d'action collaborateur
-- ─────────────────────────────────────────────────────────────────────────────

-- 8a. Notification admin : Pris en charge
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, ordre, created_at)
VALUES (
    'signalement_pris_en_charge_admin',
    'Signalement pris en charge (notification admin)',
    '✅ Signalement pris en charge — Réf. {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Signalement pris en charge</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#2980b9 0%,#3498db 100%);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
        <h1 style="margin:0;font-size:24px;">🔵 Signalement Pris en Charge</h1>
        <p style="margin:10px 0 0;font-size:15px;">{{company}}</p>
    </div>
    <div style="background:#fff;padding:30px;border:1px solid #e0e0e0;border-top:none;">
        <p>Le collaborateur <strong>{{collab_nom}}</strong> a confirmé la prise en charge du signalement suivant :</p>
        <div style="background:#e8f4fd;border-left:4px solid #3498db;padding:15px;margin:20px 0;border-radius:0 5px 5px 0;">
            <p style="margin:5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin:5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin:5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin:5px 0;"><strong>Date de prise en charge :</strong> {{date_action}}</p>
        </div>
        <p><a href="{{lien_admin}}" style="display:inline-block;background:#3498db;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;">Voir le signalement</a></p>
    </div>
    <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
        <p style="margin:0;color:#666;font-size:12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'reference,titre,adresse,collab_nom,date_action,lien_admin,company',
    'Notification envoyée aux admins quand un collaborateur confirme la prise en charge d''un signalement',
    1, 94, NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 8b. Notification admin : Sur place
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, ordre, created_at)
VALUES (
    'signalement_sur_place_admin',
    'Collaborateur sur place (notification admin)',
    '🟠 Collaborateur sur place — Réf. {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Collaborateur sur place</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#e67e22 0%,#d35400 100%);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
        <h1 style="margin:0;font-size:24px;">🟠 Collaborateur Sur Place</h1>
        <p style="margin:10px 0 0;font-size:15px;">{{company}}</p>
    </div>
    <div style="background:#fff;padding:30px;border:1px solid #e0e0e0;border-top:none;">
        <p>Le collaborateur <strong>{{collab_nom}}</strong> est maintenant sur place pour le signalement suivant :</p>
        <div style="background:#fff3e0;border-left:4px solid #e67e22;padding:15px;margin:20px 0;border-radius:0 5px 5px 0;">
            <p style="margin:5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin:5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin:5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin:5px 0;"><strong>Heure d''arrivée :</strong> {{date_action}}</p>
        </div>
        <p><a href="{{lien_admin}}" style="display:inline-block;background:#e67e22;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;">Voir le signalement</a></p>
    </div>
    <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
        <p style="margin:0;color:#666;font-size:12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'reference,titre,adresse,collab_nom,date_action,lien_admin,company',
    'Notification envoyée aux admins quand un collaborateur confirme sa présence sur place',
    1, 95, NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 8c. Notification admin : Intervention terminée
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, ordre, created_at)
VALUES (
    'signalement_intervention_terminee_admin',
    'Intervention terminée (notification admin)',
    '🟢 Intervention terminée — Réf. {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Intervention terminée</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#27ae60 0%,#2ecc71 100%);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
        <h1 style="margin:0;font-size:24px;">🟢 Intervention Terminée</h1>
        <p style="margin:10px 0 0;font-size:15px;">{{company}}</p>
    </div>
    <div style="background:#fff;padding:30px;border:1px solid #e0e0e0;border-top:none;">
        <p>Le collaborateur <strong>{{collab_nom}}</strong> a terminé l''intervention pour le signalement suivant :</p>
        <div style="background:#e8f8f0;border-left:4px solid #27ae60;padding:15px;margin:20px 0;border-radius:0 5px 5px 0;">
            <p style="margin:5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin:5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin:5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin:5px 0;"><strong>Date de fin :</strong> {{date_action}}</p>
            {{nb_heures_html}}
            {{cout_materiaux_html}}
        </div>
        {{notes_intervention_html}}
        <p><a href="{{lien_admin}}" style="display:inline-block;background:#27ae60;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;">Voir le signalement</a></p>
    </div>
    <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
        <p style="margin:0;color:#666;font-size:12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'reference,titre,adresse,collab_nom,date_action,nb_heures_html,cout_materiaux_html,notes_intervention_html,lien_admin,company',
    'Notification envoyée aux admins quand un collaborateur déclare l''intervention terminée',
    1, 96, NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 8d. Notification admin : Impossible / Reporté
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, ordre, created_at)
VALUES (
    'signalement_impossible_admin',
    'Intervention impossible / reportée (notification admin)',
    '🔴 Intervention impossible — Réf. {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Intervention impossible</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
        <h1 style="margin:0;font-size:24px;">🔴 Intervention Impossible / Reportée</h1>
        <p style="margin:10px 0 0;font-size:15px;">{{company}}</p>
    </div>
    <div style="background:#fff;padding:30px;border:1px solid #e0e0e0;border-top:none;">
        <p>Le collaborateur <strong>{{collab_nom}}</strong> signale une impossibilité ou un report pour le signalement suivant :</p>
        <div style="background:#fdecea;border-left:4px solid #e74c3c;padding:15px;margin:20px 0;border-radius:0 5px 5px 0;">
            <p style="margin:5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin:5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin:5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin:5px 0;"><strong>Date :</strong> {{date_action}}</p>
            {{motif_html}}
        </div>
        <p><a href="{{lien_admin}}" style="display:inline-block;background:#e74c3c;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;">Voir le signalement</a></p>
    </div>
    <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
        <p style="margin:0;color:#666;font-size:12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'reference,titre,adresse,collab_nom,date_action,motif_html,lien_admin,company',
    'Notification envoyée aux admins quand un collaborateur signale une impossibilité ou un report',
    1, 97, NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 8e. Email au locataire : confirmation d'intervention terminée (avec bouton de confirmation)
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, ordre, created_at)
VALUES (
    'signalement_intervention_terminee_locataire',
    'Confirmation d''intervention — Locataire',
    'Votre intervention est terminée — Réf. {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Intervention terminée</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#27ae60 0%,#2ecc71 100%);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
        <h1 style="margin:0;font-size:24px;">✅ Intervention Terminée</h1>
        <p style="margin:10px 0 0;font-size:15px;">{{company}}</p>
    </div>
    <div style="background:#fff;padding:30px;border:1px solid #e0e0e0;border-top:none;">
        <p>Bonjour {{prenom}} {{nom}},</p>
        <p>Nous vous informons que l''intervention concernant votre signalement a été réalisée.</p>
        <div style="background:#e8f8f0;border-left:4px solid #27ae60;padding:15px;margin:20px 0;border-radius:0 5px 5px 0;">
            <p style="margin:5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin:5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin:5px 0;"><strong>Logement :</strong> {{adresse}}</p>
        </div>
        <p>Pouvez-vous confirmer que l''intervention a bien été réalisée à votre satisfaction ?</p>
        <div style="text-align:center;margin:30px 0;">
            <a href="{{lien_confirmation}}" style="display:inline-block;background:#27ae60;color:white;padding:16px 32px;text-decoration:none;border-radius:8px;font-size:16px;font-weight:bold;">
                ✅ Confirmer l''intervention
            </a>
        </div>
        <p style="color:#666;font-size:13px;">Si vous avez des questions ou si le problème n''est pas résolu, n''hésitez pas à nous contacter.</p>
    </div>
    <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
        <p style="margin:0;color:#666;font-size:12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'prenom,nom,reference,titre,adresse,lien_confirmation,company',
    'Email envoyé au locataire avec bouton de confirmation quand l''intervention est terminée',
    1, 98, NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 8f. Notification collaborateurs : décompte validé
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, ordre, created_at)
VALUES (
    'decompte_valide_collab',
    'Décompte d''intervention validé (collaborateurs)',
    'Décompte validé — Réf. {{reference_decompte}}',
    '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Décompte validé</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <div style="background:linear-gradient(135deg,#2c3e50 0%,#3498db 100%);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
        <h1 style="margin:0;font-size:24px;">📋 Décompte d''Intervention Validé</h1>
        <p style="margin:10px 0 0;font-size:15px;">{{company}}</p>
    </div>
    <div style="background:#fff;padding:30px;border:1px solid #e0e0e0;border-top:none;">
        <p>Le décompte d''intervention pour le signalement suivant a été validé :</p>
        <div style="background:#e8f4fd;border-left:4px solid #3498db;padding:15px;margin:20px 0;border-radius:0 5px 5px 0;">
            <p style="margin:5px 0;"><strong>Décompte :</strong> <code>{{reference_decompte}}</code></p>
            <p style="margin:5px 0;"><strong>Signalement :</strong> {{reference_signalement}}</p>
            <p style="margin:5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin:5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin:5px 0;"><strong>Montant total :</strong> <strong>{{montant_total}} €</strong></p>
        </div>
        {{lignes_html}}
    </div>
    <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
        <p style="margin:0;color:#666;font-size:12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'reference_decompte,reference_signalement,titre,adresse,montant_total,lignes_html,company',
    'Email envoyé aux collaborateurs (service technique, etc.) quand un décompte est validé',
    1, 99, NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;


-- ─────────────────────────────────────────────────────────────────────────────
-- 9. Mettre à jour le template signalement_attribution pour inclure les 4 boutons d'action
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE email_templates
SET
    corps_html = REPLACE(
        corps_html,
        '{{photos_html}}\n    </div>',
        '{{photos_html}}\n        {{action_buttons_html}}\n    </div>'
    ),
    variables_disponibles = CONCAT(variables_disponibles, ',action_buttons_html')
WHERE identifiant = 'signalement_attribution'
  AND corps_html NOT LIKE '%action_buttons_html%';
