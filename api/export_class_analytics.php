<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: ../views/auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher info
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    header("Location: ../views/logout.php");
    exit();
}

$teacherID = $teacherRow['TeacherID'];

// Get parameters
$subjectID = $_GET['subject'] ?? null;

if (!$subjectID) {
    die("Missing required parameters");
}

// Verify teacher has access to this subject
$subjectCheck = $conn->prepare("
    SELECT s.SubjectID, s.SubjectCode, s.SubjectName, d.DepartmentName, sem.SemesterNumber
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE tsm.TeacherID = ? AND s.SubjectID = ?
");
$subjectCheck->bind_param("ii", $teacherID, $subjectID);
$subjectCheck->execute();
$subjectResult = $subjectCheck->get_result();
$subject = $subjectResult->fetch_assoc();

if (!$subject) {
    die("Access denied to this subject");
}

// Get students for the subject
$studentsQuery = $conn->prepare("
    SELECT s.StudentID, s.FullName, s.Contact, d.DepartmentName, sem.SemesterNumber
    FROM students s
    JOIN subjects sub ON s.SemesterID = sub.SemesterID AND s.DepartmentID = sub.DepartmentID
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE sub.SubjectID = ?
    ORDER BY s.FullName
");
$studentsQuery->bind_param("i", $subjectID);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();
$students = [];
while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

// Get attendance statistics for each student
$studentStats = [];
foreach ($students as $student) {
    $statsQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN Status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN Method = 'qr' THEN 1 ELSE 0 END) as qr_count,
            SUM(CASE WHEN Method = 'manual' THEN 1 ELSE 0 END) as manual_count
        FROM attendance_records 
        WHERE StudentID = ? AND SubjectID = ? AND TeacherID = ?
    ");
    $statsQuery->bind_param("iii", $student['StudentID'], $subjectID, $teacherID);
    $statsQuery->execute();
    $statsResult = $statsQuery->get_result();
    $stats = $statsResult->fetch_assoc();

    $studentStats[$student['StudentID']] = $stats;
}

// Get assignment statistics for each student
$assignmentStats = [];
foreach ($students as $student) {
    $assignmentQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_assignments,
            SUM(CASE WHEN s.SubmittedAt IS NOT NULL THEN 1 ELSE 0 END) as submitted_count,
            SUM(CASE WHEN s.Status = 'graded' THEN 1 ELSE 0 END) as graded_count,
            AVG(CASE WHEN s.Grade IS NOT NULL THEN s.Grade ELSE NULL END) as avg_grade,
            SUM(CASE WHEN s.IsLate = 1 THEN 1 ELSE 0 END) as late_submissions
        FROM assignments a
        LEFT JOIN assignment_submissions s ON a.AssignmentID = s.AssignmentID AND s.StudentID = ?
        WHERE a.SubjectID = ? AND a.TeacherID = ?
    ");
    $assignmentQuery->bind_param("iii", $student['StudentID'], $subjectID, $teacherID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignment = $assignmentResult->fetch_assoc();

    $assignmentStats[$student['StudentID']] = $assignment;
}

// Calculate overall statistics
$totalStudents = count($students);
$totalSessions = 0;
$totalPresent = 0;
$totalAbsent = 0;
$totalLate = 0;
$totalQR = 0;
$totalManual = 0;
$totalAssignments = 0;
$totalSubmitted = 0;
$totalGraded = 0;
$totalGrade = 0;
$gradedCount = 0;

foreach ($studentStats as $stats) {
    $totalSessions += $stats['total_sessions'];
    $totalPresent += $stats['present_count'];
    $totalAbsent += $stats['absent_count'];
    $totalLate += $stats['late_count'];
    $totalQR += $stats['qr_count'];
    $totalManual += $stats['manual_count'];
}

foreach ($assignmentStats as $stats) {
    $totalAssignments += $stats['total_assignments'];
    $totalSubmitted += $stats['submitted_count'];
    $totalGraded += $stats['graded_count'];
    if ($stats['avg_grade'] !== null) {
        $totalGrade += $stats['avg_grade'];
        $gradedCount++;
    }
}

$overallAttendancePercentage = $totalSessions > 0 ? round(($totalPresent / $totalSessions) * 100, 2) : 0;
$overallSubmissionRate = $totalAssignments > 0 ? round(($totalSubmitted / $totalAssignments) * 100, 2) : 0;
$overallAverageGrade = $gradedCount > 0 ? round($totalGrade / $gradedCount, 2) : 0;

// Set headers for CSV download
$filename = "Class_Analytics_" . $subject['SubjectCode'] . "_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create CSV file
$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['CLASS ANALYTICS REPORT']);
fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
fputcsv($output, ['Generated by: ' . $teacherRow['FullName']]);
fputcsv($output, []);

// Subject Information
fputcsv($output, ['SUBJECT INFORMATION']);
fputcsv($output, ['Subject Code', $subject['SubjectCode']]);
fputcsv($output, ['Subject Name', $subject['SubjectName']]);
fputcsv($output, ['Department', $subject['DepartmentName']]);
fputcsv($output, ['Semester', $subject['SemesterNumber']]);
fputcsv($output, ['Total Students', $totalStudents]);
fputcsv($output, []);

// Overall Statistics
fputcsv($output, ['OVERALL STATISTICS']);
fputcsv($output, ['Total Attendance Sessions', $totalSessions]);
fputcsv($output, ['Total Present', $totalPresent]);
fputcsv($output, ['Total Absent', $totalAbsent]);
fputcsv($output, ['Total Late', $totalLate]);
fputcsv($output, ['Overall Attendance Percentage', $overallAttendancePercentage . '%']);
fputcsv($output, ['QR Code Attendance', $totalQR]);
fputcsv($output, ['Manual Attendance', $totalManual]);
fputcsv($output, ['Total Assignments', $totalAssignments]);
fputcsv($output, ['Total Submissions', $totalSubmitted]);
fputcsv($output, ['Overall Submission Rate', $overallSubmissionRate . '%']);
fputcsv($output, ['Total Graded', $totalGraded]);
fputcsv($output, ['Overall Average Grade', $overallAverageGrade]);
fputcsv($output, []);

// Individual Student Performance
fputcsv($output, ['INDIVIDUAL STUDENT PERFORMANCE']);
fputcsv($output, ['Student Name', 'Contact', 'Department', 'Semester', 'Total Sessions', 'Present', 'Absent', 'Late', 'Attendance %', 'QR Count', 'Manual Count', 'Total Assignments', 'Submitted', 'Submission Rate %', 'Graded', 'Average Grade', 'Late Submissions']);

foreach ($students as $student) {
    $stats = $studentStats[$student['StudentID']];
    $assignment = $assignmentStats[$student['StudentID']];

    $attendancePercentage = $stats['total_sessions'] > 0 ? round(($stats['present_count'] / $stats['total_sessions']) * 100, 2) : 0;
    $submissionRate = $assignment['total_assignments'] > 0 ? round(($assignment['submitted_count'] / $assignment['total_assignments']) * 100, 2) : 0;
    $avgGrade = $assignment['avg_grade'] !== null ? round($assignment['avg_grade'], 2) : 'N/A';

    fputcsv($output, [
        $student['FullName'],
        $student['Contact'],
        $student['DepartmentName'],
        $student['SemesterNumber'],
        $stats['total_sessions'],
        $stats['present_count'],
        $stats['absent_count'],
        $stats['late_count'],
        $attendancePercentage . '%',
        $stats['qr_count'],
        $stats['manual_count'],
        $assignment['total_assignments'],
        $assignment['submitted_count'],
        $submissionRate . '%',
        $assignment['graded_count'],
        $avgGrade,
        $assignment['late_submissions']
    ]);
}

fputcsv($output, []);

// Performance Analysis
fputcsv($output, ['PERFORMANCE ANALYSIS']);
fputcsv($output, ['Category', 'Count', 'Percentage']);

// Attendance Analysis
$excellentAttendance = 0; // 90%+
$goodAttendance = 0;      // 75-89%
$fairAttendance = 0;      // 60-74%
$poorAttendance = 0;      // <60%

foreach ($studentStats as $stats) {
    if ($stats['total_sessions'] > 0) {
        $percentage = ($stats['present_count'] / $stats['total_sessions']) * 100;
        if ($percentage >= 90) $excellentAttendance++;
        elseif ($percentage >= 75) $goodAttendance++;
        elseif ($percentage >= 60) $fairAttendance++;
        else $poorAttendance++;
    }
}

fputcsv($output, ['Excellent Attendance (90%+)', $excellentAttendance, $totalStudents > 0 ? round(($excellentAttendance / $totalStudents) * 100, 2) . '%' : '0%']);
fputcsv($output, ['Good Attendance (75-89%)', $goodAttendance, $totalStudents > 0 ? round(($goodAttendance / $totalStudents) * 100, 2) . '%' : '0%']);
fputcsv($output, ['Fair Attendance (60-74%)', $fairAttendance, $totalStudents > 0 ? round(($fairAttendance / $totalStudents) * 100, 2) . '%' : '0%']);
fputcsv($output, ['Poor Attendance (<60%)', $poorAttendance, $totalStudents > 0 ? round(($poorAttendance / $totalStudents) * 100, 2) . '%' : '0%']);

fclose($output);
exit();
