<?php
include_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        echo json_encode([]);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT n.*, u.username, u.first_name, u.last_name, m.type as message_type 
                                FROM community_notifications n
                                JOIN users u ON n.actor_id = u.id
                                JOIN community_messages m ON n.message_id = m.id
                                WHERE n.user_id = ?
                                ORDER BY n.created_at DESC LIMIT 20");
        $stmt->execute([$userId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $transformed = array_map(function($n) {
            return [
                'id' => $n['id'],
                'user_id' => $n['user_id'],
                'actor_id' => $n['actor_id'],
                'actor_name' => $n['username'] ?? ($n['first_name'] . ' ' . $n['last_name']),
                'message_id' => $n['message_id'],
                'message_type' => $n['message_type'],
                'type' => $n['type'],
                'is_read' => (bool)$n['is_read'],
                'created_at' => $n['created_at']
            ];
        }, $notifs);

        echo json_encode($transformed);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $notifId = $data->id ?? null;

    if ($notifId) {
        try {
            $stmt = $conn->prepare("UPDATE community_notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([$notifId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>
