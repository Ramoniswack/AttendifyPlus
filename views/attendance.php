<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\attendance.php
session_start();
require_once(__DIR__ . '/../config/db_config.php');

// Check session
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    header("Location: /attendifyplus/views/login.php");
    exit();
}

$loginID = $_SESSION['LoginID'];

// Get teacher info
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
if (!$teacherStmt) {
    die("Prepare failed: " . $conn->error);
}
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();

if (!$teacherRow) {
    echo "<script>alert('Your login is not linked with a valid teacher account. Please contact admin.'); window.location.href='../logout.php';</script>";
    exit();
}

$teacherID = $teacherRow['TeacherID'];

// Get department via subjects assigned to teacher
$deptStmt = $conn->prepare("
    SELECT DISTINCT d.DepartmentID, d.DepartmentName
    FROM departments d
    JOIN subjects s ON s.DepartmentID = d.DepartmentID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
    LIMIT 1
");
if (!$deptStmt) {
    die("Prepare failed: " . $conn->error);
}
$deptStmt->bind_param("i", $teacherID);
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
$teacherDept = $deptResult->fetch_assoc();

if (!$teacherDept) {
    die("No department found for this teacher.");
}


// Get semesters
$semQuery = $conn->prepare("
    SELECT DISTINCT sem.SemesterID, sem.SemesterNumber
    FROM semesters sem
    JOIN subjects s ON s.SemesterID = sem.SemesterID
    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
    WHERE ts.TeacherID = ?
    ORDER BY sem.SemesterNumber
");
=======
// Fetch distinct semesters where teacher is assigned
$semQuery = $conn->prepare("SELECT DISTINCT sem.SemesterID, sem.SemesterNumber
                            FROM semesters sem
                            JOIN subjects s ON s.SemesterID = sem.SemesterID
                            JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
                            WHERE ts.TeacherID = ?");
if (!$semQuery) {
    die("Prepare failed: " . $conn->error);
}

$semQuery->bind_param("i", $teacherID);
$semQuery->execute();
$semResult = $semQuery->get_result();

$selectedSemesterID = $_POST['semester'] ?? null;
$selectedSubjectID = $_POST['subject'] ?? null;

$date = $_POST['date'] ?? date('Y-m-d');
$successMsg = isset($_GET['success']) ? "Attendance saved successfully." : "";
$errorMsg = $_GET['error'] ?? "";
=======


// Handle form submission (both new and update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {

    if (!$selectedSubjectID || !$selectedSemesterID || !$date) {
        header("Location: attendance.php?error=Missing required fields.");
        exit();
    }

    // Check if attendance already exists
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
    $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $attendanceExists = $result->fetch_assoc()['count'] > 0;

    $conn->begin_transaction();
    try {
        if ($attendanceExists) {
            // Update existing attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $updateStmt = $conn->prepare("
                    UPDATE attendance_records 
                    SET Status = ?, DateTime = ? 
                    WHERE StudentID = ? AND TeacherID = ? AND SubjectID = ? AND DATE(DateTime) = ?
                ");
                $dateTime = $date . ' ' . date('H:i:s');
                $updateStmt->bind_param("siiiis", $status, $dateTime, $studentID, $teacherID, $selectedSubjectID, $date);
                $updateStmt->execute();
            }
        } else {
            // Insert new attendance records
            foreach ($_POST['attendance'] as $studentID => $status) {
                $insertStmt = $conn->prepare("INSERT INTO attendance_records (StudentID, TeacherID, SubjectID, DateTime, Status) VALUES (?, ?, ?, ?, ?)");
                $dateTime = $date . ' ' . date('H:i:s');
                $insertStmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $dateTime, $status);
                $insertStmt->execute();
            }
        }
        $conn->commit();
        header("Location: attendance.php?success=1&semester=" . $selectedSemesterID . "&subject=" . $selectedSubjectID . "&date=" . $date);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: attendance.php?error=Failed to save attendance. Please try again.");
        exit();
    }
=======
    $date = $_POST['date'];

    if (!$selectedSubjectID) {
        die("Subject is required.");
    }

    foreach ($_POST['attendance'] as $studentID => $status) {
        $stmt = $conn->prepare("INSERT INTO attendance (StudentID, TeacherID, SubjectID, Date, Status)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $studentID, $teacherID, $selectedSubjectID, $date, $status);
        $stmt->execute();
    }

    echo "<script>alert('Attendance submitted successfully.'); window.location.href='attendance.php';</script>";
    exit();

}

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mark Attendance | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/attendance.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- JS -->
    <script src="../assets/js/lucide.min.js"></script>
    <script src="../assets/js/attendance.js" defer></script>

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mark Attendance | Attendify+</title>
  <link rel="stylesheet" href="../assets/css/manage_teacher.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <script src="../assets/js/lucide.min.js"></script>
  <script src="../assets/js/manage_teacher.js" defer></script>

</head>
<body>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_teacher_dashboard.php'; ?>

    <!-- Navbar -->
    <?php include 'navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid attendance-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="page-title">
                        <i data-lucide="clipboard-check"></i>
                        Mark Attendance
                    </h2>
                    <p class="text-muted mb-0">Record and update student attendance for your classes</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="attendance_report.php" class="btn btn-outline-primary">
                        <i data-lucide="bar-chart-3"></i>
                        <span class="d-none d-sm-inline">View Reports</span>
                    </a>
                    <a href="dashboard_teacher.php" class="btn btn-secondary">
                        <i data-lucide="arrow-left"></i>
                        <span class="d-none d-sm-inline">Back to Dashboard</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show custom-alert" role="alert">
                <div class="d-flex align-items-center">
                    <i data-lucide="check-circle" class="me-2"></i>
                    <div>
                        <strong>Success!</strong> <?= htmlspecialchars($successMsg) ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show custom-alert" role="alert">
                <div class="d-flex align-items-center">
                    <i data-lucide="alert-circle" class="me-2"></i>
                    <div>
                        <strong>Error!</strong> <?= htmlspecialchars($errorMsg) ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Department Info Card -->
        <div class="info-card mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="info-icon">
                    <i data-lucide="building-2"></i>
                </div>
                <div>
                    <h5 class="mb-1">Department</h5>
                    <p class="text-muted mb-0"><?= htmlspecialchars($teacherDept['DepartmentName']) ?></p>
                </div>
            </div>
        </div>

        <!-- Selection Form -->
        <div class="selection-card">
            <form method="POST" id="selectionForm">
                <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">

                <div class="row g-4">
                    <!-- Date Selection -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i data-lucide="calendar"></i>
                                Select Date
                            </label>
                            <input type="date"
                                name="date"
                                value="<?= $date ?>"
                                class="form-control modern-input"
                                max="<?= date('Y-m-d') ?>"
                                onchange="handleDateChange()"
                                required />
                            <small class="form-text text-muted mt-1">
                                <i data-lucide="info" style="width: 12px; height: 12px;"></i>
                                You can update attendance for past dates too
                            </small>
                        </div>
                    </div>

                    <!-- Semester Selection -->
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i data-lucide="layers"></i>
                                Select Semester
                            </label>
                            <select name="semester"
                                class="form-select modern-select"
                                onchange="handleSemesterChange()"
                                required>
                                <option value="">Choose Semester</option>
                                <?php while ($sem = $semResult->fetch_assoc()): ?>
                                    <option value="<?= $sem['SemesterID'] ?>"
                                        <?= $selectedSemesterID == $sem['SemesterID'] ? 'selected' : '' ?>>
                                        Semester <?= $sem['SemesterNumber'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Subject Selection -->
                    <div class="col-lg-4 col-md-12">
                        <div class="form-group">
                            <label class="form-label">
                                <i data-lucide="book-open"></i>
                                Select Subject
                            </label>
                            <?php if ($selectedSemesterID): ?>
                                <?php
                                $subjectQuery = $conn->prepare("
                                    SELECT s.SubjectID, s.SubjectName, s.SubjectCode
                                    FROM subjects s
                                    JOIN teacher_subject_map ts ON ts.SubjectID = s.SubjectID
                                    WHERE ts.TeacherID = ? AND s.SemesterID = ?
                                    ORDER BY s.SubjectName
                                ");
                                $subjectQuery->bind_param("ii", $teacherID, $selectedSemesterID);
                                $subjectQuery->execute();
                                $subjectResult = $subjectQuery->get_result();
                                ?>
                                <select name="subject"
                                    class="form-select modern-select"
                                    onchange="handleSubjectChange()"
                                    required>
                                    <option value="">Choose Subject</option>
                                    <?php while ($sub = $subjectResult->fetch_assoc()): ?>
                                        <option value="<?= $sub['SubjectID'] ?>"
                                            <?= $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sub['SubjectCode']) ?> - <?= htmlspecialchars($sub['SubjectName']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            <?php else: ?>
                                <select class="form-select modern-select" disabled>
                                    <option>Select semester first</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Student Attendance Section -->
        <?php if ($selectedSemesterID && $selectedSubjectID): ?>
            <?php
            // Check if attendance already exists for this date
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance_records WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?");
            $checkStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $attendanceExists = $result->fetch_assoc()['count'] > 0;

            // Get existing attendance records if they exist
            $existingAttendance = [];
            if ($attendanceExists) {
                $existingStmt = $conn->prepare("
                    SELECT StudentID, Status 
                    FROM attendance_records 
                    WHERE SubjectID = ? AND DATE(DateTime) = ? AND TeacherID = ?
                ");
                $existingStmt->bind_param("isi", $selectedSubjectID, $date, $teacherID);
                $existingStmt->execute();
                $existingResult = $existingStmt->get_result();
                while ($row = $existingResult->fetch_assoc()) {
                    $existingAttendance[$row['StudentID']] = $row['Status'];
                }
            }

            // Get students
            $studentsQuery = $conn->prepare("
                SELECT StudentID, FullName, ProgramCode
                FROM students 
                WHERE DepartmentID = ? AND SemesterID = ? 
                ORDER BY FullName
            ");
            $studentsQuery->bind_param("ii", $teacherDept['DepartmentID'], $selectedSemesterID);
            $studentsQuery->execute();
            $students = $studentsQuery->get_result();
            $studentCount = $students->num_rows;

            // Get subject info
            $subjectInfoQuery = $conn->prepare("SELECT SubjectName, SubjectCode FROM subjects WHERE SubjectID = ?");
            $subjectInfoQuery->bind_param("i", $selectedSubjectID);
            $subjectInfoQuery->execute();
            $subjectInfo = $subjectInfoQuery->get_result()->fetch_assoc();
            ?>

            <div class="attendance-section">
                <!-- Attendance Header -->
                <div class="attendance-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                        <div>
                            <h4 class="attendance-title">
                                <i data-lucide="users"></i>
                                <?= htmlspecialchars($subjectInfo['SubjectCode']) ?> - <?= htmlspecialchars($subjectInfo['SubjectName']) ?>
                            </h4>
                            <p class="text-muted mb-0">
                                <?= date('l, F j, Y', strtotime($date)) ?> • <?= $studentCount ?> Students
                                <?php if ($attendanceExists): ?>
                                    <span class="badge bg-info ms-2">
                                        <i data-lucide="edit-3" style="width: 12px; height: 12px;"></i>
                                        Update Mode
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="attendance-actions">
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="markAllPresent()">
                                <i data-lucide="check-circle"></i> Mark All Present
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAllAbsent()">
                                <i data-lucide="x-circle"></i> Mark All Absent
                            </button>
                            <?php if ($attendanceExists): ?>
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="resetToOriginal()">
                                    <i data-lucide="undo"></i> Reset to Original
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($attendanceExists): ?>
                        <div class="alert alert-warning custom-alert">
                            <div class="d-flex align-items-center">
                                <i data-lucide="edit-3" class="me-2"></i>
                                <div>
                                    <strong>Update Mode:</strong> Attendance has already been marked for this date. You can update the records below.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attendance Form -->
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">
                    <input type="hidden" name="semester" value="<?= $selectedSemesterID ?>">
                    <input type="hidden" name="subject" value="<?= $selectedSubjectID ?>">
                    <input type="hidden" name="date" value="<?= $date ?>">

                    <!-- Students Grid -->
                    <div class="students-grid">
                        <?php
                        $students->data_seek(0); // Reset result pointer
                        $studentIndex = 1; // For numbering students
                        while ($student = $students->fetch_assoc()):
                            $studentID = $student['StudentID'];
                            $currentStatus = $existingAttendance[$studentID] ?? null;
                        ?>
                            <div class="student-card <?= $currentStatus ? 'has-existing' : '' ?>" data-student-id="<?= $studentID ?>" data-original-status="<?= $currentStatus ?>">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($student['FullName'], 0, 2)) ?>
                                    </div>
                                    <div class="student-details">
                                        <h6 class="student-name"><?= htmlspecialchars($student['FullName']) ?></h6>
                                        <div class="student-meta">
                                            <span class="student-number">Student #<?= $studentIndex ?></span>
                                            <?php if ($student['ProgramCode']): ?>
                                                <span class="student-program"><?= htmlspecialchars($student['ProgramCode']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($currentStatus): ?>
                                                <span class="current-status badge bg-<?= $currentStatus === 'present' ? 'success' : ($currentStatus === 'late' ? 'warning' : 'danger') ?>">
                                                    Current: <?= ucfirst($currentStatus) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="attendance-options">
                                    <div class="btn-group attendance-toggle" role="group">
                                        <input type="radio"
                                            class="btn-check"
                                            name="attendance[<?= $studentID ?>]"
                                            id="present_<?= $studentID ?>"
                                            value="present"
                                            <?= $currentStatus === 'present' ? 'checked' : '' ?>
                                            required>
                                        <label class="btn btn-attendance btn-present" for="present_<?= $studentID ?>">
                                            <i data-lucide="check"></i>
                                            <span>Present</span>
                                        </label>

                                        <input type="radio"
                                            class="btn-check"
                                            name="attendance[<?= $studentID ?>]"
                                            id="absent_<?= $studentID ?>"
                                            value="absent"
                                            <?= $currentStatus === 'absent' ? 'checked' : '' ?>>
                                        <label class="btn btn-attendance btn-absent" for="absent_<?= $studentID ?>">
                                            <i data-lucide="x"></i>
                                            <span>Absent</span>
                                        </label>

                                        <input type="radio"
                                            class="btn-check"
                                            name="attendance[<?= $studentID ?>]"
                                            id="late_<?= $studentID ?>"
                                            value="late"
                                            <?= $currentStatus === 'late' ? 'checked' : '' ?>>
                                        <label class="btn btn-attendance btn-late" for="late_<?= $studentID ?>">
                                            <i data-lucide="clock"></i>
                                            <span>Late</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php
                            $studentIndex++;
                        endwhile;
                        ?>
                    </div>

                    <!-- Submit Section -->
                    <div class="submit-section">
                        <div class="attendance-summary">
                            <div class="summary-stats">
                                <div class="stat-item">
                                    <span class="stat-number" id="presentCount">0</span>
                                    <span class="stat-label">Present</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" id="absentCount">0</span>
                                    <span class="stat-label">Absent</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" id="lateCount">0</span>
                                    <span class="stat-label">Late</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" id="totalCount"><?= $studentCount ?></span>
                                    <span class="stat-label">Total</span>
                                </div>
                            </div>
                        </div>

                        <div class="submit-actions">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i data-lucide="refresh-cw"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary btn-submit" id="submitBtn">
                                <i data-lucide="save"></i>
                                <?= $attendanceExists ? 'Update Attendance' : 'Submit Attendance' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php elseif ($selectedSemesterID || $selectedSubjectID): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="clipboard-list"></i>
                </div>
                <h4>Select All Required Fields</h4>
                <p class="text-muted">Please select date, semester, and subject to view students and mark attendance.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Simple sidebar functionality - Same as dashboard_teacher.php
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (!sidebar || !sidebarOverlay || !sidebarToggle) {
                console.error('Sidebar elements not found');
                return;
            }
            
            // Toggle sidebar when button is clicked
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                } else {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                    document.body.classList.add('sidebar-open');
                }
            });
            
            // Close sidebar when overlay is clicked
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            });
            
            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });
            
            // Handle responsive behavior
            function handleResize() {
                if (window.innerWidth >= 1200) {
                    sidebarOverlay.classList.remove('active');
                } else {
                    if (sidebar.classList.contains('active')) {
                        sidebarOverlay.classList.add('active');
                    }
                }
            }
            
            window.addEventListener('resize', handleResize);
            handleResize();
        });

        // Store original attendance data
        window.originalAttendance = {};
        document.querySelectorAll('.student-card[data-original-status]').forEach(card => {
            const studentId = card.dataset.studentId;
            const originalStatus = card.dataset.originalStatus;
            if (originalStatus) {
                window.originalAttendance[studentId] = originalStatus;
            }
        });

        // Reset to original function
        function resetToOriginal() {
            Object.keys(window.originalAttendance).forEach(studentId => {
                const status = window.originalAttendance[studentId];
                const radio = document.getElementById(status + '_' + studentId);
                if (radio) {
                    radio.checked = true;
                }
            });
            if (typeof updateAttendanceStats === 'function') {
                updateAttendanceStats();
            }
            console.log('Reset to original attendance');
        }
    </script>
</body>
</body>
</html>
=======
    <?php include 'sidebar_admin_dashboard.php'; ?>
    <?php include 'navbar_admin.php'; ?>

<div class="container pt-5 mt-5">
    <h2>Mark Attendance</h2>
    <p><strong>Department:</strong> <?= htmlspecialchars($teacherDept['DepartmentName']) ?></p>

    <form method="POST">
        <input type="hidden" name="department" value="<?= $teacherDept['DepartmentID'] ?>">

        <label for="semesterSelect" class="form-label">Semester</label>
        <select name="semester" id="semesterSelect" class="form-select mb-3" onchange="this.form.submit()" required>
            <option value="">Select Semester</option>
            <?php while ($sem = $semResult->fetch_assoc()): ?>
                <option value="<?= $sem['SemesterID'] ?>" <?= $selectedSemesterID == $sem['SemesterID'] ? 'selected' : '' ?>>
                    Semester <?= $sem['SemesterNumber'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <?php if ($selectedSemesterID): ?>
            <?php
            $subjectQuery = $conn->prepare("SELECT s.SubjectID, s.SubjectName
                                            FROM subjects s
                                            JOIN teacher_subject_map ts ON s.SubjectID = ts.SubjectID
                                            WHERE ts.TeacherID = ? AND s.SemesterID = ?");
            if (!$subjectQuery) {
                die("Prepare failed: " . $conn->error);
            }
            $subjectQuery->bind_param("ii", $teacherID, $selectedSemesterID);
            $subjectQuery->execute();
            $subjectResult = $subjectQuery->get_result();
            ?>

            <label for="subjectSelect" class="form-label">Subject</label>
            <select name="subject" id="subjectSelect" class="form-select mb-3" onchange="this.form.submit()" required>
                <option value="">Select Subject</option>
                <?php while ($sub = $subjectResult->fetch_assoc()): ?>
                    <option value="<?= $sub['SubjectID'] ?>" <?= $selectedSubjectID == $sub['SubjectID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sub['SubjectName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        <?php endif; ?>

        <?php if ($selectedSemesterID && $selectedSubjectID): ?>
            <label for="dateInput" class="form-label">Date:</label>
            <input type="date" id="dateInput" name="date" value="<?= date('Y-m-d') ?>" required class="form-control mb-3" />

            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Exam Roll</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $studentsQuery = $conn->prepare("SELECT StudentID, FullName, ExamRoll
                                                 FROM students
                                                 WHERE DepartmentID = ? AND SemesterID = ?");
                if (!$studentsQuery) {
                    die("Prepare failed: " . $conn->error);
                }
                $studentsQuery->bind_param("ii", $teacherDept['DepartmentID'], $selectedSemesterID);
                $studentsQuery->execute();
                $students = $studentsQuery->get_result();

                while ($row = $students->fetch_assoc()):
                    $sid = $row['StudentID'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['FullName']) ?></td>
                        <td><?= htmlspecialchars($row['ExamRoll']) ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="present_<?= $sid ?>" value="present" required>
                                <label class="btn btn-outline-success btn-sm" for="present_<?= $sid ?>">Present</label>

                                <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="absent_<?= $sid ?>" value="absent">
                                <label class="btn btn-outline-danger btn-sm" for="absent_<?= $sid ?>">Absent</label>

                                <input type="radio" class="btn-check" name="attendance[<?= $sid ?>]" id="late_<?= $sid ?>" value="late">
                                <label class="btn btn-outline-warning btn-sm" for="late_<?= $sid ?>">Late</label>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <div class="text-end">
                <button type="submit" class="btn btn-success">
                    <i data-lucide="check-circle"></i> Submit Attendance
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>

