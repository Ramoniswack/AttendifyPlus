<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: login.php");
    exit();
}

include '../../config/db_config.php';

// Get student information
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

// Get recent attendance records (simplified)
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

// Get QR token if provided in URL
$qrToken = isset($_GET['token']) ? $_GET['token'] : null;
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR Scanner | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/scan_qr.css">

    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <!-- Mobile optimizations -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#3b82f6">
</head>

<body class="scanner-body" data-bs-theme="light">
    <!-- Mobile Full-Screen Scanner -->
    <div id="fullscreenScanner" class="fullscreen-scanner" style="display: none;">
        <div class="fullscreen-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="text-white mb-0 fw-bold">
                    <i data-lucide="qr-code"></i>
                    Scan QR Code
                </h6>
                <div class="d-flex gap-2">
                    <button id="fullscreenFlashBtn" class="btn btn-sm btn-outline-light rounded-pill" style="display: none;">
                        <i data-lucide="flashlight"></i>
                    </button>
                    <button id="fullscreenSwitchBtn" class="btn btn-sm btn-outline-light rounded-pill" style="display: none;">
                        <i data-lucide="rotate-cw"></i>
                    </button>
                    <button id="exitFullscreenBtn" class="btn btn-sm btn-light rounded-pill">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="fullscreen-camera-container">
            <div id="fullscreen-qr-reader" class="fullscreen-qr-reader"></div>
            <div class="fullscreen-overlay">
                <div class="scanner-target">
                    <div class="corner-frame">
                        <div class="corner top-left"></div>
                        <div class="corner top-right"></div>
                        <div class="corner bottom-left"></div>
                        <div class="corner bottom-right"></div>
                    </div>
                    <div class="scanning-line"></div>
                </div>
                <div class="scan-instructions">
                    <h5 class="text-white fw-bold mb-2">Point at QR Code</h5>
                    <p class="text-white-50 mb-0">Hold steady and center the QR code in the frame</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Mobile Quick Action Bar -->
        <div class="mobile-quick-actions d-block d-lg-none mb-3">
            <div class="d-flex gap-2">
                <button id="mobileStartScanBtn" class="btn btn-primary flex-grow-1 rounded-pill py-3">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <i data-lucide="camera" style="width: 24px; height: 24px;"></i>
                        <span class="fw-bold">Start Scanning</span>
                    </div>
                </button>
            </div>
        </div>

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

        <!-- Hidden input for student ID -->
        <input type="hidden" id="student-id" value="<?= $student['StudentID'] ?>">

        <!-- Student Info Card - Simplified -->
        <div class="row justify-content-center mb-4">
            <div class="col-12">
                <div class="student-info-card-modern">
                    <div class="student-profile">
                        <div class="student-avatar-modern">
                            <i data-lucide="user" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div class="student-details">
                            <h6 class="student-name"><?= htmlspecialchars($student['FullName']) ?></h6>
                            <div class="student-meta">
                                <span class="department-badge">
                                    <?= htmlspecialchars($student['DepartmentCode']) ?> - Sem <?= $student['SemesterNumber'] ?>
                                </span>
                                <span class="join-year"><?= $student['JoinYear'] ?></span>
                            </div>
                        </div>
                        <div class="scan-status" id="scanStatus">
                            <div class="status-indicator-modern" id="statusIndicatorModern">
                                <i data-lucide="camera" style="width: 16px; height: 16px;"></i>
                            </div>
                            <span class="status-text" id="statusTextModern">Ready</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Scanner Section - Desktop Only -->
        <div class="row justify-content-center d-none d-lg-block">
            <div class="col-lg-8 col-md-10">
                <div class="scanner-card-modern">
                    <!-- Scanner Header -->
                    <div class="scanner-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">QR Code Scanner</h5>
                                <p class="text-muted mb-0">Scan attendance QR codes</p>
                            </div>
                            <div class="scanner-controls-desktop d-none d-lg-flex">
                                <button id="startScanBtn" class="btn btn-primary">
                                    <i data-lucide="play"></i> Start
                                </button>
                                <button id="stopScanBtn" class="btn btn-outline-secondary" style="display: none;">
                                    <i data-lucide="pause"></i> Stop
                                </button>
                                <button id="switchCameraBtn" class="btn btn-outline-info" style="display: none;">
                                    <i data-lucide="rotate-cw"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Scanner Container -->
                    <div id="scannerContainer" class="scanner-container-desktop">
                        <div id="qr-reader" class="qr-reader-desktop"></div>
                        <div class="scanner-overlay-desktop" style="display: none;">
                            <div class="scanner-frame-desktop">
                                <div class="corner top-left"></div>
                                <div class="corner top-right"></div>
                                <div class="corner bottom-left"></div>
                                <div class="corner bottom-right"></div>
                                <div class="scanner-line-desktop"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Scanner Status -->
                    <div class="scanner-status-desktop">
                        <div class="d-flex align-items-center gap-3">
                            <div id="statusIndicator" class="status-indicator-desktop">
                                <i data-lucide="camera" style="width: 20px; height: 20px;"></i>
                            </div>
                            <div>
                                <h6 id="statusTitle" class="mb-0">Ready to Scan</h6>
                                <small id="statusMessage" class="text-muted">Click "Start" to begin scanning</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity (Mobile & Desktop) -->
        <div class="row justify-content-center mt-4">
            <div class="col-lg-8 col-md-10 col-12">
                <div class="activity-card">
                    <div class="activity-header">
                        <h6>
                            <i data-lucide="clock"></i>
                            Recent Attendance
                        </h6>
                        <span class="activity-count"><?= count($recentAttendance) ?></span>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recentAttendance)): ?>
                            <div class="activity-empty">
                                <i data-lucide="calendar-x" style="width: 32px; height: 32px;"></i>
                                <p class="text-muted mt-2 mb-0">No recent attendance records</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentAttendance as $record): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $record['Status'] == 'present' ? 'success' : 'error' ?>">
                                        <i data-lucide="<?= $record['Status'] == 'present' ? 'check' : 'x' ?>" style="width: 14px; height: 14px;"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?= htmlspecialchars($record['SubjectCode']) ?></div>
                                        <div class="activity-subtitle">
                                            <?= htmlspecialchars($record['SubjectName']) ?>
                                        </div>
                                        <div class="activity-time">
                                            <?= date('M j, g:i A', strtotime($record['DateTime'])) ?>
                                        </div>
                                    </div>
                                    <div class="activity-status <?= $record['Status'] ?>">
                                        <?= ucfirst($record['Status']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content success-modal-content">
                <div class="modal-body text-center p-4">
                    <div class="success-animation mb-3">
                        <div class="success-checkmark">
                            <div class="check-icon">
                                <span class="icon-line line-tip"></span>
                                <span class="icon-line line-long"></span>
                                <div class="icon-circle"></div>
                                <div class="icon-fix"></div>
                            </div>
                        </div>
                    </div>
                    <h4 id="successModalTitle" class="success-title mb-2">Attendance Marked!</h4>
                    <p id="successModalMessage" class="success-message text-muted mb-3">Your attendance has been recorded successfully</p>
                    <div class="success-details">
                        <div class="detail-item">
                            <strong id="successSubject">Subject Name</strong>
                        </div>
                        <div class="detail-item">
                            <small class="text-muted">
                                <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                <span id="successTime"></span>
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-lg rounded-pill mt-3" data-bs-dismiss="modal">
                        <i data-lucide="thumbs-up"></i>
                        Continue Scanning
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_student.js"></script>
    <script src="../../assets/js/scan_qr.js"></script>

    <!-- Pass student data to JavaScript -->
    <script>
        window.studentData = {
            studentId: <?= $student['StudentID'] ?>,
            fullName: '<?= htmlspecialchars($student['FullName']) ?>',
            department: '<?= htmlspecialchars($student['DepartmentName']) ?>',
            semester: <?= $student['SemesterNumber'] ?>,
            qrToken: <?= $qrToken ? "'" . htmlspecialchars($qrToken) . "'" : 'null' ?>
        };

        // Initialize theme and sidebar on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme from scan_qr.js
            if (typeof initializeTheme === 'function') {
                initializeTheme();
            }

            // Dashboard functionality is automatically initialized by dashboard_student.js
            console.log('Scan QR page initialized with dashboard functionality');
        });
    </script>
</body>

</html>