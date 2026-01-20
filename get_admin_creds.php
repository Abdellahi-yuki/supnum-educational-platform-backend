<?php
include_once 'api/db.php';

try {
    $stmt = $conn->query("SELECT id, email, role FROM users WHERE role = 'Root' OR role = 'admin' LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Found Admin User:\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "ID: " . $user['id'] . "\n";
    } else {
        echo "No admin user found.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
