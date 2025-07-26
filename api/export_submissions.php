<?php
require_once '../config/db_config.php';

// Check if user is logged in and is a teacher
session_start();
if (!isset($_SESSION['TeacherID'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$teacherID = $_SESSION['TeacherID'];
$assignmentID = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if (!$assignmentID) {
    http_response_code(400);
    echo 'Assignment ID is required';
    exit;
}

try {
    // Verify assignment belongs to teacher and get assignment info
    $verifyQuery = "SELECT Title, SubjectCode, DueDate FROM assignments a 
                   JOIN subjects s ON a.SubjectID = s.SubjectID 
                   WHERE a.AssignmentID = ? AND a.TeacherID = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param('ii', $assignmentID, $teacherID);
    $verifyStmt->execute();
    $assignmentResult = $verifyStmt->get_result();

    if ($assignmentResult->num_rows === 0) {
        http_response_code(404);
        echo 'Assignment not found';
        exit;
    }

    $assignmentInfo = $assignmentResult->fetch_assoc();

    // Get student submission data
    $studentsQuery = "
        SELECT 
            CONCAT(dept.DepartmentCode, sem.SemesterNumber, LPAD(st.StudentID, 3, '0')) as StudentNumber,
            st.FullName,
            login.Email,
            sub.SubmittedAt,
            sub.Grade,
            sub.IsLate,
            sub.Feedback,
            sub.OriginalFileName,
            CASE 
                WHEN sub.SubmissionID IS NOT NULL THEN 'Submitted'
                WHEN av.ViewedAt IS NOT NULL THEN 'Viewed'
                ELSE 'Pending'
            END as Status,
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

    // Generate CSV content
    $filename = sanitizeFilename($assignmentInfo['SubjectCode'] . '_' . $assignmentInfo['Title'] . '_submissions_' . date('Y-m-d'));

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write header information
    fputcsv($output, ['Assignment Report']);
    fputcsv($output, ['Assignment:', $assignmentInfo['Title']]);
    fputcsv($output, ['Subject:', $assignmentInfo['SubjectCode']]);
    fputcsv($output, ['Due Date:', date('M j, Y g:i A', strtotime($assignmentInfo['DueDate']))]);
    fputcsv($output, ['Generated:', date('M j, Y g:i A')]);
    fputcsv($output, []); // Empty row

    // Write CSV headers
    fputcsv($output, [
        'Student Number',
        'Full Name',
        'Email',
        'Status',
        'Submitted At',
        'Grade',
        'Late',
        'File Name',
        'Viewed At',
        'View Count',
        'Feedback'
    ]);

    // Write student data
    while ($row = $studentsResult->fetch_assoc()) {
        fputcsv($output, [
            $row['StudentNumber'],
            $row['FullName'],
            $row['Email'],
            $row['Status'],
            $row['SubmittedAt'] ? date('M j, Y g:i A', strtotime($row['SubmittedAt'])) : '',
            $row['Grade'] ?: '',
            $row['IsLate'] ? 'Yes' : 'No',
            $row['OriginalFileName'] ?: '',
            $row['ViewedAt'] ? date('M j, Y g:i A', strtotime($row['ViewedAt'])) : '',
            $row['ViewCount'] ?: '0',
            $row['Feedback'] ?: ''
        ]);
    }

    fclose($output);
} catch (Exception $e) {
    http_response_code(500);
    echo 'Export error: ' . $e->getMessage();
}

function sanitizeFilename($filename)
{
    // Remove or replace invalid characters for filenames
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    return substr($filename, 0, 200); // Limit length
}

$conn->close();
