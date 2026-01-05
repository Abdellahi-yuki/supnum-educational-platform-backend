<?php
// backend/setup_archives.php
require_once 'db.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Create archived_messages table without Foreign Key constraints support (MyISAM compatibility)
    $sql = "CREATE TABLE IF NOT EXISTS archived_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_message (user_id, message_id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table 'archived_messages' created successfully.\n";
    
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
