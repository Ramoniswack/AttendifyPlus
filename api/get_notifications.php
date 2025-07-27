<?php
session_start();
require_once('../config/db_config.php');
require_once('../helpers/notification_helpers.php');

if (!isset($_SESSION['LoginID']) || !isset($_SESSION['Role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = intval($_SESSION['LoginID']);
$role = strtolower($_SESSION['Role']); // 'student', 'teacher', 'admin'

// Debug information
error_log("Notification API called - UserID: $userId, Role: $role");

// Get enhanced notifications
$notifications = getEnhancedNotifications($conn, $userId, $role, 10);

// Debug information
error_log("Notifications found: " . count($notifications));

header('Content-Type: application/json');
echo json_encode($notifications);
