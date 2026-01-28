<?php
include_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    $onlyArchived = $_GET['only_archived'] ?? false;
    $search = $_GET['search'] ?? null;

    if (!$userId) {
        echo json_encode([]);
        exit;
    }

    try {
        $sql = "SELECT DISTINCT m.*, u.username, u.email, u.first_name, u.last_name, u.profile_pic as profile_path, u.role,
                (CASE WHEN am.id IS NOT NULL THEN 1 ELSE 0 END) as is_archived,
                r.content as reply_content, ru.username as reply_username
                FROM community_messages m 
                JOIN users u ON m.user_id = u.id 
                LEFT JOIN community_comments c ON m.id = c.message_id 
                LEFT JOIN community_archived_messages am ON m.id = am.message_id AND am.user_id = ?
                LEFT JOIN community_messages r ON m.reply_to_id = r.id
                LEFT JOIN users ru ON r.user_id = ru.id
                WHERE 1=1";
        
        $params = [$userId];

        if ($onlyArchived === 'true') {
            $sql .= " AND am.id IS NOT NULL";
        }
        
        if ($search) {
            $term = '%' . $search . '%';
            $sql .= " AND (m.content LIKE ? OR u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR c.content LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY m.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $messagesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messagesRaw)) {
            echo json_encode([]);
            exit;
        }

        $messageIds = array_column($messagesRaw, 'id');
        $inQuery = implode(',', array_fill(0, count($messageIds), '?'));
        
        $commentSql = "SELECT c.*, u.username, u.email, u.first_name, u.last_name, u.profile_pic as profile_path
                       FROM community_comments c 
                       JOIN users u ON c.user_id = u.id 
                       WHERE c.message_id IN ($inQuery)
                       ORDER BY c.created_at ASC";
        
        $commentStmt = $conn->prepare($commentSql);
        $commentStmt->execute($messageIds);
        $commentsRaw = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

        $commentsByMsg = [];
        foreach ($commentsRaw as $c) {
            $msgId = $c['message_id'];
            if (!isset($commentsByMsg[$msgId])) {
                $commentsByMsg[$msgId] = [];
            }
            $commentsByMsg[$msgId][] = [
                'id' => $c['id'],
                'message_id' => $c['message_id'],
                'user_id' => $c['user_id'],
                'content' => $c['content'],
                'created_at' => $c['created_at'],
                'username' => $c['username'] ?? ($c['first_name'] . ' ' . $c['last_name']),
                'full_name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
                'email' => $c['email'] ?? null,
                'profile_path' => $c['profile_path']
            ];
        }

        $transformed = array_map(function($msg) use ($commentsByMsg) {
            return [
                'id' => $msg['id'],
                'user_id' => $msg['user_id'],
                'content' => $msg['content'],
                'type' => $msg['type'],
                'media_url' => $msg['media_url'],
                'created_at' => $msg['created_at'],
                'username' => $msg['username'] ?? ($msg['first_name'] . ' ' . $msg['last_name']),
                'full_name' => trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')),
                'email' => $msg['email'] ?? null,
                'profile_path' => $msg['profile_path'],
                'role' => $msg['role'],
                'comments' => $commentsByMsg[$msg['id']] ?? [],
                'is_archived' => (bool)$msg['is_archived'],
                'reply_to_id' => $msg['reply_to_id'],
                'reply_content' => $msg['reply_content'],
                'reply_username' => $msg['reply_username']
            ];
        }, $messagesRaw);

        echo json_encode($transformed);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    // Handle message creation
    $userId = $_POST['user_id'] ?? null;
    $content = $_POST['content'] ?? '';
    // Optional: media_url if already uploaded via chunked upload
    $mediaUrl = $_POST['media_url'] ?? null;
    $type = $_POST['media_type'] ?? 'text';
    $replyToId = $_POST['reply_to_id'] ?? null;

    if (!$userId || (!$content && !$mediaUrl)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID and content/media are required']);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO community_messages (user_id, content, type, media_url, reply_to_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $content, $type, $mediaUrl, $replyToId]);
        $msgId = $conn->lastInsertId();

        // Create notification if replying to someone else's message
        if ($replyToId) {
            $replyStmt = $conn->prepare("SELECT user_id FROM community_messages WHERE id = ?");
            $replyStmt->execute([$replyToId]);
            $originalUserId = $replyStmt->fetchColumn();
            
            if ($originalUserId && $originalUserId != $userId) {
                $notifStmt = $conn->prepare("INSERT INTO community_notifications (user_id, actor_id, message_id, type) VALUES (?, ?, ?, 'reply')");
                $notifStmt->execute([$originalUserId, $userId, $msgId]);
            }
        }

        $userStmt = $conn->prepare("SELECT username, first_name, last_name, email, profile_pic as profile_path, role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("Community Message POST Error: User not found for ID $userId after insertion");
            // We use a fallback if user is not found to avoid 500
            $user = [
                'username' => 'Unknown',
                'first_name' => 'Unknown',
                'last_name' => '',
                'email' => '',
                'profile_path' => null,
                'role' => 'Etudiant'
            ];
        }

        $replyInfo = null;
        if ($replyToId) {
            $rStmt = $conn->prepare("SELECT m.content, u.username FROM community_messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
            $rStmt->execute([$replyToId]);
            $replyInfo = $rStmt->fetch(PDO::FETCH_ASSOC);
        }

        $response = [
            'id' => $msgId,
            'user_id' => $userId,
            'content' => $content,
            'type' => $type,
            'media_url' => $mediaUrl,
            'created_at' => date('Y-m-d H:i:s'),
            'username' => $user['username'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'email' => $user['email'] ?? '',
            'profile_path' => $user['profile_path'] ?? null,
            'role' => $user['role'] ?? 'Etudiant',
            'comments' => [],
            'is_archived' => false,
            'reply_to_id' => $replyToId,
            'reply_content' => $replyInfo ? $replyInfo['content'] : null,
            'reply_username' => $replyInfo ? $replyInfo['username'] : null
        ];

        echo json_encode($response);
    } catch (Throwable $t) {
        error_log("Community Message POST Error: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine());
        http_response_code(500);
        echo json_encode(['error' => $t->getMessage()]);
    }
} elseif ($method === 'DELETE') {
    // Handle deletion
    $data = json_decode(file_get_contents("php://input"));
    $messageId = $data->message_id ?? null;
    $userId = $data->user_id ?? null;

    if (!$messageId || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Message ID and User ID are required']);
        exit;
    }

    try {
        // Check permissions
        $stmt = $conn->prepare("SELECT user_id FROM community_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg) {
            http_response_code(404);
            echo json_encode(['error' => 'Message not found']);
            exit;
        }

        $userStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userRole = $userStmt->fetchColumn();

        if ($msg['user_id'] != $userId && $userRole !== 'admin' && $userRole !== 'Root') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $del = $conn->prepare("DELETE FROM community_messages WHERE id = ?");
        $del->execute([$messageId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
