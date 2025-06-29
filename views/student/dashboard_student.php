<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

include '../../config/db_config.php';

// Get current student's StudentID
$currentStudentQuery = "SELECT StudentID, SemesterID, DepartmentID, FullName, DeviceRegistered FROM students WHERE LoginID = ?";
$stmt = $conn->prepare($currentStudentQuery);
$stmt->bind_param("i", $_SESSION['LoginID']);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();

if (!$studentData) {
    die("Student data not found. Please contact administrator.");
}

$currentStudentID = $studentData['StudentID'];
$currentSemesterID = $studentData['SemesterID'];
$currentDepartmentID = $studentData['DepartmentID'];
$student = $studentData;

// Check for pending device registration token
$tokenQuery = $conn->prepare("
    SELECT Token, ExpiresAt 
    FROM device_registration_tokens 
    WHERE StudentID = ? AND Used = FALSE AND ExpiresAt > NOW()
    ORDER BY CreatedAt DESC 
    LIMIT 1
");
$tokenQuery->bind_param("i", $student['StudentID']);
$tokenQuery->execute();
$pendingToken = $tokenQuery->get_result()->fetch_assoc();

// Check if device is already registered
$deviceQuery = $conn->prepare("
    SELECT COUNT(*) as device_count 
    FROM student_devices 
    WHERE StudentID = ? AND IsActive = TRUE
");
$deviceQuery->bind_param("i", $student['StudentID']);
$deviceQuery->execute();
$deviceCount = $deviceQuery->get_result()->fetch_assoc()['device_count'];
$hasRegisteredDevice = $deviceCount > 0;

// Fetch real-time statistics for student
$stats = [];

// Get student's enrolled subjects count
$subjectsQuery = "SELECT COUNT(*) as count 
                 FROM subjects s 
                 WHERE s.SemesterID = ? AND s.DepartmentID = ?";
$stmt = $conn->prepare($subjectsQuery);
$stmt->bind_param("ii", $currentSemesterID, $currentDepartmentID);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_subjects'] = $result->fetch_assoc()['count'];

// Get total attendance records for this student
$attendanceCountQuery = "SELECT COUNT(*) as count 
                        FROM attendance_records ar 
                        WHERE ar.StudentID = ?";
$stmt = $conn->prepare($attendanceCountQuery);
$stmt->bind_param("i", $currentStudentID);
$stmt->execute();
$result = $stmt->get_result();
$totalAttendanceRecords = $result->fetch_assoc()['count'];

// Calculate attendance percentage
if ($totalAttendanceRecords > 0) {
    $presentCountQuery = "SELECT COUNT(*) as count 
                         FROM attendance_records ar 
                         WHERE ar.StudentID = ? AND ar.Status = 'present'";
    $stmt = $conn->prepare($presentCountQuery);
    $stmt->bind_param("i", $currentStudentID);
    $stmt->execute();
    $result = $stmt->get_result();
    $presentCount = $result->fetch_assoc()['count'];
    $stats['attendance_percentage'] = round(($presentCount / $totalAttendanceRecords) * 100, 1);
} else {
    $stats['attendance_percentage'] = 0;
}

// Mock assignments data (since no assignments table exists)
$stats['pending_assignments'] = 5;
$stats['completed_assignments'] = 12;

// Get subject-wise attendance for charts
$subjectAttendanceQuery = "SELECT s.SubjectName, s.SubjectCode,
                          COUNT(CASE WHEN ar.Status = 'present' THEN 1 END) as present_count,
                          COUNT(ar.StudentID) as total_count
                          FROM subjects s 
                          LEFT JOIN attendance_records ar ON s.SubjectID = ar.SubjectID AND ar.StudentID = ?
                          WHERE s.SemesterID = ? AND s.DepartmentID = ?
                          GROUP BY s.SubjectID, s.SubjectName, s.SubjectCode
                          ORDER BY s.SubjectName";
$stmt = $conn->prepare($subjectAttendanceQuery);
$stmt->bind_param("iii", $currentStudentID, $currentSemesterID, $currentDepartmentID);
$stmt->execute();
$result = $stmt->get_result();
$subjectAttendanceData = [];
while ($row = $result->fetch_assoc()) {
    $percentage = $row['total_count'] > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
    $subjectAttendanceData[] = [
        'subject' => $row['SubjectCode'],
        'name' => $row['SubjectName'],
        'percentage' => $percentage,
        'present' => $row['present_count'],
        'total' => $row['total_count']
    ];
}

// Get weekly attendance data for the last 5 weeks (mock data)
$weeklyAttendanceData = [
    ['week' => 'Week 1', 'percentage' => 88],
    ['week' => 'Week 2', 'percentage' => 92],
    ['week' => 'Week 3', 'percentage' => 85],
    ['week' => 'Week 4', 'percentage' => 90],
    ['week' => 'Week 5', 'percentage' => $stats['attendance_percentage']]
];

// Mock assignment submission data
$assignmentSubmissionData = [
    ['week' => 'Week 1', 'submissions' => 3],
    ['week' => 'Week 2', 'submissions' => 2],
    ['week' => 'Week 3', 'submissions' => 4],
    ['week' => 'Week 4', 'submissions' => 3]
];

// Get recent attendance records for activity
$recentAttendanceQuery = "SELECT ar.DateTime, ar.Status, ar.Method, s.SubjectName, s.SubjectCode, t.FullName as TeacherName
                         FROM attendance_records ar
                         JOIN subjects s ON ar.SubjectID = s.SubjectID
                         JOIN teachers t ON ar.TeacherID = t.TeacherID
                         WHERE ar.StudentID = ?
                         ORDER BY ar.DateTime DESC
                         LIMIT 5";
$stmt = $conn->prepare($recentAttendanceQuery);
$stmt->bind_param("i", $currentStudentID);
$stmt->execute();
$result = $stmt->get_result();
$recentActivity = [];
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = $row;
}

// Convert data to JSON for JavaScript
$subjectAttendanceJSON = json_encode($subjectAttendanceData);
$weeklyAttendanceJSON = json_encode($weeklyAttendanceData);
$assignmentSubmissionJSON = json_encode($assignmentSubmissionData);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Dashboard | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_student.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="layout-dashboard"></i>
                    Student Dashboard
                </h2>
                <p class="text-muted mb-0">Track your academic progress and attendance</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($hasRegisteredDevice): ?>
                    <a href="scan_qr.php" class="btn btn-primary">
                        <i data-lucide="qr-code"></i> Quick QR Scan
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-primary" disabled title="Register your device first">
                        <i data-lucide="qr-code"></i> QR Scan (Device Required)
                    </button>
                <?php endif; ?>
                <a href="submit_assignment.php" class="btn btn-outline-primary">
                    <i data-lucide="clipboard-list"></i> My Assignments
                </a>
            </div>
        </div>

        <!-- Device Registration Alert -->
        <?php if (!$hasRegisteredDevice && !$pendingToken): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i data-lucide="smartphone" class="me-2" style="width: 20px; height: 20px;"></i>
                    <div>
                        <strong>Device Not Registered</strong><br>
                        <small>You need to register your device for QR code attendance. Contact your teacher to get a registration token.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (!$hasRegisteredDevice && $pendingToken): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i data-lucide="clock" class="me-2" style="width: 20px; height: 20px;"></i>
                        <div>
                            <strong>Device Registration Available</strong><br>
                            <small>You can now register your device for QR attendance. Expires: <?= date('M j, g:i A', strtotime($pendingToken['ExpiresAt'])) ?></small>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm ms-3" onclick="registerDevice()">
                        <i data-lucide="smartphone" class="me-1"></i>
                        Register Now
                    </button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards (Colorful, Responsive) -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['attendance_percentage'] ?>%</div>
                            <div>Overall Attendance</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="calendar-check" style="width: 14px; height: 14px;"></i>
                                    <?= $totalAttendanceRecords ?> records
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card subjects-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['total_subjects'] ?></div>
                            <div>Enrolled Subjects</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                    Current semester
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="book-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card pending-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= $stats['pending_assignments'] ?></div>
                            <div>Pending Assignments</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="clipboard-list" style="width: 14px; height: 14px;"></i>
                                    <?= $stats['completed_assignments'] ?> completed
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="clipboard-list"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card activities text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">
                                <i data-lucide="<?= $hasRegisteredDevice ? 'check-circle' : 'smartphone' ?>"></i>
                            </div>
                            <div>Device Status</div>
                            <div class="mt-1">
                                <?php if ($hasRegisteredDevice): ?>
                                    <small class="text-white-50">
                                        <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                                        Registered
                                    </small>
                                <?php elseif ($pendingToken): ?>
                                    <small class="text-white-50">
                                        <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                        Registration Available
                                    </small>
                                <?php else: ?>
                                    <small class="text-white-50">
                                        <i data-lucide="x-circle" style="width: 14px; height: 14px;"></i>
                                        Not Registered
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="smartphone"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Subject-wise Attendance Chart -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i data-lucide="bar-chart-3"></i>
                            Subject-wise Attendance
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectAttendanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Weekly Attendance Trend -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i data-lucide="trending-up"></i>
                            Weekly Attendance Trend
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyAttendanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="row g-4">
            <!-- Recent Activity -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i data-lucide="activity"></i>
                            Recent Attendance Activity
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivity)): ?>
                            <div class="text-center py-4">
                                <i data-lucide="calendar-x" style="width: 48px; height: 48px;" class="text-muted mb-2"></i>
                                <p class="text-muted">No attendance records found</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $activity['Status'] === 'present' ? 'present' : 'absent' ?>">
                                            <i data-lucide="<?= $activity['Status'] === 'present' ? 'check' : 'x' ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">
                                                <?= htmlspecialchars($activity['SubjectCode']) ?> - <?= htmlspecialchars($activity['SubjectName']) ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="status-badge <?= $activity['Status'] === 'present' ? 'present' : 'absent' ?>">
                                                    <?= ucfirst($activity['Status']) ?>
                                                </span>
                                                <span class="method-badge">
                                                    <?= strtoupper($activity['Method']) ?>
                                                </span>
                                                <span class="text-muted">
                                                    with <?= htmlspecialchars($activity['TeacherName']) ?>
                                                </span>
                                            </div>
                                            <div class="activity-time">
                                                <?= date('M j, Y g:i A', strtotime($activity['DateTime'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i data-lucide="zap"></i>
                            Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($hasRegisteredDevice): ?>
                                <a href="scan_qr.php" class="btn btn-primary">
                                    <i data-lucide="qr-code" class="me-2"></i>
                                    Scan QR Code
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-primary" disabled>
                                    <i data-lucide="qr-code" class="me-2"></i>
                                    Scan QR Code (Device Required)
                                </button>
                            <?php endif; ?>

                            <a href="attendance_report.php" class="btn btn-outline-primary">
                                <i data-lucide="file-text" class="me-2"></i>
                                View Attendance Report
                            </a>

                            <a href="submit_assignment.php" class="btn btn-outline-primary">
                                <i data-lucide="upload" class="me-2"></i>
                                Submit Assignment
                            </a>

                            <a href="profile.php" class="btn btn-outline-primary">
                                <i data-lucide="user" class="me-2"></i>
                                Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Subject Performance -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i data-lucide="target"></i>
                            Subject Performance
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($subjectAttendanceData)): ?>
                            <div class="text-center py-3">
                                <i data-lucide="book-x" style="width: 32px; height: 32px;" class="text-muted mb-2"></i>
                                <p class="text-muted mb-0">No subjects enrolled</p>
                            </div>
                        <?php else: ?>
                            <div class="subject-performance-list">
                                <?php foreach ($subjectAttendanceData as $subject): ?>
                                    <div class="subject-performance-item">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-medium"><?= htmlspecialchars($subject['subject']) ?></span>
                                            <span class="percentage <?= $subject['percentage'] >= 75 ? 'good' : ($subject['percentage'] >= 50 ? 'average' : 'poor') ?>">
                                                <?= $subject['percentage'] ?>%
                                            </span>
                                        </div>
                                        <div class="progress mb-2" style="height: 6px;">
                                            <div class="progress-bar <?= $subject['percentage'] >= 75 ? 'bg-success' : ($subject['percentage'] >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                                style="width: <?= $subject['percentage'] ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $subject['present'] ?>/<?= $subject['total'] ?> classes attended
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Registration Modal -->
    <div class="modal fade" id="deviceRegistrationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="smartphone" class="me-2"></i>
                        Register Your Device
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i data-lucide="shield-check" style="width: 64px; height: 64px;" class="text-primary"></i>
                    </div>
                    <h6 class="text-center mb-3">Secure Device Registration</h6>
                    <p class="text-muted">
                        This will register your current device for QR code attendance.
                        Once registered, you'll need to use this same device to scan QR codes for attendance.
                    </p>
                    <div class="alert alert-info">
                        <i data-lucide="info" class="me-2"></i>
                        <small>
                            Your device information (browser fingerprint) will be securely stored
                            to ensure attendance security.
                        </small>
                    </div>
                    <div id="registrationStatus" class="text-center" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Registering...</span>
                        </div>
                        <p class="mt-2">Registering your device...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmRegisterBtn" onclick="confirmDeviceRegistration()">
                        <i data-lucide="check" class="me-1"></i>
                        Register Device
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Chart data from PHP
        const subjectAttendanceData = <?= $subjectAttendanceJSON ?>;
        const weeklyAttendanceData = <?= $weeklyAttendanceJSON ?>;

        // Subject-wise Attendance Chart
        const subjectCtx = document.getElementById('subjectAttendanceChart').getContext('2d');
        new Chart(subjectCtx, {
            type: 'bar',
            data: {
                labels: subjectAttendanceData.map(item => item.subject),
                datasets: [{
                    label: 'Attendance %',
                    data: subjectAttendanceData.map(item => item.percentage),
                    backgroundColor: subjectAttendanceData.map(item =>
                        item.percentage >= 75 ? 'rgba(34, 197, 94, 0.8)' :
                        item.percentage >= 50 ? 'rgba(251, 191, 36, 0.8)' :
                        'rgba(239, 68, 68, 0.8)'
                    ),
                    borderColor: subjectAttendanceData.map(item =>
                        item.percentage >= 75 ? 'rgb(34, 197, 94)' :
                        item.percentage >= 50 ? 'rgb(251, 191, 36)' :
                        'rgb(239, 68, 68)'
                    ),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });

        // Weekly Attendance Trend Chart
        const weeklyCtx = document.getElementById('weeklyAttendanceChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: weeklyAttendanceData.map(item => item.week),
                datasets: [{
                    label: 'Attendance %',
                    data: weeklyAttendanceData.map(item => item.percentage),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });

        // Device registration functions
        function registerDevice() {
            const modal = new bootstrap.Modal(document.getElementById('deviceRegistrationModal'));
            modal.show();
        }

        function confirmDeviceRegistration() {
            const statusDiv = document.getElementById('registrationStatus');
            const confirmBtn = document.getElementById('confirmRegisterBtn');
            const modalFooter = document.querySelector('#deviceRegistrationModal .modal-footer');

            // Show loading state
            statusDiv.style.display = 'block';
            modalFooter.style.display = 'none';

            // Generate device fingerprint
            const fingerprint = generateDeviceFingerprint();

            const formData = new FormData();
            formData.append('fingerprint', fingerprint);
            formData.append('user_agent', navigator.userAgent);

            // Fix the fetch URL to use correct path
            fetch('../student/student_devices.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusDiv.innerHTML = `
                        <i data-lucide="check-circle" style="width: 48px; height: 48px;" class="text-success"></i>
                        <p class="text-success mt-2 mb-0">${data.message}</p>
                    `;

                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        statusDiv.innerHTML = `
                        <i data-lucide="x-circle" style="width: 48px; height: 48px;" class="text-danger"></i>
                        <p class="text-danger mt-2 mb-0">Error: ${data.error}</p>
                    `;
                        modalFooter.style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusDiv.innerHTML = `
                    <i data-lucide="x-circle" style="width: 48px; height: 48px;" class="text-danger"></i>
                    <p class="text-danger mt-2 mb-0">Registration failed. Please try again.</p>
                `;
                    modalFooter.style.display = 'flex';
                })
                .finally(() => {
                    // Re-initialize Lucide icons
                    if (typeof lucide !== "undefined") {
                        lucide.createIcons();
                    }
                });
        }

        function generateDeviceFingerprint() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Device fingerprint', 2, 2);

            const fingerprint = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                screen.colorDepth,
                new Date().getTimezoneOffset(),
                canvas.toDataURL(),
                navigator.hardwareConcurrency || 'unknown',
                navigator.platform
            ].join('|');

            // Simple hash function
            let hash = 0;
            for (let i = 0; i < fingerprint.length; i++) {
                const char = fingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32bit integer
            }

            return Math.abs(hash).toString(16);
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>