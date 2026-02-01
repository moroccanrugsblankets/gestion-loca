<?php
/**
 * Connexion à la base de données
 * My Invest Immobilier
 */

require_once __DIR__ . '/config.php';

/**
 * Connexion PDO globale
 * Initialisée avec la configuration de la base de données
 */
$pdo = null;

try {
    $dsn = "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'] . ";charset=" . $config['DB_CHARSET'];
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ];
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
} catch (PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

/**
 * Exécuter une requête préparée
 * @param string $sql
 * @param array $params
 * @return PDOStatement|false
 */
function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erreur SQL: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer un enregistrement unique
 * @param string $sql
 * @param array $params
 * @return array|false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Récupérer plusieurs enregistrements
 * @param string $sql
 * @param array $params
 * @return array
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Obtenir l'ID du dernier enregistrement inséré
 * @return string
 */
function getLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}
