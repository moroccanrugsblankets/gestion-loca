<?php
/**
 * Configuration de l'application de signature de bail
 * My Invest Immobilier
 */

// Démarrage de la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'bail_signature');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration email
define('MAIL_FROM', 'contact@myinvest-immobilier.com');
define('MAIL_FROM_NAME', 'MY Invest Immobilier');

// Configuration application
define('SITE_URL', 'http://localhost/contrat-bail');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('PDF_DIR', dirname(__DIR__) . '/pdf/');
define('TOKEN_EXPIRY_HOURS', 24);

// Coordonnées bancaires
define('IBAN', 'FR76 1027 8021 6000 0206 1834 585');
define('BIC', 'CMCIFRA');
define('BANK_NAME', 'MY Invest Immobilier');

// Coordonnées société
define('COMPANY_NAME', 'MY Invest Immobilier');
define('COMPANY_EMAIL', 'contact@myinvest-immobilier.com');
define('COMPANY_PHONE', '');

// Sécurité
define('CSRF_TOKEN_NAME', 'csrf_token');

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
