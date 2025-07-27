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

// Get student's subjects with performance data
$subjectsQuery = $conn->prepare("
    SELECT 
        s.SubjectID,
        s.SubjectCode,
        s.SubjectName,
        s.CreditHour,
        s.LectureHour,
        t.FullName as TeacherName,
        t.TeacherID,
        -- Attendance stats
        COALESCE(att.total_sessions, 0) as total_sessions,
        COALESCE(att.present_count, 0) as present_count,
        COALESCE(att.absent_count, 0) as absent_count,
        COALESCE(att.late_count, 0) as late_count,
        -- Assignment stats
        COALESCE(assign.total_assignments, 0) as total_assignments,
        COALESCE(assign.submitted_count, 0) as submitted_count,
        COALESCE(assign.graded_count, 0) as graded_count,
        COALESCE(assign.avg_grade, 0) as avg_grade,
        COALESCE(assign.max_points, 100) as max_points
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    JOIN teachers t ON tsm.TeacherID = t.TeacherID
    LEFT JOIN (
        SELECT 
            ar.SubjectID,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN ar.Status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN ar.Status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM attendance_records ar
        WHERE ar.StudentID = ?
        GROUP BY ar.SubjectID
    ) att ON s.SubjectID = att.SubjectID
    LEFT JOIN (
        SELECT 
            a.SubjectID,
            COUNT(*) as total_assignments,
            SUM(CASE WHEN asub.SubmissionID IS NOT NULL THEN 1 ELSE 0 END) as submitted_count,
            SUM(CASE WHEN asub.Status = 'graded' THEN 1 ELSE 0 END) as graded_count,
            AVG(asub.Grade) as avg_grade,
            MAX(a.MaxPoints) as max_points
        FROM assignments a
        LEFT JOIN assignment_submissions asub ON a.AssignmentID = asub.AssignmentID AND asub.StudentID = ?
        WHERE a.Status IN ('active', 'graded')
        GROUP BY a.SubjectID
    ) assign ON s.SubjectID = assign.SubjectID
    WHERE s.DepartmentID = ? AND s.SemesterID = ?
    ORDER BY s.SubjectName
");

$subjectsQuery->bind_param("iiii", $studentID, $studentID, $studentRow['DepartmentID'], $studentRow['SemesterID']);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_student.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_student.css">
    <link rel="stylesheet" href="../../assets/css/my_subjects.css">

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
                <i data-lucide="book-open"></i>
                My Subjects
            </h2>
            <p class="text-muted mb-0">View your performance across all subjects</p>
        </div>

        <!-- Overall Performance Summary -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="book-open"></i>
                    </div>
                    <div class="mini-stat-value"><?= count($subjects) ?></div>
                    <div class="mini-stat-label">Total Subjects</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="calendar-check"></i>
                    </div>
                    <div class="mini-stat-value">
                        <?php
                        $totalSessions = array_sum(array_column($subjects, 'total_sessions'));
                        $totalPresent = array_sum(array_column($subjects, 'present_count'));
                        echo $totalSessions > 0 ? round(($totalPresent / $totalSessions) * 100) : 0;
                        ?>%
                    </div>
                    <div class="mini-stat-label">Overall Attendance</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="mini-stat-value"><?= array_sum(array_column($subjects, 'total_assignments')) ?></div>
                    <div class="mini-stat-label">Total Assignments</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mini-stat-card text-center p-3">
                    <div class="mini-stat-icon mb-2">
                        <i data-lucide="award"></i>
                    </div>
                    <div class="mini-stat-value">
                        <?php
                        $avgGrades = array_filter(array_column($subjects, 'avg_grade'));
                        echo !empty($avgGrades) ? round(array_sum($avgGrades) / count($avgGrades), 1) : 'N/A';
                        ?>
                    </div>
                    <div class="mini-stat-label">Average Grade</div>
                </div>
            </div>
        </div>

        <!-- Subjects Performance -->
        <div class="row g-4">
            <?php foreach ($subjects as $subject): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card subject-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($subject['SubjectCode']) ?></h5>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($subject['SubjectName']) ?></p>
                                </div>
                                <span class="badge bg-primary"><?= $subject['CreditHour'] ?> Credits</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Teacher Info -->
                            <div class="teacher-info mb-3">
                                <div class="d-flex align-items-center">
                                    <i data-lucide="user" class="me-2"></i>
                                    <span class="text-muted"><?= htmlspecialchars($subject['TeacherName']) ?></span>
                                </div>
                            </div>

                            <!-- Attendance Performance -->
                            <div class="performance-section mb-3">
                                <h6 class="section-title">
                                    <i data-lucide="calendar-check"></i>
                                    Attendance
                                </h6>
                                <?php if ($subject['total_sessions'] > 0): ?>
                                    <?php
                                    $attendanceRate = round(($subject['present_count'] / $subject['total_sessions']) * 100);
                                    $attendanceColor = $attendanceRate >= 80 ? 'success' : ($attendanceRate >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress mb-2" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $attendanceColor ?>"
                                            style="width: <?= $attendanceRate ?>%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?= $attendanceRate ?>% Present</small>
                                        <small class="text-muted"><?= $subject['total_sessions'] ?> sessions</small>
                                    </div>
                                    <div class="attendance-breakdown mt-2">
                                        <span class="badge bg-success me-1"><?= $subject['present_count'] ?> Present</span>
                                        <span class="badge bg-warning me-1"><?= $subject['late_count'] ?> Late</span>
                                        <span class="badge bg-danger"><?= $subject['absent_count'] ?> Absent</span>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No attendance records yet</p>
                                <?php endif; ?>
                            </div>

                            <!-- Assignment Performance -->
                            <div class="performance-section">
                                <h6 class="section-title">
                                    <i data-lucide="file-text"></i>
                                    Assignments
                                </h6>
                                <?php if ($subject['total_assignments'] > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><?= $subject['submitted_count'] ?>/<?= $subject['total_assignments'] ?> Submitted</small>
                                        <small class="text-muted"><?= $subject['graded_count'] ?> Graded</small>
                                    </div>
                                    <?php if ($subject['avg_grade'] > 0): ?>
                                        <div class="grade-info">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">Average Grade:</span>
                                                <span class="badge bg-info"><?= round($subject['avg_grade'], 1) ?>/<?= $subject['max_points'] ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No assignments yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewSubjectDetails(<?= $subject['SubjectID'] ?>, '<?= htmlspecialchars($subject['SubjectCode']) ?>', '<?= htmlspecialchars($subject['SubjectName']) ?>')">
                                <i data-lucide="eye"></i>
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($subjects)): ?>
            <!-- No Subjects Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="book-open" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>No Subjects Found</h4>
                    <p class="text-muted">You are not enrolled in any subjects for the current semester.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Subject Details Modal -->
    <div class="modal fade" id="subjectDetailsModal" tabindex="-1" aria-labelledby="subjectDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectDetailsModalLabel">
                        <i data-lucide="book-open"></i>
                        <span id="modalSubjectTitle">Subject Details</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading subject details...</p>
                    </div>

                    <div id="modalContent" style="display: none;">
                        <!-- Subject Overview -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Attendance Overview</h6>
                                        <div class="attendance-chart-container" style="height: 200px;">
                                            <canvas id="attendanceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Assignment Performance</h6>
                                        <div class="assignment-chart-container" style="height: 200px;">
                                            <canvas id="assignmentChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Statistics -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="stat-card text-center p-3">
                                    <div class="stat-icon mb-2">
                                        <i data-lucide="calendar-check"></i>
                                    </div>
                                    <div class="stat-value" id="modalAttendanceRate">0%</div>
                                    <div class="stat-label">Attendance Rate</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card text-center p-3">
                                    <div class="stat-icon mb-2">
                                        <i data-lucide="file-text"></i>
                                    </div>
                                    <div class="stat-value" id="modalAssignmentCompletion">0%</div>
                                    <div class="stat-label">Assignment Completion</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card text-center p-3">
                                    <div class="stat-icon mb-2">
                                        <i data-lucide="award"></i>
                                    </div>
                                    <div class="stat-value" id="modalAverageGrade">N/A</div>
                                    <div class="stat-label">Average Grade</div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance History -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i data-lucide="calendar"></i>
                                    Recent Attendance History
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm" id="attendanceHistoryTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Method</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attendanceHistoryBody">
                                            <!-- Attendance history will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Assignment History -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i data-lucide="file-text"></i>
                                    Assignment History
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm" id="assignmentHistoryTable">
                                        <thead>
                                            <tr>
                                                <th>Assignment</th>
                                                <th>Due Date</th>
                                                <th>Submitted</th>
                                                <th>Status</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assignmentHistoryBody">
                                            <!-- Assignment history will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="exportSubjectReport()">
                        <i data-lucide="download"></i>
                        Export Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../../assets/js/navbar_student.js"></script>
    <script src="../../assets/js/my_subjects.js"></script>
</body>

</html>