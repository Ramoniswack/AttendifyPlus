<?php
session_start();
require_once('../config/db_config.php');
require_once('../helpers/notification_helpers.php');

if (!isset($_SESSION['LoginID']) || !isset($_SESSION['Role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = intval($_SESSION['LoginID']);
$role = strtolower($_SESSION['Role']);

try {
    // Get all unread notification IDs for this user
    $sql = "SELECT n.id FROM notifications n
            LEFT JOIN notification_reads r ON n.id = r.notification_id AND r.user_id = ?
            WHERE n.user_id = ? AND r.id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    $stmt->close();

    if (count($ids) > 0) {
        $insert = $conn->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at) VALUES (?, ?, NOW())");
        foreach ($ids as $nid) {
            $insert->bind_param('ii', $nid, $userId);
            $insert->execute();
        }
        $insert->close();
    }
    echo json_encode(['success' => true, 'marked' => count($ids)]);
} catch (Exception $e) {
    error_log('Error in mark_all_notifications_read: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
