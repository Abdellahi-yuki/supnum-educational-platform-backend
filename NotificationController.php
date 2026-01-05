<?php
require_once 'db.php';

class NotificationController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function index() {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }

        try {
            // Join with users to get actor name, join with messages to get message type (video/image/text)
            $sql = "SELECT n.*, u.username as actor_name, m.type as message_type 
                    FROM notifications n 
                    JOIN users u ON n.actor_id = u.id 
                    JOIN messages m ON n.message_id = m.id 
                    WHERE n.user_id = ? 
                    ORDER BY n.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($notifications);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function markRead() {
        $input = json_decode(file_get_contents('php://input'), true);
        $notif_id = $input['id'] ?? null;

        if (!$notif_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID required']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([$notif_id]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
