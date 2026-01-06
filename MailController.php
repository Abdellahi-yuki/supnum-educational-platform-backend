<?php
require_once __DIR__ . '/db.php';

class MailController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // Helper to get JSON input
    private function getJsonInput() {
        return json_decode(file_get_contents('php://input'), true);
    }

    // Helper to send JSON response
    private function sendJson($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function getMessages() {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 3;
        $label = isset($_GET['label']) ? $_GET['label'] : 'inbox';

        try {
            $sql = "SELECT 
                        m.id, 
                        m.subject, 
                        m.body as content, 
                        m.created_at as date, 
                        m.sender_id,
                        m.parent_id as parentId,
                        u.username as `from`,
                        u.email,
                        l.is_read as isRead,
                        l.is_starred as isStarred,
                        l.is_spam,
                        l.is_trash,
                        l.is_archived
                    FROM mail_messages m
                    JOIN mail_labels l ON m.id = l.message_id
                    LEFT JOIN users u ON m.sender_id = u.id
                    WHERE l.user_id = :user_id";

            if ($label === 'inbox') {
                $sql .= " AND l.is_trash = 0 AND l.is_archived = 0 AND l.is_spam = 0";
            } elseif ($label === 'starred') {
                $sql .= " AND l.is_starred = 1 AND l.is_trash = 0";
            } elseif ($label === 'sent') {
                // Use l.user_id instead of :user_id to avoid duplicate parameter issue
                $sql .= " AND m.sender_id = l.user_id AND l.is_trash = 0";
            } elseif ($label === 'trash') {
                $sql .= " AND l.is_trash = 1";
            } elseif ($label === 'spam') {
                $sql .= " AND l.is_spam = 1";
            }

            $sql .= " ORDER BY m.created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $messages = $stmt->fetchAll();

            // Prepare recipient query once outside the loop
            $stmtRecip = $this->pdo->prepare("
                SELECT r.status, u.username, u.email, u.id as user_id
                FROM mail_recipients r
                JOIN users u ON r.user_id = u.id
                WHERE r.message_id = ?
            ");

            foreach ($messages as &$msg) {
                $msg['labels'] = [];
                if ($label === 'inbox') $msg['labels'][] = 'inbox';
                if ($msg['isStarred']) $msg['labels'][] = 'starred';
                if ($label === 'sent') $msg['labels'][] = 'sent';
                if ($msg['is_spam']) $msg['labels'][] = 'spam';
                if ($msg['is_trash']) $msg['labels'][] = 'trash';

                // Fetch recipients for this message
                $stmtRecip->execute([$msg['id']]);
                $allRecipients = $stmtRecip->fetchAll();

                $msg['recipients'] = [];
                foreach ($allRecipients as $r) {
                    // Privacy: Hide BCC unless user is sender or the BCC recipient
                    if ($r['status'] === 'bcc') {
                        if ($msg['sender_id'] != $userId && $r['user_id'] != $userId) {
                            continue;
                        }
                    }
                    $msg['recipients'][] = [
                        'name' => $r['username'],
                        'email' => $r['email'],
                        'type' => $r['status']
                    ];
                }
            }

            $this->sendJson($messages);

        } catch (Exception $e) {
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage() {
        $data = $this->getJsonInput();
        $senderId = isset($data['sender_id']) ? intval($data['sender_id']) : 3;
        
        if (!isset($data['to']) || !isset($data['subject']) || !isset($data['body'])) {
            $this->sendJson(['error' => 'Missing required fields'], 400);
        }

        try {
            // 1. Validate all recipients exist before doing anything
            $allEmails = [];
            if (isset($data['to'])) {
                $toEmails = is_array($data['to']) ? $data['to'] : array_filter(explode(',', $data['to']));
                foreach ($toEmails as $e) $allEmails[] = trim($e);
            }
            if (isset($data['cc'])) {
                $ccEmails = is_array($data['cc']) ? $data['cc'] : array_filter(explode(',', $data['cc']));
                foreach ($ccEmails as $e) $allEmails[] = trim($e);
            }
            if (isset($data['bcc'])) {
                $bccEmails = is_array($data['bcc']) ? $data['bcc'] : array_filter(explode(',', $data['bcc']));
                foreach ($bccEmails as $e) $allEmails[] = trim($e);
            }

            foreach ($allEmails as $email) {
                if (empty($email)) continue;
                $stmtCheck = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if (!$stmtCheck->fetch()) {
                    $this->sendJson(['error' => "$email does not exist"], 400);
                }
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO mail_messages (sender_id, subject, body, created_at, parent_id) VALUES (?, ?, ?, NOW(), ?)");
            $parentId = isset($data['parentId']) ? $data['parentId'] : 0;
            $stmt->execute([$senderId, $data['subject'], $data['body'], $parentId]);
            $messageId = $this->pdo->lastInsertId();

            // Helper to process recipients by type
            $processRecipients = function($emails, $type) use ($messageId) {
                if (is_string($emails)) {
                    $emails = array_filter(explode(',', $emails));
                }
                if (!is_array($emails)) return;

                foreach ($emails as $email) {
                    $email = trim($email);
                    if (empty($email)) continue;

                    $stmtUser = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmtUser->execute([$email]);
                    $user = $stmtUser->fetch();

                    if ($user) {
                        $recipientId = $user['id'];
                        
                        // Add to mail_recipients with type (to, cc, bcc)
                        $stmtRecip = $this->pdo->prepare("INSERT INTO mail_recipients (user_id, message_id, status) VALUES (?, ?, ?)");
                        $stmtRecip->execute([$recipientId, $messageId, $type]);

                        // Add to mail_labels for Recipient (Inbox)
                        $stmtLabel = $this->pdo->prepare("INSERT INTO mail_labels (user_id, message_id, is_read, is_starred, is_spam, is_trash, is_archived) VALUES (?, ?, 0, 0, 0, 0, 0)");
                        $stmtLabel->execute([$recipientId, $messageId]);
                    }
                }
            };

            $processRecipients($data['to'], 'to');
            if (isset($data['cc'])) $processRecipients($data['cc'], 'cc');
            if (isset($data['bcc'])) $processRecipients($data['bcc'], 'bcc');

            // Add to mail_labels for Sender (Sent, Read)
            $stmtSenderLabel = $this->pdo->prepare("INSERT INTO mail_labels (user_id, message_id, is_read, is_starred, is_spam, is_trash, is_archived) VALUES (?, ?, 1, 0, 0, 0, 0)");
            $stmtSenderLabel->execute([$senderId, $messageId]);

            $this->pdo->commit();

            $this->sendJson([
                'id' => $messageId,
                'subject' => $data['subject'],
                'content' => $data['body'],
                'date' => date('Y-m-d H:i:s'),
                'from' => 'Me', 
                'email' => 'me@supnum.mr',
                'isRead' => true,
                'parentId' => $parentId,
                'labels' => ['sent']
            ]);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }
    
    public function updateMessage($id) {
        $data = $this->getJsonInput();
        $userId = isset($data['user_id']) ? intval($data['user_id']) : 3;

        // Updates: isRead, isStarred, etc.
        $allowedFields = ['is_read', 'is_starred', 'is_spam', 'is_trash', 'is_archived'];
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value ? 1 : 0;
            }
        }

        if (empty($updates)) {
            $this->sendJson(['message' => 'No updates provided']);
        }

        $params[] = $userId;
        $params[] = $id;

        try {
            $sql = "UPDATE mail_labels SET " . implode(', ', $updates) . " WHERE user_id = ? AND message_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->sendJson(['success' => true]);
        } catch (Exception $e) {
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }
}
