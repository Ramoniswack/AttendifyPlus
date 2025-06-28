<?php

session_start();
require_once(__DIR__ . '/../../config/db_config.php');

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
$successMsg = isset($_GET['success']) ? "Attendance saved successfully." : "";
$errorMsg = $_GET['error'] ?? "";

// Handle QR Code Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    header('Content-Type: application/json');

    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    try {
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
            echo json_encode([
                'success' => true,
                'qr_token' => $qrToken,
                'expires_at' => $expiresAt,
                'scan_url' => 'scan_qr.php?token=' . $qrToken
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create QR session']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle form submission for manual attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        header("Location: attendance.php?error=Missing required fields.");
        exit();
    }

    // Check if attendance already exists for this date
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
    $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $attendanceExists = $result->fetch_assoc()['count'] > 0;

    $conn->begin_transaction();
    try {
        if ($attendanceExists) {
            // Update existing attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $studentCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE StudentID = ? AND SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
                $studentCheckStmt->bind_param("iisi", $studentID, $selectedSubjectID, $date, $teacherID);
                $studentCheckStmt->execute();
                $studentResult = $studentCheckStmt->get_result();
                $studentRecordExists = $studentResult->fetch_assoc()['count'] > 0;

                if ($studentRecordExists) {
                    // Update existing record (only if not marked via QR)
                    $updateStmt = $conn->prepare("
                        UPDATE attendance_records 
                        SET Status = ?, DateTime = ?, Method = 'manual'
                        WHERE StudentID = ? AND TeacherID = ? AND SubjectID = ? AND DATE(DateTime) = ? AND Method != 'qr'
                    ");
                    $dateTime = date('Y-m-d H:i:s');
                    $updateStmt->bind_param("ssiiss", $status, $dateTime, $studentID, $teacherID, $selectedSubjectID, $date);
                    $updateStmt->execute();
                } else {
                    // Insert new record
                    $insertStmt = $conn->prepare("
                        INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status, Method) 
                        VALUES (?, ?, ?, ?, ?, 'manual')
                    ");
                    $dateTime = date('Y-m-d H:i:s');
                    $insertStmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $dateTime, $status);
                    $insertStmt->execute();
                }
            }
        } else {
            // Insert new attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $insertStmt = $conn->prepare("
                    INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status, Method) 
                    VALUES (?, ?, ?, ?, ?, 'manual')
                ");
                $dateTime = date('Y-m-d H:i:s');
                $insertStmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $dateTime, $status);
                $insertStmt->execute();
            }
        }

        $conn->commit();
        header("Location: attendance.php?success=1&semester=$selectedSemesterID&subject=$selectedSubjectID&date=$date");
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
    $studentQuery = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.DeviceRegistered, s.ProgramCode,
               ar.Status as AttendanceStatus, ar.Method as AttendanceMethod,
               ar.DateTime as AttendanceTime
        FROM students s
        LEFT JOIN attendance_records ar ON s.StudentID = ar.StudentID 
            AND ar.SubjectID = ? AND ar.TeacherID = ? AND DATE(ar.DateTime) = ?
        WHERE s.SemesterID = ? AND s.DepartmentID = ?
        ORDER BY s.FullName
    ");
    $studentQuery->bind_param("iiisi", $selectedSubjectID, $teacherID, $date, $selectedSemesterID, $teacherDept['DepartmentID']);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    while ($row = $studentResult->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get current QR session if active
$currentQRSession = null;
if ($selectedSubjectID && $selectedSemesterID && $date) {
    $qrSessionQuery = $conn->prepare("
        SELECT QRToken, ExpiresAt, CreatedAt 
        FROM qr_attendance_sessions 
        WHERE TeacherID = ? AND SubjectID = ? AND Date = ? AND IsActive = 1 AND ExpiresAt > NOW()
        ORDER BY CreatedAt DESC 
        LIMIT 1
    ");
    $qrSessionQuery->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $qrSessionQuery->execute();
    $qrResult = $qrSessionQuery->get_result();
    $currentQRSession = $qrResult->fetch_assoc();
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
    <link rel="stylesheet" href="../../assets/css/attendance.css">
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
                <?= htmlspecialchars($successMsg) ?>
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
                            max="<?= date('Y-m-d') ?>" onchange="handleDateChange()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i data-lucide="calendar"></i> Semester
                        </label>
                        <select name="semester" class="form-select" onchange="handleSemesterChange()">
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
                        <select name="subject" class="form-select" onchange="handleSubjectChange()" <?= empty($subjects) ? 'disabled' : '' ?>>
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
                        <button type="submit" class="btn btn-primary d-block w-100">
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
                                            <div class="student-item mb-2 p-2 border rounded <?= $student['AttendanceStatus'] ? 'border-' . ($student['AttendanceStatus'] === 'present' ? 'success' : ($student['AttendanceStatus'] === 'absent' ? 'danger' : 'warning')) : 'border-light' ?>">

                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="student-info">
                                                        <div class="fw-medium"><?= htmlspecialchars($student['FullName']) ?>
                                                            <?php if ($student['DeviceRegistered']): ?>
                                                                <i data-lucide="smartphone" class="text-primary ms-1" style="width: 12px; height: 12px;"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">ID: <?= $student['StudentID'] ?></small>
                                                        <?php if ($student['AttendanceStatus'] && $student['AttendanceMethod'] === 'qr'): ?>
                                                            <span class="badge bg-info ms-2">QR Marked</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Radio Buttons -->
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['StudentID'] ?>]"
                                                            id="p<?= $student['StudentID'] ?>" value="present"
                                                            <?= $student['AttendanceStatus'] === 'present' ? 'checked' : '' ?>
                                                            <?= $student['AttendanceMethod'] === 'qr' ? 'disabled' : '' ?>>
                                                        <label class="btn btn-outline-success" for="p<?= $student['StudentID'] ?>">Present</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['StudentID'] ?>]"
                                                            id="a<?= $student['StudentID'] ?>" value="absent"
                                                            <?= $student['AttendanceStatus'] === 'absent' ? 'checked' : '' ?>
                                                            <?= $student['AttendanceMethod'] === 'qr' ? 'disabled' : '' ?>>
                                                        <label class="btn btn-outline-danger" for="a<?= $student['StudentID'] ?>">Absent</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['StudentID'] ?>]"
                                                            id="l<?= $student['StudentID'] ?>" value="late"
                                                            <?= $student['AttendanceStatus'] === 'late' ? 'checked' : '' ?>
                                                            <?= $student['AttendanceMethod'] === 'qr' ? 'disabled' : '' ?>>
                                                        <label class="btn btn-outline-warning" for="l<?= $student['StudentID'] ?>">Late</label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="text-center mt-3">
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i data-lucide="save"></i>
                                            Save Attendance (<?= $attendanceStats['present'] + $attendanceStats['absent'] + $attendanceStats['late'] ?>/<?= count($students) ?>)
                                        </button>
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
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deactivateQR()">
                                                    Stop
                                                </button>
                                            </div>
                                        </div>

                                        <!-- QR Code Display Area -->
                                        <div class="qr-display-area mb-3">
                                            <canvas id="qrCanvas" width="200" height="200" class="border rounded mb-2" style="display: none;"></canvas>
                                            <div id="qrPlaceholder" class="p-3 border border-dashed rounded bg-light">
                                                <div class="spinner-border text-primary mb-2" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <div class="small text-muted">Loading QR Code...</div>
                                            </div>
                                        </div>

                                        <!-- QR Options -->
                                        <div class="btn-group w-100 mb-2" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="showServerQR('<?= $currentQRSession['QRToken'] ?>')">
                                                <i data-lucide="server" style="width: 14px; height: 14px;"></i> Server QR
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="showJSQR('<?= $currentQRSession['QRToken'] ?>')">
                                                <i data-lucide="code" style="width: 14px; height: 14px;"></i> JS QR
                                            </button>
                                        </div>

                                        <!-- Direct Link -->
                                        <div class="alert alert-info py-2 mt-2">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" value="<?= $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) ?>/scan_qr.php?token=<?= $currentQRSession['QRToken'] ?>" readonly onclick="this.select()">
                                                <button class="btn btn-outline-primary" onclick="copyQRLink('<?= $currentQRSession['QRToken'] ?>')">
                                                    <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted mt-1 d-block">Students can use this link directly</small>
                                        </div>

                                        <div id="qrTimer" class="small text-muted">
                                            Expires in <span id="countdown" class="fw-bold">300</span>s
                                        </div>
                                    <?php else: ?>
                                        <!-- Generate QR Button -->
                                        <div id="qrPlaceholder" class="p-4 border border-dashed rounded mb-3 text-muted">
                                            <i data-lucide="qr-code" style="width: 48px; height: 48px;" class="mb-2"></i>
                                            <div>QR Code will appear here</div>
                                            <small class="text-muted">Click "Generate QR Code" below</small>
                                        </div>
                                        <canvas id="qrCanvas" style="display: none;" class="border rounded mb-2" width="200" height="200"></canvas>

                                        <button type="button" class="btn btn-success w-100 mb-2" onclick="generateQR()">
                                            <i data-lucide="qr-code"></i>
                                            Generate QR Code
                                        </button>

                                        <div class="small text-muted text-center">
                                            QR code will be valid for 5 minutes
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/attendance.js"></script>

    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded - Initializing...');

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            <?php if ($currentQRSession): ?>
                // Auto-initialize QR for active session
                console.log('Active QR session detected');
                setTimeout(function() {
                    console.log('Attempting to show QR...');
                    showActiveQR('<?= $currentQRSession['QRToken'] ?>');
                    startQRTimer('<?= $currentQRSession['ExpiresAt'] ?>');
                }, 1000);
            <?php endif; ?>
        });

        // Show active QR code using multiple methods
        function showActiveQR(token) {
            console.log('showActiveQR called with token:', token.substring(0, 10) + '...');

            // Try server QR first
            showServerQR(token);
        }

        // Show server-generated QR image
        function showServerQR(token) {
            console.log('Attempting server QR...');
            const canvas = document.getElementById('qrCanvas');
            const placeholder = document.getElementById('qrPlaceholder');

            if (!canvas || !placeholder) {
                console.error('Canvas or placeholder not found');
                return;
            }

            const img = new Image();
            img.onload = function() {
                console.log('Server QR image loaded successfully');
                const ctx = canvas.getContext('2d');
                canvas.width = 200;
                canvas.height = 200;
                ctx.drawImage(this, 0, 0, 200, 200);

                canvas.style.display = 'block';
                placeholder.style.display = 'none';

                showToast('Server QR code loaded!', 'success', 2000);
            };

            img.onerror = function() {
                console.warn('Server QR failed, trying JS fallback...');
                showJSQR(token);
            };

            img.src = `../../api/generate_qr_image.php?token=${encodeURIComponent(token)}&size=200&t=${Date.now()}`;
        }

        // Show JavaScript-generated QR
        function showJSQR(token) {
            console.log('Attempting JS QR...');
            const canvas = document.getElementById('qrCanvas');
            const placeholder = document.getElementById('qrPlaceholder');

            if (!canvas) return;

            // Check if QR library is available
            if (typeof window.QRCode !== 'undefined') {
                const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
                const qrData = `${baseUrl}scan_qr.php?token=${token}`;

                window.QRCode.toCanvas(canvas, qrData, {
                    width: 200,
                    height: 200,
                    margin: 2,
                    color: {
                        dark: '#1A73E8',
                        light: '#FFFFFF'
                    }
                }, function(error) {
                    if (error) {
                        console.error('JS QR generation failed:', error);
                        showQRFallback(token);
                    } else {
                        console.log('JS QR generated successfully');
                        canvas.style.display = 'block';
                        if (placeholder) placeholder.style.display = 'none';
                        showToast('JavaScript QR code generated!', 'success', 2000);
                    }
                });
            } else {
                console.warn('QR library not available, showing fallback...');
                showQRFallback(token);
            }
        }

        // Show fallback QR information
        function showQRFallback(token) {
            console.log('Showing QR fallback...');
            const placeholder = document.getElementById('qrPlaceholder');
            const canvas = document.getElementById('qrCanvas');

            if (canvas) canvas.style.display = 'none';

            if (placeholder) {
                const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
                const qrUrl = `${baseUrl}scan_qr.php?token=${token}`;

                placeholder.innerHTML = `
                    <div class="p-3 border rounded bg-info text-white">
                        <h6 class="mb-2">
                            <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                            QR Code Active!
                        </h6>
                        <p class="small mb-2">Students can scan using any QR app or use the direct link:</p>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" value="${qrUrl}" readonly onclick="this.select()">
                            <button class="btn btn-light btn-sm" onclick="copyToClipboard('${qrUrl}')">Copy</button>
                        </div>
                    </div>
                `;
                placeholder.style.display = 'block';

                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            showToast('QR link ready for students!', 'info', 3000);
        }

        // Copy QR link to clipboard
        function copyQRLink(token) {
            const baseUrl = window.location.origin + window.location.pathname.replace('attendance.php', '');
            const qrUrl = `${baseUrl}scan_qr.php?token=${token}`;
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
            console.log('Starting QR timer for:', expiresAt);

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
                    const seconds = Math.floor(timeLeft / 1000);

                    if (countdownElement) {
                        countdownElement.textContent = seconds;
                    }

                    if (qrCountdownElement) {
                        qrCountdownElement.textContent = `(${seconds}s)`;
                        if (seconds <= 30) {
                            qrCountdownElement.classList.add('text-danger');
                        }
                    }
                } else {
                    clearInterval(window.qrTimer);
                    showToast('QR code has expired. Refreshing...', 'warning');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            }, 1000);
        }

        // Deactivate QR
        function deactivateQR() {
            if (confirm('Are you sure you want to stop the QR attendance session?')) {
                location.reload();
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
    </script>
</body>

</html>