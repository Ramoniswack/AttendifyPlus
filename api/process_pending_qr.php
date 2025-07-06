<?php

/**
 * Process pending QR scans when teacher saves attendance
 * This marks pending QR scans as processed and updates their status
 */

header('Content-Type: application/json');
session_start();
require_once('../config/db_config.php');

// Check if user is logged in as teacher
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get teacher ID
$loginID = $_SESSION['LoginID'];
$teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacher = $teacherRes->fetch_assoc();
$teacherStmt->close();

if (!$teacher) {
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit();
}

$teacherID = $teacher['TeacherID'];

// Get parameters
$sessionID = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
$subjectID = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
$date = isset($_POST['date']) ? $_POST['date'] : '';

try {
    if ($sessionID > 0) {
        // Mark pending scans as processed for specific session
        $stmt = $conn->prepare("
            UPDATE qr_attendance_pending p
            JOIN qr_attendance_sessions qs ON p.SessionID = qs.SessionID
            SET p.Status = 'processed', p.ProcessedAt = NOW()
            WHERE p.SessionID = ? AND p.Status = 'pending' AND qs.TeacherID = ?
        ");
        $stmt->bind_param("ii", $sessionID, $teacherID);
    } else if ($subjectID > 0 && !empty($date)) {
        // Mark pending scans as processed for subject and date
        $stmt = $conn->prepare("
            UPDATE qr_attendance_pending p
            JOIN qr_attendance_sessions qs ON p.SessionID = qs.SessionID
            SET p.Status = 'processed', p.ProcessedAt = NOW()
            WHERE qs.SubjectID = ? AND qs.Date = ? AND p.Status = 'pending' AND qs.TeacherID = ?
        ");
        $stmt->bind_param("isi", $subjectID, $date, $teacherID);
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }

    $stmt->execute();
    $processedCount = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'processed_count' => $processedCount,
        'message' => "Processed {$processedCount} QR scan(s)"
    ]);
} catch (Exception $e) {
    error_log("Error processing QR scans: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
