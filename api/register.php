<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include_once 'db.php';
include_once 'mail_config.php';

$data = json_decode(file_get_contents("php://input"));

if(isset($data->email) && isset($data->password) && isset($data->first_name) && isset($data->last_name)) {
    $email = $data->email;
    $password = password_hash($data->password, PASSWORD_BCRYPT);
    $first_name = $data->first_name;
    $last_name = $data->last_name;
    // Default to 'user' (Student) if not provided, else use provided role (admin/user)
    $role = isset($data->role) ? $data->role : 'user'; 
    $code = rand(100000, 999999);

    if (!str_ends_with($email, '@supnum.mr')) {
        echo json_encode(["status" => "error", "message" => "L'email doit finir par @supnum.mr"]);
        exit;
    }

    try {
        // Check if user is already verified in actual users table
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if($check->rowCount() > 0){
            echo json_encode(["status" => "error", "message" => "Cet email est déjà associé à un compte vérifié."]);
            exit;
        }

        // Auto-generate username or check existing
        $username = explode('@', $email)[0];
        
        // Store user data and verification code in session instead of database
        $_SESSION['pending_user'] = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role,
            'verification_code' => $code,
            'expires_at' => time() + (3 * 60), // 3 minutes expiration
            'last_sent_at' => time()
        ];

        // Send Email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();                                            
            $mail->Host       = SMTP_HOST;                     
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = SMTP_USER;                     
            $mail->Password   = SMTP_PASS;                               
            $mail->SMTPSecure = SMTP_SECURE;            
            $mail->Port       = SMTP_PORT;                                    

            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); 
            $mail->addAddress($email);     

            $mail->isHTML(true);                                  
            $mail->Subject = 'Code de vérification SupNum';
            $mail->Body    = "<h1>Bienvenue $first_name $last_name</h1><p>Votre code de vérification est : <b>$code</b></p>";
            $mail->AltBody = "Votre code de vérification est : $code";

            $mail->send();
            
            echo json_encode(["status" => "success", "message" => "Code envoyé.", "debug_code" => $code]); 

        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Erreur d'envoi d'email: {$mail->ErrorInfo}"]);
        }

    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erreur SGBD: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Données incomplètes"]);
}
?>
