<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$studentID = $_SESSION['UserID'];

try {
    if (!isset($_GET['assignment_id']) || empty($_GET['assignment_id'])) {
        throw new Exception('Assignment ID is required');
    }

    $assignmentID = intval($_GET['assignment_id']);

    // Get assignment details
    $assignmentQuery = $conn->prepare("
        SELECT 
            a.AssignmentID,
            a.Title,
            a.Description,
            a.Instructions,
            a.DueDate,
            a.MaxPoints,
            a.Status,
            a.SubmissionType,
            a.AttachmentFileName,
            a.AttachmentPath,
            s.SubjectCode,
            s.SubjectName,
            t.FullName as TeacherName,
            t.TeacherID
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        JOIN teachers t ON a.TeacherID = t.TeacherID
        WHERE a.AssignmentID = ? AND a.Status IN ('active', 'graded')
    ");

    $assignmentQuery->bind_param("i", $assignmentID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignment = $assignmentResult->fetch_assoc();

    if (!$assignment) {
        throw new Exception('Assignment not found');
    }

    // Check if student has access to this assignment (same department and semester)
    $studentQuery = $conn->prepare("
        SELECT s.DepartmentID, s.SemesterID
        FROM students s
        WHERE s.StudentID = ?
    ");
    $studentQuery->bind_param("i", $studentID);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $student = $studentResult->fetch_assoc();

    $subjectQuery = $conn->prepare("
        SELECT DepartmentID, SemesterID
        FROM subjects
        WHERE SubjectID = (SELECT SubjectID FROM assignments WHERE AssignmentID = ?)
    ");
    $subjectQuery->bind_param("i", $assignmentID);
    $subjectQuery->execute();
    $subjectResult = $subjectQuery->get_result();
    $subject = $subjectResult->fetch_assoc();

    if ($student['DepartmentID'] != $subject['DepartmentID'] || $student['SemesterID'] != $subject['SemesterID']) {
        throw new Exception('Access denied to this assignment');
    }

    echo json_encode([
        'success' => true,
        'data' => $assignment
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
