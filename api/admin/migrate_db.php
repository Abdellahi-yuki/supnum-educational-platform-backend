<?php
include_once __DIR__ . '/../db.php';

// Execute Schema Updates
try {
    echo "Starting Migration...<br>";

    // 1. Add is_banned column to users if not exists
    $stmt = $conn->prepare("SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_banned'");
    $stmt->execute([$db_name]);
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT FALSE");
        echo "Column 'is_banned' added to 'users'.<br>";
    } else {
        echo "Column 'is_banned' already exists.<br>";
    }

    // 2. Create community_reports table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS community_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason TEXT,
        status VARCHAR(50) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    echo "Table 'community_reports' checked/created.<br>";

    echo "Migration Complete.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
