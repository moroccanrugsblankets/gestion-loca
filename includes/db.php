<?php
/**
 * Connexion à la base de données
 * My Invest Immobilier
 */

require_once __DIR__ . '/config.php';

/**
 * Obtenir une connexion à la base de données
 * @return PDO|null
 */
function getDbConnection() {
    global $config;
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'] . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
        }
    }
    
    return $pdo;
}

/**
 * Exécuter une requête préparée
 * @param string $sql
 * @param array $params
 * @return PDOStatement|false
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDbConnection();
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
    $pdo = getDbConnection();
    return $pdo->lastInsertId();
}
