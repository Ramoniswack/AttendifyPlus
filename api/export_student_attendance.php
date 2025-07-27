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
$month = $_GET['month'] ?? date('Y-m');

try {
    // Get student info
    $studentQuery = $conn->prepare("
        SELECT s.FullName, s.ProgramCode, d.DepartmentName, sem.SemesterNumber
        FROM students s
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        WHERE s.StudentID = ?
    ");
    
    $studentQuery->bind_param("i", $studentID);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $studentInfo = $studentResult->fetch_assoc();
    
    if (!$studentInfo) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit();
    }
    
    // Build query conditions
    $whereConditions = ["ar.StudentID = ?"];
    $params = [$studentID];
    $paramTypes = "i";
    
    if ($subjectID) {
        $whereConditions[] = "ar.SubjectID = ?";
        $params[] = $subjectID;
        $paramTypes .= "i";
    }
    
    if ($month) {
        $whereConditions[] = "DATE_FORMAT(ar.DateTime, '%Y-%m') = ?";
        $params[] = $month;
        $paramTypes .= "s";
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    // Get attendance records
    $attendanceQuery = $conn->prepare("
        SELECT 
            ar.DateTime,
            ar.Status,
            ar.Method,
            s.SubjectCode,
            s.SubjectName,
            t.FullName as TeacherName,
            DATE(ar.DateTime) as Date,
            TIME(ar.DateTime) as Time
        FROM attendance_records ar
        JOIN subjects s ON ar.SubjectID = s.SubjectID
        JOIN teachers t ON ar.TeacherID = t.TeacherID
        WHERE $whereClause
        ORDER BY ar.DateTime DESC
    ");
    
    $attendanceQuery->bind_param($paramTypes, ...$params);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    $attendanceData = [];
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceData[] = $row;
    }
    
    // Calculate statistics
    $totalRecords = count($attendanceData);
    $presentCount = count(array_filter($attendanceData, fn($r) => $r['Status'] === 'present'));
    $absentCount = count(array_filter($attendanceData, fn($r) => $r['Status'] === 'absent'));
    $lateCount = count(array_filter($attendanceData, fn($r) => $r['Status'] === 'late'));
    $qrCount = count(array_filter($attendanceData, fn($r) => $r['Method'] === 'qr'));
    $manualCount = count(array_filter($attendanceData, fn($r) => $r['Method'] === 'manual'));
    
    $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100) : 0;
    
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
    $filename = "Attendance_Report_{$studentInfo['FullName']}{$subjectFilter}_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Header information
    fputcsv($output, ['STUDENT ATTENDANCE REPORT']);
    fputcsv($output, []);
    fputcsv($output, ['Student Information']);
    fputcsv($output, ['Name', $studentInfo['FullName']]);
    fputcsv($output, ['Program', $studentInfo['ProgramCode']]);
    fputcsv($output, ['Department', $studentInfo['DepartmentName']]);
    fputcsv($output, ['Semester', $studentInfo['SemesterNumber']]);
    fputcsv($output, []);
    
    fputcsv($output, ['Report Filters']);
    fputcsv($output, ['Subject Filter', $subjectFilter ?: 'All Subjects']);
    fputcsv($output, ['Month Filter', $month]);
    fputcsv($output, []);
    
    fputcsv($output, ['Attendance Summary']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Records', $totalRecords]);
    fputcsv($output, ['Present', $presentCount]);
    fputcsv($output, ['Absent', $absentCount]);
    fputcsv($output, ['Late', $lateCount]);
    fputcsv($output, ['Attendance Rate', $attendanceRate . '%']);
    fputcsv($output, ['QR Code Method', $qrCount]);
    fputcsv($output, ['Manual Method', $manualCount]);
    fputcsv($output, []);
    
    // Attendance Records
    fputcsv($output, ['Attendance Records']);
    fputcsv($output, ['Date', 'Time', 'Subject Code', 'Subject Name', 'Teacher', 'Status', 'Method']);
    foreach ($attendanceData as $record) {
        fputcsv($output, [
            $record['Date'],
            $record['Time'],
            $record['SubjectCode'],
            $record['SubjectName'],
            $record['TeacherName'],
            ucfirst($record['Status']),
            ucfirst($record['Method'])
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['Report Generated', date('Y-m-d H:i:s')]);
    
    fclose($output);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?> 