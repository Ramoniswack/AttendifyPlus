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
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Get attendance data
$attendanceData = [];
$summaryData = [];

if ($selectedSubjectID) {
    // Get students for the subject
    $studentsQuery = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.Contact
        FROM students s
        JOIN subjects sub ON s.SemesterID = sub.SemesterID AND s.DepartmentID = sub.DepartmentID
        WHERE sub.SubjectID = ?
        ORDER BY s.FullName
    ");
    $studentsQuery->bind_param("i", $selectedSubjectID);
    $studentsQuery->execute();
    $studentsResult = $studentsQuery->get_result();
    $students = [];
    while ($row = $studentsResult->fetch_assoc()) {
        $students[] = $row;
    }

    // Get attendance records for the selected date
    if ($selectedDate) {
        $attendanceQuery = $conn->prepare("
            SELECT ar.StudentID, ar.Status, ar.Method, ar.DateTime, s.FullName
            FROM attendance_records ar
            JOIN students s ON ar.StudentID = s.StudentID
            WHERE ar.TeacherID = ? AND ar.SubjectID = ? AND DATE(ar.DateTime) = ?
            ORDER BY s.FullName
        ");
        $attendanceQuery->bind_param("iis", $teacherID, $selectedSubjectID, $selectedDate);
        $attendanceQuery->execute();
        $attendanceResult = $attendanceQuery->get_result();
        while ($row = $attendanceResult->fetch_assoc()) {
            $attendanceData[$row['StudentID']] = $row;
        }
    }

    // Get monthly summary
    $summaryQuery = $conn->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN Status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN Method = 'qr' THEN 1 ELSE 0 END) as qr_count,
            SUM(CASE WHEN Method = 'manual' THEN 1 ELSE 0 END) as manual_count
        FROM attendance_records 
        WHERE TeacherID = ? AND SubjectID = ? AND DATE_FORMAT(DateTime, '%Y-%m') = ?
    ");
    $summaryQuery->bind_param("iis", $teacherID, $selectedSubjectID, $selectedMonth);
    $summaryQuery->execute();
    $summaryResult = $summaryQuery->get_result();
    $summaryData = $summaryResult->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | Attendify+</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link rel="stylesheet" href="../../assets/css/attendance_report.css">

    <!-- JS Libraries -->
    <script src="../../assets/js/lucide.min.js"></script>
    <script src="../../assets/js/sidebar_teacher.js" defer></script>
    <script src="../../assets/js/navbar_teacher.js" defer></script>
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
                    <i data-lucide="file-text"></i>
                    Attendance Report
                </h2>
                <p class="text-muted mb-0">Generate and view detailed attendance reports</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="dashboard_teacher.php" class="btn btn-outline-primary">
                    <i data-lucide="arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i data-lucide="filter"></i>
                    Report Filters
                </h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-select" required>
                            <option value="">Choose a subject...</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['SubjectID'] ?>"
                                    <?= $selectedSubjectID == $subject['SubjectID'] ? 'selected' : '' ?>>
                                    Semester <?= $subject['SemesterNumber'] ?> - <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Month (for summary)</label>
                        <input type="month" name="month" class="form-control" value="<?= $selectedMonth ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="search"></i>
                            Generate Report
                        </button>
                        <?php if ($selectedSubjectID && $selectedDate): ?>
                            <button type="button" class="btn btn-outline-success" onclick="exportReport()">
                                <i data-lucide="download"></i>
                                Export CSV
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedSubjectID && $selectedDate && isset($students)): ?>
            <?php
            $subject = array_filter($subjects, function ($s) use ($selectedSubjectID) {
                return $s['SubjectID'] == $selectedSubjectID;
            });
            $subject = reset($subject);
            ?>

            <!-- Summary Statistics -->
            <?php if ($summaryData): ?>
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="mini-stat-card text-center p-3">
                            <div class="mini-stat-icon mb-2">
                                <i data-lucide="calendar-check"></i>
                            </div>
                            <div class="mini-stat-value"><?= $summaryData['total_records'] ?? 0 ?></div>
                            <div class="mini-stat-label">Total Records (Month)</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="mini-stat-card text-center p-3">
                            <div class="mini-stat-icon mb-2">
                                <i data-lucide="check-circle"></i>
                            </div>
                            <div class="mini-stat-value"><?= $summaryData['present_count'] ?? 0 ?></div>
                            <div class="mini-stat-label">Present (Month)</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="mini-stat-card text-center p-3">
                            <div class="mini-stat-icon mb-2">
                                <i data-lucide="x-circle"></i>
                            </div>
                            <div class="mini-stat-value"><?= $summaryData['absent_count'] ?? 0 ?></div>
                            <div class="mini-stat-label">Absent (Month)</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="mini-stat-card text-center p-3">
                            <div class="mini-stat-icon mb-2">
                                <i data-lucide="clock"></i>
                            </div>
                            <div class="mini-stat-value"><?= $summaryData['late_count'] ?? 0 ?></div>
                            <div class="mini-stat-label">Late (Month)</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Attendance Report Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i data-lucide="list"></i>
                        Attendance Report - <?= htmlspecialchars($subject['SubjectCode'] . ' - ' . $subject['SubjectName']) ?>
                        (<?= date('F j, Y', strtotime($selectedDate)) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Method</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $presentCount = 0;
                                $absentCount = 0;
                                $lateCount = 0;
                                $qrCount = 0;
                                $manualCount = 0;
                                ?>
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                    $attendance = $attendanceData[$student['StudentID']] ?? null;
                                    if ($attendance) {
                                        if ($attendance['Status'] == 'present') $presentCount++;
                                        elseif ($attendance['Status'] == 'absent') $absentCount++;
                                        elseif ($attendance['Status'] == 'late') $lateCount++;

                                        if ($attendance['Method'] == 'qr') $qrCount++;
                                        elseif ($attendance['Method'] == 'manual') $manualCount++;
                                    } else {
                                        $absentCount++;
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($student['FullName']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($student['Contact']) ?></td>
                                        <td>
                                            <?php if ($attendance): ?>
                                                <?php if ($attendance['Status'] == 'present'): ?>
                                                    <span class="badge bg-success">Present</span>
                                                <?php elseif ($attendance['Status'] == 'absent'): ?>
                                                    <span class="badge bg-danger">Absent</span>
                                                <?php elseif ($attendance['Status'] == 'late'): ?>
                                                    <span class="badge bg-warning text-dark">Late</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Marked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($attendance): ?>
                                                <?php if ($attendance['Method'] == 'qr'): ?>
                                                    <span class="badge bg-info">QR Code</span>
                                                <?php elseif ($attendance['Method'] == 'manual'): ?>
                                                    <span class="badge bg-secondary">Manual</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($attendance): ?>
                                                <small class="text-muted">
                                                    <?= date('h:i A', strtotime($attendance['DateTime'])) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Daily Summary</h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="fw-bold text-success"><?= $presentCount ?></div>
                                            <small class="text-muted">Present</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-danger"><?= $absentCount ?></div>
                                            <small class="text-muted">Absent</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-warning"><?= $lateCount ?></div>
                                            <small class="text-muted">Late</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Method Summary</h6>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="fw-bold text-info"><?= $qrCount ?></div>
                                            <small class="text-muted">QR Code</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="fw-bold text-secondary"><?= $manualCount ?></div>
                                            <small class="text-muted">Manual</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedSubjectID): ?>
            <!-- No Data Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="file-x" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>No Attendance Data</h4>
                    <p class="text-muted">No attendance records found for the selected date. Please try a different date.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Select Subject Message -->
            <div class="card text-center">
                <div class="card-body py-5">
                    <i data-lucide="file-text" style="width: 64px; height: 64px;" class="text-muted mb-3"></i>
                    <h4>Select Subject and Date</h4>
                    <p class="text-muted">Choose a subject and date from the filters above to generate an attendance report.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function exportReport() {
            const subject = document.querySelector('select[name="subject"]').value;
            const date = document.querySelector('input[name="date"]').value;

            if (subject && date) {
                const url = `../../api/export_attendance_report.php?subject=${subject}&date=${date}`;
                window.open(url, '_blank');
            }
        }
    </script>
</body>

</html>