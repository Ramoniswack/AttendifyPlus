<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\student\scan_qr.php
session_start();
require_once(__DIR__ . '/../../config/db_config.php');

// Check if user is logged in as student
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get student info - FIXED: Get email from login_tbl, not students table
$studentStmt = $conn->prepare("
    SELECT s.StudentID, s.FullName, s.DeviceRegistered, l.Email 
    FROM students s 
    JOIN login_tbl l ON s.LoginID = l.LoginID 
    WHERE s.LoginID = ?
");
$studentStmt->bind_param("i", $loginID);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
$student = $studentRes->fetch_assoc();

if (!$student) {
    header("Location: logout.php");
    exit();
}

// Get recent attendance records
$recentQuery = $conn->prepare("
    SELECT ar.DateTime, ar.Status, ar.Method, s.SubjectName, s.SubjectCode, t.FullName as TeacherName
    FROM attendance_records ar
    JOIN subjects s ON ar.SubjectID = s.SubjectID
    JOIN teachers t ON ar.TeacherID = t.TeacherID
    WHERE ar.StudentID = ?
    ORDER BY ar.DateTime DESC
    LIMIT 5
");
$recentQuery->bind_param("i", $student['StudentID']);
$recentQuery->execute();
$recentAttendance = $recentQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle direct token from URL
$urlToken = $_GET['token'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/attendance.css">
    <link rel="stylesheet" href="../../assets/css/scan_qr.css">

    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
</head>

<body>
    <!-- Include navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap mb-4">
            <div>
                <h2 class="page-title">
                    <i data-lucide="qr-code"></i>
                    QR Code Scanner
                </h2>
                <p class="text-muted mb-0">Scan QR codes to mark your attendance</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="dashboard_student.php" class="btn btn-outline-primary">
                    <i data-lucide="arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Student Info & Instructions -->
            <div class="col-lg-4">
                <!-- Student Info Card -->
                <div class="student-info-card fade-in mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="student-avatar">
                            <i data-lucide="user" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($student['FullName']) ?></h6>
                            <small class="text-muted">ID: <?= $student['StudentID'] ?></small>
                            <small class="text-muted d-block"><?= htmlspecialchars($student['Email']) ?></small>
                            <div class="mt-1">
                                <?php if ($student['DeviceRegistered']): ?>
                                    <span class="badge bg-success">
                                        <i data-lucide="check" style="width: 12px; height: 12px;"></i>
                                        Device Registered
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i data-lucide="alert-triangle" style="width: 12px; height: 12px;"></i>
                                        Device Not Registered
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions Card -->
                <div class="instructions-card fade-in mb-4">
                    <h6><i data-lucide="help-circle" class="me-2"></i>How to Scan</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="instruction-step">
                                <div class="step-icon">
                                    <span class="fw-bold">1</span>
                                </div>
                                <h6>Start Scanner</h6>
                                <small class="text-muted">Click "Start Scanning" button</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="instruction-step">
                                <div class="step-icon">
                                    <span class="fw-bold">2</span>
                                </div>
                                <h6>Point Camera</h6>
                                <small class="text-muted">Aim at teacher's QR code</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="instruction-step">
                                <div class="step-icon">
                                    <span class="fw-bold">3</span>
                                </div>
                                <h6>Auto Mark</h6>
                                <small class="text-muted">Attendance marked automatically</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Scans Card -->
                <div class="recent-scans-card fade-in">
                    <h6><i data-lucide="clock" class="me-2"></i>Recent Attendance</h6>
                    <?php if (!empty($recentAttendance)): ?>
                        <?php foreach ($recentAttendance as $record): ?>
                            <div class="scan-item">
                                <div class="scan-icon <?= $record['Status'] === 'present' ? 'success' : 'error' ?>">
                                    <i data-lucide="<?= $record['Status'] === 'present' ? 'check' : 'x' ?>" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-medium small"><?= htmlspecialchars($record['SubjectCode']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <?= date('M j, g:i A', strtotime($record['DateTime'])) ?>
                                        <?php if ($record['Method'] === 'qr'): ?>
                                            <span class="badge bg-info ms-1" style="font-size: 0.6rem;">QR</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i data-lucide="calendar-x" class="text-muted mb-2" style="width: 32px; height: 32px;"></i>
                            <p class="text-muted small mb-0">No recent attendance records</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Scanner -->
            <div class="col-lg-8">
                <div class="scanner-card fade-in">
                    <!-- Scanner Status -->
                    <div class="scanner-status">
                        <div class="status-indicator" id="statusIndicator">
                            <i data-lucide="camera" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h5 id="statusTitle">Camera Ready</h5>
                        <p class="text-muted mb-0" id="statusMessage">Click "Start Scanning" to begin</p>
                    </div>

                    <!-- Scanner Container -->
                    <div class="scanner-container">
                        <div id="qr-reader"></div>

                        <!-- Scanner Overlay -->
                        <div class="scanner-overlay">
                            <div class="scanner-frame">
                                <div class="corner top-left"></div>
                                <div class="corner top-right"></div>
                                <div class="corner bottom-left"></div>
                                <div class="corner bottom-right"></div>
                                <div class="scanner-line"></div>
                                <div class="scanner-text">Position QR code within frame</div>
                            </div>
                        </div>
                    </div>

                    <!-- Scanner Controls -->
                    <div class="scanner-controls">
                        <button type="button" class="btn btn-success" id="startScanBtn">
                            <i data-lucide="play"></i> Start Scanning
                        </button>
                        <button type="button" class="btn btn-danger" id="stopScanBtn" style="display: none;">
                            <i data-lucide="square"></i> Stop Scanning
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="switchCameraBtn" style="display: none;">
                            <i data-lucide="refresh-cw"></i> Switch Camera
                        </button>
                    </div>

                    <!-- Manual Input Section -->
                    <div class="manual-input-section">
                        <h6><i data-lucide="keyboard" class="me-2"></i>Manual Code Entry</h6>
                        <p class="text-muted small">If scanning doesn't work, you can manually enter the attendance code</p>
                        <div class="input-group">
                            <input type="text" class="form-control" id="manualCodeInput" placeholder="Enter attendance code...">
                            <button class="btn btn-primary" type="button" id="submitManualCode">
                                <i data-lucide="send"></i> Submit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="success-icon mb-3">
                        <i data-lucide="check-circle" style="width: 48px; height: 48px; color: #28a745;"></i>
                    </div>
                    <h5 class="mb-2">Attendance Marked!</h5>
                    <p class="text-muted mb-3" id="successMessage">Your attendance has been recorded successfully</p>
                    <div class="border rounded p-3 mb-3">
                        <div class="row">
                            <div class="col-sm-4">
                                <strong>Subject:</strong>
                            </div>
                            <div class="col-sm-8" id="successSubject">-</div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-sm-4">
                                <strong>Time:</strong>
                            </div>
                            <div class="col-sm-8" id="successTime">-</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/scan_qr.js"></script>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Auto-process URL token if present
        <?php if ($urlToken): ?>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Auto-processing URL token: <?= $urlToken ?>');
                processAttendance('<?= $urlToken ?>');
            });
        <?php endif; ?>

        // Apply theme
        if (localStorage.getItem("theme") === "dark") {
            document.body.classList.add("dark-mode");
        }
    </script>
</body>

</html>