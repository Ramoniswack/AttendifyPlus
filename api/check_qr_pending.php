<?php

/**
 * Check if a student has QR scans pending approval
 * Returns true if there are QR scans pending for a student
 */

header('Content-Type: application/json');
session_start();
require_once('../config/db_config.php');

// Check if user is logged in as student
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$studentID = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$attendanceID = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;
$checkExpiry = isset($_POST['check_expiry']) ? (bool)$_POST['check_expiry'] : false;

if (!$studentID) {
    // Try to get the student ID from the session
    $loginID = $_SESSION['LoginID'];
    $stmt = $conn->prepare("SELECT StudentID FROM students WHERE LoginID = ?");
    $stmt->bind_param("i", $loginID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $studentID = $row['StudentID'];
    }
}

if (!$studentID) {
    echo json_encode(['success' => false, 'message' => 'Student ID not found']);
    exit();
}

try {
    // If checking for QR expiry
    if ($checkExpiry) {
        // Check if any active QR sessions have expired
        $expiryQuery = "SELECT COUNT(*) AS expired_count FROM qr_attendance_sessions 
                       WHERE IsActive = 1 AND ExpiresAt <= NOW()";
        $expiryStmt = $conn->prepare($expiryQuery);
        $expiryStmt->execute();
        $expiryResult = $expiryStmt->get_result();
        $expiryRow = $expiryResult->fetch_assoc();
        $expiryStmt->close();

        $qrExpired = ($expiryRow['expired_count'] > 0);

        echo json_encode([
            'success' => true,
            'qr_expired' => $qrExpired,
            'expired_count' => $expiryRow['expired_count']
        ]);
        exit();
    }

    // Check if there are any pending QR scans for this student
    $query = "SELECT COUNT(*) AS count FROM qr_attendance 
              WHERE StudentID = ? AND Status = 'pending'";

    if ($attendanceID) {
        $query .= " AND AttendanceID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $studentID, $attendanceID);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $studentID);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $hasPendingScans = ($row['count'] > 0);

    echo json_encode([
        'success' => true,
        'has_pending_scans' => $hasPendingScans,
        'pending_count' => $row['count']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
