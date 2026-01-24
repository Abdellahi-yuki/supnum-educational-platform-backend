<?php
// Start session for unverified registrations
// Start session for unverified registrations
session_start([
    'cookie_lifetime' => 0, // Session cookie expires when browser closes
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

// DEBUG MODE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// CORS configuration
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");

// Handle preflight OPTIONS request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$db_name = 'main'; // Corrected to 'main'
$username = 'root';
$password = 'root';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Return JSON error so frontend doesn't crash on parsing
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $e->getMessage()]);
    exit;
}
?>
