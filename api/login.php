<?php
include_once 'db.php';

$data = json_decode(file_get_contents("php://input"));

if(isset($data->email) && isset($data->password)) {
    $email = $data->email;
    $password = $data->password;

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            echo json_encode([
                "status" => "success", 
                "message" => "Login successful", 
                "user" => [
                    "id" => $user['id'],
                    "email" => $user['email'],
                    "first_name" => $user['first_name'],
                    "last_name" => $user['last_name'],
                    "role" => isset($user['role']) ? $user['role'] : 'user'
                ]
            ]);
        } else {
            // Check if it's a pending account
            $pend = $conn->prepare("SELECT email FROM pending_users WHERE email = ?");
            $pend->execute([$email]);
            if ($pend->rowCount() > 0) {
                echo json_encode([
                    "status" => "unverified", 
                    "message" => "Votre compte attend la vérification du code.",
                    "email" => $email
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Identifiants invalides ou compte inexistant."]);
            }
        }
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
