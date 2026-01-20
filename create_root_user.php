<?php
include_once 'api/db.php';

$email = 'root@supnum.mr';
$password = password_hash('123456', PASSWORD_DEFAULT);
$role = 'Root';
$first_name = 'Super';
$last_name = 'Admin';

try {
    // Check if exists first (maybe under different role?)
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->rowCount() > 0) {
        $stmt = $conn->prepare("UPDATE users SET role = ?, password = ? WHERE email = ?");
        $stmt->execute([$role, $password, $email]);
        echo "Updated existing user to Root.\n";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (email, password, role, first_name, last_name, is_banned) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$email, $password, $role, $first_name, $last_name]);
        echo "Created new Root user.\n";
    }
    
    echo "Email: $email\n";
    echo "Password: 123456\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
