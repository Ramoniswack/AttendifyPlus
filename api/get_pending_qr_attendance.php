<?php

/**
 * Get pending QR attendance scans for teacher interface
 * This API fetches QR scans that are waiting for teacher approval
 */

header('Content-Type: application/json');
session_start();
require_once('../config/db_config.php');

// Check if user is logged in as teacher
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
        // Get pending scans for specific session
        $stmt = $conn->prepare("
            SELECT 
                p.PendingID,
                p.StudentID,
                p.CreatedAt,
                s.FullName as student_name,
                sub.SubjectCode,
                sub.SubjectName
            FROM qr_attendance_pending p
            JOIN qr_attendance_sessions qs ON p.SessionID = qs.SessionID
            JOIN students s ON p.StudentID = s.StudentID
            JOIN subjects sub ON qs.SubjectID = sub.SubjectID
            WHERE p.SessionID = ? AND qs.TeacherID = ?
            ORDER BY p.CreatedAt DESC
        ");
        $stmt->bind_param("ii", $sessionID, $teacherID);
    } else if ($subjectID > 0 && !empty($date)) {
        // Get pending scans for subject and date
        $stmt = $conn->prepare("
            SELECT 
                p.PendingID,
                p.StudentID,
                p.CreatedAt,
                s.FullName as student_name,
                sub.SubjectCode,
                sub.SubjectName,
                qs.SessionID
            FROM qr_attendance_pending p
            JOIN qr_attendance_sessions qs ON p.SessionID = qs.SessionID
            JOIN students s ON p.StudentID = s.StudentID
            JOIN subjects sub ON qs.SubjectID = sub.SubjectID
            WHERE qs.SubjectID = ? AND qs.Date = ? AND qs.TeacherID = ?
            ORDER BY p.CreatedAt DESC
        ");
        $stmt->bind_param("isi", $subjectID, $date, $teacherID);
    } else {
        // Get all pending scans for this teacher
        $stmt = $conn->prepare("
            SELECT 
                p.PendingID,
                p.StudentID,
                p.CreatedAt,
                s.FullName as student_name,
                sub.SubjectCode,
                sub.SubjectName,
                qs.SessionID,
                qs.Date
            FROM qr_attendance_pending p
            JOIN qr_attendance_sessions qs ON p.SessionID = qs.SessionID
            JOIN students s ON p.StudentID = s.StudentID
            JOIN subjects sub ON qs.SubjectID = sub.SubjectID
            WHERE qs.TeacherID = ?
            ORDER BY p.CreatedAt DESC
        ");
        $stmt->bind_param("i", $teacherID);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $pendingScans = [];
    while ($row = $result->fetch_assoc()) {
        $pendingScans[] = [
            'pending_id' => $row['PendingID'],
            'student_id' => $row['StudentID'],
            'student_name' => $row['student_name'],
            'scanned_at' => $row['CreatedAt'],
            'subject_code' => $row['SubjectCode'],
            'subject_name' => $row['SubjectName'],
            'session_id' => $row['SessionID'] ?? null,
            'date' => $row['Date'] ?? null
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'pending_attendance' => $pendingScans,
        'count' => count($pendingScans)
    ]);
} catch (Exception $e) {
    error_log("Error fetching pending QR attendance: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
