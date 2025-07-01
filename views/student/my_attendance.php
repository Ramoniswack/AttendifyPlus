<?php
session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

// Mock data for frontend (replace with actual database queries later)
$studentName = $_SESSION['FullName'] ?? 'John Doe';
$studentID = $_SESSION['StudentID'] ?? 'ST001';

// Mock statistics
$stats = [
    'overall_percentage' => 87.5,
    'total_classes' => 120,
    'attended_classes' => 105,
    'missed_classes' => 15,
    'current_streak' => 12,
    'best_streak' => 25,
    'warning_subjects' => 2
];

// Mock subject data
$subjectData = [
    ['subject' => 'Mathematics', 'code' => 'MATH101', 'percentage' => 92.3, 'present' => 24, 'total' => 26, 'status' => 'excellent'],
    ['subject' => 'Physics', 'code' => 'PHY101', 'percentage' => 88.9, 'present' => 16, 'total' => 18, 'status' => 'good'],
    ['subject' => 'Chemistry', 'code' => 'CHEM101', 'percentage' => 85.0, 'present' => 17, 'total' => 20, 'status' => 'good'],
    ['subject' => 'Computer Science', 'code' => 'CS101', 'percentage' => 90.0, 'present' => 18, 'total' => 20, 'status' => 'excellent'],
    ['subject' => 'English', 'code' => 'ENG101', 'percentage' => 72.7, 'present' => 16, 'total' => 22, 'status' => 'warning'],
    ['subject' => 'History', 'code' => 'HIST101', 'percentage' => 70.0, 'present' => 14, 'total' => 20, 'status' => 'warning']
];

// Mock weekly data
$weeklyData = [
    ['week' => 'Week 1', 'percentage' => 95, 'classes' => 20, 'attended' => 19],
    ['week' => 'Week 2', 'percentage' => 90, 'classes' => 20, 'attended' => 18],
    ['week' => 'Week 3', 'percentage' => 85, 'classes' => 20, 'attended' => 17],
    ['week' => 'Week 4', 'percentage' => 88, 'classes' => 20, 'attended' => 17],
    ['week' => 'Week 5', 'percentage' => 92, 'classes' => 20, 'attended' => 19],
    ['week' => 'Week 6', 'percentage' => 87, 'classes' => 20, 'attended' => 17]
];

// Mock monthly trends
$monthlyTrends = [
    ['month' => 'Jan', 'percentage' => 85],
    ['month' => 'Feb', 'percentage' => 88],
    ['month' => 'Mar', 'percentage' => 92],
    ['month' => 'Apr', 'percentage' => $stats['overall_percentage']]
];

// Mock recent activity
$recentActivity = [
    ['date' => '2024-01-15', 'subject' => 'Mathematics', 'status' => 'present', 'time' => '09:00 AM', 'method' => 'QR'],
    ['date' => '2024-01-15', 'subject' => 'Physics', 'status' => 'present', 'time' => '11:00 AM', 'method' => 'Manual'],
    ['date' => '2024-01-14', 'subject' => 'Chemistry', 'status' => 'absent', 'time' => '02:00 PM', 'method' => 'N/A'],
    ['date' => '2024-01-14', 'subject' => 'Computer Science', 'status' => 'present', 'time' => '10:00 AM', 'method' => 'QR'],
    ['date' => '2024-01-13', 'subject' => 'English', 'status' => 'present', 'time' => '01:00 PM', 'method' => 'Manual']
];

// Get current page name for sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Attendance Analytics | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/my_attendance.css">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/my_attendance.js" defer></script>
    <script src="../../assets/js/dashboard_student.js" defer></script>
</head>

<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include '../components/navbar_student.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="header-content">
                    <h2 class="page-title">
                        <i data-lucide="bar-chart-3"></i>
                        Attendance Analytics
                    </h2>
                    <p class="page-subtitle mb-0">Detailed insights into your attendance patterns and performance</p>
                </div>
                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i data-lucide="calendar"></i>
                            This Semester
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Current Month</a></li>
                            <li><a class="dropdown-item active" href="#">This Semester</a></li>
                            <li><a class="dropdown-item" href="#">Last Semester</a></li>
                            <li><a class="dropdown-item" href="#">Academic Year</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-light" onclick="exportReport()">
                        <i data-lucide="download"></i>
                        Export Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row g-4 mb-4">
            <!-- Overall Attendance -->
            <div class="col-lg-3 col-md-6">
                <div class="metric-card overall-card">
                    <div class="metric-header">
                        <div class="metric-icon">
                            <i data-lucide="target"></i>
                        </div>
                        <div class="metric-trend positive">
                            <i data-lucide="trending-up"></i>
                            <span>+2.5%</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-value"><?= $stats['overall_percentage'] ?>%</h3>
                        <p class="metric-label">Overall Attendance</p>
                        <div class="metric-progress">
                            <div class="progress-bar" style="width: <?= $stats['overall_percentage'] ?>%"></div>
                        </div>
                        <small class="metric-detail">
                            <?= $stats['attended_classes'] ?> of <?= $stats['total_classes'] ?> classes attended
                        </small>
                    </div>
                </div>
            </div>

            <!-- Current Streak -->
            <div class="col-lg-3 col-md-6">
                <div class="metric-card streak-card">
                    <div class="metric-header">
                        <div class="metric-icon">
                            <i data-lucide="zap"></i>
                        </div>
                        <div class="metric-trend positive">
                            <i data-lucide="flame"></i>
                            <span>Active</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-value"><?= $stats['current_streak'] ?></h3>
                        <p class="metric-label">Current Streak</p>
                        <div class="streak-visual">
                            <?php for($i = 0; $i < min(10, $stats['current_streak']); $i++): ?>
                                <span class="streak-dot active"></span>
                            <?php endfor; ?>
                            <?php for($i = $stats['current_streak']; $i < 10; $i++): ?>
                                <span class="streak-dot"></span>
                            <?php endfor; ?>
                        </div>
                        <small class="metric-detail">
                            Best: <?= $stats['best_streak'] ?> days
                        </small>
                    </div>
                </div>
            </div>

            <!-- Classes This Week -->
            <div class="col-lg-3 col-md-6">
                <div class="metric-card weekly-card">
                    <div class="metric-header">
                        <div class="metric-icon">
                            <i data-lucide="calendar-days"></i>
                        </div>
                        <div class="metric-trend neutral">
                            <i data-lucide="minus"></i>
                            <span>0%</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-value">18/20</h3>
                        <p class="metric-label">This Week</p>
                        <div class="weekly-calendar">
                            <div class="day-indicator present" title="Monday - Present">M</div>
                            <div class="day-indicator present" title="Tuesday - Present">T</div>
                            <div class="day-indicator absent" title="Wednesday - Absent">W</div>
                            <div class="day-indicator present" title="Thursday - Present">T</div>
                            <div class="day-indicator present" title="Friday - Present">F</div>
                        </div>
                        <small class="metric-detail">
                            90% weekly attendance
                        </small>
                    </div>
                </div>
            </div>

            <!-- Warning Subjects -->
            <div class="col-lg-3 col-md-6">
                <div class="metric-card warning-card">
                    <div class="metric-header">
                        <div class="metric-icon">
                            <i data-lucide="alert-triangle"></i>
                        </div>
                        <div class="metric-trend negative">
                            <i data-lucide="trending-down"></i>
                            <span>Risk</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h3 class="metric-value"><?= $stats['warning_subjects'] ?></h3>
                        <p class="metric-label">Below 75%</p>
                        <div class="warning-subjects">
                            <span class="warning-badge">English</span>
                            <span class="warning-badge">History</span>
                        </div>
                        <small class="metric-detail">
                            Needs immediate attention
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Attendance Trend Chart -->
            <div class="col-lg-8">
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i data-lucide="trending-up"></i>
                                Attendance Trends
                            </h5>
                            <div class="chart-controls">
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="trendPeriod" id="weekly" checked>
                                    <label class="btn btn-outline-primary" for="weekly">Weekly</label>
                                    
                                    <input type="radio" class="btn-check" name="trendPeriod" id="monthly">
                                    <label class="btn btn-outline-primary" for="monthly">Monthly</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Subject Performance -->
            <div class="col-lg-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="pie-chart"></i>
                            Subject Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectPerformanceChart" height="180"></canvas>
                        <div class="performance-legend mt-3">
                            <div class="legend-item">
                                <span class="legend-color excellent"></span>
                                <span class="legend-label">Excellent (>85%)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color good"></span>
                                <span class="legend-label">Good (75-85%)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color warning"></span>
                                <span class="legend-label">Warning (<75%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Details and Recent Activity -->
        <div class="row g-4 mb-4">
            <!-- Subject-wise Breakdown -->
            <div class="col-lg-7">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="book-open"></i>
                            Subject-wise Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="subject-analysis-list">
                            <?php foreach($subjectData as $subject): ?>
                                <div class="subject-item">
                                    <div class="subject-info">
                                        <div class="subject-details">
                                            <h6 class="subject-name"><?= $subject['subject'] ?></h6>
                                            <span class="subject-code"><?= $subject['code'] ?></span>
                                        </div>
                                        <div class="subject-stats">
                                            <span class="attendance-percentage <?= $subject['status'] ?>">
                                                <?= $subject['percentage'] ?>%
                                            </span>
                                            <small class="attendance-count">
                                                <?= $subject['present'] ?>/<?= $subject['total'] ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="subject-progress">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-<?= $subject['status'] ?>" 
                                                 style="width: <?= $subject['percentage'] ?>%">
                                            </div>
                                        </div>
                                        <div class="progress-indicators">
                                            <span class="indicator-75">75%</span>
                                            <span class="indicator-85">85%</span>
                                        </div>
                                    </div>
                                    <div class="subject-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewSubjectDetails('<?= $subject['code'] ?>')">
                                            <i data-lucide="eye"></i>
                                            Details
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-5">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="clock"></i>
                            Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php foreach($recentActivity as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-indicator <?= $activity['status'] ?>">
                                        <i data-lucide="<?= $activity['status'] === 'present' ? 'check' : 'x' ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <h6 class="timeline-subject"><?= $activity['subject'] ?></h6>
                                            <span class="timeline-status status-<?= $activity['status'] ?>">
                                                <?= ucfirst($activity['status']) ?>
                                            </span>
                                        </div>
                                        <div class="timeline-details">
                                            <span class="timeline-date">
                                                <i data-lucide="calendar"></i>
                                                <?= date('M j, Y', strtotime($activity['date'])) ?>
                                            </span>
                                            <span class="timeline-time">
                                                <i data-lucide="clock"></i>
                                                <?= $activity['time'] ?>
                                            </span>
                                            <?php if($activity['method'] !== 'N/A'): ?>
                                                <span class="timeline-method">
                                                    <i data-lucide="<?= $activity['method'] === 'QR' ? 'qr-code' : 'edit' ?>"></i>
                                                    <?= $activity['method'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewFullHistory()">
                                <i data-lucide="history"></i>
                                View Full History
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Pattern Analysis -->
        <div class="row g-4 mb-4">
            <!-- Weekly Pattern -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="calendar-days"></i>
                            Weekly Pattern Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyPatternChart" height="150"></canvas>
                        <div class="pattern-insights mt-3">
                            <div class="insight-item">
                                <i data-lucide="trending-up" class="text-success"></i>
                                <span>Best day: <strong>Tuesday</strong> (95% attendance)</span>
                            </div>
                            <div class="insight-item">
                                <i data-lucide="trending-down" class="text-warning"></i>
                                <span>Needs improvement: <strong>Friday</strong> (78% attendance)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Slot Analysis -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="clock"></i>
                            Time Slot Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="timeSlotChart" height="150"></canvas>
                        <div class="pattern-insights mt-3">
                            <div class="insight-item">
                                <i data-lucide="sun" class="text-primary"></i>
                                <span>Morning classes: <strong>92%</strong> attendance</span>
                            </div>
                            <div class="insight-item">
                                <i data-lucide="sunset" class="text-warning"></i>
                                <span>Afternoon classes: <strong>82%</strong> attendance</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Goals and Recommendations -->
        <div class="row g-4">
            <!-- Attendance Goals -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="target"></i>
                            Attendance Goals
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="goal-item">
                            <div class="goal-header">
                                <span class="goal-title">Maintain 90% Overall</span>
                                <span class="goal-status achieved">Achieved</span>
                            </div>
                            <div class="goal-progress">
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: 97.2%"></div>
                                </div>
                                <span class="goal-percentage">87.5% / 90%</span>
                            </div>
                        </div>
                        
                        <div class="goal-item">
                            <div class="goal-header">
                                <span class="goal-title">Improve English to 80%</span>
                                <span class="goal-status in-progress">In Progress</span>
                            </div>
                            <div class="goal-progress">
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: 91%"></div>
                                </div>
                                <span class="goal-percentage">72.7% / 80%</span>
                            </div>
                        </div>
                        
                        <div class="goal-item">
                            <div class="goal-header">
                                <span class="goal-title">Perfect Attendance Week</span>
                                <span class="goal-status pending">Pending</span>
                            </div>
                            <div class="goal-progress">
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: 90%"></div>
                                </div>
                                <span class="goal-percentage">4/5 days this week</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="col-lg-6">
                <div class="analytics-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="lightbulb"></i>
                            Smart Recommendations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="recommendation-list">
                            <div class="recommendation-item priority-high">
                                <div class="recommendation-icon">
                                    <i data-lucide="alert-circle"></i>
                                </div>
                                <div class="recommendation-content">
                                    <h6>Focus on English Classes</h6>
                                    <p>Your English attendance is at 72.7%. Attend the next 3 classes to reach 80%.</p>
                                    <small class="text-muted">High Priority</small>
                                </div>
                            </div>
                            
                            <div class="recommendation-item priority-medium">
                                <div class="recommendation-icon">
                                    <i data-lucide="calendar"></i>
                                </div>
                                <div class="recommendation-content">
                                    <h6>Improve Friday Attendance</h6>
                                    <p>You miss 22% of Friday classes. Set reminders for end-of-week sessions.</p>
                                    <small class="text-muted">Medium Priority</small>
                                </div>
                            </div>
                            
                            <div class="recommendation-item priority-low">
                                <div class="recommendation-icon">
                                    <i data-lucide="award"></i>
                                </div>
                                <div class="recommendation-content">
                                    <h6>Maintain Excellence</h6>
                                    <p>Keep up the great work in Mathematics and Computer Science!</p>
                                    <small class="text-muted">Keep It Up</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="download"></i>
                        Export Attendance Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="export-options">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeCharts" checked>
                            <label class="form-check-label" for="includeCharts">
                                Include Charts and Graphs
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeDetails" checked>
                            <label class="form-check-label" for="includeDetails">
                                Include Subject-wise Details
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeRecommendations">
                            <label class="form-check-label" for="includeRecommendations">
                                Include Recommendations
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="downloadReport()">
                        <i data-lucide="download"></i>
                        Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize icons
        lucide.createIcons();

        // Pass data to JavaScript
        window.attendanceData = {
            weeklyData: <?= json_encode($weeklyData) ?>,
            monthlyTrends: <?= json_encode($monthlyTrends) ?>,
            subjectData: <?= json_encode($subjectData) ?>,
            stats: <?= json_encode($stats) ?>
        };

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeAttendanceAnalytics();
        });
    </script>
</body>

</html>