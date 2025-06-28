<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\teacher\dashboard_teacher.php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

// Include database configuration
include '../../config/db_config.php';

// Get current teacher's TeacherID
$currentTeacherQuery = "SELECT TeacherID FROM teachers WHERE LoginID = ?";
$stmt = $conn->prepare($currentTeacherQuery);
$stmt->bind_param("i", $_SESSION['LoginID']);
$stmt->execute();
$result = $stmt->get_result();
$teacherData = $result->fetch_assoc();

if (!$teacherData) {
    die("Teacher data not found. Please contact administrator.");
}

$currentTeacherID = $teacherData['TeacherID'];

// Fetch real-time statistics for teacher
$stats = [];

// Get teacher's subjects count
$teacherSubjectsQuery = "SELECT COUNT(DISTINCT tsm.SubjectID) as count 
                        FROM teacher_subject_map tsm 
                        WHERE tsm.TeacherID = ?";
$stmt = $conn->prepare($teacherSubjectsQuery);
$stmt->bind_param("i", $currentTeacherID);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_subjects'] = $result->fetch_assoc()['count'];

// Get teacher's total unique students across all assigned subjects
$teacherStudentsQuery = "SELECT COUNT(DISTINCT st.StudentID) as count 
                        FROM students st 
                        JOIN subjects s ON st.SemesterID = s.SemesterID AND st.DepartmentID = s.DepartmentID
                        JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID 
                        WHERE tsm.TeacherID = ?";
$stmt = $conn->prepare($teacherStudentsQuery);
$stmt->bind_param("i", $currentTeacherID);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_students'] = $result->fetch_assoc()['count'];

// Get total attendance records count for this teacher
$attendanceCountQuery = "SELECT COUNT(*) as count 
                        FROM attendance_records ar 
                        WHERE ar.TeacherID = ?";
$stmt = $conn->prepare($attendanceCountQuery);
$stmt->bind_param("i", $currentTeacherID);
$stmt->execute();
$result = $stmt->get_result();
$totalAttendanceRecords = $result->fetch_assoc()['count'];

// Calculate average attendance percentage (present records / total records * 100)
if ($totalAttendanceRecords > 0) {
    $presentCountQuery = "SELECT COUNT(*) as count 
                         FROM attendance_records ar 
                         WHERE ar.TeacherID = ? AND ar.Status = 'present'";
    $stmt = $conn->prepare($presentCountQuery);
    $stmt->bind_param("i", $currentTeacherID);
    $stmt->execute();
    $result = $stmt->get_result();
    $presentCount = $result->fetch_assoc()['count'];
    $stats['avg_attendance'] = round(($presentCount / $totalAttendanceRecords) * 100, 1);
} else {
    $stats['avg_attendance'] = 0;
}

// Mock assignments due (since no assignments table exists)
$stats['assignments_due'] = 8;

// Get subject-wise student distribution for charts
$subjectStudentsQuery = "SELECT s.SubjectName, COUNT(DISTINCT st.StudentID) as student_count 
                        FROM subjects s 
                        JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
                        JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
                        WHERE tsm.TeacherID = ? 
                        GROUP BY s.SubjectID, s.SubjectName 
                        ORDER BY student_count DESC";
$stmt = $conn->prepare($subjectStudentsQuery);
$stmt->bind_param("i", $currentTeacherID);
$stmt->execute();
$result = $stmt->get_result();
$subjectData = [];
while ($row = $result->fetch_assoc()) {
    $subjectData[] = $row;
}

// Get weekly attendance data for the last 5 weeks (mock data since we don't have weekly aggregation)
$attendanceData = [
    ['week' => 'Week 1', 'percentage' => 85],
    ['week' => 'Week 2', 'percentage' => 82],
    ['week' => 'Week 3', 'percentage' => 78],
    ['week' => 'Week 4', 'percentage' => 88],
    ['week' => 'Week 5', 'percentage' => $stats['avg_attendance']]
];

// Mock assignment submission data
$assignmentData = [
    ['week' => 'Week 1', 'submissions' => 32],
    ['week' => 'Week 2', 'submissions' => 45],
    ['week' => 'Week 3', 'submissions' => 38],
    ['week' => 'Week 4', 'submissions' => 50]
];

// Get recent attendance records for activity
$recentAttendanceQuery = "SELECT ar.DateTime, ar.Status, st.FullName as StudentName, s.SubjectName
                         FROM attendance_records ar
                         JOIN students st ON ar.StudentID = st.StudentID
                         JOIN subjects s ON ar.SubjectID = s.SubjectID
                         WHERE ar.TeacherID = ?
                         ORDER BY ar.DateTime DESC
                         LIMIT 5";
$stmt = $conn->prepare($recentAttendanceQuery);
$stmt->bind_param("i", $currentTeacherID);
$stmt->execute();
$result = $stmt->get_result();
$recentActivity = [];
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = $row;
}

// Convert data to JSON for JavaScript
$subjectDataJSON = json_encode($subjectData);
$attendanceDataJSON = json_encode($attendanceData);
$assignmentDataJSON = json_encode($assignmentData);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Dashboard | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/dashboard_teacher.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="layout-dashboard"></i>
                    Teacher Dashboard
                </h2>
                <p class="text-muted mb-0">Manage your classes and track student progress</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="attendance.php" class="btn btn-primary">
                    <i data-lucide="check-square"></i> Quick Attendance
                </a>
                <a href="my_subjects.php" class="btn btn-outline-primary">
                    <i data-lucide="book-open"></i> My Subjects
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                            <div>Total Students</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    Across all subjects
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['avg_attendance'], 1); ?>%</div>
                            <div>Avg Attendance</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    Overall rate
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="calendar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['assignments_due']; ?></div>
                            <div>Assignments Due</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    This week
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
                            <div class="stat-number"><?php echo $stats['total_subjects']; ?></div>
                            <div>My Subjects</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                    Active courses
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="book-open"></i>
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
                        Weekly Attendance Trends
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
                        Subject Distribution
                    </h5>
                    <div class="chart-container">
                        <?php if (empty($subjectData)): ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <div class="text-center">
                                    <i data-lucide="book-open" style="width: 48px; height: 48px; opacity: 0.5;"></i>
                                    <p class="text-muted mt-2">No subjects assigned yet</p>
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
                        Assignment Submission Rates
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
                                <a href="attendance.php" class="btn btn-primary btn-sm">
                                    <i data-lucide="plus"></i> Mark Attendance
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
                                        <div class="fw-medium"><?php echo htmlspecialchars($activity['StudentName']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['SubjectName']); ?> - <?php echo ucfirst($activity['Status']); ?></small>
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
                        <i data-lucide="graduation-cap" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">My Subjects & Students</h5>
                    <p class="text-muted mb-4 flex-grow-1">View all students in your assigned subjects and manage class attendance.</p>
                    <a href="my_subjects.php" class="btn btn-gradient">
                        <i data-lucide="eye"></i> View Students
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <i data-lucide="upload-cloud" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">Upload Materials</h5>
                    <p class="text-muted mb-4 flex-grow-1">Upload your teaching materials and resources for students.</p>
                    <a href="upload_slides.php" class="btn btn-gradient" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <i data-lucide="upload"></i> Upload Files
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                        <i data-lucide="clipboard-list" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5 class="mb-3">Attendance Reports</h5>
                    <p class="text-muted mb-4 flex-grow-1">View detailed attendance reports for your subject classes.</p>
                    <a href="attendance_report.php" class="btn btn-gradient" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                        <i data-lucide="bar-chart"></i> View Reports
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
        const subjectData = <?php echo $subjectDataJSON; ?>;
        const attendanceData = <?php echo $attendanceDataJSON; ?>;
        const assignmentData = <?php echo $assignmentDataJSON; ?>;

        // Chart configurations
        Chart.defaults.font.family = 'Poppins';
        Chart.defaults.color = '#6c757d';

        // Attendance Trends Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: attendanceData.map(item => item.week),
                datasets: [{
                    label: 'Attendance %',
                    data: attendanceData.map(item => item.percentage),
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

        // Subject Distribution Chart
        if (subjectData.length > 0) {
            const subjectCtx = document.getElementById('subjectChart').getContext('2d');
            new Chart(subjectCtx, {
                type: 'doughnut',
                data: {
                    labels: subjectData.map(item => item.SubjectName),
                    datasets: [{
                        data: subjectData.map(item => item.student_count),
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

        // Assignment Chart
        const assignmentCtx = document.getElementById('assignmentChart').getContext('2d');
        new Chart(assignmentCtx, {
            type: 'bar',
            data: {
                labels: assignmentData.map(item => item.week),
                datasets: [{
                    label: 'Submissions',
                    data: assignmentData.map(item => item.submissions),
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