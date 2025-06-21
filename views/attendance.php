<?php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: /attendifyplus/views/login.php");
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
$action = $_GET['action'] ?? '';
$successMsg = "";
$errorMsg = $_GET['error'] ?? "";

if (isset($_GET['success'])) {
    $action = $_GET['action'] ?? 'saved';
    $successMsg = "Attendance " . $action . " successfully.";
}

// Handle QR Code Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        header("Content-Type: application/json");
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    // Generate unique QR token
    $qrToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+60 seconds'));

    // Deactivate any existing active sessions
    $deactivateStmt = $conn->prepare("UPDATE qr_attendance_sessions SET IsActive = 0 WHERE TeacherID = ? AND SubjectID = ? AND Date = ?");
    $deactivateStmt->bind_param("iis", $teacherID, $selectedSubjectID, $date);
    $deactivateStmt->execute();

    // Insert new QR session
    $qrStmt = $conn->prepare("INSERT INTO qr_attendance_sessions (TeacherID, SubjectID, Date, QRToken, ExpiresAt) VALUES (?, ?, ?, ?, ?)");
    $qrStmt->bind_param("iisss", $teacherID, $selectedSubjectID, $date, $qrToken, $expiresAt);
    $qrStmt->execute();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'qr_token' => $qrToken,
        'expires_at' => $expiresAt
    ]);
    exit();
}

// Handle form submission (both new and update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        $params = http_build_query([
            'error' => 'Missing required fields.',
            'semester' => $selectedSemesterID,
            'subject' => $selectedSubjectID,
            'date' => $date
        ]);
        header("Location: attendance.php?" . $params);
        exit();
    }

    // Check if attendance already exists
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
    $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $attendanceExists = $result->fetch_assoc()['count'] > 0;
    $checkStmt->close();

    $conn->begin_transaction();
    try {
        if ($attendanceExists) {
            // Update existing attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $updateStmt = $conn->prepare("
                    UPDATE attendance_records 
                    SET Status = ?, DateTime = ?, Method = 'manual'
                    WHERE StudentID = ? AND TeacherID = ? AND SubjectID = ? AND DATE(DateTime) = ?
                ");
                $dateTime = $date . ' ' . date('H:i:s');
                $updateStmt->bind_param("siiiis", $status, $dateTime, $studentID, $teacherID, $selectedSubjectID, $date);
                $updateStmt->execute();
            }
        } else {
            // Insert new attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $insertStmt = $conn->prepare("INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status, Method) VALUES (?, ?, ?, ?, ?, 'manual')");
                $dateTime = $date . ' ' . date('H:i:s');
                $insertStmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $dateTime, $status);
                $insertStmt->execute();
            }
            $insertStmt->close();
        }

        $conn->commit();

        $action = $attendanceExists ? 'updated' : 'saved';
        $params = http_build_query([
            'success' => 1,
            'action' => $action,
            'semester' => $selectedSemesterID,
            'subject' => $selectedSubjectID,
            'date' => $date
        ]);
        header("Location: attendance.php?" . $params);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: attendance.php?error=Failed to save attendance. Please try again.");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mark Attendance | Attendify+</title>
    <link rel="stylesheet" href="../assets/css/attendance.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/dashboard_teacher.js" defer></script>
</head>

<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_teacher_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include 'navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="clipboard-check"></i>
                    Mark Attendance
                </h2>
                <p class="text-muted mb-0">Track student attendance for your classes</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="attendance_report.php" class="btn btn-outline-primary">
                    <i data-lucide="bar-chart-3" class="me-1"></i>
                    Reports
                </a>
                <a href="dashboard_teacher.php" class="btn btn-primary">
                    <i data-lucide="arrow-left" class="me-1"></i>
                    Dashboard
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i data-lucide="check-circle" class="me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i data-lucide="alert-circle" class="me-2"></i>
                <strong>Error!</strong> <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Selection Form -->
        <div class="card equal-height-card">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <i data-lucide="settings" class="me-2"></i>
                    Class Selection
                </h5>
                <form method="POST" id="selectionForm">
                    <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">
                    <div class="row g-3">
                        <!-- Date -->
                        <div class="col-md-3">
                            <label class="form-label">
                                <i data-lucide="calendar" class="me-1"></i> Date
                            </label>
                            <input type="date" name="date" value="<?= $date ?>" class="form-control" max="<?= date('Y-m-d') ?>" onchange="handleFormChange()" required />
                        </div>

                        <!-- Semester -->
                        <div class="col-md-3">
                            <label class="form-label">
                                <i data-lucide="layers" class="me-1"></i> Semester
                            </label>
                            <select name="semester" class="form-select" onchange="handleFormChange()" required>
                                <option value="">Choose Semester</option>
                                <?php while ($sem = $semResult->fetch_assoc()): ?>
                                    <option value="<?= $sem['SemesterID'] ?>" <?= $selectedSemesterID == $sem['SemesterID'] ? 'selected' : '' ?>>
                                        Semester <?= $sem['SemesterNumber'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="col-md-6">
                            <label class="form-label">
                                <i data-lucide="book-open" class="me-1"></i> Subject
                            </label>
                            <?php if ($selectedSemesterID): ?>
                                <?php
                                $subjectQuery = $conn->prepare("
                                    SELECT s.SubjectID, s.SubjectName, s.SubjectCode
                                    FROM subjects s
                                    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
                                    WHERE ts.TeacherID = ? AND s.SemesterID = ?
                                    ORDER BY s.SubjectName
                                ");
                                $subjectQuery->bind_param("ii", $teacherID, $selectedSemesterID);
                                $subjectQuery->execute();
                                $subjectResult = $subjectQuery->get_result();
                                ?>
                                <select name="subject" class="form-select" onchange="handleFormChange()" required>
                                    <option value="">Choose Subject</option>
                                    <?php while ($sub = $subjectResult->fetch_assoc()): ?>
                                        <option value="<?= $sub['SubjectID'] ?>" <?= $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sub['SubjectCode']) ?> - <?= htmlspecialchars($sub['SubjectName']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            <?php else: ?>
                                <select class="form-select" disabled>
                                    <option>Select semester first</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Section -->
        <?php if ($selectedSemesterID && $selectedSubjectID): ?>
            <?php
            // Check if attendance exists
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
            $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $attendanceExists = $result->fetch_assoc()['count'] > 0;

            // Get existing attendance if exists
            $existingAttendance = [];
            $lastUpdatedTime = null;
            if ($attendanceExists) {
                $existingStmt = $conn->prepare("
                    SELECT StudentID, Status, Method, DateTime
                    FROM attendance_records 
                    WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?
                    ORDER BY DateTime DESC
                ");
                $existingStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
                $existingStmt->execute();
                $existingResult = $existingStmt->get_result();

                // Get the most recent update time
                $firstRow = $existingResult->fetch_assoc();
                if ($firstRow) {
                    $lastUpdatedTime = new DateTime($firstRow['DateTime']);
                    $existingAttendance[$firstRow['StudentID']] = [
                        'status' => $firstRow['Status'],
                        'method' => $firstRow['Method'] ?? 'manual',
                        'datetime' => $firstRow['DateTime']
                    ];
                }

                // Get the rest of the records
                while ($row = $existingResult->fetch_assoc()) {
                    $existingAttendance[$row['StudentID']] = [
                        'status' => $row['Status'],
                        'method' => $row['Method'] ?? 'manual',
                        'datetime' => $row['DateTime']
                    ];
                }
            }

            // Get students
            $studentsQuery = $conn->prepare("
                SELECT StudentID, FullName, ProgramCode
                FROM students 
                WHERE DepartmentID = ? AND SemesterID = ? 
                ORDER BY FullName
            ");
            $studentsQuery->bind_param("ii", $teacherDept['DepartmentID'], $selectedSemesterID);
            $studentsQuery->execute();
            $students = $studentsQuery->get_result();
            $studentCount = $students->num_rows;

            // Get subject info
            $subjectInfoQuery = $conn->prepare("SELECT SubjectName, SubjectCode FROM subjects WHERE SubjectID = ?");
            $subjectInfoQuery->bind_param("i", $selectedSubjectID);
            $subjectInfoQuery->execute();
            $subjectInfo = $subjectInfoQuery->get_result()->fetch_assoc();

            // Calculate attendance statistics
            $presentCount = $absentCount = $lateCount = 0;
            if ($attendanceExists) {
                foreach ($existingAttendance as $data) {
                    switch ($data['status']) {
                        case 'present':
                            $presentCount++;
                            break;
                        case 'absent':
                            $absentCount++;
                            break;
                        case 'late':
                            $lateCount++;
                            break;
                    }
                }
            }
            ?>

            <!-- Attendance Summary Stats -->
            <?php if ($attendanceExists): ?>
                <div class="card equal-height-card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                <i data-lucide="pie-chart" class="me-2"></i>
                                Attendance Summary
                            </h5>
                            <div>
                                <span class="badge bg-info">
                                    <i data-lucide="clock" class="me-1" style="width: 14px; height: 14px;"></i>
                                    Last Updated: <?= $lastUpdatedTime ? $lastUpdatedTime->format('M j, Y g:i A') : 'N/A' ?>
                                </span>
                            </div>
                        </div>

                        <div class="row text-center g-3">
                            <div class="col-md-3 col-6">
                                <div class="stats-card bg-success text-white">
                                    <div class="stats-number"><?= $presentCount ?></div>
                                    <div>Present</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stats-card bg-danger text-white">
                                    <div class="stats-number"><?= $absentCount ?></div>
                                    <div>Absent</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stats-card bg-warning text-dark">
                                    <div class="stats-number"><?= $lateCount ?></div>
                                    <div>Late</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stats-card bg-primary text-white">
                                    <div class="stats-number"><?= $studentCount ?></div>
                                    <div>Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Attendance Container -->
            <div class="attendance-container">
                <?php if ($attendanceExists): ?>
                    <!-- COMPLETED ATTENDANCE VIEW -->
                    <div class="completed-view" id="completedView">
                        <div class="row g-4">
                            <!-- Left - Table View -->
                            <div class="col-lg-8">
                                <div class="card equal-height-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="card-title mb-1"><?= htmlspecialchars($subjectInfo['SubjectCode']) ?> - <?= htmlspecialchars($subjectInfo['SubjectName']) ?></h5>
                                                <p class="text-muted mb-0">
                                                    <?= date('M j, Y', strtotime($date)) ?> • <?= $studentCount ?> Students
                                                    <span class="badge bg-success ms-2">Completed</span>
                                                </p>
                                            </div>
                                            <button type="button" class="btn btn-update" onclick="switchToUpdateMode()">
                                                <i data-lucide="edit-3" class="me-1"></i> Update Attendance
                                            </button>
                                        </div>

                                        <!-- Attendance Table -->
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Student Name</th>
                                                        <th>Status</th>
                                                        <th>Method</th>
                                                        <th>Time</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $students->data_seek(0);
                                                    $index = 1;
                                                    while ($student = $students->fetch_assoc()):
                                                        $studentID = $student['StudentID'];
                                                        $currentData = $existingAttendance[$studentID] ?? null;
                                                        $currentStatus = $currentData['status'] ?? 'absent';
                                                        $currentMethod = $currentData['method'] ?? 'manual';
                                                        $recordTime = isset($currentData['datetime']) ? date('g:i A', strtotime($currentData['datetime'])) : '';
                                                    ?>
                                                        <tr>
                                                            <td><?= $index ?></td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="student-avatar me-2">
                                                                        <?= strtoupper(substr($student['FullName'], 0, 2)) ?>
                                                                    </div>
                                                                    <div>
                                                                        <div class="fw-medium"><?= htmlspecialchars($student['FullName']) ?></div>
                                                                        <?php if ($student['ProgramCode']): ?>
                                                                            <small class="text-muted"><?= htmlspecialchars($student['ProgramCode']) ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $currentStatus === 'present' ? 'success' : ($currentStatus === 'late' ? 'warning' : 'danger') ?>">
                                                                    <?= ucfirst($currentStatus) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $currentMethod === 'qr' ? 'primary' : 'secondary' ?>">
                                                                    <?= $currentMethod === 'qr' ? 'QR Code' : 'Manual' ?>
                                                                </span>
                                                            </td>
                                                            <td><?= $recordTime ?></td>
                                                        </tr>
                                                    <?php
                                                        $index++;
                                                    endwhile;
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right - QR Code Generator -->
                            <div class="col-lg-4">
                                <div class="card equal-height-card qr-container-fixed">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">
                                            <i data-lucide="qr-code" class="me-2"></i>
                                            QR Attendance
                                        </h5>
                                        <p class="text-muted">Students scan to mark attendance</p>

                                        <div id="qrContainer" class="text-center">
                                            <div id="qrPlaceholder" class="qr-placeholder">
                                                <i data-lucide="qr-code" style="width: 80px; height: 80px;" class="text-muted"></i>
                                                <p class="text-muted mt-2">Click Generate QR</p>
                                            </div>
                                            <canvas id="qrCanvas" style="display: none; max-width: 100%;"></canvas>
                                        </div>

                                        <div class="d-grid gap-2 mt-3">
                                            <button type="button" class="btn btn-primary" onclick="generateQR()">
                                                <i data-lucide="refresh-cw" class="me-1"></i>
                                                <span id="qrButtonText">Generate QR Code</span>
                                            </button>
                                            <div id="qrTimer" class="text-center text-muted" style="display: none;">
                                                <small>Expires in: <span id="countdown">60</span>s</small>
                                            </div>
                                        </div>

                                        <div class="alert alert-info mt-3">
                                            <i data-lucide="info" class="me-1"></i>
                                            <small>QR codes expire every 60 seconds. Students who scan will be marked Present automatically.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Update Form Container -->
                    <div id="updateForm" style="display: none;" class="update-section">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card equal-height-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <i data-lucide="edit-3" class="me-2"></i>
                                                    Update Attendance: <?= htmlspecialchars($subjectInfo['SubjectCode']) ?>
                                                </h5>
                                                <p class="text-muted mb-0">
                                                    <?= date('M j, Y', strtotime($date)) ?> • <?= $studentCount ?> Students
                                                    <span class="badge bg-info ms-2">Update Mode</span>
                                                </p>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary" onclick="cancelUpdate()">
                                                <i data-lucide="x" class="me-1"></i> Cancel
                                            </button>
                                        </div>

                                        <div class="d-flex gap-2 mb-3">
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="markAllPresent()">
                                                <i data-lucide="check-circle" class="me-1"></i> All Present
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAllAbsent()">
                                                <i data-lucide="x-circle" class="me-1"></i> All Absent
                                            </button>
                                        </div>

                                        <!-- Student List -->
                                        <form method="POST" id="attendanceForm" onsubmit="return submitAttendanceForm()">
                                            <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">
                                            <input type="hidden" name="semester" value="<?= $selectedSemesterID ?>">
                                            <input type="hidden" name="subject" value="<?= $selectedSubjectID ?>">
                                            <input type="hidden" name="date" value="<?= $date ?>">

                                            <div class="students-list">
                                                <?php
                                                $students->data_seek(0);
                                                $index = 1;
                                                while ($student = $students->fetch_assoc()):
                                                    $studentID = $student['StudentID'];
                                                    $currentData = $existingAttendance[$studentID] ?? null;
                                                    $currentStatus = $currentData['status'] ?? null;
                                                ?>
                                                    <div class="student-row" data-student-id="<?= $studentID ?>">
                                                        <div class="student-avatar">
                                                            <?= strtoupper(substr($student['FullName'], 0, 2)) ?>
                                                        </div>
                                                        <div class="student-info">
                                                            <div class="student-name"><?= htmlspecialchars($student['FullName']) ?></div>
                                                            <?php if ($student['ProgramCode']): ?>
                                                                <small class="text-muted"><?= htmlspecialchars($student['ProgramCode']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="attendance-controls">
                                                            <input type="radio" class="btn-check" name="attendance[<?= $studentID ?>]" id="present_<?= $studentID ?>" value="present" <?= $currentStatus === 'present' ? 'checked' : '' ?> required>
                                                            <label class="btn btn-outline-success" for="present_<?= $studentID ?>">Present</label>

                                                            <input type="radio" class="btn-check" name="attendance[<?= $studentID ?>]" id="absent_<?= $studentID ?>" value="absent" <?= $currentStatus === 'absent' ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-danger" for="absent_<?= $studentID ?>">Absent</label>

                                                            <input type="radio" class="btn-check" name="attendance[<?= $studentID ?>]" id="late_<?= $studentID ?>" value="late" <?= $currentStatus === 'late' ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-warning" for="late_<?= $studentID ?>">Late</label>
                                                        </div>
                                                    </div>
                                                <?php
                                                    $index++;
                                                endwhile;
                                                ?>
                                            </div>

                                            <!-- Stats and Submit -->
                                            <div class="row g-3 mt-3">
                                                <div class="col-md-6">
                                                    <div class="stats-summary d-flex justify-content-around text-center">
                                                        <div>
                                                            <span class="stats-number text-success" id="presentCount">0</span>
                                                            <small class="d-block">Present</small>
                                                        </div>
                                                        <div>
                                                            <span class="stats-number text-danger" id="absentCount">0</span>
                                                            <small class="d-block">Absent</small>
                                                        </div>
                                                        <div>
                                                            <span class="stats-number text-warning" id="lateCount">0</span>
                                                            <small class="d-block">Late</small>
                                                        </div>
                                                        <div>
                                                            <span class="stats-number text-primary"><?= $studentCount ?></span>
                                                            <small class="d-block">Total</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 text-end">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i data-lucide="save" class="me-1"></i>
                                                        Update Attendance
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Right - QR Code Generator (in update mode) -->
                            <div class="col-lg-4">
                                <div class="card equal-height-card qr-container-fixed">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">
                                            <i data-lucide="qr-code" class="me-2"></i>
                                            QR Attendance
                                        </h5>
                                        <p class="text-muted">Students scan to mark attendance</p>

                                        <div id="qrContainer" class="text-center">
                                            <div id="qrPlaceholder" class="qr-placeholder">
                                                <i data-lucide="qr-code" style="width: 80px; height: 80px;" class="text-muted"></i>
                                                <p class="text-muted mt-2">Click Generate QR</p>
                                            </div>
                                            <canvas id="qrCanvas" style="display: none; max-width: 100%;"></canvas>
                                        </div>

                                        <div class="d-grid gap-2 mt-3">
                                            <button type="button" class="btn btn-primary" onclick="generateQR()">
                                                <i data-lucide="refresh-cw" class="me-1"></i>
                                                <span id="qrButtonText">Generate QR Code</span>
                                            </button>
                                            <div id="qrTimer" class="text-center text-muted" style="display: none;">
                                                <small>Expires in: <span id="countdown">60</span>s</small>
                                            </div>
                                        </div>

                                        <div class="alert alert-info mt-3">
                                            <i data-lucide="info" class="me-1"></i>
                                            <small>QR codes expire every 60 seconds. Students who scan will be marked Present automatically.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- NEW ATTENDANCE MARKING VIEW -->
                    <div class="row g-4" id="markingMode">
                        <!-- Left Side - Student List -->
                        <div class="col-lg-8">
                            <div class="card equal-height-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h5 class="card-title mb-1"><?= htmlspecialchars($subjectInfo['SubjectCode']) ?> - <?= htmlspecialchars($subjectInfo['SubjectName']) ?></h5>
                                            <p class="text-muted mb-0">
                                                <?= date('M j, Y', strtotime($date)) ?> • <?= $studentCount ?> Students
                                            </p>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="markAllPresent()">
                                                <i data-lucide="check-circle" class="me-1"></i> All Present
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAllAbsent()">
                                                <i data-lucide="x-circle" class="me-1"></i> All Absent
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Student List -->
                                    <form method="POST" id="attendanceForm" onsubmit="return submitAttendanceForm()">
                                        <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">
                                        <input type="hidden" name="semester" value="<?= $selectedSemesterID ?>">
                                        <input type="hidden" name="subject" value="<?= $selectedSubjectID ?>">
                                        <input type="hidden" name="date" value="<?= $date ?>">

                                        <div class="students-list">
                                            <?php
                                            $students->data_seek(0);
                                            $index = 1;
                                            while ($student = $students->fetch_assoc()):
                                                $studentID = $student['StudentID'];
                                            ?>
                                                <div class="student-row" data-student-id="<?= $studentID ?>">
                                                    <div class="student-avatar">
                                                        <?= strtoupper(substr($student['FullName'], 0, 2)) ?>
                                                    </div>
                                                    <div class="student-info">
                                                        <div class="student-name"><?= htmlspecialchars($student['FullName']) ?></div>
                                                        <?php if ($student['ProgramCode']): ?>
                                                            <small class="text-muted"><?= htmlspecialchars($student['ProgramCode']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="attendance-controls">
                                                        <input type="radio" class="btn-check" name="attendance[<?= $studentID ?>]" id="present_<?= $studentID ?>" value="present" required>
                                                        <label class="btn btn-outline-success" for="present_<?= $studentID ?>">Present</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $studentID ?>]" id="absent_<?= $studentID ?>" value="absent" checked>
                                                        <label class="btn btn-outline-danger" for="absent_<?= $studentID ?>">Absent</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?= $studentID ?>]" id="late_<?= $studentID ?>" value="late">
                                                        <label class="btn btn-outline-warning" for="late_<?= $studentID ?>">Late</label>
                                                    </div>
                                                </div>
                                            <?php
                                                $index++;
                                            endwhile;
                                            ?>
                                        </div>

                                        <!-- Stats and Submit -->
                                        <div class="row g-3 mt-3">
                                            <div class="col-md-6">
                                                <div class="stats-summary d-flex justify-content-around text-center">
                                                    <div>
                                                        <span class="stats-number text-success" id="presentCount">0</span>
                                                        <small class="d-block">Present</small>
                                                    </div>
                                                    <div>
                                                        <span class="stats-number text-danger" id="absentCount"><?= $studentCount ?></span>
                                                        <small class="d-block">Absent</small>
                                                    </div>
                                                    <div>
                                                        <span class="stats-number text-warning" id="lateCount">0</span>
                                                        <small class="d-block">Late</small>
                                                    </div>
                                                    <div>
                                                        <span class="stats-number text-primary"><?= $studentCount ?></span>
                                                        <small class="d-block">Total</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i data-lucide="save" class="me-1"></i>
                                                    Save Attendance
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side - QR Code -->
                        <div class="col-lg-4">
                            <div class="card equal-height-card qr-container-fixed">
                                <div class="card-body text-center">
                                    <h5 class="card-title">
                                        <i data-lucide="qr-code" class="me-2"></i>
                                        QR Attendance
                                    </h5>
                                    <p class="text-muted">Students scan to mark attendance</p>

                                    <div id="qrContainer" class="text-center">
                                        <div id="qrPlaceholder" class="qr-placeholder">
                                            <i data-lucide="qr-code" style="width: 80px; height: 80px;" class="text-muted"></i>
                                            <p class="text-muted mt-2">Click Generate QR</p>
                                        </div>
                                        <canvas id="qrCanvas" style="display: none; max-width: 100%;"></canvas>
                                    </div>

                                    <div class="d-grid gap-2 mt-3">
                                        <button type="button" class="btn btn-primary" onclick="generateQR()">
                                            <i data-lucide="refresh-cw" class="me-1"></i>
                                            <span id="qrButtonText">Generate QR Code</span>
                                        </button>
                                        <div id="qrTimer" class="text-center text-muted" style="display: none;">
                                            <small>Expires in: <span id="countdown">60</span>s</small>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <i data-lucide="info" class="me-1"></i>
                                        <small>QR codes expire every 60 seconds. Students who scan will be marked Present automatically.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="card equal-height-card text-center">
                <div class="card-body py-5">
                    <div class="empty-icon mb-3">
                        <i data-lucide="clipboard-list" style="width: 80px; height: 80px;" class="text-muted"></i>
                    </div>
                    <h4>Select All Fields</h4>
                    <p class="text-muted">Please fill all fields above to start taking attendance</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        let qrTimer = null;
        let qrCountdown = 60;

        function handleFormChange() {
            document.getElementById('selectionForm').submit();
        }

        function switchToUpdateMode() {
            const completedView = document.querySelector('.completed-view');
            const updateSection = document.getElementById('updateForm');

            if (completedView) {
                completedView.classList.add('hide');
            }

            setTimeout(() => {
                if (completedView) {
                    completedView.style.display = 'none';
                }
                if (updateSection) {
                    updateSection.style.display = 'block';
                    updateSection.classList.add('show');
                }
            }, 300);
        }

        function cancelUpdate() {
            const completedView = document.querySelector('.completed-view');
            const updateSection = document.getElementById('updateForm');

            if (updateSection) {
                updateSection.classList.remove('show');
                setTimeout(() => {
                    updateSection.style.display = 'none';
                    if (completedView) {
                        completedView.style.display = 'block';
                        completedView.classList.remove('hide');
                    }
                }, 300);
            }
        }

        function markAllPresent() {
            const buttons = document.querySelectorAll('input[value="present"]');
            buttons.forEach(radio => {
                radio.checked = true;
                updateButtonStates(radio);
            });
            updateStats();
            showToast('All students marked as Present', 'success');
        }

        function markAllAbsent() {
            const buttons = document.querySelectorAll('input[value="absent"]');
            buttons.forEach(radio => {
                radio.checked = true;
                updateButtonStates(radio);
            });
            updateStats();
            showToast('All students marked as Absent', 'warning');
        }

        function updateButtonStates(radio) {
            const row = radio.closest('.student-row');
            if (!row) return;

            const labels = row.querySelectorAll('label');

            // Remove active state from all buttons in this row
            // Remove active state from all buttons in this row
            labels.forEach(label => {
                label.classList.remove('btn-success', 'btn-danger', 'btn-warning');
                label.classList.add('btn-outline-success', 'btn-outline-danger', 'btn-outline-warning');
            });

            // Add active state to selected button
            if (radio.checked) {
                const selectedLabel = row.querySelector(`label[for="${radio.id}"]`);
                if (selectedLabel) {
                    if (radio.value === 'present') {
                        selectedLabel.classList.remove('btn-outline-success');
                        selectedLabel.classList.add('btn-success');
                    } else if (radio.value === 'absent') {
                        selectedLabel.classList.remove('btn-outline-danger');
                        selectedLabel.classList.add('btn-danger');
                    } else if (radio.value === 'late') {
                        selectedLabel.classList.remove('btn-outline-warning');
                        selectedLabel.classList.add('btn-warning');
                    }
                }
            }
        }

        function updateStats() {
            const presentCount = document.querySelectorAll('input[value="present"]:checked').length;
            const absentCount = document.querySelectorAll('input[value="absent"]:checked').length;
            const lateCount = document.querySelectorAll('input[value="late"]:checked').length;

            document.getElementById('presentCount').textContent = presentCount;
            document.getElementById('absentCount').textContent = absentCount;
            document.getElementById('lateCount').textContent = lateCount;
        }

        function generateQR() {
            const button = document.querySelector('button[onclick="generateQR()"]');
            const originalText = button.innerHTML;

            // Add loading state
            button.classList.add('btn-loading');
            button.innerHTML = '<span>Generating...</span>';
            button.disabled = true;

            const formData = new FormData();
            formData.append('generate_qr', '1');
            formData.append('semester', '<?= $selectedSemesterID ?>');
            formData.append('subject', '<?= $selectedSubjectID ?>');
            formData.append('date', '<?= $date ?>');

            fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayQR(data.qr_token);
                        startQRTimer();
                        showToast('QR Code generated successfully!', 'success');
                    } else {
                        showToast('Failed to generate QR: ' + (data.error || 'Unknown error'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to generate QR code.', 'danger');
                })
                .finally(() => {
                    // Remove loading state
                    button.classList.remove('btn-loading');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        function displayQR(token) {
            const canvas = document.getElementById('qrCanvas');
            const placeholder = document.getElementById('qrPlaceholder');

            const qrData = `${window.location.origin}/attendifyplus/views/scan.php?token=${token}`;

            QRCode.toCanvas(canvas, qrData, {
                width: 250,
                margin: 2,
                color: {
                    dark: '#1A73E8',
                    light: '#FFFFFF'
                }
            }, function(error) {
                if (error) {
                    console.error('QR Error:', error);
                    return;
                }

                placeholder.style.display = 'none';
                canvas.style.display = 'block';
            });
        }

        function startQRTimer() {
            document.getElementById('qrTimer').style.display = 'block';
            document.getElementById('qrButtonText').textContent = 'Regenerate QR';

            qrCountdown = 60;

            qrTimer = setInterval(() => {
                qrCountdown--;
                document.getElementById('countdown').textContent = qrCountdown;

                if (qrCountdown <= 0) {
                    clearInterval(qrTimer);
                    resetQR();
                }
            }, 1000);
        }

        function resetQR() {
            document.getElementById('qrCanvas').style.display = 'none';
            document.getElementById('qrPlaceholder').style.display = 'block';
            document.getElementById('qrTimer').style.display = 'none';
            document.getElementById('qrButtonText').textContent = 'Generate QR Code';

            if (qrTimer) {
                clearInterval(qrTimer);
            }
        }

        // Enhanced form submission
        function submitAttendanceForm() {
            const form = document.getElementById('attendanceForm');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;

            // Add loading state
            submitButton.classList.add('btn-loading');
            submitButton.innerHTML = '<span>Saving...</span>';
            submitButton.disabled = true;

            // Let the form submit naturally
            return true;
        }

        // Initialize functionality on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateStats();

            // Set up radio button listeners with improved state management
            document.querySelectorAll('input[type="radio"][name^="attendance"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateButtonStates(this);
                    updateStats();
                });

                // Set initial states based on existing selection
                if (radio.checked) {
                    updateButtonStates(radio);
                }
            });

            // SIDEBAR FUNCTIONALITY
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');

            if (sidebarToggle && sidebar && sidebarOverlay) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();

                    if (sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        document.body.classList.remove('sidebar-open');
                    } else {
                        sidebar.classList.add('active');
                        sidebarOverlay.classList.add('active');
                        document.body.classList.add('sidebar-open');
                    }
                });

                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        document.body.classList.remove('sidebar-open');
                    }
                });
            }

            // DARK MODE FUNCTIONALITY
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function(e) {
                    e.preventDefault();

                    document.body.classList.toggle('dark-mode');

                    const isDarkMode = document.body.classList.contains('dark-mode');
                    localStorage.setItem('darkMode', isDarkMode);

                    const icon = this.querySelector('i');
                    if (icon) {
                        if (isDarkMode) {
                            icon.setAttribute('data-lucide', 'sun');
                        } else {
                            icon.setAttribute('data-lucide', 'moon');
                        }
                        lucide.createIcons();
                    }
                });

                // Load saved preference
                const savedDarkMode = localStorage.getItem('darkMode');
                if (savedDarkMode === 'true') {
                    document.body.classList.add('dark-mode');
                    const icon = darkModeToggle.querySelector('i');
                    if (icon) {
                        icon.setAttribute('data-lucide', 'sun');
                    }
                }
            }

            // Auto-close sidebar on resize
            function handleResize() {
                if (window.innerWidth >= 1200) {
                    if (!document.body.classList.contains('sidebar-open')) {
                        sidebar?.classList.remove('active');
                        sidebarOverlay?.classList.remove('active');
                    }
                } else {
                    document.body.classList.remove('sidebar-open');
                }
            }

            window.addEventListener('resize', handleResize);
            handleResize();

            // Final icon refresh
            setTimeout(() => {
                lucide.createIcons();
            }, 100);
        });
    </script>
</body>

</html>