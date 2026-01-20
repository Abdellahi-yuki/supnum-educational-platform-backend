<?php
include_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Check if it's a members fetch or a list fetch
    $list_id = $_GET['list_id'] ?? $_GET['id'] ?? null;
    $is_members_request = isset($_GET['members']) || ($_GET['action'] ?? '') === 'members';

    if ($is_members_request && $list_id) {
        try {
            $stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.first_name, u.last_name FROM users u JOIN mailing_list_members mm ON u.id = mm.user_id WHERE mm.list_id = ?");
            $stmt->execute([$list_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        // List all mailing lists
        try {
            $stmt = $conn->query("SELECT m.*, (SELECT COUNT(*) FROM mailing_list_members mm WHERE mm.list_id = m.id) as member_count FROM mailing_lists m ORDER BY name ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $action = $data->action ?? 'create_list';

    if ($action === 'create_list') {
        if (!isset($data->name) || !isset($data->alias)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Name and Alias required"]);
            exit;
        }
        try {
            $alias = $data->alias;
            if (strpos($alias, '@') === false) $alias .= '@supnum.mr';
            
            $stmt = $conn->prepare("INSERT INTO mailing_lists (name, alias) VALUES (?, ?)");
            $stmt->execute([$data->name, $alias]);
            echo json_encode(["status" => "success", "message" => "Mailing list created", "id" => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } elseif ($action === 'add_member') {
        if (!isset($data->list_id) || !isset($data->email)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "List ID and Email required"]);
            exit;
        }
        try {
            // Resolve email to user_id
            $stmtUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmtUser->execute([$data->email]);
            $user = $stmtUser->fetch();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "User with email {$data->email} not found"]);
                exit;
            }

            $stmt = $conn->prepare("INSERT IGNORE INTO mailing_list_members (list_id, user_id) VALUES (?, ?)");
            $stmt->execute([$data->list_id, $user['id']]);
            echo json_encode(["status" => "success", "message" => "Member added"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } elseif ($action === 'remove_member') {
        if (!isset($data->list_id) || !isset($data->email)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "List ID and Email required"]);
            exit;
        }
        try {
            $stmtUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmtUser->execute([$data->email]);
            $user = $stmtUser->fetch();
            
            if ($user) {
                $stmt = $conn->prepare("DELETE FROM mailing_list_members WHERE list_id = ? AND user_id = ?");
                $stmt->execute([$data->list_id, $user['id']]);
            }
            echo json_encode(["status" => "success", "message" => "Member removed"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
} elseif ($method === 'DELETE') {
    // Delete the list itself
    $list_id = $_GET['list_id'] ?? $_GET['id'] ?? null;

    if ($list_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM mailing_lists WHERE id = ?");
            $stmt->execute([$list_id]);
            echo json_encode(["status" => "success", "message" => "Mailing list deleted"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "List ID required"]);
    }
}
?>
