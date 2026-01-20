<?php
include_once 'api/db.php';

try {
    // 1. Create mailing_lists table
    $conn->exec("CREATE TABLE IF NOT EXISTS mailing_lists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        alias VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Create mailing_list_members table
    $conn->exec("CREATE TABLE IF NOT EXISTS mailing_list_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        list_id INT NOT NULL,
        user_id INT NOT NULL,
        FOREIGN KEY (list_id) REFERENCES mailing_lists(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_member (list_id, user_id)
    )");

    echo "Mailing list tables created successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
