<?php
// filepath: d:\NEEDS\6th sem\New folder\htdocs\AttendifyPlus\views\teacher\assignments.php
session_start();
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'teacher') {
    error_log("Unauthorized access attempt to assignments.php. UserID: " . ($_SESSION['UserID'] ?? 'not set'));
    header("Location: ../auth/login.php");
    exit();
}

include '../../config/db_config.php';
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact the administrator.");
}

// Get teacher information
$loginID = $_SESSION['LoginID'];
$teacherStmt = $conn->prepare("SELECT TeacherID, FullName FROM teachers WHERE LoginID = ?");
if (!$teacherStmt) {
    error_log("Teacher query preparation failed: " . $conn->error);
    header("Location: ../../logout.php");
    exit();
}
$teacherStmt->bind_param("i", $loginID);
$teacherStmt->execute();
$teacherRes = $teacherStmt->get_result();
$teacherRow = $teacherRes->fetch_assoc();
$teacherStmt->close();

if (!$teacherRow) {
    error_log("Teacher not found for LoginID: $loginID");
    header("Location: ../../logout.php");
    exit();
}

$teacherID = $teacherRow['TeacherID'];
$teacherName = $teacherRow['FullName'];

// Get teacher's subjects for filtering
$subjectsQuery = $conn->prepare("
    SELECT s.SubjectID, s.SubjectCode, s.SubjectName
    FROM subjects s
    JOIN teacher_subject_map tsm ON s.SubjectID = tsm.SubjectID
    WHERE tsm.TeacherID = ?
    ORDER BY s.SubjectName
");
if (!$subjectsQuery) {
    error_log("Subjects query preparation failed: " . $conn->error);
    $subjects = [];
} else {
    $subjectsQuery->bind_param("i", $teacherID);
    $subjectsQuery->execute();
    $subjectsResult = $subjectsQuery->get_result();
    $subjects = $subjectsResult->fetch_all(MYSQLI_ASSOC);
    $subjectsQuery->close();
}

// Get assignments from database
$activeAssignments = [];
$draftAssignments = [];
$gradedAssignments = [];

// Check if assignments table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'assignments'");
if ($tableCheck->num_rows > 0) {
    // Get active assignments with analytics
    $activeQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName,
               COUNT(sub.SubmissionID) as submissions,
               COUNT(DISTINCT st.StudentID) as total_students,
               SUM(CASE WHEN sub.Status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
               SUM(CASE WHEN av.ViewCount > 0 THEN 1 ELSE 0 END) as viewed_count
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        LEFT JOIN assignment_submissions sub ON a.AssignmentID = sub.AssignmentID
        LEFT JOIN assignment_views av ON a.AssignmentID = av.AssignmentID
        LEFT JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
        WHERE a.TeacherID = ? AND a.Status = 'active' AND a.IsActive = 1
        GROUP BY a.AssignmentID, s.SubjectCode, s.SubjectName
        ORDER BY a.DueDate ASC
    ");
    if (!$activeQuery) {
        error_log("Active query preparation failed: " . $conn->error);
        $activeAssignments = [];
    } else {
        $activeQuery->bind_param("i", $teacherID);
        $activeQuery->execute();
        $activeResult = $activeQuery->get_result();
        while ($row = $activeResult->fetch_assoc()) {
            $row['views'] = ($row['viewed_count'] ?? 0) + ($row['submitted_count'] ?? 0);
            $row['pending'] = max(0, ($row['total_students'] ?? 0) - ($row['submissions'] ?? 0));
            $activeAssignments[] = $row;
        }
        $activeQuery->close();
    }

    // Get draft assignments
    $draftQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName,
               0 as submissions,
               COUNT(DISTINCT st.StudentID) as total_students,
               0 as views,
               0 as pending
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        LEFT JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
        WHERE a.TeacherID = ? AND a.Status = 'draft' AND a.IsActive = 1
        GROUP BY a.AssignmentID, s.SubjectCode, s.SubjectName
        ORDER BY a.CreatedAt DESC
    ");
    if (!$draftQuery) {
        error_log("Draft query preparation failed: " . $conn->error);
        $draftAssignments = [];
    } else {
        $draftQuery->bind_param("i", $teacherID);
        $draftQuery->execute();
        $draftResult = $draftQuery->get_result();
        while ($row = $draftResult->fetch_assoc()) {
            $draftAssignments[] = $row;
        }
        $draftQuery->close();
    }

    // Get graded assignments with analytics
    $gradedQuery = $conn->prepare("
        SELECT a.*, s.SubjectCode, s.SubjectName,
               COUNT(sub.SubmissionID) as submissions,
               COUNT(DISTINCT st.StudentID) as total_students,
               AVG(CASE WHEN sub.Grade IS NOT NULL THEN sub.Grade ELSE NULL END) as avg_score,
               SUM(CASE WHEN sub.Status = 'graded' THEN 1 ELSE 0 END) as graded_count,
               SUM(CASE WHEN sub.Status = 'submitted' OR sub.Status = 'graded' THEN 1 ELSE 0 END) as submitted_count
        FROM assignments a
        JOIN subjects s ON a.SubjectID = s.SubjectID
        LEFT JOIN assignment_submissions sub ON a.AssignmentID = sub.AssignmentID
        LEFT JOIN students st ON s.SemesterID = st.SemesterID AND s.DepartmentID = st.DepartmentID
        WHERE a.TeacherID = ? AND a.Status = 'graded' AND a.IsActive = 1
        GROUP BY a.AssignmentID, s.SubjectCode, s.SubjectName
        ORDER BY a.UpdatedAt DESC
    ");
    if (!$gradedQuery) {
        error_log("Graded query preparation failed: " . $conn->error);
        $gradedAssignments = [];
    } else {
        $gradedQuery->bind_param("i", $teacherID);
        $gradedQuery->execute();
        $gradedResult = $gradedQuery->get_result();
        while ($row = $gradedResult->fetch_assoc()) {
            $row['views'] = $row['submitted_count'] ?? 0;
            $row['pending'] = max(0, ($row['total_students'] ?? 0) - ($row['submissions'] ?? 0));
            $gradedAssignments[] = $row;
        }
        $gradedQuery->close();
    }
} else {
    error_log("Assignments table does not exist in the database.");
    $activeAssignments = [];
    $draftAssignments = [];
    $gradedAssignments = [];
}

$conn->close();

// Helper functions
function getTimeRemaining($dueDate)
{
    if (!$dueDate) return 'No due date';
    $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu')); // Nepal timezone
    $due = new DateTime($dueDate, new DateTimeZone('Asia/Kathmandu'));
    if ($due < $now) return 'Past due';
    $diff = $now->diff($due);
    if ($diff->days > 0) return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' remaining';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' remaining';
    return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' remaining';
}

function getSubmissionProgress($submissions, $total)
{
    if ($total == 0) return 0;
    return round(($submissions / $total) * 100);
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Attendify+</title>

    <!-- CSS -->
    <link rel="stylesheet" href="/AttendifyPlus/assets/css/dashboard_teacher.css">
    <link rel="stylesheet" href="../../assets/css/sidebar_teacher.css">
    <link rel="stylesheet" href="/AttendifyPlus/assets/css/assignments.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <!-- JS Libraries -->
    <script src="/AttendifyPlus/assets/js/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/AttendifyPlus/assets/js/sidebar_teacher.js" defer></script>
    <script src="/AttendifyPlus/assets/js/assignments.js" defer></script>
    <script src="/AttendifyPlus/assets/js/navbar_teacher.js" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar_teacher_dashboard.php'; ?>
    <!-- Navbar -->
    <?php include '../components/navbar_teacher.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid dashboard-container main-content">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="page-title">
                    <i data-lucide="clipboard-list"></i>
                    Assignments
                </h2>
                <p class="text-muted mb-0">Manage and track your student assignments</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-primary" onclick="refreshData()" title="Refresh">
                    <i data-lucide="refresh-cw"></i>
                </button>
                <button class="btn btn-primary" onclick="createAssignment()">
                    <i data-lucide="plus"></i>
                    Create Assignment
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= count($activeAssignments) ?></div>
                            <div>Active</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                    Accepting submissions
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card teachers text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= count($draftAssignments) ?></div>
                            <div>Drafts</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    Being prepared
                                </small>
                            </div>
                        </div>
                        <div class="stats-icon">
                            <i data-lucide="edit-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card admins text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?= array_sum(array_column($activeAssignments, 'submissions')) ?></div>
                            <div>Submissions</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                    Total received
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
                            <div class="stat-number"><?= count($gradedAssignments) ?></div>
                            <div>Graded</div>
                            <div class="mt-1">
                                <small class="text-white-50">
                                    <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                    Completed
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

        <!-- Filter Section -->
        <div class="filter-section mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i data-lucide="search" class="search-icon"></i>
                        </span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search assignments...">
                        <button class="btn btn-outline-secondary" id="searchClearBtn" style="display: none;">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="subjectFilter">
                        <option value="all">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= htmlspecialchars($subject['SubjectCode'] ?? '') ?>">
                                <?= htmlspecialchars($subject['SubjectName']) ?>
                                <?php if (!empty($subject['SubjectCode'])): ?>
                                    (<?= htmlspecialchars($subject['SubjectCode']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="graded">Graded</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Assignment Tabs -->
        <div class="assignments-tabs mb-4">
            <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-assignments"
                        type="button" role="tab" aria-controls="active-assignments" aria-selected="true">
                        <i data-lucide="clipboard-check"></i>
                        Active
                        <span class="badge bg-primary ms-2"><?= count($activeAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft-assignments"
                        type="button" role="tab" aria-controls="draft-assignments" aria-selected="false">
                        <i data-lucide="edit-3"></i>
                        Drafts
                        <span class="badge bg-warning ms-2"><?= count($draftAssignments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="submissions-tab" data-bs-toggle="tab" data-bs-target="#student-submissions"
                        type="button" role="tab" aria-controls="student-submissions" aria-selected="false">
                        <i data-lucide="users"></i>
                        Student Submissions
                        <span class="badge bg-info ms-2" id="submissionsCount">0</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="assignmentTabContent">
            <!-- Active Assignments Tab -->
            <div class="tab-pane fade show active" id="active-assignments" role="tabpanel" aria-labelledby="active-tab">
                <?php if (empty($activeAssignments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <i data-lucide="clipboard-check" class="empty-state-icon"></i>
                            <h3>No Active Assignments</h3>
                            <p>Create your first assignment to get started with managing student work.</p>
                            <button class="btn btn-primary" onclick="createAssignment()">
                                <i data-lucide="plus"></i>
                                Create Assignment
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Active Assignments</h4>
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="createDraft()">
                                <i data-lucide="edit-3"></i>
                                Create Draft
                            </button>
                            <button class="btn btn-primary" onclick="createAssignment()">
                                <i data-lucide="plus"></i>
                                Create Assignment
                            </button>
                        </div>
                    </div>
                    <!-- Assignment Cards Container (Minimal) -->
                    <div class="assignments-container card p-3 mb-4" style="background: var(--card-light); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid var(--border-color);">
                        <div class="assignments-grid">
                            <?php foreach ($activeAssignments as $assignment): ?>
                                <div class="assignment-card" data-status="active" data-subject="<?= htmlspecialchars($assignment['SubjectCode'] ?? '') ?>">
                                    <div class="assignment-header">
                                        <span class="subject-code"><?= htmlspecialchars($assignment['SubjectCode'] ?? '') ?></span>
                                        <span class="points"><?= htmlspecialchars($assignment['MaxPoints'] ?? '') ?> pts</span>
                                    </div>
                                    <h5 class="assignment-title"><?= htmlspecialchars($assignment['Title'] ?? '') ?></h5>
                                    <p class="assignment-description"><?= htmlspecialchars(substr($assignment['Description'] ?? '', 0, 100)) ?>...</p>
                                    <div class="assignment-analytics">
                                        <div class="analytics-grid">
                                            <div class="analytic-item">
                                                <i data-lucide="users" class="analytic-icon"></i>
                                                <div class="analytic-content">
                                                    <span class="analytic-number"><?= $assignment['total_students'] ?? 0 ?></span>
                                                    <span class="analytic-label">Total Students</span>
                                                </div>
                                            </div>
                                            <div class="analytic-item">
                                                <i data-lucide="check-circle" class="analytic-icon submitted"></i>
                                                <div class="analytic-content">
                                                    <span class="analytic-number"><?= $assignment['submissions'] ?? 0 ?></span>
                                                    <span class="analytic-label">Submitted</span>
                                                </div>
                                            </div>
                                            <div class="analytic-item">
                                                <i data-lucide="eye" class="analytic-icon viewed"></i>
                                                <div class="analytic-content">
                                                    <span class="analytic-number"><?= $assignment['views'] ?? 0 ?></span>
                                                    <span class="analytic-label">Viewed</span>
                                                </div>
                                            </div>
                                            <div class="analytic-item">
                                                <i data-lucide="clock" class="analytic-icon pending"></i>
                                                <div class="analytic-content">
                                                    <span class="analytic-number"><?= $assignment['pending'] ?? 0 ?></span>
                                                    <span class="analytic-label">Pending</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="progress mt-3">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                style="width: <?= getSubmissionProgress($assignment['submissions'] ?? 0, $assignment['total_students'] ?? 0) ?>%"
                                                aria-valuenow="<?= getSubmissionProgress($assignment['submissions'] ?? 0, $assignment['total_students'] ?? 0) ?>"
                                                aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted mt-1">
                                            <?= getSubmissionProgress($assignment['submissions'] ?? 0, $assignment['total_students'] ?? 0) ?>% submitted
                                        </small>
                                    </div>
                                    <div class="assignment-footer">
                                        <small class="text-muted">Due: <?= date('M j, Y', strtotime($assignment['DueDate'] ?? 'now')) ?> (<?= getTimeRemaining($assignment['DueDate']) ?>)</small>
                                        <div class="assignment-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewAssignment(<?= $assignment['AssignmentID'] ?>)">
                                                <i data-lucide="eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="viewAssignmentAnalytics(<?= $assignment['AssignmentID'] ?>)">
                                                <i data-lucide="bar-chart-3"></i> Analytics
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="editAssignment(<?= $assignment['AssignmentID'] ?>)">
                                                <i data-lucide="edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="viewSubmissions(<?= $assignment['AssignmentID'] ?>)">
                                                <i data-lucide="users"></i> Submissions
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Draft Assignments Tab -->
            <div class="tab-pane fade" id="draft-assignments" role="tabpanel" aria-labelledby="draft-tab">
                <?php if (empty($draftAssignments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <i data-lucide="edit-3" class="empty-state-icon"></i>
                            <h3>No Draft Assignments</h3>
                            <p>Your draft assignments will appear here when you create them.</p>
                            <button class="btn btn-primary" onclick="createDraft()">
                                <i data-lucide="plus"></i>
                                Create Draft
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Draft Assignments</h4>
                        <button class="btn btn-primary" onclick="createDraft()">
                            <i data-lucide="plus"></i>
                            Create Draft
                        </button>
                    </div>
                    <div class="assignments-grid">
                        <?php foreach ($draftAssignments as $assignment): ?>
                            <div class="assignment-card draft-card" data-status="draft" data-subject="<?= htmlspecialchars($assignment['SubjectCode'] ?? '') ?>">
                                <div class="assignment-header">
                                    <span class="subject-code"><?= htmlspecialchars($assignment['SubjectCode'] ?? '') ?></span>
                                    <span class="draft-badge">Draft</span>
                                </div>
                                <h5 class="assignment-title"><?= htmlspecialchars($assignment['Title'] ?? '') ?></h5>
                                <p class="assignment-description"><?= htmlspecialchars(substr($assignment['Description'] ?? '', 0, 100)) ?>...</p>
                                <div class="assignment-footer">
                                    <small class="text-muted">Created: <?= date('M j, Y', strtotime($assignment['CreatedAt'] ?? 'now')) ?></small>
                                    <div class="assignment-actions">
                                        <button class="btn btn-sm btn-primary" onclick="editAssignment(<?= $assignment['AssignmentID'] ?>)">
                                            <i data-lucide="edit"></i>
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="publishAssignment(<?= $assignment['AssignmentID'] ?>)">
                                            <i data-lucide="send"></i>
                                            Publish
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Student Submissions Tab -->
            <div class="tab-pane fade" id="student-submissions" role="tabpanel" aria-labelledby="submissions-tab">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Student Submissions</h4>
                    <div class="d-flex align-items-center gap-3">
                        <div class="assignment-selector">
                            <label for="assignmentSelect" class="form-label mb-0 me-2">Assignment:</label>
                            <select class="form-select" id="assignmentSelect" onchange="loadStudentSubmissions()">
                                <option value="">Select an assignment</option>
                                <?php foreach (array_merge($activeAssignments, $draftAssignments, $gradedAssignments) as $assignment): ?>
                                    <option value="<?= $assignment['AssignmentID'] ?>">
                                        <?= htmlspecialchars($assignment['Title'] ?? '') ?>
                                        <?php if (!empty($assignment['SubjectCode'])): ?>
                                            (<?= htmlspecialchars($assignment['SubjectCode']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-success" id="exportSubmissionsBtn" onclick="exportSubmissions()" disabled>
                            <i data-lucide="download"></i>
                            Export to Excel
                        </button>
                    </div>
                </div>

                <div class="submissions-summary mb-4" id="submissionsSummary" style="display: none;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="summary-card total">
                                <div class="summary-icon">
                                    <i data-lucide="users"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-number" id="totalStudents">0</span>
                                    <span class="summary-label">Total Students</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card submitted">
                                <div class="summary-icon">
                                    <i data-lucide="check-circle"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-number" id="submittedCount">0</span>
                                    <span class="summary-label">Submitted</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card viewed">
                                <div class="summary-icon">
                                    <i data-lucide="eye"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-number" id="viewedCount">0</span>
                                    <span class="summary-label">Viewed</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card pending">
                                <div class="summary-icon">
                                    <i data-lucide="clock"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-number" id="pendingCount">0</span>
                                    <span class="summary-label">Pending</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="submissions-table-container" id="submissionsTableContainer" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover submissions-table" id="submissionsTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Grade</th>
                                    <th>File</th>
                                    <th>Views</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="submissionsTableBody"></tbody>
                        </table>
                    </div>
                    <div class="loading-state text-center py-5" id="submissionsLoading" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading student submissions...</p>
                    </div>
                    <div class="empty-submissions text-center py-5" id="emptySubmissions">
                        <i data-lucide="inbox" class="empty-state-icon"></i>
                        <h5>No Data Available</h5>
                        <p class="text-muted">Select an assignment to view student submissions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignment Creation Form -->
        <div class="assignment-form-section" id="assignmentForm" style="display: none;">
            <div class="form-header">
                <h3 class="form-title">
                    <i data-lucide="plus-circle"></i>
                    <span id="formTitle">Create New Assignment</span>
                </h3>
                <button type="button" class="form-close-btn" onclick="closeAssignmentForm()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="assignmentCreateForm" enctype="multipart/form-data" method="post">
                <input type="hidden" id="assignmentStatus" name="status" value="draft">
                <input type="hidden" id="editingAssignmentId" name="assignment_id" value="">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group mb-3">
                            <label for="assignmentTitle" class="form-label">
                                Assignment Title <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" id="assignmentTitle" name="title"
                                placeholder="Enter assignment title" required maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="assignmentDescription" class="form-label">
                                Description
                            </label>
                            <textarea class="form-control" id="assignmentDescription" name="description"
                                rows="4" placeholder="Describe the assignment objectives and requirements"></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label for="assignmentInstructions" class="form-label">
                                Detailed Instructions
                            </label>
                            <textarea class="form-control" id="assignmentInstructions" name="instructions"
                                rows="6" placeholder="Provide detailed instructions for the assignment"></textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label for="assignmentSubject" class="form-label">
                                Subject <span class="required">*</span>
                            </label>
                            <select class="form-select" id="assignmentSubject" name="subject_id" required>
                                <option value="">Select a subject</option>
                                <?php foreach (
                                    $subjects as $subject
                                ): ?>
                                    <option value="<?= htmlspecialchars($subject['SubjectID']) ?>">
                                        <?= htmlspecialchars($subject['SubjectName']) ?>
                                        <?php if (!empty($subject['SubjectCode'])): ?>
                                            (<?= htmlspecialchars($subject['SubjectCode']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="assignmentDueDate" class="form-label">
                                Due Date <span class="required">*</span>
                            </label>
                            <input type="datetime-local" class="form-control" id="assignmentDueDate" name="due_date" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="assignmentPoints" class="form-label">
                                Maximum Points
                            </label>
                            <input type="number" class="form-control" id="assignmentPoints" name="max_points"
                                value="100" min="1" max="1000">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="submissionType" class="form-label">
                                Submission Type
                            </label>
                            <select class="form-select" id="submissionType" name="submission_type">
                                <option value="both">Both (Text & File)</option>
                                <option value="text">Text Only</option>
                                <option value="file">File Only</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Allow Late Submissions</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowLateSubmissions" name="allow_late_submissions" value="1">
                                <label class="form-check-label" for="allowLateSubmissions">
                                    Allow submissions after due date
                                </label>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="assignmentNotes" class="form-label">
                                Grading Rubric
                            </label>
                            <textarea class="form-control" id="assignmentNotes" name="grading_rubric"
                                rows="4" placeholder="Enter grading rubric or additional notes"></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label for="assignmentFile" class="form-label">
                                Attachment (PDF, DOC, DOCX, max 10MB)
                            </label>
                            <input type="file" id="assignmentFile" name="assignment_file" accept=".pdf,.doc,.docx" required>
                        </div>
                    </div>
                </div>
                <div class="form-actions mt-4">
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="closeAssignmentForm()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-outline-primary me-2" id="saveDraftBtn" onclick="saveDraft()">
                        Save as Draft
                    </button>
                    <button type="submit" class="btn btn-primary" id="publishAssignmentBtn">
                        <span id="submitButtonText">Publish Assignment</span>
                    </button>
                </div>
                <div id="assignmentFormErrors" class="text-danger mt-2"></div>
            </form>
        </div>

        <script>
            // Refresh functionality
            function refreshData() {
                location.reload();
            }
        </script>
</body>

</html>