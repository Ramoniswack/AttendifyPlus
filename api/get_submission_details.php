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

    // Get submission details
    $submissionQuery = $conn->prepare("
        SELECT 
            asub.SubmissionID,
            asub.SubmissionText,
            asub.SubmittedAt,
            asub.IsLate,
            asub.Status,
            asub.Grade,
            asub.MaxGrade,
            asub.Feedback,
            a.Title as AssignmentTitle,
            a.MaxPoints,
            s.SubjectCode,
            s.SubjectName,
            t.FullName as TeacherName
        FROM assignment_submissions asub
        JOIN assignments a ON asub.AssignmentID = a.AssignmentID
        JOIN subjects s ON a.SubjectID = s.SubjectID
        JOIN teachers t ON a.TeacherID = t.TeacherID
        WHERE asub.AssignmentID = ? AND asub.StudentID = ?
    ");

    $submissionQuery->bind_param("ii", $assignmentID, $studentID);
    $submissionQuery->execute();
    $submissionResult = $submissionQuery->get_result();
    $submission = $submissionResult->fetch_assoc();

    if (!$submission) {
        throw new Exception('Submission not found');
    }

    // Get submission files
    $filesQuery = $conn->prepare("
        SELECT FileID, FileName, FilePath, FileSize, FileType, UploadedAt
        FROM assignment_submission_files
        WHERE SubmissionID = ?
        ORDER BY UploadedAt
    ");

    $filesQuery->bind_param("i", $submission['SubmissionID']);
    $filesQuery->execute();
    $filesResult = $filesQuery->get_result();
    $files = [];
    while ($file = $filesResult->fetch_assoc()) {
        $files[] = $file;
    }

    $submission['Files'] = $files;

    echo json_encode([
        'success' => true,
        'data' => $submission
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
