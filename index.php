<?php
// backend_php/index.php

// Suppress Warnings/Notices to keep API JSON clean
// error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
// ini_set('display_errors', 0);

// DEBUG MODE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'Cors.php';
require_once 'db.php';
require_once 'MailController.php';

// Handle CORS
handleCors();

// Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Serve static files if they exist (for uploads)
if (file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // Let the built-in server handle it? 
    // If using php -S, it handles it if we return false.
    return false; 
}

// Router Switch
// Normalized URI: remove /backend_php/ if served from subdirectory, but we assume root of server is active directory
// We will serve from backend_php directory so /api/... works directly.

if ($uri === '/api/mail/messages' && $method === 'GET') {
    $controller = new MailController();
    $controller->getMessages();
} elseif ($uri === '/api/mail/messages' && $method === 'POST') {
    $controller = new MailController();
    $controller->sendMessage();
} elseif (preg_match('#^/api/mail/messages/(\d+)$#', $uri, $matches) && $method === 'PUT') {
    $controller = new MailController();
    $controller->updateMessage($matches[1]);
} else {
    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'uri' => $uri]);
}
