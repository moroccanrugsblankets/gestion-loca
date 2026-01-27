<?php
/**
 * Configuration principale - Système de gestion des candidatures locatives
 * My Invest Immobilier
 * Version 2.0
 */

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Europe/Paris');

// Affichage des erreurs (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');

// Gestion des erreurs pour éviter les 500
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

// =====================================================
// CONFIGURATION BASE DE DONNÉES
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'myinvest_location');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// CONFIGURATION EMAIL
// =====================================================

define('MAIL_FROM', 'contact@myinvest-immobilier.com');
define('MAIL_FROM_NAME', 'MY Invest Immobilier');
define('COMPANY_NAME', 'MY Invest Immobilier');
define('COMPANY_EMAIL', 'contact@myinvest-immobilier.com');
define('COMPANY_PHONE', '+33 (0)4 XX XX XX XX');

// =====================================================
// CONFIGURATION APPLICATION
// =====================================================

define('SITE_URL', 'https://www.myinvest-immobilier.com');
define('CANDIDATURE_URL', SITE_URL . '/candidature/');
define('ADMIN_URL', SITE_URL . '/admin/');

// Répertoires
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PDF_DIR', __DIR__ . '/../pdf/');
define('DOCUMENTS_DIR', __DIR__ . '/../documents/');

// Tailles limites pour uploads
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'application/pdf'
]);

// =====================================================
// WORKFLOW AUTOMATIQUE
// =====================================================

// Délai en jours ouvrés avant envoi de la réponse automatique
define('DELAI_REPONSE_JOURS_OUVRES', 4);

// Jours de la semaine considérés comme ouvrés (1 = Lundi, 5 = Vendredi)
define('JOURS_OUVRES', [1, 2, 3, 4, 5]);

// =====================================================
// CRITÈRES D'ACCEPTATION AUTOMATIQUE
// =====================================================

// Les candidatures sont acceptées automatiquement si :
// - Revenus >= 2300€ ET
// - Statut professionnel = CDI avec période d'essai dépassée OU
// - Statut professionnel = CDD avec revenus >= 3000€

define('REVENUS_MIN_ACCEPTATION', '2300-3000');
define('STATUTS_PRO_ACCEPTES', ['CDI', 'CDD', 'Indépendant']);

// =====================================================
// COORDONNÉES BANCAIRES
// =====================================================

define('BANK_NAME', 'MY Invest Immobilier');
define('IBAN', 'FR76 1027 8021 6000 0206 1834 585');
define('BIC', 'CMCIFRA');

// =====================================================
// CONTRAT DE BAIL
// =====================================================

define('TOKEN_EXPIRY_HOURS', 24);
define('BAILLEUR_NOM', 'MY Invest Immobilier (SCI)');
define('BAILLEUR_REPRESENTANT', 'Maxime Alexandre');
define('BAILLEUR_EMAIL', 'contact@myinvest-immobilier.com');

// =====================================================
// SÉCURITÉ
// =====================================================

// Clé pour tokens CSRF (à changer en production)
define('CSRF_KEY', 'myinvest_csrf_' . date('Y-m-d'));

// Salt pour génération de références uniques
define('REFERENCE_SALT', 'myinvest_2024_');

// =====================================================
// PAGINATION
// =====================================================

define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// =====================================================
// INFORMATIONS LÉGALES
// =====================================================

define('DPE_CLASSE_ENERGIE', 'D');
define('DPE_CLASSE_GES', 'B');
define('DPE_VALIDITE', '01/06/2035');

// =====================================================
// FONCTIONS UTILITAIRES
// =====================================================

/**
 * Calcule le nombre de jours ouvrés entre deux dates
 */
function calculerJoursOuvres($dateDebut, $dateFin) {
    $joursOuvres = 0;
    $current = clone $dateDebut;
    
    while ($current <= $dateFin) {
        $dayOfWeek = (int)$current->format('N'); // 1 (Lundi) à 7 (Dimanche)
        if (in_array($dayOfWeek, JOURS_OUVRES)) {
            $joursOuvres++;
        }
        $current->modify('+1 day');
    }
    
    return $joursOuvres;
}

/**
 * Calcule la date après X jours ouvrés
 */
function ajouterJoursOuvres($date, $nbJours) {
    $current = clone $date;
    $joursAjoutes = 0;
    
    while ($joursAjoutes < $nbJours) {
        $current->modify('+1 day');
        $dayOfWeek = (int)$current->format('N');
        if (in_array($dayOfWeek, JOURS_OUVRES)) {
            $joursAjoutes++;
        }
    }
    
    return $current;
}

/**
 * Vérifie si c'est un jour ouvré
 */
function estJourOuvre($date) {
    $dayOfWeek = (int)$date->format('N');
    return in_array($dayOfWeek, JOURS_OUVRES);
}

/**
 * Génère une référence unique
 */
function genererReferenceUnique($prefix = 'CAND') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/**
 * Génère un token sécurisé
 */
function genererToken() {
    return bin2hex(random_bytes(32));
}
