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
    // Get subject details
    $subjectQuery = $conn->prepare("
        SELECT 
            s.SubjectID,
            s.SubjectCode,
            s.SubjectName,
            s.CreditHour,
            t.FullName as TeacherName,
            -- Attendance stats
            COALESCE(att.total_sessions, 0) as total_sessions,
            COALESCE(att.present_count, 0) as present_count,
            COALESCE(att.absent_count, 0) as absent_count,
            COALESCE(att.late_count, 0) as late_count,
            -- Assignment stats
            COALESCE(assign.total_assignments, 0) as total_assignments,
            COALESCE(assign.submitted_count, 0) as submitted_count,
            COALESCE(assign.graded_count, 0) as graded_count,
            COALESCE(assign.avg_grade, 0) as avg_grade,
            COALESCE(assign.max_points, 100) as max_points
        FROM subjects s
        JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
        JOIN teachers t ON tsm.TeacherID = t.TeacherID
        LEFT JOIN (
            SELECT 
                ar.SubjectID,
                COUNT(*) as total_sessions,
                SUM(CASE WHEN ar.Status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.Status = 'late' THEN 1 ELSE 0 END) as late_count
            FROM attendance_records ar
            WHERE ar.StudentID = ? AND ar.SubjectID = ?
            GROUP BY ar.SubjectID
        ) att ON s.SubjectID = att.SubjectID
        LEFT JOIN (
            SELECT 
                a.SubjectID,
                COUNT(*) as total_assignments,
                SUM(CASE WHEN asub.SubmissionID IS NOT NULL THEN 1 ELSE 0 END) as submitted_count,
                SUM(CASE WHEN asub.Status = 'graded' THEN 1 ELSE 0 END) as graded_count,
                AVG(asub.Grade) as avg_grade,
                MAX(a.MaxPoints) as max_points
            FROM assignments a
            LEFT JOIN assignment_submissions asub ON a.AssignmentID = asub.AssignmentID AND asub.StudentID = ?
            WHERE a.SubjectID = ? AND a.Status IN ('active', 'graded')
            GROUP BY a.SubjectID
        ) assign ON s.SubjectID = assign.SubjectID
        WHERE s.SubjectID = ?
    ");
    
    $subjectQuery->bind_param("iiiii", $studentID, $subjectID, $studentID, $subjectID, $subjectID);
    $subjectQuery->execute();
    $subjectResult = $subjectQuery->get_result();
    $subjectData = $subjectResult->fetch_assoc();
    
    if (!$subjectData) {
        http_response_code(404);
        echo json_encode(['error' => 'Subject not found']);
        exit();
    }
    
    // Get recent attendance history
    $attendanceQuery = $conn->prepare("
        SELECT 
            ar.DateTime,
            ar.Status,
            ar.Method,
            DATE(ar.DateTime) as Date,
            TIME(ar.DateTime) as Time
        FROM attendance_records ar
        WHERE ar.StudentID = ? AND ar.SubjectID = ?
        ORDER BY ar.DateTime DESC
        LIMIT 10
    ");
    
    $attendanceQuery->bind_param("ii", $studentID, $subjectID);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    $attendanceHistory = [];
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceHistory[] = $row;
    }
    
    // Get assignment history
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
    $assignmentHistory = [];
    while ($row = $assignmentResult->fetch_assoc()) {
        $assignmentHistory[] = $row;
    }
    
    // Calculate attendance rate
    $attendanceRate = $subjectData['total_sessions'] > 0 
        ? round(($subjectData['present_count'] / $subjectData['total_sessions']) * 100) 
        : 0;
    
    // Calculate assignment completion rate
    $assignmentCompletion = $subjectData['total_assignments'] > 0 
        ? round(($subjectData['submitted_count'] / $subjectData['total_assignments']) * 100) 
        : 0;
    
    // Prepare response
    $response = [
        'subject' => [
            'code' => $subjectData['SubjectCode'],
            'name' => $subjectData['SubjectName'],
            'teacher' => $subjectData['TeacherName'],
            'credits' => $subjectData['CreditHour']
        ],
        'statistics' => [
            'attendance_rate' => $attendanceRate,
            'assignment_completion' => $assignmentCompletion,
            'average_grade' => $subjectData['avg_grade'] > 0 ? round($subjectData['avg_grade'], 1) : null,
            'max_points' => $subjectData['max_points'],
            'total_sessions' => $subjectData['total_sessions'],
            'present_count' => $subjectData['present_count'],
            'absent_count' => $subjectData['absent_count'],
            'late_count' => $subjectData['late_count'],
            'total_assignments' => $subjectData['total_assignments'],
            'submitted_count' => $subjectData['submitted_count'],
            'graded_count' => $subjectData['graded_count']
        ],
        'attendance_history' => $attendanceHistory,
        'assignment_history' => $assignmentHistory
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?> 