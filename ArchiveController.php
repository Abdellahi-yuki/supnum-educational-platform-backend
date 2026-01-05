<?php
// backend/ArchiveController.php

require_once 'db.php';

class ArchiveController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function toggle() {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? null;
        $messageId = $data['message_id'] ?? null;

        if (!$userId || !$messageId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id or message_id']);
            return;
        }

        try {
            // Check availability
            $stmt = $this->pdo->prepare("SELECT id FROM archived_messages WHERE user_id = ? AND message_id = ?");
            $stmt->execute([$userId, $messageId]);
            
            if ($stmt->fetch()) {
                 // Delete
                 $del = $this->pdo->prepare("DELETE FROM archived_messages WHERE user_id = ? AND message_id = ?");
                 $del->execute([$userId, $messageId]);
                 echo json_encode(['status' => 'removed', 'is_archived' => false]);
            } else {
                 // Insert
                 $ins = $this->pdo->prepare("INSERT INTO archived_messages (user_id, message_id) VALUES (?, ?)");
                 $ins->execute([$userId, $messageId]);
                 echo json_encode(['status' => 'added', 'is_archived' => true]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
