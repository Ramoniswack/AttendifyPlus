<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\dashboard_student.php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: login.php");
    exit();
}

// Include database configuration
include '../config/db_config.php';

// Get current student's StudentID
$currentStudentQuery = "SELECT StudentID, SemesterID, DepartmentID FROM students WHERE LoginID = ?";
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
    <link rel="stylesheet" href="../assets/css/dashboard_student.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/dashboard_student.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include 'navbar_student.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
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
                <a href="scan_qr.php" class="btn btn-primary">
                    <i data-lucide="qr-code"></i> Quick QR Scan
                </a>
                <a href="view_assignments.php" class="btn btn-outline-primary">
                    <i data-lucide="clipboard-list"></i> My Assignments
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['attendance_percentage'], 1); ?>%</div>
                            <div>My Attendance</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    Overall rate
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
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_subjects']; ?></div>
                            <div>My Subjects</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                    This semester
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
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['pending_assignments']; ?></div>
                            <div>Pending Tasks</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    Due soon
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="file-text"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card activities text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['completed_assignments']; ?></div>
                            <div>Completed</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Assignments
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="chart-title">
                        <i data-lucide="trending-up"></i>
                        My Weekly Attendance
                    </h5>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="chart-title">
                        <i data-lucide="pie-chart"></i>
                        Subject Attendance
                    </h5>
                    <div class="chart-container">
                        <?php if (empty($subjectAttendanceData)): ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <div class="text-center">
                                    <i data-lucide="calendar" style="width: 48px; height: 48px; opacity: 0.5;"></i>
                                    <p class="text-muted mt-2">No attendance records yet</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <canvas id="subjectChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="chart-title">
                        <i data-lucide="bar-chart"></i>
                        Assignment Submissions
                    </h5>
                    <div class="chart-container">
                        <canvas id="assignmentChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="recent-activity-card">
                    <h5 class="chart-title">
                        <i data-lucide="activity"></i>
                        Recent Activity
                    </h5>
                    <?php if (empty($recentActivity)): ?>
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i data-lucide="calendar" style="width: 48px; height: 48px; opacity: 0.5;"></i>
                                <p class="text-muted mt-2">No recent attendance records</p>
                                <a href="scan_qr.php" class="btn btn-primary btn-sm">
                                    <i data-lucide="qr-code"></i> Scan QR Code
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item d-flex align-items-center gap-3 mb-3">
                                    <div class="activity-icon <?php echo $activity['Status'] == 'present' ? 'bg-success' : 'bg-danger'; ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i data-lucide="<?php echo $activity['Status'] == 'present' ? 'check' : 'x'; ?>" style="width: 16px; height: 16px; color: white;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?php echo htmlspecialchars($activity['SubjectCode']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($activity['SubjectName']); ?> - 
                                            <?php echo ucfirst($activity['Status']); ?>
                                            <?php if ($activity['Method'] == 'qr'): ?>
                                                <span class="badge bg-primary ms-1">QR</span>
                                            <?php endif; ?>
                                        </small>
                                        <div class="small text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['DateTime'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon">
                        <i data-lucide="qr-code" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">QR Attendance</h5>
                    <p class="text-muted mb-4 flex-grow-1">Scan QR codes to mark your attendance quickly and easily.</p>
                    <a href="scan_qr.php" class="btn btn-gradient">
                        <i data-lucide="qr-code"></i> Scan QR Code
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <i data-lucide="file-plus" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">Submit Assignment</h5>
                    <p class="text-muted mb-4 flex-grow-1">Upload and submit your assignments on time.</p>
                    <a href="submit_assignment.php" class="btn btn-gradient" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <i data-lucide="upload"></i> Submit Work
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                        <i data-lucide="folder-open" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">Study Materials</h5>
                    <p class="text-muted mb-4 flex-grow-1">Access course materials and resources from your teachers.</p>
                    <a href="view_materials.php" class="btn btn-gradient" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                        <i data-lucide="folder-open"></i> View Materials
                    </a>
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
        const subjectAttendanceData = <?php echo $subjectAttendanceJSON; ?>;
        const weeklyAttendanceData = <?php echo $weeklyAttendanceJSON; ?>;
        const assignmentSubmissionData = <?php echo $assignmentSubmissionJSON; ?>;

        // Chart configurations
        Chart.defaults.font.family = 'Poppins';
        Chart.defaults.color = '#6c757d';

        // Weekly Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: weeklyAttendanceData.map(item => item.week),
                datasets: [{
                    label: 'Attendance %',
                    data: weeklyAttendanceData.map(item => item.percentage),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
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
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Subject Attendance Chart
        if (subjectAttendanceData.length > 0) {
            const subjectCtx = document.getElementById('subjectChart').getContext('2d');
            new Chart(subjectCtx, {
                type: 'doughnut',
                data: {
                    labels: subjectAttendanceData.map(item => item.subject),
                    datasets: [{
                        data: subjectAttendanceData.map(item => item.percentage),
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#17a2b8',
                            '#dc3545',
                            '#6f42c1',
                            '#fd7e14',
                            '#20c997'
                        ],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }

        // Assignment Submission Chart
        const assignmentCtx = document.getElementById('assignmentChart').getContext('2d');
        new Chart(assignmentCtx, {
            type: 'bar',
            data: {
                labels: assignmentSubmissionData.map(item => item.week),
                datasets: [{
                    label: 'Submissions',
                    data: assignmentSubmissionData.map(item => item.submissions),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false
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
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>