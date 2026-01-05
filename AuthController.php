<?php
// backend_php/AuthController.php

require_once 'db.php';

class AuthController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function signup() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            return;
        }

        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'The email has already been taken.']);
            return;
        }

        $username = explode('@', $email)[0];
        // Use BCRYPT to match legacy register.php
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $createdAt = date('Y-m-d H:i:s');
        $updatedAt = $createdAt;
        // Auto-verify users created via this endpoint
        $isVerified = 1;

        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, created_at, is_verified) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $createdAt, $isVerified]);
            $userId = $this->pdo->lastInsertId();

            echo json_encode([
                'id' => $userId,
                'username' => $username,
                'email' => $email
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'This email does not match any account. Please check your email address.']);
            return;
        }

        if (!password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Incorrect password. Please try again.']);
            return;
        }

        echo json_encode([
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'user'
        ]);
    }
}
