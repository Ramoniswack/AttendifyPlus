<?php
session_start();
require_once(__DIR__ . '/../../config/db_config.php');
require_once(__DIR__ . '/../../helpers/notification_helpers.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'])) {
    $materialID = intval($_POST['id']);
    $action = $_POST['action'] === 'download' ? 'download' : 'view';
    $studentID = $_SESSION['UserID'] ?? null;
    $teacherID = null;

    if ($materialID) {
        $stmt = $conn->prepare("SELECT TeacherID FROM materials WHERE MaterialID = ?");
        $stmt->bind_param("i", $materialID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $teacherID = $row['TeacherID'];
        }
    }

    if ($materialID && $studentID) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Log the access
        $stmt = $conn->prepare("INSERT INTO material_access_logs (MaterialID, StudentID, TeacherID, ActionType, IPAddress) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $materialID, $studentID, $teacherID, $action, $ip);
        $stmt->execute();

        // Create notification for teacher if this is a download (to avoid spam for views)
        if ($action === 'download' && $teacherID) {
            notifyMaterialAccess($conn, $studentID, $materialID, $teacherID, $action);
        }

        echo json_encode(['success' => true]);
        exit;
    }
}
echo json_encode(['success' => false, 'error' => 'Invalid request']);
