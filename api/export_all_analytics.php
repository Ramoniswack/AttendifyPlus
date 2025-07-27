<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_config.php';

try {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="complete_analytics_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // ===== SYSTEM OVERVIEW =====
    fputcsv($output, ['SYSTEM OVERVIEW']);
    fputcsv($output, ['Metric', 'Value']);
    
    // Total counts
    $totalStudents = $conn->query("SELECT COUNT(*) as count FROM students s JOIN login_tbl l ON s.LoginID = l.LoginID WHERE l.Status = 'active'")->fetch_assoc()['count'];
    $totalTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active'")->fetch_assoc()['count'];
    $totalAdmins = $conn->query("SELECT COUNT(*) as count FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Status = 'active'")->fetch_assoc()['count'];
    $totalAttendance = $conn->query("SELECT COUNT(*) as count FROM attendance_records")->fetch_assoc()['count'];
    $totalAssignments = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE IsActive = 1")->fetch_assoc()['count'];
    $totalSubmissions = $conn->query("SELECT COUNT(*) as count FROM assignment_submissions")->fetch_assoc()['count'];
    $totalMaterials = $conn->query("SELECT COUNT(*) as count FROM materials WHERE IsActive = 1")->fetch_assoc()['count'];
    $registeredDevices = $conn->query("SELECT COUNT(*) as count FROM students WHERE DeviceRegistered = 1")->fetch_assoc()['count'];
    
    fputcsv($output, ['Total Students', $totalStudents]);
    fputcsv($output, ['Total Teachers', $totalTeachers]);
    fputcsv($output, ['Total Admins', $totalAdmins]);
    fputcsv($output, ['Total Attendance Records', $totalAttendance]);
    fputcsv($output, ['Total Assignments', $totalAssignments]);
    fputcsv($output, ['Total Submissions', $totalSubmissions]);
    fputcsv($output, ['Total Materials', $totalMaterials]);
    fputcsv($output, ['Registered Devices', $registeredDevices]);
    
    fputcsv($output, []); // Empty row
    
    // ===== DEPARTMENT STATISTICS =====
    fputcsv($output, ['DEPARTMENT STATISTICS']);
    fputcsv($output, ['Department', 'Students', 'Teachers', 'Subjects']);
    
    $deptStatsQuery = "SELECT d.DepartmentName, 
                              COUNT(DISTINCT s.StudentID) as student_count,
                              COUNT(DISTINCT t.TeacherID) as teacher_count,
                              COUNT(DISTINCT sub.SubjectID) as subject_count
                       FROM departments d 
                       LEFT JOIN students s ON d.DepartmentID = s.DepartmentID AND s.LoginID IN (SELECT LoginID FROM login_tbl WHERE Status = 'active')
                       LEFT JOIN teacher_department_map tdm ON d.DepartmentID = tdm.DepartmentID
                       LEFT JOIN teachers t ON tdm.TeacherID = t.TeacherID AND t.LoginID IN (SELECT LoginID FROM login_tbl WHERE Status = 'active')
                       LEFT JOIN subjects sub ON d.DepartmentID = sub.DepartmentID
                       GROUP BY d.DepartmentID, d.DepartmentName 
                       ORDER BY student_count DESC";
    $deptStats = $conn->query($deptStatsQuery)->fetch_all(MYSQLI_ASSOC);
    
    foreach ($deptStats as $dept) {
        fputcsv($output, [
            $dept['DepartmentName'],
            $dept['student_count'],
            $dept['teacher_count'],
            $dept['subject_count']
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // ===== ATTENDANCE STATISTICS =====
    fputcsv($output, ['ATTENDANCE STATISTICS']);
    fputcsv($output, ['Status', 'Count', 'Percentage']);
    
    $attendanceStatsQuery = "SELECT 
                                Status,
                                COUNT(*) as count,
                                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM attendance_records), 2) as percentage
                             FROM attendance_records 
                             GROUP BY Status 
                             ORDER BY count DESC";
    $attendanceStats = $conn->query($attendanceStatsQuery)->fetch_all(MYSQLI_ASSOC);
    
    foreach ($attendanceStats as $stat) {
        fputcsv($output, [
            ucfirst($stat['Status']),
            $stat['count'],
            $stat['percentage'] . '%'
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // ===== ASSIGNMENT STATISTICS =====
    fputcsv($output, ['ASSIGNMENT STATISTICS']);
    fputcsv($output, ['Status', 'Count', 'Percentage']);
    
    $assignmentStatsQuery = "SELECT 
                                Status,
                                COUNT(*) as count,
                                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM assignment_submissions), 2) as percentage
                             FROM assignment_submissions 
                             GROUP BY Status 
                             ORDER BY count DESC";
    $assignmentStats = $conn->query($assignmentStatsQuery)->fetch_all(MYSQLI_ASSOC);
    
    foreach ($assignmentStats as $stat) {
        fputcsv($output, [
            ucfirst($stat['Status']),
            $stat['count'],
            $stat['percentage'] . '%'
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // ===== MONTHLY TRENDS =====
    fputcsv($output, ['MONTHLY TRENDS (Last 6 Months)']);
    fputcsv($output, ['Month', 'New Students', 'Attendance Records', 'Assignments Created', 'Submissions']);
    
    $monthlyQuery = "SELECT 
                        DATE_FORMAT(date_range.month_date, '%M %Y') as month_year,
                        COALESCE(new_students.count, 0) as new_students,
                        COALESCE(attendance.count, 0) as attendance_records,
                        COALESCE(assignments.count, 0) as assignments_created,
                        COALESCE(submissions.count, 0) as submissions
                     FROM (
                         SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL n MONTH), '%Y-%m-01') as month_date
                         FROM (
                             SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
                         ) as months
                     ) as date_range
                     LEFT JOIN (
                         SELECT DATE_FORMAT(l.CreatedDate, '%Y-%m-01') as month_date, COUNT(*) as count
                         FROM login_tbl l 
                         WHERE l.Role = 'student' 
                         AND l.CreatedDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(l.CreatedDate, '%Y-%m')
                     ) as new_students ON date_range.month_date = new_students.month_date
                     LEFT JOIN (
                         SELECT DATE_FORMAT(ar.DateTime, '%Y-%m-01') as month_date, COUNT(*) as count
                         FROM attendance_records ar
                         WHERE ar.DateTime >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(ar.DateTime, '%Y-%m')
                     ) as attendance ON date_range.month_date = attendance.month_date
                     LEFT JOIN (
                         SELECT DATE_FORMAT(a.CreatedAt, '%Y-%m-01') as month_date, COUNT(*) as count
                         FROM assignments a
                         WHERE a.CreatedAt >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(a.CreatedAt, '%Y-%m')
                     ) as assignments ON date_range.month_date = assignments.month_date
                     LEFT JOIN (
                         SELECT DATE_FORMAT(ass.SubmittedAt, '%Y-%m-01') as month_date, COUNT(*) as count
                         FROM assignment_submissions ass
                         WHERE ass.SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(ass.SubmittedAt, '%Y-%m')
                     ) as submissions ON date_range.month_date = submissions.month_date
                     ORDER BY date_range.month_date DESC";
    $monthlyStats = $conn->query($monthlyQuery)->fetch_all(MYSQLI_ASSOC);
    
    foreach ($monthlyStats as $stat) {
        fputcsv($output, [
            $stat['month_year'],
            $stat['new_students'],
            $stat['attendance_records'],
            $stat['assignments_created'],
            $stat['submissions']
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // ===== TOP PERFORMING STUDENTS =====
    fputcsv($output, ['TOP PERFORMING STUDENTS (By Attendance)']);
    fputcsv($output, ['Rank', 'Student Name', 'Department', 'Semester', 'Attendance Percentage', 'Total Sessions']);
    
    $topStudentsQuery = "SELECT 
                            s.FullName,
                            d.DepartmentName,
                            sem.SemesterNumber,
                            COUNT(ar.AttendanceID) as total_sessions,
                            SUM(CASE WHEN ar.Status = 'present' THEN 1 ELSE 0 END) as present_sessions,
                            ROUND(SUM(CASE WHEN ar.Status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.AttendanceID), 2) as attendance_percentage
                         FROM students s
                         JOIN departments d ON s.DepartmentID = d.DepartmentID
                         JOIN semesters sem ON s.SemesterID = sem.SemesterID
                         JOIN login_tbl l ON s.LoginID = l.LoginID
                         LEFT JOIN attendance_records ar ON s.StudentID = ar.StudentID
                         WHERE l.Status = 'active'
                         GROUP BY s.StudentID, s.FullName, d.DepartmentName, sem.SemesterNumber
                         HAVING total_sessions > 0
                         ORDER BY attendance_percentage DESC, total_sessions DESC
                         LIMIT 10";
    $topStudents = $conn->query($topStudentsQuery)->fetch_all(MYSQLI_ASSOC);
    
    $rank = 1;
    foreach ($topStudents as $student) {
        fputcsv($output, [
            $rank++,
            $student['FullName'],
            $student['DepartmentName'],
            'Semester ' . $student['SemesterNumber'],
            $student['attendance_percentage'] . '%',
            $student['total_sessions']
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // ===== TOP PERFORMING TEACHERS =====
    fputcsv($output, ['TOP PERFORMING TEACHERS (By Assignments & Submissions)']);
    fputcsv($output, ['Rank', 'Teacher Name', 'Department', 'Total Assignments', 'Total Submissions', 'Graded Submissions']);
    
    $topTeachersQuery = "SELECT 
                            t.FullName,
                            d.DepartmentName,
                            COUNT(DISTINCT a.AssignmentID) as total_assignments,
                            COUNT(ass.SubmissionID) as total_submissions,
                            SUM(CASE WHEN ass.Status = 'graded' THEN 1 ELSE 0 END) as graded_submissions
                         FROM teachers t
                         LEFT JOIN teacher_department_map tdm ON t.TeacherID = tdm.TeacherID
                         LEFT JOIN departments d ON tdm.DepartmentID = d.DepartmentID
                         JOIN login_tbl l ON t.LoginID = l.LoginID
                         LEFT JOIN assignments a ON t.TeacherID = a.TeacherID AND a.IsActive = 1
                         LEFT JOIN assignment_submissions ass ON a.AssignmentID = ass.AssignmentID
                         WHERE l.Status = 'active'
                         GROUP BY t.TeacherID, t.FullName, d.DepartmentName
                         ORDER BY total_assignments DESC, total_submissions DESC
                         LIMIT 10";
    $topTeachers = $conn->query($topTeachersQuery)->fetch_all(MYSQLI_ASSOC);
    
    $rank = 1;
    foreach ($topTeachers as $teacher) {
        fputcsv($output, [
            $rank++,
            $teacher['FullName'],
            $teacher['DepartmentName'] ?? 'Not Assigned',
            $teacher['total_assignments'],
            $teacher['total_submissions'],
            $teacher['graded_submissions']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Error in export_all_analytics: " . $e->getMessage());
    die('Error exporting complete analytics');
}
?> 