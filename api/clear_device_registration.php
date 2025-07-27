<?php
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

$loginID = $_SESSION['LoginID'];

try {
    // Get student info
    $studentStmt = $conn->prepare("SELECT StudentID, FullName FROM students WHERE LoginID = ?");
    $studentStmt->bind_param("i", $loginID);
    $studentStmt->execute();
    $studentRes = $studentStmt->get_result();
    $student = $studentRes->fetch_assoc();
    $studentStmt->close();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Deactivate all devices for this student
        $deactivateDevicesStmt = $conn->prepare("UPDATE student_devices SET IsActive = FALSE WHERE StudentID = ?");
        $deactivateDevicesStmt->bind_param("i", $student['StudentID']);
        $deactivateDevicesStmt->execute();
        $deactivatedCount = $deactivateDevicesStmt->affected_rows;
        $deactivateDevicesStmt->close();

        // Update student record to mark device as not registered
        $updateStudentStmt = $conn->prepare("UPDATE students SET DeviceRegistered = FALSE WHERE StudentID = ?");
        $updateStudentStmt->bind_param("i", $student['StudentID']);
        $updateStudentStmt->execute();
        $updateStudentStmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Device registration cleared successfully. You can now register your device again.',
            'deactivated_devices' => $deactivatedCount
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Clear device registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to clear device registration: ' . $e->getMessage()]);
}
