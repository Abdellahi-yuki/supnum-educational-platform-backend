<?php
include_once 'db.php';

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $totalMembers = $stmt->fetchColumn();

    $activeStmt = $conn->query("SELECT COUNT(DISTINCT user_id) FROM community_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $activeMembers = $activeStmt->fetchColumn();

    echo json_encode([
        'totalMembers' => (int)$totalMembers,
        'activeMembers' => (int)$activeMembers
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
