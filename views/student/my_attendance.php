<?php
session_start();
require_once(__DIR__ . '/../../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get student info
$studentStmt = $conn->prepare("
    SELECT s.*, d.DepartmentName, sem.SemesterNumber 
    FROM students s
    JOIN departments d ON s.DepartmentID = d.DepartmentID
    JOIN semesters sem ON s.SemesterID = sem.SemesterID
    WHERE s.LoginID = ?
");
$studentStmt->bind_param("i", $loginID);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
$studentRow = $studentRes->fetch_assoc();

if (!$studentRow) {
    header("Location: ../logout.php");
    exit();
}

$studentID = $studentRow['StudentID'];

// Get filter parameters
$selectedSubject = $_GET['subject'] ?? '';
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Get student's subjects
$subjectsQuery = $conn->prepare("
    SELECT DISTINCT s.SubjectID, s.SubjectCode, s.SubjectName
    FROM subjects s
    JOIN attendance_records ar ON s.SubjectID = ar.SubjectID
    WHERE ar.StudentID = ? AND s.DepartmentID = ? AND s.SemesterID = ?
    ORDER BY s.SubjectName
");
$subjectsQuery->bind_param("iii", $studentID, $studentRow['DepartmentID'], $studentRow['SemesterID']);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row;
}

// Get attendance records with filters
$whereConditions = ["ar.StudentID = ?"];
$params = [$studentID];
$paramTypes = "i";

if ($selectedSubject) {
    $whereConditions[] = "ar.SubjectID = ?";
    $params[] = $selectedSubject;
    $paramTypes .= "i";
}

if ($selectedMonth) {
    $whereConditions[] = "DATE_FORMAT(ar.DateTime, '%Y-%m') = ?";
    $params[] = $selectedMonth;
    $paramTypes .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

$attendanceQuery = $conn->prepare("
    SELECT 
        ar.AttendanceID,
        ar.DateTime,
        ar.Status,
        ar.Method,
        s.SubjectCode,
        s.SubjectName,
        t.FullName as TeacherName,
        DATE(ar.DateTime) as Date,
        TIME(ar.DateTime) as Time
    FROM attendance_records ar
    JOIN subjects s ON ar.SubjectID = s.SubjectID
    JOIN teachers t ON ar.TeacherID = t.TeacherID
    WHERE $whereClause
    ORDER BY ar.DateTime DESC
    LIMIT 100
");

$attendanceQuery->bind_param($paramTypes, ...$params);
$attendanceQuery->execute();
$attendanceResult = $attendanceQuery->get_result();
$attendanceRecords = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $attendanceRecords[] = $row;
}

// Calculate statistics
$totalRecords = count($attendanceRecords);
$presentCount = count(array_filter($attendanceRecords, fn($r) => $r['Status'] === 'present'));
$absentCount = count(array_filter($attendanceRecords, fn($r) => $r['Status'] === 'absent'));
$lateCount = count(array_filter($attendanceRecords, fn($r) => $r['Status'] === 'late'));
$qrCount = count(array_filter($attendanceRecords, fn($r) => $r['Method'] === 'qr'));
$manualCount = count(array_filter($attendanceRecords, fn($r) => $r['Method'] === 'manual'));

$attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_student.css">
    <link rel="stylesheet" href="../../assets/css/my_attendance.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/sidebar_student.js" defer></script>
    <script src="../../assets/js/navbar_student.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Include sidebar and navbar -->
    <?php include '../components/sidebar_student_dashboard.php'; ?>
    <?php include '../components/navbar_student.php'; ?>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">
                <i data-lucide="calendar-check"></i>
                My Attendance
            </h2>
            <p class="text-muted mb-0">Track your attendance across all subjects</p>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-select" onchange="this.form.submit()">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['SubjectID'] ?>"
                                    <?= $selectedSubject == $subject['SubjectID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" class="form-control"
                            value="<?= $selectedMonth ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="exportReport()">
                            <i data-lucide="download"></i>
                            Export
                        </button>
                        <a href="my_attendance.php" class="btn btn-outline-secondary">
                            <i data-lucide="refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="calendar-check"></i>
                    </div>
                    <div class="mini-stat-value"><?= $attendanceRate ?>%</div>
                    <div class="mini-stat-label">Attendance Rate</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <div class="mini-stat-value"><?= $presentCount ?></div>
                    <div class="mini-stat-label">Present</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="x-circle"></i>
                    </div>
                    <div class="mini-stat-value"><?= $absentCount ?></div>
                    <div class="mini-stat-label">Absent</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="clock"></i>
                    </div>
                    <div class="mini-stat-value"><?= $lateCount ?></div>
                    <div class="mini-stat-label">Late</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="pie-chart"></i>
                            Attendance Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i data-lucide="bar-chart-3"></i>
                            Attendance Method
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="methodChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i data-lucide="list"></i>
                    Attendance Records
                    <span class="badge bg-primary ms-2"><?= $totalRecords ?> records</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($totalRecords > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Method</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('M j, Y', strtotime($record['Date'])) ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($record['SubjectCode']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($record['SubjectName']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($record['TeacherName']) ?></td>
                                        <td>
                                            <?php if ($record['Status'] == 'present'): ?>
                                                <span class="badge bg-success">Present</span>
                                            <?php elseif ($record['Status'] == 'absent'): ?>
                                                <span class="badge bg-danger">Absent</span>
                                            <?php elseif ($record['Status'] == 'late'): ?>
                                                <span class="badge bg-warning text-dark">Late</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['Method'] == 'qr'): ?>
                                                <span class="badge bg-info">QR Code</span>
                                            <?php elseif ($record['Method'] == 'manual'): ?>
                                                <span class="badge bg-secondary">Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('h:i A', strtotime($record['Time'])) ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i data-lucide="calendar-x" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                        <h4>No Attendance Records</h4>
                        <p class="text-muted">No attendance records found for the selected filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../../assets/js/navbar_student.js"></script>
    <script src="../../assets/js/my_attendance.js"></script>

    <?php if ($totalRecords > 0): ?>
        <script>
            // Initialize charts
            document.addEventListener('DOMContentLoaded', function() {
                // Attendance Distribution Chart
                const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
                new Chart(attendanceCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Present', 'Absent', 'Late'],
                        datasets: [{
                            data: [<?= $presentCount ?>, <?= $absentCount ?>, <?= $lateCount ?>],
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
                            data: [<?= $qrCount ?>, <?= $manualCount ?>],
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
            });

            function exportReport() {
                const subject = document.querySelector('select[name="subject"]').value;
                const month = document.querySelector('input[name="month"]').value;

                let url = '../../api/export_student_attendance.php?';
                if (subject) url += `subject=${subject}&`;
                if (month) url += `month=${month}`;

                window.open(url, '_blank');
            }
        </script>
    <?php endif; ?>
</body>

</html>