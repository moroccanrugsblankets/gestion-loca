<?php
/**
 * Constants and configuration for document types
 * Used across the application for consistent document type labeling
 */

/**
 * Document type labels (French)
 * Maps database ENUM values to user-friendly labels
 */
define('DOCUMENT_TYPE_LABELS', [
    'piece_identite' => 'Pièce d\'identité',
    'bulletins_salaire' => 'Bulletins de salaire',
    'contrat_travail' => 'Contrat de travail',
    'avis_imposition' => 'Avis d\'imposition',
    'quittances_loyer' => 'Quittances de loyer',
    'justificatif_revenus' => 'Justificatif de revenus',
    'justificatif_domicile' => 'Justificatif de domicile',
    'autre' => 'Autre document'
]);

/**
 * Get the label for a document type
 * @param string $type The document type enum value
 * @return string The user-friendly label
 */
function getDocumentTypeLabel($type) {
    $labels = DOCUMENT_TYPE_LABELS;
    if (isset($labels[$type])) {
        return $labels[$type];
    }
    // Log unexpected types for monitoring
    error_log("Unexpected document type: $type");
    // Return formatted fallback
    return ucfirst(str_replace('_', ' ', $type));
}
