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
            // Check if user is banned
            if (isset($user['is_banned']) && $user['is_banned'] == 1) {
                // Check timestamp
                $is_fully_banned = true;
                if (!empty($user['ban_expires_at'])) {
                    $expires = strtotime($user['ban_expires_at']);
                    if (time() > $expires) {
                        // Auto-unban
                        $upd = $conn->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL WHERE id = ?");
                        $upd->execute([$user['id']]);
                        $is_fully_banned = false; // Proceed to login
                    }
                }
                
                if ($is_fully_banned) {
                    $msg = "Votre compte a été banni.";
                    if (!empty($user['ban_expires_at'])) {
                        $msg .= " Le bannissement expire le " . $user['ban_expires_at'];
                    } else {
                        $msg .= " Bannissement permanent.";
                    }
                    echo json_encode(["status" => "error", "message" => $msg]);
                    exit();
                }
            }

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
            // User not found or password incorrect
            echo json_encode(["status" => "error", "message" => "Identifiants invalides ou compte inexistant."]);
        }
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
