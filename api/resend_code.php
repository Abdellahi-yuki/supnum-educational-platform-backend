<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include_once 'db.php';
include_once 'mail_config.php';

$data = json_decode(file_get_contents("php://input"));

if(isset($data->email)) {
    $email = $data->email;

    if (!isset($_SESSION['pending_user']) || $_SESSION['pending_user']['email'] !== $email) {
        echo json_encode(["status" => "error", "message" => "Aucune session d'inscription trouvée."]);
        exit;
    }

    $now = time();
    $last_sent = $_SESSION['pending_user']['last_sent_at'];

    // 3 minutes cooldown (matching expiration)
    if ($now - $last_sent < 180) {
        $remaining = 180 - ($now - $last_sent);
        echo json_encode(["status" => "error", "message" => "Veuillez patienter encore $remaining secondes avant de renvoyer le code."]);
        exit;
    }

    $code = rand(100000, 999999);
    $_SESSION['pending_user']['verification_code'] = $code;
    $_SESSION['pending_user']['expires_at'] = $now + (3 * 60);
    $_SESSION['pending_user']['last_sent_at'] = $now;

    $first_name = $_SESSION['pending_user']['first_name'];
    $last_name = $_SESSION['pending_user']['last_name'];

    // Send Email
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
        $mail->Subject = 'Nouveau code de vérification SupNum';
        $mail->Body    = "<h1>Bonjour $first_name $last_name</h1><p>Votre nouveau code de vérification est : <b>$code</b>. Il expirera dans 5 minutes.</p>";
        $mail->AltBody = "Votre nouveau code de vérification est : $code";

        $mail->send();
        echo json_encode(["status" => "success", "message" => "Nouveau code envoyé.", "debug_code" => $code]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Erreur d'envoi d'email: {$mail->ErrorInfo}"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Email manquant"]);
}
?>
