<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_config.php';

$teacherId = $_GET['teacher_id'] ?? null;

if (!$teacherId) {
    die('Teacher ID is required');
}

try {
    // Get teacher information
    $teacherQuery = "SELECT t.TeacherID, t.FullName, t.Contact, d.DepartmentName 
                     FROM teachers t 
                     LEFT JOIN teacher_department_map tdm ON t.TeacherID = tdm.TeacherID 
                     LEFT JOIN departments d ON tdm.DepartmentID = d.DepartmentID 
                     WHERE t.TeacherID = ?";
    $teacherStmt = $conn->prepare($teacherQuery);
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();
    
    if (!$teacher) {
        die('Teacher not found');
    }
    
    // Get analytics data
    $analytics = [];
    
    // Total subjects taught
    $subjectsQuery = "SELECT COUNT(DISTINCT tsm.SubjectID) as total_subjects 
                      FROM teacher_subject_map tsm 
                      WHERE tsm.TeacherID = ?";
    $subjectsStmt = $conn->prepare($subjectsQuery);
    $subjectsStmt->bind_param("i", $teacherId);
    $subjectsStmt->execute();
    $analytics['total_subjects'] = $subjectsStmt->get_result()->fetch_assoc()['total_subjects'];
    
    // Total assignments created
    $assignmentsQuery = "SELECT COUNT(*) as total_assignments 
                        FROM assignments 
                        WHERE TeacherID = ? AND IsActive = 1";
    $assignmentsStmt = $conn->prepare($assignmentsQuery);
    $assignmentsStmt->bind_param("i", $teacherId);
    $assignmentsStmt->execute();
    $analytics['total_assignments'] = $assignmentsStmt->get_result()->fetch_assoc()['total_assignments'];
    
    // Assignment submission statistics
    $submissionQuery = "SELECT 
                           COUNT(*) as total_submissions,
                           SUM(CASE WHEN ass.Status = 'graded' THEN 1 ELSE 0 END) as graded_submissions,
                           SUM(CASE WHEN ass.Status = 'submitted' THEN 1 ELSE 0 END) as pending_submissions
                        FROM assignment_submissions ass
                        JOIN assignments a ON ass.AssignmentID = a.AssignmentID
                        WHERE a.TeacherID = ?";
    $submissionStmt = $conn->prepare($submissionQuery);
    $submissionStmt->bind_param("i", $teacherId);
    $submissionStmt->execute();
    $submissionResult = $submissionStmt->get_result()->fetch_assoc();
    
    $analytics['assignment_data'] = [
        'submitted' => $submissionResult['total_submissions'],
        'graded' => $submissionResult['graded_submissions'],
        'pending' => $submissionResult['pending_submissions']
    ];
    
    // Attendance sessions (last 30 days)
    $attendanceQuery = "SELECT 
                           DATE(ar.DateTime) as date,
                           COUNT(*) as sessions
                        FROM attendance_records ar
                        JOIN teacher_subject_map tsm ON ar.SubjectID = tsm.SubjectID
                        WHERE tsm.TeacherID = ?
                        AND ar.DateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(ar.DateTime)
                        ORDER BY date";
    $attendanceStmt = $conn->prepare($attendanceQuery);
    $attendanceStmt->bind_param("i", $teacherId);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();
    
    $attendanceData = [];
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceData[] = [
            'date' => date('M j, Y', strtotime($row['date'])),
            'sessions' => $row['sessions']
        ];
    }
    
    // Recent activities
    $activitiesQuery = "SELECT 
                           'assignment' as type,
                           a.Title as details,
                           a.CreatedAt as date
                        FROM assignments a
                        WHERE a.TeacherID = ?
                        AND a.IsActive = 1
                        UNION ALL
                        SELECT 
                           'attendance' as type,
                           CONCAT('Attendance session for ', s.SubjectName) as details,
                           ar.DateTime as date
                        FROM attendance_records ar
                        JOIN teacher_subject_map tsm ON ar.SubjectID = tsm.SubjectID
                        JOIN subjects s ON ar.SubjectID = s.SubjectID
                        WHERE tsm.TeacherID = ?
                        ORDER BY date DESC
                        LIMIT 10";
    $activitiesStmt = $conn->prepare($activitiesQuery);
    $activitiesStmt->bind_param("ii", $teacherId, $teacherId);
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
    
    // Set headers for CSV download (since PDF generation requires additional libraries)
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teacher_report_' . $teacher['TeacherID'] . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Teacher Analytics Report']);
    fputcsv($output, ['Generated on: ' . date('F j, Y \a\t g:i A')]);
    fputcsv($output, []);
    
    // Teacher Information
    fputcsv($output, ['TEACHER INFORMATION']);
    fputcsv($output, ['Name', $teacher['FullName']]);
    fputcsv($output, ['Contact', $teacher['Contact'] ?? 'N/A']);
    fputcsv($output, ['Department', $teacher['DepartmentName'] ?? 'Not Assigned']);
    fputcsv($output, []);
    
    // Statistics
    fputcsv($output, ['STATISTICS']);
    fputcsv($output, ['Total Subjects', $analytics['total_subjects']]);
    fputcsv($output, ['Total Assignments', $analytics['total_assignments']]);
    fputcsv($output, ['Total Submissions', $analytics['assignment_data']['submitted']]);
    fputcsv($output, ['Graded Submissions', $analytics['assignment_data']['graded']]);
    fputcsv($output, ['Pending Submissions', $analytics['assignment_data']['pending']]);
    fputcsv($output, []);
    
    // Attendance Data
    fputcsv($output, ['ATTENDANCE SESSIONS (Last 30 Days)']);
    fputcsv($output, ['Date', 'Sessions']);
    foreach ($attendanceData as $data) {
        fputcsv($output, [$data['date'], $data['sessions']]);
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
    error_log("Error in download_teacher_report: " . $e->getMessage());
    die('Error generating teacher report');
}
?>
