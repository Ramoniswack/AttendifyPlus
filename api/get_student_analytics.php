<?php
session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include '../config/db_config.php';

$studentId = $_GET['student_id'] ?? null;

if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit();
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
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
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

    // Attendance trend (last 30 days)
    $trendQuery = "SELECT 
                      DATE(DateTime) as date,
                      COUNT(*) as total_sessions,
                      SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_sessions
                   FROM attendance_records 
                   WHERE StudentID = ?
                   AND DateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                   GROUP BY DATE(DateTime)
                   ORDER BY date";
    $trendStmt = $conn->prepare($trendQuery);
    $trendStmt->bind_param("i", $studentId);
    $trendStmt->execute();
    $trendResult = $trendStmt->get_result();

    $trendLabels = [];
    $trendValues = [];
    while ($row = $trendResult->fetch_assoc()) {
        $trendLabels[] = date('M j', strtotime($row['date']));
        $attendanceRate = $row['total_sessions'] > 0 ? ($row['present_sessions'] / $row['total_sessions']) * 100 : 0;
        $trendValues[] = round($attendanceRate, 1);
    }

    $analytics['attendance_trend'] = [
        'labels' => $trendLabels,
        'values' => $trendValues
    ];

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

    $performanceLabels = [];
    $performanceValues = [];
    while ($row = $performanceResult->fetch_assoc()) {
        $performanceLabels[] = $row['SubjectName'];
        $attendanceRate = $row['total_sessions'] > 0 ? ($row['present_sessions'] / $row['total_sessions']) * 100 : 0;
        $grade = $row['average_grade'] ?? 0;
        $performanceValues[] = round(($attendanceRate + $grade) / 2, 1); // Combined score
    }

    $analytics['subject_performance'] = [
        'labels' => $performanceLabels,
        'values' => $performanceValues
    ];

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

    $analytics['recent_activities'] = $recentActivities;

    // Device registration status
    $analytics['device_registered'] = $student['DeviceRegistered'];

    // Material access statistics
    $materialQuery = "SELECT COUNT(*) as material_views
                     FROM material_access_logs 
                     WHERE StudentID = ?";
    $materialStmt = $conn->prepare($materialQuery);
    $materialStmt->bind_param("i", $studentId);
    $materialStmt->execute();
    $analytics['material_views'] = $materialStmt->get_result()->fetch_assoc()['material_views'];

    echo json_encode([
        'success' => true,
        'student' => $student,
        'analytics' => $analytics
    ]);
} catch (Exception $e) {
    error_log("Error in get_student_analytics: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching student analytics']);
}
