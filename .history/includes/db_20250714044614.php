<?php
// S'assurer que config.php est inclus pour les constantes DB
if (!defined('DB_HOST')) {
    $config_file = dirname(__DIR__) . '/config.php';
    if (file_exists($config_file)) {
        require_once $config_file;
    } else {
        die("Erreur: Le fichier de configuration config.php est introuvable. Vérifiez que le fichier config.php existe à la racine du projet.");
    }
}

// Vérifier que toutes les constantes nécessaires sont définies
$required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required_constants as $constant) {
    if (!defined($constant)) {
        die("Erreur: La constante de configuration '$constant' n'est pas définie dans config.php");
    }
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonctions de base de données
function executeQuery($sql, $params = []) {
    global $pdo;
    
    // Debug : Affiche la requête réelle
    $fullQuery = $sql;
    foreach ($params as $param) {
        $fullQuery = preg_replace('/\?/', "'".$param."'", $fullQuery, 1);
    }
    error_log("Requête SQL: " . $fullQuery);

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Affiche l'erreur SQL complète pour le debug temporaire
        die("ERREUR SQL: " . $e->getMessage() . "<br>Requête: " . $sql);
    }
}
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}
?>