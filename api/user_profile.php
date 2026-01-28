<?php
include_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }
    
    try {
        // Get user profile information
        $userSql = "SELECT id, username, email, first_name, last_name, profile_pic as profile_path, created_at,
                    (SELECT COUNT(*) FROM community_messages WHERE user_id = ?) as message_count,
                    (SELECT COUNT(*) FROM community_comments WHERE user_id = ?) as comment_count
                    FROM users WHERE id = ?";
        
        $userStmt = $conn->prepare($userSql);
        $userStmt->execute([$userId, $userId, $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        
        // Get user's messages
        $messagesSql = "SELECT m.*, 
                        (SELECT COUNT(*) FROM community_comments WHERE message_id = m.id) as comment_count
                        FROM community_messages m
                        WHERE m.user_id = ?
                        ORDER BY m.created_at DESC
                        LIMIT 50";
        
        $messagesStmt = $conn->prepare($messagesSql);
        $messagesStmt->execute([$userId]);
        $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'] ?? ($user['first_name'] . ' ' . $user['last_name']),
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'profile_path' => $user['profile_path'],
                'created_at' => $user['created_at'],
                'message_count' => (int)$user['message_count'],
                'comment_count' => (int)$user['comment_count']
            ],
            'messages' => array_map(function($msg) {
                return [
                    'id' => $msg['id'],
                    'content' => $msg['content'],
                    'type' => $msg['type'],
                    'media_url' => $msg['media_url'],
                    'created_at' => $msg['created_at'],
                    'comment_count' => (int)$msg['comment_count']
                ];
            }, $messages)
        ];
        
        echo json_encode($response);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
