<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\api\publish_assignment.php
ob_start(); // Start output buffering to prevent stray output
session_start();
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Suppress errors in output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Log errors to file

// Check authentication
if (!isset($_SESSION['UserID']) || !isset($_SESSION['LoginID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    ob_end_flush();
    exit();
}

try {
    include '../config/db_config.php';

    // Verify database connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception('Database connection failed');
    }

    // Get teacher ID
    $loginID = (int)$_SESSION['LoginID'];
    $teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
    if (!$teacherStmt) {
        error_log("Teacher query preparation failed: " . $conn->error);
        throw new Exception('Database error: Failed to prepare teacher query');
    }
    $teacherStmt->bind_param("i", $loginID);
    $teacherStmt->execute();
    $teacherRes = $teacherStmt->get_result();
    $teacherRow = $teacherRes->fetch_assoc();
    $teacherStmt->close();

    if (!$teacherRow) {
        error_log("Teacher not found for LoginID: $loginID");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
        ob_end_flush();
        exit();
    }

    $teacherID = (int)$teacherRow['TeacherID'];

    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON input: " . json_last_error_msg());
        throw new Exception('Invalid JSON input');
    }

    $assignmentID = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;
    if ($assignmentID <= 0) {
        error_log("Invalid assignment ID: $assignmentID");
        throw new Exception('Invalid assignment ID');
    }

    // Verify teacher owns this assignment and it's a draft
    $assignmentCheck = $conn->prepare("
        SELECT AssignmentID, Status, Title 
        FROM assignments 
        WHERE AssignmentID = ? AND TeacherID = ? AND Status = 'draft' AND IsActive = 1
    ");
    if (!$assignmentCheck) {
        error_log("Assignment check query preparation failed: " . $conn->error);
        throw new Exception('Database error: Failed to prepare assignment query');
    }
    $assignmentCheck->bind_param("ii", $assignmentID, $teacherID);
    $assignmentCheck->execute();
    $assignmentResult = $assignmentCheck->get_result();
    $assignment = $assignmentResult->fetch_assoc();
    $assignmentCheck->close();

    if (!$assignment) {
        error_log("Assignment not found or not authorized. AssignmentID: $assignmentID, TeacherID: $teacherID");
        throw new Exception('Draft assignment not found or you do not have permission to publish it');
    }

    // Update status to active
    $publishStmt = $conn->prepare("
        UPDATE assignments 
        SET Status = 'active', UpdatedAt = CURRENT_TIMESTAMP 
        WHERE AssignmentID = ? AND TeacherID = ?
    ");
    if (!$publishStmt) {
        error_log("Publish query preparation failed: " . $conn->error);
        throw new Exception('Database error: Failed to prepare publish query');
    }
    $publishStmt->bind_param("ii", $assignmentID, $teacherID);
    $success = $publishStmt->execute();
    $affectedRows = $publishStmt->affected_rows;
    $publishStmt->close();

    if (!$success || $affectedRows === 0) {
        error_log("Failed to publish assignment ID: $assignmentID. Error: " . $conn->error);
        throw new Exception('Failed to publish assignment');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Assignment "' . htmlspecialchars($assignment['Title']) . '" published successfully!',
        'assignment_id' => $assignmentID
    ]);
} catch (Exception $e) {
    error_log("Error in publish_assignment.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ob_end_flush(); // Flush output buffer
}
