<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

// Check if user is logged in and is a teacher
session_start();
if (!isset($_SESSION['TeacherID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$teacherID = $_SESSION['TeacherID'];
$assignmentID = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if (!$assignmentID) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

try {
    // First verify that the assignment belongs to this teacher
    $verifyQuery = "SELECT Title, SubjectCode FROM assignments a 
                   JOIN subjects s ON a.SubjectID = s.SubjectID 
                   WHERE a.AssignmentID = ? AND a.TeacherID = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param('ii', $assignmentID, $teacherID);
    $verifyStmt->execute();
    $assignmentResult = $verifyStmt->get_result();

    if ($assignmentResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Assignment not found']);
        exit;
    }

    $assignmentInfo = $assignmentResult->fetch_assoc();

    // Get all students who should have access to this assignment (same department/semester as subject)
    $studentsQuery = "
        SELECT DISTINCT 
            st.StudentID,
            st.FullName as Name,
            CONCAT(dept.DepartmentCode, sem.SemesterNumber, LPAD(st.StudentID, 3, '0')) as StudentNumber,
            login.Email,
            sub.SubmissionID,
            sub.SubmittedAt,
            sub.Status as SubmissionStatus,
            sub.Grade,
            sub.IsLate,
            sub.Feedback,
            CASE 
                WHEN sub.SubmissionID IS NOT NULL THEN 'submitted'
                WHEN av.ViewedAt IS NOT NULL THEN 'viewed'
                ELSE 'pending'
            END as Status,
            sub.OriginalFileName,
            sub.FileSize,
            av.ViewedAt,
            av.ViewCount
        FROM assignments a
        JOIN subjects subj ON a.SubjectID = subj.SubjectID
        JOIN students st ON st.DepartmentID = subj.DepartmentID AND st.SemesterID = subj.SemesterID
        JOIN login_tbl login ON st.LoginID = login.LoginID
        JOIN departments dept ON st.DepartmentID = dept.DepartmentID
        JOIN semesters sem ON st.SemesterID = sem.SemesterID
        LEFT JOIN assignment_submissions sub ON sub.AssignmentID = a.AssignmentID AND sub.StudentID = st.StudentID
        LEFT JOIN assignment_views av ON av.AssignmentID = a.AssignmentID AND av.StudentID = st.StudentID
        WHERE a.AssignmentID = ? AND a.TeacherID = ?
        ORDER BY st.FullName
    ";

    $studentsStmt = $conn->prepare($studentsQuery);
    $studentsStmt->bind_param('ii', $assignmentID, $teacherID);
    $studentsStmt->execute();
    $studentsResult = $studentsStmt->get_result();

    $students = [];
    while ($row = $studentsResult->fetch_assoc()) {
        $students[] = [
            'StudentID' => $row['StudentID'],
            'StudentNumber' => $row['StudentNumber'],
            'Name' => $row['Name'],
            'Email' => $row['Email'],
            'Status' => $row['Status'],
            'SubmittedAt' => $row['SubmittedAt'],
            'Grade' => $row['Grade'],
            'IsLate' => $row['IsLate'],
            'Feedback' => $row['Feedback'],
            'FileName' => $row['OriginalFileName'],
            'FileSize' => $row['FileSize'],
            'ViewedAt' => $row['ViewedAt'],
            'ViewCount' => $row['ViewCount'] ?: 0
        ];
    }

    // Get summary statistics
    $totalStudents = count($students);
    $submitted = count(array_filter($students, function ($s) {
        return $s['Status'] === 'submitted';
    }));
    $viewed = count(array_filter($students, function ($s) {
        return $s['Status'] === 'viewed';
    }));
    $pending = count(array_filter($students, function ($s) {
        return $s['Status'] === 'pending';
    }));

    echo json_encode([
        'success' => true,
        'assignment' => $assignmentInfo,
        'students' => $students,
        'summary' => [
            'total' => $totalStudents,
            'submitted' => $submitted,
            'viewed' => $viewed,
            'pending' => $pending
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
