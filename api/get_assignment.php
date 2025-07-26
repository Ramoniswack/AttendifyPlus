<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\api\get_assignment.php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include '../config/db_config.php';

// Get teacher ID
$loginID = $_SESSION['LoginID'];
$teacherStmt = $conn->prepare("SELECT TeacherID FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit();
}

$teacherID = $teacherRow['TeacherID'];

try {
    $assignmentID = intval($_GET['id'] ?? 0);

    if ($assignmentID <= 0) {
        throw new Exception('Invalid assignment ID');
    }

    // Get assignment data
    $assignmentQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        WHERE a.AssignmentID = ? AND a.TeacherID = ?
    ");
    $assignmentQuery->bind_param("ii", $assignmentID, $teacherID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();

    if ($assignmentResult->num_rows === 0) {
        throw new Exception('Assignment not found or you do not have permission to access it');
    }

    $assignment = $assignmentResult->fetch_assoc();

    // Format due date for datetime-local input
    if ($assignment['DueDate']) {
        $assignment['DueDateFormatted'] = date('Y-m-d\TH:i', strtotime($assignment['DueDate']));
    } else {
        $assignment['DueDateFormatted'] = '';
    }

    // Add file info if exists
    if ($assignment['AttachmentPath'] && file_exists($assignment['AttachmentPath'])) {
        $assignment['HasFile'] = true;
        $assignment['FileExists'] = true;
    } else {
        $assignment['HasFile'] = false;
        $assignment['FileExists'] = false;
    }

    echo json_encode([
        'success' => true,
        'assignment' => $assignment
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
