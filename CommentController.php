<?php
// backend_php/CommentController.php

require_once 'db.php';

class CommentController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function store() {
        $input = json_decode(file_get_contents('php://input'), true);

        $message_id = $input['message_id'] ?? null;
        $user_id = $input['user_id'] ?? null;
        $content = $input['content'] ?? null;

        if (!$message_id || !$user_id || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            return;
        }

        $createdAt = date('Y-m-d H:i:s');
        $updatedAt = $createdAt;

        try {
            $stmt = $this->pdo->prepare("INSERT INTO comments (message_id, user_id, content, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$message_id, $user_id, $content, $createdAt]);
            $commentId = $this->pdo->lastInsertId();

            // Fetch user info for response
            $userStmt = $this->pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $userStmt->execute([$user_id]);
            $user = $userStmt->fetch();

            echo json_encode([
                'id' => $commentId,
                'message_id' => $message_id,
                'user_id' => $user_id,
                'content' => $content,
                'created_at' => $createdAt,
                'username' => $user['username'] ?? 'Unknown',
                'email' => $user['email'] ?? null,
            ]);

            // --- Notification Notification Logic ---
            // 1. Get the owner of the message
            $msgStmt = $this->pdo->prepare("SELECT user_id FROM messages WHERE id = ?");
            $msgStmt->execute([$message_id]);
            $msgOwner = $msgStmt->fetchColumn();

            // 2. If valid and not self-comment, create notification
            if ($msgOwner && $msgOwner != $user_id) {
                $notifStmt = $this->pdo->prepare("INSERT INTO notifications (user_id, actor_id, message_id) VALUES (?, ?, ?)");
                $notifStmt->execute([$msgOwner, $user_id, $message_id]);
            }
            // ---------------------------------------

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
