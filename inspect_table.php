<?php
$host = '127.0.0.1';
$db_name = 'main';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in users table:\n";
    foreach ($columns as $col) {
        echo "- $col\n";
    }

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
