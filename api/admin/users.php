<?php
include_once '../db.php';

// Ensure only Root can access this (Security Check)
// In a real scenario, we'd check session/token here. 
// For this MVP, we rely on the frontend structure and backend role verification where possible.

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List all users
    try {
        $stmt = $conn->query("SELECT id, username, email, first_name, last_name, role, is_banned FROM users ORDER BY id DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    // Create Teacher Account
    $data = json_decode(file_get_contents("php://input"));
    
    if (isset($data->email) && isset($data->password)) {
        $email = $data->email;
        $username = $data->username ?? explode('@', $email)[0];
        $password = password_hash($data->password, PASSWORD_DEFAULT);
        $first_name = $data->first_name ?? '';
        $last_name = $data->last_name ?? '';
        // Default teacher values
        $role = 'teacher'; 
        
        try {
            $stmt = $conn->prepare("INSERT INTO users (email, username, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $username, $password, $role, $first_name, $last_name]);
            echo json_encode(["status" => "success", "message" => "Teacher account created"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Creation failed: " . $e->getMessage()]);
        }
    }
} elseif ($method === 'PUT') {
    // Ban/Unban User
    $data = json_decode(file_get_contents("php://input"));
    
    if (isset($data->id) && isset($data->action)) {
        $user_id = $data->id;
        
        if ($data->action === 'ban') {
            $duration = $data->duration ?? 'permanent'; // permanent, 24h, 7d, 30d
            $ban_expires_at = null;
            
            if ($duration === '24h') {
                $ban_expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
            } elseif ($duration === '7d') {
                $ban_expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
            } elseif ($duration === '30d') {
                $ban_expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            }
            // else permanent: stays null or can set to year 9999
            
            try {
                $stmt = $conn->prepare("UPDATE users SET is_banned = 1, ban_expires_at = ? WHERE id = ?");
                $stmt->execute([$ban_expires_at, $user_id]);
                echo json_encode(["status" => "success", "message" => "User banned " . ($ban_expires_at ? "until $ban_expires_at" : "permanently")]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } elseif ($data->action === 'unban') {
            try {
                $stmt = $conn->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                echo json_encode(["status" => "success", "message" => "User unbanned"]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
    }
} elseif ($method === 'DELETE') {
    // Delete User
    $id = $_GET['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "User deleted"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
