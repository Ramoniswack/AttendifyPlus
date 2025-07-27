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
$subjectID = $_GET['subject_id'] ?? null;

if (!$subjectID) {
    http_response_code(400);
    echo json_encode(['error' => 'Subject ID is required']);
    exit();
}

try {
    // Get student and subject info
    $infoQuery = $conn->prepare("
        SELECT 
            s.FullName as StudentName,
            s.ProgramCode,
            d.DepartmentName,
            sem.SemesterNumber,
            sub.SubjectCode,
            sub.SubjectName,
            t.FullName as TeacherName
        FROM students s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        JOIN subjects sub ON sub.SubjectID = ?
        JOIN teacher_subject_map tsm ON sub.SubjectID = tsm.SubjectID
        JOIN teachers t ON tsm.TeacherID = t.TeacherID
        WHERE s.StudentID = ?
    ");

    $infoQuery->bind_param("ii", $subjectID, $studentID);
    $infoQuery->execute();
    $infoResult = $infoQuery->get_result();
    $info = $infoResult->fetch_assoc();

    if (!$info) {
        http_response_code(404);
        echo json_encode(['error' => 'Subject not found']);
        exit();
    }

    // Get attendance data
    $attendanceQuery = $conn->prepare("
        SELECT 
            DATE(ar.DateTime) as Date,
            ar.Status,
            ar.Method,
            TIME(ar.DateTime) as Time
        FROM attendance_records ar
        WHERE ar.StudentID = ? AND ar.SubjectID = ?
        ORDER BY ar.DateTime DESC
    ");

    $attendanceQuery->bind_param("ii", $studentID, $subjectID);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    $attendanceData = [];
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceData[] = $row;
    }

    // Get assignment data
    $assignmentQuery = $conn->prepare("
        SELECT 
            a.Title,
            a.DueDate,
            a.MaxPoints,
            asub.SubmittedAt,
            asub.Status,
            asub.Grade,
            asub.IsLate
        FROM assignments a
        LEFT JOIN assignment_submissions asub ON a.AssignmentID = asub.AssignmentID AND asub.StudentID = ?
        WHERE a.SubjectID = ? AND a.Status IN ('active', 'graded')
        ORDER BY a.DueDate DESC
    ");

    $assignmentQuery->bind_param("ii", $studentID, $subjectID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignmentData = [];
    while ($row = $assignmentResult->fetch_assoc()) {
        $assignmentData[] = $row;
    }

    // Calculate statistics
    $totalSessions = count($attendanceData);
    $presentCount = count(array_filter($attendanceData, fn($a) => $a['Status'] === 'present'));
    $absentCount = count(array_filter($attendanceData, fn($a) => $a['Status'] === 'absent'));
    $lateCount = count(array_filter($attendanceData, fn($a) => $a['Status'] === 'late'));
    $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100) : 0;

    $totalAssignments = count($assignmentData);
    $submittedCount = count(array_filter($assignmentData, fn($a) => $a['SubmittedAt'] !== null));
    $gradedCount = count(array_filter($assignmentData, fn($a) => $a['Status'] === 'graded'));
    $assignmentCompletion = $totalAssignments > 0 ? round(($submittedCount / $totalAssignments) * 100) : 0;

    $grades = array_filter(array_column($assignmentData, 'Grade'));
    $averageGrade = !empty($grades) ? round(array_sum($grades) / count($grades), 1) : 0;

    // Set headers for CSV download
    $filename = "Subject_Report_{$info['SubjectCode']}_{$info['StudentName']}_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create CSV output
    $output = fopen('php://output', 'w');

    // Header information
    fputcsv($output, ['STUDENT SUBJECT PERFORMANCE REPORT']);
    fputcsv($output, []);
    fputcsv($output, ['Student Information']);
    fputcsv($output, ['Name', $info['StudentName']]);
    fputcsv($output, ['Program', $info['ProgramCode']]);
    fputcsv($output, ['Department', $info['DepartmentName']]);
    fputcsv($output, ['Semester', $info['SemesterNumber']]);
    fputcsv($output, []);

    fputcsv($output, ['Subject Information']);
    fputcsv($output, ['Subject Code', $info['SubjectCode']]);
    fputcsv($output, ['Subject Name', $info['SubjectName']]);
    fputcsv($output, ['Teacher', $info['TeacherName']]);
    fputcsv($output, []);

    fputcsv($output, ['Performance Summary']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Attendance Rate', $attendanceRate . '%']);
    fputcsv($output, ['Total Sessions', $totalSessions]);
    fputcsv($output, ['Present', $presentCount]);
    fputcsv($output, ['Absent', $absentCount]);
    fputcsv($output, ['Late', $lateCount]);
    fputcsv($output, ['Assignment Completion', $assignmentCompletion . '%']);
    fputcsv($output, ['Total Assignments', $totalAssignments]);
    fputcsv($output, ['Submitted', $submittedCount]);
    fputcsv($output, ['Graded', $gradedCount]);
    fputcsv($output, ['Average Grade', $averageGrade > 0 ? $averageGrade : 'N/A']);
    fputcsv($output, []);

    // Attendance History
    fputcsv($output, ['Attendance History']);
    fputcsv($output, ['Date', 'Status', 'Method', 'Time']);
    foreach ($attendanceData as $record) {
        fputcsv($output, [
            $record['Date'],
            ucfirst($record['Status']),
            ucfirst($record['Method']),
            $record['Time']
        ]);
    }
    fputcsv($output, []);

    // Assignment History
    fputcsv($output, ['Assignment History']);
    fputcsv($output, ['Assignment', 'Due Date', 'Submitted Date', 'Status', 'Grade', 'Late']);
    foreach ($assignmentData as $record) {
        fputcsv($output, [
            $record['Title'],
            $record['DueDate'] ? date('Y-m-d', strtotime($record['DueDate'])) : 'N/A',
            $record['SubmittedAt'] ? date('Y-m-d', strtotime($record['SubmittedAt'])) : 'Not Submitted',
            ucfirst($record['Status'] ?? 'Not Submitted'),
            $record['Grade'] ? $record['Grade'] . '/' . $record['MaxPoints'] : 'N/A',
            $record['IsLate'] ? 'Yes' : 'No'
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Report Generated', date('Y-m-d H:i:s')]);

    fclose($output);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
