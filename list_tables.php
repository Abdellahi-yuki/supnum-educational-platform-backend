<?php
require_once 'db.php';
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($tables);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
