<?php
include_once 'db.php';

// Profile update requires multipart/form-data from frontend
$current_email = isset($_POST['current_email']) ? $_POST['current_email'] : null;
$first_name = isset($_POST['first_name']) ? $_POST['first_name'] : null;
$last_name = isset($_POST['last_name']) ? $_POST['last_name'] : null;
$new_email = isset($_POST['email']) ? $_POST['email'] : null;
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : null;
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : null;

if($current_email && $first_name && $last_name && $new_email && $current_password) {
    try {
        // 1. Mandatory Password Verification
        $stmt = $conn->prepare("SELECT password, profile_pic FROM users WHERE email = ?");
        $stmt->execute([$current_email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            echo json_encode(["status" => "error", "message" => "Mot de passe actuel incorrect."]);
            exit;
        }

        // 2. Handle File Upload (Profile Picture)
        $profile_pic_path = $user['profile_pic']; // Keep current one by default
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_pic']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
            $target_dir = "uploads/";
            $target_file = $target_dir . $file_name;

            // Simple validation
            $check = getimagesize($file_tmp);
            if ($check !== false) {
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $profile_pic_path = $target_file;
                } else {
                    echo json_encode(["status" => "error", "message" => "Erreur lors du téléchargement de l'image."]);
                    exit;
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Le fichier n'est pas une image."]);
                exit;
            }
        }

        // 3. Perform Update
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_pic = ?";
        $params = [$first_name, $last_name, $new_email, $profile_pic_path];

        if ($new_password && !empty($new_password)) {
            $sql .= ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_BCRYPT);
        }

        $sql .= " WHERE email = ?";
        $params[] = $current_email;

        $update_stmt = $conn->prepare($sql);
        $update_stmt->execute($params);

        echo json_encode([
            "status" => "success", 
            "message" => "Profil mis à jour avec succès !",
            "user" => [
                "email" => $new_email,
                "first_name" => $first_name,
                "last_name" => $last_name,
                "profile_pic" => $profile_pic_path
            ]
        ]);

    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erreur SGBD: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Données incomplètes ou mot de passe absent."]);
}
?>
