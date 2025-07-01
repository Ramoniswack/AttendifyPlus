<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\scan_qr.php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: login.php");
    exit();
}

include '../../config/db_config.php';

// Get student information - FIXED QUERY
$studentQuery = "SELECT s.StudentID, s.FullName, s.Contact, s.Address, s.ProgramCode, s.JoinYear,
                        d.DepartmentName, d.DepartmentCode, 
                        sem.SemesterNumber,
                        l.Email
                FROM students s 
                JOIN departments d ON s.DepartmentID = d.DepartmentID 
                JOIN semesters sem ON s.SemesterID = sem.SemesterID 
                JOIN login_tbl l ON s.LoginID = l.LoginID
                WHERE s.LoginID = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("i", $_SESSION['LoginID']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student information not found. Please contact administrator.");
}

// Get student's subjects for current semester
$subjectsQuery = "SELECT s.SubjectID, s.SubjectCode, s.SubjectName, s.CreditHour
                 FROM subjects s 
                 WHERE s.DepartmentID = (SELECT DepartmentID FROM students WHERE LoginID = ?) 
                 AND s.SemesterID = (SELECT SemesterID FROM students WHERE LoginID = ?)
                 ORDER BY s.SubjectCode";
$stmt = $conn->prepare($subjectsQuery);
$stmt->bind_param("ii", $_SESSION['LoginID'], $_SESSION['LoginID']);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent attendance records
$recentAttendanceQuery = "SELECT ar.DateTime, ar.Status, ar.Method, 
                                s.SubjectCode, s.SubjectName, 
                                t.FullName as TeacherName
                         FROM attendance_records ar
                         JOIN subjects s ON ar.SubjectID = s.SubjectID
                         JOIN teachers t ON ar.TeacherID = t.TeacherID
                         WHERE ar.StudentID = ?
                         ORDER BY ar.DateTime DESC
                         LIMIT 3";
$stmt = $conn->prepare($recentAttendanceQuery);
$stmt->bind_param("i", $student['StudentID']);
$stmt->execute();
$recentAttendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/scan_qr.css">

    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap mb-4">
            <div>
                <h2 class="page-title">
                    <i data-lucide="qr-code"></i>
                    QR Code Scanner
                </h2>
                <p class="text-muted mb-0">Scan QR codes to mark your attendance</p>
            </div>
        </div>

        <!-- Student Info Card -->
        <div class="row justify-content-center mb-4">
            <div class="col-lg-8">
                <div class="student-info-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="student-avatar">
                            <i data-lucide="user" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1"><?= htmlspecialchars($student['FullName']) ?></h5>
                            <div class="text-muted">
                                <span class="me-3">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                    <?= htmlspecialchars($student['DepartmentName']) ?> - Semester <?= $student['SemesterNumber'] ?>
                                </span>
                                <?php if ($student['ProgramCode']): ?>
                                    <span class="me-3">
                                        <i data-lucide="tag" style="width: 14px; height: 14px;"></i>
                                        <?= htmlspecialchars($student['ProgramCode']) ?>
                                    </span>
                                <?php endif; ?>
                                <span>
                                    <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                    Joined <?= $student['JoinYear'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scanner Card -->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="scanner-card">
                    <!-- Scanner Status -->
                    <div class="scanner-status">
                        <div id="statusIndicator" class="status-indicator">
                            <i data-lucide="camera" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h5 id="statusTitle">Initializing Scanner...</h5>
                        <p id="statusMessage" class="text-muted mb-0">Please wait while we set up your camera</p>
                    </div>

                    <!-- Scanner Container -->
                    <div id="scannerContainer" class="scanner-container">
                        <div id="qr-reader" class="qr-reader"></div>

                        <!-- Scanner Overlay -->
                        <div class="scanner-overlay">
                            <div class="scanner-frame">
                                <div class="corner top-left"></div>
                                <div class="corner top-right"></div>
                                <div class="corner bottom-left"></div>
                                <div class="corner bottom-right"></div>
                                <div class="scanner-line"></div>
                            </div>
                            <div class="scanner-text">
                                Point your camera at the QR code
                            </div>
                        </div>
                    </div>

                    <!-- Scanner Controls -->
                    <div class="scanner-controls">
                        <button id="startScanBtn" class="btn btn-primary btn-lg">
                            <i data-lucide="play"></i> Start Scanning
                        </button>
                        <button id="stopScanBtn" class="btn btn-outline-secondary btn-lg" style="display: none;">
                            <i data-lucide="pause"></i> Stop Scanning
                        </button>
                        <button id="switchCameraBtn" class="btn btn-outline-info" style="display: none;" title="Switch Camera">
                            <i data-lucide="rotate-cw"></i> Switch Camera
                        </button>
                    </div>

                    <!-- Manual Input Section -->
                    <div class="manual-input-section">
                        <h6>
                            <i data-lucide="keyboard"></i>
                            Manual Code Entry
                        </h6>
                        <p class="text-muted mb-3">If scanning doesn't work, enter the code manually</p>
                        <div class="input-group">
                            <input type="text" id="manualCodeInput" class="form-control"
                                placeholder="Enter attendance code..."
                                autocomplete="off">
                            <button id="submitManualCode" class="btn btn-success">
                                <i data-lucide="send"></i> Submit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions and Recent Activity -->
        <div class="row justify-content-center mt-4">
            <!-- Instructions -->
            <div class="col-lg-6 col-md-8 mb-4">
                <div class="instructions-card">
                    <h6>
                        <i data-lucide="help-circle"></i>
                        How to Scan QR Code
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="instruction-step">
                                <div class="step-icon">
                                    <i data-lucide="camera" style="width: 20px; height: 20px;"></i>
                                </div>
                                <h6>Allow Camera</h6>
                                <p class="text-muted small">Grant camera permission when prompted</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="instruction-step">
                                <div class="step-icon">
                                    <i data-lucide="play" style="width: 20px; height: 20px;"></i>
                                </div>
                                <h6>Start Scanner</h6>
                                <p class="text-muted small">Tap "Start Scanning" button</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="instruction-step">
                                <div class="step-icon">
                                    <i data-lucide="qr-code" style="width: 20px; height: 20px;"></i>
                                </div>
                                <h6>Scan Code</h6>
                                <p class="text-muted small">Point camera at QR code</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="col-lg-6 col-md-8 mb-4">
                <div class="recent-scans-card">
                    <h6>
                        <i data-lucide="clock"></i>
                        Recent Attendance
                    </h6>
                    <?php if (empty($recentAttendance)): ?>
                        <div class="text-center py-4">
                            <i data-lucide="calendar-x" style="width: 32px; height: 32px; opacity: 0.5;"></i>
                            <p class="text-muted mt-2 mb-0">No recent attendance records</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentAttendance as $record): ?>
                            <div class="scan-item">
                                <div class="scan-icon <?= $record['Status'] == 'present' ? 'success' : 'error' ?>">
                                    <i data-lucide="<?= $record['Status'] == 'present' ? 'check' : 'x' ?>" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-medium"><?= htmlspecialchars($record['SubjectCode']) ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($record['SubjectName']) ?> -
                                        <?= ucfirst($record['Status']) ?>
                                        <?php if ($record['Method'] == 'qr'): ?>
                                            <span class="badge bg-primary ms-1">QR</span>
                                        <?php endif; ?>
                                    </small>
                                    <div class="small text-muted">
                                        <?= date('M j, Y g:i A', strtotime($record['DateTime'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- My Subjects -->
        <?php if (!empty($subjects)): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="instructions-card">
                        <h6>
                            <i data-lucide="book-open"></i>
                            My Subjects (Semester <?= $student['SemesterNumber'] ?>)
                        </h6>
                        <div class="row g-3">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                                        <i data-lucide="book" style="width: 16px; height: 16px;" class="text-primary"></i>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($subject['SubjectCode']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($subject['SubjectName']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i data-lucide="check-circle"></i>
                        Attendance Marked!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <div class="success-icon mb-3">
                            <i data-lucide="check-circle" style="width: 64px; height: 64px;" class="text-success"></i>
                        </div>
                        <h5 id="successSubject">Subject Name</h5>
                        <p id="successMessage" class="text-muted">Your attendance has been recorded successfully!</p>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                <span id="successTime"></span>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        <i data-lucide="thumbs-up"></i> Great!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/scan_qr.js"></script>

    <!-- Pass student data to JavaScript -->
    <script>
        window.studentData = {
            studentId: <?= $student['StudentID'] ?>,
            fullName: '<?= htmlspecialchars($student['FullName']) ?>',
            department: '<?= htmlspecialchars($student['DepartmentName']) ?>',
            semester: <?= $student['SemesterNumber'] ?>
        };
    </script>
</body>

</html>