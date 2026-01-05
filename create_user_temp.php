<?php
require_once 'db.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $email = 'test@supnum.mr';
    $password = 'password';
    $hashedPassword = hash('sha256', $password);
    $username = 'testuser';

    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "User already exists. Updating password to 'password'...\n";
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        echo "Password updated.\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword]);
        echo "User created successfully.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
