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
// CONFIGURATION ARRAY
// =====================================================

$config = [
    // =====================================================
    // CONFIGURATION BASE DE DONNÉES
    // =====================================================
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'bail_signature',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',
    
    // =====================================================
    // CONFIGURATION EMAIL
    // =====================================================
    'MAIL_FROM' => 'contact@myinvest-immobilier.com',
    'MAIL_FROM_NAME' => 'MY Invest Immobilier',
    
    // Configuration SMTP pour PHPMailer
    // ⚠️ IMPORTANT: Les emails ne seront PAS envoyés tant que SMTP_PASSWORD n'est pas configuré!
    // Note: Configurez ces valeurs selon votre serveur SMTP
    // IMPORTANT: Ne commitez JAMAIS de vrais mots de passe dans Git
    // Utilisez includes/config.local.php pour vos credentials (voir PHPMAILER_CONFIGURATION.md)
    'SMTP_HOST' => 'smtp.gmail.com', // Exemple: smtp.gmail.com, smtp.office365.com, etc.
    'SMTP_PORT' => 587, // Port SMTP (587 pour TLS, 465 pour SSL)
    'SMTP_SECURE' => 'tls', // 'tls' ou 'ssl'
    'SMTP_AUTH' => true, // Authentification SMTP
    'SMTP_USERNAME' => 'contact@myinvest-immobilier.com', // Votre email SMTP
    'SMTP_PASSWORD' => '', // ⚠️ CONFIGUREZ CECI dans includes/config.local.php - OBLIGATOIRE POUR ENVOYER DES EMAILS!
    'SMTP_DEBUG' => 0, // 0 = off, 1 = client, 2 = client et serveur
    
    // =====================================================
    // CONFIGURATION APPLICATION
    // =====================================================
    'SITE_URL' => 'http://localhost/contrat-bail',
    
    // Coordonnées bancaires
    'IBAN' => 'FR76 1027 8021 6000 0206 1834 585',
    'BIC' => 'CMCIFRA',
    'BANK_NAME' => 'MY Invest Immobilier',
    
    // Coordonnées société
    'COMPANY_NAME' => 'MY Invest Immobilier',
    'COMPANY_EMAIL' => 'contact@myinvest-immobilier.com',
    'COMPANY_PHONE' => '+33 (0)4 XX XX XX XX',
    
    // Emails administrateurs pour les notifications
    'ADMIN_EMAIL' => 'location@myinvest-immobilier.com', // Email principal pour notifications admin
    'ADMIN_EMAIL_SECONDARY' => '', // Email secondaire (optionnel)
    'ADMIN_EMAIL_BCC' => 'contact@myinvest-immobilier.com', // Email en copie cachée (BCC)
    
    // =====================================================
    // WORKFLOW AUTOMATIQUE
    // =====================================================
    // Délai en jours ouvrés avant envoi de la réponse automatique
    'DELAI_REPONSE_JOURS_OUVRES' => 4,
    
    // Jours de la semaine considérés comme ouvrés (1 = Lundi, 5 = Vendredi)
    'JOURS_OUVRES' => [1, 2, 3, 4, 5],
    
    // =====================================================
    // CRITÈRES D'ACCEPTATION AUTOMATIQUE
    // =====================================================
    // Les candidatures sont acceptées automatiquement si :
    // - Revenus >= 2300€ ET
    // - Statut professionnel = CDI avec période d'essai dépassée OU
    // - Statut professionnel = CDD avec revenus >= 3000€
    'REVENUS_MIN_ACCEPTATION' => '2300-3000',
    'STATUTS_PRO_ACCEPTES' => ['CDI', 'CDD', 'Indépendant'],
    
    // =====================================================
    // CONTRAT DE BAIL
    // =====================================================
    'BAILLEUR_NOM' => 'MY Invest Immobilier (SCI)',
    'BAILLEUR_REPRESENTANT' => 'Maxime Alexandre',
    'BAILLEUR_EMAIL' => 'contact@myinvest-immobilier.com',
    
    // =====================================================
    // INFORMATIONS LÉGALES
    // =====================================================
    'DPE_CLASSE_ENERGIE' => 'D',
    'DPE_CLASSE_GES' => 'B',
    'DPE_VALIDITE' => '01/06/2035',
    
    // =====================================================
    // PAGINATION
    // =====================================================
    'ITEMS_PER_PAGE' => 20,
    'MAX_ITEMS_PER_PAGE' => 100,
    
    // =====================================================
    // SÉCURITÉ
    // =====================================================
    'CSRF_TOKEN_NAME' => 'csrf_token',
    
    // Clé pour tokens CSRF (à changer en production)
    'CSRF_KEY' => 'myinvest_csrf_' . date('Y-m-d'),
    
    // reCAPTCHA v3 configuration
    // IMPORTANT: Configurez ces valeurs dans includes/config.local.php pour la production
    'RECAPTCHA_SITE_KEY' => '6LczcMMmAAAAAOVOT8pFKt5lHWzKQvTmJ-YOUR-KEY', // Clé publique à remplacer
    'RECAPTCHA_SECRET_KEY' => '6LczcMMmAAAAACI-8mypCxxPH_2MfUlh_JxgJa4K', // Clé secrète à remplacer
    'RECAPTCHA_ENABLED' => false, // Mettre à true pour activer reCAPTCHA
    'RECAPTCHA_MIN_SCORE' => 0.5, // Score minimum (0.0 à 1.0)
    
    // Salt pour génération de références uniques
    'REFERENCE_SALT' => 'myinvest_2024_',
    
    // Uploads
    'MAX_FILE_SIZE' => 5 * 1024 * 1024, // 5 Mo
    'ALLOWED_EXTENSIONS' => ['jpg', 'jpeg', 'png', 'pdf'],
    'ALLOWED_MIME_TYPES' => [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ],
    
    'TOKEN_EXPIRY_HOURS' => 24,
];

// Load local configuration if exists
if (file_exists(__DIR__ . '/config.local.php')) {
    $localConfig = require __DIR__ . '/config.local.php';
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

// Normalize SITE_URL to remove trailing slash to prevent double slashes in URLs
$config['SITE_URL'] = rtrim($config['SITE_URL'], '/');

// Répertoires (computed values after local config is loaded)
$config['UPLOAD_DIR'] = dirname(__DIR__) . '/uploads/';
$config['PDF_DIR'] = dirname(__DIR__) . '/pdf/';
$config['DOCUMENTS_DIR'] = dirname(__DIR__) . '/documents/';
$config['CANDIDATURE_URL'] = $config['SITE_URL'] . '/candidature/';
$config['ADMIN_URL'] = $config['SITE_URL'] . '/admin/';

// Timezone
date_default_timezone_set('Europe/Paris');

// Affichage des erreurs (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');

// Debug mode (set to false in production to disable debug logging)
// Can be overridden in config.local.php
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true); // true for development, false for production
}

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
    global $config;
    $joursOuvres = 0;
    $current = clone $dateDebut;
    
    while ($current <= $dateFin) {
        $dayOfWeek = (int)$current->format('N'); // 1 (Lundi) à 7 (Dimanche)
        if (in_array($dayOfWeek, $config['JOURS_OUVRES'])) {
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
    global $config;
    $current = clone $date;
    $joursAjoutes = 0;
    
    while ($joursAjoutes < $nbJours) {
        $current->modify('+1 day');
        $dayOfWeek = (int)$current->format('N');
        if (in_array($dayOfWeek, $config['JOURS_OUVRES'])) {
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
    global $config;
    $dayOfWeek = (int)$date->format('N');
    return in_array($dayOfWeek, $config['JOURS_OUVRES']);
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