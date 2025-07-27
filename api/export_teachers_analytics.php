<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_config.php';

try {
    // Get all teachers with their analytics
    $teachersQuery = "SELECT t.TeacherID, t.FullName, t.Contact, d.DepartmentName 
                      FROM teachers t 
                      LEFT JOIN teacher_department_map tdm ON t.TeacherID = tdm.TeacherID 
                      LEFT JOIN departments d ON tdm.DepartmentID = d.DepartmentID 
                      JOIN login_tbl l ON t.LoginID = l.LoginID 
                      WHERE l.Status = 'active' 
                      ORDER BY t.FullName";
    $teachers = $conn->query($teachersQuery)->fetch_all(MYSQLI_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teachers_analytics_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create CSV output
    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, [
        'Teacher ID',
        'Full Name',
        'Contact',
        'Department',
        'Total Subjects',
        'Total Assignments',
        'Total Submissions',
        'Graded Submissions',
        'Pending Submissions',
        'Attendance Sessions (30 days)',
        'Last Activity Date'
    ]);

    // Add data for each teacher
    foreach ($teachers as $teacher) {
        $teacherId = $teacher['TeacherID'];

        // Get analytics for this teacher
        $subjectsQuery = "SELECT COUNT(DISTINCT tsm.SubjectID) as total_subjects 
                          FROM teacher_subject_map tsm 
                          WHERE tsm.TeacherID = ?";
        $subjectsStmt = $conn->prepare($subjectsQuery);
        $subjectsStmt->bind_param("i", $teacherId);
        $subjectsStmt->execute();
        $totalSubjects = $subjectsStmt->get_result()->fetch_assoc()['total_subjects'];

        $assignmentsQuery = "SELECT COUNT(*) as total_assignments 
                            FROM assignments 
                            WHERE TeacherID = ? AND IsActive = 1";
        $assignmentsStmt = $conn->prepare($assignmentsQuery);
        $assignmentsStmt->bind_param("i", $teacherId);
        $assignmentsStmt->execute();
        $totalAssignments = $assignmentsStmt->get_result()->fetch_assoc()['total_assignments'];

        $submissionQuery = "SELECT 
                               COUNT(*) as total_submissions,
                               SUM(CASE WHEN Status = 'graded' THEN 1 ELSE 0 END) as graded_submissions,
                               SUM(CASE WHEN Status = 'submitted' THEN 1 ELSE 0 END) as pending_submissions
                            FROM assignment_submissions ass
                            JOIN assignments a ON ass.AssignmentID = a.AssignmentID
                            WHERE a.TeacherID = ?";
        $submissionStmt = $conn->prepare($submissionQuery);
        $submissionStmt->bind_param("i", $teacherId);
        $submissionStmt->execute();
        $submissionResult = $submissionStmt->get_result()->fetch_assoc();

        $attendanceQuery = "SELECT COUNT(*) as attendance_sessions
                           FROM attendance_records ar
                           JOIN teacher_subject_map tsm ON ar.SubjectID = tsm.SubjectID
                           WHERE tsm.TeacherID = ?
                           AND ar.DateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $attendanceStmt = $conn->prepare($attendanceQuery);
        $attendanceStmt->bind_param("i", $teacherId);
        $attendanceStmt->execute();
        $attendanceSessions = $attendanceStmt->get_result()->fetch_assoc()['attendance_sessions'];

        $lastActivityQuery = "SELECT MAX(activity_date) as last_activity
                             FROM (
                                 SELECT CreatedAt as activity_date FROM assignments WHERE TeacherID = ?
                                 UNION ALL
                                 SELECT DateTime as activity_date FROM attendance_records ar
                                 JOIN teacher_subject_map tsm ON ar.SubjectID = tsm.SubjectID
                                 WHERE tsm.TeacherID = ?
                             ) as activities";
        $lastActivityStmt = $conn->prepare($lastActivityQuery);
        $lastActivityStmt->bind_param("ii", $teacherId, $teacherId);
        $lastActivityStmt->execute();
        $lastActivity = $lastActivityStmt->get_result()->fetch_assoc()['last_activity'];

        // Write CSV row
        fputcsv($output, [
            $teacher['TeacherID'],
            $teacher['FullName'],
            $teacher['Contact'] ?? 'N/A',
            $teacher['DepartmentName'] ?? 'Not Assigned',
            $totalSubjects,
            $totalAssignments,
            $submissionResult['total_submissions'],
            $submissionResult['graded_submissions'],
            $submissionResult['pending_submissions'],
            $attendanceSessions,
            $lastActivity ? date('Y-m-d', strtotime($lastActivity)) : 'N/A'
        ]);
    }

    fclose($output);
} catch (Exception $e) {
    error_log("Error in export_teachers_analytics: " . $e->getMessage());
    die('Error exporting teachers analytics');
}
