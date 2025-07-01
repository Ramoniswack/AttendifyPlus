<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\student_devices.php
session_start();
require_once(__DIR__ . '/../../config/db_config.php');

header('Content-Type: application/json');

// Check if user is student
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$loginID = $_SESSION['LoginID'];

try {
    // Get student information
    $studentStmt = $conn->prepare("SELECT StudentID, FullName, DeviceRegistered FROM students WHERE LoginID = ?");
    $studentStmt->bind_param("i", $loginID);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $student = $studentResult->fetch_assoc();

    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }

    $studentID = $student['StudentID'];

    // Check if device already registered
    if ($student['DeviceRegistered']) {
        echo json_encode(['success' => false, 'error' => 'Device already registered']);
        exit();
    }

    // Check for valid pending token
    $tokenStmt = $conn->prepare("
        SELECT TokenID, Token 
        FROM device_registration_tokens 
        WHERE StudentID = ? AND Used = FALSE AND ExpiresAt > NOW()
        ORDER BY CreatedAt DESC 
        LIMIT 1
    ");
    $tokenStmt->bind_param("i", $studentID);
    $tokenStmt->execute();
    $tokenResult = $tokenStmt->get_result();
    $token = $tokenResult->fetch_assoc();

    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'No valid registration token found. Please contact your admin.']);
        exit();
    }

    // Get device information from POST
    $fingerprint = $_POST['fingerprint'] ?? '';
    $userAgent = $_POST['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (empty($fingerprint)) {
        echo json_encode(['success' => false, 'error' => 'Device fingerprint is required']);
        exit();
    }

    // Check if device fingerprint already exists for this student
    $existingDeviceStmt = $conn->prepare("
        SELECT DeviceID 
        FROM student_devices 
        WHERE StudentID = ? AND DeviceFingerprint = ? AND IsActive = TRUE
    ");
    $existingDeviceStmt->bind_param("is", $studentID, $fingerprint);
    $existingDeviceStmt->execute();
    $existingDevice = $existingDeviceStmt->get_result()->fetch_assoc();

    if ($existingDevice) {
        echo json_encode(['success' => false, 'error' => 'This device is already registered']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Generate device name
        $deviceName = 'Device-' . substr($fingerprint, 0, 8);
        $deviceInfo = json_encode([
            'user_agent' => $userAgent,
            'registered_at' => date('Y-m-d H:i:s')
        ]);

        // Insert device registration
        $insertDeviceStmt = $conn->prepare("
            INSERT INTO student_devices (StudentID, DeviceFingerprint, DeviceName, DeviceInfo, RegisteredAt) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insertDeviceStmt->bind_param("isss", $studentID, $fingerprint, $deviceName, $deviceInfo);

        if (!$insertDeviceStmt->execute()) {
            throw new Exception('Failed to register device');
        }

        // Mark token as used
        $updateTokenStmt = $conn->prepare("
            UPDATE device_registration_tokens 
            SET Used = TRUE 
            WHERE TokenID = ?
        ");
        $updateTokenStmt->bind_param("i", $token['TokenID']);

        if (!$updateTokenStmt->execute()) {
            throw new Exception('Failed to update token status');
        }

        // Update student record
        $updateStudentStmt = $conn->prepare("
            UPDATE students 
            SET DeviceRegistered = TRUE 
            WHERE StudentID = ?
        ");
        $updateStudentStmt->bind_param("i", $studentID);

        if (!$updateStudentStmt->execute()) {
            throw new Exception('Failed to update student record');
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully! You can now use QR code attendance.',
            'student_name' => $student['FullName']
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Device registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to register device. Please try again.']);
}
