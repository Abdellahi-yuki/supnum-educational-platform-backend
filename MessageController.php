<?php
// backend_php/MessageController.php

require_once 'db.php';

class MessageController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function index() {
        $userId = $_GET['user_id'] ?? null;
        $onlyArchived = $_GET['only_archived'] ?? false;
        $search = $_GET['search'] ?? null;

        // 1. Fetch Messages
        // We join with archived_messages to see if THIS user has archived it
        $sql = "SELECT DISTINCT m.*, u.username, u.email,
                (CASE WHEN am.id IS NOT NULL THEN 1 ELSE 0 END) as is_archived 
                FROM messages m 
                JOIN users u ON m.user_id = u.id 
                LEFT JOIN comments c ON m.id = c.message_id 
                LEFT JOIN archived_messages am ON m.id = am.message_id AND am.user_id = ?
                WHERE 1=1";
        
        $params = [$userId]; // For the LEFT JOIN condition on am.user_id

        if ($onlyArchived === 'true' && $userId) {
            $sql .= " AND am.id IS NOT NULL";
        }
        
        if ($search) {
            $term = '%' . $search . '%';
            $sql .= " AND (m.content LIKE ? OR u.username LIKE ? OR c.content LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY m.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $messagesRaw = $stmt->fetchAll();

        // If no messages, return empty array
        if (empty($messagesRaw)) {
            echo json_encode([]);
            return;
        }

        // 2. Fetch Comments for these messages
        // Optimization: Get all message IDs to filter comments
        $messageIds = array_column($messagesRaw, 'id');
        $inQuery = implode(',', array_fill(0, count($messageIds), '?'));
        
        $commentSql = "SELECT c.*, u.username, u.email 
                       FROM comments c 
                       JOIN users u ON c.user_id = u.id 
                       WHERE c.message_id IN ($inQuery)
                       ORDER BY c.created_at ASC";
        
        $commentStmt = $this->pdo->prepare($commentSql);
        $commentStmt->execute($messageIds);
        $commentsRaw = $commentStmt->fetchAll();

        // Group comments by message_id
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
                'updated_at' => $c['updated_at'] ?? null,
                'username' => $c['username'] ?? 'Unknown',
                'email' => $c['email'] ?? null,
            ];
        }

        // 3. Transform structure
        $transformed = array_map(function($msg) use ($commentsByMsg) {
            return [
                'id' => $msg['id'],
                'user_id' => $msg['user_id'],
                'content' => $msg['content'],
                'type' => $msg['type'],
                'media_url' => $msg['media_url'],
                'created_at' => $msg['created_at'],
                'updated_at' => $msg['updated_at'] ?? null,
                'username' => $msg['username'] ?? 'Unknown',
                'email' => $msg['email'] ?? null,
                'comments' => $commentsByMsg[$msg['id']] ?? [],
                'is_archived' => (bool)$msg['is_archived']
            ];
        }, $messagesRaw);

        echo json_encode($transformed);
    }

    public function store() {
        // PHP multipart/form-data handling
        $userId = $_POST['user_id'] ?? null;
        $content = $_POST['content'] ?? '';
        $file = $_FILES['file'] ?? null;

        // Check for pre-uploaded media
        $preUploadedUrl = $_POST['media_url'] ?? null;
        $preUploadedType = $_POST['media_type'] ?? null;

        if ((!$content || trim($content) === '') && !$file && !$preUploadedUrl) {
             http_response_code(400);
             echo json_encode(['error' => 'Message must have content or a file']);
             return;
        }

        $type = 'text';
        $mediaUrl = null;

        if ($preUploadedUrl) {
            $mediaUrl = $preUploadedUrl;
            $type = $preUploadedType ?? 'file';
        } elseif ($file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $originalName = basename($file['name']);
                // Sanitize filename
                $filename = time() . '-' . preg_replace('/\s+/', '_', $originalName);
                $targetPath = $uploadDir . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $mediaUrl = '/uploads/' . $filename;
                    
                    // Determine type
                    $mime = mime_content_type($targetPath);
                    if (strpos($mime, 'image/') === 0) {
                        $type = 'image';
                    } elseif (strpos($mime, 'video/') === 0) {
                        $type = 'video';
                    } else {
                        $type = 'file';
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'File upload failed (move_uploaded_file)']);
                    return;
                }
            } else {
                // Report specific upload error
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
                ];
                $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
                
                http_response_code(400);
                echo json_encode(['error' => "File upload error: $errorMsg"]);
                return;
            }
        }

        $createdAt = date('Y-m-d H:i:s');
        $updatedAt = $createdAt;

        try {
            $stmt = $this->pdo->prepare("INSERT INTO messages (user_id, content, type, media_url, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $content, $type, $mediaUrl, $createdAt]);
            $msgId = $this->pdo->lastInsertId();

            // Fetch user for response
            $userStmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            echo json_encode([
                'id' => $msgId,
                'user_id' => $userId,
                'content' => $content,
                'type' => $type,
                'media_url' => $mediaUrl,
                'created_at' => $createdAt,
                'username' => $user['username'] ?? 'Unknown',
                'comments' => []
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete() {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $messageId = $input['message_id'] ?? null;
        $userId = $input['user_id'] ?? null;

        if (!$messageId || !$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'message_id and user_id are required']);
            return;
        }

        try {
            // First, check if the message exists and get its owner
            $stmt = $this->pdo->prepare("SELECT user_id, media_url FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch();

            if (!$message) {
                http_response_code(404);
                echo json_encode(['error' => 'Message not found']);
                return;
            }

            // Permission check: user can delete their own post OR admin can delete any post
            // Get user's role
            $userStmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userRole = $userStmt->fetchColumn(); 

            $isAdmin = ($userRole === 'admin');
            $isOwner = ($message['user_id'] == $userId);

            if (!$isOwner && !$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to delete this post']);
                return;
            }

            // Delete associated comments first (foreign key constraint)
            $deleteCommentsStmt = $this->pdo->prepare("DELETE FROM comments WHERE message_id = ?");
            $deleteCommentsStmt->execute([$messageId]);

            // Delete from archived_messages
            $deleteArchivedStmt = $this->pdo->prepare("DELETE FROM archived_messages WHERE message_id = ?");
            $deleteArchivedStmt->execute([$messageId]);

            // Delete from notifications
            $deleteNotifsStmt = $this->pdo->prepare("DELETE FROM notifications WHERE message_id = ?");
            $deleteNotifsStmt->execute([$messageId]);

            // Delete the message
            $deleteStmt = $this->pdo->prepare("DELETE FROM messages WHERE id = ?");
            $deleteStmt->execute([$messageId]);

            // Delete the media file if it exists
            if ($message['media_url']) {
                $filePath = __DIR__ . $message['media_url'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
