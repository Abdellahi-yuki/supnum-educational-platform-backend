<?php
require_once 'db.php';

try {
    $pdo = Database::getInstance()->getConnection();

    // Create notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, -- The user receiving the notification (post owner)
        actor_id INT NOT NULL, -- The user who commented
        message_id INT NOT NULL, -- The post being commented on
        type VARCHAR(50) DEFAULT 'comment',
        is_read BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Notifications table created successfully.\n";

} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
