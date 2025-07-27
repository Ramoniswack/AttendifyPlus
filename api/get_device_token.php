<?php

/**
 * Get Device Registration Token API
 * Returns the current valid token for a student to register their device
 */

require_once(__DIR__ . '/../config/db_config.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    // Get student email from POST data
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        exit();
    }

    // Find student and their token
    $tokenStmt = $conn->prepare("
        SELECT 
            s.StudentID,
            s.FullName,
            s.DeviceRegistered,
            t.Token,
            t.ExpiresAt,
            t.CreatedAt
        FROM students s
        JOIN login_tbl l ON s.LoginID = l.LoginID
        LEFT JOIN device_registration_tokens t ON s.StudentID = t.StudentID 
            AND t.Used = FALSE 
            AND t.ExpiresAt > NOW()
        WHERE l.Email = ? AND l.Role = 'student'
        ORDER BY t.CreatedAt DESC
        LIMIT 1
    ");
    $tokenStmt->bind_param("s", $email);
    $tokenStmt->execute();
    $result = $tokenStmt->get_result();
    $studentData = $result->fetch_assoc();

    if (!$studentData) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }

    if ($studentData['DeviceRegistered']) {
        echo json_encode([
            'success' => false,
            'error' => 'Device already registered',
            'student_name' => $studentData['FullName']
        ]);
        exit();
    }

    if (!$studentData['Token']) {
        echo json_encode([
            'success' => false,
            'error' => 'No valid registration token found. Please contact your administrator.',
            'student_name' => $studentData['FullName']
        ]);
        exit();
    }

    // Calculate time remaining
    $expiresAt = new DateTime($studentData['ExpiresAt']);
    $now = new DateTime();
    $timeRemaining = $expiresAt->diff($now);
    $minutesRemaining = ($timeRemaining->h * 60) + $timeRemaining->i;

    echo json_encode([
        'success' => true,
        'token' => $studentData['Token'],
        'student_name' => $studentData['FullName'],
        'expires_at' => $studentData['ExpiresAt'],
        'minutes_remaining' => $minutesRemaining,
        'message' => "Token valid for {$minutesRemaining} more minutes"
    ]);
} catch (Exception $e) {
    error_log("Get device token error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to get token']);
}
