<?php
session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
    error_log("Teacher analytics API called - UserID: " . ($_SESSION['UserID'] ?? 'not set') . ", Role: " . ($_SESSION['Role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include '../config/db_config.php';

$teacherId = $_GET['teacher_id'] ?? null;

error_log("Teacher analytics requested for TeacherID: " . ($teacherId ?? 'null'));

if (!$teacherId) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
    exit();
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
        error_log("Teacher not found for ID: " . $teacherId);
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
        exit();
    }

    error_log("Teacher analytics generated successfully for: " . $teacher['FullName']);

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
        'submitted' => $submissionResult['total_submissions'] ?? 0,
        'graded' => $submissionResult['graded_submissions'] ?? 0,
        'pending' => $submissionResult['pending_submissions'] ?? 0
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

    $attendanceLabels = [];
    $attendanceValues = [];
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceLabels[] = date('M j', strtotime($row['date']));
        $attendanceValues[] = $row['sessions'];
    }

    $analytics['attendance_data'] = [
        'labels' => $attendanceLabels,
        'values' => $attendanceValues
    ];

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

    $analytics['recent_activities'] = $recentActivities;

    echo json_encode([
        'success' => true,
        'teacher' => $teacher,
        'analytics' => $analytics
    ]);
} catch (Exception $e) {
    error_log("Error in get_teacher_analytics: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching teacher analytics: ' . $e->getMessage()]);
}
