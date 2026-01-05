<?php
// Do not require db.php as it connects immediately and fails if DB doesn't exist
$host = '127.0.0.1';
$db_name = 'main';
$username = 'root';
$password = 'root'; // Try 'root' as password, or empty string if that fails. The user env usually has root/root or root/empty.

try {
    // Connect without DB name first to create it if needed
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create DB if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
    echo "Database `$db_name` checked/created successfully.\n";

    // Connect to DB
    $pdo->exec("USE `$db_name`");

    // Read SQL file
    $sql = file_get_contents('api/main.sql');
    
    // Execute SQL
    $pdo->exec($sql);
    
    echo "Schema imported successfully.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
