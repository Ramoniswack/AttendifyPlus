<?php
// Helper functions for Attendify+

/**
 * Sanitize input data
 * 
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize($data)
{
    return htmlspecialchars(trim($data));
}

/**
 * Create a notification in the database
 * 
 * @param mysqli $conn Database connection
 * @param int|null $userId User ID (null for role-based notifications)
 * @param string|null $role Role (null for user-specific notifications)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $icon Icon name (default: 'bell')
 * @param string $type Notification type (default: 'info')
 * @return bool Success status
 */
function createNotification($conn, $userId = null, $role = null, $title, $message, $icon = 'bell', $type = 'info')
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, role, title, message, icon, type, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isssss", $userId, $role, $title, $message, $icon, $type);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark a notification as read for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $notificationId Notification ID
 * @return bool Success status
 */
function markNotificationAsRead($conn, $userId, $notificationId)
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO notification_reads (notification_id, user_id, read_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE read_at = NOW()
        ");
        $stmt->bind_param("ii", $notificationId, $userId);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $role User role
 * @return int Unread count
 */
function getUnreadNotificationCount($conn, $userId, $role)
{
    try {
        $sql = "
            SELECT COUNT(*) as count
            FROM notifications n
            LEFT JOIN notification_reads r
                ON n.id = r.notification_id AND r.user_id = ?
            WHERE
                (n.user_id = ?)  -- User-specific notifications
                OR (n.role = ?)  -- Role-based notifications
                OR (n.user_id IS NULL AND n.role IS NULL)  -- Global notifications
            AND r.id IS NULL  -- Not read
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $userId, $userId, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        error_log("Error getting unread notification count: " . $e->getMessage());
        return 0;
    }
}
