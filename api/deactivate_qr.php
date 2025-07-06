<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

header('Content-Type: application/json');

// Check if user is logged in as teacher
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher ID
$teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();
$teacherStmt->close();

if (!$teacherRow) {
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit();
}

$teacherID = $teacherRow['TeacherID'];

try {
    // Deactivate all active QR sessions for this teacher
    $deactivateStmt = $conn->prepare("UPDATE qr_attendance_sessions SET IsActive = 0 WHERE TeacherID = ? AND IsActive = 1");
    $deactivateStmt->bind_param("i", $teacherID);
    $result = $deactivateStmt->execute();
    $affectedRows = $deactivateStmt->affected_rows;
    $deactivateStmt->close();

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "QR session deactivated successfully. Affected sessions: $affectedRows"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate QR session']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
