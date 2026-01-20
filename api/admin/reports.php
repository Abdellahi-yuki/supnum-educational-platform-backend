<?php
include_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List reports with details
    try {
        $sql = "SELECT r.*, 
                u.username as reporter_name, 
                m.content as message_content,
                m.user_id as message_author_id,
                m.created_at as message_date
                FROM community_reports r
                JOIN users u ON r.reporter_id = u.id
                JOIN community_messages m ON r.message_id = m.id
                ORDER BY r.created_at DESC";
                
        $stmt = $conn->query($sql);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reports);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    // Dismiss/Resolve Report OR Submit new report
    $data = json_decode(file_get_contents("php://input"));
    
    if (isset($data->action)) {
        // Admin Action
        if ($data->action === 'delete_message') {
            // Delete the reported message
            try {
                $stmt = $conn->prepare("DELETE FROM community_messages WHERE id = ?");
                $stmt->execute([$data->message_id]);
                // Also mark report as resolved
                $upd = $conn->prepare("UPDATE community_reports SET status = 'resolved' WHERE message_id = ?");
                $upd->execute([$data->message_id]);
                
                echo json_encode(["status" => "success", "message" => "Message deleted and report resolved"]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } elseif ($data->action === 'dismiss') {
            try {
                $stmt = $conn->prepare("UPDATE community_reports SET status = 'dismissed' WHERE id = ?");
                $stmt->execute([$data->report_id]);
                echo json_encode(["status" => "success", "message" => "Report dismissed"]);
            } catch (PDOException $e) {
                 http_response_code(500);
                 echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
    } else {
        // Submit New Report (User Action)
        if (isset($data->message_id) && isset($data->reporter_id) && isset($data->reason)) {
            try {
                $stmt = $conn->prepare("INSERT INTO community_reports (message_id, reporter_id, reason) VALUES (?, ?, ?)");
                $stmt->execute([$data->message_id, $data->reporter_id, $data->reason]);
                echo json_encode(["status" => "success", "message" => "Report submitted"]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
    }
}
?>
