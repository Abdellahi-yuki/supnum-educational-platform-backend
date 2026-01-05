<?php
require_once 'db.php';
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
