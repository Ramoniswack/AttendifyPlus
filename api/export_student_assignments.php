<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$studentID = $_SESSION['UserID'];
$subjectID = $_GET['subject'] ?? '';
$status = $_GET['status'] ?? '';

try {
    // Get student info
    $studentQuery = $conn->prepare("
        SELECT s.FullName, s.ProgramCode, d.DepartmentName, sem.SemesterNumber, s.DepartmentID, s.SemesterID
        FROM students s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        WHERE s.StudentID = ?
    ");

    $studentQuery->bind_param("i", $studentID);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $studentRow = $studentResult->fetch_assoc();

    if (!$studentRow) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit();
    }

    // Build query conditions
    $whereConditions = ["a.Status IN ('active', 'graded')"];
    $params = [];
    $paramTypes = "";

    // Add department and semester filter
    $whereConditions[] = "s.DepartmentID = ? AND s.SemesterID = ?";
    $params[] = $studentRow['DepartmentID'];
    $params[] = $studentRow['SemesterID'];
    $paramTypes .= "ii";

    if ($subjectID) {
        $whereConditions[] = "a.SubjectID = ?";
        $params[] = $subjectID;
        $paramTypes .= "i";
    }

    if ($status) {
        if ($status === 'submitted') {
            $whereConditions[] = "asub.SubmissionID IS NOT NULL";
        } elseif ($status === 'not_submitted') {
            $whereConditions[] = "asub.SubmissionID IS NULL";
        } elseif ($status === 'graded') {
            $whereConditions[] = "asub.Status = 'graded'";
        }
    }

    $whereClause = implode(" AND ", $whereConditions);

    // Get assignments
    $assignmentsQuery = $conn->prepare("
        SELECT 
            a.AssignmentID,
            a.Title,
            a.Description,
            a.DueDate,
            a.MaxPoints,
            a.Status as AssignmentStatus,
            s.SubjectCode,
            s.SubjectName,
            t.FullName as TeacherName,
            asub.SubmissionID,
            asub.SubmittedAt,
            asub.Status as SubmissionStatus,
            asub.Grade,
            asub.Feedback,
            asub.IsLate
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        JOIN teachers t ON a.TeacherID = t.TeacherID
        LEFT JOIN assignment_submissions asub ON a.AssignmentID = asub.AssignmentID AND asub.StudentID = ?
        WHERE $whereClause
        ORDER BY a.DueDate DESC
    ");

    $assignmentsQuery->bind_param("i" . $paramTypes, $studentID, ...$params);
    $assignmentsQuery->execute();
    $assignmentsResult = $assignmentsQuery->get_result();
    $assignments = [];
    while ($row = $assignmentsResult->fetch_assoc()) {
        $assignments[] = $row;
    }

    // Calculate statistics
    $totalAssignments = count($assignments);
    $submittedCount = count(array_filter($assignments, fn($a) => $a['SubmissionID'] !== null));
    $gradedCount = count(array_filter($assignments, fn($a) => $a['SubmissionStatus'] === 'graded'));
    $lateCount = count(array_filter($assignments, fn($a) => $a['IsLate'] == 1));

    $completionRate = $totalAssignments > 0 ? round(($submittedCount / $totalAssignments) * 100) : 0;

    // Get subject filter info
    $subjectFilter = '';
    if ($subjectID) {
        $subjectQuery = $conn->prepare("SELECT SubjectCode, SubjectName FROM subjects WHERE SubjectID = ?");
        $subjectQuery->bind_param("i", $subjectID);
        $subjectQuery->execute();
        $subjectResult = $subjectQuery->get_result();
        $subjectInfo = $subjectResult->fetch_assoc();
        $subjectFilter = $subjectInfo ? " - {$subjectInfo['SubjectCode']} ({$subjectInfo['SubjectName']})" : '';
    }

    // Set headers for CSV download
    $filename = "Assignments_Report_{$studentRow['FullName']}{$subjectFilter}_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create CSV output
    $output = fopen('php://output', 'w');

    // Header information
    fputcsv($output, ['STUDENT ASSIGNMENTS REPORT']);
    fputcsv($output, []);
    fputcsv($output, ['Student Information']);
    fputcsv($output, ['Name', $studentRow['FullName']]);
    fputcsv($output, ['Program', $studentRow['ProgramCode']]);
    fputcsv($output, ['Department', $studentRow['DepartmentName']]);
    fputcsv($output, ['Semester', $studentRow['SemesterNumber']]);
    fputcsv($output, []);

    fputcsv($output, ['Report Filters']);
    fputcsv($output, ['Subject Filter', $subjectFilter ?: 'All Subjects']);
    fputcsv($output, ['Status Filter', $status ?: 'All Status']);
    fputcsv($output, []);

    fputcsv($output, ['Assignments Summary']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Assignments', $totalAssignments]);
    fputcsv($output, ['Submitted', $submittedCount]);
    fputcsv($output, ['Graded', $gradedCount]);
    fputcsv($output, ['Late Submissions', $lateCount]);
    fputcsv($output, ['Completion Rate', $completionRate . '%']);
    fputcsv($output, []);

    // Assignments Records
    fputcsv($output, ['Assignments Records']);
    fputcsv($output, ['Title', 'Subject', 'Teacher', 'Due Date', 'Max Points', 'Status', 'Submitted Date', 'Grade', 'Feedback', 'Late']);
    foreach ($assignments as $assignment) {
        fputcsv($output, [
            $assignment['Title'],
            $assignment['SubjectCode'] . ' - ' . $assignment['SubjectName'],
            $assignment['TeacherName'],
            $assignment['DueDate'] ? date('M j, Y', strtotime($assignment['DueDate'])) : 'No due date',
            $assignment['MaxPoints'],
            $assignment['SubmissionID'] ? ($assignment['SubmissionStatus'] === 'graded' ? 'Graded' : 'Submitted') : 'Not Submitted',
            $assignment['SubmittedAt'] ? date('M j, Y', strtotime($assignment['SubmittedAt'])) : 'N/A',
            $assignment['Grade'] !== null ? $assignment['Grade'] : 'N/A',
            $assignment['Feedback'] ?: 'N/A',
            $assignment['IsLate'] ? 'Yes' : 'No'
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Report Generated', date('Y-m-d H:i:s')]);

    fclose($output);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
