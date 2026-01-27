<?php
/**
 * Configuration unifiée de l'application de gestion des baux
 * My Invest Immobilier - Système complet
 * Version 2.0
 * 
 * Base de données unique pour:
 * - Candidatures et workflow automatisé
 * - Signature électronique des contrats
 * - Gestion du cycle de vie des baux
 * - États des lieux et paiements
 */

// Démarrage de la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// CONFIGURATION BASE DE DONNÉES
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'bail_signature');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// CONFIGURATION EMAIL
// =====================================================

define('MAIL_FROM', 'contact@myinvest-immobilier.com');
define('MAIL_FROM_NAME', 'MY Invest Immobilier');

// =====================================================
// CONFIGURATION APPLICATION
// =====================================================

define('SITE_URL', 'http://localhost/contrat-bail');
define('CANDIDATURE_URL', SITE_URL . '/candidature/');
define('ADMIN_URL', SITE_URL . '/admin/');

// Répertoires
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('PDF_DIR', dirname(__DIR__) . '/pdf/');
define('DOCUMENTS_DIR', dirname(__DIR__) . '/documents/');
define('TOKEN_EXPIRY_HOURS', 24);

// Coordonnées bancaires
define('IBAN', 'FR76 1027 8021 6000 0206 1834 585');
define('BIC', 'CMCIFRA');
define('BANK_NAME', 'MY Invest Immobilier');

// Coordonnées société
define('COMPANY_NAME', 'MY Invest Immobilier');
define('COMPANY_EMAIL', 'contact@myinvest-immobilier.com');
define('COMPANY_PHONE', '+33 (0)4 XX XX XX XX');

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
// CONTRAT DE BAIL
// =====================================================

define('BAILLEUR_NOM', 'MY Invest Immobilier (SCI)');
define('BAILLEUR_REPRESENTANT', 'Maxime Alexandre');
define('BAILLEUR_EMAIL', 'contact@myinvest-immobilier.com');

// =====================================================
// INFORMATIONS LÉGALES
// =====================================================

define('DPE_CLASSE_ENERGIE', 'D');
define('DPE_CLASSE_GES', 'B');
define('DPE_VALIDITE', '01/06/2035');

// =====================================================
// PAGINATION
// =====================================================

define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// =====================================================
// SÉCURITÉ
// =====================================================

define('CSRF_TOKEN_NAME', 'csrf_token');

// Clé pour tokens CSRF (à changer en production)
define('CSRF_KEY', 'myinvest_csrf_' . date('Y-m-d'));

// Salt pour génération de références uniques
define('REFERENCE_SALT', 'myinvest_2024_');

// Uploads
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 Mo
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'application/pdf'
]);

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
// FONCTIONS UTILITAIRES
// =====================================================

/**
 * Calcule le nombre de jours ouvrés entre deux dates
 * @param DateTime $dateDebut Date de début
 * @param DateTime $dateFin Date de fin
 * @return int Nombre de jours ouvrés
 */
function calculerJoursOuvres(DateTime $dateDebut, DateTime $dateFin): int {
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
 * @param DateTime $date Date de départ
 * @param int $nbJours Nombre de jours ouvrés à ajouter
 * @return DateTime Nouvelle date
 */
function ajouterJoursOuvres(DateTime $date, int $nbJours): DateTime {
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
 * @param DateTime $date Date à vérifier
 * @return bool True si jour ouvré
 */
function estJourOuvre(DateTime $date): bool {
    $dayOfWeek = (int)$date->format('N');
    return in_array($dayOfWeek, JOURS_OUVRES);
}

/**
 * Génère une référence unique
 * @param string $prefix Préfixe de la référence (ex: 'CAND', 'CONT')
 * @return string Référence unique
 */
function genererReferenceUnique(string $prefix = 'CAND'): string {
    try {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    } catch (Exception $e) {
        // Fallback si random_bytes échoue (très rare)
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }
}

/**
 * Génère un token sécurisé
 * @return string Token hexadécimal de 64 caractères
 */
function genererToken(): string {
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Fallback si random_bytes échoue (très rare)
        return hash('sha256', uniqid('', true) . microtime());
    }
}
