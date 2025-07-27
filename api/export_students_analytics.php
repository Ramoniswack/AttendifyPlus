<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_config.php';

try {
    // Get all students with their analytics
    $studentsQuery = "SELECT s.StudentID, s.FullName, s.Contact, s.JoinYear, s.ProgramCode,
                             d.DepartmentName, sem.SemesterNumber, s.DeviceRegistered
                      FROM students s 
                      JOIN departments d ON s.DepartmentID = d.DepartmentID 
                      JOIN semesters sem ON s.SemesterID = sem.SemesterID 
                      JOIN login_tbl l ON s.LoginID = l.LoginID 
                      WHERE l.Status = 'active' 
                      ORDER BY s.FullName";
    $students = $conn->query($studentsQuery)->fetch_all(MYSQLI_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_analytics_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create CSV output
    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, [
        'Student ID',
        'Full Name',
        'Contact',
        'Department',
        'Semester',
        'Join Year',
        'Program Code',
        'Device Registered',
        'Total Attendance',
        'Present Count',
        'Absent Count',
        'Late Count',
        'Attendance Percentage',
        'Total Submissions',
        'Graded Submissions',
        'Average Grade',
        'Material Views',
        'Last Activity Date'
    ]);

    // Add data for each student
    foreach ($students as $student) {
        $studentId = $student['StudentID'];

        // Get attendance statistics
        $attendanceQuery = "SELECT 
                               COUNT(*) as total_attendance,
                               SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_count,
                               SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                               SUM(CASE WHEN Status = 'late' THEN 1 ELSE 0 END) as late_count
                            FROM attendance_records 
                            WHERE StudentID = ?";
        $attendanceStmt = $conn->prepare($attendanceQuery);
        $attendanceStmt->bind_param("i", $studentId);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result()->fetch_assoc();

        $totalAttendance = $attendanceResult['total_attendance'];
        $presentCount = $attendanceResult['present_count'];
        $attendancePercentage = $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100, 1) : 0;

        // Get assignment submission statistics
        $submissionQuery = "SELECT 
                               COUNT(*) as total_submissions,
                               SUM(CASE WHEN Status = 'graded' THEN 1 ELSE 0 END) as graded_submissions,
                               AVG(CASE WHEN Status = 'graded' THEN Grade ELSE NULL END) as average_grade
                            FROM assignment_submissions 
                            WHERE StudentID = ?";
        $submissionStmt = $conn->prepare($submissionQuery);
        $submissionStmt->bind_param("i", $studentId);
        $submissionStmt->execute();
        $submissionResult = $submissionStmt->get_result()->fetch_assoc();

        $averageGrade = round($submissionResult['average_grade'] ?? 0, 1);

        // Get material access statistics
        $materialQuery = "SELECT COUNT(*) as material_views
                         FROM material_access_logs 
                         WHERE StudentID = ?";
        $materialStmt = $conn->prepare($materialQuery);
        $materialStmt->bind_param("i", $studentId);
        $materialStmt->execute();
        $materialViews = $materialStmt->get_result()->fetch_assoc()['material_views'];

        // Get last activity date
        $lastActivityQuery = "SELECT MAX(activity_date) as last_activity
                             FROM (
                                 SELECT DateTime as activity_date FROM attendance_records WHERE StudentID = ?
                                 UNION ALL
                                 SELECT SubmittedAt as activity_date FROM assignment_submissions WHERE StudentID = ?
                             ) as activities";
        $lastActivityStmt = $conn->prepare($lastActivityQuery);
        $lastActivityStmt->bind_param("ii", $studentId, $studentId);
        $lastActivityStmt->execute();
        $lastActivity = $lastActivityStmt->get_result()->fetch_assoc()['last_activity'];

        // Write CSV row
        fputcsv($output, [
            $student['StudentID'],
            $student['FullName'],
            $student['Contact'] ?? 'N/A',
            $student['DepartmentName'],
            $student['SemesterNumber'],
            $student['JoinYear'],
            $student['ProgramCode'],
            $student['DeviceRegistered'] ? 'Yes' : 'No',
            $totalAttendance,
            $presentCount,
            $attendanceResult['absent_count'],
            $attendanceResult['late_count'],
            $attendancePercentage . '%',
            $submissionResult['total_submissions'],
            $submissionResult['graded_submissions'],
            $averageGrade,
            $materialViews,
            $lastActivity ? date('Y-m-d', strtotime($lastActivity)) : 'N/A'
        ]);
    }

    fclose($output);
} catch (Exception $e) {
    error_log("Error in export_students_analytics: " . $e->getMessage());
    die('Error exporting students analytics');
}
