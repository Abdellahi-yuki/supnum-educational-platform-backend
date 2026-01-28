<?php
include_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $messageId = $data->message_id ?? null;
    $userId = $data->user_id ?? null;
    $content = $data->content ?? '';

    if (!$messageId || !$userId || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO community_comments (message_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$messageId, $userId, $content]);
        $commentId = $conn->lastInsertId();

        // Create notification for message owner
        $msgStmt = $conn->prepare("SELECT user_id FROM community_messages WHERE id = ?");
        $msgStmt->execute([$messageId]);
        $ownerId = $msgStmt->fetchColumn();

        if ($ownerId && $ownerId != $userId) {
            $notifStmt = $conn->prepare("INSERT INTO community_notifications (user_id, actor_id, message_id, type) VALUES (?, ?, ?, 'comment')");
            $notifStmt->execute([$ownerId, $userId, $messageId]);
        }

        // Fetch user details for response
        $userStmt = $conn->prepare("SELECT username, first_name, last_name, email, profile_pic as profile_path FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'id' => $commentId,
            'message_id' => $messageId,
            'user_id' => $userId,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'username' => $user['username'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'email' => $user['email'] ?? '',
            'profile_path' => $user['profile_path'] ?? null
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
