<?php
include_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Get all users with their activity status
        $sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.profile_pic as profile_path, u.created_at,
                (SELECT COUNT(*) FROM community_messages WHERE user_id = u.id) as message_count,
                (SELECT MAX(created_at) FROM community_messages WHERE user_id = u.id) as last_activity,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM community_messages 
                        WHERE user_id = u.id 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ) THEN 1 
                    ELSE 0 
                END as is_active
                FROM users u
                ORDER BY is_active DESC, last_activity DESC";
        
        $stmt = $conn->query($sql);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = array_map(function($member) {
            return [
                'id' => $member['id'],
                'username' => $member['username'] ?? ($member['first_name'] . ' ' . $member['last_name']),
                'full_name' => trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')),
                'email' => $member['email'],
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name'],
                'profile_path' => $member['profile_path'],
                'created_at' => $member['created_at'],
                'message_count' => (int)$member['message_count'],
                'last_activity' => $member['last_activity'],
                'is_active' => (bool)$member['is_active'],
                'is_mailing_list' => false
            ];
        }, $members);

        // Include mailing lists only if requested (for Mail component)
        if (isset($_GET['include_lists']) && $_GET['include_lists'] === 'true') {
            $stmtLists = $conn->query("SELECT id, name, alias FROM mailing_lists");
            $lists = $stmtLists->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lists as $list) {
                $result[] = [
                    'id' => 'ml_' . $list['id'],
                    'username' => $list['name'] . ' (LISTE)',
                    'email' => $list['alias'],
                    'is_mailing_list' => true
                ];
            }
        }
        
        echo json_encode($result);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
