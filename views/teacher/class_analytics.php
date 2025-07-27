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

// Get teacher's subjects
$subjectsQuery = $conn->prepare("
    SELECT s.SubjectID, s.SubjectCode, s.SubjectName, s.SemesterID, sem.SemesterNumber
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE tsm.TeacherID = ?
    ORDER BY s.SemesterID, s.SubjectName
");
$subjectsQuery->bind_param("i", $teacherID);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row;
}

// Get analytics data for selected subject
$selectedSubjectID = $_GET['subject'] ?? null;
$analyticsData = null;

if ($selectedSubjectID) {
    // Get attendance analytics
    $attendanceQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN Status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN Method = 'qr' THEN 1 ELSE 0 END) as qr_count,
            SUM(CASE WHEN Method = 'manual' THEN 1 ELSE 0 END) as manual_count
        FROM attendance_records 
        WHERE TeacherID = ? AND SubjectID = ?
    ");
    $attendanceQuery->bind_param("ii", $teacherID, $selectedSubjectID);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    $attendanceData = $attendanceResult->fetch_assoc();

    // Get assignment analytics
    $assignmentQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_assignments,
            SUM(CASE WHEN Status = 'active' THEN 1 ELSE 0 END) as active_assignments,
            SUM(CASE WHEN Status = 'graded' THEN 1 ELSE 0 END) as graded_assignments
        FROM assignments 
        WHERE TeacherID = ? AND SubjectID = ?
    ");
    $assignmentQuery->bind_param("ii", $teacherID, $selectedSubjectID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignmentData = $assignmentResult->fetch_assoc();

    // Get student count
    $studentQuery = $conn->prepare("
        SELECT COUNT(DISTINCT s.StudentID) as total_students
        FROM students s
        JOIN subjects sub ON s.SemesterID = sub.SemesterID AND s.DepartmentID = sub.DepartmentID
        WHERE sub.SubjectID = ?
    ");
    $studentQuery->bind_param("i", $selectedSubjectID);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $studentData = $studentResult->fetch_assoc();

    $analyticsData = [
        'attendance' => $attendanceData,
        'assignments' => $assignmentData,
        'students' => $studentData
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Analytics | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link rel="stylesheet" href="../../assets/css/class_analytics.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/sidebar_teacher.js" defer></script>
    <script src="../../assets/js/navbar_teacher.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <i data-lucide="bar-chart-3"></i>
                    Class Analytics
                </h2>
                <p class="text-muted mb-0">View detailed analytics for your classes</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($selectedSubjectID && $analyticsData): ?>
                    <button class="btn btn-outline-success" onclick="exportClassAnalytics()">
                        <i data-lucide="download"></i>
                        Export Analytics
                    </button>
                <?php endif; ?>
                <a href="dashboard_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Subject Selection -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i data-lucide="book-open"></i>
                    Select Subject
                </h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <select name="subject" class="form-select" onchange="this.form.submit()">
                            <option value="">Choose a subject...</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['SubjectID'] ?>"
                                    <?= $selectedSubjectID == $subject['SubjectID'] ? 'selected' : '' ?>>
                                    Semester <?= $subject['SemesterNumber'] ?> - <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedSubjectID && $analyticsData): ?>
            <?php
            $subject = array_filter($subjects, function ($s) use ($selectedSubjectID) {
                return $s['SubjectID'] == $selectedSubjectID;
            });
            $subject = reset($subject);
            ?>

            <!-- Analytics Overview -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="mini-stat-card text-center p-3">
                        <div class="mini-stat-icon mb-2">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="mini-stat-value"><?= $analyticsData['students']['total_students'] ?? 0 ?></div>
                        <div class="mini-stat-label">Total Students</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="mini-stat-card text-center p-3">
                        <div class="mini-stat-icon mb-2">
                            <i data-lucide="calendar-check"></i>
                        </div>
                        <div class="mini-stat-value"><?= $analyticsData['attendance']['total_records'] ?? 0 ?></div>
                        <div class="mini-stat-label">Attendance Records</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="mini-stat-card text-center p-3">
                        <div class="mini-stat-icon mb-2">
                            <i data-lucide="clipboard-list"></i>
                        </div>
                        <div class="mini-stat-value"><?= $analyticsData['assignments']['total_assignments'] ?? 0 ?></div>
                        <div class="mini-stat-label">Total Assignments</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="mini-stat-card text-center p-3">
                        <div class="mini-stat-icon mb-2">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div class="mini-stat-value"><?= $analyticsData['assignments']['graded_assignments'] ?? 0 ?></div>
                        <div class="mini-stat-label">Graded Assignments</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4">
                <!-- Attendance Chart -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="chart-title">
                            <i data-lucide="pie-chart"></i>
                            Attendance Distribution
                        </h5>
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Attendance Method Chart -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="chart-title">
                            <i data-lucide="bar-chart-2"></i>
                            Attendance Method
                        </h5>
                        <div class="chart-container">
                            <canvas id="methodChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="row g-4 mt-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i data-lucide="trending-up"></i>
                                Attendance Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="fw-bold text-success fs-4"><?= $analyticsData['attendance']['present_count'] ?? 0 ?></div>
                                        <small class="text-muted">Present</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="fw-bold text-danger fs-4"><?= $analyticsData['attendance']['absent_count'] ?? 0 ?></div>
                                        <small class="text-muted">Absent</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="fw-bold text-warning fs-4"><?= $analyticsData['attendance']['late_count'] ?? 0 ?></div>
                                        <small class="text-muted">Late</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="fw-bold text-info fs-4">
                                            <?php
                                            $total = $analyticsData['attendance']['total_records'] ?? 0;
                                            $present = $analyticsData['attendance']['present_count'] ?? 0;
                                            echo $total > 0 ? round(($present / $total) * 100, 1) : 0;
                                            ?>%
                                        </div>
                                        <small class="text-muted">Attendance Rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i data-lucide="clipboard-check"></i>
                                Assignment Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="fw-bold text-primary fs-4"><?= $analyticsData['assignments']['active_assignments'] ?? 0 ?></div>
                                        <small class="text-muted">Active</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="fw-bold text-success fs-4"><?= $analyticsData['assignments']['graded_assignments'] ?? 0 ?></div>
                                        <small class="text-muted">Graded</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="progress mt-3">
                                        <div class="progress-bar bg-success" role="progressbar"
                                            style="width: <?= $analyticsData['assignments']['total_assignments'] > 0 ? ($analyticsData['assignments']['graded_assignments'] / $analyticsData['assignments']['total_assignments'] * 100) : 0 ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted">Grading Progress</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="bar-chart-3" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>Select a Subject</h4>
                    <p class="text-muted">Choose a subject from the dropdown above to view detailed analytics</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($selectedSubjectID && $analyticsData): ?>
        <script>
            // Attendance Chart
            const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(attendanceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent', 'Late'],
                    datasets: [{
                        data: [
                            <?= $analyticsData['attendance']['present_count'] ?? 0 ?>,
                            <?= $analyticsData['attendance']['absent_count'] ?? 0 ?>,
                            <?= $analyticsData['attendance']['late_count'] ?? 0 ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Method Chart
            const methodCtx = document.getElementById('methodChart').getContext('2d');
            new Chart(methodCtx, {
                type: 'bar',
                data: {
                    labels: ['QR Code', 'Manual'],
                    datasets: [{
                        label: 'Attendance Method',
                        data: [
                            <?= $analyticsData['attendance']['qr_count'] ?? 0 ?>,
                            <?= $analyticsData['attendance']['manual_count'] ?? 0 ?>
                        ],
                        backgroundColor: ['#17a2b8', '#6c757d'],
                        borderWidth: 0
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
                            beginAtZero: true
                        }
                    }
                }
            });

            // Export Analytics Function
            function exportClassAnalytics() {
                const subject = document.querySelector('select[name="subject"]').value;

                if (subject) {
                    const url = `../../api/export_class_analytics.php?subject=${subject}`;
                    window.open(url, '_blank');
                } else {
                    alert('Please select a subject before exporting.');
                }
            }
        </script>
    <?php endif; ?>
</body>

</html>