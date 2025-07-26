<?php
session_start();
require_once('../config/db_config.php');
require_once('../helpers/helpers.php');

if (!isset($_SESSION['LoginID']) || !isset($_SESSION['Role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$notificationId = intval($_POST['notification_id'] ?? 0);
$userId = intval($_SESSION['LoginID']);

if ($notificationId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit;
}

try {
    // Mark notification as read
    $result = markNotificationAsRead($conn, $userId, $notificationId);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
    }
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
