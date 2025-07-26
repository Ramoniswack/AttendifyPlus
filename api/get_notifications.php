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

// Get enhanced notifications
$notifications = getEnhancedNotifications($conn, $userId, $role, 10);

header('Content-Type: application/json');
echo json_encode($notifications);
