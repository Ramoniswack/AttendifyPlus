<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once(__DIR__ . '/../config/db_config.php');

header('Content-Type: application/json');

// Check if user is logged in as student
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$token = $_POST['token'] ?? '';
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'No attendance token provided']);
    exit();
}

$loginID = $_SESSION['LoginID'];

try {
    // Get student info
    $studentStmt = $conn->prepare("SELECT StudentID, FullName, DeviceRegistered FROM students WHERE LoginID = ?");
    $studentStmt->bind_param("i", $loginID);
    $studentStmt->execute();
    $studentRes = $studentStmt->get_result();
    $student = $studentRes->fetch_assoc();
    $studentStmt->close();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // Check if device is registered
    if (!$student['DeviceRegistered']) {
        echo json_encode(['success' => false, 'message' => 'Device not registered. Please register your device first.']);
        exit();
    }

    // Find active QR session with this token
    $qrSessionStmt = $conn->prepare("
        SELECT qs.SessionID, qs.TeacherID, qs.SubjectID, qs.Date, s.SubjectName, s.SubjectCode, t.FullName as TeacherName
        FROM qr_attendance_sessions qs
        JOIN subjects s ON qs.SubjectID = s.SubjectID
        JOIN teachers t ON qs.TeacherID = t.TeacherID
        WHERE qs.QRToken = ? AND qs.IsActive = 1 AND qs.ExpiresAt > NOW()
    ");
    $qrSessionStmt->bind_param("s", $token);
    $qrSessionStmt->execute();
    $qrSessionRes = $qrSessionStmt->get_result();
    $qrSession = $qrSessionRes->fetch_assoc();
    $qrSessionStmt->close();

    if (!$qrSession) {
        echo json_encode(['success' => false, 'message' => 'QR code is invalid or has expired']);
        exit();
    }

    // Check if student is enrolled in this subject
    $enrollmentStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM students s
        JOIN subjects sub ON s.SemesterID = sub.SemesterID AND s.DepartmentID = sub.DepartmentID
        WHERE s.StudentID = ? AND sub.SubjectID = ?
    ");
    $enrollmentStmt->bind_param("ii", $student['StudentID'], $qrSession['SubjectID']);
    $enrollmentStmt->execute();
    $enrollmentRes = $enrollmentStmt->get_result();
    $isEnrolled = $enrollmentRes->fetch_assoc()['count'] > 0;
    $enrollmentStmt->close();

    if (!$isEnrolled) {
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this subject']);
        exit();
    }

    // Check if attendance already marked for today
    $existingStmt = $conn->prepare("
        SELECT Status, Method FROM attendance_records
        WHERE StudentID = ? AND SubjectID = ? AND TeacherID = ? AND DATE(DateTime) = ?
    ");
    $existingStmt->bind_param("iiis", $student['StudentID'], $qrSession['SubjectID'], $qrSession['TeacherID'], $qrSession['Date']);
    $existingStmt->execute();
    $existingRes = $existingStmt->get_result();
    $existing = $existingRes->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        if ($existing['Method'] === 'qr') {
            echo json_encode([
                'success' => false,
                'message' => 'You have already marked attendance via QR code for this subject today',
                'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
                'status' => $existing['Status']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Your attendance has already been marked manually for this subject today',
                'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
                'status' => $existing['Status']
            ]);
        }
        exit();
    }

    // Mark attendance as present via QR
    $attendanceStmt = $conn->prepare("
        INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status, Method)
        VALUES (?, ?, ?, NOW(), 'present', 'qr')
    ");
    $attendanceStmt->bind_param("iii", $student['StudentID'], $qrSession['TeacherID'], $qrSession['SubjectID']);

    if ($attendanceStmt->execute()) {
        $attendanceStmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked successfully!',
            'subject' => $qrSession['SubjectCode'] . ' - ' . $qrSession['SubjectName'],
            'teacher' => $qrSession['TeacherName'],
            'time' => date('Y-m-d H:i:s'),
            'status' => 'present',
            'method' => 'qr'
        ]);
    } else {
        $attendanceStmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to mark attendance. Please try again.']);
    }
} catch (Exception $e) {
    error_log("QR Attendance Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
