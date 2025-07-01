<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\generate_token.php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check if user is teacher
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher ID
$teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
$teacher = $teacherResult->fetch_assoc();

if (!$teacher) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Teacher not found']);
    exit();
}

$teacherID = $teacher['TeacherID'];
$studentID = $_POST['student_id'] ?? null;

if (!$studentID) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Student ID is required']);
    exit();
}

try {
    // Verify student exists
    $studentStmt = $conn->prepare("SELECT StudentID, FullName, DeviceRegistered FROM students WHERE StudentID = ?");
    $studentStmt->bind_param("i", $studentID);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $student = $studentResult->fetch_assoc();

    if (!$student) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }

    if ($student['DeviceRegistered']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Student device already registered']);
        exit();
    }

    // Check for existing pending token
    $existingTokenStmt = $conn->prepare("
        SELECT TokenID FROM device_registration_tokens 
        WHERE StudentID = ? AND Used = FALSE AND ExpiresAt > NOW()
    ");
    $existingTokenStmt->bind_param("i", $studentID);
    $existingTokenStmt->execute();
    $existingResult = $existingTokenStmt->get_result();

    if ($existingResult->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Active registration token already exists for this student']);
        exit();
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Insert new token
    $insertStmt = $conn->prepare("
        INSERT INTO device_registration_tokens (StudentID, Token, ExpiresAt) 
        VALUES (?, ?, ?)
    ");
    $insertStmt->bind_param("iss", $studentID, $token, $expiresAt);
    
    if ($insertStmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Device registration token generated successfully',
            'student_name' => $student['FullName'],
            'expires_at' => $expiresAt,
            'token' => $token
        ]);
    } else {
        throw new Exception('Failed to generate token');
    }

} catch (Exception $e) {
    error_log("Token generation error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to generate registration token']);
}
?>