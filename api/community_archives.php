<?php
include_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $userId = $data->user_id ?? null;
    $messageId = $data->message_id ?? null;

    if (!$userId || !$messageId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID and Message ID are required']);
        exit;
    }

    try {
        $check = $conn->prepare("SELECT id FROM community_archived_messages WHERE user_id = ? AND message_id = ?");
        $check->execute([$userId, $messageId]);
        $existing = $check->fetch();

        if ($existing) {
            $del = $conn->prepare("DELETE FROM community_archived_messages WHERE id = ?");
            $del->execute([$existing['id']]);
            echo json_encode(['archived' => false]);
        } else {
            $ins = $conn->prepare("INSERT INTO community_archived_messages (user_id, message_id) VALUES (?, ?)");
            $ins->execute([$userId, $messageId]);
            echo json_encode(['archived' => true]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
