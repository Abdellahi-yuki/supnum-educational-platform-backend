<?php
// backend_php/index.php

// Suppress Warnings/Notices to keep API JSON clean
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

require_once 'Cors.php';
require_once 'db.php';
require_once 'AuthController.php';
require_once 'MessageController.php';
require_once 'CommentController.php';
require_once 'CommentController.php';
require_once 'UploadController.php';
require_once 'ArchiveController.php';
require_once 'NotificationController.php';

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

if ($uri === '/api/auth/signup' && $method === 'POST') {
    $controller = new AuthController();
    $controller->signup();
} elseif ($uri === '/api/auth/login' && $method === 'POST') {
    $controller = new AuthController();
    $controller->login();
} elseif ($uri === '/api/messages' && $method === 'GET') {
    $controller = new MessageController();
    $controller->index();
} elseif ($uri === '/api/messages' && $method === 'POST') {
    $controller = new MessageController();
    $controller->store();
} elseif ($uri === '/api/comments' && $method === 'POST') {
    $controller = new CommentController();
    $controller->store();
} elseif ($uri === '/api/upload/chunk' && $method === 'POST') {
    $controller = new UploadController();
    $controller->uploadChunk();
} elseif ($uri === '/api/archives/toggle' && $method === 'POST') {
    $controller = new ArchiveController();
    $controller->toggle();
} elseif ($uri === '/api/notifications' && $method === 'GET') {
    $controller = new NotificationController();
    $controller->index();
} elseif ($uri === '/api/notifications/read' && $method === 'POST') {
    $controller = new NotificationController();
    $controller->markRead();
} elseif ($uri === '/api/messages/delete' && $method === 'POST') {
    $controller = new MessageController();
    $controller->delete();
} else {
    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'uri' => $uri]);
}
