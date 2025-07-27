<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_config.php';

$studentId = $_GET['student_id'] ?? null;

if (!$studentId) {
    die('Student ID is required');
}

try {
    // Get student information
    $studentQuery = "SELECT s.StudentID, s.FullName, s.Contact, s.JoinYear, s.ProgramCode,
                            d.DepartmentName, sem.SemesterNumber, s.DeviceRegistered
                     FROM students s 
                     JOIN departments d ON s.DepartmentID = d.DepartmentID 
                     JOIN semesters sem ON s.SemesterID = sem.SemesterID 
                     WHERE s.StudentID = ?";
    $studentStmt = $conn->prepare($studentQuery);
    $studentStmt->bind_param("i", $studentId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
    
    if (!$student) {
        die('Student not found');
    }
    
    // Get analytics data
    $analytics = [];
    
    // Attendance statistics
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
    $analytics['attendance_percentage'] = $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100, 1) : 0;
    $analytics['total_attendance'] = $totalAttendance;
    $analytics['present_count'] = $presentCount;
    $analytics['absent_count'] = $attendanceResult['absent_count'];
    $analytics['late_count'] = $attendanceResult['late_count'];
    
    // Assignment submission statistics
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
    
    $analytics['total_submissions'] = $submissionResult['total_submissions'];
    $analytics['graded_submissions'] = $submissionResult['graded_submissions'];
    $analytics['average_grade'] = round($submissionResult['average_grade'] ?? 0, 1);
    
    // Subject performance
    $performanceQuery = "SELECT 
                           s.SubjectName,
                           COUNT(ar.AttendanceID) as total_sessions,
                           SUM(CASE WHEN ar.Status = 'present' THEN 1 ELSE 0 END) as present_sessions,
                           AVG(CASE WHEN ass.Status = 'graded' THEN ass.Grade ELSE NULL END) as average_grade
                        FROM subjects s
                        LEFT JOIN attendance_records ar ON s.SubjectID = ar.SubjectID AND ar.StudentID = ?
                        LEFT JOIN assignment_submissions ass ON s.SubjectID = ass.AssignmentID AND ass.StudentID = ?
                        WHERE s.DepartmentID = (SELECT DepartmentID FROM students WHERE StudentID = ?)
                        GROUP BY s.SubjectID, s.SubjectName
                        HAVING total_sessions > 0 OR average_grade IS NOT NULL
                        ORDER BY average_grade DESC";
    $performanceStmt = $conn->prepare($performanceQuery);
    $performanceStmt->bind_param("iii", $studentId, $studentId, $studentId);
    $performanceStmt->execute();
    $performanceResult = $performanceStmt->get_result();
    
    $subjectPerformance = [];
    while ($row = $performanceResult->fetch_assoc()) {
        $attendanceRate = $row['total_sessions'] > 0 ? ($row['present_sessions'] / $row['total_sessions']) * 100 : 0;
        $grade = $row['average_grade'] ?? 0;
        $subjectPerformance[] = [
            'subject' => $row['SubjectName'],
            'attendance_rate' => round($attendanceRate, 1),
            'average_grade' => round($grade, 1),
            'combined_score' => round(($attendanceRate + $grade) / 2, 1)
        ];
    }
    
    // Recent activities
    $activitiesQuery = "SELECT 
                           'attendance' as type,
                           CONCAT('Marked ', Status, ' for ', s.SubjectName) as details,
                           DateTime as date
                        FROM attendance_records ar
                        JOIN subjects s ON ar.SubjectID = s.SubjectID
                        WHERE ar.StudentID = ?
                        UNION ALL
                        SELECT 
                           'submission' as type,
                           CONCAT('Submitted assignment: ', a.Title) as details,
                           SubmittedAt as date
                        FROM assignment_submissions ass
                        JOIN assignments a ON ass.AssignmentID = a.AssignmentID
                        WHERE ass.StudentID = ?
                        ORDER BY date DESC
                        LIMIT 10";
    $activitiesStmt = $conn->prepare($activitiesQuery);
    $activitiesStmt->bind_param("ii", $studentId, $studentId);
    $activitiesStmt->execute();
    $activitiesResult = $activitiesStmt->get_result();
    
    $recentActivities = [];
    while ($row = $activitiesResult->fetch_assoc()) {
        $recentActivities[] = [
            'type' => ucfirst($row['type']),
            'details' => $row['details'],
            'date' => date('M j, Y g:i A', strtotime($row['date']))
        ];
    }
    
    // Material access statistics
    $materialQuery = "SELECT COUNT(*) as material_views
                     FROM material_access_logs 
                     WHERE StudentID = ?";
    $materialStmt = $conn->prepare($materialQuery);
    $materialStmt->bind_param("i", $studentId);
    $materialStmt->execute();
    $analytics['material_views'] = $materialStmt->get_result()->fetch_assoc()['material_views'];
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_report_' . $student['StudentID'] . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Student Analytics Report']);
    fputcsv($output, ['Generated on: ' . date('F j, Y \a\t g:i A')]);
    fputcsv($output, []);
    
    // Student Information
    fputcsv($output, ['STUDENT INFORMATION']);
    fputcsv($output, ['Name', $student['FullName']]);
    fputcsv($output, ['Contact', $student['Contact'] ?? 'N/A']);
    fputcsv($output, ['Department', $student['DepartmentName']]);
    fputcsv($output, ['Semester', 'Semester ' . $student['SemesterNumber']]);
    fputcsv($output, ['Join Year', $student['JoinYear']]);
    fputcsv($output, ['Program Code', $student['ProgramCode']]);
    fputcsv($output, ['Device Registered', $student['DeviceRegistered'] ? 'Yes' : 'No']);
    fputcsv($output, []);
    
    // Overall Statistics
    fputcsv($output, ['OVERALL STATISTICS']);
    fputcsv($output, ['Attendance Percentage', $analytics['attendance_percentage'] . '%']);
    fputcsv($output, ['Total Attendance Sessions', $analytics['total_attendance']]);
    fputcsv($output, ['Present Count', $analytics['present_count']]);
    fputcsv($output, ['Absent Count', $analytics['absent_count']]);
    fputcsv($output, ['Late Count', $analytics['late_count']]);
    fputcsv($output, ['Total Submissions', $analytics['total_submissions']]);
    fputcsv($output, ['Graded Submissions', $analytics['graded_submissions']]);
    fputcsv($output, ['Average Grade', $analytics['average_grade']]);
    fputcsv($output, ['Material Views', $analytics['material_views']]);
    fputcsv($output, []);
    
    // Subject Performance
    fputcsv($output, ['SUBJECT PERFORMANCE']);
    fputcsv($output, ['Subject', 'Attendance Rate (%)', 'Average Grade', 'Combined Score']);
    foreach ($subjectPerformance as $performance) {
        fputcsv($output, [
            $performance['subject'],
            $performance['attendance_rate'],
            $performance['average_grade'],
            $performance['combined_score']
        ]);
    }
    fputcsv($output, []);
    
    // Recent Activities
    fputcsv($output, ['RECENT ACTIVITIES']);
    fputcsv($output, ['Type', 'Details', 'Date']);
    foreach ($recentActivities as $activity) {
        fputcsv($output, [$activity['type'], $activity['details'], $activity['date']]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Error in download_student_report: " . $e->getMessage());
    die('Error generating student report');
}
?>
