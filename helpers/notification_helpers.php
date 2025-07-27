<?php
// Enhanced Notification Helper Functions for AttendifyPlus

/**
 * Create a notification with enhanced targeting
 * 
 * @param mysqli $conn Database connection
 * @param array $params Notification parameters
 * @return bool Success status
 */
function createEnhancedNotification($conn, $params)
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, role, department_id, subject_id, teacher_id, student_id,
                title, message, icon, type, action_type, action_data, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $actionData = isset($params['action_data']) ? json_encode($params['action_data']) : null;

        $stmt->bind_param(
            "isisssssssss",
            $params['user_id'],
            $params['role'],
            $params['department_id'],
            $params['subject_id'],
            $params['teacher_id'],
            $params['student_id'],
            $params['title'],
            $params['message'],
            $params['icon'],
            $params['type'],
            $params['action_type'],
            $actionData
        );

        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error creating enhanced notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification when student submits assignment
 * 
 * @param mysqli $conn Database connection
 * @param int $studentId Student ID
 * @param int $assignmentId Assignment ID
 * @param int $teacherId Teacher ID
 * @param int $subjectId Subject ID
 * @return bool Success status
 */
function notifyAssignmentSubmission($conn, $studentId, $assignmentId, $teacherId, $subjectId)
{
    // Get student and assignment details
    $studentStmt = $conn->prepare("SELECT s.FullName, s.LoginID FROM students s WHERE s.StudentID = ?");
    $studentStmt->bind_param("i", $studentId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();

    $assignmentStmt = $conn->prepare("SELECT Title FROM assignments WHERE AssignmentID = ?");
    $assignmentStmt->bind_param("i", $assignmentId);
    $assignmentStmt->execute();
    $assignmentTitle = $assignmentStmt->get_result()->fetch_assoc()['Title'];

    // Get teacher's login ID for user_id targeting
    $teacherStmt = $conn->prepare("SELECT t.LoginID FROM teachers t WHERE t.TeacherID = ?");
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacherLoginId = $teacherStmt->get_result()->fetch_assoc()['LoginID'];

    // Notify the specific teacher
    $teacherParams = [
        'user_id' => $teacherLoginId,  // Target specific teacher
        'role' => 'teacher',           // Set role for proper filtering
        'department_id' => null,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,    // Include teacher_id for reference
        'student_id' => $studentId,    // Include student_id for reference
        'title' => 'New Assignment Submission',
        'message' => "Student {$student['FullName']} has submitted assignment: {$assignmentTitle}",
        'icon' => 'clipboard-check',
        'type' => 'info',
        'action_type' => 'assignment_submitted',
        'action_data' => [
            'student_id' => $studentId,
            'assignment_id' => $assignmentId,
            'student_name' => $student['FullName'],
            'assignment_title' => $assignmentTitle
        ]
    ];

    return createEnhancedNotification($conn, $teacherParams);
}

/**
 * Create notification when student views/downloads material
 * 
 * @param mysqli $conn Database connection
 * @param int $studentId Student ID
 * @param int $materialId Material ID
 * @param int $teacherId Teacher ID
 * @param string $actionType 'view' or 'download'
 * @return bool Success status
 */
function notifyMaterialAccess($conn, $studentId, $materialId, $teacherId, $actionType)
{
    // Get student and material details
    $studentStmt = $conn->prepare("SELECT s.FullName, s.LoginID FROM students s WHERE s.StudentID = ?");
    $studentStmt->bind_param("i", $studentId);
    $studentStmt->execute();
    $studentData = $studentStmt->get_result()->fetch_assoc();
    $studentName = $studentData['FullName'];

    $materialStmt = $conn->prepare("SELECT Title FROM materials WHERE MaterialID = ?");
    $materialStmt->bind_param("i", $materialId);
    $materialStmt->execute();
    $materialTitle = $materialStmt->get_result()->fetch_assoc()['Title'];

    // Get teacher's login ID for user_id targeting
    $teacherStmt = $conn->prepare("SELECT t.LoginID FROM teachers t WHERE t.TeacherID = ?");
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacherLoginId = $teacherStmt->get_result()->fetch_assoc()['LoginID'];

    $actionText = $actionType === 'download' ? 'downloaded' : 'viewed';
    $icon = $actionType === 'download' ? 'download' : 'eye';

    // Get material details to find subject and department
    $materialDetailsStmt = $conn->prepare("
        SELECT m.Title, s.SubjectID, s.DepartmentID 
        FROM materials m 
        JOIN subjects s ON m.SubjectID = s.SubjectID 
        WHERE m.MaterialID = ?
    ");
    $materialDetailsStmt->bind_param("i", $materialId);
    $materialDetailsStmt->execute();
    $materialDetails = $materialDetailsStmt->get_result()->fetch_assoc();

    // Notify the specific teacher
    $teacherParams = [
        'user_id' => $teacherLoginId,  // Target specific teacher
        'role' => 'teacher',           // Set role for proper filtering
        'department_id' => $materialDetails['DepartmentID'],
        'subject_id' => $materialDetails['SubjectID'],
        'teacher_id' => $teacherId,    // Include teacher_id for reference
        'student_id' => $studentId,    // Include student_id for reference
        'title' => "Material {$actionText}",
        'message' => "Student {$studentName} has {$actionText} material: {$materialTitle}",
        'icon' => $icon,
        'type' => 'info',
        'action_type' => "material_{$actionType}",
        'action_data' => [
            'student_id' => $studentId,
            'material_id' => $materialId,
            'student_name' => $studentName,
            'material_title' => $materialTitle,
            'action_type' => $actionType
        ]
    ];

    return createEnhancedNotification($conn, $teacherParams);
}

/**
 * Create notification when student scans QR code
 * 
 * @param mysqli $conn Database connection
 * @param int $studentId Student ID
 * @param int $teacherId Teacher ID
 * @param int $subjectId Subject ID
 * @param int $sessionId QR Session ID
 * @return bool Success status
 */
function notifyQRScan($conn, $studentId, $teacherId, $subjectId, $sessionId)
{
    // Get student and subject details
    $studentStmt = $conn->prepare("SELECT s.FullName, s.LoginID FROM students s WHERE s.StudentID = ?");
    $studentStmt->bind_param("i", $studentId);
    $studentStmt->execute();
    $studentData = $studentStmt->get_result()->fetch_assoc();
    $studentName = $studentData['FullName'];

    $subjectStmt = $conn->prepare("SELECT SubjectName FROM subjects WHERE SubjectID = ?");
    $subjectStmt->bind_param("i", $subjectId);
    $subjectStmt->execute();
    $subjectName = $subjectStmt->get_result()->fetch_assoc()['SubjectName'];

    // Get teacher's login ID for user_id targeting
    $teacherStmt = $conn->prepare("SELECT t.LoginID FROM teachers t WHERE t.TeacherID = ?");
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacherLoginId = $teacherStmt->get_result()->fetch_assoc()['LoginID'];

    // Notify the specific teacher
    $teacherParams = [
        'user_id' => $teacherLoginId,  // Target specific teacher
        'role' => 'teacher',           // Set role for proper filtering
        'department_id' => null,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,    // Include teacher_id for reference
        'student_id' => $studentId,    // Include student_id for reference
        'title' => 'QR Code Scanned',
        'message' => "Student {$studentName} has scanned QR code for {$subjectName}",
        'icon' => 'qr-code',
        'type' => 'info',
        'action_type' => 'qr_scanned',
        'action_data' => [
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'session_id' => $sessionId,
            'student_name' => $studentName,
            'subject_name' => $subjectName
        ]
    ];

    return createEnhancedNotification($conn, $teacherParams);
}

/**
 * Create notification when teacher uploads material
 * 
 * @param mysqli $conn Database connection
 * @param int $teacherId Teacher ID
 * @param int $subjectId Subject ID
 * @param int $materialId Material ID
 * @param string $materialTitle Material title
 * @return bool Success status
 */
function notifyMaterialUpload($conn, $teacherId, $subjectId, $materialId, $materialTitle)
{
    // Get teacher and subject details
    $teacherStmt = $conn->prepare("SELECT t.FullName, t.LoginID FROM teachers t WHERE t.TeacherID = ?");
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();

    $subjectStmt = $conn->prepare("SELECT SubjectName FROM subjects WHERE SubjectID = ?");
    $subjectStmt->bind_param("i", $subjectId);
    $subjectStmt->execute();
    $subjectName = $subjectStmt->get_result()->fetch_assoc()['SubjectName'];

    // Get students enrolled in this subject (same department and semester as the subject)
    $studentsStmt = $conn->prepare("
        SELECT DISTINCT s.StudentID, s.DepartmentID, s.LoginID 
        FROM students s
        JOIN subjects sub ON s.DepartmentID = sub.DepartmentID AND s.SemesterID = sub.SemesterID
        WHERE sub.SubjectID = ?
    ");
    $studentsStmt->bind_param("i", $subjectId);
    $studentsStmt->execute();
    $students = $studentsStmt->get_result();

    $success = true;

    // Notify each specific student individually
    while ($student = $students->fetch_assoc()) {
        $studentParams = [
            'user_id' => $student['LoginID'],  // Target specific student
            'role' => 'student',               // Set role for proper filtering
            'department_id' => $student['DepartmentID'],
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,        // Include teacher_id for reference
            'student_id' => $student['StudentID'], // Include student_id for reference
            'title' => 'New Material Available',
            'message' => "{$teacher['FullName']} has uploaded new material for {$subjectName}: {$materialTitle}",
            'icon' => 'upload',
            'type' => 'info',
            'action_type' => 'material_uploaded',
            'action_data' => [
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
                'material_id' => $materialId,
                'teacher_name' => $teacher['FullName'],
                'subject_name' => $subjectName,
                'material_title' => $materialTitle
            ]
        ];

        if (!createEnhancedNotification($conn, $studentParams)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Create notification when teacher creates assignment
 * 
 * @param mysqli $conn Database connection
 * @param int $teacherId Teacher ID
 * @param int $subjectId Subject ID
 * @param int $assignmentId Assignment ID
 * @param string $assignmentTitle Assignment title
 * @return bool Success status
 */
function notifyAssignmentCreated($conn, $teacherId, $subjectId, $assignmentId, $assignmentTitle)
{
    // Get teacher and subject details
    $teacherStmt = $conn->prepare("SELECT t.FullName, t.LoginID FROM teachers t WHERE t.TeacherID = ?");
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();

    $subjectStmt = $conn->prepare("SELECT SubjectName FROM subjects WHERE SubjectID = ?");
    $subjectStmt->bind_param("i", $subjectId);
    $subjectStmt->execute();
    $subjectName = $subjectStmt->get_result()->fetch_assoc()['SubjectName'];

    // Get students enrolled in this subject (same department and semester as the subject)
    $studentsStmt = $conn->prepare("
        SELECT DISTINCT s.StudentID, s.DepartmentID, s.LoginID 
        FROM students s
        JOIN subjects sub ON s.DepartmentID = sub.DepartmentID AND s.SemesterID = sub.SemesterID
        WHERE sub.SubjectID = ?
    ");
    $studentsStmt->bind_param("i", $subjectId);
    $studentsStmt->execute();
    $students = $studentsStmt->get_result();

    $success = true;

    // Notify each specific student individually
    while ($student = $students->fetch_assoc()) {
        $studentParams = [
            'user_id' => $student['LoginID'],  // Target specific student
            'role' => 'student',               // Set role for proper filtering
            'department_id' => $student['DepartmentID'],
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,        // Include teacher_id for reference
            'student_id' => $student['StudentID'], // Include student_id for reference
            'title' => 'New Assignment Available',
            'message' => "{$teacher['FullName']} has created a new assignment for {$subjectName}: {$assignmentTitle}",
            'icon' => 'clipboard-list',
            'type' => 'info',
            'action_type' => 'assignment_created',
            'action_data' => [
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
                'assignment_id' => $assignmentId,
                'teacher_name' => $teacher['FullName'],
                'subject_name' => $subjectName,
                'assignment_title' => $assignmentTitle
            ]
        ];

        if (!createEnhancedNotification($conn, $studentParams)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Create notification when teacher takes attendance
 * 
 * @param mysqli $conn Database connection
 * @param int $teacherId Teacher ID
 * @param int $subjectId Subject ID
 * @param string $method 'manual' or 'qr'
 * @param int $presentCount Number of present students
 * @param int $totalCount Total number of students
 * @return bool Success status
 */
function notifyAttendanceTaken($conn, $teacherId, $subjectId, $method, $presentCount, $totalCount)
{
    // Get teacher and subject details
    $teacherStmt = $conn->prepare("SELECT t.FullName, t.LoginID FROM teachers t WHERE t.TeacherID = ?");
    $teacherStmt->bind_param("i", $teacherId);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();

    $subjectStmt = $conn->prepare("SELECT SubjectName FROM subjects WHERE SubjectID = ?");
    $subjectStmt->bind_param("i", $subjectId);
    $subjectStmt->execute();
    $subjectName = $subjectStmt->get_result()->fetch_assoc()['SubjectName'];

    $methodText = $method === 'qr' ? 'QR code' : 'manual';
    $icon = $method === 'qr' ? 'qr-code' : 'clipboard-check';

    // Get students enrolled in this subject (same department and semester as the subject)
    $studentsStmt = $conn->prepare("
        SELECT DISTINCT s.StudentID, s.DepartmentID, s.LoginID 
        FROM students s
        JOIN subjects sub ON s.DepartmentID = sub.DepartmentID AND s.SemesterID = sub.SemesterID
        WHERE sub.SubjectID = ?
    ");
    $studentsStmt->bind_param("i", $subjectId);
    $studentsStmt->execute();
    $students = $studentsStmt->get_result();

    $success = true;

    // Notify each specific student individually
    while ($student = $students->fetch_assoc()) {
        $studentParams = [
            'user_id' => $student['LoginID'],  // Target specific student
            'role' => 'student',               // Set role for proper filtering
            'department_id' => $student['DepartmentID'],
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,        // Include teacher_id for reference
            'student_id' => $student['StudentID'], // Include student_id for reference
            'title' => 'Attendance Recorded',
            'message' => "{$teacher['FullName']} has taken attendance for {$subjectName} using {$methodText} method. Present: {$presentCount}/{$totalCount}",
            'icon' => $icon,
            'type' => 'info',
            'action_type' => 'attendance_taken',
            'action_data' => [
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
                'method' => $method,
                'teacher_name' => $teacher['FullName'],
                'subject_name' => $subjectName,
                'present_count' => $presentCount,
                'total_count' => $totalCount
            ]
        ];

        if (!createEnhancedNotification($conn, $studentParams)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Create notification for admin actions
 * 
 * @param mysqli $conn Database connection
 * @param int $adminId Admin ID
 * @param string $action Action performed (added, edited, deleted)
 * @param string $targetType Type of target (admin, teacher, subject, student)
 * @param string $targetName Name of the target
 * @return bool Success status
 */
function notifyAdminAction($conn, $adminId, $action, $targetType, $targetName)
{
    // Get admin details
    $adminStmt = $conn->prepare("SELECT a.FullName, a.LoginID FROM admins a WHERE a.AdminID = ?");
    $adminStmt->bind_param("i", $adminId);
    $adminStmt->execute();
    $admin = $adminStmt->get_result()->fetch_assoc();

    if (!$admin) {
        return false;
    }

    $actionText = ucfirst($action);
    $targetTypeText = ucfirst($targetType);
    $icon = 'activity';

    // Set appropriate icon based on action
    switch ($action) {
        case 'added':
            $icon = 'user-plus';
            break;
        case 'edited':
            $icon = 'edit';
            break;
        case 'deleted':
            $icon = 'user-minus';
            break;
        default:
            $icon = 'activity';
    }

    // Set appropriate icon based on target type
    switch ($targetType) {
        case 'admin':
            $icon = 'shield';
            break;
        case 'teacher':
            $icon = 'graduation-cap';
            break;
        case 'subject':
            $icon = 'book-open';
            break;
        case 'student':
            $icon = 'users';
            break;
    }

    $adminParams = [
        'user_id' => $admin['LoginID'],
        'role' => 'admin',
        'department_id' => null,
        'subject_id' => null,
        'teacher_id' => null,
        'student_id' => null,
        'title' => "$targetTypeText $actionText",
        'message' => "Admin {$admin['FullName']} has $action $targetType: $targetName",
        'icon' => $icon,
        'type' => 'info',
        'action_type' => 'admin_' . strtolower($action) . '_' . strtolower($targetType),
        'action_data' => [
            'admin_id' => $adminId,
            'admin_name' => $admin['FullName'],
            'action' => $action,
            'target_type' => $targetType,
            'target_name' => $targetName
        ]
    ];

    return createEnhancedNotification($conn, $adminParams);
}

/**
 * Create admin notification visible to all admins
 * 
 * @param mysqli $conn Database connection
 * @param int $adminId Admin ID who performed the action
 * @param string $action Action performed
 * @param string $targetType Type of target
 * @param string $targetName Name of target
 * @return bool Success status
 */
function notifyAllAdmins($conn, $adminId, $action, $targetType, $targetName)
{
    // Get admin details
    $adminStmt = $conn->prepare("SELECT a.FullName, a.LoginID FROM admins a WHERE a.AdminID = ?");
    $adminStmt->bind_param("i", $adminId);
    $adminStmt->execute();
    $admin = $adminStmt->get_result()->fetch_assoc();

    if (!$admin) {
        return false;
    }

    $actionText = ucfirst($action);
    $targetTypeText = ucfirst($targetType);
    $icon = 'activity';

    // Set appropriate icon based on action
    switch ($action) {
        case 'added':
            $icon = 'user-plus';
            break;
        case 'edited':
            $icon = 'edit';
            break;
        case 'deleted':
            $icon = 'user-minus';
            break;
        case 'activated':
            $icon = 'user-check';
            break;
        case 'deactivated':
            $icon = 'user-x';
            break;
        default:
            $icon = 'activity';
    }

    // Set appropriate icon based on target type
    switch ($targetType) {
        case 'admin':
            $icon = 'shield';
            break;
        case 'teacher':
            $icon = 'graduation-cap';
            break;
        case 'subject':
            $icon = 'book-open';
            break;
        case 'student':
            $icon = 'users';
            break;
    }

    // Get all admin login IDs to notify all admins
    $allAdminsStmt = $conn->prepare("SELECT a.LoginID FROM admins a JOIN login_tbl l ON a.LoginID = l.LoginID WHERE l.Role = 'admin' AND l.Status = 'active'");
    $allAdminsStmt->execute();
    $allAdmins = $allAdminsStmt->get_result();

    $success = true;
    $notificationCount = 0;
    while ($adminRow = $allAdmins->fetch_assoc()) {
        $adminParams = [
            'user_id' => $adminRow['LoginID'],
            'role' => 'admin',
            'department_id' => null,
            'subject_id' => null,
            'teacher_id' => null,
            'student_id' => null,
            'title' => "$targetTypeText $actionText",
            'message' => "Admin {$admin['FullName']} has $action $targetType: $targetName",
            'icon' => $icon,
            'type' => 'info',
            'action_type' => 'admin_' . strtolower($action) . '_' . strtolower($targetType),
            'action_data' => [
                'admin_id' => $adminId,
                'admin_name' => $admin['FullName'],
                'action' => $action,
                'target_type' => $targetType,
                'target_name' => $targetName
            ]
        ];

        if (createEnhancedNotification($conn, $adminParams)) {
            $notificationCount++;
        } else {
            $success = false;
        }
    }

    error_log("notifyAllAdmins: Created $notificationCount notifications for admin action: $action $targetType $targetName");

    $allAdminsStmt->close();
    return $success;
}

/**
 * Get notifications with enhanced filtering
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $role User role
 * @param int $limit Limit of notifications to return
 * @return array Notifications array
 */
function getEnhancedNotifications($conn, $userId, $role, $limit = 10)
{
    try {
        $sql = "
            SELECT n.id, n.title, n.message, n.icon, n.type, n.action_type, n.action_data, n.created_at,
                   IF(r.id IS NULL, 0, 1) AS is_read
            FROM notifications n
            LEFT JOIN notification_reads r
                ON n.id = r.notification_id AND r.user_id = ?
            WHERE n.user_id = ?  -- Only user-specific notifications
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $userId, $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['action_data']) {
                $row['action_data'] = json_decode($row['action_data'], true);
            }
            $notifications[] = $row;
        }

        // Double-check sorting in PHP as well
        usort($notifications, function ($a, $b) {
            $dateA = strtotime($a['created_at']);
            $dateB = strtotime($b['created_at']);
            if ($dateA == $dateB) {
                return $b['id'] - $a['id']; // If same time, higher ID first
            }
            return $dateB - $dateA; // Latest first
        });

        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting enhanced notifications: " . $e->getMessage());
        return [];
    }
}
