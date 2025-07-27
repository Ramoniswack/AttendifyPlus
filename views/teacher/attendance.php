<?php

session_start();
require_once(__DIR__ . '/../../config/db_config.php');
require_once(__DIR__ . '/../../helpers/notification_helpers.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher info
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    header("Location: ../logout.php");
    exit();
}

$teacherID = $teacherRow['TeacherID'];

// Get department
$deptStmt = $conn->prepare("
    SELECT DISTINCT d.DepartmentID, d.DepartmentName
    FROM departments d
    JOIN subjects s ON s.DepartmentID = d.DepartmentID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
    LIMIT 1
");
$deptStmt->bind_param("i", $teacherID);
$deptStmt->execute();
$teacherDept = $deptStmt->get_result()->fetch_assoc();

if (!$teacherDept) {
    die("No department found for this teacher.");
}

// Get semesters
$semQuery = $conn->prepare("
    SELECT DISTINCT sem.SemesterID, sem.SemesterNumber
    FROM semesters sem
    JOIN subjects s ON s.SemesterID = sem.SemesterID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
    ORDER BY sem.SemesterNumber
");
$semQuery->bind_param("i", $teacherID);
$semQuery->execute();
$semResult = $semQuery->get_result();

$selectedSemesterID = $_POST['semester'] ?? $_GET['semester'] ?? null;
$selectedSubjectID = $_POST['subject'] ?? $_GET['subject'] ?? null;
$date = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');
$successMsg = isset($_GET['success']) ? ($_GET['success'] === 'updated' ? "Attendance updated successfully." : "Attendance saved successfully.") : "";
$cancelledMsg = isset($_GET['cancelled']) ? "Attendance cancelled successfully." : "";
$errorMsg = $_GET['error'] ?? "";

// Handle Enhanced Projector QR Code Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    header('Content-Type: application/json');

    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    try {
        // Load Composer autoloader for enhanced QR generation
        require_once(__DIR__ . '/../../vendor/autoload.php');

        $qrToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes')); // 5 minutes for QR attendance

        // Deactivate old QR sessions for this teacher/subject/date
        $deactivateStmt = $conn->prepare("UPDATE qr_attendance_sessions SET IsActive = 0 WHERE TeacherID = ? AND SubjectID = ? AND Date = ?");
        $deactivateStmt->bind_param("iis", $teacherID, $selectedSubjectID, $date);
        $deactivateStmt->execute();

        // Create new QR session
        $qrStmt = $conn->prepare("INSERT INTO qr_attendance_sessions (TeacherID, SubjectID, Date, QRToken, ExpiresAt) VALUES (?, ?, ?, ?, ?)");
        $qrStmt->bind_param("iisss", $teacherID, $selectedSubjectID, $date, $qrToken, $expiresAt);

        if ($qrStmt->execute()) {
            // Generate the scan URL for the QR code - FIX: Point to student scan page
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $currentDir = dirname($_SERVER['REQUEST_URI']);
            $scanUrl = $baseUrl . $currentDir . '/../student/scan_qr.php?token=' . $qrToken;

            echo json_encode([
                'success' => true,
                'qr_token' => $qrToken,
                'expires_at' => $expiresAt,
                'scan_url' => $scanUrl,
                'mode' => 'projector', // Always use projector mode (400px, 30px margin)
                'message' => 'Projector QR Code generated successfully - 400px size, optimized for classroom projection'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create QR session']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle cancel attendance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_attendance'])) {
    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        header("Location: attendance.php?error=Missing required fields.");
        exit();
    }

    // Clear pending QR scans from database
    $clearPendingStmt = $conn->prepare("DELETE FROM qr_attendance_pending WHERE TeacherID = ? AND SubjectID = ? AND DATE(CreatedAt) = ?");
    $clearPendingStmt->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $clearPendingStmt->execute();

    // Deactivate any active QR sessions
    $deactivateStmt = $conn->prepare("UPDATE qr_attendance_sessions SET IsActive = 0 WHERE TeacherID = ? AND SubjectID = ? AND Date = ?");
    $deactivateStmt->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $deactivateStmt->execute();

    header("Location: attendance.php?cancelled=1&semester=$selectedSemesterID&subject=$selectedSubjectID&date=$date");
    exit();
}

// Handle form submission for manual attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        header("Location: attendance.php?error=Missing required fields.");
        exit();
    }

    $updateMode = isset($_POST['update_mode']) && $_POST['update_mode'] == '1';

    // Check if attendance already exists for this date
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
    $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $attendanceExists = $result->fetch_assoc()['count'] > 0;

    // Get QR scanned students from database (pending scans) to determine correct method
    $qrScannedStudents = [];

    // Only check for pending QR scans if not in update mode
    if (!$updateMode) {
        $pendingQrQuery = $conn->prepare("
            SELECT qap.StudentID, qap.CreatedAt, qap.Status
            FROM qr_attendance_pending qap
            WHERE qap.TeacherID = ? AND qap.SubjectID = ? AND DATE(qap.CreatedAt) = ?
        ");
        $pendingQrQuery->bind_param("iis", $teacherID, $selectedSubjectID, $date);
        $pendingQrQuery->execute();
        $pendingResult = $pendingQrQuery->get_result();
        while ($pendingRow = $pendingResult->fetch_assoc()) {
            $qrScannedStudents[$pendingRow['StudentID']] = [
                'student_id' => $pendingRow['StudentID'],
                'scanned_at' => $pendingRow['CreatedAt'],
                'status' => $pendingRow['Status']
            ];
        }
    }

    $conn->begin_transaction();
    try {
        if ($attendanceExists || $updateMode) {
            // Update existing attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $studentID = intval($studentID);

                $studentCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE StudentID = ? AND SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
                $studentCheckStmt->bind_param("iisi", $studentID, $selectedSubjectID, $date, $teacherID);
                $studentCheckStmt->execute();
                $studentResult = $studentCheckStmt->get_result();
                $studentRecordExists = $studentResult->fetch_assoc()['count'] > 0;

                // Determine method and datetime
                $method = 'manual';
                $dateTime = date('Y-m-d H:i:s');

                if ($updateMode) {
                    // In update mode, preserve existing method and datetime
                    $existingRecordQuery = $conn->prepare("
                        SELECT Method, DateTime 
                        FROM attendance_records 
                        WHERE StudentID = ? AND SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?
                    ");
                    $existingRecordQuery->bind_param("iisi", $studentID, $selectedSubjectID, $date, $teacherID);
                    $existingRecordQuery->execute();
                    $existingRecord = $existingRecordQuery->get_result()->fetch_assoc();

                    if ($existingRecord) {
                        // Preserve original method and datetime for updates
                        $method = $existingRecord['Method'];
                        $dateTime = $existingRecord['DateTime'];
                    }
                } else {
                    // Normal mode - check for QR scans
                    if (isset($qrScannedStudents[$studentID])) {
                        if ($status === 'present') {
                            // Teacher left QR-scanned student as Present - use QR method and original scan time
                            $method = 'qr';
                            $dateTime = $qrScannedStudents[$studentID]['scanned_at'];
                        }
                        // If teacher changed to absent/late, method stays 'manual'
                    }
                }

                if ($studentRecordExists) {
                    // Update existing record
                    $updateStmt = $conn->prepare("
                        UPDATE attendance_records 
                        SET Status = ?, DateTime = ?, Method = ?
                        WHERE StudentID = ? AND TeacherID = ? AND SubjectID = ? AND DATE(DateTime) = ?
                    ");
                    $updateStmt->bind_param("sssiiss", $status, $dateTime, $method, $studentID, $teacherID, $selectedSubjectID, $date);
                    $updateStmt->execute();
                } else {
                    // Insert new record
                    $insertStmt = $conn->prepare("
                        INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status, Method) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->bind_param("iiisss", $studentID, $teacherID, $selectedSubjectID, $dateTime, $status, $method);
                    $insertStmt->execute();
                }
            }
        } else {
            // Insert new attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $studentID = intval($studentID);

                // Determine method and datetime
                $method = 'manual';
                $dateTime = date('Y-m-d H:i:s');

                if (isset($qrScannedStudents[$studentID])) {
                    if ($status === 'present') {
                        // Teacher left QR-scanned student as Present - use QR method and original scan time
                        $method = 'qr';
                        $dateTime = $qrScannedStudents[$studentID]['scanned_at'];
                    }
                    // If teacher changed to absent/late, method stays 'manual'
                }

                $insertStmt = $conn->prepare("
                    INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status, Method) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param("iiisss", $studentID, $teacherID, $selectedSubjectID, $dateTime, $status, $method);
                $insertStmt->execute();
            }
        }

        // Process pending QR scans (delete them after processing) - only for initial save
        if (!$updateMode) {
            $processPendingStmt = $conn->prepare("
                DELETE FROM qr_attendance_pending 
                WHERE TeacherID = ? AND SubjectID = ? AND DATE(CreatedAt) = ?
            ");
            $processPendingStmt->bind_param("iis", $teacherID, $selectedSubjectID, $date);
            $processPendingStmt->execute();

            // Deactivate QR sessions after saving attendance - only for initial save
            $deactivateQRStmt = $conn->prepare("
                UPDATE qr_attendance_sessions 
                SET IsActive = 0 
                WHERE TeacherID = ? AND SubjectID = ? AND Date = ?
            ");
            $deactivateQRStmt->bind_param("iis", $teacherID, $selectedSubjectID, $date);
            $deactivateQRStmt->execute();
        }

        $conn->commit();

        // Count attendance statistics for notification
        $presentCount = 0;
        $totalCount = 0;
        foreach ($_POST['attendance'] as $studentID => $status) {
            $totalCount++;
            if ($status === 'present') {
                $presentCount++;
            }
        }

        // Create notification for students about attendance being taken
        if (!$updateMode) { // Only notify on initial save, not updates
            notifyAttendanceTaken($conn, $teacherID, $selectedSubjectID, 'manual', $presentCount, $totalCount);
        }

        if ($updateMode) {
            header("Location: attendance.php?success=updated&semester=$selectedSemesterID&subject=$selectedSubjectID&date=$date");
        } else {
            header("Location: attendance.php?success=1&semester=$selectedSemesterID&subject=$selectedSubjectID&date=$date");
        }
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: attendance.php?error=Failed to save attendance: " . urlencode($e->getMessage()));
        exit();
    }
}

// Get subjects for selected semester
$subjects = [];
if ($selectedSemesterID) {
    $subjectQuery = $conn->prepare("
        SELECT s.SubjectID, s.SubjectName, s.SubjectCode
        FROM subjects s
        JOIN teacher_subject_map ts ON s.SubjectID = ts.SubjectID
        WHERE ts.TeacherID = ? AND s.SemesterID = ?
        ORDER BY s.SubjectName
    ");
    $subjectQuery->bind_param("ii", $teacherID, $selectedSemesterID);
    $subjectQuery->execute();
    $subjectResult = $subjectQuery->get_result();
    while ($row = $subjectResult->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Get students for selected semester and subject
$students = [];
if ($selectedSemesterID && $selectedSubjectID) {
    // First, get all students for this semester and department
    $studentQuery = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.DeviceRegistered, s.ProgramCode
        FROM students s
        WHERE s.SemesterID = ? AND s.DepartmentID = ?
        ORDER BY s.FullName
    ");
    $studentQuery->bind_param("ii", $selectedSemesterID, $teacherDept['DepartmentID']);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();

    // Get all students first
    $allStudents = [];
    while ($row = $studentResult->fetch_assoc()) {
        $allStudents[] = $row;
    }

    // Now get attendance data separately for better debugging
    $attendanceQuery = $conn->prepare("
        SELECT ar.StudentID, ar.Status, ar.Method, ar.DateTime
        FROM attendance_records ar
        WHERE ar.SubjectID = ? AND ar.TeacherID = ? AND DATE(ar.DateTime) = ?
    ");
    $attendanceQuery->bind_param("iis", $selectedSubjectID, $teacherID, $date);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();

    // Create attendance lookup
    $attendanceLookup = [];
    while ($attRow = $attendanceResult->fetch_assoc()) {
        $attendanceLookup[$attRow['StudentID']] = $attRow;
    }

    // Debug: Log attendance data found
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        error_log("DEBUG: Found " . count($attendanceLookup) . " attendance records for date: $date");
        foreach ($attendanceLookup as $studentId => $attData) {
            error_log("DEBUG: Student $studentId - Status: {$attData['Status']}, Method: {$attData['Method']}");
        }
    }

    // Get pending QR scans from database
    $qrScannedStudents = [];
    $pendingQrQuery = $conn->prepare("
        SELECT qap.StudentID, qap.CreatedAt
        FROM qr_attendance_pending qap
        WHERE qap.TeacherID = ? AND qap.SubjectID = ? AND DATE(qap.CreatedAt) = ?
    ");
    $pendingQrQuery->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $pendingQrQuery->execute();
    $pendingResult = $pendingQrQuery->get_result();
    while ($pendingRow = $pendingResult->fetch_assoc()) {
        $qrScannedStudents[$pendingRow['StudentID']] = $pendingRow['CreatedAt'];
    }

    // Combine student data with attendance data
    foreach ($allStudents as $student) {
        $studentId = $student['StudentID'];

        // Get attendance data for this student
        $attendanceData = $attendanceLookup[$studentId] ?? null;

        $student['AttendanceStatus'] = $attendanceData ? $attendanceData['Status'] : null;
        $student['AttendanceMethod'] = $attendanceData ? $attendanceData['Method'] : null;
        $student['AttendanceTime'] = $attendanceData ? $attendanceData['DateTime'] : null;
        $student['QRScannedAt'] = $qrScannedStudents[$studentId] ?? null;

        // Debug: Log each student's final data
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            error_log("DEBUG: Final student data - {$student['FullName']} (ID: $studentId): Status={$student['AttendanceStatus']}, Method={$student['AttendanceMethod']}");
        }

        $students[] = $student;
    }
}

// Debug: Log student data to console to verify attendance status is loaded
if ($selectedSemesterID && $selectedSubjectID && !empty($students)) {
    // Debug output removed for production
}

// Enhanced debug: Direct database check for attendance records
if (isset($_GET['debug']) && $_GET['debug'] === '1' && $selectedSubjectID && $selectedSemesterID && $date) {
    // Check 1: Direct attendance records query
    $debugQuery = $conn->prepare("
        SELECT ar.StudentID, s.FullName, ar.Status, ar.Method, ar.DateTime
        FROM attendance_records ar
        JOIN students s ON ar.StudentID = s.StudentID
        WHERE ar.SubjectID = ? AND ar.TeacherID = ? AND DATE(ar.DateTime) = ?
        ORDER BY s.FullName
    ");
    $debugQuery->bind_param("iis", $selectedSubjectID, $teacherID, $date);
    $debugQuery->execute();
    $debugResult = $debugQuery->get_result();

    $debugAttendance = [];
    while ($debugRow = $debugResult->fetch_assoc()) {
        $debugAttendance[] = $debugRow;
    }

    // Check 2: All attendance records for this teacher/subject (regardless of date)
    $debugQuery2 = $conn->prepare("
        SELECT ar.StudentID, s.FullName, ar.Status, ar.Method, ar.DateTime, DATE(ar.DateTime) as DateOnly
        FROM attendance_records ar
        JOIN students s ON ar.StudentID = s.StudentID
        WHERE ar.SubjectID = ? AND ar.TeacherID = ?
        ORDER BY ar.DateTime DESC
        LIMIT 10
    ");
    $debugQuery2->bind_param("ii", $selectedSubjectID, $teacherID);
    $debugQuery2->execute();
    $debugResult2 = $debugQuery2->get_result();

    $debugAllAttendance = [];
    while ($debugRow2 = $debugResult2->fetch_assoc()) {
        $debugAllAttendance[] = $debugRow2;
    }

    // Check 3: Students in this semester/department
    $debugQuery3 = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.SemesterID, s.DepartmentID
        FROM students s
        WHERE s.SemesterID = ? AND s.DepartmentID = ?
        ORDER BY s.FullName
    ");
    $debugQuery3->bind_param("ii", $selectedSemesterID, $teacherDept['DepartmentID']);
    $debugQuery3->execute();
    $debugResult3 = $debugQuery3->get_result();

    $debugStudents = [];
    while ($debugRow3 = $debugResult3->fetch_assoc()) {
        $debugStudents[] = $debugRow3;
    }

    // Store debug data for display
    $debugAttendanceData = $debugAttendance;
    $debugAllAttendanceData = $debugAllAttendance;
    $debugStudentsData = $debugStudents;
}

// Get current QR session if active
$currentQRSession = null;
if ($selectedSubjectID && $selectedSemesterID && $date) {
    $qrSessionQuery = $conn->prepare("
        SELECT QRToken, ExpiresAt, CreatedAt 
        FROM qr_attendance_sessions 
        WHERE TeacherID = ? AND SubjectID = ? AND Date = ? AND IsActive = 1
        ORDER BY CreatedAt DESC 
        LIMIT 1
    ");
    $qrSessionQuery->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $qrSessionQuery->execute();
    $qrResult = $qrSessionQuery->get_result();
    $currentQRSession = $qrResult->fetch_assoc();
}

// Check if attendance is in progress (pending QR scans exist but not saved yet)
$attendanceInProgress = false;
if ($selectedSubjectID && $selectedSemesterID && $date) {
    $pendingCountQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM qr_attendance_pending 
        WHERE TeacherID = ? AND SubjectID = ? AND DATE(CreatedAt) = ?
    ");
    $pendingCountQuery->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $pendingCountQuery->execute();
    $pendingResult = $pendingCountQuery->get_result();
    $attendanceInProgress = $pendingResult->fetch_assoc()['count'] > 0;
}

// Count students with registered devices
$registeredDevicesCount = 0;
foreach ($students as $student) {
    if ($student['DeviceRegistered']) {
        $registeredDevicesCount++;
    }
}

// Get attendance stats
$attendanceStats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'qrMarked' => 0
];

foreach ($students as $student) {
    if ($student['AttendanceStatus']) {
        $attendanceStats[$student['AttendanceStatus']]++;
        if ($student['AttendanceMethod'] === 'qr') {
            $attendanceStats['qrMarked']++;
        }
    }
}

// Debug: Log attendance stats
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    error_log("DEBUG: Attendance stats calculated - Present: {$attendanceStats['present']}, Absent: {$attendanceStats['absent']}, Late: {$attendanceStats['late']}, QR: {$attendanceStats['qrMarked']}");
}

// Simple direct database test to verify attendance data
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    // Test 1: Check if the specific attendance record exists
    $testQuery = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM attendance_records 
        WHERE SubjectID = ? AND TeacherID = ? AND DATE(DateTime) = ?
    ");
    $testQuery->bind_param("iis", $selectedSubjectID, $teacherID, $date);
    $testQuery->execute();
    $testResult = $testQuery->get_result();
    $testCount = $testResult->fetch_assoc()['count'];

    // Test 2: Get the actual record details
    $testQuery2 = $conn->prepare("
        SELECT ar.*, s.FullName 
        FROM attendance_records ar
        JOIN students s ON ar.StudentID = s.StudentID
        WHERE ar.SubjectID = ? AND ar.TeacherID = ? AND DATE(ar.DateTime) = ?
        LIMIT 5
    ");
    $testQuery2->bind_param("iis", $selectedSubjectID, $teacherID, $date);
    $testQuery2->execute();
    $testResult2 = $testQuery2->get_result();

    $testRecords = [];
    while ($testRow = $testResult2->fetch_assoc()) {
        $testRecords[] = $testRow;
    }

    // Test 3: Check all attendance records for this teacher/subject (any date)
    $testQuery3 = $conn->prepare("
        SELECT ar.*, s.FullName, DATE(ar.DateTime) as DateOnly
        FROM attendance_records ar
        JOIN students s ON ar.StudentID = s.StudentID
        WHERE ar.SubjectID = ? AND ar.TeacherID = ?
        ORDER BY ar.DateTime DESC
        LIMIT 10
    ");
    $testQuery3->bind_param("ii", $selectedSubjectID, $teacherID);
    $testQuery3->execute();
    $testResult3 = $testQuery3->get_result();

    $testAllRecords = [];
    while ($testRow3 = $testResult3->fetch_assoc()) {
        $testAllRecords[] = $testRow3;
    }

    // Store test data
    $debugTestCount = $testCount;
    $debugTestRecords = $testRecords;
    $debugTestAllRecords = $testAllRecords;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link rel="stylesheet" href="../../assets/css/attendance.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/sidebar_teacher.js" defer></script>
    <script src="../../assets/js/navbar_teacher.js" defer></script>
</head>

<body>
    <!-- Include sidebar and navbar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="calendar-check"></i>
                    Attendance Management
                </h2>
                <p class="text-muted mb-0">Manage student attendance manually or with QR codes</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="attendance_report.php" class="btn btn-outline-primary">
                    <i data-lucide="file-text"></i> View Reports
                </a>
                <a href="dashboard_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle" class="me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($cancelledMsg): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i data-lucide="info" class="me-2"></i>
                <?= htmlspecialchars($cancelledMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle" class="me-2"></i>
                <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <?php if ($attendanceInProgress): ?>
                    <div class="alert alert-warning mb-3">
                        <i data-lucide="clock"></i>
                        <strong>Attendance In Progress</strong> - Students have scanned QR codes. Please save or cancel attendance before changing class/subject/date.
                    </div>
                <?php endif; ?>
                <h5 class="card-title">
                    <i data-lucide="filter"></i>
                    Select Class & Date
                </h5>
                <form method="POST" id="selectionForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">
                            <i data-lucide="calendar"></i> Date
                        </label>
                        <input type="date" name="date" class="form-control" value="<?= $date ?>"
                            max="<?= date('Y-m-d') ?>" onchange="handleDateChange()"
                            <?= $attendanceInProgress ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i data-lucide="calendar"></i> Semester
                        </label>
                        <select name="semester" class="form-select" onchange="handleSemesterChange()"
                            <?= $attendanceInProgress ? 'disabled' : '' ?>>
                            <option value="">Select Semester</option>
                            <?php
                            $semResult->data_seek(0);
                            while ($sem = $semResult->fetch_assoc()): ?>
                                <option value="<?= $sem['SemesterID'] ?>" <?= $selectedSemesterID == $sem['SemesterID'] ? 'selected' : '' ?>>
                                    Semester <?= $sem['SemesterNumber'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            <i data-lucide="book-open"></i> Subject
                        </label>
                        <select name="subject" class="form-select" onchange="handleSubjectChange()"
                            <?= (empty($subjects) || $attendanceInProgress) ? 'disabled' : '' ?>>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['SubjectID'] ?>" <?= $selectedSubjectID == $subject['SubjectID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100"
                            <?= $attendanceInProgress ? 'disabled' : '' ?>>
                            <i data-lucide="search"></i> Load Class
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedSemesterID && $selectedSubjectID): ?>
            <div class="row g-4 mb-4">
                <!-- Manual Attendance (Left Column) -->
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i data-lucide="clipboard-check"></i>
                                Manual Attendance
                            </h5>
                            <div class="input-group" style="max-width: 250px;">
                                <span class="input-group-text">
                                    <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                                </span>
                                <input type="text" id="studentSearch" class="form-control" placeholder="Search...">
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if (!empty($students)): ?>
                                <!-- Progress Bar -->
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?= count($students) > 0 ? (($attendanceStats['present'] + $attendanceStats['absent'] + $attendanceStats['late']) / count($students) * 100) : 0 ?>%;">
                                    </div>
                                </div>

                                <!-- Quick Stats -->
                                <div class="row mb-3">
                                    <div class="col-3">
                                        <div class="text-center">
                                            <div class="fw-bold text-success" id="presentCount"><?= $attendanceStats['present'] ?></div>
                                            <small class="text-muted">Present</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-center">
                                            <div class="fw-bold text-danger" id="absentCount"><?= $attendanceStats['absent'] ?></div>
                                            <small class="text-muted">Absent</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-center">
                                            <div class="fw-bold text-warning" id="lateCount"><?= $attendanceStats['late'] ?></div>
                                            <small class="text-muted">Late</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-center">
                                            <div class="fw-bold text-primary"><?= count($students) - ($attendanceStats['present'] + $attendanceStats['absent'] + $attendanceStats['late']) ?></div>
                                            <small class="text-muted">Pending</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bulk Actions -->
                                <div class="btn-group w-100 mb-3" role="group">
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="markAllPresent()">
                                        All Present
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAllAbsent()">
                                        All Absent
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="markAllLate()">
                                        All Late
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()">
                                        Reset
                                    </button>
                                </div>

                                <form method="POST" id="attendanceForm">
                                    <input type="hidden" name="semester" value="<?= $selectedSemesterID ?>">
                                    <input type="hidden" name="subject" value="<?= $selectedSubjectID ?>">
                                    <input type="hidden" name="date" value="<?= $date ?>">

                                    <!-- Student List -->
                                    <div class="students-list" style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($students as $student): ?>
                                            <div class="student-item student-row mb-2 p-2 border rounded <?= $student['AttendanceStatus'] ? 'border-' . ($student['AttendanceStatus'] === 'present' ? 'success' : ($student['AttendanceStatus'] === 'absent' ? 'danger' : 'warning')) : 'border-light' ?>" data-student-id="<?= $student['StudentID'] ?>" <?php if ($student['QRScannedAt'] && !$student['AttendanceStatus']): ?> style="background-color: rgba(255, 193, 7, 0.05);" <?php endif; ?>>

                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="student-info">
                                                        <div class="fw-medium student-name"><?= htmlspecialchars($student['FullName']) ?>
                                                            <?php if ($student['DeviceRegistered']): ?>
                                                                <i data-lucide="smartphone" class="text-primary ms-1" style="width: 12px; height: 12px;"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">ID: <?= $student['StudentID'] ?></small>

                                                        <?php if ($student['AttendanceStatus']): ?>
                                                            <!-- Show existing attendance with method indicator -->
                                                            <?php if ($student['AttendanceMethod'] === 'qr'): ?>
                                                                <span class="badge bg-info ms-2">
                                                                    <i data-lucide="qr-code" style="width: 10px; height: 10px;"></i>
                                                                    QR Marked
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary ms-2">
                                                                    <i data-lucide="edit" style="width: 10px; height: 10px;"></i>
                                                                    Manual
                                                                </span>
                                                            <?php endif; ?>
                                                            <small class="text-muted d-block">
                                                                Time: <?= date('H:i:s', strtotime($student['AttendanceTime'])) ?>
                                                            </small>
                                                        <?php elseif ($student['QRScannedAt']): ?>
                                                            <!-- Show pending QR scan -->
                                                            <span class="badge bg-warning text-dark ms-2">
                                                                <i data-lucide="clock" style="width: 10px; height: 10px;"></i>
                                                                QR Scanned - Pending Save
                                                            </span>
                                                            <small class="text-muted d-block">Scanned: <?= date('H:i:s', strtotime($student['QRScannedAt'])) ?></small>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Radio Buttons -->
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php
                                                        // Determine if student has QR scanned (pending) or already marked present
                                                        $isQRScanned = !empty($student['QRScannedAt']) && empty($student['AttendanceStatus']);
                                                        $isPresentSelected = $student['AttendanceStatus'] === 'present' || $isQRScanned;
                                                        // Allow editing for all attendance types
                                                        $isDisabled = false;

                                                        // Debug: Log radio button state
                                                        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                                                            echo "<!-- DEBUG: Student {$student['FullName']} - Status: {$student['AttendanceStatus']}, Method: {$student['AttendanceMethod']}, Present Selected: " . ($isPresentSelected ? 'Yes' : 'No') . " -->";
                                                        }
                                                        ?>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['StudentID'] ?>]"
                                                            id="p<?= $student['StudentID'] ?>" value="present"
                                                            <?= $isPresentSelected ? 'checked' : '' ?>
                                                            <?= $isDisabled ? 'disabled' : '' ?>>
                                                        <label class="btn <?= $student['AttendanceStatus'] === 'present' ? 'btn-success' : ($isQRScanned ? 'btn-warning' : 'btn-outline-success') ?>" for="p<?= $student['StudentID'] ?>">
                                                            <?php if ($student['AttendanceMethod'] === 'qr' && $student['AttendanceStatus'] === 'present'): ?>
                                                                Present (QR)
                                                            <?php elseif ($isQRScanned): ?>
                                                                Present (QR)
                                                            <?php else: ?>
                                                                Present
                                                            <?php endif; ?>
                                                        </label>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['StudentID'] ?>]"
                                                            id="a<?= $student['StudentID'] ?>" value="absent"
                                                            <?= $student['AttendanceStatus'] === 'absent' ? 'checked' : '' ?>
                                                            <?= $isDisabled ? 'disabled' : '' ?>>
                                                        <label class="btn <?= $student['AttendanceStatus'] === 'absent' ? 'btn-danger' : 'btn-outline-danger' ?>" for="a<?= $student['StudentID'] ?>">Absent</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['StudentID'] ?>]"
                                                            id="l<?= $student['StudentID'] ?>" value="late"
                                                            <?= $student['AttendanceStatus'] === 'late' ? 'checked' : '' ?>
                                                            <?= $isDisabled ? 'disabled' : '' ?>>
                                                        <label class="btn <?= $student['AttendanceStatus'] === 'late' ? 'btn-warning' : 'btn-outline-warning' ?>" for="l<?= $student['StudentID'] ?>">Late</label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div> <!-- Submit Button -->
                                    <div class="text-center mt-3">
                                        <?php
                                        // Check if attendance already exists for this date/subject/teacher (any student has attendance record)
                                        $attendanceExists = false;
                                        $totalStudentsWithAttendance = $attendanceStats['present'] + $attendanceStats['absent'] + $attendanceStats['late'];

                                        // Also check database directly for more reliable detection
                                        $dbCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
                                        $dbCheckStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
                                        $dbCheckStmt->execute();
                                        $dbResult = $dbCheckStmt->get_result();
                                        $dbAttendanceExists = $dbResult->fetch_assoc()['count'] > 0;

                                        if ($totalStudentsWithAttendance > 0 || $dbAttendanceExists) {
                                            $attendanceExists = true;
                                        }

                                        // If attendance exists, this is an update scenario
                                        $isUpdateMode = $attendanceExists;

                                        // Debug output for troubleshooting
                                        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                                            echo "<div class='alert alert-info small'>";
                                            echo "<h6><i data-lucide='bug'></i> Debug Information</h6>";
                                            echo "<strong>Query Parameters:</strong><br>";
                                            echo "Date: $date<br>";
                                            echo "Subject ID: $selectedSubjectID<br>";
                                            echo "Teacher ID: $teacherID<br>";
                                            echo "Semester ID: $selectedSemesterID<br>";
                                            echo "Department ID: {$teacherDept['DepartmentID']}<br><br>";

                                            echo "<strong>Database Check:</strong><br>";
                                            echo "Total Students with Attendance: $totalStudentsWithAttendance<br>";
                                            echo "DB Attendance Exists: " . ($dbAttendanceExists ? 'Yes' : 'No') . "<br>";
                                            echo "Update Mode: " . ($isUpdateMode ? 'Yes' : 'No') . "<br><br>";

                                            if (isset($debugAttendanceData)) {
                                                echo "<strong>Direct Database Query Results (for this date):</strong><br>";
                                                if (empty($debugAttendanceData)) {
                                                    echo "❌ No attendance records found in database for this date/subject/teacher<br>";
                                                } else {
                                                    echo "✅ Found " . count($debugAttendanceData) . " attendance records:<br>";
                                                    foreach ($debugAttendanceData as $record) {
                                                        echo "- {$record['FullName']}: {$record['Status']} ({$record['Method']}) at {$record['DateTime']}<br>";
                                                    }
                                                }
                                                echo "<br>";
                                            }

                                            if (isset($debugAllAttendanceData)) {
                                                echo "<strong>All Attendance Records (last 10, any date):</strong><br>";
                                                if (empty($debugAllAttendanceData)) {
                                                    echo "❌ No attendance records found for this teacher/subject<br>";
                                                } else {
                                                    echo "✅ Found " . count($debugAllAttendanceData) . " attendance records:<br>";
                                                    foreach ($debugAllAttendanceData as $record) {
                                                        echo "- {$record['FullName']}: {$record['Status']} ({$record['Method']}) on {$record['DateOnly']} at {$record['DateTime']}<br>";
                                                    }
                                                }
                                                echo "<br>";
                                            }

                                            if (isset($debugStudentsData)) {
                                                echo "<strong>Students in this Semester/Department:</strong><br>";
                                                if (empty($debugStudentsData)) {
                                                    echo "❌ No students found in this semester/department<br>";
                                                } else {
                                                    echo "✅ Found " . count($debugStudentsData) . " students:<br>";
                                                    foreach ($debugStudentsData as $student) {
                                                        echo "- {$student['FullName']} (ID: {$student['StudentID']})<br>";
                                                    }
                                                }
                                                echo "<br>";
                                            }

                                            if (isset($debugTestCount)) {
                                                echo "<strong>Direct Database Test:</strong><br>";
                                                echo "Total attendance records for this date/subject/teacher: $debugTestCount<br>";

                                                if (isset($debugTestRecords) && !empty($debugTestRecords)) {
                                                    echo "✅ Found attendance records for this date:<br>";
                                                    foreach ($debugTestRecords as $record) {
                                                        echo "- Student: {$record['FullName']} (ID: {$record['StudentID']})<br>";
                                                        echo "  Status: {$record['Status']}, Method: {$record['Method']}<br>";
                                                        echo "  DateTime: {$record['DateTime']}<br>";
                                                        echo "  SubjectID: {$record['SubjectID']}, TeacherID: {$record['TeacherID']}<br><br>";
                                                    }
                                                } else {
                                                    echo "❌ No attendance records found for this specific date<br>";
                                                }

                                                if (isset($debugTestAllRecords) && !empty($debugTestAllRecords)) {
                                                    echo "<strong>All Attendance Records (any date):</strong><br>";
                                                    foreach ($debugTestAllRecords as $record) {
                                                        echo "- {$record['FullName']}: {$record['Status']} ({$record['Method']}) on {$record['DateOnly']}<br>";
                                                    }
                                                }
                                                echo "<br>";
                                            }

                                            echo "<strong>Form Data (Students Array):</strong><br>";
                                            foreach ($students as $student) {
                                                echo "- {$student['FullName']} (ID: {$student['StudentID']}): Status={$student['AttendanceStatus']}, Method={$student['AttendanceMethod']}, Time={$student['AttendanceTime']}<br>";
                                            }
                                            echo "</div>";
                                        }
                                        ?>

                                        <?php if ($isUpdateMode): ?>
                                            <!-- Update Mode - Attendance Already Taken -->
                                            <div class="alert alert-info text-center mb-3">
                                                <i data-lucide="edit" style="width: 24px; height: 24px;" class="mb-2 text-info"></i>
                                                <h6 class="mb-1">Attendance Already Taken</h6>
                                                <p class="mb-0 small">You can update the attendance records for this class.</p>
                                                <small class="text-muted">
                                                    Present: <?= $attendanceStats['present'] ?> |
                                                    Absent: <?= $attendanceStats['absent'] ?> |
                                                    Late: <?= $attendanceStats['late'] ?>
                                                    <?php if ($attendanceStats['qrMarked'] > 0): ?>
                                                        | QR Marked: <?= $attendanceStats['qrMarked'] ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-center">
                                                <button type="submit" class="btn btn-warning" id="updateBtn">
                                                    <i data-lucide="edit"></i>
                                                    Update Attendance
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='attendance.php'">
                                                    <i data-lucide="refresh-cw"></i>
                                                    New Session
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-sm" onclick="window.location.href='attendance.php?debug=1&semester=<?= $selectedSemesterID ?>&subject=<?= $selectedSubjectID ?>&date=<?= $date ?>'" title="Debug Info">
                                                    <i data-lucide="bug"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="update_mode" value="1">

                                            <!-- Add visual indicator that this is update mode -->
                                            <div class="alert alert-warning mt-3">
                                                <small><i data-lucide="info" class="me-1"></i>
                                                    <strong>Update Mode:</strong> Existing attendance data is loaded. You can modify any student's attendance status.</small>
                                            </div>
                                        <?php else: ?>
                                            <!-- Normal Save State -->
                                            <div class="d-flex gap-2 justify-content-center">
                                                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                                    <i data-lucide="save"></i>
                                                    Save Attendance (<span id="completedCount"><?= $totalStudentsWithAttendance ?></span>/<?= count($students) ?>)
                                                </button>
                                                <?php if ($attendanceInProgress): ?>
                                                    <button type="button" class="btn btn-outline-danger" onclick="cancelAttendance()">
                                                        <i data-lucide="x-circle"></i>
                                                        Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div id="attendanceWarning" class="alert alert-warning mt-3" style="display: none;">
                                                <i data-lucide="alert-triangle" class="me-1"></i>
                                                Please mark attendance for all students before saving.
                                            </div>
                                            <?php if ($attendanceInProgress): ?>
                                                <small class="text-muted d-block mt-2">
                                                    <i data-lucide="info"></i>
                                                    Some students have scanned QR codes. Save to confirm attendance or Cancel to clear all scans.
                                                </small>
                                            <?php endif; ?>
                                            <input type="hidden" name="update_mode" value="0">
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i data-lucide="users" style="width: 48px; height: 48px;" class="text-muted mb-3"></i>
                                    <h6>No Students Found</h6>
                                    <p class="text-muted">No students are enrolled in this semester and department.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- QR Code Section (Right Column) -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i data-lucide="qr-code"></i>
                                QR Code Attendance
                            </h5>
                            <p class="text-muted small">Generate QR code for students to mark their attendance</p>

                            <?php if ($registeredDevicesCount > 0): ?>
                                <div class="alert alert-info py-2 mb-3">
                                    <small><i data-lucide="info" class="me-1" style="width: 14px; height: 14px;"></i>
                                        <strong><?= $registeredDevicesCount ?></strong> of <strong><?= count($students) ?></strong> students have registered devices.</small>
                                </div>

                                <div class="text-center qr-container">
                                    <?php if ($currentQRSession): ?>
                                        <!-- Active QR Session -->
                                        <div class="alert alert-success py-2 mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small>
                                                    <i data-lucide="check-circle" class="me-1" style="width: 14px; height: 14px;"></i>
                                                    <strong>QR Active</strong>
                                                    <div class="mt-1">Expires: <?= date('g:i:s A', strtotime($currentQRSession['ExpiresAt'])) ?>
                                                        <span id="qrCountdown" class="text-danger fw-bold ms-1"></span>
                                                    </div>
                                                </small>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="manualRegenerateQR()" title="Regenerate QR">
                                                        <i data-lucide="refresh-cw"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deactivateQR()">
                                                        Stop
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- QR Code Display Area -->
                                        <div class="qr-display-area mb-3">
                                            <canvas id="qrCanvas" width="400" height="400" class="border rounded mb-2" style="display: none;"></canvas>
                                            <div id="qrPlaceholder" class="p-3 border border-dashed rounded bg-light">
                                                <div class="spinner-border text-primary mb-2" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <div class="small text-muted">Loading Projector QR Code (400px)...</div>
                                            </div>
                                        </div>

                                        <!-- QR Info -->
                                        <div class="alert alert-info py-2 mb-2">
                                            <small><i data-lucide="projector" class="me-1" style="width: 14px; height: 14px;"></i>
                                                <strong>Projector Mode:</strong> 400px size, 30px margin - optimized for classroom projection</small>
                                        </div>

                                        <div id="qrTimer" class="small text-muted">
                                            Expires in <span id="countdown" class="fw-bold">5:00</span>
                                        </div>
                                    <?php else: ?>
                                        <!-- Generate QR Button -->
                                        <div id="qrPlaceholder" class="p-4 border border-dashed rounded mb-3 text-muted">
                                            <i data-lucide="qr-code" style="width: 48px; height: 48px;" class="mb-2"></i>
                                            <div>Projector QR Code will appear here</div>
                                            <small class="text-muted">400px size, 30px margin - optimized for classroom projection</small>
                                        </div>
                                        <canvas id="qrCanvas" style="display: none;" class="border rounded mb-2" width="400" height="400"></canvas>

                                        <button type="button" class="btn btn-success w-100 mb-2" onclick="generateQR()"
                                            <?= $attendanceInProgress ? 'disabled' : '' ?>>
                                            <i data-lucide="projector"></i>
                                            <?= $attendanceInProgress ? 'QR In Progress - Save First' : 'Generate Projector QR Code (400px)' ?>
                                        </button>

                                        <div class="small text-muted text-center">
                                            QR code will be valid for 5 minutes and auto-renew<br>
                                            <small>High error correction, pure black/white, projector-optimized</small>
                                        </div>


                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning py-2">
                                    <small><i data-lucide="alert-triangle" class="me-1" style="width: 14px; height: 14px;"></i>
                                        No students have registered devices for QR attendance.</small>
                                </div>

                                <div class="text-center py-4">
                                    <i data-lucide="smartphone-nfc" style="width: 48px; height: 48px;" class="text-muted mb-2"></i>
                                    <p class="text-muted small">Ask students to register their devices first</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="calendar-check" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>Select Class Details</h4>
                    <p class="text-muted">Please select semester and subject to start taking attendance</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/qrcode.min.js"></script>
    <script src="../../assets/js/attendance.js"></script>

    <script>
        // Add some CSS for update mode
        const updateModeStyles = `
            <style>
                .student-row.update-mode {
                    background-color: #fff8e1 !important;
                    border: 1px solid #ffc107 !important;
                    border-radius: 6px;
                    margin-bottom: 6px !important;
                }
                .student-row.update-mode .btn-check:disabled + .btn {
                    opacity: 1;
                    background-color: #fff;
                    border-color: #dee2e6;
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', updateModeStyles);
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            <?php if ($currentQRSession): ?>
                // Auto-initialize QR for active session
                setTimeout(function() {
                    showActiveQR(<?= json_encode($currentQRSession['QRToken']) ?>);
                    startQRTimer(<?= json_encode($currentQRSession['ExpiresAt']) ?>);
                    // Start polling for QR scans
                    if (typeof startPendingQRCheck === 'function') {
                        startPendingQRCheck(<?= json_encode($currentQRSession['QRToken']) ?>);
                    }
                }, 1000);
            <?php elseif ($selectedSubjectID && $selectedSemesterID && $date): ?>
                // Even without active QR session, start polling if attendance form is loaded
                // This handles cases where students scanned but session expired
                setTimeout(function() {
                    if (typeof startQRScanPolling === 'function') {
                        startQRScanPolling();
                    }
                }, 500);
            <?php endif; ?>
        });

        // Show active QR code using projector mode only
        function showActiveQR(token) {
            console.log('showActiveQR called with token:', token.substring(0, 10) + '...');

            // Use projector mode directly
            showProjectorQRInternal(token);
        }

        // Internal projector-optimized QR image function
        function showProjectorQRInternal(token) {
            console.log('Generating Projector QR (400px)...');
            const canvas = document.getElementById('qrCanvas');
            const placeholder = document.getElementById('qrPlaceholder');

            if (!canvas || !placeholder) {
                console.error('Canvas or placeholder not found');
                return;
            }

            const img = new Image();
            img.onload = function() {
                const ctx = canvas.getContext('2d');

                // Set canvas to projector size (400px)
                canvas.width = 400;
                canvas.height = 400;
                ctx.drawImage(this, 0, 0, 400, 400);

                canvas.style.display = 'block';
                placeholder.style.display = 'none';

                showToast('Projector QR code loaded! (400px)', 'success', 3000);
            };

            img.onerror = function() {
                console.warn('Projector QR failed, showing fallback...');
                showQRFallback(token);
            };

            // Use projector mode with 400px size
            img.src = `../../api/generate_qr_image.php?token=${encodeURIComponent(token)}&mode=projector&size=400&t=${Date.now()}`;
        }

        // Show fallback QR information
        function showQRFallback(token) {
            const placeholder = document.getElementById('qrPlaceholder');
            const canvas = document.getElementById('qrCanvas');

            if (canvas) canvas.style.display = 'none';

            if (placeholder) {
                const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
                const qrUrl = `${baseUrl}../student/scan_qr.php?token=${token}`;

                placeholder.innerHTML = `
                    <div class="p-3 border rounded bg-warning text-dark">
                        <h6 class="mb-2">
                            <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
                            QR Generation Failed
                        </h6>
                        <p class="small mb-2">Use direct link instead:</p>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" value="${qrUrl}" readonly onclick="this.select()">
                            <button class="btn btn-primary btn-sm" onclick="copyToClipboard(this.previousElementSibling.value)">Copy</button>
                        </div>
                        <small class="text-muted mt-1 d-block">Students can use this link directly in their browser</small>
                    </div>
                `;
                placeholder.style.display = 'block';

                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            showToast('QR generation failed, but direct link is available!', 'warning', 4000);
        }

        // Copy QR link to clipboard
        function copyQRLink(token) {
            const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
            const qrUrl = `${baseUrl}../student/scan_qr.php?token=${token}`;
            copyToClipboard(qrUrl);
        }

        // Copy to clipboard helper
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Link copied to clipboard!', 'success', 2000);
                }).catch(() => {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showToast('Link copied to clipboard!', 'success', 2000);
                } else {
                    showToast('Could not copy link', 'error');
                }
            } catch (err) {
                showToast('Could not copy link', 'error');
            }

            document.body.removeChild(textArea);
        }

        // Start QR timer
        function startQRTimer(expiresAt) {
            const countdownElement = document.getElementById('countdown');
            const qrCountdownElement = document.getElementById('qrCountdown');

            if (!countdownElement && !qrCountdownElement) {
                console.warn('No countdown elements found');
                return;
            }

            const expiryTime = new Date(expiresAt).getTime();

            if (window.qrTimer) {
                clearInterval(window.qrTimer);
            }

            window.qrTimer = setInterval(() => {
                const now = new Date().getTime();
                const timeLeft = expiryTime - now;

                if (timeLeft > 0) {
                    const totalSeconds = Math.floor(timeLeft / 1000);
                    const minutes = Math.floor(totalSeconds / 60);
                    const seconds = totalSeconds % 60;
                    const timeDisplay = minutes > 0 ? `${minutes}:${seconds.toString().padStart(2, '0')}` : `${seconds}s`;

                    if (countdownElement) {
                        countdownElement.textContent = timeDisplay;
                    }

                    if (qrCountdownElement) {
                        qrCountdownElement.textContent = `(${timeDisplay})`;
                        if (totalSeconds <= 30) {
                            qrCountdownElement.classList.add('text-danger');
                        }
                    }
                } else {
                    clearInterval(window.qrTimer);
                    console.log('QR code expired, auto-regenerating...');

                    // Show auto-regenerating message immediately
                    const canvas = document.getElementById('qrCanvas');
                    const placeholder = document.getElementById('qrPlaceholder');
                    if (canvas) canvas.style.display = 'none';
                    if (placeholder) {
                        placeholder.innerHTML = `
                            <div class="p-3 border border-dashed rounded text-center text-primary">
                                <div class="spinner-border text-primary mb-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="fw-bold">QR Code Expired</div>
                                <small>Auto-generating new QR code...</small>
                            </div>
                        `;
                        placeholder.style.display = 'block';
                    }

                    // Auto-regenerate new QR code immediately (no delay)
                    autoRegenerateQR();
                }
            }, 1000);
        }

        // Deactivate QR
        function deactivateQR() {
            if (confirm('Are you sure you want to stop the QR attendance session?')) {
                const teacherId = <?= json_encode($teacherID) ?>;
                const subjectId = <?= json_encode($selectedSubjectID) ?>;
                const date = <?= json_encode($date) ?>;

                // Check if required data is available
                if (!teacherId || !subjectId || !date) {
                    showToast('Missing required data for QR deactivation', 'error');
                    return;
                }

                // Don't auto-reload - just hide QR section
                fetch('../../api/deactivate_qr.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        'teacher_id': teacherId,
                        'subject_id': subjectId,
                        'date': date
                    })
                }).then(() => {
                    showToast('QR session stopped', 'success', 2000);

                    // Refresh the page to update QR state
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }).catch(() => {
                    showToast('QR session stopped locally', 'info', 2000);

                    // Still refresh the page even if API call failed
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                });
            }
        }

        // Simple toast function
        function showToast(message, type = 'info', duration = 5000) {
            console.log(`Toast: ${message} (${type})`);

            // Create simple alert if no toast system
            if (!document.querySelector('.toast-container')) {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
                alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                alert.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                `;
                document.body.appendChild(alert);

                setTimeout(() => {
                    if (alert.parentElement) {
                        alert.remove();
                    }
                }, duration);
            }
        }

        // Validate attendance form before submission
        function validateAttendanceForm() {
            const attendanceForm = document.getElementById('attendanceForm');
            if (!attendanceForm) return true;

            const totalStudents = attendanceForm.querySelectorAll('input[name^="attendance["]').length / 3; // 3 radio buttons per student
            const completedStudents = new Set();

            // Check how many students have attendance marked
            attendanceForm.querySelectorAll('input[name^="attendance["]:checked').forEach(function(radio) {
                const studentId = radio.name.match(/attendance\[(\d+)\]/)[1];
                completedStudents.add(studentId);
            });

            const completed = completedStudents.size;
            const total = totalStudents;

            if (completed < total) {
                const missing = total - completed;
                showToast(`Please complete attendance for all students. ${missing} student(s) still need attendance status.`, 'warning', 8000);

                // Highlight incomplete students
                highlightIncompleteStudents();
                return false;
            }

            return true;
        }

        // Highlight students without attendance marked
        function highlightIncompleteStudents() {
            const attendanceForm = document.getElementById('attendanceForm');
            if (!attendanceForm) return;

            // Remove existing highlights
            attendanceForm.querySelectorAll('.incomplete-attendance').forEach(el => {
                el.classList.remove('incomplete-attendance');
            });

            // Add highlights to incomplete students
            const studentRows = attendanceForm.querySelectorAll('.student-row');
            studentRows.forEach(function(row) {
                const radios = row.querySelectorAll('input[type="radio"]');
                const hasSelection = Array.from(radios).some(radio => radio.checked);

                if (!hasSelection) {
                    row.classList.add('incomplete-attendance');
                    row.style.backgroundColor = '#ffebee';
                    row.style.border = '2px solid #f44336';
                    row.style.borderRadius = '8px';
                    row.style.padding = '8px';
                    row.style.marginBottom = '4px';
                }
            });

            // Remove highlights after 5 seconds
            setTimeout(() => {
                attendanceForm.querySelectorAll('.incomplete-attendance').forEach(el => {
                    el.style.backgroundColor = '';
                    el.style.border = '';
                    el.style.borderRadius = '';
                    el.style.padding = '';
                    el.style.marginBottom = '';
                    el.classList.remove('incomplete-attendance');
                });
            }, 5000);
        }

        // Update attendance counter in real-time
        function updateAttendanceCounter() {
            const attendanceForm = document.getElementById('attendanceForm');
            const submitBtn = document.getElementById('submitBtn');
            const completedCountSpan = document.getElementById('completedCount');
            const attendanceWarning = document.getElementById('attendanceWarning');

            if (!attendanceForm) return;

            const totalStudents = attendanceForm.querySelectorAll('input[name^="attendance["]').length / 3;
            const completedStudents = new Set();

            attendanceForm.querySelectorAll('input[name^="attendance["]:checked').forEach(function(radio) {
                const studentId = radio.name.match(/attendance\[(\d+)\]/)[1];
                completedStudents.add(studentId);
            });

            const completed = completedStudents.size;

            // Update completed count display
            if (completedCountSpan) {
                completedCountSpan.textContent = completed;
            }

            // Update submit button state
            if (submitBtn) {
                if (completed === totalStudents && totalStudents > 0) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `
                        <i data-lucide="check-circle"></i>
                        Save Attendance (${completed}/${totalStudents}) - Ready!
                    `;
                    submitBtn.className = 'btn btn-success';
                    if (attendanceWarning) {
                        attendanceWarning.style.display = 'none';
                    }
                } else {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `
                        <i data-lucide="save"></i>
                        Save Attendance (${completed}/${totalStudents})
                    `;
                    submitBtn.className = 'btn btn-primary';
                    if (attendanceWarning && completed > 0) {
                        attendanceWarning.style.display = 'block';
                    }
                }

                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }

        // Add form validation and counter updates
        document.addEventListener('DOMContentLoaded', function() {
            const attendanceForm = document.getElementById('attendanceForm');
            const submitBtn = document.getElementById('submitBtn');
            const updateBtn = document.getElementById('updateBtn');

            if (attendanceForm) {
                // Add form validation for both save and update
                const handleFormSubmit = function(e) {
                    // For save mode, validate all students are marked
                    if (submitBtn && !updateBtn) {
                        if (!validateAttendanceForm()) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    // For update mode, allow partial updates
                    return true;
                };

                attendanceForm.addEventListener('submit', handleFormSubmit);

                // Add counter updates on radio button changes (only for save mode)
                if (submitBtn && !updateBtn) {
                    attendanceForm.addEventListener('change', function(e) {
                        if (e.target.type === 'radio' && e.target.name.startsWith('attendance[')) {
                            updateAttendanceCounter();
                        }
                    });

                    // Initial counter update for save mode
                    updateAttendanceCounter();
                }

                // Initialize visual state for existing attendance records
                initializeFormVisualState();
            }

            // Initialize success message if present
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                showToast('Attendance saved successfully!', 'success', 5000);
            } else if (urlParams.get('success') === 'updated') {
                showToast('Attendance updated successfully!', 'success', 5000);
            } else if (urlParams.get('cancelled') === '1') {
                showToast('Attendance cancelled successfully!', 'info', 5000);
            }
        }); // Initialize form visual state to match saved data
        function initializeFormVisualState() {
            const studentItems = document.querySelectorAll('.student-item');

            studentItems.forEach(item => {
                const checkedRadio = item.querySelector('input[type="radio"]:checked');

                if (checkedRadio) {
                    // Update radio button styling (use external function if available, otherwise inline)
                    if (typeof updateRadioButtonStyling === 'function') {
                        updateRadioButtonStyling(checkedRadio);
                    } else {
                        // Inline fallback styling update
                        updateRadioButtonStylingInline(checkedRadio);
                    }

                    // Update row border color based on selection
                    const studentRow = checkedRadio.closest('.student-item');
                    if (studentRow) {
                        if (checkedRadio.value === 'present') {
                            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-success');
                        } else if (checkedRadio.value === 'absent') {
                            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-danger');
                        } else if (checkedRadio.value === 'late') {
                            studentRow.className = studentRow.className.replace(/border-\w+/, 'border-warning');
                        }
                    }
                }
            });

            // Update attendance counter for update mode
            if (document.getElementById('updateBtn')) {
                updateAttendanceCounter();
            }
        }

        // Enhanced attendance counter for update mode
        function updateAttendanceCounter() {
            const updateBtn = document.getElementById('updateBtn');
            if (!updateBtn) return;

            const totalStudents = document.querySelectorAll('input[name^="attendance["]').length / 3;
            const completedStudents = new Set();

            document.querySelectorAll('input[name^="attendance["]:checked').forEach(function(radio) {
                const studentId = radio.name.match(/attendance\[(\d+)\]/)[1];
                completedStudents.add(studentId);
            });

            const completed = completedStudents.size;

            updateBtn.innerHTML = `
                <i data-lucide="edit"></i>
                Update Attendance (${completed}/${totalStudents})
            `;

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Inline fallback for radio button styling
        function updateRadioButtonStylingInline(radioInput) {
            if (!radioInput.checked) return;

            // Get the student row
            const studentRow = radioInput.closest('.student-item');
            if (!studentRow) return;

            // Reset all labels in this row to outline style
            const allLabels = studentRow.querySelectorAll('label.btn');
            allLabels.forEach(label => {
                const input = document.getElementById(label.getAttribute('for'));
                if (input && input.value === 'present') {
                    label.className = 'btn btn-outline-success';
                } else if (input && input.value === 'absent') {
                    label.className = 'btn btn-outline-danger';
                } else if (input && input.value === 'late') {
                    label.className = 'btn btn-outline-warning';
                }
            });

            // Update the selected label to solid style
            const selectedLabel = document.querySelector(`label[for="${radioInput.id}"]`);
            if (selectedLabel) {
                if (radioInput.value === 'present') {
                    selectedLabel.className = 'btn btn-success';
                } else if (radioInput.value === 'absent') {
                    selectedLabel.className = 'btn btn-danger';
                } else if (radioInput.value === 'late') {
                    selectedLabel.className = 'btn btn-warning';
                }
            }
        }

        // QR generation throttling
        let lastQRGeneration = 0;
        const QR_GENERATION_COOLDOWN = 3000; // 3 seconds

        // Main QR generation function (used by manual button and auto-regeneration)
        function generateQR() {
            const now = Date.now();

            // Check throttling
            if (now - lastQRGeneration < QR_GENERATION_COOLDOWN) {
                const remaining = Math.ceil((QR_GENERATION_COOLDOWN - (now - lastQRGeneration)) / 1000);
                showToast(`Please wait ${remaining} seconds before generating another QR code`, 'warning');
                return;
            }

            lastQRGeneration = now;
            console.log('Generating QR code...');

            const teacherId = <?= json_encode($teacherID) ?>;
            const subjectId = <?= json_encode($selectedSubjectID) ?>;
            const date = <?= json_encode($date) ?>;

            if (!teacherId || !subjectId || !date) {
                console.error('Missing required data for QR generation');
                showToast('Cannot generate QR - missing data', 'error');
                return;
            }

            // Show loading state
            const placeholder = document.getElementById('qrPlaceholder');
            const canvas = document.getElementById('qrCanvas');

            if (placeholder) {
                placeholder.innerHTML = `
                    <div class="p-3 border border-dashed rounded text-center text-primary">
                        <div class="spinner-border text-primary mb-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="fw-bold">Generating QR Code</div>
                        <small>Please wait...</small>
                    </div>
                `;
                placeholder.style.display = 'block';
            }

            if (canvas) {
                canvas.style.display = 'none';
            }

            // Call API to generate QR
            generateQRDirect();
        }

        // Auto-regenerate QR code when expired (bypasses throttling)
        function autoRegenerateQR() {
            console.log('Auto-regenerating QR code...');

            const subjectId = <?= json_encode($selectedSubjectID) ?>;
            const semesterId = <?= json_encode($selectedSemesterID) ?>;
            const date = <?= json_encode($date) ?>;

            if (!subjectId || !semesterId || !date) {
                console.error('Missing required data for QR regeneration');
                showToast('Cannot regenerate QR - missing data', 'error');
                showManualRegenerateButton();
                return;
            }

            // Update last generation time to prevent rapid manual clicks
            lastQRGeneration = Date.now();

            // Call API to generate QR (bypass throttling for auto-regeneration)
            const formData = new FormData();
            formData.append('generate_qr', '1');
            formData.append('semester', semesterId);
            formData.append('subject', subjectId);
            formData.append('date', date);

            fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.qr_token) {
                        console.log('QR auto-regenerated successfully:', data.qr_token);
                        showToast('New QR code generated automatically!', 'success', 2000);

                        // Show the new QR code
                        showActiveQR(data.qr_token);

                        // Start the timer with proper expiry time
                        if (data.expires_at) {
                            startQRTimer(data.expires_at);
                        } else {
                            // Fallback: assume 5 minutes from now
                            const expiryTime = new Date(Date.now() + 5 * 60 * 1000).toISOString();
                            startQRTimer(expiryTime);
                        }

                        // Start polling for QR scans
                        if (typeof startPendingQRCheck === 'function') {
                            startPendingQRCheck(data.qr_token);
                        }
                    } else {
                        console.error('QR auto-regeneration failed:', data.error || 'Unknown error');
                        showToast('Auto-regeneration failed: ' + (data.error || 'Unknown error'), 'error');
                        showManualRegenerateButton();
                    }
                })
                .catch(error => {
                    console.error('QR auto-regeneration error:', error);
                    showToast('Auto-regeneration failed. Please try manually.', 'error');
                    showManualRegenerateButton();
                });
        }

        // Direct QR generation API call
        function generateQRDirect() {
            const subjectId = <?= json_encode($selectedSubjectID) ?>;
            const semesterId = <?= json_encode($selectedSemesterID) ?>;
            const date = <?= json_encode($date) ?>;

            console.log('Making API call to generate QR code...');

            // Use the attendance.php endpoint for QR generation (not the direct API)
            const formData = new FormData();
            formData.append('generate_qr', '1');
            formData.append('semester', semesterId);
            formData.append('subject', subjectId);
            formData.append('date', date);

            fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.qr_token) {
                        console.log('QR generated successfully:', data.qr_token);
                        showToast('QR code generated successfully!', 'success', 2000);

                        // Show the new QR code
                        showActiveQR(data.qr_token);

                        // Start the timer with proper expiry time
                        if (data.expires_at) {
                            startQRTimer(data.expires_at);
                        } else {
                            // Fallback: assume 5 minutes from now
                            const expiryTime = new Date(Date.now() + 5 * 60 * 1000).toISOString();
                            startQRTimer(expiryTime);
                        }

                        // Start polling for QR scans
                        if (typeof startPendingQRCheck === 'function') {
                            startPendingQRCheck(data.qr_token);
                        }
                    } else {
                        console.error('QR generation failed:', data.error || 'Unknown error');
                        showToast('Failed to generate QR code: ' + (data.error || 'Unknown error'), 'error');
                        showManualRegenerateButton();
                    }
                })
                .catch(error => {
                    console.error('QR generation error:', error);
                    showToast('Network error while generating QR code. Please try again.', 'error');
                    showManualRegenerateButton();
                });
        }

        // Show manual regenerate button when auto-regeneration fails
        function showManualRegenerateButton() {
            const placeholder = document.getElementById('qrPlaceholder');
            if (placeholder) {
                placeholder.innerHTML = `
                    <div class="p-3 border border-dashed rounded text-center text-danger">
                        <i data-lucide="alert-circle" style="width: 48px; height: 48px;" class="mb-2"></i>
                        <div class="fw-bold">QR Generation Failed</div>
                        <small class="text-muted mb-3">Unable to automatically generate new QR code</small>
                        <button type="button" class="btn btn-primary btn-sm" onclick="manualRegenerateQR()">
                            <i data-lucide="refresh-cw"></i>
                            Try Again
                        </button>
                    </div>
                `;
                placeholder.style.display = 'block';

                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }

        // Manual regenerate function
        function manualRegenerateQR() {
            console.log('Manual QR regeneration triggered');

            // Show loading state
            const placeholder = document.getElementById('qrPlaceholder');
            if (placeholder) {
                placeholder.innerHTML = `
                    <div class="p-3 border border-dashed rounded text-center text-primary">
                        <div class="spinner-border text-primary mb-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="fw-bold">Generating QR Code</div>
                        <small>Please wait...</small>
                    </div>
                `;
            }

            // Call regeneration
            autoRegenerateQR();
        }
    </script>
</body>

</html>