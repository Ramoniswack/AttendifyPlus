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

// Get filter parameters
$selectedSubjectID = $_GET['subject'] ?? null;
$selectedStudentID = $_GET['student'] ?? null;

// Get students for the selected subject
$students = [];
if ($selectedSubjectID) {
    $studentsQuery = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.Contact, s.PhotoURL, d.DepartmentName, sem.SemesterNumber
        FROM students s
        JOIN subjects sub ON s.SemesterID = sub.SemesterID AND s.DepartmentID = sub.DepartmentID
        JOIN departments d ON s.DepartmentID = d.DepartmentID
        JOIN semesters sem ON s.SemesterID = sem.SemesterID
        WHERE sub.SubjectID = ?
        ORDER BY s.FullName
    ");
    $studentsQuery->bind_param("i", $selectedSubjectID);
    $studentsQuery->execute();
    $studentsResult = $studentsQuery->get_result();
    while ($row = $studentsResult->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get student analytics if student is selected
$studentAnalytics = null;
if ($selectedStudentID && $selectedSubjectID) {
    // Get attendance stats
    $attendanceQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN Status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN Method = 'qr' THEN 1 ELSE 0 END) as qr_count,
            SUM(CASE WHEN Method = 'manual' THEN 1 ELSE 0 END) as manual_count
        FROM attendance_records 
        WHERE StudentID = ? AND SubjectID = ? AND TeacherID = ?
    ");
    $attendanceQuery->bind_param("iii", $selectedStudentID, $selectedSubjectID, $teacherID);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();
    $attendanceStats = $attendanceResult->fetch_assoc();

    // Get attendance trend (last 10 sessions)
    $trendQuery = $conn->prepare("
        SELECT Status, Method, DateTime
        FROM attendance_records 
        WHERE StudentID = ? AND SubjectID = ? AND TeacherID = ?
        ORDER BY DateTime DESC
        LIMIT 10
    ");
    $trendQuery->bind_param("iii", $selectedStudentID, $selectedSubjectID, $teacherID);
    $trendQuery->execute();
    $trendResult = $trendQuery->get_result();
    $attendanceTrend = [];
    while ($row = $trendResult->fetch_assoc()) {
        $attendanceTrend[] = $row;
    }

    // Get assignment submissions
    $assignmentQuery = $conn->prepare("
        SELECT 
            a.Title,
            a.DueDate,
            a.MaxPoints,
            s.SubmittedAt,
            s.Status,
            s.Grade,
            s.IsLate
        FROM assignments a
        LEFT JOIN assignment_submissions s ON a.AssignmentID = s.AssignmentID AND s.StudentID = ?
        WHERE a.SubjectID = ? AND a.TeacherID = ?
        ORDER BY a.DueDate DESC
    ");
    $assignmentQuery->bind_param("iii", $selectedStudentID, $selectedSubjectID, $teacherID);
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    $assignments = [];
    while ($row = $assignmentResult->fetch_assoc()) {
        $assignments[] = $row;
    }

    $studentAnalytics = [
        'attendance' => $attendanceStats,
        'trend' => $attendanceTrend,
        'assignments' => $assignments
    ];
}

// Get selected student info
$selectedStudent = null;
if ($selectedStudentID) {
    foreach ($students as $student) {
        if ($student['StudentID'] == $selectedStudentID) {
            $selectedStudent = $student;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Analytics - Attendify+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.css" rel="stylesheet">
    <link href="../../assets/css/dashboard_teacher.css" rel="stylesheet">
    <link href="../../assets/css/sidebar_teacher.css" rel="stylesheet">
    <link href="../../assets/css/students.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>
    <?php include '../components/navbar_teacher.php'; ?>

    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i data-lucide="users"></i>
                    Student Analytics
                </h1>
                <p class="text-muted">View detailed analytics for individual students</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($selectedStudentID && $selectedSubjectID): ?>
                    <button class="btn btn-outline-primary" onclick="exportStudentReport()">
                        <i data-lucide="download"></i>
                        Export Report
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="subject" class="form-label">Select Subject</label>
                        <select name="subject" id="subject" class="form-select" onchange="this.form.submit()">
                            <option value="">Choose a subject...</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['SubjectID'] ?>"
                                    <?= $selectedSubjectID == $subject['SubjectID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                                    (Semester <?= $subject['SemesterNumber'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selectedSubjectID && !empty($students)): ?>
                        <div class="col-md-6">
                            <label for="student" class="form-label">Select Student</label>
                            <select name="student" id="student" class="form-select" onchange="this.form.submit()">
                                <option value="">Choose a student...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['StudentID'] ?>"
                                        <?= $selectedStudentID == $student['StudentID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['FullName']) ?>
                                        (<?= htmlspecialchars($student['DepartmentName']) ?> - Sem <?= $student['SemesterNumber'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($selectedStudent && $studentAnalytics): ?>
            <!-- Student Profile -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i data-lucide="user"></i>
                                Student Profile
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <div class="student-photo mb-3">
                                        <?php if ($selectedStudent['PhotoURL']): ?>
                                            <img src="<?= htmlspecialchars($selectedStudent['PhotoURL']) ?>"
                                                alt="Student Photo" class="rounded-circle" width="80" height="80">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto"
                                                style="width: 80px; height: 80px;">
                                                <i data-lucide="user" style="width: 32px; height: 32px;" class="text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h4><?= htmlspecialchars($selectedStudent['FullName']) ?></h4>
                                    <p class="text-muted mb-2">
                                        <i data-lucide="phone" style="width: 16px; height: 16px;"></i>
                                        <?= htmlspecialchars($selectedStudent['Contact']) ?>
                                    </p>
                                    <p class="text-muted mb-2">
                                        <i data-lucide="graduation-cap" style="width: 16px; height: 16px;"></i>
                                        <?= htmlspecialchars($selectedStudent['DepartmentName']) ?> - Semester <?= $selectedStudent['SemesterNumber'] ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i data-lucide="bar-chart-3"></i>
                                Quick Stats
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="fw-bold text-success fs-4">
                                        <?= $studentAnalytics['attendance']['present_count'] ?? 0 ?>
                                    </div>
                                    <small class="text-muted">Present</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="fw-bold text-danger fs-4">
                                        <?= $studentAnalytics['attendance']['absent_count'] ?? 0 ?>
                                    </div>
                                    <small class="text-muted">Absent</small>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold text-warning fs-4">
                                        <?= $studentAnalytics['attendance']['late_count'] ?? 0 ?>
                                    </div>
                                    <small class="text-muted">Late</small>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold text-info fs-4">
                                        <?= $studentAnalytics['attendance']['total_sessions'] ?? 0 ?>
                                    </div>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Charts -->
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
                                <i data-lucide="trending-up"></i>
                                Attendance Trend (Last 10 Sessions)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Performance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i data-lucide="book-open"></i>
                        Assignment Performance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Due Date</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Grade</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentAnalytics['assignments'] as $assignment): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($assignment['Title']) ?></strong>
                                        </td>
                                        <td>
                                            <?= $assignment['DueDate'] ? date('M j, Y', strtotime($assignment['DueDate'])) : 'N/A' ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['SubmittedAt']): ?>
                                                <span class="badge bg-success">Submitted</span>
                                                <br>
                                                <small class="text-muted">
                                                    <?= date('M j, Y', strtotime($assignment['SubmittedAt'])) ?>
                                                    <?php if ($assignment['IsLate']): ?>
                                                        <span class="text-danger">(Late)</span>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['Status'] == 'graded'): ?>
                                                <span class="badge bg-info">Graded</span>
                                            <?php elseif ($assignment['Status'] == 'submitted'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['Grade'] !== null): ?>
                                                <strong><?= $assignment['Grade'] ?>/<?= $assignment['MaxPoints'] ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['Grade'] !== null && $assignment['MaxPoints'] > 0): ?>
                                                <?php
                                                $percentage = ($assignment['Grade'] / $assignment['MaxPoints']) * 100;
                                                if ($percentage >= 80) {
                                                    $color = 'success';
                                                } elseif ($percentage >= 60) {
                                                    $color = 'warning';
                                                } else {
                                                    $color = 'danger';
                                                }
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?= $color ?>"
                                                        style="width: <?= $percentage ?>%">
                                                        <?= round($percentage) ?>%
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedSubjectID && empty($students)): ?>
            <!-- No Students Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="users" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>No Students Found</h4>
                    <p class="text-muted">No students are enrolled in this subject for your class.</p>
                </div>
            </div>
        <?php elseif ($selectedSubjectID): ?>
            <!-- Select Student Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="user-check" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>Select a Student</h4>
                    <p class="text-muted">Choose a student from the dropdown above to view their detailed analytics.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Select Subject Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="book-open" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>Select a Subject</h4>
                    <p class="text-muted">Choose a subject from the dropdown above to view student analytics.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../../assets/js/navbar_teacher.js"></script>
    <script src="../../assets/js/students.js"></script>

    <?php if ($selectedStudent && $studentAnalytics): ?>
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
                            data: [
                                <?= $studentAnalytics['attendance']['present_count'] ?? 0 ?>,
                                <?= $studentAnalytics['attendance']['absent_count'] ?? 0 ?>,
                                <?= $studentAnalytics['attendance']['late_count'] ?? 0 ?>
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

                // Attendance Trend Chart
                const trendCtx = document.getElementById('trendChart').getContext('2d');
                const trendData = <?= json_encode(array_reverse($studentAnalytics['trend'])) ?>;

                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendData.map(item => new Date(item.DateTime).toLocaleDateString()),
                        datasets: [{
                            label: 'Attendance Status',
                            data: trendData.map(item => {
                                if (item.Status === 'present') return 3;
                                if (item.Status === 'late') return 2;
                                return 1;
                            }),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 3,
                                ticks: {
                                    stepSize: 1,
                                    callback: function(value) {
                                        if (value === 3) return 'Present';
                                        if (value === 2) return 'Late';
                                        if (value === 1) return 'Absent';
                                        return '';
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            });

            function exportStudentReport() {
                const subject = document.querySelector('select[name="subject"]').value;
                const student = document.querySelector('select[name="student"]').value;

                if (subject && student) {
                    const url = `../../api/export_student_report.php?subject=${subject}&student=${student}`;
                    window.open(url, '_blank');
                }
            }
        </script>
    <?php endif; ?>
</body>

</html>