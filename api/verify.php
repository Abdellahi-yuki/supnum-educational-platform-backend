<?php
include_once 'db.php';

$data = json_decode(file_get_contents("php://input"));

if(isset($data->email) && isset($data->code)) {
    $email = $data->email;
    $code = $data->code;

    try {
        // Check if there's a pending user in session
        if (!isset($_SESSION['pending_user']) || $_SESSION['pending_user']['email'] !== $email) {
            echo json_encode(["status" => "error", "message" => "Aucune session d'inscription trouvée pour cet email."]);
            exit;
        }

        $pendingUser = $_SESSION['pending_user'];

        // Check if code has expired (5 minutes)
        if (time() > $pendingUser['expires_at']) {
            echo json_encode(["status" => "error", "message" => "Le code de vérification a expiré."]);
            exit;
        }

        // Verify the code
        if ((string)$pendingUser['verification_code'] === (string)$code) {
            // Move to actual users table
            $sql = "INSERT INTO users (username, email, password, first_name, last_name, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)";
            $insert = $conn->prepare($sql);
            $insert->execute([
                $pendingUser['username'],
                $pendingUser['email'],
                $pendingUser['password'],
                $pendingUser['first_name'],
                $pendingUser['last_name'],
                $pendingUser['role']
            ]);

            // Clear session after successful verification
            unset($_SESSION['pending_user']);

            echo json_encode(["status" => "success", "message" => "Compte vérifié et activé !"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Code de vérification incorrect."]);
        }
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
